<?php
session_start();
require_once '../db/config.php';

// Redirect if not logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Load site settings (vision, mission, values, admin names)
$settings_result = mysqli_query($conn, "SELECT setting_key, setting_value FROM site_settings");
$site_settings = [];
if ($settings_result) {
    while ($row = mysqli_fetch_assoc($settings_result)) {
        $site_settings[$row['setting_key']] = $row['setting_value'];
    }
}
$mission_text     = $site_settings['mission']            ?? 'To strengthen the capability of organization through collaboration and active participation in school governance.';
$vision_text      = $site_settings['vision']             ?? 'A highly trusted organization committed to capacitating progressive communities.';
$values_text      = $site_settings['values']             ?? "SERVICE - Dedicated to serving our communities\nVOLUNTEERISM - Active participation and commitment";
$president_name   = $site_settings['president_name']     ?? 'Name of Interim University President';
$president_title  = $site_settings['president_title']    ?? 'Interim University President';
$dean_name        = $site_settings['dean_name']          ?? 'Name of Dean';
$dean_title       = $site_settings['dean_title']         ?? 'Dean, Office of Student Affairs and Services';

// Get organization count — orgs are users with an org_code
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE org_code IS NOT NULL AND status = 'active'");
$org_count = mysqli_fetch_assoc($result);

// Get submission count
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM submissions");
$submission_count = mysqli_fetch_assoc($result);

// Get approval statistics
$result = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM submissions
");
$stats = mysqli_fetch_assoc($result);

$approval_rate = $stats['total'] > 0 
    ? round(($stats['approved'] / $stats['total']) * 100) 
    : 0;

// Fetch all active announcements from DB
$result = mysqli_query($conn, "
    SELECT announcement_id, title, content, priority, category,
           audience, is_pinned, expires_at, created_at
    FROM announcements
    WHERE is_active = 1
    ORDER BY is_pinned DESC, FIELD(priority,'urgent','high','low'), created_at DESC
    LIMIT 10
");
$announcements = [];
while ($row = mysqli_fetch_assoc($result)) {
    $announcements[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php $current_page = 'home'; ?>
<?php include 'navbar.php'; ?>

<div id="page-content" class="page-background">

  <!-- WELCOME SECTION -->
  <div class="welcome-section">
    <div class="welcome-content">
      <h1>Welcome to OSAS</h1>
      <p class="welcome-subtitle">Office of Student Affairs and Services</p>
      <p class="welcome-description">Manage submissions, reviews, and organizational governance with ease. Stay updated with the latest announcements and maintain transparency across all departments.</p>
    </div>
    <div class="welcome-stats">
      <div class="stat-item">
        <span class="stat-number"><?php echo $org_count['count']; ?></span>
        <span class="stat-label">Organizations</span>
      </div>
      <div class="stat-item">
        <span class="stat-number"><?php echo number_format($submission_count['count']); ?></span>
        <span class="stat-label">Submissions</span>
      </div>
      <div class="stat-item">
        <span class="stat-number"><?php echo $approval_rate; ?>%</span>
        <span class="stat-label">Approval Rate</span>
      </div>
    </div>
  </div>


  <!-- ANNOUNCEMENT BOARD -->
  <div class="announcement-board">
    <div class="announcement-board-inner">
      <div class="announcement-header">
        <div class="announcement-header-left">
          <div class="announcement-icon"><i class="fas fa-bullhorn"></i></div>
          <div class="announcement-header-text">
            <h3>Latest Announcements</h3>
            <span class="announcement-subtitle">Important updates and notices</span>
          </div>
        </div>
        <button class="edit-btn" onclick="openAddModal()">
          <i class="fas fa-plus"></i> <span>Add</span>
        </button>
      </div>

      <div id="announcementList">
        <?php if (empty($announcements)): ?>
          <p style="color:#888;text-align:center;padding:20px 0;">No announcements yet.</p>
        <?php else:
          $badge_map = [
            'urgent' => ['label'=>'Urgent','color'=>'#c0392b','bg'=>'#fde8e8'],
            'high'   => ['label'=>'High',  'color'=>'#b7770d','bg'=>'#fff3cd'],
            'low'    => ['label'=>'Low',   'color'=>'#555',   'bg'=>'#f0f0f0'],
          ];
          $cat_map = [
            'event'    => ['label'=>'Event',    'color'=>'#1d4ed8','bg'=>'#dbeafe'],
            'deadline' => ['label'=>'Deadline', 'color'=>'#b91c1c','bg'=>'#fee2e2'],
            'policy'   => ['label'=>'Policy',   'color'=>'#6d28d9','bg'=>'#ede9fe'],
            'general'  => ['label'=>'General',  'color'=>'#065f46','bg'=>'#d1fae5'],
          ];
          foreach ($announcements as $ann):
            $p  = $ann['priority'] ?? 'low';
            $pb = $badge_map[$p] ?? $badge_map['low'];
            $c  = $ann['category'] ?? 'general';
            $cb = $cat_map[$c] ?? $cat_map['general'];
            $pinned  = !empty($ann['is_pinned']);
            $expires = !empty($ann['expires_at']) ? date('M d, Y', strtotime($ann['expires_at'])) : null;
            $audience = !empty($ann['audience']) ? htmlspecialchars($ann['audience']) : null;

            // Read receipt count
            $ann_id_safe = (int) $ann['announcement_id'];
            $rr = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM announcement_reads WHERE announcement_id = $ann_id_safe");
            $read_count = $rr ? (int) mysqli_fetch_assoc($rr)['cnt'] : 0;
        ?>
            <div class="announcement-item<?php echo $pinned ? ' ann-pinned-admin' : ''; ?>"
                 id="ann-<?php echo $ann['announcement_id']; ?>">
              <?php if ($pinned): ?>
                <div class="ann-pin-label"><i class="fas fa-thumbtack"></i> Pinned</div>
              <?php endif; ?>
              <div class="ann-item-header">
                <h4 class="ann-item-title">
                  <span class="ann-priority-badge" style="background:<?php echo $cb['bg']; ?>;color:<?php echo $cb['color']; ?>;"><?php echo $cb['label']; ?></span>
                  <span class="ann-priority-badge" style="background:<?php echo $pb['bg']; ?>;color:<?php echo $pb['color']; ?>;"><?php echo $pb['label']; ?></span>
                  <?php echo htmlspecialchars($ann['title']); ?>
                </h4>
                <div class="ann-item-actions">
                  <button class="ann-receipt-btn" title="Read receipts"
                    onclick="showReceipts(<?php echo $ann['announcement_id']; ?>, <?php echo htmlspecialchars(json_encode($ann['title'])); ?>)">
                    <i class="fas fa-eye"></i> <span><?php echo $read_count; ?></span>
                  </button>
                  <button class="ann-edit-btn" title="Edit"
                    onclick="openEditModal(
                      <?php echo $ann['announcement_id']; ?>,
                      <?php echo htmlspecialchars(json_encode($ann['title'])); ?>,
                      <?php echo htmlspecialchars(json_encode($ann['content'])); ?>,
                      <?php echo htmlspecialchars(json_encode($p)); ?>,
                      <?php echo htmlspecialchars(json_encode($c)); ?>,
                      <?php echo htmlspecialchars(json_encode($audience ?? '')); ?>,
                      <?php echo htmlspecialchars(json_encode($ann['expires_at'] ?? '')); ?>,
                      <?php echo $pinned ? 'true' : 'false'; ?>
                    )">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button class="ann-delete-btn" title="Delete"
                    onclick="deleteAnnouncement(<?php echo $ann['announcement_id']; ?>)">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
              <p><?php echo htmlspecialchars($ann['content']); ?></p>
              <div class="ann-item-meta">
                <small><i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($ann['created_at'])); ?></small>
                <?php if ($audience): ?>
                  <small class="ann-audience-tag"><i class="fas fa-users"></i> <?php echo $audience; ?></small>
                <?php else: ?>
                  <small style="color:#aaa;"><i class="fas fa-globe"></i> All organizations</small>
                <?php endif; ?>
                <?php if ($expires): ?>
                  <small class="ann-expires-tag"><i class="fas fa-hourglass-half"></i> Expires <?php echo $expires; ?></small>
                <?php endif; ?>
              </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ORGANIZATION VALUES SECTION -->
  <div class="values-section">
    <div class="section-header-row">
      <h2 class="section-title-label"><i class="fas fa-landmark"></i> Vision, Mission &amp; Values</h2>
      <button class="edit-btn" onclick="openVMVModal()"><i class="fas fa-pen"></i> <span>Edit</span></button>
    </div>
    <div class="values-container">
      <div class="value-card-new mission">
        <div class="card-image-header" style="background: linear-gradient(135deg, #1e90ff 0%, #00bfff 100%); position: relative; overflow: hidden;">
          <div class="hexagon-icon">
            <i class="fas fa-bullseye" style="font-size: 48px; color: #1e90ff;"></i>
          </div>
        </div>
        <div class="card-title-section"><h3>MISSION</h3></div>
        <div class="card-description">
          <p id="missionText"><?php echo htmlspecialchars($mission_text); ?></p>
        </div>
      </div>

      <div class="value-card-new vision">
        <div class="card-image-header" style="background: linear-gradient(135deg, #ff6b6b 0%, #ff1744 100%); position: relative; overflow: hidden;">
          <div class="hexagon-icon">
            <i class="fas fa-binoculars" style="font-size: 48px; color: #ff6b6b;"></i>
          </div>
        </div>
        <div class="card-title-section"><h3>VISION</h3></div>
        <div class="card-description">
          <p id="visionText"><?php echo htmlspecialchars($vision_text); ?></p>
        </div>
      </div>

      <div class="value-card-new values">
        <div class="card-image-header" style="background: linear-gradient(135deg, #ff9500 0%, #ff6f00 100%); position: relative; overflow: hidden;">
          <div class="hexagon-icon">
            <i class="fas fa-scale-balanced" style="font-size: 48px; color: #ff9500;"></i>
          </div>
        </div>
        <div class="card-title-section"><h3>VALUES</h3></div>
        <div class="card-description">
          <div id="valuesDisplay" style="font-size:0.95em;line-height:1.7;color:#4a5568;width:100%;">
            <?php
            $v_lines = array_filter(array_map('trim', explode("\n", trim($values_text))));
            echo '<ul style="list-style:none;padding:0;text-align:left;margin:0;">';
            foreach ($v_lines as $line) {
                if ($line === '') continue;
                if (strpos($line, ' - ') !== false) {
                    list($key, $desc) = explode(' - ', $line, 2);
                    echo '<li style="padding:6px 0;"><i class="fas fa-check-circle" style="margin-right:8px;color:#ff9500;"></i><strong>' . htmlspecialchars(trim($key)) . '</strong> - ' . htmlspecialchars(trim($desc)) . '</li>';
                } else {
                    echo '<li style="padding:6px 0;"><i class="fas fa-check-circle" style="margin-right:8px;color:#ff9500;"></i>' . htmlspecialchars($line) . '</li>';
                }
            }
            echo '</ul>';
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- UNIVERSITY OFFICIALS SECTION -->
  <div class="officials-section">
    <div class="section-header-row">
      <h2 class="section-title-label"><i class="fas fa-user-tie"></i> University Officials</h2>
      <button class="edit-btn" onclick="openOfficialsModal()"><i class="fas fa-pen"></i> <span>Edit</span></button>
    </div>
    <div class="officials-container">
      <div class="official-card">
        <div class="official-icon"><i class="fas fa-university"></i></div>
        <div class="official-info">
          <p class="official-name" id="presidentName"><?php echo htmlspecialchars($president_name); ?></p>
          <p class="official-title" id="presidentTitle"><?php echo htmlspecialchars($president_title); ?></p>
        </div>
      </div>
      <div class="official-card">
        <div class="official-icon"><i class="fas fa-user-graduate"></i></div>
        <div class="official-info">
          <p class="official-name" id="deanName"><?php echo htmlspecialchars($dean_name); ?></p>
          <p class="official-title" id="deanTitle"><?php echo htmlspecialchars($dean_title); ?></p>
        </div>
      </div>
    </div>
  </div>

  <style>
    /* VMV + Officials layout */
    .values-section { margin: 40px 0 20px; }
    .section-header-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; }
    .section-title-label { font-size:1.15em; font-weight:700; color:#1e3a3a; margin:0; display:flex; align-items:center; gap:8px; }
    .values-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 40px; margin-bottom: 40px; }
    .value-card-new { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 25px rgba(0,0,0,0.12); transition: all 0.3s ease; display: flex; flex-direction: column; }
    .value-card-new:hover { transform: translateY(-8px); box-shadow: 0 15px 40px rgba(0,0,0,0.2); }
    .card-image-header { height: 220px; display: flex; align-items: center; justify-content: center; }
    .hexagon-icon { width: 140px; height: 140px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
    .card-title-section { padding: 25px; text-align: center; border-bottom: 2px solid #f0f0f0; }
    .card-title-section h3 { font-size: 1.8em; font-weight: 800; margin: 0; color: #1a202c; letter-spacing: 1px; }
    .card-description { padding: 25px; flex-grow: 1; display: flex; align-items: center; }
    .card-description p { margin: 0; font-size: 0.95em; line-height: 1.7; color: #4a5568; text-align: center; }
    .card-description ul { font-size: 0.95em; line-height: 1.7; color: #4a5568; }
    @media (max-width: 768px) {
      .values-container { grid-template-columns: 1fr; gap: 30px; }
      .card-image-header { height: 180px; }
      .hexagon-icon { width: 110px; height: 110px; }
    }
    /* Officials */
    .officials-section { margin: 10px 0 50px; }
    .officials-container { display:flex; gap:24px; flex-wrap:wrap; }
    .official-card { display:flex; align-items:center; gap:18px; background:#fff; border-radius:12px; padding:22px 28px; box-shadow:0 4px 18px rgba(0,0,0,0.08); flex:1; min-width:260px; border-left:5px solid #059669; }
    .official-icon { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,#047857,#10b981); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .official-icon i { font-size:22px; color:#fff; }
    .official-name { margin:0 0 4px; font-size:1.05em; font-weight:700; color:#1a202c; }
    .official-title { margin:0; font-size:0.83em; color:#6b7280; }
  </style>

<!-- ANNOUNCEMENT MODAL (Add & Edit) -->
<div id="announcementModal" class="modal" style="display:none;">
  <div class="modal-content">
    <h3 id="modalHeading">Add Announcement</h3>
    <input type="hidden" id="editingId" value="">
    <div id="saveSuccess" style="display:none;background:#d4edda;color:#155724;padding:10px 15px;border-radius:6px;margin-bottom:15px;">
      <i class="fas fa-check-circle"></i> <span id="saveSuccessMsg">Saved successfully!</span>
    </div>
    <div id="saveError" style="display:none;background:#f8d7da;color:#721c24;padding:10px 15px;border-radius:6px;margin-bottom:15px;">
      <i class="fas fa-exclamation-circle"></i> <span id="saveErrorMsg">Failed to save.</span>
    </div>
    <form id="announcementForm">
      <label style="display:block;margin-bottom:6px;font-weight:600;">Title</label>
      <input type="text" id="annTitleInput" placeholder="Announcement title..."
        style="width:100%;padding:10px;margin-bottom:14px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;" required>
      <label style="display:block;margin-bottom:6px;font-weight:600;">Content</label>
      <textarea id="annContentInput" placeholder="Enter announcement text..."
        style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;min-height:120px;resize:vertical;box-sizing:border-box;" required></textarea>
      <label style="display:block;margin:14px 0 6px;font-weight:600;">Priority</label>
      <select id="annPriorityInput"
        style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;background:#fff;cursor:pointer;">
        <option value="low" selected><i class="fas fa-flag"></i> Low — Minor updates</option>
        <option value="high"><i class="fas fa-exclamation"></i> High — Requires attention</option>
        <option value="urgent"><i class="fas fa-exclamation-triangle"></i> Urgent — Immediate action needed</option>
      </select>

      <label style="display:block;margin:14px 0 6px;font-weight:600;">Category</label>
      <select id="annCategoryInput"
        style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;background:#fff;cursor:pointer;">
        <option value="general" selected><i class="fas fa-file-alt"></i> General</option>
        <option value="event"><i class="fas fa-calendar-check"></i> Event</option>
        <option value="deadline"><i class="fas fa-clock"></i> Deadline</option>
        <option value="policy"><i class="fas fa-gavel"></i> Policy</option>
      </select>

      <label style="display:block;margin:14px 0 6px;font-weight:600;">Target Audience
        <span style="font-weight:400;font-size:12px;color:#888;">(org_codes comma-separated, blank = all)</span>
      </label>
      <input type="text" id="annAudienceInput" placeholder="e.g. CSC,SSG,SC — leave blank for all organizations"
        style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">

      <label style="display:block;margin:14px 0 6px;font-weight:600;">Expiry Date
        <span style="font-weight:400;font-size:12px;color:#888;">(optional — automatically hides after this date)</span>
      </label>
      <input type="date" id="annExpiresInput"
        style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;">

      <label style="display:flex;align-items:center;gap:10px;margin-top:14px;cursor:pointer;font-weight:600;">
        <input type="checkbox" id="annPinnedInput" style="width:18px;height:18px;accent-color:#f59e0b;cursor:pointer;">
        <i class="fas fa-thumbtack" style="color:#f59e0b;"></i> Pin to top
        <span style="font-weight:400;font-size:12px;color:#888;">(always displays first)</span>
      </label>

      <div class="modal-buttons" style="margin-top:16px;display:flex;gap:10px;">
        <button type="submit" class="save-btn" id="saveAnnouncementBtn">
          <i class="fas fa-save"></i> Save
        </button>
        <button type="button" class="cancel-btn" onclick="closeAnnouncementModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE CONFIRM MODAL -->
<div id="deleteModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:400px;">
    <h3 style="color:#c0392b;"><i class="fas fa-exclamation-triangle"></i> Delete Announcement</h3>
    <p style="margin:14px 0;color:#555;">Are you sure you want to delete this announcement? This action cannot be undone.</p>
    <input type="hidden" id="deleteTargetId" value="">
    <div style="display:flex;gap:10px;margin-top:16px;">
      <button class="save-btn" style="background:#e74c3c;" id="confirmDeleteBtn">
        <i class="fas fa-trash"></i> Delete
      </button>
      <button class="cancel-btn" onclick="closeDeleteModal()">Cancel</button>
    </div>
  </div>
</div>

<!-- READ RECEIPTS MODAL -->
<div id="receiptsModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:480px;">
    <h3><i class="fas fa-eye"></i> Read Receipts</h3>
    <p id="receiptsHeading" style="color:#555;margin:10px 0 14px;font-size:14px;"></p>
    <div id="receiptsProgress" style="background:#e9f0ec;border-radius:8px;height:10px;margin-bottom:14px;overflow:hidden;">
      <div id="receiptsBar" style="height:100%;background:linear-gradient(90deg,#10b981,#059669);width:0%;transition:width .5s;border-radius:8px;"></div>
    </div>
    <div id="receiptsList" style="max-height:240px;overflow-y:auto;font-size:13px;"></div>
    <div style="margin-top:16px;">
      <button class="cancel-btn" onclick="closeReceiptsModal()">Close</button>
    </div>
  </div>
</div>

<style>
  /* ── Announcement list items (admin) ── */
  #announcementList {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .announcement-item {
    background: #fff;
    border: 1px solid #e8f0ec;
    border-radius: 10px;
    padding: 14px 16px;
    transition: box-shadow 0.2s;
  }
  .announcement-item:hover {
    box-shadow: 0 3px 12px rgba(0,0,0,0.07);
  }

  .ann-pinned-admin {
    border-left: 4px solid #f59e0b;
    background: #fffdf5;
  }

  .ann-pin-label {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.72rem; font-weight: 700; color: #b45309;
    background: #fef3c7; padding: 2px 10px; border-radius: 20px;
    margin-bottom: 8px;
  }

  .ann-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 8px;
  }

  .ann-item-title {
    margin: 0; font-size: 0.97rem; color: #1e3a3a; font-weight: 600;
    word-break: break-word; overflow-wrap: anywhere;
    min-width: 0; flex: 1;
    display: flex; flex-wrap: wrap; align-items: center; gap: 5px;
    line-height: 1.5;
  }

  .ann-item-actions {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
    align-items: center;
  }

  .ann-edit-btn, .ann-delete-btn, .ann-receipt-btn {
    border: none; border-radius: 6px; padding: 5px 10px;
    cursor: pointer; font-size: 13px; font-weight: 600;
    transition: opacity .2s, transform .1s;
    display: inline-flex; align-items: center; gap: 4px;
  }
  .ann-edit-btn    { background: #e3f2eb; color: #2d6a4f; }
  .ann-delete-btn  { background: #fde8e8; color: #c0392b; }
  .ann-receipt-btn { background: #e0f2fe; color: #0369a1; }
  .ann-edit-btn:hover, .ann-delete-btn:hover, .ann-receipt-btn:hover {
    opacity: .8;
    transform: translateY(-1px);
  }

  .ann-priority-badge {
    display: inline-flex; align-items: center;
    font-size: 0.68rem; font-weight: 700;
    padding: 2px 9px; border-radius: 20px;
    text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap;
    flex-shrink: 0;
  }

  .announcement-item > p {
    margin: 0 0 8px;
    color: #4a5568;
    font-size: 0.9rem;
    line-height: 1.6;
  }

  .ann-item-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 6px;
    padding-top: 8px;
    border-top: 1px solid #f0f0f0;
    align-items: center;
  }
  .ann-item-meta small {
    color: #888;
    font-size: 0.78rem;
    display: inline-flex;
    align-items: center;
    gap: 4px;
  }
  .ann-audience-tag { color: #1d4ed8 !important; font-weight: 600; }
  .ann-expires-tag  { color: #b91c1c !important; font-weight: 600; }
</style>

<?php include 'footer.php'; ?>

<script>
// ── Priority badge helper ──
const PRIORITY_STYLES = {
  urgent: { label: 'Urgent', color: '#c0392b', bg: '#fde8e8' },
  high:   { label: 'High',   color: '#b7770d', bg: '#fff3cd' },
  low:    { label: 'Low',    color: '#555',    bg: '#f0f0f0' },
};
const CATEGORY_STYLES = {
  event:    { label: 'Event',    color: '#1d4ed8', bg: '#dbeafe' },
  deadline: { label: 'Deadline', color: '#b91c1c', bg: '#fee2e2' },
  policy:   { label: 'Policy',   color: '#6d28d9', bg: '#ede9fe' },
  general:  { label: 'General',  color: '#065f46', bg: '#d1fae5' },
};
function buildBadge(priority, category) {
  const p = PRIORITY_STYLES[priority] || PRIORITY_STYLES.low;
  const c = CATEGORY_STYLES[category] || CATEGORY_STYLES.general;
  return `<span class="ann-priority-badge" style="background:${c.bg};color:${c.color};">${c.label}</span>`
       + `<span class="ann-priority-badge" style="background:${p.bg};color:${p.color};">${p.label}</span>`;
}

// ── Open Add Modal ──
function openAddModal() {
  document.getElementById('modalHeading').innerText    = 'Add Announcement';
  document.getElementById('editingId').value           = '';
  document.getElementById('annTitleInput').value       = '';
  document.getElementById('annContentInput').value     = '';
  document.getElementById('annPriorityInput').value    = 'low';
  document.getElementById('annCategoryInput').value    = 'general';
  document.getElementById('annAudienceInput').value    = '';
  document.getElementById('annExpiresInput').value     = '';
  document.getElementById('annPinnedInput').checked    = false;
  document.getElementById('saveSuccess').style.display = 'none';
  document.getElementById('saveError').style.display   = 'none';
  document.getElementById('announcementModal').style.display = 'flex';
}

// ── Open Edit Modal ──
function openEditModal(id, title, content, priority, category, audience, expires, pinned) {
  document.getElementById('modalHeading').innerText    = 'Edit Announcement';
  document.getElementById('editingId').value           = id;
  document.getElementById('annTitleInput').value       = title;
  document.getElementById('annContentInput').value     = content;
  document.getElementById('annPriorityInput').value    = priority  || 'low';
  document.getElementById('annCategoryInput').value    = category  || 'general';
  document.getElementById('annAudienceInput').value    = audience  || '';
  document.getElementById('annExpiresInput').value     = expires   || '';
  document.getElementById('annPinnedInput').checked    = pinned    || false;
  document.getElementById('saveSuccess').style.display = 'none';
  document.getElementById('saveError').style.display   = 'none';
  document.getElementById('announcementModal').style.display = 'flex';
}

function closeAnnouncementModal() {
  document.getElementById('announcementModal').style.display = 'none';
}

// ── Save (Add or Edit) ──
document.getElementById('announcementForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const btn       = document.getElementById('saveAnnouncementBtn');
  const title     = document.getElementById('annTitleInput').value.trim();
  const content   = document.getElementById('annContentInput').value.trim();
  const priority  = document.getElementById('annPriorityInput').value;
  const category  = document.getElementById('annCategoryInput').value;
  const audience  = document.getElementById('annAudienceInput').value.trim();
  const expires   = document.getElementById('annExpiresInput').value;
  const pinned    = document.getElementById('annPinnedInput').checked ? '1' : '0';
  const editingId = document.getElementById('editingId').value;

  if (!title || !content) return;

  btn.disabled  = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  const formData = new FormData();
  formData.append('title',      title);
  formData.append('content',    content);
  formData.append('priority',   priority);
  formData.append('category',   category);
  formData.append('audience',   audience);
  formData.append('expires_at', expires);
  formData.append('is_pinned',  pinned);
  if (editingId) formData.append('announcement_id', editingId);

  fetch('../api/save_announcement.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        document.getElementById('saveSuccess').style.display = 'block';
        document.getElementById('saveSuccessMsg').innerText  = editingId ? 'Updated successfully!' : 'Added successfully!';
        document.getElementById('saveError').style.display   = 'none';

        const isPinned = data.is_pinned == 1;

        if (editingId) {
          const item = document.getElementById('ann-' + editingId);
          if (item) {
            item.querySelector('.ann-item-title').innerHTML =
              buildBadge(data.priority, data.category) + data.title;
            item.querySelector('p').innerText = data.content;
            item.classList.toggle('ann-pinned-admin', isPinned);
          }
        } else {
          const list  = document.getElementById('announcementList');
          const empty = list.querySelector('p');
          if (empty) empty.remove();
          const newItem = document.createElement('div');
          newItem.className = 'announcement-item' + (isPinned ? ' ann-pinned-admin' : '');
          newItem.id        = 'ann-' + data.id;
          const pinLabel    = isPinned ? '<div class="ann-pin-label"><i class="fas fa-thumbtack"></i> Pinned</div>' : '';
          const expLabel    = data.expires_at ? `<small class="ann-expires-tag"><i class="fas fa-hourglass-half"></i> Expires ${data.expires_at}</small>` : '';
          const audLabel    = data.audience   ? `<small class="ann-audience-tag"><i class="fas fa-users"></i> ${data.audience}</small>` : `<small style="color:#aaa;"><i class="fas fa-globe"></i> All organizations</small>`;
          newItem.innerHTML = `
            ${pinLabel}
            <div class="ann-item-header">
              <h4 class="ann-item-title">${buildBadge(data.priority, data.category)}${data.title}</h4>
              <div class="ann-item-actions">
                <button class="ann-receipt-btn" title="Read receipts"
                  onclick="showReceipts(${data.id}, ${JSON.stringify(data.title)})">
                  <i class="fas fa-eye"></i> <span>0</span>
                </button>
                <button class="ann-edit-btn" title="Edit"
                  onclick="openEditModal(${data.id}, ${JSON.stringify(data.title)}, ${JSON.stringify(data.content)}, ${JSON.stringify(data.priority)}, ${JSON.stringify(data.category)}, ${JSON.stringify(data.audience)}, ${JSON.stringify(data.expires_at)}, ${isPinned})">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="ann-delete-btn" title="Delete"
                  onclick="deleteAnnouncement(${data.id})">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
            <p>${data.content}</p>
            <div class="ann-item-meta">
              <small><i class="far fa-calendar-alt"></i> ${data.created_at}</small>
              ${audLabel}${expLabel}
            </div>`;
          list.prepend(newItem);
        }
        setTimeout(closeAnnouncementModal, 1000);
      } else {
        document.getElementById('saveError').style.display  = 'block';
        document.getElementById('saveErrorMsg').innerText   = data.message || 'Failed to save.';
        document.getElementById('saveSuccess').style.display = 'none';
      }
    })
    .catch(() => {
      document.getElementById('saveError').style.display  = 'block';
      document.getElementById('saveErrorMsg').innerText   = 'Network error. Please try again.';
    })
    .finally(() => {
      btn.disabled  = false;
      btn.innerHTML = '<i class="fas fa-save"></i> Save';
    });
});

// ── Read Receipts ──
function showReceipts(id, title) {
  document.getElementById('receiptsHeading').textContent = `"${title}"`;
  document.getElementById('receiptsBar').style.width    = '0%';
  document.getElementById('receiptsList').innerHTML     = '<i class="fas fa-spinner fa-spin"></i> Loading...';
  document.getElementById('receiptsModal').style.display = 'flex';

  fetch(`/api/get_read_receipts.php?announcement_id=${id}`, { credentials: 'same-origin' })
    .then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    })
    .then(data => {
      if (!data.success) { document.getElementById('receiptsList').innerHTML = 'Failed to load.'; return; }
      const pct = data.total > 0 ? Math.round((data.read / data.total) * 100) : 0;
      document.getElementById('receiptsHeading').textContent =
        `"${title}" — ${data.read} of ${data.total} organizations read (${pct}%)`;
      document.getElementById('receiptsBar').style.width = pct + '%';
      if (data.readers.length === 0) {
        document.getElementById('receiptsList').innerHTML = '<p style="color:#888;padding:10px 0;">No reads recorded yet.</p>';
      } else {
        document.getElementById('receiptsList').innerHTML = data.readers.map(r =>
          `<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f0f0f0;">
            <span><strong>${r.org_name}</strong> <span style="color:#aaa;font-size:11px;">${r.org_code}</span></span>
            <span style="color:#888;font-size:11px;">${r.read_at.split(' ')[0]}</span>
          </div>`
        ).join('');
      }
    })
    .catch(err => {
      document.getElementById('receiptsList').innerHTML =
        `<p style="color:#c0392b;">Failed to load receipts (${err.message}). Check that <code>api/get_read_receipts.php</code> exists.</p>`;
    });
}
function closeReceiptsModal() {
  document.getElementById('receiptsModal').style.display = 'none';
}

// ── Delete ──
function deleteAnnouncement(id) {
  document.getElementById('deleteTargetId').value = id;
  document.getElementById('deleteModal').style.display = 'flex';
}

function closeDeleteModal() {
  document.getElementById('deleteModal').style.display = 'none';
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
  const id  = document.getElementById('deleteTargetId').value;
  const btn = this;
  btn.disabled  = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

  const formData = new FormData();
  formData.append('announcement_id', id);
  formData.append('action', 'delete');

  fetch('../api/save_announcement.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const item = document.getElementById('ann-' + id);
        if (item) item.remove();
        const list = document.getElementById('announcementList');
        if (!list.querySelector('.announcement-item')) {
          list.innerHTML = '<p style="color:#888;text-align:center;padding:20px 0;">No announcements yet.</p>';
        }
        closeDeleteModal();
      } else {
        alert(data.message || 'Failed to delete.');
      }
    })
    .catch(() => alert('Network error. Please try again.'))
    .finally(() => {
      btn.disabled  = false;
      btn.innerHTML = '<i class="fas fa-trash"></i> Delete';
    });
});

// Close modals on backdrop click
window.addEventListener('click', function(e) {
  if (e.target === document.getElementById('announcementModal')) closeAnnouncementModal();
  if (e.target === document.getElementById('deleteModal'))       closeDeleteModal();
  if (e.target === document.getElementById('receiptsModal'))     closeReceiptsModal();
});
</script>

<script src="../js/navbar.js"></script>

<!-- VMV EDIT MODAL -->
<div id="vmvModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:540px;">
    <h3><i class="fas fa-landmark"></i> Edit Vision, Mission &amp; Values</h3>
    <div id="vmvSuccess" style="display:none;background:#d4edda;color:#155724;padding:10px 15px;border-radius:6px;margin-bottom:15px;">
      <i class="fas fa-check-circle"></i> Saved successfully!
    </div>
    <div id="vmvError" style="display:none;background:#f8d7da;color:#721c24;padding:10px 15px;border-radius:6px;margin-bottom:15px;">
      <i class="fas fa-exclamation-circle"></i> <span id="vmvErrorMsg">Failed to save.</span>
    </div>
    <label style="display:block;margin-bottom:6px;font-weight:600;">Mission</label>
    <textarea id="vmvMission" rows="3"
      style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;resize:vertical;box-sizing:border-box;margin-bottom:14px;"><?php echo htmlspecialchars($mission_text); ?></textarea>
    <label style="display:block;margin-bottom:6px;font-weight:600;">Vision</label>
    <textarea id="vmvVision" rows="3"
      style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;resize:vertical;box-sizing:border-box;margin-bottom:14px;"><?php echo htmlspecialchars($vision_text); ?></textarea>
    <label style="display:block;margin-bottom:6px;font-weight:600;">Values
      <span style="font-weight:400;font-size:12px;color:#888;">(one value per line, format: LABEL - description)</span>
    </label>
    <textarea id="vmvValues" rows="5"
      style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;resize:vertical;box-sizing:border-box;margin-bottom:14px;"><?php echo htmlspecialchars($values_text); ?></textarea>
    <div style="display:flex;gap:10px;">
      <button class="save-btn" id="vmvSaveBtn" onclick="saveVMV()"><i class="fas fa-save"></i> Save</button>
      <button class="cancel-btn" onclick="closeVMVModal()">Cancel</button>
    </div>
  </div>
</div>

<!-- OFFICIALS EDIT MODAL -->
<div id="officialsModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width:540px;">
    <h3><i class="fas fa-user-tie"></i> Edit University Officials</h3>
    <div id="offSuccess" style="display:none;background:#d4edda;color:#155724;padding:10px 15px;border-radius:6px;margin-bottom:15px;">
      <i class="fas fa-check-circle"></i> Saved successfully!
    </div>
    <div id="offError" style="display:none;background:#f8d7da;color:#721c24;padding:10px 15px;border-radius:6px;margin-bottom:15px;">
      <i class="fas fa-exclamation-circle"></i> <span id="offErrorMsg">Failed to save.</span>
    </div>
    <label style="display:block;margin-bottom:6px;font-weight:600;"><i class="fas fa-university" style="color:#059669;"></i> Interim University President — Name</label>
    <input type="text" id="offPresidentName"
      value="<?php echo htmlspecialchars($president_name); ?>"
      style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;margin-bottom:14px;">
    <label style="display:block;margin-bottom:6px;font-weight:600;">Title / Position Label</label>
    <input type="text" id="offPresidentTitle"
      value="<?php echo htmlspecialchars($president_title); ?>"
      style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;margin-bottom:18px;">
    <label style="display:block;margin-bottom:6px;font-weight:600;"><i class="fas fa-user-graduate" style="color:#059669;"></i> Dean, OSAS — Name</label>
    <input type="text" id="offDeanName"
      value="<?php echo htmlspecialchars($dean_name); ?>"
      style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;margin-bottom:14px;">
    <label style="display:block;margin-bottom:6px;font-weight:600;">Title / Position Label</label>
    <input type="text" id="offDeanTitle"
      value="<?php echo htmlspecialchars($dean_title); ?>"
      style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;box-sizing:border-box;margin-bottom:18px;">
    <div style="display:flex;gap:10px;">
      <button class="save-btn" id="offSaveBtn" onclick="saveOfficials()"><i class="fas fa-save"></i> Save</button>
      <button class="cancel-btn" onclick="closeOfficialsModal()">Cancel</button>
    </div>
  </div>
</div>

<script>
// ── VMV Modal ──
function openVMVModal()  { document.getElementById('vmvModal').style.display = 'flex'; document.getElementById('vmvSuccess').style.display='none'; document.getElementById('vmvError').style.display='none'; }
function closeVMVModal() { document.getElementById('vmvModal').style.display = 'none'; }

function saveVMV() {
  const btn  = document.getElementById('vmvSaveBtn');
  const data = new FormData();
  data.append('mission', document.getElementById('vmvMission').value.trim());
  data.append('vision',  document.getElementById('vmvVision').value.trim());
  data.append('values',  document.getElementById('vmvValues').value.trim());
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  fetch('../api/save_settings.php', { method:'POST', body: data })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        document.getElementById('vmvSuccess').style.display = 'block';
        document.getElementById('vmvError').style.display   = 'none';
        // Update displayed text
        document.getElementById('missionText').textContent = document.getElementById('vmvMission').value.trim();
        document.getElementById('visionText').textContent  = document.getElementById('vmvVision').value.trim();
        // Rebuild values list
        const lines = document.getElementById('vmvValues').value.trim().split('\n').filter(l => l.trim());
        let html = '<ul style="list-style:none;padding:0;text-align:left;margin:0;">';
        lines.forEach(line => {
          line = line.trim();
          if (!line) return;
          if (line.includes(' - ')) {
            const [key, ...rest] = line.split(' - ');
            html += `<li style="padding:6px 0;"><i class="fas fa-check-circle" style="margin-right:8px;color:#ff9500;"></i><strong>${key.trim()}</strong> - ${rest.join(' - ').trim()}</li>`;
          } else {
            html += `<li style="padding:6px 0;"><i class="fas fa-check-circle" style="margin-right:8px;color:#ff9500;"></i>${line}</li>`;
          }
        });
        html += '</ul>';
        document.getElementById('valuesDisplay').innerHTML = html;
        setTimeout(closeVMVModal, 900);
      } else {
        document.getElementById('vmvError').style.display = 'block';
        document.getElementById('vmvErrorMsg').textContent = res.message || 'Save failed.';
      }
    })
    .catch(() => { document.getElementById('vmvError').style.display='block'; document.getElementById('vmvErrorMsg').textContent='Network error.'; })
    .finally(() => { btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Save'; });
}

// ── Officials Modal ──
function openOfficialsModal()  { document.getElementById('officialsModal').style.display = 'flex'; document.getElementById('offSuccess').style.display='none'; document.getElementById('offError').style.display='none'; }
function closeOfficialsModal() { document.getElementById('officialsModal').style.display = 'none'; }

function saveOfficials() {
  const btn  = document.getElementById('offSaveBtn');
  const data = new FormData();
  data.append('president_name',  document.getElementById('offPresidentName').value.trim());
  data.append('president_title', document.getElementById('offPresidentTitle').value.trim());
  data.append('dean_name',       document.getElementById('offDeanName').value.trim());
  data.append('dean_title',      document.getElementById('offDeanTitle').value.trim());
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
  fetch('../api/save_settings.php', { method:'POST', body: data })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        document.getElementById('offSuccess').style.display = 'block';
        document.getElementById('offError').style.display   = 'none';
        document.getElementById('presidentName').textContent  = document.getElementById('offPresidentName').value.trim();
        document.getElementById('presidentTitle').textContent = document.getElementById('offPresidentTitle').value.trim();
        document.getElementById('deanName').textContent       = document.getElementById('offDeanName').value.trim();
        document.getElementById('deanTitle').textContent      = document.getElementById('offDeanTitle').value.trim();
        setTimeout(closeOfficialsModal, 900);
      } else {
        document.getElementById('offError').style.display = 'block';
        document.getElementById('offErrorMsg').textContent = res.message || 'Save failed.';
      }
    })
    .catch(() => { document.getElementById('offError').style.display='block'; document.getElementById('offErrorMsg').textContent='Network error.'; })
    .finally(() => { btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Save'; });
}

// Close modals on backdrop click
window.addEventListener('click', function(e) {
  if (e.target === document.getElementById('vmvModal'))       closeVMVModal();
  if (e.target === document.getElementById('officialsModal')) closeOfficialsModal();
});
</script>
</body>
</html>