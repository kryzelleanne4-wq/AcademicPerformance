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
            
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    return $db;
}

// Helper: Get single value from query (replaces SQLite3::querySingle)
function querySingle($sql) {
    $db = getDB();
    $stmt = $db->query($sql);
    return $stmt->fetchColumn();
}
