/**
 * categories.js
 * Handles edit modals and delete confirmation for categories.php
 * Place at: ../script/categories.js
 */

'use strict';

// ── Edit Country Modal ────────────────────────────────────────────────────
function openEditCountry(id, name, sortOrder) {
    document.getElementById('edit-country-id').value   = id;
    document.getElementById('edit-country-name').value = name;
    openModal('editCountryModal');
}

function closeEditCountry() {
    closeModal('editCountryModal');
}

// ── Edit Club Modal ───────────────────────────────────────────────────────
function openEditClub(id, name, countryId) {
    document.getElementById('edit-club-id').value   = id;
    document.getElementById('edit-club-name').value = name;

    const sel = document.getElementById('edit-club-country');
    sel.value = countryId || '';

    openModal('editClubModal');
}

function closeEditClub() {
    closeModal('editClubModal');
}

// ── Delete: Country ───────────────────────────────────────────────────────
function confirmDeleteCountry(id, name) {
    document.getElementById('delete-modal-title').textContent = 'Delete Country?';
    document.getElementById('delete-item-name').textContent   = name;
    document.getElementById('delete-action').value            = 'delete_country';
    document.getElementById('delete-country-id').value        = id;
    document.getElementById('delete-club-id').value           = '';
    openModal('deleteModal');
}

// ── Delete: Club ──────────────────────────────────────────────────────────
function confirmDeleteClub(id, name) {
    document.getElementById('delete-modal-title').textContent = 'Delete Club?';
    document.getElementById('delete-item-name').textContent   = name;
    document.getElementById('delete-action').value            = 'delete_club';
    document.getElementById('delete-club-id').value           = id;
    document.getElementById('delete-country-id').value        = '';
    openModal('deleteModal');
}

function closeDeleteModal() {
    closeModal('deleteModal');
}

// ── Generic open / close helpers ──────────────────────────────────────────
function openModal(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.classList.remove('active');
    document.body.style.overflow = '';
}

// Click outside modal box to close
document.addEventListener('DOMContentLoaded', () => {

    ['editCountryModal', 'editClubModal', 'deleteModal'].forEach(id => {
        const overlay = document.getElementById(id);
        if (!overlay) return;
        overlay.addEventListener('click', function (e) {
            if (e.target === this) {
                closeModal(id);
            }
        });
    });

    // Escape key closes any open modal
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        ['editCountryModal', 'editClubModal', 'deleteModal'].forEach(id => {
            const overlay = document.getElementById(id);
            if (overlay && overlay.classList.contains('active')) {
                closeModal(id);
            }
        });
    });

    // Auto-dismiss success alert after 3.5s
    const alertBox = document.getElementById('alertBox');
    if (alertBox && alertBox.classList.contains('alert-success')) {
        setTimeout(() => {
            alertBox.style.transition = 'opacity 0.5s';
            alertBox.style.opacity    = '0';
            setTimeout(() => alertBox.remove(), 500);
        }, 3500);
    }
});