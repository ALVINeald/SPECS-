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
 
$basketCount  = getBasketCount($conn);
$alertsCount  = getAlertsCount($conn);
$totalProds   = $conn->query("SELECT COUNT(*) AS t FROM products WHERE active=1")->fetch_assoc()['t'];
$totalStores  = $conn->query("SELECT COUNT(*) AS t FROM stores WHERE active=1")->fetch_assoc()['t'];
 
$basketTotal = $conn->query("
    SELECT SUM(
        (SELECT MIN(pr.price) FROM prices pr WHERE pr.product_id = b.product_id) * b.quantity
    ) AS total
    FROM basket b WHERE b.user_id = $uid
")->fetch_assoc()['total'] ?? 0;
 
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
 
$topDeals = $conn->query("
    SELECT p.id, p.name, p.unit,
           MIN(pr.price) AS best_price,
           MAX(pr.price) AS worst_price,
           (MAX(pr.price) - MIN(pr.price)) AS savings,
           (SELECT s.name FROM prices pr2 JOIN stores s ON pr2.store_id=s.id
            WHERE pr2.product_id=p.id ORDER BY pr2.price ASC LIMIT 1) AS best_store
    FROM prices pr
    JOIN products p ON pr.product_id = p.id
    WHERE p.active = 1
    GROUP BY p.id
    HAVING (MAX(pr.price) - MIN(pr.price)) > 1500
    ORDER BY (MAX(pr.price) - MIN(pr.price)) DESC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);
 
$recentBasket = $conn->query("
    SELECT b.*, p.name AS product_name, p.unit,
           (SELECT MIN(pr.price) FROM prices pr WHERE pr.product_id=b.product_id) AS best_price
    FROM basket b
    JOIN products p ON b.product_id = p.id
    WHERE b.user_id = $uid
    ORDER BY b.updated_at DESC
    LIMIT 4
")->fetch_all(MYSQLI_ASSOC);
 
$budget    = (int)($user['budget'] ?? 0);
$budgetPct = $budget > 0 ? min(100, round(($basketTotal / $budget) * 100)) : 0;
$budgetColor = $budgetPct > 90 ? 'var(--red)' : ($budgetPct > 70 ? 'var(--gold)' : 'var(--leaf)');
 
include '../includes/header.php';
?>
 
<style>
/* ── DASHBOARD HERO ── */
.dash-hero {
  background: linear-gradient(135deg, var(--forest) 0%, #1e5c3a 60%, #2d7a50 100%);
  padding: 32px 24px 80px;
  position: relative;
  overflow: hidden;
}
.dash-hero::before {
  content: '';
  position: absolute;
  width: 400px; height: 400px;
  border-radius: 50%;
  background: rgba(255,255,255,.04);
  top: -120px; right: -80px;
}
.dash-hero::after {
  content: '';
  position: absolute;
  width: 200px; height: 200px;
  border-radius: 50%;
  background: rgba(233,168,32,.08);
  bottom: -60px; left: 10%;
}
.dash-hero-inner {
  max-width: 1240px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}
.dash-greeting {
  font-family: 'Nunito', sans-serif;
  font-weight: 900;
  font-size: 1.8rem;
  color: #fff;
  margin-bottom: 4px;
}
.dash-greeting span { color: var(--gold); }
.dash-subtitle {
  color: rgba(255,255,255,.6);
  font-size: .88rem;
  margin-bottom: 28px;
}
 
/* ── HERO STAT CARDS ── */
.hero-stats {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}
.hero-stat {
  background: rgba(255,255,255,.1);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,.15);
  border-radius: var(--r);
  padding: 16px 18px;
  text-decoration: none;
  transition: all .2s;
  display: block;
}
.hero-stat:hover {
  background: rgba(255,255,255,.18);
  transform: translateY(-2px);
}
.hero-stat-icon { font-size: 1.3rem; margin-bottom: 6px; }
.hero-stat-num {
  font-family: 'Nunito', sans-serif;
  font-weight: 900;
  font-size: 1.5rem;
  color: var(--gold);
  display: block;
}
.hero-stat-lbl {
  font-size: .72rem;
  color: rgba(255,255,255,.6);
  font-weight: 600;
}
 
/* ── FLOATING CARDS ── */
.dash-body {
  max-width: 1240px;
  margin: -48px auto 0;
  padding: 0 24px 40px;
  position: relative;
  z-index: 2;
}
 
/* ── ALERT BANNER ── */
.alert-banner {
  background: linear-gradient(135deg, #1a5c2e, #2d8a4e);
  border-radius: var(--r);
  padding: 18px 22px;
  margin-bottom: 20px;
  border: 1.5px solid rgba(82,183,136,.4);
}
.alert-banner-title {
  font-family: 'Nunito', sans-serif;
  font-weight: 900;
  color: var(--gold);
  margin-bottom: 12px;
  font-size: .95rem;
}
.alert-banner-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(255,255,255,.1);
  border-radius: var(--rs);
  padding: 10px 14px;
  margin-bottom: 8px;
}
.alert-banner-item:last-child { margin-bottom: 0; }
 
/* ── MAIN GRID ── */
.dash-grid {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 20px;
  margin-bottom: 20px;
}
 
/* ── DEALS CARD ── */
.deals-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.deal-item {
  border: 1.5px solid var(--sand);
  border-radius: var(--rs);
  padding: 14px;
  cursor: pointer;
  transition: all .2s;
  background: var(--cream);
}
.deal-item:hover {
  border-color: var(--mint);
  background: var(--white);
  transform: translateY(-2px);
  box-shadow: 0 4px 14px rgba(24,56,42,.08);
}
.deal-name { font-weight: 800; font-size: .86rem; margin-bottom: 2px; color: var(--ink); }
.deal-unit { font-size: .72rem; color: var(--muted); margin-bottom: 10px; }
.deal-price-best {
  font-family: 'Nunito', sans-serif;
  font-weight: 900;
  font-size: 1.05rem;
  color: var(--leaf);
}
.deal-price-old {
  font-size: .75rem;
  color: var(--muted);
  text-decoration: line-through;
  margin-top: 1px;
}
.deal-save-chip {
  display: inline-block;
  background: #d4edda;
  color: #155724;
  font-size: .65rem;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 99px;
  margin-top: 6px;
}
.deal-store { font-size: .7rem; color: var(--muted); margin-top: 4px; }
 
/* ── RIGHT SIDEBAR ── */
.sidebar { display: flex; flex-direction: column; gap: 16px; }
 
/* ── BUDGET CARD ── */
.budget-card {
  background: linear-gradient(135deg, var(--forest), #2a5e40);
  border-radius: var(--r);
  padding: 20px;
  color: #fff;
}
.budget-title {
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: rgba(255,255,255,.5);
  margin-bottom: 4px;
}
.budget-amount {
  font-family: 'Nunito', sans-serif;
  font-weight: 900;
  font-size: 1.6rem;
  color: var(--gold);
  margin-bottom: 2px;
}
.budget-of { font-size: .78rem; color: rgba(255,255,255,.5); margin-bottom: 12px; }
.budget-bar-track {
  height: 6px;
  background: rgba(255,255,255,.15);
  border-radius: 99px;
  overflow: hidden;
  margin-bottom: 6px;
}
.budget-bar-fill {
  height: 100%;
  border-radius: 99px;
  transition: width .8s ease;
}
.budget-remaining { font-size: .75rem; color: rgba(255,255,255,.5); }
 
/* ── BASKET CARD ── */
.basket-item-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 9px 0;
  border-bottom: 1px solid var(--sand);
  font-size: .83rem;
}
.basket-item-row:last-child { border-bottom: none; }
.basket-total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 12px;
  padding-top: 10px;
  border-top: 2px solid var(--sand);
}
 
/* ── QUICK LINKS ── */
.quick-links {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
}
.quick-link {
  background: var(--white);
  border: 1.5px solid var(--sand);
  border-radius: var(--r);
  padding: 18px;
  text-decoration: none;
  transition: all .2s;
  display: flex;
  align-items: center;
  gap: 14px;
}
.quick-link:hover {
  border-color: var(--mint);
  transform: translateY(-2px);
  box-shadow: 0 4px 14px rgba(24,56,42,.08);
}
.ql-icon {
  width: 42px; height: 42px;
  border-radius: 10px;
  background: var(--cream);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  flex-shrink: 0;
}
.ql-title {
  font-family: 'Nunito', sans-serif;
  font-weight: 800;
  font-size: .88rem;
  margin-bottom: 2px;
}
.ql-desc { font-size: .72rem; color: var(--muted); line-height: 1.4; }
 
@media(max-width:900px) {
  .dash-grid { grid-template-columns: 1fr; }
  .deals-grid { grid-template-columns: 1fr; }
  .dash-hero { padding-bottom: 60px; }
}
@media(max-width:600px) {
  .dash-greeting { font-size: 1.4rem; }
  .hero-stats { grid-template-columns: repeat(2,1fr); }
  .quick-links { grid-template-columns: repeat(2,1fr); }
}
</style>
 
<!-- HERO SECTION -->
<div class="dash-hero">
  <div class="dash-hero-inner">
    <div class="dash-greeting">
      👋 Hello, <span><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>!</span>
    </div>
    <div class="dash-subtitle">
      Welcome to SPECS - <?= number_format($totalProds) ?> products tracked across <?= $totalStores ?> Mbarara stores
    </div>
 
    <!-- HERO STATS -->
    <div class="hero-stats">
      <a href="basket.php" class="hero-stat">
        <div class="hero-stat-icon">🛒</div>
        <span class="hero-stat-num"><?= $basketCount ?></span>
        <span class="hero-stat-lbl">Basket Items</span>
      </a>
      <a href="alerts.php" class="hero-stat">
        <div class="hero-stat-icon">🔔</div>
        <span class="hero-stat-num"><?= $alertsCount ?></span>
        <span class="hero-stat-lbl">Active Alerts</span>
      </a>
      <a href="browse.php" class="hero-stat">
        <div class="hero-stat-icon">📦</div>
        <span class="hero-stat-num"><?= number_format($totalProds) ?></span>
        <span class="hero-stat-lbl">Products</span>
      </a>
      <a href="browse.php" class="hero-stat">
        <div class="hero-stat-icon">🏬</div>
        <span class="hero-stat-num"><?= $totalStores ?></span>
        <span class="hero-stat-lbl">Stores</span>
      </a>
    </div>
  </div>
</div>
 
<!-- DASHBOARD BODY -->
<div class="dash-body">
  <?php showFlash(); ?>
 
  <!-- TRIGGERED ALERTS BANNER -->
  <?php if (!empty($triggeredAlerts)): ?>
  <div class="alert-banner">
    <div class="alert-banner-title">🎉 Your price alerts have been triggered!</div>
    <?php foreach ($triggeredAlerts as $ta): ?>
    <div class="alert-banner-item">
      <div>
        <div style="font-weight:800;color:#fff;font-size:.88rem"><?= htmlspecialchars($ta['product_name']) ?> <span style="color:rgba(255,255,255,.5);font-size:.76rem">(<?= $ta['unit'] ?>)</span></div>
        <div style="font-size:.74rem;color:rgba(255,255,255,.5)"><?= $ta['store_name'] ?? 'Any store' ?></div>
      </div>
      <div style="text-align:right">
        <div style="font-family:'Nunito',sans-serif;font-weight:900;color:var(--gold)"><?= formatPrice($ta['current_price']) ?></div>
        <div style="font-size:.72rem;color:rgba(255,255,255,.45)">Target: <?= formatPrice($ta['target_price']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
 
  <!-- MAIN GRID -->
  <div class="dash-grid">
 
    <!-- LEFT: TOP DEALS -->
    <div class="card">
      <div class="card-title">
        🔥 Best Deals Right Now
        <a href="browse.php?sort=savings" style="float:right;font-size:.72rem;color:var(--leaf);font-weight:700">See all →</a>
      </div>
      <?php if (empty($topDeals)): ?>
        <div class="empty-state"><div class="ei">🛍️</div><p>No deals found.</p></div>
      <?php else: ?>
      <div class="deals-grid">
        <?php foreach ($topDeals as $d): ?>
        <div class="deal-item" onclick="window.location='browse.php?q=<?= urlencode($d['name']) ?>'">
          <div class="deal-name"><?= htmlspecialchars($d['name']) ?></div>
          <div class="deal-unit"><?= htmlspecialchars($d['unit']) ?></div>
          <div class="deal-price-best"><?= formatPrice($d['best_price']) ?></div>
          <div class="deal-price-old"><?= formatPrice($d['worst_price']) ?></div>
          <span class="deal-save-chip">Save <?= formatPrice($d['savings']) ?></span>
          <div class="deal-store">📍 <?= htmlspecialchars($d['best_store']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div style="margin-top:16px">
        <a href="browse.php" class="btn btn-green btn-sm">🛍️ Browse All <?= number_format($totalProds) ?> Products →</a>
      </div>
    </div>
 
    <!-- RIGHT SIDEBAR -->
    <div class="sidebar">
 
      <!-- BUDGET CARD -->
      <?php if ($budget > 0): ?>
      <div class="budget-card">
        <div class="budget-title">💰 Monthly Budget</div>
        <div class="budget-amount"><?= formatPrice($basketTotal) ?></div>
        <div class="budget-of">of <?= formatPrice($budget) ?> budget</div>
        <div class="budget-bar-track">
          <div class="budget-bar-fill" style="width:<?= $budgetPct ?>%;background:<?= $budgetColor ?>"></div>
        </div>
        <div class="budget-remaining"><?= $budgetPct ?>% used · <?= formatPrice(max(0, $budget - $basketTotal)) ?> remaining</div>
      </div>
      <?php else: ?>
      <div class="card" style="border-left:4px solid var(--gold)">
        <div class="card-title">💰 Budget Tracker</div>
        <div style="font-size:.83rem;color:var(--muted);margin-bottom:12px">Set a monthly budget to track your grocery spending.</div>
        <a href="account.php" class="btn btn-primary btn-sm">Set My Budget</a>
      </div>
      <?php endif; ?>
 
      <!-- BASKET CARD -->
      <div class="card">
        <div class="card-title">
          🛒 My Basket
          <?php if (!empty($recentBasket)): ?>
          <a href="basket.php" style="float:right;font-size:.72rem;color:var(--leaf);font-weight:700">View all →</a>
          <?php endif; ?>
        </div>
        <?php if (empty($recentBasket)): ?>
          <div style="font-size:.83rem;color:var(--muted);margin-bottom:12px;text-align:center;padding:16px 0">
            <div style="font-size:1.8rem;margin-bottom:8px">🛒</div>
            Your basket is empty
          </div>
          <a href="browse.php" class="btn btn-green btn-sm" style="width:100%;justify-content:center">Start Shopping</a>
        <?php else: ?>
          <?php foreach ($recentBasket as $bi): ?>
          <div class="basket-item-row">
            <div>
              <div style="font-weight:700"><?= htmlspecialchars($bi['product_name']) ?></div>
              <div style="font-size:.72rem;color:var(--muted)"><?= htmlspecialchars($bi['unit']) ?> × <?= $bi['quantity'] ?></div>
            </div>
            <div style="font-weight:800;color:var(--leaf)"><?= formatPrice($bi['best_price'] * $bi['quantity']) ?></div>
          </div>
          <?php endforeach; ?>
          <div class="basket-total-row">
            <div>
              <div style="font-size:.7rem;color:var(--muted);font-weight:700;text-transform:uppercase">Best Total</div>
              <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.1rem;color:var(--forest)"><?= formatPrice($basketTotal) ?></div>
            </div>
            <a href="basket.php" class="btn btn-primary btn-sm">View Basket</a>
          </div>
        <?php endif; ?>
      </div>
 
    </div>
  </div>
 
  <!-- QUICK LINKS -->
  <div style="margin-bottom:8px">
    <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1rem;color:var(--ink);margin-bottom:14px">Quick Links</div>
    <div class="quick-links">
      <?php
      $links = [
        ['🛍️', 'Browse Products',  'browse.php',  'Search & compare prices'],
        ['🛒', 'My Basket',        'basket.php',  'View items & save plan'],
        ['🔔', 'Price Alerts',     'alerts.php',  'Set & manage alerts'],
        ['📈', 'Price Trends',     'trends.php',  'Track price history'],
        ['👤', 'My Account',       'account.php', 'Profile & settings'],
      ];
      foreach ($links as $l): ?>
      <a href="<?= $l[2] ?>" class="quick-link">
        <div class="ql-icon"><?= $l[0] ?></div>
        <div>
          <div class="ql-title"><?= $l[1] ?></div>
          <div class="ql-desc"><?= $l[3] ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
 
</div>
 
<?php include '../includes/footer.php'; ?>
 