<?php 
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location:../login.php");
}
include('../dbcon.php'); 

// Fetch users to populate the 'Sold By' filter
$users = [];
$user_sql = "SELECT id, name FROM tbl_users ORDER BY name ASC";
$user_result = mysqli_query($con, $user_sql);
while ($user_row = mysqli_fetch_assoc($user_result)) {
    $users[] = $user_row;
}

// Handle delete and restore actions
if (isset($_POST['action']) && isset($_POST['order_item_id'])) {
    $order_item_id = intval($_POST['order_item_id']);
    $action = $_POST['action'];
    
    if ($action == 'delete') {
        // Permanently delete the order item
        $delete_sql = "DELETE FROM order_items WHERE id = ?";
        $stmt = mysqli_prepare($con, $delete_sql);
        mysqli_stmt_bind_param($stmt, 'i', $order_item_id);
        $result = mysqli_stmt_execute($stmt);
        
        if ($result) {
            $_SESSION['message'] = "Order item deleted successfully!";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting order item: " . mysqli_error($con);
            $_SESSION['message_type'] = "danger";
        }
    } 
    elseif ($action == 'restore') {
        // Restore to stock - get the order item details first
        $select_sql = "SELECT oi.product_id, oi.quantity, s.remain_quantity 
                      FROM order_items oi 
                      JOIN stock s ON oi.product_id = s.id 
                      WHERE oi.id = ?";
        $stmt = mysqli_prepare($con, $select_sql);
        mysqli_stmt_bind_param($stmt, 'i', $order_item_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $item = mysqli_fetch_assoc($result);
        
        if ($item) {
            // Update stock quantity
            $new_quantity = $item['remain_quantity'] + $item['quantity'];
            $update_sql = "UPDATE stock SET remain_quantity = ? WHERE id = ?";
            $stmt = mysqli_prepare($con, $update_sql);
            mysqli_stmt_bind_param($stmt, 'ii', $new_quantity, $item['product_id']);
            $update_result = mysqli_stmt_execute($stmt);
            
            if ($update_result) {
                // Delete the order item after restoring to stock
                $delete_sql = "DELETE FROM order_items WHERE id = ?";
                $stmt = mysqli_prepare($con, $delete_sql);
                mysqli_stmt_bind_param($stmt, 'i', $order_item_id);
                $delete_result = mysqli_stmt_execute($stmt);
                
                if ($delete_result) {
                    $_SESSION['message'] = "Order restored to stock successfully!";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Error deleting order item after restoration: " . mysqli_error($con);
                    $_SESSION['message_type'] = "danger";
                }
            } else {
                $_SESSION['message'] = "Error updating stock: " . mysqli_error($con);
                $_SESSION['message_type'] = "danger";
            }
        } else {
            $_SESSION['message'] = "Order item not found!";
            $_SESSION['message_type'] = "danger";
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Drug Store - View All Orders</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HDS Drug Store - Pharmacy Management System">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />

    <style>
        body { padding: 20px; font-family: Arial, sans-serif; }
        .summary-badges { margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 10px; }
        .summary-badge { font-weight: 600; font-size: 1rem; padding: 8px 12px; border-radius: 5px; color: white; }
        .badge-revenue { background-color: #17a2b8; }
        .badge-profit { background-color: #28a745; }
        .filter-group { display: flex; flex-wrap: wrap; align-items: center; gap: 15px; margin-bottom: 20px; }
        .filter-group label { margin-right: 8px; font-weight: bold; }
        .table td, .table th { white-space: nowrap; }
        .action-buttons { display: flex; gap: 5px; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<br><br><br>
<h2>Completed Orders</h2>

<!-- Display messages -->
<?php if (isset($_SESSION['message'])): ?>
<div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
    <?php echo $_SESSION['message']; ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
<?php 
unset($_SESSION['message']);
unset($_SESSION['message_type']);
endif; ?>

<div class="filter-group">
    <label for="startDate">From:</label>
    <input type="date" id="startDate" class="form-control" style="width:auto;">

    <label for="endDate">To:</label>
    <input type="date" id="endDate" class="form-control" style="width:auto;">

    <label for="monthFilter">Month:</label>
    <select id="monthFilter" class="form-control" style="width:auto;">
        <option value="all">All Months</option>
        <option value="01">January</option>
        <option value="02">February</option>
        <option value="03">March</option>
        <option value="04">April</option>
        <option value="05">May</option>
        <option value="06">June</option>
        <option value="07">July</option>
        <option value="08">August</option>
        <option value="09">September</option>
        <option value="10">October</option>
        <option value="11">November</option>
        <option value="12">December</option>
    </select>

    <label for="userFilter">Sold By:</label>
    <select id="userFilter" class="form-control" style="width:auto;">
        <option value="all">All Users</option>
        <?php foreach ($users as $user): ?>
            <option value="<?= htmlspecialchars($user['name']) ?>"><?= htmlspecialchars($user['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="summary-badges">
    <span class="summary-badge badge-revenue" id="totalRevenue">Total Revenue: 0.00 ETB</span>
    <span class="summary-badge badge-profit" id="totalProfit">Total Profit: 0.00 ETB</span>
</div>

<table id="salesTable" class="table table-striped table-bordered nowrap" style="width:100%">
    <thead>
        <tr>
            <th>Date & Time</th>
            <th>Drug Name</th>
            <th>Batch</th>
            <th>Sold By</th>
            <th>Quantity</th>
            <th>Price/Unit</th>
            <th>Total Price</th>
            <th>Profit/Unit</th>
            <th>Total Profit</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php
    // Only show orders with status 'Completed'
    $sql = "SELECT 
                oi.id, oi.product_id, oi.product_name, oi.quantity, oi.product_price, 
                oi.total_price, oi.created_at,
                s.product_code, s.profit_price,
                u.name as sold_by_name,
                o.status as order_status
            FROM order_items oi
            JOIN stock s ON oi.product_id = s.id
            JOIN tbl_users u ON oi.user_id = u.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.status = 'Completed'
            ORDER BY oi.created_at DESC";
    $result = mysqli_query($con, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $total_profit = $row['profit_price'] * $row['quantity'];
        echo "<tr>";
        echo "<td>" . date("Y-m-d H:i", strtotime($row['created_at'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['product_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['product_code']) . "</td>";
        echo "<td>" . htmlspecialchars($row['sold_by_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['quantity']) . "</td>";
        echo "<td>" . number_format($row['product_price'], 2) . "</td>";
        echo "<td>" . number_format($row['total_price'], 2) . "</td>";
        echo "<td>" . number_format($row['profit_price'], 2) . "</td>";
        echo "<td>" . number_format($total_profit, 2) . "</td>";
        echo "<td class='action-buttons'>";
        echo "<form method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to permanently delete this order?\");'>";
        echo "<input type='hidden' name='order_item_id' value='" . $row['id'] . "'>";
        echo "<input type='hidden' name='action' value='delete'>";
        echo "<button type='submit' class='btn btn-danger btn-sm' title='Permanently Delete'>";
        echo "<i class='fas fa-trash'></i>";
        echo "</button>";
        echo "</form>";
        echo "<form method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to restore this order to stock?\");'>";
        echo "<input type='hidden' name='order_item_id' value='" . $row['id'] . "'>";
        echo "<input type='hidden' name='action' value='restore'>";
        echo "<button type='submit' class='btn btn-success btn-sm' title='Restore to Stock'>";
        echo "<i class='fas fa-undo'></i>";
        echo "</button>";
        echo "</form>";
        echo "</td>";
        echo "</tr>";
    }
    ?>
    </tbody>
</table>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#salesTable').DataTable({
        responsive: true,
        pageLength: 50,
        lengthMenu: [[50, 100, 250, -1], [50, 100, 250, "All"]],
        order: [[0, 'desc']], // Default order by date descending
        columnDefs: [
            { orderable: false, targets: 9 } // Disable sorting on actions column
        ]
    });

    // ---------- Summary Calculation ----------
    function updateSummary() {
        var data = table.rows({ filter: 'applied' }).data();
        var revenue = 0;
        var profit = 0;

        for (var i = 0; i < data.length; i++) {
            revenue += parseFloat(data[i][6].replace(/[^0-9.-]+/g, "")) || 0;
            profit += parseFloat(data[i][8].replace(/[^0-9.-]+/g, "")) || 0;
        }

        $('#totalRevenue').text('Total Revenue: ' + revenue.toFixed(2) + ' ETB');
        $('#totalProfit').text('Total Profit: ' + profit.toFixed(2) + ' ETB');
    }

    // ---------- Custom Filters Logic ----------
    $.fn.dataTable.ext.search.push(function(settings, data) {
        // Date range filter
        var min = $('#startDate').val();
        var max = $('#endDate').val();
        var date = data[0].split(' ')[0]; // Get YYYY-MM-DD from the first column

        if ((min && date < min) || (max && date > max)) {
            return false;
        }

        // Month filter
        var monthFilter = $('#monthFilter').val();
        if (monthFilter !== 'all') {
            var rowMonth = data[0].split('-')[1]; // Get MM from YYYY-MM-DD
            if (rowMonth !== monthFilter) {
                return false;
            }
        }

        // 'Sold By' user filter
        var userFilter = $('#userFilter').val();
        var soldBy = data[3];

        if (userFilter !== 'all' && userFilter !== soldBy) {
            return false;
        }
        
        return true; // Show the row if all filters pass
    });

    // ---------- Apply filters on change and update summary ----------
    $('#startDate, #endDate, #monthFilter, #userFilter').on('change', function() {
        table.draw();
        updateSummary();
    });

    // Set current month as default in month filter
    var currentMonth = ("0" + (new Date().getMonth() + 1)).slice(-2);
    $('#monthFilter').val(currentMonth).trigger('change');

    table.on('draw', updateSummary);
    updateSummary(); // Initial calculation
});
  
// Logout confirmation
$(".logout").click(function(e){
  e.preventDefault();
  if(confirm("Are you sure you want to logout?")){
    localStorage.removeItem('productMultiplier');
    window.location.href = '../logout.php';
  }
});
</script>

<br><br><br>
<?php include 'footer.php'; ?>
</body>
</html>