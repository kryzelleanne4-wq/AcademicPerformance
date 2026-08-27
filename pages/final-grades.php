<?php
/**
 * Final Grades Page
 */

require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'My Final Grades';

$db = getDB();
$user = currentUser();

// Students only see their own summary.
$where = '';
$params = [];
if ($user['role'] === 'student') {
    $student = currentStudent();
    $where = 'WHERE s.id = :sid';
    $params[':sid'] = $student['id'];
}

// Get final grades summary
$stmt = $db->prepare("
    SELECT 
        s.id,
        s.student_id as student_number,
        s.first_name,
        s.last_name,
        COUNT(DISTINCT g.subject_id) as total_subjects,
        ROUND(AVG(g.score), 2) as final_average,
        CASE
            WHEN AVG(g.score) >= 96 THEN '1.00'
            WHEN AVG(g.score) >= 93 THEN '1.25'
            WHEN AVG(g.score) >= 90 THEN '1.50'
            WHEN AVG(g.score) >= 88 THEN '1.75'
            WHEN AVG(g.score) >= 85 THEN '2.00'
            WHEN AVG(g.score) >= 83 THEN '2.25'
            WHEN AVG(g.score) >= 80 THEN '2.50'
            WHEN AVG(g.score) >= 78 THEN '2.75'
            WHEN AVG(g.score) >= 75 THEN '3.00'
            ELSE '5.00'
        END as final_grade,
        SUM(sub.credits) as total_credits
    FROM students s
    LEFT JOIN grades g ON s.id = g.student_id
    LEFT JOIN subjects sub ON g.subject_id = sub.id
    $where
    GROUP BY s.id
    HAVING total_subjects > 0
    ORDER BY final_average DESC
");
$stmt->execute($params);
$finalGrades = $stmt->fetchAll();

include '../includes/header.php';
displayFlash();
?>

<div class="table-container">
    <div class="table-header">
        <h2><?php echo icon('trophy', 24); ?> Final Grades</h2>
    </div>
    
    <table data-pagination data-page-size="8">
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Subjects</th>
                <th>Credits</th>
                <th>Final Average</th>
                <th>Final Grade</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($finalGrades as $row): ?>
            <tr>
                <td data-label="Student ID"><?php echo $row['student_number']; ?></td>
                <td data-label="Name"><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                <td data-label="Subjects"><?php echo $row['total_subjects']; ?></td>
                <td data-label="Credits"><?php echo $row['total_credits']; ?></td>
                <td data-label="Final Average"><?php echo $row['final_average']; ?></td>
                <td data-label="Final Grade">
                    <span class="grade-badge <?php echo gradeBadgeClass($row['final_grade']); ?>" title="<?php echo htmlspecialchars(gradeDescriptor($row['final_grade'])); ?>">
                        <?php echo htmlspecialchars(formatGrade($row['final_grade'])); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
