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

// Add a column to an existing table when it is missing (idempotent).
function ensureColumn(PDO $db, $table, $column, $definition) {
    $columns = [];
    $stmt = $db->query('PRAGMA table_info(' . str_replace('`', '', $table) . ')');
    foreach ($stmt->fetchAll() as $existing) {
        $columns[$existing['name']] = true;
    }
    if (!isset($columns[$column])) {
        $db->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }
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

    // ── SMS delivery log ────────────────────────────────────────────────
    // One row per text message the system tried to deliver, so the admin
    // page can show what was sent, when, and why something failed.
    $db->exec("
        CREATE TABLE IF NOT EXISTS sms_notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            notification_type TEXT NOT NULL,
            title TEXT,
            message TEXT,
            status TEXT NOT NULL DEFAULT 'sent',
            error TEXT,
            reference_id INTEGER,
            sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        )
    ");
    // Earlier versions created a slimmer table; add the new columns in place.
    ensureColumn($db, 'sms_notifications', 'title', 'TEXT');
    ensureColumn($db, 'sms_notifications', 'message', 'TEXT');
    ensureColumn($db, 'sms_notifications', 'status', "TEXT NOT NULL DEFAULT 'sent'");
    ensureColumn($db, 'sms_notifications', 'error', 'TEXT');

    // ── In-app notifications ───────────────────────────────────────────
    // These power the student's Notifications page. They are always created
    // next to an SMS so the message is still readable if the phone is off.
    $db->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            notification_type TEXT NOT NULL,
            title TEXT NOT NULL,
            message TEXT NOT NULL,
            reference_id INTEGER,
            read_at DATETIME,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        )
    ");

    // Small key/value store (used to remember the last reminder run).
    $db->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            setting_key TEXT PRIMARY KEY,
            setting_value TEXT,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $db->exec('CREATE INDEX IF NOT EXISTS idx_notifications_student ON notifications(student_id, read_at)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_sms_notifications_student ON sms_notifications(student_id, sent_at)');
}

// Helper: Get single value from query (replaces SQLite3::querySingle)
function querySingle($sql) {
    $db = getDB();
    $stmt = $db->query($sql);
    return $stmt->fetchColumn();
}
