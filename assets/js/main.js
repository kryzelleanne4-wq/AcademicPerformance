/**
 * Main JavaScript File
 * Common functionality for the application
 */

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
