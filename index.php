<?php
/**
 * Dashboard - Main Entry Point
 */

require_once 'includes/functions.php';

$pageTitle = 'Dashboard';

$db = getDB();

// Get statistics (using querySingle helper)
$studentCount = querySingle("SELECT COUNT(*) FROM students");
$subjectCount = querySingle("SELECT COUNT(*) FROM subjects");
$gradeCount = querySingle("SELECT COUNT(*) FROM grades");
$activeStudents = querySingle("SELECT COUNT(*) FROM students WHERE status = 'Active'");

include 'includes/header.php';
displayFlash();
?>

<!-- Stats Cards -->
<div class="dashboard-stats">
    <div class="stat-card">
        <div class="stat-icon red">👤</div>
        <div class="stat-info">
            <h3><?php echo $studentCount; ?></h3>
            <p>Total Students</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon blue">✓</div>
        <div class="stat-info">
            <h3><?php echo $activeStudents; ?></h3>
            <p>Active Students</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon green">📚</div>
        <div class="stat-info">
            <h3><?php echo $subjectCount; ?></h3>
            <p>Total Subjects</p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon purple">📝</div>
        <div class="stat-info">
            <h3><?php echo $gradeCount; ?></h3>
            <p>Grades Recorded</p>
        </div>
    </div>
</div>

<!-- My Subjects Section -->
<div class="subjects-header">
    <h2>My Subjects</h2>
</div>

<div class="subjects-grid">
    <?php
    // Get all subjects with professor info
    $stmt = $db->query("
        SELECT s.*, 
               CASE 
                   WHEN s.subject_code LIKE 'MATH%' THEN 'math'
                   WHEN s.subject_code LIKE 'ENG%' THEN 'english'
                   WHEN s.subject_code LIKE 'BIO%' THEN 'biology'
                   WHEN s.subject_code LIKE 'CS%' OR s.subject_code LIKE 'PROG%' THEN 'programming'
                   ELSE 'science'
               END as icon_class,
               CASE 
                   WHEN s.subject_code LIKE 'MATH%' THEN '📐'
                   WHEN s.subject_code LIKE 'ENG%' THEN '📖'
                   WHEN s.subject_code LIKE 'BIO%' THEN '🧬'
                   WHEN s.subject_code LIKE 'CS%' OR s.subject_code LIKE 'PROG%' THEN '💻'
                   ELSE '🔬'
               END as icon_emoji
        FROM subjects s
        ORDER BY s.subject_name
    ");
    
    while ($subject = $stmt->fetch()):
    ?>
    <div class="subject-card">
        <div class="subject-card-icon <?php echo $subject['icon_class']; ?>">
            <?php echo $subject['icon_emoji']; ?>
        </div>
        <div class="subject-card-body">
            <h3 class="subject-card-title"><?php echo $subject['subject_name']; ?></h3>
            <p class="subject-card-section">Grade 10 - Section A</p>
            <p class="subject-card-professor">Prof. Juan Dela Cruz</p>
            <a href="pages/subject-detail.php?id=<?php echo $subject['id']; ?>" class="subject-card-link">GO TO COURSE</a>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<?php include 'includes/footer.php'; ?>
