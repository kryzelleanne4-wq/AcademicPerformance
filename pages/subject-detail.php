<?php
/**
 * Subject Detail Page
 * Students see their own grades for the subject; admins and instructors
 * see every student's grades for that subject.
 */

require_once '../includes/functions.php';
requireLogin();

$db = getDB();
$user = currentUser();

$subjectId = intval($_GET['id'] ?? 0);

if (!$subjectId) {
    header('Location: subjects.php');
    exit();
}

// Get subject info
$stmt = $db->prepare("SELECT * FROM subjects WHERE id = :id");
$stmt->bindValue(':id', $subjectId, PDO::PARAM_INT);
$stmt->execute();
$subject = $stmt->fetch();

if (!$subject) {
    header('Location: subjects.php');
    exit();
}

$pageTitle = $subject['subject_name'];

// Get grades for this subject, scoped to the logged-in student when applicable.
if ($user['role'] === 'student') {
    $student = currentStudent();
    $stmt = $db->prepare("
        SELECT g.*, s.first_name, s.last_name, s.student_id as student_number, cs.section_code
        FROM grades g
        JOIN students s ON g.student_id = s.id
        LEFT JOIN course_sections cs ON g.section_id = cs.id
        WHERE g.subject_id = :subject_id AND g.student_id = :sid
        ORDER BY g.year DESC, g.semester DESC
    ");
    $stmt->bindValue(':subject_id', $subjectId, PDO::PARAM_INT);
    $stmt->bindValue(':sid', $student['id'], PDO::PARAM_INT);
} else {
    $stmt = $db->prepare("
        SELECT g.*, s.first_name, s.last_name, s.student_id as student_number, cs.section_code
        FROM grades g
        JOIN students s ON g.student_id = s.id
        LEFT JOIN course_sections cs ON g.section_id = cs.id
        WHERE g.subject_id = :subject_id
        ORDER BY g.score DESC
    ");
    $stmt->bindValue(':subject_id', $subjectId, PDO::PARAM_INT);
}
$stmt->execute();
$grades = $stmt->fetchAll();

include '../includes/header.php';
displayFlash();
?>

<div class="table-container">
    <div class="table-header">
        <h2><?php echo icon('bar-chart', 24); ?> Grades for <?php echo htmlspecialchars($subject['subject_name']); ?></h2>
        <a href="subjects.php" class="btn btn-secondary">← Back to Subjects</a>
    </div>

    <table>
        <thead>
            <tr>
                <?php if ($user['role'] !== 'student'): ?>
                <th>Student ID</th>
                <th>Student Name</th>
                <?php endif; ?>
                <th>Section</th>
                <th>Semester</th>
                <th>Year</th>
                <th>Score</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grades as $grade): ?>
            <tr>
                <?php if ($user['role'] !== 'student'): ?>
                <td data-label="Student ID"><?php echo htmlspecialchars($grade['student_number']); ?></td>
                <td data-label="Student Name"><?php echo htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']); ?></td>
                <?php endif; ?>
                <td data-label="Section"><?php echo htmlspecialchars($grade['section_code'] ?? '—'); ?></td>
                <td data-label="Semester"><?php echo htmlspecialchars($grade['semester']); ?></td>
                <td data-label="Year"><?php echo $grade['year']; ?></td>
                <td data-label="Score"><?php echo $grade['score']; ?></td>
                <td data-label="Grade">
                    <?php
                    $gradeClass = 'grade-' . strtolower($grade['grade']);
                    ?>
                    <span class="grade-badge <?php echo $gradeClass; ?>">
                        <?php echo $grade['grade']; ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
