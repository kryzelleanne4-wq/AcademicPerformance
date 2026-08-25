<?php
/**
 * My Grades Page
 */

require_once '../includes/functions.php';

$pageTitle = 'My Grades';

$db = getDB();

// Get all grades with student and subject info
$stmt = $db->query("
    SELECT g.*, 
           s.first_name, 
           s.last_name, 
           s.student_id as student_number,
           sub.subject_code, 
           sub.subject_name,
           sub.credits
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN subjects sub ON g.subject_id = sub.id
    ORDER BY g.year DESC, g.semester DESC, sub.subject_name
");
$grades = $stmt->fetchAll();

include '../includes/header.php';
displayFlash();
?>

<div class="table-container">
    <div class="table-header">
        <h2>📝 My Grades</h2>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Subject Code</th>
                <th>Subject Name</th>
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
                <td><?php echo $grade['subject_code']; ?></td>
                <td><?php echo $grade['subject_name']; ?></td>
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
                <td><?php echo $grade['credits']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
