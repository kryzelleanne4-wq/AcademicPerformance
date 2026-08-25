<?php
/**
 * Authentication & Role-Based Access Control
 *
 * Roles: admin, instructor, student
 */

require_once __DIR__ . '/../config/database.php';

// Start the session safely (used before any output is sent).
function startSession() {
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
}

// Current logged-in user record (or null).
function currentUser() {
    startSession();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = false;
    if ($user === false) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id AND is_active = 1");
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function isLoggedIn() {
    return currentUser() !== null;
}

// Relative base so redirects/links work from both root and pages/.
function baseUrl() {
    $dir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    if ($dir === '/' || $dir === '\\' || $dir === '') {
        return '';
    }
    return str_repeat('../', substr_count(trim($dir, '/'), '/'));
}

// Redirect to the login page when not authenticated.
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . baseUrl() . 'login.php');
        exit();
    }
}

// Redirect to the dashboard when the role is not allowed.
function requireRole(...$roles) {
    requireLogin();
    $user = currentUser();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        $dir = baseUrl();
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<title>Access Denied</title>'
            . '<link rel="stylesheet" href="' . $dir . 'assets/css/style.css"></head><body>'
            . '<div style="max-width:480px;margin:120px auto;padding:32px;border:1px solid var(--outline-soft);border-radius:8px;background:var(--surface-white);text-align:center;">'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#ba1a1a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'
            . '<h1 style="margin:16px 0 8px;color:var(--ink);">Access Denied</h1>'
            . '<p style="color:var(--ink-muted);margin-bottom:24px;">You do not have permission to view this page.</p>'
            . '<a class="btn btn-primary" href="' . $dir . 'index.php">Back to Dashboard</a>'
            . '</div></body></html>';
        exit();
    }
}

// Student record linked to the logged-in user (students only).
function currentStudent() {
    $user = currentUser();
    if (!$user || $user['role'] !== 'student') {
        return null;
    }
    static $student = false;
    if ($student === false) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $user['id']]);
        $student = $stmt->fetch() ?: null;
    }
    return $student;
}

// Instructor record linked to the logged-in user (instructors only).
function currentInstructor() {
    $user = currentUser();
    if (!$user || $user['role'] !== 'instructor') {
        return null;
    }
    static $instructor = false;
    if ($instructor === false) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM instructors WHERE user_id = :uid LIMIT 1");
        $stmt->execute([':uid' => $user['id']]);
        $instructor = $stmt->fetch() ?: null;
    }
    return $instructor;
}

// Human-friendly role label.
function roleLabel($role) {
    return [
        'admin'      => 'Admin',
        'instructor' => 'Teacher',
        'student'    => 'Student'
    ][$role] ?? ucfirst((string) $role);
}
