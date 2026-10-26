<?php
/**
 * Student Notifications (in-app + SMS)
 *
 * Everything that has to notify a student goes through this file:
 *
 *   - addStudentNotification()   stores the message shown on the student's
 *                                Notifications page (works even without a phone)
 *   - sendStudentSms()           texts the student through the Android SMS
 *                                Gateway APK and records the attempt
 *   - notifyGradePosted()        used by pages/grades.php when a teacher saves
 *                                a score sheet
 *   - runScheduleReminders()     texts every student who has a class tomorrow
 *
 * All texts are sent with the credentials configured in
 * sms/function/sms-service.php (no .env file is used).
 */

require_once __DIR__ . '/functions.php';

// ── Gateway plumbing ────────────────────────────────────────────────────

/**
 * Load the Android SMS Gateway client once per request.
 * Returns null when the library (sms/vendor) has not been installed.
 */
function smsGatewayService() {
    static $service = false;
    if ($service !== false) {
        return $service;
    }

    $serviceFile = dirname(__DIR__) . '/sms/function/sms-service.php';
    $autoload    = dirname(__DIR__) . '/sms/vendor/autoload.php';

    if (!is_file($serviceFile) || !is_file($autoload)) {
        error_log('SMS: gateway library missing. Run "composer install" inside the sms/ folder.');
        return $service = null;
    }

    require_once $serviceFile;
    if (!function_exists('getSMSService')) {
        return $service = null;
    }

    try {
        $service = getSMSService();
    } catch (Throwable $e) {
        error_log('SMS: could not start the gateway client - ' . $e->getMessage());
        $service = null;
    }

    return $service;
}

/**
 * Record one SMS attempt (sent, failed or skipped) in sms_notifications.
 */
function logSmsNotification(PDO $db, $studentId, $type, $title, $message, $status, $error = null, $referenceId = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO sms_notifications
                (student_id, notification_type, title, message, status, error, reference_id, sent_at)
            VALUES
                (:student_id, :type, :title, :message, :status, :error, :reference_id, :sent_at)
        ");
        $stmt->execute([
            ':student_id'   => (int) $studentId,
            ':type'         => (string) $type,
            ':title'        => (string) $title,
            ':message'      => (string) $message,
            ':status'       => (string) $status,
            ':error'        => $error !== null ? (string) $error : null,
            ':reference_id' => $referenceId !== null ? (int) $referenceId : null,
            ':sent_at'      => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        error_log('SMS: could not write the delivery log - ' . $e->getMessage());
    }
}

/**
 * Was a text of this kind already handled today?
 * By default only successful sends count, so a failed attempt can be retried.
 */
function smsAlreadySent(PDO $db, $studentId, $type, $referenceId = null, $day = null, $status = 'sent') {
    $sql = "SELECT COUNT(*) FROM sms_notifications
            WHERE student_id = :sid
              AND notification_type = :type
              AND status = :status";
    $params = [':sid' => (int) $studentId, ':type' => $type, ':status' => $status];

    if ($referenceId !== null) {
        $sql .= ' AND reference_id = :ref';
        $params[':ref'] = (int) $referenceId;
    }
    if ($day !== null) {
        $sql .= ' AND DATE(sent_at) = :day';
        $params[':day'] = $day;
    }

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        error_log('SMS: duplicate check failed - ' . $e->getMessage());
        return false;
    }
}

// ── In-app notifications ────────────────────────────────────────────────

/**
 * Add a row to the student's Notifications page.
 */
function addStudentNotification(PDO $db, $studentId, $type, $title, $message, $referenceId = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications
                (student_id, notification_type, title, message, reference_id, created_at)
            VALUES
                (:student_id, :type, :title, :message, :reference_id, :created_at)
        ");
        $stmt->execute([
            ':student_id'   => (int) $studentId,
            ':type'         => (string) $type,
            ':title'        => (string) $title,
            ':message'      => (string) $message,
            ':reference_id' => $referenceId !== null ? (int) $referenceId : null,
            ':created_at'   => date('Y-m-d H:i:s'),
        ]);
        return (int) $db->lastInsertId();
    } catch (Throwable $e) {
        error_log('Notification: could not save - ' . $e->getMessage());
        return 0;
    }
}

/**
 * Has this exact notification already been added today?
 */
function studentNotificationExists(PDO $db, $studentId, $type, $referenceId = null, $day = null) {
    $sql = "SELECT COUNT(*) FROM notifications
            WHERE student_id = :sid AND notification_type = :type";
    $params = [':sid' => (int) $studentId, ':type' => $type];

    if ($referenceId !== null) {
        $sql .= ' AND reference_id = :ref';
        $params[':ref'] = (int) $referenceId;
    }
    if ($day !== null) {
        $sql .= ' AND DATE(created_at) = :day';
        $params[':day'] = $day;
    }

    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function countUnreadNotifications(PDO $db, $studentId) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE student_id = :sid AND read_at IS NULL");
        $stmt->execute([':sid' => (int) $studentId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function markAllNotificationsRead(PDO $db, $studentId) {
    try {
        $stmt = $db->prepare("UPDATE notifications SET read_at = :now WHERE student_id = :sid AND read_at IS NULL");
        $stmt->execute([':now' => date('Y-m-d H:i:s'), ':sid' => (int) $studentId]);
        return $stmt->rowCount();
    } catch (Throwable $e) {
        return 0;
    }
}

function markNotificationRead(PDO $db, $studentId, $notificationId) {
    try {
        $stmt = $db->prepare("
            UPDATE notifications SET read_at = :now
            WHERE id = :id AND student_id = :sid AND read_at IS NULL
        ");
        $stmt->execute([
            ':now' => date('Y-m-d H:i:s'),
            ':id'  => (int) $notificationId,
            ':sid' => (int) $studentId,
        ]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function notificationTypeLabel($type) {
    return [
        'schedule_reminder' => 'Class Reminder',
        'grade_posted'      => 'Grade Posted',
        'general'           => 'Notice',
    ][$type] ?? ucfirst(str_replace('_', ' ', (string) $type));
}

// ── Sending a text ──────────────────────────────────────────────────────

/**
 * Text a student and log the attempt.
 *
 * @return array{success:bool,message:string,skipped?:bool}
 */
function sendStudentSms(PDO $db, $studentId, $type, $title, $message, $referenceId = null) {
    $studentId = (int) $studentId;

    $stmt = $db->prepare("SELECT id, first_name, last_name, phone FROM students WHERE id = :id");
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        return ['success' => false, 'skipped' => true, 'message' => 'Student record not found.'];
    }

    $phone = trim((string) ($student['phone'] ?? ''));
    if ($phone === '') {
        $reason = 'No mobile number on record.';
        logSmsNotification($db, $studentId, $type, $title, $message, 'skipped', $reason, $referenceId);
        return ['success' => false, 'skipped' => true, 'message' => $reason];
    }

    $service = smsGatewayService();
    if (!$service) {
        $reason = 'SMS gateway library is not available (run composer install in sms/).';
        logSmsNotification($db, $studentId, $type, $title, $message, 'failed', $reason, $referenceId);
        return ['success' => false, 'message' => $reason];
    }

    try {
        $result = $service->sendSMS($phone, $message);
    } catch (Throwable $e) {
        $result = ['success' => false, 'message' => $e->getMessage()];
    }

    if (!empty($result['success'])) {
        logSmsNotification($db, $studentId, $type, $title, $message, 'sent', null, $referenceId);
        return ['success' => true, 'message' => 'SMS sent to ' . $phone . '.'];
    }

    logSmsNotification($db, $studentId, $type, $title, $message, 'failed', $result['message'] ?? 'Unknown error', $referenceId);
    return ['success' => false, 'message' => $result['message'] ?? 'Could not send the SMS.'];
}

// ── Schedule parsing ────────────────────────────────────────────────────

function notificationDayIndex($name) {
    static $map = [
        'monday' => 0, 'mon' => 0,
        'tuesday' => 1, 'tue' => 1, 'tues' => 1,
        'wednesday' => 2, 'wed' => 2, 'weds' => 2,
        'thursday' => 3, 'thu' => 3, 'thur' => 3, 'thurs' => 3,
        'friday' => 4, 'fri' => 4,
        'saturday' => 5, 'sat' => 5,
        'sunday' => 6, 'sun' => 6,
    ];
    $name = strtolower(trim((string) $name));
    return $map[$name] ?? null;
}

/**
 * Work out which weekdays a schedule string covers.
 *
 * Handles the formats the Schedules page accepts, including ones that have no
 * time yet (e.g. "Mon - Thu") and compact codes (e.g. "MWF", "TTh").
 *
 * @return int[] Day numbers, 0 = Sunday ... 6 = Saturday.
 */
function scheduleDays($schedule) {
    $text = strtolower(trim((string) $schedule));
    if ($text === '') {
        return [];
    }

    $days = [];
    $namePattern = 'monday|mon|tuesday|tue|tues|wednesday|wed|weds|thursday|thu|thur|thurs|friday|fri|saturday|sat|sunday|sun';

    // Each ";" separated chunk is an independent session.
    foreach (explode(';', $text) as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') {
            continue;
        }

        // Drop the time part: "7:00 AM - 8:30 AM", "13:00-14:30", "7-8 PM".
        $clean = preg_replace(
            '/\d{1,2}(?::\d{2})?\s*(?:am|pm)?\s*(?:-|–|—|to|until)\s*\d{1,2}(?::\d{2})?\s*(?:am|pm)?/i',
            ' ',
            $chunk
        );
        $clean = preg_replace('/\b\d{1,2}:\d{2}\s*(?:am|pm)?/i', ' ', (string) $clean);
        $clean = preg_replace('/\b(?:am|pm)\b/i', ' ', (string) $clean);
        $clean = trim(preg_replace('/\s+/', ' ', (string) $clean));

        // 1) A day range: "Mon - Thu", "Monday to Friday".
        if (preg_match('/(' . $namePattern . ')\s*(?:-|–|—|to|through)\s*(' . $namePattern . ')/i', $clean, $m)) {
            $start = notificationDayIndex($m[1]);
            $end   = notificationDayIndex($m[2]);
            if ($start !== null && $end !== null && $start <= $end) {
                for ($d = $start; $d <= $end; $d++) {
                    $days[$d] = true;
                }
                continue;
            }
        }

        // 2) Day names or abbreviations: "Mon, Wed", "Tuesday & Thursday".
        if (preg_match_all('/\b(' . $namePattern . ')\b/i', $clean, $m)) {
            $found = false;
            foreach ($m[1] as $name) {
                $index = notificationDayIndex($name);
                if ($index !== null) {
                    $days[$index] = true;
                    $found = true;
                }
            }
            if ($found) {
                continue;
            }
        }

        // 3) Compact codes: "MWF", "TTh", "T,Th".
        $code = preg_replace('/[\s,&.\/]+/', '', $clean);
        if ($code !== '' && preg_match('/^[mtwhfs]{2,6}$/i', $code)) {
            $map = ['m' => 0, 't' => 1, 'w' => 2, 'h' => 3, 'f' => 4, 's' => 5];
            $length = strlen($code);
            for ($i = 0; $i < $length; $i++) {
                $pair = strtolower(substr($code, $i, 2));
                if ($pair === 'th') {
                    $days[$map['h']] = true;
                    $i++;
                } elseif (isset($map[strtolower($code[$i])])) {
                    $days[$map[strtolower($code[$i])]] = true;
                }
            }
        }
    }

    $result = array_keys($days);
    sort($result);
    return $result;
}

function scheduleHasDay($schedule, $weekday) {
    return in_array((int) $weekday, scheduleDays($schedule), true);
}

// ── Grade posted ────────────────────────────────────────────────────────

/**
 * Tell a student their grade was posted (in-app + SMS).
 */
function notifyGradePosted(PDO $db, $studentId, $sectionId, $subjectCode, $subjectName, $grade, $score, $instructorName = '') {
    $stmt = $db->prepare("SELECT first_name, phone FROM students WHERE id = :id");
    $stmt->execute([':id' => (int) $studentId]);
    $student = $stmt->fetch();
    if (!$student) {
        return ['success' => false, 'message' => 'Student record not found.'];
    }

    $firstName     = trim((string) $student['first_name']);
    $displayGrade  = formatGrade($grade) !== '' ? formatGrade($grade) : (string) $grade;
    $scoreText     = ($score !== null && $score !== '') ? ' (' . rtrim(rtrim(number_format((float) $score, 2, '.', ''), '0'), '.') . '%)' : '';
    $subjectLabel  = trim($subjectCode . ' - ' . $subjectName, ' -');
    $teacherText   = $instructorName !== '' ? ' by ' . $instructorName : '';

    $title = 'Grade posted: ' . $subjectCode;
    $body  = 'Your grade for ' . $subjectLabel . ' was posted' . $teacherText . ': ' . $displayGrade . $scoreText
           . '. Open My Grades to see the component breakdown.';
    $sms   = 'Hi ' . $firstName . '! Your grade for ' . $subjectLabel . ' was posted: ' . $displayGrade . $scoreText
           . '. Check your student portal. - ACADEMIC EXCELLENCE';

    // A new row is created only when the teacher actually changed a grade,
    // so each update produces exactly one notification.
    addStudentNotification($db, $studentId, 'grade_posted', $title, $body, $sectionId);

    return sendStudentSms($db, $studentId, 'grade_posted', $title, $sms, $sectionId);
}

// ── Class tomorrow reminders ────────────────────────────────────────────

function appSetting(PDO $db, $key, $default = null) {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = :key");
        $stmt->execute([':key' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : $value;
    } catch (Throwable $e) {
        return $default;
    }
}

function saveAppSetting(PDO $db, $key, $value) {
    try {
        $stmt = $db->prepare("
            INSERT INTO app_settings (setting_key, setting_value, updated_at)
            VALUES (:key, :value, :updated_at)
            ON CONFLICT(setting_key) DO UPDATE SET
                setting_value = excluded.setting_value,
                updated_at = excluded.updated_at
        ");
        $stmt->execute([
            ':key'        => $key,
            ':value'      => (string) $value,
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        error_log('Settings: could not save ' . $key . ' - ' . $e->getMessage());
    }
}

/**
 * Notify every enrolled student who has a class tomorrow.
 *
 * Safe to call as often as you like: a student gets at most one text per
 * class per day, and the in-app notification is only added once.
 *
 * @param bool $force Ignore the once-per-day run marker (manual "send now").
 * @return array Summary counters for the caller/CLI output.
 */
function runScheduleReminders(PDO $db, $force = false) {
    // A classroom-sized batch of texts can take longer than PHP's default 30s
    // web limit, and a half-finished run simply resumes on the next page load.
    if (PHP_SAPI !== 'cli' && function_exists('set_time_limit')) {
        @set_time_limit(180);
    }

    $tomorrowTs      = strtotime('+1 day');
    $tomorrow        = date('Y-m-d', $tomorrowTs);
    // scheduleDays() numbers the week with Monday = 0 (matching SCHEDULE_DAYS).
    $tomorrowWeekday = (int) date('N', $tomorrowTs) - 1;
    $tomorrowName    = date('l', $tomorrowTs);
    $today           = date('Y-m-d');

    $summary = [
        'date'             => $tomorrow,
        'weekday'          => $tomorrowName,
        'ran'              => true,
        'checked'          => 0,
        'matched'          => 0,
        'sent'             => 0,
        'failed'           => 0,
        'skipped_no_phone' => 0,
        'already_sent'     => 0,
    ];

    $stmt = $db->query("
        SELECT e.id AS enrollment_id,
               e.section_id,
               st.id AS student_id,
               st.first_name,
               st.last_name,
               st.phone,
               sub.subject_code,
               sub.subject_name,
               cs.section_code,
               cs.schedule,
               cs.room,
               ins.first_name AS instructor_first,
               ins.last_name  AS instructor_last
        FROM enrollments e
        JOIN students st         ON e.student_id = st.id
        JOIN course_sections cs  ON e.section_id = cs.id
        JOIN subjects sub        ON cs.subject_id = sub.id
        JOIN academic_terms t    ON cs.term_id = t.id
        LEFT JOIN instructors ins ON cs.instructor_id = ins.id
        WHERE e.status = 'Enrolled'
          AND st.status = 'Active'
          AND t.is_current = 1
          AND cs.status NOT IN ('Cancelled', 'Completed')
    ");

    foreach ($stmt as $row) {
        $summary['checked']++;

        if (!scheduleHasDay($row['schedule'], $tomorrowWeekday)) {
            continue;
        }

        $summary['matched']++;

        $studentId  = (int) $row['student_id'];
        $sectionId  = (int) $row['section_id'];
        $firstName  = trim((string) $row['first_name']);
        $subject    = trim((string) $row['subject_code'] . ' - ' . (string) $row['subject_name'], ' -');
        $schedule   = trim((string) $row['schedule']);
        $room       = trim((string) $row['room']);
        $instructor = trim((string) $row['instructor_first'] . ' ' . (string) $row['instructor_last']);

        // In-app copy (also shown to students without a phone number).
        if (!studentNotificationExists($db, $studentId, 'schedule_reminder', $sectionId, $today)) {
            $title = 'Class tomorrow: ' . $row['subject_code'];
            $body  = 'You have ' . $subject . ' tomorrow, ' . $tomorrowName . ' ' . date('M j', $tomorrowTs) . '.'
                   . ($schedule !== '' ? ' Schedule: ' . $schedule . '.' : '')
                   . ($room !== '' ? ' Room: ' . $room . '.' : '')
                   . ($instructor !== '' ? ' Instructor: ' . $instructor . '.' : '');
            addStudentNotification($db, $studentId, 'schedule_reminder', $title, $body, $sectionId);
        }

        // Only one text per class per day, even if the job runs twice.
        if (smsAlreadySent($db, $studentId, 'schedule_reminder', $sectionId, $today)) {
            $summary['already_sent']++;
            continue;
        }

        if (trim((string) $row['phone']) === '') {
            $summary['skipped_no_phone']++;
            // Record the miss once per day instead of on every manual run.
            if (!smsAlreadySent($db, $studentId, 'schedule_reminder', $sectionId, $today, 'skipped')) {
                logSmsNotification(
                    $db, $studentId, 'schedule_reminder',
                    'Class tomorrow: ' . $row['subject_code'],
                    'Reminder for ' . $subject . ' on ' . $tomorrow,
                    'skipped', 'No mobile number on record.', $sectionId
                );
            }
            continue;
        }

        $sms = 'Hi ' . $firstName . '! REMINDER: ' . $subject . ' class TOMORROW, '
             . $tomorrowName . ' ' . date('M j', $tomorrowTs) . '.'
             . ($schedule !== '' ? ' Schedule: ' . $schedule . '.' : '')
             . ($room !== '' ? ' Room: ' . $room . '.' : '')
             . ($instructor !== '' ? ' Instructor: ' . $instructor . '.' : '')
             . ' - ACADEMIC EXCELLENCE';

        $result = sendStudentSms(
            $db, $studentId, 'schedule_reminder',
            'Class tomorrow: ' . $row['subject_code'],
            $sms, $sectionId
        );

        if (!empty($result['success'])) {
            $summary['sent']++;
        } else {
            $summary['failed']++;
        }
    }

    saveAppSetting($db, 'schedule_reminder_last_run', date('Y-m-d H:i:s'));
    $summary['ran_at'] = date('Y-m-d H:i:s');

    return $summary;
}

/**
 * Run the reminder job at most once per calendar day.
 * Called from the dashboard so reminders go out even without a cron entry.
 *
 * @return array|null Summary when it ran, null when it was already done today.
 */
function runScheduleRemindersIfDue(PDO $db) {
    $lastRun = (string) appSetting($db, 'schedule_reminder_last_run', '');
    if ($lastRun !== '' && substr($lastRun, 0, 10) === date('Y-m-d')) {
        return null;
    }

    return runScheduleReminders($db);
}
