<?php
/**
 * Schedule Reminder Cron Job
 * 
 * This script should be run daily (preferably in the evening) to send SMS reminders
 * to students about their classes happening the next day.
 * 
 * HOW TO SET UP AS A CRON JOB:
 * 
 * Linux/Mac:
 * Add this line to your crontab (crontab -e):
 * 0 18 * * * php /path/to/your/project/sms/cron/schedule-reminder.php
 * (This runs daily at 6:00 PM)
 * 
 * Windows (Task Scheduler):
 * 1. Open Task Scheduler
 * 2. Create Basic Task
 * 3. Set trigger: Daily at 6:00 PM
 * 4. Action: Start a program
 * 5. Program: C:\path\to\php.exe
 * 6. Arguments: C:\Development\Badilles\sms\cron\schedule-reminder.php
 * 
 * OR run manually for testing:
 * php sms/cron/schedule-reminder.php
 */

// Set paths relative to the script location
$projectRoot = dirname(dirname(__DIR__));
require_once $projectRoot . '/config/database.php';
require_once $projectRoot . '/sms/function/sms-service.php';

// Get database connection
$db = getDB();

// Get SMS service
$smsService = getSMSService();

// Get tomorrow's date
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$tomorrowDayName = date('l', strtotime('+1 day')); // e.g., "Monday"

echo "=== Schedule Reminder Cron Job ===\n";
echo "Running at: " . date('Y-m-d H:i:s') . "\n";
echo "Checking for classes on: {$tomorrow} ({$tomorrowDayName})\n\n";

try {
    // Find all active enrollments for classes happening tomorrow
    // We need to parse the schedule field to check if it contains tomorrow's day
    $stmt = $db->prepare("
        SELECT DISTINCT
            e.id as enrollment_id,
            s.id as student_id,
            s.first_name,
            s.last_name,
            s.phone,
            sub.subject_code,
            sub.subject_name,
            cs.schedule,
            cs.room,
            i.first_name as instructor_first,
            i.last_name as instructor_last,
            cs.section_code
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN course_sections cs ON e.section_id = cs.id
        JOIN subjects sub ON cs.subject_id = sub.id
        JOIN instructors i ON cs.instructor_id = i.id
        JOIN academic_terms t ON cs.term_id = t.id
        WHERE e.status = 'Enrolled'
          AND s.status = 'Active'
          AND t.is_current = 1
          AND s.phone IS NOT NULL
          AND s.phone != ''
          AND cs.schedule IS NOT NULL
          AND cs.schedule != ''
    ");
    
    $stmt->execute();
    $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($enrollments) . " total active enrollments with phone numbers.\n";
    
    $sentCount = 0;
    $skippedCount = 0;
    $errorCount = 0;
    
    foreach ($enrollments as $enrollment) {
        $schedule = strtolower($enrollment['schedule']);
        $tomorrowDay = strtolower($tomorrowDayName);
        
        // Check if the schedule contains tomorrow's day
        // Schedule format examples: "MWF 9:00-10:00 AM", "TTh 1:00-2:30 PM", "Monday/Wednesday 10:00 AM"
        $hasClass = false;
        
        // Check for full day name
        if (strpos($schedule, $tomorrowDay) !== false) {
            $hasClass = true;
        }
        
        // Check for abbreviated day names
        $dayAbbreviations = [
            'monday' => 'mon|m',
            'tuesday' => 'tue|tu|t',
            'wednesday' => 'wed|w',
            'thursday' => 'thu|th',
            'friday' => 'fri|f',
            'saturday' => 'sat|s'
        ];
        
        if (isset($dayAbbreviations[$tomorrowDay])) {
            $patterns = explode('|', $dayAbbreviations[$tomorrowDay]);
            foreach ($patterns as $pattern) {
                // Use word boundaries to match abbreviations accurately
                if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/i', $schedule)) {
                    $hasClass = true;
                    break;
                }
            }
        }
        
        if (!$hasClass) {
            $skippedCount++;
            continue;
        }
        
        // Student has class tomorrow, send reminder
        $studentName = $enrollment['first_name'] . ' ' . $enrollment['last_name'];
        $subjectName = $enrollment['subject_code'] . ' - ' . $enrollment['subject_name'];
        $room = $enrollment['room'] ?: 'TBA';
        $instructorName = $enrollment['instructor_first'] . ' ' . $enrollment['instructor_last'];
        
        echo "Sending reminder to {$studentName} ({$enrollment['phone']}) for {$subjectName}...\n";
        
        $result = $smsService->sendScheduleReminder(
            $enrollment['phone'],
            $studentName,
            $subjectName,
            $enrollment['schedule'],
            $room,
            $instructorName
        );
        
        if ($result['success']) {
            $sentCount++;
            echo "  ✓ Sent successfully\n";
            
            // Optional: Log the notification in database for tracking
            logNotification($db, $enrollment['student_id'], 'schedule_reminder', $enrollment['enrollment_id']);
        } else {
            $errorCount++;
            echo "  ✗ Failed: {$result['message']}\n";
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "SMS sent: {$sentCount}\n";
    echo "Skipped (no class tomorrow): {$skippedCount}\n";
    echo "Errors: {$errorCount}\n";
    echo "===============\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Schedule reminder cron error: " . $e->getMessage());
    exit(1);
}

/**
 * Log SMS notification in database (optional tracking)
 */
function logNotification($db, $studentId, $type, $referenceId = null) {
    try {
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
        
        $stmt = $db->prepare("
            INSERT INTO sms_notifications (student_id, notification_type, reference_id)
            VALUES (:student_id, :type, :ref_id)
        ");
        
        $stmt->execute([
            ':student_id' => $studentId,
            ':type' => $type,
            ':ref_id' => $referenceId
        ]);
    } catch (Exception $e) {
        // Don't fail the whole process if logging fails
        error_log("Failed to log notification: " . $e->getMessage());
    }
}
