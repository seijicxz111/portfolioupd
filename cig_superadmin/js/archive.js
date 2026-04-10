// Document Archive page functions

// Auto-search and auto-filter on input/change
function initAutoSearch() {
  let searchDebounceTimer;
  const searchForm = document.querySelector('.search-filter-form');
  const searchInput = document.querySelector('.search-input');
  const filterSelect = document.querySelector('.filter-select');

  if (searchInput && searchForm) {
    searchInput.addEventListener('input', function() {
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(function() {
        searchForm.submit();
      }, 500); // Wait 500ms after user stops typing before submitting
    });
  }

  if (filterSelect && searchForm) {
    filterSelect.addEventListener('change', function() {
      searchForm.submit();
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
