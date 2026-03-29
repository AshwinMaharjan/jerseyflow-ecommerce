// ── Live Summary ──────────────────────────────────────────────────────────
const fields = {
    name:  { input: 'product_name', output: 'sum-name' },
    price: { input: 'price',        output: 'sum-price', prefix: 'Rs. ' },
    stock: { input: 'stock',        output: 'sum-stock', suffix: ' pcs' },
    club:  { input: 'club_id',      output: 'sum-club',  isSelect: true },
    size:  { input: 'size_id',      output: 'sum-size',  isSelect: true },
    kit:   { input: 'kit_id',       output: 'sum-kit',   isSelect: true },
};

// Track original values to detect changes
const originalValues = {};
Object.entries(fields).forEach(([key, { input }]) => {
    const el = document.getElementById(input);
    if (el) originalValues[key] = el.value;
});

const changesIndicator = document.getElementById('changesIndicator');

function checkChanges() {
    const hasChange = Object.entries(fields).some(([key, { input }]) => {
        const el = document.getElementById(input);
        return el && el.value !== originalValues[key];
    });
    changesIndicator.classList.toggle('hidden', !hasChange);
}

Object.values(fields).forEach(({ input, output, prefix = '', suffix = '', isSelect }) => {
    const el  = document.getElementById(input);
    const out = document.getElementById(output);
    if (!el || !out) return;

    const update = () => {
        const val = isSelect
            ? (el.options[el.selectedIndex]?.text || '—')
            : el.value.trim();
        out.textContent = val ? prefix + val + suffix : '—';
        checkChanges();
    };

    el.addEventListener('input', update);
    el.addEventListener('change', update);
});

// ── Image Preview ─────────────────────────────────────────────────────────
const imageInput  = document.getElementById('image');
const imagePreview = document.getElementById('imagePreview');
const uploadBox   = document.getElementById('imageUploadBox');
const placeholder = document.getElementById('uploadPlaceholder');
const removeBtn   = document.getElementById('removeImage');
const removeFlag  = document.getElementById('removeImageFlag');
const currentNote = document.getElementById('currentImageNote');

imageInput.addEventListener('change', function () {
    const file = this.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = e => {
        imagePreview.src = e.target.result;
        imagePreview.classList.remove('hidden');
        placeholder.classList.add('hidden');
        removeBtn.classList.remove('hidden');
        removeFlag.value = '0';
        if (currentNote) currentNote.style.display = 'none';
        changesIndicator.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
});

removeBtn.addEventListener('click', () => {
    imageInput.value = '';
    imagePreview.src = '';
    imagePreview.classList.add('hidden');
    placeholder.classList.remove('hidden');
    removeBtn.classList.add('hidden');
    removeFlag.value = '1';
    if (currentNote) currentNote.style.display = 'none';
    changesIndicator.classList.remove('hidden');
});

// Drag & drop
uploadBox.addEventListener('dragover', e => { e.preventDefault(); uploadBox.classList.add('drag-over'); });
uploadBox.addEventListener('dragleave', () => uploadBox.classList.remove('drag-over'));
uploadBox.addEventListener('drop', e => {
    e.preventDefault();
    uploadBox.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        const dt = new DataTransfer();
        dt.items.add(file);
        imageInput.files = dt.files;
        imageInput.dispatchEvent(new Event('change'));
    }
});

// ── Auto-dismiss success alert ────────────────────────────────────────────
const alertBox = document.getElementById('alertBox');
if (alertBox && alertBox.classList.contains('alert-success')) {
    setTimeout(() => {
        alertBox.style.transition = 'opacity 0.5s';
        alertBox.style.opacity = '0';
        setTimeout(() => alertBox.remove(), 500);
    }, 3500);
}

// ── Warn on unsaved changes before leaving ────────────────────────────────
let formSubmitted = false;
document.getElementById('editProductForm').addEventListener('submit', () => {
    formSubmitted = true;
});

window.addEventListener('beforeunload', (e) => {
    if (!formSubmitted && !changesIndicator.classList.contains('hidden')) {
        e.preventDefault();
        e.returnValue = '';
    }
});
