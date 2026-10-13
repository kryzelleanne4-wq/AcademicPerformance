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
               cs.id AS section_id, cs.section_code, cs.schedule, cs.room,
               r.floor AS room_floor,
               d.department_code, d.department_name,
               ins.first_name, ins.last_name
        FROM enrollments e
        JOIN course_sections cs ON e.section_id = cs.id
        JOIN subjects s ON cs.subject_id = s.id
        JOIN instructors ins ON cs.instructor_id = ins.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN rooms r ON r.room_code = cs.room
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
               cs.id AS section_id, cs.section_code, cs.schedule, cs.room,
               r.floor AS room_floor,
               d.department_code, d.department_name,
               ins.first_name, ins.last_name
        FROM course_sections cs
        JOIN subjects s ON cs.subject_id = s.id
        JOIN instructors ins ON cs.instructor_id = ins.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN rooms r ON r.room_code = cs.room
        WHERE cs.instructor_id = :iid
        ORDER BY s.subject_name
    ");
    $stmt->execute([':iid' => $instructor['id']]);
    $subjects = $stmt->fetchAll();
} else {
    $stmt = $db->query("
        SELECT s.id, s.subject_code, s.subject_name,
               $iconClass AS icon_class,
               NULL AS section_id, NULL AS section_code, NULL AS schedule,
               NULL AS room, NULL AS room_floor,
               d.department_code, d.department_name,
               NULL AS first_name, NULL AS last_name
        FROM subjects s
        LEFT JOIN departments d ON s.department_id = d.id
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
        <?php include '../includes/subject-card.php'; ?>
    <?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>
