<?php
/**
 * Schedule Reminder Job - "you have a class tomorrow" SMS
 *
 * Sends one text message to every student who has a class the next day and
 * records an in-app notification on their Notifications page.
 *
 * RUN IT AUTOMATICALLY
 * --------------------
 * You normally do not have to schedule anything: the dashboard runs this job
 * once per day (see index.php) so reminders go out as soon as somebody opens
 * the system. If you prefer a real scheduled task, either option below works
 * and the job stays safe to run twice - a student is never texted twice for
 * the same class on the same day.
 *
 * Linux/Mac (crontab -e):
 *   0 18 * * * php /path/to/project/sms/cron/schedule-reminder.php
 *   (daily at 6:00 PM)
 *
 * Windows (Task Scheduler):
 *   1. Open Task Scheduler -> Create Basic Task
 *   2. Trigger: Daily at 6:00 PM
 *   3. Action: Start a program
 *      Program:   C:\path\to\php.exe
 *      Arguments: C:\Development\Badilles\sms\cron\schedule-reminder.php
 *
 * Or run it by hand any time:
 *   php sms/cron/schedule-reminder.php
 *
 * The SMS Gateway username/password live in sms/function/sms-service.php.
 */

$projectRoot = dirname(dirname(__DIR__));
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/includes/notifications.php';

$db = getDB();

echo "=== Schedule Reminder Job ===\n";
echo 'Run at: ' . date('Y-m-d H:i:s') . "\n";

try {
    // $force = true: manual/cron runs ignore the "already ran today" marker.
    // Duplicate protection per student + class + day is handled inside.
    $summary = runScheduleReminders($db, true);

    echo 'Checking classes on: ' . $summary['date'] . ' (' . $summary['weekday'] . ")\n\n";
    echo 'Active enrollments checked: ' . $summary['checked'] . "\n";
    echo 'Students with a class tomorrow: ' . $summary['matched'] . "\n";
    echo 'SMS sent: ' . $summary['sent'] . "\n";
    echo 'Already notified today: ' . $summary['already_sent'] . "\n";
    echo 'Skipped (no mobile number): ' . $summary['skipped_no_phone'] . "\n";
    echo 'Failed: ' . $summary['failed'] . "\n";

    if ($summary['failed'] > 0) {
        echo "\nSome messages could not be delivered. Open the SMS Notifications page\n";
        echo "in the admin portal to see the error returned by the gateway.\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    error_log('Schedule reminder job error: ' . $e->getMessage());
    exit(1);
}
