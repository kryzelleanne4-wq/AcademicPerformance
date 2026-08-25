<?php
/**
 * My Performance Page
 */

require_once '../includes/functions.php';
requireLogin();

$pageTitle = 'My Performance';

$db = getDB();
$user = currentUser();

// Students only see their own performance summary.
$where = '';
$params = [];
if ($user['role'] === 'student') {
    $student = currentStudent();
    $where = 'WHERE s.id = :sid';
    $params[':sid'] = $student['id'];
}

// Get average grades per student
$stmt = $db->prepare("
    SELECT 
        s.id,
        s.student_id as student_number,
        s.first_name,
        s.last_name,
        COUNT(g.id) as total_subjects,
        ROUND(AVG(g.score), 2) as average_score,
        CASE 
            WHEN AVG(g.score) >= 90 THEN 'A'
            WHEN AVG(g.score) >= 80 THEN 'B'
            WHEN AVG(g.score) >= 70 THEN 'C'
            WHEN AVG(g.score) >= 60 THEN 'D'
            ELSE 'F'
        END as average_grade
    FROM students s
    LEFT JOIN grades g ON s.id = g.student_id
    $where
    GROUP BY s.id
    ORDER BY average_score DESC
");
$stmt->execute($params);
$studentPerformance = $stmt->fetchAll();

include '../includes/header.php';
displayFlash();
?>

<div class="table-container">
    <div class="table-header">
        <h2>📈 Performance Overview</h2>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Subjects Taken</th>
                <th>Average Score</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($studentPerformance as $row): ?>
            <tr>
                <td><?php echo $row['student_number']; ?></td>
                <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                <td><?php echo $row['total_subjects']; ?></td>
                <td><?php echo $row['average_score'] ?? 'N/A'; ?></td>
                <td>
                    <?php if ($row['average_grade']): ?>
                    <?php $gradeClass = 'grade-' . strtolower($row['average_grade']); ?>
                    <span class="grade-badge <?php echo $gradeClass; ?>">
                        <?php echo $row['average_grade']; ?>
                    </span>
                    <?php else: ?>
                    <span>No grades</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
