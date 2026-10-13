<?php
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location:../login.php");
    exit();
}
include('../../dbcon.php');

// Check user permissions
$user_id = $_SESSION['user_session'];
$perm_sql = "SELECT can_manage_controlled FROM user_permissions WHERE user_id = ?";
$perm_stmt = mysqli_prepare($con, $perm_sql);
mysqli_stmt_bind_param($perm_stmt, "i", $user_id);
mysqli_stmt_execute($perm_stmt);
$perm_result = mysqli_stmt_get_result($perm_stmt);
$permissions = mysqli_fetch_assoc($perm_result);
$can_manage_controlled = $permissions['can_manage_controlled'] ?? 0;

$success_count = 0;
$error_count = 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $file['tmp_name'];
        $file_type = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (strtolower($file_type) === 'csv') {
            // Process CSV file
            if (($handle = fopen($file_tmp, "r")) !== FALSE) {
                $row = 0;
                $batch_id = uniqid();
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $row++;
                    
                    // Skip header row
                    if ($row === 1) continue;
                    
                    // Skip empty rows
                    if (empty($data[0]) || empty(trim($data[1]))) continue;
                    
                    try {
                        // Map CSV columns (adjust based on your CSV structure)
                        $product_code = mysqli_real_escape_string($con, trim($data[0]));
                        $medicine_name = mysqli_real_escape_string($con, trim($data[1]));
                        $category = mysqli_real_escape_string($con, trim($data[2]));
                        $manufacturer = mysqli_real_escape_string($con, trim($data[3]));
                        $quantity = intval($data[4]);
                        $expire_date = mysqli_real_escape_string($con, trim($data[5]));
                        $company = mysqli_real_escape_string($con, trim($data[6]));
                        $actual_price = floatval($data[7]);
                        $selling_price = floatval($data[8]);
                        $supplier_id = !empty($data[9]) ? intval($data[9]) : NULL;
                        $schedule = !empty($data[10]) ? mysqli_real_escape_string($con, trim($data[10])) : NULL;
                        
                        // Validate required fields
                        if (empty($product_code) || empty($medicine_name) || empty($category)) {
                            $errors[] = "Row $row: Missing required fields (Product Code, Medicine Name, Category)";
                            $error_count++;
                            continue;
                        }
                        
                        if ($quantity <= 0) {
                            $errors[] = "Row $row: Quantity must be greater than 0";
                            $error_count++;
                            continue;
                        }
                        
                        if ($actual_price <= 0 || $selling_price <= 0) {
                            $errors[] = "Row $row: Prices must be greater than 0";
                            $error_count++;
                            continue;
                        }
                        
                        // Validate expiry date
                        if (!strtotime($expire_date)) {
                            $errors[] = "Row $row: Invalid expiry date format";
                            $error_count++;
                            continue;
                        }
                        
                        // Check if product already exists
                        $check_sql = "SELECT id FROM stock WHERE product_code = ? AND medicine_name = ?";
                        $check_stmt = mysqli_prepare($con, $check_sql);
                        mysqli_stmt_bind_param($check_stmt, "ss", $product_code, $medicine_name);
                        mysqli_stmt_execute($check_stmt);
                        $existing = mysqli_stmt_get_result($check_stmt)->fetch_assoc();
                        
                        if ($existing) {
                            // Update existing product
                            $update_sql = "UPDATE stock SET 
                                          category = ?, 
                                          manufacturer = ?, 
                                          quantity = quantity + ?, 
                                          remain_quantity = remain_quantity + ?,
                                          expire_date = ?,
                                          company = ?,
                                          actual_price = ?,
                                          selling_price = ?,
                                          supplier_id = ?,
                                          status = 'Available'
                                          WHERE id = ?";
                            $update_stmt = mysqli_prepare($con, $update_sql);
                            mysqli_stmt_bind_param($update_stmt, "ssiissddii", 
                                $category, $manufacturer, $quantity, $quantity, 
                                $expire_date, $company, $actual_price, $selling_price, 
                                $supplier_id, $existing['id']
                            );
                            
                            if (mysqli_stmt_execute($update_stmt)) {
                                $success_count++;
                                
                                // Update controlled substances if applicable
                                if ($schedule && $can_manage_controlled) {
                                    $cs_sql = "INSERT INTO controlled_substances (product_id, schedule, created_at) 
                                              VALUES (?, ?, NOW()) 
                                              ON DUPLICATE KEY UPDATE schedule = ?";
                                    $cs_stmt = mysqli_prepare($con, $cs_sql);
                                    mysqli_stmt_bind_param($cs_stmt, "iss", $existing['id'], $schedule, $schedule);
                                    mysqli_stmt_execute($cs_stmt);
                                }
                            } else {
                                $errors[] = "Row $row: Failed to update existing product - " . mysqli_error($con);
                                $error_count++;
                            }
                        } else {
                            // Insert new product
                            $insert_sql = "INSERT INTO stock 
                                          (product_code, medicine_name, category, manufacturer, 
                                           quantity, used_quantity, remain_quantity, expire_date, 
                                           company, actual_price, selling_price, supplier_id, status) 
                                          VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, 'Available')";
                            $insert_stmt = mysqli_prepare($con, $insert_sql);
                            mysqli_stmt_bind_param($insert_stmt, "ssssiissddi", 
                                $product_code, $medicine_name, $category, $manufacturer,
                                $quantity, $quantity, $expire_date, $company,
                                $actual_price, $selling_price, $supplier_id
                            );
                            
                            if (mysqli_stmt_execute($insert_stmt)) {
                                $new_product_id = mysqli_insert_id($con);
                                $success_count++;
                                
                                // Add to controlled substances if applicable
                                if ($schedule && $can_manage_controlled) {
                                    $cs_sql = "INSERT INTO controlled_substances (product_id, schedule, created_at) 
                                              VALUES (?, ?, NOW())";
                                    $cs_stmt = mysqli_prepare($con, $cs_sql);
                                    mysqli_stmt_bind_param($cs_stmt, "is", $new_product_id, $schedule);
                                    mysqli_stmt_execute($cs_stmt);
                                }
                                
                                // Log the import
                                $log_sql = "INSERT INTO logs (user_id, username, level, action, details, ip_address) 
                                           VALUES (?, ?, 'INFO', 'DRUG_IMPORTED', ?, ?)";
                                $log_stmt = mysqli_prepare($con, $log_sql);
                                $log_details = "Imported product: $medicine_name (Batch: $batch_id)";
                                $ip_address = $_SERVER['REMOTE_ADDR'];
                                mysqli_stmt_bind_param($log_stmt, "isss", $user_id, $_SESSION['username'], $log_details, $ip_address);
                                mysqli_stmt_execute($log_stmt);
                                
                            } else {
                                $errors[] = "Row $row: Failed to insert new product - " . mysqli_error($con);
                                $error_count++;
                            }
                        }
                        
                    } catch (Exception $e) {
                        $errors[] = "Row $row: Processing error - " . $e->getMessage();
                        $error_count++;
                    }
                }
                fclose($handle);
            }
        } else {
            $errors[] = "Invalid file type. Please upload a CSV file.";
        }
    } else {
        $errors[] = "File upload error: " . $file['error'];
    }
}

// Get suppliers for dropdown
$suppliers = [];
$sup_sql = "SELECT id, name FROM suppliers ORDER BY name";
$sup_result = mysqli_query($con, $sup_sql);
while ($sup = mysqli_fetch_assoc($sup_result)) {
    $suppliers[$sup['id']] = $sup['name'];
}

// Get categories
$categories = [];
$cat_sql = "SELECT id, name FROM drug_categories ORDER BY name";
$cat_result = mysqli_query($con, $cat_sql);
while ($cat = mysqli_fetch_assoc($cat_result)) {
    $categories[$cat['id']] = $cat['name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Import Drugs - Pharmacy Management</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    
    <style>
        body { padding: 20px; font-family: Arial, sans-serif; }
        .upload-area { 
            border: 2px dashed #007bff; 
            border-radius: 10px; 
            padding: 40px; 
            text-align: center; 
            margin: 20px 0; 
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        .upload-area:hover { background-color: #e9ecef; }
        .upload-area.dragover { border-color: #28a745; background-color: #d4edda; }
        .template-download { margin: 20px 0; }
        .instructions { background-color: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .error-list { max-height: 200px; overflow-y: auto; }
    </style>
</head>
<body>
<?php include '../header.php'; ?>
<br><br><br>

<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4><i class="fas fa-file-import"></i> Import Drugs</h4>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($success_count) || isset($error_count)): ?>
                        <div class="alert alert-info">
                            <h5>Import Results:</h5>
                            <p><strong>Successfully processed:</strong> <?= $success_count ?> items</p>
                            <p><strong>Errors:</strong> <?= $error_count ?> items</p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-warning">
                            <h5>Import Errors:</h5>
                            <div class="error-list">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Download Template -->
                    <div class="template-download">
                        <h5>Download CSV Template</h5>
                        <p>Use this template to ensure proper formatting:</p>
                        <a href="import-export/download_template.php" class="btn btn-outline-success">
                            <i class="fas fa-download"></i> Download Template
                        </a>
                    </div>

                    <!-- Instructions -->
                    <div class="instructions">
                        <h5>CSV Format Instructions:</h5>
                        <p><strong>Required Columns:</strong></p>
                        <ol>
                            <li><strong>Product Code</strong> (Unique identifier)</li>
                            <li><strong>Medicine Name</strong></li>
                            <li><strong>Category</strong></li>
                            <li><strong>Manufacturer</strong></li>
                            <li><strong>Quantity</strong> (Must be positive integer)</li>
                            <li><strong>Expiry Date</strong> (YYYY-MM-DD format)</li>
                            <li><strong>Shelf Location/Company</strong></li>
                            <li><strong>Cost Price</strong> (Must be positive number)</li>
                            <li><strong>Selling Price</strong> (Must be positive number)</li>
                            <li><strong>Supplier ID</strong> (Optional - must exist in suppliers table)</li>
                            <li><strong>Controlled Substance Schedule</strong> (Optional - I, II, III, IV, V)</li>
                        </ol>
                        <p class="text-muted"><small>Note: Existing products with matching Product Code and Medicine Name will be updated (quantities added).</small></p>
                    </div>

                    <!-- Upload Form -->
                    <form method="POST" enctype="multipart/form-data" id="importForm">
                        <div class="upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                            <h5>Drag & Drop CSV File Here</h5>
                            <p class="text-muted">or click to browse</p>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv" style="display: none;" required>
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('csv_file').click()">
                                <i class="fas fa-folder-open"></i> Choose File
                            </button>
                            <div id="fileInfo" class="mt-2"></div>
                        </div>

                        <div class="form-group">
                            <label for="import_mode">Import Mode:</label>
                            <select class="form-control" id="import_mode" name="import_mode">
                                <option value="add_update">Add New & Update Existing</option>
                                <option value="add_only">Add New Only (Skip Existing)</option>
                                <option value="update_only">Update Existing Only</option>
                            </select>
                        </div>

                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="skip_errors" name="skip_errors" checked>
                            <label class="form-check-label" for="skip_errors">Skip rows with errors and continue</label>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-upload"></i> Start Import
                            </button>
                            <a href="../view.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-arrow-left"></i> Back to Inventory
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Drag and drop functionality
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('csv_file');
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        uploadArea.classList.add('dragover');
    }
    
    function unhighlight() {
        uploadArea.classList.remove('dragover');
    }
    
    uploadArea.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        updateFileInfo(files[0]);
    }
    
    fileInput.addEventListener('change', function() {
        updateFileInfo(this.files[0]);
    });
    
    function updateFileInfo(file) {
        const fileInfo = document.getElementById('fileInfo');
        if (file) {
            fileInfo.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-file-csv"></i> 
                    <strong>${file.name}</strong> 
                    (${(file.size / 1024).toFixed(2)} KB)
                </div>
            `;
        } else {
            fileInfo.innerHTML = '';
        }
    }
    
    // Form submission confirmation
    $('#importForm').on('submit', function() {
        const file = fileInput.files[0];
        if (!file) {
            alert('Please select a CSV file to import.');
            return false;
        }
        
        return confirm('Are you sure you want to import this CSV file? This may update existing inventory data.');
    });
});
</script>

<br><br><br>
<?php include '../footer.php'; ?>
</body>
</html>