/**
 * users_orders.js
 * Lightweight enhancements for the user orders page.
 * No status-update logic — users are read-only.
 */

(function () {
    'use strict';

    /* ── Auto-submit filters on select change ──────────────────────────────── */
    const filterSelects = document.querySelectorAll('.filter-select');
    filterSelects.forEach(function (select) {
        select.addEventListener('change', function () {
            // Small debounce so rapid changes don't fire multiple submits
            clearTimeout(select._debounce);
            select._debounce = setTimeout(function () {
                select.closest('form').submit();
            }, 300);
        });
    });

    /* ── Highlight the row on hover via JS fallback (CSS handles most of it) ─ */
    // The primary hover effect is handled by CSS :hover on .order-row.
    // This JS block provides an accessible keyboard focus highlight for
    // rows that contain focusable elements (the View button).
    const rows = document.querySelectorAll('.order-row');
    rows.forEach(function (row) {
        const focusables = row.querySelectorAll('a, button');
        focusables.forEach(function (el) {
            el.addEventListener('focus', function () {
                rows.forEach(function (r) { r.classList.remove('row-focused'); });
                row.classList.add('row-focused');
            });
            el.addEventListener('blur', function () {
                row.classList.remove('row-focused');
            });
        });
    });

    /* ── Dismiss success alert automatically (if ever shown) ──────────────── */
    const alert = document.querySelector('.alert-success');
    if (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity .5s ease';
            alert.style.opacity    = '0';
            setTimeout(function () { alert.remove(); }, 520);
        }, 3500);
    }

})();