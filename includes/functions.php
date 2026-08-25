<?php
/**
 * Common Helper Functions
 */

require_once __DIR__ . '/../config/database.php';

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
