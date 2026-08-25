<?php
/**
 * Grades Management Page
 */

require_once '../includes/functions.php';

$db = getDB();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $student_id = intval($_POST['student_id']);
                $subject_id = intval($_POST['subject_id']);
                $semester = sanitize($_POST['semester']);
                $year = intval($_POST['year']);
                $score = floatval($_POST['score']);
                $grade = calculateGrade($score);
                
                $stmt = $db->prepare("
                    INSERT INTO grades (student_id, subject_id, semester, year, score, grade)
                    VALUES (:student_id, :subject_id, :semester, :year, :score, :grade)
                ");
                
                try {
                    $stmt->bindValue(':student_id', $student_id, PDO::PARAM_INT);
                    $stmt->bindValue(':subject_id', $subject_id, PDO::PARAM_INT);
                    $stmt->bindValue(':semester', $semester);
                    $stmt->bindValue(':year', $year, PDO::PARAM_INT);
                    $stmt->bindValue(':score', $score);
                    $stmt->bindValue(':grade', $grade);
                    $stmt->execute();
                    
                    setFlash('Grade added successfully!');
                    header('Location: grades.php');
                    exit();
                } catch (Exception $e) {
                    $message = 'Error adding grade: ' . $e->getMessage();
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                $stmt = $db->prepare("DELETE FROM grades WHERE id = :id");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                
                setFlash('Grade deleted successfully!');
                header('Location: grades.php');
                exit();
                break;
        }
    }
}

// Get data for forms and display
$studentsStmt = $db->query("SELECT * FROM students WHERE status = 'Active' ORDER BY last_name");
$activeStudents = $studentsStmt->fetchAll();

$subjectsStmt = $db->query("SELECT * FROM subjects ORDER BY subject_code");
$allSubjects = $subjectsStmt->fetchAll();

// Get grades with student and subject info
$gradesStmt = $db->query("
    SELECT g.*, s.student_id as student_number, s.first_name, s.last_name, sub.subject_code, sub.subject_name
    FROM grades g
    JOIN students s ON g.student_id = s.id
    JOIN subjects sub ON g.subject_id = sub.id
    ORDER BY g.year DESC, g.semester DESC, s.last_name
");
$gradesList = $gradesStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades - Student Performance</title>
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
        <?php displayFlash(); ?>
        
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2>📝 Grades List</h2>
                <button class="btn btn-primary" onclick="document.getElementById('addGradeModal').style.display='block'">+ Add Grade</button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Subject</th>
                        <th>Semester</th>
                        <th>Year</th>
                        <th>Score</th>
                        <th>Grade</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gradesList as $grade): ?>
                    <tr>
                        <td><?php echo $grade['first_name'] . ' ' . $grade['last_name']; ?></td>
                        <td><?php echo $grade['subject_code'] . ' - ' . $grade['subject_name']; ?></td>
                        <td><?php echo $grade['semester']; ?></td>
                        <td><?php echo $grade['year']; ?></td>
                        <td><?php echo $grade['score']; ?></td>
                        <td>
                            <span class="grade-badge" style="background: <?php echo getGradeColor($grade['grade']); ?>">
                                <?php echo $grade['grade']; ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $grade['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <!-- Add Grade Modal -->
    <div id="addGradeModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; max-width: 500px; margin: 100px auto; padding: 2rem; border-radius: 10px;">
            <h2 style="margin-bottom: 1.5rem;">Add New Grade</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Student</label>
                    <select name="student_id" class="form-control" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($activeStudents as $student): ?>
                        <option value="<?php echo $student['id']; ?>">
                            <?php echo $student['student_id'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Subject</label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($allSubjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>">
                            <?php echo $subject['subject_code'] . ' - ' . $subject['subject_name']; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" class="form-control" required>
                        <option value="First Semester">First Semester</option>
                        <option value="Second Semester">Second Semester</option>
                        <option value="Summer">Summer</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" class="form-control" value="<?php echo date('Y'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Score (0-100)</label>
                    <input type="number" name="score" class="form-control" min="0" max="100" step="0.01" required
                           oninput="document.getElementById('previewGrade').textContent = this.value >= 90 ? 'A' : this.value >= 80 ? 'B' : this.value >= 70 ? 'C' : this.value >= 60 ? 'D' : 'F'">
                    <small>Grade: <strong id="previewGrade">-</strong></small>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-success">Save Grade</button>
                    <button type="button" class="btn btn-danger" onclick="document.getElementById('addGradeModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.getElementById('addGradeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    </script>
</body>
</html>
