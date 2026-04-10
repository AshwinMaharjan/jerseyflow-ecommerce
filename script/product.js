/* ══════════════════════════════════════════════════════════════
   product.js  —  JerseyFlow  |  Product Detail Page
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
       1. GALLERY
    ──────────────────────────────────────────────────────── */
    const mainImg = document.getElementById('mainImg');
    const thumbs  = document.querySelectorAll('.thumb');

    thumbs.forEach(thumb => {
        thumb.addEventListener('click', () => {
            if (!mainImg) return;
            mainImg.classList.add('fade');
            setTimeout(() => {
                mainImg.src = thumb.dataset.src;
                mainImg.onload = () => mainImg.classList.remove('fade');
                setTimeout(() => mainImg.classList.remove('fade'), 280);
            }, 180);
            thumbs.forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
        });
    });

    /* ────────────────────────────────────────────────────────
       2. SIZE SELECTION — variant-aware
       VARIANTS_BY_SIZE is injected by PHP as:
       { "M": { variant_id, size, color, stock, price }, ... }
    ──────────────────────────────────────────────────────── */
    const sizeBtns  = document.querySelectorAll('.size-btn');
    const sizeError = document.getElementById('sizeError');

    // State
    let selectedSize      = null;
    let selectedVariantId = null;
    let selectedStock     = 0;
    let selectedPrice     = (typeof BASE_PRICE !== 'undefined') ? BASE_PRICE : 0;

    const variantsBySize  = (typeof VARIANTS_BY_SIZE !== 'undefined') ? VARIANTS_BY_SIZE : {};

    // ── Enable/disable size buttons based on variant stock ──
    sizeBtns.forEach(btn => {
        const sz      = btn.dataset.size;
        const variant = variantsBySize[sz];

        if (variant && parseInt(variant.stock, 10) > 0) {
            btn.classList.remove('unavailable');
            btn.dataset.available = '1';
            btn.removeAttribute('title');
        } else {
            btn.classList.add('unavailable');
            btn.dataset.available = '0';
            btn.title = 'Not available in this size';
        }
    });

    // ── Size click ──────────────────────────────────────────
    sizeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.dataset.available === '0') {
                showToast(`Size ${btn.dataset.size} is not available for this jersey.`, 'error');
                return;
            }

            sizeBtns.forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');
            if (sizeError) sizeError.style.display = 'none';

            selectedSize      = btn.dataset.size;
            const variant     = variantsBySize[selectedSize];
            selectedVariantId = variant ? variant.variant_id : null;
            selectedStock     = variant ? parseInt(variant.stock, 10) : 0;
            // Use variant price if available, otherwise fall back to BASE_PRICE
            selectedPrice = (variant && parseFloat(variant.price) > 0) ? parseFloat(variant.price) : BASE_PRICE;

            updatePrices();
        });
    });

    /* ────────────────────────────────────────────────────────
       3. QTY — dynamic price breakdown
    ──────────────────────────────────────────────────────── */
    const qtySelect    = document.getElementById('qtySelect');
    const displayPrice = document.getElementById('displayPrice');
    const detailLabel  = document.getElementById('detailLabel');
    const detailPrice  = document.getElementById('detailPrice');
    const totalRow     = document.getElementById('totalRow');
    const totalPrice   = document.getElementById('totalPrice');

    function updatePrices() {
        const qty   = parseInt(qtySelect?.value ?? 1, 10);
        const price = selectedPrice;  // variant price or BASE_PRICE
        const total = price * qty;

        if (displayPrice) displayPrice.textContent = fmt(qty > 1 ? total : price);

        if (qty > 1) {
            if (detailLabel) detailLabel.textContent  = `${qty}× Product Price`;
            if (detailPrice) detailPrice.textContent  = fmt(price);
            if (totalRow)    totalRow.style.display   = 'flex';
            if (totalPrice)  totalPrice.textContent   = fmt(total);
        } else {
            if (detailLabel) detailLabel.textContent  = 'Product Price';
            if (detailPrice) detailPrice.textContent  = fmt(price);
            if (totalRow)    totalRow.style.display   = 'none';
        }
    }

    qtySelect?.addEventListener('change', updatePrices);
    updatePrices();

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
        if (!selectedSize || !selectedVariantId) {
            if (sizeError) sizeError.style.display = 'block';
            showToast('Please select a size before adding to cart.', 'error');
            return;
        }

        // ③ Build payload — variant_id included
        const productId   = cartBtn.dataset.id;
        const productName = cartBtn.dataset.name;
        const qty         = parseInt(qtySelect?.value ?? 1, 10);

        const formData = new FormData();
        formData.append('action',      'add');
        formData.append('product_id',  productId);
        formData.append('variant_id',  selectedVariantId);  // ← key addition
        formData.append('size',        selectedSize);
        formData.append('qty',         qty);

        // ④ AJAX
        cartBtn.disabled  = true;
        cartBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Adding…';

        fetch('/jerseyflow-ecommerce/users/cart_actions.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(`Great choice! "${productName}" is now in your cart!`, 'success');
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
                cartBtn.disabled  = false;
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