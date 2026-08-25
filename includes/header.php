<?php
/**
 * Common Header with Sidebar
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set current page for active menu
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Student Performance'; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>
                <span class="red">STUDENT</span><br>
                <span class="white">ACADEMIC</span><br>
                <span class="red">PERFORMANCE</span>
            </h1>
        </div>
        
        <nav class="sidebar-menu">
            <div class="menu-category">Main Menu</div>
            <a href="../index.php" class="menu-item <?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                <span class="icon">📊</span>
                Dashboard
            </a>
            
            <div class="menu-category">My Grades</div>
            <a href="../pages/my-grades.php" class="menu-item <?php echo $currentPage === 'my-grades' ? 'active' : ''; ?>">
                <span class="icon">📝</span>
                My Grades
            </a>
            
            <div class="menu-category">Performance</div>
            <a href="../pages/subjects.php" class="menu-item <?php echo $currentPage === 'subjects' ? 'active' : ''; ?>">
                <span class="icon">📚</span>
                My Subjects
            </a>
            <a href="../pages/performance.php" class="menu-item <?php echo $currentPage === 'performance' ? 'active' : ''; ?>">
                <span class="icon">📈</span>
                My Performances
            </a>
            <a href="../pages/final-grades.php" class="menu-item <?php echo $currentPage === 'final-grades' ? 'active' : ''; ?>">
                <span class="icon">🏆</span>
                My Final Grades
            </a>
            
            <div class="menu-category">Management</div>
            <a href="../pages/students.php" class="menu-item <?php echo $currentPage === 'students' ? 'active' : ''; ?>">
                <span class="icon">👤</span>
                Students
            </a>
            <a href="../pages/grades.php" class="menu-item <?php echo $currentPage === 'grades' ? 'active' : ''; ?>">
                <span class="icon">✏️</span>
                Add Grades
            </a>
            <a href="../pages/reports.php" class="menu-item <?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
                <span class="icon">📋</span>
                Reports
            </a>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <h1 class="page-title"><?php echo $pageTitle ?? 'Dashboard'; ?></h1>
            <div class="user-info">
                <span class="user-name">Welcome, Admin</span>
                <div class="user-avatar">A</div>
            </div>
        </header>
        
        <!-- Page Content -->
        <div class="page-content">
