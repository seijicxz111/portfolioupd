<?php
/**
 * docx_to_pdf.php  –  Admin version
 * Converts a submitted DOCX to PDF via LibreOffice and streams it inline.
 */
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(401);
    die("Unauthorized");
}

require_once '../db/config.php';

$submissionId = isset($_GET['submission_id']) ? (int)$_GET['submission_id'] : 0;
if (!$submissionId) { http_response_code(400); die("Invalid submission ID"); }

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if (!$conn) { http_response_code(500); die("DB error"); }

$stmt = $conn->prepare("SELECT file_name, file_path FROM submissions WHERE submission_id = ? LIMIT 1");
$stmt->bind_param("i", $submissionId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) { http_response_code(404); die("Not found"); }

// cig_superadmin/pages/ → ../../cig_user/org-dashboard/uploads/submissions/
$uploadsDir = realpath(__DIR__ . '/../../cig_user/org-dashboard/uploads/submissions');
$diskPath   = $uploadsDir . '/' . basename($row['file_name']);

if (!file_exists($diskPath)) {
    http_response_code(404);
    die("File not found on disk: " . htmlspecialchars($diskPath));
}

$ext = strtolower(pathinfo($diskPath, PATHINFO_EXTENSION));

// Already a PDF — stream directly
if ($ext === 'pdf') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($diskPath) . '"');
    header('Content-Length: ' . filesize($diskPath));
    readfile($diskPath);
    exit;
}

// ── LibreOffice conversion ────────────────────────────────────
$tmpDir  = sys_get_temp_dir() . '/docx_preview_' . $submissionId . '_' . time();
if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);
$srcCopy = $tmpDir . '/' . basename($diskPath);
copy($diskPath, $srcCopy);

$loPaths = [
    'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
    'C:\\Program Files\\LibreOffice\\program\\soffice',
    'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
    'C:\\Program Files (x86)\\LibreOffice\\program\\soffice',
    '/usr/bin/soffice',
    '/usr/lib/libreoffice/program/soffice',
    '/opt/libreoffice/program/soffice',
    'soffice',
];

$soffice = null;
foreach ($loPaths as $p) {
    if (file_exists($p)) { $soffice = $p; break; }
}
if (!$soffice) {
    $isWin = strncasecmp(PHP_OS, 'WIN', 3) === 0;
    $found = trim((string)@shell_exec($isWin ? 'where soffice.exe 2>nul' : 'which soffice 2>/dev/null'));
    if ($found) $soffice = strtok($found, "\r\n");
}

if (!$soffice) {
    @unlink($srcCopy); @rmdir($tmpDir);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>body{font-family:Arial,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f5f5f5;}
    .box{background:#fff;padding:2rem 2.5rem;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.15);text-align:center;max-width:420px;}
    .icon{font-size:3rem;margin-bottom:1rem;}h3{margin:0 0 .5rem;color:#333;}p{color:#666;font-size:.9rem;margin:0;}
    </style></head><body><div class="box"><div class="icon">📄</div>
    <h3>Preview unavailable</h3>
    <p>LibreOffice is not installed on this server.<br>Install LibreOffice to enable DOCX preview.</p>
    </div></body></html>';
    exit;
}

$cmd        = sprintf('"%s" --headless --convert-to pdf --outdir "%s" "%s" 2>&1', $soffice, $tmpDir, $srcCopy);
$output     = [];
$returnCode = 0;
exec($cmd, $output, $returnCode);

$pdfPath = $tmpDir . '/' . pathinfo($srcCopy, PATHINFO_FILENAME) . '.pdf';

if ($returnCode !== 0 || !file_exists($pdfPath)) {
    @unlink($srcCopy); @rmdir($tmpDir);
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    $errDetail = implode('<br>', array_map('htmlspecialchars', $output));
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>body{font-family:Arial,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f5f5f5;}
    .box{background:#fff;padding:2rem 2.5rem;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.15);text-align:center;max-width:480px;}
    .icon{font-size:3rem;margin-bottom:1rem;}h3{margin:0 0 .5rem;color:#c0392b;}p{color:#666;font-size:.85rem;margin:.5rem 0 0;}
    </style></head><body><div class="box"><div class="icon">⚠️</div>
    <h3>Conversion failed</h3><p>' . $errDetail . '</p>
    </div></body></html>';
    exit;
}

while (ob_get_level()) ob_end_clean();
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="preview.pdf"');
header('Content-Length: ' . filesize($pdfPath));
header('Cache-Control: private, max-age=300');
readfile($pdfPath);

@unlink($srcCopy);
@unlink($pdfPath);
@rmdir($tmpDir);
exit;