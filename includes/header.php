<?php
// ============================================================
//  SPECS – Header / Navigation Bar
//  File: includes/header.php
// ============================================================
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($conn))             require_once __DIR__ . '/../config/db.php';
if (!function_exists('clean')) require_once __DIR__ . '/functions.php';

$basketCount = isLoggedIn() ? getBasketCount($conn) : 0;
$alertsCount = isLoggedIn() ? getAlertsCount($conn) : 0;
$user        = getCurrentUser();

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

  <!-- NAV STYLES -->
  <style>
  /* ── RESET NAV ── */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Nunito Sans', sans-serif; background: var(--cream); color: var(--ink); min-height: 100vh; padding-top: 64px; }
  a { text-decoration: none; color: inherit; }

  /* ── TOP NAV ── */
  .top-nav {
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 999;
    height: 64px;
    background: rgba(24, 56, 42, 0.97);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(255,255,255,.08);
    display: flex;
    align-items: center;
    padding: 0 28px;
    gap: 0;
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
  }

  /* ── BRAND ── */
  .nav-brand {
    font-family: 'Nunito', sans-serif;
    font-weight: 900;
    font-size: 1.35rem;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    margin-right: 32px;
    letter-spacing: -.3px;
  }
  .nav-brand em { color: var(--gold); font-style: normal; }
  .nav-brand-badge {
    font-size: .58rem;
    background: rgba(233,168,32,.2);
    border: 1px solid rgba(233,168,32,.3);
    color: var(--gold);
    padding: 2px 8px;
    border-radius: 99px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
  }

  /* ── NAV LINKS ── */
  .nav-links {
    display: flex;
    align-items: center;
    gap: 2px;
    flex: 1;
    height: 100%;
  }

  .nl {
    position: relative;
    display: flex;
    align-items: center;
    gap: 6px;
    height: 64px;
    padding: 0 14px;
    font-family: 'Nunito', sans-serif;
    font-weight: 700;
    font-size: .82rem;
    color: rgba(255,255,255,.6);
    border: none;
    background: none;
    cursor: pointer;
    transition: color .18s;
    white-space: nowrap;
    letter-spacing: .01em;
  }

  .nl:hover { color: rgba(255,255,255,.9); }

  .nl.active {
    color: #fff;
  }

  /* Active underline */
  .nl::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 14px;
    right: 14px;
    height: 2px;
    background: var(--gold);
    border-radius: 2px 2px 0 0;
    transform: scaleX(0);
    transition: transform .2s ease;
  }

  .nl:hover::after { transform: scaleX(1); }
  .nl.active::after { transform: scaleX(1); }

  .nl-icon { font-size: .95rem; }

  /* Alert count pill */
  .nl-count {
    background: var(--red);
    color: #fff;
    font-size: .58rem;
    font-weight: 900;
    min-width: 16px;
    height: 16px;
    border-radius: 99px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
  }

  /* ── NAV RIGHT ── */
  .nav-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
    margin-left: 16px;
  }

  /* Basket button */
  .basket-btn {
    display: flex;
    align-items: center;
    gap: 7px;
    background: var(--gold);
    color: var(--forest);
    font-family: 'Nunito', sans-serif;
    font-weight: 900;
    font-size: .8rem;
    padding: 8px 16px;
    border-radius: 99px;
    border: none;
    cursor: pointer;
    transition: all .18s;
    letter-spacing: .01em;
  }
  .basket-btn:hover {
    background: #f0b422;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(233,168,32,.4);
  }
  .bcount {
    background: var(--forest);
    color: var(--gold);
    border-radius: 99px;
    min-width: 18px;
    height: 18px;
    font-size: .62rem;
    font-weight: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
  }

  /* Divider */
  .nav-divider {
    width: 1px;
    height: 24px;
    background: rgba(255,255,255,.12);
  }

  /* Avatar */
  .nav-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--mint), var(--leaf));
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Nunito', sans-serif;
    font-weight: 900;
    font-size: .88rem;
    color: #fff;
    flex-shrink: 0;
    border: 2px solid rgba(255,255,255,.15);
  }

  .nav-admin-avatar {
    background: linear-gradient(135deg, var(--gold), #d4940f);
    color: var(--forest);
  }

  /* Name */
  .nav-name {
    color: rgba(255,255,255,.75);
    font-size: .8rem;
    font-weight: 700;
    font-family: 'Nunito', sans-serif;
    max-width: 90px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  /* Sign out */
  .btn-signout {
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    color: rgba(255,255,255,.6);
    border-radius: 99px;
    padding: 6px 14px;
    font-family: 'Nunito', sans-serif;
    font-weight: 700;
    font-size: .76rem;
    cursor: pointer;
    transition: all .18s;
  }
  .btn-signout:hover {
    background: rgba(255,255,255,.15);
    color: #fff;
    border-color: rgba(255,255,255,.2);
  }

  /* Guest buttons */
  .btn-signin {
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.15);
    color: rgba(255,255,255,.8);
    border-radius: 99px;
    padding: 7px 18px;
    font-family: 'Nunito', sans-serif;
    font-weight: 700;
    font-size: .8rem;
    cursor: pointer;
    transition: all .18s;
  }
  .btn-signin:hover { background: rgba(255,255,255,.18); color: #fff; }

  .btn-register {
    background: var(--gold);
    border: none;
    color: var(--forest);
    border-radius: 99px;
    padding: 7px 18px;
    font-family: 'Nunito', sans-serif;
    font-weight: 900;
    font-size: .8rem;
    cursor: pointer;
    transition: all .18s;
  }
  .btn-register:hover {
    background: #f0b422;
    transform: translateY(-1px);
  }

  /* Admin badge */
  .admin-badge {
    background: rgba(233,168,32,.15);
    border: 1px solid rgba(233,168,32,.3);
    color: var(--gold);
    font-size: .62rem;
    font-weight: 800;
    padding: 2px 8px;
    border-radius: 99px;
    text-transform: uppercase;
    letter-spacing: .06em;
  }

  /* Page wrapper */
  .page-wrap { min-height: calc(100vh - 64px); }

  /* ── SHARED COMPONENTS ── */
  .ph {
    background: linear-gradient(135deg, var(--forest), var(--leaf));
    padding: 28px 24px;
    color: #fff;
  }
  .ph h1 { font-family:'Nunito',sans-serif; font-size:1.55rem; font-weight:900; margin-bottom:3px; }
  .ph p  { color: rgba(255,255,255,.6); font-size:.83rem; }
  .ctr   { max-width:1240px; margin:0 auto; padding:24px; }
  .card  { background:var(--white); border-radius:var(--r); border:1.5px solid var(--sand); padding:22px; }
  .card-title {
    font-family:'Nunito',sans-serif; font-weight:800; font-size:.92rem;
    color:var(--forest); margin-bottom:14px; padding-bottom:11px;
    border-bottom:1.5px solid var(--sand);
  }
  .btn {
    display:inline-flex; align-items:center; gap:6px;
    font-family:'Nunito',sans-serif; font-weight:800;
    padding:10px 20px; border-radius:var(--rs); border:none;
    cursor:pointer; transition:all .18s; font-size:.88rem;
    text-decoration:none;
  }
  .btn:hover { transform:translateY(-1px); }
  .btn-primary { background:var(--gold); color:var(--forest); }
  .btn-primary:hover { background:#d4940f; }
  .btn-green { background:var(--leaf); color:#fff; }
  .btn-green:hover { background:var(--forest); }
  .btn-red   { background:#f8d7da; color:#721c24; }
  .btn-red:hover { background:#f5c2c7; }
  .btn-sm    { padding:6px 13px; font-size:.78rem; }
  .fgrp      { margin-bottom:14px; }
  .flabel    { display:block; font-size:.72rem; font-weight:800; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:5px; }
  .finput    { width:100%; background:var(--cream); border:1.5px solid var(--sand); border-radius:var(--rs); padding:11px 13px; font-size:.92rem; color:var(--ink); outline:none; transition:border-color .18s; font-family:'Nunito Sans',sans-serif; }
  .finput:focus { border-color:var(--leaf); background:var(--white); }
  .tbl-wrap  { overflow-x:auto; }
  .tbl       { width:100%; border-collapse:collapse; font-size:.84rem; }
  .tbl thead { background:var(--leaf); color:#fff; }
  .tbl th    { padding:11px 14px; text-align:left; font-size:.7rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase; }
  .tbl td    { padding:11px 14px; border-bottom:1px solid var(--sand); }
  .tbl tr:last-child td { border-bottom:none; }
  .tbl tr:hover td { background:#faf7f1; }
  .badge     { display:inline-block; font-size:.65rem; font-weight:800; padding:2px 8px; border-radius:99px; }
  .badge-green  { background:#d4edda; color:#155724; }
  .badge-yellow { background:#fff3cd; color:#856404; }
  .badge-red    { background:#f8d7da; color:#721c24; }
  .badge-blue   { background:#cce5ff; color:#004085; }
  .price-best   { color:#155724; font-weight:800; }
  .price-high   { color:#721c24; }
  .flash        { padding:12px 18px; border-radius:var(--rs); margin-bottom:16px; font-size:.88rem; font-weight:600; }
  .flash-success{ background:#d4edda; border:1.5px solid #a3d4b5; color:#155724; }
  .flash-error  { background:#f8d7da; border:1.5px solid #fcc; color:#721c24; }
  .empty-state  { text-align:center; padding:52px 20px; color:var(--muted); }
  .empty-state .ei { font-size:3rem; margin-bottom:10px; display:block; }

  /* Toast */
  .toast {
    position:fixed; bottom:22px; right:22px; z-index:9999;
    background:var(--forest); color:#fff;
    padding:12px 20px; border-radius:99px;
    box-shadow:0 8px 26px rgba(0,0,0,.28);
    display:flex; align-items:center; gap:9px;
    font-size:.83rem; font-weight:700; max-width:320px;
    transform:translateY(80px); opacity:0;
    transition:all .3s cubic-bezier(.34,1.56,.64,1);
    border:1px solid rgba(255,255,255,.1);
  }
  .toast.show { transform:translateY(0); opacity:1; }

  /* ── HAMBURGER BUTTON (hidden on desktop) ── */
  .nav-burger {
    display: none;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 5px;
    width: 42px;
    height: 42px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 10px;
    cursor: pointer;
    flex-shrink: 0;
    margin-left: 10px;
    padding: 0;
  }
  .nav-burger span {
    display: block;
    width: 18px;
    height: 2px;
    background: #fff;
    border-radius: 2px;
    transition: transform .25s ease, opacity .2s ease;
  }
  .top-nav.menu-open .nav-burger span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
  .top-nav.menu-open .nav-burger span:nth-child(2) { opacity: 0; }
  .top-nav.menu-open .nav-burger span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

  /* Links that only appear inside the mobile menu */
  .nl-mobile-only { display: none; }

  /* ── MOBILE NAV (≤900px) ── */
  @media (max-width: 900px) {
    html, body { overflow-x: hidden; }
    body { padding-top: 64px; }

    .top-nav { padding: 0 14px; }
    .nav-brand { margin-right: 0; flex: 1; }
    .nav-name, .nav-divider, .btn-signout { display: none; }
    .basket-btn { padding: 8px 12px; font-size: .74rem; }
    .nav-burger { display: flex; }

    /* Turn the link row into a slide-down menu */
    .top-nav .nav-links {
      display: flex;
      position: fixed;
      top: 64px; left: 0; right: 0;
      flex-direction: column;
      align-items: stretch;
      gap: 0;
      height: auto;
      max-height: calc(100vh - 64px);
      overflow-y: auto;
      background: rgba(24, 56, 42, .98);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      padding: 8px 0 12px;
      border-bottom: 1px solid rgba(255,255,255,.1);
      box-shadow: 0 18px 30px rgba(0,0,0,.35);
      transform: translateY(-12px);
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: opacity .2s ease, transform .2s ease, visibility .2s;
      z-index: 998;
    }
    .top-nav.menu-open .nav-links {
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
      transform: translateY(0);
    }
    .top-nav .nav-links .nl {
      width: 100%;
      height: 50px;
      padding: 0 22px;
      font-size: .92rem;
      color: rgba(255,255,255,.75);
    }
    .top-nav .nav-links .nl.active {
      color: #fff;
      background: rgba(255,255,255,.06);
    }
    .top-nav .nav-links .nl::after { display: none; }
    .top-nav .nav-links .nl-mobile-only {
      display: flex;
      border-top: 1px solid rgba(255,255,255,.1);
      margin-top: 6px;
      padding-top: 4px;
    }
  }

  @media (max-width: 600px) {
    .nav-brand-badge { display: none; }
    .btn-signin { display: none; }
  }
  </style>

  <?= isset($extraHead) ? $extraHead : '' ?>
</head>
<body>

<!-- ── TOP NAVIGATION ── -->
<nav class="top-nav">

  <!-- BRAND -->
  <a href="<?= isAdmin() ? '/specs/admin/index.php' : (isLoggedIn() ? '/specs/user/index.php' : '/specs/index.php') ?>" class="nav-brand">
    <!--SP<em>EC</em>S -->
    SPECS
    <span class="nav-brand-badge">Mbarara</span>
  </a>

  <?php if (isLoggedIn() && !isAdmin()): ?>
  <!-- ── USER NAV ── -->
  <div class="nav-links">
    <a href="/specs/user/index.php"   class="nl <?= $currentPage==='index'   && $currentDir==='user' ? 'active':'' ?>">
      <span class="nl-icon">🏠</span> Home
    </a>
    <a href="/specs/user/browse.php"  class="nl <?= $currentPage==='browse'  ? 'active':'' ?>">
      <span class="nl-icon">🛍️</span> Browse
    </a>
    <a href="/specs/user/trends.php"  class="nl <?= $currentPage==='trends'  ? 'active':'' ?>">
      <span class="nl-icon">📈</span> Trends
    </a>
    <a href="/specs/user/route.php"   class="nl <?= $currentPage==='route'   ? 'active':'' ?>">
      <span class="nl-icon">🗺️</span> Route
    </a>
    <a href="/specs/user/alerts.php"  class="nl <?= $currentPage==='alerts'  ? 'active':'' ?>">
      <span class="nl-icon">🔔</span> Alerts
      <?php if ($alertsCount > 0): ?>
        <span class="nl-count"><?= $alertsCount ?></span>
      <?php endif; ?>
    </a>
    <a href="/specs/user/account.php" class="nl <?= $currentPage==='account' ? 'active':'' ?>">
      <span class="nl-icon">👤</span> Account
    </a>
    <a href="/specs/logout.php" class="nl nl-mobile-only">
      <span class="nl-icon">🚪</span> Sign Out
    </a>
  </div>
  <div class="nav-right">
    <a href="/specs/user/basket.php" class="basket-btn">
      🛒 Basket
      <span class="bcount" id="basket-badge"><?= $basketCount ?></span>
    </a>
    <div class="nav-divider"></div>
    <div class="nav-avatar"><?= avatarLetter() ?></div>
    <span class="nav-name"><?= htmlspecialchars(explode(' ',$user['name'])[0]) ?></span>
    <a href="/specs/logout.php" class="btn-signout">Sign Out</a>
  </div>

  <?php elseif (isAdmin()): ?>
  <!-- ── ADMIN NAV ── -->
  <div class="nav-links">
    <a href="/specs/admin/index.php"    class="nl <?= $currentPage==='index'    && $currentDir==='admin' ? 'active':'' ?>">
      <span class="nl-icon">📊</span> Dashboard
    </a>
    <a href="/specs/admin/products.php" class="nl <?= $currentPage==='products' ? 'active':'' ?>">
      <span class="nl-icon">🛒</span> Products
    </a>
    <a href="/specs/admin/prices.php"   class="nl <?= $currentPage==='prices'   ? 'active':'' ?>">
      <span class="nl-icon">💰</span> Prices
    </a>
    <a href="/specs/admin/stores.php"   class="nl <?= $currentPage==='stores'   ? 'active':'' ?>">
      <span class="nl-icon">🏬</span> Stores
    </a>
    <a href="/specs/admin/users.php"    class="nl <?= $currentPage==='users'    ? 'active':'' ?>">
      <span class="nl-icon">👥</span> Users
    </a>
    <a href="/specs/admin/alerts.php"   class="nl <?= $currentPage==='alerts'   ? 'active':'' ?>">
      <span class="nl-icon">🔔</span> Alerts
    </a>
    <a href="/specs/admin/reports.php"  class="nl <?= $currentPage==='reports'  ? 'active':'' ?>">
      <span class="nl-icon">📋</span> Reports
    </a>
    <a href="/specs/logout.php" class="nl nl-mobile-only">
      <span class="nl-icon">🚪</span> Sign Out
    </a>
  </div>
  <div class="nav-right">
    <span class="admin-badge">Admin</span>
    <div class="nav-divider"></div>
    <div class="nav-avatar nav-admin-avatar"><?= avatarLetter() ?></div>
    <span class="nav-name"><?= htmlspecialchars(explode(' ',$user['name'])[0]) ?></span>
    <a href="/specs/logout.php" class="btn-signout">Sign Out</a>
  </div>

  <?php else: ?>
  <!-- ── GUEST NAV ── -->
  <div class="nav-links">
    <a href="/specs/index.php" class="nl <?= $currentPage==='index' && $currentDir!=='admin' && $currentDir!=='user' ? 'active':'' ?>">
      <span class="nl-icon">🏠</span> Home
    </a>
    <a href="/specs/login.php" class="nl nl-mobile-only">
      <span class="nl-icon">🔑</span> Sign In
    </a>
  </div>
  <div class="nav-right">
    <a href="/specs/login.php"    class="btn-signin">Sign In</a>
    <a href="/specs/register.php" class="btn-register">Get Started Free</a>
  </div>
  <?php endif; ?>

  <!-- HAMBURGER (mobile only) -->
  <button type="button" class="nav-burger" aria-label="Open menu"
          onclick="this.closest('.top-nav').classList.toggle('menu-open')">
    <span></span><span></span><span></span>
  </button>

</nav>

<!-- TOAST -->
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

// ── BACK/FORWARD CACHE GUARD ──
// If the browser restores this page from its back-forward cache
// (e.g. back gesture after signing out), reload it from the server
// so the session check in requireLogin() runs again.
window.addEventListener('pageshow', function (e) {
  if (e.persisted) {
    window.location.reload();
  }
});
</script>

<div class="page-wrap">