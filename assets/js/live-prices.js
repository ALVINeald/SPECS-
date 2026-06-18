/**
 * assets/js/live-prices.js
 * Drop into assets/js/live-prices.js
 *
 * Include this on browse.php / index.php (or wherever you want live updates
 * visible during the demo) with: <script src="assets/js/live-prices.js"></script>
 *
 * ASSUMPTION (needs your confirmation): this expects each price element on the
 * page to carry a `data-price-id="X"` attribute matching prices.id, e.g.
 *   <span class="price" data-price-id="42">UGX 4,500</span>
 *
 * I don't have your browse.php markup in front of me, so I can't guarantee this
 * matches your actual HTML structure yet. Paste the price element markup from
 * browse.php and I'll adjust the selector/formatting in one pass - this file
 * does nothing harmful if the attribute isn't present, it just won't find
 * anything to update.
 */
(function () {
  const POLL_INTERVAL_MS = 3000;

  function formatPrice(value) {
    return 'UGX ' + Number(value).toLocaleString();
  }

  function pollPrices() {
    const elements = document.querySelectorAll('[data-price-id]');
    if (elements.length === 0) return;

    const ids = Array.from(elements).map(function (el) {
      return el.getAttribute('data-price-id');
    });

    fetch('api/get_live_prices.php?ids=' + ids.join(','))
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.success) return;

        const priceMap = {};
        data.prices.forEach(function (p) { priceMap[p.id] = p.price; });

        elements.forEach(function (el) {
          const id = el.getAttribute('data-price-id');
          if (priceMap.hasOwnProperty(id)) {
            const newText = formatPrice(priceMap[id]);
            if (el.textContent.trim() !== newText) {
              el.textContent = newText;
              el.classList.add('price-flash');
              setTimeout(function () { el.classList.remove('price-flash'); }, 600);
            }
          }
        });
      })
      .catch(function (err) { console.error('Live price poll failed:', err); });
  }

  setInterval(pollPrices, POLL_INTERVAL_MS);
})();
