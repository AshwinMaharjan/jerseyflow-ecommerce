/**
 * edit_profile.js
 * Handles: live avatar preview, remove-photo toggle, client-side validation.
 */

(function () {
  'use strict';

  /* ── Element refs ──────────────────────────────────────────────────────── */
  const fileInput      = document.getElementById('profile_image');
  const avatarPreview  = document.getElementById('avatarPreview');
  const avatarInitials = document.getElementById('avatarInitials');
  const avatarCircle   = document.getElementById('avatarCircle');
  const removePicInput = document.getElementById('remove_picture');
  const removePhotoBtn = document.getElementById('removePhotoBtn');
  const emailInput     = document.getElementById('email');
  const form           = document.getElementById('editProfileForm');

  /* ── Live avatar preview ───────────────────────────────────────────────── */
  if (fileInput) {
    fileInput.addEventListener('change', function () {
      const file = this.files[0];
      if (!file) return;

      // Basic client-side type check
      if (!file.type.startsWith('image/')) {
        showFieldError(fileInput, 'Please select a valid image file.');
        this.value = '';
        return;
      }

      // 2 MB limit
      if (file.size > 2 * 1024 * 1024) {
        showFieldError(fileInput, 'Image must be smaller than 2 MB.');
        this.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onload = function (e) {
        // Show preview image, hide initials
        if (avatarInitials) avatarInitials.style.display = 'none';
        if (avatarPreview) {
          avatarPreview.src     = e.target.result;
          avatarPreview.style.display = 'block';
        }
        // Re-enable remove button
        showRemoveBtn();
        // Clear any remove flag
        if (removePicInput) removePicInput.value = '0';
      };
      reader.readAsDataURL(file);
    });
  }

  /* ── Remove photo button ───────────────────────────────────────────────── */
  if (removePhotoBtn) {
    // Make visible if PHP rendered it (has class or inline style display:none removed)
    const phpVisible = removePhotoBtn.style.display !== 'none';
    if (phpVisible) removePhotoBtn.classList.add('visible');

    removePhotoBtn.addEventListener('click', function () {
      // Reset preview to initials
      if (avatarPreview) {
        avatarPreview.src          = '';
        avatarPreview.style.display = 'none';
      }
      if (avatarInitials) avatarInitials.style.display = '';
      // Clear file input
      if (fileInput) fileInput.value = '';
      // Signal server to delete existing image
      if (removePicInput) removePicInput.value = '1';
      // Hide the button until a new image is chosen
      hideRemoveBtn();
    });
  }

  function showRemoveBtn () {
    if (removePhotoBtn) {
      removePhotoBtn.style.display = '';
      removePhotoBtn.classList.add('visible');
    }
  }
  function hideRemoveBtn () {
    if (removePhotoBtn) {
      removePhotoBtn.style.display = 'none';
      removePhotoBtn.classList.remove('visible');
    }
  }

  /* ── Client-side form validation ───────────────────────────────────────── */
  if (form) {
    form.addEventListener('submit', function (e) {
      let valid = true;

      // Email
      clearFieldError(emailInput);
      const emailVal = emailInput ? emailInput.value.trim() : '';
      if (!emailVal) {
        showFieldError(emailInput, 'Email address is required.');
        valid = false;
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
        showFieldError(emailInput, 'Please enter a valid email address.');
        valid = false;
      }

      // Phone (optional but validate format if filled)
      const phoneInput = document.getElementById('phone');
      if (phoneInput) {
        clearFieldError(phoneInput);
        const phoneVal = phoneInput.value.trim();
        if (phoneVal && !/^\+?[\d\s\-\(\)]{6,20}$/.test(phoneVal)) {
          showFieldError(phoneInput, 'Please enter a valid phone number.');
          valid = false;
        }
      }

      if (!valid) {
        e.preventDefault();
        // Scroll to first error
        const firstError = form.querySelector('.is-invalid');
        if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  }

  /* ── Input-level error helpers ─────────────────────────────────────────── */
  function showFieldError (input, message) {
    if (!input) return;
    input.classList.add('is-invalid');

    // Remove any existing inline error
    const existing = input.parentElement.querySelector('.field-error');
    if (existing) existing.remove();

    const msg = document.createElement('p');
    msg.className   = 'field-error';
    msg.textContent = message;
    msg.style.cssText = 'font-size:.72rem;color:#f87171;margin-top:.3rem;';
    input.insertAdjacentElement('afterend', msg);
  }

  function clearFieldError (input) {
    if (!input) return;
    input.classList.remove('is-invalid');
    const existing = input.parentElement.querySelector('.field-error');
    if (existing) existing.remove();
  }

  // Clear errors on user interaction
  document.querySelectorAll('.field-input').forEach(function (inp) {
    inp.addEventListener('input', function () { clearFieldError(this); });
  });

})();