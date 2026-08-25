<?php
/**
 * Grades Management Page
 * Teachers record grades for their sections; admins can use any section.
 * The exported Excel file matches the on-screen record columns.
 */

require_once '../includes/functions.php';
requireRole('admin', 'instructor');

$db = getDB();
$user = currentUser();
$instructor = currentInstructor();

// Sections available to this user (used to scope grading).
if ($user['role'] === 'admin') {
    $sectionsStmt = $db->query("
        SELECT cs.id, cs.section_code, sub.subject_code, sub.subject_name,
               ins.first_name, ins.last_name, t.term_name, t.academic_year
        FROM course_sections cs
        JOIN subjects sub ON cs.subject_id = sub.id
        JOIN instructors ins ON cs.instructor_id = ins.id
        JOIN academic_terms t ON cs.term_id = t.id
        ORDER BY t.is_current DESC, sub.subject_code, cs.section_code
    ");
} else {
    $sectionsStmt = $db->prepare("
        SELECT cs.id, cs.section_code, sub.subject_code, sub.subject_name,
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

// Map section -> subject and section -> enrolled students for the JS filter.
$sectionSubjects = [];
$sectionStudents = [];
foreach ($sections as $section) {
    $sectionSubjects[$section['id']] = [
        'id'   => $section['id'],
        'code' => $section['subject_code'],
        'name' => $section['subject_name']
    ];
    $stmt = $db->prepare("
        SELECT st.id, st.student_id, st.first_name, st.last_name
        FROM enrollments e
        JOIN students st ON e.student_id = st.id
        WHERE e.section_id = :sid AND e.status = 'Enrolled'
        ORDER BY st.last_name, st.first_name
    ");
    $stmt->execute([':sid' => $section['id']]);
    $sectionStudents[$section['id']] = $stmt->fetchAll();
}

// Handle form submissions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
            $section_id = intval($_POST['section_id'] ?? 0);
            $student_id = intval($_POST['student_id'] ?? 0);
            $semester = sanitize($_POST['semester'] ?? '');
            $year = intval($_POST['year'] ?? date('Y'));
            $score = floatval($_POST['score'] ?? 0);

            if (!$section_id || !$student_id || $semester === '') {
                setFlash('Section, student, semester and score are required.', 'error');
                header('Location: grades.php');
                exit();
            }

            try {
                $sectionStmt = $db->prepare("SELECT * FROM course_sections WHERE id = :id");
                $sectionStmt->execute([':id' => $section_id]);
                $section = $sectionStmt->fetch();

                if (!$section) {
                    throw new Exception('Section not found.');
                }

                // Find the enrollment to link the grade record to.
                $enrollStmt = $db->prepare("SELECT id FROM enrollments WHERE student_id = :sid AND section_id = :sec AND status = 'Enrolled' LIMIT 1");
                $enrollStmt->execute([':sid' => $student_id, ':sec' => $section_id]);
                $enrollment_id = $enrollStmt->fetchColumn() ?: null;

                $grade = calculateGrade($score);
                $stmt = $db->prepare("
                    INSERT INTO grades (student_id, subject_id, section_id, enrollment_id, instructor_id, semester, year, score, grade)
                    VALUES (:student_id, :subject_id, :section_id, :enrollment_id, :instructor_id, :semester, :year, :score, :grade)
                ");
                $stmt->execute([
                    ':student_id'    => $student_id,
                    ':subject_id'    => $section['subject_id'],
                    ':section_id'    => $section_id,
                    ':enrollment_id' => $enrollment_id,
                    ':instructor_id' => $section['instructor_id'],
                    ':semester'      => $semester,
                    ':year'          => $year,
                    ':score'         => $score,
                    ':grade'         => $grade
                ]);

                // Mirror the final score onto the enrollment record.
                $upd = $db->prepare("UPDATE enrollments SET final_score = :score, final_grade = :grade WHERE id = :id");
                $upd->execute([':score' => $score, ':grade' => $grade, ':id' => $enrollment_id]);

                setFlash('Grade recorded successfully!');
            } catch (Exception $e) {
                setFlash('Error adding grade: ' . $e->getMessage(), 'error');
            }
            header('Location: grades.php');
            exit();
            break;

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM grades WHERE id = :id");
            $stmt->execute([':id' => $id]);
            setFlash('Grade deleted successfully!');
            header('Location: grades.php');
            exit();
            break;
    }
}

// Scope the grade list to this user's sections.
if ($user['role'] === 'admin') {
    $gradesList = $db->query("
        SELECT g.*, s.student_id as student_number, s.first_name, s.last_name,
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
        SELECT g.*, s.student_id as student_number, s.first_name, s.last_name,
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

// Excel export of the record list (same columns as the on-screen table).
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    exportExcel('grade-records', [
        'Student ID', 'First Name', 'Last Name', 'Subject Code', 'Subject Name', 'Section',
        'Semester', 'Year', 'Score', 'Grade', 'Remarks'
    ], pickColumns($gradesList, [
        'student_number', 'first_name', 'last_name', 'subject_code', 'subject_name', 'section_code',
        'semester', 'year', 'score', 'grade', 'remarks'
    ]));
}

$pageTitle = 'Grades';
include '../includes/header.php';
displayFlash();
?>

<main>
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2><?php echo icon('pen-line', 24); ?> Grades List</h2>
            <div style="display: flex; gap: 8px;">
                <a href="?export=excel" class="btn btn-secondary btn-sm"><?php echo icon('download', 14); ?> Export to Excel</a>
                <button class="btn btn-primary" onclick="document.getElementById('addGradeModal').style.display='block'"><?php echo icon('plus', 14); ?> Add Grade</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Subject</th>
                    <th>Section</th>
                    <th>Semester</th>
                    <th>Year</th>
                    <th>Score</th>
                    <th>Grade</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($gradesList as $grade): ?>
                <tr>
                    <td><?php echo htmlspecialchars($grade['student_number'] . ' - ' . $grade['first_name'] . ' ' . $grade['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($grade['subject_code'] . ' - ' . $grade['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($grade['section_code'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($grade['semester']); ?></td>
                    <td><?php echo $grade['year']; ?></td>
                    <td><?php echo $grade['score']; ?></td>
                    <td>
                        <span class="grade-badge grade-<?php echo strtolower($grade['grade']); ?>">
                            <?php echo $grade['grade']; ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $grade['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add Grade Modal -->
<div id="addGradeModal" class="modal-overlay" style="display: none;">
    <div class="modal-box">
        <h2>Add New Grade</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label>Section</label>
                <select name="section_id" id="gradeSectionSelect" class="form-control" required>
                    <option value="">-- Select Section --</option>
                    <?php foreach ($sections as $section): ?>
                    <option value="<?php echo $section['id']; ?>">
                        <?php echo htmlspecialchars($section['subject_code'] . ' - ' . $section['subject_name'] . ' (' . $section['section_code'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Subject</label>
                <input type="text" id="gradeSubjectDisplay" class="form-control" placeholder="Auto-filled from section" readonly>
            </div>

            <div class="form-group">
                <label>Student</label>
                <select name="student_id" id="gradeStudentSelect" class="form-control" required>
                    <option value="">-- Select Section First --</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" class="form-control" required>
                        <option value="First Semester">First Semester</option>
                        <option value="Second Semester">Second Semester</option>
                        <option value="Summer">Summer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" class="form-control" value="<?php echo date('Y'); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Score (0-100)</label>
                <input type="number" name="score" class="form-control" min="0" max="100" step="0.01" required
                       oninput="document.getElementById('previewGrade').textContent = this.value >= 90 ? 'A' : this.value >= 80 ? 'B' : this.value >= 70 ? 'C' : this.value >= 60 ? 'D' : 'F'">
                <small>Grade: <strong id="previewGrade">-</strong></small>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-success">Save Grade</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('addGradeModal').style.display='none'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Section -> subject and section -> students maps from PHP.
    var sectionSubjects = <?php echo json_encode($sectionSubjects); ?>;
    var sectionStudents = <?php echo json_encode($sectionStudents); ?>;

    document.getElementById('gradeSectionSelect').addEventListener('change', function() {
        var sectionId = this.value;
        var subjectInput = document.getElementById('gradeSubjectDisplay');
        var studentSelect = document.getElementById('gradeStudentSelect');

        studentSelect.innerHTML = '<option value="">-- Select Student --</option>';

        if (!sectionId) {
            subjectInput.value = '';
            return;
        }

        if (sectionSubjects[sectionId]) {
            subjectInput.value = sectionSubjects[sectionId].code + ' - ' + sectionSubjects[sectionId].name;
        }

        (sectionStudents[sectionId] || []).forEach(function(student) {
            var option = document.createElement('option');
            option.value = student.id;
            option.textContent = student.student_id + ' - ' + student.first_name + ' ' + student.last_name;
            studentSelect.appendChild(option);
        });
    });

    document.getElementById('addGradeModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.style.display = 'none';
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
