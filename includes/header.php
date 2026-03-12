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
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Nunito+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
  <style>
    :root {
      --forest:#18382a;--leaf:#2d6a4f;--mint:#52b788;--gold:#e9a820;
      --amber:#f4a261;--cream:#fdf8f2;--sand:#e8e2d9;--ink:#1c1a17;
      --muted:#7a7060;--white:#fff;--red:#e63946;
      --r:12px;--rs:7px;
      --sh:0 2px 14px rgba(24,56,42,.09);
      --sh2:0 4px 24px rgba(24,56,42,.16);
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Nunito Sans',sans-serif;background:var(--cream);color:var(--ink);min-height:100vh}
    a{text-decoration:none;color:inherit}
    button,input,select,textarea{font-family:'Nunito Sans',sans-serif}
    h1,h2,h3,h4,h5{font-family:'Nunito',sans-serif}

    /* ── TOP NAV ── */
    .top-nav{
      position:fixed;top:0;left:0;right:0;z-index:500;
      background:var(--forest);height:58px;
      display:flex;align-items:center;padding:0 20px;gap:10px;
      box-shadow:0 2px 12px rgba(0,0,0,.25);
    }
    .nav-brand{
      font-family:'Nunito',sans-serif;font-weight:900;font-size:1.2rem;
      color:var(--white);display:flex;align-items:center;gap:7px;flex-shrink:0;
    }
    .nav-brand em{color:var(--gold);font-style:normal}
    .nav-brand small{
      font-size:.58rem;background:rgba(255,255,255,.14);
      padding:2px 7px;border-radius:99px;color:rgba(255,255,255,.7);
      font-weight:700;text-transform:uppercase;letter-spacing:.05em;
    }
    .nav-links{display:flex;gap:2px;flex:1;overflow-x:auto}
    .nl{
      padding:6px 12px;border-radius:var(--rs);
      font-weight:700;font-size:.8rem;cursor:pointer;
      transition:all .15s;color:rgba(255,255,255,.58);
      border:none;background:none;white-space:nowrap;
    }
    .nl:hover{color:var(--white);background:rgba(255,255,255,.1)}
    .nl.active{color:var(--white);background:rgba(255,255,255,.14)}
    .nav-right{display:flex;align-items:center;gap:8px;flex-shrink:0}
    .basket-btn{
      display:flex;align-items:center;gap:5px;
      background:var(--gold);color:var(--forest);
      font-family:'Nunito',sans-serif;font-weight:900;font-size:.78rem;
      padding:6px 13px;border-radius:var(--rs);cursor:pointer;
      border:none;transition:all .18s;
    }
    .basket-btn:hover{background:#d4940f}
    .bcount{
      background:var(--red);color:#fff;border-radius:50%;
      width:16px;height:16px;font-size:.62rem;font-weight:900;
      display:flex;align-items:center;justify-content:center;
    }
    .navav{
      width:32px;height:32px;border-radius:50%;
      background:var(--mint);display:flex;align-items:center;
      justify-content:center;font-size:.9rem;font-weight:900;color:#fff;
      cursor:pointer;overflow:hidden;
    }
    .navav img{width:100%;height:100%;object-fit:cover}
    .nav-name{color:rgba(255,255,255,.75);font-size:.8rem;font-weight:700}
    .btn-lo{
      background:rgba(255,255,255,.1);color:rgba(255,255,255,.65);
      border:none;border-radius:var(--rs);padding:5px 11px;
      font-weight:700;font-size:.75rem;cursor:pointer;transition:all .15s;
    }
    .btn-lo:hover{background:rgba(255,255,255,.2);color:#fff}

    /* ── PAGE WRAPPER ── */
    .page-wrap{padding-top:58px;min-height:100vh}

    /* ── PAGE HEADER ── */
    .ph{
      background:linear-gradient(135deg,var(--forest),var(--leaf));
      padding:28px 24px;color:#fff;
    }
    .ph h1{font-size:1.55rem;font-weight:900;margin-bottom:3px}
    .ph p{color:rgba(255,255,255,.6);font-size:.83rem}

    /* ── CONTAINER ── */
    .ctr{max-width:1240px;margin:0 auto;padding:24px}

    /* ── BUTTONS ── */
    .btn{
      display:inline-flex;align-items:center;gap:6px;
      font-family:'Nunito',sans-serif;font-weight:800;
      padding:10px 20px;border-radius:var(--rs);border:none;
      cursor:pointer;transition:all .18s;font-size:.88rem;
    }
    .btn-primary{background:var(--gold);color:var(--forest)}
    .btn-primary:hover{background:#d4940f;transform:translateY(-1px)}
    .btn-green{background:var(--leaf);color:#fff}
    .btn-green:hover{background:var(--forest)}
    .btn-red{background:#f8d7da;color:#721c24}
    .btn-red:hover{background:#f5c2c7}
    .btn-sm{padding:5px 12px;font-size:.76rem}

    /* ── FORM ELEMENTS ── */
    .fgrp{margin-bottom:14px}
    .flabel{
      display:block;font-size:.72rem;font-weight:800;
      color:var(--muted);text-transform:uppercase;
      letter-spacing:.06em;margin-bottom:5px;
    }
    .finput{
      width:100%;background:var(--cream);border:1.5px solid var(--sand);
      border-radius:var(--rs);padding:11px 13px;font-size:.92rem;
      color:var(--ink);outline:none;transition:border-color .18s;
    }
    .finput:focus{border-color:var(--leaf);background:var(--white)}

    /* ── CARDS ── */
    .card{
      background:var(--white);border-radius:var(--r);
      border:1.5px solid var(--sand);padding:22px;
    }
    .card-title{
      font-family:'Nunito',sans-serif;font-weight:800;
      font-size:.92rem;color:var(--forest);
      margin-bottom:14px;padding-bottom:11px;
      border-bottom:1.5px solid var(--sand);
    }

    /* ── TABLES ── */
    .tbl-wrap{overflow-x:auto}
    .tbl{
      width:100%;border-collapse:collapse;background:var(--white);
      border-radius:var(--r);overflow:hidden;box-shadow:var(--sh);font-size:.84rem;
    }
    .tbl thead{background:var(--leaf);color:#fff}
    .tbl th{
      padding:11px 14px;text-align:left;
      font-size:.7rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;
    }
    .tbl td{padding:11px 14px;border-bottom:1px solid var(--sand)}
    .tbl tr:last-child td{border-bottom:none}
    .tbl tr:hover td{background:#faf7f1}

    /* ── BADGES ── */
    .badge{display:inline-block;font-size:.65rem;font-weight:800;padding:2px 8px;border-radius:99px}
    .badge-green{background:#d4edda;color:#155724}
    .badge-yellow{background:#fff3cd;color:#856404}
    .badge-red{background:#f8d7da;color:#721c24}
    .badge-blue{background:#cce5ff;color:#004085}

    /* ── PRICE BADGES ── */
    .price-best{background:#d4edda;color:#155724;font-weight:800;padding:3px 9px;border-radius:6px;font-size:.82rem}
    .price-mid{background:#fff3cd;color:#856404;font-weight:800;padding:3px 9px;border-radius:6px;font-size:.82rem}
    .price-high{background:#f8d7da;color:#721c24;font-weight:800;padding:3px 9px;border-radius:6px;font-size:.82rem}

    /* ── ALERTS/FLASH ── */
    .flash{padding:12px 18px;border-radius:var(--rs);margin-bottom:16px;font-size:.88rem;font-weight:600}
    .flash-success{background:#d4edda;border:1.5px solid #a3d4b5;color:#155724}
    .flash-error{background:#f8d7da;border:1.5px solid #fcc;color:#721c24}
    .flash-info{background:#fff3cd;border:1.5px solid #ffd666;color:#856404}

    /* ── EMPTY STATE ── */
    .empty-state{text-align:center;padding:52px 20px;color:var(--muted)}
    .empty-state .ei{font-size:3rem;margin-bottom:10px}
    .empty-state p{font-size:.9rem}

    /* ── TOAST NOTIFICATION ── */
    .toast{
      position:fixed;bottom:22px;right:22px;z-index:9999;
      background:var(--forest);color:#fff;
      padding:12px 18px;border-radius:var(--rs);
      box-shadow:0 8px 26px rgba(0,0,0,.28);
      display:flex;align-items:center;gap:9px;
      font-size:.83rem;font-weight:700;max-width:320px;
      transform:translateY(80px);opacity:0;
      transition:all .3s cubic-bezier(.34,1.56,.64,1);
    }
    .toast.show{transform:translateY(0);opacity:1}

    /* ── RESPONSIVE ── */
    @media(max-width:600px){
      .nav-links{display:none}
      .ph h1{font-size:1.2rem}
      .ctr{padding:14px}
    }
  </style>
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
    <a href="/specs/user/index.php"   class="nl <?= $currentPage==='index'   && $currentDir==='user'   ? 'active':'' ?>">🏠 Home</a>
    <a href="/specs/user/browse.php"  class="nl <?= $currentPage==='browse'                             ? 'active':'' ?>">🛍️ Browse</a>
    <a href="/specs/user/trends.php"  class="nl <?= $currentPage==='trends'                             ? 'active':'' ?>">📈 Trends</a>
    <a href="/specs/user/alerts.php"  class="nl <?= $currentPage==='alerts'                             ? 'active':'' ?>">🔔 Alerts <?= $alertsCount>0?"<span class='bcount'>$alertsCount</span>":'' ?></a>
    <a href="/specs/user/account.php" class="nl <?= $currentPage==='account'                            ? 'active':'' ?>">👤 Account</a>
  </div>
  <div class="nav-right">
    <a href="/specs/user/basket.php" class="basket-btn">
      🛒 Basket <span class="bcount"><?= $basketCount ?></span>
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

<script>
// Toast notification function (available on all pages)
function showToast(msg, icon = '✅') {
  document.getElementById('toast-msg').textContent  = msg;
  document.getElementById('toast-icon').textContent = icon;
  const t = document.getElementById('specs-toast');
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3400);
}
</script>

<div class="page-wrap">
