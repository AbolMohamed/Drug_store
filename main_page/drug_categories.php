<?php
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location: login.php");
    exit();
}

include('../dbcon.php');

// Initialize variables
$categories = [];
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_category':
                    $name = mysqli_real_escape_string($con, trim($_POST['name']));
                    $description = mysqli_real_escape_string($con, trim($_POST['description']));
                    
                    // Check if category already exists
                    $check_sql = "SELECT id FROM drug_categories WHERE name = ?";
                    $check_stmt = mysqli_prepare($con, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, 's', $name);
                    mysqli_stmt_execute($check_stmt);
                    mysqli_stmt_store_result($check_stmt);
                    
                    if (mysqli_stmt_num_rows($check_stmt) > 0) {
                        $error = "Category '$name' already exists!";
                    } else {
                        $sql = "INSERT INTO drug_categories (name, description) VALUES (?, ?)";
                        $stmt = mysqli_prepare($con, $sql);
                        mysqli_stmt_bind_param($stmt, 'ss', $name, $description);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "Category added successfully!";
                            
                            // Log the action
                            $user_id = $_SESSION['user_id'] ?? 0;
                            $username = $_SESSION['username'] ?? 'Unknown';
                            $log_sql = "INSERT INTO logs (user_id, username, level, action, details, ip_address) 
                                       VALUES (?, ?, 'INFO', 'CATEGORY_ADDED', ?, ?)";
                            $log_stmt = mysqli_prepare($con, $log_sql);
                            $log_details = "Added category: $name";
                            $ip_address = $_SERVER['REMOTE_ADDR'];
                            mysqli_stmt_bind_param($log_stmt, 'isss', $user_id, $username, $log_details, $ip_address);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                        } else {
                            $error = "Failed to add category: " . mysqli_error($con);
                        }
                        mysqli_stmt_close($stmt);
                    }
                    mysqli_stmt_close($check_stmt);
                    break;
                    
                case 'update_category':
                    $id = (int)$_POST['id'];
                    $name = mysqli_real_escape_string($con, trim($_POST['name']));
                    $description = mysqli_real_escape_string($con, trim($_POST['description']));
                    
                    // Check if category name already exists (excluding current category)
                    $check_sql = "SELECT id FROM drug_categories WHERE name = ? AND id != ?";
                    $check_stmt = mysqli_prepare($con, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, 'si', $name, $id);
                    mysqli_stmt_execute($check_stmt);
                    mysqli_stmt_store_result($check_stmt);
                    
                    if (mysqli_stmt_num_rows($check_stmt) > 0) {
                        $error = "Category '$name' already exists!";
                    } else {
                        $sql = "UPDATE drug_categories SET name = ?, description = ? WHERE id = ?";
                        $stmt = mysqli_prepare($con, $sql);
                        mysqli_stmt_bind_param($stmt, 'ssi', $name, $description, $id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "Category updated successfully!";
                            
                            // Log the action
                            $user_id = $_SESSION['user_id'] ?? 0;
                            $username = $_SESSION['username'] ?? 'Unknown';
                            $log_sql = "INSERT INTO logs (user_id, username, level, action, details, ip_address) 
                                       VALUES (?, ?, 'INFO', 'CATEGORY_UPDATED', ?, ?)";
                            $log_stmt = mysqli_prepare($con, $log_sql);
                            $log_details = "Updated category: $name (ID: $id)";
                            $ip_address = $_SERVER['REMOTE_ADDR'];
                            mysqli_stmt_bind_param($log_stmt, 'isss', $user_id, $username, $log_details, $ip_address);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                        } else {
                            $error = "Failed to update category: " . mysqli_error($con);
                        }
                        mysqli_stmt_close($stmt);
                    }
                    mysqli_stmt_close($check_stmt);
                    break;
                    
                case 'delete_category':
                    $id = (int)$_POST['id'];
                    $name = mysqli_real_escape_string($con, trim($_POST['name']));
                    
                    // Check if category is being used in stock
                    $check_sql = "SELECT COUNT(*) as product_count FROM stock WHERE category = ?";
                    $check_stmt = mysqli_prepare($con, $check_sql);
                    mysqli_stmt_bind_param($check_stmt, 's', $name);
                    mysqli_stmt_execute($check_stmt);
                    $result = mysqli_stmt_get_result($check_stmt);
                    $row = mysqli_fetch_assoc($result);
                    $product_count = $row['product_count'];
                    mysqli_stmt_close($check_stmt);
                    
                    if ($product_count > 0) {
                        $error = "Cannot delete category '$name' because it is used by $product_count product(s). Please reassign products first.";
                    } else {
                        $sql = "DELETE FROM drug_categories WHERE id = ?";
                        $stmt = mysqli_prepare($con, $sql);
                        mysqli_stmt_bind_param($stmt, 'i', $id);
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $success = "Category deleted successfully!";
                            
                            // Log the action
                            $user_id = $_SESSION['user_id'] ?? 0;
                            $username = $_SESSION['username'] ?? 'Unknown';
                            $log_sql = "INSERT INTO logs (user_id, username, level, action, details, ip_address) 
                                       VALUES (?, ?, 'WARNING', 'CATEGORY_DELETED', ?, ?)";
                            $log_stmt = mysqli_prepare($con, $log_sql);
                            $log_details = "Deleted category: $name (ID: $id)";
                            $ip_address = $_SERVER['REMOTE_ADDR'];
                            mysqli_stmt_bind_param($log_stmt, 'isss', $user_id, $username, $log_details, $ip_address);
                            mysqli_stmt_execute($log_stmt);
                            mysqli_stmt_close($log_stmt);
                        } else {
                            $error = "Failed to delete category: " . mysqli_error($con);
                        }
                        mysqli_stmt_close($stmt);
                    }
                    break;
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch categories from database
try {
    $category_sql = "SELECT 
        dc.*,
        COUNT(s.id) as product_count
    FROM drug_categories dc
    LEFT JOIN stock s ON dc.name = s.category
    GROUP BY dc.id
    ORDER BY dc.name ASC";
    
    $category_result = mysqli_query($con, $category_sql);
    if ($category_result) {
        $categories = mysqli_fetch_all($category_result, MYSQLI_ASSOC);
    }
    
    // Calculate statistics
    $total_categories = count($categories);
    $total_products = array_sum(array_column($categories, 'product_count'));
    $most_used_category = $categories ? max(array_column($categories, 'product_count')) : 0;
    
} catch (Exception $e) {
    $error = "Failed to fetch categories: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Management - Category Management</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css" rel="stylesheet">
    
    <style>
        body { 
            padding: 20px; 
            font-family: Arial, sans-serif; 
            background-color: #f5f7fa;
        }
        
        .summary-badges { 
            margin-bottom: 20px; 
            display: flex; 
            flex-wrap: wrap; 
            gap: 10px; 
        }
        
        .summary-badge { 
            font-weight: 600; 
            font-size: 1rem; 
            padding: 8px 12px; 
            border-radius: 5px; 
            color: white; 
        }
        
        .badge-total { background-color: #007bff; }
        .badge-products { background-color: #28a745; }
        .badge-most-used { background-color: #ffc107; color: #000; }
        .badge-average { background-color: #6c757d; }
        
        .filter-group { 
            display: flex; 
            flex-wrap: wrap; 
            align-items: center; 
            gap: 15px; 
            margin-bottom: 20px; 
        }
        
        .filter-group label { 
            margin-right: 8px; 
            font-weight: bold; 
        }
        
        .action-buttons { 
            display: flex; 
            gap: 5px; 
        }
        
        .category-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #007bff, #0056b3);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        
        .product-badge {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #007bff;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .filter-group {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-group label {
                margin-bottom: 5px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .section-header .btn {
                margin-top: 10px;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            
            .container {
                padding: 0;
            }
            
            .summary-badges {
                justify-content: center;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-value {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <br><br><br><br>
    
    <div class="container-fluid">
        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <div class="section-header d-flex justify-content-between align-items-center mb-3">
            <h2 class="section-title">
                <i class="fas fa-tags me-2"></i>Category Management
            </h2>
            <button class="btn btn-success quick-action-btn" id="add-category-btn">
                <i class="fas fa-plus"></i> Add New Category
            </button>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_categories; ?></div>
                <div class="stat-label">Total Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_products; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $most_used_category; ?></div>
                <div class="stat-label">Most Used Category</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_categories > 0 ? round($total_products / $total_categories, 1) : 0; ?></div>
                <div class="stat-label">Avg Products per Category</div>
            </div>
        </div>
    
        <!-- Search Bar -->
        <div class="filter-group">
            <div class="d-flex align-items-center">
                <label for="category-search">Search:</label>
                <input type="text" class="form-control" id="category-search" placeholder="Search categories..." style="width: auto;">
            </div>
            <button class="btn btn-primary" onclick="searchCategories()">
                <i class="fas fa-search"></i> Search
            </button>
        </div>
        
        <!-- Categories Table -->
        <div class="table-responsive">
            <table id="categories-table" class="table table-striped table-bordered nowrap" style="width:100%">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Products</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="categories-tbody">
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo $category['id']; ?></td>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="category-icon me-3">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                </div>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($category['description'] ?? 'No description'); ?></td>
                        <td>
                            <?php if ($category['product_count'] > 0): ?>
                                <span class="product-badge"><?php echo $category['product_count']; ?> products</span>
                            <?php else: ?>
                                <span class="text-muted">No products</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($category['created_at'])); ?></td>
                        <td class="action-buttons">
                            <button class="btn btn-sm btn-info edit-btn" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger delete-btn" onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>', <?php echo $category['product_count']; ?>)" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (empty($categories)): ?>
        <div class="empty-state" id="categories-empty">
            <i class="fas fa-tags"></i>
            <h3>No Categories Found</h3>
            <p>Add your first category to organize your products.</p>
            <button class="btn btn-primary" id="add-first-category">
                <i class="fas fa-plus"></i> Add Category
            </button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Category Modal -->
    <div class="modal fade" id="category-modal" tabindex="-1" role="dialog" aria-labelledby="category-modal-title" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="category-form" method="post">
                    <input type="hidden" id="category-id" name="id">
                    <input type="hidden" name="action" id="category-action" value="add_category">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="category-modal-title">Add New Category</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="category-name" class="form-label">Category Name *</label>
                            <input type="text" class="form-control" id="category-name" name="name" required 
                                   placeholder="Enter category name" maxlength="255">
                            <small class="form-text text-muted">Unique name for the category</small>
                        </div>
                        <div class="form-group">
                            <label for="category-description" class="form-label">Description</label>
                            <textarea class="form-control" id="category-description" name="description" 
                                      rows="4" placeholder="Enter category description (optional)"></textarea>
                            <small class="form-text text-muted">Brief description of what this category contains</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="save-category">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="delete-modal" tabindex="-1" role="dialog" aria-labelledby="delete-modal-title" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="delete-form" method="post">
                    <input type="hidden" id="delete-id" name="id">
                    <input type="hidden" id="delete-name" name="name">
                    <input type="hidden" name="action" id="delete-action" value="delete_category">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Deletion</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p id="delete-message">Are you sure you want to delete this category?</p>
                        <div id="delete-warning" class="alert alert-warning" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span id="warning-text"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="confirm-delete">Delete Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
    
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            var table = $('#categories-table').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                language: {
                    search: "Search categories:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ categories",
                    infoEmpty: "No categories available",
                    zeroRecords: "No matching categories found"
                },
                order: [[1, 'asc']] // Sort by category name
            });
            
            // Search functionality
            $('#category-search').on('keyup', function() {
                table.search(this.value).draw();
            });
        });

        // Modal Functions
        $('#add-category-btn').click(function() {
            openCategoryModal();
        });
        
        if ($('#add-first-category').length) {
            $('#add-first-category').click(function() {
                openCategoryModal();
            });
        }
        
        // Functions
        function openCategoryModal(category = null) {
            const modalTitle = $('#category-modal-title');
            const form = $('#category-form');
            
            if (category) {
                modalTitle.text('Edit Category');
                $('#category-id').val(category.id);
                $('#category-name').val(category.name);
                $('#category-description').val(category.description || '');
                $('#category-action').val('update_category');
            } else {
                modalTitle.text('Add New Category');
                form[0].reset();
                $('#category-id').val('');
                $('#category-action').val('add_category');
            }
            
            $('#category-modal').modal('show');
        }
        
        function openDeleteModal(id, name, productCount = 0) {
            const message = $('#delete-message');
            const warningDiv = $('#delete-warning');
            const warningText = $('#warning-text');
            
            $('#delete-id').val(id);
            $('#delete-name').val(name);
            
            if (productCount > 0) {
                message.text(`Cannot delete category "${name}"`);
                warningText.text(`This category is currently used by ${productCount} product(s). Please reassign these products to another category before deleting.`);
                warningDiv.show();
                $('#confirm-delete').prop('disabled', true);
                $('#confirm-delete').html('<i class="fas fa-ban"></i> Cannot Delete');
            } else {
                message.text(`Are you sure you want to delete the category "${name}"? This action cannot be undone.`);
                warningDiv.hide();
                $('#confirm-delete').prop('disabled', false);
                $('#confirm-delete').html('<i class="fas fa-trash"></i> Delete Category');
            }
            
            $('#delete-modal').modal('show');
        }
        
        function editCategory(category) {
            openCategoryModal(category);
        }
        
        function deleteCategory(id, name, productCount) {
            openDeleteModal(id, name, productCount);
        }
        
        function searchCategories() {
            const searchTerm = $('#category-search').val();
            const table = $('#categories-table').DataTable();
            table.search(searchTerm).draw();
        }
        
        // Form validation
        $('#category-form').on('submit', function(e) {
            const categoryName = $('#category-name').val().trim();
            if (!categoryName) {
                e.preventDefault();
                alert('Please enter a category name');
                $('#category-name').focus();
                return;
            }
            
            // Show loading state
            const saveBtn = $('#save-category');
            saveBtn.prop('disabled', true);
            saveBtn.html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            $('.alert').alert('close');
        }, 5000);
        
        // Quick category name validation
        $('#category-name').on('input', function(e) {
            const value = e.target.value;
            if (value.length > 255) {
                e.target.value = value.substring(0, 255);
            }
        });
        
        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Ctrl + N to add new category
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openCategoryModal();
            }
        });
    </script>
    <br><br><br><br>
    <?php include 'footer.php'; ?>
</body>
</html>