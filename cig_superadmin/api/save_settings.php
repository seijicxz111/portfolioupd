<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../db/config.php';

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Collect which keys were submitted
$allowed_keys = ['mission', 'vision', 'values', 'president_name', 'president_title', 'dean_name', 'dean_title'];
$updates = [];
foreach ($allowed_keys as $key) {
    if (isset($_POST[$key])) {
        $updates[$key] = trim($_POST[$key]);
    }
}

if (empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'No data provided']);
    exit();
}

// Upsert each key into site_settings
$stmt = mysqli_prepare($conn,
    "INSERT INTO site_settings (setting_key, setting_value)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . mysqli_error($conn)]);
    exit();
}

foreach ($updates as $key => $value) {
    mysqli_stmt_bind_param($stmt, 'ss', $key, $value);
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => false, 'message' => 'Save failed: ' . mysqli_error($conn)]);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        exit();
    }
}

mysqli_stmt_close($stmt);
mysqli_close($conn);

echo json_encode(['success' => true]);
