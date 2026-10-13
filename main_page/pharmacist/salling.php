<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!session_id()) session_start();

// Enhanced authentication check
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

// Get pinned products
include '../../dbcon.php';
$pinned_products = [];
if ($stmt = $con->prepare('SELECT product_id FROM pinned_products WHERE user_id = ?')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pinned_products[] = $row['product_id'];
    }
}

// Get all available products with check for items in others' carts
$products = [];
$stmt = $con->prepare('SELECT s.*, 
                      (SELECT COUNT(*) FROM cart c WHERE c.product_id = s.id AND c.user_id != ?) as in_others_cart
                      FROM stock s 
                      WHERE s.remain_quantity >= 1 AND s.status = "Available" 
                      ORDER BY s.medicine_name ASC');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['is_pinned'] = in_array($row['id'], $pinned_products);
    $row['in_others_cart'] = $row['in_others_cart'] > 0;
    $products[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>HDS Drug Store - Selling Page</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HDS Drug Store - Pharmacy Management System">
    
    <!-- CSS Links -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../src/facebox.css">
    <link rel="stylesheet" href="../../css/style.css">
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
    </style>
</head>

<body>
  <?php include 'header.php'; ?>
  <br><br><br>
  <div class="content-header">
    <h4 class="mb-0">&nbsp; &nbsp;<i class="fas fa-shopping-cart"></i> Selling Page</h4>
  </div>
  <br>

  <!-- Search Bar Start -->
  <div class="container search-bar-container">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <form class="form-inline justify-content-center" id="searchForm">
          <div class="input-group w-100">
            <input class="form-control" type="search" placeholder="Search Medicine Name, Manufacturer..." aria-label="Search" id="searchInput">
            <div class="input-group-append">
              <button class="btn btn-info" type="button" id="searchButton"><i class="fas fa-search"></i> Search</button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <div class="search-results-info"></div>
  </div>
  <!-- Search Bar End -->

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
                <div class="card p-2 mb-2 product-card flex-fill <?= $row['in_others_cart'] ? 'disabled-card' : '' ?>"
                     data-pid="<?= $row['id'] ?>"
                     data-max="<?= (int)$row['remain_quantity'] ?>"
                     data-name="<?= htmlspecialchars(strtolower($row['medicine_name'])) ?>"
                     data-manufacturer="<?= htmlspecialchars(strtolower($row['manufacturer'])) ?>">
                    <?php if ($row['in_others_cart']): ?>
                    <div class="item-in-use-overlay">
                        <div class="item-in-use-message">Item being ordered by another user</div>
                    </div>
                    <?php endif; ?>
                    <span class="pinned-badge"><i class="fas fa-thumbtack"></i></span>
                    <span class="badge badge-pill badge-info stock-badge">Stock: <?= $row['remain_quantity'] ?></span>
                    <br>
                    <div class="card-body p-1">
                        <h6 class="card-title text-center text-info" style="font-size:0.95rem;"><?= htmlspecialchars($row['medicine_name']) ?></h6>
                        <div class="manufacturer-name text-center"><?= htmlspecialchars($row['manufacturer']) ?></div>
                        <div class="card-text text-center price-text" style="font-size:0.9rem;">
                            <?= number_format((float)$row['selling_price'], 2) ?> ETB
                        </div>
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
            <div class="card p-2 mb-2 product-card flex-fill <?= $row['in_others_cart'] ? 'disabled-card' : '' ?>"
                 data-pid="<?= $row['id'] ?>"
                 data-max="<?= (int)$row['remain_quantity'] ?>"
                 data-name="<?= htmlspecialchars(strtolower($row['medicine_name'])) ?>"
                 data-manufacturer="<?= htmlspecialchars(strtolower($row['manufacturer'])) ?>">
                <?php if ($row['in_others_cart']): ?>
                <div class="item-in-use-overlay">
                    <div class="item-in-use-message">Item being ordered by another user</div>
                </div>
                <?php endif; ?>
                <span class="badge badge-pill badge-info stock-badge">Stock: <?= $row['remain_quantity'] ?></span>
                <br>
                <div class="card-body p-1">
                    <h6 class="card-title text-center text-info" style="font-size:0.95rem;"><?= htmlspecialchars($row['medicine_name']) ?></h6>
                    <div class="manufacturer-name text-center"><?= htmlspecialchars($row['manufacturer']) ?></div>
                    <div class="card-text text-center price-text" style="font-size:0.9rem;">
                        <?= number_format((float)$row['selling_price'], 2) ?> ETB
                    </div>
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
  <!-- Displaying Products End -->

  <!-- JavaScript Libraries -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
  <script src="../../src/facebox.js"></script>
  <script>
// Improved Search functionality
function performSearch() {
  var searchVal = $("#searchInput").val().toLowerCase().trim();
  var hasResults = false;
  
  // Reset all products to hidden first
  $("#productsContainer .product-card-parent").addClass('d-none').removeClass('d-flex');
  
  // Show matching products
  $("#productsContainer .product-card-parent").each(function() {
    var medName = $(this).find('.product-card').data('name');
    var manufacturer = $(this).find('.product-card').data('manufacturer');
    if (searchVal === '' || medName.includes(searchVal) || manufacturer.includes(searchVal)) {
      $(this).addClass('d-flex').removeClass('d-none');
      hasResults = true;
    }
  });
  
  // Show message if no results found
  if (!hasResults && searchVal !== '') {
    $("#message").html('<div class="alert alert-warning">No products found matching "' + searchVal + '"</div>');
  } else {
    $("#message").empty();
  }
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

jQuery(document).ready(function($) {
    // Facebox popup forms
    $("a[id*=popup").facebox({
        loadingImage: '../../src/img/loading.gif',
        closeImage: '../../src/img/closelabel.png'
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
load_cart_item_number();

// Auto-dismiss alerts
setTimeout(() => $(".alert").alert('close'), 5000);

// Logout confirmation
$(".logout").click(function(e){
  e.preventDefault();
  if(confirm("Are you sure you want to logout?")){
    localStorage.removeItem('productMultiplier');
    window.location.href = '../../logout.php';
  }
});
  </script>
</body>
<?php include 'footer.php'; ?>
</html>