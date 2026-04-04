/**
 * JerseyFlow — Limited Edition Section
 * File: limited_edition.js
 *
 * Handles:
 *  - Filter buttons (all / club / national / home / away)
 *  - Quick View modal open / close / populate
 *  - Keyboard (Escape) and overlay click to close modal
 *  - Live stock counter update after filter
 */

(function () {
  'use strict';

  /* ── DOM REFS ── */
  const grid       = document.getElementById('ltd-grid');
  const overlay    = document.getElementById('jf-ltd-overlay');
  const closeBtn   = document.getElementById('jf-ltd-close');
  const stockText  = document.getElementById('ltd-stock-text');
  const filters    = document.querySelectorAll('.jf-ltd-filter');
  const quickBtns  = document.querySelectorAll('.jf-ltd-quickview');

  // Modal fields
  const mImg     = document.getElementById('ltd-modal-img');
  const mEdition = document.getElementById('ltd-modal-edition');
  const mLabel   = document.getElementById('ltd-modal-label');
  const mName    = document.getElementById('ltd-modal-name');
  const mKit     = document.getElementById('ltd-modal-kit');
  const mPrice   = document.getElementById('ltd-modal-price');
  const mStock   = document.getElementById('ltd-modal-stock');
  const mDesc    = document.getElementById('ltd-modal-desc');
  const mCta     = document.getElementById('ltd-modal-cta');

  if (!grid || !overlay) return;

  /* ── FILTER LOGIC ── */
  filters.forEach(btn => {
    btn.addEventListener('click', () => {
      // Toggle active state
      filters.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const filter = btn.dataset.filter;
      const cards  = grid.querySelectorAll('.jf-ltd-card');
      let visible  = 0;

      cards.forEach(card => {
        const matchType     = card.dataset.type     === filter;
        const matchCategory = card.dataset.category === filter;
        const show = (filter === 'all') || matchType || matchCategory;

        if (show) {
          card.classList.remove('hidden');
          visible++;
        } else {
          card.classList.add('hidden');
        }
      });

      // Update live counter
      if (stockText) {
        const label = filter === 'all' ? 'Drop' : 'Result';
        stockText.textContent = `${visible} ${label}${visible !== 1 ? 's' : ''} Available`;
      }
    });
  });

  /* ── MODAL OPEN ── */
  function openModal(btn) {
    const stock = parseInt(btn.dataset.stock, 10);

    // Populate image & edition watermark
    mImg.src = btn.dataset.img;
    mImg.alt = btn.dataset.name;
    if (mEdition) mEdition.textContent = btn.dataset.edition || '';

    // Text fields
    mLabel.textContent = btn.dataset.label  || '';
    mName.textContent  = btn.dataset.name   || '';
    mKit.textContent   = btn.dataset.kit    || '';
    mPrice.textContent = 'Rs ' + btn.dataset.price;
    mDesc.textContent  = btn.dataset.desc   || '';

    // Stock status
    mStock.className = 'jf-ltd-modal__stock';
    if (stock === 0) {
      mStock.textContent = 'Sold Out';
      mStock.classList.add('out');
    } else if (stock <= 5) {
      mStock.textContent = `Only ${stock} Left`;
      mStock.classList.add('low');
    } else {
      mStock.textContent = 'In Stock';
      mStock.classList.add('in');
    }

    // CTA link
    if (mCta) {
      mCta.href = `product.php?id=${btn.dataset.id}`;
    }

    // Show overlay
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Focus trap — move focus to close button
    setTimeout(() => closeBtn && closeBtn.focus(), 50);
  }

  /* ── MODAL CLOSE ── */
  function closeModal() {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  /* ── EVENT LISTENERS ── */
  quickBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      openModal(btn);
    });
  });

  // Card click also opens modal (excluding badge / other elements)
  grid.querySelectorAll('.jf-ltd-card').forEach(card => {
    card.addEventListener('click', () => {
      const btn = card.querySelector('.jf-ltd-quickview');
      if (btn) openModal(btn);
    });
  });

  // Close on button
  if (closeBtn) closeBtn.addEventListener('click', closeModal);

  // Close on overlay backdrop click
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlay.classList.contains('active')) {
      closeModal();
    }
  });

})();