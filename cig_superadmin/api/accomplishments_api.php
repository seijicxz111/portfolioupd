<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['admin_logged_in'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

require_once '../db/config.php';
$conn   = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── GET YEARS ─────────────────────────────────────────────────────────────
if ($action === 'get_years') {
    $res = mysqli_query($conn, "SELECT * FROM academic_years ORDER BY start_date DESC");
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    echo json_encode(['success'=>true,'years'=>$rows]); exit;
}

// ── ADD YEAR ──────────────────────────────────────────────────────────────
if ($action === 'add_year' && $method === 'POST') {
    $label = mysqli_real_escape_string($conn, $_POST['label'] ?? '');
    $start = mysqli_real_escape_string($conn, $_POST['start_date'] ?? '');
    $end   = mysqli_real_escape_string($conn, $_POST['end_date'] ?? '');
    if (!$label || !$start || !$end) { echo json_encode(['success'=>false,'message'=>'Missing fields']); exit; }
    mysqli_query($conn, "INSERT INTO academic_years (label,start_date,end_date,is_current) VALUES ('$label','$start','$end',0)");
    echo json_encode(['success'=>true,'year_id'=>mysqli_insert_id($conn)]); exit;
}

// ── SET CURRENT YEAR ──────────────────────────────────────────────────────
if ($action === 'set_current' && $method === 'POST') {
    $yid = (int)($_POST['year_id'] ?? 0);
    mysqli_query($conn, "UPDATE academic_years SET is_current=0");
    mysqli_query($conn, "UPDATE academic_years SET is_current=1 WHERE year_id=$yid");
    echo json_encode(['success'=>true]); exit;
}

// ── GET REQUIREMENTS ──────────────────────────────────────────────────────
if ($action === 'get_requirements') {
    $yid = (int)($_GET['year_id'] ?? 0);
    if (!$yid) { echo json_encode(['success'=>false,'message'=>'year_id required']); exit; }
    $res = mysqli_query($conn, "SELECT * FROM accreditation_requirements WHERE year_id=$yid ORDER BY req_id ASC");
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    echo json_encode(['success'=>true,'requirements'=>$rows]); exit;
}

// ── ADD REQUIREMENT ───────────────────────────────────────────────────────
if ($action === 'add_requirement' && $method === 'POST') {
    $yid   = (int)($_POST['year_id'] ?? 0);
    $title = mysqli_real_escape_string($conn, $_POST['title'] ?? '');
    $desc  = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $pts   = (int)($_POST['points'] ?? 10);
    $req   = $_POST['is_required'] ?? '1';
    if (!$yid || !$title) { echo json_encode(['success'=>false,'message'=>'Missing fields']); exit; }
    mysqli_query($conn, "INSERT INTO accreditation_requirements (year_id,title,description,points,is_required) VALUES ($yid,'$title','$desc',$pts,$req)");
    echo json_encode(['success'=>true,'req_id'=>mysqli_insert_id($conn)]); exit;
}

// ── DELETE REQUIREMENT ────────────────────────────────────────────────────
if ($action === 'delete_requirement' && $method === 'POST') {
    $rid = (int)($_POST['req_id'] ?? 0);
    mysqli_query($conn, "DELETE FROM accreditation_requirements WHERE req_id=$rid");
    echo json_encode(['success'=>true]); exit;
}

// ── GET ORG ACCOMPLISHMENTS (points + accreditation + percentage) ─────────
if ($action === 'org_accomplishments') {
    $yid = (int)($_GET['year_id'] ?? 0);
    if (!$yid) { echo json_encode(['success'=>false,'message'=>'year_id required']); exit; }

    // All orgs
    $res = mysqli_query($conn, "SELECT user_id, org_name, org_code FROM users WHERE org_code IS NOT NULL AND status='active' ORDER BY org_name");
    $orgs = [];
    while ($r = mysqli_fetch_assoc($res)) $orgs[] = $r;

    // Total requirements for this year
    $req_res   = mysqli_query($conn, "SELECT COUNT(*) as cnt, COALESCE(SUM(points),0) as max_pts FROM accreditation_requirements WHERE year_id=$yid");
    $req_totals = mysqli_fetch_assoc($req_res);
    $total_reqs = (int)$req_totals['cnt'];
    $max_pts    = (int)$req_totals['max_pts'];

    // Build per-org data
    foreach ($orgs as &$org) {
        $oid = (int)$org['user_id'];

        // Points earned this year
        $pts_res = mysqli_query($conn, "SELECT COALESCE(SUM(points),0) as total FROM org_points WHERE org_id=$oid AND year_id=$yid");
        $org['points_earned'] = (int)mysqli_fetch_assoc($pts_res)['total'];

        // Approved submissions this year (for submission-based percentage)
        $sub_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM submissions WHERE org_id=$oid AND status='approved' AND year_id=$yid");
        $org['approved_submissions'] = (int)mysqli_fetch_assoc($sub_res)['cnt'];

        // Total submissions this year
        $total_sub_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM submissions WHERE org_id=$oid AND year_id=$yid");
        $org['total_submissions'] = (int)mysqli_fetch_assoc($total_sub_res)['cnt'];

        // Accreditation checklist
        $done_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM accreditation_status WHERE org_id=$oid AND year_id=$yid AND is_done=1");
        $org['accreditation_done'] = (int)mysqli_fetch_assoc($done_res)['cnt'];
        $org['accreditation_total'] = $total_reqs;

        // Percentage by submissions
        $org['pct_submissions'] = $org['total_submissions'] > 0
            ? round(($org['approved_submissions'] / $org['total_submissions']) * 100)
            : 0;

        // Percentage by points
        $org['pct_points'] = $max_pts > 0
            ? round(($org['points_earned'] / $max_pts) * 100)
            : 0;

        // Accreditation percentage
        $org['pct_accreditation'] = $total_reqs > 0
            ? round(($org['accreditation_done'] / $total_reqs) * 100)
            : 0;
    }

    echo json_encode([
        'success' => true,
        'orgs'    => $orgs,
        'total_requirements' => $total_reqs,
        'max_points' => $max_pts,
    ]);
    exit;
}

// ── GET ORG ACCREDITATION DETAIL ──────────────────────────────────────────
if ($action === 'org_accreditation_detail') {
    $oid = (int)($_GET['org_id']  ?? 0);
    $yid = (int)($_GET['year_id'] ?? 0);
    if (!$oid || !$yid) { echo json_encode(['success'=>false,'message'=>'Missing params']); exit; }

    $res = mysqli_query($conn, "
        SELECT r.req_id, r.title, r.description, r.points, r.is_required,
               COALESCE(s.is_done,0) as is_done, s.done_at, s.submission_id
        FROM accreditation_requirements r
        LEFT JOIN accreditation_status s ON s.req_id=r.req_id AND s.org_id=$oid AND s.year_id=$yid
        WHERE r.year_id=$yid
        ORDER BY r.req_id ASC
    ");
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    echo json_encode(['success'=>true,'checklist'=>$rows]); exit;
}

// ── TOGGLE ACCREDITATION ITEM ─────────────────────────────────────────────
if ($action === 'toggle_accreditation' && $method === 'POST') {
    $oid    = (int)($_POST['org_id']  ?? 0);
    $rid    = (int)($_POST['req_id']  ?? 0);
    $yid    = (int)($_POST['year_id'] ?? 0);
    $is_done = (int)($_POST['is_done'] ?? 0);
    $done_at = $is_done ? date('Y-m-d H:i:s') : null;
    $done_sql = $done_at ? "'$done_at'" : 'NULL';

    mysqli_query($conn, "
        INSERT INTO accreditation_status (org_id,req_id,year_id,is_done,done_at)
        VALUES ($oid,$rid,$yid,$is_done,$done_sql)
        ON DUPLICATE KEY UPDATE is_done=$is_done, done_at=$done_sql
    ");

    // Award points if marking done
    if ($is_done) {
        $pts_res = mysqli_query($conn, "SELECT points FROM accreditation_requirements WHERE req_id=$rid");
        $pts = (int)mysqli_fetch_assoc($pts_res)['points'];
        // Avoid double-awarding
        $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT point_id FROM org_points WHERE org_id=$oid AND year_id=$yid AND reason='accreditation_req_$rid' LIMIT 1"));
        if (!$exists && $pts > 0) {
            $reason = "accreditation_req_$rid";
            mysqli_query($conn, "INSERT INTO org_points (org_id,year_id,points,reason) VALUES ($oid,$yid,$pts,'$reason')");
        }
    } else {
        // Remove points
        $reason = "accreditation_req_$rid";
        mysqli_query($conn, "DELETE FROM org_points WHERE org_id=$oid AND year_id=$yid AND reason='$reason'");
    }

    echo json_encode(['success'=>true]); exit;
}

// ── AWARD MANUAL POINTS ───────────────────────────────────────────────────
if ($action === 'award_points' && $method === 'POST') {
    $oid    = (int)($_POST['org_id']  ?? 0);
    $yid    = (int)($_POST['year_id'] ?? 0);
    $pts    = (int)($_POST['points']  ?? 0);
    $reason = mysqli_real_escape_string($conn, $_POST['reason'] ?? 'Manual award');
    if (!$oid || !$yid || !$pts) { echo json_encode(['success'=>false,'message'=>'Missing fields']); exit; }
    mysqli_query($conn, "INSERT INTO org_points (org_id,year_id,points,reason) VALUES ($oid,$yid,$pts,'$reason')");
    echo json_encode(['success'=>true]); exit;
}

// ── TRANSFER/ARCHIVE YEAR ─────────────────────────────────────────────────
if ($action === 'transfer_year' && $method === 'POST') {
    $from_yid = (int)($_POST['from_year_id'] ?? 0);
    $to_yid   = (int)($_POST['to_year_id']   ?? 0);
    if (!$from_yid || !$to_yid) { echo json_encode(['success'=>false,'message'=>'Missing year IDs']); exit; }

    // Archive submissions from old year
    mysqli_query($conn, "UPDATE submissions SET is_archived=1 WHERE year_id=$from_yid");

    // Set new year as current
    mysqli_query($conn, "UPDATE academic_years SET is_current=0");
    mysqli_query($conn, "UPDATE academic_years SET is_current=1 WHERE year_id=$to_yid");

    echo json_encode(['success'=>true,'message'=>'Year transferred successfully']); exit;
}

echo json_encode(['error'=>'Invalid action']);
mysqli_close($conn);