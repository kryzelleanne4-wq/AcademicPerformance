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
