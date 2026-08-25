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

// Confirm before delete actions
document.querySelectorAll('.btn-danger').forEach(btn => {
    btn.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to delete this?')) {
            e.preventDefault();
        }
    });
});

// Auto-hide flash messages after 5 seconds
document.querySelectorAll('.alert').forEach(alert => {
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
});
