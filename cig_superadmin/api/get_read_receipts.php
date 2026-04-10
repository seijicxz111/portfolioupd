<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Catch fatal errors and return JSON instead of 500
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'PHP fatal error: ' . $err['message'] . ' in ' . $err['file'] . ' line ' . $err['line']
        ]);
    }
});

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized — not logged in as admin']);
    exit();
}

require_once __DIR__ . '/../db/config.php';

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if (!$conn) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'DB connect failed: ' . mysqli_connect_error()]);
    exit();
}

$ann_id = (int) ($_GET['announcement_id'] ?? 0);
if (!$ann_id) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid announcement_id']);
    exit();
}

// Total orgs denominator
$res          = mysqli_query($conn, "SELECT audience FROM announcements WHERE announcement_id = $ann_id LIMIT 1");
$row          = $res ? mysqli_fetch_assoc($res) : null;
$audience_raw = trim($row['audience'] ?? '');

if ($audience_raw !== '') {
    $codes     = array_map(function($c) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, trim($c)) . "'";
    }, explode(',', $audience_raw));
    $total_res = mysqli_query($conn,
        "SELECT COUNT(DISTINCT org_code) AS cnt FROM users
         WHERE org_code IN (" . implode(',', $codes) . ")
           AND status = 'active' AND org_code IS NOT NULL");
} else {
    $total_res = mysqli_query($conn,
        "SELECT COUNT(DISTINCT org_code) AS cnt FROM users
         WHERE org_code IS NOT NULL AND status = 'active'");
}

$total_row = $total_res ? mysqli_fetch_assoc($total_res) : null;
$total     = isset($total_row['cnt']) ? (int)$total_row['cnt'] : 0;

// Who read it
$reads_res = mysqli_query($conn,
    "SELECT ar.org_code, ar.read_at, u.org_name
     FROM announcement_reads ar
     LEFT JOIN users u ON ar.org_code = u.org_code
     WHERE ar.announcement_id = $ann_id
     GROUP BY ar.org_code
     ORDER BY ar.read_at DESC");

$readers = [];
if ($reads_res) {
    while ($r = mysqli_fetch_assoc($reads_res)) {
        $readers[] = [
            'org_code' => $r['org_code'],
            'org_name' => !empty($r['org_name']) ? $r['org_name'] : $r['org_code'],
            'read_at'  => $r['read_at'],
        ];
    }
}

mysqli_close($conn);
ob_end_clean();
echo json_encode([
    'success' => true,
    'total'   => $total,
    'read'    => count($readers),
    'readers' => $readers,
]);