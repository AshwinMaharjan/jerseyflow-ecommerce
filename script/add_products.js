/**
 * add_products.js
 * Frontend validation + UI behaviour for add_products.php
 */

'use strict';

// ── DOM references ────────────────────────────────────────────────────────
const form          = document.getElementById('addProductForm');
const priceInput    = document.getElementById('price');
const stockInput    = document.getElementById('stock');
const clubSel       = document.getElementById('club_id');
const countrySel    = document.getElementById('country_id');
const imageInput    = document.getElementById('images');          // updated id
const imagePreview  = document.getElementById('imagePreview');
const uploadBox     = document.getElementById('imageUploadBox');
const placeholder   = document.getElementById('uploadPlaceholder');
const removeBtn     = document.getElementById('removeImage');
const resetBtn      = document.getElementById('resetBtn');

// ── Helper: show / hide a field error ────────────────────────────────────
function showError(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.style.display = msg ? 'block' : 'none';
}

// ── Price validation (Rs. 500 – 20,000) ──────────────────────────────────
function validatePrice() {
    const raw = priceInput.value.trim();
    if (!raw) {
        showError('price-error', '');
        priceInput.setCustomValidity('');
        return true;
    }
    const v = parseFloat(raw);
    if (isNaN(v) || v < 500 || v > 20000) {
        const msg = 'Price must be between Rs. 500 and Rs. 20,000.';
        showError('price-error', msg);
        priceInput.setCustomValidity(msg);
        return false;
    }
    showError('price-error', '');
    priceInput.setCustomValidity('');
    return true;
}

// ── Stock validation (whole number, 0 – 500) ──────────────────────────────
function validateStock() {
    const raw = stockInput.value.trim();
    if (!raw) {
        showError('stock-error', '');
        stockInput.setCustomValidity('');
        return true;
    }
    const v = parseInt(raw, 10);
    if (!/^\d+$/.test(raw) || v < 0 || v > 500) {
        const msg = 'Stock must be a whole number between 0 and 500.';
        showError('stock-error', msg);
        stockInput.setCustomValidity(msg);
        return false;
    }
    showError('stock-error', '');
    stockInput.setCustomValidity('');
    return true;
}

// ── Club / Country: at least one required ────────────────────────────────
function validateClubCountry() {
    const ok  = clubSel.value || countrySel.value;
    const msg = ok ? '' : 'Select at least one — a Club or a Country.';
    showError('club-country-error', msg);
    clubSel.setCustomValidity(msg);
    countrySel.setCustomValidity(msg);
    return !!ok;
}

// ── Attach real-time listeners ────────────────────────────────────────────
priceInput.addEventListener('input', validatePrice);
priceInput.addEventListener('blur',  validatePrice);

stockInput.addEventListener('input', validateStock);
stockInput.addEventListener('blur',  validateStock);

clubSel.addEventListener('change',    validateClubCountry);
countrySel.addEventListener('change', validateClubCountry);

// ── Block submit if any validation fails ──────────────────────────────────
form.addEventListener('submit', function (e) {
    const priceOk       = validatePrice();
    const stockOk       = validateStock();
    const clubCountryOk = validateClubCountry();

    if (!priceOk || !stockOk || !clubCountryOk) {
        e.preventDefault();
        const firstError = form.querySelector('.field-error[style*="block"]');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});

// ── Live Summary ──────────────────────────────────────────────────────────
const summaryFields = [
    { inputId: 'product_name', outputId: 'sum-name' },
    { inputId: 'price',        outputId: 'sum-price',   prefix: 'Rs. ' },
    { inputId: 'stock',        outputId: 'sum-stock',   suffix: ' pcs' },
    { inputId: 'club_id',      outputId: 'sum-club',    isSelect: true },
    { inputId: 'country_id',   outputId: 'sum-country', isSelect: true },
    { inputId: 'size_id',      outputId: 'sum-size',    isSelect: true },
    { inputId: 'kit_id',       outputId: 'sum-kit',     isSelect: true },
];

summaryFields.forEach(({ inputId, outputId, prefix = '', suffix = '', isSelect }) => {
    const el  = document.getElementById(inputId);
    const out = document.getElementById(outputId);
    if (!el || !out) return;

    const update = () => {
        let val = '';
        if (isSelect) {
            const opt = el.options[el.selectedIndex];
            val = (opt && !opt.value) ? '' : (opt ? opt.text : '');
        } else {
            val = el.value.trim();
        }
        out.textContent = val ? prefix + val + suffix : '—';
    };

    el.addEventListener('input',  update);
    el.addEventListener('change', update);
    update();
});

// ── Multi-Image Preview ───────────────────────────────────────────────────

// Renders the primary preview + thumbnail strip
function renderPreviews(files) {
    if (!files || files.length === 0) return;

    // Show first image as the main preview
    const reader = new FileReader();
    reader.onload = (e) => {
        imagePreview.src = e.target.result;
        imagePreview.classList.remove('hidden');
        placeholder.classList.add('hidden');
        removeBtn.classList.remove('hidden');
    };
    reader.readAsDataURL(files[0]);

    // Update placeholder text with count + primary badge
    updateCountBadge(files.length);

    // Render thumbnail strip
    renderThumbnails(files);
}

// Shows how many images are selected
function updateCountBadge(count) {
    let badge = document.getElementById('image-count-badge');
    if (!badge) {
        badge = document.createElement('p');
        badge.id = 'image-count-badge';
        badge.style.cssText = 'margin:8px 0 0;font-size:0.82rem;color:var(--text-muted,#888);text-align:center;';
        uploadBox.after(badge);
    }
    badge.textContent = count === 1
        ? '1 image selected  (primary)'
        : `${count} images selected  ·  first = primary, rest = gallery`;
}

// Builds a small thumbnail row below the main preview
function renderThumbnails(files) {
    // Remove any existing strip
    const existing = document.getElementById('thumb-strip');
    if (existing) existing.remove();
    if (files.length <= 1) return;

    const strip = document.createElement('div');
    strip.id = 'thumb-strip';
    strip.style.cssText = [
        'display:flex',
        'flex-wrap:wrap',
        'gap:6px',
        'margin-top:10px',
    ].join(';');

    Array.from(files).forEach((file, idx) => {
        const wrapper = document.createElement('div');
        wrapper.style.cssText = 'position:relative;width:64px;height:64px;flex-shrink:0;';

        const img = document.createElement('img');
        img.style.cssText = 'width:100%;height:100%;object-fit:cover;border-radius:6px;border:2px solid ' +
            (idx === 0 ? 'var(--accent,#4f8ef7)' : '#ddd') + ';';
        img.title = idx === 0 ? 'Primary image' : 'Gallery image ' + idx;

        const r = new FileReader();
        r.onload = (e) => { img.src = e.target.result; };
        r.readAsDataURL(file);

        // Primary badge on first thumb
        if (idx === 0) {
            const badge = document.createElement('span');
            badge.textContent = 'Primary';
            badge.style.cssText = [
                'position:absolute',
                'bottom:2px',
                'left:50%',
                'transform:translateX(-50%)',
                'background:var(--accent,#4f8ef7)',
                'color:#fff',
                'font-size:9px',
                'font-weight:700',
                'padding:1px 4px',
                'border-radius:3px',
                'white-space:nowrap',
            ].join(';');
            wrapper.appendChild(badge);
        }

        wrapper.appendChild(img);
        strip.appendChild(wrapper);
    });

    uploadBox.after(strip);
}

function clearPreview() {
    imageInput.value = '';
    imagePreview.src = '';
    imagePreview.classList.add('hidden');
    placeholder.classList.remove('hidden');
    removeBtn.classList.add('hidden');

    // Remove thumbnail strip and count badge
    const strip = document.getElementById('thumb-strip');
    if (strip) strip.remove();
    const badge = document.getElementById('image-count-badge');
    if (badge) badge.remove();
}

// File input change
imageInput.addEventListener('change', function () {
    if (this.files.length > 0) {
        renderPreviews(this.files);
    }
});

removeBtn.addEventListener('click', clearPreview);

// ── Drag & drop (supports multiple files) ────────────────────────────────
uploadBox.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadBox.classList.add('drag-over');
});

uploadBox.addEventListener('dragleave', () => {
    uploadBox.classList.remove('drag-over');
});

uploadBox.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadBox.classList.remove('drag-over');

    const droppedFiles = e.dataTransfer.files;
    if (!droppedFiles.length) return;

    // Merge dropped files into the input
    const dt = new DataTransfer();
    // Keep existing files if any, then add dropped ones
    Array.from(imageInput.files).forEach(f => dt.items.add(f));
    Array.from(droppedFiles).forEach(f => {
        // Only add allowed image types
        if (['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) {
            dt.items.add(f);
        }
    });

    imageInput.files = dt.files;
    if (imageInput.files.length > 0) {
        renderPreviews(imageInput.files);
    }
});

// ── Reset button ──────────────────────────────────────────────────────────
resetBtn.addEventListener('click', () => {
    setTimeout(() => {
        clearPreview();

        ['price-error', 'stock-error', 'club-country-error'].forEach(id => showError(id, ''));

        [priceInput, stockInput, clubSel, countrySel].forEach(el => {
            el.setCustomValidity('');
        });

        summaryFields.forEach(({ outputId }) => {
            const out = document.getElementById(outputId);
            if (out) out.textContent = '—';
        });
    }, 0);
});

imageInput.addEventListener("change", function () {
    if (this.files.length > 4) {
        alert("You can upload maximum 4 images only.");
        this.value = "";
    }
});