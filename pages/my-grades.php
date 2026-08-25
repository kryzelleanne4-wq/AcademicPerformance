<?php
/**
 * My Grades Page (Student)
 * Students view their own grades.
 */

require_once '../includes/functions.php';
requireRole('student');

$pageTitle = 'My Grades';

$db = getDB();
$student = currentStudent();

// Excel export of the same table shown on screen (with component breakdown).
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $stmt = $db->prepare("
        SELECT g.*, sub.subject_code, sub.subject_name, sub.credits, cs.subject_id
        FROM grades g
        JOIN subjects sub ON g.subject_id = sub.id
        LEFT JOIN course_sections cs ON g.section_id = cs.id
        WHERE g.student_id = :sid
        ORDER BY g.year DESC, g.semester DESC, sub.subject_name
    ");
    $rows = $stmt->execute([':sid' => $student['id']]) ? $stmt->fetchAll() : [];

    // Determine the union of components across the student's subjects for export headers.
    $exportComponents = [];
    foreach ($rows as $row) {
        $sid = (int) ($row['subject_id'] ?? 0);
        if ($sid) {
            foreach (getComponents($db, $sid) as $c) {
                $exportComponents[$c['id']] = $c['component_name'] . ' (/ ' . (float) $c['max_score'] . ')';
            }
        }
    }

    $headers = ['Subject Code', 'Subject Name', 'Semester', 'Year'];
    foreach ($exportComponents as $label) {
        $headers[] = $label;
    }
    $headers[] = 'Overall (%)';
    $headers[] = 'Grade';

    $exportRows = [];
    foreach ($rows as $row) {
        $sid = (int) ($row['subject_id'] ?? 0);
        $comps = getComponents($db, $sid);
        $gid = $row['id'];
        $line = ['subject_code' => $row['subject_code'], 'subject_name' => $row['subject_name'], 'semester' => $row['semester'], 'year' => $row['year']];
        foreach ($comps as $comp) {
            $csStmt = $db->prepare("SELECT score FROM grade_scores WHERE grade_id = :gid AND component_id = :cid");
            $csStmt->execute([':gid' => $gid, ':cid' => $comp['id']]);
            $line['comp_' . $comp['id']] = $csStmt->fetchColumn() ?? '';
        }
        $line['overall'] = $row['score'];
        $line['grade'] = formatGrade($row['grade']);
        $exportRows[] = $line;
    }

    $keys = ['subject_code', 'subject_name', 'semester', 'year'];
    foreach (array_keys($exportComponents) as $cid) {
        $keys[] = 'comp_' . $cid;
    }
    $keys[] = 'overall';
    $keys[] = 'grade';

    exportExcel('my-grades', $headers, pickColumns($exportRows, $keys));
}

// Get grades for the logged-in student only.
$stmt = $db->prepare("
    SELECT g.*,
           sub.subject_code,
           sub.subject_name,
           sub.credits,
           cs.section_code,
           cs.subject_id
    FROM grades g
    JOIN subjects sub ON g.subject_id = sub.id
    LEFT JOIN course_sections cs ON g.section_id = cs.id
    WHERE g.student_id = :sid
    ORDER BY g.year DESC, g.semester DESC, sub.subject_name
");
$stmt->execute([':sid' => $student['id']]);
$grades = $stmt->fetchAll();

// Load per-component scores for each grade (for the breakdown columns).
$componentsBySubject = [];
$gradeCompScores = [];
foreach ($grades as $grade) {
    $subjectId = (int) ($grade['subject_id'] ?? 0);
    if ($subjectId && !isset($componentsBySubject[$subjectId])) {
        $componentsBySubject[$subjectId] = getComponents($db, $subjectId);
    }
    if (isset($grade['id']) && $grade['id']) {
        $csStmt = $db->prepare("SELECT component_id, score FROM grade_scores WHERE grade_id = :gid");
        $csStmt->execute([':gid' => $grade['id']]);
        foreach ($csStmt->fetchAll() as $cs) {
            $gradeCompScores[$grade['id']][$cs['component_id']] = $cs['score'];
        }
    }
}

include '../includes/header.php';
displayFlash();
?>

<div class="table-container">
    <div class="table-header">
        <h2><?php echo icon('file-text', 24); ?> My Grades</h2>
        <a href="?export=excel" class="btn btn-secondary btn-sm"><?php echo icon('download', 14); ?> Export to Excel</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Subject Code</th>
                <th>Subject Name</th>
                <th>Section</th>
                <th>Semester</th>
                <th>Year</th>
                <th>Component Scores</th>
                <th>Overall (%)</th>
                <th>Grade</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grades as $grade): ?>
            <?php
            $subjectId = (int) ($grade['subject_id'] ?? 0);
            $comps = $componentsBySubject[$subjectId] ?? [];
            $gid = $grade['id'];
            ?>
            <tr>
                <td data-label="Subject Code"><?php echo htmlspecialchars($grade['subject_code']); ?></td>
                <td data-label="Subject Name"><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                <td data-label="Section"><?php echo htmlspecialchars($grade['section_code'] ?? '—'); ?></td>
                <td data-label="Semester"><?php echo htmlspecialchars($grade['semester']); ?></td>
                <td data-label="Year"><?php echo $grade['year']; ?></td>
                <td data-label="Component Scores">
                    <?php if ($comps && !empty($gradeCompScores[$gid])): ?>
                    <div style="display:flex;flex-direction:column;gap:2px;font-size:13px;">
                        <?php foreach ($comps as $comp): ?>
                        <span style="white-space:nowrap;">
                            <?php echo htmlspecialchars($comp['component_name']); ?>:
                            <strong><?php
                                $val = $gradeCompScores[$gid][$comp['id']] ?? null;
                                echo $val !== null ? $val : '—';
                            ?></strong>
                            <span style="color:var(--ink-muted);">/<?php echo (float) $comp['max_score']; ?></span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <span style="color:var(--ink-muted);">—</span>
                    <?php endif; ?>
                </td>
                <td data-label="Overall (%)"><?php echo $grade['score'] ?? '—'; ?></td>
                <td data-label="Grade">
                    <span class="grade-badge <?php echo gradeBadgeClass($grade['grade']); ?>" title="<?php echo htmlspecialchars(gradeDescriptor($grade['grade'])); ?>">
                        <?php echo htmlspecialchars(formatGrade($grade['grade'])); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include '../includes/footer.php'; ?>
