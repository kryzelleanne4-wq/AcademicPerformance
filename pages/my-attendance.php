<?php
/**
 * My Attendance Page (Student)
 * Students view their own attendance records.
 */

require_once '../includes/functions.php';
requireRole('student');

$db = getDB();
$student = currentStudent();

// Date-range filter.
$dateFrom = sanitize($_GET['from'] ?? '');
$dateTo = sanitize($_GET['to'] ?? '');
if ($dateFrom && !strtotime($dateFrom)) {
    $dateFrom = '';
}
if ($dateTo && !strtotime($dateTo)) {
    $dateTo = '';
}

// Build the shared record query (used by both the table and the export).
function myAttendanceSql() {
    global $dateFrom, $dateTo;
    $sql = "
        SELECT a.attendance_date, a.status, a.remarks,
               cs.section_code, cs.schedule,
               sub.subject_code, sub.subject_name
        FROM attendance a
        JOIN course_sections cs ON a.section_id = cs.id
        JOIN subjects sub ON cs.subject_id = sub.id
        WHERE a.student_id = :sid
    ";
    if ($dateFrom) {
        $sql .= ' AND a.attendance_date >= :from';
    }
    if ($dateTo) {
        $sql .= ' AND a.attendance_date <= :to';
    }
    $sql .= ' ORDER BY a.attendance_date DESC';
    return $sql;
}

// Excel export of the same filtered table shown on screen.
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $sql = myAttendanceSql();
    $params = [':sid' => $student['id']];
    if ($dateFrom) {
        $params[':from'] = $dateFrom;
    }
    if ($dateTo) {
        $params[':to'] = $dateTo;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    exportExcel('my-attendance', [
        'Date', 'Subject Code', 'Subject Name', 'Section', 'Schedule', 'Status', 'Remarks'
    ], pickColumns($stmt->fetchAll(), [
        'attendance_date', 'subject_code', 'subject_name', 'section_code', 'schedule', 'status', 'remarks'
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

// Attendance records for the student's enrolled sections (optionally filtered).
$recordsStmt = $db->prepare(myAttendanceSql());
$recordsParams = [':sid' => $student['id']];
if ($dateFrom) {
    $recordsParams[':from'] = $dateFrom;
}
if ($dateTo) {
    $recordsParams[':to'] = $dateTo;
}
$recordsStmt->execute($recordsParams);
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
        <h2><?php echo icon('calendar-check', 24); ?> My Attendance
            <small style="display: block; color: var(--ink-muted); font-size: 12px; font-weight: 400; text-transform: none; letter-spacing: 0;">
                <?php echo count($records); ?> record(s)
            </small>
        </h2>
        <div class="filter-bar">
            <form method="GET" class="filter-bar">
                <label>From
                    <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </label>
                <label>To
                    <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($dateTo); ?>">
                </label>
                <button type="submit" class="btn btn-primary btn-sm"><?php echo icon('filter', 14); ?> Filter</button>
                <?php if ($dateFrom || $dateTo): ?>
                <a href="my-attendance.php" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </form>
            <a href="?export=excel<?php echo $dateFrom ? '&from=' . urlencode($dateFrom) : ''; ?><?php echo $dateTo ? '&to=' . urlencode($dateTo) : ''; ?>" class="btn btn-secondary btn-sm"><?php echo icon('download', 14); ?> Export to Excel</a>
        </div>
    </div>

    <table data-pagination data-page-size="8">
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
                <td data-label="Date"><?php echo formatDate($record['attendance_date']); ?></td>
                <td data-label="Subject"><?php echo htmlspecialchars($record['subject_code'] . ' - ' . $record['subject_name']); ?></td>
                <td data-label="Section"><code><?php echo htmlspecialchars($record['section_code']); ?></code></td>
                <td data-label="Schedule"><?php echo htmlspecialchars($record['schedule'] ?? '—'); ?></td>
                <td data-label="Status">
                    <span class="attendance-badge status-<?php echo strtolower($record['status']); ?>">
                        <?php echo htmlspecialchars($record['status']); ?>
                    </span>
                </td>
                <td data-label="Remarks"><?php echo htmlspecialchars($record['remarks'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
