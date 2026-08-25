<?php
/**
 * My Subjects Page
 */

require_once '../includes/functions.php';

$pageTitle = 'My Subjects';

$db = getDB();

include '../includes/header.php';
displayFlash();
?>

<div class="subjects-header">
    <h2>📚 My Subjects</h2>
</div>

<div class="subjects-grid">
    <?php
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
            <a href="subject-detail.php?id=<?php echo $subject['id']; ?>" class="subject-card-link">GO TO COURSE</a>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<?php include '../includes/footer.php'; ?>
