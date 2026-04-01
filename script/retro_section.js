/**
 * JerseyFlow — Retro Jersey Section JS
 * File: retro_section.js
 *
 * Features:
 *  1. Filter buttons (All / Club / National / Home / Away)
 *  2. Quick View modal (open, close, ESC, backdrop)
 */

(function () {
  'use strict';

  /* ─────────────────────────────────────────────
     1.  FILTER BUTTONS
     Cards carry two data attributes set in PHP:
       data-type     → "home" | "away" | "third"
       data-category → "club" | "national"
  ───────────────────────────────────────────── */
  const filterBtns = document.querySelectorAll('.jf-retro-filter');
  const cards      = document.querySelectorAll('.jf-retro-card');

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {

      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const filter = btn.dataset.filter; // 'all' | 'club' | 'national' | 'home' | 'away'

      cards.forEach(card => {
        const type     = card.dataset.type;     // home / away / third
        const category = card.dataset.category; // club / national

        const match =
          filter === 'all' ||
          filter === type  ||
          filter === category;

        if (match) {
          card.classList.remove('hidden');

          // Re-trigger reveal animation
          card.style.animation = 'none';
          void card.offsetHeight;         // force reflow
          card.style.animation = '';
        } else {
          card.classList.add('hidden');
        }
      });
    });
  });


  /* ─────────────────────────────────────────────
     2.  QUICK VIEW MODAL
  ───────────────────────────────────────────── */
  const overlay   = document.getElementById('jf-retro-overlay');
  const closeBtn  = document.getElementById('jf-retro-close');
  const quickBtns = document.querySelectorAll('.jf-retro-quickview');

  // Modal element refs
  const modalImg    = document.getElementById('retro-modal-img');
  const modalLabel  = document.getElementById('retro-modal-label');
  const modalName   = document.getElementById('retro-modal-name');
  const modalKit    = document.getElementById('retro-modal-kit');
  const modalPrice  = document.getElementById('retro-modal-price');
  const modalStock  = document.getElementById('retro-modal-stock');
  const modalDesc   = document.getElementById('retro-modal-desc');
  const modalCta    = document.getElementById('retro-modal-cta');

  /** Open and populate modal */
  function openModal(btn) {
    const { id, name, price, label, kit, img, desc, stock } = btn.dataset;

    modalImg.src             = img   || '';
    modalImg.alt             = name  || '';
    modalLabel.textContent   = label || '';
    modalName.textContent    = name  || '';
    modalKit.textContent     = kit   || '';
    modalPrice.textContent   = 'Rs ' + price;
    modalDesc.textContent    = desc  || '';
    modalCta.href            = `product.php?id=${id}`;

    // Stock badge
    const stockNum = parseInt(stock, 10);
    if (stockNum > 0) {
      modalStock.textContent = 'In Stock';
      modalStock.className   = 'jf-retro-modal__stock in';
    } else {
      modalStock.textContent = 'Sold Out';
      modalStock.className   = 'jf-retro-modal__stock out';
    }

    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    setTimeout(() => closeBtn.focus(), 80);
  }

  /** Close modal */
  function closeModal() {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  // Quick View buttons
  quickBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      openModal(btn);
    });
  });

  // Clicking the whole card also opens quick view
  cards.forEach(card => {
    card.addEventListener('click', () => {
      const qBtn = card.querySelector('.jf-retro-quickview');
      if (qBtn) openModal(qBtn);
    });
  });

  // Close on X
  closeBtn.addEventListener('click', closeModal);

  // Close on backdrop click
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });

  // Close on ESC
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlay.classList.contains('active')) {
      closeModal();
    }
  });

})();