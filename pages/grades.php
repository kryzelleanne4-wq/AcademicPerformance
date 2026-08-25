<?php
/**
 * Grades Score Sheet (Teacher)
 * An Excel-like, editable per-section score sheet. Teachers enter component
 * scores (e.g. Quiz over 50, Final Exam over 100) for each student and the
 * overall weighted score and letter grade update automatically.
 * Admins can view the score sheet but only teachers can edit it.
 */

require_once '../includes/functions.php';
requireRole('admin', 'instructor');

$db = getDB();
$user = currentUser();
$instructor = currentInstructor();
$canRecord = ($user['role'] === 'instructor');

// Sections available to this user (used to scope the score sheet).
if ($user['role'] === 'admin') {
    $sectionsStmt = $db->query("
        SELECT cs.id, cs.section_code, sub.subject_code, sub.subject_name,
               ins.first_name, ins.last_name, t.term_name, t.academic_year, cs.subject_id, cs.term_id
        FROM course_sections cs
        JOIN subjects sub ON cs.subject_id = sub.id
        JOIN instructors ins ON cs.instructor_id = ins.id
        JOIN academic_terms t ON cs.term_id = t.id
        ORDER BY t.is_current DESC, sub.subject_code, cs.section_code
    ");
} else {
    $sectionsStmt = $db->prepare("
        SELECT cs.id, cs.section_code, sub.subject_code, sub.subject_name,
               ins.first_name, ins.last_name, t.term_name, t.academic_year, cs.subject_id, cs.term_id
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

$sectionId = intval($_GET['section_id'] ?? 0);

// Semesters from the current term (or a fallback).
$selectedSection = null;
foreach ($sections as $section) {
    if ((int) $section['id'] === $sectionId) {
        $selectedSection = $section;
        break;
    }
}

// Handle save of the whole score sheet for a section.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'save_sheet') {
    if (!$canRecord) {
        setFlash('Admins can view the score sheet but only teachers can record scores.', 'error');
        header('Location: grades.php' . ($sectionId ? '?section_id=' . $sectionId : ''));
        exit();
    }

    $sectionId = intval($_POST['section_id'] ?? 0);
    $semester = sanitize($_POST['semester'] ?? 'First Semester');
    $year = intval($_POST['year'] ?? date('Y'));
    $scores = (array) ($_POST['scores'] ?? []);      // [enrollment_row_key][component_id] = score
    $savedCount = 0;

    $classSectionsStmt = $db->prepare("SELECT * FROM course_sections WHERE id = :id");
    $classSectionsStmt->execute([':id' => $sectionId]);
    $clsSection = $classSectionsStmt->fetch();

    if (!$clsSection) {
        setFlash('Section not found.', 'error');
        header('Location: grades.php');
        exit();
    }

    ensureComponents($db, $clsSection['subject_id']);
    $components = getComponents($db, $clsSection['subject_id']);

    try {
        $db->beginTransaction();

        foreach ($scores as $key => $componentScores) {
            // key is "enrollmentId:studentId"
            $parts = explode(':', (string) $key);
            $enrollmentId = isset($parts[0]) ? (int) $parts[0] : 0;
            $studentId = isset($parts[1]) ? (int) $parts[1] : 0;
            if (!$enrollmentId || !$studentId) {
                continue;
            }

            // Build the per-component score map for this student.
            $scoreMap = [];
            foreach ((array) $componentScores as $cid => $value) {
                $cid = (int) $cid;
                $value = trim((string) $value);
                $scoreMap[$cid] = ($value !== '') ? (float) $value : null;
            }

            $overall = computeOverallScore($components, $scoreMap);

            if ($overall === null) {
                // No scores entered yet for this row: skip.
                continue;
            }

            // Upsert the grade row for this student+section.
            $gradeStmt = $db->prepare("
                SELECT id FROM grades
                WHERE student_id = :sid AND section_id = :sec AND semester = :sem AND year = :yr
                LIMIT 1
            ");
            $gradeStmt->execute([
                ':sid' => $studentId, ':sec' => $sectionId,
                ':sem' => $semester, ':yr' => $year
            ]);
            $gradeId = $gradeStmt->fetchColumn();

            if ($gradeId) {
                $upd = $db->prepare("UPDATE grades SET score = :score, grade = :grade, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                $upd->execute([':score' => $overall, ':grade' => calculateGrade($overall), ':id' => $gradeId]);
            } else {
                $ins = $db->prepare("
                    INSERT INTO grades (student_id, subject_id, section_id, enrollment_id, instructor_id, semester, year, score, grade)
                    VALUES (:sid, :subj, :sec, :enc, :ins, :sem, :yr, :score, :grade)
                ");
                $ins->execute([
                    ':sid' => $studentId, ':subj' => $clsSection['subject_id'], ':sec' => $sectionId,
                    ':enc' => $enrollmentId, ':ins' => $clsSection['instructor_id'],
                    ':sem' => $semester, ':yr' => $year, ':score' => $overall, ':grade' => calculateGrade($overall)
                ]);
                $gradeId = (int) $db->lastInsertId();
            }

            // Upsert each component score.
            $compUpsert = $db->prepare("
                INSERT INTO grade_scores (grade_id, component_id, score)
                VALUES (:gid, :cid, :score)
                ON CONFLICT(grade_id, component_id)
                DO UPDATE SET score = excluded.score, updated_at = CURRENT_TIMESTAMP
            ");
            foreach ($components as $component) {
                $val = $scoreMap[$component['id']] ?? null;
                $compUpsert->execute([':gid' => $gradeId, ':cid' => $component['id'], ':score' => $val]);
            }

            $savedCount++;
        }

        // Mirror overall score onto the enrollment record.
        $mirror = $db->prepare("
            UPDATE enrollments SET final_score = (
                SELECT score FROM grades g WHERE g.enrollment_id = enrollments.id LIMIT 1
            ), final_grade = (
                SELECT grade FROM grades g WHERE g.enrollment_id = enrollments.id LIMIT 1
            ) WHERE id IN (SELECT id FROM enrollments WHERE section_id = :sec AND status != 'Dropped')
        ");
        $mirror->execute([':sec' => $sectionId]);

        $db->commit();
        setFlash('Score sheet saved for ' . $savedCount . ' student(s).');
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlash('Error saving score sheet: ' . $e->getMessage(), 'error');
    }

    header('Location: grades.php?section_id=' . $sectionId);
    exit();
}

// Handle assessment component management (add/update/delete/order).
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'save_components') {
    if (!$canRecord) {
        setFlash('Only teachers can manage assessment components.', 'error');
        header('Location: grades.php' . ($sectionId ? '?section_id=' . $sectionId : ''));
        exit();
    }

    $subjectId = intval($_POST['subject_id'] ?? 0);
    if (!$subjectId) {
        setFlash('Subject required.', 'error');
        header('Location: grades.php' . ($sectionId ? '?section_id=' . $sectionId : ''));
        exit();
    }

    $names = (array) ($_POST['name'] ?? []);
    $maxes = (array) ($_POST['max'] ?? []);
    $weights = (array) ($_POST['weight'] ?? []);
    $ids = (array) ($_POST['cid'] ?? []);
    $deletes = (array) ($_POST['delete'] ?? []);

    try {
        $db->beginTransaction();

        // First handle deletions.
        foreach ($deletes as $cid) {
            deleteComponent($db, (int) $cid);
        }

        // Re-fetch current components (some may have been deleted).
        $existing = $db->query("SELECT * FROM assessment_components WHERE subject_id = " . $subjectId . " ORDER BY sort_order, id")->fetchAll();
        $existingByCid = [];
        foreach ($existing as $c) {
            $existingByCid[(int) $c['id']] = $c;
        }

        // Update existing components.
        $order = 0;
        foreach ($ids as $cid) {
            $cid = (int) $cid;
            if (!isset($existingByCid[$cid])) {
                continue; // deleted this round
            }
            $name = sanitize($names[$cid] ?? $existingByCid[$cid]['component_name']);
            $max = (float) ($maxes[$cid] ?? $existingByCid[$cid]['max_score']);
            $weight = (float) ($weights[$cid] ?? $existingByCid[$cid]['weight']);
            if ($name === '' || $max <= 0 || $weight < 0) {
                throw new Exception('Each component needs a name, a max score > 0, and a weight >= 0.');
            }
            $stmt = $db->prepare("UPDATE assessment_components SET component_name = :n, max_score = :m, weight = :w, sort_order = :o, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
            $stmt->execute([':n' => $name, ':m' => $max, ':w' => $weight, ':o' => $order, ':id' => $cid]);
            $order++;
        }

        // Add new components (those provided with a name but no existing id).
        foreach ($names as $key => $name) {
            if (is_numeric($key) && $key > 0) {
                continue; // keys that are component ids are updates handled above
            }
            $name = sanitize($name);
            $max = (float) ($maxes[$key] ?? 0);
            $weight = (float) ($weights[$key] ?? 0);
            if ($name === '' || $max <= 0 || $weight < 0) {
                continue;
            }
            $stmt = $db->prepare("INSERT INTO assessment_components (subject_id, component_name, max_score, weight, sort_order) VALUES (:s, :n, :m, :w, :o)");
            $stmt->execute([':s' => $subjectId, ':n' => $name, ':m' => $max, ':w' => $weight, ':o' => $order]);
            $order++;
        }

        // Recompute overall scores for all grades in this subject.
        $gradeIds = $db->query("SELECT id FROM grades WHERE subject_id = " . $subjectId)->fetchAll(PDO::FETCH_COLUMN);
        foreach ($gradeIds as $gid) {
            recalcGrade($db, (int) $gid);
        }

        $db->commit();
        setFlash('Assessment components saved. Grades were recomputed.');
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        setFlash('Error saving components: ' . $e->getMessage(), 'error');
    }

    header('Location: grades.php?section_id=' . $sectionId);
    exit();
}

// Build the score sheet grid for the selected section.
$sheet = [];        // rows: student + per-component scores + overall/grade
$components = [];   // columns for this section's subject
if ($selectedSection) {
    ensureComponents($db, $selectedSection['subject_id']);
    $components = getComponents($db, $selectedSection['subject_id']);

    // Load grades for this section keyed by (semester -> student/section).
    $gradeMap = [];
    $gStmt = $db->prepare("SELECT * FROM grades WHERE section_id = :sec ORDER BY year DESC, semester");
    $gStmt->execute([':sec' => $sectionId]);
    $grades = $gStmt->fetchAll();
    foreach ($grades as $g) {
        $gradeMap[$g['semester'] . '|' . $g['enrollment_id']] = $g;
    }

    // Load component scores per grade.
    $compScoreMap = [];
    if ($grades) {
        $ids = array_column($grades, 'id');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $csStmt = $db->prepare("SELECT grade_id, component_id, score FROM grade_scores WHERE grade_id IN ($ph)");
        $csStmt->execute($ids);
        foreach ($csStmt->fetchAll() as $cs) {
            $compScoreMap[$cs['grade_id']][$cs['component_id']] = $cs['score'];
        }
    }

    // Students enrolled in this section.
    $rosterStmt = $db->prepare("
        SELECT st.id AS student_id, st.student_id AS student_number, st.first_name, st.last_name, e.id AS enrollment_id
        FROM enrollments e
        JOIN students st ON e.student_id = st.id
        WHERE e.section_id = :sec AND e.status != 'Dropped'
        ORDER BY st.last_name, st.first_name
    ");
    $rosterStmt->execute([':sec' => $sectionId]);
    $enrollees = $rosterStmt->fetchAll();

    // Try to preserve the semester/year of an existing grade for this section;
    // otherwise fall back to the current term's semester or 'First Semester'.
    $semester = 'First Semester';
    $year = (int) date('Y');
    if ($grades) {
        $semester = $grades[0]['semester'];
        $year = (int) $grades[0]['year'];
    } elseif ($selectedSection['term_name']) {
        // Map known term names to a semester label.
        $tname = strtolower($selectedSection['term_name']);
        if (strpos($tname, 'second') !== false) {
            $semester = 'Second Semester';
        } elseif (strpos($tname, 'summer') !== false) {
            $semester = 'Summer';
        }
    }

    foreach ($enrollees as $row) {
        $key = $semester . '|' . $row['enrollment_id'];
        $grade = $gradeMap[$key] ?? null;
        $gradeId = $grade['id'] ?? null;

        $compScores = [];
        foreach ($components as $component) {
            $compScores[$component['id']] = $gradeId && isset($compScoreMap[$gradeId][$component['id']])
                ? $compScoreMap[$gradeId][$component['id']]
                : '';
        }

        $sheet[] = [
            'student_id'       => $row['student_id'],
            'student_number'   => $row['student_number'],
            'first_name'       => $row['first_name'],
            'last_name'        => $row['last_name'],
            'enrollment_id'    => $row['enrollment_id'],
            'grade_id'         => $gradeId,
            'overall'          => $grade ? $grade['score'] : '',
            'letter'           => $grade ? $grade['grade'] : '',
            'comp_scores'      => $compScores
        ];
    }
}

// Recent grade records (accordion / list below the sheet).
if ($user['role'] === 'admin') {
    $gradesList = $db->query("
        SELECT g.*, s.student_id AS student_number, s.first_name, s.last_name,
               sub.subject_code, sub.subject_name, cs.section_code
        FROM grades g
        JOIN students s ON g.student_id = s.id
        JOIN subjects sub ON g.subject_id = sub.id
        LEFT JOIN course_sections cs ON g.section_id = cs.id
        ORDER BY g.year DESC, g.semester DESC, s.last_name
    ")->fetchAll();
} else {
    $instructorSectionIds = array_column($sections, 'id');
    $inPlaceholders = implode(',', array_fill(0, max(1, count($instructorSectionIds)), '?'));
    $stmt = $db->prepare("
        SELECT g.*, s.student_id AS student_number, s.first_name, s.last_name,
               sub.subject_code, sub.subject_name, cs.section_code
        FROM grades g
        JOIN students s ON g.student_id = s.id
        JOIN subjects sub ON g.subject_id = sub.id
        LEFT JOIN course_sections cs ON g.section_id = cs.id
        WHERE g.instructor_id = :iid OR g.section_id IN ($inPlaceholders)
        ORDER BY g.year DESC, g.semester DESC, s.last_name
    ");
    $stmt->execute(array_merge([':iid' => $instructor['id']], $instructorSectionIds ?: [0]));
    $gradesList = $stmt->fetchAll();
}

// Excel export of the score sheet (same columns as on screen).
if (isset($_GET['export']) && $_GET['export'] === 'excel' && $selectedSection) {
    $headers = ['Student ID', 'Name'];
    foreach ($components as $component) {
        $headers[] = $component['component_name'] . ' (/' . (float) $component['max_score'] . ')';
    }
    $headers[] = 'Overall (%)';
    $headers[] = 'Grade';

    $rows = [];
    foreach ($sheet as $r) {
        $row = ['student_id' => $r['student_number'], 'name' => $r['last_name'] . ', ' . $r['first_name']];
        foreach ($components as $component) {
            $row['comp_' . $component['id']] = $r['comp_scores'][$component['id']] ?? '';
        }
        $row['overall'] = $r['overall'] !== '' ? $r['overall'] : '';
        $row['letter'] = $r['letter'] !== '' ? formatGrade($r['letter']) : '';
        $rows[] = $row;
    }

    $keys = ['student_id', 'name'];
    foreach ($components as $component) {
        $keys[] = 'comp_' . $component['id'];
    }
    $keys[] = 'overall';
    $keys[] = 'letter';

    exportExcel('score-sheet-section-' . $sectionId, $headers, pickColumns($rows, $keys));
}

$pageTitle = 'Grades';
include '../includes/header.php';
displayFlash();
?>

<main>
    <?php if (!$canRecord): ?>
    <div class="alert" style="background: var(--surface-low);">
        <?php echo icon('lock', 16); ?>
        You are viewing the score sheet as an admin. Only teachers can edit scores.
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h2 style="margin-bottom: 4px;"><?php echo icon('pen-line', 24); ?> Grade Score Sheet</h2>
                <small style="color: var(--ink-muted);">
                    Enter scores like an Excel sheet &mdash; the overall score and letter grade update automatically.
                </small>
            </div>
        </div>

        <div style="padding: 24px; border-bottom: 1px solid var(--outline-soft);">
            <form method="GET" class="form-row" style="align-items: flex-end;">
                <div class="form-group" style="flex: 2;">
                    <label>Section / Subject</label>
                    <select name="section_id" class="form-control" required>
                        <option value="">-- Select Section --</option>
                        <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['id']; ?>" <?php echo (int) $section['id'] === $sectionId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($section['subject_code'] . ' - ' . $section['subject_name'] . ' (' . $section['section_code'] . ') — ' . $section['first_name'] . ' ' . $section['last_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><?php echo icon('clipboard-list', 14); ?> Load Score Sheet</button>
                </div>
                <?php if ($selectedSection): ?>
                <div class="form-group">
                    <a href="?section_id=<?php echo $sectionId; ?>&export=excel" class="btn btn-secondary"><?php echo icon('download', 14); ?> Export to Excel</a>
                </div>
                <?php if ($canRecord): ?>
                <?php
                    $selectedSubjectId = (int) $selectedSection['subject_id'];
                    $selectedComps = $selectedSection ? getComponents($db, $selectedSubjectId) : [];
                ?>
                <div class="form-group">
                    <button type="button" class="btn btn-secondary" onclick="openComponentsModal()"><?php echo icon('settings', 14); ?> Manage Components</button>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($selectedSection && $components): ?>
        <form method="POST" class="score-sheet" id="scoreSheetForm">
            <input type="hidden" name="action" value="save_sheet">
            <input type="hidden" name="section_id" value="<?php echo $sectionId; ?>">
            <input type="hidden" name="semester" value="<?php echo htmlspecialchars($semester); ?>">
            <input type="hidden" name="year" value="<?php echo $year; ?>">

            <table class="score-sheet-table">
                <thead>
                    <tr>
                        <th rowspan="2">#</th>
                        <th rowspan="2">Student</th>
                        <?php foreach ($components as $component): ?>
                        <th colspan="1"><?php echo htmlspecialchars($component['component_name']); ?>
                            <small style="display:block;">(over <?php echo (float) $component['max_score']; ?>) &middot; <?php echo (float) $component['weight']; ?>%</small>
                        </th>
                        <?php endforeach; ?>
                        <th rowspan="2">Overall (%)</th>
                        <th rowspan="2">Grade</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $idx = 0; ?>
                    <?php foreach ($sheet as $r): $idx++; ?>
                    <tr>
                        <td data-label="#"><?php echo $idx; ?></td>
                        <td data-label="Student">
                            <code><?php echo htmlspecialchars($r['student_number']); ?></code>
                            <div style="color: var(--ink-muted); font-size: 13px;"><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></div>
                        </td>
                        <?php $rowKey = $r['enrollment_id'] . ':' . $r['student_id']; ?>
                        <?php foreach ($components as $component): ?>
                        <?php $cid = $component['id']; ?>
                        <td data-label="<?php echo htmlspecialchars($component['component_name']); ?>">
                            <input type="number"
                                   class="form-control component-score"
                                   name="scores[<?php echo $rowKey; ?>][<?php echo $cid; ?>]"
                                   data-max="<?php echo (float) $component['max_score']; ?>"
                                   data-weight="<?php echo (float) $component['weight']; ?>"
                                   data-maxlabel="<?php echo htmlspecialchars($component['component_name']); ?>"
                                   min="0"
                                   max="<?php echo (float) $component['max_score']; ?>"
                                   step="any"
                                   value="<?php echo $r['comp_scores'][$cid] !== '' ? htmlspecialchars($r['comp_scores'][$cid]) : ''; ?>"
                                   <?php if (!$canRecord) echo 'readonly'; ?>>
                        </td>
                        <?php endforeach; ?>
                        <td data-label="Overall (%)" class="overall-cell"><?php echo $r['overall'] !== '' ? $r['overall'] : '—'; ?></td>
                        <td data-label="Grade" class="grade-cell">
                            <?php if ($r['letter']): ?>
                            <span class="grade-badge <?php echo gradeBadgeClass($r['letter']); ?>" title="<?php echo htmlspecialchars(gradeDescriptor($r['letter'])); ?>"><?php echo htmlspecialchars(formatGrade($r['letter'])); ?></span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$sheet): ?>
                    <tr><td colspan="<?php echo count($components) + 4; ?>" style="text-align:center;">No enrolled students in this section.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($canRecord): ?>
            <div class="score-sheet-footer">
                <span>
                    <strong>Weights:</strong>
                    <?php foreach ($components as $componentName => $component): ?>
                    <?php echo htmlspecialchars($component['component_name']); ?> <?php echo (float) $component['weight']; ?>%<?php echo array_key_last($components) !== $componentName ? ' &middot;' : ''; ?>
                    <?php endforeach; ?>
                </span>
                <button type="submit" class="btn btn-success"><?php echo icon('save', 14); ?> Save Score Sheet</button>
            </div>
            <?php endif; ?>
        </form>
        <?php elseif ($sectionId): ?>
            <p style="padding: 24px; color: var(--ink-muted);">No subject or assessment components configured for this section.</p>
        <?php endif; ?>
    </div>

    <?php if ($gradesList): ?>
    <div class="card" style="margin-top: 24px;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php echo icon('file-text', 24); ?> Recorded Grades</h2>
            <a href="?section_id=<?php echo $sectionId ? $sectionId : ''; ?>&export=excel" class="btn btn-secondary btn-sm"><?php echo icon('download', 14); ?> Export Score Sheet</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Subject</th>
                    <th>Section</th>
                    <th>Semester</th>
                    <th>Year</th>
                    <th>Overall (%)</th>
                    <th>Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gradesList as $grade): ?>
                <tr>
                    <td data-label="Student"><?php echo htmlspecialchars($grade['student_number'] . ' - ' . $grade['first_name'] . ' ' . $grade['last_name']); ?></td>
                    <td data-label="Subject"><?php echo htmlspecialchars($grade['subject_code'] . ' - ' . $grade['subject_name']); ?></td>
                    <td data-label="Section"><?php echo htmlspecialchars($grade['section_code'] ?? '—'); ?></td>
                    <td data-label="Semester"><?php echo htmlspecialchars($grade['semester']); ?></td>
                    <td data-label="Year"><?php echo $grade['year']; ?></td>
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
    </div>    <?php endif; ?>
</main>

<?php if ($canRecord && $selectedSection): ?>
<!-- Manage Components Modal -->
<div id="componentsModal" class="modal-overlay" style="display: none;">
    <div class="modal-box modal-box-wide">
        <h2><?php echo icon('settings', 20); ?> Manage Components &mdash; <?php echo htmlspecialchars($selectedSection['subject_code'] . ' ' . $selectedSection['subject_name']); ?></h2>
        <p style="margin-bottom: 20px; color: var(--ink-muted);">
            Add, rename, or re-weight the scoring columns. Overall grades are recomputed automatically.
        </p>

        <form method="POST">
            <input type="hidden" name="action" value="save_components">
            <input type="hidden" name="subject_id" value="<?php echo (int) $selectedSection['subject_id']; ?>">

            <div class="component-rows" id="componentRows">
                <?php foreach ($selectedComps as $comp): ?>
                <div class="component-row">
                    <input type="checkbox" class="component-delete" name="delete[]" value="<?php echo (int) $comp['id']; ?>" title="Remove">
                    <input type="hidden" name="cid[]" value="<?php echo (int) $comp['id']; ?>">
                    <input type="text" name="name[<?php echo (int) $comp['id']; ?>]" class="form-control" value="<?php echo htmlspecialchars($comp['component_name']); ?>" placeholder="Name" required>
                    <input type="number" name="max[<?php echo (int) $comp['id']; ?>]" class="form-control" value="<?php echo (float) $comp['max_score']; ?>" min="0.01" step="any" placeholder="Max" required>
                    <input type="number" name="weight[<?php echo (int) $comp['id']; ?>]" class="form-control" value="<?php echo (float) $comp['weight']; ?>" min="0" max="100" step="any" placeholder="Weight %" required>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin: 16px 0;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="addComponentRow()"><?php echo icon('plus', 14); ?> Add Component</button>
            </div>

            <div class="alert" style="background: var(--surface-low);">
                Weights are percentages. Uncheck a row then save to remove it. Saved changes are applied to this subject's score sheet and existing grades are recalculated.
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success"><?php echo icon('save', 14); ?> Save Components</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('componentsModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    function openComponentsModal() {
        document.getElementById('componentsModal').style.display = 'block';
    }

    function addComponentRow() {
        var rows = document.getElementById('componentRows');
        var count = rows.querySelectorAll('input[name*="name"]').length;
        // Use a non-numeric key so the PHP add branch can identify new rows
        // (existing rows are keyed by their numeric component id).
        var key = 'new_' + Date.now();
        var row = document.createElement('div');
        row.className = 'component-row';
        row.innerHTML =
            '<input type="checkbox" class="component-delete" title="Remove">' +
            '<input type="text" name="name[' + key + ']" class="form-control" placeholder="Name" required>' +
            '<input type="number" name="max[' + key + ']" class="form-control" value="100" min="0.01" step="any" placeholder="Max" required>' +
            '<input type="number" name="weight[' + key + ']" class="form-control" value="0" min="0" max="100" step="any" placeholder="Weight %" required>';
        // New rows must not contain a hidden cid so the PHP 'add' branch picks them up.
        rows.appendChild(row);
    }

    document.getElementById('componentsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
