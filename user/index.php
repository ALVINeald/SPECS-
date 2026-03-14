<?php
// ============================================================
//  SPECS – Consumer Dashboard/Home
//  File: user/index.php
// ============================================================
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'My Dashboard';
$user      = getCurrentUser();
$uid       = (int)$user['id'];

// ── STATS ─────────────────────────────────────────────────────
$basketCount  = getBasketCount($conn);
$alertsCount  = getAlertsCount($conn);
$totalProds   = $conn->query("SELECT COUNT(*) AS t FROM products WHERE active=1")->fetch_assoc()['t'];
$totalStores  = $conn->query("SELECT COUNT(*) AS t FROM stores WHERE active=1")->fetch_assoc()['t'];

// ── BASKET TOTAL ──────────────────────────────────────────────
$basketTotal = $conn->query("
    SELECT SUM(
        (SELECT MIN(pr.price) FROM prices pr WHERE pr.product_id = b.product_id) * b.quantity
    ) AS total
    FROM basket b WHERE b.user_id = $uid
")->fetch_assoc()['total'] ?? 0;

// ── TRIGGERED ALERTS ─────────────────────────────────────────
$triggeredAlerts = $conn->query("
    SELECT a.*, p.name AS product_name, p.unit,
           s.name AS store_name,
           (SELECT MIN(pr.price) FROM prices pr WHERE pr.product_id=a.product_id) AS current_price
    FROM alerts a
    JOIN products p ON a.product_id = p.id
    LEFT JOIN stores s ON a.store_id = s.id
    WHERE a.user_id = $uid AND a.is_triggered = 1 AND a.is_active = 1
    ORDER BY a.triggered_at DESC
    LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

// ── TOP DEALS TODAY ───────────────────────────────────────────
$topDeals = $conn->query("
    SELECT p.id, p.name, p.unit,
           MIN(pr.price) AS best_price,
           MAX(pr.price) AS worst_price,
           s.name AS best_store
    FROM prices pr
    JOIN products p ON pr.product_id = p.id
    JOIN stores s   ON pr.store_id   = s.id
    WHERE p.active = 1
    GROUP BY p.id
    HAVING (worst_price - best_price) > 1500
    ORDER BY (worst_price - best_price) DESC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// ── RECENT BASKET ITEMS ───────────────────────────────────────
$recentBasket = $conn->query("
    SELECT b.*, p.name AS product_name, p.unit,
           (SELECT MIN(pr.price) FROM prices pr WHERE pr.product_id=b.product_id) AS best_price,
           (SELECT s.name FROM prices pr JOIN stores s ON pr.store_id=s.id WHERE pr.product_id=b.product_id ORDER BY pr.price ASC LIMIT 1) AS best_store
    FROM basket b
    JOIN products p ON b.product_id = p.id
    WHERE b.user_id = $uid
    ORDER BY b.updated_at DESC
    LIMIT 4
")->fetch_all(MYSQLI_ASSOC);

// Budget usage
$budget     = (int)$user['budget'];
$budgetUsed = (int)$basketTotal;
$budgetPct  = $budget > 0 ? min(100, round(($budgetUsed / $budget) * 100)) : 0;

include '../includes/header.php';
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <h1>👋 Hello, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</h1>
      <p>Here's your SPECS dashboard — <?= $totalProds ?> products across <?= $totalStores ?> Mbarara stores</p>
    </div>
    <a href="browse.php" class="btn btn-primary">🛍️ Browse Products</a>
  </div>
</div>

<div class="ctr">
  <?php showFlash(); ?>

  <!-- TRIGGERED ALERTS -->
  <?php if (!empty($triggeredAlerts)): ?>
  <div style="background:#d4edda;border:1.5px solid #a3d4b5;border-radius:var(--r);padding:16px 20px;margin-bottom:20px">
    <div style="font-weight:800;color:#155724;margin-bottom:10px">🎉 Your price alerts have been triggered!</div>
    <?php foreach ($triggeredAlerts as $ta): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;background:#fff;border-radius:var(--rs);padding:10px 14px;margin-bottom:8px">
      <div>
        <strong><?= htmlspecialchars($ta['product_name']) ?></strong>
        <span style="color:var(--muted);font-size:.8rem"> (<?= $ta['unit'] ?>)</span>
        <div style="font-size:.78rem;color:var(--muted)"><?= $ta['store_name'] ?? 'Any store' ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-family:'Nunito',sans-serif;font-weight:900;color:var(--leaf)"><?= formatPrice($ta['current_price']) ?></div>
        <div style="font-size:.74rem;color:var(--muted)">Target: <?= formatPrice($ta['target_price']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- STAT CARDS -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;margin-bottom:24px">
    <?php
    $cards = [
      ['🛒', 'Basket Items',   $basketCount,             'basket.php',  'var(--forest)'],
      ['🔔', 'Active Alerts',  $alertsCount,             'alerts.php',  'var(--gold)'],
      ['📦', 'Products',       number_format($totalProds), 'browse.php', 'var(--leaf)'],
      ['🏬', 'Stores',         $totalStores,             'browse.php',  '#2196F3'],
    ];
    foreach ($cards as $c): ?>
    <a href="<?= $c[3] ?>" style="text-decoration:none">
      <div style="background:var(--white);border:1.5px solid var(--sand);border-radius:var(--r);padding:18px;transition:all .2s"
           onmouseover="this.style.borderColor='<?= $c[4] ?>'" onmouseout="this.style.borderColor='var(--sand)'">
        <div style="font-size:1.4rem;margin-bottom:6px"><?= $c[0] ?></div>
        <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.5rem;color:<?= $c[4] ?>"><?= $c[2] ?></div>
        <div style="font-size:.74rem;color:var(--muted);font-weight:600"><?= $c[1] ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">

    <!-- TOP DEALS -->
    <div class="card">
      <div class="card-title">🔥 Best Deals Right Now</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <?php foreach ($topDeals as $d):
          $save = $d['worst_price'] - $d['best_price'];
        ?>
        <div style="border:1.5px solid var(--sand);border-radius:var(--rs);padding:14px;transition:all .2s;cursor:pointer"
             onclick="window.location='browse.php?q=<?= urlencode($d['name']) ?>'"
             onmouseover="this.style.borderColor='var(--mint)'" onmouseout="this.style.borderColor='var(--sand)'">
          <div style="font-weight:700;font-size:.86rem;margin-bottom:4px"><?= htmlspecialchars($d['name']) ?></div>
          <div style="font-size:.74rem;color:var(--muted);margin-bottom:8px"><?= htmlspecialchars($d['unit']) ?></div>
          <div style="font-family:'Nunito',sans-serif;font-weight:900;color:var(--leaf);font-size:1rem"><?= formatPrice($d['best_price']) ?></div>
          <div style="font-size:.74rem;color:var(--muted);text-decoration:line-through"><?= formatPrice($d['worst_price']) ?></div>
          <span style="background:#d4edda;color:#155724;font-size:.68rem;font-weight:800;padding:2px 7px;border-radius:99px;margin-top:5px;display:inline-block">
            Save <?= formatPrice($save) ?>
          </span>
          <div style="font-size:.72rem;color:var(--muted);margin-top:4px">Best: <?= htmlspecialchars($d['best_store']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="margin-top:14px">
        <a href="browse.php" class="btn btn-green btn-sm">See All Products →</a>
      </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div style="display:flex;flex-direction:column;gap:18px">

      <!-- BUDGET TRACKER -->
      <?php if ($budget > 0): ?>
      <div class="card">
        <div class="card-title">💰 Monthly Budget</div>
        <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.4rem;color:var(--forest)"><?= formatPrice($budgetUsed) ?></div>
        <div style="font-size:.78rem;color:var(--muted);margin-bottom:10px">of <?= formatPrice($budget) ?> budget</div>
        <div style="height:8px;background:var(--sand);border-radius:99px;overflow:hidden;margin-bottom:6px">
          <div style="height:100%;width:<?= $budgetPct ?>%;background:<?= $budgetPct>90?'var(--red)':($budgetPct>70?'var(--gold)':'var(--leaf)') ?>;border-radius:99px;transition:width .5s"></div>
        </div>
        <div style="font-size:.76rem;color:var(--muted)"><?= $budgetPct ?>% used · <?= formatPrice(max(0,$budget-$budgetUsed)) ?> remaining</div>
      </div>
      <?php else: ?>
      <div class="card">
        <div class="card-title">💰 Budget Tracker</div>
        <div style="font-size:.84rem;color:var(--muted);margin-bottom:12px">Set a monthly budget to track your spending.</div>
        <a href="account.php" class="btn btn-sm btn-primary">Set Budget</a>
      </div>
      <?php endif; ?>

      <!-- RECENT BASKET -->
      <div class="card">
        <div class="card-title">🛒 Your Basket</div>
        <?php if (empty($recentBasket)): ?>
          <div style="font-size:.84rem;color:var(--muted);margin-bottom:12px">Your basket is empty.</div>
          <a href="browse.php" class="btn btn-sm btn-green">Start Shopping</a>
        <?php else: ?>
          <?php foreach ($recentBasket as $bi): ?>
          <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--sand);font-size:.83rem">
            <div>
              <strong><?= htmlspecialchars($bi['product_name']) ?></strong>
              <span style="color:var(--muted)"> ×<?= $bi['quantity'] ?></span>
            </div>
            <div style="font-weight:700;color:var(--leaf)"><?= formatPrice($bi['best_price'] * $bi['quantity']) ?></div>
          </div>
          <?php endforeach; ?>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">
            <strong style="font-size:.85rem">Total: <?= formatPrice($basketTotal) ?></strong>
            <a href="basket.php" class="btn btn-sm btn-primary">View Basket</a>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- QUICK LINKS -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px">
    <?php
    $links = [
      ['🛍️','Browse Products',  'browse.php',  'Browse 205+ products and compare prices across all stores'],
      ['🛒','My Basket',        'basket.php',  'View your saved items and get the best store recommendation'],
      ['🔔','Price Alerts',     'alerts.php',  'Set target prices and get notified when products drop'],
      ['📈','Price Trends',     'trends.php',  'See how prices have changed over the past 6 months'],
      ['👤','My Account',       'account.php', 'Update your profile, budget and preferences'],
    ];
    foreach ($links as $l): ?>
    <a href="<?= $l[2] ?>" style="text-decoration:none">
      <div style="background:var(--white);border:1.5px solid var(--sand);border-radius:var(--r);padding:20px;transition:all .2s"
           onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='var(--mint)'"
           onmouseout="this.style.transform='';this.style.borderColor='var(--sand)'">
        <div style="font-size:1.6rem;margin-bottom:8px"><?= $l[0] ?></div>
        <div style="font-family:'Nunito',sans-serif;font-weight:800;margin-bottom:5px"><?= $l[1] ?></div>
        <div style="font-size:.78rem;color:var(--muted);line-height:1.5"><?= $l[3] ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
