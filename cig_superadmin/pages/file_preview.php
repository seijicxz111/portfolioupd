<?php
/**
 * file_preview.php  –  Admin version
 * Streams submission files to the browser for inline preview.
 */
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    die("Unauthorized");
}

require_once '../db/config.php';
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if (!$conn) { http_response_code(500); die("Connection failed"); }

$submissionId = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;
$download     = isset($_GET['download']) && $_GET['download'] === '1';

if (!$submissionId) { http_response_code(400); die("Invalid submission ID"); }

$stmt = $conn->prepare("SELECT file_name, file_path FROM submissions WHERE submission_id = ? LIMIT 1");
$stmt->bind_param("i", $submissionId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) { http_response_code(404); die("Document not found"); }

$fileName = $row['file_name'];
$ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

// cig_superadmin/pages/ → ../../cig_user/org-dashboard/uploads/submissions/
$uploadsDir = realpath(__DIR__ . '/../../cig_user/org-dashboard/uploads/submissions');
$diskPath   = $uploadsDir . '/' . basename($row['file_name']);

if (!file_exists($diskPath)) {
    http_response_code(404);
    die("File not found on disk: " . htmlspecialchars($diskPath));
}

$fileBytes = file_get_contents($diskPath);
if (empty($fileBytes)) { http_response_code(404); die("File content not found"); }

$mimeMap = [
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'doc'  => 'application/msword',
    'xls'  => 'application/vnd.ms-excel',
    'txt'  => 'text/plain',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];

while (ob_get_level()) { ob_end_clean(); }

if ($download) {
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . addslashes($fileName) . '"');
} elseif (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt'])) {
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . addslashes($fileName) . '"');
} else {
    header('Content-Type: application/octet-stream');
}

header('Content-Length: ' . mb_strlen($fileBytes, '8bit'));
header('Cache-Control: no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');

echo $fileBytes;
exit;