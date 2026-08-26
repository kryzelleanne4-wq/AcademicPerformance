<?php
/**
 * Seed the database with realistic sample data:
 *   - 5 departments
 *   - 15 subjects
 *   - 5 instructors (teachers)
 *   - 30 students
 *   - 15 course sections (schedules)
 *
 * Run with: php database/seed.php
 */

require_once __DIR__ . '/../config/database.php';

$db = getDB();
$db->exec('PRAGMA foreign_keys = ON');

// ── Helpers ──────────────────────────────────────────────────────────────────

function generateStudentId(PDO $db): string {
    $prefix = 'STU-' . date('Y') . '-';
    $stmt = $db->prepare("SELECT student_id FROM students WHERE student_id LIKE :p ORDER BY student_id DESC LIMIT 1");
    $stmt->execute([':p' => $prefix . '%']);
    $last = $stmt->fetchColumn();
    $num = $last ? (int) substr($last, -4) + 1 : 1;
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function generateEmployeeId(PDO $db): string {
    $prefix = 'EMP-' . date('Y') . '-';
    $stmt = $db->prepare("SELECT employee_id FROM instructors WHERE employee_id LIKE :p ORDER BY employee_id DESC LIMIT 1");
    $stmt->execute([':p' => $prefix . '%']);
    $last = $stmt->fetchColumn();
    $num = $last ? (int) substr($last, -4) + 1 : 1;
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

// ── Begin ────────────────────────────────────────────────────────────────────

$db->beginTransaction();

try {

    // ════════════════════════════════════════════════════════════════════════
    // 1. DEPARTMENTS (need 5 total, already have 2)
    // ════════════════════════════════════════════════════════════════════════
    $departments = [
        ['code' => 'CS',   'name' => 'Computer Science',            'desc' => 'Study of computation, algorithms, and software systems.'],
        ['code' => 'IT',   'name' => 'Information Technology',      'desc' => 'Application of technology to solve business and organizational problems.'],
        ['code' => 'BA',   'name' => 'Business Administration',     'desc' => 'Management of business operations and organizational principles.'],
        ['code' => 'EE',   'name' => 'Electrical Engineering',      'desc' => 'Study of electrical systems, circuits, and power generation.'],
        ['code' => 'ED',   'name' => 'Education',                   'desc' => 'Training of future educators and pedagogical research.'],
    ];

    $deptIds = [];
    $stmtIns = $db->prepare("INSERT OR IGNORE INTO departments (department_code, department_name, description) VALUES (:c, :n, :d)");
    foreach ($departments as $d) {
        $stmtIns->execute([':c' => $d['code'], ':n' => $d['name'], ':d' => $d['desc']]);
        $deptIds[$d['code']] = (int) $db->lastInsertId();
        if ($deptIds[$d['code']] === 0) {
            // Already existed – look it up
            $stmtSel = $db->prepare("SELECT id FROM departments WHERE department_code = :c");
            $stmtSel->execute([':c' => $d['code']]);
            $deptIds[$d['code']] = (int) $stmtSel->fetchColumn();
        }
    }
    echo "✓ Departments seeded\n";

    // ════════════════════════════════════════════════════════════════════════
    // 2. USERS + INSTRUCTORS (need 5 total, already have 1)
    // ════════════════════════════════════════════════════════════════════════
    $passwordHash = password_hash('password123', PASSWORD_DEFAULT);

    $instructors = [
        ['first' => 'Maria',     'last' => 'Santos',      'email' => 'maria.santos@school.edu',  'dept' => 'CS',  'title' => 'Professor',        'spec' => 'Artificial Intelligence'],
        ['first' => 'Jose',      'last' => 'Reyes',       'email' => 'jose.reyes@school.edu',   'dept' => 'IT',  'title' => 'Associate Professor', 'spec' => 'Network Security'],
        ['first' => 'Ana',       'last' => 'Cruz',        'email' => 'ana.cruz@school.edu',     'dept' => 'BA',  'title' => 'Assistant Professor', 'spec' => 'Marketing'],
        ['first' => 'Ricardo',   'last' => 'Garcia',      'email' => 'ricardo.garcia@school.edu','dept' => 'EE', 'title' => 'Professor',        'spec' => 'Power Systems'],
        ['first' => 'Lorna',     'last' => 'Aquino',      'email' => 'lorna.aquino@school.edu', 'dept' => 'ED',  'title' => 'Lecturer',          'spec' => 'Curriculum Design'],
    ];

    // Check if the existing instructor (id=1) already has names
    $existingInstructor = $db->query("SELECT id FROM instructors LIMIT 1")->fetch();

    $instructorIds = [];
    $stmtUser = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (:u, :p, :fn, :e, 'instructor')");
    $stmtInstr = $db->prepare("INSERT INTO instructors (user_id, employee_id, first_name, last_name, email, department_id, title, specialization, hired_date) VALUES (:uid, :eid, :fn, :ln, :e, :did, :t, :sp, :hd)");

    foreach ($instructors as $i) {
        $loginId = generateEmployeeId($db);
        $stmtUser->execute([
            ':u'  => $loginId,
            ':p'  => $passwordHash,
            ':fn' => $i['first'] . ' ' . $i['last'],
            ':e'  => $i['email'],
        ]);
        $userId = (int) $db->lastInsertId();

        $stmtInstr->execute([
            ':uid' => $userId,
            ':eid' => $loginId,
            ':fn'  => $i['first'],
            ':ln'  => $i['last'],
            ':e'   => $i['email'],
            ':did' => $deptIds[$i['dept']] ?? null,
            ':t'   => $i['title'],
            ':sp'  => $i['spec'],
            ':hd'  => '2024-01-15',
        ]);
        $instructorIds[] = (int) $db->lastInsertId();
    }
    echo "✓ Instructors seeded (5)\n";

    // ════════════════════════════════════════════════════════════════════════
    // 3. SUBJECTS (need 15 total, already have 2)
    // ════════════════════════════════════════════════════════════════════════
    $subjects = [
        // CS subjects
        ['code' => 'CS101', 'name' => 'Introduction to Programming',         'dept' => 'CS',  'credits' => 3, 'level' => 1, 'desc' => 'Fundamentals of programming using Python.'],
        ['code' => 'CS201', 'name' => 'Data Structures and Algorithms',      'dept' => 'CS',  'credits' => 3, 'level' => 2, 'desc' => 'Arrays, linked lists, trees, graphs, and sorting algorithms.'],
        ['code' => 'CS301', 'name' => 'Object-Oriented Programming',         'dept' => 'CS',  'credits' => 3, 'level' => 2, 'desc' => 'OOP principles using Java.'],
        ['code' => 'CS401', 'name' => 'Operating Systems',                   'dept' => 'CS',  'credits' => 3, 'level' => 3, 'desc' => 'Process management, memory, and file systems.'],

        // IT subjects
        ['code' => 'IT101', 'name' => 'Fundamentals of Information Technology','dept' => 'IT', 'credits' => 3, 'level' => 1, 'desc' => 'Overview of IT systems and infrastructure.'],
        ['code' => 'IT201', 'name' => 'Web Development',                     'dept' => 'IT',  'credits' => 3, 'level' => 2, 'desc' => 'HTML, CSS, JavaScript, and PHP for web apps.'],
        ['code' => 'IT301', 'name' => 'Database Management Systems',         'dept' => 'IT',  'credits' => 3, 'level' => 2, 'desc' => 'Relational databases, SQL, and normalization.'],
        ['code' => 'IT321', 'name' => 'Advanced Database',                   'dept' => 'IT',  'credits' => 3, 'level' => 3, 'desc' => 'Advanced SQL, transactions, and NoSQL.'],

        // BA subjects
        ['code' => 'BA101', 'name' => 'Principles of Management',            'dept' => 'BA',  'credits' => 3, 'level' => 1, 'desc' => 'Planning, organizing, leading, and controlling.'],
        ['code' => 'BA201', 'name' => 'Marketing Fundamentals',              'dept' => 'BA',  'credits' => 3, 'level' => 2, 'desc' => '4Ps of marketing and consumer behavior.'],
        ['code' => 'BA301', 'name' => 'Financial Accounting',                'dept' => 'BA',  'credits' => 3, 'level' => 2, 'desc' => 'Double-entry bookkeeping and financial statements.'],

        // EE subjects
        ['code' => 'EE101', 'name' => 'Basic Circuit Theory',                'dept' => 'EE',  'credits' => 4, 'level' => 1, 'desc' => 'Ohm\'s law, Kirchhoff\'s laws, and network analysis.'],
        ['code' => 'EE201', 'name' => 'Electromagnetics',                    'dept' => 'EE',  'credits' => 4, 'level' => 2, 'desc' => 'Maxwell\'s equations and wave propagation.'],

        // ED subjects
        ['code' => 'ED101', 'name' => 'Foundations of Education',            'dept' => 'ED',  'credits' => 3, 'level' => 1, 'desc' => 'History and philosophy of education.'],
        ['code' => 'ED201', 'name' => 'Educational Psychology',              'dept' => 'ED',  'credits' => 3, 'level' => 2, 'desc' => 'Learning theories and motivation.'],
    ];

    $stmtSubj = $db->prepare("INSERT OR IGNORE INTO subjects (department_id, subject_code, subject_name, description, credits, course_level) VALUES (:did, :c, :n, :d, :cr, :cl)");
    $subjectIds = [];
    foreach ($subjects as $s) {
        $stmtSubj->execute([
            ':did' => $deptIds[$s['dept']] ?? null,
            ':c'   => $s['code'],
            ':n'   => $s['name'],
            ':d'   => $s['desc'],
            ':cr'  => $s['credits'],
            ':cl'  => $s['level'],
        ]);
        $insertedId = (int) $db->lastInsertId();
        if ($insertedId === 0) {
            $stmtSel = $db->prepare("SELECT id FROM subjects WHERE subject_code = :c");
            $stmtSel->execute([':c' => $s['code']]);
            $subjectIds[$s['code']] = (int) $stmtSel->fetchColumn();
        } else {
            $subjectIds[$s['code']] = $insertedId;
        }
    }
    echo "✓ Subjects seeded (15)\n";

    // ════════════════════════════════════════════════════════════════════════
    // 4. ACADEMIC TERMS (ensure at least one exists)
    // ════════════════════════════════════════════════════════════════════════
    $termCount = (int) querySingle("SELECT COUNT(*) FROM academic_terms");
    if ($termCount === 0) {
        $stmtTerm = $db->prepare("INSERT INTO academic_terms (term_code, term_name, academic_year, start_date, end_date, is_current) VALUES (:c, :n, :y, :s, :e, 1)");
        $stmtTerm->execute([
            ':c' => 'T2026-1',
            ':n' => 'First Semester',
            ':y' => '2026-2027',
            ':s' => '2026-08-01',
            ':e' => '2027-01-31',
        ]);
    }
    $currentTermId = (int) querySingle("SELECT id FROM academic_terms WHERE is_current = 1 LIMIT 1");
    if (!$currentTermId) {
        $currentTermId = (int) querySingle("SELECT id FROM academic_terms ORDER BY id DESC LIMIT 1");
    }
    echo "✓ Academic term ready (id={$currentTermId})\n";

    // ════════════════════════════════════════════════════════════════════════
    // 5. USERS + STUDENTS (need 30 total, already have 3)
    // ════════════════════════════════════════════════════════════════════════
    $existingStudentCount = (int) querySingle("SELECT COUNT(*) FROM students");
    $needed = 30 - $existingStudentCount;

    $studentData = [
        // Male students
        ['first' => 'Mark',       'last' => 'Aquino',       'email' => 'mark.aquino@student.edu',      'gender' => 'Male',   'year' => 1],
        ['first' => 'James',      'last' => 'Bautista',     'email' => 'james.bautista@student.edu',   'gender' => 'Male',   'year' => 1],
        ['first' => 'Carlos',     'last' => 'Dela Rosa',    'email' => 'carlos.delarosa@student.edu',  'gender' => 'Male',   'year' => 2],
        ['first' => 'David',      'last' => 'Espinosa',     'email' => 'david.espinosa@student.edu',   'gender' => 'Male',   'year' => 2],
        ['first' => 'Elijah',     'last' => 'Fernandez',    'email' => 'elijah.fernandez@student.edu', 'gender' => 'Male',   'year' => 1],
        ['first' => 'Francis',    'last' => 'Gonzales',     'email' => 'francis.gonzales@student.edu', 'gender' => 'Male',   'year' => 3],
        ['first' => 'Gabriel',    'last' => 'Hernandez',    'email' => 'gabriel.hernandez@student.edu','gender' => 'Male',   'year' => 2],
        ['first' => 'Harold',     'last' => 'Ibarra',       'email' => 'harold.ibarra@student.edu',    'gender' => 'Male',   'year' => 1],
        ['first' => 'Ivan',       'last' => 'Lopez',        'email' => 'ivan.lopez@student.edu',       'gender' => 'Male',   'year' => 3],
        ['first' => 'Jerome',     'last' => 'Mendoza',      'email' => 'jerome.mendoza@student.edu',   'gender' => 'Male',   'year' => 2],
        ['first' => 'Kevin',      'last' => 'Navarro',      'email' => 'kevin.navarro@student.edu',    'gender' => 'Male',   'year' => 1],
        ['first' => 'Luis',       'last' => 'Ortega',       'email' => 'luis.ortega@student.edu',      'gender' => 'Male',   'year' => 4],
        ['first' => 'Marco',      'last' => 'Padilla',      'email' => 'marco.padilla@student.edu',    'gender' => 'Male',   'year' => 3],
        ['first' => 'Nathan',     'last' => 'Quintana',     'email' => 'nathan.quintana@student.edu',  'gender' => 'Male',   'year' => 2],
        ['first' => 'Oscar',      'last' => 'Rivera',       'email' => 'oscar.rivera@student.edu',     'gender' => 'Male',   'year' => 1],

        // Female students
        ['first' => 'Angela',     'last' => 'Castillo',     'email' => 'angela.castillo@student.edu',  'gender' => 'Female', 'year' => 1],
        ['first' => 'Bea',        'last' => 'Dimaculangan', 'email' => 'bea.dimaculangan@student.edu', 'gender' => 'Female', 'year' => 2],
        ['first' => 'Catherine',  'last' => 'Estrada',      'email' => 'catherine.estrada@student.edu', 'gender' => 'Female', 'year' => 1],
        ['first' => 'Diana',      'last' => 'Flores',       'email' => 'diana.flores@student.edu',     'gender' => 'Female', 'year' => 3],
        ['first' => 'Ella',       'last' => 'Gutierrez',    'email' => 'ella.gutierrez@student.edu',   'gender' => 'Female', 'year' => 2],
        ['first' => 'Frances',    'last' => 'Ignacio',      'email' => 'frances.ignacio@student.edu',  'gender' => 'Female', 'year' => 1],
        ['first' => 'Grace',      'last' => 'Jimenez',      'email' => 'grace.jimenez@student.edu',    'gender' => 'Female', 'year' => 4],
        ['first' => 'Hannah',     'last' => 'Katigbak',     'email' => 'hannah.katigbak@student.edu',  'gender' => 'Female', 'year' => 2],
        ['first' => 'Isabel',     'last' => 'Lim',          'email' => 'isabel.lim@student.edu',       'gender' => 'Female', 'year' => 3],
        ['first' => 'Jasmine',    'last' => 'Mercado',      'email' => 'jasmine.mercado@student.edu',  'gender' => 'Female', 'year' => 1],
        ['first' => 'Karen',      'last' => 'Nolasco',      'email' => 'karen.nolasco@student.edu',    'gender' => 'Female', 'year' => 2],
        ['first' => 'Laura',      'last' => 'Ocampo',       'email' => 'laura.ocampo@student.edu',     'gender' => 'Female', 'year' => 3],
        ['first' => 'Michelle',   'last' => 'Pascual',      'email' => 'michelle.pascual@student.edu', 'gender' => 'Female', 'year' => 1],
        ['first' => 'Nicole',     'last' => 'Quiambao',     'email' => 'nicole.quiambao@student.edu',  'gender' => 'Female', 'year' => 4],
    ];

    // Only add as many as needed
    $studentData = array_slice($studentData, 0, $needed);

    // Get all subject IDs as an array for random assignment
    $allSubjectIds = array_values($subjectIds);

    $stmtUserS = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (:u, :p, :fn, :e, 'student')");
    $stmtStud  = $db->prepare("INSERT INTO students (user_id, student_id, first_name, last_name, email, gender, year_level, enrollment_date, status) VALUES (:uid, :sid, :fn, :ln, :e, :g, :yl, :ed, 'Active')");

    foreach ($studentData as $s) {
        $loginId = generateStudentId($db);
        $stmtUserS->execute([
            ':u'  => $loginId,
            ':p'  => $passwordHash,
            ':fn' => $s['first'] . ' ' . $s['last'],
            ':e'  => $s['email'],
        ]);
        $userId = (int) $db->lastInsertId();

        $stmtStud->execute([
            ':uid' => $userId,
            ':sid' => $loginId,
            ':fn'  => $s['first'],
            ':ln'  => $s['last'],
            ':e'   => $s['email'],
            ':g'   => $s['gender'],
            ':yl'  => $s['year'],
            ':ed'  => '2026-08-25',
        ]);
    }
    echo "✓ Students seeded (" . count($studentData) . " added, 30 total)\n";

    // ════════════════════════════════════════════════════════════════════════
    // 6. COURSE SECTIONS / SCHEDULES (need 15 total, already have 1)
    // ════════════════════════════════════════════════════════════════════════
    $schedules = [
        // CS sections
        ['subj' => 'CS101', 'instIdx' => 0, 'code' => 'A', 'room' => 'Room 101', 'sched' => 'Mon & Wed, 8:00 - 9:30 AM',     'cap' => 40],
        ['subj' => 'CS201', 'instIdx' => 0, 'code' => 'A', 'room' => 'Room 102', 'sched' => 'Tue & Thu, 10:00 - 11:30 AM',   'cap' => 35],
        ['subj' => 'CS301', 'instIdx' => 0, 'code' => 'A', 'room' => 'Room 103', 'sched' => 'Mon & Wed, 2:00 - 3:30 PM',     'cap' => 35],
        ['subj' => 'CS401', 'instIdx' => 0, 'code' => 'A', 'room' => 'Room 104', 'sched' => 'Tue & Thu, 1:00 - 2:30 PM',     'cap' => 30],

        // IT sections
        ['subj' => 'IT101', 'instIdx' => 1, 'code' => 'A', 'room' => 'Room 201', 'sched' => 'Mon & Wed, 10:00 - 11:30 AM',   'cap' => 40],
        ['subj' => 'IT201', 'instIdx' => 1, 'code' => 'A', 'room' => 'Room 202', 'sched' => 'Tue & Thu, 8:00 - 9:30 AM',     'cap' => 35],
        ['subj' => 'IT301', 'instIdx' => 1, 'code' => 'A', 'room' => 'Room 203', 'sched' => 'Mon & Wed, 3:00 - 4:30 PM',     'cap' => 30],
        ['subj' => 'IT321', 'instIdx' => 1, 'code' => 'A', 'room' => 'Room 204', 'sched' => 'Fri, 9:00 AM - 12:00 PM',       'cap' => 30],

        // BA sections
        ['subj' => 'BA101', 'instIdx' => 2, 'code' => 'A', 'room' => 'Room 301', 'sched' => 'Mon & Wed, 1:00 - 2:30 PM',     'cap' => 45],
        ['subj' => 'BA201', 'instIdx' => 2, 'code' => 'A', 'room' => 'Room 302', 'sched' => 'Tue & Thu, 3:00 - 4:30 PM',     'cap' => 40],
        ['subj' => 'BA301', 'instIdx' => 2, 'code' => 'A', 'room' => 'Room 303', 'sched' => 'Mon & Wed, 10:00 - 11:30 AM',   'cap' => 35],

        // EE sections
        ['subj' => 'EE101', 'instIdx' => 3, 'code' => 'A', 'room' => 'Room 401', 'sched' => 'Tue & Thu, 10:00 - 11:30 AM',   'cap' => 30],
        ['subj' => 'EE201', 'instIdx' => 3, 'code' => 'A', 'room' => 'Room 402', 'sched' => 'Mon & Wed, 4:00 - 5:30 PM',     'cap' => 25],

        // ED sections
        ['subj' => 'ED101', 'instIdx' => 4, 'code' => 'A', 'room' => 'Room 501', 'sched' => 'Tue & Thu, 8:00 - 9:30 AM',     'cap' => 40],
        ['subj' => 'ED201', 'instIdx' => 4, 'code' => 'A', 'room' => 'Room 502', 'sched' => 'Mon & Wed, 11:00 AM - 12:30 PM','cap' => 35],
    ];

    // Fetch all existing instructor IDs (the ones we just created)
    $allInstructors = $db->query("SELECT id FROM instructors ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    if (count($allInstructors) < 5) {
        // Not enough instructors? Fill from the ones we created
        $allInstructors = array_merge($allInstructors, $instructorIds);
        $allInstructors = array_unique($allInstructors);
        sort($allInstructors);
    }

    $stmtSec = $db->prepare("INSERT OR IGNORE INTO course_sections (subject_id, instructor_id, term_id, section_code, room, schedule, capacity) VALUES (:s, :i, :t, :c, :r, :sch, :cap)");
    foreach ($schedules as $sc) {
        $stmtSec->execute([
            ':s'   => $subjectIds[$sc['subj']],
            ':i'   => $allInstructors[$sc['instIdx']] ?? $allInstructors[0],
            ':t'   => $currentTermId,
            ':c'   => $sc['code'],
            ':r'   => $sc['room'],
            ':sch' => $sc['sched'],
            ':cap' => $sc['cap'],
        ]);
    }
    echo "✓ Course sections / schedules seeded (15)\n";

    // ════════════════════════════════════════════════════════════════════════
    // 7. ENROLLMENTS – enroll students in sections (no duplicate subjects)
    // ════════════════════════════════════════════════════════════════════════
    $allStudents = $db->query("SELECT id FROM students ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $allSections = $db->query("SELECT id, subject_id FROM course_sections ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($allStudents) && !empty($allSections)) {
        $stmtEnroll = $db->prepare("INSERT OR IGNORE INTO enrollments (student_id, section_id) VALUES (:sid, :secid)");

        $enrolled = 0;
        foreach ($allStudents as $studentId) {
            // Track which subjects this student is already enrolled in
            $enrolledSubjects = [];

            // Shuffle sections for randomness
            $shuffled = $allSections;
            shuffle($shuffled);

            $targetCount = rand(2, 4);
            $count = 0;

            foreach ($shuffled as $section) {
                if ($count >= $targetCount) break;

                // Skip if already enrolled in another section of the same subject
                if (in_array($section['subject_id'], $enrolledSubjects, true)) {
                    continue;
                }

                $stmtEnroll->execute([':sid' => $studentId, ':secid' => $section['id']]);
                if ($stmtEnroll->rowCount() > 0) {
                    $enrolledSubjects[] = $section['subject_id'];
                    $enrolled++;
                    $count++;
                }
            }
        }
        echo "✓ Enrollments seeded ({$enrolled} enrollments, no duplicate subjects)\n";
    }

    $db->commit();
    echo "\n🎉 Database seeded successfully!\n";

    // Summary
    echo "\n── Summary ──────────────────────────────\n";
    echo "Departments:      " . querySingle("SELECT COUNT(*) FROM departments") . "\n";
    echo "Subjects:         " . querySingle("SELECT COUNT(*) FROM subjects") . "\n";
    echo "Users:            " . querySingle("SELECT COUNT(*) FROM users") . "\n";
    echo "  - Admin:        " . querySingle("SELECT COUNT(*) FROM users WHERE role = 'admin'") . "\n";
    echo "  - Instructors:  " . querySingle("SELECT COUNT(*) FROM users WHERE role = 'instructor'") . "\n";
    echo "  - Students:     " . querySingle("SELECT COUNT(*) FROM users WHERE role = 'student'") . "\n";
    echo "Instructors:      " . querySingle("SELECT COUNT(*) FROM instructors") . "\n";
    echo "Students:         " . querySingle("SELECT COUNT(*) FROM students") . "\n";
    echo "Academic Terms:   " . querySingle("SELECT COUNT(*) FROM academic_terms") . "\n";
    echo "Course Sections:  " . querySingle("SELECT COUNT(*) FROM course_sections") . "\n";
    echo "Enrollments:      " . querySingle("SELECT COUNT(*) FROM enrollments") . "\n";

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
