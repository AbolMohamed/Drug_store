<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (!session_id()) session_start();
require '../../dbcon.php';

// Check if user is logged in and has cashier privileges
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user_role = isset($_SESSION['usertype']) ? $_SESSION['usertype'] : '';

// Fetch pending orders
$pending_orders = [];
$stmt = $con->prepare('SELECT o.id, o.order_number, o.customer_name, o.order_total, o.created_at, u.username 
                       FROM orders o 
                       JOIN tbl_users u ON o.user_id = u.id 
                       WHERE o.status = "Pending" 
                       ORDER BY o.created_at DESC');
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pending_orders[] = $row;
}
$stmt->close();

// Fetch completed orders (today)
$completed_orders = [];
$today = date('Y-m-d');
$stmt = $con->prepare('SELECT o.id, o.order_number, o.customer_name, o.order_total, o.payment_method, o.created_at, u.username 
                       FROM orders o 
                       JOIN tbl_users u ON o.user_id = u.id 
                       WHERE DATE(o.created_at) = ? AND o.status = "Completed" 
                       ORDER BY o.created_at DESC');
$stmt->bind_param('s', $today);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $completed_orders[] = $row;
}
$stmt->close();

// Fetch reminders for the current user
$reminders = [];
$stmt = $con->prepare('SELECT * FROM cashier_reminders WHERE user_id = ? AND is_completed = 0 ORDER BY created_at DESC');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reminders[] = $row;
}
$stmt->close();

// Add new reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reminder'])) {
    $reminder_text = trim($_POST['reminder_text']);
    
    if (!empty($reminder_text)) {
        $stmt = $con->prepare('INSERT INTO cashier_reminders (user_id, reminder_text) VALUES (?, ?)');
        $stmt->bind_param('is', $user_id, $reminder_text);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Reminder added successfully!";
            $_SESSION['showAlert'] = true;
        } else {
            $_SESSION['message'] = "Error adding reminder: " . $con->error;
            $_SESSION['showAlert'] = true;
        }
        $stmt->close();
        
        header("Location: cashier.php");
        exit();
    }
}

// Mark reminder as completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_reminder'])) {
    $reminder_id = $_POST['reminder_id'];
    
    $stmt = $con->prepare('UPDATE cashier_reminders SET is_completed = 1, completed_at = NOW() WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $reminder_id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Reminder marked as completed!";
        $_SESSION['showAlert'] = true;
    } else {
        $_SESSION['message'] = "Error updating reminder: " . $con->error;
        $_SESSION['showAlert'] = true;
    }
    $stmt->close();
    
    header("Location: cashier.php");
    exit();
}

// Delete reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_reminder'])) {
    $reminder_id = $_POST['reminder_id'];
    
    $stmt = $con->prepare('DELETE FROM cashier_reminders WHERE id = ? AND user_id = ?');
    $stmt->bind_param('ii', $reminder_id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Reminder deleted!";
        $_SESSION['showAlert'] = true;
    } else {
        $_SESSION['message'] = "Error deleting reminder: " . $con->error;
        $_SESSION['showAlert'] = true;
    }
    $stmt->close();
    
    header("Location: cashier.php");
    exit();
}

// Process order if requested
$order_details = [];
$order_items = [];
if (isset($_GET['process_order']) && is_numeric($_GET['process_order'])) {
    $order_id = $_GET['process_order'];
    
    // Fetch order details
    $stmt = $con->prepare('SELECT o.*, u.username 
                           FROM orders o 
                           JOIN tbl_users u ON o.user_id = u.id 
                           WHERE o.id = ?');
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $order_result = $stmt->get_result();
    if ($order_result->num_rows > 0) {
        $order_details = $order_result->fetch_assoc();
    }
    $stmt->close();
    
    // Fetch order items
    $stmt = $con->prepare('SELECT oi.*, s.medicine_name, s.remain_quantity 
                           FROM order_items oi 
                           JOIN stock s ON oi.product_id = s.id 
                           WHERE oi.order_id = ?');
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    while ($row = $items_result->fetch_assoc()) {
        $order_items[] = $row;
    }
    $stmt->close();
}

// Complete order payment (using checkout logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_payment'])) {
    $order_id = $_POST['order_id'];
    $payment_method = $_POST['payment_method'];
    $is_free_service = isset($_POST['free_service']) ? 1 : 0;
    
    $con->begin_transaction();
    
    try {
        // Fetch order details
        $stmt = $con->prepare('SELECT * FROM orders WHERE id = ?');
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        
        if (!$order) {
            throw new Exception("Order not found!");
        }
        
        // Fetch order items with stock information
        $stmt = $con->prepare('SELECT oi.*, s.remain_quantity 
                               FROM order_items oi 
                               JOIN stock s ON oi.product_id = s.id 
                               WHERE oi.order_id = ?');
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $items_result = $stmt->get_result();
        $order_items = $items_result->fetch_all(MYSQLI_ASSOC);
        
        // Validate stock availability
        foreach ($order_items as $item) {
            if ($item['quantity'] > $item['remain_quantity']) {
                throw new Exception("Only {$item['remain_quantity']} items available for {$item['product_name']}");
            }
        }
        
        // Update order status and payment method
        $stmt = $con->prepare('UPDATE orders SET status = "Completed", payment_method = ?, free_service = ? WHERE id = ?');
        $stmt->bind_param('sii', $payment_method, $is_free_service, $order_id);
        $stmt->execute();
        
        // Update stock quantities for all items in the order
        foreach ($order_items as $item) {
            $update_stock = $con->prepare('UPDATE stock SET used_quantity = used_quantity + ?, remain_quantity = remain_quantity - ? WHERE id = ?');
            $update_stock->bind_param('iii', $item['quantity'], $item['quantity'], $item['product_id']);
            $update_stock->execute();
            
            // Update free_service status in order_items if needed
            if ($is_free_service) {
                $update_item = $con->prepare('UPDATE order_items SET free_service = ? WHERE id = ?');
                $update_item->bind_param('ii', $is_free_service, $item['id']);
                $update_item->execute();
            }
        }
        
        $con->commit();
        
        $_SESSION['message'] = "Payment processed successfully! Order completed.";
        $_SESSION['showAlert'] = true;
        header("Location: cashier.php");
        exit();
    } catch (Exception $e) {
        $con->rollback();
        $_SESSION['message'] = "Error processing payment: " . $e->getMessage();
        $_SESSION['showAlert'] = true;
        header("Location: cashier.php?process_order=" . $order_id);
        exit();
    }
}

// Cancel order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = $_POST['order_id'];
    
    // Update order status to Cancelled
    $stmt = $con->prepare('UPDATE orders SET status = "Cancelled" WHERE id = ?');
    $stmt->bind_param('i', $order_id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Order cancelled successfully!";
        $_SESSION['showAlert'] = true;
        header("Location: cashier.php");
        exit();
    } else {
        $_SESSION['message'] = "Error cancelling order: " . $con->error;
        $_SESSION['showAlert'] = true;
        header("Location: cashier.php?process_order=" . $order_id);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Drug Store - Cashier Panel</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="../css/style.css" />

    <style>
        body { 
            padding: 20px; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f0f4f8; 
        }
        .cashier-container { 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.08); 
            padding: 25px; 
            margin-top: 20px; 
        }
        .header-title { 
            color: #17a2b8; 
            font-weight: 700; 
            padding: 15px 0; 
            margin-bottom: 25px;
            border-bottom: 2px solid #e9ecef;
        }
        .card-dashboard {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
        }
        .card-dashboard:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .card-pending {
            border-left: 4px solid #ffc107;
        }
        .card-completed {
            border-left: 4px solid #28a745;
        }
        .card-total {
            border-left: 4px solid #17a2b8;
        }
        .card-reminders {
            border-left: 4px solid #6f42c1;
        }
        .order-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .order-card:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .order-card.active {
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
        }
        .etb-currency:before { 
            content: "ETB "; 
            font-weight: bold; 
            color: #17a2b8; 
        }
        .badge-pending {
            background: #ffc107;
            color: #212529;
        }
        .badge-completed {
            background: #28a745;
        }
        .badge-cancelled {
            background: #dc3545;
        }
        .badge-processing {
            background: #17a2b8;
        }
        .payment-method-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .cash-badge {
            background: #d4edda;
            color: #155724;
        }
        .card-badge {
            background: #d1ecf1;
            color: #0c5460;
        }
        .mobile-badge {
            background: #fff3cd;
            color: #856404;
        }
        .order-details-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .product-row {
            border-bottom: 1px solid #dee2e6;
            padding: 10px 0;
        }
        .alert-notification { 
            border-radius: 8px; 
            border: none; 
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }
        .customer-info {
            background: #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .stock-warning { 
            color: #dc3545; 
            font-size: 0.8rem; 
        }
        
        /* Reminder styles */
        .reminder-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-left: 4px solid #6f42c1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .reminder-text {
            flex: 1;
            margin-right: 15px;
        }
        .reminder-actions {
            display: flex;
            gap: 8px;
        }
        .reminder-form {
            margin-top: 15px;
        }
        .reminder-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .completed-reminder {
            text-decoration: line-through;
            opacity: 0.7;
            border-left-color: #28a745;
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .cashier-container {
                padding: 20px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            .header-title {
                font-size: 1.5rem;
            }
            .card-dashboard {
                margin-bottom: 15px;
            }
            .reminder-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .reminder-actions {
                margin-top: 10px;
                align-self: flex-end;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            .cashier-container {
                padding: 15px;
            }
            .header-title {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
  <?php include 'header.php';?>
  <br><br><br>

  <div class="container">
    <h2 class="header-title"><i class="fas fa-cash-register"></i> Cashier Panel</h2>

    <!-- Alert -->
    <div class="alert alert-success alert-dismissible mt-3 alert-notification" 
         style="display:<?php if(isset($_SESSION['showAlert']) && $_SESSION['showAlert']===true){echo 'block';} else{echo 'none';} unset($_SESSION['showAlert']); ?>">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <strong><?php if(isset($_SESSION['message'])){echo htmlspecialchars($_SESSION['message']);} unset($_SESSION['message']); ?></strong>
    </div>

    <!-- Dashboard Cards -->
    <div class="row">
        <div class="col-md-3">
            <div class="card card-dashboard card-pending">
                <div class="card-body">
                    <h5 class="card-title">Pending Orders</h5>
                    <h2 class="card-text"><?php echo count($pending_orders); ?></h2>
                    <p class="card-text">Awaiting payment processing</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-dashboard card-completed">
                <div class="card-body">
                    <h5 class="card-title">Completed Today</h5>
                    <h2 class="card-text"><?php echo count($completed_orders); ?></h2>
                    <p class="card-text">Successful transactions</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-dashboard card-total">
                <div class="card-body">
                    <h5 class="card-title">Today's Revenue</h5>
                    <h2 class="card-text">
                        <span class="etb-currency"></span>
                        <?php 
                        $total_revenue = 0;
                        foreach ($completed_orders as $order) {
                            $total_revenue += $order['order_total'];
                        }
                        echo number_format($total_revenue, 2); 
                        ?>
                    </h2>
                    <p class="card-text">Total earnings for today</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-dashboard card-reminders">
                <div class="card-body">
                    <h5 class="card-title">My Reminders</h5>
                    <h2 class="card-text"><?php echo count($reminders); ?></h2>
                    <p class="card-text">Notes to remember</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <!-- Pending Orders Column -->
        <div class="col-md-5">
            <div class="cashier-container">
                <h4><i class="fas fa-clock"></i> Pending Orders</h4>
                <hr>
                
                <?php if (count($pending_orders) > 0): ?>
                    <?php foreach ($pending_orders as $order): ?>
                        <div class="order-card <?php echo (isset($order_id) && $order_id == $order['id']) ? 'active' : ''; ?>" 
                             onclick="window.location='?process_order=<?php echo $order['id']; ?>'">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5>Order #<?php echo $order['order_number']; ?></h5>
                                    <p class="mb-1">Customer: <?php echo htmlspecialchars($order['customer_name'] ? $order['customer_name'] : 'Guest'); ?></p>
                                    <p class="mb-0">Date: <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></p>
                                </div>
                                <div class="text-right">
                                    <span class="badge badge-pending">Pending</span>
                                    <h4 class="mt-2"><span class="etb-currency"></span><?php echo number_format($order['order_total'], 2); ?></h4>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle fa-3x text-muted"></i>
                        <h4 class="mt-3 text-muted">No Pending Orders</h4>
                        <p>All orders have been processed</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Reminders Section -->
            <div class="cashier-container mt-4">
                <h4><i class="fas fa-sticky-note"></i> My Daily Reminders</h4>
                <hr>
                
                <?php if (count($reminders) > 0): ?>
                    <?php foreach ($reminders as $reminder): ?>
                        <div class="reminder-item">
                            <div class="reminder-text">
                                <?php echo htmlspecialchars($reminder['reminder_text']); ?>
                                <div class="reminder-date">
                                    Added: <?php echo date('M j, g:i A', strtotime($reminder['created_at'])); ?>
                                </div>
                            </div>
                            <div class="reminder-actions">
                                <form method="POST" action="cashier.php" class="d-inline">
                                    <input type="hidden" name="reminder_id" value="<?php echo $reminder['id']; ?>">
                                    <button type="submit" name="complete_reminder" class="btn btn-sm btn-success" title="Mark as completed">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <form method="POST" action="cashier.php" class="d-inline">
                                    <input type="hidden" name="reminder_id" value="<?php echo $reminder['id']; ?>">
                                    <button type="submit" name="delete_reminder" class="btn btn-sm btn-danger" title="Delete reminder">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class="fas fa-clipboard-list fa-2x text-muted"></i>
                        <p class="mt-2 text-muted">No reminders yet. Add a note to remember something important.</p>
                    </div>
                <?php endif; ?>
                
                <!-- Add Reminder Form -->
                <div class="reminder-form">
                    <form method="POST" action="cashier.php">
                        <div class="input-group">
                            <input type="text" name="reminder_text" class="form-control" placeholder="Add a new reminder..." required>
                            <div class="input-group-append">
                                <button type="submit" name="add_reminder" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Order Details Column -->
        <div class="col-md-7">
            <div class="cashier-container">
                <?php if (isset($order_details) && !empty($order_details)): ?>
                    <h4><i class="fas fa-receipt"></i> Order Details #<?php echo $order_details['order_number']; ?></h4>
                    <hr>
                    
                    <div class="order-details-container">
                        <!-- Customer Information -->
                        <div class="customer-info">
                            <h5>Customer Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($order_details['customer_name'] ? $order_details['customer_name'] : 'Not provided'); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order_details['customer_email'] ? $order_details['customer_email'] : 'Not provided'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($order_details['customer_phone'] ? $order_details['customer_phone'] : 'Not provided'); ?></p>
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($order_details['customer_address'] ? $order_details['customer_address'] : 'Not provided'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Items -->
                        <h5>Order Items:</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($order_items) > 0): ?>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr class="product-row">
                                                <td>
                                                    <?php echo htmlspecialchars($item['medicine_name']); ?>
                                                    <?php if ($item['quantity'] > $item['remain_quantity']): ?>
                                                        <div class="stock-warning">Only <?php echo $item['remain_quantity']; ?> available in stock</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="etb-currency"></span><?php echo number_format($item['product_price'], 2); ?></td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td><span class="etb-currency"></span><?php echo number_format($item['total_price'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No items found for this order</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-right"><strong>Total:</strong></td>
                                        <td><strong><span class="etb-currency"></span><?php echo number_format($order_details['order_total'], 2); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <hr>
                        
                        <!-- Payment Form -->
                        <h5>Process Payment:</h5>
                        <form method="POST" action="cashier.php" id="paymentForm">
                            <input type="hidden" name="order_id" value="<?php echo $order_details['id']; ?>">
                            
                            <div class="form-group">
                                <label><strong>Payment Method:</strong></label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cash" value="Cash" checked>
                                    <label class="form-check-label" for="cash">
                                        <span class="payment-method-badge cash-badge">Cash</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="card" value="Card">
                                    <label class="form-check-label" for="card">
                                        <span class="payment-method-badge card-badge">Credit/Debit Card</span>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="mobile" value="Mobile Payment">
                                    <label class="form-check-label" for="mobile">
                                        <span class="payment-method-badge mobile-badge">Mobile Payment</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="freeService" name="free_service" value="1">
                                <label class="form-check-label" for="freeService">Free Service</label>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <form method="POST" action="cashier.php" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?php echo $order_details['id']; ?>">
                                    <button type="submit" name="cancel_order" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this order?')">
                                        <i class="fas fa-times-circle"></i> Cancel Order
                                    </button>
                                </form>
                                <div>
                                    <a href="cashier.php" class="btn btn-secondary">Back</a>
                                    <button type="submit" name="complete_payment" class="btn btn-success" onclick="return validateStock()">
                                        <i class="fas fa-check-circle"></i> Complete Payment
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-receipt fa-3x text-muted"></i>
                        <h4 class="mt-3 text-muted">Select an Order</h4>
                        <p>Choose a pending order from the list to view details and process payment</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Transactions -->
            <?php if (count($completed_orders) > 0): ?>
            <div class="cashier-container mt-4">
                <h4><i class="fas fa-history"></i> Today's Transactions</h4>
                <hr>
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completed_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['order_number']; ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_name'] ? $order['customer_name'] : 'Guest'); ?></td>
                                    <td><span class="etb-currency"></span><?php echo number_format($order['order_total'], 2); ?></td>
                                    <td>
                                        <?php 
                                        $badge_class = '';
                                        if ($order['payment_method'] === 'Cash') $badge_class = 'cash-badge';
                                        else if ($order['payment_method'] === 'Card') $badge_class = 'card-badge';
                                        else if ($order['payment_method'] === 'Mobile Payment') $badge_class = 'mobile-badge';
                                        else $badge_class = 'cash-badge'; // Default
                                        ?>
                                        <span class="payment-method-badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($order['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('g:i A', strtotime($order['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <br><br>
  </div>

  <!-- JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>

  <script>
    $(document).ready(function(){
        // Initialize DataTable for transactions
        $('table').DataTable({
            responsive: true,
            pageLength: 5,
            lengthMenu: [[5, 10, 20, -1], [5, 10, 20, "All"]],
            ordering: true,
            order: [[0, 'desc']]
        });
        
        // Auto-refresh the page every 60 seconds to check for new orders
        setInterval(function() {
            // Only refresh if we're not in the middle of processing an order
            if (!window.location.href.includes('process_order')) {
                window.location.reload();
            }
        }, 60000);
    });
    
    // Validate stock before completing payment
    function validateStock() {
        let hasStockIssue = false;
        $('.stock-warning').each(function() {
            if ($(this).is(':visible')) {
                hasStockIssue = true;
                return false; // break out of loop
            }
        });
        
        if (hasStockIssue) {
            if (!confirm('Some items have insufficient stock. Are you sure you want to proceed with the payment?')) {
                return false;
            }
        }
        
        return true;
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
</body>
</html>