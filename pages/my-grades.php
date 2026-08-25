<?php
/**
 * My Grades Page (Student)
 * Students view their own grades.
 */

require_once '../includes/functions.php';
requireRole('student');

$pageTitle = 'My Grades';

$db = getDB();
$student = currentStudent();

// Excel export of the same table shown on screen.
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $stmt = $db->prepare("
        SELECT g.*, sub.subject_code, sub.subject_name, sub.credits
        FROM grades g
        JOIN subjects sub ON g.subject_id = sub.id
        WHERE g.student_id = :sid
        ORDER BY g.year DESC, g.semester DESC, sub.subject_name
    ");
    $stmt->execute([':sid' => $student['id']]);
    exportExcel('my-grades', [
        'Subject Code', 'Subject Name', 'Semester', 'Year', 'Score', 'Grade', 'Credits'
    ], pickColumns($stmt->fetchAll(), [
        'subject_code', 'subject_name', 'semester', 'year', 'score', 'grade', 'credits'
    ]));
}

// Get grades for the logged-in student only.
$stmt = $db->prepare("
    SELECT g.*,
           sub.subject_code,
           sub.subject_name,
           sub.credits,
           cs.section_code
    FROM grades g
    JOIN subjects sub ON g.subject_id = sub.id
    LEFT JOIN course_sections cs ON g.section_id = cs.id
    WHERE g.student_id = :sid
    ORDER BY g.year DESC, g.semester DESC, sub.subject_name
");
$stmt->execute([':sid' => $student['id']]);
$grades = $stmt->fetchAll();

include '../includes/header.php';
displayFlash();
?>

<div class="table-container">
    <div class="table-header">
        <h2>📝 My Grades</h2>
        <a href="?export=excel" class="btn btn-secondary btn-sm">⬇ Export to Excel</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Subject Code</th>
                <th>Subject Name</th>
                <th>Section</th>
                <th>Semester</th>
                <th>Year</th>
                <th>Score</th>
                <th>Grade</th>
                <th>Credits</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grades as $grade): ?>
            <tr>
                <td><?php echo htmlspecialchars($grade['subject_code']); ?></td>
                <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                <td><?php echo htmlspecialchars($grade['section_code'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($grade['semester']); ?></td>
                <td><?php echo $grade['year']; ?></td>
                <td><?php echo $grade['score']; ?></td>
                <td>
                    <?php
                    $gradeClass = 'grade-' . strtolower($grade['grade']);
                    ?>
                    <span class="grade-badge <?php echo $gradeClass; ?>">
                        <?php echo $grade['grade']; ?>
                    </span>
                </td>
                <td><?php echo $grade['credits']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
