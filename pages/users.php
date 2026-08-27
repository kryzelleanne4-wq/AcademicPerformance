<?php
/**
 * Users Management Page (Admin only)
 * Add students and instructors. Login IDs are auto-generated
 * (STU-YYYY-#### / EMP-YYYY-####) and the default password is password123.
 */

require_once '../includes/functions.php';
requireRole('admin');

$db = getDB();

// Handle form submissions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_user':
            $role = ($_POST['role'] ?? '') === 'instructor' ? 'instructor' : 'student';
            $first_name = sanitize($_POST['first_name'] ?? '');
            $last_name = sanitize($_POST['last_name'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $gender = sanitize($_POST['gender'] ?? 'Male');
            $program_id = intval($_POST['program_id'] ?? 0);
            $year_level = intval($_POST['year_level'] ?? 0);
            $block_id = intval($_POST['block_id'] ?? 0);
            $student_type = ($_POST['student_type'] ?? 'Regular') === 'Irregular' ? 'Irregular' : 'Regular';
            $department_id = intval($_POST['department_id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');

            if ($first_name === '' || $last_name === '') {
                setFlash('First and last name are required.', 'error');
                header('Location: users.php');
                exit();
            }

            $full_name = $first_name . ' ' . $last_name;
            $passwordHash = password_hash(defaultPassword(), PASSWORD_DEFAULT);

            try {
                $db->beginTransaction();

                if ($role === 'student') {
                    $loginId = generateStudentId($db);
                    $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (:u, :p, :fn, :e, 'student')");
                    $stmt->execute([':u' => $loginId, ':p' => $passwordHash, ':fn' => $full_name, ':e' => $email ?: null]);
                    $userId = (int) $db->lastInsertId();

                    $stmt = $db->prepare("INSERT INTO students (user_id, student_id, first_name, last_name, email, gender, program_id, year_level, block_id, student_type) VALUES (:uid, :sid, :fn, :ln, :e, :g, :pid, :yl, :bid, :st)");
                    $stmt->execute([
                        ':uid' => $userId,
                        ':sid' => $loginId,
                        ':fn'  => $first_name,
                        ':ln'  => $last_name,
                        ':e'   => $email ?: null,
                        ':g'   => $gender,
                        ':pid' => $program_id ?: null,
                        ':yl'  => $year_level ?: null,
                        ':bid' => $block_id ?: null,
                        ':st'  => $student_type
                    ]);
                } else {
                    $loginId = generateEmployeeId($db);
                    $stmt = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (:u, :p, :fn, :e, 'instructor')");
                    $stmt->execute([':u' => $loginId, ':p' => $passwordHash, ':fn' => $full_name, ':e' => $email ?: null]);
                    $userId = (int) $db->lastInsertId();

                    $stmt = $db->prepare("INSERT INTO instructors (user_id, employee_id, first_name, last_name, email, department_id, title) VALUES (:uid, :eid, :fn, :ln, :e, :did, :t)");
                    $stmt->execute([
                        ':uid' => $userId,
                        ':eid' => $loginId,
                        ':fn'  => $first_name,
                        ':ln'  => $last_name,
                        ':e'   => $email ?: null,
                        ':did' => $department_id ?: null,
                        ':t'   => $title ?: null
                    ]);
                }

                $db->commit();
                setFlash(ucfirst($role) . ' "' . $full_name . '" added. Login ID: <strong>' . $loginId . '</strong> &middot; Default password: <strong>' . defaultPassword() . '</strong>');
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                setFlash('Error adding user: ' . $e->getMessage(), 'error');
            }

            header('Location: users.php');
            exit();
            break;

        case 'toggle_active':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("UPDATE users SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND role != 'admin'");
            $stmt->execute([':id' => $id]);
            setFlash('User status updated.');
            header('Location: users.php');
            exit();
            break;

        case 'reset_password':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("UPDATE users SET password = :p, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':id' => $id, ':p' => password_hash(defaultPassword(), PASSWORD_DEFAULT)]);
            setFlash('Password reset to <strong>' . defaultPassword() . '</strong>.');
            header('Location: users.php');
            exit();
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            // Delete the login account; student/instructor rows are removed via FK cascade.
            $stmt = $db->prepare("DELETE FROM users WHERE id = :id AND role != 'admin'");
            $stmt->execute([':id' => $id]);
            setFlash('User deleted.');
            header('Location: users.php');
            exit();
            break;
    }
}

// Data for forms and lists
$programs = $db->query("SELECT * FROM programs ORDER BY program_name")->fetchAll();
$departments = $db->query("SELECT * FROM departments ORDER BY department_name")->fetchAll();
$blocks = $db->query("
    SELECT b.*, d.department_code, d.department_name
    FROM blocks b
    JOIN departments d ON b.department_id = d.id
    WHERE b.is_active = 1
    ORDER BY d.department_name, b.year_level, b.block_code
")->fetchAll();

$usersStmt = $db->query("
    SELECT u.*,
           COALESCE(s.student_id, i.employee_id, u.username) AS login_id,
           COALESCE(s.first_name || ' ' || s.last_name, i.first_name || ' ' || i.last_name, u.full_name) AS display_name
    FROM users u
    LEFT JOIN students s ON s.user_id = u.id
    LEFT JOIN instructors i ON i.user_id = u.id
    ORDER BY u.role, display_name
");
$allUsers = $usersStmt->fetchAll();

$pageTitle = 'Users';
include '../includes/header.php';
displayFlash();
?>

<main>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php echo icon('users', 24); ?> Manage Users</h2>
            <button class="btn btn-primary" onclick="document.getElementById('addUserModal').style.display='block'"><?php echo icon('plus', 14); ?> Add User</button>
        </div>

        <table data-pagination data-page-size="8">
            <thead>
                <tr>
                    <th>Login ID</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allUsers as $u): ?>
                <tr>
                    <td data-label="Login ID"><code><?php echo htmlspecialchars($u['login_id']); ?></code></td>
                    <td data-label="Name"><?php echo htmlspecialchars($u['display_name']); ?></td>
                    <td data-label="Role">
                        <span class="grade-badge role-<?php echo $u['role']; ?>"><?php echo roleLabel($u['role']); ?></span>
                    </td>
                    <td data-label="Email"><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                    <td data-label="Status">
                        <span class="attendance-badge status-<?php echo $u['is_active'] ? 'present' : 'absent'; ?>">
                            <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td data-label="Last Login"><?php echo $u['last_login_at'] ? formatDate($u['last_login_at']) : 'Never'; ?></td>
                    <td data-label="Actions">
                        <?php if ($u['role'] !== 'admin'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">
                                <?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Reset Password</button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user account?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2>Add New User</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">

            <div class="form-group">
                <label>User Type</label>
                <select name="role" class="form-control" id="userRoleSelect" onchange="toggleUserType()">
                    <option value="student">Student</option>
                    <option value="instructor">Instructor</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control">
            </div>

            <!-- Student-only fields -->
            <div id="studentFields">
                <div class="form-row">
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" class="form-control">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Year Level</label>
                        <select name="year_level" class="form-control">
                            <option value="">--</option>
                            <?php for ($y = 1; $y <= 5; $y++): ?>
                            <option value="<?php echo $y; ?>">Year <?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Program</label>
                    <select name="program_id" class="form-control">
                        <option value="">-- No Program --</option>
                        <?php foreach ($programs as $program): ?>
                        <option value="<?php echo $program['id']; ?>">
                            <?php echo htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Type</label>
                        <select name="student_type" class="form-control">
                            <option value="Regular">Regular</option>
                            <option value="Irregular">Irregular</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Class Block</label>
                        <select name="block_id" class="form-control">
                            <option value="">-- No Block --</option>
                            <?php foreach ($blocks as $block): ?>
                            <option value="<?php echo $block['id']; ?>">
                                <?php echo htmlspecialchars(blockLabel($block['department_code'], $block['year_level'], $block['block_code'], $block['block_name'])); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Instructor-only fields -->
            <div id="instructorFields" style="display: none;">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="">-- No Department --</option>
                        <?php foreach ($departments as $department): ?>
                        <option value="<?php echo $department['id']; ?>">
                            <?php echo htmlspecialchars($department['department_code'] . ' - ' . $department['department_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Associate Professor">
                </div>
            </div>

            <div class="alert" style="background: var(--surface-low);">
                A login ID will be auto-generated. The default password is
                <strong><?php echo defaultPassword(); ?></strong>. Users can change it after their first login.
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Create User</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addUserModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleUserType() {
        var isInstructor = document.getElementById('userRoleSelect').value === 'instructor';
        document.getElementById('studentFields').style.display = isInstructor ? 'none' : '';
        document.getElementById('instructorFields').style.display = isInstructor ? '' : 'none';
    }

    var addUserModal = document.getElementById('addUserModal');
    addUserModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
