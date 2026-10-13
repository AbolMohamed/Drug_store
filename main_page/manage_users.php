<?php
session_start();
require '../dbcon.php';

// Check if user is logged in
if(!isset($_SESSION['user_session'])) {
    header("location:login.php");
    exit();
}

// DELETE functionality
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $con->prepare("DELETE FROM tbl_users WHERE id=?");
    $stmt->bind_param("i", $id);
    if($stmt->execute()) {
        $_SESSION['message'] = "User deleted successfully.";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting user.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: manage_users.php");
    exit();
}

// UPDATE status functionality
if (isset($_GET['toggle_status'])) {
    $id = $_GET['toggle_status'];
    // Get current status
    $result = $con->query("SELECT isActive FROM tbl_users WHERE id=$id");
    $row = $result->fetch_assoc();
    $newStatus = ($row['isActive'] == 'Active') ? 'Inactive' : 'Active';
    
    $stmt = $con->prepare("UPDATE tbl_users SET isActive=? WHERE id=?");
    $stmt->bind_param("si", $newStatus, $id);
    if($stmt->execute()) {
        $_SESSION['message'] = "User status updated.";
        $_SESSION['message_type'] = "info";
    } else {
        $_SESSION['message'] = "Error updating user status.";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: manage_users.php");
    exit();
}

// FETCH users
$result = $con->query("SELECT id, name, username, email, mobile, isActive, usertype, created_at 
                       FROM tbl_users 
                       ORDER BY id DESC");
$totalUsers = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>HDS Drug Store - User Management</title>
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HDS Drug Store - Pharmacy Management System">
    
    <!-- CSS Links -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    
    <style>
        body { 
            padding: 20px; 
            font-family: Arial, sans-serif; 
        }
        .action-buttons { 
            display: flex; 
            gap: 5px; 
            white-space: nowrap;
        }
        .btn-sm { 
            padding: 0.25rem 0.5rem; 
            font-size: 0.75rem; 
        }
        .summary-badge { 
            font-weight: 600; 
            font-size: 1rem; 
            padding: 8px 12px; 
            border-radius: 5px; 
            color: white; 
            background-color: #17a2b8; 
            margin-bottom: 20px; 
            display: inline-block; 
        }
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        .status-inactive {
            color: #dc3545;
            font-weight: bold;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #e3e6f0;
            font-weight: bold;
        }
        .table th {
            border-top: none;
            background-color: #6794bb;
            color: #FFFFFF;
        }
    </style>
</head>
<body>
    <?php include 'header.php';?>
    <br><br><br>

    <div class="container-fluid">
        <h2><i class="fas fa-users-cog"></i> User Management</h2>

        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php 
        unset($_SESSION['message'], $_SESSION['message_type']); 
        endif; ?>

        <!-- Add User Button -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="summary-badge">Total Users: <?= $totalUsers ?></span>
            <a href="addUser.php" class="btn btn-success">
                <i class="fas fa-user-plus"></i> Add New User
            </a>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-table"></i> User List
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="usersTable" class="table table-striped table-bordered nowrap" style="width:100%">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Email Address</th>
                                <th>Mobile</th>
                                <th>User Type</th>
                                <th>Created</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['mobile']) ?></td>
                                <td><?= htmlspecialchars($row['usertype']) ?></td>
                                <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <?php if($row['isActive'] == 'Active'): ?>
                                        <span class="status-active">Active</span>
                                    <?php else: ?>
                                        <span class="status-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="update_user.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="change_password.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-key"></i>
                                    </a>
                                    <a href="?toggle_status=<?= $row['id'] ?>" class="btn btn-secondary btn-sm" 
                                       onclick="return confirm('Change status of this user?')">
                                        <i class="fas fa-power-off"></i>
                                    </a>
                                    <a href="?delete=<?= $row['id'] ?>" class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this user?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>

    <script>
    $(document).ready(function() {
        // Initialize DataTable
        var table = $('#usersTable').DataTable({
            responsive: true,
            pageLength: 25,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
            order: [[5, 'desc']], // Sort by created date by default
            columnDefs: [{ 
                orderable: false, 
                targets: 7, // Actions column
                responsivePriority: 1 // Ensure actions column is always visible
            }]
        });
        
        // Handle responsive display changes
        table.on('responsive-display', function (e, datatable, row, showHide, update) {
            // You can add any custom behavior when responsive display changes
        });
        
        // Load cart item count
        load_cart_item_number();
        
        function load_cart_item_number() {
            $.ajax({
                url: 'action.php',
                method: 'get',
                data: {cartItem: "cart_item"},
                success: function(response) {
                    $("#cart-item").html(response);
                }
            });
        }
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
    <br><br>
    <?php include 'footer.php';?>
</body>
</html>