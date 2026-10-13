<?php
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location:../login.php");
}
require_once '../../dbcon.php';
require_once 'analytics_functions.php';
if (!function_exists('getSalesData')) {
    require_once __DIR__ . '/analytics_functions_demo.php';
}

// Handle filters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$serviceFilter = $_GET['service_filter'] ?? 'all';
$paymentFilter = $_GET['payment_filter'] ?? 'all';
$monthFilter = $_GET['month_filter'] ?? 'all';
$yearFilter = $_GET['year_filter'] ?? date('Y');
$period = $_GET['period'] ?? 'daily';
$presetRange = $_GET['preset_range'] ?? '';

// Apply preset date ranges
if (!empty($presetRange)) {
    switch ($presetRange) {
        case 'today':
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');
            break;
        case 'yesterday':
            $startDate = date('Y-m-d', strtotime('-1 day'));
            $endDate = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'last_7_days':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            $endDate = date('Y-m-d');
            break;
        case 'last_30_days':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $endDate = date('Y-m-d');
            break;
        case 'this_month':
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            break;
        case 'last_month':
            $startDate = date('Y-m-01', strtotime('-1 month'));
            $endDate = date('Y-m-t', strtotime('-1 month'));
            break;
        case 'this_quarter':
            $currentMonth = date('n');
            $currentYear = date('Y');
            if ($currentMonth >= 1 && $currentMonth <= 3) {
                $startDate = $currentYear . '-01-01';
                $endDate = $currentYear . '-03-31';
            } elseif ($currentMonth >= 4 && $currentMonth <= 6) {
                $startDate = $currentYear . '-04-01';
                $endDate = $currentYear . '-06-30';
            } elseif ($currentMonth >= 7 && $currentMonth <= 9) {
                $startDate = $currentYear . '-07-01';
                $endDate = $currentYear . '-09-30';
            } elseif ($currentMonth >= 10 && $currentMonth <= 12) {
                $startDate = $currentYear . '-10-01';
                $endDate = $currentYear . '-12-31';
            }
            break;
        case 'this_year':
            $startDate = date('Y-01-01');
            $endDate = date('Y-12-31');
            break;
    }
}

// Get analytics data
$salesData = getSalesData($con, $startDate, $endDate, $serviceFilter, $paymentFilter, $monthFilter, $yearFilter);
if ($salesData === false) die("Error: Could not retrieve sales data.");

// ✅ Extra safeguard (only completed orders everywhere)
$salesData = array_filter($salesData, function($order) {
    return isset($order['status']) 
        && strtolower($order['status']) === 'completed'
        && (!isset($order['is_deleted']) || $order['is_deleted'] == 0)
        && (!isset($order['is_restored']) || $order['is_restored'] == 0);
});
$salesData = array_values($salesData); // reindex
$metrics = getDashboardMetrics($salesData, $con);
$topProducts = getTopProducts($salesData, 10);
$trendData = getTrendData($salesData, $period);
$serviceAnalysis = getServiceAnalysis($salesData);
$stockStatus = getStockStatus($con);

// NEW: Get enhanced report data
$categoryAnalysis = getCategorySalesAnalysis($con, $startDate, $endDate);
$timePeriodProducts = getTimePeriodProductSales($con, $period, $startDate, $endDate, 20);
$lowStockItems = getLowStockItems($con, 10);
$expirationAnalysis = getExpirationAnalysis($con, 30);

// NEW: Calculate stock capital and profit
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

// Get unique years for filter dropdown
$yearQuery = "SELECT DISTINCT YEAR(order_date) as year FROM orders WHERE order_date IS NOT NULL ORDER BY year DESC";
$yearResult = mysqli_query($con, $yearQuery);
$availableYears = [];
while ($row = mysqli_fetch_assoc($yearResult)) {
    $availableYears[] = $row['year'];
}
?>
<?php include 'header.php'; ?>
<br>
<br>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/png" sizes="16x16" href="../../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HDS Drug Store - Pharmacy Management System">
  <title>Drug Store Analytics - Reports Dashboard</title>
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- DataTables CSS -->
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
  <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet" />
  <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <style>
    :root {
      --primary: #6c5ce7;
      --secondary: #a29bfe;
      --success: #00b894;
      --info: #00cec9;
      --warning: #fdcb6e;
      --danger: #d63031;
      --light: #f8f9fa;
      --dark: #212529;
      --background: #f0f2f5;
      --card-bg: #ffffff;
      --text-primary: #2d3748;
      --text-secondary: #718096;
      --border-radius: 16px;
      --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.04);
      --shadow-md: 0 6px 12px rgba(0, 0, 0, 0.08);
      --shadow-lg: 0 15px 30px rgba(0, 0, 0, 0.12);
      --gradient-primary: linear-gradient(135deg, #6c5ce7 0%, #8e44ad 100%);
      --gradient-success: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
      --gradient-warning: linear-gradient(135deg, #fdcb6e 0%, #e17055 100%);
      --gradient-danger: linear-gradient(135deg, #d63031 0%, #c23616 100%);
      --gradient-info: linear-gradient(135deg, #00cec9 0%, #0984e3 100%);
    }
    
    [data-theme="dark"] {
      --background: #121826;
      --card-bg: #1e293b;
      --text-primary: #f1f5f9;
      --text-secondary: #94a3b8;
      --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.18);
      --shadow-md: 0 6px 12px rgba(0, 0, 0, 0.24);
      --shadow-lg: 0 15px 30px rgba(0, 0, 0, 0.30);
    }
    
    body { 
      font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif; 
      background-color: var(--background);
      color: var(--text-primary);
      transition: all 0.3s ease;
      padding-top: 20px;
    }
    
    .navbar-brand { 
      font-weight: 800; 
      color: var(--primary) !important; 
      letter-spacing: -0.5px;
    }
    
    .card {
      background-color: var(--card-bg);
      border: none;
      border-radius: var(--border-radius);
      box-shadow: var(--shadow-sm);
      transition: all 0.3s ease;
      margin-bottom: 24px;
    }
    
    .card:hover {
      box-shadow: var(--shadow-md);
      transform: translateY(-2px);
    }
    
    .metric-card { 
      border-radius: var(--border-radius); 
      box-shadow: var(--shadow-sm);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    
    .metric-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: var(--gradient-primary);
      opacity: 0.8;
    }
    
    .metric-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
    }
    
    .metric-value { 
      font-size: 1.8rem; 
      font-weight: 800; 
      margin-bottom: 0;
      letter-spacing: -0.5px;
    }
    
    .metric-label { 
      font-size: 0.9rem; 
      color: var(--text-secondary);
      font-weight: 500;
      margin-bottom: 8px;
    }
    
    .metric-icon {
      font-size: 1.8rem;
      opacity: 0.8;
      margin-bottom: 12px;
    }
    
    .chart-container { 
      position: relative; 
      height: 400px; 
      margin: 20px 0; 
    }
    
    .filters-card { 
      background: var(--gradient-primary);
      color: white; 
      border-radius: var(--border-radius); 
    }
    
    .table-container { 
      max-height: 400px; 
      overflow-y: auto; 
    }
    
    .status-badge { 
      font-size: 0.75rem; 
      padding: 6px 10px; 
      border-radius: 20px;
      font-weight: 600;
    }
    
    .alert-expired { 
      background-color: rgba(214, 48, 49, 0.1); 
      border-left: 4px solid var(--danger); 
    }
    
    .alert-expiring-soon, .alert-expires-this-week { 
      background-color: rgba(253, 203, 110, 0.1); 
      border-left: 4px solid var(--warning); 
    }
    
    .alert-low-stock, .alert-critical-low { 
      background-color: rgba(0, 206, 201, 0.1); 
      border-left: 4px solid var(--info); 
    }
    
    .alert-good, .alert-normal { 
      background-color: rgba(0, 184, 148, 0.1); 
      border-left: 4px solid var(--success); 
    }
    
    .alert-out-of-stock { 
      background-color: rgba(214, 48, 49, 0.1); 
      border-left: 4px solid var(--danger); 
    }
    
    .alert-expires-soon { 
      background-color: rgba(108, 92, 231, 0.1); 
      border-left: 4px solid var(--primary); 
    }
    
    .enhanced-card { 
      border-radius: var(--border-radius); 
      box-shadow: var(--shadow-sm);
      margin-bottom: 24px;
    }
    
    .section-header { 
      background: var(--gradient-primary);
      color: white; 
      border-radius: var(--border-radius) var(--border-radius) 0 0; 
      padding: 16px 24px;
      font-weight: 700;
    }
    
    .summary-badge { 
      font-weight: 700; 
      font-size: 1rem; 
      padding: 10px 16px; 
      border-radius: 12px; 
      color: white; 
      background: var(--gradient-primary); 
      margin-bottom: 20px; 
      display: inline-block; 
    }
    
    .action-buttons { 
      display: flex; 
      gap: 8px; 
    }
    
    .btn-sm { 
      padding: 0.5rem 0.75rem; 
      font-size: 0.8rem; 
      border-radius: 8px;
      font-weight: 600;
    }
    
    .dataTables_wrapper {
      padding: 0 10px;
    }
    
    .theme-switch {
      position: relative;
      display: inline-block;
      width: 60px;
      height: 30px;
    }
    
    .theme-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    
    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 34px;
    }
    
    .slider:before {
      position: absolute;
      content: "";
      height: 22px;
      width: 22px;
      left: 4px;
      bottom: 4px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }
    
    input:checked + .slider {
      background: var(--gradient-primary);
    }
    
    input:checked + .slider:before {
      transform: translateX(30px);
    }
    
    .nav-tabs .nav-link {
      border: none;
      border-bottom: 3px solid transparent;
      font-weight: 600;
      color: var(--text-secondary);
      padding: 12px 20px;
    }
    
    .nav-tabs .nav-link.active {
      color: var(--primary);
      border-bottom: 3px solid var(--primary);
      background: transparent;
    }
    
    .filter-section {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      padding: 15px;
      margin-bottom: 15px;
    }
    
    .filter-title {
      font-weight: 600;
      margin-bottom: 10px;
      color: white;
      display: flex;
      align-items: center;
    }
    
    .filter-title i {
      margin-right: 8px;
    }
    
    .export-btn {
      background: var(--gradient-success);
      border: none;
      color: white;
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .export-btn:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }
    
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.8);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 9999;
      display: none;
    }
    
    .spinner {
      width: 50px;
      height: 50px;
      border: 5px solid var(--secondary);
      border-top: 5px solid var(--primary);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    
    @media (max-width: 768px) {
      .metric-value {
        font-size: 1.5rem;
      }
      
      .chart-container {
        height: 300px;
      }
      
      .section-header h5 {
        font-size: 1.1rem;
      }
      
      .filter-section {
        padding: 10px;
      }
    }
    
    /* Custom scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }
    
    ::-webkit-scrollbar-track {
      background: transparent;
    }
    
    ::-webkit-scrollbar-thumb {
      background: #cbd5e0;
      border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
      background: #a0aec0;
    }
    
    /* Animation for metrics */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .metric-card {
      animation: fadeIn 0.5s ease-out forwards;
    }
    
    .metric-card:nth-child(1) { animation-delay: 0.1s; }
    .metric-card:nth-child(2) { animation-delay: 0.2s; }
    .metric-card:nth-child(3) { animation-delay: 0.3s; }
    .metric-card:nth-child(4) { animation-delay: 0.4s; }
    .metric-card:nth-child(5) { animation-delay: 0.5s; }
    .metric-card:nth-child(6) { animation-delay: 0.6s; }
    
    /* Glassmorphism effect for cards */
    .glass-card {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    [data-theme="dark"] .glass-card {
      background: rgba(30, 41, 59, 0.5);
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    /* Modern 2025 theme updates */
    .btn-modern {
      background: var(--gradient-primary);
      color: white;
      border: none;
      border-radius: 12px;
      padding: 10px 20px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .btn-modern:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }
    
    .modern-select {
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      padding: 10px;
      background: white;
      transition: all 0.3s ease;
    }
    
    .modern-select:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(108, 92, 231, 0.1);
    }
    
    .card-modern {
      border-radius: 20px;
      box-shadow: var(--shadow-sm);
      transition: all 0.3s ease;
      overflow: hidden;
    }
    
    .card-modern:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-lg);
    }
    
    .card-modern-header {
      background: var(--gradient-primary);
      color: white;
      padding: 20px;
      font-weight: 700;
    }
    
    .card-modern-body {
      padding: 20px;
      background: white;
    }
    
    /* Theme toggle button */
    .theme-toggle {
      position: fixed;
      bottom: 20px;
      right: 20px;
      z-index: 1000;
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: var(--gradient-primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: var(--shadow-lg);
      transition: all 0.3s ease;
    }
    
    .theme-toggle:hover {
      transform: scale(1.1);
    }

    .loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    flex-direction: column;
    display: none;
}

.loading-overlay[data-theme="dark"] {
    background: rgba(18, 24, 38, 0.9);
}

.spinner {
    width: 50px;
    height: 50px;
    border: 5px solid var(--secondary);
    border-top: 5px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 15px;
}

.loading-text {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
  </style>
</head>
<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner"></div>
</div>

<!-- Theme Toggle Button -->
<div class="theme-toggle" id="themeToggle">
  <i class="bi bi-sun-fill" id="themeIcon"></i>
</div>
<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner"></div>
  <div class="loading-text">Generating report...</div>
</div>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm rounded-pill mx-3 mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="#"><i class="bi bi-heart-pulse-fill me-2"></i>Report Analytics</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarContent">
      <div class="navbar-nav ms-auto align-items-center">
        <span class="nav-link">Today: <i class="bi bi-calendar-event me-1"></i> <?= date('M d, Y') ?></span>
        <a href="telegram_bot.php" class="nav-link" style= "color:green;"><i class="bi bi-telegram me-1"></i> Send To Telegram Settings</a>
      </div>
    </div>
  </div>
</nav>

<div class="container-fluid">

  <!-- Filters -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card filters-card border-0">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0 text-white"><i class="bi bi-funnel-fill me-2"></i> Dashboard Filters</h5>
            <div class="export-buttons">
              <button class="export-btn me-2" onclick="exportData('pdf')"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</button>
              <button class="export-btn me-2" onclick="exportData('excel')"><i class="bi bi-file-earmark-excel me-1"></i> Excel</button>
            </div>
          </div>
          
          <form method="GET" id="filterForm" class="row g-3">
            <div class="col-12 col-md-6 col-lg-2">
              <div class="filter-section">
                <div class="filter-title"><i class="bi bi-calendar"></i> Preset Ranges</div>
                <select class="form-select modern-select" name="preset_range" id="presetRange">
                  <option value="">Custom Range</option>
                  <option value="today" <?= $presetRange==='today'?'selected':'' ?>>Today</option>
                  <option value="yesterday" <?= $presetRange==='yesterday'?'selected':'' ?>>Yesterday</option>
                  <option value="last_7_days" <?= $presetRange==='last_7_days'?'selected':'' ?>>Last 7 Days</option>
                  <option value="last_30_days" <?= $presetRange==='last_30_days'?'selected':'' ?>>Last 30 Days</option>
                  <option value="this_month" <?= $presetRange==='this_month'?'selected':'' ?>>This Month</option>
                  <option value="last_month" <?= $presetRange==='last_month'?'selected':'' ?>>Last Month</option>
                  <option value="this_quarter" <?= $presetRange==='this_quarter'?'selected':'' ?>>This Quarter</option>
                  <option value="this_year" <?= $presetRange==='this_year'?'selected':'' ?>>This Year</option>
                </select>
              </div>
            </div>
            
            <div class="col-12 col-md-6 col-lg-2">
              <div class="filter-section">
                <div class="filter-title"><i class="bi bi-calendar"></i> Date Range</div>
                <div class="input-group">
                  <input type="date" class="form-control modern-select" name="start_date" id="startDate" value="<?= $startDate ?>">
                  <span class="input-group-text">to</span>
                  <input type="date" class="form-control modern-select" name="end_date" id="endDate" value="<?= $endDate ?>">
                </div>
              </div>
            </div>
            
            <div class="col-12 col-md-6 col-lg-2">
              <div class="filter-section">
                <div class="filter-title"><i class="bi bi-tag"></i> Service Type</div>
                <select class="form-select modern-select" name="service_filter" id="serviceFilter">
                  <option value="all" <?= $serviceFilter==='all'?'selected':'' ?>>All Services</option>
                  <option value="paid" <?= $serviceFilter==='paid'?'selected':'' ?>>Paid Only</option>
                  <option value="free" <?= $serviceFilter==='free'?'selected':'' ?>>Free Only</option>
                </select>
              </div>
            </div>
            
            <div class="col-12 col-md-6 col-lg-2">
              <div class="filter-section">
                <div class="filter-title"><i class="bi bi-credit-card"></i> Payment Method</div>
                <select class="form-select modern-select" name="payment_filter" id="paymentFilter">
                  <option value="all" <?= $paymentFilter==='all'?'selected':'' ?>>All Methods</option>
                  <option value="cash" <?= $paymentFilter==='cash'?'selected':'' ?>>Cash</option>
                  <option value="mobile" <?= $paymentFilter==='mobile'?'selected':'' ?>>Mobile</option>
                  <option value="card" <?= $paymentFilter==='card'?'selected':'' ?>>Card</option>
                </select>
              </div>
            </div>
            
            <div class="col-12 col-md-6 col-lg-2">
              <div class="filter-section">
                <div class="filter-title"><i class="bi bi-calendar-month"></i> Month</div>
                <select class="form-select modern-select" name="month_filter" id="monthFilter">
                  <option value="all" <?= $monthFilter==='all'?'selected':'' ?>>All Months</option>
                  <option value="01" <?= $monthFilter==='01'?'selected':'' ?>>January</option>
                  <option value="02" <?= $monthFilter==='02'?'selected':'' ?>>February</option>
                  <option value="03" <?= $monthFilter==='03'?'selected':'' ?>>March</option>
                  <option value="04" <?= $monthFilter==='04'?'selected':'' ?>>April</option>
                  <option value="05" <?= $monthFilter==='05'?'selected':'' ?>>May</option>
                  <option value="06" <?= $monthFilter==='06'?'selected':'' ?>>June</option>
                  <option value="07" <?= $monthFilter==='07'?'selected':'' ?>>July</option>
                  <option value="08" <?= $monthFilter==='08'?'selected':'' ?>>August</option>
                  <option value="09" <?= $monthFilter==='09'?'selected':'' ?>>September</option>
                  <option value="10" <?= $monthFilter==='10'?'selected':'' ?>>October</option>
                  <option value="11" <?= $monthFilter==='11'?'selected':'' ?>>November</option>
                  <option value="12" <?= $monthFilter==='12'?'selected':'' ?>>December</option>
                </select>
              </div>
            </div>
            
            <div class="col-12 col-md-6 col-lg-2">
              <div class="filter-section">
                <div class="filter-title"><i class="bi bi-calendar-year"></i> Year</div>
                <select class="form-select modern-select" name="year_filter" id="yearFilter">
                  <option value="all" <?= $yearFilter==='all'?'selected':'' ?>>All Years</option>
                  <?php foreach ($availableYears as $year): ?>
                    <option value="<?= $year ?>" <?= $yearFilter==$year?'selected':'' ?>><?= $year ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            
            <div class="col-12 col-md-6 col-lg-2">
              <div class="filter-section">
                <div class="filter-title"><i class="bi bi-bar-chart"></i> Period</div>
                <select class="form-select modern-select" name="period" id="periodFilter">
                  <option value="daily" <?= $period==='daily'?'selected':'' ?>>Daily</option>
                  <option value="weekly" <?= $period==='weekly'?'selected':'' ?>>Weekly</option>
                  <option value="monthly" <?= $period==='monthly'?'selected':'' ?>>Monthly</option>
                  <option value="yearly" <?= $period==='yearly'?'selected':'' ?>>Yearly</option>
                </select>
              </div>
            </div>
            
            <div class="col-12 col-md-6 col-lg-2 d-flex align-items-end">
              <div class="d-grid gap-2 w-100">
                <button type="submit" class="btn btn-modern"><i class="bi bi-filter-circle me-3"></i> Apply Filters</button>
                <a href="reports.php" class="btn btn-outline-light rounded-pill"><i class="bi bi-arrow-clockwise me-1"></i> Reset</a>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Metrics -->
  <div class="row mb-4 g-3" id="metricsContainer">
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card metric-card border-0">
        <div class="card-body text-center p-3">
          <div class="metric-icon text-primary">
            <i class="bi bi-cash-coin"></i>
          </div>
          <p class="metric-value text-primary">ETB <?= number_format($metrics['total_revenue'],2) ?></p>
          <p class="metric-label">Revenue</p>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card metric-card border-0">
        <div class="card-body text-center p-3">
          <div class="metric-icon text-success">
            <i class="bi bi-graph-up-arrow"></i>
          </div>
          <p class="metric-value text-success">ETB <?= number_format($metrics['total_profit'],2) ?></p>
          <p class="metric-label">Profit</p>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card metric-card border-0">
        <div class="card-body text-center p-3">
          <div class="metric-icon text-warning">
            <i class="bi bi-receipt"></i>
          </div>
          <p class="metric-value text-warning"><?= number_format($metrics['unique_orders']) ?></p>
          <p class="metric-label">Orders</p>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card metric-card border-0">
        <div class="card-body text-center p-3">
          <div class="metric-icon text-info">
            <i class="bi bi-box-seam"></i>
          </div>
          <p class="metric-value text-info"><?= number_format($metrics['total_quantity']) ?></p>
          <p class="metric-label">Items Sold</p>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card metric-card border-0">
        <div class="card-body text-center p-2">
          <div class="metric-icon text-primary">
            <i class="bi bi-boxes"></i>
          </div>
          <p class="metric-value text-primary">ETB <?= number_format($stockCapital, 2) ?></p>
          <p class="metric-label">Stock Capital</p>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card metric-card border-0">
        <div class="card-body text-center p-2">
          <div class="metric-icon text-danger">
            <i class="bi bi-currency-exchange"></i>
          </div>
          <p class="metric-value text-success">ETB <?= number_format($potentialProfit, 2) ?></p>
          <p class="metric-label">Potential Profit</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Charts Row -->
  <div class="row mb-4">
    <div class="col-xl-8">
      <div class="card enhanced-card border-0">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-bar-chart-line-fill me-2"></i> Revenue & Profit Trends (<?= ucfirst($period) ?>)</h5>
        </div>
        <div class="card-body">
          <div class="chart-container">
            <canvas id="trendChart"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card enhanced-card border-0">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-pie-chart-fill me-2"></i> Service Distribution</h5>
        </div>
        <div class="card-body">
          <div class="chart-container" style="height:300px;">
            <canvas id="serviceChart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Category Analysis -->
  <div class="row mb-4">
    <div class="col-lg-6">
      <div class="card enhanced-card border-0">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-tags-fill me-2"></i> Category Sales Analysis (Top 10)</h5>
        </div>
        <div class="card-body">
          <div class="chart-container" style="height:300px;">
            <canvas id="categoryChart"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card enhanced-card border-0">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-list-ol me-2"></i> Top Categories (Top 10)</h5>
        </div>
        <div class="card-body table-responsive" style="max-height: 350px;">
          <table class="table table-hover">
            <thead class="table-dark">
              <tr><th>Category</th><th>Orders</th><th>Qty Sold</th><th>Revenue</th><th>Profit</th><th>Margin %</th></tr>
            </thead>
            <tbody>
              <?php 
              // Limit to top 10 categories
              $topCategories = array_slice($categoryAnalysis, 0, 10);
              foreach ($topCategories as $cat): ?>
              <tr>
                <td><strong><?= htmlspecialchars($cat['category']) ?></strong></td>
                <td><?= number_format($cat['total_orders']) ?></td>
                <td><?= number_format($cat['total_quantity_sold']) ?></td>
                <td class="text-primary">ETB <?= number_format($cat['total_revenue'], 2) ?></td>
                <td class="text-success">ETB <?= number_format($cat['total_profit'], 2) ?></td>
                <td><span class="badge bg-<?= $cat['profit_margin_percent'] > 20 ? 'success' : ($cat['profit_margin_percent'] > 10 ? 'warning' : 'danger') ?>"><?= number_format($cat['profit_margin_percent'], 1) ?>%</span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Service Analysis -->
  <div class="row mb-4">
    <div class="col-md-6">
      <div class="card enhanced-card border-0">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-bar-chart-fill me-2"></i> Service Analysis</h5>
        </div>
        <div class="card-body">
          <?php foreach ($serviceAnalysis as $type => $d): ?>
          <div class="mb-3 p-3 rounded-3" style="background-color: <?= $type==='Paid'?'rgba(108, 92, 231, 0.1)':'rgba(253, 203, 110, 0.1)' ?>;">
            <h6 class="d-flex align-items-center">
              <span class="badge bg-<?= $type==='Paid'?'primary':'warning' ?> me-2"><?= $type ?></span>
             <p class="metric-label"> Services Performance </p>
            </h6>
            <div class="row text-center mb-2">
              <div class="col-6"><div class="fw-bold text-success">ETB <?= number_format($d['revenue'],0) ?></div><p class="metric-label">Revenue</p></div>
              <div class="col-6"><div class="fw-bold text-primary">ETB <?= number_format($d['profit'],0) ?></div><p class="metric-label">Profit</p></div>
            </div>
            <div class="row text-center">
              <div class="col-6"><div class="fw-bold text-warning"><?= number_format($d['orders']) ?></div><p class="metric-label">Orders</p></div>
              <div class="col-6"><div class="fw-bold text-info"><?= number_format($d['quantity']) ?></div><p class="metric-label">Items</p></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    
    <!-- Stock Summary -->
    <div class="col-md-6">
      <div class="card enhanced-card border-0">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-box2-fill me-2"></i> Stock Summary</h5>
        </div>
        <div class="card-body">
          <div class="row text-center mb-4">
            <div class="col-6">
              <div class="p-3 rounded-3" style="background-color: rgba(108, 92, 231, 0.1);">
                <h3 class="text-primary">ETB <?= number_format($stockCapital, 2) ?></h3>
                <p class="metric-label">Stock Capital Value</p>
              </div>
            </div>
            <div class="col-6">
              <div class="p-3 rounded-3" style="background-color: rgba(0, 206, 201, 0.1);">
                <h3 class="text-info">ETB <?= number_format($stockValue, 2) ?></h3>
                <p class="metric-label">Stock Market Value</p>
              </div>
            </div>
          </div>
          <div class="p-3 rounded-3" style="background-color: rgba(0, 184, 148, 0.1);">
            <h3 class="text-success text-center">ETB <?= number_format($potentialProfit, 2) ?></h3>
            <p class="metric-label text-center">Potential Profit from Stock</p>
            <div class="progress mt-2" style="height: 10px;">
              <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, ($potentialProfit/($stockCapital+1))*100) ?>%" aria-valuenow="<?= ($potentialProfit/($stockCapital+1))*100 ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <p class="metric-label text-center">Profit Margin: <?= number_format(($potentialProfit/($stockCapital+1))*100, 2) ?>%</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Low Stock & Expiration Analysis -->
  <div class="row mb-4">
    <div class="col-lg-6">
      <div class="card enhanced-card border-0">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i> Low Stock Alert</h5>
        </div>
        <div class="card-body table-responsive" style="max-height: 400px;">
          <table class="table table-hover">
            <thead class="table-dark">
              <tr><th>Product</th><th>Category</th><th>Remaining</th><th>Status</th><th>Value</th></tr>
            </thead>
            <tbody>
              <?php 
              // Limit to top 10 low stock items
              $topLowStockItems = array_slice($lowStockItems, 0);
              foreach ($topLowStockItems as $item): ?>
              <tr class="<?= 'alert-'.strtolower(str_replace(' ','-',$item['stock_level'])) ?>">
                <td>
                  <strong><?= htmlspecialchars($item['medicine_name']) ?></strong>
                  <br><small class="text-muted"><?= htmlspecialchars($item['product_code']) ?></small>
                </td>
                <td><?= htmlspecialchars($item['category']) ?></td>
                <td><?= number_format($item['remain_quantity']) ?></td>
                <td>
                  <span class="badge 
                    <?= $item['stock_level'] === 'Out of Stock' ? 'bg-danger' : 
                        ($item['stock_level'] === 'Critical Low' ? 'bg-warning' : 'bg-info') ?>">
                    <?= htmlspecialchars($item['stock_level']) ?>
                  </span>
                </td>
                <td>ETB <?= number_format($item['selling_price'] * $item['remain_quantity'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Expiration Analysis -->
    <div class="col-lg-6">
      <div class="card enhanced-card border-0">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-calendar-x-fill me-2"></i> Expiration Analysis</h5>
        </div>
        <div class="card-body table-responsive" style="max-height: 400px;">
          <table class="table table-hover">
            <thead class="table-dark">
              <tr><th>Product</th><th>Category</th><th>Expire Date</th><th>Days Left</th><th>Status</th><th>Stock</th></tr>
            </thead>
            <tbody>
              <?php 
              // expiration items
              $topExpirationItems = array_slice($expirationAnalysis, 0);
              foreach ($topExpirationItems as $item): ?>
              <tr class="<?= 'alert-'.strtolower(str_replace(' ','-',$item['expiration_status'])) ?>">
                <td>
                  <strong><?= htmlspecialchars($item['medicine_name']) ?></strong>
                  <br><small class="text-muted"><?= htmlspecialchars($item['product_code']) ?></small>
                </td>
                <td><?= htmlspecialchars($item['category']) ?></td>
                <td><?= date('M d, Y', strtotime($item['expire_date'])) ?></td>
                <td>
                  <?php if ($item['days_remaining'] < 0): ?>
                    <span class="text-danger"><?= abs($item['days_remaining']) ?> days ago</span>
                  <?php else: ?>
                    <span class="text-<?= $item['alert_level'] ?>"><?= $item['days_remaining'] ?> days</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-<?= $item['alert_level'] ?>">
                    <?= htmlspecialchars($item['expiration_status']) ?>
                  </span>
                </td>
                <td><?= number_format($item['remain_quantity']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Time Period Products -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card enhanced-card border-0">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> <?= ucfirst($period) ?> Top-Selling Products (Top 10)</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="timePeriodTable" class="table table-hover" style="width:100%">
              <thead class="table-dark">
                <tr>
                  <th>Period</th>
                  <th>Product</th>
                  <th>Category</th>
                  <th>Manufacturer</th>
                  <th>Qty Sold</th>
                  <th>Revenue</th>
                  <th>Profit</th>
                  <th>Orders</th>
                  <th>Avg Price</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                // Limit to top 10 time period products
                $topTimePeriodProducts = array_slice($timePeriodProducts, 0, 10);
                foreach ($topTimePeriodProducts as $product): ?>
                <tr>
                  <td><span class="badge bg-info"><?= htmlspecialchars($product['time_period']) ?></span></td>
                  <td><strong><?= htmlspecialchars($product['medicine_name']) ?></strong></td>
                  <td><?= htmlspecialchars($product['category']) ?></td>
                  <td><?= htmlspecialchars($product['manufacturer']) ?></td>
                  <td><?= number_format($product['total_quantity_sold']) ?></td>
                  <td class="text-primary">ETB <?= number_format($product['total_revenue'], 2) ?></td>
                  <td class="text-success">ETB <?= number_format($product['total_profit'], 2) ?></td>
                  <td><?= number_format($product['order_count']) ?></td>
                  <td>ETB <?= number_format($product['avg_selling_price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Top Products -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card enhanced-card border-0">
        <div class="card-header section-header">
          <h5 class="mb-0"><i class="bi bi-trophy-fill me-2"></i> Top Products (Top 10)</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table id="topProductsTable" class="table table-hover" style="width:100%">
              <thead class="table-dark">
                <tr>
                  <th>Product</th>
                  <th>Category</th>
                  <th>Qty</th>
                  <th>Capital</th>
                  <th>Revenue</th>
                  <th>Profit</th>
                  <th>Margin</th>
                  <th>Sold By</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                // Limit to top 10 products
                $topProducts = array_slice($topProducts, 0, 10);
                foreach ($topProducts as $p): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($p['medicine_name']) ?></strong>
                    <br><small class="text-muted"><?= htmlspecialchars($p['manufacturer']) ?></small>
                    <?php if (!empty($p['sellers'])): ?>
                      <ul class="mt-1 mb-0 small text-muted">
                        <?php foreach ($p['sellers'] as $seller => $info): ?>
                          <li><?= htmlspecialchars($seller) ?> (<?= number_format($info['qty']) ?> sold, <?= number_format($info['percent'],1) ?>%)</li>
                        <?php endforeach; ?>
                      </ul>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($p['category']) ?></td>
                  <td><?= number_format($p['total_quantity']) ?></td>
                  <td>ETB <?= number_format($p['avg_cost_price'] * $p['total_quantity'], 2) ?></td>
                  <td class="text-primary">ETB <?= number_format($p['total_revenue'], 2) ?></td>
                  <td class="text-success">ETB <?= number_format($p['total_profit'], 2) ?></td>
                  <td><span class="badge bg-<?= $p['profit_margin'] > 20 ? 'success' : ($p['profit_margin'] > 10 ? 'warning' : 'danger') ?>"><?= number_format($p['profit_margin'], 1) ?>%</span></td>
                  <td><span class="badge bg-secondary">Summary</span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript for Charts -->
<script>
// Trend Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendLabels = <?= json_encode(array_column($trendData, 'period')) ?>;
const trendRevenue = <?= json_encode(array_column($trendData, 'revenue')) ?>;
const trendProfit = <?= json_encode(array_column($trendData, 'profit')) ?>;

const trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Revenue',
            data: trendRevenue,
            borderColor: '#6c5ce7',
            backgroundColor: 'rgba(108, 92, 231, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#6c5ce7',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: '#6c5ce7'
        }, {
            label: 'Profit',
            data: trendProfit,
            borderColor: '#00cec9',
            backgroundColor: 'rgba(0, 206, 201, 0.1)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#00cec9',
            pointBorderColor: '#fff',
            pointHoverBackgroundColor: '#fff',
            pointHoverBorderColor: '#00cec9'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: 20
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(context) {
                        return context.dataset.label + ': ETB ' + context.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    drawBorder: false
                },
                ticks: {
                    callback: function(value) {
                        return 'ETB ' + value.toLocaleString();
                    }
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Service Chart
const serviceCtx = document.getElementById('serviceChart').getContext('2d');
const serviceData = <?= json_encode($serviceAnalysis) ?>;
const serviceLabels = Object.keys(serviceData);
const serviceRevenues = serviceLabels.map(label => serviceData[label].revenue);

const serviceChart = new Chart(serviceCtx, {
    type: 'doughnut',
    data: {
        labels: serviceLabels,
        datasets: [{
            data: serviceRevenues,
            backgroundColor: ['#6c5ce7', '#fdcb6e'],
            borderWidth: 0,
            hoverOffset: 15
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    usePointStyle: true,
                    padding: 20
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ETB ' + context.parsed.toLocaleString();
                    }
                }
            }
        }
    }
});

// Category Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryData = <?= json_encode($categoryAnalysis) ?>;
// Limit to top 10 categories for the chart
const topCategoryData = categoryData.slice(0, 10);
const categoryLabels = topCategoryData.map(cat => cat.category);
const categoryRevenues = topCategoryData.map(cat => parseFloat(cat.total_revenue));

const categoryChart = new Chart(categoryCtx, {
    type: 'bar',
    data: {
        labels: categoryLabels,
        datasets: [{
            label: 'Revenue',
            data: categoryRevenues,
            backgroundColor: 'rgba(108, 92, 231, 0.8)',
            borderColor: '#6c5ce7',
            borderWidth: 0,
            borderRadius: 6,
            hoverBackgroundColor: 'rgba(108, 92, 231, 1)'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return 'ETB ' + context.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    drawBorder: false
                },
                ticks: {
                    callback: function(value) {
                        return 'ETB ' + value.toLocaleString();
                    }
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});

// Theme toggle functionality
const themeToggleBtn = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');
const htmlElement = document.documentElement;
// Update loading overlay theme when theme changes
function updateLoadingOverlayTheme() {
    const isDark = htmlElement.getAttribute('data-theme') === 'dark';
    const loadingOverlay = document.getElementById('loadingOverlay');
    
    if (isDark) {
        loadingOverlay.setAttribute('data-theme', 'dark');
    } else {
        loadingOverlay.removeAttribute('data-theme');
    }
}

// Call this function when theme changes
updateLoadingOverlayTheme();
// Check for saved theme preference or respect OS preference
if (localStorage.getItem('theme') === 'dark' || 
    (window.matchMedia('(prefers-color-scheme: dark)').matches && !localStorage.getItem('theme'))) {
    htmlElement.setAttribute('data-theme', 'dark');
    themeIcon.classList.remove('bi-sun-fill');
    themeIcon.classList.add('bi-moon-fill');
} else {
    htmlElement.removeAttribute('data-theme');
    themeIcon.classList.remove('bi-moon-fill');
    themeIcon.classList.add('bi-sun-fill');
}

// Listen for toggle changes
themeToggleBtn.addEventListener('click', function() {
    if (htmlElement.getAttribute('data-theme') === 'dark') {
        htmlElement.removeAttribute('data-theme');
        localStorage.setItem('theme', 'light');
        themeIcon.classList.remove('bi-moon-fill');
        themeIcon.classList.add('bi-sun-fill');
    } else {
        htmlElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('theme', 'dark');
        themeIcon.classList.remove('bi-sun-fill');
        themeIcon.classList.add('bi-moon-fill');
    }
    
    // Update charts to match theme
    updateChartThemes();
});

// Function to update chart colors based on theme
function updateChartThemes() {
    const isDark = htmlElement.getAttribute('data-theme') === 'dark';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
    const textColor = isDark ? '#f1f5f9' : '#2d3748';
    
    // Update all charts
    [trendChart, serviceChart, categoryChart].forEach(chart => {
        chart.options.plugins.legend.labels.color = textColor;
        chart.options.scales.x.ticks.color = textColor;
        chart.options.scales.y.ticks.color = textColor;
        chart.options.scales.x.grid.color = gridColor;
        chart.options.scales.y.grid.color = gridColor;
        chart.update();
    });
}

// Initialize chart themes
updateChartThemes();
</script>

<!-- Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTables with export buttons
    $('#timePeriodTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        order: [[4, 'desc']], // Sort by quantity sold by default
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search products..."
        },
        dom: 'Bfrtip',
        buttons: [
            'copy', 'excel', 'pdf', 'print'
        ]
    });
    
    $('#topProductsTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        order: [[2, 'desc']], // Sort by quantity by default
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search products..."
        },
        dom: 'Bfrtip',
        buttons: [
            'copy', 'excel', 'pdf', 'print'
        ]
    });
    
    // Preset range handler
    $('#presetRange').change(function() {
        const preset = $(this).val();
        if (preset) {
            $('#filterForm').submit();
        }
    });
    
    // Regular form submission instead of AJAX for now
    // This fixes the AJAX error by using standard form submission
    $('#filterForm').submit(function(e) {
        // Show loading overlay
        $('#loadingOverlay').fadeIn();
        
        // Let the form submit normally
        return true;
    });
});

// Function to export data
function exportData(format) {
    // Get current filter values
    const filters = {
        start_date: $('#startDate').val(),
        end_date: $('#endDate').val(),
        service_filter: $('#serviceFilter').val(),
        payment_filter: $('#paymentFilter').val(),
        month_filter: $('#monthFilter').val(),
        year_filter: $('#yearFilter').val(),
        period: $('#periodFilter').val(),
        preset_range: $('#presetRange').val(),
        format: format
    };
    
    // Show loading overlay
    $('#loadingOverlay').fadeIn();
    
    // Build export URL
    let exportUrl = 'export_data.php';
    let firstParam = true;
    
    for (const key in filters) {
        if (filters[key]) {
            exportUrl += (firstParam ? '?' : '&') + key + '=' + encodeURIComponent(filters[key]);
            firstParam = false;
        }
    }
    
    // Create a temporary iframe for download
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = exportUrl;
    document.body.appendChild(iframe);
    
    // Remove the iframe after a delay
    setTimeout(function() {
        document.body.removeChild(iframe);
        $('#loadingOverlay').fadeOut();
    }, 3000);
}
  
// Logout confirmation
$(".logout").click(function(e){
  e.preventDefault();
  if(confirm("Are you sure you want to logout?")){
    localStorage.removeItem('productMultiplier');
    window.location.href = '../../logout.php';
  }
});
</script>
<br>
<br>
<?php include 'footer.php'; ?>
</body>
</html>