<?php
function getSalesData($con, $startDate = null, $endDate = null, $serviceFilter = 'all') {
    $sql = "
        SELECT 
            o.id as order_id,
            o.order_number,
            o.customer_name,
            o.customer_email,
            o.order_date,
            o.order_total,
            o.payment_method,
            o.status,
            o.free_service as order_free_service,
            oi.id as item_id,
            oi.product_id,
            oi.product_name as medicine_name,
            oi.product_price as selling_price,
            oi.quantity,
            oi.total_price as item_total,
            oi.free_service as item_free_service,
            uo.name as order_seller,
            ui.name as item_seller,
            s.actual_price,
            s.category,
            s.manufacturer,
            s.company,
            s.expire_date,
            s.quantity as stock_quantity,
            s.remain_quantity,
            (oi.product_price - s.actual_price) as profit_per_unit,
            ((oi.product_price - s.actual_price) * oi.quantity) as total_profit,
            CASE 
                WHEN s.actual_price > 0 THEN ((oi.product_price - s.actual_price) / s.actual_price * 100)
                ELSE 0 
            END as profit_margin_percent,
            CASE 
                WHEN o.free_service = 1 OR oi.free_service = 1 THEN 'Free'
                ELSE 'Paid'
            END as service_type
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN stock s ON oi.product_id = s.id
        LEFT JOIN tbl_users uo ON o.user_id = uo.id
        LEFT JOIN tbl_users ui ON oi.user_id = ui.id
        WHERE 1=1
    ";
    
    $params = [];
    if ($startDate) {
        $sql .= " AND DATE(o.order_date) >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $sql .= " AND DATE(o.order_date) <= ?";
        $params[] = $endDate;
    }
    if ($serviceFilter === 'paid') {
        $sql .= " AND (o.free_service = 0 AND oi.free_service = 0)";
    } elseif ($serviceFilter === 'free') {
        $sql .= " AND (o.free_service = 1 OR oi.free_service = 1)";
    }
    
    $sql .= " ORDER BY o.order_date DESC";
    
    $stmt = mysqli_prepare($con, $sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getDashboardMetrics($salesData, $con) {
    $totalRevenue = 0;
    $totalProfit = 0;
    $uniqueOrders = [];
    $totalQuantity = 0;
    
    foreach ($salesData as $row) {
        $totalRevenue += $row['item_total'];
        $totalProfit += $row['total_profit'];
        $uniqueOrders[$row['order_id']] = true;
        $totalQuantity += $row['quantity'];
    }
    
    $uniqueOrderCount = count($uniqueOrders);
    $avgOrderValue = $uniqueOrderCount > 0 ? $totalRevenue / $uniqueOrderCount : 0;
    $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue * 100) : 0;

    // Stock Capital & Profit
    $stockSql = "SELECT 
                    SUM(actual_price * quantity) as total_capital,
                    SUM((selling_price - actual_price) * quantity) as potential_profit
                 FROM stock";
    $res = mysqli_query($con, $stockSql);
    $stockRow = mysqli_fetch_assoc($res);

    return [
        'total_revenue' => $totalRevenue,
        'total_profit' => $totalProfit,
        'unique_orders' => $uniqueOrderCount,
        'total_quantity' => $totalQuantity,
        'avg_order_value' => $avgOrderValue,
        'profit_margin' => $profitMargin,
        'total_stock_capital' => $stockRow['total_capital'] ?? 0,
        'total_stock_profit' => $stockRow['potential_profit'] ?? 0
    ];
}

function getTopProducts($salesData, $limit = 10) {
    $products = [];
    
    foreach ($salesData as $row) {
        $productId = $row['product_id'];
        if (!isset($products[$productId])) {
            $products[$productId] = [
                'product_id' => $productId,
                'medicine_name' => $row['medicine_name'],
                'category' => $row['category'],
                'manufacturer' => $row['manufacturer'],
                'total_quantity' => 0,
                'total_revenue' => 0,
                'total_profit' => 0,
                'avg_selling_price' => 0,
                'avg_cost_price' => 0,
                'profit_margin' => 0,
                'sellers' => [] // sellers with qty
            ];
        }
        
        $products[$productId]['total_quantity'] += $row['quantity'];
        $products[$productId]['total_revenue'] += $row['item_total'];
        $products[$productId]['total_profit'] += $row['total_profit'];

        if ($row['item_seller']) {
            if (!isset($products[$productId]['sellers'][$row['item_seller']])) {
                $products[$productId]['sellers'][$row['item_seller']] = 0;
            }
            $products[$productId]['sellers'][$row['item_seller']] += $row['quantity'];
        }
    }
    
    foreach ($products as &$product) {
        $product['avg_selling_price'] = $product['total_quantity'] > 0 ? $product['total_revenue'] / $product['total_quantity'] : 0;
        $product['avg_cost_price'] = $product['total_quantity'] > 0 ? ($product['total_revenue'] - $product['total_profit']) / $product['total_quantity'] : 0;
        $product['profit_margin'] = $product['total_revenue'] > 0 ? ($product['total_profit'] / $product['total_revenue'] * 100) : 0;

        // Calculate seller percentage
        foreach ($product['sellers'] as $seller => $qty) {
            $product['sellers'][$seller] = [
                'qty' => $qty,
                'percent' => $product['total_quantity'] > 0 ? ($qty / $product['total_quantity'] * 100) : 0
            ];
        }
    }
    
    usort($products, function($a, $b) {
        return $b['total_revenue'] <=> $a['total_revenue'];
    });
    
    return array_slice($products, 0, $limit);
}

function getTrendData($salesData, $period = 'daily') {
    $trends = [];
    
    foreach ($salesData as $row) {
        $date = new DateTime($row['order_date']);
        switch ($period) {
            case 'daily': $key = $date->format('Y-m-d'); break;
            case 'weekly': $key = $date->format('Y-W'); break;
            case 'monthly': $key = $date->format('Y-m'); break;
            case 'yearly': $key = $date->format('Y'); break;
            default: $key = $date->format('Y-m-d');
        }
        
        if (!isset($trends[$key])) {
            $trends[$key] = [
                'period' => $key,
                'revenue' => 0,
                'profit' => 0,
                'orders' => [],
                'quantity' => 0,
                'sellers' => []
            ];
        }
        
        $trends[$key]['revenue'] += $row['item_total'];
        $trends[$key]['profit'] += $row['total_profit'];
        $trends[$key]['orders'][$row['order_id']] = true;
        $trends[$key]['quantity'] += $row['quantity'];
        if ($row['item_seller']) $trends[$key]['sellers'][] = $row['item_seller'];
    }
    
    foreach ($trends as &$trend) {
        $trend['orders'] = count($trend['orders']);
        $trend['sellers'] = array_unique($trend['sellers']);
    }
    
    ksort($trends);
    return array_values($trends);
}

function getServiceAnalysis($salesData) {
    $analysis = [
        'Paid' => ['revenue' => 0, 'profit' => 0, 'quantity' => 0, 'orders' => []],
        'Free' => ['revenue' => 0, 'profit' => 0, 'quantity' => 0, 'orders' => []]
    ];
    
    foreach ($salesData as $row) {
        $serviceType = $row['service_type'];
        $analysis[$serviceType]['revenue'] += $row['item_total'];
        $analysis[$serviceType]['profit'] += $row['total_profit'];
        $analysis[$serviceType]['quantity'] += $row['quantity'];
        $analysis[$serviceType]['orders'][$row['order_id']] = true;
    }
    
    foreach ($analysis as &$service) {
        $service['orders'] = count($service['orders']);
    }
    
    return $analysis;
}

function getStockStatus($con) {
    $sql = "
        SELECT 
            id,
            product_code,
            medicine_name,
            category,
            manufacturer,
            company,
            quantity as total_stock,
            used_quantity,
            remain_quantity,
            expire_date,
            actual_price,
            selling_price,
            profit_price,
            status,
            CASE 
                WHEN expire_date < CURDATE() THEN 'Expired'
                WHEN expire_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expiring Soon'
                WHEN remain_quantity <= 10 THEN 'Low Stock'
                ELSE 'Good'
            END as stock_alert
        FROM stock
        ORDER BY remain_quantity ASC, expire_date ASC
    ";
    
    $result = mysqli_query($con, $sql);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// NEW ENHANCED FUNCTIONS

/**
 * Get category-based sales analysis showing which category sells the most
 */
function getCategorySalesAnalysis($con, $startDate = null, $endDate = null) {
    $sql = "
        SELECT 
            s.category,
            COUNT(DISTINCT oi.order_id) as total_orders,
            SUM(oi.quantity) as total_quantity_sold,
            SUM(oi.total_price) as total_revenue,
            SUM((oi.product_price - s.actual_price) * oi.quantity) as total_profit,
            AVG(oi.product_price) as avg_selling_price,
            AVG(s.actual_price) as avg_cost_price,
            COUNT(DISTINCT oi.product_id) as unique_products,
            CASE 
                WHEN SUM(oi.total_price) > 0 THEN 
                    (SUM((oi.product_price - s.actual_price) * oi.quantity) / SUM(oi.total_price) * 100)
                ELSE 0 
            END as profit_margin_percent
        FROM order_items oi
        JOIN stock s ON oi.product_id = s.id
        JOIN orders o ON oi.order_id = o.id
        WHERE 1=1
    ";
    
    $params = [];
    if ($startDate) {
        $sql .= " AND DATE(o.order_date) >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $sql .= " AND DATE(o.order_date) <= ?";
        $params[] = $endDate;
    }
    
    $sql .= " GROUP BY s.category ORDER BY total_revenue DESC";
    
    $stmt = mysqli_prepare($con, $sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Get time-period based product sales with top-selling products
 */
function getTimePeriodProductSales($con, $period = 'daily', $startDate = null, $endDate = null, $limit = 10) {
    $dateFormat = match($period) {
        'daily' => '%Y-%m-%d',
        'weekly' => '%Y-%u',
        'monthly' => '%Y-%m',
        'yearly' => '%Y',
        default => '%Y-%m-%d'
    };
    
    $sql = "
        SELECT 
            DATE_FORMAT(o.order_date, '$dateFormat') as time_period,
            s.medicine_name,
            s.category,
            s.manufacturer,
            SUM(oi.quantity) as total_quantity_sold,
            SUM(oi.total_price) as total_revenue,
            SUM((oi.product_price - s.actual_price) * oi.quantity) as total_profit,
            COUNT(DISTINCT oi.order_id) as order_count,
            AVG(oi.product_price) as avg_selling_price
        FROM order_items oi
        JOIN stock s ON oi.product_id = s.id
        JOIN orders o ON oi.order_id = o.id
        WHERE 1=1
    ";
    
    $params = [];
    if ($startDate) {
        $sql .= " AND DATE(o.order_date) >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $sql .= " AND DATE(o.order_date) <= ?";
        $params[] = $endDate;
    }
    
    $sql .= " GROUP BY time_period, oi.product_id 
              ORDER BY time_period DESC, total_quantity_sold DESC 
              LIMIT ?";
    $params[] = $limit;
    
    $stmt = mysqli_prepare($con, $sql);
    if ($params) {
        $types = str_repeat('s', count($params) - 1) . 'i';
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Get low stock items (quantity < 10)
 */
function getLowStockItems($con, $threshold = 10) {
    $sql = "
        SELECT 
            id,
            product_code,
            medicine_name,
            category,
            manufacturer,
            company,
            quantity as total_stock,
            used_quantity,
            remain_quantity,
            actual_price,
            selling_price,
            profit_price,
            expire_date,
            status,
            CASE 
                WHEN remain_quantity = 0 THEN 'Out of Stock'
                WHEN remain_quantity <= 5 THEN 'Critical Low'
                WHEN remain_quantity <= ? THEN 'Low Stock'
                ELSE 'Normal'
            END as stock_level
        FROM stock 
        WHERE remain_quantity <= ?
        ORDER BY remain_quantity ASC, expire_date ASC
    ";
    
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $threshold, $threshold);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Get expired and expiring items with days remaining
 */
function getExpirationAnalysis($con, $daysAhead = 30) {
    $sql = "
        SELECT 
            id,
            product_code,
            medicine_name,
            category,
            manufacturer,
            company,
            quantity as total_stock,
            remain_quantity,
            expire_date,
            actual_price,
            selling_price,
            DATEDIFF(expire_date, CURDATE()) as days_remaining,
            CASE 
                WHEN expire_date < CURDATE() THEN 'Expired'
                WHEN expire_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'Expires This Week'
                WHEN expire_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN 'Expires Soon'
                ELSE 'Valid'
            END as expiration_status,
            CASE 
                WHEN expire_date < CURDATE() THEN 'danger'
                WHEN expire_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'warning'
                WHEN expire_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN 'info'
                ELSE 'success'
            END as alert_level
        FROM stock 
        WHERE expire_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
           OR expire_date < CURDATE()
        ORDER BY expire_date ASC
    ";
    
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $daysAhead, $daysAhead, $daysAhead);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/**
 * Generate comprehensive daily report data
 */
function getDailyReportData($con, $date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $salesData = getSalesData($con, $date, $date);
    $metrics = getDashboardMetrics($salesData, $con);
    $categoryAnalysis = getCategorySalesAnalysis($con, $date, $date);
    $lowStock = getLowStockItems($con);
    $expiration = getExpirationAnalysis($con);
    $topProducts = getTopProducts($salesData, 5);
    
    return [
        'date' => $date,
        'metrics' => $metrics,
        'sales_data' => $salesData,
        'category_analysis' => $categoryAnalysis,
        'low_stock_items' => $lowStock,
        'expiration_analysis' => $expiration,
        'top_products' => $topProducts,
        'total_sales_count' => count($salesData),
        'categories_sold' => count($categoryAnalysis)
    ];
}

/**
 * Format report data for Telegram message
 */
function formatTelegramReport($reportData) {
    $date = $reportData['date'];
    $metrics = $reportData['metrics'];
    $categoryAnalysis = $reportData['category_analysis'];
    $lowStock = $reportData['low_stock_items'];
    $expiration = $reportData['expiration_analysis'];
    $topProducts = $reportData['top_products'];
    
    $message = "📊 *Daily Drug Store Report - " . date('M d, Y', strtotime($date)) . "*\n\n";
    
    // Key Metrics
    $message .= "💰 *Financial Summary:*\n";
    $message .= "Revenue: ETB " . number_format($metrics['total_revenue'], 2) . "\n";
    $message .= "Profit: ETB " . number_format($metrics['total_profit'], 2) . "\n";
    $message .= "Orders: " . number_format($metrics['unique_orders']) . "\n";
    $message .= "Items Sold: " . number_format($metrics['total_quantity']) . "\n\n";
    
    // Top Categories
    if (!empty($categoryAnalysis)) {
        $message .= "🏆 *Top Categories:*\n";
        foreach (array_slice($categoryAnalysis, 0, 3) as $cat) {
            $message .= "• " . $cat['category'] . ": ETB " . number_format($cat['total_revenue'], 0) . " (" . $cat['total_quantity_sold'] . " items)\n";
        }
        $message .= "\n";
    }
    
    // Top Products
    if (!empty($topProducts)) {
        $message .= "🥇 *Top Products:*\n";
        foreach (array_slice($topProducts, 0, 3) as $product) {
            $message .= "• " . $product['medicine_name'] . ": " . $product['total_quantity'] . " sold\n";
        }
        $message .= "\n";
    }
    
    // Alerts
    $criticalAlerts = array_filter($lowStock, function($item) {
        return $item['remain_quantity'] <= 5;
    });
    
    $expiredItems = array_filter($expiration, function($item) {
        return $item['days_remaining'] < 0;
    });
    
    $expiringSoon = array_filter($expiration, function($item) {
        return $item['days_remaining'] >= 0 && $item['days_remaining'] <= 7;
    });
    
    if (!empty($criticalAlerts) || !empty($expiredItems) || !empty($expiringSoon)) {
        $message .= "⚠️ *Critical Alerts:*\n";
        
        if (!empty($expiredItems)) {
            $message .= "🔴 Expired: " . count($expiredItems) . " items\n";
        }
        
        if (!empty($expiringSoon)) {
            $message .= "🟡 Expiring this week: " . count($expiringSoon) . " items\n";
        }
        
        if (!empty($criticalAlerts)) {
            $message .= "📦 Critical low stock: " . count($criticalAlerts) . " items\n";
        }
        $message .= "\n";
    }
    
    $message .= "📱 *Generated:* " . date('Y-m-d H:i:s') . "\n";
    $message .= "_Drug Store Management System_";
    
    return $message;
}
?>
