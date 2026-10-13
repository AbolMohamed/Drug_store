<?php
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location: login.php");
    exit();
}

include('../dbcon.php');

// Initialize variables
$customers = [];
$suppliers = [];
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_customer':
                    $name = mysqli_real_escape_string($con, trim($_POST['name']));
                    $email = mysqli_real_escape_string($con, trim($_POST['email']));
                    $phone = mysqli_real_escape_string($con, trim($_POST['phone']));
                    $address = mysqli_real_escape_string($con, trim($_POST['address']));
                    
                    $sql = "INSERT INTO customers (name, email, phone, address) VALUES (?, ?, ?, ?)";
                    $stmt = mysqli_prepare($con, $sql);
                    mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $phone, $address);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Customer added successfully!";
                    } else {
                        $error = "Failed to add customer: " . mysqli_error($con);
                    }
                    mysqli_stmt_close($stmt);
                    break;
                    
                case 'update_customer':
                    $id = (int)$_POST['id'];
                    $name = mysqli_real_escape_string($con, trim($_POST['name']));
                    $email = mysqli_real_escape_string($con, trim($_POST['email']));
                    $phone = mysqli_real_escape_string($con, trim($_POST['phone']));
                    $address = mysqli_real_escape_string($con, trim($_POST['address']));
                    
                    $sql = "UPDATE customers SET name=?, email=?, phone=?, address=? WHERE id=?";
                    $stmt = mysqli_prepare($con, $sql);
                    mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $phone, $address, $id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Customer updated successfully!";
                    } else {
                        $error = "Failed to update customer: " . mysqli_error($con);
                    }
                    mysqli_stmt_close($stmt);
                    break;
                    
                case 'delete_customer':
                    $id = (int)$_POST['id'];
                    $sql = "DELETE FROM customers WHERE id=?";
                    $stmt = mysqli_prepare($con, $sql);
                    mysqli_stmt_bind_param($stmt, 'i', $id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Customer deleted successfully!";
                    } else {
                        $error = "Failed to delete customer: " . mysqli_error($con);
                    }
                    mysqli_stmt_close($stmt);
                    break;
                    
                case 'add_supplier':
                    $name = mysqli_real_escape_string($con, trim($_POST['name']));
                    $contact_person = mysqli_real_escape_string($con, trim($_POST['contact_person']));
                    $phone = mysqli_real_escape_string($con, trim($_POST['phone']));
                    $email = mysqli_real_escape_string($con, trim($_POST['email']));
                    $address = mysqli_real_escape_string($con, trim($_POST['address']));
                    
                    $sql = "INSERT INTO suppliers (name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($con, $sql);
                    mysqli_stmt_bind_param($stmt, 'sssss', $name, $contact_person, $phone, $email, $address);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Supplier added successfully!";
                    } else {
                        $error = "Failed to add supplier: " . mysqli_error($con);
                    }
                    mysqli_stmt_close($stmt);
                    break;
                    
                case 'update_supplier':
                    $id = (int)$_POST['id'];
                    $name = mysqli_real_escape_string($con, trim($_POST['name']));
                    $contact_person = mysqli_real_escape_string($con, trim($_POST['contact_person']));
                    $phone = mysqli_real_escape_string($con, trim($_POST['phone']));
                    $email = mysqli_real_escape_string($con, trim($_POST['email']));
                    $address = mysqli_real_escape_string($con, trim($_POST['address']));
                    
                    $sql = "UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?";
                    $stmt = mysqli_prepare($con, $sql);
                    mysqli_stmt_bind_param($stmt, 'sssssi', $name, $contact_person, $phone, $email, $address, $id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Supplier updated successfully!";
                    } else {
                        $error = "Failed to update supplier: " . mysqli_error($con);
                    }
                    mysqli_stmt_close($stmt);
                    break;
                    
                case 'delete_supplier':
                    $id = (int)$_POST['id'];
                    $sql = "DELETE FROM suppliers WHERE id=?";
                    $stmt = mysqli_prepare($con, $sql);
                    mysqli_stmt_bind_param($stmt, 'i', $id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Supplier deleted successfully!";
                    } else {
                        $error = "Failed to delete supplier: " . mysqli_error($con);
                    }
                    mysqli_stmt_close($stmt);
                    break;
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch data from database
try {
    // Fetch customers with their statistics
    $customer_sql = "SELECT 
        c.*,
        COUNT(o.id) as total_orders,
        COALESCE(SUM(o.final_total), 0) as total_spent,
        MAX(o.order_date) as last_purchase
    FROM customers c
    LEFT JOIN orders o ON c.id = o.customer_id
    GROUP BY c.id
    ORDER BY c.name ASC";
    
    $customer_result = mysqli_query($con, $customer_sql);
    if ($customer_result) {
        $customers = mysqli_fetch_all($customer_result, MYSQLI_ASSOC);
    }
    
    // Fetch suppliers
    $supplier_sql = "SELECT 
        s.*,
        COUNT(st.id) as total_products
    FROM suppliers s
    LEFT JOIN stock st ON s.id = st.supplier_id
    GROUP BY s.id
    ORDER BY s.name ASC";
    
    $supplier_result = mysqli_query($con, $supplier_sql);
    if ($supplier_result) {
        $suppliers = mysqli_fetch_all($supplier_result, MYSQLI_ASSOC);
    }
    
    // Calculate statistics
    $total_customers = count($customers);
    $total_loyalty_points = array_sum(array_column($customers, 'loyalty_points'));
    $total_purchases = array_sum(array_column($customers, 'total_purchases'));
    $total_suppliers = count($suppliers);
    $supplier_products = array_sum(array_column($suppliers, 'total_products'));
    
} catch (Exception $e) {
    $error = "Failed to fetch data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Management - Customer & Supplier Management</title>
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
        .badge-loyalty { background-color: #28a745; }
        .badge-purchases { background-color: #ffc107; color: #000; }
        .badge-suppliers { background-color: #6c757d; }
        .badge-products { background-color: #17a2b8; }
        
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
        
        .loyalty-badge {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
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
        
        .tabs {
            display: flex;
            background-color: white;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            flex: 1;
            font-weight: 500;
        }
        
        .tab.active {
            background-color: #007bff;
            color: white;
        }
        
        .tab:hover:not(.active) {
            background-color: #f1f1f1;
        }
        
        .tab-content {
            display: none;
            background-color: white;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .tab-content.active {
            display: block;
        }
        
        .quick-action-btn { 
            transition: all 0.3s ease; 
        }
        
        .quick-action-btn:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 8px rgba(0,0,0,0.2); 
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
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                padding: 12px 15px;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            
            .container-fluid {
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
            
            .tab-content {
                padding: 15px;
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
        
        <h2>Customer & Supplier Management</h2>
        
        <div class="tabs">
            <div class="tab active" data-tab="customers">Customers</div>
            <div class="tab" data-tab="suppliers">Suppliers</div>
        </div>
        
        <!-- Customers Tab -->
        <div class="tab-content active" id="customers-tab">
            <!-- Action Buttons -->
            <div class="d-flex justify-content-between mb-3">
                <div>
                    <button class="btn btn-success quick-action-btn" id="add-customer-btn">
                        <i class="fas fa-plus-circle"></i> Add New Customer
                    </button>
                </div>
                <div>
                    <button class="btn btn-secondary quick-action-btn" id="export-customers-btn">
                        <i class="fas fa-file-export"></i> Export Data
                    </button>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value" id="total-customers"><?php echo $total_customers; ?></div>
                    <div class="stat-label">Total Customers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="total-loyalty-points"><?php echo $total_loyalty_points; ?></div>
                    <div class="stat-label">Total Loyalty Points</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="total-purchases">ETB <?php echo number_format($total_purchases, 2); ?></div>
                    <div class="stat-label">Total Purchases</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-group">
                <label for="customer-search">Search:</label>
                <input type="text" class="form-control" id="customer-search" placeholder="Search customers..." style="width: auto;">
                
                <label for="customer-loyalty-filter">Loyalty:</label>
                <select id="customer-loyalty-filter" class="form-control" style="width:auto;">
                    <option value="all">All Customers</option>
                    <option value="with-points">With Loyalty Points</option>
                    <option value="without-points">Without Loyalty Points</option>
                </select>
                
                <label for="customer-activity-filter">Activity:</label>
                <select id="customer-activity-filter" class="form-control" style="width:auto;">
                    <option value="all">All Activity</option>
                    <option value="active">Active (Recent Purchase)</option>
                    <option value="inactive">Inactive (No Recent Purchase)</option>
                </select>
            </div>
            
            <!-- Customers Table -->
            <div class="table-responsive">
                <table id="customers-table" class="table table-striped table-bordered nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Loyalty Points</th>
                            <th>Total Orders</th>
                            <th>Total Spent</th>
                            <th>Last Purchase</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customers-tbody">
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo $customer['id']; ?></td>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone'] ?? '-'); ?></td>
                            <td>
                                <?php if ($customer['loyalty_points'] > 0): ?>
                                    <span class="loyalty-badge"><?php echo $customer['loyalty_points']; ?> pts</span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo $customer['total_orders']; ?></td>
                            <td>ETB <?php echo number_format($customer['total_spent'] ?? 0, 2); ?></td>
                            <td>
                                <?php if ($customer['last_purchase']): ?>
                                    <?php echo date('M j, Y', strtotime($customer['last_purchase'])); ?>
                                <?php else: ?>
                                    Never
                                <?php endif; ?>
                            </td>
                            <td class="action-buttons">
                                <button class="btn btn-sm btn-info edit-btn" onclick="editCustomer(<?php echo htmlspecialchars(json_encode($customer)); ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo addslashes($customer['name']); ?>')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($customers)): ?>
            <div class="empty-state" id="customers-empty">
                <i class="fas fa-users"></i>
                <h3>No Customers Found</h3>
                <p>Add your first customer to get started.</p>
                <button class="btn btn-primary" id="add-first-customer">
                    <i class="fas fa-plus"></i> Add Customer
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Suppliers Tab -->
        <div class="tab-content" id="suppliers-tab">
            <!-- Action Buttons -->
            <div class="d-flex justify-content-between mb-3">
                <div>
                    <button class="btn btn-success quick-action-btn" id="add-supplier-btn">
                        <i class="fas fa-plus-circle"></i> Add New Supplier
                    </button>
                </div>
                <div>
                    <button class="btn btn-secondary quick-action-btn" id="export-suppliers-btn">
                        <i class="fas fa-file-export"></i> Export Data
                    </button>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value" id="total-suppliers"><?php echo $total_suppliers; ?></div>
                    <div class="stat-label">Total Suppliers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="supplier-products"><?php echo $supplier_products; ?></div>
                    <div class="stat-label">Products from Suppliers</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-group">
                <label for="supplier-search">Search:</label>
                <input type="text" class="form-control" id="supplier-search" placeholder="Search suppliers..." style="width: auto;">
                
                <label for="supplier-products-filter">Products:</label>
                <select id="supplier-products-filter" class="form-control" style="width:auto;">
                    <option value="all">All Suppliers</option>
                    <option value="with-products">With Products</option>
                    <option value="without-products">Without Products</option>
                </select>
            </div>
            
            <!-- Suppliers Table -->
            <div class="table-responsive">
                <table id="suppliers-table" class="table table-striped table-bordered nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Products</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="suppliers-tbody">
                        <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><?php echo $supplier['id']; ?></td>
                            <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['contact_person'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['phone'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($supplier['email'] ?? '-'); ?></td>
                            <td><?php echo $supplier['total_products']; ?></td>
                            <td class="action-buttons">
                                <button class="btn btn-sm btn-info edit-btn" onclick="editSupplier(<?php echo htmlspecialchars(json_encode($supplier)); ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger delete-btn" onclick="deleteSupplier(<?php echo $supplier['id']; ?>, '<?php echo addslashes($supplier['name']); ?>')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($suppliers)): ?>
            <div class="empty-state" id="suppliers-empty">
                <i class="fas fa-industry"></i>
                <h3>No Suppliers Found</h3>
                <p>Add your first supplier to get started.</p>
                <button class="btn btn-primary" id="add-first-supplier">
                    <i class="fas fa-plus"></i> Add Supplier
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Customer Modal -->
    <div class="modal fade" id="customer-modal" tabindex="-1" role="dialog" aria-labelledby="customer-modal-title" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="customer-form" method="post">
                    <input type="hidden" id="customer-id" name="id">
                    <input type="hidden" name="action" id="customer-action" value="add_customer">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="customer-modal-title">Add New Customer</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="customer-name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="customer-name" name="name" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="customer-email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="customer-email" name="email">
                            </div>
                            <div class="form-group">
                                <label for="customer-phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="customer-phone" name="phone">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="customer-address" class="form-label">Address</label>
                            <textarea class="form-control" id="customer-address" name="address" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="save-customer">Save Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Supplier Modal -->
    <div class="modal fade" id="supplier-modal" tabindex="-1" role="dialog" aria-labelledby="supplier-modal-title" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form id="supplier-form" method="post">
                    <input type="hidden" id="supplier-id" name="id">
                    <input type="hidden" name="action" id="supplier-action" value="add_supplier">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="supplier-modal-title">Add New Supplier</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="supplier-name" class="form-label">Supplier Name *</label>
                            <input type="text" class="form-control" id="supplier-name" name="name" required>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="supplier-contact" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="supplier-contact" name="contact_person">
                            </div>
                            <div class="form-group">
                                <label for="supplier-phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="supplier-phone" name="phone">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="supplier-email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="supplier-email" name="email">
                        </div>
                        <div class="form-group">
                            <label for="supplier-address" class="form-label">Address</label>
                            <textarea class="form-control" id="supplier-address" name="address" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="save-supplier">Save Supplier</button>
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
                    <input type="hidden" name="action" id="delete-action">
                    
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Deletion</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p id="delete-message">Are you sure you want to delete this item?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="confirm-delete">Delete</button>
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
            var customersTable = $('#customers-table').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                language: {
                    search: "Search customers:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ customers",
                    infoEmpty: "No customers available",
                    zeroRecords: "No matching customers found"
                },
                order: [[1, 'asc']] // Sort by name
            });
            
            var suppliersTable = $('#suppliers-table').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                language: {
                    search: "Search suppliers:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ suppliers",
                    infoEmpty: "No suppliers available",
                    zeroRecords: "No matching suppliers found"
                },
                order: [[1, 'asc']] // Sort by name
            });
            
            // Search functionality
            $('#customer-search').on('keyup', function() {
                customersTable.search(this.value).draw();
            });
            
            $('#supplier-search').on('keyup', function() {
                suppliersTable.search(this.value).draw();
            });
            
            // Filter functionality
            $('#customer-loyalty-filter, #customer-activity-filter').on('change', function() {
                applyCustomerFilters();
            });
            
            $('#supplier-products-filter').on('change', function() {
                applySupplierFilters();
            });
        });

        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.getAttribute('data-tab');
                
                // Update active tab
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                
                // Update active content
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });
        
        // Customer Modal Functions
        $('#add-customer-btn').click(function() {
            openCustomerModal();
        });
        
        if ($('#add-first-customer').length) {
            $('#add-first-customer').click(function() {
                openCustomerModal();
            });
        }
        
        // Supplier Modal Functions
        $('#add-supplier-btn').click(function() {
            openSupplierModal();
        });
        
        if ($('#add-first-supplier').length) {
            $('#add-first-supplier').click(function() {
                openSupplierModal();
            });
        }
        
        // Functions
        function openCustomerModal(customer = null) {
            const modalTitle = $('#customer-modal-title');
            const form = $('#customer-form');
            
            if (customer) {
                modalTitle.text('Edit Customer');
                $('#customer-id').val(customer.id);
                $('#customer-name').val(customer.name);
                $('#customer-email').val(customer.email || '');
                $('#customer-phone').val(customer.phone || '');
                $('#customer-address').val(customer.address || '');
                $('#customer-action').val('update_customer');
            } else {
                modalTitle.text('Add New Customer');
                form[0].reset();
                $('#customer-id').val('');
                $('#customer-action').val('add_customer');
            }
            
            $('#customer-modal').modal('show');
        }
        
        function openSupplierModal(supplier = null) {
            const modalTitle = $('#supplier-modal-title');
            const form = $('#supplier-form');
            
            if (supplier) {
                modalTitle.text('Edit Supplier');
                $('#supplier-id').val(supplier.id);
                $('#supplier-name').val(supplier.name);
                $('#supplier-contact').val(supplier.contact_person || '');
                $('#supplier-phone').val(supplier.phone || '');
                $('#supplier-email').val(supplier.email || '');
                $('#supplier-address').val(supplier.address || '');
                $('#supplier-action').val('update_supplier');
            } else {
                modalTitle.text('Add New Supplier');
                form[0].reset();
                $('#supplier-id').val('');
                $('#supplier-action').val('add_supplier');
            }
            
            $('#supplier-modal').modal('show');
        }
        
        function openDeleteModal(type, id, name) {
            const message = $('#delete-message');
            const actionField = $('#delete-action');
            const idField = $('#delete-id');
            
            if (type === 'customer') {
                message.text(`Are you sure you want to delete the customer "${name}"? This action cannot be undone.`);
                actionField.val('delete_customer');
            } else {
                message.text(`Are you sure you want to delete the supplier "${name}"? This action cannot be undone.`);
                actionField.val('delete_supplier');
            }
            
            idField.val = id;
            $('#delete-modal').modal('show');
        }
        
        function editCustomer(customer) {
            openCustomerModal(customer);
        }
        
        function editSupplier(supplier) {
            openSupplierModal(supplier);
        }
        
        function deleteCustomer(id, name) {
            openDeleteModal('customer', id, name);
        }
        
        function deleteSupplier(id, name) {
            openDeleteModal('supplier', id, name);
        }
        
        function applyCustomerFilters() {
            const loyaltyFilter = $('#customer-loyalty-filter').val();
            const activityFilter = $('#customer-activity-filter').val();
            const table = $('#customers-table').DataTable();
            
            // This is a simplified filter implementation
            // In a real application, you would implement more sophisticated filtering
            table.draw();
        }
        
        function applySupplierFilters() {
            const productsFilter = $('#supplier-products-filter').val();
            const table = $('#suppliers-table').DataTable();
            
            // This is a simplified filter implementation
            // In a real application, you would implement more sophisticated filtering
            table.draw();
        }
        
        // Export functionality
        $('#export-customers-btn').click(function() {
            alert('Export customers functionality would be implemented here');
        });
        
        $('#export-suppliers-btn').click(function() {
            alert('Export suppliers functionality would be implemented here');
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            $('.alert').alert('close');
        }, 5000);
    </script>
    <br><br><br><br>
    <?php include 'footer.php'; ?>
</body>
</html>