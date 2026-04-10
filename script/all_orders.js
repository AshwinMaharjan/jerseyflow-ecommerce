/**
 * all_orders.js
 * Behaviour enhancements for the All Orders admin page.
 */

(function () {
    'use strict';

    // ── Colour map for payment-status select ──────────────────────────────────
    const PAYMENT_COLORS = {
        unpaid:   { bg: 'rgba(100,100,100,.18)', color: '#9ca3af', border: 'rgba(120,120,120,.35)' },
        paid:     { bg: 'rgba(22,101,52,.22)',   color: '#86efac', border: 'rgba(22,101,52,.4)'    },
        failed:   { bg: 'rgba(185,28,28,.22)',   color: '#fca5a5', border: 'rgba(185,28,28,.4)'    },
        refunded: { bg: 'rgba(124,58,237,.18)',  color: '#c4b5fd', border: 'rgba(124,58,237,.35)'  },
    };

    function applyPaymentColor(select) {
        var val   = select.value;
        var theme = PAYMENT_COLORS[val];
        if (!theme) return;
        select.style.background  = theme.bg;
        select.style.color       = theme.color;
        select.style.borderColor = theme.border;
    }

    // ── Payment status: apply colour + auto-submit on change ─────────────────
    document.querySelectorAll('.payment-select').forEach(function (select) {
        applyPaymentColor(select);
        select.dataset.previous = select.value;

        select.addEventListener('change', function () {
            applyPaymentColor(select);

            var val = select.value;
            if (['refunded', 'failed'].includes(val)) {
                if (!window.confirm('Set payment status to "' + ucfirst(val) + '"?\nThis action may be hard to reverse.')) {
                    select.value = select.dataset.previous;
                    applyPaymentColor(select);
                    return;
                }
            }
            select.dataset.previous = val;
            select.closest('form').submit();
        });
    });

    // ── Order status: auto-submit from the Status column only ────────────────
    document.querySelectorAll('.order-status-select').forEach(function (select) {
        select.dataset.previous = select.value;

        select.addEventListener('change', function () {
            var val = select.value;
            if (['cancelled', 'delivered'].includes(val)) {
                if (!window.confirm('Set order status to "' + ucfirst(val) + '"?\nThis action may be hard to reverse.')) {
                    select.value = select.dataset.previous;
                    return;
                }
            }
            select.dataset.previous = val;
            select.closest('form').submit();
        });
    });

    // ── Auto-submit filters on select change ──────────────────────────────────
    var filtersForm = document.getElementById('filters-form');
    if (filtersForm) {
        filtersForm.querySelectorAll('.filter-select').forEach(function (select) {
            select.addEventListener('change', function () {
                filtersForm.querySelectorAll('input[name="page"]').forEach(function (el) { el.remove(); });
                filtersForm.submit();
            });
        });
    }

    // ── Auto-dismiss success alert ────────────────────────────────────────────
    var alertEl = document.querySelector('.alert-success');
    if (alertEl) {
        setTimeout(function () {
            alertEl.style.transition = 'opacity .5s ease, max-height .5s ease, margin .5s ease, padding .5s ease';
            alertEl.style.opacity    = '0';
            alertEl.style.maxHeight  = '0';
            alertEl.style.padding    = '0 16px'; 
            alertEl.style.margin     = '0';
            setTimeout(function () { alertEl.remove(); }, 550);
        }, 3500);
    }

    function ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

})();