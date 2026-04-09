/* invoice.js — JerseyFlow */

document.addEventListener('DOMContentLoaded', () => {

    /* ── Stagger-animate item rows on load ── */
    const rows = document.querySelectorAll('.item-row');
    rows.forEach((row, i) => {
        row.style.opacity = '0';
        row.style.transform = 'translateX(-8px)';
        row.style.transition = `opacity .3s ease ${0.05 * i + 0.1}s, transform .3s ease ${0.05 * i + 0.1}s`;
        requestAnimationFrame(() => {
            row.style.opacity = '1';
            row.style.transform = 'translateX(0)';
        });
    });

    /* ── Print button keyboard shortcut (Ctrl/Cmd + P already works natively) ── */
    /* ── Copy order number on click ── */
    const invoiceNum = document.querySelector('.invoice-num');
    if (invoiceNum) {
        invoiceNum.style.cursor = 'pointer';
        invoiceNum.title = 'Click to copy';

        invoiceNum.addEventListener('click', () => {
            const text = invoiceNum.textContent.trim();
            navigator.clipboard?.writeText(text).then(() => {
                const original = invoiceNum.textContent;
                invoiceNum.textContent = 'Copied!';
                invoiceNum.style.color = '#4ade80';
                setTimeout(() => {
                    invoiceNum.textContent = original;
                    invoiceNum.style.color = '';
                }, 1500);
            });
        });
    }

});