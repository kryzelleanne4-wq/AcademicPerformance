<?php
/**
 * Blocks Management Page (Admin only)
 * Class blocks group students by department and year level, e.g. "BSIT 1st Year - Block 1".
 * Each block can hold many students (regular and irregular), many schedules,
 * and many instructors/lessons.
 */

require_once '../includes/functions.php';
requireRole('admin');

$db = getDB();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $department_id = intval($_POST['department_id'] ?? 0);
            $year_level = intval($_POST['year_level'] ?? 0);
            $block_code = sanitize($_POST['block_code'] ?? '');
            $block_name = sanitize($_POST['block_name'] ?? '');

            if (!$department_id || !$year_level || $block_code === '') {
                setFlash('Department, year level and block code are required.', 'error');
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO blocks (department_id, year_level, block_code, block_name) VALUES (:d, :y, :c, :n)");
                    $stmt->execute([':d' => $department_id, ':y' => $year_level, ':c' => $block_code, ':n' => $block_name ?: null]);
                    setFlash('Block "' . blockLabel('', $year_level, $block_code) . '" added successfully!');
                } catch (Exception $e) {
                    setFlash('Error adding block: ' . $e->getMessage(), 'error');
                }
            }
            header('Location: blocks.php');
            exit();
            break;

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $department_id = intval($_POST['department_id'] ?? 0);
            $year_level = intval($_POST['year_level'] ?? 0);
            $block_code = sanitize($_POST['block_code'] ?? '');
            $block_name = sanitize($_POST['block_name'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (!$id || !$department_id || !$year_level || $block_code === '') {
                setFlash('Department, year level and block code are required.', 'error');
            } else {
                try {
                    $stmt = $db->prepare("UPDATE blocks SET department_id = :d, year_level = :y, block_code = :c, block_name = :n, is_active = :ia, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                    $stmt->execute([':d' => $department_id, ':y' => $year_level, ':c' => $block_code, ':n' => $block_name ?: null, ':ia' => $is_active, ':id' => $id]);
                    setFlash('Block updated successfully!');
                } catch (Exception $e) {
                    setFlash('Error updating block: ' . $e->getMessage(), 'error');
                }
            }
            header('Location: blocks.php');
            exit();
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            try {
                $stmt = $db->prepare("DELETE FROM blocks WHERE id = :id");
                $stmt->execute([':id' => $id]);
                setFlash('Block deleted.');
            } catch (Exception $e) {
                setFlash('Cannot delete block: ' . $e->getMessage(), 'error');
            }
            header('Location: blocks.php');
            exit();
            break;
    }
}

$departments = $db->query("SELECT * FROM departments ORDER BY department_name")->fetchAll();

$blocks = $db->query("
    SELECT b.*, d.department_code, d.department_name,
           (SELECT COUNT(*) FROM students s WHERE s.block_id = b.id) AS student_count,
           (SELECT COUNT(*) FROM students s WHERE s.block_id = b.id AND s.student_type = 'Regular') AS regular_count,
           (SELECT COUNT(*) FROM students s WHERE s.block_id = b.id AND s.student_type = 'Irregular') AS irregular_count,
           (SELECT COUNT(*) FROM course_sections cs WHERE cs.block_id = b.id) AS schedule_count
    FROM blocks b
    JOIN departments d ON b.department_id = d.id
    ORDER BY d.department_name, b.year_level, b.block_code
")->fetchAll();

$pageTitle = 'Blocks';
include '../includes/header.php';
displayFlash();
?>

<main>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php echo icon('grid', 24); ?> Class Blocks</h2>
            <button class="btn btn-primary" onclick="document.getElementById('addBlockModal').style.display='block'"><?php echo icon('plus', 14); ?> Add Block</button>
        </div>

        <div class="table-search-bar">
            <div class="table-search-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text" id="blockSearch" placeholder="Search by block, department, year...">
            </div>
            <span class="search-count"></span>
        </div>

        <table data-pagination data-page-size="8" id="blockTable">
            <thead>
                <tr>
                    <th>Block</th>
                    <th>Department</th>
                    <th>Year Level</th>
                    <th>Students</th>
                    <th>Regular</th>
                    <th>Irregular</th>
                    <th>Schedules</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($blocks as $block): ?>
                <?php $label = blockLabel($block['department_code'], $block['year_level'], $block['block_code'], $block['block_name']); ?>
                <tr data-search="<?php echo htmlspecialchars(strtolower($block['block_code'] . ' ' . $block['department_code'] . ' ' . $block['department_name'] . ' ' . yearOrdinal($block['year_level']) . ' ' . $label)); ?>">
                    <td data-label="Block"><code><?php echo htmlspecialchars($block['block_code']); ?></code></td>
                    <td data-label="Department"><?php echo htmlspecialchars($block['department_code'] . ' - ' . $block['department_name']); ?></td>
                    <td data-label="Year Level"><?php echo htmlspecialchars(yearOrdinal($block['year_level']) . ' Year'); ?></td>
                    <td data-label="Students"><?php echo $block['student_count']; ?></td>
                    <td data-label="Regular"><?php echo $block['regular_count']; ?></td>
                    <td data-label="Irregular"><?php echo $block['irregular_count']; ?></td>
                    <td data-label="Schedules"><?php echo $block['schedule_count']; ?></td>
                    <td data-label="Status">
                        <span class="attendance-badge status-<?php echo $block['is_active'] ? 'present' : 'absent'; ?>">
                            <?php echo $block['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td data-label="Actions">
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $block['id']; ?>"
                                data-code="<?php echo htmlspecialchars($block['block_code'], ENT_QUOTES); ?>"
                                data-name="<?php echo htmlspecialchars($label, ENT_QUOTES); ?>"
                                data-department="<?php echo htmlspecialchars($block['department_code'] . ' - ' . $block['department_name'], ENT_QUOTES); ?>"
                                data-year="<?php echo htmlspecialchars(yearOrdinal($block['year_level']) . ' Year', ENT_QUOTES); ?>"
                                data-students="<?php echo $block['student_count']; ?>"
                                data-regular="<?php echo $block['regular_count']; ?>"
                                data-irregular="<?php echo $block['irregular_count']; ?>"
                                data-schedules="<?php echo $block['schedule_count']; ?>"
                                data-status="<?php echo $block['is_active'] ? 'Active' : 'Inactive'; ?>"
                                data-created="<?php echo htmlspecialchars($block['created_at'] ?? '', ENT_QUOTES); ?>"
                                onclick="viewBlock(this)">View</button>
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $block['id']; ?>"
                                data-department-id="<?php echo (int) $block['department_id']; ?>"
                                data-year-level="<?php echo (int) $block['year_level']; ?>"
                                data-code="<?php echo htmlspecialchars($block['block_code'], ENT_QUOTES); ?>"
                                data-name="<?php echo htmlspecialchars($block['block_name'] ?? '', ENT_QUOTES); ?>"
                                data-active="<?php echo (int) $block['is_active']; ?>"
                                onclick="editBlock(this)">Edit</button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this block? Students and schedules linked to it will keep their records but lose the block assignment.')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $block['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add Block Modal -->
<div id="addBlockModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('plus', 20); ?> Add New Block</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label>Department</label>
                <select name="department_id" class="form-control" required>
                    <option value="">-- Select Department --</option>
                    <?php foreach ($departments as $department): ?>
                    <option value="<?php echo $department['id']; ?>">
                        <?php echo htmlspecialchars($department['department_code'] . ' - ' . $department['department_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Year Level</label>
                    <select name="year_level" class="form-control" required>
                        <option value="">-- Year --</option>
                        <?php for ($y = 1; $y <= 5; $y++): ?>
                        <option value="<?php echo $y; ?>"><?php echo htmlspecialchars(yearOrdinal($y)); ?> Year</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Block Code</label>
                    <input type="text" name="block_code" class="form-control" placeholder="e.g. 1, 2, A" required>
                </div>
            </div>

            <div class="form-group">
                <label>Block Name (optional)</label>
                <input type="text" name="block_name" class="form-control" placeholder="e.g. BSIT 1A">
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Block</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addBlockModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- View Block Modal -->
<div id="viewBlockModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('grid', 20); ?> Block Details</h2>
        <div class="detail-list">
            <div class="detail-row"><span class="detail-label">Block</span><span class="detail-value" id="viewBlockName"></span></div>
            <div class="detail-row"><span class="detail-label">Department</span><span class="detail-value" id="viewBlockDept"></span></div>
            <div class="detail-row"><span class="detail-label">Year Level</span><span class="detail-value" id="viewBlockYear"></span></div>
            <div class="detail-row"><span class="detail-label">Students</span><span class="detail-value" id="viewBlockStudents"></span></div>
            <div class="detail-row"><span class="detail-label">Regular</span><span class="detail-value" id="viewBlockRegular"></span></div>
            <div class="detail-row"><span class="detail-label">Irregular</span><span class="detail-value" id="viewBlockIrregular"></span></div>
            <div class="detail-row"><span class="detail-label">Schedules</span><span class="detail-value" id="viewBlockSchedules"></span></div>
            <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value" id="viewBlockStatus"></span></div>
            <div class="detail-row"><span class="detail-label">Created</span><span class="detail-value" id="viewBlockCreated"></span></div>
        </div>
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('viewBlockModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<!-- Edit Block Modal -->
<div id="editBlockModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('pen-line', 20); ?> Edit Block</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editBlockId">

            <div class="form-group">
                <label>Department</label>
                <select name="department_id" id="editBlockDept" class="form-control" required>
                    <?php foreach ($departments as $department): ?>
                    <option value="<?php echo $department['id']; ?>">
                        <?php echo htmlspecialchars($department['department_code'] . ' - ' . $department['department_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Year Level</label>
                    <select name="year_level" id="editBlockYear" class="form-control" required>
                        <?php for ($y = 1; $y <= 5; $y++): ?>
                        <option value="<?php echo $y; ?>"><?php echo htmlspecialchars(yearOrdinal($y)); ?> Year</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Block Code</label>
                    <input type="text" name="block_code" id="editBlockCode" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label>Block Name (optional)</label>
                <input type="text" name="block_name" id="editBlockName" class="form-control" placeholder="e.g. BSIT 1A">
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="editBlockActive" value="1" checked>
                    Active (available for students and schedules)
                </label>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('editBlockModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function viewBlock(btn) {
        document.getElementById('viewBlockName').textContent = btn.dataset.name;
        document.getElementById('viewBlockDept').textContent = btn.dataset.department;
        document.getElementById('viewBlockYear').textContent = btn.dataset.year;
        document.getElementById('viewBlockStudents').textContent = btn.dataset.students;
        document.getElementById('viewBlockRegular').textContent = btn.dataset.regular;
        document.getElementById('viewBlockIrregular').textContent = btn.dataset.irregular;
        document.getElementById('viewBlockSchedules').textContent = btn.dataset.schedules;
        document.getElementById('viewBlockStatus').textContent = btn.dataset.status;
        document.getElementById('viewBlockCreated').textContent = btn.dataset.created ? new Date(btn.dataset.created.replace(' ', 'T')).toLocaleString() : '—';
        document.getElementById('viewBlockModal').style.display = 'block';
    }

    function editBlock(btn) {
        document.getElementById('editBlockId').value = btn.dataset.id;
        document.getElementById('editBlockDept').value = btn.dataset.departmentId;
        document.getElementById('editBlockYear').value = btn.dataset.yearLevel;
        document.getElementById('editBlockCode').value = btn.dataset.code;
        document.getElementById('editBlockName').value = btn.dataset.name || '';
        document.getElementById('editBlockActive').checked = btn.dataset.active === '1';
        document.getElementById('editBlockModal').style.display = 'block';
    }

    ['addBlockModal', 'viewBlockModal', 'editBlockModal'].forEach(function(id) {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });

    initTableSearch('blockSearch', 'blockTable');
</script>

<?php include '../includes/footer.php'; ?>