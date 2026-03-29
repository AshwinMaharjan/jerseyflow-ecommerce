/**
 * toggleMenu(btn)
 * Toggles the .open class on the parent .sidebar-item.
 * Other open items are closed automatically (accordion behaviour).
 */
function toggleMenu(btn) {
    const item    = btn.closest('.sidebar-item');
    const isOpen  = item.classList.contains('open');

    // Close all siblings first (accordion)
    item.parentElement.querySelectorAll('.sidebar-item.open').forEach(el => {
        el.classList.remove('open');
    });

    if (!isOpen) {
        item.classList.add('open');
    }
}

// Auto-open the active parent on page load
document.addEventListener('DOMContentLoaded', () => {
    const activeChild = document.querySelector('.sidebar-dropdown .sidebar-dropdown-link.active');
    if (activeChild) {
        const parentItem = activeChild.closest('.sidebar-item');
        if (parentItem) parentItem.classList.add('open');
    }
});
