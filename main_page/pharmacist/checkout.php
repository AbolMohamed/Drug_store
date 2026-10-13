<?php
session_start();
require '../../dbcon.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}
$user_id = $_SESSION['user_id'];

// Redirect if this user's cart is empty
$stmt = $con->prepare('SELECT COUNT(*) AS num_items FROM cart WHERE user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
if ($row['num_items'] == 0) {
    $_SESSION['message'] = 'Your cart is empty. Please add items before checkout.';
    $_SESSION['showAlert'] = 'block';
    header('Location: cart.php');
    exit();
}

// Process checkout form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize inputs
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING) ?? '';
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '';
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING) ?? '';
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING) ?? '';
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING) ?? 'Cash on Delivery';
    $is_free_service = isset($_POST['free_service']) ? 1 : 0;

    // Begin transaction
    $con->begin_transaction();

    try {
        // Fetch this user's cart items with product details
        $stmt = $con->prepare('SELECT c.*, s.id as product_id, s.remain_quantity 
                              FROM cart c 
                              JOIN stock s ON c.product_id = s.id 
                              WHERE c.user_id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $cart_items = $result->fetch_all(MYSQLI_ASSOC);

        // Validate stock and prepare order data
        $products = [];
        $grand_total = 0;
        $order_items = [];
        
        foreach ($cart_items as $item) {
            $qty = max(1, $item['qty'] ?? 1);
            $multiplier = max(1, $item['multiplier'] ?? 1);
            $realQty = $qty * $multiplier;
            $max_qty = $item['remain_quantity'];
            
            // Check stock availability
            if ($realQty > $max_qty) {
                throw new Exception("Only {$max_qty} items available for {$item['product_name']}");
            }
            
            $item_total = $item['product_price'] * $realQty;
            $products[] = "{$item['product_name']} (x{$realQty})";
            $grand_total += $item_total;
            
            $order_items[] = [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'price' => $item['product_price'],
                'qty' => $realQty,
                'total' => $item_total
            ];
        }

        $products_str = implode(', ', $products);
        $order_date = date('Y-m-d H:i:s');
        $status = 'Completed';
        $order_number = 'ORD' . date('YmdHis') . rand(100, 999);

        // Insert into orders table
        $stmt = $con->prepare('INSERT INTO orders 
                              (order_number, customer_name, customer_email, customer_phone, customer_address, 
                               products, payment_method, order_total, order_date, status, 
                               free_service, user_id) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param(
            'sssssssdssii',
            $order_number,
            $name,
            $email,
            $phone,
            $address,
            $products_str,
            $payment_method,
            $grand_total,
            $order_date,
            $status,
            $is_free_service,
            $user_id
        );
        $stmt->execute();
        $order_id = $con->insert_id;

        // Insert order items and update stock
        foreach ($order_items as $item) {
            // Insert into order_items
            $stmt = $con->prepare('INSERT INTO order_items 
                                  (order_id, product_id, product_name, product_price, 
                                   quantity, total_price, free_service, user_id) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param(
                'iisdidii',
                $order_id,
                $item['product_id'],
                $item['product_name'],
                $item['price'],
                $item['qty'],
                $item['total'],
                $is_free_service,
                $user_id
            );
            $stmt->execute();

            // Update stock - removed act_remain_quantity as it doesn't exist in your schema
            $stmt2 = $con->prepare('UPDATE stock 
                                   SET remain_quantity = remain_quantity - ?, 
                                       used_quantity = used_quantity + ? 
                                   WHERE id = ?');
            $stmt2->bind_param('iii', $item['qty'], $item['qty'], $item['product_id']);
            $stmt2->execute();
        }

        // Clear this user's cart
        $stmt = $con->prepare('DELETE FROM cart WHERE user_id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();

        // Commit transaction
        $con->commit();

        $_SESSION['order_success'] = true;
        $_SESSION['order_id'] = $order_id;
        $_SESSION['order_number'] = $order_number;
        $_SESSION['message'] = 'Order Completed Successfully! Your Order Number: ' . $order_number;
        $_SESSION['showAlert'] = 'block';
        
        // Clear multipliers from localStorage via session storage event
        $_SESSION['clear_multipliers'] = true;
        
        header('Location: salling.php');
        exit();

    } catch (Exception $e) {
        $con->rollback();
        $_SESSION['message'] = 'Checkout failed: ' . $e->getMessage();
        $_SESSION['showAlert'] = 'block';
        header('Location: checkout.php');
        exit();
    }
}

// Get cart items for display
$stmt = $con->prepare('SELECT c.*, s.id as product_id, s.remain_quantity 
                      FROM cart c 
                      JOIN stock s ON c.product_id = s.id 
                      WHERE c.user_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$grand_total = 0;
foreach ($cart_items as $item) {
    $realQty = $item['qty']; // No multiplier
    $grand_total += $item['product_price'] * $realQty;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>HDS Drug Store - Checkout</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .form-control:required:invalid { border-color: #dc3545; }
        .form-control:required:valid { border-color: #28a745; }
        .order-summary { background-color: #f8f9fa; border-radius: 5px; padding: 20px; }
        .stock-warning { color: #dc3545; font-size: 0.8rem; }
        .product-id-badge { 
            background-color: #17a2b8; 
            color: white; 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-size: 0.8em;
        }
        .multiplier-badge {
            background-color: #6c757d;
            color: white;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 0.7em;
            margin-left: 5px;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<div class="container py-5">
    <?php if (isset($_SESSION['showAlert']) && isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= strpos($_SESSION['message'], 'failed') !== false ? 'danger' : 'success' ?> alert-dismissible">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php unset($_SESSION['showAlert'], $_SESSION['message']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0"><i class="fas fa-user-circle"></i> Customer Information</h4>
                </div>
                <div class="card-body">
                    <form id="checkoutForm" method="POST">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="name">Full Name <small class="text-muted">(Optional)</small></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="email">Email <small class="text-muted">(Optional)</small></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="phone">Phone Number <small class="text-muted">(Optional)</small></label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="payment_method">Payment Method</label>
                                <select class="form-control" id="payment_method" name="payment_method" required>
                                    <option value="Cash on Delivery" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Cash on Delivery' ? 'selected' : 'selected') ?>>Cash on Delivery</option>
                                    <option value="Credit/Debit Card" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Credit/Debit Card' ? 'selected' : '') ?>>Credit/Debit Card</option>
                                    <option value="Mobile Payment" <?= (isset($_POST['payment_method']) && $_POST['payment_method'] == 'Mobile Payment' ? 'selected' : '') ?>>Mobile Payment</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="address">Delivery Address <small class="text-muted">(Optional)</small></label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="freeService" name="free_service" <?= isset($_POST['free_service']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="freeService">Free Service</label>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0"><i class="fas fa-receipt"></i> Order Summary</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- In the order summary table, remove multiplier calculations -->
<?php foreach ($cart_items as $item): 
    $realQty = $item['qty']; // No multiplier
    $total = $item['product_price'] * $realQty;
?>
<tr>
    <td>
        <?= htmlspecialchars($item['product_name']) ?>
        <span class="product-id-badge">ID: <?= $item['product_id'] ?></span>
    </td>
    <td><?= $realQty ?></td>
    <td><?= number_format($total, 2) ?> ETB</td>
</tr>
<?php if ($realQty > $item['remain_quantity']): ?>
<tr class="stock-warning">
    <td colspan="3">Only <?= $item['remain_quantity'] ?> available in stock</td>
</tr>
<?php endif; ?>
<?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2">Total:</th>
                                    <th><?= number_format($grand_total, 2) ?> ETB</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <button type="button" id="sendToCashierBtn" class="btn btn-warning btn-block btn-lg mt-2">
                         <i class="fas fa-paper-plane"></i> Send to Cashier
                    </button>
                    <button type="submit" form="checkoutForm" class="btn btn-info btn-block btn-lg">
                        <i class="fas fa-check-circle"></i> Complete Order
                    </button>
                    <a href="cart.php" class="btn btn-outline-secondary btn-block mt-2">
                        <i class="fas fa-arrow-left"></i> Back to Cart
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
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
$(document).ready(function() {
    // Validate form before submission
    $('#checkoutForm').on('submit', function(e) {
        let valid = true;
        let stockErrors = [];
        
        // Check if any items exceed stock
        $('.stock-warning').each(function() {
            if ($(this).is(':visible')) {
                stockErrors.push($(this).text());
                valid = false;
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Some items in your cart exceed available stock:\n' + stockErrors.join('\n') + '\n\nPlease adjust quantities before checkout.');
            window.location.href = 'cart.php';
        }
    });
    
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').alert('close');
    }, 5000);
    
    // Clear multipliers from localStorage if session indicates it
    <?php if (isset($_SESSION['clear_multipliers'])): ?>
    localStorage.removeItem('productMultiplier');
    <?php unset($_SESSION['clear_multipliers']); ?>
    <?php endif; ?>
    $(document).ready(function () {
    $('#sendToCashierBtn').on('click', function () {
        if (!confirm("Send this order to the cashier?")) return;

        $.ajax({
            url: 'action.php',
            method: 'POST',
            dataType: 'json',
            data: {
                send_to_cashier: true,
                user_id: <?= $user_id ?>,
                name: $('#name').val(),
                email: $('#email').val(),
                phone: $('#phone').val(),
                address: $('#address').val(),
                payment_method: $('#payment_method').val()
            },
            success: function (response) {
                if (response.success) {
                    alert(response.message + "\nOrder Number: " + response.order_number);
                    window.location.href = "salling.php";
                } else {
                    alert("Error: " + response.message);
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX Error:", error);
                alert("Something went wrong. Please try again.");
            }
        });
    });
});
});
</script>
</body>
<?php include 'footer.php'; ?>
</html>