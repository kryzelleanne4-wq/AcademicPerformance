<?php
/**
 * Subject Detail Page
 */

require_once '../includes/functions.php';

$db = getDB();

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

// Get grades for this subject
$stmt = $db->prepare("
    SELECT g.*, s.first_name, s.last_name, s.student_id as student_number
    FROM grades g
    JOIN students s ON g.student_id = s.id
    WHERE g.subject_id = :subject_id
    ORDER BY g.score DESC
");
$stmt->bindValue(':subject_id', $subjectId, PDO::PARAM_INT);
$stmt->execute();
$grades = $stmt->fetchAll();

include '../includes/header.php';
displayFlash();
?>

<div class="table-container">
    <div class="table-header">
        <h2>📊 Grades for <?php echo $subject['subject_name']; ?></h2>
        <a href="subjects.php" class="btn btn-secondary">← Back to Subjects</a>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Student Name</th>
                <th>Semester</th>
                <th>Year</th>
                <th>Score</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grades as $grade): ?>
            <tr>
                <td><?php echo $grade['student_number']; ?></td>
                <td><?php echo $grade['first_name'] . ' ' . $grade['last_name']; ?></td>
                <td><?php echo $grade['semester']; ?></td>
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
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
