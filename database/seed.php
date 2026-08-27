<?php
/**
 * Seed the database with realistic sample data for the block-based setup:
 *   - 3 departments (BSIT, BSBA, BSED) with programs
 *   - Class blocks per department & year level (e.g. BSIT 1st Year - Block 1)
 *   - 7 instructors
 *   - 24 subjects (2 per department per year)
 *   - 90 students assigned to blocks (regular + irregular)
 *   - Class schedules (course sections) tied to blocks
 *   - Enrollments (irregulars also take subjects from other year levels)
 *
 * WARNING: This wipes existing seed data. Run with: php database/seed.php
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
    // 0. WIPE all existing seed data (child tables first for foreign keys)
    // ════════════════════════════════════════════════════════════════════════
    foreach ([
        'grade_scores',
        'grades',
        'attendance',
        'enrollments',
        'course_sections',
        'assessment_components',
        'students',
        'instructors',
        'subjects',
        'blocks',
        'programs',
        'departments',
        'academic_terms',
    ] as $table) {
        $db->exec("DELETE FROM $table");
    }
    // Remove all login accounts; the admin account is recreated below.
    $db->exec("DELETE FROM users");
    echo "✓ Old seed data removed\n";

    // ════════════════════════════════════════════════════════════════════════
    // 1. ADMIN
    // ════════════════════════════════════════════════════════════════════════
    $db->prepare("INSERT INTO users (username, password, full_name, role, is_active) VALUES (:u, :p, :fn, 'admin', 1)")
        ->execute([':u' => 'admin', ':p' => password_hash('admin123', PASSWORD_DEFAULT), ':fn' => 'Administrator']);
    echo "✓ Admin account ready (admin / admin123)\n";

    // ════════════════════════════════════════════════════════════════════════
    // 2. DEPARTMENTS + PROGRAMS
    // ════════════════════════════════════════════════════════════════════════
    $departments = [
        ['code' => 'BSIT', 'name' => 'BS in Information Technology', 'desc' => 'Application of technology to solve business and organizational problems.'],
        ['code' => 'BSBA', 'name' => 'BS in Business Administration', 'desc' => 'Management of business operations and organizational principles.'],
        ['code' => 'BSED', 'name' => 'BS in Education', 'desc' => 'Training of future educators and pedagogical research.'],
    ];

    $stmtDept = $db->prepare("INSERT INTO departments (department_code, department_name, description) VALUES (:c, :n, :d)");
    $deptIds = [];
    foreach ($departments as $d) {
        $stmtDept->execute([':c' => $d['code'], ':n' => $d['name'], ':d' => $d['desc']]);
        $deptIds[$d['code']] = (int) $db->lastInsertId();
    }

    $programs = [
        ['dept' => 'BSIT', 'code' => 'BSIT', 'name' => 'Bachelor of Science in Information Technology'],
        ['dept' => 'BSBA', 'code' => 'BSBA', 'name' => 'Bachelor of Science in Business Administration'],
        ['dept' => 'BSED', 'code' => 'BSED', 'name' => 'Bachelor of Secondary Education'],
    ];
    $stmtProg = $db->prepare("INSERT INTO programs (department_id, program_code, program_name, degree_level, duration_years) VALUES (:d, :c, :n, 'Bachelor', 4)");
    $programIds = [];
    foreach ($programs as $p) {
        $stmtProg->execute([':d' => $deptIds[$p['dept']], ':c' => $p['code'], ':n' => $p['name']]);
        $programIds[$p['dept']] = (int) $db->lastInsertId();
    }
    echo "✓ Departments & programs seeded (3)\n";

    // ════════════════════════════════════════════════════════════════════════
    // 3. INSTRUCTORS
    // ════════════════════════════════════════════════════════════════════════
    $passwordHash = password_hash('password123', PASSWORD_DEFAULT);
    $instructors = [
        ['first' => 'Maria',   'last' => 'Santos',   'dept' => 'BSIT', 'title' => 'Professor',          'spec' => 'Database Systems'],
        ['first' => 'Mitch',   'last' => 'Ramos',    'dept' => 'BSIT', 'title' => 'Instructor',         'spec' => 'Programming'],
        ['first' => 'Noel',    'last' => 'Fusingan', 'dept' => 'BSIT', 'title' => 'Instructor',         'spec' => 'Web Development'],
        ['first' => 'Jose',    'last' => 'Reyes',    'dept' => 'BSBA', 'title' => 'Associate Professor', 'spec' => 'Management'],
        ['first' => 'Ana',     'last' => 'Cruz',     'dept' => 'BSBA', 'title' => 'Assistant Professor', 'spec' => 'Marketing'],
        ['first' => 'Lorna',   'last' => 'Aquino',   'dept' => 'BSED', 'title' => 'Professor',          'spec' => 'Curriculum Design'],
        ['first' => 'Ricardo', 'last' => 'Garcia',   'dept' => 'BSED', 'title' => 'Lecturer',           'spec' => 'Educational Technology'],
    ];

    $stmtUser = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (:u, :p, :fn, :e, 'instructor')");
    $stmtInst = $db->prepare("INSERT INTO instructors (user_id, employee_id, first_name, last_name, email, department_id, title, specialization, hired_date) VALUES (:uid, :eid, :fn, :ln, :e, :did, :t, :sp, :hd)");

    $instructorIds = []; // indexed by subject assignment key below
    foreach ($instructors as $i) {
        $loginId = generateEmployeeId($db);
        $email = strtolower($i['first'] . '.' . $i['last']) . '@school.edu';
        $stmtUser->execute([':u' => $loginId, ':p' => $passwordHash, ':fn' => $i['first'] . ' ' . $i['last'], ':e' => $email]);
        $userId = (int) $db->lastInsertId();
        $stmtInst->execute([
            ':uid' => $userId,
            ':eid' => $loginId,
            ':fn'  => $i['first'],
            ':ln'  => $i['last'],
            ':e'   => $email,
            ':did' => $deptIds[$i['dept']],
            ':t'   => $i['title'],
            ':sp'  => $i['spec'],
            ':hd'  => '2024-06-01',
        ]);
        $instructorIds[$i['first'] . ' ' . $i['last']] = (int) $db->lastInsertId();
    }
    echo "✓ Instructors seeded (7, password: password123)\n";

    // ════════════════════════════════════════════════════════════════════════
    // 4. SUBJECTS (2 per department per year level)
    // ════════════════════════════════════════════════════════════════════════
    $subjects = [
        // BSIT
        ['code' => 'PROG1',  'name' => 'Fundamentals of Programming',   'dept' => 'BSIT', 'credits' => 3, 'level' => 1, 'instr' => 'Mitch Ramos'],
        ['code' => 'WEB1',   'name' => 'Web Development 1',             'dept' => 'BSIT', 'credits' => 3, 'level' => 1, 'instr' => 'Noel Fusingan'],
        ['code' => 'DBMS',   'name' => 'Database Management',           'dept' => 'BSIT', 'credits' => 3, 'level' => 2, 'instr' => 'Maria Santos'],
        ['code' => 'WEB2',   'name' => 'Web Development 2',             'dept' => 'BSIT', 'credits' => 3, 'level' => 2, 'instr' => 'Noel Fusingan'],
        ['code' => 'SAD',    'name' => 'Systems Analysis and Design',   'dept' => 'BSIT', 'credits' => 3, 'level' => 3, 'instr' => 'Maria Santos'],
        ['code' => 'IA',     'name' => 'Information Assurance',         'dept' => 'BSIT', 'credits' => 3, 'level' => 3, 'instr' => 'Mitch Ramos'],
        ['code' => 'CAP1',   'name' => 'Capstone Project 1',            'dept' => 'BSIT', 'credits' => 3, 'level' => 4, 'instr' => 'Noel Fusingan'],
        ['code' => 'MOBDEV', 'name' => 'Mobile Development',            'dept' => 'BSIT', 'credits' => 3, 'level' => 4, 'instr' => 'Mitch Ramos'],

        // BSBA
        ['code' => 'MGT1',   'name' => 'Principles of Management',      'dept' => 'BSBA', 'credits' => 3, 'level' => 1, 'instr' => 'Jose Reyes'],
        ['code' => 'ACC1',   'name' => 'Basic Accounting',              'dept' => 'BSBA', 'credits' => 3, 'level' => 1, 'instr' => 'Ana Cruz'],
        ['code' => 'MKTG',   'name' => 'Marketing Fundamentals',        'dept' => 'BSBA', 'credits' => 3, 'level' => 2, 'instr' => 'Ana Cruz'],
        ['code' => 'HRM',    'name' => 'Human Resource Management',     'dept' => 'BSBA', 'credits' => 3, 'level' => 2, 'instr' => 'Jose Reyes'],
        ['code' => 'FIN1',   'name' => 'Financial Management',          'dept' => 'BSBA', 'credits' => 3, 'level' => 3, 'instr' => 'Jose Reyes'],
        ['code' => 'OPM',    'name' => 'Operations Management',         'dept' => 'BSBA', 'credits' => 3, 'level' => 3, 'instr' => 'Ana Cruz'],
        ['code' => 'BPL',    'name' => 'Business Policy & Strategy',    'dept' => 'BSBA', 'credits' => 3, 'level' => 4, 'instr' => 'Jose Reyes'],
        ['code' => 'ENTREP', 'name' => 'Entrepreneurship',              'dept' => 'BSBA', 'credits' => 3, 'level' => 4, 'instr' => 'Ana Cruz'],

        // BSED
        ['code' => 'FDNED',  'name' => 'Foundations of Education',      'dept' => 'BSED', 'credits' => 3, 'level' => 1, 'instr' => 'Lorna Aquino'],
        ['code' => 'EDPSY',  'name' => 'Educational Psychology',        'dept' => 'BSED', 'credits' => 3, 'level' => 1, 'instr' => 'Ricardo Garcia'],
        ['code' => 'FACLRN', 'name' => 'Facilitating Learning',         'dept' => 'BSED', 'credits' => 3, 'level' => 2, 'instr' => 'Lorna Aquino'],
        ['code' => 'ASMT',   'name' => 'Assessment of Learning',        'dept' => 'BSED', 'credits' => 3, 'level' => 2, 'instr' => 'Ricardo Garcia'],
        ['code' => 'CURR',   'name' => 'Curriculum Development',        'dept' => 'BSED', 'credits' => 3, 'level' => 3, 'instr' => 'Lorna Aquino'],
        ['code' => 'PRNTE',  'name' => 'Principles of Teaching',        'dept' => 'BSED', 'credits' => 3, 'level' => 3, 'instr' => 'Ricardo Garcia'],
        ['code' => 'FT',     'name' => 'Field Study & Practice Teaching', 'dept' => 'BSED', 'credits' => 6, 'level' => 4, 'instr' => 'Lorna Aquino'],
        ['code' => 'EDRSCH', 'name' => 'Research in Education',         'dept' => 'BSED', 'credits' => 3, 'level' => 4, 'instr' => 'Ricardo Garcia'],
    ];

    $stmtSubj = $db->prepare("INSERT INTO subjects (department_id, subject_code, subject_name, description, credits, course_level) VALUES (:d, :c, :n, :desc, :cr, :cl)");
    $subjectIds = []; // ['code' => id]
    foreach ($subjects as $s) {
        $stmtSubj->execute([
            ':d'    => $deptIds[$s['dept']],
            ':c'    => $s['code'],
            ':n'    => $s['name'],
            ':desc' => null,
            ':cr'   => $s['credits'],
            ':cl'   => $s['level'],
        ]);
        $subjectIds[$s['code']] = (int) $db->lastInsertId();
    }
    echo "✓ Subjects seeded (24)\n";

    // ════════════════════════════════════════════════════════════════════════
    // 5. ACADEMIC TERMS
    // ════════════════════════════════════════════════════════════════════════
    $stmtTerm = $db->prepare("INSERT INTO academic_terms (term_code, term_name, academic_year, start_date, end_date, is_current) VALUES (:c, :n, :y, :s, :e, :cur)");
    $stmtTerm->execute([
        ':c' => 'T2026-1', ':n' => 'First Semester', ':y' => '2026-2027',
        ':s' => '2026-08-01', ':e' => '2027-01-31', ':cur' => 1,
    ]);
    $currentTermId = (int) $db->lastInsertId();
    $stmtTerm->execute([
        ':c' => 'T2026-2', ':n' => 'Second Semester', ':y' => '2026-2027',
        ':s' => '2027-02-01', ':e' => '2027-06-30', ':cur' => 0,
    ]);
    echo "✓ Academic terms seeded (2)\n";

    // ════════════════════════════════════════════════════════════════════════
    // 6. BLOCKS (department × year level × block code)
    //    Years 1-2 have two blocks, years 3-4 have one.
    // ════════════════════════════════════════════════════════════════════════
    $blockPlan = [1 => [1, 2], 2 => [1, 2], 3 => [1], 4 => [1]];
    $stmtBlock = $db->prepare("INSERT INTO blocks (department_id, year_level, block_code, block_name) VALUES (:d, :y, :c, :n)");
    $blocks = []; // ['BSIT' => [year => [blockCode => id]]]
    foreach ($deptIds as $deptCode => $deptId) {
        foreach ($blockPlan as $year => $blockCodes) {
            foreach ($blockCodes as $blockCode) {
                $stmtBlock->execute([
                    ':d' => $deptId,
                    ':y' => $year,
                    ':c' => (string) $blockCode,
                    ':n' => $deptCode . ' ' . $year . (($blockCode === 1) ? 'A' : 'B'),
                ]);
                $blocks[$deptCode][$year][$blockCode] = (int) $db->lastInsertId();
            }
        }
    }
    // Insertion order (BSIT first) so the first seeded student lands in BSIT 1st Year - Block 1.
    $allBlockRows = $db->query("SELECT b.*, d.department_code FROM blocks b JOIN departments d ON b.department_id = d.id ORDER BY b.id")->fetchAll();
    echo "✓ Blocks seeded (" . count($allBlockRows) . ")\n";

    // ════════════════════════════════════════════════════════════════════════
    // 7. COURSE SECTIONS / SCHEDULES (lessons per block)
    //    Each block gets the 2 subjects of its department & year level,
    //    with its own section code (B1, B2, ...) and distinct days/times.
    // ════════════════════════════════════════════════════════════════════════
    $scheduleTimes = [
        'BSIT' => [1 => ['Mon & Wed, 7:00 - 8:30 AM', 'Tue & Thu, 7:00 - 8:30 AM'],
                   2 => ['Mon & Wed, 8:30 - 10:00 AM', 'Tue & Thu, 8:30 - 10:00 AM'],
                   3 => ['Mon & Wed, 1:00 - 2:30 PM',  'Tue & Thu, 1:00 - 2:30 PM'],
                   4 => ['Fri, 9:00 AM - 12:00 PM',   'Mon & Wed, 3:00 - 4:30 PM']],
        'BSBA' => [1 => ['Mon & Wed, 9:00 - 10:30 AM', 'Tue & Thu, 9:00 - 10:30 AM'],
                   2 => ['Mon & Wed, 10:30 AM - 12:00 PM', 'Tue & Thu, 10:30 AM - 12:00 PM'],
                   3 => ['Mon & Wed, 2:30 - 4:00 PM',  'Tue & Thu, 2:30 - 4:00 PM'],
                   4 => ['Fri, 1:00 - 4:00 PM',        'Mon & Wed, 4:30 - 6:00 PM']],
        'BSED' => [1 => ['Mon & Wed, 8:00 - 9:30 AM',  'Tue & Thu, 8:00 - 9:30 AM'],
                   2 => ['Mon & Wed, 9:30 - 11:00 AM', 'Tue & Thu, 9:30 - 11:00 AM'],
                   3 => ['Mon & Wed, 1:30 - 3:00 PM',  'Tue & Thu, 1:30 - 3:00 PM'],
                   4 => ['Fri, 8:00 - 11:00 AM',       'Mon & Wed, 3:30 - 5:00 PM']],
    ];
    $roomBase = ['BSIT' => 100, 'BSBA' => 200, 'BSED' => 300];

    $subjectByDeptYear = []; // [deptCode][year] = [subjectCode, subjectCode]
    foreach ($subjects as $s) {
        $subjectByDeptYear[$s['dept']][$s['level']][] = $s['code'];
    }

    $stmtSec = $db->prepare("INSERT INTO course_sections (subject_id, instructor_id, term_id, block_id, section_code, room, schedule, capacity) VALUES (:s, :i, :t, :b, :c, :r, :sch, :cap)");
    $sectionsByBlock = [];   // [blockId] => [sectionId, ...]
    $sectionsByDeptYear = []; // [deptCode][year] => [sectionId, ...]
    $subjectInstructor = [];
    foreach ($subjects as $s) {
        $subjectInstructor[$s['code']] = $s['instr'];
    }
    $countSec = 0;
    foreach ($allBlockRows as $block) {
        $deptCode = $block['department_code'];
        $year = (int) $block['year_level'];
        $times = $scheduleTimes[$deptCode][$year];
        foreach ($subjectByDeptYear[$deptCode][$year] as $subjIdx => $subjCode) {
            $stmtSec->execute([
                ':s'   => $subjectIds[$subjCode],
                ':i'   => $instructorIds[$subjectInstructor[$subjCode]],
                ':t'   => $currentTermId,
                ':b'   => (int) $block['id'],
                ':c'   => 'B' . $block['block_code'],
                ':r'   => 'Room ' . ($roomBase[$deptCode] + $year * 10 + $subjIdx * 2 + (int) $block['block_code']),
                ':sch' => $times[$subjIdx],
                ':cap' => 40,
            ]);
            $sectionId = (int) $db->lastInsertId();
            $sectionsByBlock[(int) $block['id']][] = $sectionId;
            $sectionsByDeptYear[$deptCode][$year][] = $sectionId;
            $countSec++;
        }
    }
    echo "✓ Class schedules seeded ($countSec sections)\n";

    // ════════════════════════════════════════════════════════════════════════
    // 8. STUDENTS (assigned to blocks, regular + irregular)
    //    First student is Juan Dela Cruz in BSIT 1st Year - Block 1.
    // ════════════════════════════════════════════════════════════════════════
    $firstNames = ['Juan', 'Mark', 'James', 'Carlos', 'David', 'Elijah', 'Francis', 'Gabriel', 'Harold', 'Ivan', 'Angela', 'Bea', 'Catherine', 'Diana', 'Ella'];
    $lastNames  = ['Dela Cruz', 'Aquino', 'Bautista', 'Castillo', 'Dimaculangan', 'Espinosa', 'Fernandez', 'Flores', 'Gonzales', 'Gutierrez', 'Hernandez', 'Ibarra', 'Jimenez', 'Lopez', 'Mendoza'];

    $stmtUserS = $db->prepare("INSERT INTO users (username, password, full_name, email, role) VALUES (:u, :p, :fn, :e, 'student')");
    $stmtStud = $db->prepare("INSERT INTO students (user_id, student_id, first_name, last_name, email, gender, program_id, year_level, block_id, student_type, enrollment_date, status) VALUES (:uid, :sid, :fn, :ln, :e, :g, :pid, :yl, :bid, :st, :ed, 'Active')");

    $studentsInBlocks = []; // [blockId] => [studentId, ...]
    $studentIds = [];
    $totalStudents = 90; // 18 blocks × 5 students
    for ($i = 0; $i < $totalStudents; $i++) {
        $block = $allBlockRows[intdiv($i, 5)];
        $blockId = (int) $block['id'];
        $deptCode = $block['department_code'];
        $year = (int) $block['year_level'];

        // Pair first/last names so every combination is unique (225 available)
        $first = $firstNames[$i % count($firstNames)];
        $last  = $lastNames[intdiv($i, count($firstNames))];
        $gender = in_array($first, ['Angela', 'Bea', 'Catherine', 'Diana', 'Ella'], true) ? 'Female' : 'Male';
        $type = ($i % 6 === 5) ? 'Irregular' : 'Regular'; // ~17% irregular

        $loginId = generateStudentId($db);
        $email = strtolower($first . '.' . str_replace(' ', '', $last)) . '@student.edu';

        $stmtUserS->execute([':u' => $loginId, ':p' => $passwordHash, ':fn' => $first . ' ' . $last, ':e' => $email]);
        $userId = (int) $db->lastInsertId();

        $stmtStud->execute([
            ':uid' => $userId,
            ':sid' => $loginId,
            ':fn'  => $first,
            ':ln'  => $last,
            ':e'   => $email,
            ':g'   => $gender,
            ':pid' => $programIds[$deptCode],
            ':yl'  => $year,
            ':bid' => $blockId,
            ':st'  => $type,
            ':ed'  => '2026-08-25',
        ]);
        $studentId = (int) $db->lastInsertId();
        $studentIds[] = $studentId;
        $studentsInBlocks[$blockId][] = $studentId;
    }
    echo "✓ Students seeded ($totalStudents, ~17% irregular, password: password123)\n";

    // ════════════════════════════════════════════════════════════════════════
    // 9. ENROLLMENTS
    //    Every student takes the sections of their own block (their year's
    //    subjects). Irregular students additionally take 1 subject from
    //    another year level of the same department.
    // ════════════════════════════════════════════════════════════════════════
    $stmtEnroll = $db->prepare("INSERT OR IGNORE INTO enrollments (student_id, section_id) VALUES (:sid, :secid)");
    $allStudentRows = $db->query("SELECT id, block_id, student_type FROM students ORDER BY id")->fetchAll();
    $studentTypeByBlock = []; // [studentId] => type
    foreach ($allStudentRows as $r) {
        $studentTypeByBlock[(int) $r['id']] = $r['student_type'];
    }

    $enrolled = 0;
    $irregularExtras = 0;
    foreach ($allStudentRows as $student) {
        $studentId = (int) $student['id'];
        $blockId = (int) $student['block_id'];

        // Own block's sections
        foreach ($sectionsByBlock[$blockId] ?? [] as $sectionId) {
            $stmtEnroll->execute([':sid' => $studentId, ':secid' => $sectionId]);
            $enrolled += $stmtEnroll->rowCount();
        }

        // Irregular: one section from another year level of the same department
        if ($student['student_type'] === 'Irregular') {
            $blockRow = null;
            foreach ($allBlockRows as $br) {
                if ((int) $br['id'] === $blockId) {
                    $blockRow = $br;
                    break;
                }
            }
            if ($blockRow) {
                $deptCode = $blockRow['department_code'];
                $year = (int) $blockRow['year_level'];
                $candidates = [];
                foreach ($sectionsByDeptYear[$deptCode] ?? [] as $otherYear => $secs) {
                    if ($otherYear !== $year) {
                        foreach ($secs as $secId) {
                            $candidates[] = $secId;
                        }
                    }
                }
                if ($candidates) {
                    $pick = $candidates[array_rand($candidates)];
                    $stmtEnroll->execute([':sid' => $studentId, ':secid' => $pick]);
                    $enrolled += $stmtEnroll->rowCount();
                    $irregularExtras += $stmtEnroll->rowCount();
                }
            }
        }
    }
    echo "✓ Enrollments seeded ($enrolled total, $irregularExtras irregular extras)\n";

    $db->commit();
    echo "\n🎉 Database seeded successfully!\n";

    // ── Summary ──────────────────────────────────────────────────────────────
    echo "\n── Summary ──────────────────────────────────────────────\n";
    echo "Departments:      " . querySingle("SELECT COUNT(*) FROM departments") . "\n";
    echo "Programs:         " . querySingle("SELECT COUNT(*) FROM programs") . "\n";
    echo "Blocks:           " . querySingle("SELECT COUNT(*) FROM blocks") . "\n";
    echo "Subjects:         " . querySingle("SELECT COUNT(*) FROM subjects") . "\n";
    echo "Instructors:      " . querySingle("SELECT COUNT(*) FROM instructors") . "\n";
    echo "Students:         " . querySingle("SELECT COUNT(*) FROM students") . "\n";
    echo "  - Regular:      " . querySingle("SELECT COUNT(*) FROM students WHERE student_type = 'Regular'") . "\n";
    echo "  - Irregular:    " . querySingle("SELECT COUNT(*) FROM students WHERE student_type = 'Irregular'") . "\n";
    echo "Course Sections:  " . querySingle("SELECT COUNT(*) FROM course_sections") . "\n";
    echo "Enrollments:      " . querySingle("SELECT COUNT(*) FROM enrollments") . "\n";
    echo "\nDemo logins (default password: password123):\n";
    echo "  admin / admin123\n";
    echo "  " . querySingle("SELECT employee_id FROM instructors ORDER BY id LIMIT 1") . "  Maria Santos (instructor)\n";
    echo "  " . querySingle("SELECT student_id FROM students ORDER BY id LIMIT 1") . "  Juan Dela Cruz (student)\n";

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}