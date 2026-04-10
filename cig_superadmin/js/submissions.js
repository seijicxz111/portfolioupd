// ============================================================
//  submissions.js  –  Admin Submissions Page
// ============================================================

// ── Auto-search (debounced) ──────────────────────────────────
(function initAutoSearch() {
  let timer;
  const form  = document.querySelector('.search-filter-form');
  const input = document.querySelector('.search-input');
  if (input && form) {
    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(() => form.submit(), 500);
    });
  }
})();

// Attach backdrop-click and Escape listeners once the modal exists in DOM
// Uses a small poll so it works regardless of where the script tag is placed
(function attachModalListeners() {
  function tryAttach() {
    const modal = document.getElementById('previewModal');
    if (!modal) return; // not in DOM yet — wait
    modal.addEventListener('click', function (e) {
      if (e.target === this) closePreviewModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closePreviewModal();
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tryAttach);
  } else {
    tryAttach();
  }
})();


// ============================================================
//  PREVIEW MODAL  (ported from user-side document_tracking)
// ============================================================

/**
 * openPreviewModal(id, ext, title, status)
 * Called by the Preview button in the table row.
 */
function openPreviewModal(id, ext, title, status) {
  const modal    = document.getElementById('previewModal');
  const loading  = document.getElementById('previewLoading');
  const loadMsg  = document.getElementById('previewLoadingMsg');
  const errorDiv = document.getElementById('previewError');
  const pdfFrame = document.getElementById('previewPdfFrame');
  const docxWrap = document.getElementById('previewDocxWrap');
  const titleEl  = document.getElementById('previewTitle');
  const iconEl   = document.getElementById('previewFileIcon');

  if (!modal) { console.error('previewModal element not found in page.'); return; }

  // Reset state
  loading.style.display  = 'flex';
  loadMsg.textContent    = 'Loading document\u2026';
  errorDiv.style.display = 'none';
  pdfFrame.style.display = 'none';
  docxWrap.style.display = 'none';
  pdfFrame.src           = '';
  docxWrap.innerHTML     = '';
  titleEl.textContent    = title;
  modal.style.display    = 'flex';

  // File-type icon
  const iconMap = { pdf:'fa-file-pdf', docx:'fa-file-word', doc:'fa-file-word', xlsx:'fa-file-excel', xls:'fa-file-excel' };
  iconEl.className = 'fas ' + (iconMap[ext] || 'fa-file-alt');

  // Bind approve / reject inside modal header
  _bindModalActions(id, status);

  // Preview URLs — adjust path prefix if your admin folder differs
  const previewUrl = '../pages/file_preview.php?submission_id=' + id;
  const convertUrl = '../pages/docx_to_pdf.php?submission_id='  + id;

  if (ext === 'pdf') {
    pdfFrame.src           = previewUrl;
    pdfFrame.style.display = 'block';
    loading.style.display  = 'none';
    pdfFrame.onerror       = () => _showPreviewError('Failed to load PDF.');

  } else if (ext === 'docx' || ext === 'doc') {
    loadMsg.textContent    = 'Converting document\u2026';
    pdfFrame.src           = convertUrl;
    pdfFrame.style.display = 'block';
    pdfFrame.onload        = () => { loading.style.display = 'none'; };
    pdfFrame.onerror       = () => _showPreviewError('Failed to convert document.');

  } else if (ext === 'xlsx' || ext === 'xls') {
    if (typeof XLSX === 'undefined') {
      const s  = document.createElement('script');
      s.src    = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
      s.onload = () => _loadXlsx(previewUrl, docxWrap, loading);
      document.head.appendChild(s);
    } else {
      _loadXlsx(previewUrl, docxWrap, loading);
    }

  } else if (!ext) {
    // No file attached — show metadata only, hide loading
    loading.style.display = 'none';
    docxWrap.innerHTML    = '<div style="padding:40px;text-align:center;color:#9ca3af;"><i class="fas fa-ban" style="font-size:2rem;margin-bottom:12px;display:block;"></i>No file attached to this submission.</div>';
    docxWrap.style.display = 'block';

  } else {
    _showPreviewError('Preview not available for this file type.');
  }
}

function closePreviewModal() {
  const modal = document.getElementById('previewModal');
  if (!modal) return;
  modal.style.display = 'none';
  document.getElementById('previewPdfFrame').src       = '';
  document.getElementById('previewDocxWrap').innerHTML = '';
}


// ── Private helpers ──────────────────────────────────────────

function _loadXlsx(url, wrap, loading) {
  fetch(url)
    .then(r => { if (!r.ok) throw new Error('Server error ' + r.status); return r.arrayBuffer(); })
    .then(buf => {
      const wb   = XLSX.read(new Uint8Array(buf), { type: 'array' });
      let   html = '<style>table{border-collapse:collapse;font-size:.8rem;width:100%;}td,th{border:1px solid #ccc;padding:4px 8px;white-space:nowrap;}</style>';
      wb.SheetNames.forEach(name => {
        html += `<div style="padding:16px;"><h3 style="margin:0 0 8px;color:#047857;font-size:.9rem;">${name}</h3>`;
        html += XLSX.utils.sheet_to_html(wb.Sheets[name], { editable: false });
        html += '</div>';
      });
      wrap.innerHTML        = html;
      wrap.style.display    = 'block';
      loading.style.display = 'none';
    })
    .catch(e => _showPreviewError('Could not render spreadsheet: ' + e.message));
}

function _showPreviewError(msg) {
  const loading = document.getElementById('previewLoading');
  const errorDiv = document.getElementById('previewError');
  const errorMsg = document.getElementById('previewErrorMsg');
  if (loading)  loading.style.display  = 'none';
  if (errorDiv) errorDiv.style.display = 'flex';
  if (errorMsg) errorMsg.textContent   = msg;
}

// Re-bind approve/reject each time modal opens
// Uses onclick directly to avoid stale duplicate listeners from addEventListener
function _bindModalActions(id, status) {
  const approveBtn = document.getElementById('modalApproveBtn');
  const rejectBtn  = document.getElementById('modalRejectBtn');
  if (!approveBtn || !rejectBtn) return;

  approveBtn.disabled = (status === 'approved');
  rejectBtn.disabled  = (status === 'rejected');

  approveBtn.onclick = function() { closePreviewModal(); approveSubmission(id); };
  rejectBtn.onclick  = function() { closePreviewModal(); rejectSubmission(id);  };
}


// ============================================================
//  APPROVE  →  opens remarks modal first
// ============================================================
function approveSubmission(id) {
  _showRemarksModal(id, 'approve');
}


// ============================================================
//  REJECT  →  opens remarks modal (required)
// ============================================================
function rejectSubmission(id) {
  _showRemarksModal(id, 'reject');
}

// ============================================================
//  UNIFIED REMARKS MODAL  (approve = optional, reject = required)
// ============================================================
function _showRemarksModal(id, mode) {
  const isReject   = mode === 'reject';
  const accentColor = isReject ? '#ef4444' : '#10b981';
  const headerBg    = isReject ? '#fef2f2' : '#f0fdf4';
  const borderColor = isReject ? '#fecaca' : '#bbf7d0';
  const icon        = isReject ? 'fa-times-circle' : 'fa-check-circle';
  const title       = isReject ? 'Reject Submission' : 'Approve Submission';
  const subtitle    = isReject
    ? 'Provide a reason so the submitter can revise and resubmit.'
    : 'Optionally add remarks for the submitter (e.g. what was good, what to note).';
  const btnLabel    = isReject ? 'Reject Submission' : 'Approve Submission';
  const btnClass    = isReject ? 'confirm-reject' : 'confirm-approve';
  const required    = isReject ? '<span style="color:#ef4444">*</span>' : '<span style="color:#9ca3af">(optional)</span>';

  const QUICK_REASONS = isReject ? [
    'Incomplete documentation',
    'Does not meet requirements',
    'Duplicate submission',
    'Incorrect format',
    'Missing signatures or approvals',
  ] : [
    'Well-documented',
    'Meets all requirements',
    'Approved with minor notes',
    'Good submission, no issues',
  ];

  let overlay = document.getElementById('rejectCommentOverlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'rejectCommentOverlay';
    document.body.appendChild(overlay);
  }

  overlay.innerHTML = `
    <div class="reject-modal-box">
      <div class="reject-modal-header" style="background:${headerBg};border-bottom:1px solid ${borderColor};">
        <span class="reject-modal-icon" style="color:${accentColor};"><i class="fas ${icon}"></i></span>
        <div>
          <div class="reject-modal-title">${title}</div>
          <div class="reject-modal-subtitle">${subtitle}</div>
        </div>
        <button class="reject-modal-close" onclick="_closeRejectModal()">&times;</button>
      </div>

      <div class="reject-modal-body">
        <div class="reject-quick-label">Quick ${isReject ? 'reasons' : 'remarks'}</div>
        <div class="reject-quick-list" style="--accent:${accentColor};">
          ${QUICK_REASONS.map(r => `<button class="reject-quick-btn" style="border-color:${accentColor}33;color:${accentColor};" onclick="_fillRejectReason('${r}')">${r}</button>`).join('')}
        </div>

        <label class="reject-textarea-label" for="rejectReasonText">
          ${isReject ? 'Rejection comment' : 'Remarks'} ${required}
        </label>
        <textarea id="rejectReasonText" class="reject-textarea"
                  style="--focus-color:${accentColor};"
                  placeholder="${isReject ? 'Describe the issue and what the submitter should do to correct it…' : 'Add any notes or feedback for the submitter…'}"
                  maxlength="500" oninput="_updateRejectCharCount(this)"></textarea>
        <div class="reject-char-count"><span id="rejectCharCount">0</span> / 500</div>
      </div>

      <div class="reject-modal-footer">
        <button class="confirm-btn confirm-cancel" onclick="_closeRejectModal()">
          <i class="fas fa-arrow-left"></i> Cancel
        </button>
        <button class="confirm-btn ${btnClass}" id="rejectSubmitBtn" onclick="_submitAction(${id}, '${mode}')">
          <i class="fas ${isReject ? 'fa-times' : 'fa-check'}"></i> ${btnLabel}
        </button>
      </div>
    </div>`;

  overlay.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById('rejectReasonText')?.focus(), 100);
}

function _fillRejectReason(text) {
  const ta = document.getElementById('rejectReasonText');
  if (!ta) return;
  ta.value = text;
  _updateRejectCharCount(ta);
}

function _updateRejectCharCount(ta) {
  const el = document.getElementById('rejectCharCount');
  if (el) el.textContent = ta.value.length;
}

function _closeRejectModal() {
  const overlay = document.getElementById('rejectCommentOverlay');
  if (overlay) overlay.style.display = 'none';
  document.body.style.overflow = '';
}

function _submitAction(id, mode) {
  const isReject = mode === 'reject';
  const ta       = document.getElementById('rejectReasonText');
  const reason   = ta ? ta.value.trim() : '';

  if (isReject && !reason) {
    ta.classList.add('reject-textarea-error');
    ta.placeholder = 'A rejection reason is required before submitting.';
    ta.focus();
    return;
  }

  const btn = document.getElementById('rejectSubmitBtn');
  if (btn) {
    btn.disabled  = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${isReject ? 'Rejecting…' : 'Approving…'}`;
  }

  _callApiWithReason(mode === 'approve' ? 'approve' : 'reject', id, reason)
    .then(data => {
      _closeRejectModal();
      if (data.success) {
        _showToast(isReject ? 'Submission rejected.' : 'Submission approved!', isReject ? 'error' : 'success');
        _removeRow(id);
      } else {
        _showToast(data.message || 'Action failed.', 'error');
      }
    })
    .catch(() => {
      _closeRejectModal();
      _showToast('Network error. Please try again.', 'error');
    });
}


// ============================================================
//  API HELPER
// ============================================================
function _callApi(action, submissionId) {
  const form = new FormData();
  form.append('action', action);
  form.append('submission_id', submissionId);
  return fetch('../api/submissions.php', { method: 'POST', body: form }).then(r => r.json());
}

function _callApiWithReason(action, submissionId, reason) {
  const form = new FormData();
  form.append('action', action);
  form.append('submission_id', submissionId);
  form.append('reason', reason);
  return fetch('../api/submissions.php', { method: 'POST', body: form }).then(r => r.json());
}


// ============================================================
//  TABLE ROW REMOVAL
// ============================================================
function _removeRow(id) {
  // Match the Preview button by its onclick attribute
  const btn = document.querySelector('button[onclick*="openPreviewModal(' + id + ',"]');
  if (!btn) return;
  const row = btn.closest('tr');
  if (!row) return;
  row.style.transition = 'opacity .4s ease, transform .4s ease';
  row.style.opacity    = '0';
  row.style.transform  = 'translateX(30px)';
  setTimeout(() => {
    row.remove();
    const tbody = document.querySelector('table tbody');
    if (tbody && !tbody.querySelector('tr')) {
      tbody.innerHTML = '<tr><td colspan="7" class="empty-row">No submissions found</td></tr>';
    }
  }, 400);
}


// ============================================================
//  CONFIRM DIALOG
// ============================================================
let _confirmOverlay = null;

function _showConfirm(title, message, confirmLabel, confirmClass, onConfirm) {
  if (!_confirmOverlay) {
    _confirmOverlay = document.createElement('div');
    _confirmOverlay.id = 'confirmOverlay';
    document.body.appendChild(_confirmOverlay);
  }
  _confirmOverlay.innerHTML = `
    <div class="confirm-box">
      <div class="confirm-title">${title}</div>
      <div class="confirm-body">${message}</div>
      <div class="confirm-footer">
        <button id="confirmOkBtn"     class="confirm-btn ${confirmClass}">${confirmLabel}</button>
        <button id="confirmCancelBtn" class="confirm-btn confirm-cancel">Cancel</button>
      </div>
    </div>`;
  _confirmOverlay.style.display = 'flex';
  document.body.style.overflow  = 'hidden';
  document.getElementById('confirmOkBtn').onclick     = () => { _closeConfirm(); onConfirm(); };
  document.getElementById('confirmCancelBtn').onclick = _closeConfirm;
}

function _closeConfirm() {
  if (_confirmOverlay) _confirmOverlay.style.display = 'none';
  document.body.style.overflow = '';
}


// ============================================================
//  TOAST
// ============================================================
function _showToast(message, type) {
  const old = document.getElementById('subToast');
  if (old) old.remove();
  const c = {
    success: { bg: '#10b981', icon: 'fa-check-circle'  },
    error:   { bg: '#ef4444', icon: 'fa-times-circle'  },
    info:    { bg: '#3b82f6', icon: 'fa-info-circle'   }
  }[type] || { bg: '#3b82f6', icon: 'fa-info-circle' };
  const t = document.createElement('div');
  t.id = 'subToast';
  t.innerHTML = `<i class="fas ${c.icon}"></i> ${message}`;
  t.style.cssText = `position:fixed;bottom:30px;right:30px;z-index:99999;background:${c.bg};color:#fff;padding:13px 20px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 6px 20px rgba(0,0,0,.2);display:flex;align-items:center;gap:10px;animation:toastIn .3s ease;max-width:360px;`;
  document.body.appendChild(t);
  setTimeout(() => {
    t.style.animation = 'toastOut .3s ease forwards';
    setTimeout(() => t.remove(), 300);
  }, 3500);
}


// ============================================================
//  INJECTED STYLES
// ============================================================
(function () {
  const s = document.createElement('style');
  s.textContent = `
    @keyframes toastIn  { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
    @keyframes toastOut { from{opacity:1;transform:translateY(0)}     to{opacity:0;transform:translateY(20px)} }
    @keyframes modalIn  { from{opacity:0;transform:scale(.93) translateY(16px)} to{opacity:1;transform:scale(1) translateY(0)} }

    /* ── Confirm overlay ── */
    #confirmOverlay {
      display:none;position:fixed;inset:0;z-index:10000;
      background:rgba(0,0,0,.5);backdrop-filter:blur(3px);
      align-items:center;justify-content:center;
    }
    .confirm-box {
      background:#fff;border-radius:14px;padding:28px 26px;
      width:90%;max-width:400px;
      box-shadow:0 16px 50px rgba(0,0,0,.22);
      animation:modalIn .25s ease;
    }
    .confirm-title { font-size:17px;font-weight:700;color:#1f2937;margin-bottom:12px;display:flex;align-items:center;gap:9px; }
    .confirm-body  { font-size:14px;color:#4b5563;line-height:1.6;margin-bottom:22px; }
    .confirm-footer{ display:flex;gap:10px;justify-content:flex-end; }
    .confirm-btn   { padding:9px 18px;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;transition:transform .15s;color:#fff; }
    .confirm-btn:hover { transform:translateY(-2px); }
    .confirm-approve { background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 3px 10px rgba(16,185,129,.35); }
    .confirm-reject  { background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 3px 10px rgba(239,68,68,.35); }
    .confirm-cancel  { background:linear-gradient(135deg,#6b7280,#4b5563);box-shadow:0 3px 10px rgba(107,114,128,.3); }

    /* ── Modal header action buttons ── */
    .modal-action-btn {
      display:inline-flex;align-items:center;gap:6px;
      padding:7px 16px;border:none;border-radius:7px;
      font-size:13px;font-weight:700;cursor:pointer;color:#fff;
      transition:transform .15s,filter .15s;white-space:nowrap;
    }
    .modal-action-btn:disabled { opacity:.45;cursor:not-allowed;transform:none!important; }
    .modal-action-btn:hover:not(:disabled) { transform:translateY(-1px);filter:brightness(1.1); }
    #modalApproveBtn { background:linear-gradient(135deg,#10b981,#059669); }
    #modalRejectBtn  { background:linear-gradient(135deg,#ef4444,#dc2626); }

    /* ── Reject-comment modal ── */
    #rejectCommentOverlay {
      display:none;position:fixed;inset:0;z-index:10001;
      background:rgba(0,0,0,.55);backdrop-filter:blur(4px);
      align-items:center;justify-content:center;padding:16px;
    }
    .reject-modal-box {
      background:#fff;border-radius:16px;width:100%;max-width:520px;
      box-shadow:0 20px 60px rgba(0,0,0,.28);
      animation:modalIn .25s ease;overflow:hidden;
    }
    .reject-modal-header {
      display:flex;align-items:flex-start;gap:13px;
      padding:20px 22px 18px;background:#fef2f2;border-bottom:1px solid #fecaca;
      position:relative;
    }
    .reject-modal-icon { font-size:1.6rem;color:#ef4444;flex-shrink:0;margin-top:2px; }
    .reject-modal-title { font-size:16px;font-weight:700;color:#1f2937; }
    .reject-modal-subtitle { font-size:12.5px;color:#6b7280;margin-top:3px;line-height:1.4; }
    .reject-modal-close {
      position:absolute;top:14px;right:16px;
      background:none;border:none;font-size:1.4rem;color:#9ca3af;
      cursor:pointer;line-height:1;padding:2px 6px;border-radius:6px;
      transition:background .15s,color .15s;
    }
    .reject-modal-close:hover { background:#fee2e2;color:#ef4444; }

    .reject-modal-body { padding:20px 22px; }
    .reject-quick-label { font-size:11.5px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px; }
    .reject-quick-list  { display:flex;flex-wrap:wrap;gap:7px;margin-bottom:18px; }
    .reject-quick-btn {
      padding:6px 12px;border:1.5px solid #fca5a5;border-radius:20px;
      background:#fff;color:#dc2626;font-size:12px;font-weight:600;
      cursor:pointer;transition:background .15s,transform .1s;white-space:nowrap;
    }
    .reject-quick-btn:hover { background:#fee2e2;transform:translateY(-1px); }

    .reject-textarea-label { display:block;font-size:13px;font-weight:700;color:#374151;margin-bottom:7px; }
    .reject-textarea {
      width:100%;height:110px;padding:11px 13px;
      border:1.5px solid #d1d5db;border-radius:9px;
      font-size:13.5px;color:#1f2937;resize:vertical;
      font-family:inherit;line-height:1.55;box-sizing:border-box;
      transition:border-color .2s,box-shadow .2s;outline:none;
    }
    .reject-textarea:focus { border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.12); }
    .reject-textarea-error { border-color:#ef4444!important; }
    .reject-char-count { text-align:right;font-size:11.5px;color:#9ca3af;margin-top:5px; }

    .reject-modal-footer {
      display:flex;justify-content:flex-end;gap:10px;
      padding:15px 22px;background:#f9fafb;border-top:1px solid #f3f4f6;
    }


    #previewDocxWrap { background:#e8e8e8; }
    #previewDocxWrap .docx-wrapper { background:#e8e8e8!important;padding:16px!important; }
    #previewDocxWrap .docx-wrapper>section.docx {
      width:100%!important;max-width:900px!important;min-height:auto!important;
      margin:0 auto 16px!important;padding:72px 90px!important;
      box-shadow:0 2px 12px rgba(0,0,0,.2)!important;
      box-sizing:border-box!important;overflow:visible!important;background:#fff!important;
    }
    #previewDocxWrap img, #previewDocxWrap svg image {
      max-width:100%!important;height:auto!important;
      visibility:visible!important;display:inline-block!important;
    }
    #previewDocxWrap table { max-width:100%!important;table-layout:fixed!important;word-break:break-word!important; }
    #previewDocxWrap [style*="position:absolute"],
    #previewDocxWrap [style*="position: absolute"] {
      position:relative!important;left:auto!important;top:auto!important;
      transform:none!important;margin:0!important;
    }
  `;
  document.head.appendChild(s);
})();