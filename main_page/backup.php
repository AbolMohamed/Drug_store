<?php
session_start();
require '../dbcon.php';

// Set backup directory (outside web root for security)
$backup_dir = __DIR__ . '/../../secure_backups/';

// Create backup directory if it doesn't exist
if(!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Process backup request
if(isset($_GET['backup'])) {
    // Include all tables from your database
    $tables_to_backup = ['cart', 'drug_categories', 'logs', 'orders', 'order_items', 
                        'pinned_products', 'sessions', 'stock', 'tbl_users'];
    
    // Perform the backup
    $backup_file = backupDatabaseTables($con, $tables_to_backup, $backup_dir);
    
    if($backup_file) {
        $_SESSION['message'] = "Database backup created successfully: " . basename($backup_file);
        $_SESSION['showAlert'] = "block";
        $_SESSION['alertClass'] = "alert-success";
    } else {
        $_SESSION['message'] = "Error creating database backup";
        $_SESSION['showAlert'] = "block";
        $_SESSION['alertClass'] = "alert-danger";
    }
    
    header("location: backup.php");
    exit();
}

// Process restore request
if(isset($_POST['restore'])) {
    if(isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] == UPLOAD_ERR_OK) {
        // Validate file type
        $file_info = pathinfo($_FILES['backup_file']['name']);
        if(strtolower($file_info['extension']) !== 'sql') {
            $_SESSION['message'] = "Invalid file type. Please upload a .sql file";
            $_SESSION['showAlert'] = "block";
            $_SESSION['alertClass'] = "alert-danger";
            header("location: backup.php");
            exit();
        }
        
        $restore_result = restoreDatabase($con, $_FILES['backup_file']['tmp_name']);
        
        if($restore_result) {
            $_SESSION['message'] = "Database restored successfully";
            $_SESSION['showAlert'] = "block";
            $_SESSION['alertClass'] = "alert-success";
        } else {
            $_SESSION['message'] = "Error restoring database";
            $_SESSION['showAlert'] = "block";
            $_SESSION['alertClass'] = "alert-danger";
        }
    } else {
        $_SESSION['message'] = "Please select a valid backup file";
        $_SESSION['showAlert'] = "block";
        $_SESSION['alertClass'] = "alert-danger";
    }
    
    header("location: backup.php");
    exit();
}

/**
 * Backup database tables
 */
function backupDatabaseTables($connection, $tables, $backup_dir) {
    try {
        $return = '';
        
        // Get all tables if '*' specified
        if($tables == '*') {
            $tables = array();
            $result = mysqli_query($connection, 'SHOW TABLES');
            while($row = mysqli_fetch_row($result)) {
                $tables[] = $row[0];
            }
        } else {
            $tables = is_array($tables) ? $tables : explode(',', $tables);
        }
        
        // Cycle through each table
        foreach($tables as $table) {
            $result = mysqli_query($connection, 'SELECT * FROM ' . $table);
            if(!$result) {
                error_log("Error reading table $table: " . mysqli_error($connection));
                continue;
            }
            
            $num_fields = mysqli_num_fields($result);
            
            $return .= 'DROP TABLE IF EXISTS ' . $table . ';';
            $row2 = mysqli_fetch_row(mysqli_query($connection, 'SHOW CREATE TABLE ' . $table));
            $return .= "\n\n" . $row2[1] . ";\n\n";
            
            // Insert data
            for($i = 0; $i < $num_fields; $i++) {
                mysqli_data_seek($result, 0); // Reset result pointer
                while($row = mysqli_fetch_row($result)) {
                    $return .= 'INSERT INTO ' . $table . ' VALUES(';
                    for($j = 0; $j < $num_fields; $j++) {
                        if(isset($row[$j])) {
                            $row[$j] = addslashes($row[$j]);
                            $row[$j] = preg_replace("/\n/", "\\n", $row[$j]);
                            $return .= '"' . $row[$j] . '"';
                        } else {
                            $return .= 'NULL';
                        }
                        if($j < ($num_fields - 1)) { 
                            $return .= ','; 
                        }
                    }
                    $return .= ");\n";
                }
            }
            $return .= "\n\n\n";
            mysqli_free_result($result);
        }
        
        // Save backup file
        $date = date("Y-m-d_H-i-s");
        $backup_file = $backup_dir . 'pharmacy_backup_' . $date . '.sql';
        
        $handle = fopen($backup_file, 'w+');
        if(!$handle) {
            error_log("Cannot create backup file: " . $backup_file);
            return false;
        }
        
        fwrite($handle, $return);
        fclose($handle);
        
        return $backup_file;
    } catch(Exception $e) {
        error_log("Backup failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Restore database from backup file
 */
function restoreDatabase($connection, $backup_file) {
    try {
        // Temporary variable, used to store current query
        $templine = '';
        
        // Read in entire file
        $lines = file($backup_file);
        if(!$lines) {
            error_log("Cannot read backup file: " . $backup_file);
            return false;
        }
        
        // Disable foreign key checks during restore
        mysqli_query($connection, 'SET FOREIGN_KEY_CHECKS=0');
        
        // Loop through each line
        foreach($lines as $line) {
            // Skip it if it's a comment
            if(substr($line, 0, 2) == '--' || trim($line) == '') {
                continue;
            }
            
            // Add this line to the current segment
            $templine .= $line;
            
            // If it has a semicolon at the end, it's the end of the query
            if(substr(trim($line), -1, 1) == ';') {
                // Perform the query
                if(!mysqli_query($connection, $templine)) {
                    error_log("Error performing query: " . $templine . " - " . mysqli_error($connection));
                    // Reset foreign key checks
                    mysqli_query($connection, 'SET FOREIGN_KEY_CHECKS=1');
                    return false;
                }
                
                // Reset temp variable to empty
                $templine = '';
            }
        }
        
        // Re-enable foreign key checks
        mysqli_query($connection, 'SET FOREIGN_KEY_CHECKS=1');
        
        return true;
    } catch(Exception $e) {
        error_log("Restore failed: " . $e->getMessage());
        // Ensure foreign key checks are re-enabled even on error
        mysqli_query($connection, 'SET FOREIGN_KEY_CHECKS=1');
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Pharmacy Management System - Database Backup</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- CSS Links -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../src/facebox.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .backup-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .backup-list {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
        }
        .backup-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .backup-item:last-child {
            border-bottom: none;
        }
        .file-size {
            font-size: 0.9em;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'header.php';?>
    <br>
    <br>
    <br>

    <div class="content-header">
        <h4 class="mb-0">&nbsp; &nbsp;<i class="fas fa-database"></i> Database Backup & Restore</h4>
    </div>
    <br>
    <br>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Alert Messages -->
                <div style="display:<?php if (isset($_SESSION['showAlert'])) {
                    echo $_SESSION['showAlert'];
                } else {
                    echo 'none';
                } unset($_SESSION['showAlert']); ?>" class="alert <?php echo isset($_SESSION['alertClass']) ? $_SESSION['alertClass'] : 'alert-success'; ?> alert-dismissible mt-3">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <strong><?php if (isset($_SESSION['message'])) {
                        echo $_SESSION['message'];
                    } unset($_SESSION['message']); ?></strong>
                </div>
                
                <div class="backup-container">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-cloud-download-alt"></i> Create Backup</h5>
                                </div>
                                <div class="card-body">
                                    <p>Create a complete backup of your pharmacy database including all tables, orders, products, and user data.</p>
                                    <a href="backup.php?backup=1" class="btn btn-success" onclick="return confirm('This will create a backup of the entire database. Continue?')">
                                        <i class="fas fa-download"></i> Backup Now
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-warning text-dark">
                                    <h5 class="mb-0"><i class="fas fa-cloud-upload-alt"></i> Restore Backup</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-danger"><strong>Warning:</strong> Restoring will overwrite all current data.</p>
                                    <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('WARNING: This will overwrite all current data. Are you sure you want to continue?')">
                                        <div class="form-group">
                                            <label for="backup_file">Select Backup File (.sql)</label>
                                            <input type="file" class="form-control-file" id="backup_file" name="backup_file" accept=".sql" required>
                                        </div>
                                        <button type="submit" name="restore" class="btn btn-warning">
                                            <i class="fas fa-upload"></i> Restore Database
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-history"></i> Available Backups</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $backup_files = glob($backup_dir . '*.sql');
                            if(count($backup_files) > 0): ?>
                                <div class="backup-list">
                                    <?php 
                                    rsort($backup_files); // Sort by newest first
                                    foreach($backup_files as $file):
                                        $file_name = basename($file);
                                        $file_size = filesize($file);
                                        $file_size_formatted = $file_size > 1024 * 1024 
                                            ? round($file_size / (1024 * 1024), 2) . ' MB'
                                            : round($file_size / 1024, 2) . ' KB';
                                        $file_date = date('F d, Y H:i:s', filemtime($file));
                                    ?>
                                        <div class="backup-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-file-alt mr-2"></i>
                                                    <strong><?php echo $file_name; ?></strong>
                                                </div>
                                                <span class="file-size"><?php echo $file_size_formatted; ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="text-muted"><?php echo $file_date; ?></small>
                                                <div>
                                                    <a href="download_backup.php?file=<?php echo urlencode($file_name); ?>" class="btn btn-sm btn-primary mr-2">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No backup files found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    
    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script src="../src/facebox.js"></script>
    
    <script>
    jQuery(document).ready(function($) {
        // Facebox popup forms
        $("a[id*=popup").facebox({
            loadingImage: '../src/img/loading.gif',
            closeImage: '../src/img/closelabel.png'
        });
        
        // Logout confirmation
        $(".logout").click(function(e) {
            e.preventDefault();
            if(confirm("Are you sure you want to logout?")) {
                window.location.href = '../logout.php';
            }
        });
     
    });
    </script>
</body>
<br>
<br>
<br>
<?php include 'footer.php';?>
</html>