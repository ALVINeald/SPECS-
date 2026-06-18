/**
 * assets/js/simulator.js
 * Drop into assets/js/simulator.js
 * Used only by admin/simulator.php
 */
(function () {
  const startBtn = document.getElementById('startBtn');
  const stopBtn = document.getElementById('stopBtn');
  const resetBtn = document.getElementById('resetBtn');
  const statusEl = document.getElementById('simStatus');
  const logEl = document.getElementById('simLog');

  let intervalId = null;
  let totalChanges = 0;

  function setLiveStatus(isLive) {
    statusEl.textContent = isLive ? 'Live' : 'Idle';
    statusEl.classList.toggle('live', isLive);
    startBtn.disabled = isLive;
    stopBtn.disabled = !isLive;
  }

  function renderChanges(changes) {
    if (logEl.querySelector('.sim-log-empty')) {
      logEl.innerHTML = '';
    }
    changes.forEach(function (c) {
      const entry = document.createElement('div');
      entry.className = 'sim-log-entry';
      const direction = c.new_price > c.old_price ? 'up' : 'down';
      const arrow = direction === 'up' ? '\u2191' : '\u2193';
      entry.innerHTML =
        '<div>' +
          '<span class="sim-log-product">' + c.product + '</span><br>' +
          '<span class="sim-log-store">' + c.store + '</span>' +
        '</div>' +
        '<div class="sim-log-price ' + direction + '">' +
          'UGX ' + c.old_price.toLocaleString() + ' \u2192 ' + c.new_price.toLocaleString() +
          ' (' + arrow + ' ' + Math.abs(c.pct_change) + '%)' +
        '</div>';
      logEl.prepend(entry);
    });
    totalChanges += changes.length;

    // Keep the log from growing unbounded over a long demo
    while (logEl.children.length > 200) {
      logEl.removeChild(logEl.lastChild);
    }
  }

  function runCycle() {
    fetch('../api/simulate_prices.php?action=simulate')
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.success) {
          renderChanges(data.changes);
        } else {
          console.error('Simulation error:', data.message);
        }
      })
      .catch(function (err) { console.error('Network error:', err); });
  }

  startBtn.addEventListener('click', function () {
    setLiveStatus(true);
    runCycle(); // fire immediately, then every 2s
    intervalId = setInterval(runCycle, 2000);
  });

  stopBtn.addEventListener('click', function () {
    clearInterval(intervalId);
    intervalId = null;
    setLiveStatus(false);
  });

  resetBtn.addEventListener('click', function () {
    if (intervalId) {
      clearInterval(intervalId);
      intervalId = null;
      setLiveStatus(false);
    }
    fetch('../api/simulate_prices.php?action=reset')
      .then(function (res) { return res.json(); })
      .then(function (data) {
        logEl.innerHTML = '<div class="sim-log-empty">' +
          (data.success ? 'Prices reset to baseline.' : data.message) +
          '</div>';
        totalChanges = 0;
      })
      .catch(function (err) { console.error('Network error:', err); });
  });
})();
