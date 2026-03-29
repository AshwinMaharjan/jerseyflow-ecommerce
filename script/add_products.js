/**
 * add_products.js
 * Frontend validation + UI behaviour for add_products.php
 * Place at:  ../js/add_products.js  (relative to admin pages)
 */

'use strict';

// ── DOM references ────────────────────────────────────────────────────────
const form        = document.getElementById('addProductForm');
const priceInput  = document.getElementById('price');
const stockInput  = document.getElementById('stock');
const clubSel     = document.getElementById('club_id');
const countrySel  = document.getElementById('country_id');
const imageInput  = document.getElementById('image');
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
        return true; // let 'required' handle empty
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
    // Reject decimals, negatives, and out-of-range values
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
    const ok = clubSel.value || countrySel.value;
    const msg = ok ? '' : 'Select at least one — a Club or a Country.';
    showError('club-country-error', msg);
    // Apply custom validity to both so the browser won't submit
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
    const priceOk        = validatePrice();
    const stockOk        = validateStock();
    const clubCountryOk  = validateClubCountry();

    if (!priceOk || !stockOk || !clubCountryOk) {
        e.preventDefault();

        // Scroll to the first visible error
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
            // Ignore placeholder options (those starting with "--")
            val = (opt && !opt.value) ? '' : (opt ? opt.text : '');
        } else {
            val = el.value.trim();
        }
        out.textContent = val ? prefix + val + suffix : '—';
    };

    el.addEventListener('input',  update);
    el.addEventListener('change', update);
    update(); // initialise on load (for POST repopulation)
});

// ── Image Preview ─────────────────────────────────────────────────────────
function showPreview(file) {
    if (!file || !file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        imagePreview.src = e.target.result;
        imagePreview.classList.remove('hidden');
        placeholder.classList.add('hidden');
        removeBtn.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}

function clearPreview() {
    imageInput.value  = '';
    imagePreview.src  = '';
    imagePreview.classList.add('hidden');
    placeholder.classList.remove('hidden');
    removeBtn.classList.add('hidden');
}

imageInput.addEventListener('change', function () {
    if (this.files[0]) showPreview(this.files[0]);
});

removeBtn.addEventListener('click', clearPreview);

// Drag & drop support
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
    const file = e.dataTransfer.files[0];
    if (file) {
        // Inject the dropped file into the real input
        const dt = new DataTransfer();
        dt.items.add(file);
        imageInput.files = dt.files;
        showPreview(file);
    }
});

// ── Reset button: clear preview + errors + summary ────────────────────────
resetBtn.addEventListener('click', () => {
    // Defer until after the native reset has fired
    setTimeout(() => {
        clearPreview();

        // Clear all inline errors
        ['price-error', 'stock-error', 'club-country-error'].forEach(id => showError(id, ''));

        // Reset custom validity flags
        [priceInput, stockInput, clubSel, countrySel].forEach(el => {
            el.setCustomValidity('');
        });

        // Reset summary spans
        summaryFields.forEach(({ outputId }) => {
            const out = document.getElementById(outputId);
            if (out) out.textContent = '—';
        });
    }, 0);
});