<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'Browse Products';
$uid       = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)$_POST['product_id'];
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $conn->query("
        INSERT INTO basket (user_id, product_id, quantity)
        VALUES ($uid, $pid, $qty)
        ON DUPLICATE KEY UPDATE quantity = quantity + $qty
    ");
    setFlash('success', '✅ Item added to basket!');
    redirect('browse.php?' . http_build_query($_GET));
}

$search    = isset($_GET['q'])    ? $conn->real_escape_string(trim($_GET['q'])) : '';
$catFilter = isset($_GET['cat'])  ? (int)$_GET['cat']  : 0;
$sortBy    = isset($_GET['sort']) ? clean($_GET['sort']) : 'name';

$categories = getCategories($conn);
$stores     = getStores($conn);

$where = "WHERE p.active = 1";
if ($search)    $where .= " AND (p.name LIKE '%$search%' OR c.name LIKE '%$search%')";
if ($catFilter) $where .= " AND p.category_id = $catFilter";

$orderBy = match($sortBy) {
    'price_asc'  => 'min_price ASC',
    'price_desc' => 'min_price DESC',
    'savings'    => 'savings DESC',
    default      => 'c.name ASC, p.name ASC'
};

$products = $conn->query("
    SELECT p.id, p.name, p.unit, p.description, c.name AS cat_name,
           MIN(pr.price) AS min_price, MAX(pr.price) AS max_price,
           (MAX(pr.price) - MIN(pr.price)) AS savings
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN prices pr    ON pr.product_id  = p.id
    $where
    GROUP BY p.id
    ORDER BY $orderBy
")->fetch_all(MYSQLI_ASSOC);

$basketPids = [];
$br = $conn->query("SELECT product_id FROM basket WHERE user_id=$uid");
while ($row = $br->fetch_assoc()) $basketPids[] = $row['product_id'];

$allStorePrices = [];
if (!empty($products)) {
    $pids = implode(',', array_column($products, 'id'));
    $priceRows = $conn->query("
        SELECT pr.product_id, pr.price, s.id AS store_id, s.name AS store_name,
               s.short_name, s.tier, s.address
        FROM prices pr
        JOIN stores s ON pr.store_id = s.id
        WHERE pr.product_id IN ($pids) AND s.active=1
        ORDER BY pr.price ASC
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($priceRows as $pr) {
        $allStorePrices[$pr['product_id']][] = $pr;
    }
}

// Pass all store prices to JS as JSON
$pricesForJS = json_encode($allStorePrices);

include '../includes/header.php';
?>

<style>
/* ── PAGE HEADER ── */
.ph { background:linear-gradient(135deg,var(--forest),var(--leaf)); padding:28px 24px; }
.ph h1 { color:#fff; font-size:1.55rem; margin-bottom:3px; }
.ph p  { color:rgba(255,255,255,.6); font-size:.83rem; }

/* ── CATEGORY PILLS ── */
.cat-pills { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
.cat-pill  {
  padding:6px 14px; border-radius:99px; font-size:.78rem; font-weight:700;
  border:1.5px solid var(--sand); background:var(--white); color:var(--ink);
  cursor:pointer; transition:all .18s; text-decoration:none;
}
.cat-pill:hover,.cat-pill.active { background:var(--forest); color:#fff; border-color:var(--forest); }

/* ── PRODUCT GRID ── */
.products-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(280px,1fr));
  gap:18px;
}

/* ── PRODUCT CARD ── */
.product-card {
  background:var(--white); border:1.5px solid var(--sand);
  border-radius:var(--r); overflow:hidden; transition:all .2s;
}
.product-card:hover {
  border-color:var(--mint);
  box-shadow:0 6px 20px rgba(24,56,42,.1);
  transform:translateY(-2px);
}
.pc-body { padding:16px 18px; }
.pc-name { font-weight:800; font-size:.94rem; margin-bottom:2px; }
.pc-unit { font-size:.74rem; color:var(--muted); }
.pc-cat  { display:inline-block; background:var(--cream); color:var(--muted); font-size:.66rem; font-weight:700; padding:2px 8px; border-radius:99px; margin-top:6px; }

.pc-price-row {
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 18px; border-top:1px solid var(--sand); border-bottom:1px solid var(--sand);
}
.pc-best-price { font-family:'Nunito',sans-serif; font-weight:900; font-size:1.15rem; color:var(--leaf); }
.pc-worst-price { font-size:.76rem; color:var(--muted); text-decoration:line-through; }
.pc-save-chip { background:#d4edda; color:#155724; font-size:.66rem; font-weight:800; padding:3px 10px; border-radius:99px; }

/* ── COMPARE + ADD BUTTONS ── */
.pc-actions { display:flex; gap:8px; padding:12px 18px; }
.compare-btn {
  flex:1; background:var(--cream); border:1.5px solid var(--sand);
  border-radius:var(--rs); padding:9px 10px;
  font-family:'Nunito',sans-serif; font-weight:800; font-size:.76rem;
  cursor:pointer; transition:all .18s; color:var(--forest);
  display:flex; align-items:center; justify-content:center; gap:5px;
}
.compare-btn:hover { background:var(--forest); color:#fff; border-color:var(--forest); }

.pc-add-bar { display:flex; gap:8px; padding:0 18px 14px; }
.pc-qty {
  width:52px; padding:7px; border:1.5px solid var(--sand);
  border-radius:var(--rs); font-size:.88rem; text-align:center;
  font-family:'Nunito Sans',sans-serif;
}
.pc-add-btn {
  flex:1; background:var(--forest); color:#fff; border:none;
  border-radius:var(--rs); padding:8px;
  font-family:'Nunito',sans-serif; font-weight:900; font-size:.82rem;
  cursor:pointer; transition:all .18s;
}
.pc-add-btn:hover  { background:var(--leaf); }
.pc-add-btn.in-basket { background:var(--leaf); }

/* ══════════════════════════════════════════
   GLASSMORPHISM OVERLAY (iPhone style)
══════════════════════════════════════════ */
#glass-overlay {
  position:fixed;
  inset:0;
  z-index:9000;
  background:rgba(0,0,0,.35);
  backdrop-filter:blur(6px);
  -webkit-backdrop-filter:blur(6px);
  display:none;
  align-items:flex-end;
  justify-content:center;
  padding-bottom:0;
  animation:overlayIn .25s ease;
}
#glass-overlay.open { display:flex; }

@keyframes overlayIn {
  from { opacity:0; }
  to   { opacity:1; }
}

/* ── GLASS PANEL ── */
#glass-panel {
  width:100%;
  max-width:520px;
  background:rgba(255,255,255,.18);
  backdrop-filter:blur(40px) saturate(180%);
  -webkit-backdrop-filter:blur(40px) saturate(180%);
  border:1px solid rgba(255,255,255,.35);
  border-radius:28px 28px 0 0;
  padding:0 0 32px;
  box-shadow:0 -20px 60px rgba(0,0,0,.3);
  animation:panelUp .32s cubic-bezier(.34,1.2,.64,1);
  max-height:85vh;
  overflow-y:auto;
}

@keyframes panelUp {
  from { transform:translateY(100%); opacity:0; }
  to   { transform:translateY(0);    opacity:1; }
}

/* ── DRAG HANDLE ── */
.glass-handle {
  width:44px; height:5px;
  background:rgba(255,255,255,.5);
  border-radius:99px;
  margin:12px auto 0;
}

/* ── GLASS HEADER ── */
.glass-header {
  padding:16px 22px 12px;
  border-bottom:1px solid rgba(255,255,255,.2);
}
.glass-title {
  font-family:'Nunito',sans-serif;
  font-weight:900;
  font-size:1.05rem;
  color:#fff;
  text-shadow:0 1px 4px rgba(0,0,0,.3);
  margin-bottom:2px;
}
.glass-unit {
  font-size:.76rem;
  color:rgba(255,255,255,.7);
  font-weight:600;
}

/* ── GLASS STORE ROWS ── */
.glass-stores { padding:10px 14px; }

.glass-store-row {
  display:flex;
  align-items:center;
  gap:12px;
  padding:13px 14px;
  border-radius:16px;
  margin-bottom:8px;
  background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.2);
  transition:all .18s;
  cursor:pointer;
}
.glass-store-row:hover {
  background:rgba(255,255,255,.22);
  transform:scale(1.01);
}
.glass-store-row.best-row {
  background:rgba(82,183,136,.25);
  border-color:rgba(82,183,136,.5);
}

/* Rank circle */
.glass-rank {
  width:32px; height:32px;
  border-radius:50%;
  background:rgba(255,255,255,.15);
  display:flex; align-items:center; justify-content:center;
  font-family:'Nunito',sans-serif; font-weight:900; font-size:.82rem;
  color:#fff; flex-shrink:0;
}
.glass-store-row.best-row .glass-rank {
  background:var(--gold); color:var(--forest);
}

/* Store info */
.glass-store-info { flex:1; min-width:0; }
.glass-store-name {
  font-family:'Nunito',sans-serif; font-weight:800;
  font-size:.9rem; color:#fff;
  text-shadow:0 1px 3px rgba(0,0,0,.3);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.glass-store-tier {
  font-size:.68rem; color:rgba(255,255,255,.6); margin-top:1px;
}
.glass-best-chip {
  background:var(--gold); color:var(--forest);
  font-size:.6rem; font-weight:900;
  padding:2px 7px; border-radius:99px; margin-left:6px;
  vertical-align:middle;
}

/* Price section */
.glass-price-col { text-align:right; }
.glass-price {
  font-family:'Nunito',sans-serif; font-weight:900;
  font-size:1.05rem; color:#fff;
  text-shadow:0 1px 4px rgba(0,0,0,.3);
}
.glass-store-row.best-row .glass-price { color:var(--gold); }
.glass-more-pct {
  font-size:.68rem; color:rgba(255,180,180,.9); margin-top:1px;
}

/* Add button in glass */
.glass-add-btn {
  background:rgba(255,255,255,.2);
  border:1px solid rgba(255,255,255,.35);
  color:#fff; border-radius:99px;
  padding:6px 14px;
  font-family:'Nunito',sans-serif; font-weight:800;
  font-size:.74rem; cursor:pointer; transition:all .18s;
  white-space:nowrap; flex-shrink:0;
}
.glass-add-btn:hover { background:var(--gold); color:var(--forest); border-color:var(--gold); }
.glass-store-row.best-row .glass-add-btn {
  background:var(--gold); color:var(--forest); border-color:var(--gold);
}

/* ── GLASS FOOTER ── */
.glass-footer {
  padding:12px 22px 0;
  border-top:1px solid rgba(255,255,255,.15);
}
.glass-footer-note {
  font-size:.72rem; color:rgba(255,255,255,.55);
  text-align:center;
}

/* ── PRICE BAR ── */
.glass-bar-track {
  height:4px; background:rgba(255,255,255,.15);
  border-radius:99px; overflow:hidden; margin-top:4px;
}
.glass-bar-fill {
  height:100%; border-radius:99px;
  background:linear-gradient(90deg, var(--gold), var(--mint));
}

@media(max-width:600px){
  .products-grid { grid-template-columns:1fr; }
  #glass-panel   { max-width:100%; border-radius:24px 24px 0 0; }
}
</style>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <h1>🛍️ Browse Products</h1>
      <p><?= count($products) ?> products found · Tap "Compare Stores to compare prices in each store" on any product</p>
    </div>
    <a href="basket.php" class="btn btn-primary btn-sm">🛒 View Basket</a>
  </div>
</div>

<div class="ctr">
  <?php showFlash(); ?>

  <!-- SEARCH & FILTER -->
  <div class="card" style="margin-bottom:20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:2;min-width:200px">
        <label class="flabel">Search Products</label>
        <input type="text" name="q" class="finput" placeholder="e.g. Sugar, Milk, Rice..." value="<?= htmlspecialchars($search) ?>"/>
      </div>
      <div style="min-width:170px">
        <label class="flabel">Category</label>
        <select name="cat" class="finput">
          <option value="0">All Categories</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $catFilter==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:150px">
        <label class="flabel">Sort By</label>
        <select name="sort" class="finput">
          <option value="name"       <?= $sortBy==='name'       ?'selected':'' ?>>Name A–Z</option>
          <option value="price_asc"  <?= $sortBy==='price_asc'  ?'selected':'' ?>>Cheapest First</option>
          <option value="price_desc" <?= $sortBy==='price_desc' ?'selected':'' ?>>Most Expensive</option>
          <option value="savings"    <?= $sortBy==='savings'    ?'selected':'' ?>>Biggest Savings</option>
        </select>
      </div>
      <button type="submit" class="btn btn-green">🔍 Search</button>
      <a href="browse.php" class="btn" style="background:var(--sand)">Clear</a>
    </form>
  </div>

  <!-- CATEGORY PILLS -->
  <div class="cat-pills">
    <a href="browse.php<?= $search?"?q=".urlencode($search):'' ?>" class="cat-pill <?= !$catFilter?'active':'' ?>">All</a>
    <?php foreach ($categories as $c): ?>
    <a href="browse.php?cat=<?= $c['id'] ?><?= $search?"&q=".urlencode($search):'' ?>" class="cat-pill <?= $catFilter==$c['id']?'active':'' ?>">
      <?= htmlspecialchars($c['name']) ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- PRODUCTS GRID -->
  <?php if (empty($products)): ?>
    <div class="empty-state card"><div class="ei">🔍</div><p>No products found.</p></div>
  <?php else: ?>
  <div class="products-grid">
    <?php foreach ($products as $p):
      $storePrices = $allStorePrices[$p['id']] ?? [];
      $inBasket    = in_array($p['id'], $basketPids);
      $minPrice    = !empty($storePrices) ? $storePrices[0]['price'] : 0;
      $savings     = $p['max_price'] - $p['min_price'];
    ?>
    <div class="product-card">
      <div class="pc-body">
        <div class="pc-name"><?= htmlspecialchars($p['name']) ?></div>
        <div class="pc-unit"><?= htmlspecialchars($p['unit']) ?></div>
        <span class="pc-cat"><?= htmlspecialchars($p['cat_name']) ?></span>
      </div>

      <div class="pc-price-row">
        <div>
          <div style="font-size:.62rem;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:2px">Best Price</div>
          <div class="pc-best-price"><?= formatPrice($minPrice) ?></div>
          <?php if ($p['max_price'] > $minPrice): ?>
          <div class="pc-worst-price"><?= formatPrice($p['max_price']) ?></div>
          <?php endif; ?>
        </div>
        <?php if ($savings > 1000): ?>
        <span class="pc-save-chip">Save <?= formatPrice($savings) ?></span>
        <?php endif; ?>
      </div>

      <div class="pc-actions">
        <button class="compare-btn" onclick="openGlass(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', '<?= addslashes($p['unit']) ?>')">
          🏬 Compare Stores
        </button>
      </div>

      <div class="pc-add-bar">
        <form method="POST" style="display:flex;gap:8px;width:100%">
          <input type="hidden" name="product_id" value="<?= $p['id'] ?>"/>
          <input type="number" name="quantity" value="1" min="1" max="20" class="pc-qty"/>
          <button type="submit" class="pc-add-btn <?= $inBasket?'in-basket':'' ?>">
            <?= $inBasket ? '✅ In Basket' : '🛒 Add to Basket' ?>
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════
     GLASSMORPHISM OVERLAY
═══════════════════════════════════════ -->
<div id="glass-overlay" onclick="handleOverlayClick(event)">
  <div id="glass-panel">
    <div class="glass-handle"></div>
    <div class="glass-header">
      <div class="glass-title" id="glass-title">Product Name</div>
      <div class="glass-unit"  id="glass-unit">unit</div>
    </div>
    <div class="glass-stores" id="glass-stores">
      <!-- Rows injected by JS -->
    </div>
    <div class="glass-footer">
      <div class="glass-footer-note">Tap anywhere outside to close</div>
    </div>
  </div>
</div>

<script>
// All store prices from PHP
const allPrices = <?= $pricesForJS ?>;

function openGlass(pid, name, unit) {
  const stores  = allPrices[pid] || [];
  const overlay = document.getElementById('glass-overlay');
  const panel   = document.getElementById('glass-panel');

  document.getElementById('glass-title').textContent = name;
  document.getElementById('glass-unit').textContent  = unit;

  if (!stores.length) {
    document.getElementById('glass-stores').innerHTML =
      '<div style="text-align:center;padding:24px;color:rgba(255,255,255,.6)">No prices available</div>';
  } else {
    const minP = Math.min(...stores.map(s => s.price));
    const maxP = Math.max(...stores.map(s => s.price));

    document.getElementById('glass-stores').innerHTML = stores.map((s, i) => {
      const isBest  = i === 0;
      const pct     = minP > 0 ? Math.round(((s.price - minP) / minP) * 100) : 0;
      const barW    = maxP > 0 ? Math.round((s.price / maxP) * 100) : 100;

      return `
        <div class="glass-store-row ${isBest ? 'best-row' : ''}" onclick="addFromGlass(${pid}, 1)">
          <div class="glass-rank">${i + 1}</div>
          <div class="glass-store-info">
            <div class="glass-store-name">
              ${escHtml(s.store_name)}
              ${isBest ? '<span class="glass-best-chip">CHEAPEST</span>' : ''}
            </div>
            <div class="glass-store-tier">${capitalise(s.tier)}</div>
            <div class="glass-bar-track">
              <div class="glass-bar-fill" style="width:${barW}%"></div>
            </div>
          </div>
          <div class="glass-price-col">
            <div class="glass-price">UGX ${parseInt(s.price).toLocaleString()}</div>
            ${!isBest && pct > 0 ? `<div class="glass-more-pct">+${pct}% more</div>` : ''}
          </div>
          <button class="glass-add-btn" onclick="event.stopPropagation(); addFromGlass(${pid}, 1, '${escHtml(s.store_name)}', ${s.price})">
            ${isBest ? '🛒 Add' : '+ Add'}
          </button>
        </div>`;
    }).join('');
  }

  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';

  // Re-animate panel
  panel.style.animation = 'none';
  panel.offsetHeight;
  panel.style.animation = 'panelUp .32s cubic-bezier(.34,1.2,.64,1)';
}

function closeGlass() {
  const overlay = document.getElementById('glass-overlay');
  overlay.classList.remove('open');
  document.body.style.overflow = '';
}

function handleOverlayClick(e) {
  // Close if clicking outside the panel
  if (e.target === document.getElementById('glass-overlay')) {
    closeGlass();
  }
}

// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeGlass();
});

// Swipe down to close
let touchStartY = 0;
document.getElementById('glass-panel').addEventListener('touchstart', e => {
  touchStartY = e.touches[0].clientY;
});
document.getElementById('glass-panel').addEventListener('touchend', e => {
  const diff = e.changedTouches[0].clientY - touchStartY;
  if (diff > 80) closeGlass(); // swipe down 80px closes
});

function addFromGlass(pid, qty, storeName, price) {
  // Submit add to basket via hidden form
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'browse.php<?= $search ? '?q='.urlencode($search) : '' ?><?= $catFilter ? ($search ? '&' : '?').'cat='.$catFilter : '' ?>';

  const inputs = { product_id: pid, quantity: qty };
  for (const [k, v] of Object.entries(inputs)) {
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = k; inp.value = v;
    form.appendChild(inp);
  }
  document.body.appendChild(form);
  form.submit();
}

function escHtml(str) {
  const d = document.createElement('div');
  d.appendChild(document.createTextNode(str));
  return d.innerHTML;
}

function capitalise(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}
</script>

<?php include '../includes/footer.php'; ?>