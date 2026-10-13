<?php
/**
 * Subject Detail Page
 * Students see their own grades for the subject; admins and instructors
 * see every student's grades for that subject.
 *
 * When opened from a course card the `section` id is supplied so the page is
 * scoped to that exact class (its room, schedule, instructor and term).
 */

require_once '../includes/functions.php';
requireLogin();

$db = getDB();
$user = currentUser();

$subjectId = intval($_GET['id'] ?? 0);
$sectionId = intval($_GET['section'] ?? 0);

if (!$subjectId) {
    header('Location: subjects.php');
    exit();
}

// Get subject info with its department
$stmt = $db->prepare("
    SELECT s.*, d.department_code, d.department_name
    FROM subjects s
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE s.id = :id
");
$stmt->bindValue(':id', $subjectId, PDO::PARAM_INT);
$stmt->execute();
$subject = $stmt->fetch();

if (!$subject) {
    header('Location: subjects.php');
    exit();
}

$pageTitle = $subject['subject_name'];

// The specific course/section this card represents (optional).
$section = null;
if ($sectionId) {
    $stmt = $db->prepare("
        SELECT cs.*,
               i.first_name, i.last_name, i.title,
               t.term_name, t.academic_year,
               b.year_level, b.block_code, b.block_name,
               r.floor AS room_floor,
               (SELECT COUNT(*) FROM enrollments e WHERE e.section_id = cs.id AND e.status = 'Enrolled') AS enrolled_count
        FROM course_sections cs
        LEFT JOIN instructors i ON cs.instructor_id = i.id
        LEFT JOIN academic_terms t ON cs.term_id = t.id
        LEFT JOIN blocks b ON cs.block_id = b.id
        LEFT JOIN rooms r ON r.room_code = cs.room
        WHERE cs.id = :sid AND cs.subject_id = :subject_id
    ");
    $stmt->execute([':sid' => $sectionId, ':subject_id' => $subjectId]);
    $section = $stmt->fetch() ?: null;

    if (!$section) {
        // Section does not belong to this subject: fall back to the subject view.
        $sectionId = 0;
    }
}

// Facts shown in the course panel.
$classBlock = '';
if ($section) {
    if (!empty($section['block_name'])) {
        $classBlock = $section['block_name'];
    } elseif (!empty($section['block_code'])) {
        $classBlock = yearOrdinal($section['year_level']) . ' Year - Block ' . $section['block_code'];
    }
}

$courseFacts = [];
if ($section) {
    $courseFacts[] = ['Section', $section['section_code'] ?: '—'];
    if ($classBlock !== '') {
        $courseFacts[] = ['Class Block', $classBlock];
    }
}
$courseFacts[] = ['Department', trim(($subject['department_code'] ?? '') . (!empty($subject['department_name']) ? ' — ' . $subject['department_name'] : '')) ?: '—'];
if ($section) {
    $courseFacts[] = ['Instructor', !empty($section['first_name']) ? trim(($section['title'] ? $section['title'] . ' ' : '') . $section['first_name'] . ' ' . $section['last_name']) : '—'];
    $courseFacts[] = ['Schedule', $section['schedule'] ?: 'Not set'];
    $courseFacts[] = ['Room', $section['room'] ?: '—'];
    $courseFacts[] = ['Location', !empty($section['room_floor']) ? $section['room_floor'] : '—'];
    $courseFacts[] = ['Term', !empty($section['term_name']) ? $section['term_name'] . ' ' . $section['academic_year'] : '—'];
    $courseFacts[] = ['Enrolled', ($section['enrolled_count'] ?? 0) . ($section['capacity'] ? ' of ' . (int) $section['capacity'] : '')];
    $courseFacts[] = ['Status', $section['status'] ?: '—'];
}

// Get grades for this subject (scoped to the section and/or the logged-in student).
$scopeSql = '';
$scopeParams = [':subject_id' => $subjectId];
if ($section) {
    $scopeSql .= ' AND g.section_id = :section_id';
    $scopeParams[':section_id'] = $section['id'];
}

if ($user['role'] === 'student') {
    $student = currentStudent();
    $stmt = $db->prepare("
        SELECT g.*, s.first_name, s.last_name, s.student_id as student_number, cs.section_code
        FROM grades g
        JOIN students s ON g.student_id = s.id
        LEFT JOIN course_sections cs ON g.section_id = cs.id
        WHERE g.subject_id = :subject_id AND g.student_id = :sid" . $scopeSql . "
        ORDER BY g.year DESC, g.semester DESC
    ");
    $stmt->bindValue(':subject_id', $subjectId, PDO::PARAM_INT);
    $stmt->bindValue(':sid', $student['id'], PDO::PARAM_INT);
    if ($section) {
        $stmt->bindValue(':section_id', $section['id'], PDO::PARAM_INT);
    }
} else {
    $stmt = $db->prepare("
        SELECT g.*, s.first_name, s.last_name, s.student_id as student_number, cs.section_code
        FROM grades g
        JOIN students s ON g.student_id = s.id
        LEFT JOIN course_sections cs ON g.section_id = cs.id
        WHERE g.subject_id = :subject_id" . $scopeSql . "
        ORDER BY g.score DESC
    ");
    $stmt->bindValue(':subject_id', $subjectId, PDO::PARAM_INT);
    if ($section) {
        $stmt->bindValue(':section_id', $section['id'], PDO::PARAM_INT);
    }
}
$stmt->execute();
$grades = $stmt->fetchAll();

include '../includes/header.php';
displayFlash();
?>

<div class="course-info">
    <div class="course-info-head">
        <div>
            <h2><?php echo htmlspecialchars($subject['subject_name']); ?></h2>
            <p class="course-info-sub">
                <?php echo htmlspecialchars($subject['subject_code']); ?>
                <?php if ($section): ?>
                    &middot; Section <?php echo htmlspecialchars($section['section_code']); ?>
                <?php else: ?>
                    &middot; All sections
                <?php endif; ?>
            </p>
        </div>
        <a href="subjects.php" class="btn btn-secondary">← Back to Subjects</a>
    </div>

    <div class="course-info-grid">
        <?php foreach ($courseFacts as $fact): ?>
        <div class="course-info-item">
            <span class="course-info-label"><?php echo htmlspecialchars($fact[0]); ?></span>
            <span class="course-info-value"><?php echo htmlspecialchars($fact[1]); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h2><?php echo icon('bar-chart', 24); ?> Grades<?php echo $section ? ' — Section ' . htmlspecialchars($section['section_code']) : ''; ?></h2>
    </div>

    <table data-pagination data-page-size="8">
        <thead>
            <tr>
                <?php if ($user['role'] !== 'student'): ?>
                <th>Student ID</th>
                <th>Student Name</th>
                <?php endif; ?>
                <th>Section</th>
                <th>Semester</th>
                <th>Year</th>
                <th>Score</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grades as $grade): ?>
            <tr>
                <?php if ($user['role'] !== 'student'): ?>
                <td data-label="Student ID"><?php echo htmlspecialchars($grade['student_number']); ?></td>
                <td data-label="Student Name"><?php echo htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']); ?></td>
                <?php endif; ?>
                <td data-label="Section"><?php echo htmlspecialchars($grade['section_code'] ?? '—'); ?></td>
                <td data-label="Semester"><?php echo htmlspecialchars($grade['semester']); ?></td>
                <td data-label="Year"><?php echo $grade['year']; ?></td>
                <td data-label="Score"><?php echo $grade['score']; ?></td>
                <td data-label="Grade">
                    <span class="grade-badge <?php echo gradeBadgeClass($grade['grade']); ?>" title="<?php echo htmlspecialchars(gradeDescriptor($grade['grade'])); ?>">
                        <?php echo htmlspecialchars(formatGrade($grade['grade'])); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (empty($grades)): ?>
    <p class="course-empty">No grades recorded yet for this course.</p>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
