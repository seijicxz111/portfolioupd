<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../db/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if (!$conn) {
    die("<div style='font-family:sans-serif;padding:40px;color:#dc2626;'><h2>Database Connection Failed</h2><p>" . mysqli_connect_error() . "</p></div>");
}

$message = '';
$msgType = '';

$action    = $_GET['action'] ?? '';
$target_id = intval($_GET['id'] ?? 0);

if ($action && $target_id) {
    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $target_id);
        $message = ($stmt->execute() && $stmt->affected_rows > 0) ? 'User deleted successfully.' : 'Failed to delete user. They may have linked records.';
        $msgType  = ($stmt->affected_rows > 0) ? 'success' : 'error';
    } elseif ($action === 'deactivate') {
        $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $message = $stmt->affected_rows > 0 ? 'User account deactivated.' : 'Failed to deactivate user.';
        $msgType  = $stmt->affected_rows > 0 ? 'success' : 'error';
    } elseif ($action === 'activate') {
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $message = $stmt->affected_rows > 0 ? 'User account activated.' : 'Failed to activate user.';
        $msgType  = $stmt->affected_rows > 0 ? 'success' : 'error';
    }
}

$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Shared fields ──────────────────────────────────────────
    $full_name   = trim($_POST['full_name']   ?? '');
    $email       = trim($_POST['email']       ?? '');
    $phone       = trim($_POST['phone']       ?? '');
    // ── User-specific ──────────────────────────────────────────
    $username    = trim($_POST['username']    ?? '');
    $role        = $_POST['role']             ?? 'user';
    $password    = $_POST['password']         ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';
    // ── Org-specific ───────────────────────────────────────────
    $org_name    = trim($_POST['org_name']    ?? '');
    $org_code    = trim($_POST['org_code']    ?? '');
    $description = trim($_POST['description'] ?? '');

    $errors = [];

    // Required
    if (empty($full_name))  $errors[] = 'Full name is required.';
    if (empty($email))      $errors[] = 'Email is required.';
    if (empty($username))   $errors[] = 'Username is required.';
    if (empty($password))   $errors[] = 'Password is required.';
    if (empty($org_name))   $errors[] = 'Organization name is required.';
    if (empty($org_code))   $errors[] = 'Organization code is required.';

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Invalid email address.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (strlen($password) > 0 && strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters.';

    if (empty($errors)) {
        // Check duplicate user
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute(); $check->store_result();
        if ($check->num_rows > 0) $errors[] = 'Username or email already exists.';
    }
    if (empty($errors)) {
        // Check duplicate org code in users table
        $chkOrg = $conn->prepare("SELECT user_id FROM users WHERE org_code = ?");
        $chkOrg->bind_param("s", $org_code);
        $chkOrg->execute(); $chkOrg->store_result();
        if ($chkOrg->num_rows > 0) $errors[] = 'Organization code already exists.';
    }

    if (!empty($errors)) {
        $message = implode('<br>', $errors);
        $msgType = 'error';
    } else {
        $conn->begin_transaction();
        try {
            // Insert user with org fields all in one row
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmtU = $conn->prepare(
                "INSERT INTO users (username, email, full_name, role, password_hash, status,
                                    org_name, org_code, description, contact_person, phone, created_at)
                 VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, NOW())"
            );
            $stmtU->bind_param(
                "ssssssssss",
                $username, $email, $full_name, $role, $password_hash,
                $org_name, $org_code, $description, $full_name, $phone
            );
            $stmtU->execute();

            $conn->commit();
            $message = "User <strong>$full_name</strong> and organization <strong>$org_name</strong> created successfully!";
            $msgType = 'success';
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'Transaction failed: ' . $e->getMessage();
            $msgType = 'error';
        }
    }
}

$users_result = $conn->query("SELECT user_id, username, email, full_name, role, status, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Management — CIG System</title>
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/navbar.css">
  <link rel="stylesheet" href="../css/components.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
  <style>

    /* ══════════════════════════════════════════
       BASE — from style.css
    ══════════════════════════════════════════ */
    html { scroll-behavior: smooth; }

    * {
      margin: 0; padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif; /* from navbar.css */
    }

    body {
      display: flex;
      min-height: 100vh;
      background: linear-gradient(135deg, #f5f7fa 0%, #e8f5f0 25%, #f0fdf9 50%, #e0f2e9 75%, #f5f7fa 100%);
      background-size: 400% 400%;
      animation: smoothGradient 15s ease infinite;
    }

    @keyframes smoothGradient {
      0%   { background-position: 0% 50%; }
      50%  { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    /* Scrollbar — from style.css */
    ::-webkit-scrollbar { width: 10px; height: 10px; }
    ::-webkit-scrollbar-track { background: rgba(16,185,129,0.05); border-radius: 10px; }
    ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 10px; box-shadow: 0 0 10px rgba(16,185,129,0.3); }
    ::-webkit-scrollbar-thumb:hover { background: linear-gradient(135deg, #059669 0%, #047857 100%); }

    /* ══════════════════════════════════════════
       SIDEBAR — from navbar.css (exact copy)
    ══════════════════════════════════════════ */
    .sidebar {
      width: 240px;
      background: linear-gradient(180deg, #ffffff 0%, #f0fdf9 50%, #e8f5f0 100%);
      color: #2d3748;
      padding: 20px;
      transition: .3s;
      box-shadow: 2px 0 25px rgba(16,185,129,0.1), inset -1px 0 0 rgba(0,0,0,0.05);
      backdrop-filter: blur(10px);
      border-right: 1px solid rgba(16,185,129,0.1);
      position: sticky;
      top: 0;
      display: flex;
      flex-direction: column;
      height: 100vh;
      overflow-y: auto;
      z-index: 99;
    }

    .logo { width: 100%; max-width: 300px; height: auto; margin: 5% auto 20%; display: block; cursor: pointer; }

    .sidebar a {
      display: flex;
      align-items: center;
      color: #2d3748;
      text-decoration: none;
      padding: 12px 15px;
      border-radius: 10px;
      margin-bottom: 8px;
      transition: all .35s cubic-bezier(0.34, 1.56, 0.64, 1);
      position: relative;
      font-weight: 500;
      font-size: 0.9em;
      overflow: hidden;
    }

    .sidebar a::before {
      content: '';
      position: absolute;
      top: 0; left: -100%;
      width: 100%; height: 100%;
      background: linear-gradient(90deg, rgba(16,185,129,0.1), rgba(5,150,105,0.1));
      transition: left 0.35s ease;
      z-index: -1;
    }

    .sidebar a:hover::before { left: 0; }

    .sidebar a:hover {
      background: linear-gradient(135deg, rgba(16,185,129,0.15) 0%, rgba(5,150,105,0.15) 100%);
      color: #10b981;
      padding-left: 20px;
      box-shadow: -4px 0 12px rgba(16,185,129,0.2), inset 1px 0 0 rgba(16,185,129,0.3);
      transform: translateX(3px);
    }

    /* Active nav link — from navbar.css */
    .sidebar a.active {
      background: linear-gradient(90deg, #10b981 0%, #059669 100%);
      color: white;
      box-shadow: 0 6px 20px rgba(16,185,129,0.4), inset 0 1px 0 rgba(255,255,255,0.3);
    }

    .sidebar a.active::after {
      content: '';
      position: absolute;
      right: 0; top: 0; bottom: 0;
      width: 4px;
      background: white;
      border-radius: 2px;
    }

    .sidebar a i {
      margin-right: 12px;
      font-size: 1.1em;
      width: 24px; height: 24px;
      display: flex; align-items: center; justify-content: center;
      transition: transform 0.35s ease;
    }

    .sidebar a:hover i { transform: scale(1.2) rotate(5deg); }
    .sidebar a.active i { transform: scale(1.15); }

    .logo-link {
      display: block;
      text-decoration: none;
      padding: 8px;
      border-radius: 10px;
      transition: all 0.35s ease;
    }

    .logo-link.active {
      background: linear-gradient(135deg, rgba(16,185,129,0.15) 0%, rgba(5,150,105,0.15) 100%);
      box-shadow: 0 4px 15px rgba(16,185,129,0.25), inset 0 1px 0 rgba(255,255,255,0.5);
      border-left: 3px solid #10b981;
      padding-left: 5px;
    }

    .sidebar-footer {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: auto;
      padding-top: 20px;
      border-top: 2px solid rgba(16,185,129,0.15);
    }

    .logout-btn {
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ef4444;
      text-decoration: none;
      padding: 12px 15px;
      border-radius: 10px;
      transition: all 0.35s ease;
      font-weight: 600;
      font-size: 0.95em;
      border: 1.5px solid #ef4444;
      background: transparent;
      overflow: hidden;
      position: relative;
    }

    .logout-btn::before {
      content: '';
      position: absolute;
      top: 0; left: -100%;
      width: 100%; height: 100%;
      background: rgba(239,68,68,0.1);
      transition: left 0.35s ease;
      z-index: -1;
    }

    .logout-btn:hover::before { left: 0; }

    .logout-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(239,68,68,0.3);
      background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%);
      color: white;
      border-color: #dc2626;
    }

    /* ══════════════════════════════════════════
       TOPBAR — from navbar.css (exact copy)
    ══════════════════════════════════════════ */
    .main { flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

    .topbar {
      background-color: #fcfbfc;
      padding: 20px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border-bottom: 2px solid rgba(200,210,220,0.5);
      position: sticky;
      top: 0;
      z-index: 98;
    }

    .topbar-left { display: flex; align-items: center; gap: 15px; }
    .topbar-right { display: flex; align-items: center; gap: 25px; }

    .topbar div { font-size: 0.95em; font-weight: 600; color: #2d3748; }

    /* CIG header — from navbar.css */
    #cig {
      font-size: 1.1em;
      font-weight: 700;
      color: #2d3748;
      letter-spacing: 0.3px;
      margin: 0;
    }

    .cig-subtitle {
      font-size: 0.75em;
      font-weight: 500;
      color: #4a5568;
      letter-spacing: 0.2px;
      margin: 4px 0 0 0;
    }

    /* Notification bell — from navbar.css */
    .notification-bell { position: relative; cursor: pointer; display: flex; align-items: center; transition: .3s; }
    .bell-icon { font-size: 1.5em; transition: all .3s ease-in-out; }
    .notification-bell:hover .bell-icon { transform: scale(1.15) rotate(-10deg); }
    .notification-badge {
      position: absolute; top: -8px; right: -8px;
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white; border-radius: 50%;
      width: 24px; height: 24px;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.75em; font-weight: 700;
      box-shadow: 0 2px 8px rgba(239,68,68,0.3);
    }

    /* Notification panel — from navbar.css */
    .notification-panel {
      position: absolute; top: 65px; right: 30px;
      width: 380px; background: white; border-radius: 12px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
      display: none; flex-direction: column;
      z-index: 999; max-height: 500px; overflow: hidden;
      animation: slideDown .3s ease-in-out;
    }
    @keyframes slideDown { from { opacity:0; transform:translateY(-15px); } to { opacity:1; transform:translateY(0); } }
    .notification-panel.show { display: flex; }
    .notification-header { padding: 18px 20px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
    .notification-header h4 { color: #1a202c; font-size: 1.1em; margin: 0; font-weight: 700; }
    .close-notification { background: none; border: none; color: #2d3748; font-size: 1.2em; cursor: pointer; padding: 0; transition: .2s; }
    .close-notification:hover { transform: scale(1.2); }
    .notification-list { overflow-y: auto; max-height: 360px; }
    .notification-item { padding: 16px 20px; border-bottom: 1px solid #f5f5f5; display: flex; gap: 12px; cursor: pointer; transition: all .2s; background: white; }
    .notification-item:hover { background: #f9fafb; padding-left: 24px; }
    .notification-icon { font-size: 1.5em; min-width: 32px; display: flex; align-items: center; justify-content: center; }
    .notification-content { flex: 1; }
    .notification-title { margin: 0; color: #1a202c; font-weight: 600; font-size: 0.95em; }
    .notification-text { margin: 4px 0; color: #4a5568; font-size: 0.85em; }
    .notification-time { color: #a0aec0; font-size: 0.75em; display: block; margin-top: 4px; }

    /* ══════════════════════════════════════════
       PAGE — from organizations.css (exact copy)
    ══════════════════════════════════════════ */
    @keyframes waveBackground {
      0%   { background-position: 0% 50%; }
      50%  { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(5px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .page {
      background: linear-gradient(-45deg, #d1fae5 0%, #c1fada 15%, #d1fae5 30%, #c7f0dd 50%, #baf3d0 70%, #c1fada 85%, #d1fae5 100%);
      background-size: 300% 300%;
      animation: waveBackground 12s ease infinite;
      flex: 1;
      padding: 30px 40px;
      display: none;
      overflow-y: auto;
    }

    .page.active {
      display: block;
      animation: fadeIn 0.3s ease-in;
    }

    /* Page Header — from organizations.css */
    .page-header {
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 3px solid #10b981;
      position: relative;
    }

    .page-header::before {
      content: '';
      position: absolute;
      bottom: -3px; left: 0;
      width: 60px; height: 3px;
      background: linear-gradient(90deg, #10b981 0%, #059669 100%);
      border-radius: 2px;
      animation: slideIn 0.6s ease-out;
    }

    @keyframes slideIn { from { width: 0; } to { width: 60px; } }

    .page-header h2 {
      margin: 0;
      font-size: 28px;
      font-weight: 700;
      background: linear-gradient(135deg, #047857 0%, #10b981 50%, #059669 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      display: flex;
      align-items: center;
      gap: 14px;
      letter-spacing: -0.5px;
    }

    .page-header h2 i {
      font-size: 32px;
      color: #10b981;
      -webkit-text-fill-color: #10b981;
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      -webkit-background-clip: text;
      background-clip: text;
      display: inline-block;
      line-height: 1;
      vertical-align: middle;
      margin-right: 4px;
      transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .page-header h2:hover i { transform: scale(1.2) rotate(5deg); }

    /* ══════════════════════════════════════════
       TABLE — from organizations.css (exact copy)
    ══════════════════════════════════════════ */
    .table-container {
      background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(240,254,250,0.95) 100%);
      padding: 30px;
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(16,185,129,0.12), inset 0 1px 0 rgba(255,255,255,0.8);
      overflow-x: auto;
      border: 1px solid rgba(16,185,129,0.1);
      backdrop-filter: blur(10px);
    }

    table { width: 100%; border-collapse: collapse; border-spacing: 0; }
    th, td { padding: 16px 18px; text-align: left; font-size: 0.95em; }

    th {
      background: linear-gradient(90deg, #10b981 0%, #059669 100%);
      font-weight: 700;
      color: white;
      border-bottom: 2px solid #0f9670;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 0.85em;
    }

    th i { margin-right: 6px; color: #ffffff; }

    tbody tr { border-bottom: 1px solid #f0f0f0; transition: all .25s ease-in-out; }
    tbody tr:nth-child(odd)  { background: #fafbfc; }
    tbody tr:nth-child(even) { background: white; }
    tbody tr:hover {
      background: linear-gradient(90deg, #ecfdf5 0%, #f0fdf4 100%);
      box-shadow: inset 0 0 12px rgba(16,185,129,0.15), inset 1px 0 3px rgba(16,185,129,0.1);
      transform: scale(1.002);
    }

    .title-cell { color: #047857; }
    .ref-number { font-weight: 600; color: #10b981; font-size: 12px; letter-spacing: 0.5px; }

    /* ══════════════════════════════════════════
       ALERTS — from components.css (exact copy)
    ══════════════════════════════════════════ */
    .success-alert {
      background: linear-gradient(135deg, #d4fce5 0%, #d1fae5 100%);
      border-left: 4px solid #10b981;
      border-radius: 10px;
      padding: 16px 20px;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 4px 12px rgba(16,185,129,0.15);
      color: #047857;
      font-weight: 500;
      animation: slideInDown 0.4s ease-out;
    }
    .success-alert i { font-size: 20px; color: #10b981; }

    .error-alert {
      background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
      border-left: 4px solid #ef4444;
      border-radius: 10px;
      padding: 16px 20px;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 4px 12px rgba(239,68,68,0.15);
      color: #991b1b;
      font-weight: 500;
    }
    .error-alert i { font-size: 20px; color: #ef4444; }

    /* ══════════════════════════════════════════
       BUTTONS — from components.css (exact copy)
    ══════════════════════════════════════════ */
    .btn-action {
      padding: 10px 18px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
      white-space: nowrap;
      font-family: 'Poppins', sans-serif;
    }
    .btn-action:hover { transform: translateY(-2px); }

    .btn-view {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(16,185,129,0.3);
    }
    .btn-view:hover { box-shadow: 0 6px 16px rgba(16,185,129,0.4); }

    .btn-download {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(59,130,246,0.3);
    }
    .btn-download:hover { box-shadow: 0 6px 16px rgba(59,130,246,0.4); }

    .action-buttons { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

    /* ══════════════════════════════════════════
       STATUS & ROLE BADGES — from components.css
    ══════════════════════════════════════════ */
    .status-badge {
      background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
      color: #047857;
      padding: 8px 14px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border: 1px solid #6ee7b7;
      white-space: nowrap;
    }
    .status-badge.active   { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #047857; border-color: #6ee7b7; }
    .status-badge.inactive { background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); color: #64748b; border-color: #cbd5e1; }
    .status-badge .fa-circle { font-size: 7px; }

    .badge-role {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .badge-role.admin { background: #ede9fe; color: #6d28d9; }
    .badge-role.user  { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #047857; }

    /* ══════════════════════════════════════════
       EMPTY STATE — from components.css
    ══════════════════════════════════════════ */
    .empty-state { text-align: center; padding: 40px 20px; color: #9ca3af; }
    .empty-state i { font-size: 48px; color: #d1d5db; margin-bottom: 12px; display: block; }
    .empty-state p { margin: 0; font-size: 16px; font-weight: 500; }
    .empty-row { text-align: center !important; }

    /* ══════════════════════════════════════════
       FORMS — from organizations.css
    ══════════════════════════════════════════ */
    input, select, textarea {
      padding: 12px;
      border-radius: 8px;
      border: 1.5px solid #cbd5e0;
      background: white;
      font-size: 0.95em;
      font-family: 'Poppins', sans-serif;
      transition: .3s;
    }
    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: #10b981;
      box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
    }
    label { font-weight: 600; color: #2d3748; margin-top: 5px; font-size: 0.95em; }

    /* ══════════════════════════════════════════
       CONFIRM ACTION MODAL
    ══════════════════════════════════════════ */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
    .modal-overlay.active { display: flex; }
    .modal-box { background: white; border-radius: 16px; padding: 36px; max-width: 420px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.25); text-align: center; animation: fadeIn 0.3s ease; }
    .modal-icon { font-size: 52px; margin-bottom: 16px; line-height: 1; display: flex; justify-content: center; }
    .modal-icon i { display: inline-flex; }
    .icon-delete     i { color: #ef4444; }
    .icon-deactivate i { color: #f59e0b; }
    .icon-activate   i { color: #10b981; }
    .modal-box h3 { font-size: 1.3em; font-weight: 700; margin-bottom: 10px; color: #1e293b; }
    .modal-box p  { font-size: 14px; color: #64748b; margin-bottom: 28px; line-height: 1.6; }
    .modal-actions { display: flex; gap: 12px; justify-content: center; }
    .modal-cancel { background: #f0f0f0; color: #4a5568; border: 2px solid #e2e8f0; border-radius: 10px; padding: 11px 26px; font-size: 14px; font-weight: 600; cursor: pointer; transition: .2s; font-family: 'Poppins', sans-serif; }
    .modal-cancel:hover { background: #e8ecf1; }
    .modal-confirm { border: none; border-radius: 10px; padding: 11px 26px; font-size: 14px; font-weight: 600; cursor: pointer; color: white; text-decoration: none; transition: .2s; font-family: 'Poppins', sans-serif; }
    .modal-confirm.delete     { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .modal-confirm.deactivate { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .modal-confirm.activate   { background: linear-gradient(135deg, #10b981, #059669); }

    /* ══════════════════════════════════════════
       CREATE USER MODAL
    ══════════════════════════════════════════ */
    .cu-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); overflow-y: auto; }
    .cu-modal-content { background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%); margin: 3% auto; border-radius: 14px; width: 90%; max-width: 600px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: visible; animation: slideUp 0.4s cubic-bezier(0.4,0,0.2,1); }
    @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    .cu-modal-header { background: linear-gradient(135deg, #047857 0%, #059669 100%); padding: 25px 30px; color: white; display: flex; justify-content: space-between; align-items: center; }
    .cu-modal-header h2 { margin: 0; font-size: 21px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
    .cu-modal-header p  { margin: 6px 0 0; font-size: 13px; opacity: 0.9; }
    .cu-modal-close { background: rgba(255,255,255,0.2); border: none; color: white; font-size: 20px; cursor: pointer; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: background 0.2s; }
    .cu-modal-close:hover { background: rgba(255,255,255,0.35); }
    .cu-modal-body { padding: 28px 30px; }
    .cu-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 18px; }
    .cu-label { display: block; font-weight: 600; color: #2d3748; margin-bottom: 7px; font-size: 13px; }
    .cu-label span { color: #dc3545; }
    .cu-input { width: 100%; padding: 11px 13px; border: 1.5px solid #cbd5e0; border-radius: 8px; font-size: 14px; font-family: 'Poppins', sans-serif; transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box; background: white; }
    .cu-input:focus { outline: none; border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }
    .cu-modal-footer { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding-top: 18px; border-top: 1px solid #e2e8f0; margin-top: 6px; }
    .cu-btn-cancel { padding: 12px 20px; background: #f0f0f0; color: #4a5568; border: 2px solid #e2e8f0; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; font-family: 'Poppins', sans-serif; transition: background 0.2s; }
    .cu-btn-cancel:hover { background: #e8ecf1; }
    .cu-btn-submit { padding: 12px 20px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 14px; font-family: 'Poppins', sans-serif; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 4px 15px rgba(16,185,129,0.3); }
    .cu-btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16,185,129,0.4); }

    .cu-section-label {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #047857;
      background: linear-gradient(90deg, rgba(16,185,129,0.1), transparent);
      border-left: 3px solid #10b981;
      padding: 6px 10px;
      border-radius: 0 6px 6px 0;
      margin-bottom: 14px;
      margin-top: 4px;
      display: flex;
      align-items: center;
      gap: 7px;
    }

    /* ══════════════════════════════════════════
       RESPONSIVE — from navbar.css / organizations.css
    ══════════════════════════════════════════ */
    @media (max-width: 768px) {
      /* Override inline sidebar — use hamburger slide-in */
      body { flex-direction: column; }

      .sidebar {
        position: fixed !important;
        left: -240px !important;
        top: 0 !important;
        width: 240px !important;
        height: 100vh !important;
        z-index: 1000 !important;
        transition: left 0.3s ease !important;
      }

      .sidebar.open { left: 0 !important; }

      .sidebar a span { display: inline !important; }

      .main { width: 100% !important; margin-left: 0 !important; }

      .topbar { padding: 14px 16px !important; }

      .page { padding: 16px 12px !important; }

      .table-container { padding: 12px !important; overflow-x: auto !important; }

      table { min-width: 750px !important; font-size: 0.85em !important; }

      th, td { padding: 10px 8px !important; white-space: nowrap !important; }

      td.title-cell { white-space: normal !important; }

      .cu-form-row { grid-template-columns: 1fr !important; }

      .action-buttons { flex-wrap: nowrap !important; gap: 4px !important; }

      .notification-panel { width: calc(100vw - 32px) !important; right: 16px !important; }

      /* Modal mobile fix */
      .cu-modal {
        align-items: flex-start !important;
        padding: 16px !important;
        overflow-y: auto !important;
      }

      .cu-modal-content {
        margin: 0 auto 40px auto !important;
        width: 100% !important;
        max-width: 100% !important;
        border-radius: 12px !important;
      }

      .cu-modal-body {
        padding: 20px 16px !important;
        overflow-y: visible !important;
      }

      .cu-modal-header {
        padding: 18px 16px !important;
      }

      .cu-modal-header h2 {
        font-size: 17px !important;
      }

      .cu-modal-footer {
        grid-template-columns: 1fr !important;
        gap: 8px !important;
      }
    }

    @media (max-width: 480px) {
      table { min-width: 680px !important; font-size: 0.8em !important; }
      th, td { padding: 8px 6px !important; }
    }
  </style>
</head>
<body>

<?php
$current_page = 'create_user';
$user_name    = $_SESSION['admin_email'] ?? 'Admin';
?>
<?php include 'navbar.php'; ?>

  <div class="page active">

    <div class="page-header">
      <h2><i class="bi bi-person-add"></i> Create User Account</h2>
    </div>

    <?php if ($msgType === 'success'): ?>
    <div class="success-alert">
      <i class="fas fa-check-circle"></i>
      <span><?= $message ?></span>
    </div>
    <?php elseif ($msgType === 'error'): ?>
    <div class="error-alert">
      <i class="fas fa-exclamation-circle"></i>
      <span><?= $message ?></span>
    </div>
    <?php endif; ?>

    <div class="error-alert" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;margin-bottom:20px;">
      <i class="fas fa-shield-alt"></i>
      <span><strong>Security Notice:</strong> Restrict access to this page after creating accounts. Do not leave it publicly accessible.</span>
    </div>

    <button onclick="showCreateModal()" class="btn-action btn-view" style="margin-bottom:20px;">
      <i class="bi bi-person-add"></i> Create User &amp; Organization
    </button>

    <div class="table-container">
      <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
      <table style="min-width:750px;">
        <thead>
          <tr>
            <th><i class="fas fa-hashtag"></i> ID</th>
            <th><i class="fas fa-user"></i> Full Name</th>
            <th><i class="fas fa-at"></i> Username</th>
            <th><i class="fas fa-envelope"></i> Email</th>
            <th><i class="fas fa-user-tag"></i> Role</th>
            <th><i class="fas fa-info-circle"></i> Status</th>
            <th><i class="fas fa-calendar-alt"></i> Created</th>
            <th><i class="fas fa-cog"></i> Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($users_result && $users_result->num_rows > 0): ?>
            <?php while ($u = $users_result->fetch_assoc()): $isActive = ($u['status'] === 'active'); ?>
            <tr>
              <td><span style="font-weight:600;color:#10b981;">#<?= $u['user_id'] ?></span></td>
              <td class="title-cell"><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
              <td><?= htmlspecialchars($u['username']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="badge-role <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
              <td>
                <span class="status-badge <?= $u['status'] ?>">
                  <i class="fas fa-circle"></i> <?= ucfirst($u['status']) ?>
                </span>
              </td>
              <td><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
              <td>
                <div class="action-buttons">
                  <?php if ($isActive): ?>
                    <button class="btn-action btn-download" onclick="confirmAction('deactivate',<?= $u['user_id'] ?>,'<?= htmlspecialchars(addslashes($u['full_name'])) ?>')">
                      <i class="fas fa-ban"></i> Deactivate
                    </button>
                  <?php else: ?>
                    <button class="btn-action btn-view" onclick="confirmAction('activate',<?= $u['user_id'] ?>,'<?= htmlspecialchars(addslashes($u['full_name'])) ?>')">
                      <i class="fas fa-check"></i> Activate
                    </button>
                  <?php endif; ?>
                  <button class="btn-action" style="background:#fef2f2;color:#dc2626;" onclick="confirmAction('delete',<?= $u['user_id'] ?>,'<?= htmlspecialchars(addslashes($u['full_name'])) ?>')">
                    <i class="fas fa-trash"></i> Delete
                  </button>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="empty-row">
                <div class="empty-state">
                  <i class="fas fa-inbox"></i>
                  <p>No users found</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>

  </div>

  <?php include 'footer.php'; ?>
</div><!-- end .main -->

<!-- Unified Create Modal -->
<div id="createModal" class="cu-modal">
  <div class="cu-modal-content" style="max-width:660px;">

    <div class="cu-modal-header">
      <div>
        <h2><i class="bi bi-person-add"></i> Create User &amp; Organization</h2>
        <p>Fill in the details below — shared fields apply to both the user account and organization</p>
      </div>
      <button class="cu-modal-close" onclick="closeCreateModal()">✕</button>
    </div>

    <div class="cu-modal-body">
      <form method="POST">

        <!-- ── SECTION: Shared ─────────────────────────────── -->
        <div class="cu-section-label"><i class="fas fa-link"></i> Shared Information</div>
        <div class="cu-form-row">
          <div>
            <label class="cu-label" for="full_name">Full Name <span>*</span></label>
            <input class="cu-input" type="text" name="full_name" id="full_name"
              placeholder="e.g. Maria Santos"
              value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
          </div>
          <div>
            <label class="cu-label" for="email">Email Address <span>*</span></label>
            <input class="cu-input" type="email" name="email" id="email"
              placeholder="e.g. maria@cig.edu.ph"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>
        </div>
        <div style="margin-bottom:18px;">
          <label class="cu-label" for="phone">Phone Number</label>
          <input class="cu-input" type="tel" name="phone" id="phone"
            placeholder="+63-123-456-7890"
            value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>

        <!-- ── SECTION: User Account ──────────────────────── -->
        <div class="cu-section-label"><i class="bi bi-person-fill"></i> User Account</div>
        <div class="cu-form-row">
          <div>
            <label class="cu-label" for="username">Username <span>*</span></label>
            <input class="cu-input" type="text" name="username" id="username"
              placeholder="e.g. mariasantos"
              value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
          </div>
          <div>
            <label class="cu-label" for="role">Role <span>*</span></label>
            <select class="cu-input" name="role" id="role">
              <option value="user"  <?= (($_POST['role'] ?? 'user') === 'user')  ? 'selected' : '' ?>>User (Organization Member)</option>
              <option value="admin" <?= (($_POST['role'] ?? '')      === 'admin') ? 'selected' : '' ?>>Admin</option>
            </select>
          </div>
        </div>
        <div class="cu-form-row">
          <div>
            <label class="cu-label" for="password">Password <span>*</span></label>
            <input class="cu-input" type="password" name="password" id="password"
              placeholder="Min. 8 characters" required>
          </div>
          <div>
            <label class="cu-label" for="confirm_password">Confirm Password <span>*</span></label>
            <input class="cu-input" type="password" name="confirm_password" id="confirm_password"
              placeholder="Repeat password" required>
          </div>
        </div>

        <!-- ── SECTION: Organization ──────────────────────── -->
        <div class="cu-section-label"><i class="fas fa-building"></i> Organization</div>
        <div class="cu-form-row">
          <div>
            <label class="cu-label" for="org_name">Organization Name <span>*</span></label>
            <input class="cu-input" type="text" name="org_name" id="org_name"
              placeholder="e.g. Student Government Association"
              value="<?= htmlspecialchars($_POST['org_name'] ?? '') ?>" required>
          </div>
          <div>
            <label class="cu-label" for="org_code">Code <span>*</span></label>
            <input class="cu-input" type="text" name="org_code" id="org_code"
              placeholder="e.g. SGA"
              value="<?= htmlspecialchars($_POST['org_code'] ?? '') ?>" required>
          </div>
        </div>
        <div style="margin-bottom:18px;">
          <label class="cu-label" for="description">Description</label>
          <textarea class="cu-input" name="description" id="description"
            placeholder="Brief description of the organization..."
            style="height:75px;resize:none;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="cu-modal-footer">
          <button type="button" class="cu-btn-cancel" onclick="closeCreateModal()">Cancel</button>
          <button type="submit" class="cu-btn-submit">
            <i class="bi bi-person-add"></i> Create User &amp; Organization
          </button>
        </div>

      </form>
    </div>
  </div>
</div>

<!-- Confirm Action Modal -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal-box">
    <div class="modal-icon" id="modalIcon"></div>
    <h3 id="modalTitle"></h3>
    <p id="modalMsg"></p>
    <div class="modal-actions">
      <button class="modal-cancel" onclick="closeModal()">Cancel</button>
      <a id="modalConfirmBtn" href="#" class="modal-confirm"></a>
    </div>
  </div>
</div>

<script src="../js/navbar.js"></script>
<script>
function toggleNotificationPanel() {
  const panel = document.getElementById('notificationPanel');
  panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function showCreateModal() { document.getElementById('createModal').style.display = 'block'; }
function closeCreateModal() { document.getElementById('createModal').style.display = 'none'; }
window.addEventListener('click', e => { if (e.target === document.getElementById('createModal')) closeCreateModal(); });

// Legacy aliases
function showCreateUser() { showCreateModal(); }
function closeCreateUser() { closeCreateModal(); }
function showCreateOrg()  { showCreateModal(); }
function closeCreateOrg() { closeCreateModal(); }


function confirmAction(action, userId, name) {
  const configs = {
    delete:     { icon:'<i class="bi bi-trash"></i>',        iconCls:'icon-delete',     title:'Delete User',       msg:`Permanently delete <strong>${name}</strong>? This cannot be undone.`, label:'Yes, Delete', cls:'delete' },
    deactivate: { icon:'<i class="bi bi-lock"></i>',         iconCls:'icon-deactivate', title:'Deactivate Account', msg:`Deactivating <strong>${name}</strong> will prevent them from logging in.`, label:'Deactivate', cls:'deactivate' },
    activate:   { icon:'<i class="bi bi-check2-square"></i>', iconCls:'icon-activate',  title:'Activate Account',   msg:`Restore login access for <strong>${name}</strong>?`, label:'Activate', cls:'activate' }
  };
  const cfg = configs[action];
  const iconEl = document.getElementById('modalIcon');
  iconEl.innerHTML  = cfg.icon;
  iconEl.className  = `modal-icon ${cfg.iconCls}`;
  document.getElementById('modalTitle').textContent = cfg.title;
  document.getElementById('modalMsg').innerHTML     = cfg.msg;
  const btn = document.getElementById('modalConfirmBtn');
  btn.textContent = cfg.label;
  btn.className   = `modal-confirm ${cfg.cls}`;
  btn.href        = `?action=${action}&id=${userId}`;
  document.getElementById('modalOverlay').classList.add('active');
}
function closeModal() { document.getElementById('modalOverlay').classList.remove('active'); }
document.getElementById('modalOverlay').addEventListener('click', function(e) { if (e.target === this) closeModal(); });

<?php if ($msgType === 'error' && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
window.addEventListener('DOMContentLoaded', () => showCreateModal());
<?php endif; ?>
</script>
</body>
</html>