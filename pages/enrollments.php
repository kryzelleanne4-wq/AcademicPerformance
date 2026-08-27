<?php
/**
 * Enrollments Management Page (Admin only)
 * Enhanced: stat cards, search/filter, grouped student picker, section capacity.
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
                // Look up the subject for the selected section
                $stmtSubject = $db->prepare("SELECT subject_id, capacity FROM course_sections WHERE id = :id");
                $stmtSubject->execute([':id' => $section_id]);
                $sectionInfo = $stmtSubject->fetch(PDO::FETCH_ASSOC);
                $subjectId = (int) $sectionInfo['subject_id'];
                $capacity = $sectionInfo['capacity'] ? (int) $sectionInfo['capacity'] : null;

                if (!$subjectId) {
                    setFlash('Invalid section selected.', 'error');
                } else {
                    // Count current enrollments to check capacity
                    $currentCount = 0;
                    if ($capacity) {
                        $stmtCount = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE section_id = :sid AND status = 'Enrolled'");
                        $stmtCount->execute([':sid' => $section_id]);
                        $currentCount = (int) $stmtCount->fetchColumn();
                    }

                    // Find all other sections for the same subject
                    $stmtOtherSections = $db->prepare("SELECT id FROM course_sections WHERE subject_id = :sid AND id != :id");
                    $stmtOtherSections->execute([':sid' => $subjectId, ':id' => $section_id]);
                    $otherSectionIds = $stmtOtherSections->fetchAll(PDO::FETCH_COLUMN);

                    $inserted = 0;
                    $skipped = 0;
                    $capacityReached = 0;
                    $stmtEnroll = $db->prepare("INSERT OR IGNORE INTO enrollments (student_id, section_id) VALUES (:sid, :sec)");
                    $stmtCheck = $db->prepare("SELECT id FROM enrollments WHERE student_id = :sid AND section_id = :sec");

                    foreach ($student_ids as $student_id) {
                        // Check capacity
                        if ($capacity && ($currentCount + $inserted) >= $capacity) {
                            $capacityReached++;
                            continue;
                        }

                        // Check if already enrolled in this exact section
                        $stmtCheck->execute([':sid' => $student_id, ':sec' => $section_id]);
                        if ($stmtCheck->fetch()) {
                            $skipped++;
                            continue;
                        }

                        // Check if enrolled in another section of the same subject
                        $alreadyEnrolled = false;
                        foreach ($otherSectionIds as $otherId) {
                            $stmtCheck->execute([':sid' => $student_id, ':sec' => $otherId]);
                            if ($stmtCheck->fetch()) {
                                $alreadyEnrolled = true;
                                break;
                            }
                        }

                        if ($alreadyEnrolled) {
                            $skipped++;
                            continue;
                        }

                        $stmtEnroll->execute([':sid' => $student_id, ':sec' => $section_id]);
                        if ($stmtEnroll->rowCount() > 0) {
                            $inserted++;
                        }
                    }

                    $msg = $inserted . ' student(s) enrolled successfully!';
                    $details = [];
                    if ($skipped > 0) {
                        $details[] = $skipped . ' skipped (already enrolled in this subject)';
                    }
                    if ($capacityReached > 0) {
                        $details[] = $capacityReached . ' skipped (section at capacity)';
                    }
                    if (!empty($details)) {
                        $msg .= ' ' . implode(', ', $details) . '.';
                    }
                    setFlash($msg, $inserted > 0 ? 'success' : 'error');
                }
            }
            header('Location: enrollments.php');
            exit();

        case 'drop':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("UPDATE enrollments SET status = 'Dropped' WHERE id = :id");
            $stmt->execute([':id' => $id]);
            setFlash('Enrollment dropped.');
            header('Location: enrollments.php');
            exit();

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
    }
}

// ── Data for stats & table ──
$allEnrollments = $db->query("
    SELECT e.id, e.status, e.enrolled_at, e.remarks, e.final_score, e.final_grade,
           st.student_id, st.first_name, st.last_name,
           sub.subject_code, sub.subject_name, cs.section_code, cs.schedule, cs.capacity,
           t.term_name, t.academic_year,
           ins.first_name AS instructor_first, ins.last_name AS instructor_last
    FROM enrollments e
    JOIN students st ON e.student_id = st.id
    JOIN course_sections cs ON e.section_id = cs.id
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN academic_terms t ON cs.term_id = t.id
    JOIN instructors ins ON cs.instructor_id = ins.id
    ORDER BY e.enrolled_at DESC
")->fetchAll();

// ── Stats ──
$totalEnrollments = count($allEnrollments);
$activeCount = 0;
$droppedCount = 0;
$completedCount = 0;
$withdrawnCount = 0;
foreach ($allEnrollments as $e) {
    switch ($e['status']) {
        case 'Enrolled': $activeCount++; break;
        case 'Dropped': $droppedCount++; break;
        case 'Completed': $completedCount++; break;
        case 'Withdrawn': $withdrawnCount++; break;
    }
}

// ── Sections with enrollment counts ──
$sections = $db->query("
    SELECT cs.id, cs.section_code, cs.schedule, cs.capacity,
           sub.subject_code, sub.subject_name,
           ins.first_name, ins.last_name, t.term_name, t.academic_year,
           (SELECT COUNT(*) FROM enrollments e WHERE e.section_id = cs.id AND e.status = 'Enrolled') AS enrolled_count
    FROM course_sections cs
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN instructors ins ON cs.instructor_id = ins.id
    JOIN academic_terms t ON cs.term_id = t.id
    ORDER BY t.is_current DESC, sub.subject_code, cs.section_code
")->fetchAll();

// ── Active students grouped by block ──
$activeStudents = $db->query("
    SELECT st.*, d.department_code, b.year_level AS block_year_level,
           b.block_code AS block_code, b.block_name AS block_name
    FROM students st
    LEFT JOIN blocks b ON st.block_id = b.id
    LEFT JOIN departments d ON b.department_id = d.id
    WHERE st.status = 'Active'
    ORDER BY d.department_code, b.year_level, b.block_code, st.last_name, st.first_name
")->fetchAll();

// Group students by block for the modal
$studentsByBlock = [];
$unassignedStudents = [];
foreach ($activeStudents as $student) {
    if ($student['block_id']) {
        $blockKey = blockLabel($student['department_code'], $student['block_year_level'], $student['block_code'], $student['block_name']);
        $studentsByBlock[$blockKey][] = $student;
    } else {
        $unassignedStudents[] = $student;
    }
}
ksort($studentsByBlock);

// ── Unique terms for filter ──
$terms = $db->query("SELECT DISTINCT t.term_name, t.academic_year FROM academic_terms t ORDER BY t.academic_year DESC, t.term_name DESC")->fetchAll();

// Fetch departments for modal filter
$departments = $db->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();

$pageTitle = 'Enrollments';
include '../includes/header.php';
displayFlash();
?>

<style>
/* ── Enrollment-specific enhancements ── */
.enrollment-stats {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 32px;
}
.enrollment-stat {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px;
    border: 1px solid var(--outline-soft);
    border-radius: var(--radius-md);
    background: var(--surface-white);
    transition: box-shadow 150ms ease;
}
.enrollment-stat:hover {
    box-shadow: var(--shadow-hover);
}
.enrollment-stat .stat-icon {
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-md);
}
.enrollment-stat .stat-icon.total { background: var(--surface-container); color: var(--primary); }
.enrollment-stat .stat-icon.active { background: var(--success-soft); color: var(--success); }
.enrollment-stat .stat-icon.dropped { background: var(--warning-soft); color: var(--warning); }
.enrollment-stat .stat-icon.completed { background: var(--primary-soft); color: var(--primary-container); }
.enrollment-stat .stat-icon.withdrawn { background: var(--error-soft); color: var(--error); }
.enrollment-stat .stat-info h3 {
    font-size: 28px;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 2px;
}
.enrollment-stat .stat-info p {
    font-size: 13px;
    color: var(--ink-muted);
    font-weight: 500;
}

/* Status badges with distinct colors */
.enrollment-status {
    display: inline-flex;
    min-width: 52px;
    align-items: center;
    justify-content: center;
    padding: 4px 12px;
    border-radius: var(--radius-pill);
    font-size: 12px;
    font-weight: 700;
    line-height: 16px;
}
.enrollment-status.status-enrolled {
    background: var(--success-soft);
    color: var(--success);
}
.enrollment-status.status-dropped {
    background: var(--warning-soft);
    color: var(--warning);
}
.enrollment-status.status-completed {
    background: var(--primary-soft);
    color: var(--primary-container);
}
.enrollment-status.status-withdrawn {
    background: var(--error-soft);
    color: var(--error);
}

/* Filter bar */
.enrollment-filter-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding: 16px 24px;
    border-bottom: 1px solid var(--outline-soft);
    background: var(--surface-low);
}
.enrollment-filter-bar label {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--ink-muted);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.03em;
}
.enrollment-filter-bar .form-control {
    width: auto;
    min-width: 140px;
    min-height: 34px;
    padding: 4px 10px;
    font-size: 13px;
}
.enrollment-filter-bar .search-input {
    min-width: 220px;
}
.enrollment-filter-bar .search-input svg {
    position: relative;
    top: 2px;
    margin-right: 4px;
}

/* Student picker in modal */
.student-block-group {
    margin-bottom: 16px;
}
.student-block-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    margin-bottom: 4px;
    border-radius: var(--radius-sm);
    background: var(--surface-container);
    color: var(--primary);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    user-select: none;
    transition: background-color 150ms ease;
}
.student-block-header:hover {
    background: var(--surface-high);
}
.student-block-header .count {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: var(--radius-pill);
    background: var(--surface-white);
    color: var(--ink-muted);
}
.student-block-list {
    max-height: 0;
    overflow: hidden;
    transition: max-height 250ms ease;
}
.student-block-list.open {
    max-height: 600px;
    overflow-y: auto;
}
.student-block-list label {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 12px 6px 24px;
    cursor: pointer;
    font-size: 13px;
    line-height: 20px;
    border-radius: var(--radius-sm);
    transition: background-color 100ms ease;
}
.student-block-list label:hover {
    background: var(--surface-low);
}
.student-block-list label input[type="checkbox"] {
    flex-shrink: 0;
}
.student-search-hint {
    padding: 12px;
    color: var(--ink-muted);
    font-size: 13px;
    text-align: center;
}

/* Section capacity bar */
.capacity-info {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    font-size: 12px;
    color: var(--ink-muted);
}
.capacity-bar {
    flex: 1;
    height: 6px;
    border-radius: 3px;
    background: var(--surface-container);
    overflow: hidden;
}
.capacity-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 300ms ease;
}
.capacity-bar-fill.low { background: var(--success); }
.capacity-bar-fill.medium { background: var(--warning); }
.capacity-bar-fill.high { background: var(--error); }

/* Select all / none toggle */
.select-toggle-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    margin-bottom: 8px;
    border: 1px solid var(--outline-soft);
    border-radius: var(--radius-sm);
    background: var(--surface-white);
    font-size: 12px;
}
.select-toggle-bar .toggle-link {
    color: var(--primary);
    font-weight: 600;
    cursor: pointer;
    text-decoration: underline;
}
.select-toggle-bar .toggle-link:hover {
    color: var(--gold);
}
.selected-count {
    color: var(--success);
    font-weight: 700;
}

/* Enrollment modal filters */
.enrollment-modal-filters {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}
.enrollment-modal-filter {
    flex: 1;
    min-width: 120px;
}
.enrollment-modal-filter label {
    display: block;
    margin-bottom: 4px;
    color: var(--ink-muted);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}
.enrollment-modal-filter .form-control {
    min-height: 34px;
    padding: 4px 10px;
    font-size: 13px;
}
.enrollment-modal-filter:first-child {
    flex: 2;
    min-width: 180px;
}

@media (max-width: 1100px) {
    .enrollment-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 768px) {
    .enrollment-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .enrollment-filter-bar {
        padding: 12px 16px;
    }
    .enrollment-filter-bar .search-input {
        min-width: 100%;
    }
}
</style>

<main>
    <!-- ── Stat Cards ── -->
    <div class="enrollment-stats">
        <div class="enrollment-stat">
            <div class="stat-icon total"><?php echo icon('clipboard-list', 22); ?></div>
            <div class="stat-info">
                <h3><?php echo $totalEnrollments; ?></h3>
                <p>Total Enrollments</p>
            </div>
        </div>
        <div class="enrollment-stat">
            <div class="stat-icon active"><?php echo icon('check-circle', 22); ?></div>
            <div class="stat-info">
                <h3><?php echo $activeCount; ?></h3>
                <p>Active</p>
            </div>
        </div>
        <div class="enrollment-stat">
            <div class="stat-icon completed"><?php echo icon('award', 22); ?></div>
            <div class="stat-info">
                <h3><?php echo $completedCount; ?></h3>
                <p>Completed</p>
            </div>
        </div>
        <div class="enrollment-stat">
            <div class="stat-icon dropped"><?php echo icon('clock', 22); ?></div>
            <div class="stat-info">
                <h3><?php echo $droppedCount; ?></h3>
                <p>Dropped</p>
            </div>
        </div>
        <div class="enrollment-stat">
            <div class="stat-icon withdrawn"><?php echo icon('x-circle', 22); ?></div>
            <div class="stat-info">
                <h3><?php echo $withdrawnCount; ?></h3>
                <p>Withdrawn</p>
            </div>
        </div>
    </div>

    <!-- ── Enrollment Table ── -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php echo icon('clipboard-list', 24); ?> Enrollment Records</h2>
            <button class="btn btn-primary" onclick="openEnrollModal()"><?php echo icon('plus', 14); ?> Enroll Students</button>
        </div>

        <!-- Filter Bar -->
        <div class="enrollment-filter-bar">
            <label>
                <?php echo icon('filter', 14); ?> Search
                <input type="text" id="enrollSearch" class="form-control search-input" placeholder="Search by name, student ID, subject...">
            </label>
            <label>
                Status
                <select id="filterStatus" class="form-control" onchange="filterEnrollments()">
                    <option value="">All Statuses</option>
                    <option value="Enrolled">Enrolled</option>
                    <option value="Completed">Completed</option>
                    <option value="Dropped">Dropped</option>
                    <option value="Withdrawn">Withdrawn</option>
                </select>
            </label>
            <label>
                Term
                <select id="filterTerm" class="form-control" onchange="filterEnrollments()">
                    <option value="">All Terms</option>
                    <?php foreach ($terms as $term): ?>
                    <option value="<?php echo htmlspecialchars($term['term_name'] . ' ' . $term['academic_year']); ?>">
                        <?php echo htmlspecialchars($term['term_name'] . ' ' . $term['academic_year']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-secondary btn-sm" onclick="clearFilters()" style="margin-left: auto;">Clear Filters</button>
        </div>

        <div class="table-container">
        <table data-pagination data-page-size="10" id="enrollmentTable">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Subject</th>
                    <th>Instructor</th>
                    <th>Schedule</th>
                    <th>Term</th>
                    <th>Status</th>
                    <th>Enrolled At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allEnrollments as $enrollment): ?>
                <tr data-status="<?php echo htmlspecialchars($enrollment['status']); ?>"
                    data-term="<?php echo htmlspecialchars($enrollment['term_name'] . ' ' . $enrollment['academic_year']); ?>"
                    data-search="<?php echo htmlspecialchars(strtolower($enrollment['student_id'] . ' ' . $enrollment['first_name'] . ' ' . $enrollment['last_name'] . ' ' . $enrollment['subject_code'] . ' ' . $enrollment['subject_name'])); ?>">
                    <td data-label="Student">
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name']); ?></div>
                        <div style="font-size: 12px; color: var(--ink-muted);"><?php echo htmlspecialchars($enrollment['student_id']); ?></div>
                    </td>
                    <td data-label="Subject">
                        <div style="font-weight: 500;"><?php echo htmlspecialchars($enrollment['subject_code']); ?></div>
                        <div style="font-size: 12px; color: var(--ink-muted);"><?php echo htmlspecialchars($enrollment['subject_name']); ?></div>
                    </td>
                    <td data-label="Instructor"><?php echo htmlspecialchars($enrollment['instructor_first'] . ' ' . $enrollment['instructor_last']); ?></td>
                    <td data-label="Schedule"><?php echo htmlspecialchars($enrollment['schedule'] ?? '—'); ?></td>
                    <td data-label="Term"><?php echo htmlspecialchars($enrollment['term_name'] . ' ' . $enrollment['academic_year']); ?></td>
                    <td data-label="Status">
                        <span class="enrollment-status status-<?php echo strtolower($enrollment['status']); ?>">
                            <?php echo htmlspecialchars($enrollment['status']); ?>
                        </span>
                    </td>
                    <td data-label="Enrolled At"><?php echo formatDate($enrollment['enrolled_at']); ?></td>
                    <td data-label="Actions">
                        <button type="button" class="btn btn-secondary btn-sm"
                                data-id="<?php echo $enrollment['id']; ?>"
                                data-student="<?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name'], ENT_QUOTES); ?>"
                                data-student-id="<?php echo htmlspecialchars($enrollment['student_id'], ENT_QUOTES); ?>"
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
                        <button type="button" class="btn btn-danger btn-sm"
                                data-drop-id="<?php echo $enrollment['id']; ?>"
                                data-drop-student="<?php echo htmlspecialchars($enrollment['first_name'] . ' ' . $enrollment['last_name'], ENT_QUOTES); ?>"
                                onclick="confirmDrop(this)">Drop</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</main>

<!-- ── Enroll Modal ── -->
<div id="enrollModal" class="modal-overlay" style="display: none;">
    <div class="modal-box" style="max-width: 820px;">
        <h2><?php echo icon('plus', 20); ?> Enroll Students</h2>

        <!-- Section Selection -->
        <div class="form-group">
            <label>Select Section (Subject + Block)</label>
            <select name="section_id" id="enrollSectionSelect" class="form-control" required onchange="updateCapacityInfo()">
                <option value="">-- Choose a section --</option>
                <?php foreach ($sections as $section): ?>
                <option value="<?php echo $section['id']; ?>"
                        data-capacity="<?php echo $section['capacity'] ?? ''; ?>"
                        data-enrolled="<?php echo $section['enrolled_count']; ?>">
                    <?php echo htmlspecialchars($section['subject_code'] . ' - ' . $section['subject_name']); ?>
                    &nbsp;|&nbsp; <?php echo htmlspecialchars($section['section_code']); ?>
                    &nbsp;|&nbsp; <?php echo htmlspecialchars($section['first_name'] . ' ' . $section['last_name']); ?>
                    &nbsp;|&nbsp; <?php echo htmlspecialchars($section['term_name'] . ' ' . $section['academic_year']); ?>
                    <?php if ($section['schedule']): ?>
                    &nbsp;|&nbsp; <?php echo htmlspecialchars($section['schedule']); ?>
                    <?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div class="capacity-info" id="capacityInfo" style="display: none;">
                <span id="capacityText"></span>
                <div class="capacity-bar">
                    <div class="capacity-bar-fill" id="capacityBarFill" style="width: 0%;"></div>
                </div>
            </div>
        </div>

        <!-- Student Selection -->
        <div class="form-group">
            <label>Select Students to Enroll</label>

            <!-- Filter Bar -->
            <div class="enrollment-modal-filters">
                <div class="enrollment-modal-filter">
                    <label>Search</label>
                    <input type="text" id="studentSearchInput" class="form-control" placeholder="Name or ID..." oninput="filterStudentPicker()">
                </div>
                <div class="enrollment-modal-filter">
                    <label>Department</label>
                    <select id="filterDept" class="form-control" onchange="filterStudentPicker()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['department_code']); ?>">
                            <?php echo htmlspecialchars($dept['department_code'] . ' - ' . $dept['department_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="enrollment-modal-filter">
                    <label>Year</label>
                    <select id="filterYear" class="form-control" onchange="filterStudentPicker()">
                        <option value="">All Years</option>
                        <?php for ($y = 1; $y <= 5; $y++): ?>
                        <option value="<?php echo $y; ?>">Year <?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="select-toggle-bar">
                <span id="selectedCount">0 selected</span>
                <span>
                    <span class="toggle-link" onclick="toggleAllStudents(true)">Select All</span>
                    &nbsp;&middot;&nbsp;
                    <span class="toggle-link" onclick="toggleAllStudents(false)">Clear All</span>
                </span>
            </div>

            <div id="studentPickerList" style="max-height: 360px; overflow-y: auto; border: 1px solid var(--outline-soft); border-radius: var(--radius-sm); padding: 8px;">
                <?php if (!empty($studentsByBlock)): ?>
                    <?php foreach ($studentsByBlock as $blockName => $blockStudents): ?>
                    <div class="student-block-group" data-block-name="<?php echo htmlspecialchars(strtolower($blockName)); ?>">
                        <div class="student-block-header" onclick="toggleBlock(this)">
                            <span><?php echo htmlspecialchars($blockName); ?></span>
                            <span class="count"><?php echo count($blockStudents); ?> students</span>
                        </div>
                        <div class="student-block-list open">
                            <?php foreach ($blockStudents as $student): ?>
                            <label data-student-search="<?php echo htmlspecialchars(strtolower($student['student_id'] . ' ' . $student['first_name'] . ' ' . $student['last_name'])); ?>" data-dept="<?php echo htmlspecialchars($student['department_code'] ?? ''); ?>" data-year="<?php echo htmlspecialchars($student['block_year_level'] ?? ''); ?>">
                                <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" onchange="updateSelectedCount()">
                                <span>
                                    <?php echo htmlspecialchars($student['student_id'] . ' — ' . $student['first_name'] . ' ' . $student['last_name']); ?>
                                    <?php if ($student['student_type'] === 'Irregular'): ?>
                                    <em style="color: var(--warning); font-size: 11px;"> (Irregular)</em>
                                    <?php endif; ?>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($unassignedStudents)): ?>
                <div class="student-block-group" data-block-name="unassigned">
                    <div class="student-block-header" onclick="toggleBlock(this)">
                        <span>Unassigned Students</span>
                        <span class="count"><?php echo count($unassignedStudents); ?> students</span>
                    </div>
                    <div class="student-block-list">
                        <?php foreach ($unassignedStudents as $student): ?>
                        <label data-student-search="<?php echo htmlspecialchars(strtolower($student['student_id'] . ' ' . $student['first_name'] . ' ' . $student['last_name'])); ?>" data-dept="" data-year="">
                            <input type="checkbox" name="student_ids[]" value="<?php echo $student['id']; ?>" onchange="updateSelectedCount()">
                            <span>
                                <?php echo htmlspecialchars($student['student_id'] . ' — ' . $student['first_name'] . ' ' . $student['last_name']); ?>
                                <?php if ($student['student_type'] === 'Irregular'): ?>
                                <em style="color: var(--warning); font-size: 11px;"> (Irregular)</em>
                                <?php endif; ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="student-search-hint" id="noResultsHint" style="display: none;">
                    No students match your search.
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="button" class="btn btn-success" onclick="submitEnrollForm()"><?php echo icon('check-circle', 14); ?> Enroll Selected</button>
            <button type="button" class="btn btn-danger" onclick="closeModal('enrollModal')">Cancel</button>
        </div>

        <form id="enrollForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="enroll">
            <input type="hidden" name="section_id" id="enrollSectionHidden">
            <div id="enrollStudentInputs"></div>
        </form>
    </div>
</div>

<!-- ── View Enrollment Modal ── -->
<div id="viewEnrollmentModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2><?php echo icon('clipboard-list', 20); ?> Enrollment Details</h2>
        <div class="detail-list">
            <div class="detail-row"><span class="detail-label">Student</span><span class="detail-value" id="viewEnrStudent"></span></div>
            <div class="detail-row"><span class="detail-label">Student ID</span><span class="detail-value" id="viewEnrStudentId"></span></div>
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
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewEnrollmentModal')">Close</button>
        </div>
    </div>
</div>

<!-- ── Edit Enrollment Modal ── -->
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
                <textarea name="remarks" id="editEnrRemarks" class="form-control" rows="3" placeholder="Optional notes about this enrollment (e.g. reason for dropping, special circumstances)"></textarea>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success"><?php echo icon('save', 14); ?> Save Changes</button>
                <button type="button" class="btn btn-danger" onclick="closeModal('editEnrollmentModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Drop Confirmation Modal ── -->
<div id="dropConfirmModal" class="modal-overlay" style="display: none;">
    <div class="modal-box" style="max-width: 420px;">
        <h2 style="text-align: center;"><?php echo icon('x-circle', 28); ?></h2>
        <h2 style="text-align: center;">Drop Enrollment?</h2>
        <p style="text-align: center; color: var(--ink-muted); margin-bottom: 1.5rem;">
            Are you sure you want to drop <strong id="dropStudentName"></strong> from this section?
            <br><small>This will mark the enrollment as "Dropped".</small>
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center;">
            <button type="button" class="btn btn-danger" onclick="executeDrop()"><?php echo icon('x-circle', 14); ?> Yes, Drop</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('dropConfirmModal')">Cancel</button>
        </div>
    </div>
</div>

<script>
// ── Close modals on overlay click ──
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}
['enrollModal', 'viewEnrollmentModal', 'editEnrollmentModal', 'dropConfirmModal'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
});

// ── Open enroll modal ──
function openEnrollModal() {
    document.getElementById('enrollSectionSelect').value = '';
    document.getElementById('capacityInfo').style.display = 'none';
    document.getElementById('studentSearchInput').value = '';
    document.getElementById('filterDept').value = '';
    document.getElementById('filterYear').value = '';
    document.querySelectorAll('#enrollForm input[name="student_ids[]"]').forEach(function(cb) { cb.checked = false; });
    updateSelectedCount();
    filterStudentPicker();
    document.getElementById('enrollModal').style.display = 'block';
}

// ── Section capacity display ──
function updateCapacityInfo() {
    var sel = document.getElementById('enrollSectionSelect');
    var opt = sel.options[sel.selectedIndex];
    var info = document.getElementById('capacityInfo');
    if (!opt || !opt.value) {
        info.style.display = 'none';
        return;
    }
    var capacity = parseInt(opt.dataset.capacity) || 0;
    var enrolled = parseInt(opt.dataset.enrolled) || 0;
    var text = document.getElementById('capacityText');
    var barFill = document.getElementById('capacityBarFill');

    if (capacity > 0) {
        var pct = Math.min((enrolled / capacity) * 100, 100);
        var remaining = Math.max(capacity - enrolled, 0);
        text.textContent = enrolled + ' / ' + capacity + ' enrolled (' + remaining + ' slots remaining)';
        barFill.style.width = pct + '%';
        barFill.className = 'capacity-bar-fill ' + (pct < 50 ? 'low' : pct < 80 ? 'medium' : 'high');
        info.style.display = 'flex';
    } else {
        text.textContent = enrolled + ' enrolled (no capacity limit set)';
        barFill.style.width = '0%';
        info.style.display = 'flex';
    }
}

// ── Student block toggle ──
function toggleBlock(header) {
    var list = header.nextElementSibling;
    list.classList.toggle('open');
}

// ── Student search / filter ──
function filterStudentPicker() {
    var query = document.getElementById('studentSearchInput').value.toLowerCase().trim();
    var deptFilter = document.getElementById('filterDept').value;
    var yearFilter = document.getElementById('filterYear').value;
    var groups = document.querySelectorAll('.student-block-group');
    var anyVisible = false;

    groups.forEach(function(group) {
        var labels = group.querySelectorAll('label[data-student-search]');
        var groupVisible = false;
        labels.forEach(function(label) {
            var matchSearch = !query || label.dataset.studentSearch.indexOf(query) !== -1;
            var matchDept = !deptFilter || label.dataset.dept === deptFilter;
            var matchYear = !yearFilter || label.dataset.year === yearFilter;
            var match = matchSearch && matchDept && matchYear;
            label.style.display = match ? 'flex' : 'none';
            if (match) groupVisible = true;
        });
        group.style.display = groupVisible ? 'block' : 'none';
        if (groupVisible) anyVisible = true;

        // Auto-open blocks when filtering
        if ((query || deptFilter || yearFilter) && groupVisible) {
            group.querySelector('.student-block-list').classList.add('open');
        }
    });

    document.getElementById('noResultsHint').style.display = anyVisible ? 'none' : 'block';
}

// ── Select all / none ──
function toggleAllStudents(selectAll) {
    var checkboxes = document.querySelectorAll('#studentPickerList input[type="checkbox"]');
    checkboxes.forEach(function(cb) {
        var label = cb.closest('label');
        if (label && label.style.display !== 'none') {
            cb.checked = selectAll;
        }
    });
    updateSelectedCount();
}

function updateSelectedCount() {
    var count = document.querySelectorAll('#enrollForm input[name="student_ids[]"]:checked, #enrollModal input[name="student_ids[]"]:checked').length;
    document.getElementById('selectedCount').innerHTML = '<span class="selected-count">' + count + '</span> student' + (count !== 1 ? 's' : '') + ' selected';
}

// ── Submit enrollment form ──
function submitEnrollForm() {
    var sectionId = document.getElementById('enrollSectionSelect').value;
    if (!sectionId) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'warning', title: 'Select a section', text: 'Please choose a section before enrolling students.' });
        } else {
            alert('Please select a section.');
        }
        return;
    }

    var checked = document.querySelectorAll('#studentPickerList input[type="checkbox"]:checked');
    if (checked.length === 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'warning', title: 'Select students', text: 'Please select at least one student to enroll.' });
        } else {
            alert('Please select at least one student.');
        }
        return;
    }

    var hidden = document.getElementById('enrollSectionHidden');
    hidden.value = sectionId;

    var container = document.getElementById('enrollStudentInputs');
    container.innerHTML = '';
    checked.forEach(function(cb) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'student_ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });

    document.getElementById('enrollForm').submit();
}

// ── View enrollment ──
function viewEnrollment(btn) {
    document.getElementById('viewEnrStudent').textContent = btn.dataset.student;
    document.getElementById('viewEnrStudentId').textContent = btn.dataset.studentId;
    document.getElementById('viewEnrSubject').textContent = btn.dataset.subject;
    document.getElementById('viewEnrSection').textContent = btn.dataset.section;
    document.getElementById('viewEnrSchedule').textContent = btn.dataset.schedule || '—';
    document.getElementById('viewEnrTerm').textContent = btn.dataset.term;

    // Render status with badge
    var statusEl = document.getElementById('viewEnrStatus');
    statusEl.innerHTML = '<span class="enrollment-status status-' + btn.dataset.status.toLowerCase() + '">' + btn.dataset.status + '</span>';

    document.getElementById('viewEnrScore').textContent = btn.dataset.finalScore || '—';
    document.getElementById('viewEnrGrade').textContent = btn.dataset.finalGrade || '—';
    document.getElementById('viewEnrRemarks').textContent = btn.dataset.remarks || '—';
    try {
        document.getElementById('viewEnrDate').textContent = new Date(btn.dataset.enrolledAt.replace(' ', 'T')).toLocaleString();
    } catch(e) {
        document.getElementById('viewEnrDate').textContent = btn.dataset.enrolledAt;
    }
    document.getElementById('viewEnrollmentModal').style.display = 'block';
}

// ── Edit enrollment ──
function editEnrollment(btn) {
    document.getElementById('editEnrId').value = btn.dataset.id;
    document.getElementById('editEnrStatus').value = btn.dataset.status;
    document.getElementById('editEnrRemarks').value = btn.dataset.remarks || '';
    document.getElementById('editEnrollmentModal').style.display = 'block';
}

// ── Drop confirmation ──
var pendingDropId = null;
function confirmDrop(btn) {
    pendingDropId = btn.dataset.dropId;
    document.getElementById('dropStudentName').textContent = btn.dataset.dropStudent;
    document.getElementById('dropConfirmModal').style.display = 'block';
}
function executeDrop() {
    if (!pendingDropId) return;
    var form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="drop"><input type="hidden" name="id" value="' + pendingDropId + '">';
    document.body.appendChild(form);
    form.submit();
}

// ── Table search & filter ──
var searchInput = document.getElementById('enrollSearch');
searchInput.addEventListener('input', filterEnrollments);

function filterEnrollments() {
    var query = searchInput.value.toLowerCase().trim();
    var statusFilter = document.getElementById('filterStatus').value;
    var termFilter = document.getElementById('filterTerm').value;
    var rows = document.querySelectorAll('#enrollmentTable tbody tr');
    var visible = 0;

    rows.forEach(function(row) {
        var matchSearch = !query || row.dataset.search.indexOf(query) !== -1;
        var matchStatus = !statusFilter || row.dataset.status === statusFilter;
        var matchTerm = !termFilter || row.dataset.term === termFilter;
        var show = matchSearch && matchStatus && matchTerm;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
}

function clearFilters() {
    searchInput.value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterTerm').value = '';
    filterEnrollments();
}

function clearStudentFilters() {
    document.getElementById('studentSearchInput').value = '';
    document.getElementById('filterDept').value = '';
    document.getElementById('filterYear').value = '';
    filterStudentPicker();
}
</script>

<?php include '../includes/footer.php'; ?>
