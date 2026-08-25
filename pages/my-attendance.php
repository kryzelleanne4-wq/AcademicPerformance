<?php
/**
 * My Attendance Page (Student)
 * Students view their own attendance records.
 */

require_once '../includes/functions.php';
requireRole('student');

$db = getDB();
$student = currentStudent();

// Excel export of the same table shown on screen.
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $stmt = $db->prepare("
        SELECT st.student_id, st.first_name, st.last_name,
               sub.subject_code || ' - ' || sub.subject_name AS subject,
               cs.section_code, a.attendance_date, a.status, a.remarks
        FROM attendance a
        JOIN students st ON a.student_id = st.id
        JOIN course_sections cs ON a.section_id = cs.id
        JOIN subjects sub ON cs.subject_id = sub.id
        WHERE a.student_id = :sid
        ORDER BY a.attendance_date DESC
    ");
    $stmt->execute([':sid' => $student['id']]);
    exportExcel('my-attendance', [
        'Student ID', 'First Name', 'Last Name', 'Subject', 'Section', 'Date', 'Status', 'Remarks'
    ], pickColumns($stmt->fetchAll(), [
        'student_id', 'first_name', 'last_name', 'subject', 'section_code', 'attendance_date', 'status', 'remarks'
    ]));
}

// Attendance summary for the stats row.
$summaryStmt = $db->prepare("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) AS present,
           SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) AS late,
           SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) AS absent,
           SUM(CASE WHEN status = 'Excused' THEN 1 ELSE 0 END) AS excused
    FROM attendance WHERE student_id = :sid
");
$summaryStmt->execute([':sid' => $student['id']]);
$summary = $summaryStmt->fetch();

// Attendance records for the student's enrolled sections.
$recordsStmt = $db->prepare("
    SELECT a.attendance_date, a.status, a.remarks,
           cs.section_code, cs.schedule,
           sub.subject_code, sub.subject_name
    FROM attendance a
    JOIN course_sections cs ON a.section_id = cs.id
    JOIN subjects sub ON cs.subject_id = sub.id
    WHERE a.student_id = :sid
    ORDER BY a.attendance_date DESC
");
$recordsStmt->execute([':sid' => $student['id']]);
$records = $recordsStmt->fetchAll();

$pageTitle = 'My Attendance';
include '../includes/header.php';
displayFlash();
?>

<div class="dashboard-stats" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('calendar', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo (int) ($summary['total'] ?? 0); ?></h3>
            <p>Total Records</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('check-circle', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo (int) ($summary['present'] ?? 0); ?></h3>
            <p>Present</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('clock', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo (int) ($summary['late'] ?? 0); ?></h3>
            <p>Late</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('x-circle', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo (int) ($summary['absent'] ?? 0); ?></h3>
            <p>Absent</p>
        </div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h2><?php echo icon('calendar-check', 24); ?> My Attendance</h2>
        <a href="?export=excel" class="btn btn-secondary btn-sm"><?php echo icon('download', 14); ?> Export to Excel</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Subject</th>
                <th>Section</th>
                <th>Schedule</th>
                <th>Status</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($records as $record): ?>
            <tr>
                <td><?php echo formatDate($record['attendance_date']); ?></td>
                <td><?php echo htmlspecialchars($record['subject_code'] . ' - ' . $record['subject_name']); ?></td>
                <td><code><?php echo htmlspecialchars($record['section_code']); ?></code></td>
                <td><?php echo htmlspecialchars($record['schedule'] ?? '—'); ?></td>
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

<?php include '../includes/footer.php'; ?>
