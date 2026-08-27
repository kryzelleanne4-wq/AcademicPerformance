<?php
/**
 * Students Management Page (Admin only)
 * Handle adding, editing, viewing students.
 * Student IDs are auto-generated (STU-YYYY-####) and double as the login ID.
 */

require_once '../includes/functions.php';
requireRole('admin');

$db = getDB();
$message = '';

// Handle form submissions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $first_name = sanitize($_POST['first_name']);
                $last_name = sanitize($_POST['last_name']);
                $email = sanitize($_POST['email']);
                $phone = sanitize($_POST['phone']);
                $gender = sanitize($_POST['gender']);
                
                try {
                    $db->beginTransaction();

                    $student_id = generateStudentId($db);

                    // Create the login account first.
                    $stmt = $db->prepare("
                        INSERT INTO users (username, password, full_name, email, role)
                        VALUES (:username, :password, :full_name, :email, 'student')
                    ");
                    $stmt->execute([
                        ':username'  => $student_id,
                        ':password'  => password_hash(defaultPassword(), PASSWORD_DEFAULT),
                        ':full_name' => $first_name . ' ' . $last_name,
                        ':email'     => $email ?: null
                    ]);
                    $userId = (int) $db->lastInsertId();

                    // Then the student record linked to that account.
                    $stmt = $db->prepare("
                        INSERT INTO students (user_id, student_id, first_name, last_name, email, phone, gender)
                        VALUES (:user_id, :student_id, :first_name, :last_name, :email, :phone, :gender)
                    ");
                    $stmt->execute([
                        ':user_id'    => $userId,
                        ':student_id' => $student_id,
                        ':first_name' => $first_name,
                        ':last_name'  => $last_name,
                        ':email'      => $email ?: null,
                        ':phone'      => $phone ?: null,
                        ':gender'     => $gender
                    ]);

                    $db->commit();

                    setFlash('Student added. Login ID: <strong>' . $student_id . '</strong> &middot; Default password: <strong>' . defaultPassword() . '</strong>');
                    header('Location: students.php');
                    exit();
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $message = 'Error adding student: ' . $e->getMessage();
                }
                break;
                
            case 'toggle_status':
                $id = intval($_POST['id']);
                $status = sanitize($_POST['status']);
                $allowed = ['Active', 'Inactive', 'Graduated', 'Suspended'];
                if (in_array($status, $allowed, true)) {
                    $stmt = $db->prepare("UPDATE students SET status = :status WHERE id = :id");
                    $stmt->execute([':status' => $status, ':id' => $id]);
                    setFlash('Student status updated.');
                }
                header('Location: students.php');
                exit();
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

<?php
$pageTitle = 'Students';
include '../includes/header.php';
?>

<main>
        
        <div class="card">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2><?php echo icon('user', 24); ?> Students List</h2>
                <button class="btn btn-primary" onclick="document.getElementById('addStudentModal').style.display='block'"><?php echo icon('plus', 14); ?> Add Student</button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-error"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <table data-pagination data-page-size="8">
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
                        <td data-label="Student ID"><code><?php echo htmlspecialchars($student['student_id']); ?></code></td>
                        <td data-label="Name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                        <td data-label="Email"><?php echo htmlspecialchars($student['email'] ?? ''); ?></td>
                        <td data-label="Phone"><?php echo htmlspecialchars($student['phone'] ?? ''); ?></td>
                        <td data-label="Gender"><?php echo htmlspecialchars($student['gender'] ?? ''); ?></td>
                        <td data-label="Status">
                            <span class="attendance-badge status-<?php echo $student['status'] === 'Active' ? 'present' : 'absent'; ?>">
                                <?php echo htmlspecialchars($student['status']); ?>
                            </span>
                        </td>
                        <td data-label="Actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                                <select name="status" class="form-control" style="display:inline-block;width:auto;min-height:32px;padding:4px 8px;" onchange="this.form.submit()">
                                    <?php foreach (['Active', 'Inactive', 'Graduated', 'Suspended'] as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo $student['status'] === $status ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $student['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
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
                
                <div class="alert" style="background: var(--surface-low);">
                    The student ID is generated automatically and is used as the login ID.
                    The default password is <strong><?php echo defaultPassword(); ?></strong>.
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

<?php include '../includes/footer.php'; ?>
