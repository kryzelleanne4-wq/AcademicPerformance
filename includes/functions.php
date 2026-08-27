<?php
/**
 * Common Helper Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Convert a percentage score (0-100) to the Philippine 5-to-1 grading scale.
// 1.00 is the highest (excellent) and 5.00 is the lowest (failing).
// Passing is >= 75% (3.00). Returns the numeric grade as a string like '1.50'.
function calculateGrade($score) {
    $score = (float) $score;
    if ($score >= 96) return '1.00';
    if ($score >= 93) return '1.25';
    if ($score >= 90) return '1.50';
    if ($score >= 88) return '1.75';
    if ($score >= 85) return '2.00';
    if ($score >= 83) return '2.25';
    if ($score >= 80) return '2.50';
    if ($score >= 78) return '2.75';
    if ($score >= 75) return '3.00';
    return '5.00';
}

// Descriptor for a 5-to-1 numeric grade (for tooltips/labels).
function gradeDescriptor($grade) {
    return [
        '1.00' => 'Excellent',
        '1.25' => 'Very Good',
        '1.50' => 'Very Good',
        '1.75' => 'Good',
        '2.00' => 'Good',
        '2.25' => 'Satisfactory',
        '2.50' => 'Satisfactory',
        '2.75' => 'Fairly Satisfactory',
        '3.00' => 'Fairly Satisfactory',
        '4.00' => 'Conditional',
        '5.00' => 'Failing'
    ][(string) $grade] ?? '';
}

// Badge class for a 5-to-1 numeric grade (groups bands by performance).
function gradeBadgeClass($grade) {
    $g = (string) $grade;
    if ($g === '1.00' || $g === '1.25' || $g === '1.50') return 'grade-top';
    if ($g === '1.75' || $g === '2.00' || $g === '2.25') return 'grade-good';
    if ($g === '2.50' || $g === '2.75' || $g === '3.00') return 'grade-pass';
    return 'grade-fail';
}

// Display a numeric grade with a single decimal place (e.g. '1.50' -> '1.5').
// Truncates the second decimal so the 5-to-1 quarter-point grades stay as-is.
function formatGrade($grade) {
    if ($grade === null || $grade === '') return '';
    $g = (float) $grade;
    return number_format(floor($g * 10) / 10, 1, '.', '');
}

// Default assessment components for a subject (Excel-style score sheet columns).
function defaultComponents() {
    return [
        ['Quiz', 50, 20],
        ['Assignment', 20, 10],
        ['Midterm Exam', 100, 30],
        ['Final Exam', 100, 40]
    ];
}

// Ensure a subject has assessment components; seeds defaults if none exist.
function ensureComponents(PDO $db, $subjectId, $termId = null) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM assessment_components WHERE subject_id = :s");
    $stmt->execute([':s' => $subjectId]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $ins = $db->prepare("INSERT INTO assessment_components (subject_id, term_id, component_name, max_score, weight, sort_order) VALUES (:s, :t, :n, :m, :w, :o)");
    $order = 0;
    foreach (defaultComponents() as $component) {
        $ins->execute([':s' => $subjectId, ':t' => $termId ?: null, ':n' => $component[0], ':m' => $component[1], ':w' => $component[2], ':o' => $order++]);
    }
}

// Get the assessment components for a subject, ordered for the score sheet.
function getComponents(PDO $db, $subjectId) {
    ensureComponents($db, $subjectId);
    $stmt = $db->prepare("SELECT * FROM assessment_components WHERE subject_id = :s ORDER BY sort_order, id");
    $stmt->execute([':s' => $subjectId]);
    return $stmt->fetchAll();
}

// Add a new assessment component to a subject.
function addComponent(PDO $db, $subjectId, $name, $maxScore, $weight) {
    $order = (int) $db->query("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM assessment_components WHERE subject_id = " . (int) $subjectId)->fetchColumn();
    $stmt = $db->prepare("INSERT INTO assessment_components (subject_id, component_name, max_score, weight, sort_order) VALUES (:s, :n, :m, :w, :o)");
    $stmt->execute([':s' => $subjectId, ':n' => $name, ':m' => $maxScore, ':w' => $weight, ':o' => $order]);
}

// Update an existing assessment component (name, max, weight).
function updateComponent(PDO $db, $componentId, $name, $maxScore, $weight) {
    $stmt = $db->prepare("UPDATE assessment_components SET component_name = :n, max_score = :m, weight = :w, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $stmt->execute([':n' => $name, ':m' => $maxScore, ':w' => $weight, ':id' => $componentId]);
}

// Delete an assessment component and its recorded scores.
function deleteComponent(PDO $db, $componentId) {
    $gradeIds = $db->query("SELECT DISTINCT grade_id FROM grade_scores WHERE component_id = " . (int) $componentId)->fetchAll(PDO::FETCH_COLUMN);
    $db->prepare("DELETE FROM grade_scores WHERE component_id = :id")->execute([':id' => $componentId]);
    $db->prepare("DELETE FROM assessment_components WHERE id = :id")->execute([':id' => $componentId]);
    // Recompute overall for any affected grades once the component is gone.
    if ($gradeIds) {
        foreach ($gradeIds as $gid) {
            recalcGrade($db, (int) $gid);
        }
    }
}

// Recompute a grade's overall score from its remaining component scores.
// The subject/component set must still exist; uses grade_scores.
function recalcGrade(PDO $db, $gradeId) {
    $gStmt = $db->prepare("SELECT g.*, cs.subject_id FROM grades g LEFT JOIN course_sections cs ON g.section_id = cs.id WHERE g.id = :id");
    $gStmt->execute([':id' => $gradeId]);
    $g = $gStmt->fetch();
    if (!$g) {
        return;
    }
    $components = getComponents($db, (int) $g['subject_id']);
    $csStmt = $db->prepare("SELECT component_id, score FROM grade_scores WHERE grade_id = :gid");
    $csStmt->execute([':gid' => $gradeId]);
    $scoreMap = [];
    foreach ($csStmt->fetchAll() as $cs) {
        if ($cs['score'] !== null) {
            $scoreMap[$cs['component_id']] = $cs['score'];
        }
    }
    $overall = computeOverallScore($components, $scoreMap);
    if ($overall === null) {
        $db->prepare("UPDATE grades SET score = NULL, grade = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute([':id' => $gradeId]);
    } else {
        $db->prepare("UPDATE grades SET score = :score, grade = :grade, updated_at = CURRENT_TIMESTAMP WHERE id = :id")
            ->execute([':score' => $overall, ':grade' => calculateGrade($overall), ':id' => $gradeId]);
    }
}

// Recalculate ONLY the numeric grade label of every grade row from its stored score.
// Used to migrate letter grades (A/B/F) to the 5-to-1 scale after a switch.
function migrateGradesToNumeric(PDO $db) {
    $rows = $db->query("SELECT id, score FROM grades WHERE score IS NOT NULL")->fetchAll();
    $stmt = $db->prepare("UPDATE grades SET grade = :g, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    foreach ($rows as $row) {
        $stmt->execute([':g' => calculateGrade($row['score']), ':id' => $row['id']]);
    }
    // Mirror to enrollments.
    $db->exec("
        UPDATE enrollments SET final_grade = (
            SELECT g.grade FROM grades g WHERE g.enrollment_id = enrollments.id LIMIT 1
        )
    ");
    return count($rows);
}

// Compute the weighted overall score (0-100) from component scores.
function computeOverallScore(array $components, array $componentScores) {
    $weighted = 0.0;
    $totalWeight = 0.0;
    $haveAny = false;
    foreach ($components as $component) {
        $cid = $component['id'];
        if (isset($componentScores[$cid]) && $componentScores[$cid] !== '' && $componentScores[$cid] !== null) {
            $score = (float) $componentScores[$cid];
            $max = (float) $component['max_score'];
            $weight = (float) $component['weight'];
            $percent = $max > 0 ? ($score / $max) * 100 : 0;
            $weighted += $percent * $weight;
            $totalWeight += $weight;
            $haveAny = true;
        }
    }
    if (!$haveAny || $totalWeight <= 0) {
        return null;
    }
    return round($weighted / $totalWeight, 2);
}

// Get grade color for display
function getGradeColor($grade) {
    $colors = [
        'A' => '#22c55e',
        'B' => '#3b82f6',
        'C' => '#eab308',
        'D' => '#f97316',
        'F' => '#ef4444'
    ];
    return $colors[$grade] ?? '#6b7280';
}

// Format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Set flash message
function setFlash($message, $type = 'success') {
    $_SESSION['flash'] = [
        'message' => $message,
        'type' => $type
    ];
}

// Display flash message
function displayFlash() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $message = $_SESSION['flash']['message'];
        echo "<div class='alert alert-{$type}'>{$message}</div>";
        unset($_SESSION['flash']);
    }
}

// Generate the next student ID, e.g. STU-2026-0001
function generateStudentId(PDO $db) {
    $prefix = 'STU-' . date('Y') . '-';
    $stmt = $db->prepare("SELECT student_id FROM students WHERE student_id LIKE :prefix ORDER BY student_id DESC LIMIT 1");
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();
    $num = $last ? (int) substr($last, -4) + 1 : 1;
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

// Generate the next instructor/employee ID, e.g. EMP-2026-0001
function generateEmployeeId(PDO $db) {
    $prefix = 'EMP-' . date('Y') . '-';
    $stmt = $db->prepare("SELECT employee_id FROM instructors WHERE employee_id LIKE :prefix ORDER BY employee_id DESC LIMIT 1");
    $stmt->execute([':prefix' => $prefix . '%']);
    $last = $stmt->fetchColumn();
    $num = $last ? (int) substr($last, -4) + 1 : 1;
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

// Default password assigned to newly created user accounts.
function defaultPassword() {
    return 'password123';
}

// Ordinal label for a year level (1 => '1st', 2 => '2nd', 3 => '3rd', 5 => '5th').
function yearOrdinal($year) {
    $year = (int) $year;
    if ($year === 1) return '1st';
    if ($year === 2) return '2nd';
    if ($year === 3) return '3rd';
    return $year . 'th';
}

// Display label for a class block, e.g. "BSIT - 1st Year - Block 1".
// Falls back to the stored block_name when one is set.
function blockLabel($departmentCode, $yearLevel, $blockCode, $blockName = null) {
    if ($blockName !== null && $blockName !== '') {
        return $blockName;
    }
    return $departmentCode . ' - ' . yearOrdinal($yearLevel) . ' Year - Block ' . $blockCode;
}

// Reorder DB rows into a plain list of values matching $keys order,
// ready for exportExcel.
function pickColumns(array $rows, array $keys) {
    return array_map(function ($row) use ($keys) {
        $out = [];
        foreach ($keys as $key) {
            $out[] = $row[$key] ?? '';
        }
        return $out;
    }, $rows);
}

// Stream a table as an Excel-readable .xls file so the exported record
// matches the columns shown on screen. Rows must be plain lists of values
// in the same order as $headers (use pickColumns to build them).
function exportExcel($filename, array $headers, array $rows) {
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel renders characters correctly
    echo '<table border="1"><thead><tr>';
    foreach ($headers as $header) {
        echo '<th style="background:#e7eeff;font-weight:bold;">' . htmlspecialchars((string) $header, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $row = array_values((array) $row);
        echo '<tr>';
        foreach ($headers as $index => $header) {
            $value = $row[$index] ?? '';
            echo '<td>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    exit();
}

// ── Schedule Conflict Detection ──────────────────────────────────────

// Full day-name mapping (abbreviation => minutes-from-midnight for 00:00).
const SCHEDULE_DAYS = [
    'mon' => 0, 'monday'    => 0,
    'tue' => 1, 'tuesday'   => 1,
    'wed' => 2, 'wednesday' => 2,
    'thu' => 3, 'thursday'  => 3,
    'fri' => 4, 'friday'    => 4,
    'sat' => 5, 'saturday'  => 5,
    'sun' => 6, 'sunday'    => 6,
];

// Parse a time string (e.g. "8:00 AM", "13:30") into minutes from midnight.
function parseTimeToMinutes($timeStr) {
    $timeStr = trim($timeStr);
    if ($timeStr === '') return null;

    // Strip trailing AM/PM
    $isPM = false;
    $hasAmPm = false;
    if (preg_match('/^(.+?)\s*(AM|PM)$/i', $timeStr, $m)) {
        $timeStr = trim($m[1]);
        $isPM = strtolower($m[2]) === 'pm';
        $hasAmPm = true;
    }

    if (!preg_match('/^(\d{1,2})(?::(\d{2}))?$/', $timeStr, $m)) {
        return null;
    }

    $hour = (int) $m[1];
    $min  = isset($m[2]) ? (int) $m[2] : 0;

    if ($hasAmPm) {
        if ($isPM && $hour < 12) $hour += 12;
        if (!$isPM && $hour === 12) $hour = 0;
    }

    return $hour * 60 + $min;
}

// Parse a schedule string like "Mon & Wed, 8:00 - 9:30 AM" into structured data.
// Returns [days => [...], start_minutes => int, end_minutes => int, raw => string]
// or null if the string cannot be parsed.
function parseSchedule($schedule) {
    $schedule = trim($schedule);
    if ($schedule === '') return null;

    // Handle multi-session schedules separated by ";"
    if (strpos($schedule, ';') !== false) {
        $sessions = [];
        $parts = array_map('trim', explode(';', $schedule));
        foreach ($parts as $part) {
            if ($part === '') continue;
            $parsed = parseSingleScheduleSession($part);
            if ($parsed) {
                $sessions[] = $parsed;
            }
        }
        if (empty($sessions)) return null;
        // Return the first session's data for backward compatibility,
        // but also include all sessions in a 'sessions' key
        $first = $sessions[0];
        $first['sessions'] = $sessions;
        return $first;
    }

    return parseSingleScheduleSession($schedule);
}

// Parse a single schedule session string (e.g. "Mon & Wed, 8:00 - 9:30 AM").
// Returns [days => [...], start_minutes => int, end_minutes => int, raw => string]
// or null if the string cannot be parsed.
function parseSingleScheduleSession($schedule) {
    $schedule = trim($schedule);
    if ($schedule === '') return null;

    $normalized = strtolower($schedule);

    // Expand common abbreviations
    $normalized = str_replace(['&', ','], ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);

    // Extract time range
    $timePattern = '/(\d{1,2}:?\d{0,2}\s*(?:AM|PM)?)\s*(?:-|to)\s*(\d{1,2}:?\d{0,2}\s*(?:AM|PM)?)/i';
    if (!preg_match($timePattern, $normalized, $timeMatch)) {
        return null;
    }

    $startMinutes = parseTimeToMinutes($timeMatch[1]);
    $endMinutes   = parseTimeToMinutes($timeMatch[2]);
    if ($startMinutes === null || $endMinutes === null) return null;
    if ($endMinutes <= $startMinutes) return null;

    // Extract day names from the part before the time
    $dayPart = trim(substr($normalized, 0, strpos($normalized, $timeMatch[0])));

    $days = [];
    // Try full names first
    if (preg_match('/(\w+)\s*(?:-|through|to)\s*(\w+)/', $dayPart, $rangeM)) {
        $startDay = $rangeM[1];
        $endDay   = $rangeM[2];
        if (isset(SCHEDULE_DAYS[$startDay]) && isset(SCHEDULE_DAYS[$endDay])) {
            $s = SCHEDULE_DAYS[$startDay];
            $e = SCHEDULE_DAYS[$endDay];
            if ($s <= $e) {
                for ($i = $s; $i <= $e; $i++) {
                    $days[] = $i;
                }
            }
        }
    }

    // Try single-letter/short patterns like "MWF" or "TTh"
    if (empty($days) && preg_match('/^([mtwfs]{2,})$/i', $dayPart)) {
        $letterMap = ['m' => 0, 't' => 1, 'w' => 2, 'h' => 3, 'f' => 4, 's' => 5];
        $expanded = '';
        $i = 0;
        $len = strlen($dayPart);
        while ($i < $len) {
            if ($i + 1 < $len && substr($dayPart, $i, 2) === 'th') {
                $expanded .= 'h';
                $i += 2;
            } else {
                $expanded .= $dayPart[$i];
                $i++;
            }
        }
        $days = [];
        foreach (str_split($expanded) as $ch) {
            if (isset($letterMap[$ch])) {
                $days[] = $letterMap[$ch];
            }
        }
        $days = array_unique($days);
        sort($days);
    }

    // Try individual day names separated by spaces
    if (empty($days)) {
        preg_match_all('/\b(mon|tue|wed|thu|fri|sat|sun|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i', $dayPart, $dayMatches);
        foreach ($dayMatches[1] as $d) {
            $d = strtolower($d);
            if (isset(SCHEDULE_DAYS[$d])) {
                $days[] = SCHEDULE_DAYS[$d];
            }
        }
        $days = array_unique($days);
        sort($days);
    }

    if (empty($days)) return null;

    return [
        'days'           => $days,
        'start_minutes'  => $startMinutes,
        'end_minutes'    => $endMinutes,
        'raw'            => $schedule,
    ];
}

// Check if two time ranges on the same day overlap.
// Both ranges are [start, end) in minutes-from-midnight.
function timesOverlap($start1, $end1, $start2, $end2) {
    return $start1 < $end2 && $start2 < $end1;
}

// Check a proposed schedule against all existing course_sections for conflicts.
// Returns an array of conflict descriptions, or an empty array if no conflicts.
// $excludeId is the section ID to skip (for edits).
function findScheduleConflicts(PDO $db, $termId, $schedule, $excludeId = null) {
    $parsed = parseSchedule($schedule);
    if (!$parsed) return [];

    // Get all sessions to check (handles multi-session schedules)
    $sessionsToCheck = isset($parsed['sessions']) ? $parsed['sessions'] : [$parsed];

    $conflicts = [];

    // Get all active sections for the same term
    $sql = "SELECT cs.id, cs.section_code, cs.schedule, cs.room,
                   cs.instructor_id, cs.block_id,
                   sub.subject_code, sub.subject_name,
                   ins.first_name, ins.last_name
            FROM course_sections cs
            JOIN subjects sub ON cs.subject_id = sub.id
            JOIN instructors ins ON cs.instructor_id = ins.id
            WHERE cs.term_id = :term
              AND cs.schedule IS NOT NULL AND cs.schedule != ''
              AND cs.status NOT IN ('Cancelled', 'Completed')";
    $params = [':term' => $termId];

    if ($excludeId) {
        $sql .= " AND cs.id != :exclude";
        $params[':exclude'] = $excludeId;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $existing = $stmt->fetchAll();

    $dayNameMap = [0=>'Mon',1=>'Tue',2=>'Wed',3=>'Thu',4=>'Fri',5=>'Sat',6=>'Sun'];

    foreach ($existing as $row) {
        $existingParsed = parseSchedule($row['schedule']);
        if (!$existingParsed) continue;

        // Get all sessions from the existing schedule
        $existingSessions = isset($existingParsed['sessions']) ? $existingParsed['sessions'] : [$existingParsed];

        // Check each of our sessions against each existing session
        foreach ($sessionsToCheck as $mySession) {
            foreach ($existingSessions as $theirSession) {
                // Check if any day overlaps
                $commonDays = array_intersect($mySession['days'], $theirSession['days']);
                if (empty($commonDays)) continue;

                // Check if time ranges overlap
                if (!timesOverlap($mySession['start_minutes'], $mySession['end_minutes'],
                                  $theirSession['start_minutes'], $theirSession['end_minutes'])) {
                    continue;
                }

                // We have a conflict
                $sectionLabel = $row['subject_code'] . ' (' . $row['section_code'] . ')';
                $dayNames = [];
                foreach ($commonDays as $d) { $dayNames[] = $dayNameMap[$d]; }
                $dayStr = implode(', ', $dayNames);
                $existingTimeStr = formatMinutes($theirSession['start_minutes']) . ' - ' . formatMinutes($theirSession['end_minutes']);

                // Room conflict
                if ($row['room'] && isset($_POST['room']) && $row['room'] === $_POST['room'] && $_POST['room'] !== '') {
                    $conflicts[] = [
                        'type'    => 'room',
                        'message' => 'Room "' . htmlspecialchars($_POST['room']) . '" is already booked by ' . $sectionLabel . ' (' . $row['first_name'] . ' ' . $row['last_name'] . ') on ' . $dayStr . ' ' . $existingTimeStr,
                    ];
                }

                // Instructor conflict
                if ($row['instructor_id'] == ($_POST['instructor_id'] ?? 0)) {
                    $conflicts[] = [
                        'type'    => 'instructor',
                        'message' => 'Instructor ' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . ' is already teaching ' . $sectionLabel . ' on ' . $dayStr . ' ' . $existingTimeStr,
                    ];
                }

                // Class block conflict
                if ($row['block_id'] && $row['block_id'] == ($_POST['block_id'] ?? 0) && ($_POST['block_id'] ?? 0) > 0) {
                    $conflicts[] = [
                        'type'    => 'block',
                        'message' => 'Class block already has ' . $sectionLabel . ' scheduled on ' . $dayStr . ' ' . $existingTimeStr,
                    ];
                }
            }
        }
    }

    return $conflicts;
}

// Format minutes-from-midnight to a readable time like "8:00 AM".
function formatMinutes($minutes) {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    $suffix = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12;
    if ($h12 === 0) $h12 = 12;
    return $h12 . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ' ' . $suffix;
}

// Get a human-readable label for the days in a parsed schedule.
function scheduleDayLabel($days) {
    $dayNames = [0=>'Mon',1=>'Tue',2=>'Wed',3=>'Thu',4=>'Fri',5=>'Sat',6=>'Sun'];
    $names = [];
    foreach ($days as $d) {
        $names[] = $dayNames[$d] ?? '?';
    }
    return implode(', ', $names);
}
