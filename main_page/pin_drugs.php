<?php
session_start();
if(!isset($_SESSION['user_session'])){
    header("location:../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

// Database connection
require '../dbcon.php';

// Get pinned products
$pinned_products = [];
if ($stmt = $con->prepare('SELECT product_id FROM pinned_products WHERE user_id = ?')) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pinned_products[] = $row['product_id'];
    }
    $stmt->close();
}

// Get products with FIFO status and check if in others' carts
$products = [];
$stmt = $con->prepare('SELECT s.*, 
                      (SELECT COUNT(*) FROM pinned_products 
                       WHERE product_id = s.id AND user_id = ?) AS is_pinned,
                      (SELECT COUNT(*) FROM cart c 
                       WHERE c.product_id = s.product_code AND c.user_id != ?) AS in_others_cart
                      FROM stock s 
                      WHERE s.remain_quantity >= 1 AND s.status = "Available"
                      ORDER BY is_pinned DESC, s.medicine_name ASC');
$stmt->bind_param('ii', $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['is_pinned'] = (bool)$row['is_pinned'];
    $row['in_others_cart'] = (bool)$row['in_others_cart'];
    $products[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>HDS Drug Store - Selling Page</title>
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
        /* Your CSS styles remain the same */
        .product-card { 
            transition: all 0.3s ease; 
            cursor: pointer; 
            height: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
        }
        /* ... rest of your CSS styles ... */
    </style>
</head>

<body>
  <?php include 'header.php'; ?>
  <br><br><br>
  <div class="content-header">
    <h4 class="mb-0">&nbsp; &nbsp;<i class="fas fa-thumbtack"></i> Selling Page (Pin Products)</h4>
  </div>
  <br>

  <!-- Search Bar -->
  <div class="container search-bar-container">
    <div class="row justify-content-center">
      <div class="col-md-8">
        <form class="form-inline justify-content-center" id="searchForm">
          <div class="input-group w-100">
            <input class="form-control" type="search" placeholder="Search Medicine Name, Manufacturer or Code..." 
                   aria-label="Search" id="searchInput">
            <div class="input-group-append">
              <button class="btn btn-info" type="button" id="searchButton">
                <i class="fas fa-search"></i> Search
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
    <div class="search-results-info"></div>
  </div>

  <!-- Displaying Products -->
  <div class="container">
    <div id="message"></div>
    
    <!-- Pinned Products Section -->
    <?php 
    $pinned_items = array_filter($products, function($p) { 
        return $p['is_pinned'] && $p['remain_quantity'] > 0 && !$p['in_others_cart']; 
    });
    
    if (!empty($pinned_items)): 
    ?>
    <div class="pinned-section">
        <h5><i class="fas fa-thumbtack"></i> Pinned Products</h5>
        <div class="row mt-2 pb-3">
            <?php foreach ($pinned_items as $row): ?>
                <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-3 product-card-parent">
                    <div class="card p-2 mb-2 product-card flex-fill"
                         data-pid="<?= $row['id'] ?>"
                         data-max="<?= (int)$row['remain_quantity'] ?>"
                         data-name="<?= htmlspecialchars(strtolower($row['medicine_name'])) ?>"
                         data-manufacturer="<?= htmlspecialchars(strtolower($row['manufacturer'])) ?>"
                         data-code="<?= htmlspecialchars(strtolower($row['product_code'])) ?>">
                        <span class="pinned-badge"><i class="fas fa-thumbtack"></i></span>
                        <span class="badge badge-pill badge-info stock-badge">Stock: <?= $row['remain_quantity'] ?></span>
                        <br>
                        <div class="card-body p-1">
                            <h6 class="card-title text-center text-info" style="font-size:0.95rem;">
                                <?= htmlspecialchars($row['medicine_name']) ?>
                            </h6>
                            <div class="manufacturer-name text-center">
                                <?= htmlspecialchars($row['manufacturer']) ?>
                            </div>
                            <div class="card-text text-center price-text" style="font-size:0.9rem;">
                                <?= number_format((float)$row['selling_price'], 2) ?> ETB
                            </div>
                            <div class="text-center">
                                <span class="multiplier-badge" id="multiplier-<?= $row['id'] ?>"></span>
                            </div>
                        </div>
                        <div class="card-footer p-1 bg-transparent border-top-0">
                            <div class="action-buttons">
                                <a href="pin_&_unpin.php?action=unpin&id=<?= $row['id'] ?>" 
                                   class="action-btn pin-btn" 
                                   data-product-id="<?= $row['id'] ?>" 
                                   title="Unpin Product">
                                    <i class="fas fa-thumbtack text-warning"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Regular Products Section -->
    <div class="row mt-2 pb-3" id="productsContainer">
        <?php 
        $has_products = false;
        foreach ($products as $row): 
            if (!$row['is_pinned'] && $row['remain_quantity'] > 0):
                $has_products = true;
        ?>
        <div class="col-6 col-sm-4 col-md-3 col-lg-2 mb-3 product-card-parent">
            <div class="card p-2 mb-2 product-card flex-fill <?= $row['in_others_cart'] ? 'disabled-card' : '' ?>"
                 data-pid="<?= $row['id'] ?>"
                 data-max="<?= (int)$row['remain_quantity'] ?>"
                 data-name="<?= htmlspecialchars(strtolower($row['medicine_name'])) ?>"
                 data-manufacturer="<?= htmlspecialchars(strtolower($row['manufacturer'])) ?>"
                 data-code="<?= htmlspecialchars(strtolower($row['product_code'])) ?>">
                <?php if ($row['in_others_cart']): ?>
                <div class="item-in-use-overlay">
                    <div class="item-in-use-message">Item being ordered by another user</div>
                </div>
                <?php endif; ?>
                <span class="badge badge-pill badge-info stock-badge">Stock: <?= $row['remain_quantity'] ?></span>
                <br>
                <div class="card-body p-1">
                    <h6 class="card-title text-center text-info" style="font-size:0.95rem;">
                        <?= htmlspecialchars($row['medicine_name']) ?>
                    </h6>
                    <div class="manufacturer-name text-center">
                        <?= htmlspecialchars($row['manufacturer']) ?>
                    </div>
                    <div class="card-text text-center price-text" style="font-size:0.9rem;">
                        <?= number_format((float)$row['selling_price'], 2) ?> ETB
                    </div>
                    <div class="text-center">
                        <span class="multiplier-badge" id="multiplier-<?= $row['id'] ?>"></span>
                    </div>
                </div>
                <div class="card-footer p-1 bg-transparent border-top-0">
                    <div class="action-buttons">
                        <a href="pin_&_unpin.php?action=pin&id=<?= $row['id'] ?>"
                           class="action-btn" title="Pin Product">
                            <i class="fas fa-thumbtack"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php 
            endif;
        endforeach; 
        
        if (!$has_products): ?>
        <div class="col-12">
            <div class="alert alert-info mt-2">
                <strong>No products available for pinning.</strong>
            </div>
        </div>
        <?php endif; ?>
    </div>
  </div>
  <br>
  <br>
  <!-- JavaScript Libraries -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
  <script src="../src/facebox.js"></script>
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
      var code = $(this).find('.product-card').data('code');
      
      if (searchVal === '' || 
          medName.includes(searchVal) || 
          manufacturer.includes(searchVal) || 
          code.includes(searchVal)) {
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

  // Initialize facebox for popups
  $(document).ready(function() {
    $('a[rel="facebox"]').facebox({
      loadingImage: '../src/loading.gif',
      closeImage: '../src/closelabel.png'
    });
    
    // Handle pin/unpin actions with AJAX
    $('.pin-btn, .action-btn[title="Pin Product"]').on('click', function(e) {
      e.preventDefault();
      var $link = $(this);
      var href = $link.attr('href');
      
      $.get(href, function(response) {
        if (response.success) {
          // Reload the page to see changes
          location.reload();
        } else {
          alert('Error: ' + response.message);
        }
      }).fail(function() {
        alert('Failed to perform action. Please try again.');
      });
    });
  });
  </script>
</body>
<?php include 'footer.php'; ?>
</html>