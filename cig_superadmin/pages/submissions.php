<?php
/**
 * CIG Superadmin - Submissions Page
 * Shows ONLY pending submissions + bulk forward/reject
 */

session_start();
require_once '../db/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$db   = new Database();
$user = ['full_name' => $_SESSION['admin_email'] ?? 'Superadmin'];
$search_query = $_GET['search'] ?? '';

$query  = "
    SELECT s.*, u.full_name as submitted_by_name,
           COALESCE(u.org_name, o.org_name) as org_name
    FROM submissions s
    LEFT JOIN users u ON s.user_id = u.user_id
    LEFT JOIN users o ON s.org_id = o.user_id
    WHERE s.status = 'pending'
";
$params = [];

if ($search_query) {
    $query   .= " AND (s.title LIKE ? OR u.org_name LIKE ? OR o.org_name LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}
$query .= " ORDER BY s.submitted_at DESC";

try {
    $submissions = $db->fetchAll($query, $params);
} catch (Exception $e) {
    $submissions = [];
}

function previewOnclick($s) {
    $id    = (int) $s['submission_id'];
    $ext   = strtolower(pathinfo($s['file_name'] ?? '', PATHINFO_EXTENSION));
    $title = addslashes(strip_tags($s['title']));
    return "openPreviewModal({$id},'{$ext}','{$title}','pending')";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Submissions - Superadmin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="../css/components.css">
<link rel="stylesheet" href="../css/submissions.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php $current_page = 'submissions'; $user_name = $user['full_name'] ?? ''; ?>
<?php include 'navbar.php'; ?>

  <div class="page active">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
      <h2 style="margin:0;"><i class="fas fa-file-alt"></i> Submissions
        <span style="font-size:0.6em;font-weight:500;color:#718096;margin-left:10px;">Pending — awaiting your review</span>
      </h2>
    </div>

    <!-- BULK ACTION BAR (hidden until checkboxes selected) -->
    <div id="bulkBar" style="display:none;align-items:center;gap:12px;flex-wrap:wrap;
         padding:12px 18px;background:linear-gradient(135deg,#1e3a5f,#2563eb);
         border-radius:12px;margin-bottom:18px;box-shadow:0 4px 16px rgba(37,99,235,.25);">
      <span id="bulkCount" style="color:#fff;font-weight:700;font-size:0.95em;flex:1;">
        0 selected
      </span>
      <button onclick="bulkAction('forward')"
        style="padding:9px 20px;background:#10b981;color:#fff;border:none;border-radius:8px;
               font-weight:700;font-size:0.88em;cursor:pointer;display:flex;align-items:center;gap:7px;
               transition:opacity .2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        <i class="fas fa-paper-plane"></i> Forward All to Admin
      </button>
      <button onclick="bulkAction('reject')"
        style="padding:9px 20px;background:#ef4444;color:#fff;border:none;border-radius:8px;
               font-weight:700;font-size:0.88em;cursor:pointer;display:flex;align-items:center;gap:7px;
               transition:opacity .2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
        <i class="fas fa-times"></i> Reject All
      </button>
      <button onclick="clearSelection()"
        style="padding:9px 16px;background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:8px;
               font-weight:600;font-size:0.88em;cursor:pointer;">
        <i class="fas fa-times-circle"></i> Clear
      </button>
    </div>

    <div class="search-filter-container">
      <form method="GET" class="search-filter-form">
        <div class="search-input-wrapper">
          <i class="fas fa-search search-icon"></i>
          <input type="text" name="search" placeholder="Search submissions..."
                 value="<?php echo htmlspecialchars($search_query); ?>" class="search-input">
        </div>
      </form>
    </div>

    <div class="table-container">
      <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
      <table style="min-width:700px;">
        <thead>
          <tr>
            <th style="width:40px;">
              <input type="checkbox" id="selectAll" title="Select all"
                style="width:16px;height:16px;cursor:pointer;accent-color:#3b82f6;">
            </th>
            <th><i class="fas fa-hashtag"></i> Ref No</th>
            <th><i class="fas fa-building"></i> Organization</th>
            <th><i class="fas fa-file-alt"></i> Title & Type</th>
            <th><i class="fas fa-tag"></i> Status</th>
            <th><i class="fas fa-user"></i> Submitted By</th>
            <th><i class="fas fa-calendar"></i> Date & Time</th>
            <th><i class="fas fa-cog"></i> Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($submissions)): ?>
            <?php foreach ($submissions as $index => $sub): ?>
              <tr id="row-<?php echo $sub['submission_id']; ?>">
                <td>
                  <input type="checkbox" class="row-checkbox"
                    data-id="<?php echo $sub['submission_id']; ?>"
                    style="width:16px;height:16px;cursor:pointer;accent-color:#3b82f6;"
                    onchange="updateBulkBar()">
                </td>
                <td class="ref-number">#<?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></td>
                <td><?php echo htmlspecialchars($sub['org_name'] ?? 'N/A'); ?></td>
                <td class="title-cell">
                  <?php
                    $ext = strtolower(pathinfo($sub['file_name'] ?? '', PATHINFO_EXTENSION));
                    $badgeMap = ['pdf'=>['PDF','#e74c3c'],'docx'=>['DOCX','#2980b9'],'doc'=>['DOC','#2980b9'],'xlsx'=>['XLSX','#27ae60'],'xls'=>['XLS','#27ae60']];
                    $iconMap  = ['pdf'=>'fa-file-pdf','docx'=>'fa-file-word','doc'=>'fa-file-word','xlsx'=>'fa-file-excel','xls'=>'fa-file-excel'];
                    [$badgeLabel, $badgeColor] = $badgeMap[$ext] ?? [strtoupper($ext) ?: '—', '#7f8c8d'];
                    $iconClass = $iconMap[$ext] ?? 'fa-file-alt';
                  ?>
                  <div style="display:flex;align-items:center;gap:8px;">
                    <i class="fas <?php echo $iconClass; ?>" style="font-size:1.3rem;color:<?php echo $badgeColor; ?>;flex-shrink:0;"></i>
                    <div>
                      <strong><?php echo htmlspecialchars($sub['title']); ?></strong>
                      <div style="margin-top:3px;">
                        <span style="background:<?php echo $badgeColor; ?>;color:#fff;font-size:.68rem;font-weight:700;padding:2px 7px;border-radius:4px;">
                          <?php echo $badgeLabel; ?>
                        </span>
                      </div>
                    </div>
                  </div>
                </td>
                <td><span class="status-badge pending"><i class="fas fa-clock"></i> Pending</span></td>
                <td><?php echo htmlspecialchars($sub['submitted_by_name'] ?? 'N/A'); ?></td>
                <td>
                  <div><?php echo date('M d, Y', strtotime($sub['submitted_at'])); ?></div>
                  <small style="color:#888;font-size:.78rem;"><i class="far fa-clock"></i> <?php echo date('h:i A', strtotime($sub['submitted_at'])); ?></small>
                </td>
                <td>
                  <div class="action-buttons">
                    <button class="btn-action btn-view" onclick="<?php echo previewOnclick($sub); ?>">
                      <i class="fas fa-eye"></i> Preview
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="empty-row">
                <div style="text-align:center;padding:30px;color:#aaa;">
                  <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                  No pending submissions
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
</div>

<!-- DOCUMENT PREVIEW MODAL -->
<div id="previewModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:10px;width:92vw;max-width:1060px;height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.4);">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 18px;background:#047857;color:#fff;flex-shrink:0;gap:12px;">
      <div style="display:flex;align-items:center;gap:9px;min-width:0;flex:1;">
        <i id="previewFileIcon" class="fas fa-file-alt" style="font-size:1.1rem;flex-shrink:0;"></i>
        <span id="previewTitle" style="font-size:.95rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
        <button id="modalForwardBtn" class="modal-action-btn" style="background:linear-gradient(135deg,#3b82f6,#2563eb);">
          <i class="fas fa-paper-plane"></i> Forward to Admin
        </button>
        <button id="modalRejectBtn" class="modal-action-btn" style="background:linear-gradient(135deg,#ef4444,#dc2626);">
          <i class="fas fa-times"></i> Reject
        </button>
      </div>
      <button onclick="closePreviewModal()" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer;line-height:1;flex-shrink:0;">&times;</button>
    </div>
    <div id="previewLoading" style="display:flex;flex-direction:column;align-items:center;justify-content:center;flex:1;gap:12px;color:#666;">
      <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#047857;"></i>
      <span id="previewLoadingMsg" style="font-size:.9rem;">Loading document&hellip;</span>
    </div>
    <div id="previewError" style="display:none;flex-direction:column;align-items:center;justify-content:center;flex:1;gap:10px;color:#c0392b;">
      <i class="fas fa-exclamation-triangle" style="font-size:2rem;"></i>
      <span id="previewErrorMsg" style="font-size:.9rem;text-align:center;max-width:400px;"></span>
    </div>
    <iframe id="previewPdfFrame" style="display:none;flex:1;border:none;width:100%;"></iframe>
    <div id="previewDocxWrap" style="display:none;flex:1;overflow:auto;"></div>
  </div>
</div>

<!-- BULK CONFIRM MODAL -->
<div id="bulkConfirmModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;width:90%;max-width:420px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.25);animation:modalPop .25s ease;">
    <div id="bulkConfirmHeader" style="padding:20px 24px 16px;display:flex;align-items:center;gap:12px;">
      <div id="bulkConfirmIcon" style="width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1em;flex-shrink:0;"></div>
      <div>
        <div id="bulkConfirmTitle" style="font-size:1.05em;font-weight:700;color:#1a202c;"></div>
        <div id="bulkConfirmSubtitle" style="font-size:0.83em;color:#718096;margin-top:2px;"></div>
      </div>
    </div>
    <div style="padding:0 24px 20px;">
      <div id="bulkConfirmList" style="background:#f8fafc;border-radius:8px;padding:12px 14px;max-height:180px;overflow-y:auto;font-size:0.85em;color:#2d3748;border:1px solid #e2e8f0;"></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;padding:14px 24px 20px;border-top:1px solid #f0f0f0;">
      <button onclick="closeBulkConfirm()"
        style="padding:9px 18px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:8px;font-weight:600;font-size:0.88em;cursor:pointer;font-family:inherit;">
        Cancel
      </button>
      <button id="bulkConfirmBtn"
        style="padding:9px 20px;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:0.88em;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:7px;">
      </button>
    </div>
  </div>
</div>

<style>
@keyframes modalPop { from{transform:scale(.93) translateY(16px);opacity:0} to{transform:scale(1) translateY(0);opacity:1} }
</style>

<script src="../js/navbar.js"></script>
<script src="../js/submissions.js"></script>
<script>
// ── Select All ────────────────────────────────────────────────────────────
document.getElementById('selectAll').addEventListener('change', function() {
  document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
  updateBulkBar();
});

function updateBulkBar() {
  const checked = document.querySelectorAll('.row-checkbox:checked');
  const bar     = document.getElementById('bulkBar');
  const count   = document.getElementById('bulkCount');
  // Sync select-all state
  const all = document.querySelectorAll('.row-checkbox');
  document.getElementById('selectAll').indeterminate = checked.length > 0 && checked.length < all.length;
  document.getElementById('selectAll').checked = checked.length === all.length && all.length > 0;

  if (checked.length > 0) {
    bar.style.display = 'flex';
    count.textContent = checked.length + ' selected';
  } else {
    bar.style.display = 'none';
  }
}

function clearSelection() {
  document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
  document.getElementById('selectAll').checked = false;
  updateBulkBar();
}

// ── Bulk Action ───────────────────────────────────────────────────────────
let _bulkMode = null;
let _bulkIds  = [];

function bulkAction(mode) {
  const checked = document.querySelectorAll('.row-checkbox:checked');
  if (!checked.length) return;

  _bulkMode = mode;
  _bulkIds  = Array.from(checked).map(cb => cb.dataset.id);

  const isForward = mode === 'forward';
  const color     = isForward ? '#3b82f6' : '#ef4444';
  const iconBg    = isForward ? '#dbeafe' : '#fee2e2';
  const iconColor = isForward ? '#1d4ed8' : '#b91c1c';
  const icon      = isForward ? 'fa-paper-plane' : 'fa-times';
  const title     = isForward ? `Forward ${_bulkIds.length} submission${_bulkIds.length > 1 ? 's' : ''} to Admin` : `Reject ${_bulkIds.length} submission${_bulkIds.length > 1 ? 's' : ''}`;
  const subtitle  = isForward ? 'These will be forwarded for admin final review.' : 'These submissions will be rejected and orgs notified.';

  // Build list of selected titles
  const listHtml = _bulkIds.map(id => {
    const row   = document.getElementById('row-' + id);
    const title = row ? row.querySelector('.title-cell strong')?.textContent || 'Submission #' + id : 'Submission #' + id;
    const org   = row ? row.cells[2]?.textContent?.trim() || '' : '';
    return `<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #f0f0f0;">
              <span style="font-weight:600;">${title}</span>
              <span style="color:#718096;font-size:0.9em;">${org}</span>
            </div>`;
  }).join('');

  document.getElementById('bulkConfirmHeader').style.background = iconBg + '66';
  document.getElementById('bulkConfirmIcon').style.background   = iconBg;
  document.getElementById('bulkConfirmIcon').style.color        = iconColor;
  document.getElementById('bulkConfirmIcon').innerHTML          = `<i class="fas ${icon}"></i>`;
  document.getElementById('bulkConfirmTitle').textContent       = title;
  document.getElementById('bulkConfirmSubtitle').textContent    = subtitle;
  document.getElementById('bulkConfirmList').innerHTML          = listHtml;

  const btn = document.getElementById('bulkConfirmBtn');
  btn.style.background = `linear-gradient(135deg,${color},${isForward ? '#1d4ed8' : '#dc2626'})`;
  btn.innerHTML        = `<i class="fas ${icon}"></i> ${isForward ? 'Forward All' : 'Reject All'}`;
  btn.onclick          = executeBulkAction;

  document.getElementById('bulkConfirmModal').style.display = 'flex';
}

function closeBulkConfirm() {
  document.getElementById('bulkConfirmModal').style.display = 'none';
}

function executeBulkAction() {
  const btn = document.getElementById('bulkConfirmBtn');
  btn.disabled  = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing…';

  const fd = new FormData();
  fd.append('action', _bulkMode === 'forward' ? 'bulk_forward' : 'bulk_reject');
  _bulkIds.forEach(id => fd.append('submission_ids[]', id));

  fetch('../api/submissions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      closeBulkConfirm();
      if (data.success) {
        const msg = _bulkMode === 'forward'
          ? `${data.count} submission${data.count !== 1 ? 's' : ''} forwarded to Admin.`
          : `${data.count} submission${data.count !== 1 ? 's' : ''} rejected.`;
        _showToast(msg, _bulkMode === 'forward' ? 'success' : 'error');
        // Remove rows
        _bulkIds.forEach(id => {
          const row = document.getElementById('row-' + id);
          if (row) {
            row.style.transition = 'opacity .3s,transform .3s';
            row.style.opacity    = '0';
            row.style.transform  = 'translateX(30px)';
            setTimeout(() => row.remove(), 300);
          }
        });
        clearSelection();
        // Show empty state if no rows left
        setTimeout(() => {
          const tbody = document.querySelector('table tbody');
          if (tbody && !tbody.querySelector('tr[id]')) {
            tbody.innerHTML = `<tr><td colspan="8" class="empty-row">
              <div style="text-align:center;padding:30px;color:#aaa;">
                <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                No pending submissions
              </div></td></tr>`;
          }
        }, 400);
      } else {
        _showToast(data.message || 'Bulk action failed.', 'error');
      }
    })
    .catch(() => {
      closeBulkConfirm();
      _showToast('Network error. Please try again.', 'error');
    });
}

// ── Single submission modal actions ───────────────────────────────────────
function _bindModalActions(id, status) {
  const forwardBtn = document.getElementById('modalForwardBtn');
  const rejectBtn  = document.getElementById('modalRejectBtn');
  if (!forwardBtn || !rejectBtn) return;
  forwardBtn.disabled = (status !== 'pending');
  rejectBtn.disabled  = (status === 'rejected');
  forwardBtn.onclick  = function() { closePreviewModal(); _showRemarksModal(id, 'forward'); };
  rejectBtn.onclick   = function() { closePreviewModal(); _showRemarksModal(id, 'reject'); };
}

function _showRemarksModal(id, mode) {
  const isReject  = mode === 'reject';
  const accentColor = isReject ? '#ef4444' : '#3b82f6';
  const headerBg    = isReject ? '#fef2f2' : '#eff6ff';
  const borderColor = isReject ? '#fecaca' : '#bfdbfe';
  const icon        = isReject ? 'fa-times-circle' : 'fa-paper-plane';
  const title       = isReject ? 'Reject Submission' : 'Forward to Admin';
  const subtitle    = isReject ? 'Provide a reason so the submitter knows what to fix.' : 'Add optional remarks for the admin before forwarding.';
  const btnLabel    = isReject ? 'Reject Submission' : 'Forward to Admin';
  const required    = isReject ? '<span style="color:#ef4444">*</span>' : '<span style="color:#9ca3af">(optional)</span>';

  const QUICK = isReject
    ? ['Incomplete documentation','Does not meet requirements','Duplicate submission','Incorrect format','Missing signatures or approvals']
    : ['For admin final review','Documents complete','Meets initial requirements','Reviewed and forwarding'];

  let overlay = document.getElementById('rejectCommentOverlay');
  if (!overlay) { overlay = document.createElement('div'); overlay.id = 'rejectCommentOverlay'; document.body.appendChild(overlay); }

  overlay.innerHTML = `
    <div class="reject-modal-box">
      <div class="reject-modal-header" style="background:${headerBg};border-bottom:1px solid ${borderColor};">
        <span class="reject-modal-icon" style="color:${accentColor};"><i class="fas ${icon}"></i></span>
        <div><div class="reject-modal-title">${title}</div><div class="reject-modal-subtitle">${subtitle}</div></div>
        <button class="reject-modal-close" onclick="_closeRejectModal()">&times;</button>
      </div>
      <div class="reject-modal-body">
        <div class="reject-quick-label">Quick ${isReject ? 'reasons' : 'remarks'}</div>
        <div class="reject-quick-list">
          ${QUICK.map(r => `<button class="reject-quick-btn" style="border-color:${accentColor}33;color:${accentColor};" onclick="_fillRejectReason('${r}')">${r}</button>`).join('')}
        </div>
        <label class="reject-textarea-label" for="rejectReasonText">${isReject ? 'Rejection reason' : 'Remarks'} ${required}</label>
        <textarea id="rejectReasonText" class="reject-textarea"
          placeholder="${isReject ? 'Describe what needs to be corrected…' : 'Add any notes for the admin (optional)…'}"
          maxlength="500" oninput="_updateRejectCharCount(this)"></textarea>
        <div class="reject-char-count"><span id="rejectCharCount">0</span> / 500</div>
      </div>
      <div class="reject-modal-footer">
        <button class="confirm-btn confirm-cancel" onclick="_closeRejectModal()"><i class="fas fa-arrow-left"></i> Cancel</button>
        <button class="confirm-btn" id="rejectSubmitBtn" onclick="_submitSuperadminAction(${id}, '${mode}')"
          style="background:${isReject ? 'linear-gradient(135deg,#ef4444,#dc2626)' : 'linear-gradient(135deg,#3b82f6,#2563eb)'};">
          <i class="fas fa-${isReject ? 'times' : 'paper-plane'}"></i> ${btnLabel}
        </button>
      </div>
    </div>`;

  overlay.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById('rejectReasonText')?.focus(), 100);
}

function _submitSuperadminAction(id, mode) {
  const isReject = mode === 'reject';
  const ta       = document.getElementById('rejectReasonText');
  const reason   = ta ? ta.value.trim() : '';
  if (isReject && !reason) { ta.classList.add('reject-textarea-error'); ta.placeholder = 'A rejection reason is required.'; ta.focus(); return; }

  const btn = document.getElementById('rejectSubmitBtn');
  if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${isReject ? 'Rejecting…' : 'Forwarding…'}`; }

  const fd = new FormData();
  fd.append('action',        isReject ? 'reject' : 'forward');
  fd.append('submission_id', id);
  fd.append('reason',        reason);

  fetch('../api/submissions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      _closeRejectModal();
      if (data.success) {
        _showToast(isReject ? 'Submission rejected.' : 'Forwarded to Admin.', isReject ? 'error' : 'success');
        _removeRow(id);
      } else {
        _showToast(data.message || 'Action failed.', 'error');
      }
    })
    .catch(() => { _closeRejectModal(); _showToast('Network error.', 'error'); });
}

// Close bulk modal on backdrop
document.getElementById('bulkConfirmModal').addEventListener('click', function(e) {
  if (e.target === this) closeBulkConfirm();
});

function toggleNotificationPanel() {
  var panel = document.getElementById('notificationPanel');
  if (panel) panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}
</script>
</body>
</html>