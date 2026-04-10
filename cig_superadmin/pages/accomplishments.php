<?php
session_start();
require_once '../db/config.php';
if (!isset($_SESSION['admin_logged_in'])) { header("Location: login.php"); exit(); }

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Get all years
$years_res = mysqli_query($conn, "SELECT * FROM academic_years ORDER BY start_date DESC");
$years = [];
$current_year = null;
while ($y = mysqli_fetch_assoc($years_res)) {
    $years[] = $y;
    if ($y['is_current']) $current_year = $y;
}
if (!$current_year && count($years)) $current_year = $years[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Accomplishments — CIG Admin</title>
<link rel="stylesheet" href="../css/style.css">
<link rel="stylesheet" href="../css/navbar.css">
<link rel="stylesheet" href="../css/components.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ── Page Layout ─────────────────────────────────────────────── */
.acc-toolbar {
  display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
  margin-bottom: 28px;
}
.acc-toolbar select {
  padding: 10px 16px; border-radius: 8px; border: 1.5px solid #d1fae5;
  font-size: 0.95em; font-family: inherit; background: white;
  color: #1a202c; cursor: pointer; min-width: 160px;
}
.acc-toolbar select:focus { outline: none; border-color: #10b981; }

.btn-primary {
  padding: 10px 18px; background: linear-gradient(135deg,#10b981,#059669);
  color: white; border: none; border-radius: 8px; font-size: 0.88em;
  font-weight: 700; font-family: inherit; cursor: pointer;
  display: inline-flex; align-items: center; gap: 7px; transition: opacity .2s;
}
.btn-primary:hover { opacity: .85; }
.btn-secondary {
  padding: 10px 18px; background: white; color: #2d3748;
  border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.88em;
  font-weight: 600; font-family: inherit; cursor: pointer;
  display: inline-flex; align-items: center; gap: 7px; transition: all .2s;
}
.btn-secondary:hover { border-color: #10b981; color: #10b981; }
.btn-danger {
  padding: 8px 14px; background: #fee2e2; color: #b91c1c;
  border: none; border-radius: 8px; font-size: 0.82em; font-weight: 700;
  font-family: inherit; cursor: pointer; transition: opacity .2s;
}
.btn-danger:hover { opacity: .8; }

/* ── Summary Cards ───────────────────────────────────────────── */
.summary-grid {
  display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr));
  gap: 18px; margin-bottom: 28px;
}
.summary-card {
  background: white; border-radius: 12px; padding: 20px 22px;
  border: 1.5px solid #e2e8f0; box-shadow: 0 4px 14px rgba(0,0,0,.06);
  display: flex; align-items: center; gap: 16px;
}
.summary-icon {
  width: 48px; height: 48px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; font-size: 1.3em;
  flex-shrink: 0;
}
.summary-icon.green  { background: #d1fae5; color: #10b981; }
.summary-icon.blue   { background: #dbeafe; color: #3b82f6; }
.summary-icon.purple { background: #ede9fe; color: #8b5cf6; }
.summary-icon.amber  { background: #fef3c7; color: #d97706; }
.summary-val  { font-size: 1.6em; font-weight: 800; color: #1a202c; line-height:1; }
.summary-lbl  { font-size: 0.78em; color: #718096; font-weight: 600; margin-top: 3px; }

/* ── Org Table ───────────────────────────────────────────────── */
.table-card {
  background: white; border-radius: 14px; overflow: hidden;
  border: 1.5px solid #e2e8f0; box-shadow: 0 4px 16px rgba(0,0,0,.06);
  margin-bottom: 28px;
}
.table-card-header {
  padding: 18px 22px; border-bottom: 2px solid #f0f0f0;
  display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;
}
.table-card-header h3 { margin:0; font-size:1em; font-weight:800; color:#1a202c; display:flex; align-items:center; gap:8px; }
.table-card-header h3 i { color:#10b981; }
table { width:100%; border-collapse:collapse; }
thead tr { background: linear-gradient(90deg,#f0fdf4,#f7fffe); }
th { padding:12px 16px; font-size:0.8em; font-weight:700; color:#065f46; text-transform:uppercase; letter-spacing:.4px; text-align:left; white-space:nowrap; }
td { padding:13px 16px; border-bottom:1px solid #f7f7f7; font-size:0.9em; color:#2d3748; }
tr:last-child td { border-bottom:none; }
tr:hover td { background:#fafffe; }

/* ── Progress Bar ─────────────────────────────────────────────── */
.progress-wrap { display:flex; align-items:center; gap:10px; }
.progress-bar  { flex:1; height:8px; background:#e2e8f0; border-radius:99px; overflow:hidden; min-width:80px; }
.progress-fill { height:100%; border-radius:99px; transition:width .5s; }
.progress-pct  { font-size:0.82em; font-weight:700; color:#2d3748; min-width:36px; text-align:right; }

/* ── Rank Badge ──────────────────────────────────────────────── */
.rank-badge {
  display:inline-flex; align-items:center; justify-content:center;
  width:28px; height:28px; border-radius:50%; font-size:0.82em; font-weight:800;
}
.rank-1 { background:#fef3c7; color:#d97706; }
.rank-2 { background:#f1f5f9; color:#64748b; }
.rank-3 { background:#fde8e8; color:#c0392b; }
.rank-n { background:#f0f0f0; color:#9ca3af; }

/* ── Accreditation Modal ──────────────────────────────────────── */
.modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
.modal-box { background:white; border-radius:16px; width:90%; max-width:620px; max-height:85vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.25); }
.modal-head { background:linear-gradient(135deg,#047857,#10b981); color:white; padding:20px 24px; display:flex; justify-content:space-between; align-items:center; flex-shrink:0; }
.modal-head h3 { margin:0; font-size:1.05em; font-weight:700; }
.modal-close { background:rgba(255,255,255,.2); border:none; color:white; width:30px; height:30px; border-radius:50%; cursor:pointer; font-size:1em; }
.modal-body { padding:22px; overflow-y:auto; flex:1; }
.modal-foot { padding:16px 22px; border-top:1px solid #f0f0f0; display:flex; justify-content:flex-end; gap:10px; flex-shrink:0; }

.checklist-item {
  display:flex; align-items:flex-start; gap:12px;
  padding:12px 14px; border-radius:8px; margin-bottom:8px;
  border:1.5px solid #e2e8f0; transition:border-color .2s;
}
.checklist-item.done { background:#f0fdf4; border-color:#a7f3d0; }
.checklist-item input[type=checkbox] { width:18px; height:18px; accent-color:#10b981; cursor:pointer; margin-top:2px; flex-shrink:0; }
.checklist-title { font-weight:700; font-size:0.92em; color:#1a202c; }
.checklist-desc  { font-size:0.8em; color:#718096; margin-top:2px; }
.checklist-pts   { margin-left:auto; font-size:0.8em; font-weight:700; color:#10b981; white-space:nowrap; flex-shrink:0; }

/* ── Year Management Panel ───────────────────────────────────── */
.year-panel { background:white; border-radius:14px; padding:22px; border:1.5px solid #e2e8f0; box-shadow:0 4px 16px rgba(0,0,0,.06); margin-bottom:28px; }
.year-panel h3 { margin:0 0 16px; font-size:1em; font-weight:800; color:#1a202c; display:flex; align-items:center; gap:8px; }
.year-panel h3 i { color:#10b981; }
.year-list { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
.year-item { display:flex; align-items:center; gap:12px; padding:10px 14px; border-radius:8px; border:1.5px solid #e2e8f0; }
.year-item.current { border-color:#10b981; background:#f0fdf4; }
.year-label { font-weight:700; font-size:0.92em; color:#1a202c; flex:1; }
.year-badge { font-size:0.72em; font-weight:700; padding:2px 10px; border-radius:20px; background:#d1fae5; color:#065f46; }
.add-year-form { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; padding-top:14px; border-top:1px solid #f0f0f0; }
.add-year-form input { padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:0.88em; font-family:inherit; }
.add-year-form input:focus { outline:none; border-color:#10b981; }

/* ── Requirements Panel ──────────────────────────────────────── */
.req-panel { background:white; border-radius:14px; padding:22px; border:1.5px solid #e2e8f0; box-shadow:0 4px 16px rgba(0,0,0,.06); margin-bottom:28px; }
.req-panel h3 { margin:0 0 16px; font-size:1em; font-weight:800; color:#1a202c; display:flex; align-items:center; gap:8px; }
.req-panel h3 i { color:#10b981; }
.req-list { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
.req-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:8px; border:1.5px solid #e2e8f0; }
.req-item-title { flex:1; font-size:0.9em; font-weight:600; color:#1a202c; }
.req-item-pts   { font-size:0.82em; font-weight:700; color:#10b981; }
.req-item-req   { font-size:0.75em; padding:2px 8px; border-radius:20px; }
.req-item-req.required { background:#fee2e2; color:#b91c1c; }
.req-item-req.optional { background:#f0f0f0; color:#718096; }
.add-req-form { display:flex; flex-direction:column; gap:10px; padding-top:14px; border-top:1px solid #f0f0f0; }
.add-req-row { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
.add-req-form input, .add-req-form select, .add-req-form textarea {
  padding:9px 12px; border:1.5px solid #e2e8f0; border-radius:8px;
  font-size:0.88em; font-family:inherit;
}
.add-req-form input:focus, .add-req-form select:focus, .add-req-form textarea:focus { outline:none; border-color:#10b981; }

.alert-success { background:#d1fae5; color:#065f46; padding:10px 16px; border-radius:8px; font-size:0.88em; font-weight:600; display:none; margin-bottom:14px; }
</style>
</head>
<body>
<?php $current_page = 'accomplishments'; ?>
<?php include 'navbar.php'; ?>

<div id="page-content" class="page-background">
  <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;margin-bottom:24px;">
    <h2 style="margin:0;"><i class="fas fa-trophy" style="color:#10b981;"></i> Org Accomplishments</h2>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <button class="btn-secondary" onclick="openTransferModal()"><i class="fas fa-exchange-alt"></i> Year Transfer</button>
      <button class="btn-primary" onclick="document.getElementById('setupPanel').style.display=document.getElementById('setupPanel').style.display==='none'?'block':'none'"><i class="fas fa-cog"></i> Setup</button>
    </div>
  </div>

  <!-- YEAR SELECTOR -->
  <div class="acc-toolbar">
    <label style="font-weight:700;color:#2d3748;font-size:0.9em;">Academic Year:</label>
    <select id="yearSelect" onchange="loadYear()">
      <?php foreach ($years as $y): ?>
        <option value="<?= $y['year_id'] ?>" <?= $y['is_current'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($y['label']) ?><?= $y['is_current'] ? ' (Current)' : '' ?>
        </option>
      <?php endforeach; ?>
      <?php if (!$years): ?>
        <option value="">No years set up</option>
      <?php endif; ?>
    </select>
    <button class="btn-primary" onclick="loadYear()"><i class="fas fa-sync-alt"></i> Refresh</button>
  </div>

  <!-- SUMMARY CARDS -->
  <div class="summary-grid" id="summaryCards">
    <div class="summary-card"><div class="summary-icon green"><i class="fas fa-building"></i></div><div><div class="summary-val" id="sumOrgs">—</div><div class="summary-lbl">Organizations</div></div></div>
    <div class="summary-card"><div class="summary-icon blue"><i class="fas fa-star"></i></div><div><div class="summary-val" id="sumTopPts">—</div><div class="summary-lbl">Highest Points</div></div></div>
    <div class="summary-card"><div class="summary-icon purple"><i class="fas fa-check-double"></i></div><div><div class="summary-val" id="sumAvgAcc">—</div><div class="summary-lbl">Avg Accreditation</div></div></div>
    <div class="summary-card"><div class="summary-icon amber"><i class="fas fa-percentage"></i></div><div><div class="summary-val" id="sumAvgPct">—</div><div class="summary-lbl">Avg Accomplishment</div></div></div>
  </div>

  <!-- ORG TABLE -->
  <div class="table-card">
    <div class="table-card-header">
      <h3><i class="fas fa-list-ol"></i> Organization Rankings</h3>
      <input type="text" id="orgSearch" placeholder="Search org…" oninput="filterTable()"
        style="padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:0.88em;font-family:inherit;width:200px;">
    </div>
    <div style="overflow-x:auto;">
      <table>
        <thead><tr>
          <th>#</th>
          <th>Organization</th>
          <th>Points</th>
          <th>Accreditation</th>
          <th>% by Submissions</th>
          <th>% by Points</th>
          <th>Actions</th>
        </tr></thead>
        <tbody id="orgTableBody">
          <tr><td colspan="7" style="text-align:center;color:#aaa;padding:30px;">Select a year to load data</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- SETUP PANEL (hidden by default) -->
  <div id="setupPanel" style="display:none;">

    <!-- Year Management -->
    <div class="year-panel">
      <h3><i class="fas fa-calendar-alt"></i> Academic Years</h3>
      <div class="year-list" id="yearList">
        <?php foreach ($years as $y): ?>
          <div class="year-item <?= $y['is_current'] ? 'current' : '' ?>" id="yitem-<?= $y['year_id'] ?>">
            <span class="year-label"><?= htmlspecialchars($y['label']) ?> (<?= $y['start_date'] ?> → <?= $y['end_date'] ?>)</span>
            <?php if ($y['is_current']): ?><span class="year-badge">Current</span><?php endif; ?>
            <?php if (!$y['is_current']): ?>
              <button class="btn-primary" style="padding:6px 12px;font-size:0.78em;" onclick="setCurrentYear(<?= $y['year_id'] ?>, '<?= htmlspecialchars($y['label']) ?>')">Set Current</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="add-year-form">
        <div>
          <div style="font-size:0.78em;font-weight:700;color:#718096;margin-bottom:4px;">Label</div>
          <input type="text" id="newYearLabel" placeholder="e.g. 2025-2026" style="width:130px;">
        </div>
        <div>
          <div style="font-size:0.78em;font-weight:700;color:#718096;margin-bottom:4px;">Start Date</div>
          <input type="date" id="newYearStart">
        </div>
        <div>
          <div style="font-size:0.78em;font-weight:700;color:#718096;margin-bottom:4px;">End Date</div>
          <input type="date" id="newYearEnd">
        </div>
        <button class="btn-primary" onclick="addYear()"><i class="fas fa-plus"></i> Add Year</button>
      </div>
    </div>

    <!-- Accreditation Requirements -->
    <div class="req-panel">
      <h3><i class="fas fa-tasks"></i> Accreditation Requirements
        <span style="font-size:0.78em;font-weight:500;color:#718096;margin-left:6px;">for selected year</span>
      </h3>
      <div id="reqAlert" class="alert-success"></div>
      <div class="req-list" id="reqList">
        <p style="color:#aaa;font-size:0.88em;">Select a year above to manage requirements.</p>
      </div>
      <div class="add-req-form">
        <div class="add-req-row">
          <div style="flex:2;min-width:160px;">
            <div style="font-size:0.78em;font-weight:700;color:#718096;margin-bottom:4px;">Requirement Title *</div>
            <input type="text" id="reqTitle" placeholder="e.g. Submit Constitution" style="width:100%;">
          </div>
          <div style="width:90px;">
            <div style="font-size:0.78em;font-weight:700;color:#718096;margin-bottom:4px;">Points</div>
            <input type="number" id="reqPoints" value="10" min="1" style="width:100%;">
          </div>
          <div style="width:110px;">
            <div style="font-size:0.78em;font-weight:700;color:#718096;margin-bottom:4px;">Type</div>
            <select id="reqRequired" style="width:100%;">
              <option value="1">Required</option>
              <option value="0">Optional</option>
            </select>
          </div>
        </div>
        <div>
          <div style="font-size:0.78em;font-weight:700;color:#718096;margin-bottom:4px;">Description (optional)</div>
          <input type="text" id="reqDesc" placeholder="Brief description…" style="width:100%;max-width:500px;">
        </div>
        <div><button class="btn-primary" onclick="addRequirement()"><i class="fas fa-plus"></i> Add Requirement</button></div>
      </div>
    </div>

  </div><!-- /setupPanel -->

  <!-- ACCREDITATION DETAIL MODAL -->
  <div class="modal" id="accModal">
    <div class="modal-box">
      <div class="modal-head">
        <h3 id="accModalTitle"><i class="fas fa-tasks"></i> Accreditation Checklist</h3>
        <button class="modal-close" onclick="closeAccModal()">✕</button>
      </div>
      <div class="modal-body" id="accModalBody">Loading…</div>
      <div class="modal-foot">
        <button class="btn-secondary" onclick="closeAccModal()">Close</button>
      </div>
    </div>
  </div>

  <!-- YEAR TRANSFER MODAL -->
  <div class="modal" id="transferModal">
    <div class="modal-box" style="max-width:480px;">
      <div class="modal-head">
        <h3><i class="fas fa-exchange-alt"></i> Year Transfer</h3>
        <button class="modal-close" onclick="document.getElementById('transferModal').style.display='none'">✕</button>
      </div>
      <div class="modal-body">
        <p style="color:#4a5568;font-size:0.9em;margin-bottom:18px;">Archive all submissions from one year and activate a new year. Org accounts are carried over automatically.</p>
        <div style="margin-bottom:14px;">
          <div style="font-size:0.82em;font-weight:700;color:#718096;margin-bottom:6px;">Archive From Year</div>
          <select id="transferFrom" style="width:100%;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;">
            <?php foreach ($years as $y): ?>
              <option value="<?= $y['year_id'] ?>"><?= htmlspecialchars($y['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="margin-bottom:18px;">
          <div style="font-size:0.82em;font-weight:700;color:#718096;margin-bottom:6px;">Activate New Year</div>
          <select id="transferTo" style="width:100%;padding:10px;border:1.5px solid #e2e8f0;border-radius:8px;font-family:inherit;">
            <?php foreach ($years as $y): ?>
              <option value="<?= $y['year_id'] ?>"><?= htmlspecialchars($y['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="transferAlert" style="display:none;background:#d1fae5;color:#065f46;padding:10px 14px;border-radius:8px;font-size:0.88em;margin-bottom:10px;"></div>
        <div style="background:#fef3c7;border-radius:8px;padding:12px 14px;font-size:0.85em;color:#92400e;">
          <i class="fas fa-exclamation-triangle"></i> This will archive all submissions from the selected year. This cannot be undone.
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn-secondary" onclick="document.getElementById('transferModal').style.display='none'">Cancel</button>
        <button class="btn-primary" id="transferBtn" onclick="doTransfer()"><i class="fas fa-exchange-alt"></i> Transfer</button>
      </div>
    </div>
  </div>

</div><!-- /page-content -->

<?php include 'footer.php'; ?>
<script src="../js/navbar.js"></script>
<script>
let allOrgs = [];
let currentYearId = <?= $current_year ? $current_year['year_id'] : 'null' ?>;
let currentAccOrg = null;

// ── Load year data ─────────────────────────────────────────────────────────
function loadYear() {
  const yid = document.getElementById('yearSelect').value;
  if (!yid) return;
  currentYearId = yid;

  fetch(`/api/accomplishments.php?action=org_accomplishments&year_id=${yid}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) return;
      allOrgs = data.orgs || [];
      renderTable(allOrgs);
      renderSummary(allOrgs);
    });

  loadRequirements(yid);
}

// ── Render summary cards ───────────────────────────────────────────────────
function renderSummary(orgs) {
  document.getElementById('sumOrgs').textContent = orgs.length;
  const topPts = orgs.length ? Math.max(...orgs.map(o => o.points_earned)) : 0;
  document.getElementById('sumTopPts').textContent = topPts;
  const avgAcc = orgs.length ? Math.round(orgs.reduce((a,o) => a + o.pct_accreditation, 0) / orgs.length) : 0;
  document.getElementById('sumAvgAcc').textContent = avgAcc + '%';
  const avgPct = orgs.length ? Math.round(orgs.reduce((a,o) => a + o.pct_submissions, 0) / orgs.length) : 0;
  document.getElementById('sumAvgPct').textContent = avgPct + '%';
}

// ── Render org table ───────────────────────────────────────────────────────
function renderTable(orgs) {
  const sorted = [...orgs].sort((a,b) => b.points_earned - a.points_earned);
  const tbody = document.getElementById('orgTableBody');
  if (!sorted.length) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#aaa;padding:30px;">No organizations found for this year</td></tr>';
    return;
  }
  tbody.innerHTML = sorted.map((o, i) => {
    const rank = i+1;
    const rankClass = rank===1?'rank-1':rank===2?'rank-2':rank===3?'rank-3':'rank-n';
    const pctSubColor = o.pct_submissions >= 80 ? '#10b981' : o.pct_submissions >= 50 ? '#f59e0b' : '#ef4444';
    const pctPtsColor = o.pct_points >= 80 ? '#10b981' : o.pct_points >= 50 ? '#f59e0b' : '#ef4444';
    const accColor    = o.pct_accreditation >= 80 ? '#10b981' : o.pct_accreditation >= 50 ? '#f59e0b' : '#ef4444';
    return `
      <tr data-org="${o.org_name.toLowerCase()} ${(o.org_code||'').toLowerCase()}">
        <td><span class="rank-badge ${rankClass}">${rank}</span></td>
        <td>
          <div style="font-weight:700;color:#1a202c;">${o.org_name}</div>
          <div style="font-size:0.78em;color:#718096;">${o.org_code || ''}</div>
        </td>
        <td>
          <span style="font-size:1.1em;font-weight:800;color:#10b981;">${o.points_earned}</span>
          <span style="font-size:0.75em;color:#718096;"> pts</span>
        </td>
        <td>
          <div class="progress-wrap">
            <div class="progress-bar"><div class="progress-fill" style="width:${o.pct_accreditation}%;background:${accColor};"></div></div>
            <span class="progress-pct" style="color:${accColor};">${o.pct_accreditation}%</span>
          </div>
          <div style="font-size:0.75em;color:#718096;margin-top:2px;">${o.accreditation_done}/${o.accreditation_total} items
            <button onclick="openAccModal(${o.user_id},'${o.org_name.replace(/'/g,"\\'")}',${currentYearId})"
              style="margin-left:6px;background:#f0fdf4;border:1px solid #a7f3d0;border-radius:5px;padding:2px 7px;font-size:0.9em;cursor:pointer;color:#065f46;">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </td>
        <td>
          <div class="progress-wrap">
            <div class="progress-bar"><div class="progress-fill" style="width:${o.pct_submissions}%;background:${pctSubColor};"></div></div>
            <span class="progress-pct" style="color:${pctSubColor};">${o.pct_submissions}%</span>
          </div>
          <div style="font-size:0.75em;color:#718096;margin-top:2px;">${o.approved_submissions} approved / ${o.total_submissions} total</div>
        </td>
        <td>
          <div class="progress-wrap">
            <div class="progress-bar"><div class="progress-fill" style="width:${o.pct_points}%;background:${pctPtsColor};"></div></div>
            <span class="progress-pct" style="color:${pctPtsColor};">${o.pct_points}%</span>
          </div>
        </td>
        <td>
          <button class="btn-secondary" style="padding:6px 10px;font-size:0.78em;" onclick="openAwardModal(${o.user_id},'${o.org_name.replace(/'/g,"\\'")}')">
            <i class="fas fa-plus"></i> Points
          </button>
        </td>
      </tr>`;
  }).join('');
}

// ── Filter table ───────────────────────────────────────────────────────────
function filterTable() {
  const q = document.getElementById('orgSearch').value.toLowerCase();
  document.querySelectorAll('#orgTableBody tr[data-org]').forEach(tr => {
    tr.style.display = tr.dataset.org.includes(q) ? '' : 'none';
  });
}

// ── Accreditation modal ────────────────────────────────────────────────────
function openAccModal(orgId, orgName, yid) {
  currentAccOrg = { orgId, yid };
  document.getElementById('accModalTitle').innerHTML = `<i class="fas fa-tasks"></i> ${orgName} — Checklist`;
  document.getElementById('accModalBody').innerHTML = '<p style="text-align:center;color:#aaa;"><i class="fas fa-spinner fa-spin"></i> Loading…</p>';
  document.getElementById('accModal').style.display = 'flex';

  fetch(`/api/accomplishments.php?action=org_accreditation_detail&org_id=${orgId}&year_id=${yid}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success || !data.checklist.length) {
        document.getElementById('accModalBody').innerHTML = '<p style="color:#aaa;text-align:center;">No requirements set for this year. Add them in Setup.</p>';
        return;
      }
      document.getElementById('accModalBody').innerHTML = data.checklist.map(c => `
        <div class="checklist-item ${c.is_done=='1'?'done':''}" id="ci-${c.req_id}">
          <input type="checkbox" ${c.is_done=='1'?'checked':''} onchange="toggleChecklist(${c.req_id}, this.checked)">
          <div style="flex:1;">
            <div class="checklist-title">${c.title} ${c.is_required=='1'?'<span style="font-size:0.72em;color:#b91c1c;">*Required</span>':''}</div>
            ${c.description ? `<div class="checklist-desc">${c.description}</div>` : ''}
          </div>
          <div class="checklist-pts">+${c.points} pts</div>
        </div>`).join('');
    });
}

function toggleChecklist(reqId, isDone) {
  const fd = new FormData();
  fd.append('action', 'toggle_accreditation');
  fd.append('org_id',  currentAccOrg.orgId);
  fd.append('req_id',  reqId);
  fd.append('year_id', currentAccOrg.yid);
  fd.append('is_done', isDone ? 1 : 0);
  fetch('../api/accomplishments.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(() => loadYear()); // refresh table
}

function closeAccModal() { document.getElementById('accModal').style.display = 'none'; }

// ── Award points modal (inline prompt for now) ─────────────────────────────
function openAwardModal(orgId, orgName) {
  const pts    = prompt(`Award manual points to ${orgName}?\nEnter points:`);
  if (!pts || isNaN(pts) || pts <= 0) return;
  const reason = prompt('Reason (optional):') || 'Manual award';
  const fd = new FormData();
  fd.append('action',  'award_points');
  fd.append('org_id',  orgId);
  fd.append('year_id', currentYearId);
  fd.append('points',  pts);
  fd.append('reason',  reason);
  fetch('../api/accomplishments.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(data => { if (data.success) loadYear(); });
}

// ── Year management ────────────────────────────────────────────────────────
function addYear() {
  const label = document.getElementById('newYearLabel').value.trim();
  const start = document.getElementById('newYearStart').value;
  const end   = document.getElementById('newYearEnd').value;
  if (!label || !start || !end) { alert('Fill in all year fields.'); return; }
  const fd = new FormData();
  fd.append('action','add_year'); fd.append('label',label);
  fd.append('start_date',start); fd.append('end_date',end);
  fetch('../api/accomplishments.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const list = document.getElementById('yearList');
        const item = document.createElement('div');
        item.className = 'year-item';
        item.id = 'yitem-' + data.year_id;
        item.innerHTML = `<span class="year-label">${label} (${start} → ${end})</span>
          <button class="btn-primary" style="padding:6px 12px;font-size:0.78em;" onclick="setCurrentYear(${data.year_id},'${label}')">Set Current</button>`;
        list.appendChild(item);
        // Add to select dropdowns
        [document.getElementById('yearSelect'), document.getElementById('transferFrom'), document.getElementById('transferTo')]
          .forEach(sel => { if(sel) { const opt = document.createElement('option'); opt.value=data.year_id; opt.textContent=label; sel.appendChild(opt); }});
        document.getElementById('newYearLabel').value = '';
        document.getElementById('newYearStart').value = '';
        document.getElementById('newYearEnd').value   = '';
      }
    });
}

function setCurrentYear(yid, label) {
  if (!confirm(`Set "${label}" as the current academic year?`)) return;
  const fd = new FormData();
  fd.append('action','set_current'); fd.append('year_id',yid);
  fetch('../api/accomplishments.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(() => location.reload());
}

// ── Requirements management ────────────────────────────────────────────────
function loadRequirements(yid) {
  fetch(`/api/accomplishments.php?action=get_requirements&year_id=${yid}`)
    .then(r => r.json())
    .then(data => {
      const list = document.getElementById('reqList');
      if (!data.requirements || !data.requirements.length) {
        list.innerHTML = '<p style="color:#aaa;font-size:0.88em;">No requirements yet for this year.</p>';
        return;
      }
      list.innerHTML = data.requirements.map(r => `
        <div class="req-item" id="req-${r.req_id}">
          <span class="req-item-title">${r.title}</span>
          <span class="req-item-pts">+${r.points} pts</span>
          <span class="req-item-req ${r.is_required=='1'?'required':'optional'}">${r.is_required=='1'?'Required':'Optional'}</span>
          <button class="btn-danger" onclick="deleteRequirement(${r.req_id})"><i class="fas fa-trash"></i></button>
        </div>`).join('');
    });
}

function addRequirement() {
  const yid   = document.getElementById('yearSelect').value;
  const title = document.getElementById('reqTitle').value.trim();
  const pts   = document.getElementById('reqPoints').value;
  const req   = document.getElementById('reqRequired').value;
  const desc  = document.getElementById('reqDesc').value.trim();
  if (!yid || !title) { alert('Select a year and enter a title.'); return; }
  const fd = new FormData();
  fd.append('action','add_requirement'); fd.append('year_id',yid);
  fd.append('title',title); fd.append('points',pts);
  fd.append('is_required',req); fd.append('description',desc);
  fetch('../api/accomplishments.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const al = document.getElementById('reqAlert');
        al.textContent = '✓ Requirement added!';
        al.style.display = 'block';
        setTimeout(() => al.style.display='none', 2000);
        loadRequirements(yid);
        document.getElementById('reqTitle').value = '';
        document.getElementById('reqDesc').value  = '';
        document.getElementById('reqPoints').value = '10';
      }
    });
}

function deleteRequirement(rid) {
  if (!confirm('Delete this requirement?')) return;
  const fd = new FormData();
  fd.append('action','delete_requirement'); fd.append('req_id',rid);
  fetch('../api/accomplishments.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(() => loadRequirements(document.getElementById('yearSelect').value));
}

// ── Year transfer ──────────────────────────────────────────────────────────
function openTransferModal() { document.getElementById('transferModal').style.display = 'flex'; }

function doTransfer() {
  const from = document.getElementById('transferFrom').value;
  const to   = document.getElementById('transferTo').value;
  if (from === to) { alert('From and To years must be different.'); return; }
  if (!confirm('Archive all submissions from the selected year and activate the new year?')) return;
  const btn = document.getElementById('transferBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Transferring…';
  const fd = new FormData();
  fd.append('action','transfer_year'); fd.append('from_year_id',from); fd.append('to_year_id',to);
  fetch('../api/accomplishments.php', { method:'POST', body:fd })
    .then(r => r.json())
    .then(data => {
      const al = document.getElementById('transferAlert');
      al.style.display = 'block';
      al.textContent = data.success ? '✓ Transfer complete! Reloading…' : '✗ ' + (data.message || 'Failed');
      if (data.success) setTimeout(() => location.reload(), 1500);
    })
    .finally(() => { btn.disabled=false; btn.innerHTML='<i class="fas fa-exchange-alt"></i> Transfer'; });
}

// Close modals on backdrop click
window.addEventListener('click', e => {
  if (e.target === document.getElementById('accModal'))      closeAccModal();
  if (e.target === document.getElementById('transferModal')) document.getElementById('transferModal').style.display='none';
});

// Auto-load on page open
window.addEventListener('DOMContentLoaded', () => { if (currentYearId) loadYear(); });
</script>
</body>
</html>