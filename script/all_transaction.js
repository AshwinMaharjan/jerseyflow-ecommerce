/* ============================================================
   all_payments.js — Admin Payments Panel
   ============================================================ */

/* ---------- Toast Notification ---------- */
function showToast(msg, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.className = 'ap-toast ' + type;
    // Force reflow so transition fires even on rapid successive calls
    void toast.offsetWidth;
    toast.classList.add('show');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => {
        toast.classList.remove('show');
    }, 2800);
}

/* ---------- Status Update (AJAX) ---------- */
function updateStatus(selectEl) {
    const paymentId = selectEl.dataset.paymentId;
    const newStatus = selectEl.value;
    const row       = selectEl.closest('tr');

    // Visual feedback — disable while saving
    selectEl.classList.add('updating');

    const formData = new FormData();
    formData.append('payment_id',  paymentId);
    formData.append('new_status',  newStatus);
    formData.append('action',      'update_payment_status');

    fetch('update_payment_status.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        selectEl.classList.remove('updating');
        if (data.success) {
            // Re-colour the select to match new status
            selectEl.className = 'status-select status-' + newStatus;
            // Update row's data-status for filter sync
            row.dataset.status = newStatus;
            showToast('Status updated to "' + capitalize(newStatus) + '"', 'success');
        } else {
            showToast(data.message || 'Failed to update status.', 'error');
            // Revert UI to original value
            selectEl.value = row.dataset.status;
            selectEl.className = 'status-select status-' + row.dataset.status;
        }
    })
    .catch(() => {
        selectEl.classList.remove('updating');
        showToast('Network error. Please try again.', 'error');
        selectEl.value = row.dataset.status;
        selectEl.className = 'status-select status-' + row.dataset.status;
    });
}

/* ---------- Filter Logic ---------- */
function applyFilters() {
    const search  = document.getElementById('searchInput').value.toLowerCase().trim();
    const status  = document.getElementById('statusFilter').value;
    const gateway = document.getElementById('gatewayFilter').value;

    const rows   = document.querySelectorAll('#paymentsTable tbody tr:not(.no-data-row)');
    let visible  = 0;

    rows.forEach(row => {
        const rowText    = row.textContent.toLowerCase();
        const rowStatus  = row.dataset.status  || '';
        const rowGateway = row.dataset.gateway || '';

        const matchSearch  = !search  || rowText.includes(search);
        const matchStatus  = !status  || rowStatus  === status;
        const matchGateway = !gateway || rowGateway === gateway;

        if (matchSearch && matchStatus && matchGateway) {
            row.classList.remove('hidden-row');
            visible++;
        } else {
            row.classList.add('hidden-row');
        }
    });

    const countEl = document.getElementById('visibleCount');
    if (countEl) countEl.textContent = visible;
}

/* ---------- Helpers ---------- */
function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

/* ---------- Event Listeners ---------- */
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('searchInput') .addEventListener('input',  applyFilters);
    document.getElementById('statusFilter') .addEventListener('change', applyFilters);
    document.getElementById('gatewayFilter').addEventListener('change', applyFilters);
});