<?php
/**
 * College Academic Performance Database Schema
 *
 * This file is a PHP database setup script despite its .sql extension.
 * Run it once with: php config/database.sql
 *
 * The schema keeps the existing subjects, students, and grades table names
 * so the current pages remain compatible while adding college workflows.
 */

require_once __DIR__ . '/database.php';

$db = getDB();
$db->exec('PRAGMA foreign_keys = ON');

function tableExists(PDO $db, $table) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :table");
    $stmt->execute([':table' => $table]);
    return (bool) $stmt->fetchColumn();
}

function tableColumns(PDO $db, $table) {
    $columns = [];
    $stmt = $db->query('PRAGMA table_info(' . str_replace('`', '', $table) . ')');
    foreach ($stmt->fetchAll() as $column) {
        $columns[$column['name']] = true;
    }
    return $columns;
}

function addColumnIfMissing(PDO $db, $table, $column, $definition) {
    $columns = tableColumns($db, $table);
    if (!isset($columns[$column])) {
        $db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }
}

$db->beginTransaction();

try {
    // The old schema allowed only admin and teacher. Rebuild that table once
    // so existing accounts are retained and teacher accounts become instructors.
    if (tableExists($db, 'users')) {
        $usersSql = $db->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();
        if (strpos((string) $usersSql, "'instructor'") === false) {
            $db->exec('DROP TABLE IF EXISTS users_new');
            $db->exec("\n                CREATE TABLE users_new (\n                    id INTEGER PRIMARY KEY AUTOINCREMENT,\n                    username TEXT NOT NULL UNIQUE COLLATE NOCASE,\n                    password TEXT NOT NULL,\n                    full_name TEXT NOT NULL,\n                    email TEXT UNIQUE COLLATE NOCASE,\n                    role TEXT NOT NULL DEFAULT 'student' CHECK(role IN ('admin', 'instructor', 'student')),\n                    is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0, 1)),\n                    last_login_at DATETIME,\n                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP\n                )\n            ");
            $db->exec("\n                INSERT INTO users_new (id, username, password, full_name, role, created_at)\n                SELECT id, username, password, full_name,\n                       CASE WHEN role = 'teacher' THEN 'instructor' ELSE role END,\n                       created_at\n                FROM users\n            ");
            $db->exec('DROP TABLE users');
            $db->exec('ALTER TABLE users_new RENAME TO users');
        }
    } else {
        $db->exec("\n            CREATE TABLE users (\n                id INTEGER PRIMARY KEY AUTOINCREMENT,\n                username TEXT NOT NULL UNIQUE COLLATE NOCASE,\n                password TEXT NOT NULL,\n                full_name TEXT NOT NULL,\n                email TEXT UNIQUE COLLATE NOCASE,\n                role TEXT NOT NULL DEFAULT 'student' CHECK(role IN ('admin', 'instructor', 'student')),\n                is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0, 1)),\n                last_login_at DATETIME,\n                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP\n            )\n        ");
    }

    // Organizational structure for college programs and their courses.
    $db->exec("\n        CREATE TABLE IF NOT EXISTS departments (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            department_code TEXT NOT NULL UNIQUE COLLATE NOCASE,\n            department_name TEXT NOT NULL,\n            description TEXT,\n            is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0, 1)),\n            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP\n        )\n    ");

    $db->exec("\n        CREATE TABLE IF NOT EXISTS programs (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            department_id INTEGER NOT NULL,\n            program_code TEXT NOT NULL UNIQUE COLLATE NOCASE,\n            program_name TEXT NOT NULL,\n            degree_level TEXT NOT NULL DEFAULT 'Bachelor' CHECK(degree_level IN ('Certificate', 'Associate', 'Bachelor', 'Master', 'Doctorate')),\n            duration_years REAL,\n            is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0, 1)),\n            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT\n        )\n    ");

    $db->exec("\n        CREATE TABLE IF NOT EXISTS academic_terms (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            term_code TEXT NOT NULL UNIQUE COLLATE NOCASE,\n            term_name TEXT NOT NULL,\n            academic_year TEXT NOT NULL,\n            start_date DATE NOT NULL,\n            end_date DATE NOT NULL,\n            is_current INTEGER NOT NULL DEFAULT 0 CHECK(is_current IN (0, 1)),\n            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            CHECK(end_date >= start_date)\n        )\n    ");

    // Existing student columns are preserved; these fields connect students
    // to login accounts and college programs.
    if (!tableExists($db, 'students')) {
        $db->exec("\n            CREATE TABLE students (\n                id INTEGER PRIMARY KEY AUTOINCREMENT,\n                user_id INTEGER UNIQUE,\n                student_id TEXT NOT NULL UNIQUE COLLATE NOCASE,\n                first_name TEXT NOT NULL,\n                last_name TEXT NOT NULL,\n                email TEXT UNIQUE COLLATE NOCASE,\n                phone TEXT,\n                date_of_birth DATE,\n                gender TEXT CHECK(gender IN ('Male', 'Female', 'Other')),\n                program_id INTEGER,\n                year_level INTEGER CHECK(year_level IS NULL OR year_level BETWEEN 1 AND 12),\n                enrollment_date DATE NOT NULL DEFAULT CURRENT_DATE,\n                expected_graduation_date DATE,\n                status TEXT NOT NULL DEFAULT 'Active' CHECK(status IN ('Active', 'Inactive', 'Graduated', 'Suspended')),\n                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,\n                FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE SET NULL\n            )\n        ");
    } else {
        addColumnIfMissing($db, 'students', 'user_id', 'INTEGER REFERENCES users(id) ON DELETE SET NULL');
        addColumnIfMissing($db, 'students', 'program_id', 'INTEGER REFERENCES programs(id) ON DELETE SET NULL');
        addColumnIfMissing($db, 'students', 'year_level', 'INTEGER');
        addColumnIfMissing($db, 'students', 'expected_graduation_date', 'DATE');
        addColumnIfMissing($db, 'students', 'updated_at', 'DATETIME');
    }

    $db->exec("\n        CREATE TABLE IF NOT EXISTS instructors (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            user_id INTEGER NOT NULL UNIQUE,\n            employee_id TEXT NOT NULL UNIQUE COLLATE NOCASE,\n            first_name TEXT NOT NULL,\n            last_name TEXT NOT NULL,\n            email TEXT UNIQUE COLLATE NOCASE,\n            phone TEXT,\n            department_id INTEGER,\n            title TEXT,\n            specialization TEXT,\n            status TEXT NOT NULL DEFAULT 'Active' CHECK(status IN ('Active', 'Inactive', 'On Leave')),\n            hired_date DATE,\n            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,\n            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL\n        )\n    ");

    // subjects is the compatibility name for the college course catalog.
    if (!tableExists($db, 'subjects')) {
        $db->exec("\n            CREATE TABLE subjects (\n                id INTEGER PRIMARY KEY AUTOINCREMENT,\n                department_id INTEGER,\n                subject_code TEXT NOT NULL UNIQUE COLLATE NOCASE,\n                subject_name TEXT NOT NULL,\n                description TEXT,\n                credits INTEGER NOT NULL DEFAULT 3 CHECK(credits > 0 AND credits <= 8),\n                course_level INTEGER CHECK(course_level IS NULL OR course_level BETWEEN 1 AND 8),\n                is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0, 1)),\n                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL\n            )\n        ");
    } else {
        addColumnIfMissing($db, 'subjects', 'department_id', 'INTEGER REFERENCES departments(id) ON DELETE SET NULL');
        addColumnIfMissing($db, 'subjects', 'course_level', 'INTEGER');
        addColumnIfMissing($db, 'subjects', 'is_active', "INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0, 1))");
        addColumnIfMissing($db, 'subjects', 'updated_at', 'DATETIME');
    }

    $db->exec("\n        CREATE TABLE IF NOT EXISTS course_sections (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            subject_id INTEGER NOT NULL,\n            instructor_id INTEGER NOT NULL,\n            term_id INTEGER NOT NULL,\n            section_code TEXT NOT NULL COLLATE NOCASE,\n            room TEXT,\n            schedule TEXT,\n            capacity INTEGER CHECK(capacity IS NULL OR capacity > 0),\n            status TEXT NOT NULL DEFAULT 'Open' CHECK(status IN ('Open', 'Closed', 'Completed', 'Cancelled')),\n            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            UNIQUE(subject_id, term_id, section_code),\n            FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE RESTRICT,\n            FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE RESTRICT,\n            FOREIGN KEY (term_id) REFERENCES academic_terms(id) ON DELETE RESTRICT\n        )\n    ");

    $db->exec("\n        CREATE TABLE IF NOT EXISTS enrollments (\n            id INTEGER PRIMARY KEY AUTOINCREMENT,\n            student_id INTEGER NOT NULL,\n            section_id INTEGER NOT NULL,\n            enrolled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n            status TEXT NOT NULL DEFAULT 'Enrolled' CHECK(status IN ('Enrolled', 'Dropped', 'Completed', 'Withdrawn')),\n            final_score REAL CHECK(final_score IS NULL OR final_score BETWEEN 0 AND 100),\n            final_grade TEXT,\n            remarks TEXT,\n            UNIQUE(student_id, section_id),\n            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,\n            FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE CASCADE\n        )\n    ");

    // grades remains the page-facing final-grade table. New records can point
    // to sections/enrollments while legacy records continue using semester/year.
    if (!tableExists($db, 'grades')) {
        $db->exec("\n            CREATE TABLE grades (\n                id INTEGER PRIMARY KEY AUTOINCREMENT,\n                student_id INTEGER NOT NULL,\n                subject_id INTEGER NOT NULL,\n                section_id INTEGER,\n                enrollment_id INTEGER,\n                instructor_id INTEGER,\n                semester TEXT NOT NULL,\n                year INTEGER NOT NULL,\n                score REAL CHECK(score IS NULL OR score BETWEEN 0 AND 100),\n                grade TEXT,\n                assessment_type TEXT NOT NULL DEFAULT 'Final',\n                status TEXT NOT NULL DEFAULT 'Published' CHECK(status IN ('Draft', 'Published', 'Locked')),\n                remarks TEXT,\n                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,\n                FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,\n                FOREIGN KEY (section_id) REFERENCES course_sections(id) ON DELETE SET NULL,\n                FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE SET NULL,\n                FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE SET NULL\n            )\n        ");
    } else {
        addColumnIfMissing($db, 'grades', 'section_id', 'INTEGER REFERENCES course_sections(id) ON DELETE SET NULL');
        addColumnIfMissing($db, 'grades', 'enrollment_id', 'INTEGER REFERENCES enrollments(id) ON DELETE SET NULL');
        addColumnIfMissing($db, 'grades', 'instructor_id', 'INTEGER REFERENCES instructors(id) ON DELETE SET NULL');
        addColumnIfMissing($db, 'grades', 'assessment_type', "TEXT NOT NULL DEFAULT 'Final'");
        addColumnIfMissing($db, 'grades', 'status', "TEXT NOT NULL DEFAULT 'Published' CHECK(status IN ('Draft', 'Published', 'Locked'))");
        addColumnIfMissing($db, 'grades', 'remarks', 'TEXT');
        addColumnIfMissing($db, 'grades', 'updated_at', 'DATETIME');
    }

    // Supporting indexes for common dashboard, roster, and gradebook queries.
    $indexes = [
        'CREATE UNIQUE INDEX IF NOT EXISTS idx_students_user_id ON students(user_id) WHERE user_id IS NOT NULL',
        'CREATE INDEX IF NOT EXISTS idx_students_program_status ON students(program_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_instructors_department_status ON instructors(department_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_subjects_department ON subjects(department_id)',
        'CREATE INDEX IF NOT EXISTS idx_sections_instructor_term ON course_sections(instructor_id, term_id)',
        'CREATE INDEX IF NOT EXISTS idx_sections_subject_term ON course_sections(subject_id, term_id)',
        'CREATE INDEX IF NOT EXISTS idx_enrollments_student_status ON enrollments(student_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_enrollments_section_status ON enrollments(section_id, status)',
        'CREATE INDEX IF NOT EXISTS idx_grades_student_term ON grades(student_id, year, semester)',
        'CREATE INDEX IF NOT EXISTS idx_grades_subject_term ON grades(subject_id, year, semester)',
        'CREATE INDEX IF NOT EXISTS idx_grades_instructor ON grades(instructor_id)'
    ];
    foreach ($indexes as $indexSql) {
        $db->exec($indexSql);
    }

    // Keep one local administrator available for first-time setup.
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("\n        INSERT OR IGNORE INTO users (username, password, full_name, role, is_active)\n        VALUES (:username, :password, :full_name, 'admin', 1)\n    ");
    $stmt->execute([
        ':username' => 'admin',
        ':password' => $hashedPassword,
        ':full_name' => 'Administrator'
    ]);

    $db->commit();
    echo "College academic database initialized successfully!\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}
