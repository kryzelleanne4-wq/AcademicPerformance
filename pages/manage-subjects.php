<?php
/**
 * Courses / Subjects Management Page (Admin only)
 * The college course catalog.
 */

require_once '../includes/functions.php';
requireRole('admin');

$db = getDB();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $subject_code = sanitize($_POST['subject_code'] ?? '');
            $subject_name = sanitize($_POST['subject_name'] ?? '');
            $department_id = intval($_POST['department_id'] ?? 0);
            $description = sanitize($_POST['description'] ?? '');
            $credits = intval($_POST['credits'] ?? 3);
            $course_level = intval($_POST['course_level'] ?? 0);

            if ($subject_code === '' || $subject_name === '') {
                setFlash('Subject code and name are required.', 'error');
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO subjects (department_id, subject_code, subject_name, description, credits, course_level) VALUES (:did, :c, :n, :d, :cr, :cl)");
                    $stmt->execute([
                        ':did' => $department_id ?: null,
                        ':c'   => $subject_code,
                        ':n'   => $subject_name,
                        ':d'   => $description ?: null,
                        ':cr'  => $credits,
                        ':cl'  => $course_level ?: null
                    ]);
                    setFlash('Subject "' . $subject_name . '" added successfully!');
                } catch (Exception $e) {
                    setFlash('Error adding subject: ' . $e->getMessage(), 'error');
                }
            }
            header('Location: manage-subjects.php');
            exit();
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            try {
                $stmt = $db->prepare("DELETE FROM subjects WHERE id = :id");
                $stmt->execute([':id' => $id]);
                setFlash('Subject deleted.');
            } catch (Exception $e) {
                setFlash('Cannot delete subject: ' . $e->getMessage(), 'error');
            }
            header('Location: manage-subjects.php');
            exit();
            break;
    }
}

$departments = $db->query("SELECT * FROM departments ORDER BY department_name")->fetchAll();

$subjects = $db->query("
    SELECT s.*, d.department_code, d.department_name
    FROM subjects s
    LEFT JOIN departments d ON s.department_id = d.id
    ORDER BY s.subject_code
")->fetchAll();

$pageTitle = 'Courses / Subjects';
include '../includes/header.php';
displayFlash();
?>

<main>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php echo icon('book-open', 24); ?> Course Catalog</h2>
            <button class="btn btn-primary" onclick="document.getElementById('addSubjectModal').style.display='block'"><?php echo icon('plus', 14); ?> Add Subject</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Credits</th>
                    <th>Level</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subjects as $subject): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($subject['department_name'] ?? '—'); ?></td>
                    <td><?php echo $subject['credits']; ?></td>
                    <td><?php echo $subject['course_level'] ? 'Year ' . $subject['course_level'] : '—'; ?></td>
                    <td>
                        <span class="attendance-badge status-<?php echo $subject['is_active'] ? 'present' : 'absent'; ?>">
                            <?php echo $subject['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this subject?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $subject['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add Subject Modal -->
<div id="addSubjectModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2>Add New Subject</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="form-row">
                <div class="form-group">
                    <label>Subject Code</label>
                    <input type="text" name="subject_code" class="form-control" placeholder="e.g. CS101" required>
                </div>
                <div class="form-group">
                    <label>Credits</label>
                    <input type="number" name="credits" class="form-control" value="3" min="1" max="8" required>
                </div>
            </div>

            <div class="form-group">
                <label>Subject Name</label>
                <input type="text" name="subject_name" class="form-control" placeholder="e.g. Introduction to Programming" required>
            </div>

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
                <label>Course Level (Year)</label>
                <select name="course_level" class="form-control">
                    <option value="">-- Any --</option>
                    <?php for ($y = 1; $y <= 5; $y++): ?>
                    <option value="<?php echo $y; ?>">Year <?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Subject</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addSubjectModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('addSubjectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
