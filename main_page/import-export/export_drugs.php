<?php
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location:../login.php");
    exit();
}
include('../../dbcon.php');

// Get system settings
$settings_sql = "SELECT setting_key, setting_value FROM system_settings WHERE is_public = 1";
$settings_result = mysqli_query($con, $settings_sql);
$system_settings = [];
while ($setting = mysqli_fetch_assoc($settings_result)) {
    $system_settings[$setting['setting_key']] = $setting['setting_value'];
}

$currency_symbol = $system_settings['currency_symbol'] ?? 'ETB';
$low_stock_threshold = $system_settings['low_stock_threshold'] ?? 10;
$expiry_warning_days = $system_settings['expiry_warning_days'] ?? 30;

// Get export parameters
$export_type = $_GET['export_type'] ?? '1';
$lowYieldFilter = $_GET['lowYieldFilter'] ?? 'all';
$expiryFilter = $_GET['expiryFilter'] ?? 'all';
$categoryFilter = $_GET['categoryFilter'] ?? 'all';
$statusFilter = $_GET['statusFilter'] ?? 'all';

// Build query based on export type
$sql = "SELECT s.*, 
               cs.schedule,
               (SELECT name FROM suppliers sup WHERE sup.id = s.supplier_id) as supplier_name,
               DATEDIFF(s.expire_date, CURDATE()) as days_left
        FROM stock s 
        LEFT JOIN controlled_substances cs ON s.id = cs.product_id 
        WHERE 1=1";

switch ($export_type) {
    case '2': // Full Inventory
        // No additional filters needed
        break;
    case '3': // Expired Items
        $sql .= " AND s.expire_date < CURDATE() AND s.status = 'Available'";
        break;
    case '4': // Low Stock Items
        $sql .= " AND s.remain_quantity <= $low_stock_threshold AND s.remain_quantity > 0 AND s.status = 'Available'";
        break;
    default: // Current View (apply filters)
        if ($lowYieldFilter !== 'all') {
            switch ($lowYieldFilter) {
                case '5':
                    $sql .= " AND s.remain_quantity <= 5";
                    break;
                case '10':
                    $sql .= " AND s.remain_quantity <= 10";
                    break;
                case 'above10':
                    $sql .= " AND s.remain_quantity > 10";
                    break;
                case 'outofstock':
                    $sql .= " AND (s.status = 'Out of Stock' OR s.remain_quantity <= 0)";
                    break;
            }
        }

        if ($expiryFilter !== 'all') {
            switch ($expiryFilter) {
                case 'expired':
                    $sql .= " AND s.expire_date < CURDATE()";
                    break;
                case 'week':
                    $sql .= " AND s.expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $sql .= " AND s.expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                    break;
                case '2months':
                    $sql .= " AND s.expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)";
                    break;
                case 'above2months':
                    $sql .= " AND s.expire_date > DATE_ADD(CURDATE(), INTERVAL 60 DAY)";
                    break;
            }
        }

        if ($categoryFilter !== 'all') {
            $sql .= " AND s.category = '" . mysqli_real_escape_string($con, $categoryFilter) . "'";
        }

        if ($statusFilter !== 'all') {
            $sql .= " AND s.status = '" . mysqli_real_escape_string($con, $statusFilter) . "'";
        }
        break;
}

$sql .= " ORDER BY s.remain_quantity ASC, s.expire_date ASC";

$result = mysqli_query($con, $sql);

// Set headers for download
$filename = "pharmacy_inventory_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV headers
$headers = [
    'ID',
    'Product Code',
    'Medicine Name',
    'Category',
    'Manufacturer',
    'Supplier',
    'Total Quantity',
    'Used Quantity',
    'Remaining Quantity',
    'Expiry Date',
    'Days Left',
    'Shelf Location',
    'Cost Price (' . $currency_symbol . ')',
    'Selling Price (' . $currency_symbol . ')',
    'Profit (' . $currency_symbol . ')',
    'Profit %',
    'Status',
    'Controlled Substance Schedule',
    'Stock Status'
];

fputcsv($output, $headers);

// Add data rows
while ($row = mysqli_fetch_assoc($result)) {
    // Calculate profit and percentage
    $profit = $row['selling_price'] - $row['actual_price'];
    $profit_percentage = $row['actual_price'] > 0 ? ($profit / $row['actual_price']) * 100 : 0;
    
    // Determine stock status
    $stock_status = 'Good';
    if ($row['days_left'] < 0) {
        $stock_status = 'Expired';
    } elseif ($row['days_left'] <= $expiry_warning_days) {
        $stock_status = 'Expiring Soon';
    } elseif ($row['remain_quantity'] <= $low_stock_threshold) {
        $stock_status = 'Low Stock';
    } elseif ($row['status'] === 'Out of Stock') {
        $stock_status = 'Out of Stock';
    }

    $data = [
        $row['id'],
        $row['product_code'],
        $row['medicine_name'],
        $row['category'],
        $row['manufacturer'],
        $row['supplier_name'] ?? 'N/A',
        $row['quantity'],
        $row['used_quantity'],
        $row['remain_quantity'],
        $row['expire_date'],
        $row['days_left'],
        $row['company'],
        number_format($row['actual_price'], 2),
        number_format($row['selling_price'], 2),
        number_format($profit, 2),
        number_format($profit_percentage, 2) . '%',
        $row['status'],
        $row['schedule'] ? 'Schedule ' . $row['schedule'] : 'No',
        $stock_status
    ];

    fputcsv($output, $data);
}

fclose($output);
exit;
?>