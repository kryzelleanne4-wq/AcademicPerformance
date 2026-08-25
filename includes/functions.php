<?php
/**
 * Common Helper Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Calculate letter grade from score
function calculateGrade($score) {
    if ($score >= 90) return 'A';
    if ($score >= 80) return 'B';
    if ($score >= 70) return 'C';
    if ($score >= 60) return 'D';
    return 'F';
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
