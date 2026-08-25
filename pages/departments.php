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

        <table>
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
                    <td><code><?php echo htmlspecialchars($department['department_code']); ?></code></td>
                    <td><?php echo htmlspecialchars($department['department_name']); ?></td>
                    <td><?php echo htmlspecialchars($department['description'] ?? ''); ?></td>
                    <td><?php echo $department['instructor_count']; ?></td>
                    <td><?php echo $department['subject_count']; ?></td>
                    <td>
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
        <h2>Add New Department</h2>
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

<script>
    document.getElementById('addDeptModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
