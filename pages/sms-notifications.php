<?php
/**
 * SMS Notifications Management Page (Admin only)
 * View notification logs and send test messages
 */

require_once '../includes/functions.php';
requireRole('admin');

$db = getDB();
$user = currentUser();

// Create notifications table if it doesn't exist
$db->exec("
    CREATE TABLE IF NOT EXISTS sms_notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        notification_type TEXT NOT NULL,
        reference_id INTEGER,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )
");

// Handle actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_test':
            require_once __DIR__ . '/../sms/function/sms-service.php';
            $smsService = getSMSService();
            
            $phone = sanitize($_POST['phone'] ?? '');
            $message = sanitize($_POST['message'] ?? '');
            
            if (empty($phone) || empty($message)) {
                setFlash('Phone number and message are required.', 'error');
            } else {
                $result = $smsService->sendSMS($phone, $message);
                
                if ($result['success']) {
                    setFlash('Test SMS sent successfully!');
                } else {
                    setFlash('Failed to send SMS: ' . $result['message'], 'error');
                }
            }
            
            header('Location: sms-notifications.php');
            exit();
            break;
            
        case 'run_schedule_reminder':
            // Run the schedule reminder script
            $scriptPath = __DIR__ . '/../sms/cron/schedule-reminder.php';
            
            if (file_exists($scriptPath)) {
                ob_start();
                include $scriptPath;
                $output = ob_get_clean();
                
                setFlash('Schedule reminder script executed. Check logs for details.');
            } else {
                setFlash('Schedule reminder script not found.', 'error');
            }
            
            header('Location: sms-notifications.php');
            exit();
            break;
            
        case 'clear_logs':
            $stmt = $db->prepare("DELETE FROM sms_notifications");
            $stmt->execute();
            setFlash('Notification logs cleared.');
            header('Location: sms-notifications.php');
            exit();
            break;
    }
}

// Get notification statistics
$stats = [
    'total' => (int) querySingle("SELECT COUNT(*) FROM sms_notifications"),
    'today' => (int) querySingle("SELECT COUNT(*) FROM sms_notifications WHERE DATE(sent_at) = DATE('now')"),
    'this_week' => (int) querySingle("SELECT COUNT(*) FROM sms_notifications WHERE DATE(sent_at) >= DATE('now', '-7 days')"),
    'schedule_reminders' => (int) querySingle("SELECT COUNT(*) FROM sms_notifications WHERE notification_type = 'schedule_reminder'"),
    'grade_notifications' => (int) querySingle("SELECT COUNT(*) FROM sms_notifications WHERE notification_type = 'grade_posted'")
];

// Get recent notifications
$recentStmt = $db->prepare("
    SELECT 
        sn.id,
        sn.notification_type,
        sn.sent_at,
        s.student_id,
        s.first_name,
        s.last_name,
        s.phone
    FROM sms_notifications sn
    JOIN students s ON sn.student_id = s.id
    ORDER BY sn.sent_at DESC
    LIMIT 50
");
$recentStmt->execute();
$recentNotifications = $recentStmt->fetchAll();

// Get students without phone numbers
$noPhoneStmt = $db->query("
    SELECT COUNT(*) 
    FROM students 
    WHERE status = 'Active' 
    AND (phone IS NULL OR phone = '')
");
$studentsWithoutPhone = (int) $noPhoneStmt->fetchColumn();

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="mb-4">
        <i data-lucide="smartphone" class="me-2"></i>
        SMS Notifications
    </h1>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total Sent</h6>
                    <h3 class="mb-0"><?= number_format($stats['total']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Sent Today</h6>
                    <h3 class="mb-0"><?= number_format($stats['today']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Schedule Reminders</h6>
                    <h3 class="mb-0"><?= number_format($stats['schedule_reminders']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Grade Notifications</h6>
                    <h3 class="mb-0"><?= number_format($stats['grade_notifications']) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <?php if ($studentsWithoutPhone > 0): ?>
    <div class="alert alert-warning">
        <i data-lucide="alert-triangle" class="me-2"></i>
        <strong>Notice:</strong> <?= $studentsWithoutPhone ?> active student(s) don't have phone numbers.
        <a href="students.php" class="alert-link">Update student records</a> to enable SMS notifications for them.
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Test SMS Panel -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i data-lucide="send" class="me-2"></i>
                        Send Test SMS
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="send_test">
                        
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" 
                                   placeholder="9171234567" required>
                            <small class="form-text text-muted">
                                Format: 9XXXXXXXXX (Philippine mobile number)
                            </small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="4" 
                                      required>This is a test message from the School Management System.</textarea>
                            <small class="form-text text-muted">
                                Max 160 characters for standard SMS
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="send" class="me-2"></i>
                            Send Test SMS
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Actions Panel -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i data-lucide="settings" class="me-2"></i>
                        Actions
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" class="mb-3">
                        <input type="hidden" name="action" value="run_schedule_reminder">
                        <button type="submit" class="btn btn-info w-100 mb-2">
                            <i data-lucide="calendar" class="me-2"></i>
                            Run Schedule Reminder Now
                        </button>
                        <small class="form-text text-muted">
                            Manually trigger schedule reminders for tomorrow's classes
                        </small>
                    </form>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <h6>Setup Instructions</h6>
                        <p class="small text-muted mb-2">
                            Follow the setup guide to configure your SMS Gateway credentials and automated reminders.
                        </p>
                        <a href="../SMS-SETUP-GUIDE.md" target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i data-lucide="book-open" class="me-2"></i>
                            View Setup Guide
                        </a>
                    </div>
                    
                    <hr>
                    
                    <form method="post" onsubmit="return confirm('Clear all notification logs?');">
                        <input type="hidden" name="action" value="clear_logs">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i data-lucide="trash-2" class="me-2"></i>
                            Clear All Logs
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Notifications -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i data-lucide="list" class="me-2"></i>
                Recent Notifications
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($recentNotifications)): ?>
                <p class="text-muted mb-0">No notifications sent yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Type</th>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentNotifications as $notif): ?>
                                <tr>
                                    <td><?= date('M d, Y h:i A', strtotime($notif['sent_at'])) ?></td>
                                    <td>
                                        <?php if ($notif['notification_type'] === 'schedule_reminder'): ?>
                                            <span class="badge bg-info">Schedule Reminder</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Grade Posted</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']) ?></td>
                                    <td><?= htmlspecialchars($notif['student_id']) ?></td>
                                    <td><?= htmlspecialchars($notif['phone']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
