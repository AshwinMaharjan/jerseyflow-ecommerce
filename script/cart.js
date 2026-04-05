/* ══════════════════════════════════════════════════════════════
   cart.js  —  JerseyFlow  |  Shopping Cart Page
   Place this file at:  js/cart.js
══════════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {

    /* ── Helpers ───────────────────────────────────────────── */
    function fmt(str) {
        // str comes from PHP number_format, just prefix
        return 'Rs. ' + str;
    }

    function showToast(message, type = 'success') {
        const wrap = document.getElementById('toastWrap');
        if (!wrap) return;
        const icon  = type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check';
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<i class="fa-solid ${icon}"></i><span>${message}</span>`;
        wrap.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'toastOut .28s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    async function postAction(payload) {
        const fd = new FormData();
        Object.entries(payload).forEach(([k, v]) => fd.append(k, v));
        const res  = await fetch('cart_actions.php', { method: 'POST', body: fd });
        return res.json();
    }

    /** Update navbar cart count badge if element exists */
    function setCartCount(n) {
        const el = document.getElementById('cartCount');
        if (el) el.textContent = n;
    }

    /** Update the summary subtotal + total display */
    function setSummaryTotal(formattedTotal) {
        const sub = document.getElementById('summarySubtotal');
        const tot = document.getElementById('summaryTotal');
        if (sub) sub.textContent = fmt(formattedTotal);
        if (tot) tot.textContent = fmt(formattedTotal);
    }

    /* ────────────────────────────────────────────────────────
       REMOVE ITEM
    ──────────────────────────────────────────────────────── */
    document.querySelectorAll('.btn-remove').forEach(btn => {
        btn.addEventListener('click', async () => {
            const cartId = btn.dataset.cartId;
            const row    = document.getElementById(`cartItem-${cartId}`);

            btn.disabled = true;
            try {
                const data = await postAction({ action: 'remove', cart_id: cartId });
                if (data.success) {
                    // Animate out then remove DOM node
                    row?.classList.add('removing');
                    setTimeout(() => {
                        row?.remove();
                        setSummaryTotal(data.cart_total);
                        setCartCount(data.cart_count);
                        // If no more items, reload to show empty state
                        if (document.querySelectorAll('.cart-item').length === 0) {
                            location.reload();
                        }
                    }, 320);
                } else {
                    showToast(data.message ?? 'Could not remove item.', 'error');
                    btn.disabled = false;
                }
            } catch {
                showToast('Something went wrong. Please try again.', 'error');
                btn.disabled = false;
            }
        });
    });

    /* ────────────────────────────────────────────────────────
       QTY DECREMENT
    ──────────────────────────────────────────────────────── */
    document.querySelectorAll('.qty-dec').forEach(btn => {
        btn.addEventListener('click', async () => {
            const cartId     = btn.dataset.cartId;
            const qtyDisplay = document.getElementById(`qty-${cartId}`);
            const current    = parseInt(qtyDisplay?.textContent ?? '1', 10);

            // If qty is 1, ask to remove instead
            if (current <= 1) {
                const removeBtn = document.querySelector(`.btn-remove[data-cart-id="${cartId}"]`);
                removeBtn?.click();
                return;
            }

            const newQty = current - 1;
            btn.disabled = true;
            try {
                const data = await postAction({ action: 'update', cart_id: cartId, qty: newQty });
                if (data.success) {
                    if (qtyDisplay) qtyDisplay.textContent = newQty;
                    const lineEl = document.getElementById(`lineTotal-${cartId}`);
                    if (lineEl) lineEl.textContent = fmt(data.line_total);
                    setSummaryTotal(data.cart_total);
                    setCartCount(data.cart_count);
                } else {
                    showToast(data.message ?? 'Could not update qty.', 'error');
                }
            } catch {
                showToast('Something went wrong.', 'error');
            } finally {
                btn.disabled = false;
            }
        });
    });

    /* ────────────────────────────────────────────────────────
       QTY INCREMENT
    ──────────────────────────────────────────────────────── */
    document.querySelectorAll('.qty-inc').forEach(btn => {
        btn.addEventListener('click', async () => {
            const cartId     = btn.dataset.cartId;
            const stock      = parseInt(btn.dataset.stock ?? '99', 10);
            const qtyDisplay = document.getElementById(`qty-${cartId}`);
            const current    = parseInt(qtyDisplay?.textContent ?? '1', 10);

            if (current >= 10) {
                showToast('Maximum order quantity is 10.', 'error');
                return;
            }
            if (current >= stock) {
                showToast(`Only ${stock} units available in stock.`, 'error');
                return;
            }

            const newQty = current + 1;
            btn.disabled = true;
            try {
                const data = await postAction({ action: 'update', cart_id: cartId, qty: newQty });
                if (data.success) {
                    if (qtyDisplay) qtyDisplay.textContent = newQty;
                    const lineEl = document.getElementById(`lineTotal-${cartId}`);
                    if (lineEl) lineEl.textContent = fmt(data.line_total);
                    setSummaryTotal(data.cart_total);
                    setCartCount(data.cart_count);
                } else {
                    showToast(data.message ?? 'Could not update qty.', 'error');
                }
            } catch {
                showToast('Something went wrong.', 'error');
            } finally {
                btn.disabled = false;
            }
        });
    });

    /* ────────────────────────────────────────────────────────
       CLEAR CART
    ──────────────────────────────────────────────────────── */
    const clearBtn = document.getElementById('clearCartBtn');
    clearBtn?.addEventListener('click', async () => {
        if (!confirm('Are you sure you want to remove all items from your cart?')) return;

        clearBtn.disabled = true;
        try {
            const data = await postAction({ action: 'clear' });
            if (data.success) {
                location.reload();
            } else {
                showToast(data.message ?? 'Could not clear cart.', 'error');
                clearBtn.disabled = false;
            }
        } catch {
            showToast('Something went wrong.', 'error');
            clearBtn.disabled = false;
        }
    });

});