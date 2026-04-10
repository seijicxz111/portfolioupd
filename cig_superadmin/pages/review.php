<?php
/**
 * CIG Admin Dashboard - Review & Approval Page
 * Folder-based submission review and approval workflow
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

// Get selected organization from URL
$selected_org_id = $_GET['org'] ?? null;
$selected_submission_id = $_GET['id'] ?? null;
$search_query = $_GET['search'] ?? '';

// Get all organizations (49 for now - can be changed later)
$organizations = [];
try {
    $organizations = $db->fetchAll("
        SELECT user_id as org_id, org_name, org_code,
               (SELECT COUNT(*) FROM submissions WHERE (org_id = users.user_id OR user_id = users.user_id)) as submission_count
        FROM users
        WHERE org_code IS NOT NULL AND status = 'active'
        ORDER BY org_name ASC
        LIMIT 49
    ");
    
    // If we have less than 49, we can keep what we have or the user can add more
} catch (Exception $e) {
    error_log('Organizations Error: ' . $e->getMessage());
    $organizations = [];
}

// Sample organizations for demonstration
$sample_organizations = [
    [
        'org_id' => 1,
        'org_name' => 'Student Government Association',
        'org_code' => 'SGA',
        'submission_count' => 2
    ],
    [
        'org_id' => 2,
        'org_name' => 'Cultural and Arts Club',
        'org_code' => 'CAC',
        'submission_count' => 1
    ],
    [
        'org_id' => 3,
        'org_name' => 'Academic Excellence Board',
        'org_code' => 'AEB',
        'submission_count' => 2
    ]
];

// Use sample organizations if no real organizations exist
if (empty($organizations)) {
    $organizations = $sample_organizations;
}

// Get submissions for selected organization (approved only)
$submissions = [];
$current_org = null;
if ($selected_org_id) {
    try {
        // Get organization details
        $current_org = $db->fetchRow("
            SELECT user_id as org_id, org_name, org_code, description, email, phone, contact_person, status
            FROM users WHERE user_id = ?
        ", [$selected_org_id]);
        
        // Use sample organization if not found
        if (!$current_org) {
            $current_org = [
                'org_id' => 1,
                'org_name' => 'Student Government Association',
                'org_code' => 'SGA',
                'description' => 'Main student government body'
            ];
        }
        
        // Get approved submissions for this organization
        $query = "
            SELECT s.*, u.full_name as submitted_by_name,
                   COALESCE(u.org_name, o.org_name) as org_name,
                   (SELECT COUNT(*) FROM reviews WHERE submission_id = s.submission_id) as review_count
            FROM submissions s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN users o ON s.org_id = o.user_id
            WHERE (s.org_id = ? OR s.user_id = ?)
        ";
        $params = [$selected_org_id, $selected_org_id];
        
        if ($search_query) {
            $query .= " AND (s.title LIKE ? OR u.full_name LIKE ?)";
            $params[] = "%$search_query%";
            $params[] = "%$search_query%";
        }
        
        $query .= " ORDER BY s.submitted_at DESC";
        $submissions = $db->fetchAll($query, $params);
    } catch (Exception $e) {
        error_log('Submissions Query Error: ' . $e->getMessage());
        $submissions = [];
    }
}

// Get individual submission details
$submission = null;
if ($selected_submission_id && $selected_org_id) {
    try {
        $submission = $db->fetchRow("
            SELECT s.*, u.full_name as submitted_by_name,
                   COALESCE(u.org_name, o.org_name) as org_name
            FROM submissions s
            LEFT JOIN users u ON s.user_id = u.user_id
            LEFT JOIN users o ON s.org_id = o.user_id
            WHERE s.submission_id = ? AND (s.org_id = ? OR s.user_id = ?)
        ", [$selected_submission_id, $selected_org_id, $selected_org_id]);
        
        // Get reviews for this submission
        if ($submission) {
            $reviews = $db->fetchAll("
                SELECT r.*, u.full_name, u.username
                FROM reviews r
                LEFT JOIN users u ON r.reviewer_id = u.user_id
                WHERE r.submission_id = ?
                ORDER BY r.reviewed_at DESC
            ", [$selected_submission_id]);
        } else {
            // Sample submission for demonstration
            $submission = [
                'submission_id' => 101,
                'title' => 'New Student Governance Initiative',
                'description' => 'This initiative aims to improve student government representation and involvement across all academic departments.',
                'org_name' => 'Student Government Association',
                'status' => 'approved',
                'submitted_by_name' => 'John Santos',
                'submitted_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
            ];
            $reviews = [];
        }
    } catch (Exception $e) {
        error_log('Review Page Error: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action        = $_POST['action'];
    $submission_id = (int)$_POST['submission_id'];
    $org_id        = (int)($_GET['org'] ?? 0);

    try {
        if ($action === 'approve') {
            $db->update('submissions', ['status' => 'in_review'], 'submission_id = ?', [$submission_id]);
            $db->insert('reviews', [
                'submission_id' => $submission_id,
                'reviewer_id'   => 1,
                'feedback'      => $_POST['feedback'] ?? '',
                'status'        => 'completed',
                'reviewed_at'   => date('Y-m-d H:i:s')
            ]);
        } elseif ($action === 'reject') {
            $db->update('submissions', ['status' => 'rejected'], 'submission_id = ?', [$submission_id]);
            $db->insert('reviews', [
                'submission_id' => $submission_id,
                'reviewer_id'   => 1,
                'feedback'      => $_POST['feedback'] ?? '',
                'status'        => 'completed',
                'reviewed_at'   => date('Y-m-d H:i:s')
            ]);
        }
        header('Location: review.php?org=' . $org_id . '&id=' . $submission_id . '&success=1');
        exit();
    } catch (Exception $e) {
        error_log('Review Update Error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review & Approval - Admin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="../css/components.css">
<link rel="stylesheet" href="../css/review.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>
<body>

<?php 
$current_page = 'review';
$user_name = $user['full_name'] ?? '';
?>
<?php include 'navbar.php'; ?>

  <!-- REVIEW & APPROVAL & ORGANIZATIONS -->
  <div class="page active">
    <div class="page-header">
      <h2><i class="fas fa-cogs"></i> Organization Management & Reviews</h2>
      <?php if (!$selected_org_id && !$selected_submission_id): ?>
      <div class="page-header-search">
        <div class="search-input-wrapper-header">
          <i class="fas fa-search search-icon"></i>
          <input type="text" id="orgSearchInput" placeholder="Search organizations..." class="search-input-header">
        </div>
      </div>
      <?php endif; ?>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="success-alert">
      <i class="fas fa-check-circle"></i>
      <span>Review submitted successfully!</span>
    </div>
    <?php endif; ?>

    <?php if ($selected_submission_id && $submission): ?>
      <!-- SUBMISSION DETAIL VIEW -->
      <div class="breadcrumb-nav">
        <a href="review.php"><i class="fas fa-folder-open"></i> Organizations</a>
        <i class="fas fa-chevron-right"></i>
        <a href="review.php?org=<?php echo $selected_org_id; ?>"><i class="fas fa-building"></i> <?php echo htmlspecialchars($current_org['org_name'] ?? ''); ?></a>
        <i class="fas fa-chevron-right"></i>
        <span><i class="fas fa-file"></i> <?php echo htmlspecialchars($submission['title']); ?></span>
      </div>

      <div class="submission-detail-card">
        <div class="submission-header">
          <h3><?php echo htmlspecialchars($submission['title']); ?></h3>
          <?php
            $st = $submission['status'];
            $st_labels = ['pending'=>'Pending','in_review'=>'Forwarded to Admin','approved'=>'Approved (Final)','rejected'=>'Rejected','archived'=>'Archived'];
            $st_icons  = ['pending'=>'clock','in_review'=>'paper-plane','approved'=>'check-circle','rejected'=>'ban','archived'=>'archive'];
            $st_label  = $st_labels[$st] ?? ucfirst($st);
            $st_icon   = $st_icons[$st]  ?? 'circle';
          ?>
          <span class="status-badge <?php echo strtolower($st); ?>">
            <i class="fas fa-<?php echo $st_icon; ?>"></i> <?php echo $st_label; ?>
          </span>
        </div>

        <div class="submission-meta">
          <div class="meta-item">
            <span class="meta-label"><i class="fas fa-building"></i> Organization</span>
            <span class="meta-value"><?php echo htmlspecialchars($submission['org_name']); ?></span>
          </div>
          <div class="meta-item">
            <span class="meta-label"><i class="fas fa-user"></i> Submitted By</span>
            <span class="meta-value"><?php echo htmlspecialchars($submission['submitted_by_name']); ?></span>
          </div>
          <div class="meta-item">
            <span class="meta-label"><i class="fas fa-calendar"></i> Submitted At</span>
            <span class="meta-value"><?php echo date('M d, Y H:i', strtotime($submission['submitted_at'])); ?></span>
          </div>
        </div>

        <div class="submission-description">
          <h4>Description</h4>
          <p><?php echo htmlspecialchars($submission['description']); ?></p>
        </div>
      </div>

      <!-- REVIEWS SECTION -->
      <div class="reviews-section">
        <h3><i class="fas fa-comments"></i> Reviews (<?php echo isset($reviews) ? count($reviews) : 0; ?>)</h3>
        
        <?php if (!empty($reviews)): ?>
          <div class="reviews-list">
            <?php foreach ($reviews as $review): ?>
              <div class="review-item">
                <div class="review-header">
                  <div class="reviewer-info">
                    <span class="reviewer-name"><?php echo htmlspecialchars($review['full_name'] ?? 'Unknown'); ?></span>
                    <span class="review-date"><i class="fas fa-clock"></i> <?php echo date('M d, Y H:i', strtotime($review['reviewed_at'])); ?></span>
                  </div>
                  <span class="review-status-badge <?php echo strtolower($review['status']); ?>">
                    <?php echo ucfirst($review['status']); ?>
                  </span>
                </div>
                <div class="review-feedback">
                  <p><?php echo htmlspecialchars($review['feedback']); ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="no-reviews">
            <i class="fas fa-inbox"></i>
            <span>No reviews yet</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- SUBMISSION ACTIONS -->
      <div class="submission-actions-card">
        <div class="actions-header">
          <h3><i class="fas fa-check-double"></i> Review Actions</h3>
          <p>Forward to Admin for final review, or reject this submission.</p>
        </div>

        <?php if ($submission['status'] === 'pending'): ?>
        <form method="POST" style="margin-top:20px;">
          <input type="hidden" name="submission_id" value="<?php echo $submission['submission_id']; ?>">
          <div style="margin-bottom:14px;">
            <label style="font-weight:600;color:#065f46;font-size:0.9em;display:block;margin-bottom:6px;">
              <i class="fas fa-comment"></i> Feedback (optional)
            </label>
            <textarea name="feedback" rows="3"
              style="width:100%;padding:10px;border:1.5px solid #a7f3d0;border-radius:8px;font-size:0.9em;resize:vertical;font-family:inherit;"
              placeholder="Enter feedback for the organization..."></textarea>
          </div>
          <div class="actions-buttons">
            <button type="submit" name="action" value="approve"
              onclick="return confirm('Forward this submission to Admin for final review?')"
              style="padding:12px 28px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:white;border:none;border-radius:10px;cursor:pointer;font-weight:700;font-size:0.95em;display:flex;align-items:center;gap:8px;transition:all .2s;">
              <i class="fas fa-paper-plane"></i> Forward to Admin
            </button>
            <button type="submit" name="action" value="reject"
              style="padding:12px 28px;background:linear-gradient(135deg,#ef4444,#dc2626);color:white;border:none;border-radius:10px;cursor:pointer;font-weight:700;font-size:0.95em;display:flex;align-items:center;gap:8px;transition:all .2s;">
              <i class="fas fa-times"></i> Reject
            </button>
            <a href="review.php?org=<?php echo $selected_org_id; ?>"
              style="padding:12px 28px;background:#f0f0f0;color:#4a5568;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.95em;display:flex;align-items:center;gap:8px;">
              <i class="fas fa-arrow-left"></i> Back
            </a>
          </div>
        </form>

        <?php else: ?>
        <div class="actions-buttons" style="margin-top:20px;">
          <?php
            $st = $submission['status'];
            $st_cfg = [
              'in_review' => ['bg'=>'#dbeafe','color'=>'#1d4ed8','icon'=>'paper-plane','label'=>'Forwarded to Admin — Pending Final Review'],
              'approved'  => ['bg'=>'#d1fae5','color'=>'#065f46','icon'=>'check-circle','label'=>'Approved (Final)'],
              'rejected'  => ['bg'=>'#fee2e2','color'=>'#991b1b','icon'=>'ban','label'=>'Rejected'],
            ];
            $cfg = $st_cfg[$st] ?? ['bg'=>'#f0f0f0','color'=>'#4a5568','icon'=>'circle','label'=>ucfirst($st)];
          ?>
          <span style="padding:10px 18px;border-radius:10px;font-weight:700;font-size:0.9em;
            background:<?php echo $cfg['bg']; ?>;color:<?php echo $cfg['color']; ?>;
            display:flex;align-items:center;gap:8px;">
            <i class="fas fa-<?php echo $cfg['icon']; ?>"></i>
            <?php echo $cfg['label']; ?>
          </span>
          <a href="review.php?org=<?php echo $selected_org_id; ?>"
            style="padding:12px 28px;background:#f0f0f0;color:#4a5568;border-radius:10px;text-decoration:none;font-weight:600;font-size:0.95em;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-arrow-left"></i> Back to Organization
          </a>
        </div>
        <?php endif; ?>

    <?php elseif ($selected_org_id && $current_org): ?>
      <!-- ORGANIZATION SUBMISSIONS TABLE VIEW -->
      <div class="breadcrumb-nav">
        <a href="review.php"><i class="fas fa-folder-open"></i> Organizations</a>
        <i class="fas fa-chevron-right"></i>
        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($current_org['org_name']); ?></span>
      </div>

      <div class="org-header-section">
        <div class="org-header-icon">
          <i class="fas fa-building"></i>
        </div>
        <div class="org-header-content">
          <div class="org-header-title">
            <h3><?php echo htmlspecialchars($current_org['org_name']); ?></h3>
            <span class="org-status-badge"><?php echo strtoupper($current_org['status'] ?? 'ACTIVE'); ?></span>
          </div>
          <div class="org-header-meta">
            <span class="meta-item"><i class="fas fa-code"></i> <?php echo htmlspecialchars($current_org['org_code'] ?? 'N/A'); ?></span>
          </div>
        </div>
        <button onclick="showOrgInfoModal()" class="btn-info">
          <i class="fas fa-info-circle"></i> <span>Organization Info</span>
        </button>
      </div>
      
      <div class="search-filter-container">
        <form method="GET" class="search-filter-form">
          <input type="hidden" name="org" value="<?php echo htmlspecialchars($selected_org_id); ?>">
          <div class="search-input-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" name="search" placeholder="Search by title or submitter..." value="<?php echo htmlspecialchars($search_query); ?>" class="search-input">
          </div>
          <?php if ($search_query): ?>
            <a href="review.php?org=<?php echo htmlspecialchars($selected_org_id); ?>" class="btn-clear">
              <i class="fas fa-times"></i> Clear
            </a>
          <?php endif; ?>
        </form>
      </div>
      
      <div class="table-container" style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
        <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
        <table class="submissions-table">
          <thead>
            <tr>
              <th><i class="fas fa-hashtag"></i> Ref #</th>
              <th><i class="fas fa-file-alt"></i> Title</th>
              <th><i class="fas fa-tag"></i> Status</th>
              <th><i class="fas fa-user"></i> Submitted By</th>
              <th><i class="fas fa-calendar"></i> Date</th>
              <th><i class="fas fa-comments"></i> Reviews</th>
              <th><i class="fas fa-cog"></i> Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($submissions)): ?>
              <?php foreach ($submissions as $index => $sub): ?>
                <tr>
                  <td class="ref-number">#<?php echo str_pad($index + 1, 3, '0', STR_PAD_LEFT); ?></td>
                  <td class="title-cell"><strong><?php echo htmlspecialchars($sub['title']); ?></strong></td>
                  <td><?php
                    $s = $sub['status'];
                    $sl = ['pending'=>'Pending','in_review'=>'Forwarded','approved'=>'Approved','rejected'=>'Rejected','archived'=>'Archived'];
                    $si = ['pending'=>'clock','in_review'=>'paper-plane','approved'=>'check-circle','rejected'=>'ban','archived'=>'archive'];
                  ?><span class="status-badge <?php echo strtolower($s); ?>"><i class="fas fa-<?php echo $si[$s]??'circle'; ?>"></i> <?php echo $sl[$s]??ucfirst($s); ?></span></td>
                  <td><?php echo htmlspecialchars($sub['submitted_by_name'] ?? 'N/A'); ?></td>
                  <td><?php echo date('M d, Y', strtotime($sub['submitted_at'])); ?></td>
                  <td class="review-count"><span class="badge-count"><?php echo $sub['review_count'] ?? 0; ?></span></td>
                  <td>
                    <div class="action-buttons">
                      <a href="review.php?org=<?php echo $selected_org_id; ?>&id=<?php echo $sub['submission_id']; ?>" class="btn-action btn-view" title="Preview">
                        <i class="fas fa-eye"></i> Preview
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr class="empty-row">
                <td colspan="7">
                  <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No submissions found</p>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
        </div>
      </div>

    <?php else: ?>
      
      <div class="folders-grid">
        <?php 
        // Display 49 organization folders
        for ($i = 1; $i <= 49; $i++): 
          $org = $organizations[$i - 1] ?? null;
        ?>
          <?php if ($org): ?>
            <a href="review.php?org=<?php echo htmlspecialchars($org['org_id']); ?>" class="folder-card">
              <div class="folder-icon">
                <i class="fas fa-folder"></i>
              </div>
              <div class="folder-content">
                <p class="folder-name"><?php echo htmlspecialchars($org['org_name']); ?></p>
                <p class="folder-code"><?php echo htmlspecialchars($org['org_code']); ?></p>
              </div>
              <div class="folder-stats">
                <span class="stat-badge">
                  <i class="fas fa-file-alt"></i>
                  <?php echo $org['submission_count'] ?? 0; ?> submissions
                </span>
              </div>
              <div class="folder-hover-arrow">
                <i class="fas fa-chevron-right"></i>
              </div>
            </a>
          <?php else: ?>
            <div class="folder-card folder-empty">
              <div class="folder-icon">
                <i class="fas fa-folder"></i>
              </div>
              <div class="folder-content">
                <p class="folder-name">Empty Slot</p>
                <p class="folder-code">--</p>
              </div>
            </div>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    <?php endif; ?>

  <!-- ORGANIZATION INFO MODAL -->
  <div id="orgInfoModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2><i class="fas fa-building"></i> Organization Information</h2>
        <button type="button" onclick="closeOrgInfoModal()" class="modal-close">✕</button>
      </div>
      <div class="modal-body org-info-content">
        <div class="info-section">
          <div class="info-grid">
            <div class="info-item">
              <label>Organization Name</label>
              <p><?php echo htmlspecialchars($current_org['org_name'] ?? 'N/A'); ?></p>
            </div>
            <div class="info-item">
              <label>Organization Code</label>
              <p><?php echo htmlspecialchars($current_org['org_code'] ?? 'N/A'); ?></p>
            </div>
            <div class="info-item">
              <label>Status</label>
              <p><span class="status-badge <?php echo strtolower($current_org['status'] ?? 'active'); ?>"><i class="fas fa-circle"></i> <?php echo ucfirst($current_org['status'] ?? 'active'); ?></span></p>
            </div>
            <div class="info-item">
              <label>Email</label>
              <p><?php echo htmlspecialchars($current_org['email'] ?? 'N/A'); ?></p>
            </div>
            <div class="info-item">
              <label>Phone</label>
              <p><?php echo htmlspecialchars($current_org['phone'] ?? 'N/A'); ?></p>
            </div>
            <div class="info-item">
              <label>Contact Person</label>
              <p><?php echo htmlspecialchars($current_org['contact_person'] ?? 'N/A'); ?></p>
            </div>
          </div>
          
          <?php if (!empty($current_org['description'])): ?>
          <div class="description-section">
            <label>Description</label>
            <p><?php echo htmlspecialchars($current_org['description']); ?></p>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeOrgInfoModal()" class="btn-secondary">Close</button>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

<script src="../js/navbar.js"></script>
<script src="../js/review.js"></script>
<script>
</script>

<style>
/* PAGE HEADER WITH SEARCH */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  gap: 20px;
  flex-wrap: wrap;
}

.page-header h2 {
  flex: 1;
  min-width: 300px;
}

.page-header-search {
  min-width: 300px;
}

.search-input-wrapper-header {
  position: relative;
  display: flex;
  align-items: center;
}

.search-input-header {
  width: 100%;
  padding: 10px 16px 10px 40px;
  border: 1.5px solid #e5e7eb;
  border-radius: 10px;
  font-size: 14px;
  transition: all 0.3s ease;
  background: white;
}

.search-input-header:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
  background: #f9fafb;
}

.search-input-wrapper-header .search-icon {
  position: absolute;
  left: 12px;
  color: #9ca3af;
  font-size: 14px;
  pointer-events: none;
}

.search-input-header::placeholder {
  color: #d1d5db;
}

/* ORGANIZATION HEADER SECTION */
.org-header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 40px;
  padding: 28px 30px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 1) 100%);
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(16, 185, 129, 0.12),
              inset 0 1px 0 rgba(255, 255, 255, 0.8);
  border: 1.5px solid rgba(16, 185, 129, 0.15);
  position: relative;
  overflow: hidden;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.org-header-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: linear-gradient(90deg, #10b981 0%, #059669 50%, #047857 100%);
}

.org-header-section:hover {
  box-shadow: 0 12px 40px rgba(16, 185, 129, 0.16),
              inset 0 1px 0 rgba(255, 255, 255, 0.8);
  transform: translateY(-4px);
}

.org-header-icon {
  width: 70px;
  height: 70px;
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  color: #10b981;
  box-shadow: 0 8px 24px rgba(16, 185, 129, 0.2);
  flex-shrink: 0;
  animation: iconFloat 3s ease-in-out infinite;
}

@keyframes iconFloat {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-8px);
  }
}

.org-header-content {
  flex: 1;
  margin-left: 24px;
  min-width: 0;
}

.org-header-title {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.org-header-title h3 {
  margin: 0;
  font-size: 1.6em;
  color: #1a202c;
  font-weight: 800;
  letter-spacing: -0.5px;
}

.org-status-badge {
  display: inline-block;
  padding: 6px 12px;
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  color: #065f46;
  font-size: 0.75em;
  font-weight: 700;
  border-radius: 8px;
  letter-spacing: 0.5px;
}

.org-header-meta {
  display: flex;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
}

.org-header-meta .meta-item {
  font-size: 0.9em;
  color: #718096;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: 6px;
}

.org-header-meta .meta-item i {
  color: #10b981;
  font-size: 1em;
}

.btn-info {
  padding: 12px 28px;
  background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  color: white;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 700;
  font-size: 0.95em;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
  display: flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
  flex-shrink: 0;
  position: relative;
  overflow: hidden;
}

.btn-info::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: left 0.6s ease;
}

.btn-info:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
  background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
}

.btn-info:hover::before {
  left: 100%;
}

.btn-info:active {
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(59, 130, 246, 0.3);
}

.btn-info span {
  font-size: 0.95em;
  letter-spacing: 0.3px;
}

/* ORGANIZATION INFO MODAL */
.modal {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0, 0, 0, 0.5);
  justify-content: center;
  align-items: center;
  animation: fadeIn 0.3s ease-in-out;
  backdrop-filter: blur(4px);
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.modal-content {
  background: white;
  padding: 0;
  border-radius: 12px;
  width: 90%;
  max-width: 600px;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
  animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  background: linear-gradient(135deg, #047857 0%, #10b981 100%);
  color: white;
  padding: 25px 30px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: none;
}

.modal-header h2 {
  margin: 0;
  font-size: 1.4em;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 12px;
}

.modal-close {
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  font-size: 24px;
  cursor: pointer;
  width: 35px;
  height: 35px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.modal-close:hover {
  background: rgba(255, 255, 255, 0.3);
}

.modal-body {
  padding: 30px;
  max-height: 60vh;
  overflow-y: auto;
}

.org-info-content .info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 20px;
}

.info-item {
  display: flex;
  flex-direction: column;
}

.info-item label {
  font-weight: 700;
  color: #047857;
  font-size: 0.85em;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}

.info-item p {
  margin: 0;
  color: #2d3748;
  font-size: 1em;
  padding: 10px 12px;
  background: #f7fafc;
  border-radius: 6px;
  border-left: 3px solid #10b981;
}

.description-section {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 2px solid #f0f0f0;
}

.description-section label {
  font-weight: 700;
  color: #047857;
  font-size: 0.85em;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: block;
  margin-bottom: 10px;
}

.description-section p {
  margin: 0;
  color: #2d3748;
  line-height: 1.6;
  padding: 12px;
  background: #f7fafc;
  border-radius: 6px;
  border-left: 3px solid #10b981;
}

.modal-footer {
  padding: 20px 30px;
  border-top: 1px solid #f0f0f0;
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.btn-secondary {
  padding: 10px 20px;
  background: #f0f0f0;
  color: #4a5568;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.9em;
  transition: all 0.3s ease;
}

.btn-secondary:hover {
  background: #e8ecf1;
  border-color: #cbd5e0;
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .page-header h2 {
    min-width: auto;
  }

  .page-header-search {
    min-width: auto;
    width: 100%;
  }

  .org-header-section {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
  }

  .org-header-icon {
    width: 60px;
    height: 60px;
    font-size: 28px;
  }

  .org-header-content {
    margin-left: 0;
    width: 100%;
  }

  .org-header-title h3 {
    font-size: 1.3em;
  }

  .org-header-meta {
    gap: 12px;
  }

  .btn-info {
    width: 100%;
    justify-content: center;
    padding: 12px 20px;
  }

  .modal-content {
    width: 95%;
  }

  .org-info-content .info-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .org-header-section {
    padding: 16px;
  }

  .org-header-icon {
    width: 50px;
    height: 50px;
    font-size: 24px;
  }

  .org-header-title h3 {
    font-size: 1.1em;
  }

  .org-header-meta {
    flex-direction: column;
    gap: 8px;
    width: 100%;
  }

  .btn-info {
    padding: 10px 16px;
    font-size: 0.85em;
  }

  .btn-info span {
    display: none;
  }
}

/* FOLDERS GRID STYLES */
.folders-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 25px;
  margin-top: 30px;
}

.folder-card {
  display: flex;
  flex-direction: column;
  gap: 15px;
  padding: 25px;
  background: linear-gradient(135deg, #ffffff 0%, #f8fafb 100%);
  border: 2px solid rgba(16, 185, 129, 0.15);
  border-radius: 14px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  text-decoration: none;
  color: inherit;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.08);
  position: relative;
  overflow: hidden;
}

.folder-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 4px;
  background: linear-gradient(90deg, #10b981 0%, #059669 50%, #047857 100%);
  transition: left 0.4s ease;
}

.folder-card:hover {
  transform: translateY(-8px);
  border-color: rgba(16, 185, 129, 0.4);
  box-shadow: 0 12px 32px rgba(16, 185, 129, 0.2);
  background: linear-gradient(135deg, #f0fdf4 0%, #f0fdf4 100%);
}

.folder-card:hover::before {
  left: 100%;
}

.folder-card.folder-empty {
  opacity: 0.4;
  pointer-events: none;
  cursor: not-allowed;
}

.folder-icon {
  font-size: 48px;
  color: #10b981;
  animation: iconBounce 2s ease-in-out infinite;
}

@keyframes iconBounce {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-8px);
  }
}

.folder-content {
  flex: 1;
  text-align: center;
}

.folder-name {
  margin: 0;
  font-size: 1.1em;
  font-weight: 700;
  color: #1a202c;
  line-height: 1.3;
}

.folder-code {
  margin: 5px 0 0 0;
  font-size: 0.85em;
  color: #718096;
  font-weight: 600;
  letter-spacing: 0.5px;
  text-align: center;
}

.folder-stats {
  display: flex;
  align-items: center;
  gap: 8px;
}

.stat-badge {
  display: inline-block;
  padding: 8px 12px;
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  color: #065f46;
  font-size: 0.85em;
  font-weight: 700;
  border-radius: 8px;
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 6px;
}

.folder-hover-arrow {
  opacity: 0;
  transform: translateX(-10px);
  transition: all 0.3s ease;
  font-size: 1.2em;
  color: #10b981;
}

.folder-card:hover .folder-hover-arrow {
  opacity: 1;
  transform: translateX(0);
}

@media (max-width: 768px) {
  .folders-grid {
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 15px;
  }

  .folder-card {
    padding: 18px;
  }

  .folder-icon {
    font-size: 36px;
  }

  .folder-name {
    font-size: 0.95em;
  }
}

@media (max-width: 480px) {
  .folders-grid {
    grid-template-columns: 1fr;
  }
}

/* BREADCRUMB NAVIGATION */
.breadcrumb-nav {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 25px;
  padding: 12px 0;
  font-size: 0.95em;
  flex-wrap: wrap;
}

.breadcrumb-nav a {
  color: #10b981;
  text-decoration: none;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: all 0.3s ease;
  padding: 6px 10px;
  border-radius: 6px;
}

.breadcrumb-nav a:hover {
  background: rgba(16, 185, 129, 0.1);
  color: #059669;
}

.breadcrumb-nav i.fa-chevron-right {
  color: #cbd5e0;
  font-size: 0.8em;
}

.breadcrumb-nav span {
  color: #4a5568;
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 500;
}

/* SEARCH FILTER CONTAINER */
.search-filter-container {
  margin-bottom: 30px;
}

.search-filter-form {
  display: flex;
  gap: 12px;
  align-items: center;
}

.search-input-wrapper {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
  max-width: 500px;
}

.search-input-wrapper .search-icon {
  position: absolute;
  left: 14px;
  color: #cbd5e0;
  font-size: 16px;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 12px 14px 12px 42px;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  font-size: 14px;
  transition: all 0.3s ease;
  background: white;
  color: #2d3748;
}

.search-input:focus {
  outline: none;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
  background: #f0fdf4;
}

.search-input::placeholder {
  color: #a0aec0;
}

.btn-clear {
  padding: 10px 16px;
  background: #fed7aa;
  color: #92400e;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.9em;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 6px;
  text-decoration: none;
}

.btn-clear:hover {
  background: #fdba74;
  transform: translateY(-2px);
}

/* TABLE CONTAINER */
.table-container {
  background: white;
  border-radius: 12px;
  overflow-x: auto;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  border: 1.5px solid #e2e8f0;
}

.submissions-table {
  width: 100%;
  border-collapse: collapse;
}

.submissions-table thead {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
}

.submissions-table th {
  padding: 16px 18px;
  text-align: left;
  font-weight: 700;
  font-size: 0.9em;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.submissions-table th i {
  margin-right: 6px;
  font-size: 0.85em;
}

.submissions-table tbody tr {
  border-bottom: 1px solid #f0f0f0;
  transition: all 0.3s ease;
}

.submissions-table tbody tr:hover {
  background: #f9fafb;
}

.submissions-table tbody tr.empty-row:hover {
  background: white;
}

.submissions-table td {
  padding: 16px 18px;
  color: #2d3748;
  font-size: 0.95em;
}

.submissions-table .ref-number {
  font-weight: 700;
  color: #10b981;
}

.submissions-table .title-cell {
  font-weight: 600;
  color: #1a202c;
}

.status-badge {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85em;
  white-space: nowrap;
}

.status-badge.approved {
  background: #d1fae5;
  color: #065f46;
}

.status-badge.in_review {
  background: #dbeafe;
  color: #1d4ed8;
}

.status-badge.pending {
  background: #fef08a;
  color: #78350f;
}

.status-badge.rejected {
  background: #fee2e2;
  color: #991b1b;
}

.status-badge i {
  margin-right: 4px;
}

.review-count {
  text-align: center;
}

.badge-count {
  display: inline-block;
  padding: 4px 10px;
  background: #edf2f7;
  color: #2d3748;
  border-radius: 6px;
  font-weight: 700;
  font-size: 0.9em;
}

.action-buttons {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 8px 14px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.85em;
  font-weight: 600;
  transition: all 0.3s ease;
  text-decoration: none;
}

.btn-action.btn-view {
  background: #dbeafe;
  color: #1e40af;
}

.btn-action.btn-view:hover {
  background: #bfdbfe;
  transform: translateY(-2px);
}



.btn-action i {
  font-size: 0.9em;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 40px 20px;
  color: #a0aec0;
}

.empty-state i {
  font-size: 3em;
  color: #cbd5e0;
}

.empty-state p {
  font-size: 1em;
  margin: 0;
  color: #718096;
  font-weight: 500;
}

/* SUBMISSION DETAIL CARD */
.submission-detail-card {
  background: white;
  border-radius: 12px;
  padding: 30px;
  margin-bottom: 30px;
  border: 1.5px solid #e2e8f0;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}

.submission-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  margin-bottom: 25px;
  padding-bottom: 20px;
  border-bottom: 2px solid #f0f0f0;
}

.submission-header h3 {
  margin: 0;
  font-size: 1.6em;
  color: #1a202c;
  font-weight: 800;
}

.submission-meta {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 25px;
}

.submission-meta .meta-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.meta-label {
  font-weight: 700;
  color: #10b981;
  font-size: 0.85em;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: flex;
  align-items: center;
  gap: 6px;
}

.meta-value {
  color: #2d3748;
  font-size: 1em;
  padding: 10px 12px;
  background: #f7fafc;
  border-radius: 6px;
  border-left: 3px solid #10b981;
}

.submission-description {
  margin-bottom: 20px;
}

.submission-description h4 {
  margin: 0 0 12px 0;
  color: #1a202c;
  font-weight: 700;
  font-size: 1.05em;
}

.submission-description p {
  margin: 0;
  color: #4a5568;
  line-height: 1.6;
  padding: 12px;
  background: #f7fafc;
  border-radius: 6px;
  border-left: 3px solid #10b981;
}

/* REVIEWS SECTION */
.reviews-section {
  margin: 40px 0;
}

.reviews-section h3 {
  margin: 0 0 25px 0;
  font-size: 1.3em;
  color: #1a202c;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 10px;
}

.reviews-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.review-item {
  background: white;
  border: 1.5px solid #e2e8f0;
  border-radius: 10px;
  padding: 20px;
  transition: all 0.3s ease;
}

.review-item:hover {
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
  border-color: rgba(16, 185, 129, 0.3);
}

.review-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  gap: 15px;
}

.reviewer-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.reviewer-name {
  font-weight: 700;
  color: #1a202c;
}

.review-date {
  font-size: 0.85em;
  color: #718096;
  display: flex;
  align-items: center;
  gap: 5px;
}

.review-status-badge {
  padding: 6px 12px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85em;
  white-space: nowrap;
}

.review-status-badge.approved {
  background: #d1fae5;
  color: #065f46;
}

.review-status-badge.rejected {
  background: #fee2e2;
  color: #991b1b;
}

.review-feedback {
  padding-top: 15px;
  border-top: 1px solid #f0f0f0;
}

.review-feedback p {
  margin: 0;
  color: #4a5568;
  line-height: 1.6;
}

.no-reviews {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 40px 20px;
  background: #f9fafb;
  border-radius: 10px;
  color: #a0aec0;
}

.no-reviews i {
  font-size: 2.5em;
  color: #cbd5e0;
}

.submission-actions-card {
  background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
  border-radius: 12px;
  padding: 30px;
  border: 1.5px solid rgba(16, 185, 129, 0.3);
}

.actions-header h3 {
  margin: 0 0 8px 0;
  color: #065f46;
  font-size: 1.2em;
  font-weight: 700;
}

.actions-header p {
  margin: 0;
  color: #047857;
  font-size: 0.95em;
}

.actions-buttons {
  display: flex;
  gap: 12px;
  margin-top: 20px;
  flex-wrap: wrap;
}

.success-alert {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  background: #d1fae5;
  color: #065f46;
  border-radius: 10px;
  margin-bottom: 25px;
  border-left: 4px solid #10b981;
  font-weight: 600;
}

.success-alert i {
  font-size: 1.2em;
}

@media (max-width: 768px) {
  .search-filter-form {
    flex-direction: column;
    align-items: stretch;
  }

  .search-input-wrapper {
    max-width: 100%;
  }

  .submission-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .submission-meta {
    grid-template-columns: 1fr;
  }

  .review-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .action-buttons {
    flex-direction: column;
  }

  .btn-action {
    width: 100%;
    justify-content: center;
  }

  .submissions-table th,
  .submissions-table td {
    padding: 12px 10px;
    font-size: 0.85em;
  }
}

@media (max-width: 480px) {
  .breadcrumb-nav {
    font-size: 0.85em;
  }

  .submissions-table th {
    padding: 10px 8px;
    font-size: 0.75em;
  }

  .submissions-table td {
    padding: 10px 8px;
    font-size: 0.85em;
  }

  .action-buttons {
    flex-direction: column;
  }
}
</style>

<script>
// Search functionality for organizations grid
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('orgSearchInput');
  if (!searchInput) return;

  searchInput.addEventListener('keyup', function() {
    const searchQuery = this.value.toLowerCase();
    const folderCards = document.querySelectorAll('.folder-card');
    
    folderCards.forEach(card => {
      const folderName = card.querySelector('.folder-name')?.textContent.toLowerCase() || '';
      const folderCode = card.querySelector('.folder-code')?.textContent.toLowerCase() || '';
      
      if (folderName.includes(searchQuery) || folderCode.includes(searchQuery)) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  });
});

function showOrgInfoModal() {
  document.getElementById('orgInfoModal').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeOrgInfoModal() {
  document.getElementById('orgInfoModal').style.display = 'none';
  document.body.style.overflow = 'auto';
}

window.onclick = function(event) {
  const modal = document.getElementById('orgInfoModal');
  if (event.target === modal) {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  }
}
</script>

</body>
</html>