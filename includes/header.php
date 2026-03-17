<?php
// ============================================================
//  SPECS – Header / Navigation Bar
//  File: includes/header.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
 
$basketCount = isLoggedIn() ? getBasketCount($conn) : 0;
$alertsCount = isLoggedIn() ? getAlertsCount($conn) : 0;
$user        = getCurrentUser();
 
// Detect current page for active nav highlight
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= isset($pageTitle) ? $pageTitle . ' – SPECS Mbarara' : 'SPECS – Mbarara City Price Comparison' ?></title>
 
  <!-- FONTS -->
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Nunito+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
 
  <!-- FAVICON -->
  <link rel="icon" href="/specs/assets/images/favicon.ico"/>
 
  <!-- GLOBAL CSS -->
  <link rel="stylesheet" href="/specs/assets/css/style.css"/>
 
  <!-- SECTION CSS -->
  <?php if ($currentDir === 'admin'): ?>
  <link rel="stylesheet" href="/specs/assets/css/admin.css"/>
  <?php elseif ($currentDir === 'user'): ?>
  <link rel="stylesheet" href="/specs/assets/css/user.css"/>
  <?php endif; ?>
 
  <!-- CHART.JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
 
  <!-- EXTRA HEAD (per-page override) -->
  <?= isset($extraHead) ? $extraHead : '' ?>
</head>
<body>
 
<!-- TOP NAVIGATION -->
<nav class="top-nav">
  <a href="<?= isAdmin() ? '/specs/admin/index.php' : '/specs/user/index.php' ?>" class="nav-brand">
    SP<em>EC</em>S <small>Mbarara</small>
  </a>
 
  <?php if (isLoggedIn() && !isAdmin()): ?>
  <!-- USER NAV LINKS -->
  <div class="nav-links">
    <a href="/specs/user/index.php"   class="nl <?= $currentPage==='index'  && $currentDir==='user' ? 'active':'' ?>">🏠 Home</a>
    <a href="/specs/user/browse.php"  class="nl <?= $currentPage==='browse'                         ? 'active':'' ?>">🛍️ Browse</a>
    <a href="/specs/user/trends.php"  class="nl <?= $currentPage==='trends'                         ? 'active':'' ?>">📈 Trends</a>
    <a href="/specs/user/alerts.php"  class="nl <?= $currentPage==='alerts'                         ? 'active':'' ?>">🔔 Alerts <?= $alertsCount>0?"<span class='bcount'>$alertsCount</span>":'' ?></a>
    <a href="/specs/user/account.php" class="nl <?= $currentPage==='account'                        ? 'active':'' ?>">👤 Account</a>
  </div>
  <div class="nav-right">
    <a href="/specs/user/basket.php" class="basket-btn">
      🛒 Basket <span class="bcount" id="basket-badge"><?= $basketCount ?></span>
    </a>
    <div class="navav">
      <?php if (!empty($user['avatar'])): ?>
        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar"/>
      <?php else: ?>
        <?= avatarLetter() ?>
      <?php endif; ?>
    </div>
    <span class="nav-name"><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></span>
    <a href="/specs/logout.php" class="btn-lo">Sign Out</a>
  </div>
 
  <?php elseif (isAdmin()): ?>
  <!-- ADMIN NAV LINKS -->
  <div class="nav-links">
    <a href="/specs/admin/index.php"    class="nl <?= $currentPage==='index'    && $currentDir==='admin' ? 'active':'' ?>">📊 Dashboard</a>
    <a href="/specs/admin/products.php" class="nl <?= $currentPage==='products'                          ? 'active':'' ?>">🛒 Products</a>
    <a href="/specs/admin/prices.php"   class="nl <?= $currentPage==='prices'                            ? 'active':'' ?>">💰 Prices</a>
    <a href="/specs/admin/stores.php"   class="nl <?= $currentPage==='stores'                            ? 'active':'' ?>">🏬 Stores</a>
    <a href="/specs/admin/users.php"    class="nl <?= $currentPage==='users'                             ? 'active':'' ?>">👥 Users</a>
    <a href="/specs/admin/alerts.php"   class="nl <?= $currentPage==='alerts'                            ? 'active':'' ?>">🔔 Alerts</a>
    <a href="/specs/admin/reports.php"  class="nl <?= $currentPage==='reports'                           ? 'active':'' ?>">📋 Reports</a>
  </div>
  <div class="nav-right">
    <div class="navav" style="background:var(--gold);color:var(--forest)"><?= avatarLetter() ?></div>
    <span class="nav-name">Admin</span>
    <a href="/specs/logout.php" class="btn-lo">Sign Out</a>
  </div>
 
  <?php else: ?>
  <!-- GUEST NAV -->
  <div class="nav-links">
    <a href="/specs/index.php" class="nl">🏠 Home</a>
  </div>
  <div class="nav-right">
    <a href="/specs/login.php"    class="btn-lo">Sign In</a>
    <a href="/specs/register.php" class="btn" style="background:var(--gold);color:var(--forest);padding:6px 16px;font-size:.8rem">Register</a>
  </div>
  <?php endif; ?>
</nav>
 
<!-- TOAST NOTIFICATION -->
<div class="toast" id="specs-toast">
  <span id="toast-icon">✅</span>
  <span id="toast-msg">Done!</span>
</div>
 
<!-- GLOBAL JS -->
<script src="/specs/assets/js/main.js"></script>
 
<?php if ($currentDir === 'user'): ?>
<script src="/specs/assets/js/basket.js"></script>
<script src="/specs/assets/js/alerts.js"></script>
<script src="/specs/assets/js/charts.js"></script>
<?php endif; ?>
 
<script>
function showToast(msg, icon = '✅') {
  document.getElementById('toast-msg').textContent  = msg;
  document.getElementById('toast-icon').textContent = icon;
  const t = document.getElementById('specs-toast');
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3400);
}
</script>
 
<div class="page-wrap">
 