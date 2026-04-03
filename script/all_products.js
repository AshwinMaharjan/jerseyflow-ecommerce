const PRODUCTS = window.PRODUCTS;
const PAGE_DATA = window.PAGE_DATA;

// ── View Modal ────────────────────────────────────────────────────────────
function openViewModal(id) {
    const p = PRODUCTS.find(x => parseInt(x.product_id) === id);
    if (!p) return;

    // Image
    const img   = document.getElementById('vm-image');
    const noImg = document.getElementById('vm-no-image');
    if (p.image) {
        img.src = '../uploads/products/' + p.image;
        img.classList.remove('hidden');
        noImg.classList.add('hidden');
    } else {
        img.src = '';
        img.classList.add('hidden');
        noImg.classList.remove('hidden');
    }

    // Text fields
    document.getElementById('vm-name').textContent  = p.product_name || '—';
    document.getElementById('vm-price').textContent = p.price
        ? 'Rs. ' + parseFloat(p.price).toLocaleString('en-IN', { minimumFractionDigits: 2 })
        : '—';

    // Stock badge
    const stock      = parseInt(p.stock) || 0;
    const stockClass = stock === 0 ? 'stock-out' : (stock <= 5 ? 'stock-low' : 'stock-ok');
    const stockLabel = stock === 0 ? 'Out of Stock' : (stock <= 5 ? 'Low Stock' : 'In Stock');
    document.getElementById('vm-stock').innerHTML =
        `<span class="stock-badge ${stockClass}">${stockLabel}</span> ` +
        `<span style="font-size:12px;color:var(--muted)">${stock} pcs</span>`;

    document.getElementById('vm-club').textContent = p.club_name || '—';
    document.getElementById('vm-kit').textContent  = p.kit_name  || '—';
    document.getElementById('vm-size').textContent = p.size_name || '—';

    // Badges row
    const badgesEl = document.getElementById('vm-badges');
    badgesEl.innerHTML = '';
    if (p.club_name) badgesEl.innerHTML += `<span class="badge badge-club">${p.club_name}</span>`;
    if (p.kit_name)  badgesEl.innerHTML += `<span class="badge badge-kit">${p.kit_name}</span>`;
    if (p.size_name) badgesEl.innerHTML += `<span class="size-pill">${p.size_name}</span>`;

    // Description
    const descWrap = document.getElementById('vm-description-wrap');
    const descEl   = document.getElementById('vm-description');
    if (p.description && p.description.trim()) {
        descEl.textContent    = p.description;
        descWrap.style.display = 'flex';
    } else {
        descWrap.style.display = 'none';
    }

    // Action buttons
    document.getElementById('vm-edit-btn').href = 'edit_product.php?id=' + id;
    document.getElementById('vm-delete-btn').onclick = () => {
        closeViewModal();
        setTimeout(() => confirmDelete(id, p.product_name), 150);
    };

    document.getElementById('viewModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('active');
    document.body.style.overflow = '';
}

document.getElementById('viewModal').addEventListener('click', function (e) {
    if (e.target === this) closeViewModal();
});

// ── Delete Modal ──────────────────────────────────────────────────────────
function confirmDelete(id, name) {
    document.getElementById('deleteProductName').textContent = name;

    // Build delete URL preserving ALL current GET params except 'delete' itself
    const params = new URLSearchParams(window.location.search);
    params.delete('delete'); // remove stale delete param if any
    params.set('delete', id);

    document.getElementById('deleteConfirmBtn').href = 'all_products.php?' + params.toString();
    document.getElementById('deleteModal').classList.add('active');
}

function closeModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

document.getElementById('deleteModal').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});

// ── Clear search ──────────────────────────────────────────────────────────
function clearSearch() {
    document.getElementById('searchInput').value = '';
    document.getElementById('filterForm').submit();
}

// ── Auto-dismiss alerts ───────────────────────────────────────────────────
document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity    = '0';
        setTimeout(() => el.remove(), 500);
    }, 3500);
});