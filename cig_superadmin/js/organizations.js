// Organizations page functions

function showCreateOrg() {
  document.getElementById('createOrgModal').style.display = 'block';
}

function closeCreateOrg() {
  document.getElementById('createOrgModal').style.display = 'none';
}

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
  window.onclick = function(event) {
    const modal = document.getElementById('createOrgModal');
    if (event.target === modal) {
      modal.style.display = 'none';
    }
  };
});

