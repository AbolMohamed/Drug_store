<?php
/**
 * Enhanced Analytics Functions for Drug Store System
 * Uses real data from the database
 * Only processes orders with status 'Completed'
 */

/**
 * Get comprehensive sales data with all analytics fields
 * Only includes completed orders
 */
function getSalesData($con, $startDate = null, $endDate = null, $serviceFilter = 'all') {
    $whereClause = "WHERE o.status = 'Completed'"; // Only completed orders
    $params = [];
    $types = "";
    
    if ($startDate && $endDate) {
        $whereClause .= " AND DATE(o.order_date) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= "ss";
    } elseif ($startDate) {
        $whereClause .= " AND DATE(o.order_date) >= ?";
        $params[] = $startDate;
        $types .= "s";
    } elseif ($endDate) {
        $whereClause .= " AND DATE(o.order_date) <= ?";
        $params[] = $endDate;
        $types .= "s";
    }
    
    if ($serviceFilter === 'free') {
        $whereClause .= " AND (o.free_service = 1 OR oi.free_service = 1)";
    } elseif ($serviceFilter === 'paid') {
        $whereClause .= " AND o.free_service = 0 AND oi.free_service = 0";
    }
    
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
            COALESCE(u1.name, 'Unknown') as order_seller,
            COALESCE(u2.name, 'Unknown') as item_seller,
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
                WHEN s.actual_price > 0 THEN 
                    ROUND(((oi.product_price - s.actual_price) / s.actual_price) * 100, 2)
                ELSE 0 
            END as profit_margin_percent,
            CASE 
                WHEN o.free_service = 1 OR oi.free_service = 1 THEN 'Free'
                ELSE 'Paid'
            END as service_type,
            DATEDIFF(s.expire_date, CURDATE()) as days_until_expiry
        FROM orders o
        INNER JOIN order_items oi ON o.id = oi.order_id
        INNER JOIN stock s ON oi.product_id = s.id
        LEFT JOIN tbl_users u1 ON o.user_id = u1.id
        LEFT JOIN tbl_users u2 ON oi.user_id = u2.id
        $whereClause
        ORDER BY o.order_date DESC, o.id DESC
    ";
    
    if (!empty($params)) {
        $stmt = $con->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    } else {
        $result = $con->query($sql);
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    return [];
}

/**
 * Get dashboard metrics from sales data
 * Only processes completed orders
 */
function getDashboardMetrics($salesData, $con) {
    $totalRevenue = 0;
    $totalProfit = 0;
    $totalQuantity = 0;
    $uniqueOrders = [];
    $freeServiceRevenue = 0;
    $paidServiceRevenue = 0;
    $categories = [];
    
    foreach ($salesData as $sale) {
        // Skip non-completed orders (though getSalesData should already filter them)
        if ($sale['status'] !== 'Completed') {
            continue;
        }
        
        $totalRevenue += $sale['item_total'];
        $totalProfit += $sale['total_profit'];
        $totalQuantity += $sale['quantity'];
        $uniqueOrders[$sale['order_id']] = true;
        
        if ($sale['service_type'] === 'Free') {
            $freeServiceRevenue += $sale['item_total'];
        } else {
            $paidServiceRevenue += $sale['item_total'];
        }
        
        if (!isset($categories[$sale['category']])) {
            $categories[$sale['category']] = 0;
        }
        $categories[$sale['category']] += $sale['item_total'];
    }
    
    $uniqueOrderCount = count($uniqueOrders);
    $averageOrderValue = $uniqueOrderCount > 0 ? $totalRevenue / $uniqueOrderCount : 0;
    $profitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;
    
    return [
        'total_revenue' => $totalRevenue,
        'total_profit' => $totalProfit,
        'total_quantity' => $totalQuantity,
        'unique_orders' => $uniqueOrderCount,
        'average_order_value' => $averageOrderValue,
        'profit_margin' => $profitMargin,
        'free_service_revenue' => $freeServiceRevenue,
        'paid_service_revenue' => $paidServiceRevenue,
        'categories_count' => count($categories),
        'top_category' => !empty($categories) ? array_search(max($categories), $categories) : 'N/A'
    ];
}

/**
 * Enhanced Category Sales Analysis
 * Only includes completed orders
 */
function getCategorySalesAnalysis($con, $startDate = null, $endDate = null) {
    $whereClause = "WHERE o.status = 'Completed'"; // Only completed orders
    $params = [];
    $types = "";
    
    if ($startDate && $endDate) {
        $whereClause .= " AND DATE(o.order_date) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= "ss";
    }
    
    $sql = "
        SELECT 
            s.category,
            COUNT(DISTINCT oi.id) as total_transactions,
            SUM(oi.quantity) as total_quantity_sold,
            SUM(oi.total_price) as total_revenue,
            SUM((oi.product_price - s.actual_price) * oi.quantity) as total_profit,
            AVG(oi.product_price) as avg_selling_price,
            AVG(s.actual_price) as avg_cost_price,
            ROUND(AVG((oi.product_price - s.actual_price) / s.actual_price * 100), 2) as avg_profit_margin,
            COUNT(DISTINCT s.id) as unique_products,
            MIN(o.order_date) as first_sale_date,
            MAX(o.order_date) as last_sale_date,
            SUM(CASE WHEN o.free_service = 1 OR oi.free_service = 1 THEN oi.total_price ELSE 0 END) as free_service_revenue,
            SUM(CASE WHEN o.free_service = 0 AND oi.free_service = 0 THEN oi.total_price ELSE 0 END) as paid_service_revenue
        FROM orders o
        INNER JOIN order_items oi ON o.id = oi.order_id
        INNER JOIN stock s ON oi.product_id = s.id
        $whereClause
        GROUP BY s.category
        ORDER BY total_revenue DESC
    ";
    
    if (!empty($params)) {
        $stmt = $con->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    } else {
        $result = $con->query($sql);
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    return [];
}

/**
 * Time-Period Product Sales Analysis (Daily/Monthly/Yearly)
 * Only includes completed orders
 */
function getTimePeriodProductSales($con, $period = 'daily', $startDate = null, $endDate = null, $limit = 10) {
    $groupBy = "";
    $selectPeriod = "";
    
    switch ($period) {
        case 'daily':
            $groupBy = "DATE(o.order_date), s.id";
            $selectPeriod = "DATE(o.order_date) as period_date";
            break;
        case 'weekly':
            $groupBy = "YEAR(o.order_date), WEEK(o.order_date), s.id";
            $selectPeriod = "CONCAT(YEAR(o.order_date), '-W', LPAD(WEEK(o.order_date), 2, '0')) as period_date";
            break;
        case 'monthly':
            $groupBy = "YEAR(o.order_date), MONTH(o.order_date), s.id";
            $selectPeriod = "DATE_FORMAT(o.order_date, '%Y-%m') as period_date";
            break;
        case 'yearly':
            $groupBy = "YEAR(o.order_date), s.id";
            $selectPeriod = "YEAR(o.order_date) as period_date";
            break;
        default:
            $groupBy = "DATE(o.order_date), s.id";
            $selectPeriod = "DATE(o.order_date) as period_date";
    }
    
    $whereClause = "WHERE o.status = 'Completed'"; // Only completed orders
    $params = [];
    $types = "";
    
    if ($startDate && $endDate) {
        $whereClause .= " AND DATE(o.order_date) BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
        $types .= "ss";
    }
    
    $sql = "
        SELECT 
            $selectPeriod,
            s.id as product_id,
            s.medicine_name,
            s.category,
            s.manufacturer,
            SUM(oi.quantity) as total_quantity,
            SUM(oi.total_price) as total_revenue,
            SUM((oi.product_price - s.actual_price) * oi.quantity) as total_profit,
            COUNT(DISTINCT oi.id) as transaction_count,
            AVG(oi.product_price) as avg_selling_price,
            ROUND(AVG((oi.product_price - s.actual_price) / s.actual_price * 100), 2) as avg_profit_margin
        FROM orders o
        INNER JOIN order_items oi ON o.id = oi.order_id
        INNER JOIN stock s ON oi.product_id = s.id
        $whereClause
        GROUP BY $groupBy, s.id, s.medicine_name, s.category, s.manufacturer
        ORDER BY total_quantity DESC, total_revenue DESC
        LIMIT ?
    ";
    
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $con->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    return [];
}

/**
 * Get Low Stock Items (default threshold: 10 items)
 * This function doesn't depend on order status
 */
function getLowStockItems($con, $threshold = 10) {
    $sql = "
        SELECT 
            s.id as product_id,
            s.product_code,
            s.medicine_name,
            s.category,
            s.manufacturer,
            s.company,
            s.quantity as stock_quantity,
            s.used_quantity,
            s.remain_quantity,
            s.actual_price,
            s.selling_price,
            s.expire_date,
            DATEDIFF(s.expire_date, CURDATE()) as days_until_expiry,
            CASE 
                WHEN s.remain_quantity = 0 THEN 'Out of Stock'
                WHEN s.remain_quantity <= 5 THEN 'Critical'
                WHEN s.remain_quantity <= 10 THEN 'Low'
                ELSE 'Normal'
            END as stock_status,
            CASE 
                WHEN DATEDIFF(s.expire_date, CURDATE()) < 0 THEN 'Expired'
                WHEN DATEDIFF(s.expire_date, CURDATE()) <= 7 THEN 'Expiring Soon'
                WHEN DATEDIFF(s.expire_date, CURDATE()) <= 30 THEN 'Expiring This Month'
                ELSE 'Good'
            END as expiry_status,
            COALESCE(
                (SELECT SUM(oi.quantity) 
                 FROM order_items oi 
                 INNER JOIN orders o ON oi.order_id = o.id 
                 WHERE oi.product_id = s.id 
                   AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ), 0
            ) as sold_last_30_days
        FROM stock s
        WHERE s.remain_quantity <= ?
        ORDER BY 
            CASE 
                WHEN s.remain_quantity = 0 THEN 1
                WHEN s.remain_quantity <= 5 THEN 2
                WHEN s.remain_quantity <= 10 THEN 3
                ELSE 4
            END,
            s.remain_quantity ASC,
            DATEDIFF(s.expire_date, CURDATE()) ASC
    ";
    
    $stmt = $con->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $threshold);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    return [];
}

/**
 * Get Expiration Analysis (items expiring within specified days)
 * This function doesn't depend on order status
 */
function getExpirationAnalysis($con, $daysAhead = 30) {
    $sql = "
        SELECT 
            s.id as product_id,
            s.product_code,
            s.medicine_name,
            s.category,
            s.manufacturer,
            s.company,
            s.quantity as stock_quantity,
            s.remain_quantity,
            s.actual_price,
            s.selling_price,
            s.expire_date,
            DATEDIFF(s.expire_date, CURDATE()) as days_remaining,
            CASE 
                WHEN DATEDIFF(s.expire_date, CURDATE()) < 0 THEN 'Expired'
                WHEN DATEDIFF(s.expire_date, CURDATE()) = 0 THEN 'Expires Today'
                WHEN DATEDIFF(s.expire_date, CURDATE()) <= 7 THEN 'Expires This Week'
                WHEN DATEDIFF(s.expire_date, CURDATE()) <= 30 THEN 'Expires This Month'
                ELSE 'Good'
            END as expiry_status,
            (s.remain_quantity * s.actual_price) as potential_loss_value,
            COALESCE(
                (SELECT SUM(oi.quantity) 
                 FROM order_items oi 
                 INNER JOIN orders o ON oi.order_id = o.id 
                 WHERE oi.product_id = s.id 
                   AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                ), 0
            ) as sold_last_7_days,
            COALESCE(
                (SELECT SUM(oi.quantity) 
                 FROM order_items oi 
                 INNER JOIN orders o ON oi.order_id = o.id 
                 WHERE oi.product_id = s.id 
                   AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ), 0
            ) as sold_last_30_days
        FROM stock s
        WHERE DATEDIFF(s.expire_date, CURDATE()) <= ?
        ORDER BY 
            CASE 
                WHEN DATEDIFF(s.expire_date, CURDATE()) < 0 THEN 1
                WHEN DATEDIFF(s.expire_date, CURDATE()) <= 7 THEN 2
                WHEN DATEDIFF(s.expire_date, CURDATE()) <= 30 THEN 3
                ELSE 4
            END,
            DATEDIFF(s.expire_date, CURDATE()) ASC,
            s.remain_quantity DESC
    ";
    
    $stmt = $con->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $daysAhead);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    return [];
}

/**
 * Get Top Products by various metrics
 * Only processes completed orders
 */
function getTopProducts($salesData, $limit = 10, $metric = 'quantity') {
    $products = [];
    
    foreach ($salesData as $sale) {
        // Skip non-completed orders
        if ($sale['status'] !== 'Completed') {
            continue;
        }
        
        $key = $sale['product_id'];
        
        if (!isset($products[$key])) {
            $products[$key] = [
                'product_id' => $sale['product_id'],
                'medicine_name' => $sale['medicine_name'],
                'category' => $sale['category'],
                'manufacturer' => $sale['manufacturer'],
                'total_quantity' => 0,
                'total_revenue' => 0,
                'total_profit' => 0,
                'transaction_count' => 0,
                'avg_selling_price' => 0,
                'profit_margin' => 0
            ];
        }
        
        $products[$key]['total_quantity'] += $sale['quantity'];
        $products[$key]['total_revenue'] += $sale['item_total'];
        $products[$key]['total_profit'] += $sale['total_profit'];
        $products[$key]['transaction_count']++;
    }
    
    // Calculate averages and profit margins
    foreach ($products as &$product) {
        if ($product['total_quantity'] > 0) {
            $product['avg_selling_price'] = $product['total_revenue'] / $product['total_quantity'];
        }
        if ($product['total_revenue'] > 0) {
            $product['profit_margin'] = ($product['total_profit'] / $product['total_revenue']) * 100;
        }
    }
    
    // Sort by specified metric
    switch ($metric) {
        case 'revenue':
            usort($products, function($a, $b) { return $b['total_revenue'] <=> $a['total_revenue']; });
            break;
        case 'profit':
            usort($products, function($a, $b) { return $b['total_profit'] <=> $a['total_profit']; });
            break;
        case 'margin':
            usort($products, function($a, $b) { return $b['profit_margin'] <=> $a['profit_margin']; });
            break;
        default: // quantity
            usort($products, function($a, $b) { return $b['total_quantity'] <=> $a['total_quantity']; });
    }
    
    return array_slice($products, 0, $limit);
}

/**
 * Get Trend Data for Charts
 * Only processes completed orders
 */
function getTrendData($salesData, $period = 'daily') {
    $trendData = [];
    
    foreach ($salesData as $sale) {
        // Skip non-completed orders
        if ($sale['status'] !== 'Completed') {
            continue;
        }
        
        $dateKey = '';
        switch ($period) {
            case 'daily':
                $dateKey = date('Y-m-d', strtotime($sale['order_date']));
                break;
            case 'weekly':
                $dateKey = date('Y-W', strtotime($sale['order_date']));
                break;
            case 'monthly':
                $dateKey = date('Y-m', strtotime($sale['order_date']));
                break;
            case 'yearly':
                $dateKey = date('Y', strtotime($sale['order_date']));
                break;
            default:
                $dateKey = date('Y-m-d', strtotime($sale['order_date']));
        }
        
        if (!isset($trendData[$dateKey])) {
            $trendData[$dateKey] = [
                'period' => $dateKey,
                'revenue' => 0,
                'profit' => 0,
                'orders' => 0,
                'quantity' => 0
            ];
        }
        
        $trendData[$dateKey]['revenue'] += $sale['item_total'];
        $trendData[$dateKey]['profit'] += $sale['total_profit'];
        $trendData[$dateKey]['quantity'] += $sale['quantity'];
        $trendData[$dateKey]['orders']++;
    }
    
    // Sort by period
    ksort($trendData);
    
    return array_values($trendData);
}

/**
 * Get Service Analysis
 * Only processes completed orders
 */
function getServiceAnalysis($salesData) {
    $serviceData = [
        'Paid' => ['revenue' => 0, 'profit' => 0, 'orders' => 0, 'quantity' => 0],
        'Free' => ['revenue' => 0, 'profit' => 0, 'orders' => 0, 'quantity' => 0]
    ];
    
    $processedOrders = [];
    
    foreach ($salesData as $sale) {
        // Skip non-completed orders
        if ($sale['status'] !== 'Completed') {
            continue;
        }
        
        $serviceType = $sale['service_type'];
        
        $serviceData[$serviceType]['revenue'] += $sale['item_total'];
        $serviceData[$serviceType]['profit'] += $sale['total_profit'];
        $serviceData[$serviceType]['quantity'] += $sale['quantity'];
        
        // Count unique orders per service type
        if (!isset($processedOrders[$serviceType][$sale['order_id']])) {
            $serviceData[$serviceType]['orders']++;
            $processedOrders[$serviceType][$sale['order_id']] = true;
        }
    }
    
    return $serviceData;
}

/**
 * Get Stock Status
 * This function doesn't depend on order status
 */
function getStockStatus($con) {
    $sql = "
        SELECT 
            id as product_id,
            product_code,
            medicine_name,
            category,
            manufacturer,
            quantity as total_stock,
            used_quantity,
            remain_quantity,
            expire_date,
            actual_price,
            selling_price,
            status,
            CASE 
                WHEN remain_quantity = 0 THEN 'Out of Stock'
                WHEN remain_quantity <= 5 THEN 'Critical Low'
                WHEN remain_quantity <= 10 THEN 'Low Stock'
                ELSE 'Good'
            END as stock_alert,
            CASE 
                WHEN DATEDIFF(expire_date, CURDATE()) < 0 THEN 'Expired'
                WHEN DATEDIFF(expire_date, CURDATE()) <= 7 THEN 'Expiring Soon'
                WHEN DATEDIFF(expire_date, CURDATE()) <= 30 THEN 'Expiring This Month'
                ELSE 'Good'
            END as expiry_alert
        FROM stock
        ORDER BY remain_quantity ASC, DATEDIFF(expire_date, CURDATE()) ASC
    ";
    
    $result = $con->query($sql);
    if ($result) {
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    return [];
}

/**
 * Get Daily Report Data for Telegram
 * Only includes completed orders
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
 * Format Telegram Daily Report Message
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
    $message .= "Profit: ETB " . number_format($metrics['total_profit'], 2) . " (" . 
                number_format($metrics['profit_margin'], 1) . "%)\n";
    $message .= "Orders: " . number_format($metrics['unique_orders']) . "\n";
    $message .= "Items Sold: " . number_format($metrics['total_quantity']) . "\n";
    $message .= "Avg Order: ETB " . number_format($metrics['average_order_value'], 2) . "\n\n";
    
    // Service breakdown
    if ($metrics['free_service_revenue'] > 0 || $metrics['paid_service_revenue'] > 0) {
        $message .= "🔄 *Service Breakdown:*\n";
        $message .= "Paid Services: ETB " . number_format($metrics['paid_service_revenue'], 2) . "\n";
        $message .= "Free Services: ETB " . number_format($metrics['free_service_revenue'], 2) . "\n\n";
    }
    
    // Top Categories
    if (!empty($categoryAnalysis)) {
        $message .= "🏆 *Top Categories:*\n";
        foreach (array_slice($categoryAnalysis, 0, 3) as $cat) {
            $message .= "• " . $cat['category'] . ": ETB " . number_format($cat['total_revenue'], 0) . 
                       " (" . $cat['total_quantity_sold'] . " items)\n";
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
    
    // Critical alerts
    $criticalStock = array_filter($lowStock, function($item) {
        return $item['remain_quantity'] <= 5;
    });
    
    $expiredItems = array_filter($expiration, function($item) {
        return $item['days_remaining'] < 0;
    });
    
    $expiringSoon = array_filter($expiration, function($item) {
        return $item['days_remaining'] >= 0 && $item['days_remaining'] <= 7;
    });
    
    if (!empty($criticalStock) || !empty($expiredItems) || !empty($expiringSoon)) {
        $message .= "⚠️ *Critical Alerts:*\n";
        
        if (!empty($expiredItems)) {
            $message .= "🔴 Expired: " . count($expiredItems) . " items\n";
            foreach (array_slice($expiredItems, 0, 3) as $item) {
                $message .= "  • " . $item['medicine_name'] . " (Expired " . abs($item['days_remaining']) . " days ago)\n";
            }
        }
        
        if (!empty($expiringSoon)) {
            $message .= "🟡 Expiring this week: " . count($expiringSoon) . " items\n";
            foreach (array_slice($expiringSoon, 0, 3) as $item) {
                $message .= "  • " . $item['medicine_name'] . " (" . $item['days_remaining'] . " days left)\n";
            }
        }
        
        if (!empty($criticalStock)) {
            $message .= "📦 Critical low stock: " . count($criticalStock) . " items\n";
            foreach (array_slice($criticalStock, 0, 3) as $item) {
                $message .= "  • " . $item['medicine_name'] . " (" . $item['remain_quantity'] . " left)\n";
            }
        }
        $message .= "\n";
    }
    
    $message .= "📱 *Generated:* " . date('Y-m-d H:i:s') . "\n";
    $message .= "_Drug Store Management System_";
    
    return $message;
}

/**
 * Send Daily Report via Telegram
 */
function sendDailyReportToTelegram($con, $date = null) {
    try {
        $reportData = getDailyReportData($con, $date);
        $message = formatTelegramReport($reportData);
        // $result = sendTelegramMessage($message);
        
        // if ($result['success']) {
        //     error_log("Daily report sent successfully for date: " . ($date ?: date('Y-m-d')));
        //     return ['success' => true, 'message' => 'Daily report sent successfully'];
        // } else {
        //     error_log("Failed to send daily report: " . $result['error']);
        //     return ['success' => false, 'error' => $result['error']];
        // }
        return ['success' => true, 'message' => 'Daily report ready (Telegram integration commented out)'];
    } catch (Exception $e) {
        error_log("Error sending daily report: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get Weekly Summary Report
 * Only includes completed orders
 */
function getWeeklySummaryData($con, $startDate = null) {
    if (!$startDate) {
        $startDate = date('Y-m-d', strtotime('last monday'));
    }
    $endDate = date('Y-m-d', strtotime($startDate . ' +6 days'));
    
    $salesData = getSalesData($con, $startDate, $endDate);
    $metrics = getDashboardMetrics($salesData, $con);
    $categoryAnalysis = getCategorySalesAnalysis($con, $startDate, $endDate);
    $topProducts = getTopProducts($salesData, 10);
    
    // Get daily breakdown
    $dailyBreakdown = [];
    for ($i = 0; $i < 7; $i++) {
        $day = date('Y-m-d', strtotime($startDate . ' +' . $i . ' days'));
        $dayData = getSalesData($con, $day, $day);
        $dayMetrics = getDashboardMetrics($dayData, $con);
        $dailyBreakdown[] = [
            'date' => $day,
            'day_name' => date('l', strtotime($day)),
            'revenue' => $dayMetrics['total_revenue'],
            'profit' => $dayMetrics['total_profit'],
            'orders' => $dayMetrics['unique_orders'],
            'items_sold' => $dayMetrics['total_quantity']
        ];
    }
    
    return [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'metrics' => $metrics,
        'sales_data' => $salesData,
        'category_analysis' => $categoryAnalysis,
        'top_products' => $topProducts,
        'daily_breakdown' => $dailyBreakdown
    ];
}

/**
 * Get Monthly Summary Report
 * Only includes completed orders
 */
function getMonthlySummaryData($con, $year = null, $month = null) {
    if (!$year) $year = date('Y');
    if (!$month) $month = date('m');
    
    $startDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));
    
    $salesData = getSalesData($con, $startDate, $endDate);
    $metrics = getDashboardMetrics($salesData, $con);
    $categoryAnalysis = getCategorySalesAnalysis($con, $startDate, $endDate);
    $topProducts = getTopProducts($salesData, 15);
    
    return [
        'year' => $year,
        'month' => $month,
        'month_name' => date('F', mktime(0, 0, 0, $month, 1, $year)),
        'start_date' => $startDate,
        'end_date' => $endDate,
        'metrics' => $metrics,
        'sales_data' => $salesData,
        'category_analysis' => $categoryAnalysis,
        'top_products' => $topProducts
    ];
}

/**
 * Get Year-over-Year Comparison
 * Only includes completed orders
 */
function getYearOverYearComparison($con, $currentYear = null, $previousYear = null) {
    if (!$currentYear) $currentYear = date('Y');
    if (!$previousYear) $previousYear = $currentYear - 1;
    
    $currentYearData = getSalesData($con, $currentYear . '-01-01', $currentYear . '-12-31');
    $previousYearData = getSalesData($con, $previousYear . '-01-01', $previousYear . '-12-31');
    
    $currentMetrics = getDashboardMetrics($currentYearData, $con);
    $previousMetrics = getDashboardMetrics($previousYearData, $con);
    
    // Calculate growth percentages
    $growth = [
        'revenue' => $previousMetrics['total_revenue'] > 0 ? 
            (($currentMetrics['total_revenue'] - $previousMetrics['total_revenue']) / $previousMetrics['total_revenue']) * 100 : 0,
        'profit' => $previousMetrics['total_profit'] > 0 ? 
            (($currentMetrics['total_profit'] - $previousMetrics['total_profit']) / $previousMetrics['total_profit']) * 100 : 0,
        'orders' => $previousMetrics['unique_orders'] > 0 ? 
            (($currentMetrics['unique_orders'] - $previousMetrics['unique_orders']) / $previousMetrics['unique_orders']) * 100 : 0,
        'quantity' => $previousMetrics['total_quantity'] > 0 ? 
            (($currentMetrics['total_quantity'] - $previousMetrics['total_quantity']) / $previousMetrics['total_quantity']) * 100 : 0
    ];
    
    return [
        'current_year' => $currentYear,
        'previous_year' => $previousYear,
        'current_metrics' => $currentMetrics,
        'previous_metrics' => $previousMetrics,
        'growth' => $growth
    ];
}
?>