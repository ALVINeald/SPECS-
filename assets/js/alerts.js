/* ============================================================
   SPECS – Alerts JavaScript
   File: assets/js/alerts.js
   Used by: user/alerts.php and user/browse.php
   ============================================================ */

/* ── SET ALERT (AJAX) ─────────────────────────────────────── */
function setAlert(productId, targetPrice, storeId, buttonEl) {
  if (!productId || !targetPrice || targetPrice <= 0) {
    showToast('Please select a product and enter a target price', 'error');
    return;
  }

  const formData = new FormData();
  formData.append('product_id',   productId);
  formData.append('target_price', targetPrice);
  if (storeId) formData.append('store_id', storeId);

  if (buttonEl) {
    buttonEl.disabled    = true;
    buttonEl.textContent = '⏳ Setting...';
  }

  fetch('../api/set_alert.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast(data.message, 'success', 5000);

        if (data.already_met) {
          showToast(`⚡ Price is already at your target! Current best: UGX ${parseInt(data.current_best).toLocaleString()}`, 'warning', 6000);
        }

        // Update alert count badge in nav if present
        const alertBadge = document.getElementById('alert-count-badge');
        if (alertBadge) {
          const current = parseInt(alertBadge.textContent) || 0;
          alertBadge.textContent = current + 1;
        }

        if (buttonEl) {
          buttonEl.textContent      = '✅ Alert Set';
          buttonEl.style.background = 'var(--leaf)';
          buttonEl.disabled         = false;
        }

        // Close modal if open
        closeModal('addAlertModal');

        // Reload alerts list if on alerts page
        if (window.location.pathname.includes('alerts.php')) {
          setTimeout(() => window.location.reload(), 1200);
        }
      } else {
        showToast(data.message || 'Could not set alert', 'error');
        if (buttonEl) {
          buttonEl.disabled    = false;
          buttonEl.textContent = '🔔 Set Alert';
        }
      }
    })
    .catch(() => {
      showToast('Network error — please try again', 'error');
      if (buttonEl) {
        buttonEl.disabled    = false;
        buttonEl.textContent = '🔔 Set Alert';
      }
    });
}

/* ── REMOVE ALERT (AJAX) ──────────────────────────────────── */
function removeAlert(alertId, cardEl) {
  if (!confirm('Remove this price alert?')) return;

  const formData = new FormData();
  formData.append('action',   'delete');
  formData.append('alert_id', alertId);

  fetch(window.location.href, { method: 'POST', body: formData })
    .then(() => {
      if (cardEl) {
        cardEl.style.transition = 'all .3s';
        cardEl.style.opacity    = '0';
        cardEl.style.transform  = 'scale(.95)';
        setTimeout(() => {
          cardEl.remove();
          updateAlertCount(-1);
        }, 300);
      }
      showToast('Alert removed', 'default');
    })
    .catch(() => showToast('Could not remove alert', 'error'));
}

/* ── UPDATE ALERT COUNT ───────────────────────────────────── */
function updateAlertCount(delta) {
  const badge = document.getElementById('alert-count-badge');
  if (!badge) return;
  const current = parseInt(badge.textContent) || 0;
  const newCount = Math.max(0, current + delta);
  badge.textContent = newCount;
}

/* ── SUGGEST PRICE FROM API ───────────────────────────────── */
function suggestAlertPrice(productId, inputId, hintId) {
  if (!productId) return;

  fetch(`../api/get_prices.php?product_id=${productId}`)
    .then(r => r.json())
    .then(data => {
      if (!data.length) return;

      const prices  = data.map(d => parseInt(d.price));
      const minP    = Math.min(...prices);
      const maxP    = Math.max(...prices);
      const suggest = Math.round(minP * 0.9); // suggest 10% below current best

      const input = document.getElementById(inputId);
      const hint  = document.getElementById(hintId);

      if (input) {
        input.placeholder = `e.g. ${suggest.toLocaleString()}`;
      }

      if (hint) {
        hint.innerHTML = `
          Current best: <strong>UGX ${minP.toLocaleString()}</strong> · 
          Highest: <strong>UGX ${maxP.toLocaleString()}</strong><br>
          <span style="color:var(--leaf)">Suggested target: UGX ${suggest.toLocaleString()} (10% below best)</span>
        `;
      }
    })
    .catch(() => {});
}

/* ── ALERT PROGRESS ANIMATION ─────────────────────────────── */
function animateAlertProgress() {
  document.querySelectorAll('.alert-progress-fill').forEach(bar => {
    const target = bar.dataset.width || '0%';
    bar.style.width = '0%';
    setTimeout(() => {
      bar.style.transition = 'width .8s ease';
      bar.style.width      = target;
    }, 200);
  });
}

/* ── CHECK ALL ALERTS (admin only) ───────────────────────── */
function runAlertCheck(buttonEl) {
  if (buttonEl) {
    buttonEl.disabled    = true;
    buttonEl.textContent = '⏳ Checking...';
  }

  fetch('../api/check_alerts.php')
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const msg = `✅ Checked ${data.alerts_checked} alerts — ${data.alerts_triggered} triggered`;
        showToast(msg, 'success', 5000);

        if (data.alerts_triggered > 0) {
          const list = data.triggered.map(t =>
            `• ${t.product} → UGX ${parseInt(t.current_price).toLocaleString()} (target: ${parseInt(t.target_price).toLocaleString()})`
          ).join('\n');
          console.log('Triggered alerts:\n' + list);
        }
      } else {
        showToast(data.message || 'Alert check failed', 'error');
      }

      if (buttonEl) {
        buttonEl.disabled    = false;
        buttonEl.textContent = '⚡ Check Alerts Now';
      }
    })
    .catch(() => {
      showToast('Network error', 'error');
      if (buttonEl) {
        buttonEl.disabled    = false;
        buttonEl.textContent = '⚡ Check Alerts Now';
      }
    });
}

/* ── QUICK SET ALERT FROM BROWSE PAGE ─────────────────────── */
function quickSetAlert(productId, productName, currentPrice) {
  // Pre-fill alert modal if it exists
  const productSel = document.getElementById('alertProduct');
  const priceInput = document.getElementById('alertPrice');
  const hintEl     = document.getElementById('alertPriceHint');

  if (productSel) productSel.value = productId;
  if (priceInput) priceInput.placeholder = `Current best: UGX ${parseInt(currentPrice).toLocaleString()}`;
  if (hintEl) {
    hintEl.innerHTML = `Setting alert for <strong>${escapeHtml(productName)}</strong>. Current best: UGX ${parseInt(currentPrice).toLocaleString()}`;
  }

  openModal('addAlertModal');
}

/* ── ON PAGE LOAD ─────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
  animateAlertProgress();
});
