/* ============================================================
   SPECS – Basket JavaScript
   File: assets/js/basket.js
   Used by: user/basket.php and user/browse.php
   ============================================================ */

/* ── ADD TO BASKET (AJAX) ─────────────────────────────────── */
function addToBasket(productId, quantity, buttonEl) {
  if (!productId) return;
  quantity = quantity || 1;

  const formData = new FormData();
  formData.append('product_id', productId);
  formData.append('quantity', quantity);

  if (buttonEl) {
    buttonEl.disabled    = true;
    buttonEl.textContent = '⏳ Adding...';
  }

  fetch('../api/add_to_basket.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(`✅ ${data.product_name} added to basket!`, 'success');
        refreshBasketBadge(data.basket_count);

        if (buttonEl) {
          buttonEl.textContent             = '✅ In Basket';
          buttonEl.style.background        = 'var(--leaf)';
          buttonEl.style.color             = '#fff';
          buttonEl.disabled                = false;
        }
      } else {
        showToast(data.message || 'Could not add to basket', 'error');
        if (buttonEl) {
          buttonEl.disabled    = false;
          buttonEl.textContent = '🛒 Add to Basket';
        }
      }
    })
    .catch(() => {
      showToast('Network error — please try again.', 'error');
      if (buttonEl) {
        buttonEl.disabled    = false;
        buttonEl.textContent = '🛒 Add to Basket';
      }
    });
}

/* ── REFRESH BASKET BADGE ─────────────────────────────────── */
function refreshBasketBadge(count) {
  const badge = document.getElementById('basket-badge');
  if (!badge) return;
  badge.textContent    = count;
  badge.style.display  = count > 0 ? 'flex' : 'none';

  // Animate badge
  badge.style.transform = 'scale(1.4)';
  setTimeout(() => badge.style.transform = 'scale(1)', 200);
}

/* ── UPDATE QUANTITY ──────────────────────────────────────── */
function updateQty(productId, delta) {
  const qtyEl = document.getElementById(`qty-${productId}`);
  if (!qtyEl) return;

  let current = parseInt(qtyEl.textContent) || 1;
  let newQty  = Math.max(0, current + delta);
  qtyEl.textContent = newQty;

  const formData = new FormData();
  formData.append('action',     'update_qty');
  formData.append('product_id', productId);
  formData.append('quantity',   newQty);

  fetch(window.location.href, { method: 'POST', body: formData })
    .then(() => {
      if (newQty === 0) {
        // Remove the row
        const row = document.getElementById(`basket-row-${productId}`);
        if (row) {
          row.style.opacity = '0';
          setTimeout(() => { row.remove(); recalcBasketTotal(); }, 300);
        }
      } else {
        recalcBasketTotal();
      }
    })
    .catch(() => showToast('Could not update quantity', 'error'));
}

/* ── REMOVE ITEM ──────────────────────────────────────────── */
function removeFromBasket(productId) {
  if (!confirm('Remove this item from basket?')) return;

  const formData = new FormData();
  formData.append('action',     'remove');
  formData.append('product_id', productId);

  fetch(window.location.href, { method: 'POST', body: formData })
    .then(() => {
      const row = document.getElementById(`basket-row-${productId}`);
      if (row) {
        row.style.transition = 'all .3s';
        row.style.opacity    = '0';
        row.style.height     = '0';
        setTimeout(() => { row.remove(); recalcBasketTotal(); }, 300);
      }
      showToast('Item removed from basket', 'default');
    })
    .catch(() => showToast('Could not remove item', 'error'));
}

/* ── RECALCULATE BASKET TOTAL ─────────────────────────────── */
function recalcBasketTotal() {
  let total = 0;
  document.querySelectorAll('.basket-item-row').forEach(row => {
    const price = parseInt(row.dataset.price) || 0;
    const qty   = parseInt(row.querySelector('.qty-display')?.textContent) || 0;
    total += price * qty;
  });

  const totalEl = document.getElementById('basket-total');
  if (totalEl) totalEl.textContent = 'UGX ' + total.toLocaleString();

  const countEl = document.getElementById('basket-item-count');
  const rows    = document.querySelectorAll('.basket-item-row').length;
  if (countEl) countEl.textContent = rows + ' item' + (rows !== 1 ? 's' : '');
}

/* ── SAVE BASKET AS PLAN ──────────────────────────────────── */
function saveBasketPlan(storeId) {
  if (!storeId) {
    showToast('Please select a store first', 'error');
    return;
  }

  const btn = document.getElementById('save-plan-btn');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Saving...'; }

  const formData = new FormData();
  formData.append('store_id', storeId);

  fetch('../api/save_basket.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(`🧾 Plan ${data.plan_ref} saved!`, 'success');
        renderReceipt(data);
      } else {
        showToast(data.message || 'Could not save plan', 'error');
      }
      if (btn) { btn.disabled = false; btn.textContent = '🧾 Generate Plan'; }
    })
    .catch(() => {
      showToast('Network error', 'error');
      if (btn) { btn.disabled = false; btn.textContent = '🧾 Generate Plan'; }
    });
}

/* ── RENDER RECEIPT ───────────────────────────────────────── */
function renderReceipt(data) {
  const box = document.getElementById('receipt-box');
  if (!box) return;

  const itemsHtml = data.items.map(item => `
    <div class="receipt-item">
      <span>${escapeHtml(item.name)} (${escapeHtml(item.unit)}) ×${item.quantity}</span>
      <span>UGX ${(item.price * item.quantity).toLocaleString()}</span>
    </div>
  `).join('');

  box.innerHTML = `
    <div class="receipt-header">
      <div class="receipt-brand">SPECS</div>
      <div style="font-size:.72rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.1em">
        Mbarara City Shopping Plan
      </div>
      <div class="receipt-ref">${data.plan_ref}</div>
      <div style="font-size:.76rem;color:rgba(255,255,255,.45)">${new Date().toLocaleString()}</div>
    </div>
    <div style="font-size:.82rem;color:rgba(255,255,255,.6);margin-bottom:6px">
      🏬 Store: <strong style="color:#fff">${escapeHtml(data.store_name)}</strong>
    </div>
    ${itemsHtml}
    <div class="receipt-total">
      <span>TOTAL</span>
      <span style="color:var(--gold)">UGX ${data.total.toLocaleString()}</span>
    </div>
    ${data.savings > 0 ? `
    <div class="receipt-savings">
      🎉 You save UGX ${data.savings.toLocaleString()} vs most expensive store!
    </div>` : ''}
    <div style="margin-top:16px;text-align:center;display:flex;gap:10px;justify-content:center">
      <button onclick="printReceipt('receipt-box')"
              style="background:var(--gold);color:var(--forest);border:none;padding:8px 16px;border-radius:8px;font-weight:800;cursor:pointer">
        🖨️ Print
      </button>
      <button onclick="sharePlan('${data.plan_ref}','${escapeHtml(data.store_name)}',${data.total})"
              style="background:rgba(255,255,255,.15);color:#fff;border:none;padding:8px 16px;border-radius:8px;font-weight:800;cursor:pointer">
        📤 Share
      </button>
    </div>
  `;

  box.style.display = 'block';
  box.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ── LOAD STORE PRICES POPUP ──────────────────────────────── */
function loadStorePrices(productId, productName, productUnit) {
  const modal   = document.getElementById('storePriceModal');
  const title   = document.getElementById('spmTitle');
  const unit    = document.getElementById('spmUnit');
  const content = document.getElementById('spmContent');
  if (!modal) return;

  if (title)   title.textContent   = productName;
  if (unit)    unit.textContent    = productUnit;
  if (content) content.innerHTML   = '<div style="text-align:center;padding:20px;color:var(--muted)">Loading prices...</div>';
  modal.style.display = 'flex';

  fetch(`../api/get_prices.php?product_id=${productId}`)
    .then(r => r.json())
    .then(data => {
      if (!data.length) {
        content.innerHTML = '<p style="color:var(--muted)">No prices available.</p>';
        return;
      }

      const minPrice = Math.min(...data.map(d => d.price));
      content.innerHTML = data.map(row => {
        const isBest = row.price === minPrice;
        const pct    = Math.round(((row.price - minPrice) / minPrice) * 100);
        return `
          <div class="store-price-row ${isBest ? 'best' : ''}">
            <div>
              <strong>${escapeHtml(row.store_name)}</strong>
              ${isBest ? '<span class="store-best-chip">BEST</span>' : ''}
              <div style="font-size:.74rem;color:var(--muted)">${row.tier}</div>
            </div>
            <div style="text-align:right">
              <div class="store-price-value">UGX ${parseInt(row.price).toLocaleString()}</div>
              ${!isBest && pct > 0 ? `<div style="font-size:.72rem;color:var(--red)">+${pct}% more</div>` : ''}
            </div>
          </div>`;
      }).join('');
    })
    .catch(() => {
      if (content) content.innerHTML = '<p style="color:var(--red)">Failed to load prices.</p>';
    });
}
