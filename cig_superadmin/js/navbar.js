// Notification panel toggle
function toggleNotificationPanel() {
  let panel = document.getElementById("notificationPanel");
  if (panel) {
    panel.classList.toggle("show");
  }
}

// Close notification panel when clicking outside
document.addEventListener('click', function(e) {
  let panel = document.getElementById("notificationPanel");
  let bell = document.querySelector(".notification-bell");
  if (panel && !panel.contains(e.target) && !bell.contains(e.target)) {
    panel.classList.remove("show");
  }
});

// Logout function
function logout() {
  if (confirm('Are you sure you want to logout?')) {
    // Clear session/localStorage if needed
    localStorage.clear();
    sessionStorage.clear();
    // Redirect to login page
    window.location.href = 'login.html';
  }
}


// ── Notification Panel ────────────────────────────────────────────────────
function toggleNotificationPanel() {
  const panel = document.getElementById('notificationPanel');
  if (!panel) return;
  const isOpen = panel.classList.contains('show');
  panel.classList.toggle('show');
  if (!isOpen) fetchNotifications(); // load fresh on open
}

document.addEventListener('click', function(e) {
  const panel = document.getElementById('notificationPanel');
  const bell  = document.querySelector('.notification-bell');
  if (panel && !panel.contains(e.target) && bell && !bell.contains(e.target)) {
    panel.classList.remove('show');
  }
});

// ── Fetch pending submissions as notifications ────────────────────────────
function fetchNotifications() {
  const list  = document.getElementById('notifList');
  const badge = document.getElementById('notificationBadge');
  if (!list) return;

  list.innerHTML = '<div style="text-align:center;padding:20px;color:#aaa;"><i class="fas fa-spinner fa-spin"></i> Loading…</div>';

  fetch('../api/notifications.php?action=list')
    .then(r => r.json())
    .then(data => {
      if (!data.success) { list.innerHTML = '<div style="text-align:center;padding:16px;color:#aaa;">Failed to load.</div>'; return; }

      // Update badge
      if (badge) {
        badge.textContent = data.count > 0 ? (data.count > 99 ? '99+' : data.count) : '0';
        badge.style.background = data.count > 0 ? 'linear-gradient(135deg,#ef4444,#dc2626)' : '#aaa';
      }

      if (!data.notifications.length) {
        list.innerHTML = '<div style="text-align:center;padding:20px;color:#aaa;">No pending submissions</div>';
        return;
      }

      list.innerHTML = data.notifications.map(n => {
        const statusColor = n.status === 'in_review' ? '#3b82f6' : '#f59e0b';
        const statusLabel = n.status === 'in_review' ? 'In Review' : 'Pending';
        const timeAgo     = timeSince(n.submitted_at);
        return `
          <div class="notif-item" onclick="window.location.href='review.php'">
            <div class="notif-icon" style="background:${statusColor}20;color:${statusColor};">
              <i class="fas fa-file-alt"></i>
            </div>
            <div class="notif-body">
              <div class="notif-title">${escHtml(n.title)}</div>
              <div class="notif-org">${escHtml(n.org_name)} · ${escHtml(n.org_code || '')}</div>
              <div class="notif-meta">
                <span style="background:${statusColor}20;color:${statusColor};padding:2px 8px;border-radius:20px;font-size:0.72em;font-weight:700;">${statusLabel}</span>
                <span style="color:#aaa;font-size:0.75em;">${timeAgo}</span>
              </div>
            </div>
          </div>`;
      }).join('');
    })
    .catch(() => { list.innerHTML = '<div style="text-align:center;padding:16px;color:#aaa;">Could not load notifications.</div>'; });
}

// Auto-refresh badge count every 60s
function refreshBadge() {
  fetch('../api/notifications.php?action=list')
    .then(r => r.json())
    .then(data => {
      const badge = document.getElementById('notificationBadge');
      if (badge && data.count !== undefined) {
        badge.textContent = data.count > 99 ? '99+' : data.count;
        badge.style.background = data.count > 0 ? 'linear-gradient(135deg,#ef4444,#dc2626)' : '#aaa';
      }
    }).catch(() => {});
}
window.addEventListener('DOMContentLoaded', () => { refreshBadge(); setInterval(refreshBadge, 60000); });

// ── Helpers ───────────────────────────────────────────────────────────────
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function timeSince(dateStr) {
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
  if (diff < 60)   return 'just now';
  if (diff < 3600) return Math.floor(diff/60) + 'm ago';
  if (diff < 86400)return Math.floor(diff/3600) + 'h ago';
  return Math.floor(diff/86400) + 'd ago';
}