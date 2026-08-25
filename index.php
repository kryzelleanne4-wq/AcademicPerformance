<?php
/**
 * Dashboard - Main Entry Point (role-aware)
 */

require_once 'includes/functions.php';
requireLogin();

$pageTitle = 'Dashboard';

$db = getDB();
$user = currentUser();
$role = $user['role'];

// Stats per role.
$stats = [];
if ($role === 'admin') {
    $stats = [
        ['👤', querySingle("SELECT COUNT(*) FROM students"), 'Total Students'],
        ['🧑‍🏫', querySingle("SELECT COUNT(*) FROM instructors"), 'Total Instructors'],
        ['📚', querySingle("SELECT COUNT(*) FROM subjects WHERE is_active = 1"), 'Active Subjects'],
        ['📋', querySingle("SELECT COUNT(*) FROM enrollments WHERE status = 'Enrolled'"), 'Active Enrollments']
    ];
} elseif ($role === 'instructor') {
    $instructor = currentInstructor();
    $stats = [
        ['🗓️', querySingle("SELECT COUNT(*) FROM course_sections WHERE instructor_id = " . (int) $instructor['id']), 'My Sections'],
        ['👥', querySingle("
            SELECT COUNT(DISTINCT e.student_id)
            FROM enrollments e
            JOIN course_sections cs ON e.section_id = cs.id
            WHERE cs.instructor_id = " . (int) $instructor['id'] . " AND e.status = 'Enrolled'
        "), 'My Students'],
        ['✏️', querySingle("SELECT COUNT(*) FROM grades WHERE instructor_id = " . (int) $instructor['id']), 'Grades Recorded'],
        ['✅', querySingle("
            SELECT COUNT(*)
            FROM attendance a
            JOIN course_sections cs ON a.section_id = cs.id
            WHERE cs.instructor_id = " . (int) $instructor['id'] . " AND a.attendance_date = date('now')
        "), "Today's Attendance"]
    ];
} else {
    $student = currentStudent();
    $stats = [
        ['📚', querySingle("
            SELECT COUNT(*) FROM enrollments WHERE student_id = " . (int) $student['id'] . " AND status = 'Enrolled'
        "), 'My Subjects'],
        ['📝', querySingle("SELECT COUNT(*) FROM grades WHERE student_id = " . (int) $student['id']), 'Grades Recorded'],
        ['🎯', querySingle("
            SELECT ROUND(AVG(score), 2) FROM grades WHERE student_id = " . (int) $student['id']
        ) ?: '—', 'Average Score'],
        ['✅', querySingle("
            SELECT COUNT(*) FROM attendance WHERE student_id = " . (int) $student['id'] . " AND status = 'Present'
        "), 'Days Present']
    ];
}

// Subjects shown on the dashboard.
$iconClass = "
    CASE
        WHEN s.subject_code LIKE 'MATH%' THEN 'math'
        WHEN s.subject_code LIKE 'ENG%' THEN 'english'
        WHEN s.subject_code LIKE 'BIO%' THEN 'biology'
        WHEN s.subject_code LIKE 'CS%' OR s.subject_code LIKE 'PROG%' THEN 'programming'
        ELSE 'science'
    END
";
$iconEmoji = "
    CASE
        WHEN s.subject_code LIKE 'MATH%' THEN '📐'
        WHEN s.subject_code LIKE 'ENG%' THEN '📖'
        WHEN s.subject_code LIKE 'BIO%' THEN '🧬'
        WHEN s.subject_code LIKE 'CS%' OR s.subject_code LIKE 'PROG%' THEN '💻'
        ELSE '🔬'
    END
";

$subjects = [];
if ($role === 'student') {
    $stmt = $db->prepare("
        SELECT s.subject_name, s.subject_code, cs.section_code, cs.schedule,
               ins.first_name, ins.last_name,
               $iconClass AS icon_class, $iconEmoji AS icon_emoji, s.id
        FROM enrollments e
        JOIN course_sections cs ON e.section_id = cs.id
        JOIN subjects s ON cs.subject_id = s.id
        JOIN instructors ins ON cs.instructor_id = ins.id
        WHERE e.student_id = :sid AND e.status = 'Enrolled'
        ORDER BY s.subject_name
    ");
    $stmt->execute([':sid' => $student['id']]);
    $subjects = $stmt->fetchAll();
} elseif ($role === 'instructor') {
    $instructor = currentInstructor();
    $stmt = $db->prepare("
        SELECT s.subject_name, s.subject_code, cs.section_code, cs.schedule,
               ins.first_name, ins.last_name,
               $iconClass AS icon_class, $iconEmoji AS icon_emoji, s.id
        FROM course_sections cs
        JOIN subjects s ON cs.subject_id = s.id
        JOIN instructors ins ON cs.instructor_id = ins.id
        WHERE cs.instructor_id = :iid
        ORDER BY s.subject_name
    ");
    $stmt->execute([':iid' => $instructor['id']]);
    $subjects = $stmt->fetchAll();
} else {
    $subjects = $db->query("
        SELECT s.subject_name, s.subject_code, NULL AS section_code, NULL AS schedule,
               NULL AS first_name, NULL AS last_name,
               $iconClass AS icon_class, $iconEmoji AS icon_emoji, s.id
        FROM subjects s WHERE s.is_active = 1
        ORDER BY s.subject_name
    ")->fetchAll();
}

include 'includes/header.php';
displayFlash();
?>

<!-- Stats Cards -->
<div class="dashboard-stats">
    <?php foreach ($stats as $stat): ?>
    <div class="stat-card">
        <div class="stat-icon"><?php echo $stat[0]; ?></div>
        <div class="stat-info">
            <h3><?php echo $stat[1]; ?></h3>
            <p><?php echo $stat[2]; ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- My Subjects Section -->
<div class="subjects-header">
    <h2><?php echo $role === 'admin' ? 'Course Catalog' : 'My Subjects'; ?></h2>
</div>

<div class="subjects-grid">
    <?php if (empty($subjects)): ?>
        <p style="color: var(--ink-muted);">No subjects available yet.</p>
    <?php endif; ?>

    <?php foreach ($subjects as $subject): ?>
    <div class="subject-card">
        <div class="subject-card-icon <?php echo $subject['icon_class']; ?>">
            <?php echo $subject['icon_emoji']; ?>
        </div>
        <div class="subject-card-body">
            <h3 class="subject-card-title"><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
            <p class="subject-card-section">
                <?php echo htmlspecialchars($subject['subject_code']); ?>
                <?php if ($subject['section_code']): ?>
                    &middot; Section <?php echo htmlspecialchars($subject['section_code']); ?>
                <?php endif; ?>
            </p>
            <p class="subject-card-professor">
                <?php if ($subject['first_name']): ?>
                    <?php echo htmlspecialchars('Prof. ' . $subject['first_name'] . ' ' . $subject['last_name']); ?>
                    <?php if ($subject['schedule']): ?>
                        <br><?php echo htmlspecialchars($subject['schedule']); ?>
                    <?php endif; ?>
                <?php else: ?>
                    Instructor assigned via schedule
                <?php endif; ?>
            </p>
            <a href="pages/subject-detail.php?id=<?php echo $subject['id']; ?>" class="subject-card-link">GO TO COURSE</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>
