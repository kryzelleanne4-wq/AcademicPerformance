/**
 * Main JavaScript File
 * Common functionality for the application
 */

// Toggle the sidebar on smaller screens.
const menuToggle = document.querySelector('.menu-toggle');
const sidebar = document.querySelector('.sidebar');

if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', function() {
        const isOpen = sidebar.classList.toggle('show');
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
}

// User dropdown menu (avatar toggle in the top bar).
const userMenu = document.querySelector('.user-menu');
const userMenuToggle = document.querySelector('.user-menu-toggle');

if (userMenu && userMenuToggle) {
    userMenuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = userMenu.classList.toggle('open');
        userMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Close when clicking anywhere outside the menu.
    document.addEventListener('click', function(e) {
        if (!userMenu.contains(e.target)) {
            userMenu.classList.remove('open');
            userMenuToggle.setAttribute('aria-expanded', 'false');
        }
    });

    // Close on Escape.
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            userMenu.classList.remove('open');
            userMenuToggle.setAttribute('aria-expanded', 'false');
        }
    });
}

// ========================================
// Excel-like score sheet: live weighted score + grade computation
// ========================================
const scoreSheet = document.getElementById('scoreSheetForm');

if (scoreSheet) {
    // Returns the numeric grade stripped to a single decimal (e.g. 1.50 -> 1.5).
    function numericGrade(percent) {
        let raw;
        if (percent >= 96) raw = 1.0;
        else if (percent >= 93) raw = 1.25;
        else if (percent >= 90) raw = 1.5;
        else if (percent >= 88) raw = 1.75;
        else if (percent >= 85) raw = 2.0;
        else if (percent >= 83) raw = 2.25;
        else if (percent >= 80) raw = 2.5;
        else if (percent >= 78) raw = 2.75;
        else if (percent >= 75) raw = 3.0;
        else raw = 5.0;
        return Math.floor(raw * 10) / 10;
    }

    function gradeBadge(grade) {
        const g = Number(grade);
        let cls = 'grade-pass';
        if (g <= 1.5) cls = 'grade-top';
        else if (g <= 2.25) cls = 'grade-good';
        else if (g > 3) cls = 'grade-fail';
        return '<span class="grade-badge ' + cls + '">' + g + '</span>';
    }

    function recomputeRow(row) {
        const inputs = row.querySelectorAll('.component-score');
        let weighted = 0;
        let totalWeight = 0;
        let any = false;

        inputs.forEach(input => {
            const max = parseFloat(input.dataset.max) || 0;
            const val = input.value;
            input.classList.remove('is-over');

            if (val !== '' && val !== null) {
                const n = parseFloat(val);
                if (!isNaN(n) && n > max) {
                    input.classList.add('is-over');
                }
                // weight per component (stored in data-weight so JS doesn't need server)
                const weightStr = input.dataset.weight;
                const weight = parseFloat(weightStr ?? '') || 0;
                if (!isNaN(n) && max > 0) {
                    weighted += (n / max) * 100 * weight;
                    totalWeight += weight;
                    any = true;
                }
            }
        });

        const overallCell = row.querySelector('.overall-cell');
        const gradeCell = row.querySelector('.grade-cell');
        if (!any || totalWeight <= 0) {
            overallCell.textContent = '—';
            gradeCell.innerHTML = '<span class="text-muted">—</span>';
            return;
        }

        const overall = Math.round((weighted / totalWeight) * 100) / 100;
        const grade = numericGrade(overall);
        overallCell.textContent = overall;
        gradeCell.innerHTML = gradeBadge(grade);
    }

    // Recompute all rows on load.
    scoreSheet.querySelectorAll('tbody tr').forEach(recomputeRow);

    // Recompute the current row as the teacher types.
    scoreSheet.addEventListener('input', function(e) {
        if (e.target.classList && e.target.classList.contains('component-score')) {
            recomputeRow(e.target.closest('tr'));
        }
    });
}

// SweetAlert2 confirmations for destructive actions.
document.querySelectorAll('.btn-danger').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (typeof Swal === 'undefined' || btn.dataset.confirmed === 'true') return;

        e.preventDefault();
        Swal.fire({
            title: 'Are you sure?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ba1a1a',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then(result => {
            if (result.isConfirmed) {
                btn.dataset.confirmed = 'true';
                if (btn.form) btn.form.submit();
                else if (btn.href) window.location.href = btn.href;
            }
        });
    });
});

// Display server-side flash messages as SweetAlert toasts.
document.querySelectorAll('.alert').forEach(alert => {
    if (typeof Swal === 'undefined') return;

    const isError = alert.classList.contains('alert-error');
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: isError ? 'error' : 'success',
        title: alert.textContent.trim(),
        showConfirmButton: false,
        timer: 4500,
        timerProgressBar: true
    });
    alert.remove();
});

// ========================================
// Reusable Table Search
// ========================================
/**
 * Initialize a search bar that filters table rows.
 * @param {string} inputId - The search input element ID
 * @param {string} tableId - The table element ID
 */
function initTableSearch(inputId, tableId) {
    var input = document.getElementById(inputId);
    var table = document.getElementById(tableId);
    if (!input || !table) return;

    var tbody = table.querySelector('tbody');
    if (!tbody) return;

    var rows = Array.from(tbody.querySelectorAll('tr'));
    var countEl = input.closest('.table-search-bar') ? input.closest('.table-search-bar').querySelector('.search-count') : null;

    // Pre-compute search text for each row
    var rowSearchData = rows.map(function(row) {
        var searchText = row.dataset.search || '';
        if (!searchText) {
            var cells = row.querySelectorAll('td');
            cells.forEach(function(cell) {
                searchText += ' ' + cell.textContent;
            });
        }
        return searchText.toLowerCase();
    });

    input.addEventListener('input', function() {
        var query = input.value.toLowerCase().trim();
        var visible = 0;

        rows.forEach(function(row, i) {
            var match = !query || rowSearchData[i].indexOf(query) !== -1;
            // Use both hidden attribute and style to work with pagination
            row.dataset.searchHidden = match ? '0' : '1';
            if (match) visible++;
        });

        if (countEl) {
            countEl.textContent = visible + ' of ' + rows.length;
        }

        // Trigger a custom event so the page can react if needed
        input.dispatchEvent(new Event('tablesearch', { bubbles: true }));
    });
}
