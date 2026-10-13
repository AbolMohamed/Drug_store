<?php
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location:../login.php");
    exit();
}
require_once '../../dbcon.php';
require_once 'analytics_functions.php';
if (!function_exists('getSalesData')) {
    require_once __DIR__ . '/analytics_functions_demo.php';
}

// Get filter parameters
$format = $_GET['format'] ?? 'pdf';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$serviceFilter = $_GET['service_filter'] ?? 'all';
$paymentFilter = $_GET['payment_filter'] ?? 'all';
$monthFilter = $_GET['month_filter'] ?? 'all';
$yearFilter = $_GET['year_filter'] ?? date('Y');
$period = $_GET['period'] ?? 'daily';

// Get analytics data
$salesData = getSalesData($con, $startDate, $endDate, $serviceFilter, $paymentFilter, $monthFilter, $yearFilter);
if ($salesData === false) die("Error: Could not retrieve sales data.");

// Filter only completed orders
$salesData = array_filter($salesData, function($order) {
    return isset($order['status']) 
        && strtolower($order['status']) === 'completed'
        && (!isset($order['is_deleted']) || $order['is_deleted'] == 0)
        && (!isset($order['is_restored']) || $order['is_restored'] == 0);
});
$salesData = array_values($salesData);

// Get metrics and other data
$metrics = getDashboardMetrics($salesData, $con);
$topProducts = getTopProducts($salesData, 10);
$trendData = getTrendData($salesData, $period);
$serviceAnalysis = getServiceAnalysis($salesData);
$categoryAnalysis = getCategorySalesAnalysis($con, $startDate, $endDate);
$timePeriodProducts = getTimePeriodProductSales($con, $period, $startDate, $endDate, 20);
$lowStockItems = getLowStockItems($con, 10);
$expirationAnalysis = getExpirationAnalysis($con, 30);

// Calculate stock values
$stockCapital = 0;
$potentialProfit = 0;
$stockValue = 0;
$stockQuery = "SELECT remain_quantity, actual_price, selling_price FROM stock";
$stockResult = mysqli_query($con, $stockQuery);
if ($stockResult) {
    while ($row = mysqli_fetch_assoc($stockResult)) {
        $quantity = $row['remain_quantity'];
        $cost = $row['actual_price'];
        $price = $row['selling_price'];
        
        $stockCapital += $quantity * $cost;
        $potentialProfit += $quantity * ($price - $cost);
        $stockValue += $quantity * $price;
    }
}

// Format dates for filename
$formattedStartDate = date('Y-m-d', strtotime($startDate));
$formattedEndDate = date('Y-m-d', strtotime($endDate));

if ($format === 'pdf') {
    // Generate PDF
    require_once('tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('HDS Drug Store System');
    $pdf->SetAuthor('HDS Drug Store');
    $pdf->SetTitle('Analytics Report - ' . $formattedStartDate . ' to ' . $formattedEndDate);
    $pdf->SetSubject('Analytics Report');
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(10);
    $pdf->SetFooterMargin(10);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, 'HDS Drug Store - Analytics Report', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Period: ' . $formattedStartDate . ' to ' . $formattedEndDate, 0, 1, 'C');
    $pdf->Ln(10);
    
    // Filters applied
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Filters Applied:', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    $filters = "Service Type: " . ucfirst($serviceFilter) . 
               " | Payment Method: " . ucfirst($paymentFilter) .
               " | Month: " . ($monthFilter === 'all' ? 'All' : DateTime::createFromFormat('!m', $monthFilter)->format('F')) .
               " | Year: " . ($yearFilter === 'all' ? 'All' : $yearFilter) .
               " | Period: " . ucfirst($period);
    
    $pdf->MultiCell(0, 10, $filters, 0, 'L');
    $pdf->Ln(10);
    
    // Key Metrics
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Key Metrics:', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    $metricsData = array(
        array('Metric', 'Value'),
        array('Total Revenue', 'ETB ' . number_format($metrics['total_revenue'], 2)),
        array('Total Profit', 'ETB ' . number_format($metrics['total_profit'], 2)),
        array('Orders', number_format($metrics['unique_orders'])),
        array('Items Sold', number_format($metrics['total_quantity'])),
        array('Stock Capital', 'ETB ' . number_format($stockCapital, 2)),
        array('Potential Profit', 'ETB ' . number_format($potentialProfit, 2))
    );
    
    // Create metrics table
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.1);
    $pdf->SetFont('', 'B');
    
    // Header
    $pdf->Cell(90, 7, 'Metric', 1, 0, 'C', 1);
    $pdf->Cell(90, 7, 'Value', 1, 1, 'C', 1);
    
    // Data
    $pdf->SetFont('', '');
    $pdf->SetFillColor(255, 255, 255);
    $fill = false;
    
    for ($i = 1; $i < count($metricsData); $i++) {
        $pdf->Cell(90, 6, $metricsData[$i][0], 'LR', 0, 'L', $fill);
        $pdf->Cell(90, 6, $metricsData[$i][1], 'LR', 1, 'R', $fill);
        $fill = !$fill;
    }
    
    $pdf->Cell(180, 0, '', 'T');
    $pdf->Ln(10);
    
    // Top Products
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Top Products:', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    $productsHeader = array('Product', 'Category', 'Qty', 'Revenue', 'Profit', 'Margin');
    $productsData = array();
    
    foreach (array_slice($topProducts, 0, 10) as $product) {
        $productsData[] = array(
            $product['medicine_name'],
            $product['category'],
            number_format($product['total_quantity']),
            'ETB ' . number_format($product['total_revenue'], 2),
            'ETB ' . number_format($product['total_profit'], 2),
            number_format($product['profit_margin'], 1) . '%'
        );
    }
    
    // Create products table
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(0);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.1);
    $pdf->SetFont('', 'B');
    
    // Column widths
    $w = array(60, 40, 20, 30, 30, 20);
    
    // Header
    for ($i = 0; $i < count($productsHeader); $i++) {
        $pdf->Cell($w[$i], 7, $productsHeader[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();
    
    // Data
    $pdf->SetFont('', '');
    $pdf->SetFillColor(255, 255, 255);
    $fill = false;
    
    foreach ($productsData as $row) {
        for ($i = 0; $i < count($row); $i++) {
            $pdf->Cell($w[$i], 6, $row[$i], 'LR', 0, 'C', $fill);
        }
        $pdf->Ln();
        $fill = !$fill;
    }
    
    $pdf->Cell(array_sum($w), 0, '', 'T');
    $pdf->Ln(10);
    
    // Service Analysis
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Service Analysis:', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    
    foreach ($serviceAnalysis as $type => $data) {
        $pdf->Cell(0, 6, $type . ' Services:', 0, 1);
        $pdf->Cell(0, 6, 'Revenue: ETB ' . number_format($data['revenue'], 2) . 
                     ' | Profit: ETB ' . number_format($data['profit'], 2) . 
                     ' | Orders: ' . number_format($data['orders']) . 
                     ' | Items: ' . number_format($data['quantity']), 0, 1);
        $pdf->Ln(5);
    }
    
    // Add more sections as needed (Category Analysis, Low Stock, etc.)
    
    // Close and output PDF document
    $pdf->Output('analytics_report_' . $formattedStartDate . '_to_' . $formattedEndDate . '.pdf', 'D');
    
} elseif ($format === 'excel' || $format === 'csv') {
    // Generate Excel/CSV
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="analytics_report_' . $formattedStartDate . '_to_' . $formattedEndDate . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Create Excel data
    $excelData = "<table border='1'>";
    
    // Title
    $excelData .= "<tr><th colspan='6' style='background-color: #6c5ce7; color: white;'>HDS Drug Store - Analytics Report</th></tr>";
    $excelData .= "<tr><th colspan='6'>Period: " . $formattedStartDate . " to " . $formattedEndDate . "</th></tr>";
    $excelData .= "<tr><th colspan='6'>Filters Applied: Service Type: " . ucfirst($serviceFilter) . 
                  " | Payment Method: " . ucfirst($paymentFilter) .
                  " | Month: " . ($monthFilter === 'all' ? 'All' : DateTime::createFromFormat('!m', $monthFilter)->format('F')) .
                  " | Year: " . ($yearFilter === 'all' ? 'All' : $yearFilter) .
                  " | Period: " . ucfirst($period) . "</th></tr>";
    $excelData .= "<tr><td colspan='6'></td></tr>";
    
    // Key Metrics
    $excelData .= "<tr><th colspan='6' style='background-color: #a29bfe;'>Key Metrics</th></tr>";
    $excelData .= "<tr><th>Metric</th><th>Value</th><th colspan='4'></th></tr>";
    $excelData .= "<tr><td>Total Revenue</td><td>ETB " . number_format($metrics['total_revenue'], 2) . "</td><td colspan='4'></td></tr>";
    $excelData .= "<tr><td>Total Profit</td><td>ETB " . number_format($metrics['total_profit'], 2) . "</td><td colspan='4'></td></tr>";
    $excelData .= "<tr><td>Orders</td><td>" . number_format($metrics['unique_orders']) . "</td><td colspan='4'></td></tr>";
    $excelData .= "<tr><td>Items Sold</td><td>" . number_format($metrics['total_quantity']) . "</td><td colspan='4'></td></tr>";
    $excelData .= "<tr><td>Stock Capital</td><td>ETB " . number_format($stockCapital, 2) . "</td><td colspan='4'></td></tr>";
    $excelData .= "<tr><td>Potential Profit</td><td>ETB " . number_format($potentialProfit, 2) . "</td><td colspan='4'></td></tr>";
    $excelData .= "<tr><td colspan='6'></td></tr>";
    
    // Top Products
    $excelData .= "<tr><th colspan='6' style='background-color: #a29bfe;'>Top Products</th></tr>";
    $excelData .= "<tr><th>Product</th><th>Category</th><th>Qty</th><th>Revenue</th><th>Profit</th><th>Margin</th></tr>";
    
    foreach (array_slice($topProducts, 0, 10) as $product) {
        $excelData .= "<tr>";
        $excelData .= "<td>" . $product['medicine_name'] . "</td>";
        $excelData .= "<td>" . $product['category'] . "</td>";
        $excelData .= "<td>" . number_format($product['total_quantity']) . "</td>";
        $excelData .= "<td>ETB " . number_format($product['total_revenue'], 2) . "</td>";
        $excelData .= "<td>ETB " . number_format($product['total_profit'], 2) . "</td>";
        $excelData .= "<td>" . number_format($product['profit_margin'], 1) . "%</td>";
        $excelData .= "</tr>";
    }
    $excelData .= "<tr><td colspan='6'></td></tr>";
    
    // Service Analysis
    $excelData .= "<tr><th colspan='6' style='background-color: #a29bfe;'>Service Analysis</th></tr>";
    $excelData .= "<tr><th>Service Type</th><th>Revenue</th><th>Profit</th><th>Orders</th><th>Items</th><th></th></tr>";
    
    foreach ($serviceAnalysis as $type => $data) {
        $excelData .= "<tr>";
        $excelData .= "<td>" . $type . "</td>";
        $excelData .= "<td>ETB " . number_format($data['revenue'], 2) . "</td>";
        $excelData .= "<td>ETB " . number_format($data['profit'], 2) . "</td>";
        $excelData .= "<td>" . number_format($data['orders']) . "</td>";
        $excelData .= "<td>" . number_format($data['quantity']) . "</td>";
        $excelData .= "<td></td>";
        $excelData .= "</tr>";
    }
    $excelData .= "<tr><td colspan='6'></td></tr>";
    
    // Category Analysis
    $excelData .= "<tr><th colspan='6' style='background-color: #a29bfe;'>Category Analysis (Top 10)</th></tr>";
    $excelData .= "<tr><th>Category</th><th>Orders</th><th>Qty Sold</th><th>Revenue</th><th>Profit</th><th>Margin %</th></tr>";
    
    foreach (array_slice($categoryAnalysis, 0, 10) as $category) {
        $excelData .= "<tr>";
        $excelData .= "<td>" . $category['category'] . "</td>";
        $excelData .= "<td>" . number_format($category['total_orders']) . "</td>";
        $excelData .= "<td>" . number_format($category['total_quantity_sold']) . "</td>";
        $excelData .= "<td>ETB " . number_format($category['total_revenue'], 2) . "</td>";
        $excelData .= "<td>ETB " . number_format($category['total_profit'], 2) . "</td>";
        $excelData .= "<td>" . number_format($category['profit_margin_percent'], 1) . "%</td>";
        $excelData .= "</tr>";
    }
    $excelData .= "<tr><td colspan='6'></td></tr>";
    
    // Low Stock Items
    $excelData .= "<tr><th colspan='6' style='background-color: #a29bfe;'>Low Stock Alert (Top 10)</th></tr>";
    $excelData .= "<tr><th>Product</th><th>Category</th><th>Remaining</th><th>Status</th><th>Value</th><th></th></tr>";
    
    foreach (array_slice($lowStockItems, 0, 10) as $item) {
        $excelData .= "<tr>";
        $excelData .= "<td>" . $item['medicine_name'] . "</td>";
        $excelData .= "<td>" . $item['category'] . "</td>";
        $excelData .= "<td>" . number_format($item['remain_quantity']) . "</td>";
        $excelData .= "<td>" . $item['stock_level'] . "</td>";
        $excelData .= "<td>ETB " . number_format($item['selling_price'] * $item['remain_quantity'], 2) . "</td>";
        $excelData .= "<td></td>";
        $excelData .= "</tr>";
    }
    
    $excelData .= "</table>";
    
    echo $excelData;
    exit;
}
?>