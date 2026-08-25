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

        case 'update':
            $id = intval($_POST['id'] ?? 0);
            $status = sanitize($_POST['status'] ?? 'Enrolled');
            $remarks = sanitize($_POST['remarks'] ?? '');

            $validStatuses = ['Enrolled', 'Dropped', 'Completed', 'Withdrawn'];
            if (!in_array($status, $validStatuses, true)) {
                $status = 'Enrolled';
            }

            if (!$id) {
                setFlash('Invalid enrollment.', 'error');
            } else {
                $stmt = $db->prepare("UPDATE enrollments SET status = :st, remarks = :r WHERE id = :id");
                $stmt->execute([':st' => $status, ':r' => $remarks ?: null, ':id' => $id]);
                setFlash('Enrollment updated to "' . $status . '".');
            }
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
    SELECT e.id, e.status, e.enrolled_at, e.remarks, e.final_score, e.final_grade,
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
                    <td data-label="Student"><?php echo htmlspecialchars($enrollment['student_id'] . ' - ' . $enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></td>
                    <td data-label="Subject"><?php echo htmlspecialchars($enrollment['subject_code'] . ' - ' . $enrollment['subject_name']); ?></td>
                    <td data-label="Section"><code><?php echo htmlspecialchars($enrollment['section_code']); ?></code></td>
                    <td data-label="Schedule"><?php echo htmlspecialchars($enrollment['schedule'] ?? '—'); ?></td>
                    <td data-label="Term"><?php echo htmlspecialchars($enrollment['term_name'] . ' ' . $enrollment['academic_year']); ?></td>
                    <td data-label="Status">
                        <span class="attendance-badge status-<?php echo $enrollment['status'] === 'Enrolled' ? 'present' : 'absent'; ?>">
                            <?php echo htmlspecialchars($enrollment['status']); ?>
                        </span>
                    </td>
                    <td data-label="Enrolled At"><?php echo formatDate($enrollment['enrolled_at']); ?></td>
                    <td data-label="Actions">
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $enrollment['id']; ?>"
                                data-student="<?php echo htmlspecialchars($enrollment['student_id'] . ' - ' . $enrollment['first_name'] . ' ' . $enrollment['last_name'], ENT_QUOTES); ?>"
                                data-subject="<?php echo htmlspecialchars($enrollment['subject_code'] . ' - ' . $enrollment['subject_name'], ENT_QUOTES); ?>"
                                data-section="<?php echo htmlspecialchars($enrollment['section_code'], ENT_QUOTES); ?>"
                                data-schedule="<?php echo htmlspecialchars($enrollment['schedule'] ?? '', ENT_QUOTES); ?>"
                                data-term="<?php echo htmlspecialchars($enrollment['term_name'] . ' ' . $enrollment['academic_year'], ENT_QUOTES); ?>"
                                data-status="<?php echo htmlspecialchars($enrollment['status'], ENT_QUOTES); ?>"
                                data-remarks="<?php echo htmlspecialchars($enrollment['remarks'] ?? '', ENT_QUOTES); ?>"
                                data-final-score="<?php echo $enrollment['final_score'] ?? ''; ?>"
                                data-final-grade="<?php echo htmlspecialchars(formatGrade($enrollment['final_grade'] ?? ''), ENT_QUOTES); ?>"
                                data-enrolled-at="<?php echo htmlspecialchars($enrollment['enrolled_at'], ENT_QUOTES); ?>"
                                onclick="viewEnrollment(this)">View</button>
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $enrollment['id']; ?>"
                                data-status="<?php echo htmlspecialchars($enrollment['status'], ENT_QUOTES); ?>"
                                data-remarks="<?php echo htmlspecialchars($enrollment['remarks'] ?? '', ENT_QUOTES); ?>"
                                onclick="editEnrollment(this)">Edit</button>
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

<!-- View Enrollment Modal -->
<div id="viewEnrollmentModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('clipboard-list', 20); ?> Enrollment Details</h2>
        <div class="detail-list">
            <div class="detail-row"><span class="detail-label">Student</span><span class="detail-value" id="viewEnrStudent"></span></div>
            <div class="detail-row"><span class="detail-label">Subject</span><span class="detail-value" id="viewEnrSubject"></span></div>
            <div class="detail-row"><span class="detail-label">Section</span><span class="detail-value" id="viewEnrSection"></span></div>
            <div class="detail-row"><span class="detail-label">Schedule</span><span class="detail-value" id="viewEnrSchedule"></span></div>
            <div class="detail-row"><span class="detail-label">Term</span><span class="detail-value" id="viewEnrTerm"></span></div>
            <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value" id="viewEnrStatus"></span></div>
            <div class="detail-row"><span class="detail-label">Final Score</span><span class="detail-value" id="viewEnrScore"></span></div>
            <div class="detail-row"><span class="detail-label">Final Grade</span><span class="detail-value" id="viewEnrGrade"></span></div>
            <div class="detail-row"><span class="detail-label">Remarks</span><span class="detail-value" id="viewEnrRemarks"></span></div>
            <div class="detail-row"><span class="detail-label">Enrolled At</span><span class="detail-value" id="viewEnrDate"></span></div>
        </div>
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('viewEnrollmentModal').style.display='none'">Close</button>
        </div>
    </div>
</div>

<!-- Edit Enrollment Modal -->
<div id="editEnrollmentModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('pen-line', 20); ?> Edit Enrollment</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="editEnrId">

            <div class="form-group">
                <label>Status</label>
                <select name="status" id="editEnrStatus" class="form-control">
                    <?php foreach (['Enrolled', 'Dropped', 'Completed', 'Withdrawn'] as $status): ?>
                    <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" id="editEnrRemarks" class="form-control" rows="3" placeholder="Optional notes"></textarea>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('editEnrollmentModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function viewEnrollment(btn) {
        document.getElementById('viewEnrStudent').textContent = btn.dataset.student;
        document.getElementById('viewEnrSubject').textContent = btn.dataset.subject;
        document.getElementById('viewEnrSection').textContent = btn.dataset.section;
        document.getElementById('viewEnrSchedule').textContent = btn.dataset.schedule || '—';
        document.getElementById('viewEnrTerm').textContent = btn.dataset.term;
        document.getElementById('viewEnrStatus').textContent = btn.dataset.status;
        document.getElementById('viewEnrScore').textContent = btn.dataset.finalScore || '—';
        document.getElementById('viewEnrGrade').textContent = btn.dataset.finalGrade || '—';
        document.getElementById('viewEnrRemarks').textContent = btn.dataset.remarks || '—';
        document.getElementById('viewEnrDate').textContent = new Date(btn.dataset.enrolledAt.replace(' ', 'T')).toLocaleString();
        document.getElementById('viewEnrollmentModal').style.display = 'block';
    }

    function editEnrollment(btn) {
        document.getElementById('editEnrId').value = btn.dataset.id;
        document.getElementById('editEnrStatus').value = btn.dataset.status;
        document.getElementById('editEnrRemarks').value = btn.dataset.remarks || '';
        document.getElementById('editEnrollmentModal').style.display = 'block';
    }

    ['enrollModal', 'viewEnrollmentModal', 'editEnrollmentModal'].forEach(function(id) {
        document.getElementById(id).addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
