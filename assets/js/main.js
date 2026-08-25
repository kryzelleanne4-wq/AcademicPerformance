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
