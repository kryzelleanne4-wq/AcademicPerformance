<?php
/**
 * Notifications Page (Student)
 *
 * Shows every message the system sent to this student - the "you have a class
 * tomorrow" reminders and the "your grade was posted" notices - and lets them
 * mark items as read. The same messages are also delivered by SMS.
 */

require_once '../includes/functions.php';
require_once '../includes/notifications.php';
requireRole('student');

$pageTitle = 'Notifications';

$db = getDB();
$student = currentStudent();
$studentId = (int) $student['id'];

// Mark one / all notifications as read.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        markNotificationRead($db, $studentId, (int) ($_POST['id'] ?? 0));
    } elseif ($action === 'mark_all_read') {
        $count = markAllNotificationsRead($db, $studentId);
        setFlash($count > 0 ? $count . ' notification(s) marked as read.' : 'You are already up to date.');
    }

    $filter = sanitize($_POST['type'] ?? 'all');
    header('Location: notifications.php' . ($filter !== 'all' ? '?type=' . urlencode($filter) : ''));
    exit();
}

// Optional type filter from the tab links.
$type = sanitize($_GET['type'] ?? 'all');
if (!in_array($type, ['schedule_reminder', 'grade_posted'], true)) {
    $type = 'all';
}

$sql = "SELECT * FROM notifications WHERE student_id = :sid";
$params = [':sid' => $studentId];
if ($type !== 'all') {
    $sql .= ' AND notification_type = :type';
    $params[':type'] = $type;
}
$sql .= ' ORDER BY created_at DESC, id DESC LIMIT 200';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

$totalCount     = (int) querySingle("SELECT COUNT(*) FROM notifications WHERE student_id = " . $studentId);
$unreadCount    = countUnreadNotifications($db, $studentId);
$reminderCount  = (int) querySingle("SELECT COUNT(*) FROM notifications WHERE student_id = " . $studentId . " AND notification_type = 'schedule_reminder'");
$gradeCount     = (int) querySingle("SELECT COUNT(*) FROM notifications WHERE student_id = " . $studentId . " AND notification_type = 'grade_posted'");

// Friendly "2 hours ago" style label.
function notificationTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    if (!$time) {
        return '';
    }
    $diff = time() - $time;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' hr ago';
    }
    if ($diff < 172800) {
        return 'yesterday';
    }
    return formatDate($timestamp);
}

include '../includes/header.php';
displayFlash();
?>

<div class="dashboard-stats" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('bell', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo $totalCount; ?></h3>
            <p>All Notifications</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('help', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo $unreadCount; ?></h3>
            <p>Unread</p>
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
            <p>Grades Posted</p>
        </div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h2><?php echo icon('bell', 24); ?> Notifications
            <small style="display: block; color: var(--ink-muted); font-size: 12px; font-weight: 400; text-transform: none; letter-spacing: 0;">
                Class reminders and grade updates, also sent to <?php echo htmlspecialchars($student['phone'] ?: 'your mobile number'); ?>
            </small>
        </h2>
        <?php if ($unreadCount > 0): ?>
            <form method="post" style="margin: 0;">
                <input type="hidden" name="action" value="mark_all_read">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                <button type="submit" class="btn btn-secondary btn-sm"><?php echo icon('check-circle', 14); ?> Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="filter-bar" style="border-bottom: 1px solid var(--outline-hairline);">
        <a href="notifications.php" class="btn btn-sm <?php echo $type === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">All</a>
        <a href="notifications.php?type=schedule_reminder" class="btn btn-sm <?php echo $type === 'schedule_reminder' ? 'btn-primary' : 'btn-secondary'; ?>">Class Reminders</a>
        <a href="notifications.php?type=grade_posted" class="btn btn-sm <?php echo $type === 'grade_posted' ? 'btn-primary' : 'btn-secondary'; ?>">Grades Posted</a>
    </div>

    <?php if (empty($notifications)): ?>
        <div style="padding: 48px 24px; text-align: center; color: var(--ink-muted);">
            <?php echo icon('bell', 40); ?>
            <p style="margin-top: 12px;">No notifications yet. You will be notified here (and by SMS) when you have a class the next day or when a teacher posts your grade.</p>
        </div>
    <?php else: ?>
        <div class="notification-list">
            <?php foreach ($notifications as $notification): ?>
                <?php
                $isUnread = empty($notification['read_at']);
                $notifType = $notification['notification_type'];
                $iconName = $notifType === 'schedule_reminder' ? 'calendar' : ($notifType === 'grade_posted' ? 'award' : 'bell');
                ?>
                <article class="notification-item <?php echo $isUnread ? 'is-unread' : ''; ?>">
                    <div class="notification-icon notification-icon-<?php echo htmlspecialchars($notifType); ?>">
                        <?php echo icon($iconName, 20); ?>
                    </div>
                    <div class="notification-body">
                        <div class="notification-title-row">
                            <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                            <span class="notification-time" title="<?php echo htmlspecialchars(date('M j, Y g:i A', strtotime($notification['created_at']))); ?>">
                                <?php echo htmlspecialchars(notificationTimeAgo($notification['created_at'])); ?>
                            </span>
                        </div>
                        <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                        <div class="notification-meta">
                            <span class="notification-badge"><?php echo htmlspecialchars(notificationTypeLabel($notifType)); ?></span>
                            <?php if ($notifType === 'grade_posted'): ?>
                                <a href="my-grades.php" class="notification-link">View grades</a>
                            <?php else: ?>
                                <a href="subjects.php" class="notification-link">View subjects</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($isUnread): ?>
                        <form method="post" class="notification-action">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="id" value="<?php echo (int) $notification['id']; ?>">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                            <button type="submit" class="btn btn-sm btn-secondary">Mark read</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
