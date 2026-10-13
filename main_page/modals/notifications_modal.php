<?php
// Get notifications for the current user
$notification_sql = "SELECT n.*, u.name as sender_name 
                    FROM notifications n 
                    LEFT JOIN tbl_users u ON n.user_id = u.id 
                    WHERE (n.user_id = ? OR n.user_id IS NULL) 
                    AND (n.expires_at IS NULL OR n.expires_at > NOW())
                    ORDER BY n.created_at DESC 
                    LIMIT 50";
$notification_stmt = mysqli_prepare($con, $notification_sql);
mysqli_stmt_bind_param($notification_stmt, "i", $user_id);
mysqli_stmt_execute($notification_stmt);
$notifications = mysqli_stmt_get_result($notification_stmt);
?>

<div class="modal fade" id="notificationsModal" tabindex="-1" role="dialog" aria-labelledby="notificationsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="notificationsModalLabel">
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($notification_count > 0): ?>
                        <span class="badge badge-light"><?= $notification_count ?> new</span>
                    <?php endif; ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" class="text-white">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php if (mysqli_num_rows($notifications) > 0): ?>
                    <div class="list-group">
                        <?php while ($notification = mysqli_fetch_assoc($notifications)): ?>
                            <div class="list-group-item <?= $notification['is_read'] ? '' : 'list-group-item-warning' ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1">
                                        <?php if ($notification['priority'] === 'urgent'): ?>
                                            <i class="fas fa-exclamation-triangle text-danger"></i>
                                        <?php elseif ($notification['priority'] === 'high'): ?>
                                            <i class="fas fa-exclamation-circle text-warning"></i>
                                        <?php else: ?>
                                            <i class="fas fa-info-circle text-info"></i>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($notification['title']) ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?= date('M j, g:i A', strtotime($notification['created_at'])) ?>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge badge-primary ml-2">New</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <p class="mb-1"><?= htmlspecialchars($notification['message']) ?></p>
                                <?php if ($notification['sender_name']): ?>
                                    <small class="text-muted">From: <?= htmlspecialchars($notification['sender_name']) ?></small>
                                <?php endif; ?>
                                
                                <div class="mt-2">
                                    <?php if (!$notification['is_read']): ?>
                                        <button class="btn btn-sm btn-outline-success mark-read-btn" data-id="<?= $notification['id'] ?>">
                                            <i class="fas fa-check"></i> Mark Read
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($notification['related_id'] && $notification['related_type']): ?>
                                        <button class="btn btn-sm btn-outline-primary view-related-btn" 
                                                data-id="<?= $notification['related_id'] ?>" 
                                                data-type="<?= $notification['related_type'] ?>">
                                            <i class="fas fa-external-link-alt"></i> View
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No notifications found.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <?php if ($notification_count > 0): ?>
                    <button type="button" class="btn btn-primary" id="markAllRead">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Mark single notification as read
    $('.mark-read-btn').click(function() {
        var notificationId = $(this).data('id');
        var $notificationItem = $(this).closest('.list-group-item');
        
        $.ajax({
            url: 'drug_crud/mark_notification_read.php',
            type: 'POST',
            data: { notification_id: notificationId },
            success: function(response) {
                var result = JSON.parse(response);
                if (result.success) {
                    $notificationItem.removeClass('list-group-item-warning');
                    $(this).remove();
                    updateNotificationCount();
                }
            }
        });
    });

    // Mark all notifications as read
    $('#markAllRead').click(function() {
        $.ajax({
            url: 'drug_crud/mark_all_notifications_read.php',
            type: 'POST',
            data: { user_id: <?= $user_id ?> },
            success: function(response) {
                var result = JSON.parse(response);
                if (result.success) {
                    $('.list-group-item').removeClass('list-group-item-warning');
                    $('.mark-read-btn').remove();
                    updateNotificationCount();
                }
            }
        });
    });

    // View related item
    $('.view-related-btn').click(function() {
        var relatedId = $(this).data('id');
        var relatedType = $(this).data('type');
        
        switch(relatedType) {
            case 'order':
                window.open('orders.php?view=' + relatedId, '_blank');
                break;
            case 'stock':
                window.open('view.php?highlight=' + relatedId, '_blank');
                break;
            case 'prescription':
                window.open('prescriptions.php?view=' + relatedId, '_blank');
                break;
            default:
                alert('Related item type not supported');
        }
    });

    function updateNotificationCount() {
        $.ajax({
            url: 'drug_crud/get_notification_count.php',
            type: 'GET',
            success: function(response) {
                var count = JSON.parse(response).count;
                $('.badge-warning').text(count);
                if (count === 0) {
                    $('.badge-warning').hide();
                }
            }
        });
    }
});
</script>