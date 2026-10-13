<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!session_id()) session_start();

// Enhanced authentication check
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';
include('../dbcon.php'); 
// Get user permissions for discounts
$user_permissions = [];
if ($stmt = $con->prepare('SELECT max_discount_percentage, can_apply_discount, can_manage_credit, can_manage_controlled FROM user_permissions WHERE user_id = ?')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_permissions = $result->fetch_assoc() ?? [];
}

// Get system settings
$system_settings = [];
$settings_result = $con->query('SELECT setting_key, setting_value FROM system_settings WHERE is_public = 1');
while ($row = $settings_result->fetch_assoc()) {
    $system_settings[$row['setting_key']] = $row['setting_value'];
}

// Get pinned products
$pinned_products = [];
if ($stmt = $con->prepare('SELECT product_id FROM pinned_products WHERE user_id = ?')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pinned_products[] = $row['product_id'];
    }
}

// Get all available products with check for items in others' carts and expiry status
$products = [];
$low_stock_threshold = $system_settings['low_stock_threshold'] ?? 10;
$expiry_warning_days = $system_settings['expiry_warning_days'] ?? 30;

$stmt = $con->prepare('SELECT s.*, 
                      (SELECT COUNT(*) FROM cart c WHERE c.product_id = s.id AND c.user_id != ?) as in_others_cart,
                      CASE 
                          WHEN s.remain_quantity <= ? THEN "low_stock"
                          WHEN s.expire_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN "expiring_soon"
                          ELSE "normal"
                      END as stock_status
                      FROM stock s 
                      WHERE s.remain_quantity >= 1 AND s.status = "Available" 
                      ORDER BY s.medicine_name ASC');
$stmt->bind_param('iii', $user_id, $low_stock_threshold, $expiry_warning_days);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['is_pinned'] = in_array($row['id'], $pinned_products);
    $row['in_others_cart'] = $row['in_others_cart'] > 0;
    $products[] = $row;
}

// Get cashier reminders
$cashier_reminders = [];
if ($stmt = $con->prepare('SELECT id, reminder_text, is_completed FROM cashier_reminders WHERE user_id = ? AND is_completed = 0 ORDER BY created_at DESC LIMIT 5')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cashier_reminders = $result->fetch_all(MYSQLI_ASSOC);
}

// Get notifications
$notifications = [];
if ($stmt = $con->prepare('SELECT id, title, message, notification_type, priority, created_at FROM notifications WHERE user_id = ? AND is_read = 0 AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY created_at DESC LIMIT 10')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= $system_settings['pharmacy_name'] ?? 'HDS Drug Store' ?> - Selling Page</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HDS Drug Store - Pharmacy Management System">
    
    <!-- CSS Links -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../src/facebox.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .product-card { 
            transition: all 0.3s ease; 
            cursor: pointer; 
            height: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
        }
        .product-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .scaled { 
            transform: scale(1.05); 
            border: 2px solid #17a2b8;
            box-shadow: 0 0 10px rgba(23, 162, 184, 0.5);
        }
        .multiplier-badge { 
            display: inline-block; 
            margin-top: 6px; 
            padding: 2px 7px; 
            border-radius: 8px; 
            background: #e9ecef; 
            font-size: 0.9em;
            font-weight: bold;
            color: #495057;
        }
        .search-bar-container { 
            margin-bottom: 1.5rem; 
        }
        #searchInput { 
            max-width: 400px; 
            border-radius: 20px;
        }
        .manufacturer-name {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .price-text {
            font-weight: bold;
            color: #28a745;
        }
        .action-btn {
            color: #17a2b8;
            transition: all 0.2s;
            margin: 0 3px;
        }
        .action-btn:hover {
            color: #138496;
            transform: scale(1.1);
        }
        .stock-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            font-size: 0.7rem;
        }
        .product-card-parent {
            position: relative;
        }
        .out-of-stock {
            opacity: 0.6;
            position: relative;
        }
        .out-of-stock::after {
            content: "Out of Stock";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(220, 53, 69, 0.8);
            color: white;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 0.9rem;
        }
        .pinned-badge {
            position: absolute;
            top: 5px;
            left: 5px;
            color: #ffc107;
            z-index: 1;
        }
        .search-results-info {
            margin: 10px 0;
            font-size: 0.9rem;
            color: #6c757d;
            text-align: center;
        }
        .pinned-section {
            margin-bottom: 20px;
            border-bottom: 2px solid #17a2b8;
            padding-bottom: 10px;
        }
        .pinned-section h5 {
            color: #17a2b8;
        }
        .action-buttons {
            display: flex;
            justify-content: center;
        }
        .disabled-card {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }
        .item-in-use-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.8);
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        .item-in-use-message {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9rem;
            text-align: center;
        }
        .low-stock {
            border-left: 4px solid #ffc107;
        }
        .expiring-soon {
            border-left: 4px solid #fd7e14;
        }
        .controlled-substance {
            border-right: 4px solid #dc3545;
        }
        .notification-badge {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 1050;
        }
        .reminder-item {
            border-left: 4px solid #17a2b8;
            margin-bottom: 8px;
        }
        .quick-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .category-filter {
            margin-bottom: 15px;
        }
        .prescription-badge {
            background-color: #6f42c1;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 5px;
        }
        .loyalty-points {
            background-color: #20c997;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
    </style>
</head>

<body>
  <?php include 'header.php'; ?>
  <br><br><br>
  
  <!-- Notifications Badge -->
  <?php if (!empty($notifications)): ?>
  <div class="notification-badge">
      <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#notificationsModal">
          <i class="fas fa-bell"></i> <?= count($notifications) ?>
      </button>
  </div>
  <?php endif; ?>

  <div class="content-header">
      <h4 class="mb-0">&nbsp; &nbsp;<i class="fas fa-shopping-cart"></i> Selling Page 
          <small class="text-muted">- <?= $system_settings['pharmacy_name'] ?? 'HDS Drug Store' ?></small>
      </h4>
  </div>
  <br>
  <!-- Reminders Section -->
  <?php if (!empty($cashier_reminders) && $user_role === 'Cashier'): ?>
  <div class="container">
      <div class="alert alert-info">
          <h6><i class="fas fa-bell"></i> Your Reminders</h6>
          <?php foreach ($cashier_reminders as $reminder): ?>
          <div class="reminder-item pl-3">
              <?= htmlspecialchars($reminder['reminder_text']) ?>
              <a href="complete_reminder.php?id=<?= $reminder['id'] ?>" class="btn btn-success btn-sm ml-2">
                  <i class="fas fa-check"></i> Complete
              </a>
          </div>
          <?php endforeach; ?>
      </div>
  </div>
  <?php endif; ?>

  <!-- Search and Filters -->
  <div class="container">
      <div class="row">
          <div class="col-md-8">
              <div class="search-bar-container">
                  <form class="form-inline" id="searchForm">
                      <div class="input-group w-100">
                          <input class="form-control" type="search" placeholder="Search Medicine Name, Manufacturer, Category..." aria-label="Search" id="searchInput">
                          <div class="input-group-append">
                              <button class="btn btn-info" type="button" id="searchButton"><i class="fas fa-search"></i> Search</button>
                          </div>
                      </div>
                  </form>
              </div>
          </div>
          <div class="col-md-4">
              <div class="category-filter">
                  <select class="form-control" id="categoryFilter">
                      <option value="">All Categories</option>
                      <?php
                      $categories = $con->query('SELECT DISTINCT category FROM stock WHERE category IS NOT NULL AND category != "" ORDER BY category');
                      while ($cat = $categories->fetch_assoc()): ?>
                      <option value="<?= htmlspecialchars($cat['category']) ?>"><?= htmlspecialchars($cat['category']) ?></option>
                      <?php endwhile; ?>
                  </select>
              </div>
          </div>
      </div>
      <div class="row">
          <div class="col-12">
              <div class="btn-group btn-group-sm mb-3">
                  <button type="button" class="btn btn-outline-secondary active" data-filter="all">All</button>
                  <button type="button" class="btn btn-outline-warning" data-filter="low_stock">Low Stock</button>
                  <button type="button" class="btn btn-outline-danger" data-filter="expiring_soon">Expiring Soon</button>
                  <button type="button" class="btn btn-outline-primary" data-filter="pinned">Pinned</button>
              </div>
          </div>
      </div>
      <div class="search-results-info"></div>
  </div>

  <!-- Displaying Products Start -->
  <div class="container">
    <div id="message"></div>
    
    <?php
    // Separate pinned and regular products
    $pinned_items = array_filter($products, function($item) {
        return $item['is_pinned'] && $item['remain_quantity'] > 0;
    });
    
    $regular_items = array_filter($products, function($item) {
        return !$item['is_pinned'] && $item['remain_quantity'] > 0;
    });
    
    $has_products = count($products) > 0;
    ?>

    <!-- Pinned Products Section -->
    <?php if (!empty($pinned_items)): ?>
    <div class="pinned-section">
        <h5><i class="fas fa-thumbtack"></i> Pinned Products</h5>
        <div class="row mt-2 pb-3">
            <?php foreach ($pinned_items as $row): ?>
            <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-3 product-card-parent">
                <div class="card p-2 mb-2 product-card flex-fill <?= $row['in_others_cart'] ? 'disabled-card' : '' ?> 
                    <?= $row['stock_status'] === 'low_stock' ? 'low-stock' : '' ?>
                    <?= $row['stock_status'] === 'expiring_soon' ? 'expiring-soon' : '' ?>"
                     data-pid="<?= $row['id'] ?>"
                     data-max="<?= (int)$row['remain_quantity'] ?>"
                     data-name="<?= htmlspecialchars(strtolower($row['medicine_name'])) ?>"
                     data-manufacturer="<?= htmlspecialchars(strtolower($row['manufacturer'])) ?>"
                     data-category="<?= htmlspecialchars(strtolower($row['category'] ?? '')) ?>"
                     data-stock-status="<?= $row['stock_status'] ?>"
                     data-pinned="true">
                    <?php if ($row['in_others_cart']): ?>
                    <div class="item-in-use-overlay">
                        <div class="item-in-use-message">Item being ordered by another user</div>
                    </div>
                    <?php endif; ?>
                    <span class="pinned-badge"><i class="fas fa-thumbtack"></i></span>
                    <span class="badge badge-pill badge-<?= 
                        $row['stock_status'] === 'low_stock' ? 'warning' : 
                        ($row['stock_status'] === 'expiring_soon' ? 'danger' : 'info')
                    ?> stock-badge">Stock: <?= $row['remain_quantity'] ?></span>
                    <br>
                    <div class="card-body p-1">
                        <h6 class="card-title text-center text-info" style="font-size:0.95rem;">
                            <?= htmlspecialchars($row['medicine_name']) ?>
                        </h6>
                        <div class="manufacturer-name text-center"><b>Sup:</b> <?= htmlspecialchars($row['manufacturer']) ?></div>
                        <div class="text-center small text-muted mb-1"><?= htmlspecialchars($row['category']) ?></div>
                        <div class="card-text text-center price-text" style="font-size:0.9rem;">
                            <?= number_format((float)$row['selling_price'], 2) ?> <?= $system_settings['currency_symbol'] ?? 'ETB' ?>
                        </div>
                        <?php if ($row['expire_date'] && strtotime($row['expire_date']) <= strtotime("+{$expiry_warning_days} days")): ?>
                        <div class="text-center small text-danger">
                            <i class="fas fa-exclamation-triangle"></i> Exp: <?= date('M Y', strtotime($row['expire_date'])) ?>
                        </div>
                        <?php endif; ?>
                        <div class="text-center">
                            <span class="multiplier-badge" id="multiplier-<?= $row['id'] ?>"></span>
                        </div>
                    </div>
                    <div class="card-footer p-1 bg-transparent border-top-0">
                        <form action="" class="form-submit" data-pid="<?= $row['id'] ?>">
                            <input type="hidden" class="pid" value="<?= $row['id'] ?>">
                            <input type="hidden" class="pname" value="<?= htmlspecialchars($row['medicine_name']) ?>">
                            <input type="hidden" class="pmanufacturer" value="<?= htmlspecialchars($row['manufacturer']) ?>">
                            <input type="hidden" class="pprice" value="<?= $row['selling_price'] ?>">
                            <input type="hidden" class="pmultiplier" value="1">
                            <div class="action-buttons">
                                <a id="popup-<?= $row['id'] ?>" href="pin_&_unpin.php?id=<?= $row['id'] ?>&action=unpin"
                                   class="action-btn" title="Unpin Product">
                                    <i class="fas fa-thumbtack"></i>
                                </a>
                                <?php if ($user_permissions['can_apply_discount'] ?? false): ?>
                                <a href="apply_discount.php?product_id=<?= $row['id'] ?>" class="action-btn" title="Apply Discount">
                                    <i class="fas fa-tag"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Regular Products Section -->
    <div class="row mt-2 pb-3" id="productsContainer">
        <?php foreach ($regular_items as $row): ?>
        <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-3 product-card-parent">
            <div class="card p-2 mb-2 product-card flex-fill <?= $row['in_others_cart'] ? 'disabled-card' : '' ?>
                <?= $row['stock_status'] === 'low_stock' ? 'low-stock' : '' ?>
                <?= $row['stock_status'] === 'expiring_soon' ? 'expiring-soon' : '' ?>"
                 data-pid="<?= $row['id'] ?>"
                 data-max="<?= (int)$row['remain_quantity'] ?>"
                 data-name="<?= htmlspecialchars(strtolower($row['medicine_name'])) ?>"
                 data-manufacturer="<?= htmlspecialchars(strtolower($row['manufacturer'])) ?>"
                 data-category="<?= htmlspecialchars(strtolower($row['category'] ?? '')) ?>"
                 data-stock-status="<?= $row['stock_status'] ?>"
                 data-pinned="false">
                <?php if ($row['in_others_cart']): ?>
                <div class="item-in-use-overlay">
                    <div class="item-in-use-message">Item being ordered by another user</div>
                </div>
                <?php endif; ?>
                <span class="badge badge-pill badge-<?= 
                    $row['stock_status'] === 'low_stock' ? 'warning' : 
                    ($row['stock_status'] === 'expiring_soon' ? 'danger' : 'info')
                ?> stock-badge">Stock: <?= $row['remain_quantity'] ?></span>
                <br>
                <div class="card-body p-1">
                    <h6 class="card-title text-center text-info" style="font-size:0.95rem;">
                        <?= htmlspecialchars($row['medicine_name']) ?>
                    </h6>
                    <div class="manufacturer-name text-center"><b>Sup: </b><?= htmlspecialchars($row['manufacturer']) ?></div>
                    <div class="text-center small text-muted mb-1"><?= htmlspecialchars($row['category']) ?></div>
                    <div class="card-text text-center price-text" style="font-size:0.9rem;">
                        <?= number_format((float)$row['selling_price'], 2) ?> <?= $system_settings['currency_symbol'] ?? 'ETB' ?>
                    </div>
                    <?php if ($row['expire_date'] && strtotime($row['expire_date']) <= strtotime("+{$expiry_warning_days} days")): ?>
                    <div class="text-center small text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Exp: <?= date('M Y', strtotime($row['expire_date'])) ?>
                    </div>
                    <?php endif; ?>
                    <div class="text-center">
                        <span class="multiplier-badge" id="multiplier-<?= $row['id'] ?>"></span>
                    </div>
                </div>
                <div class="card-footer p-1 bg-transparent border-top-0">
                    <form action="" class="form-submit" data-pid="<?= $row['id'] ?>">
                        <input type="hidden" class="pid" value="<?= $row['id'] ?>">
                        <input type="hidden" class="pname" value="<?= htmlspecialchars($row['medicine_name']) ?>">
                        <input type="hidden" class="pmanufacturer" value="<?= htmlspecialchars($row['manufacturer']) ?>">
                        <input type="hidden" class="pprice" value="<?= $row['selling_price'] ?>">
                        <input type="hidden" class="pmultiplier" value="1">
                        <div class="action-buttons">
                            <a id="popup-<?= $row['id'] ?>" href="pin_&_unpin.php?id=<?= $row['id'] ?>&action=pin"
                               class="action-btn" title="Pin Product">
                                <i class="fas fa-thumbtack"></i>
                            </a>
                            <?php if ($user_permissions['can_apply_discount'] ?? false): ?>
                            <a href="apply_discount.php?product_id=<?= $row['id'] ?>" class="action-btn" title="Apply Discount">
                                <i class="fas fa-tag"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (!$has_products): ?>
        <div class="col-12">
            <div class="alert alert-danger alert-dismissible mt-2">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <strong>All items are out of stock or marked as unavailable by administrator!</strong>
            </div>
        </div>
        <?php endif; ?>
    </div>
  </div>
  <br>
  <br>

  <!-- Notifications Modal -->
  <div class="modal fade" id="notificationsModal" tabindex="-1">
      <div class="modal-dialog modal-lg">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title"><i class="fas fa-bell"></i> Notifications</h5>
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
              </div>
              <div class="modal-body">
                  <?php if (empty($notifications)): ?>
                  <p class="text-muted">No new notifications.</p>
                  <?php else: ?>
                  <?php foreach ($notifications as $notification): ?>
                  <div class="alert alert-<?= 
                      $notification['priority'] === 'urgent' ? 'danger' : 
                      ($notification['priority'] === 'high' ? 'warning' : 'info')
                  ?>">
                      <h6><?= htmlspecialchars($notification['title']) ?></h6>
                      <p class="mb-1"><?= htmlspecialchars($notification['message']) ?></p>
                      <small class="text-muted"><?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?></small>
                      <a href="mark_notification_read.php?id=<?= $notification['id'] ?>" class="btn btn-sm btn-outline-secondary float-right">
                          Mark Read
                      </a>
                  </div>
                  <?php endforeach; ?>
                  <?php endif; ?>
              </div>
          </div>
      </div>
  </div>

  <!-- JavaScript Libraries -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
  <script src="../src/facebox.js"></script>
  <script>
// Enhanced Search and Filter functionality
function performSearch() {
  var searchVal = $("#searchInput").val().toLowerCase().trim();
  var categoryFilter = $("#categoryFilter").val().toLowerCase();
  var activeFilter = $('.btn-group .btn.active').data('filter');
  
  var visibleCount = 0;
  
  $("#productsContainer .product-card-parent, .pinned-section .product-card-parent").each(function() {
    var $card = $(this);
    var medName = $card.find('.product-card').data('name');
    var manufacturer = $card.find('.product-card').data('manufacturer');
    var category = $card.find('.product-card').data('category');
    var stockStatus = $card.find('.product-card').data('stock-status');
    var isPinned = $card.find('.product-card').data('pinned');
    
    var matchesSearch = searchVal === '' || 
                       medName.includes(searchVal) || 
                       manufacturer.includes(searchVal) ||
                       category.includes(searchVal);
    
    var matchesCategory = categoryFilter === '' || category.includes(categoryFilter);
    
    var matchesFilter = activeFilter === 'all' ||
                       (activeFilter === 'low_stock' && stockStatus === 'low_stock') ||
                       (activeFilter === 'expiring_soon' && stockStatus === 'expiring_soon') ||
                       (activeFilter === 'pinned' && isPinned);
    
    if (matchesSearch && matchesCategory && matchesFilter) {
      $card.removeClass('d-none').addClass('d-flex');
      visibleCount++;
    } else {
      $card.removeClass('d-flex').addClass('d-none');
    }
  });
  
  // Update results info
  var resultsInfo = visibleCount + ' products found';
  if (searchVal) resultsInfo += ' for "' + searchVal + '"';
  if (categoryFilter) resultsInfo += ' in ' + $("#categoryFilter option:selected").text();
  $(".search-results-info").text(resultsInfo);
}

// Search on button click
$("#searchButton").on("click", performSearch);

// Search as you type with debounce
var searchTimeout;
$("#searchInput").on("input", function() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(performSearch, 300);
});

// Search on Enter key
$("#searchInput").on("keyup", function(e) {
  if (e.key === "Enter") {
    performSearch();
  }
});

// Category filter
$("#categoryFilter").on("change", performSearch);

// Filter buttons
$('.btn-group .btn').on('click', function() {
  $('.btn-group .btn').removeClass('active');
  $(this).addClass('active');
  performSearch();
});

jQuery(document).ready(function($) {
    // Facebox popup forms
    $("a[id*=popup").facebox({
        loadingImage: '../src/img/loading.gif',
        closeImage: '../src/img/closelabel.png'
    });
});

// --- Improved Multiplier System ---
let productMultiplier = JSON.parse(localStorage.getItem('productMultiplier') || '{}');

// Initialize multiplier badges and hidden fields
const initMultipliers = () => {
  $('.product-card').each(function() {
    const pid = $(this).data('pid');
    const mult = productMultiplier[pid] || 0;
    $("#multiplier-" + pid).text(mult ? (mult + "X") : "");
    $(`[data-pid="${pid}"] .pmultiplier`).val(mult);
  });
};
initMultipliers();

// Product card click handler
$('.product-card:not(.out-of-stock):not(.disabled-card)').on('click', function(e) {
  // Don't trigger if clicking on action buttons
  if ($(e.target).closest('.action-btn').length) return;
  
  const $card = $(this);
  const pid = $card.data('pid');
  updateMultiplier(pid, $card);
});

// Update multiplier and add to cart
function updateMultiplier(pid, $card) {
  const maxQty = parseInt($card.data('max')) || 1;
  
  // Increment multiplier with rollover
  productMultiplier[pid] = productMultiplier[pid] || 0;
  productMultiplier[pid] = productMultiplier[pid] < maxQty ? productMultiplier[pid] + 1 : 1;

  // Save to localStorage
  localStorage.setItem('productMultiplier', JSON.stringify(productMultiplier));

  // Update UI
  $("#multiplier-" + pid).text(productMultiplier[pid] + "X");
  $(`[data-pid="${pid}"] .pmultiplier`).val(productMultiplier[pid]);

  // Visual feedback
  $card.addClass("scaled");
  setTimeout(() => $card.removeClass("scaled"), 200);

  // Add to cart via AJAX
  const $form = $(`.form-submit[data-pid="${pid}"]`);
  $.ajax({
    url: 'action.php',
    method: 'post',
    data: {
      pid: pid,
      pname: $form.find(".pname").val(),
      pprice: $form.find(".pprice").val(),
      pmultiplier: productMultiplier[pid]
    },
    success: function(response) {
      $("#message").html(response).fadeIn().delay(3000).fadeOut();
      load_cart_item_number();
      updateQuickStats();
    },
    error: function() {
      $("#message").html('<div class="alert alert-danger">Failed to add item to cart</div>')
                  .fadeIn().delay(3000).fadeOut();
    }
  });
}

// Clear multipliers function (called from cart page)
window.clearMultipliers = function() {
  productMultiplier = {};
  localStorage.removeItem('productMultiplier');
  $('.multiplier-badge').text('');
  $('.pmultiplier').val('1');
};

// Listen for cart clearing from other pages
window.addEventListener('storage', function(e) {
  if (e.key === 'productMultiplier' && e.newValue === null) {
    clearMultipliers();
  }
});

// Listen for messages from iframe (for facebox)
window.addEventListener('message', function(e) {
  if (e.data === 'pinUpdated') {
    location.reload();
  }
});

// Load cart item count
function load_cart_item_number() {
  $.ajax({
    url: 'action.php',
    method: 'get',
    data: { cartItem: "cart_item" },
    success: function(response) {
      $("#cart-item").html(response);
    }
  });
}

// Update quick stats
function updateQuickStats() {
  // This would typically make an AJAX call to get updated stats
  console.log('Stats would be updated here');
}

load_cart_item_number();

// Auto-dismiss alerts
setTimeout(() => $(".alert").alert('close'), 5000);

// Logout confirmation
$(".logout").click(function(e){
  e.preventDefault();
  if(confirm("Are you sure you want to logout?")){
    localStorage.removeItem('productMultiplier');
    window.location.href = '../logout.php';
  }
});

// Check for low stock items on page load
$(document).ready(function() {
    const lowStockCount = $('.low-stock').length;
    const expiringCount = $('.expiring-soon').length;
    
    if (lowStockCount > 0) {
        console.log(`Warning: ${lowStockCount} items are low in stock`);
    }
    if (expiringCount > 0) {
        console.log(`Warning: ${expiringCount} items are expiring soon`);
    }
});
  </script>
</body>
<?php include 'footer.php'; ?>
</html>