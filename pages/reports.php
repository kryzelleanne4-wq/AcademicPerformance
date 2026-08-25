<?php
/**
 * Reports Page (Admin only)
 */

require_once '../includes/functions.php';
requireRole('admin');

$db = getDB();

// Get average grades per student
$stmt = $db->query("
    SELECT 
        s.id,
        s.student_id,
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
    GROUP BY s.id
    ORDER BY average_score DESC
");
$studentGrades = $stmt->fetchAll();
?>

<?php
$pageTitle = 'Reports';
include '../includes/header.php';
?>

<main>
        <div class="card">
            <div class="card-header">
                <h2><?php echo icon('bar-chart', 24); ?> Student Performance Summary</h2>
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
                    <?php foreach ($studentGrades as $row): ?>
                    <tr>
                        <td data-label="Student ID"><?php echo $row['student_id']; ?></td>
                        <td data-label="Name"><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                        <td data-label="Subjects Taken"><?php echo $row['total_subjects']; ?></td>
                        <td data-label="Average Score"><?php echo $row['average_score'] ?? 'N/A'; ?></td>
                        <td data-label="Grade">
                            <?php if ($row['average_grade']): ?>
                            <span class="grade-badge grade-<?php echo strtolower($row['average_grade']); ?>">
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
    </main>

<?php include '../includes/footer.php'; ?>
