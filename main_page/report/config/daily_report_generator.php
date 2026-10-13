<?php
/**
 * Daily Report Generator
 * 
 * This file contains functions specifically for generating
 * comprehensive daily reports with all analytics data.
 */

if (!function_exists('getSalesData')) {
    require_once __DIR__ . '/analytics_functions_demo.php';
}
/**
 * Generate detailed daily report with enhanced analytics
 */
function generateDetailedDailyReport($con, $date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $startDate = $date;
    $endDate = $date;
    
    // Basic sales data
    $salesData = getSalesData($con, $startDate, $endDate);
    $metrics = getDashboardMetrics($salesData, $con);
    
    // Enhanced analytics
    $categoryAnalysis = getCategorySalesAnalysis($con, $startDate, $endDate);
    $topProducts = getTopProducts($salesData, 10);
    $lowStockItems = getLowStockItems($con, 10);
    $expirationAnalysis = getExpirationAnalysis($con, 30);
    $serviceAnalysis = getServiceAnalysis($salesData);
    
    // Alerts and notifications
    $alerts = generateAlerts($lowStockItems, $expirationAnalysis);
    
    // Performance insights
    $insights = generatePerformanceInsights($metrics, $categoryAnalysis, $topProducts);
    
    return [
        'report_date' => $date,
        'generated_at' => date('Y-m-d H:i:s'),
        'metrics' => $metrics,
        'sales_data' => $salesData,
        'category_analysis' => $categoryAnalysis,
        'top_products' => $topProducts,
        'service_analysis' => $serviceAnalysis,
        'low_stock_items' => $lowStockItems,
        'expiration_analysis' => $expirationAnalysis,
        'alerts' => $alerts,
        'insights' => $insights,
        'summary' => [
            'total_sales_transactions' => count($salesData),
            'categories_with_sales' => count($categoryAnalysis),
            'unique_products_sold' => count($topProducts),
            'critical_alerts_count' => count($alerts['critical']),
            'warning_alerts_count' => count($alerts['warnings'])
        ]
    ];
}

/**
 * Generate alerts based on stock and expiration data
 */
function generateAlerts($lowStockItems, $expirationAnalysis) {
    $critical = [];
    $warnings = [];
    $info = [];
    
    // Critical stock alerts (out of stock or very low)
    foreach ($lowStockItems as $item) {
        if ($item['remain_quantity'] == 0) {
            $critical[] = [
                'type' => 'out_of_stock',
                'product' => $item['medicine_name'],
                'message' => $item['medicine_name'] . ' is completely out of stock',
                'severity' => 'critical',
                'data' => $item
            ];
        } elseif ($item['remain_quantity'] <= 3) {
            $critical[] = [
                'type' => 'critical_low_stock',
                'product' => $item['medicine_name'],
                'message' => $item['medicine_name'] . ' has only ' . $item['remain_quantity'] . ' units left',
                'severity' => 'critical',
                'data' => $item
            ];
        } elseif ($item['remain_quantity'] <= 10) {
            $warnings[] = [
                'type' => 'low_stock',
                'product' => $item['medicine_name'],
                'message' => $item['medicine_name'] . ' is running low (' . $item['remain_quantity'] . ' units)',
                'severity' => 'warning',
                'data' => $item
            ];
        }
    }
    
    // Expiration alerts
    foreach ($expirationAnalysis as $item) {
        if ($item['days_remaining'] < 0) {
            $critical[] = [
                'type' => 'expired',
                'product' => $item['medicine_name'],
                'message' => $item['medicine_name'] . ' expired ' . abs($item['days_remaining']) . ' days ago',
                'severity' => 'critical',
                'data' => $item
            ];
        } elseif ($item['days_remaining'] <= 3) {
            $critical[] = [
                'type' => 'expiring_soon',
                'product' => $item['medicine_name'],
                'message' => $item['medicine_name'] . ' expires in ' . $item['days_remaining'] . ' days',
                'severity' => 'critical',
                'data' => $item
            ];
        } elseif ($item['days_remaining'] <= 7) {
            $warnings[] = [
                'type' => 'expiring_this_week',
                'product' => $item['medicine_name'],
                'message' => $item['medicine_name'] . ' expires in ' . $item['days_remaining'] . ' days',
                'severity' => 'warning',
                'data' => $item
            ];
        } elseif ($item['days_remaining'] <= 30) {
            $info[] = [
                'type' => 'expiring_this_month',
                'product' => $item['medicine_name'],
                'message' => $item['medicine_name'] . ' expires in ' . $item['days_remaining'] . ' days',
                'severity' => 'info',
                'data' => $item
            ];
        }
    }
    
    return [
        'critical' => $critical,
        'warnings' => $warnings,
        'info' => $info,
        'total_alerts' => count($critical) + count($warnings) + count($info)
    ];
}

/**
 * Generate performance insights
 */
function generatePerformanceInsights($metrics, $categoryAnalysis, $topProducts) {
    $insights = [];
    
    // Revenue insights
    if ($metrics['total_revenue'] > 0) {
        $insights['revenue'] = [
            'message' => 'Daily revenue of ETB ' . number_format($metrics['total_revenue'], 2) . ' generated',
            'profit_margin' => round($metrics['profit_margin'], 1) . '% profit margin',
            'status' => $metrics['profit_margin'] > 20 ? 'excellent' : ($metrics['profit_margin'] > 10 ? 'good' : 'needs_improvement')
        ];
    } else {
        $insights['revenue'] = [
            'message' => 'No sales recorded today',
            'status' => 'attention_needed'
        ];
    }
    
    // Category performance
    if (!empty($categoryAnalysis)) {
        $topCategory = $categoryAnalysis[0];
        $insights['top_category'] = [
            'category' => $topCategory['category'],
            'revenue' => $topCategory['total_revenue'],
            'message' => $topCategory['category'] . ' was the top-performing category with ETB ' . number_format($topCategory['total_revenue'], 2)
        ];
    }
    
    // Product performance
    if (!empty($topProducts)) {
        $topProduct = $topProducts[0];
        $insights['top_product'] = [
            'product' => $topProduct['medicine_name'],
            'quantity' => $topProduct['total_quantity'],
            'revenue' => $topProduct['total_revenue'],
            'message' => $topProduct['medicine_name'] . ' was the best seller with ' . $topProduct['total_quantity'] . ' units sold'
        ];
    }
    
    // Order insights
    if ($metrics['unique_orders'] > 0) {
        $insights['orders'] = [
            'count' => $metrics['unique_orders'],
            'average_value' => $metrics['avg_order_value'],
            'message' => $metrics['unique_orders'] . ' orders processed with average value of ETB ' . number_format($metrics['avg_order_value'], 2)
        ];
    }
    
    return $insights;
}

/**
 * Generate formatted text report
 */
function generateTextReport($reportData) {
    $report = "=== DAILY DRUG STORE REPORT ===\n";
    $report .= "Date: " . $reportData['report_date'] . "\n";
    $report .= "Generated: " . $reportData['generated_at'] . "\n\n";
    
    // Financial Summary
    $report .= "FINANCIAL SUMMARY:\n";
    $report .= "- Revenue: ETB " . number_format($reportData['metrics']['total_revenue'], 2) . "\n";
    $report .= "- Profit: ETB " . number_format($reportData['metrics']['total_profit'], 2) . "\n";
    $report .= "- Profit Margin: " . round($reportData['metrics']['profit_margin'], 1) . "%\n";
    $report .= "- Orders: " . $reportData['metrics']['unique_orders'] . "\n";
    $report .= "- Items Sold: " . $reportData['metrics']['total_quantity'] . "\n\n";
    
    // Top Categories
    if (!empty($reportData['category_analysis'])) {
        $report .= "TOP CATEGORIES:\n";
        foreach (array_slice($reportData['category_analysis'], 0, 5) as $i => $cat) {
            $report .= ($i+1) . ". " . $cat['category'] . " - ETB " . number_format($cat['total_revenue'], 2) . "\n";
        }
        $report .= "\n";
    }
    
    // Top Products
    if (!empty($reportData['top_products'])) {
        $report .= "TOP PRODUCTS:\n";
        foreach (array_slice($reportData['top_products'], 0, 5) as $i => $product) {
            $report .= ($i+1) . ". " . $product['medicine_name'] . " - " . $product['total_quantity'] . " sold\n";
        }
        $report .= "\n";
    }
    
    // Alerts
    if ($reportData['alerts']['total_alerts'] > 0) {
        $report .= "ALERTS:\n";
        if (!empty($reportData['alerts']['critical'])) {
            $report .= "Critical (" . count($reportData['alerts']['critical']) . "):\n";
            foreach ($reportData['alerts']['critical'] as $alert) {
                $report .= "- " . $alert['message'] . "\n";
            }
        }
        if (!empty($reportData['alerts']['warnings'])) {
            $report .= "Warnings (" . count($reportData['alerts']['warnings']) . "):\n";
            foreach ($reportData['alerts']['warnings'] as $alert) {
                $report .= "- " . $alert['message'] . "\n";
            }
        }
        $report .= "\n";
    }
    
    $report .= "=== END OF REPORT ===\n";
    
    return $report;
}

/**
 * Generate HTML report
 */
function generateHtmlReport($reportData) {
    $html = "<!DOCTYPE html><html><head><title>Daily Report - " . $reportData['report_date'] . "</title>";
    $html .= "<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #2563eb; color: white; padding: 20px; border-radius: 5px; }
        .metric { display: inline-block; margin: 10px; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .alert-critical { background: #fee; border-left: 4px solid #dc2626; padding: 10px; margin: 5px 0; }
        .alert-warning { background: #fffbeb; border-left: 4px solid #d97706; padding: 10px; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8f9fa; }
    </style></head><body>";
    
    $html .= "<div class='header'><h1>Daily Drug Store Report</h1>";
    $html .= "<p>Date: " . $reportData['report_date'] . " | Generated: " . $reportData['generated_at'] . "</p></div>";
    
    // Metrics
    $html .= "<h2>Financial Summary</h2>";
    $html .= "<div class='metric'><strong>Revenue</strong><br>ETB " . number_format($reportData['metrics']['total_revenue'], 2) . "</div>";
    $html .= "<div class='metric'><strong>Profit</strong><br>ETB " . number_format($reportData['metrics']['total_profit'], 2) . "</div>";
    $html .= "<div class='metric'><strong>Margin</strong><br>" . round($reportData['metrics']['profit_margin'], 1) . "%</div>";
    $html .= "<div class='metric'><strong>Orders</strong><br>" . $reportData['metrics']['unique_orders'] . "</div>";
    
    // Alerts
    if ($reportData['alerts']['total_alerts'] > 0) {
        $html .= "<h2>Alerts</h2>";
        foreach ($reportData['alerts']['critical'] as $alert) {
            $html .= "<div class='alert-critical'><strong>Critical:</strong> " . $alert['message'] . "</div>";
        }
        foreach ($reportData['alerts']['warnings'] as $alert) {
            $html .= "<div class='alert-warning'><strong>Warning:</strong> " . $alert['message'] . "</div>";
        }
    }
    
    // Categories
    if (!empty($reportData['category_analysis'])) {
        $html .= "<h2>Top Categories</h2><table><tr><th>Category</th><th>Revenue</th><th>Quantity</th><th>Orders</th></tr>";
        foreach (array_slice($reportData['category_analysis'], 0, 5) as $cat) {
            $html .= "<tr><td>" . $cat['category'] . "</td><td>ETB " . number_format($cat['total_revenue'], 2) . "</td>";
            $html .= "<td>" . $cat['total_quantity_sold'] . "</td><td>" . $cat['total_orders'] . "</td></tr>";
        }
        $html .= "</table>";
    }
    
    $html .= "</body></html>";
    
    return $html;
}
?>
