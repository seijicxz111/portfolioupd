// Announcement Board Functions

const PRIORITY_STYLES = {
  urgent: { label: 'Urgent', color: '#c0392b', bg: '#fde8e8' },
  high:   { label: 'High',   color: '#b7770d', bg: '#fff3cd' },
  low:    { label: 'Low',    color: '#555',    bg: '#f0f0f0' },
};

function buildBadge(priority) {
  const s = PRIORITY_STYLES[priority] || PRIORITY_STYLES.low;
  return `<span class="ann-priority-badge" style="background:${s.bg};color:${s.color};">${s.label}</span>`;
}

function openAddModal() {
  document.getElementById('modalHeading').innerText = 'Add Announcement';
  document.getElementById('editingId').value        = '';
  document.getElementById('annTitleInput').value    = '';
  document.getElementById('annContentInput').value  = '';
  document.getElementById('annPriorityInput').value = 'low';
  document.getElementById('saveSuccess').style.display = 'none';
  document.getElementById('saveError').style.display   = 'none';
  document.getElementById('announcementModal').style.display = 'flex';
}

function openEditModal(id, title, content, priority) {
  document.getElementById('modalHeading').innerText  = 'Edit Announcement';
  document.getElementById('editingId').value         = id;
  document.getElementById('annTitleInput').value     = title;
  document.getElementById('annContentInput').value   = content;
  document.getElementById('annPriorityInput').value  = priority || 'low';
  document.getElementById('saveSuccess').style.display = 'none';
  document.getElementById('saveError').style.display   = 'none';
  document.getElementById('announcementModal').style.display = 'flex';
}

function closeAnnouncementModal() {
  document.getElementById('announcementModal').style.display = 'none';
}

// Handle announcement save
document.getElementById('announcementForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const btn       = document.getElementById('saveAnnouncementBtn');
  const title     = document.getElementById('annTitleInput').value.trim();
  const content   = document.getElementById('annContentInput').value.trim();
  const priority  = document.getElementById('annPriorityInput').value;
  const editingId = document.getElementById('editingId').value;

  if (!title || !content) return;

  btn.disabled  = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

  const formData = new FormData();
  formData.append('title',    title);
  formData.append('content',  content);
  formData.append('priority', priority);
  if (editingId) formData.append('announcement_id', editingId);

  fetch('../api/save_announcement.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        document.getElementById('saveSuccess').style.display = 'block';
        document.getElementById('saveSuccessMsg').innerText  = editingId ? 'Updated successfully!' : 'Added successfully!';
        document.getElementById('saveError').style.display   = 'none';

        if (editingId) {
          const item = document.getElementById('ann-' + editingId);
          if (item) {
            item.querySelector('h4').innerHTML = buildBadge(data.priority) + data.title;
            item.querySelector('p').innerText  = data.content;
          }
        } else {
          const list  = document.getElementById('announcementList');
          const empty = list.querySelector('p');
          if (empty) empty.remove();
          const newItem = document.createElement('div');
          newItem.className = 'announcement-item';
          newItem.id        = 'ann-' + data.id;
          newItem.innerHTML = `
            <div class="ann-item-header">
              <h4>${buildBadge(data.priority)}${data.title}</h4>
              <div class="ann-item-actions">
                <button class="ann-edit-btn" title="Edit"
                  onclick="openEditModal(${data.id}, ${JSON.stringify(data.title)}, ${JSON.stringify(data.content)}, ${JSON.stringify(data.priority)})">
                  <i class="fas fa-edit"></i>
                </button>
                <button class="ann-delete-btn" title="Delete"
                  onclick="deleteAnnouncement(${data.id})">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
            <p>${data.content}</p>
            <small style="color:#888;">Posted: ${data.created_at}</small>`;
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

// Close modal when clicking outside
window.addEventListener('click', function(e) {
  if (e.target === document.getElementById('announcementModal')) closeAnnouncementModal();
});