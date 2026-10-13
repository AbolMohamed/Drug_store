<?php 
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location:../login.php");
    exit();
}
include('../dbcon.php'); 

// Get system settings
$settings_sql = "SELECT setting_key, setting_value FROM system_settings WHERE is_public = 1";
$settings_result = mysqli_query($con, $settings_sql);
$system_settings = [];
while ($setting = mysqli_fetch_assoc($settings_result)) {
    $system_settings[$setting['setting_key']] = $setting['setting_value'];
}

// Use settings for thresholds
$low_stock_threshold = $system_settings['low_stock_threshold'] ?? 10;
$expiry_warning_days = $system_settings['expiry_warning_days'] ?? 30;
$currency_symbol = $system_settings['currency_symbol'] ?? 'ETB';
$pharmacy_name = $system_settings['pharmacy_name'] ?? 'Drug Store';

// Get user info
$user_id = $_SESSION['user_session'];

// Check user permissions
$perm_sql = "SELECT can_manage_controlled, can_apply_discount, can_manage_credit, max_discount_percentage FROM user_permissions WHERE user_id = ?";
$perm_stmt = mysqli_prepare($con, $perm_sql);
mysqli_stmt_bind_param($perm_stmt, "i", $user_id);
mysqli_stmt_execute($perm_stmt);
$perm_result = mysqli_stmt_get_result($perm_stmt);
$permissions = mysqli_fetch_assoc($perm_result);
$can_manage_controlled = $permissions['can_manage_controlled'] ?? 0;
$can_apply_discount = $permissions['can_apply_discount'] ?? 0;
$can_manage_credit = $permissions['can_manage_credit'] ?? 0;
$max_discount = $permissions['max_discount_percentage'] ?? 0;

// Get notification counts
$notification_sql = "SELECT COUNT(*) as notification_count FROM notifications 
                    WHERE (user_id = ? OR user_id IS NULL) AND is_read = 0 AND (expires_at IS NULL OR expires_at > NOW())";
$notification_stmt = mysqli_prepare($con, $notification_sql);
mysqli_stmt_bind_param($notification_stmt, "i", $user_id);
mysqli_stmt_execute($notification_stmt);
$notification_result = mysqli_stmt_get_result($notification_stmt);
$notification_count = mysqli_fetch_assoc($notification_result)['notification_count'];

// Get reminder counts
$reminder_sql = "SELECT COUNT(*) as reminder_count FROM cashier_reminders 
                WHERE user_id = ? AND is_completed = 0";
$reminder_stmt = mysqli_prepare($con, $reminder_sql);
mysqli_stmt_bind_param($reminder_stmt, "i", $user_id);
mysqli_stmt_execute($reminder_stmt);
$reminder_result = mysqli_stmt_get_result($reminder_stmt);
$reminder_count = mysqli_fetch_assoc($reminder_result)['reminder_count'];

// Get supplier list for dropdowns
$suppliers = [];
$sup_sql = "SELECT id, name FROM suppliers ORDER BY name";
$sup_result = mysqli_query($con, $sup_sql);
while ($sup = mysqli_fetch_assoc($sup_result)) {
    $suppliers[$sup['id']] = $sup['name'];
}

// Get categories for dropdowns
$categories = [];
$cat_sql = "SELECT id, name FROM drug_categories ORDER BY name";
$cat_result = mysqli_query($con, $cat_sql);
while ($cat = mysqli_fetch_assoc($cat_result)) {
    $categories[$cat['id']] = $cat['name'];
}

// Get critical alerts
$critical_expired_sql = "SELECT COUNT(*) as count FROM stock WHERE expire_date < CURDATE() AND status = 'Available'";
$critical_low_stock_sql = "SELECT COUNT(*) as count FROM stock WHERE remain_quantity <= ? AND remain_quantity > 0 AND status = 'Available'";
$critical_expired = mysqli_fetch_assoc(mysqli_query($con, $critical_expired_sql))['count'];
$critical_low_stmt = mysqli_prepare($con, $critical_low_stock_sql);
mysqli_stmt_bind_param($critical_low_stmt, "i", $low_stock_threshold);
mysqli_stmt_execute($critical_low_stmt);
$critical_low_result = mysqli_stmt_get_result($critical_low_stmt);
$critical_low_stock = mysqli_fetch_assoc($critical_low_result)['count'];

// Get import/export statistics
$total_products = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) as total FROM stock"))['total'];
$inventory_value = mysqli_fetch_assoc(mysqli_query($con, "SELECT SUM(remain_quantity * selling_price) as value FROM stock WHERE status='Available'"))['value'] ?? 0;
$total_categories = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(DISTINCT category) as total FROM stock"))['total'];
$total_suppliers = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(DISTINCT supplier_id) as total FROM stock WHERE supplier_id IS NOT NULL"))['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= $pharmacy_name ?> - Stock Management</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HDS Drug Store - Pharmacy Management System">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css" rel="stylesheet" />

    <style>
        body { padding: 20px; font-family: Arial, sans-serif; }
        .summary-badges { margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; }
        .summary-badge { font-weight: 600; font-size: 1rem; padding: 8px 12px; border-radius: 5px; color: white; }
        .badge-total { background-color: #007bff; }
        .badge-expired { background-color: #dc3545; }
        .badge-lowstock { background-color: #ffc107; color: #000; }
        .badge-outofstock { background-color: #6c757d; }
        .badge-controlled { background-color: #dc3545; }
        .badge-expiring { background-color: #fd7e14; }
        .filter-group { display: flex; flex-wrap: wrap; align-items: center; gap: 15px; margin-bottom: 20px; }
        .filter-group label { margin-right: 8px; font-weight: bold; }
        .table td, .table th { white-space: nowrap; }
        .action-buttons { display: flex; gap: 5px; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
        .expired-row { background-color: #ffe6e6 !important; }
        .low-stock-row { background-color: #fff3cd !important; }
        .out-of-stock-row { background-color: #f8f9fa !important; }
        .pinned-product { background-color: #e8f5e8 !important; }
        .controlled-substance { border-left: 4px solid #dc3545; }
        .expiring-soon-row { background-color: #fff3cd !important; border-left: 3px solid #fd7e14; }
        .notification-bar { padding: 10px; background-color: #f8f9fa; border-radius: 5px; border-left: 4px solid #007bff; margin-bottom: 15px; }
        .supplier-info { font-size: 0.8rem; color: #6c757d; font-style: italic; }
        .prescription-indicator { color: #6f42c1; margin-left: 5px; }
        .import-export-buttons { display: flex; gap: 10px; align-items: center; }
        .stats-card { transition: transform 0.2s; }
        .stats-card:hover { transform: translateY(-2px); }
        .bulk-actions { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<br><br><br>

<!-- Critical Alerts Banner -->
<?php if ($critical_expired > 0 || $critical_low_stock > 0): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <h5><i class="fas fa-exclamation-triangle"></i> Critical Alerts</h5>
    <?php if ($critical_expired > 0): ?>
        <p class="mb-1"><strong><?= $critical_expired ?></strong> items have expired and need immediate attention!</p>
    <?php endif; ?>
    <?php if ($critical_low_stock > 0): ?>
        <p class="mb-1"><strong><?= $critical_low_stock ?></strong> items are critically low on stock (≤ <?= $low_stock_threshold ?> units)!</p>
    <?php endif; ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<?php endif; ?>

<!-- Notification Bar -->
<div class="notification-bar d-flex justify-content-between align-items-center">
    <div>
        <?php if ($notification_count > 0): ?>
            <span class="badge badge-warning" data-toggle="modal" data-target="#notificationsModal">
                <i class="fas fa-bell"></i> <?= $notification_count ?> Notifications
            </span>
        <?php endif; ?>
        <?php if ($reminder_count > 0): ?>
            <span class="badge badge-info" data-toggle="modal" data-target="#remindersModal">
                <i class="fas fa-tasks"></i> <?= $reminder_count ?> Reminders
            </span>
        <?php endif; ?>
        <?php if ($can_apply_discount): ?>
            <span class="badge badge-success">
                <i class="fas fa-tag"></i> Max Discount: <?= $max_discount ?>%
            </span>
        <?php endif; ?>
    </div>
    <div>
        <small class="text-muted">Last updated: <?= date('M j, Y g:i A') ?></small>
    </div>
</div>

<h2><i class="fas fa-pills"></i> <?= $pharmacy_name ?> - Inventory Management</h2>

<!-- Show Alerts -->
<?php if (isset($_GET['msg'])): ?>
    <div id="actionAlert" class="alert 
        <?php 
            if ($_GET['msg'] === 'added') echo 'alert-success'; 
            elseif ($_GET['msg'] === 'updated') echo 'alert-info'; 
            elseif ($_GET['msg'] === 'deleted') echo 'alert-danger'; 
            elseif ($_GET['msg'] === 'imported') echo 'alert-success';
            elseif ($_GET['msg'] === 'exported') echo 'alert-info';
            else echo 'alert-secondary'; 
        ?> 
        alert-dismissible fade show" role="alert">
        <?php 
            if ($_GET['msg'] === 'added') echo '<strong>Success!</strong> Drug has been added.';
            elseif ($_GET['msg'] === 'updated') echo '<strong>Updated!</strong> Drug information has been updated.';
            elseif ($_GET['msg'] === 'deleted') echo '<strong>Deleted!</strong> Drug has been removed.';
            elseif ($_GET['msg'] === 'imported') echo '<strong>Success!</strong> Drugs have been imported successfully.';
            elseif ($_GET['msg'] === 'exported') echo '<strong>Exported!</strong> Inventory data has been exported.';
            else echo 'Action completed.';
        ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php endif; ?>

<!-- Quick Stats Dashboard -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center stats-card">
            <div class="card-body">
                <h3 class="text-primary"><?= $total_products ?></h3>
                <p class="text-muted">Total Products</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center stats-card">
            <div class="card-body">
                <h3 class="text-warning"><?= $critical_low_stock ?></h3>
                <p class="text-muted">Low Stock</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center stats-card">
            <div class="card-body">
                <h3 class="text-danger"><?= $critical_expired ?></h3>
                <p class="text-muted">Expired</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center stats-card">
            <div class="card-body">
                <h3 class="text-success"><?= number_format($inventory_value, 2) ?></h3>
                <p class="text-muted">Inventory Value</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center stats-card">
            <div class="card-body">
                <h3 class="text-info"><?= $total_categories ?></h3>
                <p class="text-muted">Categories</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center stats-card">
            <div class="card-body">
                <h3 class="text-secondary"><?= $total_suppliers ?></h3>
                <p class="text-muted">Suppliers</p>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions Section -->
<div class="bulk-actions">
    <div class="d-flex justify-content-between align-items-center">
        <div class="import-export-buttons">
            <button id="importDrugsBtn" class="btn btn-info" data-toggle="modal" data-target="#importDrugsModal">
                <i class="fas fa-file-import"></i> Import Drugs
            </button>
            <div class="btn-group">
                <button id="exportBtn" class="btn btn-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-file-export"></i> Export Data
                </button>
                <div class="dropdown-menu">
                    <a class="dropdown-item export-option" href="#" data-type="1">
                        <i class="fas fa-table"></i> Current View
                    </a>
                    <a class="dropdown-item export-option" href="#" data-type="2">
                        <i class="fas fa-database"></i> Full Inventory
                    </a>
                    <a class="dropdown-item export-option" href="#" data-type="3">
                        <i class="fas fa-calendar-times"></i> Expired Items
                    </a>
                    <a class="dropdown-item export-option" href="#" data-type="4">
                        <i class="fas fa-exclamation-triangle"></i> Low Stock Items
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="import-export/download_template.php">
                        <i class="fas fa-download"></i> Download Template
                    </a>
                </div>
            </div>
        </div>
        
        <div>
            <button id="registerDrugBtn" class="btn btn-success" data-toggle="modal" data-target="#registerDrugModal">
                <i class="fas fa-plus-circle"></i> Register Drug
            </button>  
            <?php if ($can_manage_controlled): ?>
                <button id="manageControlledBtn" class="btn btn-danger" data-toggle="modal" data-target="#controlledSubstancesModal">
                    <i class="fas fa-exclamation-triangle"></i> Manage Controlled
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filter-group">
    <label for="lowYieldFilter">Stock Filter:</label>
    <select id="lowYieldFilter" class="form-control" style="width:auto;">
        <option value="all">All Stock Levels</option>
        <option value="5">Low Stock (≤ 5)</option>
        <option value="10">Medium Stock (≤ 10)</option>
        <option value="above10">Good Stock (> 10)</option>
        <option value="outofstock">Out of Stock</option>
    </select>

    <label for="expiryFilter">Expiry Filter:</label>
    <select id="expiryFilter" class="form-control" style="width:auto;">
        <option value="all">All Expiry Dates</option>
        <option value="expired">Expired</option>
        <option value="week">Expires in 1 Week</option>
        <option value="month">Expires in 1 Month</option>
        <option value="2months">Expires in 2 Months</option>
        <option value="above2months">Expires after 2 Months</option>
    </select>

    <label for="categoryFilter">Category:</label>
    <select id="categoryFilter" class="form-control" style="width:auto;">
        <option value="all">All Categories</option>
        <?php foreach ($categories as $id => $name): ?>
            <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
        <?php endforeach; ?>
    </select>

    <label for="statusFilter">Status:</label>
    <select id="statusFilter" class="form-control" style="width:auto;">
        <option value="all">All Status</option>
        <option value="Available">Available</option>
        <option value="Out of Stock">Out of Stock</option>
    </select>

    <label for="supplierFilter">Supplier:</label>
    <select id="supplierFilter" class="form-control" style="width:auto;">
        <option value="all">All Suppliers</option>
        <?php foreach ($suppliers as $id => $name): ?>
            <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
        <?php endforeach; ?>
    </select>

    <button id="clearFilters" class="btn btn-outline-secondary btn-sm">Clear Filters</button>
</div>
<table id="drugTable" class="table table-striped table-bordered nowrap" style="width:100%">
    <thead>
        <tr>
            <th>#</th>
            <th>Batch</th>
            <th>Drug Name</th>
            <th>Category</th>
            <th>Manufacturer</th>
            <th>Total Qty</th>
            <th>Sold Qty</th>
            <th>Remain Qty</th>
            <th>Expiry Date</th>
            <th>Days Left</th>
            <th>Shelf</th>
            <th>Buy Price (<?= $currency_symbol ?>)</th>
            <th>Sell Price (<?= $currency_symbol ?>)</th>
            <th>Profit (<?= $currency_symbol ?>)</th>
            <th>Profit %</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $sql = "SELECT s.*, 
                   cs.schedule, cs.disposal_date, 
                   (SELECT COUNT(*) FROM pinned_products pp WHERE pp.product_id = s.id AND pp.user_id = ?) as is_pinned,
                   (SELECT COUNT(*) FROM prescription_items pi WHERE pi.product_id = s.id) as has_prescriptions,
                   (SELECT COUNT(*) FROM cart c WHERE c.product_id = s.id) as in_cart
            FROM stock s 
            LEFT JOIN controlled_substances cs ON s.id = cs.product_id 
            ORDER BY s.remain_quantity ASC, s.expire_date ASC";
    
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $counter = 1;
    
    while ($row = mysqli_fetch_assoc($result)) {
        $expireFormatted = date("d-m-Y", strtotime($row['expire_date']));
        
        // Calculate days until expiry
        $today = new DateTime();
        $expiryDate = new DateTime($row['expire_date']);
        $daysLeft = $today->diff($expiryDate)->days;
        $daysLeft = $expiryDate > $today ? $daysLeft : -$daysLeft;
        
        // Calculate profit percentage
        $profitPercentage = $row['actual_price'] > 0 ? 
            (($row['selling_price'] - $row['actual_price']) / $row['actual_price']) * 100 : 0;
        
        // Fake calculations
        $fakeProfit = $row['actual_price'] * 0.25;
        $fakeSelling = $row['actual_price'] + $fakeProfit;
        $fakeProfitPercentage = 25.00;

        // Determine row classes
        $rowClass = '';
        if ($daysLeft < 0) $rowClass .= ' expired-row';
        elseif ($daysLeft <= $expiry_warning_days && $daysLeft > 0) $rowClass .= ' expiring-soon-row';
        if ($row['remain_quantity'] <= $low_stock_threshold && $row['remain_quantity'] > 0) $rowClass .= ' low-stock-row';
        if ($row['status'] === 'Out of Stock' || $row['remain_quantity'] <= 0) $rowClass .= ' out-of-stock-row';
        if ($row['is_pinned']) $rowClass .= ' pinned-product';
        if ($row['schedule']) $rowClass .= ' controlled-substance';
        if ($row['in_cart'] > 0) $rowClass .= ' in-cart-row';

        echo "<tr class='$rowClass' data-id='{$row['id']}'>";
        echo "<td>$counter</td>";
        echo "<td>".htmlspecialchars($row['product_code'])."</td>";
        echo "<td>";
        if ($row['is_pinned']) echo "<i class='fas fa-thumbtack text-success' title='Pinned'></i> ";
        if ($row['schedule']) echo "<i class='fas fa-exclamation-triangle text-danger' title='Controlled Substance - Schedule {$row['schedule']}'></i> ";
        if ($row['has_prescriptions'] > 0) echo "<i class='fas fa-file-prescription prescription-indicator' title='Has prescriptions'></i> ";
        if ($row['in_cart'] > 0) echo "<i class='fas fa-shopping-cart text-info' title='In shopping cart'></i> ";
        echo htmlspecialchars($row['medicine_name'])."</td>";
        echo "<td>".htmlspecialchars($row['category'])."</td>";
        echo "<td>";
        echo htmlspecialchars($row['manufacturer']);
        if ($row['supplier_id']) {
            $supplier_name = $suppliers[$row['supplier_id']] ?? 'Unknown';
            echo "<br><small class='supplier-info'>Supplier: " . htmlspecialchars($supplier_name) . "</small>";
        }
        echo "</td>";
        echo "<td>".htmlspecialchars($row['quantity'])."</td>";
        echo "<td>".htmlspecialchars($row['used_quantity'])."</td>";
        echo "<td>".htmlspecialchars($row['remain_quantity'])."</td>";
        echo "<td>$expireFormatted</td>";
        echo "<td>";
        if ($daysLeft < 0) {
            echo "<span class='text-danger'><strong>Expired</strong></span>";
        } else if ($daysLeft <= $expiry_warning_days) {
            echo "<span class='text-warning'><strong>$daysLeft</strong> days</span>";
        } else {
            echo "<span class='text-success'><strong>$daysLeft</strong> days</span>";
        }
        echo "</td>";
        echo "<td>".htmlspecialchars($row['company'])."</td>";
        echo "<td>".number_format($row['actual_price'], 2)."</td>";

        // Sell Price with toggle spans
        echo "<td class='sell-cell'>
                <span class='true-sell'>".number_format($row['selling_price'], 2)."</span>
                <span class='fake-sell' style='display:none;'>".number_format($fakeSelling, 2)."</span>
              </td>";

        // Profit with toggle spans
        echo "<td class='profit-cell'>
                <span class='true-profit'>".number_format($row['profit_price'], 2)."</span>
                <span class='fake-profit' style='display:none;'>".number_format($fakeProfit, 2)."</span>
              </td>";

        // Profit Percentage with toggle spans
        echo "<td class='profit-percent-cell'>
                <span class='true-profit-percent'>".number_format($profitPercentage, 2)."%</span>
                <span class='fake-profit-percent' style='display:none;'>".number_format($fakeProfitPercentage, 2)."%</span>
              </td>";

        echo "<td>";
        if ($row['schedule']) {
            echo "<span class='badge badge-danger' title='Controlled Substance - Schedule {$row['schedule']}'>CS-{$row['schedule']}</span> ";
        }
        echo "<span class='badge badge-".($row['status'] === 'Available' ? 'success' : 'secondary')."'>".htmlspecialchars($row['status'])."</span></td>";
        
        echo "<td class='action-buttons'>";
        // Edit button
        echo "<button class='btn btn-sm btn-info edit-btn' data-id='{$row['id']}' title='Edit'>
                <i class='fas fa-edit'></i>Edit
              </button>";
        
        // Delete button
        echo "<button class='btn btn-sm btn-danger delete-btn' data-id='{$row['id']}' title='Delete'>
                <i class='fas fa-trash'></i>Delete
              </button>";
        echo "</td>";
        echo "</tr>";
        $counter++;
    }
    ?>
    </tbody>
</table>

<!-- Import Drugs Modal -->
<div class="modal fade" id="importDrugsModal" tabindex="-1" role="dialog" aria-labelledby="importDrugsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="importDrugsModalLabel">
                    <i class="fas fa-file-import"></i> Import Drugs from CSV
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> Import Instructions</h6>
                    <p class="mb-1">• Download the CSV template first to ensure proper formatting</p>
                    <p class="mb-1">• Required fields: Product Code, Medicine Name, Category, Manufacturer, Quantity, Expiry Date, Cost Price, Selling Price</p>
                    <p class="mb-0">• Existing products with matching Product Code and Name will be updated (quantities added)</p>
                </div>
                
                <div class="text-center mb-3">
                    <a href="import-export/download_template.php" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-download"></i> Download CSV Template
                    </a>
                </div>
                
                <form action="import-export/import_drugs.php" method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="form-group">
                        <label for="csv_file"><strong>Select CSV File:</strong></label>
                        <input type="file" class="form-control-file" id="csv_file" name="csv_file" accept=".csv" required>
                        <small class="form-text text-muted">Only .csv files are allowed. Max file size: 10MB</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="import_mode"><strong>Import Mode:</strong></label>
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
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning:</strong> This action will modify your inventory. 
                            It's recommended to export your current data before importing.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" form="importForm" class="btn btn-success">
                    <i class="fas fa-upload"></i> Start Import
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include Other Modals -->
<?php include 'drug_crud/add_drugform.php'; ?>
<?php include 'drug_crud/update_form.php'; ?>
<?php include 'drug_crud/delete.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#drugTable').DataTable({
        responsive: true,
        pageLength: 50,
        lengthMenu: [[50, 100, 250, -1], [50, 100, 250, "All"]],
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: 16 } // Disable sorting on actions column
        ],
        dom: '<"top"lf>rt<"bottom"ip><"clear">',
        language: {
            search: "Search inventory:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)"
        }
    });

    // Initialize Select2
    $('.select2').select2();

    // Initialize Datepicker
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true
    });

    // ---------- Export Functionality ----------
    $('.export-option').click(function(e) {
        e.preventDefault();
        var exportType = $(this).data('type');
        var params = [];
        
        params.push('export_type=' + exportType);
        params.push('lowYieldFilter=' + $('#lowYieldFilter').val());
        params.push('expiryFilter=' + $('#expiryFilter').val());
        params.push('categoryFilter=' + $('#categoryFilter').val());
        params.push('statusFilter=' + $('#statusFilter').val());
        params.push('supplierFilter=' + $('#supplierFilter').val());
        
        // Show loading indicator
        var $btn = $(this);
        var originalText = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Exporting...');
        
        setTimeout(function() {
            window.open('import-export/export_drugs.php?' + params.join('&'), '_blank');
            $btn.html(originalText);
        }, 500);
    });

    // ---------- Import Form Validation ----------
    $('#importForm').on('submit', function(e) {
        var fileInput = $('#csv_file')[0];
        if (!fileInput.files[0]) {
            alert('Please select a CSV file to import.');
            e.preventDefault();
            return false;
        }
        
        var fileName = fileInput.files[0].name;
        var fileExtension = fileName.split('.').pop().toLowerCase();
        
        if (fileExtension !== 'csv') {
            alert('Please select a valid CSV file.');
            e.preventDefault();
            return false;
        }
        
        return confirm('Are you sure you want to import this CSV file? This may update existing inventory data.');
    });

    // ---------- Summary Counters ----------
    function updateSummary() {
        var data = table.rows({filter: 'applied'}).data();
        var expiredCount = 0;
        var lowStockCount = 0;
        var outOfStockCount = 0;
        var controlledCount = 0;
        var expiringCount = 0;

        table.rows({filter: 'applied'}).every(function() {
            var rowData = this.data();
            var daysLeftText = $(rowData[9]).find('strong').text();
            var daysLeft = parseInt(daysLeftText) || 0;
            var remainQty = parseInt(rowData[7]) || 0;
            var status = $(rowData[15]).text() || '';
            var isControlled = $(rowData[2]).find('.fa-exclamation-triangle').length > 0;

            if (daysLeft < 0) expiredCount++;
            if (remainQty <= <?= $low_stock_threshold ?> && remainQty > 0) lowStockCount++;
            if (status.includes('Out of Stock') || remainQty <= 0) outOfStockCount++;
            if (isControlled) controlledCount++;
            if (daysLeft <= <?= $expiry_warning_days ?> && daysLeft > 0) expiringCount++;
        });

        $('#totalCount').text('Total Drugs: ' + data.length);
        $('#expiredCount').text('Expired: ' + expiredCount);
        $('#lowStockCount').text('Low Stock: ' + lowStockCount);
        $('#outOfStockCount').text('Out of Stock: ' + outOfStockCount);
        $('#expiringCount').text('Expiring Soon: ' + expiringCount);
        if (controlledCount > 0) {
            if ($('#controlledCount').length) {
                $('#controlledCount').text('Controlled: ' + controlledCount);
            } else {
                $('.summary-badges').append('<span class="summary-badge badge-controlled" id="controlledCount">Controlled: ' + controlledCount + '</span>');
            }
        } else {
            $('#controlledCount').remove();
        }
    }

    // ---------- Advanced Filters ----------
    $.fn.dataTable.ext.search.push(function(settings, data) {
        var remainQty = parseInt(data[7]) || 0;
        var daysLeftText = $(data[9]).find('strong').text();
        var daysLeft = parseInt(daysLeftText) || 0;
        var category = data[3] || '';
        var status = $(data[15]).text() || '';
        var manufacturer = data[4] || '';
        var categoryFilter = $('#categoryFilter').val();
        var statusFilter = $('#statusFilter').val();
        var supplierFilter = $('#supplierFilter').val();

        // Stock level filter
        var lowYieldFilter = $('#lowYieldFilter').val();
        var passesLowYield = (lowYieldFilter === 'all') ||
                             (lowYieldFilter === '5' && remainQty <= 5) ||
                             (lowYieldFilter === '10' && remainQty <= 10) ||
                             (lowYieldFilter === 'above10' && remainQty > 10) ||
                             (lowYieldFilter === 'outofstock' && (status.includes('Out of Stock') || remainQty <= 0));

        // Expiry filter
        var expiryFilter = $('#expiryFilter').val();
        var passesExpiry = (expiryFilter === 'all') ||
                           (expiryFilter === 'expired' && daysLeft < 0) ||
                           (expiryFilter === 'week' && daysLeft >= 0 && daysLeft <= 7) ||
                           (expiryFilter === 'month' && daysLeft >= 0 && daysLeft <= 30) ||
                           (expiryFilter === '2months' && daysLeft >= 0 && daysLeft <= 60) ||
                           (expiryFilter === 'above2months' && daysLeft > 60);

        // Category filter
        var passesCategory = (categoryFilter === 'all') || 
                            (category.toLowerCase().includes(categoryFilter.toLowerCase()));

        // Status filter
        var passesStatus = (statusFilter === 'all') || 
                          (status.includes(statusFilter));

        // Supplier filter
        var passesSupplier = (supplierFilter === 'all') || 
                            (manufacturer.toLowerCase().includes(supplierFilter.toLowerCase()));

        return passesLowYield && passesExpiry && passesCategory && passesStatus && passesSupplier;
    });

    // Apply filters on change
    $('#lowYieldFilter, #expiryFilter, #categoryFilter, #statusFilter, #supplierFilter').on('change', function() {
        table.draw();
        updateSummary();
    });

    // Clear filters
    $('#clearFilters').on('click', function() {
        $('#lowYieldFilter, #expiryFilter, #categoryFilter, #statusFilter, #supplierFilter').val('all');
        table.draw();
        updateSummary();
    });

    table.on('draw', updateSummary);
    updateSummary();

    // ---------- Edit button handler ----------
    $('#drugTable').on('click', '.edit-btn', function () {
        var $btn = $(this);
        var id = $btn.data('id');

        var tr = $btn.closest('tr');
        if (tr.hasClass('child')) tr = tr.prev();

        var rowData = table.row(tr).data() || [];

        // Populate edit form
        $('#edit_id').val(id);
        $('#edit_product_code').val(rowData[1] || '');
        $('#edit_medicine_name').val(rowData[2] || '');
        $('#edit_category').val(rowData[3] || '');
        $('#edit_manufacturer').val(rowData[4] || '');
        $('#edit_quantity').val(parseInt(rowData[5]) || 0);
        $('#edit_used_quantity').val(parseInt(rowData[6]) || 0);
        $('#edit_remain_quantity').val(parseInt(rowData[7]) || 0);

        // Expiry date
        var exp = (rowData[8] || '').split('-');
        if (exp.length === 3) {
            $('#edit_expire_date').val(exp[2] + '-' + exp[1] + '-' + exp[0]);
        }

        $('#edit_company').val(rowData[10] || '');
        $('#edit_actual_price').val(parseFloat(rowData[11]) || '');
        
        // Get selling price from visible span
        var sellingTxt = $(tr).find('.sell-cell .true-sell:visible').text().trim();
        if (!sellingTxt) sellingTxt = $(tr).find('.sell-cell .true-sell').text().trim();
        $('#edit_selling_price').val(parseFloat(sellingTxt.replace(/,/g, '')) || '');

        $('#edit_status').val($(rowData[15]).text().trim() || 'Available');

        $('#editDrugModal').modal('show');
    });

    // ---------- Delete button ----------
    $('#drugTable').on('click', '.delete-btn', function () {
        var $btn = $(this);
        var id = $btn.data('id');

        var tr = $btn.closest('tr');
        if (tr.hasClass('child')) tr = tr.prev();

        var rowData = table.row(tr).data() || [];
        $('#delete_id').val(id);
        $('#delete_drug_name').text(rowData[2] || 'this item');
        $('#deleteDrugModal').modal('show');
    });

    // ---------- Auto-hide alerts ----------
    setTimeout(function() { 
        $("#actionAlert").alert('close'); 
    }, 5000);

    // ---------- File input styling ----------
    $('#csv_file').on('change', function() {
        var fileName = $(this).val().split('\\').pop();
        if (fileName) {
            $(this).next('.custom-file-label').html(fileName);
        }
    });
});

// Logout confirmation
$(".logout").click(function(e){
    e.preventDefault();
    if(confirm("Are you sure you want to logout?")){
        window.location.href = '../logout.php';
    }
});
</script>

<br><br><br>
<?php include 'footer.php'; ?>
</body>
</html>