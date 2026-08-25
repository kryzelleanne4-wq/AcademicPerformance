<?php
/**
 * My Subjects Page
 * Students see their enrolled subjects, instructors see the subjects they
 * teach, admins see the full course catalog.
 */

require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'My Subjects';

$db = getDB();
$user = currentUser();

$iconClass = "
    CASE
        WHEN s.subject_code LIKE 'MATH%' THEN 'math'
        WHEN s.subject_code LIKE 'ENG%' THEN 'english'
        WHEN s.subject_code LIKE 'BIO%' THEN 'biology'
        WHEN s.subject_code LIKE 'CS%' OR s.subject_code LIKE 'PROG%' THEN 'programming'
        ELSE 'science'
    END
";

$subjects = [];

if ($user['role'] === 'student') {
    $student = currentStudent();
    $stmt = $db->prepare("
        SELECT s.id, s.subject_code, s.subject_name,
               $iconClass AS icon_class,
               cs.section_code, cs.schedule,
               ins.first_name, ins.last_name
        FROM enrollments e
        JOIN course_sections cs ON e.section_id = cs.id
        JOIN subjects s ON cs.subject_id = s.id
        JOIN instructors ins ON cs.instructor_id = ins.id
        WHERE e.student_id = :sid AND e.status = 'Enrolled'
        ORDER BY s.subject_name
    ");
    $stmt->execute([':sid' => $student['id']]);
    $subjects = $stmt->fetchAll();
} elseif ($user['role'] === 'instructor') {
    $instructor = currentInstructor();
    $stmt = $db->prepare("
        SELECT s.id, s.subject_code, s.subject_name,
               $iconClass AS icon_class,
               cs.section_code, cs.schedule,
               ins.first_name, ins.last_name
        FROM course_sections cs
        JOIN subjects s ON cs.subject_id = s.id
        JOIN instructors ins ON cs.instructor_id = ins.id
        WHERE cs.instructor_id = :iid
        ORDER BY s.subject_name
    ");
    $stmt->execute([':iid' => $instructor['id']]);
    $subjects = $stmt->fetchAll();
} else {
    $stmt = $db->query("
        SELECT s.id, s.subject_code, s.subject_name,
               $iconClass AS icon_class,
               NULL AS section_code, NULL AS schedule,
               NULL AS first_name, NULL AS last_name
        FROM subjects s
        ORDER BY s.subject_name
    ");
    $subjects = $stmt->fetchAll();
}

include '../includes/header.php';
displayFlash();
?>

<div class="subjects-header">
    <h2><?php echo icon('book-open', 24); ?> <?php echo $user['role'] === 'admin' ? 'Course Catalog' : 'My Subjects'; ?></h2>
</div>

<div class="subjects-grid">
    <?php if (empty($subjects)): ?>
        <p style="color: var(--ink-muted);">No subjects available yet.</p>
    <?php endif; ?>

    <?php foreach ($subjects as $subject): ?>
    <div class="subject-card">
        <div class="subject-card-icon <?php echo $subject['icon_class']; ?>">
            <?php echo icon(subjectIcon($subject['icon_class']), 40); ?>
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
            <a href="subject-detail.php?id=<?php echo $subject['id']; ?>" class="subject-card-link">GO TO COURSE</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>
