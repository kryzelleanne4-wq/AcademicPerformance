<?php
/**
 * Reports Page
 */

require_once '../includes/functions.php';

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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Student Performance</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <a href="../index.php">📚 Student Performance</a>
        </div>
        <ul class="nav-links">
            <li><a href="../index.php">Dashboard</a></li>
            <li><a href="students.php">Students</a></li>
            <li><a href="subjects.php">Subjects</a></li>
            <li><a href="grades.php">Grades</a></li>
            <li><a href="reports.php">Reports</a></li>
        </ul>
    </nav>
    
    <main class="container">
        <div class="card">
            <div class="card-header">
                <h2>📊 Student Performance Summary</h2>
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
                        <td><?php echo $row['student_id']; ?></td>
                        <td><?php echo $row['first_name'] . ' ' . $row['last_name']; ?></td>
                        <td><?php echo $row['total_subjects']; ?></td>
                        <td><?php echo $row['average_score'] ?? 'N/A'; ?></td>
                        <td>
                            <?php if ($row['average_grade']): ?>
                            <span class="grade-badge" style="background: <?php echo getGradeColor($row['average_grade']); ?>">
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
</body>
</html>
