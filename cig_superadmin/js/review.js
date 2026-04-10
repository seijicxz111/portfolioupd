// Review & Approval page functions

// Auto-search on input after DOM is ready
function initAutoSearch() {
  let searchDebounceTimer;
  const searchForm = document.querySelector('.search-filter-form');
  const searchInput = document.querySelector('.search-input');

  if (searchInput && searchForm) {
    searchInput.addEventListener('input', function() {
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(function() {
        searchForm.submit();
      }, 500); // Wait 500ms after user stops typing before submitting
    });
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAutoSearch);
} else {
  initAutoSearch();
}

function filterTable() {
  let searchInput = document.querySelector('.search-bar').value.toLowerCase();
  let filterSelect = document.querySelector('.filter-select').value;
  let table = document.querySelector('table tbody');
  let rows = table.querySelectorAll('tr');
  
  rows.forEach(row => {
    let text = row.innerText.toLowerCase();
    let status = row.querySelector('.status') ? row.querySelector('.status').innerText : '';
    
    let matchesSearch = text.includes(searchInput);
    let matchesFilter = filterSelect === '' || status === filterSelect;
    
    row.style.display = matchesSearch && matchesFilter ? '' : 'none';
  });
}

function approve(btn) {
  let row = btn.parentElement.parentElement;
  let status = row.querySelector(".status");
  status.innerText = "Approved";
  status.className = "status approved";
  btn.remove();
  alert("Submission Approved!");
}
