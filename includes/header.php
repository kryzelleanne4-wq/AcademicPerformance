<?php
/**
 * Common Header with Sidebar
 */

require_once __DIR__ . '/functions.php';
requireLogin();

// Set current page for active menu
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$assetPrefix = $currentPage === 'index' ? '' : '../';

$user = currentUser();
$role = $user['role'];
$fullName = $user['full_name'];
$roleLabel = roleLabel($role);
$avatarLetter = strtoupper(substr($fullName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Student Performance'; ?></title>
    <link rel="stylesheet" href="<?php echo $assetPrefix; ?>assets/css/style.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>
                <span class="red">ACADEMIC</span><br>
                <span class="white">EXCELLENCE</span>
            </h1>
            <span class="portal-label"><?php echo strtoupper($roleLabel); ?> PORTAL</span>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-category">Main Menu</div>
            <a href="<?php echo $assetPrefix; ?>index.php" class="menu-item <?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                <span class="icon">📊</span>
                Dashboard
            </a>

            <?php if ($role === 'admin'): ?>
                <div class="menu-category">Administration</div>
                <a href="<?php echo $assetPrefix; ?>pages/users.php" class="menu-item <?php echo $currentPage === 'users' ? 'active' : ''; ?>">
                    <span class="icon">👥</span>
                    Users
                </a>
                <a href="<?php echo $assetPrefix; ?>pages/students.php" class="menu-item <?php echo $currentPage === 'students' ? 'active' : ''; ?>">
                    <span class="icon">👤</span>
                    Students
                </a>
                <a href="<?php echo $assetPrefix; ?>pages/departments.php" class="menu-item <?php echo $currentPage === 'departments' ? 'active' : ''; ?>">
                    <span class="icon">🏛️</span>
                    Departments
                </a>
                <a href="<?php echo $assetPrefix; ?>pages/manage-subjects.php" class="menu-item <?php echo $currentPage === 'manage-subjects' ? 'active' : ''; ?>">
                    <span class="icon">📚</span>
                    Courses / Subjects
                </a>
                <a href="<?php echo $assetPrefix; ?>pages/schedules.php" class="menu-item <?php echo $currentPage === 'schedules' ? 'active' : ''; ?>">
                    <span class="icon">🗓️</span>
                    Schedules
                </a>
                <a href="<?php echo $assetPrefix; ?>pages/enrollments.php" class="menu-item <?php echo $currentPage === 'enrollments' ? 'active' : ''; ?>">
                    <span class="icon">📋</span>
                    Enrollments
                </a>

                <div class="menu-category">Academic Records</div>
                <a href="<?php echo $assetPrefix; ?>pages/attendance.php" class="menu-item <?php echo $currentPage === 'attendance' ? 'active' : ''; ?>">
                    <span class="icon">✅</span>
                    Attendance
                </a>
                <a href="<?php echo $assetPrefix; ?>pages/grades.php" class="menu-item <?php echo $currentPage === 'grades' ? 'active' : ''; ?>">
                    <span class="icon">✏️</span>
                    Grades
                </a>
                <a href="<?php echo $assetPrefix; ?>pages/reports.php" class="menu-item <?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
                    <span class="icon">📈</span>
                    Reports
                </a>

            <?php elseif ($role === 'instructor'): ?>
                <div class="menu-category">Teaching</div>
                <a href="<?php echo $assetPrefix; ?>pages/subjects.php" class="menu-item <?php echo $currentPage === 'subjects' ? 'active' : ''; ?>">
                    <span class="icon">📚</span>
                    My Subjects
                </a>
                <a href="<?php echo $assetPrefix; ?>pages/attendance.php" class="menu-item <?php echo $currentPage === 'attendance' ? 'active' : ''; ?>">
                    <span class="icon">✅</span>
                    Attendance
                </a>
                <a href="<?php echo $assetPrefix; ?>pages/grades.php" class="menu-item <?php echo $currentPage === 'grades' ? 'active' : ''; ?>">
                    <span class="icon">✏️</span>
                    Grades
                </a>

            <?php else: ?>
                <div class="menu-category">My Records</div>
                <a href="<?php echo $assetPrefix; ?>pages/my-grades.php" class="menu-item <?php echo $currentPage === 'my-grades' ? 'active' : ''; ?>">
                    <span class="icon">📝</span>
                    My Grades
                </a>
                <a href="<?php echo $assetPrefix; ?>pages/subjects.php" class="menu-item <?php echo $currentPage === 'subjects' ? 'active' : ''; ?>">
                    <span class="icon">📚</span>
                    My Subjects
                </a>
                <a href="<?php echo $assetPrefix; ?>pages/my-attendance.php" class="menu-item <?php echo $currentPage === 'my-attendance' ? 'active' : ''; ?>">
                    <span class="icon">✅</span>
                    My Attendance
                </a>
                <a href="<?php echo $assetPrefix; ?>pages/final-grades.php" class="menu-item <?php echo $currentPage === 'final-grades' ? 'active' : ''; ?>">
                    <span class="icon">🏆</span>
                    My Final Grades
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <button class="menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false">☰</button>
            <h1 class="page-title"><?php echo $pageTitle ?? 'Dashboard'; ?></h1>
            <div class="user-info">
                <div class="user-meta">
                    <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                    <span class="user-role"><?php echo $roleLabel; ?></span>
                </div>
                <div class="user-avatar"><?php echo $avatarLetter; ?></div>
                <a href="<?php echo $assetPrefix; ?>pages/change-password.php" class="btn btn-secondary btn-sm">Change Password</a>
                <a href="<?php echo $assetPrefix; ?>logout.php" class="btn btn-danger btn-sm">Logout</a>
            </div>
        </header>

        <!-- Page Content -->
        <div class="page-content">
