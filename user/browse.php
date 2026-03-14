<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'Browse Products';
$uid       = (int)$_SESSION['user_id'];

// ── ADD TO BASKET ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)$_POST['product_id'];
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $conn->query("
        INSERT INTO basket (user_id, product_id, quantity)
        VALUES ($uid, $pid, $qty)
        ON DUPLICATE KEY UPDATE quantity = quantity + $qty
    ");
    setFlash('success', 'Item added to basket! 🛒');
    redirect('browse.php?' . http_build_query($_GET));
}

// ── FILTERS ───────────────────────────────────────────────────
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
    SELECT p.id, p.name, p.unit, p.description, c.name AS cat_name, c.icon,
           MIN(pr.price) AS min_price,
           MAX(pr.price) AS max_price,
           (MAX(pr.price) - MIN(pr.price)) AS savings
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN prices pr    ON pr.product_id  = p.id
    $where
    GROUP BY p.id
    ORDER BY $orderBy
")->fetch_all(MYSQLI_ASSOC);

// Get user's basket product IDs
$basketPids = [];
$br = $conn->query("SELECT product_id FROM basket WHERE user_id=$uid");
while ($row = $br->fetch_assoc()) $basketPids[] = $row['product_id'];

include '../includes/header.php';
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto">
    <h1>🛍️ Browse Products</h1>
    <p><?= count($products) ?> products found across <?= count($stores) ?> stores</p>
  </div>
</div>

<div class="ctr">
  <?php showFlash(); ?>

  <!-- SEARCH & FILTER BAR -->
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
      <div style="min-width:160px">
        <label class="flabel">Sort By</label>
        <select name="sort" class="finput">
          <option value="name"        <?= $sortBy==='name'       ?'selected':'' ?>>Name A-Z</option>
          <option value="price_asc"   <?= $sortBy==='price_asc'  ?'selected':'' ?>>Cheapest First</option>
          <option value="price_desc"  <?= $sortBy==='price_desc' ?'selected':'' ?>>Most Expensive</option>
          <option value="savings"     <?= $sortBy==='savings'    ?'selected':'' ?>>Biggest Savings</option>
        </select>
      </div>
      <button type="submit" class="btn btn-green">🔍 Search</button>
      <a href="browse.php" class="btn" style="background:var(--sand)">Clear</a>
    </form>
  </div>

  <!-- CATEGORY PILLS -->
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px">
    <a href="browse.php" style="text-decoration:none">
      <span style="background:<?= !$catFilter?'var(--forest)':'var(--white)' ?>;color:<?= !$catFilter?'#fff':'var(--ink)' ?>;border:1.5px solid <?= !$catFilter?'var(--forest)':'var(--sand)' ?>;padding:6px 14px;border-radius:99px;font-size:.78rem;font-weight:700;cursor:pointer">
        All
      </span>
    </a>
    <?php foreach ($categories as $c): ?>
    <a href="browse.php?cat=<?= $c['id'] ?><?= $search?"&q=".urlencode($search):'' ?>" style="text-decoration:none">
      <span style="background:<?= $catFilter==$c['id']?'var(--forest)':'var(--white)' ?>;color:<?= $catFilter==$c['id']?'#fff':'var(--ink)' ?>;border:1.5px solid <?= $catFilter==$c['id']?'var(--forest)':'var(--sand)' ?>;padding:6px 14px;border-radius:99px;font-size:.78rem;font-weight:700;cursor:pointer">
        <?= htmlspecialchars($c['name']) ?>
      </span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- PRODUCTS GRID -->
  <?php if (empty($products)): ?>
    <div class="empty-state">
      <div class="ei">🔍</div>
      <p>No products found. Try a different search.</p>
    </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
    <?php foreach ($products as $p):
      $inBasket = in_array($p['id'], $basketPids);
      $savings  = $p['savings'];
    ?>
    <div style="background:var(--white);border:1.5px solid var(--sand);border-radius:var(--r);padding:18px;transition:all .2s"
         onmouseover="this.style.borderColor='var(--mint)';this.style.transform='translateY(-2px)'"
         onmouseout="this.style.borderColor='var(--sand)';this.style.transform=''">

      <!-- Product header -->
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
        <div style="flex:1">
          <div style="font-weight:800;font-size:.94rem;margin-bottom:2px"><?= htmlspecialchars($p['name']) ?></div>
          <div style="font-size:.75rem;color:var(--muted)"><?= htmlspecialchars($p['unit']) ?></div>
        </div>
        <?php if ($savings > 1000): ?>
        <span style="background:#d4edda;color:#155724;font-size:.65rem;font-weight:800;padding:3px 8px;border-radius:99px;flex-shrink:0;margin-left:8px">
          Save <?= formatPrice($savings) ?>
        </span>
        <?php endif; ?>
      </div>

      <!-- Category badge -->
      <span style="background:var(--cream);color:var(--muted);font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:99px;display:inline-block;margin-bottom:12px">
        <?= htmlspecialchars($p['cat_name']) ?>
      </span>

      <!-- Price range -->
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
        <div>
          <div style="font-size:.68rem;color:var(--muted);font-weight:700;text-transform:uppercase">Best Price</div>
          <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.15rem;color:var(--leaf)"><?= formatPrice($p['min_price']) ?></div>
        </div>
        <?php if ($p['max_price'] > $p['min_price']): ?>
        <div style="color:var(--sand)">→</div>
        <div>
          <div style="font-size:.68rem;color:var(--muted);font-weight:700;text-transform:uppercase">Highest</div>
          <div style="font-size:.9rem;color:var(--muted);text-decoration:line-through"><?= formatPrice($p['max_price']) ?></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Store prices button -->
      <button class="btn btn-sm" style="background:var(--cream);color:var(--forest);width:100%;margin-bottom:8px;justify-content:center"
              onclick="loadStorePrices(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', '<?= addslashes($p['unit']) ?>')">
        🏬 Compare All Stores
      </button>

      <!-- Add to basket -->
      <form method="POST" style="display:flex;gap:6px">
        <input type="hidden" name="product_id" value="<?= $p['id'] ?>"/>
        <input type="number" name="quantity" value="1" min="1" max="20"
               style="width:60px;padding:7px;border:1.5px solid var(--sand);border-radius:var(--rs);font-size:.85rem;text-align:center"/>
        <button type="submit" class="btn btn-sm <?= $inBasket?'btn-green':'btn-primary' ?>" style="flex:1;justify-content:center">
          <?= $inBasket ? '✅ In Basket' : '🛒 Add to Basket' ?>
        </button>
      </form>

    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- STORE PRICE MODAL -->
<div id="storePriceModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--white);border-radius:var(--r);padding:26px;width:100%;max-width:460px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <div>
        <h3 style="font-family:'Nunito',sans-serif;font-weight:900" id="spmTitle">Prices</h3>
        <div style="font-size:.78rem;color:var(--muted)" id="spmUnit"></div>
      </div>
      <button onclick="document.getElementById('storePriceModal').style.display='none'" style="background:none;border:none;font-size:1.5rem;cursor:pointer">×</button>
    </div>
    <div id="spmContent">Loading...</div>
    <div style="margin-top:16px">
      <a href="alerts.php" class="btn btn-sm btn-primary">🔔 Set Price Alert</a>
    </div>
  </div>
</div>

<script>
function loadStorePrices(pid, name, unit) {
  document.getElementById('spmTitle').textContent = name;
  document.getElementById('spmUnit').textContent  = unit;
  document.getElementById('spmContent').innerHTML = '<div style="text-align:center;padding:20px;color:var(--muted)">Loading prices...</div>';
  document.getElementById('storePriceModal').style.display = 'flex';

  fetch('../api/get_prices.php?product_id=' + pid)
    .then(r => r.json())
    .then(data => {
      if (!data.length) {
        document.getElementById('spmContent').innerHTML = '<p style="color:var(--muted)">No prices available.</p>';
        return;
      }
      const minPrice = Math.min(...data.map(d => d.price));
      let html = '';
      data.forEach((row, i) => {
        const isBest = row.price === minPrice;
        const pct    = Math.round(((row.price - minPrice) / minPrice) * 100);
        html += `
          <div style="display:flex;justify-content:space-between;align-items:center;
                      padding:11px 14px;border-radius:var(--rs);margin-bottom:7px;
                      background:${isBest ? '#f0fdf4' : 'var(--cream)'};
                      border:1.5px solid ${isBest ? '#a3d4b5' : 'var(--sand)'}">
            <div>
              <strong>${row.store_name}</strong>
              ${isBest ? '<span style="background:#d4edda;color:#155724;font-size:.66rem;font-weight:800;padding:2px 7px;border-radius:99px;margin-left:6px">BEST</span>' : ''}
              <div style="font-size:.74rem;color:var(--muted)">${row.tier}</div>
            </div>
            <div style="text-align:right">
              <div style="font-family:Nunito,sans-serif;font-weight:900;font-size:1rem;color:${isBest ? 'var(--leaf)' : 'var(--ink)'}">
                UGX ${parseInt(row.price).toLocaleString()}
              </div>
              ${!isBest && pct > 0 ? `<div style="font-size:.72rem;color:var(--red)">+${pct}% more</div>` : ''}
            </div>
          </div>`;
      });
      document.getElementById('spmContent').innerHTML = html;
    })
    .catch(() => {
      document.getElementById('spmContent').innerHTML = '<p style="color:var(--red)">Failed to load prices.</p>';
    });
}
</script>

<?php include '../includes/footer.php'; ?>
