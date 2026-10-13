<?php
/**
 * Database Configuration
 * PDO SQLite connection setup
 */

define('DB_PATH', __DIR__ . '/../database/school.db');

// Create database directory if it doesn't exist
if (!is_dir(__DIR__ . '/../database')) {
    mkdir(__DIR__ . '/../database', 0755, true);
}

// Create PDO SQLite connection
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign keys
            $db->exec('PRAGMA foreign_keys = ON');

            ensureSchema($db);
            
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $db;
}

// Create any tables that may be missing from an existing database file.
// Kept idempotent so it is safe to run on every connection.
function ensureSchema(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS rooms (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            room_code  TEXT NOT NULL UNIQUE,
            floor      TEXT,
            capacity   INTEGER,
            is_active  INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // First run on an existing dataset: adopt the room names already stored on
    // schedules so nothing that is in use disappears from the room dropdown.
    if ((int) $db->query("SELECT COUNT(*) FROM rooms")->fetchColumn() === 0) {
        $existing = $db->query("
            SELECT DISTINCT room FROM course_sections
            WHERE room IS NOT NULL AND TRIM(room) != ''
            ORDER BY room
        ")->fetchAll(PDO::FETCH_COLUMN);

        if ($existing) {
            $insert = $db->prepare("INSERT OR IGNORE INTO rooms (room_code) VALUES (:room)");
            foreach ($existing as $room) {
                $insert->execute([':room' => $room]);
            }
        }
    }
}

// Helper: Get single value from query (replaces SQLite3::querySingle)
function querySingle($sql) {
    $db = getDB();
    $stmt = $db->query($sql);
    return $stmt->fetchColumn();
}
