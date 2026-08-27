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
        <h2><?php echo icon('trending-up', 24); ?> Performance Overview</h2>
    </div>
    
    <table data-pagination data-page-size="8">
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
                <td data-label="Student ID"><?php echo $row['student_number']; ?></td>
                <td data-label="Name"><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                <td data-label="Subjects Taken"><?php echo $row['total_subjects']; ?></td>
                <td data-label="Average Score"><?php echo $row['average_score'] ?? 'N/A'; ?></td>
                <td data-label="Grade">
                    <?php if ($row['average_grade']): ?>
                    <span class="grade-badge <?php echo gradeBadgeClass($row['average_grade']); ?>" title="<?php echo htmlspecialchars(gradeDescriptor($row['average_grade'])); ?>">
                        <?php echo htmlspecialchars(formatGrade($row['average_grade'])); ?>
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
