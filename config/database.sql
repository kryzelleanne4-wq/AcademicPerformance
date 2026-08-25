<?php
/**
 * Database Schema
 * Run this file once to initialize the database
 * Usage: php config/database.sql
 */

require_once __DIR__ . '/database.php';

$db = getDB();

// Create Students table
$db->exec("
    CREATE TABLE IF NOT EXISTS students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id TEXT UNIQUE NOT NULL,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        email TEXT,
        phone TEXT,
        date_of_birth DATE,
        gender TEXT CHECK(gender IN ('Male', 'Female', 'Other')),
        enrollment_date DATE DEFAULT CURRENT_DATE,
        status TEXT DEFAULT 'Active' CHECK(status IN ('Active', 'Inactive', 'Graduated')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Create Subjects table
$db->exec("
    CREATE TABLE IF NOT EXISTS subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        subject_code TEXT UNIQUE NOT NULL,
        subject_name TEXT NOT NULL,
        description TEXT,
        credits INTEGER DEFAULT 3,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Create Grades table
$db->exec("
    CREATE TABLE IF NOT EXISTS grades (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        subject_id INTEGER NOT NULL,
        semester TEXT NOT NULL,
        year INTEGER NOT NULL,
        score REAL,
        grade TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
    )
");

// Create Users table for login
$db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        full_name TEXT NOT NULL,
        role TEXT DEFAULT 'teacher' CHECK(role IN ('admin', 'teacher')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Insert default admin user (password: admin123)
$hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $db->prepare("INSERT OR IGNORE INTO users (username, password, full_name, role) VALUES (:username, :password, :full_name, :role)");
$stmt->execute([
    ':username' => 'admin',
    ':password' => $hashedPassword,
    ':full_name' => 'Administrator',
    ':role' => 'admin'
]);

echo "Database initialized successfully!\n";
