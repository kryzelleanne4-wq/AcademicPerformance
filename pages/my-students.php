<?php
/**
 * My Students Page (Instructor)
 * Every student enrolled in the sections the logged-in teacher handles,
 * with their section, schedule, contact details and current grade.
 */

require_once '../includes/functions.php';
requireRole('instructor');

$pageTitle = 'My Students';

$db = getDB();
$instructor = currentInstructor();

if (!$instructor) {
    include '../includes/header.php';
    echo '<div class="alert alert-error">Your teacher profile is not linked to a user account yet. '
        . 'Ask the administrator to set it up on the Instructors page.</div>';
    include '../includes/footer.php';
    exit();
}

// Sections this teacher handles (also drives the filter dropdown and the stats).
$sectionStmt = $db->prepare("
    SELECT cs.id, cs.section_code, cs.schedule,
           sub.subject_code, sub.subject_name,
           t.term_name, t.academic_year
    FROM course_sections cs
    JOIN subjects sub ON cs.subject_id = sub.id
    LEFT JOIN academic_terms t ON cs.term_id = t.id
    WHERE cs.instructor_id = :iid
    ORDER BY sub.subject_code, cs.section_code
");
$sectionStmt->execute([':iid' => $instructor['id']]);
$sections = $sectionStmt->fetchAll();

$sectionId = intval($_GET['section_id'] ?? 0);

// One row per enrollment in this teacher's sections.
$sql = "
    SELECT st.id, st.student_id, st.first_name, st.last_name, st.email, st.phone,
           st.year_level, st.status AS student_status, st.student_type,
           b.year_level AS block_year_level, b.block_code, b.block_name,
           d.department_code,
           cs.id AS section_id, cs.section_code, cs.schedule, cs.room,
           sub.subject_code, sub.subject_name,
           t.term_name, t.academic_year,
           e.status AS enrollment_status,
           g.score, g.grade
    FROM enrollments e
    JOIN students st ON e.student_id = st.id
    JOIN course_sections cs ON e.section_id = cs.id
    JOIN subjects sub ON cs.subject_id = sub.id
    LEFT JOIN blocks b ON st.block_id = b.id
    LEFT JOIN departments d ON b.department_id = d.id
    LEFT JOIN academic_terms t ON cs.term_id = t.id
    LEFT JOIN grades g ON g.enrollment_id = e.id
    WHERE cs.instructor_id = :iid AND e.status = 'Enrolled'
";
if ($sectionId) {
    $sql .= ' AND cs.id = :sec';
}
$sql .= ' ORDER BY st.last_name, st.first_name, sub.subject_code';

$stmt = $db->prepare($sql);
$stmt->execute($sectionId ? [':iid' => $instructor['id'], ':sec' => $sectionId] : [':iid' => $instructor['id']]);
$rows = $stmt->fetchAll();

// Excel export of the same rows and columns shown on screen.
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $headers = ['Student ID', 'Name', 'Block', 'Year Level', 'Subject', 'Section',
                'Schedule', 'Email', 'Phone', 'Grade', 'Score (%)', 'Status'];

    $exportRows = [];
    foreach ($rows as $row) {
        $exportRows[] = [
            'student_id' => $row['student_id'],
            'name'       => $row['first_name'] . ' ' . $row['last_name'],
            'block'      => $row['block_code'] ? blockLabel($row['department_code'], $row['block_year_level'], $row['block_code'], $row['block_name']) : '—',
            'year'       => yearOrdinal($row['year_level']) . ' Year',
            'subject'    => $row['subject_code'] . ' - ' . $row['subject_name'],
            'section'    => $row['section_code'],
            'schedule'   => $row['schedule'] ?? '',
            'email'      => $row['email'] ?? '',
            'phone'      => $row['phone'] ?? '',
            'grade'      => formatGrade($row['grade']),
            'score'      => $row['score'] ?? '',
            'status'     => $row['student_status'],
        ];
    }

    exportExcel('my-students', $headers, pickColumns($exportRows, [
        'student_id', 'name', 'block', 'year', 'subject', 'section',
        'schedule', 'email', 'phone', 'grade', 'score', 'status',
    ]));
}

// Summary numbers for the stat cards.
$uniqueStudents = [];
$gradedCount = 0;
$scoreTotal = 0.0;
foreach ($rows as $row) {
    $uniqueStudents[(int) $row['id']] = true;
    if ($row['score'] !== null && $row['score'] !== '') {
        $gradedCount++;
        $scoreTotal += (float) $row['score'];
    }
}
$classAverage = $gradedCount > 0 ? round($scoreTotal / $gradedCount, 2) : null;

include '../includes/header.php';
displayFlash();
?>

<div class="dashboard-stats" style="grid-template-columns: repeat(4, minmax(0, 1fr));">
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('users', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo count($uniqueStudents); ?></h3>
            <p>My Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('book-open', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo count($sections); ?></h3>
            <p>Sections</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('pen-line', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo $gradedCount; ?></h3>
            <p>Graded</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><?php echo icon('bar-chart', 20); ?></div>
        <div class="stat-info">
            <h3><?php echo $classAverage !== null ? $classAverage . '%' : '—'; ?></h3>
            <p>Class Average</p>
        </div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h2><?php echo icon('user', 24); ?> My Students
            <small style="display: block; color: var(--ink-muted); font-size: 12px; font-weight: 400; text-transform: none; letter-spacing: 0;">
                <?php echo count($uniqueStudents); ?> student(s) &middot; <?php echo count($rows); ?> enrollment record(s)
            </small>
        </h2>
        <div class="filter-bar">
            <form method="GET" class="filter-bar">
                <label>Section
                    <select name="section_id" class="form-control">
                        <option value="">All my sections</option>
                        <?php foreach ($sections as $section): ?>
                        <option value="<?php echo (int) $section['id']; ?>" <?php echo (int) $section['id'] === $sectionId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($section['subject_code'] . ' - ' . $section['subject_name'] . ' (' . $section['section_code'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="btn btn-primary btn-sm"><?php echo icon('filter', 14); ?> Filter</button>
                <?php if ($sectionId): ?>
                <a href="my-students.php" class="btn btn-secondary btn-sm">Reset</a>
                <?php endif; ?>
            </form>
            <a href="?export=excel<?php echo $sectionId ? '&section_id=' . $sectionId : ''; ?>" class="btn btn-secondary btn-sm"><?php echo icon('download', 14); ?> Export to Excel</a>
        </div>
    </div>

    <div class="table-search-bar">
        <div class="table-search-wrapper">
            <span class="search-icon"><?php echo icon('search', 16); ?></span>
            <input type="text" id="myStudentsSearch" placeholder="Search by name, ID, subject, section...">
        </div>
        <span class="search-count"></span>
    </div>

    <table data-pagination data-page-size="10" id="myStudentsTable">
        <thead>
            <tr>
                <th>Student ID</th>
                <th>Name</th>
                <th>Block</th>
                <th>Subject</th>
                <th>Section</th>
                <th>Schedule</th>
                <th>Contact</th>
                <th>Grade</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
            <tr data-search="<?php echo htmlspecialchars(strtolower(
                $row['student_id'] . ' ' . $row['first_name'] . ' ' . $row['last_name'] . ' '
                . ($row['email'] ?? '') . ' ' . $row['subject_code'] . ' ' . $row['subject_name'] . ' '
                . $row['section_code']
            )); ?>">
                <td data-label="Student ID"><code><?php echo htmlspecialchars($row['student_id']); ?></code></td>
                <td data-label="Name"><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                <td data-label="Block">
                    <?php echo htmlspecialchars($row['block_code'] ? blockLabel($row['department_code'], $row['block_year_level'], $row['block_code'], $row['block_name']) : '—'); ?>
                </td>
                <td data-label="Subject"><?php echo htmlspecialchars($row['subject_code'] . ' - ' . $row['subject_name']); ?></td>
                <td data-label="Section"><?php echo htmlspecialchars($row['section_code']); ?></td>
                <td data-label="Schedule"><?php echo htmlspecialchars($row['schedule'] ?? '—'); ?></td>
                <td data-label="Contact">
                    <?php if (!empty($row['email'])): ?>
                    <div style="font-size: 13px;"><?php echo htmlspecialchars($row['email']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($row['phone'])): ?>
                    <div style="font-size: 13px; color: var(--ink-muted);"><?php echo htmlspecialchars($row['phone']); ?></div>
                    <?php elseif (empty($row['email'])): ?>
                    <span style="color: var(--ink-muted);">—</span>
                    <?php endif; ?>
                </td>
                <td data-label="Grade">
                    <?php if ($row['grade'] !== null && $row['grade'] !== ''): ?>
                    <span class="grade-badge <?php echo gradeBadgeClass($row['grade']); ?>" title="<?php echo htmlspecialchars(gradeDescriptor($row['grade'])); ?>">
                        <?php echo htmlspecialchars(formatGrade($row['grade'])); ?>
                    </span>
                    <?php if ($row['score'] !== null && $row['score'] !== ''): ?>
                    <div style="font-size: 12px; color: var(--ink-muted);"><?php echo (float) $row['score']; ?>%</div>
                    <?php endif; ?>
                    <?php else: ?>
                    <span style="color: var(--ink-muted);">Not graded</span>
                    <?php endif; ?>
                </td>
                <td data-label="Status">
                    <span class="attendance-badge status-<?php echo $row['student_status'] === 'Active' ? 'present' : 'absent'; ?>">
                        <?php echo htmlspecialchars($row['student_status']); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
