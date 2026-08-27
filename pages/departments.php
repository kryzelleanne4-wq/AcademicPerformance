<?php
/**
 * Departments Management Page (Admin only)
 */

require_once '../includes/functions.php';
requireRole('admin');

$db = getDB();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $code = sanitize($_POST['department_code'] ?? '');
            $name = sanitize($_POST['department_name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');

            if ($code === '' || $name === '') {
                setFlash('Department code and name are required.', 'error');
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO departments (department_code, department_name, description) VALUES (:c, :n, :d)");
                    $stmt->execute([':c' => $code, ':n' => $name, ':d' => $description ?: null]);
                    setFlash('Department "' . $name . '" added successfully!');
                } catch (Exception $e) {
                    setFlash('Error adding department: ' . $e->getMessage(), 'error');
                }
            }
            header('Location: departments.php');
            exit();
            break;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $code = sanitize($_POST['department_code'] ?? '');
            $name = sanitize($_POST['department_name'] ?? '');
            $description = sanitize($_POST['description'] ?? '');

            if (!$id || $code === '' || $name === '') {
                setFlash('Department code and name are required.', 'error');
            } else {
                try {
                    $stmt = $db->prepare("UPDATE departments SET department_code = :c, department_name = :n, description = :d, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $stmt->execute([':c' => $code, ':n' => $name, ':d' => $description ?: null, ':id' => $id]);
                    setFlash('Department "' . $name . '" updated successfully!');
                } catch (Exception $e) {
                    setFlash('Error updating department: ' . $e->getMessage(), 'error');
                }
            }
            header('Location: departments.php');
            exit();
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            try {
                $stmt = $db->prepare("DELETE FROM departments WHERE id = :id");
                $stmt->execute([':id' => $id]);
                setFlash('Department deleted.');
            } catch (Exception $e) {
                setFlash('Cannot delete: ' . $e->getMessage(), 'error');
            }
            header('Location: departments.php');
            exit();
            break;
    }
}

$departments = $db->query("
    SELECT d.*,
           (SELECT COUNT(*) FROM instructors i WHERE i.department_id = d.id) AS instructor_count,
           (SELECT COUNT(*) FROM subjects s WHERE s.department_id = d.id) AS subject_count
    FROM departments d
    ORDER BY d.department_name
")->fetchAll();

$pageTitle = 'Departments';
include '../includes/header.php';
displayFlash();
?>

<main>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php echo icon('landmark', 24); ?> Departments</h2>
            <button class="btn btn-primary" onclick="document.getElementById('addDeptModal').style.display='block'"><?php echo icon('plus', 14); ?> Add Department</button>
        </div>

        <table data-pagination data-page-size="8">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Instructors</th>
                    <th>Subjects</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $department): ?>
                <tr>
                    <td data-label="Code"><code><?php echo htmlspecialchars($department['department_code']); ?></code></td>
                    <td data-label="Name"><?php echo htmlspecialchars($department['department_name']); ?></td>
                    <td data-label="Description"><?php echo htmlspecialchars($department['description'] ?? ''); ?></td>
                    <td data-label="Instructors"><?php echo $department['instructor_count']; ?></td>
                    <td data-label="Subjects"><?php echo $department['subject_count']; ?></td>
                    <td data-label="Actions">
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $department['id']; ?>"
                                data-code="<?php echo htmlspecialchars($department['department_code'], ENT_QUOTES); ?>"
                                data-name="<?php echo htmlspecialchars($department['department_name'], ENT_QUOTES); ?>"
                                data-description="<?php echo htmlspecialchars($department['description'] ?? '', ENT_QUOTES); ?>"
                                data-instructors="<?php echo $department['instructor_count']; ?>"
                                data-subjects="<?php echo $department['subject_count']; ?>"
                                data-created="<?php echo htmlspecialchars($department['created_at'] ?? '', ENT_QUOTES); ?>"
                                onclick="viewDept(this)">View</button>
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $department['id']; ?>"
                                data-code="<?php echo htmlspecialchars($department['department_code'], ENT_QUOTES); ?>"
                                data-name="<?php echo htmlspecialchars($department['department_name'], ENT_QUOTES); ?>"
                                data-description="<?php echo htmlspecialchars($department['description'] ?? '', ENT_QUOTES); ?>"
                                onclick="editDept(this)">Edit</button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this department?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $department['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add Department Modal -->
<div id="addDeptModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('plus', 20); ?> Add New Department</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label>Department Code</label>
                <input type="text" name="department_code" class="form-control" placeholder="e.g. CS" required>
            </div>

            <div class="form-group">
                <label>Department Name</label>
                <input type="text" name="department_name" class="form-control" placeholder="e.g. Computer Science" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Department</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addDeptModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- View Department Modal -->
<div id="viewDeptModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('landmark', 20); ?> Department Details</h2>
        <div class="detail-list">
            <div class="detail-row"><span class="detail-label">Code</span><span class="detail-value" id="viewDeptCode"></span></div>
            <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value" id="viewDeptName"></span></div>
            <div class="detail-row"><span class="detail-label">Description</span><span class="detail-value" id="viewDeptDesc"></span></div>
            <div class="detail-row"><span class="detail-label">Instructors</span><span class="detail-value" id="viewDeptInstructors"></span></div>
            <div class="detail-row"><span class="detail-label">Subjects</span><span class="detail-value" id="viewDeptSubjects"></span></div>
            <div class="detail-row"><span class="detail-label">Created</span><span class="detail-value" id="viewDeptCreated"></span></div>
        </div>
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('viewDeptModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div id="editDeptModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('pen-line', 20); ?> Edit Department</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editDeptId">

            <div class="form-group">
                <label>Department Code</label>
                <input type="text" name="department_code" id="editDeptCode" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Department Name</label>
                <input type="text" name="department_name" id="editDeptName" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="editDeptDesc" class="form-control" rows="3"></textarea>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('editDeptModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function viewDept(btn) {
        document.getElementById('viewDeptCode').textContent = btn.dataset.code;
        document.getElementById('viewDeptName').textContent = btn.dataset.name;
        document.getElementById('viewDeptDesc').textContent = btn.dataset.description || '—';
        document.getElementById('viewDeptInstructors').textContent = btn.dataset.instructors;
        document.getElementById('viewDeptSubjects').textContent = btn.dataset.subjects;
        document.getElementById('viewDeptCreated').textContent = btn.dataset.created ? new Date(btn.dataset.created.replace(' ', 'T')).toLocaleString() : '—';
        document.getElementById('viewDeptModal').style.display = 'block';
    }

    function editDept(btn) {
        document.getElementById('editDeptId').value = btn.dataset.id;
        document.getElementById('editDeptCode').value = btn.dataset.code;
        document.getElementById('editDeptName').value = btn.dataset.name;
        document.getElementById('editDeptDesc').value = btn.dataset.description || '';
        document.getElementById('editDeptModal').style.display = 'block';
    }

    ['addDeptModal', 'viewDeptModal', 'editDeptModal'].forEach(function(id) {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
