/**
 * JerseyFlow — checkout.js
 * Handles:
 *  - Address card selection highlight
 *  - Form submit guard (cart empty / no address)
 *  - Pay button loading state
 */

(function () {
    'use strict';

    /* ── Address card selection ──────────────────────────── */
    const addressCards = document.querySelectorAll('.address-card');
    const addrRadios   = document.querySelectorAll('input[name="address_id"]');

    function updateAddressSelection() {
        addressCards.forEach(card => {
            const radio = card.querySelector('input[type="radio"]');
            if (radio) {
                card.classList.toggle('selected', radio.checked);
            }
        });
    }

    addrRadios.forEach(radio => {
        radio.addEventListener('change', updateAddressSelection);
    });

    // Run once on load to reflect pre-checked default
    updateAddressSelection();


    /* ── Pay button guard & loading state ───────────────── */
    const form    = document.getElementById('checkout-form');
    const payBtn  = document.getElementById('pay-btn');

    if (form && payBtn) {
        form.addEventListener('submit', function (e) {

            // Prevent if button is disabled (cart empty)
            if (payBtn.disabled || payBtn.classList.contains('disabled')) {
                e.preventDefault();
                return;
            }

            // Check address selected
            const selectedAddr = document.querySelector('input[name="address_id"]:checked');
            const hasAddressSection = document.querySelector('.address-grid');

            if (hasAddressSection && !selectedAddr) {
                e.preventDefault();
                showAddressWarning();
                return;
            }

            // Show loading state on button
            payBtn.disabled = true;
            payBtn.innerHTML = `
                <svg class="spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M21 12a9 9 0 1 1-6.22-8.56"/>
                </svg>
                Processing…
            `;
        });
    }


    /* ── Address warning toast ───────────────────────────── */
    function showAddressWarning() {
        const existing = document.getElementById('jf-toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'jf-toast';
        toast.textContent = 'Please select a delivery address.';
        toast.style.cssText = `
            position: fixed;
            bottom: 28px;
            left: 50%;
            transform: translateX(-50%) translateY(10px);
            background: #2a1a1a;
            border: 1px solid rgba(224,112,112,.35);
            color: #e07070;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            padding: 11px 22px;
            border-radius: 8px;
            z-index: 9999;
            opacity: 0;
            transition: opacity .25s, transform .25s;
            pointer-events: none;
        `;
        document.body.appendChild(toast);

        requestAnimationFrame(() => {
            toast.style.opacity  = '1';
            toast.style.transform = 'translateX(-50%) translateY(0)';
        });

        setTimeout(() => {
            toast.style.opacity   = '0';
            toast.style.transform = 'translateX(-50%) translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }, 3000);

        // Scroll to address section
        const section = document.getElementById('section-address');
        if (section) section.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }


    /* ── Spin animation for loading state ───────────────── */
    const spinStyle = document.createElement('style');
    spinStyle.textContent = `
        @keyframes jf-spin {
            to { transform: rotate(360deg); }
        }
        .spin { animation: jf-spin .7s linear infinite; }
    `;
    document.head.appendChild(spinStyle);

})();