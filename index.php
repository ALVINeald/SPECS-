<?php
// ============================================================
//  SPECS – Public Homepage
//  File: index.php
// ============================================================
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';

// If logged in redirect to their dashboard
if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/index.php' : 'user/index.php');
}

// Get live stats from database
$totalProducts = $conn->query("SELECT COUNT(*) AS t FROM products WHERE active=1")->fetch_assoc()['t'];
$totalStores   = $conn->query("SELECT COUNT(*) AS t FROM stores WHERE active=1")->fetch_assoc()['t'];
$totalUsers    = $conn->query("SELECT COUNT(*) AS t FROM users WHERE role='user'")->fetch_assoc()['t'];
$totalPrices   = $conn->query("SELECT COUNT(*) AS t FROM prices")->fetch_assoc()['t'];

// Get cheapest product deals (best savings)
$deals = $conn->query("
    SELECT p.id, p.name, p.unit, 
           MIN(pr.price) AS best_price, 
           MAX(pr.price) AS worst_price,
           (MAX(pr.price) - MIN(pr.price)) AS savings,
           s.name AS best_store
    FROM prices pr
    JOIN products p ON pr.product_id = p.id
    JOIN stores s ON pr.store_id = s.id
    WHERE p.active = 1
    GROUP BY p.id
    HAVING (MAX(pr.price) - MIN(pr.price)) > 2000
    ORDER BY (MAX(pr.price) - MIN(pr.price)) DESC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// Get stores
$stores = $conn->query("SELECT * FROM stores WHERE active=1 ORDER BY tier DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>SPECS – Mbarara City Supermarket Price Comparison</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Nunito+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    :root{
      --forest:#18382a;--leaf:#2d6a4f;--mint:#52b788;--gold:#e9a820;
      --amber:#f4a261;--cream:#fdf8f2;--sand:#e8e2d9;--ink:#1c1a17;
      --muted:#7a7060;--white:#fff;--r:14px;--rs:8px;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Nunito Sans',sans-serif;background:var(--cream);color:var(--ink)}
    a{text-decoration:none;color:inherit}

    /* ── NAV ── */
    nav{
      position:fixed;top:0;left:0;right:0;z-index:500;
      background:var(--forest);height:58px;
      display:flex;align-items:center;padding:0 24px;
      justify-content:space-between;
      box-shadow:0 2px 12px rgba(0,0,0,.25);
    }
    .nav-brand{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1.3rem;color:#fff;
    }
    .nav-brand em{color:var(--gold);font-style:normal}
    .nav-right{display:flex;gap:10px;align-items:center}
    .btn-nav{
      padding:7px 18px;border-radius:var(--rs);
      font-family:'Nunito',sans-serif;font-weight:800;
      font-size:.84rem;cursor:pointer;border:none;transition:all .18s;
    }
    .btn-outline{background:rgba(255,255,255,.1);color:#fff}
    .btn-outline:hover{background:rgba(255,255,255,.2)}
    .btn-gold{background:var(--gold);color:var(--forest)}
    .btn-gold:hover{background:#d4940f}

    /* ── HERO ── */
    .hero{
      min-height:100vh;display:flex;align-items:center;
      background:linear-gradient(135deg,var(--forest) 0%,var(--leaf) 60%,#3a8a62 100%);
      padding:80px 24px 60px;position:relative;overflow:hidden;
    }
    .hero::before{
      content:'';position:absolute;
      width:600px;height:600px;border-radius:50%;
      background:rgba(255,255,255,.04);
      top:-200px;right:-150px;
    }
    .hero::after{
      content:'';position:absolute;
      width:400px;height:400px;border-radius:50%;
      background:rgba(233,168,32,.08);
      bottom:-100px;left:-100px;
    }
    .hero-inner{
      max-width:1200px;margin:0 auto;width:100%;
      display:flex;align-items:center;gap:60px;
      position:relative;z-index:1;
    }
    .hero-text{flex:1}
    .hero-badge{
      display:inline-flex;align-items:center;gap:7px;
      background:rgba(233,168,32,.18);border:1px solid rgba(233,168,32,.35);
      color:var(--gold);padding:5px 14px;border-radius:99px;
      font-size:.74rem;font-weight:800;letter-spacing:.06em;
      text-transform:uppercase;margin-bottom:20px;
    }
    .hero h1{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:3.2rem;color:#fff;line-height:1.1;
      margin-bottom:18px;
    }
    .hero h1 em{color:var(--gold);font-style:normal}
    .hero p{
      color:rgba(255,255,255,.7);font-size:1.05rem;
      line-height:1.7;margin-bottom:32px;max-width:480px;
    }
    .hero-btns{display:flex;gap:12px;flex-wrap:wrap}
    .btn-hero-primary{
      background:var(--gold);color:var(--forest);
      padding:14px 28px;border-radius:var(--rs);
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1rem;border:none;cursor:pointer;
      transition:all .2s;display:inline-block;
    }
    .btn-hero-primary:hover{background:#d4940f;transform:translateY(-2px)}
    .btn-hero-secondary{
      background:rgba(255,255,255,.12);color:#fff;
      padding:14px 28px;border-radius:var(--rs);
      font-family:'Nunito',sans-serif;font-weight:800;
      font-size:1rem;border:1.5px solid rgba(255,255,255,.25);
      cursor:pointer;transition:all .2s;display:inline-block;
    }
    .btn-hero-secondary:hover{background:rgba(255,255,255,.2)}

    /* Hero stats */
    .hero-stats{
      display:flex;gap:24px;margin-top:36px;flex-wrap:wrap;
    }
    .hs{text-align:center}
    .hs-num{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1.8rem;color:var(--gold);display:block;
    }
    .hs-lbl{color:rgba(255,255,255,.55);font-size:.74rem;font-weight:600}

    /* Hero visual */
    .hero-visual{
      flex:1;display:grid;grid-template-columns:1fr 1fr;
      gap:12px;max-width:380px;
    }
    .hv-card{
      background:rgba(255,255,255,.1);backdrop-filter:blur(10px);
      border:1px solid rgba(255,255,255,.15);border-radius:12px;
      padding:16px;
    }
    .hv-card.tall{grid-row:span 2}
    .hv-product{font-size:.78rem;font-weight:700;color:#fff;margin-bottom:8px}
    .hv-prices{display:flex;flex-direction:column;gap:5px}
    .hv-price-row{
      display:flex;justify-content:space-between;
      font-size:.73rem;color:rgba(255,255,255,.7);
    }
    .hv-price-best{color:var(--gold);font-weight:800}
    .savings-badge{
      background:rgba(233,168,32,.2);color:var(--gold);
      border-radius:6px;padding:3px 7px;font-size:.68rem;
      font-weight:800;margin-top:6px;display:inline-block;
    }

    /* ── STATS BAR ── */
    .stats-bar{
      background:var(--white);border-bottom:1.5px solid var(--sand);
      padding:20px 24px;
    }
    .stats-inner{
      max-width:1200px;margin:0 auto;
      display:flex;justify-content:space-around;align-items:center;
      flex-wrap:wrap;gap:16px;
    }
    .stat-item{text-align:center}
    .stat-num{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1.6rem;color:var(--forest);display:block;
    }
    .stat-lbl{font-size:.74rem;color:var(--muted);font-weight:600}

    /* ── SECTIONS ── */
    section{padding:64px 24px}
    .sec-inner{max-width:1200px;margin:0 auto}
    .sec-tag{
      font-size:.7rem;font-weight:800;color:var(--leaf);
      text-transform:uppercase;letter-spacing:.1em;margin-bottom:8px;
    }
    .sec-title{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:2rem;color:var(--ink);margin-bottom:12px;
    }
    .sec-sub{color:var(--muted);font-size:.92rem;max-width:520px;line-height:1.7}

    /* ── HOW IT WORKS ── */
    .steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;margin-top:40px}
    .step{background:var(--white);border-radius:var(--r);padding:28px;border:1.5px solid var(--sand)}
    .step-num{
      width:42px;height:42px;border-radius:10px;
      background:var(--forest);color:var(--gold);
      font-family:'Nunito',sans-serif;font-weight:900;font-size:1.1rem;
      display:flex;align-items:center;justify-content:center;margin-bottom:14px;
    }
    .step h3{font-family:'Nunito',sans-serif;font-weight:800;font-size:1rem;margin-bottom:7px}
    .step p{font-size:.83rem;color:var(--muted);line-height:1.6}

    /* ── TOP DEALS ── */
    .deals-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:32px}
    .deal-card{
      background:var(--white);border-radius:var(--r);
      border:1.5px solid var(--sand);padding:18px;
      transition:all .2s;
    }
    .deal-card:hover{border-color:var(--mint);transform:translateY(-2px);box-shadow:0 8px 24px rgba(24,56,42,.1)}
    .deal-name{font-weight:700;font-size:.9rem;margin-bottom:4px}
    .deal-unit{font-size:.74rem;color:var(--muted);margin-bottom:12px}
    .deal-prices{display:flex;justify-content:space-between;align-items:flex-end}
    .deal-best{font-family:'Nunito',sans-serif;font-weight:900;font-size:1.1rem;color:var(--leaf)}
    .deal-worst{font-size:.78rem;color:var(--muted);text-decoration:line-through}
    .deal-save{
      background:#d4edda;color:#155724;
      font-size:.7rem;font-weight:800;
      padding:3px 8px;border-radius:99px;margin-top:6px;display:inline-block;
    }
    .deal-store{font-size:.74rem;color:var(--muted);margin-top:6px}

    /* ── STORES ── */
    .stores-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin-top:32px}
    .store-card{
      background:var(--white);border-radius:var(--r);
      border:1.5px solid var(--sand);padding:20px;
      text-align:center;transition:all .2s;
    }
    .store-card:hover{border-color:var(--gold);transform:translateY(-2px)}
    .store-icon{font-size:2rem;margin-bottom:10px}
    .store-name{font-family:'Nunito',sans-serif;font-weight:800;font-size:.92rem;margin-bottom:4px}
    .store-addr{font-size:.74rem;color:var(--muted);margin-bottom:8px}
    .store-tier{
      font-size:.66rem;font-weight:800;padding:2px 9px;
      border-radius:99px;text-transform:uppercase;letter-spacing:.05em;
    }
    .tier-premium{background:#fff3cd;color:#856404}
    .tier-mid{background:#cce5ff;color:#004085}
    .tier-budget{background:#d4edda;color:#155724}
    .tier-market{background:#f8d7da;color:#721c24}

    /* ── FEATURES ── */
    .features-bg{background:var(--forest)}
    .features-bg .sec-title{color:#fff}
    .features-bg .sec-sub{color:rgba(255,255,255,.6)}
    .features-bg .sec-tag{color:var(--gold)}
    .feat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;margin-top:36px}
    .feat{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);border-radius:var(--r);padding:24px}
    .feat-icon{font-size:1.8rem;margin-bottom:12px}
    .feat h3{font-family:'Nunito',sans-serif;font-weight:800;color:#fff;margin-bottom:7px;font-size:.95rem}
    .feat p{font-size:.82rem;color:rgba(255,255,255,.6);line-height:1.6}

    /* ── CTA ── */
    .cta-section{
      background:linear-gradient(135deg,var(--gold),var(--amber));
      padding:64px 24px;text-align:center;
    }
    .cta-section h2{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:2.2rem;color:var(--forest);margin-bottom:12px;
    }
    .cta-section p{color:rgba(24,56,42,.7);margin-bottom:28px;font-size:.95rem}
    .btn-cta{
      background:var(--forest);color:#fff;
      padding:14px 32px;border-radius:var(--rs);
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1rem;border:none;cursor:pointer;
      transition:all .2s;display:inline-block;margin:6px;
    }
    .btn-cta:hover{background:var(--leaf);transform:translateY(-2px)}
    .btn-cta-outline{
      background:transparent;color:var(--forest);
      border:2px solid var(--forest);
      padding:14px 32px;border-radius:var(--rs);
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1rem;cursor:pointer;
      transition:all .2s;display:inline-block;margin:6px;
    }

    /* ── FOOTER ── */
    footer{
      background:var(--forest);color:rgba(255,255,255,.45);
      padding:28px 24px;
    }
    .footer-inner{
      max-width:1200px;margin:0 auto;
      display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;
    }
    .footer-brand{
      font-family:'Nunito',sans-serif;font-weight:900;
      color:var(--gold);font-size:1.1rem;
    }

    @media(max-width:768px){
      .hero h1{font-size:2rem}
      .hero-visual{display:none}
      .hero-inner{flex-direction:column}
    }
  </style>
</head>
<body>

<!-- NAVIGATION -->
<nav>
  <div class="nav-brand">SP<em>EC</em>S <small style="font-size:.55rem;background:rgba(255,255,255,.12);padding:2px 7px;border-radius:99px;color:rgba(255,255,255,.6);font-weight:700">Mbarara</small></div>
  <div class="nav-right">
    <a href="login.php"    class="btn-nav btn-outline">Sign In</a>
    <a href="register.php" class="btn-nav btn-gold">Get Started Free</a>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-inner">
    <div class="hero-text">
      <div class="hero-badge">🇺🇬 Mbarara City · Uganda</div>
      <h1>Stop <em>Overpaying</em><br>at Mbarara Supermarkets</h1>
      <p>Compare prices across <?= $totalStores ?> supermarkets, track price trends, set alerts and build a smart shopping plan — all in one free app.</p>
      <div class="hero-btns">
        <a href="register.php" class="btn-hero-primary">Start Saving Free →</a>
        <a href="login.php"    class="btn-hero-secondary">Sign In</a>
      </div>
      <div class="hero-stats">
        <div class="hs"><span class="hs-num"><?= number_format($totalProducts) ?>+</span><span class="hs-lbl">Products</span></div>
        <div class="hs"><span class="hs-num"><?= $totalStores ?></span><span class="hs-lbl">Supermarkets</span></div>
        <div class="hs"><span class="hs-num"><?= number_format($totalPrices) ?>+</span><span class="hs-lbl">Price Records</span></div>
        <div class="hs"><span class="hs-num"><?= number_format($totalUsers) ?>+</span><span class="hs-lbl">Shoppers</span></div>
      </div>
    </div>
    <div class="hero-visual">
      <div class="hv-card tall">
        <div class="hv-product">🛢️ Mukwano Oil 1L</div>
        <div class="hv-prices">
          <?php
          $oilPrices = $conn->query("SELECT pr.price, s.short_name FROM prices pr JOIN stores s ON pr.store_id=s.id WHERE pr.product_id=39 ORDER BY pr.price ASC LIMIT 5");
          $first = true;
          while($op = $oilPrices->fetch_assoc()):
          ?>
          <div class="hv-price-row">
            <span><?= $op['short_name'] ?></span>
            <span class="<?= $first ? 'hv-price-best' : '' ?>">UGX <?= number_format($op['price']) ?></span>
          </div>
          <?php $first = false; endwhile; ?>
        </div>
        <span class="savings-badge">Save up to UGX 3,000</span>
      </div>
      <div class="hv-card">
        <div class="hv-product">🍞 White Bread</div>
        <div class="hv-prices">
          <?php
          $breadPrices = $conn->query("SELECT pr.price, s.short_name FROM prices pr JOIN stores s ON pr.store_id=s.id WHERE pr.product_id=69 ORDER BY pr.price ASC LIMIT 3");
          $first = true;
          while($bp = $breadPrices->fetch_assoc()):
          ?>
          <div class="hv-price-row">
            <span><?= $bp['short_name'] ?></span>
            <span class="<?= $first ? 'hv-price-best' : '' ?>">UGX <?= number_format($bp['price']) ?></span>
          </div>
          <?php $first = false; endwhile; ?>
        </div>
      </div>
      <div class="hv-card">
        <div class="hv-product">🥛 Fresh Milk 1L</div>
        <div class="hv-prices">
          <?php
          $milkPrices = $conn->query("SELECT pr.price, s.short_name FROM prices pr JOIN stores s ON pr.store_id=s.id WHERE pr.product_id=24 ORDER BY pr.price ASC LIMIT 3");
          $first = true;
          while($mp = $milkPrices->fetch_assoc()):
          ?>
          <div class="hv-price-row">
            <span><?= $mp['short_name'] ?></span>
            <span class="<?= $first ? 'hv-price-best' : '' ?>">UGX <?= number_format($mp['price']) ?></span>
          </div>
          <?php $first = false; endwhile; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- STATS BAR -->
<div class="stats-bar">
  <div class="stats-inner">
    <div class="stat-item"><span class="stat-num"><?= number_format($totalProducts) ?>+</span><span class="stat-lbl">Products Tracked</span></div>
    <div class="stat-item"><span class="stat-num"><?= $totalStores ?></span><span class="stat-lbl">Mbarara Supermarkets</span></div>
    <div class="stat-item"><span class="stat-num"><?= number_format($totalPrices) ?>+</span><span class="stat-lbl">Live Price Records</span></div>
    <div class="stat-item"><span class="stat-num">UGX</span><span class="stat-lbl">All Prices in Shillings</span></div>
  </div>
</div>

<!-- HOW IT WORKS -->
<section>
  <div class="sec-inner">
    <div class="sec-tag">How it works</div>
    <div class="sec-title">Save money in 3 simple steps</div>
    <div class="sec-sub">SPECS makes it easy to find the best prices across Mbarara supermarkets without visiting each one.</div>
    <div class="steps">
      <div class="step">
        <div class="step-num">1</div>
        <h3>Create your free account</h3>
        <p>Sign up in seconds using your email or Google account. Set your monthly budget to get started.</p>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <h3>Browse & compare prices</h3>
        <p>Search for any product and instantly see prices across all 7 Mbarara supermarkets side by side.</p>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <h3>Build your shopping plan</h3>
        <p>Add items to your basket, pick the best store for each item, and download a shareable shopping receipt.</p>
      </div>
      <div class="step">
        <div class="step-num">4</div>
        <h3>Set price alerts</h3>
        <p>Tell us your target price for any product and we will notify you when it drops below your limit.</p>
      </div>
    </div>
  </div>
</section>

<!-- TOP DEALS -->
<?php if (!empty($deals)): ?>
<section style="background:var(--white);border-top:1.5px solid var(--sand);border-bottom:1.5px solid var(--sand)">
  <div class="sec-inner">
    <div class="sec-tag">Price comparison</div>
    <div class="sec-title">Biggest savings right now 🔥</div>
    <div class="sec-sub">Products with the biggest price differences across Mbarara stores.</div>
    <div class="deals-grid">
      <?php foreach($deals as $deal): 
        $saving = $deal['worst_price'] - $deal['best_price'];
      ?>
      <div class="deal-card">
        <div class="deal-name"><?= htmlspecialchars($deal['name']) ?></div>
        <div class="deal-unit"><?= htmlspecialchars($deal['unit']) ?></div>
        <div class="deal-prices">
          <div>
            <div class="deal-best">UGX <?= number_format($deal['best_price']) ?></div>
            <div class="deal-worst">UGX <?= number_format($deal['worst_price']) ?></div>
          </div>
        </div>
        <span class="deal-save">Save UGX <?= number_format($saving) ?></span>
        <div class="deal-store">Best at: <?= htmlspecialchars($deal['best_store']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="text-align:center;margin-top:28px">
      <a href="register.php" class="btn-hero-primary" style="background:var(--forest);color:#fff;padding:12px 28px;border-radius:var(--rs);font-family:'Nunito',sans-serif;font-weight:900;display:inline-block">
        See All <?= $totalProducts ?> Products →
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- STORES -->
<section>
  <div class="sec-inner">
    <div class="sec-tag">Our coverage</div>
    <div class="sec-title">All major Mbarara supermarkets</div>
    <div class="sec-sub">We track prices across every major supermarket in Mbarara City.</div>
    <div class="stores-grid">
      <?php 
      $storeIcons = ['premium'=>'🏆','mid'=>'🛒','budget'=>'💰','market'=>'🏪'];
      foreach($stores as $store): ?>
      <div class="store-card">
        <div class="store-icon"><?= $storeIcons[$store['tier']] ?? '🏬' ?></div>
        <div class="store-name"><?= htmlspecialchars($store['name']) ?></div>
        <div class="store-addr"><?= htmlspecialchars($store['address']) ?></div>
        <span class="store-tier tier-<?= $store['tier'] ?>"><?= ucfirst($store['tier']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FEATURES -->
<section class="features-bg">
  <div class="sec-inner">
    <div class="sec-tag">Features</div>
    <div class="sec-title">Everything you need to shop smart</div>
    <div class="sec-sub">Built specifically for Mbarara City shoppers who want to stretch their shillings further.</div>
    <div class="feat-grid">
      <div class="feat"><div class="feat-icon">🔍</div><h3>Price Comparison</h3><p>See prices for 205+ products across 7 stores instantly, sorted by cheapest.</p></div>
      <div class="feat"><div class="feat-icon">🔔</div><h3>Price Alerts</h3><p>Set a target price and get notified by email when a product drops below it.</p></div>
      <div class="feat"><div class="feat-icon">📈</div><h3>Price Trends</h3><p>See how prices have changed over the past 6 months with interactive charts.</p></div>
      <div class="feat"><div class="feat-icon">🧾</div><h3>Shopping Receipts</h3><p>Download a shareable shopping plan that looks like an MTN MoMo receipt.</p></div>
      <div class="feat"><div class="feat-icon">💰</div><h3>Budget Tracker</h3><p>Set your monthly grocery budget and track how much you are spending.</p></div>
      <div class="feat"><div class="feat-icon">🛒</div><h3>Smart Basket</h3><p>Add items to your basket and SPECS tells you which store gives the best total.</p></div>
    </div>
  </div>
</section>

<!-- CTA -->
<div class="cta-section">
  <h2>Ready to start saving? 🚀</h2>
  <p>Join Mbarara shoppers already using SPECS to spend less on groceries every month.</p>
  <a href="register.php" class="btn-cta">Create Free Account</a>
  <a href="login.php"    class="btn-cta-outline">Sign In</a>
</div>

<!-- FOOTER -->
<footer>
  <div class="footer-inner">
    <div>
      <span class="footer-brand">SPECS</span>
      <span style="font-size:.8rem"> — Supermarket Pricing Estimation & Comparison System</span>
    </div>
    <div style="font-size:.76rem">
      Built by <strong style="color:var(--gold)">Mbabazi Alvin</strong> · 24/BSU/DIT/3253 · Bishop Stuart University · Mbarara
    </div>
  </div>
</footer>

</body>
</html>
