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
// Data tables: shared search + pagination
// ========================================
/**
 * Wire up a table that opts in with `data-pagination`: substring search, page
 * controls, an empty state and a result counter.
 *
 * Rows are hidden with an inline `display` value so the paging keeps working in
 * the responsive card layout, where `tbody tr { display: block }` would
 * otherwise override the `hidden` attribute.
 *
 * @param {HTMLTableElement} table
 * @param {HTMLInputElement} [explicitInput] - Force a specific search field.
 * @returns {object|null} A small API: { setInput, refresh }.
 */
function initDataTable(table, explicitInput) {
    if (table._dataTable) {
        if (explicitInput) table._dataTable.setInput(explicitInput);
        return table._dataTable;
    }

    var tbody = table.querySelector('tbody');
    if (!tbody) return null;

    var allRows = Array.from(tbody.querySelectorAll('tr')).filter(function (row) {
        return !row.classList.contains('table-empty-row');
    });
    var pageSize = parseInt(table.dataset.pageSize || '10', 10) || 10;
    var page = 1;

    // The search bar lives in the same card/section, directly above the table.
    var scope = table.closest('.card') || table.closest('.table-container') || table.parentElement;
    var bar = null;
    if (scope) {
        var bars = Array.from(scope.querySelectorAll('.table-search-bar'));
        for (var i = 0; i < bars.length; i++) {
            if (bars[i].compareDocumentPosition(table) & Node.DOCUMENT_POSITION_FOLLOWING) {
                bar = bars[i];
                break;
            }
        }
    }

    var input = explicitInput || (bar ? bar.querySelector('input') : null);
    var countEl = bar ? bar.querySelector('.search-count') : null;

    // Empty state row (kept out of `allRows` so paging math stays correct).
    var emptyRow = document.createElement('tr');
    emptyRow.className = 'table-empty-row';
    emptyRow.style.display = 'none';
    var emptyCell = document.createElement('td');
    emptyCell.className = 'table-empty';
    emptyCell.colSpan = table.querySelectorAll('thead th').length || 1;
    emptyCell.textContent = 'No matching records found.';
    emptyRow.appendChild(emptyCell);
    tbody.appendChild(emptyRow);

    // Pagination controls sit under the table, inside the scroll container.
    var wrapper = table.closest('.table-container') || table.parentElement;
    var controls = document.createElement('div');
    controls.className = 'pagination';
    controls.style.display = 'none';
    wrapper.appendChild(controls);

    // Pre-compute the searchable text once per row.
    var searchText = allRows.map(function (row) {
        var text = row.dataset.search;
        if (!text) {
            text = '';
            row.querySelectorAll('td').forEach(function (cell) {
                text += ' ' + cell.textContent;
            });
        }
        return text.toLowerCase();
    });

    function applyQuery() {
        if (!input) return;
        var query = input.value.toLowerCase().trim();
        allRows.forEach(function (row, i) {
            row.dataset.searchHidden = (!query || searchText[i].indexOf(query) !== -1) ? '0' : '1';
        });
    }

    function render() {
        var filtered = allRows.filter(function (row) {
            return row.dataset.searchHidden !== '1';
        });
        var totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
        page = Math.min(page, totalPages);

        allRows.forEach(function (row) { row.style.display = 'none'; });
        filtered.forEach(function (row, index) {
            var onPage = index >= (page - 1) * pageSize && index < page * pageSize;
            row.style.display = onPage ? '' : 'none';
        });

        emptyRow.style.display = filtered.length === 0 ? '' : 'none';

        if (countEl) {
            countEl.textContent = filtered.length === allRows.length
                ? allRows.length + ' records'
                : filtered.length + ' of ' + allRows.length;
        }

        controls.innerHTML = '';
        if (filtered.length <= pageSize) {
            controls.style.display = 'none';
            return;
        }
        controls.style.display = '';

        var previous = document.createElement('button');
        previous.type = 'button';
        previous.className = 'pagination-btn';
        previous.textContent = 'Previous';
        previous.disabled = page === 1;
        previous.addEventListener('click', function () { page--; render(); });
        controls.appendChild(previous);

        var status = document.createElement('span');
        status.className = 'pagination-status';
        status.textContent = 'Page ' + page + ' of ' + totalPages;
        controls.appendChild(status);

        var next = document.createElement('button');
        next.type = 'button';
        next.className = 'pagination-btn';
        next.textContent = 'Next';
        next.disabled = page === totalPages;
        next.addEventListener('click', function () { page++; render(); });
        controls.appendChild(next);
    }

    if (input) {
        var clear = null;
        if (bar && !bar.querySelector('.table-search-clear')) {
            clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'table-search-clear';
            clear.setAttribute('aria-label', 'Clear search');
            clear.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
            clear.hidden = true;
            (input.closest('.table-search-wrapper') || input.parentElement).appendChild(clear);
            clear.addEventListener('click', function () {
                input.value = '';
                clear.hidden = true;
                input.focus();
                applyQuery();
                page = 1;
                render();
            });
        }

        input.addEventListener('input', function () {
            if (clear) clear.hidden = input.value === '';
            applyQuery();
            page = 1;
            render();
        });
    }

    // Pages with extra filters (status/term dropdowns) can reuse the same pager
    // by setting `row.dataset.searchHidden` and dispatching this event.
    table.addEventListener('tablefilterchange', function () {
        page = 1;
        render();
    });

    var api = {
        setInput: function (newInput) { input = newInput; },
        refresh: render
    };
    table._dataTable = api;

    applyQuery();
    render();
    return api;
}

/**
 * Backwards-compatible helper for wiring a specific input/table pair.
 */
function initTableSearch(inputId, tableId) {
    var input = document.getElementById(inputId);
    var table = document.getElementById(tableId);
    if (!input || !table) return;
    initDataTable(table, input);
}

// main.js loads at the end of <body>, so page scripts cannot call
// initTableSearch synchronously. Auto-wire every paginated table instead.
function initDataTables() {
    document.querySelectorAll('table[data-pagination]').forEach(function (table) {
        initDataTable(table);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDataTables);
} else {
    initDataTables();
}
