<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!session_id()) session_start();
require '../../dbcon.php';

// Ensure logged-in user
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

/**
 * Sanitize helper
 */
function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Get cart grand total
 */
function getGrandTotal($con, $user_id) {
    $stmt = $con->prepare("SELECT SUM(total_price) AS gt FROM cart WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? number_format($row['gt'], 2) : "0.00";
}
/**
 * ===================== Send to Cashier =====================
 */
if (isset($_POST['send_to_cashier'])) {
    $user_id = intval($_POST['user_id']);
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'Pending';

    // Check if cart has items
    $cart_stmt = $con->prepare("SELECT COUNT(*) AS cnt FROM cart WHERE user_id=?");
    $cart_stmt->bind_param("i", $user_id);
    $cart_stmt->execute();
    $cart_count = $cart_stmt->get_result()->fetch_assoc()['cnt'];

    if ($cart_count == 0) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit();
    }

    $con->begin_transaction();

    try {
        // Generate order number
        $order_number = 'ORD' . date('YmdHis') . rand(100, 999);

        // Calculate grand total
        $total_stmt = $con->prepare("SELECT SUM(total_price) AS total FROM cart WHERE user_id=?");
        $total_stmt->bind_param("i", $user_id);
        $total_stmt->execute();
        $grand_total = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;

        // Get cart items for products list
        $items_stmt = $con->prepare("SELECT product_name, qty FROM cart WHERE user_id=?");
        $items_stmt->bind_param("i", $user_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();

        $products_list = [];
        while ($item = $items_result->fetch_assoc()) {
            $products_list[] = "{$item['product_name']} (x{$item['qty']})";
        }
        $products = implode(', ', $products_list);

        // Insert into orders with customer info
        $order_stmt = $con->prepare("INSERT INTO orders 
            (order_number, customer_name, customer_email, customer_phone, customer_address, 
             products, payment_method, order_total, order_date, status, user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending', ?)");
        $order_stmt->bind_param(
            "ssssssdsi",
            $order_number, $name, $email, $phone, $address,
            $products, $payment_method, $grand_total, $user_id
        );
        $order_stmt->execute();
        $order_id = $con->insert_id;

        // Insert order items
        $cart_items = $con->prepare("SELECT product_id, product_name, product_price, qty, total_price FROM cart WHERE user_id=?");
        $cart_items->bind_param("i", $user_id);
        $cart_items->execute();
        $cart_result = $cart_items->get_result();

        while ($cart_item = $cart_result->fetch_assoc()) {
            $item_stmt = $con->prepare("INSERT INTO order_items 
                (order_id, product_id, product_name, product_price, quantity, total_price, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $item_stmt->bind_param("iisidii", $order_id, $cart_item['product_id'], $cart_item['product_name'], $cart_item['product_price'], $cart_item['qty'], $cart_item['total_price'], $user_id);
            $item_stmt->execute();
        }

        // Clear cart
        $clear_cart = $con->prepare("DELETE FROM cart WHERE user_id=?");
        $clear_cart->bind_param("i", $user_id);
        $clear_cart->execute();

        $con->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Order sent to cashier successfully!',
            'order_number' => $order_number
        ]);

    } catch (Exception $e) {
        $con->rollback();
        error_log("Send to cashier error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
    }
    exit();
}



/**
 * ===================== Update cart quantity =====================
 */
if (isset($_POST['update_cart_quantity'])) {
    $cart_id = intval($_POST['cart_id']);
    $qty = max(1, intval($_POST['quantity']));

    // Get product in cart
    $stmt = $con->prepare("SELECT product_id, product_name, product_price FROM cart WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $cart_row = $stmt->get_result()->fetch_assoc();

    if (!$cart_row) {
        echo json_encode(['success'=>false, 'message'=>"Cart item not found"]);
        exit;
    }

    $product_id = $cart_row['product_id'];
    $product_price = $cart_row['product_price'];

    // Check stock
    $stock_stmt = $con->prepare("SELECT remain_quantity FROM stock WHERE id=?");
    $stock_stmt->bind_param("i", $product_id);
    $stock_stmt->execute();
    $stock_row = $stock_stmt->get_result()->fetch_assoc();
    $max_stock = intval($stock_row['remain_quantity'] ?? 0);

    if ($qty > $max_stock) {
        echo json_encode(['success'=>false, 'message'=>"Only $max_stock items available"]);
        exit;
    }

    // Update cart
    $item_total = $product_price * $qty;
    $update = $con->prepare("UPDATE cart SET qty=?, total_price=? WHERE id=? AND user_id=?");
    $update->bind_param("idii", $qty, $item_total, $cart_id, $user_id);
    $update->execute();

    echo json_encode([
        'success'=>true,
        'item_total'=>number_format($item_total, 2),
        'grand_total'=>getGrandTotal($con, $user_id)
    ]);
    exit;
}

/**
 * ===================== Add item to cart =====================
 */
if (isset($_POST['pid'])) {
    $pid = intval($_POST['pid']);
    $pname = sanitize($_POST['pname']);
    $pprice = floatval($_POST['pprice']);
    $pqty = 1;
    $total_price = $pprice * $pqty;

    // Check if in another user's cart
    $check_others = $con->prepare("SELECT COUNT(*) AS cnt FROM cart WHERE product_id=? AND user_id!=?");
    $check_others->bind_param("ii", $pid, $user_id);
    $check_others->execute();
    $in_others = $check_others->get_result()->fetch_assoc()['cnt'];

    if ($in_others > 0) {
        echo '<div class="alert alert-danger">This item is being ordered by another user!</div>';
        exit;
    }

    // Check stock
    $stock = $con->prepare("SELECT remain_quantity FROM stock WHERE id=?");
    $stock->bind_param("i", $pid);
    $stock->execute();
    $stock_row = $stock->get_result()->fetch_assoc();
    if (!$stock_row || $stock_row['remain_quantity'] < $pqty) {
        echo '<div class="alert alert-danger">Not enough stock available!</div>';
        exit;
    }

    // Check if already in cart
    $stmt = $con->prepare("SELECT id, qty FROM cart WHERE product_id=? AND user_id=?");
    $stmt->bind_param("ii", $pid, $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        $ins = $con->prepare("INSERT INTO cart (product_id, product_name, product_price, qty, total_price, user_id) VALUES (?,?,?,?,?,?)");
        $ins->bind_param("isdidi", $pid, $pname, $pprice, $pqty, $total_price, $user_id);
        $ins->execute();
        echo '<div class="alert alert-success">Item added to cart!</div>';
    } else {
        $new_qty = $row['qty'] + $pqty;
        $new_total = $new_qty * $pprice;
        $upd = $con->prepare("UPDATE cart SET qty=?, total_price=? WHERE id=? AND user_id=?");
        $upd->bind_param("idii", $new_qty, $new_total, $row['id'], $user_id);
        $upd->execute();
        echo '<div class="alert alert-info">Cart updated!</div>';
    }
    exit;
}

/**
 * ===================== Get cart items count =====================
 */
if ($_GET['cartItem'] ?? '' === 'cart_item') {
    $stmt = $con->prepare("SELECT COUNT(*) AS cnt FROM cart WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    echo $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    exit;
}

/**
 * ===================== Remove item =====================
 */
if (isset($_GET['remove'])) {
    $id = intval($_GET['remove']);
    $stmt = $con->prepare("DELETE FROM cart WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $_SESSION['message'] = 'Item removed!';
    header("Location: cart.php");
    exit;
}

/**
 * ===================== Clear cart =====================
 */
if (isset($_GET['clear'])) {
    $stmt = $con->prepare("DELETE FROM cart WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $_SESSION['message'] = 'All items removed!';
    header("Location: cart.php");
    exit;
}

/**
 * ===================== Checkout =====================
 */
if ($_POST['action'] ?? '' === 'order') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $products = sanitize($_POST['products']);
    $grand_total = floatval($_POST['grand_total']);
    $address = sanitize($_POST['address']);
    $pmode = sanitize($_POST['pmode']);

    $order_number = 'ORD' . date('YmdHis') . rand(100, 999);
    $con->begin_transaction();

    try {
        $stmt = $con->prepare("INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, customer_address, products, payment_method, order_total, order_date, user_id) VALUES (?,?,?,?,?,?,?,?,NOW(),?)");
        $stmt->bind_param("ssssssdsi", $order_number, $name, $email, $phone, $address, $products, $pmode, $grand_total, $user_id);
        $stmt->execute();
        $order_id = $con->insert_id;

        // Insert order items + update stock
        $cart = $con->prepare("SELECT product_id, product_name, product_price, qty FROM cart WHERE user_id=?");
        $cart->bind_param("i", $user_id);
        $cart->execute();
        $res = $cart->get_result();
        while ($item = $res->fetch_assoc()) {
            $qty = $item['qty'];
            $item_total = $qty * $item['product_price'];
            $oi = $con->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, total_price, user_id) VALUES (?,?,?,?,?,?,?)");
            $oi->bind_param("iisidii", $order_id, $item['product_id'], $item['product_name'], $item['product_price'], $qty, $item_total, $user_id);
            $oi->execute();

            $up = $con->prepare("UPDATE stock SET used_quantity=used_quantity+?, remain_quantity=remain_quantity-? WHERE id=?");
            $up->bind_param("iii", $qty, $qty, $item['product_id']);
            $up->execute();
        }

        $con->prepare("DELETE FROM cart WHERE user_id=?")->bind_param("i", $user_id)->execute();
        $con->commit();

        echo "<div class='text-center'><h2>Order Placed Successfully!</h2><h4>Order Number: {$order_number}</h4></div>";
    } catch (Exception $e) {
        $con->rollback();
        echo "<div class='alert alert-danger'>Order failed: {$e->getMessage()}</div>";
    }
    exit;
}
