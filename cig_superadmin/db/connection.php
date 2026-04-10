<?php
/**
 * db/connection.php
 * Thin wrapper: loads config constants and returns a mysqli connection.
 * Used by api/organizations.php and api/users.php.
 */

require_once __DIR__ . '/config.php';

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if (!$conn) {
    // If called from an API file, return JSON error; otherwise plain text
    $isApi = (strpos($_SERVER['SCRIPT_FILENAME'] ?? '', '/api/') !== false);
    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]);
    } else {
        die('Database connection failed: ' . mysqli_connect_error());
    }
    exit();
}

mysqli_set_charset($conn, 'utf8mb4');
