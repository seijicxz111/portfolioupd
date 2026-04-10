<?php
/**
 * CIG Admin Dashboard - Document Archive Page
 * Displays archived documents and file management
 */

session_start();
require_once '../db/config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$user = ['full_name' => $_SESSION['admin_email'] ?? 'Admin'];

// Get filter parameters
$search_query = $_GET['search'] ?? '';
$org_filter = $_GET['org'] ?? '';

// Get rejected submissions
try {
    $query = "
        SELECT s.*, u.full_name as submitted_by_name,
               COALESCE(u.org_name, o.org_name) as org_name,
               COALESCE(u.user_id, o.user_id) as resolved_org_id
        FROM submissions s
        LEFT JOIN users u ON s.user_id = u.user_id
        LEFT JOIN users o ON s.org_id = o.user_id
        WHERE s.status = 'rejected'
    ";
    $params = [];
    
    if ($org_filter) {
        $query .= " AND (u.user_id = ? OR o.user_id = ?)";
        $params[] = $org_filter;
        $params[] = $org_filter;
    }
    
    if ($search_query) {
        $query .= " AND (s.title LIKE ? OR u.full_name LIKE ? OR u.org_name LIKE ? OR o.org_name LIKE ?)";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
        $params[] = "%$search_query%";
    }
    
    $query .= " ORDER BY s.updated_at DESC";
    $rejected_submissions = $db->fetchAll($query, $params);
    
    // Get all org users for filter dropdown
    $organizations = $db->fetchAll("SELECT user_id as org_id, org_name FROM users WHERE org_code IS NOT NULL AND status = 'active' ORDER BY org_name ASC");
} catch (Exception $e) {
    error_log('Archive Error: ' . $e->getMessage());
    $rejected_submissions = [];
    $organizations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Document Archive - Admin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="../css/components.css">
<link rel="stylesheet" href="../css/archive.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php 
$current_page = 'archive';
$user_name = $user['full_name'] ?? '';
?>
<?php include 'navbar.php'; ?>

  <!-- DOCUMENT ARCHIVE -->
  <div class="page active">
    <div class="page-header">
      <h2><i class="fas fa-archive"></i> Archive - Rejected Submissions</h2>
    </div>


    <!-- SEARCH & FILTER -->
    <div class="search-filter-container">
      <form method="GET" class="search-filter-form">
        <div class="search-input-wrapper">
          <i class="fas fa-search search-icon"></i>
          <input type="text" name="search" placeholder="Search by title, submitter, or organization..." value="<?php echo htmlspecialchars($search_query); ?>" class="search-input">
        </div>
        <select name="org" class="filter-select">
          <option value="">All Organizations</option>
          <?php foreach ($organizations as $org): ?>
            <option value="<?php echo htmlspecialchars($org['org_id']); ?>" <?php echo $org_filter == $org['org_id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($org['org_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if ($search_query || $org_filter): ?>
          <a href="archive.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- REJECTED SUBMISSIONS -->
    <div>
      <h3 style="font-size: 18px; font-weight: 700; color: #047857; margin: 0 0 20px 0; display: flex; align-items: center; gap: 10px;"><i class="fas fa-trash"></i> Rejected Submissions (<?php echo count($rejected_submissions); ?>)</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th><i class="fas fa-hashtag"></i> Ref No</th>
              <th><i class="fas fa-file-alt"></i> Title</th>
              <th><i class="fas fa-building"></i> Organization</th>
              <th><i class="fas fa-user"></i> Submitted By</th>
              <th><i class="fas fa-calendar"></i> Submission Date</th>
              <th><i class="fas fa-ban"></i> Rejected Date</th>
              <th><i class="fas fa-cog"></i> Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($rejected_submissions)): ?>
              <?php foreach ($rejected_submissions as $index => $submission): ?>
                <tr>
                  <td class="ref-number">#<?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></td>
                  <td class="title-cell"><strong><?php echo htmlspecialchars($submission['title']); ?></strong></td>
                  <td><?php echo htmlspecialchars($submission['org_name'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($submission['submitted_by_name'] ?? 'N/A'); ?></td>
                  <td><?php echo date('M d, Y', strtotime($submission['submitted_at'])); ?></td>
                  <td><?php echo date('M d, Y', strtotime($submission['updated_at'])); ?></td>
                  <td>
                    <div class="action-buttons">
                      <?php
                        $ext   = strtolower(pathinfo($submission['file_name'] ?? '', PATHINFO_EXTENSION));
                        $title = addslashes(strip_tags($submission['title']));
                      ?>
                      <button class="btn-action btn-view"
                              onclick="openPreviewModal(<?php echo $submission['submission_id']; ?>,'<?php echo $ext; ?>','<?php echo $title; ?>')">
                        <i class="fas fa-eye"></i> Preview
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" class="empty-row">No rejected submissions found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php include 'footer.php'; ?>
</div>

<style>
/* Archive page specific styles are loaded from components.css */
</style>

<!-- ══════════════════════════════════════════════════════════
     DOCUMENT PREVIEW MODAL
     ══════════════════════════════════════════════════════════ -->
<div id="previewModal"
     style="display:none;position:fixed;inset:0;z-index:9999;
            background:rgba(0,0,0,.6);align-items:center;justify-content:center;">

  <div style="background:#fff;border-radius:10px;width:92vw;max-width:1060px;
              height:90vh;display:flex;flex-direction:column;overflow:hidden;
              box-shadow:0 8px 40px rgba(0,0,0,.4);">

    <!-- Header -->
    <div style="display:flex;align-items:center;justify-content:space-between;
                padding:13px 18px;background:#047857;color:#fff;flex-shrink:0;gap:12px;">

      <!-- File icon + title -->
      <div style="display:flex;align-items:center;gap:9px;min-width:0;flex:1;">
        <i id="previewFileIcon" class="fas fa-file-alt" style="font-size:1.1rem;flex-shrink:0;"></i>
        <span id="previewTitle"
              style="font-size:.95rem;font-weight:600;overflow:hidden;
                     text-overflow:ellipsis;white-space:nowrap;"></span>
        <span style="background:rgba(255,255,255,.2);padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:700;white-space:nowrap;">
          REJECTED
        </span>
      </div>

      <!-- Close -->
      <button onclick="closePreviewModal()"
              style="background:none;border:none;color:#fff;font-size:1.5rem;
                     cursor:pointer;line-height:1;flex-shrink:0;">&times;</button>
    </div>

    <!-- Loading spinner -->
    <div id="previewLoading"
         style="display:flex;flex-direction:column;align-items:center;
                justify-content:center;flex:1;gap:12px;color:#666;">
      <i class="fas fa-spinner fa-spin" style="font-size:2rem;color:#047857;"></i>
      <span id="previewLoadingMsg" style="font-size:.9rem;">Loading document&hellip;</span>
    </div>

    <!-- Error state -->
    <div id="previewError"
         style="display:none;flex-direction:column;align-items:center;
                justify-content:center;flex:1;gap:10px;color:#c0392b;">
      <i class="fas fa-exclamation-triangle" style="font-size:2rem;"></i>
      <span id="previewErrorMsg"
            style="font-size:.9rem;text-align:center;max-width:400px;"></span>
    </div>

    <!-- PDF iframe -->
    <iframe id="previewPdfFrame"
            style="display:none;flex:1;border:none;width:100%;"></iframe>

    <!-- DOCX rendered output -->
    <div id="previewDocxWrap"
         style="display:none;flex:1;overflow:auto;"></div>

  </div>
</div>

<script src="../js/navbar.js"></script>
<script>
function toggleNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

// ── Preview Modal ─────────────────────────────────────────────────────────
let _currentId = null;

function openPreviewModal(id, ext, title) {
    _currentId = id;

    // Reset state
    document.getElementById('previewTitle').textContent    = title;
    document.getElementById('previewLoading').style.display  = 'flex';
    document.getElementById('previewError').style.display    = 'none';
    document.getElementById('previewPdfFrame').style.display = 'none';
    document.getElementById('previewDocxWrap').style.display = 'none';
    document.getElementById('previewDocxWrap').innerHTML     = '';
    document.getElementById('previewModal').style.display    = 'flex';

    // Set file icon
    const iconEl = document.getElementById('previewFileIcon');
    const icons  = { pdf:'fa-file-pdf', docx:'fa-file-word', doc:'fa-file-word',
                     xlsx:'fa-file-excel', xls:'fa-file-excel', txt:'fa-file-alt' };
    iconEl.className = 'fas ' + (icons[ext] || 'fa-file-alt');

    if (ext === 'pdf') {
        loadPdf(id);
    } else if (ext === 'docx' || ext === 'doc') {
        loadDocx(id, ext);
    } else {
        showError('Preview not supported for .' + ext + ' files. Use the download button.');
    }
}

function closePreviewModal() {
    document.getElementById('previewModal').style.display = 'none';
    const frame = document.getElementById('previewPdfFrame');
    frame.src = '';
}

// Close on backdrop click
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('previewModal').addEventListener('click', function (e) {
        if (e.target === this) closePreviewModal();
    });
});

function showLoading(msg) {
    document.getElementById('previewLoadingMsg').textContent = msg || 'Loading document…';
    document.getElementById('previewLoading').style.display  = 'flex';
    document.getElementById('previewError').style.display    = 'none';
    document.getElementById('previewPdfFrame').style.display = 'none';
    document.getElementById('previewDocxWrap').style.display = 'none';
}

function showError(msg) {
    document.getElementById('previewErrorMsg').textContent  = msg;
    document.getElementById('previewError').style.display   = 'flex';
    document.getElementById('previewLoading').style.display = 'none';
    document.getElementById('previewPdfFrame').style.display = 'none';
    document.getElementById('previewDocxWrap').style.display = 'none';
}

function loadPdf(id) {
    const frame = document.getElementById('previewPdfFrame');
    frame.onload = function () {
        document.getElementById('previewLoading').style.display = 'none';
        frame.style.display = 'block';
    };
    frame.onerror = function () { showError('Failed to load PDF.'); };
    frame.src = '../pages/file_preview.php?submission_id=' + id;
}

function loadDocx(id, ext) {
    showLoading('Converting document…');
    const wrap = document.getElementById('previewDocxWrap');

    // Try LibreOffice PDF conversion first
    const pdfUrl = '../pages/docx_to_pdf.php?submission_id=' + id;
    const frame  = document.getElementById('previewPdfFrame');
    frame.onload = function () {
        // Check if it returned an error page vs real PDF
        try {
            const ct = frame.contentDocument?.contentType || '';
            if (ct.includes('text/html')) {
                // LibreOffice not available — fall back to mammoth.js render
                frame.style.display = 'none';
                loadDocxMammoth(id);
                return;
            }
        } catch(e) { /* cross-origin */ }
        document.getElementById('previewLoading').style.display = 'none';
        frame.style.display = 'block';
    };
    frame.onerror = function () { loadDocxMammoth(id); };
    frame.src = pdfUrl;
}

function loadDocxMammoth(id) {
    showLoading('Rendering document…');
    fetch('../pages/file_preview.php?submission_id=' + id)
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.arrayBuffer();
        })
        .then(buffer => {
            if (typeof mammoth === 'undefined') {
                // Load mammoth.js dynamically
                const s = document.createElement('script');
                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js';
                s.onload  = () => renderMammoth(buffer);
                s.onerror = () => showError('Could not load document renderer.');
                document.head.appendChild(s);
            } else {
                renderMammoth(buffer);
            }
        })
        .catch(err => showError('Failed to load document: ' + err.message));
}

function renderMammoth(buffer) {
    mammoth.convertToHtml({ arrayBuffer: buffer })
        .then(result => {
            const wrap = document.getElementById('previewDocxWrap');
            wrap.innerHTML = '<div style="padding:30px;max-width:860px;margin:0 auto;font-family:Georgia,serif;line-height:1.7;font-size:15px;">'
                           + result.value + '</div>';
            document.getElementById('previewLoading').style.display = 'none';
            wrap.style.display = 'block';
        })
        .catch(() => showError('Could not render document content.'));
}
</script>
</body>
</html>

<?php
// No additional functions needed for the new archive structure.
?>