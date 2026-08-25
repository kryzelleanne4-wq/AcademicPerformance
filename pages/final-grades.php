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
            WHEN AVG(g.score) >= 90 THEN 'A'
            WHEN AVG(g.score) >= 80 THEN 'B'
            WHEN AVG(g.score) >= 70 THEN 'C'
            WHEN AVG(g.score) >= 60 THEN 'D'
            ELSE 'F'
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
    
    <table>
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
                    <?php $gradeClass = 'grade-' . strtolower($row['final_grade']); ?>
                    <span class="grade-badge <?php echo $gradeClass; ?>">
                        <?php echo $row['final_grade']; ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
