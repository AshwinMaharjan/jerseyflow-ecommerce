/* ─── users.js ───────────────────────────────────────────────────────────── */

'use strict';

/* ── Dropdown toggle ──────────────────────────────────────────────────────── */
function toggleDropdown(btn) {
  const wrap    = btn.closest('.action-wrap');
  const dropdown = wrap.querySelector('.action-dropdown');
  const isOpen  = dropdown.classList.contains('open');

  // Close all open dropdowns
  closeAllDropdowns();

  if (!isOpen) {
    dropdown.classList.add('open');
    btn.classList.add('active');
  }
}

function closeAllDropdowns() {
  document.querySelectorAll('.action-dropdown.open').forEach(d => {
    d.classList.remove('open');
    const btn = d.closest('.action-wrap')?.querySelector('.btn-action-toggle');
    if (btn) btn.classList.remove('active');
  });
}

document.addEventListener('click', e => {
  if (!e.target.closest('.action-wrap')) closeAllDropdowns();
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeAllDropdowns(); closeModal(); }
});

/* ── Confirm Modal ────────────────────────────────────────────────────────── */
let pendingAction  = null;
let pendingUserId  = null;

const MODAL_CONFIG = {
  block: {
    title : 'Block User',
    msg   : name => `Are you sure you want to block <strong>${escHtml(name)}</strong>? They will not be able to log in.`,
    icon  : 'fa-ban',
    style : 'warn',
  },
  activate: {
    title : 'Activate User',
    msg   : name => `Activate <strong>${escHtml(name)}</strong>? They will regain full access.`,
    icon  : 'fa-circle-check',
    style : 'success',
  },
  delete: {
    title : 'Delete User',
    msg   : name => `Permanently remove <strong>${escHtml(name)}</strong>? This action cannot be undone.`,
    icon  : 'fa-trash',
    style : 'danger',
  },
};

function confirmAction(action, userId, userName) {
  closeAllDropdowns();
  const cfg = MODAL_CONFIG[action];
  if (!cfg) return;

  pendingAction = action;
  pendingUserId = userId;

  const modal      = document.getElementById('confirmModal');
  const iconEl     = document.getElementById('modalIcon');
  const titleEl    = document.getElementById('modalTitle');
  const msgEl      = document.getElementById('modalMsg');
  const confirmBtn = document.getElementById('modalConfirmBtn');

  iconEl.innerHTML  = `<i class="fa-solid ${cfg.icon}"></i>`;
  iconEl.className  = `modal-icon icon-${cfg.style}`;
  titleEl.textContent = cfg.title;
  msgEl.innerHTML   = cfg.msg(userName);
  confirmBtn.className = `btn-modal-confirm ${cfg.style}`;

  modal.classList.add('open');
}

function closeModal() {
  const modal = document.getElementById('confirmModal');
  if (modal) modal.classList.remove('open');
  pendingAction = null;
  pendingUserId = null;
}

function executeAction() {
  if (!pendingAction || !pendingUserId) return;

  const form = document.getElementById('actionForm');
  document.getElementById('actionField').value  = pendingAction;
  document.getElementById('userIdField').value  = pendingUserId;

  closeModal();
  form.submit();
}

// Close modal on overlay click
document.addEventListener('DOMContentLoaded', () => {
  const overlay = document.getElementById('confirmModal');
  if (overlay) {
    overlay.addEventListener('click', e => {
      if (e.target === overlay) closeModal();
    });
  }
});

/* ── Select All / Bulk ────────────────────────────────────────────────────── */
const selectAllEl = document.getElementById('selectAll');
const bulkBar     = document.getElementById('bulkBar');
const bulkCountEl = document.getElementById('bulkCount');

function getChecked() {
  return [...document.querySelectorAll('.row-check:checked')];
}

function updateBulkBar() {
  const checked = getChecked();
  const count   = checked.length;

  if (count > 0) {
    bulkBar?.classList.add('visible');
    if (bulkCountEl) bulkCountEl.textContent = count;
  } else {
    bulkBar?.classList.remove('visible');
  }

  // Sync select-all state
  const all = document.querySelectorAll('.row-check');
  if (selectAllEl) {
    selectAllEl.checked       = all.length > 0 && count === all.length;
    selectAllEl.indeterminate = count > 0 && count < all.length;
  }
}

if (selectAllEl) {
  selectAllEl.addEventListener('change', () => {
    document.querySelectorAll('.row-check').forEach(cb => {
      cb.checked = selectAllEl.checked;
      cb.closest('tr')?.classList.toggle('row-selected', selectAllEl.checked);
    });
    updateBulkBar();
  });
}

document.querySelectorAll('.row-check').forEach(cb => {
  cb.addEventListener('change', () => {
    cb.closest('tr')?.classList.toggle('row-selected', cb.checked);
    updateBulkBar();
  });
});

const BULK_CONFIRM = {
  block    : { title: 'Block Selected', msg: n => `Block ${n} selected user(s)?`, style: 'warn',    icon: 'fa-ban'          },
  activate : { title: 'Activate Selected', msg: n => `Activate ${n} selected user(s)?`, style: 'success', icon: 'fa-circle-check' },
  delete   : { title: 'Delete Selected', msg: n => `Permanently delete ${n} selected user(s)? Cannot be undone.`, style: 'danger', icon: 'fa-trash' },
};

function bulkAction(action) {
  const checked = getChecked();
  if (!checked.length) return;

  const ids = checked.map(cb => cb.value).join(',');
  const cfg = BULK_CONFIRM[action];
  if (!cfg) return;

  // Reuse modal
  const modal      = document.getElementById('confirmModal');
  const iconEl     = document.getElementById('modalIcon');
  const titleEl    = document.getElementById('modalTitle');
  const msgEl      = document.getElementById('modalMsg');
  const confirmBtn = document.getElementById('modalConfirmBtn');

  iconEl.innerHTML  = `<i class="fa-solid ${cfg.icon}"></i>`;
  iconEl.className  = `modal-icon icon-${cfg.style}`;
  titleEl.textContent = cfg.title;
  msgEl.innerHTML   = cfg.msg(checked.length);
  confirmBtn.className = `btn-modal-confirm ${cfg.style}`;

  // Override executeAction for bulk
  confirmBtn.onclick = () => {
    document.getElementById('actionField').value  = `bulk_${action}`;
    document.getElementById('userIdField').value  = '';
    document.getElementById('bulkIdsField').value = ids;
    closeModal();
    document.getElementById('actionForm').submit();
  };

  modal.classList.add('open');
}

function clearSelection() {
  document.querySelectorAll('.row-check').forEach(cb => {
    cb.checked = false;
    cb.closest('tr')?.classList.remove('row-selected');
  });
  if (selectAllEl) { selectAllEl.checked = false; selectAllEl.indeterminate = false; }
  updateBulkBar();
}

/* ── Search clear ─────────────────────────────────────────────────────────── */
function clearSearch() {
  const form = document.getElementById('filterForm');
  if (!form) return;
  const input = form.querySelector('input[name="search"]');
  if (input) { input.value = ''; form.submit(); }
}

/* ── Auto-dismiss toast ───────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  const toast = document.getElementById('pageToast');
  if (toast) {
    setTimeout(() => {
      toast.style.transition = 'opacity 0.4s';
      toast.style.opacity    = '0';
      setTimeout(() => toast.remove(), 400);
    }, 4000);
  }

  // Restore bulk bar state on back-navigation (bfcache)
  updateBulkBar();
});

/* ── Util ─────────────────────────────────────────────────────────────────── */
function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}