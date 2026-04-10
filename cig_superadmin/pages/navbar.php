<?php
/**
 * CIG Navigation Bar Component
 * Include this file in all admin pages
 * 
 * Optional Parameters:
 * - $current_page: Current page identifier (dashboard, submissions, review, archive, organizations, reports)
 * - $unread_count: Number of unread notifications (default: 0)
 * - $user_name: User's full name for display (default: empty)
 * - $notifications: Array of notification objects (optional)
 */

// Set defaults if not provided
$current_page = $current_page ?? '';
$unread_count = $unread_count ?? 0;
$user_name = $user_name ?? '';
$notifications = $notifications ?? [];
?>

<!-- MOBILE OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- NAVBAR (Sidebar + Topbar) -->
<div class="sidebar" id="sidebar">
  <a href="index.php" class="logo-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" data-page="home"><img src="../assets/osas2.png" alt="Logo" class="logo"></a>
  <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" data-page="dashboard"><i></i> <span>Dashboard</span></a>
  <a href="submissions.php" class="nav-link <?php echo $current_page === 'submissions' ? 'active' : ''; ?>" data-page="submissions"><i></i> <span>Submissions</span></a>
  <a href="review.php" class="nav-link <?php echo $current_page === 'review' ? 'active' : ''; ?>" data-page="review"><i></i> <span>Review & Approval</span></a>
  <a href="archive.php" class="nav-link <?php echo $current_page === 'archive' ? 'active' : ''; ?>" data-page="archive"><i></i> <span>Document Archive</span></a>
  <a href="create_user.php" class="nav-link <?php echo $current_page === 'create_user' ? 'active' : ''; ?>" data-page="create_user"><i></i> <span>Account Management</span></a>
  <a href="accomplishments.php" class="nav-link <?php echo $current_page === 'accomplishments' ? 'active' : ''; ?>" data-page="accomplishments"><i></i> <span>Accomplishments</span></a>
  <div class="sidebar-footer">
    <a href="logout.php" class="logout-btn"><span>Logout</span></a>
  </div>
</div>

<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <button id="hamburgerBtn" onclick="toggleSidebar()" style="display:none;background:none;border:none;font-size:1.5em;cursor:pointer;color:#2d3748;padding:4px 8px;">&#9776;</button>
      <div id="cig">Office of Student Affairs and Services<p class="cig-subtitle">Pamantasan ng Lungsod ng San Pablo</p></div>
    </div>
    <div class="topbar-right">
      <div class="notification-bell" onclick="toggleNotificationPanel()">
  <i class="fas fa-bell" style="font-size:1.7em;color:#2d3748;"></i>
  <span class="notification-badge" id="notificationBadge"><?php echo $unread_count; ?></span>
</div>
      <?php if (!empty($user_name)): ?>
        <div><?php echo htmlspecialchars($user_name); ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- NOTIFICATION PANEL -->
  <div id="notificationPanel" class="notification-panel">
    <div class="notification-header">
      <h4><i class="fas fa-bell" style="color:#10b981;margin-right:6px;"></i> Pending Submissions</h4>
      <div style="display:flex;gap:8px;align-items:center;">
        <a href="review.php" style="font-size:0.78em;color:#10b981;font-weight:700;text-decoration:none;">View All</a>
        <button class="close-notification" onclick="toggleNotificationPanel()">✕</button>
      </div>
    </div>
    <div class="notification-list" id="notifList">
      <div style="text-align:center;padding:20px;color:#aaa;">Loading…</div>
    </div>
  </div>
  <style>
  .notif-item { display:flex; gap:12px; padding:12px 16px; border-bottom:1px solid #f7f7f7; cursor:pointer; transition:background .15s; }
  .notif-item:hover { background:#f7fffe; }
  .notif-icon { width:36px; height:36px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:0.95em; flex-shrink:0; }
  .notif-body { flex:1; min-width:0; }
  .notif-title { font-weight:700; font-size:0.88em; color:#1a202c; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .notif-org   { font-size:0.78em; color:#718096; margin:2px 0; }
  .notif-meta  { display:flex; align-items:center; gap:8px; margin-top:4px; }
  </style>
<script>
// Mobile sidebar toggle
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('show');
}

// Show hamburger on mobile
function checkMobile() {
  const btn = document.getElementById('hamburgerBtn');
  if (window.innerWidth <= 768) {
    btn.style.display = 'block';
  } else {
    btn.style.display = 'none';
    closeSidebar();
  }
}
checkMobile();
window.addEventListener('resize', checkMobile);
</script>