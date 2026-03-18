<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'Smart Shopping Route';
$uid       = (int)$_SESSION['user_id'];

// ── STORE COORDINATES (Mbarara City) ──────────────────────────
$storeCoords = [
    1 => ['lat'=>-0.6134,'lng'=>30.6589,'name'=>'FRESCO Supermarket',     'short'=>'FRESCO',    'address'=>'Buremba Road'],
    2 => ['lat'=>-0.6118,'lng'=>30.6601,'name'=>'Kirimi Supermarket',      'short'=>'Kirimi',    'address'=>'Buremba Road'],
    3 => ['lat'=>-0.6098,'lng'=>30.6572,'name'=>'Day to Day Supermarket',  'short'=>'Day2Day',   'address'=>'High Street'],
    4 => ['lat'=>-0.6142,'lng'=>30.6615,'name'=>'Apple Door to Door',      'short'=>'Apple D2D', 'address'=>'Bananuka Drive'],
    5 => ['lat'=>-0.6086,'lng'=>30.6558,'name'=>'Amazon Express',          'short'=>'Amazon',    'address'=>'High Street'],
    6 => ['lat'=>-0.6165,'lng'=>30.6632,'name'=>'Golf Course Supermarket', 'short'=>'Golf Cse',  'address'=>'Lower Circular Rd'],
    7 => ['lat'=>-0.6077,'lng'=>30.6543,'name'=>'Mbarara Central Market',  'short'=>'C.Market',  'address'=>'Buremba Road'],
];

// ── GET BASKET ─────────────────────────────────────────────────
$basketItems = $conn->query("
    SELECT b.quantity, p.id AS product_id, p.name AS product_name, p.unit,
           c.name AS category
    FROM basket b
    JOIN products p   ON b.product_id   = p.id
    JOIN categories c ON p.category_id  = c.id
    WHERE b.user_id = $uid
    ORDER BY c.name, p.name
")->fetch_all(MYSQLI_ASSOC);

if (empty($basketItems)) {
    setFlash('error', 'Your basket is empty. Add items first!');
    redirect('browse.php');
}

// ── GET ALL PRICES FOR BASKET ITEMS ───────────────────────────
$pids = implode(',', array_column($basketItems, 'product_id'));

$priceRows = $conn->query("
    SELECT pr.product_id, pr.store_id, pr.price,
           s.name AS store_name, s.short_name, s.tier
    FROM prices pr
    JOIN stores s ON pr.store_id = s.id
    WHERE pr.product_id IN ($pids) AND s.active = 1
    ORDER BY pr.price ASC
")->fetch_all(MYSQLI_ASSOC);

// Group all prices by product
$allPricesMap = [];
foreach ($priceRows as $pr) {
    $allPricesMap[$pr['product_id']][] = $pr;
}

// ── BUILD SPLIT BASKET ─────────────────────────────────────────
// For each item → cheapest store wins
$splitStops  = []; // store_id => { store info, items[] }
$splitItems  = []; // flat list of items with store assigned
$splitTotal  = 0;

foreach ($basketItems as $item) {
    $pid    = $item['product_id'];
    $prices = $allPricesMap[$pid] ?? [];
    if (empty($prices)) continue;

    // Cheapest store for this item
    $cheapest = $prices[0]; // already sorted ASC
    $sid      = $cheapest['store_id'];
    $price    = $cheapest['price'];
    $lineTotal= $price * $item['quantity'];
    $splitTotal += $lineTotal;

    // Also find most expensive for comparison
    $mostExpensive = end($prices);
    $worstPrice    = $mostExpensive['price'];
    $saving        = ($worstPrice - $price) * $item['quantity'];

    $splitItems[] = [
        'product_id'    => $pid,
        'product_name'  => $item['product_name'],
        'unit'          => $item['unit'],
        'quantity'      => $item['quantity'],
        'category'      => $item['category'],
        'store_id'      => $sid,
        'store_name'    => $cheapest['store_name'],
        'short_name'    => $cheapest['short_name'],
        'price'         => $price,
        'worst_price'   => $worstPrice,
        'line_total'    => $lineTotal,
        'saving'        => $saving,
        'all_prices'    => $prices,
    ];

    // Group by store stop
    if (!isset($splitStops[$sid])) {
        $splitStops[$sid] = [
            'store_id'   => $sid,
            'store_name' => $cheapest['store_name'],
            'short_name' => $cheapest['short_name'],
            'tier'       => $cheapest['tier'],
            'coords'     => $storeCoords[$sid] ?? null,
            'items'      => [],
            'subtotal'   => 0,
        ];
    }
    $splitStops[$sid]['items'][]   = [
        'name'      => $item['product_name'],
        'unit'      => $item['unit'],
        'qty'       => $item['quantity'],
        'price'     => $price,
        'line_total'=> $lineTotal,
        'saving'    => $saving,
    ];
    $splitStops[$sid]['subtotal'] += $lineTotal;
}

// ── SINGLE-STORE COMPARISON ────────────────────────────────────
// What would it cost to buy EVERYTHING at the single cheapest store?
$singleStoreTotals = [];
$stores = getStores($conn);
foreach ($stores as $s) {
    $sid = $s['id'];
    $total = 0; $canBuyAll = true;
    foreach ($basketItems as $item) {
        $found = false;
        foreach (($allPricesMap[$item['product_id']] ?? []) as $pr) {
            if ($pr['store_id'] == $sid) {
                $total += $pr['price'] * $item['quantity'];
                $found = true; break;
            }
        }
        if (!$found) $canBuyAll = false;
    }
    if ($total > 0) {
        $singleStoreTotals[$sid] = [
            'name'      => $s['name'],
            'total'     => $total,
            'can_buy_all'=> $canBuyAll,
        ];
    }
}
asort($singleStoreTotals, SORT_REGULAR);
$bestSingleStore  = reset($singleStoreTotals);
$worstSingleTotal = max(array_column($singleStoreTotals, 'total'));

$totalSaving   = $bestSingleStore ? $bestSingleStore['total'] - $splitTotal : 0;
$worstSaving   = $worstSingleTotal - $splitTotal;
$stopCount     = count($splitStops);

// Build Google Maps URL with all stops
$gmapsUrl = '';
$stopList  = array_values($splitStops);
if (count($stopList) >= 1) {
    $origin = urlencode(($stopList[0]['store_name'] ?? '') . ', Mbarara, Uganda');
    $dest   = urlencode((end($stopList)['store_name'] ?? '') . ', Mbarara, Uganda');
    $wps    = [];
    if (count($stopList) > 2) {
        foreach (array_slice($stopList, 1, -1) as $s) {
            $wps[] = urlencode($s['store_name'] . ', Mbarara, Uganda');
        }
    }
    $wpStr    = $wps ? '&waypoints=' . implode('|', $wps) : '';
    $gmapsUrl = "https://www.google.com/maps/dir/?api=1&origin=$origin&destination=$dest$wpStr&travelmode=walking";
}

include '../includes/header.php';
?>

<style>
.ph { background:linear-gradient(135deg,var(--forest),var(--leaf)); padding:28px 24px; }
.ph h1 { color:#fff; font-size:1.55rem; margin-bottom:3px; }
.ph p  { color:rgba(255,255,255,.6); font-size:.83rem; }

/* ── HERO SAVINGS BANNER ── */
.savings-hero {
  background:linear-gradient(135deg,#1a5c2e,#2d8a4e);
  border-radius:var(--r); padding:22px 28px; margin-bottom:24px;
  display:flex; justify-content:space-between; align-items:center;
  flex-wrap:wrap; gap:16px;
  border:1.5px solid rgba(82,183,136,.3);
}
.sh-stat { text-align:center; }
.sh-num  { font-family:'Nunito',sans-serif; font-weight:900; font-size:1.5rem; color:var(--gold); }
.sh-lbl  { font-size:.7rem; color:rgba(255,255,255,.55); font-weight:600; }

/* ── MAIN GRID ── */
.route-grid { display:grid; grid-template-columns:1fr 380px; gap:22px; }

/* ── MAP ── */
.map-card { background:var(--white); border:1.5px solid var(--sand); border-radius:var(--r); overflow:hidden; }
.map-header {
  background:var(--forest); padding:16px 20px;
  display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;
}
.map-title { font-family:'Nunito',sans-serif; font-weight:900; font-size:.95rem; color:#fff; }
.map-sub   { font-size:.74rem; color:rgba(255,255,255,.6); margin-top:2px; }
#map { height:360px; width:100%; }
.gmaps-btn {
  background:var(--gold); color:var(--forest); border:none;
  border-radius:99px; padding:8px 18px; font-family:'Nunito',sans-serif;
  font-weight:900; font-size:.78rem; cursor:pointer; transition:all .18s;
  text-decoration:none; display:inline-flex; align-items:center; gap:6px;
  white-space:nowrap;
}
.gmaps-btn:hover { background:#d4940f; transform:translateY(-1px); }

/* ── STOP CARDS ── */
.stops-wrap  { padding:16px 20px; }
.stops-title { font-family:'Nunito',sans-serif; font-weight:900; font-size:.88rem; margin-bottom:14px; color:var(--ink); }

.stop-card {
  border:1.5px solid var(--sand); border-radius:var(--r);
  margin-bottom:10px; overflow:hidden; transition:all .2s;
}
.stop-card:hover { box-shadow:0 4px 14px rgba(0,0,0,.08); }

.stop-header {
  display:flex; align-items:center; gap:12px;
  padding:13px 16px; cursor:pointer; background:var(--cream);
  transition:background .15s;
}
.stop-header:hover { background:var(--sand); }

.stop-num {
  width:32px; height:32px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  font-family:'Nunito',sans-serif; font-weight:900; font-size:.86rem;
  color:#fff; flex-shrink:0;
  box-shadow:0 2px 6px rgba(0,0,0,.2);
}
.stop-info { flex:1; min-width:0; }
.stop-name { font-weight:800; font-size:.9rem; }
.stop-meta { font-size:.7rem; color:var(--muted); margin-top:1px; }
.stop-right { text-align:right; flex-shrink:0; }
.stop-subtotal { font-family:'Nunito',sans-serif; font-weight:900; font-size:.95rem; }
.stop-items-count { font-size:.68rem; color:var(--muted); margin-top:1px; }
.stop-chevron { font-size:.7rem; color:var(--muted); margin-left:4px; transition:transform .2s; }
.stop-chevron.open { transform:rotate(180deg); }

.stop-body { display:none; border-top:1.5px solid var(--sand); }
.stop-body.open { display:block; }

.stop-item-row {
  display:flex; align-items:center; gap:10px;
  padding:11px 16px; border-bottom:1px solid var(--sand); font-size:.85rem;
}
.stop-item-row:last-child { border-bottom:none; }
.sir-icon {
  width:28px; height:28px; border-radius:8px;
  background:var(--cream); display:flex; align-items:center;
  justify-content:center; font-size:.9rem; flex-shrink:0;
}
.sir-info { flex:1; min-width:0; }
.sir-name { font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.sir-unit { font-size:.7rem; color:var(--muted); }
.sir-price { text-align:right; flex-shrink:0; }
.sir-total { font-family:'Nunito',sans-serif; font-weight:900; color:var(--forest); font-size:.9rem; }
.sir-saving { font-size:.68rem; color:#155724; background:#d4edda; padding:1px 6px; border-radius:99px; margin-top:2px; }

.stop-subtotal-row {
  display:flex; justify-content:space-between;
  padding:11px 16px; background:var(--cream);
  font-family:'Nunito',sans-serif; font-weight:900;
}

/* ── RIGHT SIDEBAR ── */
.route-sidebar { display:flex; flex-direction:column; gap:16px; }

/* ── ITEM ASSIGNMENT TABLE ── */
.assign-card { background:var(--white); border:1.5px solid var(--sand); border-radius:var(--r); overflow:hidden; }
.assign-header { background:var(--forest); padding:14px 18px; }
.assign-title  { font-family:'Nunito',sans-serif; font-weight:900; font-size:.9rem; color:#fff; margin-bottom:2px; }
.assign-sub    { font-size:.72rem; color:rgba(255,255,255,.55); }

.assign-row {
  display:flex; align-items:center; gap:10px;
  padding:11px 16px; border-bottom:1px solid var(--sand); font-size:.84rem;
}
.assign-row:last-child { border-bottom:none; }
.assign-product { flex:1; min-width:0; }
.assign-pname   { font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.assign-qty     { font-size:.7rem; color:var(--muted); }
.assign-store-chip {
  font-size:.68rem; font-weight:800; padding:3px 9px;
  border-radius:99px; color:#fff; white-space:nowrap; flex-shrink:0;
}
.assign-price { text-align:right; flex-shrink:0; min-width:72px; }
.assign-p     { font-weight:800; font-size:.84rem; }
.assign-save  { font-size:.65rem; color:#155724; }

/* ── SINGLE STORE COMPARISON ── */
.compare-card { background:var(--white); border:1.5px solid var(--sand); border-radius:var(--r); padding:18px; }
.compare-row  {
  display:flex; justify-content:space-between; align-items:center;
  padding:9px 0; border-bottom:1px solid var(--sand); font-size:.84rem;
}
.compare-row:last-child { border-bottom:none; }
.compare-highlight {
  background:#f0fdf4; border:1.5px solid #a3d4b5;
  border-radius:var(--rs); padding:11px 14px;
  display:flex; justify-content:space-between; align-items:center;
  margin-bottom:12px; font-size:.84rem;
}

/* ── ROUTE CONNECTOR ── */
.route-connector {
  display:flex; align-items:center; gap:8px;
  padding:4px 16px; font-size:.72rem; color:var(--muted); font-weight:600;
}
.route-connector::before { content:'↓'; color:var(--mint); font-size:1rem; font-weight:900; }

@media(max-width:900px){ .route-grid { grid-template-columns:1fr; } }
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css"/>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <h1>🗺️ Smart Shopping Route</h1>
      <p>Each item sent to the store that sells it cheapest — then mapped as a route</p>
    </div>
    <a href="basket.php" class="btn btn-primary btn-sm">← Back to Basket</a>
  </div>
</div>

<div class="ctr">

  <!-- SAVINGS HERO -->
  <div class="savings-hero">
    <div class="sh-stat">
      <div class="sh-num"><?= $stopCount ?></div>
      <div class="sh-lbl">Store<?= $stopCount>1?'s':'' ?> to Visit</div>
    </div>
    <div class="sh-stat">
      <div class="sh-num"><?= formatPrice($splitTotal) ?></div>
      <div class="sh-lbl">Your Optimised Total</div>
    </div>
    <?php if ($bestSingleStore && $totalSaving > 0): ?>
    <div class="sh-stat">
      <div class="sh-num">UGX <?= number_format($totalSaving) ?></div>
      <div class="sh-lbl">Saved vs Best Single Store</div>
    </div>
    <?php endif; ?>
    <?php if ($worstSaving > 0): ?>
    <div class="sh-stat">
      <div class="sh-num">UGX <?= number_format($worstSaving) ?></div>
      <div class="sh-lbl">Saved vs Most Expensive</div>
    </div>
    <?php endif; ?>
    <div style="background:rgba(255,255,255,.1);border-radius:var(--r);padding:12px 16px;font-size:.78rem;color:rgba(255,255,255,.8);max-width:260px">
      💡 <strong style="color:var(--gold)">How it works for you:</strong> SPECS has checked every item in your basket and assigned each one to the store selling it cheapest. Your route visits all those stores in order. Good luck!
    </div>
  </div>

  <div class="route-grid">

    <!-- LEFT: MAP + STOPS -->
    <div>
      <div class="map-card">
        <div class="map-header">
          <div>
            <div class="map-title">📍 Your Route — <?= $stopCount ?> Stop<?= $stopCount>1?'s':'' ?> in Mbarara</div>
            <div class="map-sub">Follow the numbered markers in order</div>
          </div>
          <?php if ($gmapsUrl): ?>
          <a href="<?= $gmapsUrl ?>" target="_blank" class="gmaps-btn">
            🗺️ Open in Google Maps
          </a>
          <?php endif; ?>
        </div>

        <div id="map"></div>

        <div class="stops-wrap">
          <div class="stops-title">🚶 Step-by-step Route</div>
          <?php
          $stopColors = ['#18382a','#e9a820','#2196F3','#9c27b0','#e63946','#f4a261','#52b788'];
          $stopNum    = 1;
          foreach ($splitStops as $sid => $stop):
            $color = $stopColors[($stopNum-1) % count($stopColors)];
          ?>
          <?php if ($stopNum > 1): ?>
          <div class="route-connector">Walk to next store</div>
          <?php endif; ?>

          <div class="stop-card" id="stopcard-<?= $sid ?>">
            <div class="stop-header" onclick="toggleStop(<?= $sid ?>, <?= $stopNum ?>)">
              <div class="stop-num" style="background:<?= $color ?>"><?= $stopNum ?></div>
              <div class="stop-info">
                <div class="stop-name"><?= htmlspecialchars($stop['store_name']) ?></div>
                <div class="stop-meta">
                  📍 <?= htmlspecialchars($storeCoords[$sid]['address'] ?? 'Mbarara') ?> ·
                  <?= ucfirst($stop['tier']) ?>
                </div>
              </div>
              <div class="stop-right">
                <div class="stop-subtotal" style="color:<?= $color ?>"><?= formatPrice($stop['subtotal']) ?></div>
                <div class="stop-items-count"><?= count($stop['items']) ?> item<?= count($stop['items'])>1?'s':'' ?> to buy <span class="stop-chevron" id="chev-<?= $sid ?>">▼</span></div>
              </div>
            </div>

            <div class="stop-body open" id="stopbody-<?= $sid ?>">
              <?php foreach ($stop['items'] as $si): ?>
              <div class="stop-item-row">
                <div class="sir-icon">🛒</div>
                <div class="sir-info">
                  <div class="sir-name"><?= htmlspecialchars($si['name']) ?></div>
                  <div class="sir-unit"><?= htmlspecialchars($si['unit']) ?> × <?= $si['qty'] ?></div>
                </div>
                <div class="sir-price">
                  <div class="sir-total"><?= formatPrice($si['line_total']) ?></div>
                  <?php if ($si['saving'] > 0): ?>
                  <div class="sir-saving">Save <?= formatPrice($si['saving']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
              <div class="stop-subtotal-row">
                <span>Subtotal at <?= htmlspecialchars($stop['short_name']) ?></span>
                <span style="color:<?= $color ?>"><?= formatPrice($stop['subtotal']) ?></span>
              </div>
            </div>
          </div>

          <?php $stopNum++; endforeach; ?>

          <!-- GRAND TOTAL ROW -->
          <div style="background:var(--forest);border-radius:var(--r);padding:16px 20px;margin-top:14px;display:flex;justify-content:space-between;align-items:center">
            <div>
              <div style="font-family:'Nunito',sans-serif;font-weight:900;color:#fff;font-size:.95rem">🎉 Grand Total</div>
              <div style="font-size:.74rem;color:rgba(255,255,255,.55)">Buying each item at its cheapest store</div>
            </div>
            <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.4rem;color:var(--gold)"><?= formatPrice($splitTotal) ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT SIDEBAR -->
    <div class="route-sidebar">

      <!-- ITEM ASSIGNMENT TABLE -->
      <div class="assign-card">
        <div class="assign-header">
          <div class="assign-title">🛒 Where Each Item is Cheapest</div>
          <div class="assign-sub">SPECS assigned each item to its cheapest store</div>
        </div>
        <?php
        $stopColors2 = ['#18382a','#e9a820','#2196F3','#9c27b0','#e63946','#f4a261','#52b788'];
        // Build store → color map
        $storeColorMap = [];
        $ci = 0;
        foreach ($splitStops as $sid => $st) {
          $storeColorMap[$sid] = $stopColors2[$ci % count($stopColors2)];
          $ci++;
        }
        foreach ($splitItems as $item):
          $chipColor = $storeColorMap[$item['store_id']] ?? 'var(--forest)';
        ?>
        <div class="assign-row">
          <div class="assign-product">
            <div class="assign-pname"><?= htmlspecialchars($item['product_name']) ?></div>
            <div class="assign-qty"><?= $item['unit'] ?> × <?= $item['quantity'] ?></div>
          </div>
          <span class="assign-store-chip" style="background:<?= $chipColor ?>">
            <?= htmlspecialchars($item['short_name']) ?>
          </span>
          <div class="assign-price">
            <div class="assign-p"><?= formatPrice($item['price']) ?></div>
            <?php if ($item['saving'] > 0): ?>
            <div class="assign-save">save <?= formatPrice($item['saving']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <div style="padding:13px 16px;background:var(--cream);display:flex;justify-content:space-between;font-family:'Nunito',sans-serif;font-weight:900;border-top:2px solid var(--sand)">
          <span>Total</span>
          <span style="color:var(--leaf)"><?= formatPrice($splitTotal) ?></span>
        </div>
      </div>

      <!-- COMPARISON: single store vs split -->
      <div class="compare-card">
        <div class="card-title">📊 Single Store vs Split Route</div>
        <div style="font-size:.76rem;color:var(--muted);margin-bottom:12px">How much you save by splitting across stores</div>

        <div class="compare-highlight">
          <div>
            <div style="font-size:.68rem;font-weight:700;color:var(--leaf);text-transform:uppercase;margin-bottom:2px">✅ Your Split Route</div>
            <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.1rem;color:var(--leaf)"><?= formatPrice($splitTotal) ?></div>
          </div>
          <div style="font-size:.8rem;color:var(--leaf);font-weight:700"><?= $stopCount ?> stores</div>
        </div>

        <div style="font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;margin-bottom:8px">If you bought all at one store:</div>
        <?php foreach ($singleStoreTotals as $sid => $ss):
          $diff = $ss['total'] - $splitTotal;
        ?>
        <div class="compare-row">
          <div>
            <span style="font-weight:700"><?= htmlspecialchars($ss['name']) ?></span>
            <?php if (!$ss['can_buy_all']): ?>
            <span style="font-size:.66rem;color:var(--red)"> (missing items)</span>
            <?php endif; ?>
          </div>
          <div style="text-align:right">
            <div style="font-weight:700"><?= formatPrice($ss['total']) ?></div>
            <?php if ($diff > 0): ?>
            <div style="font-size:.68rem;color:var(--red)">+<?= formatPrice($diff) ?> more</div>
            <?php else: ?>
            <div style="font-size:.68rem;color:var(--leaf)">same/cheaper</div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- BACK TO BASKET -->
      <a href="basket.php" class="btn btn-green" style="width:100%;justify-content:center">
        ← Back to Basket & Save Plan
      </a>

    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
const storeCoords  = <?= json_encode($storeCoords) ?>;
const splitStopsJS = <?= json_encode(array_values($splitStops)) ?>;
const stopColors   = ['#18382a','#e9a820','#2196F3','#9c27b0','#e63946','#f4a261','#52b788'];

// ── INIT MAP ──────────────────────────────────────────────────
const map = L.map('map').setView([-0.6120, 30.6590], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(map);

// ── MARKERS & ROUTE LINE ──────────────────────────────────────
const latLngs = [];
splitStopsJS.forEach((stop, i) => {
  const c = storeCoords[stop.store_id];
  if (!c) return;
  const color = stopColors[i % stopColors.length];
  const textColor = color === '#e9a820' ? '#18382a' : '#fff';

  const icon = L.divIcon({
    html: `<div style="background:${color};color:${textColor};width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Nunito',sans-serif;font-weight:900;font-size:15px;border:3px solid #fff;box-shadow:0 3px 12px rgba(0,0,0,.4)">${i+1}</div>`,
    iconSize: [36,36], className: ''
  });

  const itemList = stop.items.map(it =>
    `<div style="padding:4px 0;border-bottom:1px solid #eee;font-size:.78rem">
      <b>${it.name}</b> × ${it.qty} &nbsp;
      <span style="color:#2d6a4f;font-weight:700">UGX ${it.line_total.toLocaleString()}</span>
     </div>`
  ).join('');

  L.marker([c.lat, c.lng], {icon}).addTo(map)
    .bindPopup(`
      <div style="font-family:'Nunito Sans',sans-serif;min-width:200px">
        <div style="background:${color};color:${textColor};padding:8px 12px;margin:-8px -12px 10px;border-radius:6px 6px 0 0;font-family:'Nunito',sans-serif;font-weight:900">
          Stop ${i+1}: ${c.name}
        </div>
        <div style="font-size:.74rem;color:#888;margin-bottom:8px">${c.address} · Buy ${stop.items.length} item${stop.items.length>1?'s':''}</div>
        ${itemList}
        <div style="padding:8px 0 0;font-family:'Nunito',sans-serif;font-weight:900;font-size:.9rem;color:#18382a">
          Subtotal: UGX ${stop.subtotal.toLocaleString()}
        </div>
      </div>
    `, {maxWidth: 240});

  latLngs.push([c.lat, c.lng]);
});

// Route polyline
if (latLngs.length > 1) {
  L.polyline(latLngs, {
    color:'#52b788', weight:4, opacity:.85, dashArray:'10,6'
  }).addTo(map);
  map.fitBounds(L.latLngBounds(latLngs).pad(0.18));
} else if (latLngs.length === 1) {
  map.setView(latLngs[0], 16);
}

// ── TOGGLE STOP BODY ─────────────────────────────────────────
function toggleStop(sid, stopNum) {
  const body  = document.getElementById('stopbody-' + sid);
  const chev  = document.getElementById('chev-' + sid);
  const isOpen= body.classList.contains('open');
  body.classList.toggle('open');
  chev.classList.toggle('open');

  // Fly map to this store
  const c = storeCoords[sid];
  if (c) map.flyTo([c.lat, c.lng], 16, {duration:1.2});
}
</script>

<?php include '../includes/footer.php'; ?>