<?php
/**
 * Instructors Management Page (Admin only)
 * CRUD for instructors with user account creation.
 * Employee IDs are auto-generated (EMP-YYYY-####) and double as the login ID.
 */

require_once '../includes/functions.php';
requireRole('admin');

$db = getDB();

// Handle form submissions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $first_name    = sanitize($_POST['first_name'] ?? '');
            $last_name     = sanitize($_POST['last_name'] ?? '');
            $email         = sanitize($_POST['email'] ?? '');
            $phone         = sanitize($_POST['phone'] ?? '');
            $department_id = intval($_POST['department_id'] ?? 0);
            $title         = sanitize($_POST['title'] ?? '');
            $specialization = sanitize($_POST['specialization'] ?? '');
            $hired_date    = sanitize($_POST['hired_date'] ?? '');

            if ($first_name === '' || $last_name === '') {
                setFlash('First and last name are required.', 'error');
                header('Location: instructors.php');
                exit();
            }

            try {
                $db->beginTransaction();

                $employeeId = generateEmployeeId($db);
                $fullName   = $first_name . ' ' . $last_name;

                // Create the login account
                $stmt = $db->prepare("
                    INSERT INTO users (username, password, full_name, email, role)
                    VALUES (:u, :p, :fn, :e, 'instructor')
                ");
                $stmt->execute([
                    ':u'  => $employeeId,
                    ':p'  => password_hash(defaultPassword(), PASSWORD_DEFAULT),
                    ':fn' => $fullName,
                    ':e'  => $email ?: null
                ]);
                $userId = (int) $db->lastInsertId();

                // Create the instructor record
                $stmt = $db->prepare("
                    INSERT INTO instructors (user_id, employee_id, first_name, last_name, email, phone, department_id, title, specialization, hired_date)
                    VALUES (:uid, :eid, :fn, :ln, :e, :ph, :did, :t, :sp, :hd)
                ");
                $stmt->execute([
                    ':uid' => $userId,
                    ':eid' => $employeeId,
                    ':fn'  => $first_name,
                    ':ln'  => $last_name,
                    ':e'   => $email ?: null,
                    ':ph'  => $phone ?: null,
                    ':did' => $department_id ?: null,
                    ':t'   => $title ?: null,
                    ':sp'  => $specialization ?: null,
                    ':hd'  => $hired_date ?: null
                ]);

                $db->commit();

                setFlash('Instructor added. Login ID: <strong>' . $employeeId . '</strong> &middot; Default password: <strong>' . defaultPassword() . '</strong>');
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                setFlash('Error adding instructor: ' . $e->getMessage(), 'error');
            }

            header('Location: instructors.php');
            exit();
            break;

        case 'update':
            $id             = intval($_POST['id'] ?? 0);
            $first_name     = sanitize($_POST['first_name'] ?? '');
            $last_name      = sanitize($_POST['last_name'] ?? '');
            $email          = sanitize($_POST['email'] ?? '');
            $phone          = sanitize($_POST['phone'] ?? '');
            $department_id  = intval($_POST['department_id'] ?? 0);
            $title          = sanitize($_POST['title'] ?? '');
            $specialization = sanitize($_POST['specialization'] ?? '');
            $status         = sanitize($_POST['status'] ?? 'Active');
            $hired_date     = sanitize($_POST['hired_date'] ?? '');

            if (!$id || $first_name === '' || $last_name === '') {
                setFlash('ID and name are required.', 'error');
            } else {
                $validStatuses = ['Active', 'Inactive', 'On Leave'];
                if (!in_array($status, $validStatuses, true)) {
                    $status = 'Active';
                }

                $stmt = $db->prepare("
                    UPDATE instructors
                    SET first_name = :fn, last_name = :ln, email = :e, phone = :ph,
                        department_id = :did, title = :t, specialization = :sp,
                        status = :st, hired_date = :hd, updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':fn'  => $first_name,
                    ':ln'  => $last_name,
                    ':e'   => $email ?: null,
                    ':ph'  => $phone ?: null,
                    ':did' => $department_id ?: null,
                    ':t'   => $title ?: null,
                    ':sp'  => $specialization ?: null,
                    ':st'  => $status,
                    ':hd'  => $hired_date ?: null,
                    ':id'  => $id
                ]);

                // Also update the linked user account name
                $userId = $db->query("SELECT user_id FROM instructors WHERE id = " . $id)->fetchColumn();
                if ($userId) {
                    $db->prepare("UPDATE users SET full_name = :fn, email = :e, updated_at = CURRENT_TIMESTAMP WHERE id = :uid")
                       ->execute([':fn' => $first_name . ' ' . $last_name, ':e' => $email ?: null, ':uid' => $userId]);
                }

                setFlash('Instructor updated successfully.');
            }

            header('Location: instructors.php');
            exit();
            break;

        case 'toggle_status':
            $id     = intval($_POST['id'] ?? 0);
            $status = sanitize($_POST['status'] ?? 'Active');
            $valid  = ['Active', 'Inactive', 'On Leave'];
            if (in_array($status, $valid, true)) {
                $db->prepare("UPDATE instructors SET status = :st, updated_at = CURRENT_TIMESTAMP WHERE id = :id")
                   ->execute([':st' => $status, ':id' => $id]);
                setFlash('Instructor status updated.');
            }
            header('Location: instructors.php');
            exit();
            break;

        case 'reset_password':
            $id = intval($_POST['id'] ?? 0);
            $userId = $db->query("SELECT user_id FROM instructors WHERE id = " . $id)->fetchColumn();
            if ($userId) {
                $db->prepare("UPDATE users SET password = :p, updated_at = CURRENT_TIMESTAMP WHERE id = :uid")
                   ->execute([':p' => password_hash(defaultPassword(), PASSWORD_DEFAULT), ':uid' => $userId]);
                setFlash('Password reset to <strong>' . defaultPassword() . '</strong>.');
            }
            header('Location: instructors.php');
            exit();
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            $userId = $db->query("SELECT user_id FROM instructors WHERE id = " . $id)->fetchColumn();
            if ($userId) {
                $db->prepare("DELETE FROM users WHERE id = :uid")->execute([':uid' => $userId]);
                setFlash('Instructor deleted.');
            }
            header('Location: instructors.php');
            exit();
            break;
    }
}

// Fetch departments for forms
$departments = $db->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();

// Fetch all instructors
$instructors = $db->query("
    SELECT i.*, d.department_code, d.department_name,
           u.is_active AS user_active, u.last_login_at
    FROM instructors i
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN users u ON i.user_id = u.id
    ORDER BY i.last_name, i.first_name
")->fetchAll();

$pageTitle = 'Instructors';
include '../includes/header.php';
displayFlash();
?>

<main>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php echo icon('users', 24); ?> Instructors</h2>
            <button class="btn btn-primary" onclick="document.getElementById('addInstructorModal').style.display='block'"><?php echo icon('plus', 14); ?> Add Instructor</button>
        </div>

        <table data-pagination data-page-size="8">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Title</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($instructors as $inst): ?>
                <tr>
                    <td data-label="Employee ID"><code><?php echo htmlspecialchars($inst['employee_id']); ?></code></td>
                    <td data-label="Name"><?php echo htmlspecialchars($inst['first_name'] . ' ' . $inst['last_name']); ?></td>
                    <td data-label="Department"><?php echo htmlspecialchars($inst['department_code'] ? $inst['department_code'] . ' - ' . $inst['department_name'] : '—'); ?></td>
                    <td data-label="Title"><?php echo htmlspecialchars($inst['title'] ?? '—'); ?></td>
                    <td data-label="Email"><?php echo htmlspecialchars($inst['email'] ?? ''); ?></td>
                    <td data-label="Phone"><?php echo htmlspecialchars($inst['phone'] ?? '—'); ?></td>
                    <td data-label="Status">
                        <span class="attendance-badge status-<?php echo $inst['status'] === 'Active' ? 'present' : 'absent'; ?>">
                            <?php echo htmlspecialchars($inst['status']); ?>
                        </span>
                    </td>
                    <td data-label="Actions">
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $inst['id']; ?>"
                                data-employee-id="<?php echo htmlspecialchars($inst['employee_id'], ENT_QUOTES); ?>"
                                data-first-name="<?php echo htmlspecialchars($inst['first_name'], ENT_QUOTES); ?>"
                                data-last-name="<?php echo htmlspecialchars($inst['last_name'], ENT_QUOTES); ?>"
                                data-email="<?php echo htmlspecialchars($inst['email'] ?? '', ENT_QUOTES); ?>"
                                data-phone="<?php echo htmlspecialchars($inst['phone'] ?? '', ENT_QUOTES); ?>"
                                data-department-id="<?php echo (int) $inst['department_id']; ?>"
                                data-title="<?php echo htmlspecialchars($inst['title'] ?? '', ENT_QUOTES); ?>"
                                data-specialization="<?php echo htmlspecialchars($inst['specialization'] ?? '', ENT_QUOTES); ?>"
                                data-status="<?php echo htmlspecialchars($inst['status'], ENT_QUOTES); ?>"
                                data-hired-date="<?php echo htmlspecialchars($inst['hired_date'] ?? '', ENT_QUOTES); ?>"
                                data-user-active="<?php echo $inst['user_active'] ?? 1; ?>"
                                data-last-login="<?php echo htmlspecialchars($inst['last_login_at'] ?? '', ENT_QUOTES); ?>"
                                onclick="viewInstructor(this)">View</button>
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $inst['id']; ?>"
                                data-first-name="<?php echo htmlspecialchars($inst['first_name'], ENT_QUOTES); ?>"
                                data-last-name="<?php echo htmlspecialchars($inst['last_name'], ENT_QUOTES); ?>"
                                data-email="<?php echo htmlspecialchars($inst['email'] ?? '', ENT_QUOTES); ?>"
                                data-phone="<?php echo htmlspecialchars($inst['phone'] ?? '', ENT_QUOTES); ?>"
                                data-department-id="<?php echo (int) $inst['department_id']; ?>"
                                data-title="<?php echo htmlspecialchars($inst['title'] ?? '', ENT_QUOTES); ?>"
                                data-specialization="<?php echo htmlspecialchars($inst['specialization'] ?? '', ENT_QUOTES); ?>"
                                data-status="<?php echo htmlspecialchars($inst['status'], ENT_QUOTES); ?>"
                                data-hired-date="<?php echo htmlspecialchars($inst['hired_date'] ?? '', ENT_QUOTES); ?>"
                                onclick="editInstructor(this)">Edit</button>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="id" value="<?php echo $inst['id']; ?>">
                            <button type="submit" class="btn btn-secondary btn-sm">Reset PW</button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this instructor and their user account?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $inst['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add Instructor Modal -->
<div id="addInstructorModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('user', 20); ?> Add Instructor</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="alert" style="background: var(--surface-low);">
                A login ID (EMP-YYYY-####) will be auto-generated. The default password is
                <strong><?php echo defaultPassword(); ?></strong>.
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

            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="">-- No Department --</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>">
                            <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Associate Professor">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Specialization</label>
                    <input type="text" name="specialization" class="form-control" placeholder="e.g. Computer Science">
                </div>
                <div class="form-group">
                    <label>Hired Date</label>
                    <input type="date" name="hired_date" class="form-control">
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Instructor</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addInstructorModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- View Instructor Modal -->
<div id="viewInstructorModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('user', 20); ?> Instructor Details</h2>
        <div class="detail-list">
            <div class="detail-row"><span class="detail-label">Employee ID</span><span class="detail-value" id="viewEmpId"></span></div>
            <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value" id="viewName"></span></div>
            <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value" id="viewEmail"></span></div>
            <div class="detail-row"><span class="detail-label">Phone</span><span class="detail-value" id="viewPhone"></span></div>
            <div class="detail-row"><span class="detail-label">Department</span><span class="detail-value" id="viewDept"></span></div>
            <div class="detail-row"><span class="detail-label">Title</span><span class="detail-value" id="viewTitle"></span></div>
            <div class="detail-row"><span class="detail-label">Specialization</span><span class="detail-value" id="viewSpec"></span></div>
            <div class="detail-row"><span class="detail-label">Hired Date</span><span class="detail-value" id="viewHired"></span></div>
            <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value" id="viewStatus"></span></div>
            <div class="detail-row"><span class="detail-label">Account Active</span><span class="detail-value" id="viewUserActive"></span></div>
            <div class="detail-row"><span class="detail-label">Last Login</span><span class="detail-value" id="viewLastLogin"></span></div>
        </div>
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('viewInstructorModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<!-- Edit Instructor Modal -->
<div id="editInstructorModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('pen-line', 20); ?> Edit Instructor</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editInstId">

            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" id="editFirstName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" id="editLastName" class="form-control" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="editEmail" class="form-control">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" id="editPhone" class="form-control">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" id="editDept" class="form-control">
                        <option value="">-- No Department --</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>">
                            <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" id="editTitle" class="form-control" placeholder="e.g. Associate Professor">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Specialization</label>
                    <input type="text" name="specialization" id="editSpec" class="form-control">
                </div>
                <div class="form-group">
                    <label>Hired Date</label>
                    <input type="date" name="hired_date" id="editHired" class="form-control">
                </div>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" id="editStatus" class="form-control">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="On Leave">On Leave</option>
                </select>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('editInstructorModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function viewInstructor(btn) {
        var d = btn.dataset;
        document.getElementById('viewEmpId').textContent = d.employeeId;
        document.getElementById('viewName').textContent = d.firstName + ' ' + d.lastName;
        document.getElementById('viewEmail').textContent = d.email || '—';
        document.getElementById('viewPhone').textContent = d.phone || '—';

        // Department: reconstruct from available data
        var deptId = parseInt(d.departmentId) || 0;
        var deptSelect = document.querySelector('#editDept');
        var deptLabel = '—';
        if (deptSelect) {
            var opt = deptSelect.querySelector('option[value="' + deptId + '"]');
            if (opt && deptId > 0) deptLabel = opt.textContent.trim();
        }
        document.getElementById('viewDept').textContent = deptLabel;
        document.getElementById('viewTitle').textContent = d.title || '—';
        document.getElementById('viewSpec').textContent = d.specialization || '—';
        document.getElementById('viewHired').textContent = d.hiredDate || '—';

        var statusEl = document.getElementById('viewStatus');
        var statusCls = d.status === 'Active' ? 'status-present' : 'status-absent';
        statusEl.innerHTML = '<span class="attendance-badge ' + statusCls + '">' + d.status + '</span>';

        var activeEl = document.getElementById('viewUserActive');
        activeEl.innerHTML = '<span class="attendance-badge ' + (d.userActive == 1 ? 'status-present' : 'status-absent') + '">' + (d.userActive == 1 ? 'Active' : 'Inactive') + '</span>';

        document.getElementById('viewLastLogin').textContent = d.lastLogin ? new Date(d.lastLogin).toLocaleDateString() : 'Never';

        document.getElementById('viewInstructorModal').style.display = 'block';
    }

    function editInstructor(btn) {
        var d = btn.dataset;
        document.getElementById('editInstId').value = d.id;
        document.getElementById('editFirstName').value = d.firstName;
        document.getElementById('editLastName').value = d.lastName;
        document.getElementById('editEmail').value = d.email || '';
        document.getElementById('editPhone').value = d.phone || '';
        document.getElementById('editDept').value = d.departmentId || '';
        document.getElementById('editTitle').value = d.title || '';
        document.getElementById('editSpec').value = d.specialization || '';
        document.getElementById('editStatus').value = d.status;
        document.getElementById('editHired').value = d.hiredDate || '';
        document.getElementById('editInstructorModal').style.display = 'block';
    }

    // Close modals on overlay click
    ['addInstructorModal', 'viewInstructorModal', 'editInstructorModal'].forEach(function(id) {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
