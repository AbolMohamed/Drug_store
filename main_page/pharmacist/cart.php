<?php
// cart.php - rewritten mobile-friendly cart page (copy/replace your existing file)
// NOTE: adjust include paths if your structure differs.

error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!session_id()) session_start();
require '../../dbcon.php';

// Ensure logged-in user
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if (!$user_id) {
    header("Location: ../../login.php");
    exit;
}

/**
 * Fetch cart items into an array (so we can render both table and mobile cards from same data)
 */
$sql = "SELECT 
        c.id AS cart_id,
        c.product_id,
        c.qty,
        c.product_price,
        c.total_price,
        s.medicine_name,
        s.remain_quantity AS stock_quantity
    FROM cart c
    JOIN stock s ON c.product_id = s.id
    WHERE c.user_id = ?
    ORDER BY c.id DESC";
$stmt = $con->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
$grand_total = 0.00;
while ($r = $res->fetch_assoc()) {
    $items[] = $r;
    $grand_total += floatval($r['total_price']);
}
$stmt->close();

$cart_count = count($items);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Shopping Cart — Drug Store</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" href="../../images/favicon.png">
  <!-- Bootstrap 4 -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    /* --- aesthetic and responsive tweaks --- */
    :root { --accent: #17a2b8; --muted: #6c757d; }
    body { background:#f6f8f9; font-family: "Segoe UI", Roboto, Arial, sans-serif; padding: 16px; }
    .page-wrap { max-width:1100px; margin: 0 auto; }
    .header-title { color: var(--accent); font-weight:700; margin-bottom:12px; display:flex; align-items:center; gap:10px; }
    .summary-badge { background:var(--accent); color:#fff; padding:7px 14px; border-radius:999px; font-weight:600; }
    .card-shell { background:#fff; border-radius:10px; padding:16px; box-shadow:0 6px 18px rgba(0,0,0,0.06); }
    table.dataTable tbody tr td { vertical-align: middle; }
    .etb-currency::before { content: "ETB "; font-weight:700; color: var(--accent); }
    .quantity-controls { display:flex; align-items:center; gap:6px; justify-content:center; }
    .qty-input { width:72px; text-align:center; height:36px; }
    .qty-btn { width:36px; height:36px; display:flex; align-items:center; justify-content:center; }
    .mobile-card { display:none; background:#fff; border-radius:10px; padding:12px; margin-bottom:12px; box-shadow:0 4px 12px rgba(0,0,0,0.04); }
    .mobile-product-name { color:var(--accent); font-weight:700; margin-bottom:6px; }
    .empty-cart { text-align:center; padding:34px 12px; color:var(--muted); }
    .grand-total-row td { background:#f1fbfc; font-weight:700; }
    @media (max-width: 768px) {
      table#cartTable { display:none !important; } /* hide desktop table on mobile, show mobile-cards instead */
      .mobile-card { display:block; }
    }
  </style>
</head>
<body>
  <?php include 'header.php'; ?>
  <br><br><br>

  <div class="page-wrap">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="header-title"><i class="fas fa-shopping-cart"></i> Shopping Cart</h2>
      <span class="summary-badge">Items in Cart: <?= $cart_count ?></span>
    </div>

    <!-- top controls -->
    <div class="mb-3 d-flex flex-column flex-md-row justify-content-between gap-2">
      <div class="w-100 search-container card-shell p-2">
        <form id="cartSearchForm" class="form-inline">
          <div class="input-group w-100">
            <input id="cartSearchBar" class="form-control" placeholder="Search by drug name, id or price...">
            <div class="input-group-append">
              <button class="btn btn-outline-info" type="button" id="cartSearchBtn"><i class="fas fa-search"></i></button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="card-shell">
      <!-- Desktop table -->
      <div class="table-responsive">
        <table id="cartTable" class="table table-striped table-bordered" style="width:100%">
          <thead>
            <tr>
              <th>Drug ID</th>
              <th>Drug Name</th>
              <th>Unit Price</th>
              <th style="width:170px">Quantity</th>
              <th>Total Price</th>
              <th style="width:110px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($cart_count > 0): ?>
              <?php foreach ($items as $r): ?>
                <tr data-cart-id="<?= intval($r['cart_id']) ?>">
                  <td class="product-id-cell"><?= htmlspecialchars($r['product_id']) ?>
                    <input type="hidden" class="pid" value="<?= intval($r['cart_id']) ?>">
                    <input type="hidden" class="product-id" value="<?= htmlspecialchars($r['product_id']) ?>">
                  </td>
                  <td><?= htmlspecialchars($r['medicine_name']) ?></td>
                  <td>
                    <span class="etb-currency"></span><?= number_format($r['product_price'], 2) ?> <i style="color: red;">&nbsp;X<i>
                    <input type="hidden" class="pprice" value="<?= number_format($r['product_price'], 2, '.', '') ?>">
                  </td>
                  <td>
                    <div class="quantity-controls">
                      <button class="btn btn-outline-secondary btn-sm qty-btn minus-btn" type="button" aria-label="Decrease qty">-</button>
                      <input type="number" class="form-control qty-input itemQtyInput" value="<?= intval($r['qty']) ?>"
                             min="1" max="<?= intval($r['stock_quantity']) ?>"
                             data-cart-id="<?= intval($r['cart_id']) ?>"
                             data-product-id="<?= intval($r['product_id']) ?>"
                             data-stock-max="<?= intval($r['stock_quantity']) ?>"
                             data-product-name="<?= htmlspecialchars($r['medicine_name']) ?>">
                      <button class="btn btn-outline-secondary btn-sm qty-btn plus-btn" type="button" aria-label="Increase qty">+</button><i style="color:red;">=</i>
                    </div>
                  </td>
                  <td class="itemTotal"><span class="etb-currency"></span><?= number_format($r['total_price'], 2) ?></td>
                  <td class="action-buttons text-center">
                    <a href="action.php?remove=<?= intval($r['cart_id']) ?>" class="btn btn-danger btn-sm removeCartItemBtn" title="Remove item">
                      <i class="fas fa-trash"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>

          <?php if ($cart_count > 0): ?>
          <tfoot>
            <tr class="grand-total-row">
              <td colspan="2">
                <a href="salling.php" class="btn btn-success"><i class="fas fa-cart-plus"></i> Continue Shopping</a>
                <a href="action.php?clear=all" id="clearCartBtn" class="btn btn-outline-danger"> <i class="fas fa-trash"></i> Clear Cart</a>
              </td>
              <td></td>
              <td></td>
              <td colspan="2">
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<a href="checkout.php" id="checkoutBtn" class="btn btn-info <?= $cart_count==0 ? 'disabled' : '' ?>"><i class="far fa-credit-card"></i> Checkout</a>
              </td>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>

      <!-- Mobile cards (only rendered for small screens) -->
      <?php if ($cart_count > 0): ?>
        <?php foreach ($items as $r): ?>
          <!-- Mobile card -->
<div class="mobile-card">
  <div class="mobile-product-name"><?= htmlspecialchars($r['medicine_name']) ?></div>

  <!-- Hidden price for JS -->
  <input type="hidden" class="pprice" value="<?= number_format($r['product_price'], 2, '.', '') ?>">

  <div class="d-flex justify-content-between mb-2">
    <div><span class="mobile-label">Drug ID:</span> <?= htmlspecialchars($r['product_id']) ?></div>
    <div><span class="mobile-label">Unit:</span> <span class="etb-currency"></span><?= number_format($r['product_price'],2) ?></div>
  </div>

  <div class="d-flex justify-content-between align-items-center">
    <div style="flex:1">
      <div class="quantity-controls">
        <button class="btn btn-outline-secondary btn-sm qty-btn minus-btn" type="button">-</button>
        <input type="number" class="form-control qty-input itemQtyInput" value="<?= intval($r['qty']) ?>"
               min="1" max="<?= intval($r['stock_quantity']) ?>"
               data-cart-id="<?= intval($r['cart_id']) ?>"
               data-product-id="<?= intval($r['product_id']) ?>"
               data-stock-max="<?= intval($r['stock_quantity']) ?>"
               data-product-name="<?= htmlspecialchars($r['medicine_name']) ?>">
        <button class="btn btn-outline-secondary btn-sm qty-btn plus-btn" type="button">+</button> 
      </div>
    </div>
    <div class="text-right">
      <div class="itemTotal" data-total="<?= number_format($r['total_price'],2,'.','') ?>">
        <span class="etb-currency"></span><?= number_format($r['total_price'],2) ?>
      </div>
      <a href="action.php?remove=<?= intval($r['cart_id']) ?>" class="btn btn-danger btn-sm mt-2 removeCartItemBtn"><i class="fas fa-trash"></i> Remove</a>
    </div>
  </div>
</div>

        <?php endforeach; ?>

        <!-- mobile total card -->
        <div class="mobile-card grand-total-row">
          <div class="d-flex justify-content-between mb-2">
            <div><strong>Grand Total</strong></div>
            <div id="mobileGrandTotal"><b><span class="etb-currency"></span><?= number_format($grand_total,2) ?></b></div>
          </div>
          <div class="d-flex gap-2">
            <a href="salling.php" class="btn btn-success"><i class="fas fa-cart-plus"></i> Continue</a>
            <a href="action.php?clear=all" class="btn btn-outline-danger btn-block" id="clearCartBtnMobile"><i class="fas fa-trash"></i> Clear</a>
            <a href="checkout.php" class="btn btn-info btn-block" id="checkout"><i class="far fa-credit-card"></i> Checkout</a>
          </div>
        </div>
      <?php else: ?>
        <!-- show empty cart for mobile/desktop -->
        <div class="empty-cart">
          <div style="font-size:36px;color:#e9ecef;"><i class="fas fa-shopping-cart"></i></div>
          <h5 class="mt-3">Your cart is empty</h5>
          <p class="text-muted">Add products to your cart to see them here.</p>
          <a href="salling.php" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> Start Shopping</a>
        </div>
      <?php endif; ?>
    </div>
        <br>
        <br>
  </div> <!-- /.page-wrap -->

  <!-- JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
  <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
  <script src="../../src/facebox.js"></script>

  <script>
    // Function to clear product multipliers from localStorage
    function clearMultipliers() {
        console.log("Clearing product multipliers from localStorage...");
        localStorage.removeItem('productMultiplier');
        // Use sessionStorage to signal clearing across tabs for immediate effect
        sessionStorage.setItem('multipliersCleared', Date.now()); 
    }

    // Listen for storage events to sync multiplier clearing across tabs
    window.addEventListener('storage', function(e) {
        if (e.key === 'multipliersCleared' && e.newValue !== e.oldValue) {
            console.log("Storage event detected: multipliers cleared in another tab.");
            localStorage.removeItem('productMultiplier');
        }
    });

    $(document).ready(function(){
        // Initialize DataTable
        var table = $('#cartTable').DataTable({
            responsive: true,
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
            ordering: false,
            columnDefs: [{ 
                responsivePriority: 1, 
                targets: -1 // Ensure actions column is always visible
            }],
            language: {
                emptyTable: "Your cart is empty. Start shopping!",
                zeroRecords: "No matching drugs found in your cart."
            }
        });
        
        // Facebox popup forms
        $("a[id*=popup").facebox({
            loadingImage: '../../src/img/loading.gif',
            closeImage: '../../src/img/closelabel.png'
        });
        $("#sendToCashierBtn, #sendToCashierBtnMobile").on("click", function() {
            sendToCashier(this);
        });

        function clearCart(e, element) {
            if (confirm('Are you sure you want to clear your entire cart? This action cannot be undone.')) {
                clearMultipliers();
                return true; // Proceed with the href action
            }
            e.preventDefault(); // Prevent default action if user cancels
            return false;
        }

        $("#clearCartBtn, #clearCartBtnMobile").on("click", function(e) {
            return clearCart(e, this);
        });
        
        function checkout(e, element) {
            if ($(element).hasClass('disabled')) {
                e.preventDefault();
                alert("Your cart is empty. Please add items before checking out.");
                return false;
            }
            if (checkout) {
                clearMultipliers(); // Clear multipliers before proceeding to checkout
                return true;
            }
            e.preventDefault(); // Prevent default action if user cancels
            return false;
        }
        
        $("#checkoutBtn, #checkoutBtnMobile").on("click", function(e) {
            return checkout(e, this);
        });
        
        // When removing a single item, check if cart becomes empty to clear multipliers
        $(document).on("click", ".removeCartItemBtn", function(e) {
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                // Estimate if cart will become empty or nearly empty
                if ($("table#cartTable tbody tr").length <= 1 || $(".mobile-card").length <= 2) {
                    clearMultipliers();
                }
                return true; // Proceed with the href action
            }
            e.preventDefault(); // Prevent default action if user cancels
            return false;
        });
        // Search
        function performCartSearch(){
            var value=$("#cartSearchBar").val().toLowerCase();
            
            // For desktop view
            table.search(value).draw();
            
            // For mobile view
            $(".mobile-card").show();
            if (value) {
                $(".mobile-card").each(function() {
                    var text = $(this).text().toLowerCase();
                    if (text.indexOf(value) === -1) {
                        $(this).hide();
                    }
                });
            }
        }
        
        $("#cartSearchForm").on("submit",function(e){e.preventDefault(); performCartSearch();});
        $("#cartSearchForm button").on("click",performCartSearch);
        $("#cartSearchBar").on("keyup",performCartSearch);

        // Quantity Plus/Minus
        $(document).on('click','.plus-btn',function(){
            var $input=$(this).closest('.quantity-controls').find('.itemQtyInput');
            var currentVal=parseInt($input.val())||1;
            var maxVal=parseInt($input.data('stock-max'))||1;
            if(currentVal<maxVal){ 
                $input.val(currentVal+1).trigger('change'); 
            } else { 
                alert("Maximum available is "+maxVal); 
            }
        });
        
        $(document).on('click','.minus-btn',function(){
            var $input=$(this).closest('.quantity-controls').find('.itemQtyInput');
            var currentVal=parseInt($input.val())||1;
            if(currentVal>1){ 
                $input.val(currentVal-1).trigger('change'); 
            }
        });

        // Quantity Change
        $(document).on('change','.itemQtyInput',function(){
            var $input=$(this);
            var cartId=$input.data('cart-id');
            var newQty=parseInt($input.val())||1;
            var maxQty=parseInt($input.data('stock-max'))||1;

            if(newQty>maxQty){ 
                alert("Only "+maxQty+" available."); 
                $input.val(maxQty); 
                newQty=maxQty; 
            }
            if(newQty<1){ 
                $input.val(1); 
                newQty=1; 
            }

            // Update row total locally for both desktop and mobile
            var unitPrice;
            var rowTotal;
            
            // Desktop update
            var $row=$input.closest('tr');
            if ($row.length) {
                unitPrice=parseFloat($row.find('.pprice').val())||0;
                rowTotal=newQty*unitPrice;
                $row.find('.itemTotal').html('<span class="etb-currency"></span>'+rowTotal.toFixed(2));
            }
            
            // Mobile update
var $mobileCard = $input.closest('.mobile-card');
if ($mobileCard.length) {
    var unitPrice = parseFloat($mobileCard.find('.pprice').val()) || 0;
    var rowTotal = newQty * unitPrice;
    var $mobileTotal = $mobileCard.find('.itemTotal');
    $mobileTotal.data('total', rowTotal); // update data attribute
    $mobileTotal.html('<span class="etb-currency"></span>' + rowTotal.toFixed(2));
}

// Recalculate grand total
var grandTotal = 0;
$('.itemTotal').each(function(){
    grandTotal += parseFloat($(this).data('total')) || 0;
});
$('#cartGrandTotal, #mobileGrandTotal').html('<b><span class="etb-currency"></span>' + grandTotal.toFixed(2) + '</b>');

            // Sync with backend
            $.post("action.php",{update_cart_quantity:1,cart_id:cartId,quantity:newQty},function(res){
                try{ 
                    var data=JSON.parse(res);
                    if(!data.success){ 
                        alert(data.message||"Error syncing"); 
                        location.reload(); 
                    }
                } catch(e){ 
                    location.reload(); 
                }
            });
        });
    });
    
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