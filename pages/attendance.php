<?php
/**
 * Attendance Recording Page
 * Teachers record attendance for their sections; admins can use any section.
 * The exported Excel file matches the on-screen record columns.
 */

require_once '../includes/functions.php';
requireRole('admin', 'instructor');

$db = getDB();
$user = currentUser();
$instructor = currentInstructor();

// Sections available to this user.
if ($user['role'] === 'admin') {
    $sectionsStmt = $db->query("
        SELECT cs.id, cs.section_code, cs.schedule, sub.subject_code, sub.subject_name,
               ins.first_name, ins.last_name, t.term_name, t.academic_year
        FROM course_sections cs
        JOIN subjects sub ON cs.subject_id = sub.id
        JOIN instructors ins ON cs.instructor_id = ins.id
        JOIN academic_terms t ON cs.term_id = t.id
        ORDER BY t.is_current DESC, sub.subject_code, cs.section_code
    ");
} else {
    $sectionsStmt = $db->prepare("
        SELECT cs.id, cs.section_code, cs.schedule, sub.subject_code, sub.subject_name,
               ins.first_name, ins.last_name, t.term_name, t.academic_year
        FROM course_sections cs
        JOIN subjects sub ON cs.subject_id = sub.id
        JOIN instructors ins ON cs.instructor_id = ins.id
        JOIN academic_terms t ON cs.term_id = t.id
        WHERE cs.instructor_id = :iid
        ORDER BY t.is_current DESC, sub.subject_code, cs.section_code
    ");
    $sectionsStmt->execute([':iid' => $instructor['id']]);
}
$sections = $sectionsStmt->fetchAll();

$sectionId = intval($_GET['section_id'] ?? $_POST['section_id'] ?? 0);
$date = sanitize($_GET['date'] ?? $_POST['date'] ?? date('Y-m-d'));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $section_id = intval($_POST['section_id'] ?? 0);
    $attendance_date = sanitize($_POST['date'] ?? date('Y-m-d'));
    $statuses = (array) ($_POST['status'] ?? []);
    $remarks = (array) ($_POST['remarks'] ?? []);

    if (!$section_id) {
        setFlash('Select a section first.', 'error');
    } else {
        $validStatuses = ['Present', 'Absent', 'Late', 'Excused'];
        $upsert = $db->prepare("
            INSERT INTO attendance (student_id, section_id, attendance_date, status, remarks, recorded_by)
            VALUES (:sid, :sec, :d, :st, :r, :rb)
            ON CONFLICT(student_id, section_id, attendance_date)
            DO UPDATE SET status = excluded.status, remarks = excluded.remarks, recorded_by = excluded.recorded_by, updated_at = CURRENT_TIMESTAMP
        ");
        $recordedBy = $user['role'] === 'instructor' ? $instructor['id'] : null;
        foreach ($statuses as $studentId => $status) {
            $studentId = intval($studentId);
            $status = in_array($status, $validStatuses, true) ? $status : 'Present';
            $remark = sanitize($remarks[$studentId] ?? '');
            $upsert->execute([
                ':sid' => $studentId,
                ':sec' => $section_id,
                ':d'   => $attendance_date,
                ':st'  => $status,
                ':r'   => $remark ?: null,
                ':rb'  => $recordedBy
            ]);
        }
        setFlash('Attendance saved for ' . date('M d, Y', strtotime($attendance_date)) . '.');
    }
    header('Location: attendance.php?section_id=' . $sectionId . '&date=' . urlencode($attendance_date));
    exit();
}

// Excel export of the records list (same columns as the on-screen table).
if (isset($_GET['export']) && $_GET['export'] === 'excel' && $sectionId) {
    $stmt = $db->prepare("
        SELECT st.student_id, st.first_name, st.last_name,
               sub.subject_code || ' - ' || sub.subject_name AS subject,
               cs.section_code, a.attendance_date, a.status, a.remarks
        FROM attendance a
        JOIN students st ON a.student_id = st.id
        JOIN course_sections cs ON a.section_id = cs.id
        JOIN subjects sub ON cs.subject_id = sub.id
        WHERE a.section_id = :sid
        ORDER BY a.attendance_date DESC, st.last_name
    ");
    $stmt->execute([':sid' => $sectionId]);
    exportExcel('attendance-section-' . $sectionId, [
        'Student ID', 'First Name', 'Last Name', 'Subject', 'Section', 'Date', 'Status', 'Remarks'
    ], pickColumns($stmt->fetchAll(), [
        'student_id', 'first_name', 'last_name', 'subject', 'section_code', 'attendance_date', 'status', 'remarks'
    ]));
}

// Roster of enrolled students for the selected section + date.
$roster = [];
if ($sectionId) {
    $stmt = $db->prepare("
        SELECT st.id, st.student_id, st.first_name, st.last_name,
               a.status, a.remarks
        FROM enrollments e
        JOIN students st ON e.student_id = st.id
        LEFT JOIN attendance a ON a.student_id = st.id AND a.section_id = :sec AND a.attendance_date = :d
        WHERE e.section_id = :sec2 AND e.status = 'Enrolled'
        ORDER BY st.last_name, st.first_name
    ");
    $stmt->execute([':sec' => $sectionId, ':d' => $date, ':sec2' => $sectionId]);
    $roster = $stmt->fetchAll();
}

// Recent attendance history for the selected section.
$history = [];
if ($sectionId) {
    $stmt = $db->prepare("
        SELECT st.student_id, st.first_name, st.last_name, a.attendance_date, a.status, a.remarks
        FROM attendance a
        JOIN students st ON a.student_id = st.id
        WHERE a.section_id = :sid
        ORDER BY a.attendance_date DESC, st.last_name
        LIMIT 100
    ");
    $stmt->execute([':sid' => $sectionId]);
    $history = $stmt->fetchAll();
}

$pageTitle = 'Attendance';
include '../includes/header.php';
displayFlash();
?>

<main>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2>✅ Record Attendance</h2>
        </div>
        <div style="padding: 24px;">
            <form method="GET" class="form-row" style="align-items: flex-end;">
                <div class="form-group" style="flex: 2;">
                    <label>Section</label>
                    <select name="section_id" class="form-control" required>
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['id']; ?>" <?php echo $section['id'] === $sectionId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($section['subject_code'] . ' - ' . $section['subject_name'] . ' (' . $section['section_code'] . ') — ' . $section['first_name'] . ' ' . $section['last_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date); ?>" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Load Roster</button>
                </div>
            </form>

            <?php if ($sectionId && $roster): ?>
            <form method="POST" style="margin-top: 24px;">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="section_id" value="<?php echo $sectionId; ?>">
                <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">

                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roster as $student): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($student['student_id']); ?></code></td>
                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            <td>
                                <select name="status[<?php echo $student['id']; ?>]" class="form-control">
                                    <?php foreach (['Present', 'Absent', 'Late', 'Excused'] as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo ($student['status'] ?? 'Present') === $status ? 'selected' : ''; ?>>
                                        <?php echo $status; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="remarks[<?php echo $student['id']; ?>]" class="form-control"
                                       value="<?php echo htmlspecialchars($student['remarks'] ?? ''); ?>" placeholder="Optional">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 16px;">
                    <button type="submit" class="btn btn-success">Save Attendance</button>
                </div>
            </form>
            <?php elseif ($sectionId): ?>
                <p style="margin-top: 24px; color: var(--ink-muted);">No enrolled students in this section.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($sectionId): ?>
    <div class="card" style="margin-top: 24px;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2>📄 Attendance Records</h2>
            <a href="?section_id=<?php echo $sectionId; ?>&export=excel" class="btn btn-secondary btn-sm">⬇ Export to Excel</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $record): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($record['student_id']); ?></code></td>
                    <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                    <td><?php echo formatDate($record['attendance_date']); ?></td>
                    <td>
                        <span class="attendance-badge status-<?php echo strtolower($record['status']); ?>">
                            <?php echo htmlspecialchars($record['status']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($record['remarks'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>
