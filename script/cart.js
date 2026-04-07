/**
 * cart.js — JerseyFlow
 * Handles: qty update, remove, clear cart, item selection, dynamic subtotal
 */

(function () {
    'use strict';

    // ── Element refs ──────────────────────────────────────────
    const cartItemsEl       = document.getElementById('cartItems');
    const toastWrap         = document.getElementById('toastWrap');
    const clearCartBtn      = document.getElementById('clearCartBtn');
    const summarySubtotal   = document.getElementById('summarySubtotal');
    const summaryTotal      = document.getElementById('summaryTotal');
    const summaryItemLabel  = document.getElementById('summaryItemLabel');
    const checkoutBtn       = document.getElementById('checkoutBtn');
    const checkoutForm      = document.getElementById('checkoutForm');
    const selectedInput     = document.getElementById('selectedCartIdsInput');
    const selectAllCb       = document.getElementById('selectAllCheckbox');
    const selectedCountLabel= document.getElementById('selectedCountLabel');
    const noSelectionMsg    = document.getElementById('noSelectionMsg');

    // ── Confirmation Modal ────────────────────────────────────
    // Inject modal HTML once into the page
    const modalHTML = `
        <div class="jf-modal-overlay" id="jfModalOverlay">
            <div class="jf-modal">
                <div class="jf-modal-icon"><i class="fa-solid fa-trash"></i></div>
                <h3 class="jf-modal-title" id="jfModalTitle">Clear Cart</h3>
                <p class="jf-modal-body" id="jfModalBody">Remove all items from your cart?</p>
                <div class="jf-modal-actions">
                    <button class="jf-modal-cancel" id="jfModalCancel">Cancel</button>
                    <button class="jf-modal-confirm" id="jfModalConfirm">Yes, Remove All</button>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    const modalOverlay  = document.getElementById('jfModalOverlay');
    const modalTitle    = document.getElementById('jfModalTitle');
    const modalBody     = document.getElementById('jfModalBody');
    const modalCancel   = document.getElementById('jfModalCancel');
    const modalConfirm  = document.getElementById('jfModalConfirm');

    let _modalResolve = null;

    function showConfirmModal(title, body, confirmLabel = 'Confirm') {
        modalTitle.textContent   = title;
        modalBody.textContent    = body;
        modalConfirm.textContent = confirmLabel;
        modalOverlay.classList.add('active');
        return new Promise(resolve => { _modalResolve = resolve; });
    }

    function closeModal(result) {
        modalOverlay.classList.remove('active');
        if (_modalResolve) { _modalResolve(result); _modalResolve = null; }
    }

    modalCancel.addEventListener('click',  () => closeModal(false));
    modalConfirm.addEventListener('click', () => closeModal(true));
    modalOverlay.addEventListener('click', e => {
        if (e.target === modalOverlay) closeModal(false);
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && modalOverlay.classList.contains('active')) closeModal(false);
    });

    // ── Toast ─────────────────────────────────────────────────
    function showToast(message, type = 'success') {
        const t = document.createElement('div');
        t.className = `toast toast-${type}`;
        t.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i> ${message}`;
        toastWrap.appendChild(t);
        setTimeout(() => {
            t.style.animation = 'toastOut .28s ease forwards';
            t.addEventListener('animationend', () => t.remove());
        }, 3000);
    }

    // ── Format currency ───────────────────────────────────────
    function fmt(n) {
        return 'Rs. ' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // ── Get all item checkboxes ───────────────────────────────
    function getCheckboxes() {
        return [...document.querySelectorAll('.item-checkbox')];
    }

    // ── Recalculate subtotal from selected + checked items ────
    function recalcSummary() {
        const checkboxes = getCheckboxes();
        let total        = 0;
        let selectedCount = 0;

        checkboxes.forEach(cb => {
            const cartId  = cb.dataset.cartId;
            const itemEl  = document.getElementById(`cartItem-${cartId}`);
            if (!itemEl) return;

            const qty     = parseInt(document.getElementById(`qty-${cartId}`)?.textContent ?? '1', 10);
            const price   = parseFloat(itemEl.dataset.price ?? '0');
            const lineTotal = price * qty;

            if (cb.checked) {
                total += lineTotal;
                selectedCount++;
            }

            itemEl.classList.toggle('item-deselected', !cb.checked);
        });

        const total_items = checkboxes.length;

        if (summarySubtotal)  summarySubtotal.textContent  = fmt(total);
        if (summaryTotal)     summaryTotal.textContent     = fmt(total);
        if (summaryItemLabel) summaryItemLabel.textContent =
            `Subtotal (${selectedCount} ${selectedCount === 1 ? 'item' : 'items'})`;
        if (selectedCountLabel) selectedCountLabel.textContent =
            `${selectedCount} of ${total_items} selected`;

        const selectedIds = checkboxes
            .filter(cb => cb.checked)
            .map(cb => cb.dataset.cartId);
        if (selectedInput) selectedInput.value = selectedIds.join(',');

        const hasSelection = selectedCount > 0;
        if (checkoutBtn) {
            checkoutBtn.disabled = !hasSelection;
            checkoutBtn.classList.toggle('btn-checkout--disabled', !hasSelection);
        }
        if (noSelectionMsg) noSelectionMsg.style.display = hasSelection ? 'none' : 'flex';

        if (selectAllCb) {
            if (selectedCount === 0) {
                selectAllCb.indeterminate = false;
                selectAllCb.checked       = false;
            } else if (selectedCount === total_items) {
                selectAllCb.indeterminate = false;
                selectAllCb.checked       = true;
            } else {
                selectAllCb.indeterminate = true;
            }
        }
    }

    // ── Select All / Deselect All ─────────────────────────────
    if (selectAllCb) {
        selectAllCb.addEventListener('change', () => {
            getCheckboxes().forEach(cb => { cb.checked = selectAllCb.checked; });
            recalcSummary();
        });
    }

    // ── Item checkbox change ──────────────────────────────────
    document.addEventListener('change', e => {
        if (e.target.classList.contains('item-checkbox')) recalcSummary();
    });

    // ── Checkout form validation ──────────────────────────────
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', e => {
            const ids = selectedInput?.value ?? '';
            if (!ids) {
                e.preventDefault();
                showToast('Please select at least one item to checkout.', 'error');
                if (noSelectionMsg) noSelectionMsg.style.display = 'flex';
            }
        });
    }

    // ── AJAX helper ───────────────────────────────────────────
    // Uses an absolute path from the site root so it works regardless
    // of which subdirectory the current page is served from.
    const API_BASE = '/jerseyflow-ecommerce/api';

    async function apiPost(endpoint, data) {
        const res = await fetch(`${API_BASE}/${endpoint}`, {
            method:  'POST',
            headers: {
                'Content-Type':     'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(data),
        });

        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch {
            // Server returned non-JSON (e.g. a PHP error/HTML page)
            console.error(`Non-JSON response from ${endpoint}:`, text);
            return { success: false, message: 'Server error. Check console for details.' };
        }
    }

    // ── Update quantity ───────────────────────────────────────
    async function updateQty(cartId, delta) {
        const qtyEl  = document.getElementById(`qty-${cartId}`);
        const itemEl = document.getElementById(`cartItem-${cartId}`);
        const incBtn = itemEl?.querySelector('.qty-inc');
        if (!qtyEl || !itemEl) return;

        const currentQty = parseInt(qtyEl.textContent, 10);
        const stock      = parseInt(incBtn?.dataset.stock ?? '99', 10);
        const newQty     = currentQty + delta;

        if (newQty < 1)     { removeItem(cartId); return; }
        if (newQty > stock) { showToast('Not enough stock available.', 'error'); return; }

        try {
            const res = await apiPost('cart_update.php', { cart_id: cartId, quantity: newQty });
            if (res.success) {
                qtyEl.textContent = newQty;
                itemEl.dataset.quantity = newQty;

                const price  = parseFloat(itemEl.dataset.price ?? '0');
                const lineEl = document.getElementById(`lineTotal-${cartId}`);
                if (lineEl) lineEl.textContent = fmt(price * newQty);

                recalcSummary();
            } else {
                showToast(res.message ?? 'Failed to update quantity.', 'error');
            }
        } catch (err) {
            console.error('updateQty error:', err);
            showToast('Network error. Please try again.', 'error');
        }
    }

    // ── Remove item ───────────────────────────────────────────
    async function removeItem(cartId) {
        const itemEl = document.getElementById(`cartItem-${cartId}`);
        if (!itemEl) return;

        try {
            const res = await apiPost('cart_remove.php', { cart_id: cartId });
            if (res.success) {
                itemEl.classList.add('removing');
                itemEl.addEventListener('transitionend', () => {
                    itemEl.remove();
                    recalcSummary();
                    if (!document.querySelector('.cart-item')) window.location.reload();
                }, { once: true });
            } else {
                showToast(res.message ?? 'Could not remove item.', 'error');
            }
        } catch (err) {
            console.error('removeItem error:', err);
            showToast('Network error. Please try again.', 'error');
        }
    }

    // ── Clear cart ────────────────────────────────────────────
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', async () => {
            const confirmed = await showConfirmModal(
                'Clear Cart',
                'Are you sure you want to remove all items from your cart?',
                'Yes, Clear Cart'
            );
            if (!confirmed) return;

            try {
                const res = await apiPost('cart_clear.php', {});
                if (res.success) {
                    window.location.reload();
                } else {
                    showToast(res.message ?? 'Could not clear cart.', 'error');
                }
            } catch (err) {
                console.error('clearCart error:', err);
                showToast('Network error. Please try again.', 'error');
            }
        });
    }

    // ── Event delegation for qty + remove buttons ─────────────
    if (cartItemsEl) {
        cartItemsEl.addEventListener('click', e => {
            const btn    = e.target.closest('button');
            if (!btn) return;
            const cartId = btn.dataset.cartId;
            if (!cartId) return;

            if (btn.classList.contains('qty-inc'))    updateQty(cartId, +1);
            if (btn.classList.contains('qty-dec'))    updateQty(cartId, -1);
            if (btn.classList.contains('btn-remove')) removeItem(cartId);
        });
    }

    // ── Init summary on page load ─────────────────────────────
    recalcSummary();

})();