/* ============================================================
   search.js  —  JerseyFlow Search Page
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {

    /* ── Mobile Filter Sidebar Toggle ───────────────────────── */
    const filterBtn     = document.getElementById('mobileFilterBtn');
    const sidebar       = document.getElementById('filtersSidebar');
    const resetBtn      = document.getElementById('resetFilters');

    if (filterBtn && sidebar) {
        // Create overlay element
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);

        const openSidebar = () => {
            sidebar.classList.add('open');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        };

        const closeSidebar = () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        };

        filterBtn.addEventListener('click', openSidebar);
        overlay.addEventListener('click', closeSidebar);

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeSidebar();
        });
    }


    /* ── Filter Group Accordion ─────────────────────────────── */
    document.querySelectorAll('.filter-group-toggle').forEach(toggle => {
        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
        });
    });


    /* ── Size Pills ─────────────────────────────────────────── */
    document.querySelectorAll('.size-pill').forEach(pill => {
        const radio = pill.querySelector('input[type="radio"]');
        if (!radio) return;

        // Sync initial active class
        if (radio.checked) pill.classList.add('active');

        pill.addEventListener('click', () => {
            // Remove active from all pills
            document.querySelectorAll('.size-pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            radio.checked = true;
        });
    });


    /* ── Reset Filters ──────────────────────────────────────── */
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            const q = new URLSearchParams(window.location.search).get('q') || '';
            window.location.href = `/jerseyflow-ecommerce/search.php?q=${encodeURIComponent(q)}`;
        });
    }


    /* ── Search Input: Keyboard shortcut (/) ────────────────── */
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        document.addEventListener('keydown', (e) => {
            // Focus on "/" press when not already in an input
            const tag = document.activeElement.tagName;
            if (e.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
        });
    }

});


/* ── Sort — preserve existing filter params ─────────────────── */
function applySort(value) {
    const params = new URLSearchParams(window.location.search);
    params.set('sort', value);
    window.location.href = `/jerseyflow-ecommerce/search.php?${params.toString()}`;
}