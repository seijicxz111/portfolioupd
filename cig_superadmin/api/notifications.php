<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['admin_logged_in'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

require_once '../db/config.php';
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

$action = $_GET['action'] ?? 'list';

// ── LIST: pending submissions as notifications ────────────────────────────
if ($action === 'list') {
    $res = mysqli_query($conn, "
        SELECT s.submission_id, s.title, s.submitted_at, s.status,
               u.org_name, u.org_code
        FROM submissions s
        JOIN users u ON s.org_id = u.user_id
        WHERE s.status IN ('pending','in_review')
        ORDER BY s.submitted_at DESC
        LIMIT 20
    ");
    $items = [];
    while ($r = mysqli_fetch_assoc($res)) $items[] = $r;

    $count_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM submissions WHERE status='pending'");
    $count = mysqli_fetch_assoc($count_res)['cnt'];

    echo json_encode(['success' => true, 'count' => (int)$count, 'notifications' => $items]);
    exit;
}

// ── MARK READ (optional future use) ──────────────────────────────────────
if ($action === 'mark_read' && isset($_GET['submission_id'])) {
    $sid = (int)$_GET['submission_id'];
    mysqli_query($conn, "UPDATE submissions SET status='in_review' WHERE submission_id=$sid AND status='pending'");
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
mysqli_close($conn);