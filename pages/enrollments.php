<?php
/**
 * Enrollments Management Page (Admin only)
 * Enroll students into course sections.
 */

require_once '../includes/functions.php';
requireRole('admin');

$db = getDB();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'enroll':
            $section_id = intval($_POST['section_id'] ?? 0);
            $student_ids = array_map('intval', (array) ($_POST['student_ids'] ?? []));

            if (!$section_id || empty($student_ids)) {
                setFlash('Select a section and at least one student.', 'error');
            } else {
                $inserted = 0;
                $stmt = $db->prepare("INSERT OR IGNORE INTO enrollments (student_id, section_id) VALUES (:sid, :sec)");
                foreach ($student_ids as $student_id) {
                    $stmt->execute([':sid' => $student_id, ':sec' => $section_id]);
                    if ($stmt->rowCount() > 0) {
                        $inserted++;
                    }
                }
                setFlash($inserted . ' student(s) enrolled successfully!');
            }
            header('Location: enrollments.php');
            exit();
            break;

        case 'drop':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("UPDATE enrollments SET status = 'Dropped' WHERE id = :id");
            $stmt->execute([':id' => $id]);
            setFlash('Enrollment dropped.');
            header('Location: enrollments.php');
            exit();
            break;
    }
}

$sections = $db->query("
    SELECT cs.id, cs.section_code, cs.schedule, sub.subject_code, sub.subject_name,
           ins.first_name, ins.last_name, t.term_name, t.academic_year
    FROM course_sections cs
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN instructors ins ON cs.instructor_id = ins.id
    JOIN academic_terms t ON cs.term_id = t.id
    ORDER BY t.is_current DESC, sub.subject_code, cs.section_code
")->fetchAll();

$activeStudents = $db->query("SELECT * FROM students WHERE status = 'Active' ORDER BY last_name, first_name")->fetchAll();

$enrollments = $db->query("
    SELECT e.id, e.status, e.enrolled_at,
           st.student_id, st.first_name, st.last_name,
           sub.subject_code, sub.subject_name, cs.section_code, cs.schedule,
           t.term_name, t.academic_year
    FROM enrollments e
    JOIN students st ON e.student_id = st.id
    JOIN course_sections cs ON e.section_id = cs.id
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN academic_terms t ON cs.term_id = t.id
    ORDER BY e.enrolled_at DESC
")->fetchAll();

$pageTitle = 'Enrollments';
include '../includes/header.php';
displayFlash();
?>

<main>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php echo icon('clipboard-list', 24); ?> Enroll Students</h2>
            <button class="btn btn-primary" onclick="document.getElementById('enrollModal').style.display='block'"><?php echo icon('plus', 14); ?> Enroll Students</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Subject</th>
                    <th>Section</th>
                    <th>Schedule</th>
                    <th>Term</th>
                    <th>Status</th>
                    <th>Enrolled At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enrollments as $enrollment): ?>
                <tr>
                    <td><?php echo htmlspecialchars($enrollment['student_id'] . ' - ' . $enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($enrollment['subject_code'] . ' - ' . $enrollment['subject_name']); ?></td>
                    <td><code><?php echo htmlspecialchars($enrollment['section_code']); ?></code></td>
                    <td><?php echo htmlspecialchars($enrollment['schedule'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($enrollment['term_name'] . ' ' . $enrollment['academic_year']); ?></td>
                    <td>
                        <span class="attendance-badge status-<?php echo $enrollment['status'] === 'Enrolled' ? 'present' : 'absent'; ?>">
                            <?php echo htmlspecialchars($enrollment['status']); ?>
                        </span>
                    </td>
                    <td><?php echo formatDate($enrollment['enrolled_at']); ?></td>
                    <td>
                        <?php if ($enrollment['status'] === 'Enrolled'): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Drop this enrollment?')">
                            <input type="hidden" name="action" value="drop">
                            <input type="hidden" name="id" value="<?php echo $enrollment['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Drop</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Enroll Modal -->
<div id="enrollModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2>Enroll Students</h2>
        <form method="POST">
            <input type="hidden" name="action" value="enroll">

            <div class="form-group">
                <label>Section</label>
                <select name="section_id" class="form-control" required>
                    <option value="">-- Select Section --</option>
                    <?php foreach ($sections as $section): ?>
                    <option value="<?php echo $section['id']; ?>">
                        <?php echo htmlspecialchars($section['subject_code'] . ' - ' . $section['subject_name'] . ' (' . $section['section_code'] . ') — ' . $section['first_name'] . ' ' . $section['last_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Students</label>
                <div style="max-height: 320px; overflow-y: auto; border: 1px solid var(--outline-soft); border-radius: 4px; padding: 12px;">
                    <?php foreach ($activeStudents as $student): ?>
                    <label style="display: flex; align-items: center; gap: 8px; padding: 6px 0; cursor: pointer;">
                        <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>">
                        <span><?php echo htmlspecialchars($student['student_id'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Enroll Selected</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('enrollModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('enrollModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
