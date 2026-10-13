<?php
session_start();
if (!isset($_SESSION['user_session'])) {
    header("location:../login.php");
    exit();
}

include('../../dbcon.php');

// Get user info and permissions
$user_id = $_SESSION['user_session'];
$user_sql = "SELECT u.*, up.can_manage_controlled, up.can_apply_discount, up.can_manage_credit 
             FROM tbl_users u 
             LEFT JOIN user_permissions up ON u.id = up.user_id 
             WHERE u.id = ?";
$user_stmt = mysqli_prepare($con, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$current_user = mysqli_fetch_assoc($user_result);

// Get system settings
$settings_sql = "SELECT setting_key, setting_value FROM system_settings WHERE is_public = 1";
$settings_result = mysqli_query($con, $settings_sql);
$system_settings = [];
while ($setting = mysqli_fetch_assoc($settings_result)) {
    $system_settings[$setting['setting_key']] = $setting['setting_value'];
}

$currency_symbol = $system_settings['currency_symbol'] ?? 'ETB';
$pharmacy_name = $system_settings['pharmacy_name'] ?? 'Drug Store';

// Handle actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_logs'])) {
        $days = intval($_POST['days']);
        
        // First, let's check how many records will be affected
        $count_query = "SELECT COUNT(*) as count FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)";
        $count_result = mysqli_query($con, $count_query);
        $count_data = mysqli_fetch_assoc($count_result);
        $deleted_count = $count_data['count'];
        
        if ($deleted_count > 0) {
            // Temporarily disable foreign key checks to handle orphaned user_ids
            mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 0");
            
            $clear_query = "DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)";
            if (mysqli_query($con, $clear_query)) {
                // Re-enable foreign key checks
                mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 1");
                
                // Log the action
                $log_sql = "INSERT INTO logs (user_id, username, level, action, details, ip_address) 
                           VALUES (?, ?, 'INFO', 'LOGS_CLEARED', ?, ?)";
                $log_stmt = mysqli_prepare($con, $log_sql);
                $details = "Cleared $deleted_count log entries older than $days days";
                $ip_address = $_SERVER['REMOTE_ADDR'];
                mysqli_stmt_bind_param($log_stmt, "isss", $user_id, $current_user['username'], $details, $ip_address);
                mysqli_stmt_execute($log_stmt);
                
                $message = "<div class='alert alert-success'>Cleared $deleted_count log entries older than $days days.</div>";
            } else {
                // Re-enable foreign key checks even if there's an error
                mysqli_query($con, "SET FOREIGN_KEY_CHECKS = 1");
                $message = "<div class='alert alert-danger'>Error clearing logs: " . mysqli_error($con) . "</div>";
            }
        } else {
            $message = "<div class='alert alert-info'>No log entries found older than $days days.</div>";
        }
    }
    
    if (isset($_POST['export_logs'])) {
        exportLogs($con);
        exit;
    }
}

// Export function
function exportLogs($con) {
    $query = "SELECT l.*, u.name as user_name 
              FROM logs l 
              LEFT JOIN tbl_users u ON l.user_id = u.id 
              ORDER BY l.created_at DESC";
    $result = mysqli_query($con, $query);
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="pharmacy_logs_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Header
    fputcsv($output, ['ID', 'Timestamp', 'Level', 'User ID', 'Username', 'User Name', 'Action', 'Details', 'IP Address']);
    
    // Data
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['level'],
            $row['user_id'],
            $row['username'],
            $row['user_name'],
            $row['action'],
            $row['details'],
            $row['ip_address']
        ]);
    }
    
    fclose($output);
    exit;
}

// Get log statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN level = 'INFO' THEN 1 END) as info,
    COUNT(CASE WHEN level = 'WARNING' THEN 1 END) as warning,
    COUNT(CASE WHEN level = 'ERROR' THEN 1 END) as error,
    COUNT(CASE WHEN level = 'CRITICAL' THEN 1 END) as critical,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_7d
FROM logs";
$stats_result = mysqli_query($con, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get user statistics for filter
$users_query = "SELECT DISTINCT l.user_id, l.username, u.name 
                FROM logs l 
                LEFT JOIN tbl_users u ON l.user_id = u.id 
                WHERE l.user_id IS NOT NULL 
                ORDER BY u.name";
$users_result = mysqli_query($con, $users_query);
$log_users = [];
while ($user = mysqli_fetch_assoc($users_result)) {
    $log_users[$user['user_id']] = $user['name'] ?: $user['username'];
}

// Get action types for filter
$actions_query = "SELECT DISTINCT action FROM logs ORDER BY action";
$actions_result = mysqli_query($con, $actions_query);
$log_actions = [];
while ($action = mysqli_fetch_assoc($actions_result)) {
    $log_actions[] = $action['action'];
}

// Get logs with filters
$level_filter = $_GET['level'] ?? 'all';
$user_filter = $_GET['user'] ?? 'all';
$action_filter = $_GET['action'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';
$search_term = $_GET['search'] ?? '';

$logs_query = "SELECT l.*, u.name as user_name 
               FROM logs l 
               LEFT JOIN tbl_users u ON l.user_id = u.id 
               WHERE 1=1";

if ($level_filter !== 'all') {
    $logs_query .= " AND l.level = '$level_filter'";
}

if ($user_filter !== 'all') {
    $logs_query .= " AND l.user_id = '$user_filter'";
}

if ($action_filter !== 'all') {
    $logs_query .= " AND l.action = '$action_filter'";
}

if ($date_filter !== 'all') {
    switch ($date_filter) {
        case 'today':
            $logs_query .= " AND DATE(l.created_at) = CURDATE()";
            break;
        case 'yesterday':
            $logs_query .= " AND DATE(l.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $logs_query .= " AND l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $logs_query .= " AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

if (!empty($search_term)) {
    $search_term = mysqli_real_escape_string($con, $search_term);
    $logs_query .= " AND (l.action LIKE '%$search_term%' OR l.details LIKE '%$search_term%' OR l.username LIKE '%$search_term%' OR u.name LIKE '%$search_term%')";
}

$logs_query .= " ORDER BY l.created_at DESC 
                 LIMIT 1000";

$logs_result = mysqli_query($con, $logs_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" sizes="16x16" href="../../images/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="HDS Drug Store - Pharmacy Management System">
    <title>Log Management - <?= $pharmacy_name ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
    <style>
        body { 
            padding: 20px; 
            font-family: Arial, sans-serif; 
            background-color: #f8f9fa;
        }
        .card { 
            margin-bottom: 20px; 
            border: none; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
        }
        .stats-card { 
            transition: transform 0.2s; 
            text-align: center; 
            padding: 15px;
        }
        .stats-card:hover { 
            transform: translateY(-2px); 
        }
        .log-info { border-left: 4px solid #17a2b8; }
        .log-warning { border-left: 4px solid #ffc107; }
        .log-error { border-left: 4px solid #dc3545; }
        .log-critical { border-left: 4px solid #721c24; }
        .badge-info { background-color: #17a2b8; }
        .badge-warning { background-color: #ffc107; color: #000; }
        .badge-error { background-color: #dc3545; }
        .badge-critical { background-color: #721c24; }
        .filter-group { 
            display: flex; 
            flex-wrap: wrap; 
            align-items: center; 
            gap: 15px; 
            margin-bottom: 20px; 
        }
        .filter-group label { 
            margin-right: 8px; 
            font-weight: bold; 
        }
        .table td, .table th { 
            white-space: nowrap; 
        }
        .notification-bar { 
            padding: 10px; 
            background-color: #f8f9fa; 
            border-radius: 5px; 
            border-left: 4px solid #007bff; 
            margin-bottom: 15px; 
        }
        .action-buttons { 
            display: flex; 
            gap: 5px; 
        }
        .btn-sm { 
            padding: 0.25rem 0.5rem; 
            font-size: 0.75rem; 
        }
        .bulk-actions { 
            background-color: #f8f9fa; 
            padding: 15px; 
            border-radius: 5px; 
            margin-bottom: 20px; 
        }
        .summary-badges { 
            margin-bottom: 20px; 
            display: flex; 
            flex-wrap: wrap; 
            gap: 10px; 
        }
        .summary-badge { 
            font-weight: 600; 
            font-size: 1rem; 
            padding: 8px 12px; 
            border-radius: 5px; 
            color: white; 
        }
        .badge-total { background-color: #007bff; }
        .badge-info { background-color: #17a2b8; }
        .badge-warning { background-color: #ffc107; color: #000; }
        .badge-error { background-color: #dc3545; }
        .badge-critical { background-color: #721c24; }
        .log-details { 
            max-width: 300px; 
            overflow: hidden; 
            text-overflow: ellipsis; 
            white-space: nowrap; 
        }
        @media (max-width: 768px) {
            .filter-group { 
                flex-direction: column; 
                align-items: stretch; 
            }
            .filter-group .form-control { 
                width: 100% !important; 
            }
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<br><br><br>

<!-- Header -->
<header class="mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2><i class="fas fa-clipboard-list"></i> System Logs</h2>
            <p class="text-muted">Monitor system activities and events</p>
        </div>
        <div>
            <a href="../view.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Inventory
            </a>
        </div>
    </div>
</header>

<!-- Notification Bar -->
<div class="notification-bar d-flex justify-content-between align-items-center">
    <div>
        <span class="badge badge-info">
            <i class="fas fa-info-circle"></i> Showing last 1000 log entries
        </span>
        <?php if ($stats['last_24h'] > 0): ?>
            <span class="badge badge-warning">
                <i class="fas fa-clock"></i> <?= $stats['last_24h'] ?> logs in last 24h
            </span>
        <?php endif; ?>
    </div>
    <div>
        <small class="text-muted">Last updated: <?= date('M j, Y g:i A') ?></small>
    </div>
</div>

<!-- Quick Stats Dashboard -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center stats-card">
            <div class="card-body">
                <h3 class="text-primary"><?= number_format($stats['total']) ?></h3>
                <p class="text-muted">Total Logs</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center stats-card">
            <div class="card-body">
                <h3 class="text-info"><?= number_format($stats['info']) ?></h3>
                <p class="text-muted">Info</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center stats-card">
            <div class="card-body">
                <h3 class="text-warning"><?= number_format($stats['warning']) ?></h3>
                <p class="text-muted">Warnings</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center stats-card">
            <div class="card-body">
                <h3 class="text-danger"><?= number_format($stats['error']) ?></h3>
                <p class="text-muted">Errors</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center stats-card">
            <div class="card-body">
                <h3 class="text-dark"><?= number_format($stats['critical']) ?></h3>
                <p class="text-muted">Critical</p>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card text-center stats-card">
            <div class="card-body">
                <h3 class="text-success"><?= number_format($stats['last_7d']) ?></h3>
                <p class="text-muted">Last 7 Days</p>
            </div>
        </div>
    </div>
</div>

<?php echo $message; ?>

<!-- Bulk Actions Section -->
<div class="bulk-actions">
    <div class="d-flex justify-content-between align-items-center">
        <div class="import-export-buttons">
            <button class="btn btn-warning" data-toggle="modal" data-target="#clearModal">
                <i class="fas fa-broom"></i> Clear Old Logs
            </button>
            <form method="POST" class="d-inline">
                <button type="submit" name="export_logs" class="btn btn-success">
                    <i class="fas fa-download"></i> Export All Logs
                </button>
            </form>
        </div>
        
        <div>
            <a href="logs.php" class="btn btn-info">
                <i class="fas fa-sync"></i> Refresh
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filter-group">
    <label for="levelFilter">Log Level:</label>
    <select id="levelFilter" class="form-control" style="width:auto;">
        <option value="all">All Levels</option>
        <option value="INFO" <?= $level_filter == 'INFO' ? 'selected' : '' ?>>Info</option>
        <option value="WARNING" <?= $level_filter == 'WARNING' ? 'selected' : '' ?>>Warning</option>
        <option value="ERROR" <?= $level_filter == 'ERROR' ? 'selected' : '' ?>>Error</option>
        <option value="CRITICAL" <?= $level_filter == 'CRITICAL' ? 'selected' : '' ?>>Critical</option>
    </select>

    <label for="userFilter">User:</label>
    <select id="userFilter" class="form-control" style="width:auto;">
        <option value="all">All Users</option>
        <?php foreach ($log_users as $id => $name): ?>
            <option value="<?= $id ?>" <?= $user_filter == $id ? 'selected' : '' ?>>
                <?= htmlspecialchars($name) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="actionFilter">Action:</label>
    <select id="actionFilter" class="form-control" style="width:auto;">
        <option value="all">All Actions</option>
        <?php foreach ($log_actions as $action): ?>
            <option value="<?= htmlspecialchars($action) ?>" <?= $action_filter == $action ? 'selected' : '' ?>>
                <?= htmlspecialchars($action) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="dateFilter">Date Range:</label>
    <select id="dateFilter" class="form-control" style="width:auto;">
        <option value="all">All Dates</option>
        <option value="today" <?= $date_filter == 'today' ? 'selected' : '' ?>>Today</option>
        <option value="yesterday" <?= $date_filter == 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
        <option value="week" <?= $date_filter == 'week' ? 'selected' : '' ?>>Last 7 Days</option>
        <option value="month" <?= $date_filter == 'month' ? 'selected' : '' ?>>Last 30 Days</option>
    </select>

    <form method="GET" class="d-flex align-items-center">
        <input type="text" name="search" class="form-control" placeholder="Search logs..." 
               value="<?= htmlspecialchars($search_term) ?>" style="width: 200px;">
        <button type="submit" class="btn btn-primary ml-2">
            <i class="fas fa-search"></i>
        </button>
    </form>

    <a href="logs.php" class="btn btn-outline-secondary btn-sm">Clear Filters</a>
</div>

<!-- Logs Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table id="logsTable" class="table table-striped table-bordered" style="width:100%">
                <thead class="thead">
                    <tr>
                        <th>ID</th>
                        <th>Timestamp</th>
                        <th>Level</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($logs_result) > 0): ?>
                        <?php while ($log = mysqli_fetch_assoc($logs_result)): ?>
                            <?php
                            $level_class = 'log-' . strtolower($log['level']);
                            $badge_class = 'badge-' . strtolower($log['level']);
                            ?>
                            <tr class="<?= $level_class ?>">
                                <td><?= $log['id'] ?></td>
                                <td><?= date('M j, H:i:s', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= $log['level'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['user_name']): ?>
                                        <?= htmlspecialchars($log['user_name']) ?>
                                        <br><small class="text-muted">@<?= htmlspecialchars($log['username']) ?></small>
                                    <?php else: ?>
                                        <?= $log['username'] ? htmlspecialchars($log['username']) : 'System' ?>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= $log['action'] ?></code></td>
                                <td class="log-details" title="<?= htmlspecialchars($log['details']) ?>">
                                    <?= strlen($log['details']) > 50 ? substr($log['details'], 0, 50) . '...' : $log['details'] ?>
                                </td>
                                <td><small><?= $log['ip_address'] ?></small></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fas fa-info-circle fa-2x mb-3"></i><br>
                                No log entries found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Clear Logs Modal -->
<div class="modal fade" id="clearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title">
                    <i class="fas fa-broom"></i> Clear Old Logs
                </h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        This will permanently delete old log entries. This action cannot be undone.
                    </div>
                    <div class="form-group">
                        <label>Delete logs older than:</label>
                        <select name="days" class="form-control" required>
                            <option value="7">7 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="90">90 days</option>
                            <option value="180">180 days</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            This action will be logged for security purposes.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="clear_logs" class="btn btn-warning">Clear Logs</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#logsTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "All"]],
        order: [[0, 'desc']],
        dom: '<"top"lf>rt<"bottom"ip><"clear">',
        language: {
            search: "Search logs:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Showing 0 to 0 of 0 entries",
            infoFiltered: "(filtered from _MAX_ total entries)"
        }
    });

    // Initialize Select2
    $('.select2').select2();

    // Filter functionality
    $('#levelFilter, #userFilter, #actionFilter, #dateFilter').on('change', function() {
        var url = new URL(window.location.href);
        url.searchParams.set('level', $('#levelFilter').val());
        url.searchParams.set('user', $('#userFilter').val());
        url.searchParams.set('action', $('#actionFilter').val());
        url.searchParams.set('date', $('#dateFilter').val());
        window.location.href = url.toString();
    });

    // Show full details on click
    $(document).on('click', '.log-details', function() {
        var details = $(this).attr('title');
        if (details && details.length > 50) {
            alert('Log Details:\n\n' + details);
        }
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
});
</script>

<br><br><br>
<?php include 'footer.php'; ?>
</body>
</html>