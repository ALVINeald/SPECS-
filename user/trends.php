<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'Price Trends';

$products = $conn->query("SELECT id, name, unit FROM products WHERE active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$stores   = getStores($conn);

$selectedPid = isset($_GET['product_id']) ? (int)$_GET['product_id'] : ($products[0]['id'] ?? 0);

// Get selected product info
$selProduct = null;
if ($selectedPid) {
    $selProduct = $conn->query("SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON p.category_id=c.id WHERE p.id=$selectedPid")->fetch_assoc();
}

// Get price history for selected product
$history = [];
$rows = [];
if ($selectedPid) {
    $rows = $conn->query("
        SELECT ph.changed_at, ph.new_price, ph.store_id, s.name AS store_name, s.short_name
        FROM price_history ph
        JOIN stores s ON ph.store_id = s.id
        WHERE ph.product_id = $selectedPid
        ORDER BY ph.changed_at ASC
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as $r) {
        $history[$r['store_id']][] = ['date' => $r['changed_at'], 'price' => $r['new_price'], 'store' => $r['store_name']];
    }
}

// Current prices across stores
$currentPrices = [];
if ($selectedPid) {
    $cr = $conn->query("
        SELECT pr.price, s.name AS store_name, s.short_name, s.tier
        FROM prices pr
        JOIN stores s ON pr.store_id = s.id
        WHERE pr.product_id = $selectedPid
        ORDER BY pr.price ASC
    ");
    while ($row = $cr->fetch_assoc()) $currentPrices[] = $row;
}

$minPrice = !empty($currentPrices) ? $currentPrices[0]['price'] : 0;
$maxPrice = !empty($currentPrices) ? end($currentPrices)['price'] : 0;

include '../includes/header.php';
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto">
    <h1>📈 Price Trends</h1>
    <p>Track how prices change over time across Mbarara stores</p>
  </div>
</div>

<div class="ctr">

  <!-- PRODUCT SELECTOR -->
  <div class="card" style="margin-bottom:22px">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <div style="flex:1;min-width:220px">
        <label class="flabel">Select Product to Track</label>
        <select name="product_id" class="finput" onchange="this.form.submit()">
          <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $selectedPid==$p['id']?'selected':'' ?>>
            <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['unit']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <?php if ($selProduct): ?>

  <!-- PRODUCT SUMMARY STATS -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:24px">
    <div style="background:var(--white);border:1.5px solid var(--sand);border-radius:var(--r);padding:18px">
      <div style="font-size:.68rem;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:6px">Cheapest Now</div>
      <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.4rem;color:var(--leaf)"><?= formatPrice($minPrice) ?></div>
      <div style="font-size:.74rem;color:var(--muted)"><?= $currentPrices[0]['store_name'] ?? '—' ?></div>
    </div>
    <div style="background:var(--white);border:1.5px solid var(--sand);border-radius:var(--r);padding:18px">
      <div style="font-size:.68rem;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:6px">Most Expensive</div>
      <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.4rem;color:var(--red)"><?= formatPrice($maxPrice) ?></div>
      <div style="font-size:.74rem;color:var(--muted)"><?= end($currentPrices)['store_name'] ?? '—' ?></div>
    </div>
    <div style="background:var(--white);border:1.5px solid var(--sand);border-radius:var(--r);padding:18px">
      <div style="font-size:.68rem;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:6px">Max Saving</div>
      <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.4rem;color:var(--gold)"><?= formatPrice($maxPrice - $minPrice) ?></div>
      <div style="font-size:.74rem;color:var(--muted)">vs most expensive</div>
    </div>
    <div style="background:var(--white);border:1.5px solid var(--sand);border-radius:var(--r);padding:18px">
      <div style="font-size:.68rem;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:6px">Price Changes</div>
      <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.4rem;color:var(--forest)"><?= count($rows) ?></div>
      <div style="font-size:.74rem;color:var(--muted)">recorded changes</div>
    </div>
  </div>

  <!-- CURRENT PRICES BAR CHART -->
  <div class="card" style="margin-bottom:22px">
    <div class="card-title">🏬 Current Prices Across Stores — <?= htmlspecialchars($selProduct['name']) ?></div>
    <?php if (!empty($currentPrices)): ?>
    <?php foreach ($currentPrices as $i => $cp):
      $pct = $maxPrice > 0 ? round(($cp['price'] / $maxPrice) * 100) : 0;
      $isBest = $i === 0;
    ?>
    <div style="margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;margin-bottom:5px">
        <span style="font-weight:700;font-size:.88rem">
          <?= htmlspecialchars($cp['store_name']) ?>
          <?php if ($isBest): ?>
            <span style="background:#d4edda;color:#155724;font-size:.65rem;font-weight:800;padding:2px 7px;border-radius:99px;margin-left:6px">BEST</span>
          <?php endif; ?>
        </span>
        <span style="font-family:'Nunito',sans-serif;font-weight:900;color:<?= $isBest?'var(--leaf)':'var(--ink)' ?>">
          <?= formatPrice($cp['price']) ?>
        </span>
      </div>
      <div style="height:10px;background:var(--sand);border-radius:99px;overflow:hidden">
        <div style="height:100%;width:<?= $pct ?>%;background:<?= $isBest?'var(--leaf)':'var(--mint)' ?>;border-radius:99px;transition:width .5s"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="empty-state">
      <div class="ei">🏪</div>
      <p>No prices recorded for this product yet.</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- PRICE HISTORY LINE CHART -->
  <?php if (!empty($history)): ?>
  <div class="card" style="margin-bottom:22px">
    <div class="card-title">📈 Price History — <?= htmlspecialchars($selProduct['name']) ?></div>
    <canvas id="trendChart" style="width:100%;max-height:320px"></canvas>
  </div>

  <!-- ✅ Adapter MUST be loaded before the inline script -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
  <script>
  (function() {
    const raw = <?= json_encode($history) ?>;
    const storeColors = ['#18382a','#e9a820','#52b788','#2196F3','#9c27b0','#f4a261','#e63946'];
    const datasets = [];
    let i = 0;
    for (const [storeId, points] of Object.entries(raw)) {
      datasets.push({
        label: points[0].store,
        data: points.map(p => ({ x: p.date, y: parseFloat(p.price) })),
        borderColor: storeColors[i % storeColors.length],
        backgroundColor: storeColors[i % storeColors.length] + '22',
        tension: 0.4,
        fill: false,
        pointRadius: 4,
        pointHoverRadius: 7
      });
      i++;
    }
    new Chart(document.getElementById('trendChart'), {
      type: 'line',
      data: { datasets },
      options: {
        responsive: true,
        interaction: { intersect: false, mode: 'index' },
        scales: {
          x: {
            type: 'time',
            time: { unit: 'day', tooltipFormat: 'dd MMM yyyy' },
            title: { display: true, text: 'Date' }
          },
          y: {
            title: { display: true, text: 'Price (UGX)' },
            ticks: { callback: v => 'UGX ' + Number(v).toLocaleString() }
          }
        },
        plugins: {
          tooltip: {
            callbacks: {
              label: ctx => ctx.dataset.label + ': UGX ' + Number(ctx.parsed.y).toLocaleString()
            }
          },
          legend: { position: 'bottom' }
        }
      }
    });
  })();
  </script>

  <?php else: ?>
  <div class="card" style="margin-bottom:22px">
    <div class="empty-state">
      <div class="ei">📈</div>
      <p>No price history recorded yet for this product.<br>
        <small style="color:var(--muted)">History is recorded each time an admin updates a price.</small>
      </p>
    </div>
  </div>
  <?php endif; ?>

  <!-- SET ALERT CTA -->
  <div style="background:var(--forest);border-radius:var(--r);padding:22px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px">
    <div>
      <div style="font-family:'Nunito',sans-serif;font-weight:800;color:#fff;margin-bottom:4px">🔔 Want to know when the price drops?</div>
      <div style="color:rgba(255,255,255,.6);font-size:.84rem">Set a price alert for <?= htmlspecialchars($selProduct['name']) ?> and we will notify you.</div>
    </div>
    <a href="alerts.php" class="btn btn-sm" style="background:var(--gold);color:var(--forest);font-family:'Nunito',sans-serif;font-weight:900">Set Price Alert</a>
  </div>

  <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>