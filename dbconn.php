<?php
/**
 * Database Connection Configuration
 * KR ASSESSLY - Smart Assessment Portal
 * 
 * This file provides a centralized database connection
 * that can be included in all PHP files requiring database access.
 */

// Prevent direct access to this file
if (!defined('DB_ACCESS')) {
    define('DB_ACCESS', true);
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_kr_assessly');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Global database connection variable
$conn = null;

/**
 * Get database connection
 * @return PDO|null Returns PDO connection object or null on failure
 */
function getDBConnection() {
    global $conn;
    
    // Return existing connection if already established
    if ($conn !== null) {
        return $conn;
    }
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];
        
        $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        return $conn;
        
    } catch (PDOException $e) {
        // Log error (in production, use proper error logging)
        error_log("Database Connection Error: " . $e->getMessage());
        
        // Return null on connection failure
        return null;
    }
}

/**
 * Close database connection
 */
function closeDBConnection() {
    global $conn;
    $conn = null;
}

/**
 * Check if database connection is active
 * @return bool
 */
function isDBConnected() {
    global $conn;
    return $conn !== null;
}

// Automatically establish connection when file is included
$conn = getDBConnection();

// Handle connection failure
if ($conn === null) {
    // Only output JSON error if this is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Database connection failed. Please contact administrator.'
        ]);
        exit();
    }
}
?>