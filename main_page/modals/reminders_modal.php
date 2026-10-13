<?php
// Get reminders for the current user
$reminder_sql = "SELECT cr.*, u.name as created_by_name 
                FROM cashier_reminders cr 
                LEFT JOIN tbl_users u ON cr.user_id = u.id 
                WHERE cr.user_id = ? 
                ORDER BY cr.is_completed ASC, cr.created_at DESC 
                LIMIT 20";
$reminder_stmt = mysqli_prepare($con, $reminder_sql);
mysqli_stmt_bind_param($reminder_stmt, "i", $user_id);
mysqli_stmt_execute($reminder_stmt);
$reminders = mysqli_stmt_get_result($reminder_stmt);
?>

<div class="modal fade" id="remindersModal" tabindex="-1" role="dialog" aria-labelledby="remindersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="remindersModalLabel">
                    <i class="fas fa-tasks"></i> My Reminders
                    <?php if ($reminder_count > 0): ?>
                        <span class="badge badge-light"><?= $reminder_count ?> pending</span>
                    <?php endif; ?>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" class="text-white">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Add New Reminder Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-plus-circle"></i> Add New Reminder</h6>
                    </div>
                    <div class="card-body">
                        <form id="addReminderForm">
                            <div class="form-group">
                                <label for="reminderText">Reminder Text:</label>
                                <textarea class="form-control" id="reminderText" name="reminder_text" rows="3" placeholder="Enter your reminder here..." required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="reminderPriority">Priority:</label>
                                <select class="form-control" id="reminderPriority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Reminder
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Reminders List -->
                <?php if (mysqli_num_rows($reminders) > 0): ?>
                    <div class="list-group">
                        <?php while ($reminder = mysqli_fetch_assoc($reminders)): ?>
                            <div class="list-group-item <?= $reminder['is_completed'] ? 'list-group-item-success' : 'list-group-item-warning' ?>">
                                <div class="d-flex w-100 justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-1">
                                                <?php if ($reminder['is_completed']): ?>
                                                    <i class="fas fa-check-circle text-success"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-clock text-warning"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($reminder['reminder_text']) ?>
                                            </h6>
                                            <?php if (!$reminder['is_completed']): ?>
                                                <span class="badge badge-<?= 
                                                    $reminder['priority'] === 'high' ? 'danger' : 
                                                    ($reminder['priority'] === 'medium' ? 'warning' : 'secondary')
                                                ?> ml-2">
                                                    <?= ucfirst($reminder['priority']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            Created: <?= date('M j, g:i A', strtotime($reminder['created_at'])) ?>
                                            <?php if ($reminder['created_by_name']): ?>
                                                • By: <?= htmlspecialchars($reminder['created_by_name']) ?>
                                            <?php endif; ?>
                                            <?php if ($reminder['is_completed'] && $reminder['completed_at']): ?>
                                                • Completed: <?= date('M j, g:i A', strtotime($reminder['completed_at'])) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="ml-3">
                                        <?php if (!$reminder['is_completed']): ?>
                                            <button class="btn btn-sm btn-success complete-reminder-btn" data-id="<?= $reminder['id'] ?>" title="Mark Complete">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-danger delete-reminder-btn" data-id="<?= $reminder['id'] ?>" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No reminders found. Add your first reminder above!</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <?php if ($reminder_count > 0): ?>
                    <button type="button" class="btn btn-primary" id="completeAllReminders">
                        <i class="fas fa-check-double"></i> Complete All
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Add new reminder
    $('#addReminderForm').submit(function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        
        $.ajax({
            url: 'drug_crud/add_reminder.php',
            type: 'POST',
            data: formData,
            success: function(response) {
                var result = JSON.parse(response);
                if (result.success) {
                    $('#reminderText').val('');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            }
        });
    });

    // Complete reminder
    $('.complete-reminder-btn').click(function() {
        var reminderId = $(this).data('id');
        
        $.ajax({
            url: 'drug_crud/complete_reminder.php',
            type: 'POST',
            data: { reminder_id: reminderId },
            success: function(response) {
                var result = JSON.parse(response);
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            }
        });
    });

    // Delete reminder
    $('.delete-reminder-btn').click(function() {
        var reminderId = $(this).data('id');
        
        if (confirm('Are you sure you want to delete this reminder?')) {
            $.ajax({
                url: 'drug_crud/delete_reminder.php',
                type: 'POST',
                data: { reminder_id: reminderId },
                success: function(response) {
                    var result = JSON.parse(response);
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                }
            });
        }
    });

    // Complete all reminders
    $('#completeAllReminders').click(function() {
        if (confirm('Mark all reminders as completed?')) {
            $.ajax({
                url: 'drug_crud/complete_all_reminders.php',
                type: 'POST',
                data: { user_id: <?= $user_id ?> },
                success: function(response) {
                    var result = JSON.parse(response);
                    if (result.success) {
                        location.reload();
                    }
                }
            });
        }
    });
});
</script>