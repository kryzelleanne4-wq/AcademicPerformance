<?php
/**
 * Schedules Management Page (Admin only)
 * Course sections carry the schedule (days/time), room, instructor, term.
 */

require_once '../includes/functions.php';
requireRole('admin');

$db = getDB();

// Make sure there is at least one academic term to assign sections to.
$termCount = (int) querySingle("SELECT COUNT(*) FROM academic_terms");
if ($termCount === 0) {
    $year = date('Y');
    $nextYear = $year + 1;
    $stmt = $db->prepare("INSERT INTO academic_terms (term_code, term_name, academic_year, start_date, end_date, is_current) VALUES (:c, :n, :y, :s, :e, 1)");
    $stmt->execute([
        ':c' => 'T' . $year . '-1',
        ':n' => 'First Semester',
        ':y' => $year . '-' . $nextYear,
        ':s' => $year . '-08-01',
        ':e' => $nextYear . '-01-31'
    ]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_term':
            $term_code = sanitize($_POST['term_code'] ?? '');
            $term_name = sanitize($_POST['term_name'] ?? '');
            $academic_year = sanitize($_POST['academic_year'] ?? '');
            $start_date = sanitize($_POST['start_date'] ?? '');
            $end_date = sanitize($_POST['end_date'] ?? '');

            if ($term_code === '' || $term_name === '' || $academic_year === '' || !$start_date || !$end_date) {
                setFlash('All term fields are required.', 'error');
            } else {
                try {
                    $db->prepare("UPDATE academic_terms SET is_current = 0")->execute();
                    $stmt = $db->prepare("INSERT INTO academic_terms (term_code, term_name, academic_year, start_date, end_date, is_current) VALUES (:c, :n, :y, :s, :e, 1)");
                    $stmt->execute([':c' => $term_code, ':n' => $term_name, ':y' => $academic_year, ':s' => $start_date, ':e' => $end_date]);
                    setFlash('Term "' . $term_name . '" added and set as current.');
                } catch (Exception $e) {
                    setFlash('Error adding term: ' . $e->getMessage(), 'error');
                }
            }
            header('Location: schedules.php');
            exit();
            break;

        case 'add_section':
            $subject_id = intval($_POST['subject_id'] ?? 0);
            $instructor_id = intval($_POST['instructor_id'] ?? 0);
            $term_id = intval($_POST['term_id'] ?? 0);
            $section_code = sanitize($_POST['section_code'] ?? '');
            $room = sanitize($_POST['room'] ?? '');
            $schedule = sanitize($_POST['schedule'] ?? '');
            $capacity = intval($_POST['capacity'] ?? 0);

            if (!$subject_id || !$instructor_id || !$term_id || $section_code === '') {
                setFlash('Subject, instructor, term and section code are required.', 'error');
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO course_sections (subject_id, instructor_id, term_id, section_code, room, schedule, capacity) VALUES (:s, :i, :t, :c, :r, :sch, :cap)");
                    $stmt->execute([
                        ':s'   => $subject_id,
                        ':i'   => $instructor_id,
                        ':t'   => $term_id,
                        ':c'   => $section_code,
                        ':r'   => $room ?: null,
                        ':sch' => $schedule ?: null,
                        ':cap' => $capacity ?: null
                    ]);
                    setFlash('Schedule created successfully!');
                } catch (Exception $e) {
                    setFlash('Error creating schedule: ' . $e->getMessage(), 'error');
                }
            }
            header('Location: schedules.php');
            exit();
            break;

        case 'delete_section':
            $id = intval($_POST['id'] ?? 0);
            try {
                $db->prepare("DELETE FROM course_sections WHERE id = :id")->execute([':id' => $id]);
                setFlash('Schedule deleted.');
            } catch (Exception $e) {
                setFlash('Cannot delete schedule: ' . $e->getMessage(), 'error');
            }
            header('Location: schedules.php');
            exit();
            break;
    }
}

$terms = $db->query("SELECT * FROM academic_terms ORDER BY is_current DESC, start_date DESC")->fetchAll();
$subjects = $db->query("SELECT * FROM subjects WHERE is_active = 1 ORDER BY subject_code")->fetchAll();
$instructors = $db->query("SELECT * FROM instructors WHERE status = 'Active' ORDER BY last_name")->fetchAll();

$sections = $db->query("
    SELECT cs.*, sub.subject_code, sub.subject_name, ins.first_name, ins.last_name,
           t.term_name, t.academic_year,
           (SELECT COUNT(*) FROM enrollments e WHERE e.section_id = cs.id AND e.status = 'Enrolled') AS enrolled_count
    FROM course_sections cs
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN instructors ins ON cs.instructor_id = ins.id
    JOIN academic_terms t ON cs.term_id = t.id
    ORDER BY t.is_current DESC, sub.subject_code, cs.section_code
")->fetchAll();

$pageTitle = 'Schedules';
include '../includes/header.php';
displayFlash();
?>

<main>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php echo icon('calendar', 24); ?> Class Schedules</h2>
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-secondary" onclick="document.getElementById('addTermModal').style.display='block'"><?php echo icon('plus', 14); ?> Add Term</button>
                <button class="btn btn-primary" onclick="document.getElementById('addSectionModal').style.display='block'"><?php echo icon('plus', 14); ?> Add Schedule</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Section</th>
                    <th>Subject</th>
                    <th>Instructor</th>
                    <th>Term</th>
                    <th>Schedule</th>
                    <th>Room</th>
                    <th>Capacity</th>
                    <th>Enrolled</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sections as $section): ?>
                <tr>
                    <td data-label="Section"><code><?php echo htmlspecialchars($section['section_code']); ?></code></td>
                    <td data-label="Subject"><?php echo htmlspecialchars($section['subject_code'] . ' - ' . $section['subject_name']); ?></td>
                    <td data-label="Instructor"><?php echo htmlspecialchars($section['first_name'] . ' ' . $section['last_name']); ?></td>
                    <td data-label="Term"><?php echo htmlspecialchars($section['term_name'] . ' ' . $section['academic_year']); ?></td>
                    <td data-label="Schedule"><?php echo htmlspecialchars($section['schedule'] ?? '—'); ?></td>
                    <td data-label="Room"><?php echo htmlspecialchars($section['room'] ?? '—'); ?></td>
                    <td data-label="Capacity"><?php echo $section['capacity'] ?: '—'; ?></td>
                    <td data-label="Enrolled"><?php echo $section['enrolled_count']; ?></td>
                    <td data-label="Actions">
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this schedule?')">
                            <input type="hidden" name="action" value="delete_section">
                            <input type="hidden" name="id" value="<?php echo $section['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add Schedule Modal -->
<div id="addSectionModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2>Add Class Schedule</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_section">

            <div class="form-row">
                <div class="form-group">
                    <label>Subject</label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                        <option value="<?php echo $subject['id']; ?>">
                            <?php echo htmlspecialchars($subject['subject_code'] . ' - ' . $subject['subject_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Section Code</label>
                    <input type="text" name="section_code" class="form-control" placeholder="e.g. A" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Instructor</label>
                    <select name="instructor_id" class="form-control" required>
                        <option value="">-- Select Instructor --</option>
                        <?php foreach ($instructors as $instructor): ?>
                        <option value="<?php echo $instructor['id']; ?>">
                            <?php echo htmlspecialchars($instructor['first_name'] . ' ' . $instructor['last_name'] . ' (' . $instructor['employee_id'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Term</label>
                    <select name="term_id" class="form-control" required>
                        <?php foreach ($terms as $term): ?>
                        <option value="<?php echo $term['id']; ?>" <?php echo $term['is_current'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($term['term_name'] . ' ' . $term['academic_year'] . ($term['is_current'] ? ' (Current)' : '')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Schedule (Days / Time)</label>
                    <input type="text" name="schedule" class="form-control" placeholder="e.g. Mon & Wed, 8:00 - 9:30 AM">
                </div>
                <div class="form-group">
                    <label>Room</label>
                    <input type="text" name="room" class="form-control" placeholder="e.g. Room 201">
                </div>
            </div>

            <div class="form-group">
                <label>Capacity</label>
                <input type="number" name="capacity" class="form-control" min="1" placeholder="e.g. 40">
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Schedule</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addSectionModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Term Modal -->
<div id="addTermModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2>Add Academic Term</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_term">

            <div class="form-row">
                <div class="form-group">
                    <label>Term Code</label>
                    <input type="text" name="term_code" class="form-control" placeholder="e.g. T2026-2" required>
                </div>
                <div class="form-group">
                    <label>Term Name</label>
                    <input type="text" name="term_name" class="form-control" placeholder="e.g. Second Semester" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Academic Year</label>
                    <input type="text" name="academic_year" class="form-control" placeholder="e.g. 2026-2027" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Term</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addTermModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    ['addSectionModal', 'addTermModal'].forEach(function(id) {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
