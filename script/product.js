/* ══════════════════════════════════════════════════════════════
   product.js  —  JerseyFlow  |  Product Detail Page
   Place this file at:  js/product.js
══════════════════════════════════════════════════════════════ */

document.addEventListener('DOMContentLoaded', () => {

    /* ── Helpers ───────────────────────────────────────────── */
    function fmt(num) {
        return 'Rs. ' + num.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function showToast(message, type = 'error') {
        const wrap = document.getElementById('toastWrap');
        if (!wrap) return;

        const icon  = type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check';
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `<i class="fa-solid ${icon}"></i><span>${message}</span>`;
        wrap.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'toastOut .28s ease forwards';
            setTimeout(() => toast.remove(), 280);
        }, 4000);
    }

    /* ────────────────────────────────────────────────────────
       1. GALLERY — thumbnail click swaps main image
    ──────────────────────────────────────────────────────── */
    const mainImg = document.getElementById('mainImg');
    const thumbs  = document.querySelectorAll('.thumb');

    thumbs.forEach(thumb => {
        thumb.addEventListener('click', () => {
            const newSrc = thumb.dataset.src;
            if (!mainImg) return;

            mainImg.classList.add('fade');
            setTimeout(() => {
                mainImg.src = newSrc;
                mainImg.onload = () => mainImg.classList.remove('fade');
                setTimeout(() => mainImg.classList.remove('fade'), 280);
            }, 180);

            thumbs.forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
        });
    });

    /* ────────────────────────────────────────────────────────
       2. SIZE SELECTION — with availability check
    ──────────────────────────────────────────────────────── */
    const sizeBtns   = document.querySelectorAll('.size-btn');
    const sizeError  = document.getElementById('sizeError');
    let   selectedSize = null;

    sizeBtns.forEach(btn => {
        btn.addEventListener('click', () => {

            // Block unavailable sizes
            if (btn.dataset.available === '0') {
                showToast(`Size ${btn.dataset.size} is not available for this jersey.`, 'error');
                return;
            }

            // Toggle selection
            sizeBtns.forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            selectedSize = btn.dataset.size;
            if (sizeError) sizeError.style.display = 'none';
        });
    });

    /* ────────────────────────────────────────────────────────
       3. QTY — dynamic price breakdown in Price Detail box
    ──────────────────────────────────────────────────────── */
    const qtySelect    = document.getElementById('qtySelect');
    const displayPrice = document.getElementById('displayPrice');
    const detailLabel  = document.getElementById('detailLabel');
    const detailPrice  = document.getElementById('detailPrice');
    const totalRow     = document.getElementById('totalRow');
    const totalPrice   = document.getElementById('totalPrice');

    function updatePrices() {
        const qty   = parseInt(qtySelect?.value ?? 1, 10);
        const total = BASE_PRICE * qty;

        // Big price always shows total
        if (displayPrice) displayPrice.textContent = fmt(total);

        if (qty > 1) {
            // "2× Product Price  |  Rs. X each"
            if (detailLabel) detailLabel.textContent = `${qty}× Product Price`;
            if (detailPrice) detailPrice.textContent  = fmt(BASE_PRICE);   // unit price
            // Show total row
            if (totalRow)  totalRow.style.display  = 'flex';
            if (totalPrice) totalPrice.textContent = fmt(total);
        } else {
            if (detailLabel) detailLabel.textContent = 'Product Price';
            if (detailPrice) detailPrice.textContent = fmt(BASE_PRICE);
            if (totalRow)    totalRow.style.display  = 'none';
        }
    }

    qtySelect?.addEventListener('change', updatePrices);
    updatePrices(); // initialise on load

    /* ────────────────────────────────────────────────────────
       4. ADD TO CART
    ──────────────────────────────────────────────────────── */
    const cartBtn    = document.getElementById('addToCartBtn');
    const isLoggedIn = cartBtn?.dataset.loggedIn === '1';

    cartBtn?.addEventListener('click', () => {

        // ① Login check
        if (!isLoggedIn) {
            showToast('You must be logged in to add items to your cart.', 'error');
            return;
        }

        // ② Size check
        if (!selectedSize) {
            if (sizeError) sizeError.style.display = 'block';
            showToast('Please select a size before adding to cart.', 'error');
            return;
        }

        // ③ Build payload
        const productId   = cartBtn.dataset.id;
        const productName = cartBtn.dataset.name;
        const qty         = parseInt(qtySelect?.value ?? 1, 10);

        const formData = new FormData();
        formData.append('action',     'add');
        formData.append('product_id', productId);
        formData.append('size',       selectedSize);
        formData.append('qty',        qty);

        // ④ AJAX
        cartBtn.disabled = true;
        cartBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Adding…';

        fetch('cart_actions.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(`Great choice! "${productName}"  is now in your cart!`, 'success');
                    // Update navbar cart count if element exists
                    const cartCount = document.getElementById('cartCount');
                    if (cartCount && data.cart_count !== undefined) {
                        cartCount.textContent = data.cart_count;
                    }
                } else {
                    showToast(data.message ?? 'Could not add to cart. Please try again.', 'error');
                }
            })
            .catch(() => showToast('Something went wrong. Please try again.', 'error'))
            .finally(() => {
                cartBtn.disabled = false;
                cartBtn.innerHTML = '<i class="fa-solid fa-cart-shopping"></i> Add to Cart';
            });
    });

    /* ────────────────────────────────────────────────────────
       5. INFO TABS
    ──────────────────────────────────────────────────────── */
    const tabBtns     = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            tabContents.forEach(tc => {
                tc.classList.toggle('active', tc.id === `tab-${btn.dataset.tab}`);
            });
        });
    });

});