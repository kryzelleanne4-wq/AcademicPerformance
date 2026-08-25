<?php
/**
 * Students Management Page
 * Handle adding, editing, viewing students
 */

require_once '../includes/functions.php';

$db = getDB();
$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $student_id = sanitize($_POST['student_id']);
                $first_name = sanitize($_POST['first_name']);
                $last_name = sanitize($_POST['last_name']);
                $email = sanitize($_POST['email']);
                $phone = sanitize($_POST['phone']);
                $gender = sanitize($_POST['gender']);
                
                $stmt = $db->prepare("
                    INSERT INTO students (student_id, first_name, last_name, email, phone, gender)
                    VALUES (:student_id, :first_name, :last_name, :email, :phone, :gender)
                ");
                
                try {
                    $stmt->bindValue(':student_id', $student_id);
                    $stmt->bindValue(':first_name', $first_name);
                    $stmt->bindValue(':last_name', $last_name);
                    $stmt->bindValue(':email', $email);
                    $stmt->bindValue(':phone', $phone);
                    $stmt->bindValue(':gender', $gender);
                    $stmt->execute();
                    
                    setFlash('Student added successfully!');
                    header('Location: students.php');
                    exit();
                } catch (Exception $e) {
                    $message = 'Error adding student: ' . $e->getMessage();
                }
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                $stmt = $db->prepare("DELETE FROM students WHERE id = :id");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                
                setFlash('Student deleted successfully!');
                header('Location: students.php');
                exit();
                break;
        }
    }
}

// Get all students
$stmt = $db->query("SELECT * FROM students ORDER BY last_name, first_name");
$students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - Student Performance</title>
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
                <h2>👤 Students List</h2>
                <button class="btn btn-primary" onclick="document.getElementById('addStudentModal').style.display='block'">+ Add Student</button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-error"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Gender</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo $student['student_id']; ?></td>
                        <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                        <td><?php echo $student['email']; ?></td>
                        <td><?php echo $student['phone']; ?></td>
                        <td><?php echo $student['gender']; ?></td>
                        <td><?php echo $student['status']; ?></td>
                        <td>
                            <a href="student_detail.php?id=<?php echo $student['id']; ?>" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">View</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <!-- Add Student Modal -->
    <div id="addStudentModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="background: white; max-width: 500px; margin: 100px auto; padding: 2rem; border-radius: 10px;">
            <h2 style="margin-bottom: 1.5rem;">Add New Student</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Student ID</label>
                    <input type="text" name="student_id" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" class="form-control">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-success">Save Student</button>
                    <button type="button" class="btn btn-danger" onclick="document.getElementById('addStudentModal').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Close modal when clicking outside
        document.getElementById('addStudentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    </script>
</body>
</html>
