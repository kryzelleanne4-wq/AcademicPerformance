<?php
/**
 * SMS Notifications (Admin)
 *
 * Where the administrator can:
 *   - see every text the system sent (and why one failed)
 *   - send a test message to check the gateway APK
 *   - run the "class tomorrow" reminders by hand
 *
 * The daily reminders also run automatically when the dashboard is opened
 * (see index.php), so this page is mainly for checking and testing.
 */

require_once '../includes/functions.php';
require_once '../includes/notifications.php';
requireRole('admin');

$pageTitle = 'SMS Notifications';

$db = getDB();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'send_test':
            $phone = sanitize($_POST['phone'] ?? '');
            $message = trim((string) ($_POST['message'] ?? ''));

            if ($phone === '' || $message === '') {
                setFlash('Enter a phone number and a message.', 'error');
                break;
            }

            $service = smsGatewayService();
            if (!$service) {
                setFlash('The SMS gateway library is missing. Run "composer install" inside the sms/ folder.', 'error');
                break;
            }

            $result = $service->sendSMS($phone, $message);
            if (!empty($result['success'])) {
                setFlash('Test SMS sent to ' . htmlspecialchars($phone) . '.');
            } else {
                setFlash('Test SMS failed: ' . htmlspecialchars($result['message'] ?? 'unknown error'), 'error');
            }
            break;

        case 'run_reminders':
            $summary = runScheduleReminders($db, true);
            $flash = 'Reminder run for ' . $summary['weekday'] . ', ' . date('M j', strtotime($summary['date'])) . ': '
                   . $summary['matched'] . ' student(s) with a class, '
                   . $summary['sent'] . ' SMS sent';
            if ($summary['already_sent'] > 0) {
                $flash .= ', ' . $summary['already_sent'] . ' already notified today';
            }
            if ($summary['skipped_no_phone'] > 0) {
                $flash .= ', ' . $summary['skipped_no_phone'] . ' without a mobile number';
            }
            if ($summary['failed'] > 0) {
                $flash .= ', ' . $summary['failed'] . ' failed';
            }
            setFlash($flash . '.');
            break;

        case 'clear_logs':
            $db->exec('DELETE FROM sms_notifications');
            setFlash('SMS delivery log cleared.');
            break;
    }

    header('Location: sms-notifications.php');
    exit();
}

// Counters.
$sentTotal     = (int) querySingle("SELECT COUNT(*) FROM sms_notifications WHERE status = 'sent'");
$sentToday     = (int) querySingle("SELECT COUNT(*) FROM sms_notifications WHERE status = 'sent' AND DATE(sent_at) = DATE('now')");
$reminderCount = (int) querySingle("SELECT COUNT(*) FROM sms_notifications WHERE status = 'sent' AND notification_type = 'schedule_reminder'");
$gradeCount    = (int) querySingle("SELECT COUNT(*) FROM sms_notifications WHERE status = 'sent' AND notification_type = 'grade_posted'");
$failedCount   = (int) querySingle("SELECT COUNT(*) FROM sms_notifications WHERE status = 'failed'");
$lastRun       = (string) appSetting($db, 'schedule_reminder_last_run', '');

$loggerStmt = $db->prepare("
    SELECT sn.*, s.first_name, s.last_name, s.student_id AS student_no, s.phone
    FROM sms_notifications sn
    JOIN students s ON sn.student_id = s.id
    ORDER BY sn.sent_at DESC, sn.id DESC
    LIMIT 100
");
$loggerStmt->execute();
$logRows = $loggerStmt->fetchAll();

$studentsWithoutPhone = (int) querySingle("
    SELECT COUNT(*) FROM students
    WHERE status = 'Active' AND (phone IS NULL OR TRIM(phone) = '')
");

include '../includes/header.php';
displayFlash();
?>

<div class="dashboard-stats" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('bell', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo $sentTotal; ?></h3>
            <p>SMS Delivered</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('clock', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo $sentToday; ?></h3>
            <p>Sent Today</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('calendar', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo $reminderCount; ?></h3>
            <p>Class Reminders</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('award', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo $gradeCount; ?></h3>
            <p>Grade Notices</p>
        </div>
    </div>
</div>

<?php if ($studentsWithoutPhone > 0): ?>
    <div class="alert alert-error">
        <?php echo icon('help', 18); ?>
        <span>
            <?php echo $studentsWithoutPhone; ?> active student(s) have no mobile number, so they cannot receive SMS.
            <a href="students.php" style="font-weight: 700; text-decoration: underline;">Update their records</a>
            to enable text notifications. They still see every message on their Notifications page.
        </span>
    </div>
<?php endif; ?>

<?php if ($failedCount > 0): ?>
    <div class="alert alert-error">
        <?php echo icon('x-circle', 18); ?>
        <span>
            <?php echo $failedCount; ?> message(s) could not be delivered. Check that the phone running the
            SMS Gateway APK is online, signed in, and has load, then look at the error in the log below.
        </span>
    </div>
<?php endif; ?>

<div class="dashboard-stats" style="grid-template-columns: repeat(2, minmax(0, 1fr)); margin-bottom: 24px;">
    <div class="card" style="padding: 24px;">
        <h2 style="display: flex; align-items: center; gap: 8px; font-size: 18px; margin-bottom: 4px;">
            <?php echo icon('calendar-check', 20); ?> Class Reminders
        </h2>
        <p style="color: var(--ink-muted); font-size: 13px; line-height: 20px; margin-bottom: 16px;">
            Texts every student who has a class tomorrow. The job also runs on its own once a day when the
            dashboard is opened, so this button is for testing or for sending it again.
            <?php if ($lastRun !== ''): ?>
                <br><strong>Last run:</strong> <?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($lastRun))); ?>
            <?php else: ?>
                <br><strong>Last run:</strong> not yet
            <?php endif; ?>
        </p>
        <form method="post">
            <input type="hidden" name="action" value="run_reminders">
            <button type="submit" class="btn btn-primary"><?php echo icon('calendar', 14); ?> Send reminders for tomorrow</button>
        </form>
    </div>

    <div class="card" style="padding: 24px;">
        <h2 style="display: flex; align-items: center; gap: 8px; font-size: 18px; margin-bottom: 16px;">
            <?php echo icon('help', 20); ?> Gateway Setup
        </h2>
        <p style="color: var(--ink-muted); font-size: 13px; line-height: 20px;">
            The username and password from your SMS Gateway APK are entered in
            <code>sms/function/sms-service.php</code> (no .env file is used). The phone must be switched on,
            connected to the internet, and signed in to the same account. A test message below confirms the setup.
        </p>
        <a href="../SMS-SETUP-GUIDE.md" target="_blank" class="btn btn-secondary btn-sm"><?php echo icon('book-open', 14); ?> Open setup guide</a>
    </div>
</div>

<div class="table-container" style="margin-bottom: 24px;">
    <div class="table-header">
        <h2><?php echo icon('bell', 24); ?> Send a Test SMS</h2>
    </div>
    <form method="post" style="padding: 24px;">
        <input type="hidden" name="action" value="send_test">
        <div class="form-row">
            <div class="form-group">
                <label for="test-phone">Phone Number</label>
                <input type="text" id="test-phone" name="phone" class="form-control" placeholder="09171234567" required>
            </div>
            <div class="form-group">
                <label for="test-message">Message</label>
                <input type="text" id="test-message" name="message" class="form-control"
                       value="Test message from the Academic Excellence System." required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo icon('bell', 14); ?> Send Test SMS</button>
    </form>
</div>

<div class="table-container">
    <div class="table-header">
        <h2><?php echo icon('file-text', 24); ?> Delivery Log
            <small style="display: block; color: var(--ink-muted); font-size: 12px; font-weight: 400; text-transform: none; letter-spacing: 0;">
                Last 100 messages
            </small>
        </h2>
        <?php if ($sentTotal + $failedCount > 0): ?>
            <form method="post" onsubmit="return confirm('Clear the SMS delivery log?');" style="margin: 0;">
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="btn btn-danger btn-sm"><?php echo icon('x', 14); ?> Clear log</button>
            </form>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Sent</th>
                <th>Type</th>
                <th>Student</th>
                <th>Number</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logRows)): ?>
                <tr class="table-empty-row">
                    <td colspan="5" class="table-empty">No SMS has been sent yet.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($logRows as $row): ?>
                <tr>
                    <td data-label="Sent"><?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($row['sent_at']))); ?></td>
                    <td data-label="Type"><?php echo htmlspecialchars(notificationTypeLabel($row['notification_type'])); ?></td>
                    <td data-label="Student">
                        <?php echo htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])); ?>
                        <span style="display: block; color: var(--ink-muted); font-size: 12px;"><?php echo htmlspecialchars($row['student_no']); ?></span>
                    </td>
                    <td data-label="Number"><?php echo htmlspecialchars($row['phone'] ?: '—'); ?></td>
                    <td data-label="Status">
                        <?php if ($row['status'] === 'sent'): ?>
                            <span class="grade-badge grade-top">Sent</span>
                        <?php elseif ($row['status'] === 'skipped'): ?>
                            <span class="grade-badge grade-pass" title="<?php echo htmlspecialchars((string) $row['error']); ?>">No number</span>
                        <?php else: ?>
                            <span class="grade-badge grade-fail">Failed</span>
                            <?php if (!empty($row['error'])): ?>
                                <span style="display: block; color: var(--error); font-size: 12px; margin-top: 4px;"><?php echo htmlspecialchars($row['error']); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
