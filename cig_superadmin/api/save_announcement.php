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

$action          = trim($_POST['action'] ?? '');
$announcement_id = (int) ($_POST['announcement_id'] ?? 0);
$admin_id        = $_SESSION['admin_id'] ?? 1;

// ── DELETE ──────────────────────────────────────────────────
if ($action === 'delete') {
    if (!$announcement_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
        exit();
    }
    $stmt = mysqli_prepare($conn, "DELETE FROM announcements WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $announcement_id);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . mysqli_error($conn)]);
    }
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit();
}

// ── ADD or EDIT ──────────────────────────────────────────────
$title      = trim($_POST['title']       ?? '');
$content    = trim($_POST['content']     ?? '');
$priority   = trim($_POST['priority']    ?? 'low');
$category   = trim($_POST['category']   ?? 'general');
$audience   = trim($_POST['audience']   ?? '');   // comma-separated org_codes or empty = all
$expires_at = trim($_POST['expires_at'] ?? '');
$is_pinned  = isset($_POST['is_pinned']) && $_POST['is_pinned'] == '1' ? 1 : 0;

$allowed_priorities = ['low', 'high', 'urgent'];
if (!in_array($priority, $allowed_priorities)) $priority = 'low';

$allowed_categories = ['general', 'event', 'deadline', 'policy'];
if (!in_array($category, $allowed_categories)) $category = 'general';

// Sanitise expires_at — must be a future date or empty
if ($expires_at !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires_at)) {
    $expires_at = '';
}
$expires_param = $expires_at !== '' ? $expires_at : null;

// Sanitise audience — strip to comma-separated alphanum/underscore tokens
if ($audience !== '') {
    $parts    = array_filter(array_map('trim', explode(',', $audience)));
    $parts    = array_filter($parts, fn($p) => preg_match('/^[A-Za-z0-9_\-]+$/', $p));
    $audience = implode(',', $parts);
}
$audience_param = $audience !== '' ? $audience : null;

if (empty($title) || empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Title and content are required']);
    exit();
}

if ($announcement_id) {
    // EDIT existing
    $stmt = mysqli_prepare($conn,
        "UPDATE announcements
         SET title = ?, content = ?, priority = ?, category = ?,
             audience = ?, expires_at = ?, is_pinned = ?,
             updated_by = ?, updated_at = NOW()
         WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'ssssssiii',
        $title, $content, $priority, $category,
        $audience_param, $expires_param, $is_pinned,
        $admin_id, $announcement_id);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success'    => true,
            'id'         => $announcement_id,
            'title'      => htmlspecialchars($title),
            'content'    => htmlspecialchars($content),
            'priority'   => $priority,
            'category'   => $category,
            'audience'   => $audience,
            'expires_at' => $expires_at,
            'is_pinned'  => $is_pinned,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed: ' . mysqli_error($conn)]);
    }
} else {
    // ADD new
    $stmt = mysqli_prepare($conn,
        "INSERT INTO announcements
            (title, content, priority, category, audience, expires_at, is_pinned, created_by, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
    mysqli_stmt_bind_param($stmt, 'ssssssii',
        $title, $content, $priority, $category,
        $audience_param, $expires_param, $is_pinned, $admin_id);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'success'    => true,
            'id'         => mysqli_insert_id($conn),
            'title'      => htmlspecialchars($title),
            'content'    => htmlspecialchars($content),
            'priority'   => $priority,
            'category'   => $category,
            'audience'   => $audience,
            'expires_at' => $expires_at,
            'is_pinned'  => $is_pinned,
            'created_at' => date('M d, Y'),
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Insert failed: ' . mysqli_error($conn)]);
    }
}

mysqli_stmt_close($stmt);
mysqli_close($conn);