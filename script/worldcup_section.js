/**
 * JerseyFlow — World Cup 2026 Section JS
 * File: worldcup_section.js
 *
 * Features:
 *  1. Filter buttons (All / Home / Away / Third)
 *  2. Quick View modal (open, close, ESC key, backdrop click)
 */

(function () {
  'use strict';

  /* ─────────────────────────────────────────────
     1.  FILTER BUTTONS
  ───────────────────────────────────────────── */
  const filterBtns = document.querySelectorAll('.jf-filter-btn');
  const cards      = document.querySelectorAll('.jf-product-card');

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {

      // Toggle active class
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const filter = btn.dataset.filter; // 'all' | 'home' | 'away' | 'third'

      cards.forEach(card => {
        const type = card.dataset.type; // set in PHP via kit_name

        if (filter === 'all' || type === filter) {
          card.classList.remove('hidden');

          // Re-trigger entry animation
          card.style.animation = 'none';
          card.offsetHeight;                        // force reflow
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
  const overlay    = document.getElementById('jf-modal-overlay');
  const closeBtn   = document.getElementById('jf-modal-close');
  const quickBtns  = document.querySelectorAll('.jf-quickview-btn');

  // Modal element refs
  const modalImg     = document.getElementById('modal-img');
  const modalCountry = document.getElementById('modal-country');
  const modalName    = document.getElementById('modal-name');
  const modalKit     = document.getElementById('modal-kit');
  const modalPrice   = document.getElementById('modal-price');
  const modalStock   = document.getElementById('modal-stock');
  const modalDesc    = document.getElementById('modal-desc');
  const modalCta     = document.getElementById('modal-cta');

  /** Populate and open modal */
  function openModal(btn) {
    const {
      id, name, price, country, kit, img, desc, stock
    } = btn.dataset;

    // Fill content
    modalImg.src         = img  || '';
    modalImg.alt         = name || '';
    modalCountry.textContent = country || '';
    modalName.textContent    = name    || '';
    modalKit.textContent     = kit     || '';
    modalPrice.textContent   = 'Rs ' + price;
    modalDesc.textContent    = desc    || '';

    // Stock indicator
    const stockNum = parseInt(stock, 10);
    if (stockNum > 0) {
      modalStock.textContent = 'In Stock';
      modalStock.className   = 'jf-modal__stock in';
    } else {
      modalStock.textContent = 'Out of Stock';
      modalStock.className   = 'jf-modal__stock out';
    }

    // CTA link
    modalCta.href = `product.php?id=${id}`;

    // Show overlay
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Focus the close button for accessibility
    setTimeout(() => closeBtn.focus(), 100);
  }

  /** Close modal */
  function closeModal() {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  // Attach open handlers to all Quick View buttons
  quickBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      openModal(btn);
    });
  });

  // Also allow clicking the entire card to open quick view
  cards.forEach(card => {
    card.addEventListener('click', () => {
      const qBtn = card.querySelector('.jf-quickview-btn');
      if (qBtn) openModal(qBtn);
    });
  });

  // Close on X button
  closeBtn.addEventListener('click', closeModal);

  // Close on backdrop click (outside modal box)
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });

  // Close on ESC key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlay.classList.contains('active')) {
      closeModal();
    }
  });

})();