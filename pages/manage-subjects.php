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

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $subject_code = sanitize($_POST['subject_code'] ?? '');
            $subject_name = sanitize($_POST['subject_name'] ?? '');
            $department_id = intval($_POST['department_id'] ?? 0);
            $description = sanitize($_POST['description'] ?? '');
            $credits = intval($_POST['credits'] ?? 3);
            $course_level = intval($_POST['course_level'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (!$id || $subject_code === '' || $subject_name === '') {
                setFlash('Subject code and name are required.', 'error');
            } else {
                try {
                    $stmt = $db->prepare("UPDATE subjects SET department_id = :did, subject_code = :c, subject_name = :n, description = :d, credits = :cr, course_level = :cl, is_active = :ia, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $stmt->execute([
                        ':did' => $department_id ?: null,
                        ':c'   => $subject_code,
                        ':n'   => $subject_name,
                        ':d'   => $description ?: null,
                        ':cr'  => $credits,
                        ':cl'  => $course_level ?: null,
                        ':ia'  => $is_active,
                        ':id'  => $id
                    ]);
                    setFlash('Subject "' . $subject_name . '" updated successfully!');
                } catch (Exception $e) {
                    setFlash('Error updating subject: ' . $e->getMessage(), 'error');
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

        <div class="table-search-bar">
            <div class="table-search-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text" id="subjectSearch" placeholder="Search by code, name, department...">
            </div>
            <span class="search-count"></span>
        </div>

        <table data-pagination data-page-size="8" id="subjectTable">
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
                <tr data-search="<?php echo htmlspecialchars(strtolower($subject['subject_code'] . ' ' . $subject['subject_name'] . ' ' . ($subject['department_code'] ?? '') . ' ' . ($subject['department_name'] ?? ''))); ?>">
                    <td data-label="Code"><code><?php echo htmlspecialchars($subject['subject_code']); ?></code></td>
                    <td data-label="Name"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                    <td data-label="Department"><?php echo htmlspecialchars($subject['department_name'] ?? '—'); ?></td>
                    <td data-label="Credits"><?php echo $subject['credits']; ?></td>
                    <td data-label="Level"><?php echo $subject['course_level'] ? 'Year ' . $subject['course_level'] : '—'; ?></td>
                    <td data-label="Status">
                        <span class="attendance-badge status-<?php echo $subject['is_active'] ? 'present' : 'absent'; ?>">
                            <?php echo $subject['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td data-label="Actions">
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $subject['id']; ?>"
                                data-code="<?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES); ?>"
                                data-name="<?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES); ?>"
                                data-department="<?php echo htmlspecialchars($subject['department_name'] ?? '', ENT_QUOTES); ?>"
                                data-department-id="<?php echo (int) $subject['department_id']; ?>"
                                data-description="<?php echo htmlspecialchars($subject['description'] ?? '', ENT_QUOTES); ?>"
                                data-credits="<?php echo $subject['credits']; ?>"
                                data-level="<?php echo (int) $subject['course_level']; ?>"
                                data-active="<?php echo (int) $subject['is_active']; ?>"
                                data-created="<?php echo htmlspecialchars($subject['created_at'] ?? '', ENT_QUOTES); ?>"
                                onclick="viewSubject(this)">View</button>
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $subject['id']; ?>"
                                data-code="<?php echo htmlspecialchars($subject['subject_code'], ENT_QUOTES); ?>"
                                data-name="<?php echo htmlspecialchars($subject['subject_name'], ENT_QUOTES); ?>"
                                data-department-id="<?php echo (int) $subject['department_id']; ?>"
                                data-description="<?php echo htmlspecialchars($subject['description'] ?? '', ENT_QUOTES); ?>"
                                data-credits="<?php echo $subject['credits']; ?>"
                                data-level="<?php echo (int) $subject['course_level']; ?>"
                                data-active="<?php echo (int) $subject['is_active']; ?>"
                                onclick="editSubject(this)">Edit</button>
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
        <h2><?php echo icon('plus', 20); ?> Add New Subject</h2>
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

<!-- View Subject Modal -->
<div id="viewSubjectModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('book-open', 20); ?> Subject Details</h2>
        <div class="detail-list">
            <div class="detail-row"><span class="detail-label">Code</span><span class="detail-value" id="viewSubjCode"></span></div>
            <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value" id="viewSubjName"></span></div>
            <div class="detail-row"><span class="detail-label">Department</span><span class="detail-value" id="viewSubjDept"></span></div>
            <div class="detail-row"><span class="detail-label">Credits</span><span class="detail-value" id="viewSubjCredits"></span></div>
            <div class="detail-row"><span class="detail-label">Level</span><span class="detail-value" id="viewSubjLevel"></span></div>
            <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value" id="viewSubjStatus"></span></div>
            <div class="detail-row"><span class="detail-label">Description</span><span class="detail-value" id="viewSubjDesc"></span></div>
            <div class="detail-row"><span class="detail-label">Created</span><span class="detail-value" id="viewSubjCreated"></span></div>
        </div>
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('viewSubjectModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div id="editSubjectModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('pen-line', 20); ?> Edit Subject</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editSubjId">

            <div class="form-row">
                <div class="form-group">
                    <label>Subject Code</label>
                    <input type="text" name="subject_code" id="editSubjCode" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Credits</label>
                    <input type="number" name="credits" id="editSubjCredits" class="form-control" min="1" max="8" required>
                </div>
            </div>

            <div class="form-group">
                <label>Subject Name</label>
                <input type="text" name="subject_name" id="editSubjName" class="form-control" required>
            </div>

            <div class="form-group">
                <label>Department</label>
                <select name="department_id" id="editSubjDept" class="form-control">
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
                <select name="course_level" id="editSubjLevel" class="form-control">
                    <option value="">-- Any --</option>
                    <?php for ($y = 1; $y <= 5; $y++): ?>
                    <option value="<?php echo $y; ?>">Year <?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="editSubjDesc" class="form-control" rows="3"></textarea>
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="editSubjActive" value="1" checked>
                    Active (available for scheduling)
                </label>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('editSubjectModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function viewSubject(btn) {
        document.getElementById('viewSubjCode').textContent = btn.dataset.code;
        document.getElementById('viewSubjName').textContent = btn.dataset.name;
        document.getElementById('viewSubjDept').textContent = btn.dataset.department || '—';
        document.getElementById('viewSubjCredits').textContent = btn.dataset.credits;
        document.getElementById('viewSubjLevel').textContent = btn.dataset.level ? 'Year ' + btn.dataset.level : '—';
        document.getElementById('viewSubjStatus').textContent = btn.dataset.active === '1' ? 'Active' : 'Inactive';
        document.getElementById('viewSubjDesc').textContent = btn.dataset.description || '—';
        document.getElementById('viewSubjCreated').textContent = btn.dataset.created ? new Date(btn.dataset.created.replace(' ', 'T')).toLocaleString() : '—';
        document.getElementById('viewSubjectModal').style.display = 'block';
    }

    function editSubject(btn) {
        document.getElementById('editSubjId').value = btn.dataset.id;
        document.getElementById('editSubjCode').value = btn.dataset.code;
        document.getElementById('editSubjName').value = btn.dataset.name;
        document.getElementById('editSubjCredits').value = btn.dataset.credits;
        document.getElementById('editSubjDept').value = btn.dataset.departmentId || '';
        document.getElementById('editSubjLevel').value = btn.dataset.level || '';
        document.getElementById('editSubjDesc').value = btn.dataset.description || '';
        document.getElementById('editSubjActive').checked = btn.dataset.active === '1';
        document.getElementById('editSubjectModal').style.display = 'block';
    }

    ['addSubjectModal', 'viewSubjectModal', 'editSubjectModal'].forEach(function(id) {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });

    initTableSearch('subjectSearch', 'subjectTable');
</script>

<?php include '../includes/footer.php'; ?>
