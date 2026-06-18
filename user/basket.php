<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'My Basket';
$uid       = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_qty') {
        $pid = (int)$_POST['product_id'];
        $qty = (int)$_POST['quantity'];
        if ($qty <= 0) $conn->query("DELETE FROM basket WHERE user_id=$uid AND product_id=$pid");
        else           $conn->query("UPDATE basket SET quantity=$qty WHERE user_id=$uid AND product_id=$pid");
        // Stay on page — and use fragment to scroll back to store panel
        header("Location: basket.php#store-panel");
        exit();
    }

    if ($action === 'remove') {
        $pid = (int)$_POST['product_id'];
        $conn->query("DELETE FROM basket WHERE user_id=$uid AND product_id=$pid");
        setFlash('success', 'Item removed.');
        header("Location: basket.php#store-panel");
        exit();
    }

    if ($action === 'clear') {
        $conn->query("DELETE FROM basket WHERE user_id=$uid");
        setFlash('success', 'Basket cleared.');
        redirect('basket.php');
    }

    if ($action === 'save_plan') {
        $store_id = (int)$_POST['store_id'];
        $items = $conn->query("
            SELECT b.quantity, p.name, p.unit,
                   COALESCE(
                       (SELECT pr.price FROM prices pr WHERE pr.product_id=b.product_id AND pr.store_id=$store_id LIMIT 1),
                       (SELECT MIN(pr2.price) FROM prices pr2 WHERE pr2.product_id=b.product_id)
                   ) AS price
            FROM basket b JOIN products p ON b.product_id=p.id
            WHERE b.user_id=$uid
        ")->fetch_all(MYSQLI_ASSOC);

        $total   = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
        $ref     = generatePlanRef($conn);
        $json    = $conn->real_escape_string(json_encode($items));
        $maxT    = $conn->query("SELECT SUM(max_p*b.quantity) AS t FROM basket b JOIN (SELECT product_id,MAX(price) AS max_p FROM prices GROUP BY product_id) mp ON mp.product_id=b.product_id WHERE b.user_id=$uid")->fetch_assoc()['t'] ?? $total;
        $savings = max(0, $maxT - $total);

        $conn->query("INSERT INTO store_plans (user_id,store_id,plan_ref,items_json,total_amount,savings) VALUES ($uid,$store_id,'$ref','$json',$total,$savings)");
        setFlash('success', "🧾 Plan $ref saved! Scroll up to print.");
        header("Location: basket.php?plan=$ref#receipt");
        exit();
    }
}

// ── DATA ──────────────────────────────────────────────────────
$basketItems = $conn->query("
    SELECT b.*, p.name AS product_name, p.unit, c.name AS cat_name
    FROM basket b
    JOIN products p  ON b.product_id=p.id
    JOIN categories c ON p.category_id=c.id
    WHERE b.user_id=$uid ORDER BY c.name, p.name
")->fetch_all(MYSQLI_ASSOC);

$stores       = getStores($conn);
$allPrices    = [];
$storeTotals  = [];
$itemBestP    = [];

if (!empty($basketItems)) {
    $pids = implode(',', array_column($basketItems, 'product_id'));
    foreach ($conn->query("SELECT pr.product_id,pr.store_id,pr.price FROM prices pr JOIN stores s ON pr.store_id=s.id WHERE pr.product_id IN ($pids) AND s.active=1")->fetch_all(MYSQLI_ASSOC) as $pr) {
        $allPrices[$pr['product_id']][$pr['store_id']] = $pr['price'];
    }
    foreach ($basketItems as $item) {
        $pid = $item['product_id'];
        $itemBestP[$pid] = !empty($allPrices[$pid]) ? min($allPrices[$pid]) : 0;
    }
    foreach ($stores as $s) {
        $total = 0; $missing = 0;
        foreach ($basketItems as $item) {
            $p = $allPrices[$item['product_id']][$s['id']] ?? null;
            if ($p) $total += $p * $item['quantity'];
            else    $missing++;
        }
        if ($total > 0) $storeTotals[$s['id']] = ['store'=>$s,'total'=>$total,'missing'=>$missing,'has_all'=>$missing===0];
    }
    asort($storeTotals);
}

$bestStore   = !empty($storeTotals) ? reset($storeTotals) : null;
$worstTotal  = !empty($storeTotals) ? max(array_column($storeTotals,'total')) : 0;
$basketTotal = array_sum(array_map(fn($i)=>($itemBestP[$i['product_id']]??0)*$i['quantity'], $basketItems));
$userBudget  = (int)($_SESSION['monthly_budget'] ?? 0);

// Default selected tab — first (cheapest) store
$defaultTab  = $bestStore ? $bestStore['store']['id'] : 0;

$savedPlan = null;
if (isset($_GET['plan'])) {
    $ref = $conn->real_escape_string($_GET['plan']);
    $savedPlan = $conn->query("SELECT sp.*,s.name AS store_name FROM store_plans sp JOIN stores s ON sp.store_id=s.id WHERE sp.plan_ref='$ref' AND sp.user_id=$uid LIMIT 1")->fetch_assoc();
}

include '../includes/header.php';
?>

<style>
.ph { background:linear-gradient(135deg,var(--forest),var(--leaf)); padding:28px 24px; }
.ph h1 { color:#fff; font-size:1.55rem; margin-bottom:3px; }
.ph p  { color:rgba(255,255,255,.6); font-size:.83rem; }

/* ── LAYOUT ── */
.basket-wrap { max-width:1240px; margin:0 auto; padding:24px; }
.basket-grid { display:grid; grid-template-columns:1fr 360px; gap:22px; }

/* ── BASKET ITEMS ── */
.basket-item {
  display:flex; align-items:flex-start; gap:12px;
  padding:14px 0; border-bottom:1px solid var(--sand);
}
.basket-item:last-child { border-bottom:none; }
.bi-info { flex:1; min-width:0; }
.bi-name { font-weight:800; font-size:.9rem; margin-bottom:1px; }
.bi-unit { font-size:.72rem; color:var(--muted); }
.bi-best { font-size:.76rem; color:var(--leaf); font-weight:700; margin-top:2px; }
.qty-controls { display:flex; align-items:center; gap:7px; flex-shrink:0; }
.qty-btn {
  width:26px; height:26px; border-radius:6px; background:var(--sand);
  border:none; cursor:pointer; font-size:.9rem; font-weight:900;
  display:flex; align-items:center; justify-content:center; transition:all .15s;
}
.qty-btn:hover { background:var(--mint); color:#fff; }
.qty-num { font-family:'Nunito',sans-serif; font-weight:900; font-size:.95rem; min-width:18px; text-align:center; }
.bi-price { font-family:'Nunito',sans-serif; font-weight:900; color:var(--forest); font-size:.95rem; text-align:right; min-width:80px; flex-shrink:0; }

/* ═══════════════════════════════════════
   STORE COMPARISON PANEL
   (stays in place — no page reload)
═══════════════════════════════════════ */
#store-panel {
  background:var(--white);
  border:1.5px solid var(--sand);
  border-radius:var(--r);
  overflow:hidden;
  scroll-margin-top:80px; /* offset for fixed nav */
}

.sp-header {
  background:linear-gradient(135deg,var(--forest),#2a5e40);
  padding:18px 22px;
}
.sp-title { font-family:'Nunito',sans-serif; font-weight:900; font-size:1rem; color:#fff; margin-bottom:3px; }
.sp-sub   { font-size:.76rem; color:rgba(255,255,255,.6); }

/* ── TABS ── */
.sp-tabs {
  display:flex; overflow-x:auto; background:var(--cream);
  border-bottom:1.5px solid var(--sand); scrollbar-width:none;
}
.sp-tabs::-webkit-scrollbar { display:none; }

.sp-tab {
  padding:12px 18px; border:none; background:none; cursor:pointer;
  font-family:'Nunito',sans-serif; font-weight:700; font-size:.78rem;
  color:var(--muted); border-bottom:3px solid transparent;
  transition:all .18s; white-space:nowrap; flex-shrink:0;
  position:relative;
}
.sp-tab:hover { color:var(--forest); background:rgba(255,255,255,.7); }
.sp-tab.active { color:var(--forest); border-bottom-color:var(--gold); background:var(--white); font-weight:900; }
.sp-tab .tab-total { display:block; font-size:.68rem; margin-top:1px; }
.sp-tab.active .tab-total { color:var(--leaf); }
.sp-tab .best-pill {
  background:var(--gold); color:var(--forest);
  font-size:.55rem; font-weight:900;
  padding:1px 5px; border-radius:99px; margin-left:4px;
  vertical-align:middle;
}

/* ── TAB CONTENT ── */
.sp-content { display:none; padding:18px 22px; }
.sp-content.active { display:block; }

.sc-item {
  display:flex; justify-content:space-between; align-items:center;
  padding:10px 0; border-bottom:1px solid var(--sand); font-size:.86rem;
}
.sc-item:last-of-type { border-bottom:none; }
.sc-name { font-weight:700; }
.sc-qty  { font-size:.72rem; color:var(--muted); }
.sc-price{ font-family:'Nunito',sans-serif; font-weight:900; color:var(--forest); }
.sc-na   { font-size:.74rem; color:var(--red); font-style:italic; }

.sc-total-row {
  display:flex; justify-content:space-between; align-items:center;
  padding:14px 0 8px; border-top:2px solid var(--sand); margin-top:8px;
}
.sc-total-num {
  font-family:'Nunito',sans-serif; font-weight:900; font-size:1.3rem; color:var(--forest);
}
.sc-savings {
  background:#d4edda; color:#155724; border-radius:var(--rs);
  padding:9px 14px; font-size:.82rem; font-weight:700;
  text-align:center; margin:10px 0;
}
.sc-missing { font-size:.74rem; color:var(--red); margin-top:4px; }

.sc-save-btn {
  width:100%; background:var(--forest); color:#fff; border:none;
  border-radius:var(--rs); padding:12px;
  font-family:'Nunito',sans-serif; font-weight:900; font-size:.9rem;
  cursor:pointer; transition:all .18s; margin-top:6px;
  display:flex; align-items:center; justify-content:center; gap:8px;
}
.sc-save-btn:hover { background:var(--leaf); transform:translateY(-1px); }

/* ── SIDEBAR ── */
.basket-sidebar { display:flex; flex-direction:column; gap:16px; }

.summary-card {
  background:linear-gradient(135deg,var(--forest),#2a5e40);
  border-radius:var(--r); padding:20px; color:#fff;
}
.sc-label  { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:rgba(255,255,255,.5); margin-bottom:4px; }
.sc-amount { font-family:'Nunito',sans-serif; font-weight:900; font-size:1.6rem; color:var(--gold); margin-bottom:2px; }
.sc-sml    { font-size:.76rem; color:rgba(255,255,255,.5); margin-bottom:12px; }

.rank-card { background:var(--white); border:1.5px solid var(--sand); border-radius:var(--r); padding:20px; }
.rank-row {
  display:flex; justify-content:space-between; align-items:center;
  padding:9px 0; border-bottom:1px solid var(--sand); cursor:pointer;
  transition:background .15s; border-radius:var(--rs); padding:9px 8px;
}
.rank-row:hover { background:var(--cream); }
.rank-row:last-child { border-bottom:none; }
.rank-num {
  width:24px; height:24px; border-radius:50%;
  background:var(--sand); color:var(--muted);
  display:flex; align-items:center; justify-content:center;
  font-family:'Nunito',sans-serif; font-weight:900; font-size:.72rem;
  flex-shrink:0;
}
.rank-row:first-child .rank-num { background:var(--gold); color:var(--forest); }

/* ── RECEIPT ── */
.receipt-box {
  background:var(--forest); color:#fff; border-radius:var(--r);
  padding:26px; margin-bottom:22px; scroll-margin-top:80px;
}
.receipt-header { text-align:center; border-bottom:2px dashed rgba(255,255,255,.3); padding-bottom:16px; margin-bottom:14px; }
.receipt-brand  { font-family:'Nunito',sans-serif; font-weight:900; font-size:1.4rem; color:var(--gold); }
.receipt-item   { display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid rgba(255,255,255,.1); font-size:.84rem; }
.receipt-total  { display:flex; justify-content:space-between; padding:12px 0 6px; font-size:1rem; font-weight:900; }

@media(max-width:900px){
  .basket-grid { grid-template-columns:1fr; }
}
</style>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <h1>🛒 My Basket</h1>
      <p><?= count($basketItems) ?> item<?= count($basketItems)!=1?'s':'' ?> · Compare store prices below</p>
    </div>
    <?php if (!empty($basketItems)): ?>
    <form method="POST" onsubmit="return confirm('Clear all items?')">
      <input type="hidden" name="action" value="clear"/>
      <button type="submit" class="btn btn-red btn-sm">🗑️ Clear All</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="basket-wrap">
  <?php showFlash(); ?>

  <!-- RECEIPT -->
  <?php if ($savedPlan): ?>
  <div class="receipt-box" id="receipt">
    <div class="receipt-header">
      <div class="receipt-brand">SPECS</div>
      <div style="font-size:.7rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.1em">Mbarara City Shopping Plan</div>
      <div style="font-size:.8rem;color:rgba(255,255,255,.5);margin-top:4px"><?= $savedPlan['plan_ref'] ?></div>
      <div style="font-size:.72rem;color:rgba(255,255,255,.4)"><?= date('d M Y, H:i', strtotime($savedPlan['created_at'])) ?></div>
    </div>
    <div style="font-size:.82rem;color:rgba(255,255,255,.6);margin-bottom:8px">🏬 Store: <strong style="color:#fff"><?= htmlspecialchars($savedPlan['store_name']) ?></strong></div>
    <?php foreach (json_decode($savedPlan['items_json'],true) as $pi): ?>
    <div class="receipt-item">
      <span><?= htmlspecialchars($pi['name']) ?> (<?= $pi['unit'] ?>) × <?= $pi['quantity'] ?></span>
      <span style="font-weight:700">UGX <?= number_format($pi['price']*$pi['quantity']) ?></span>
    </div>
    <?php endforeach; ?>
    <div class="receipt-total">
      <span>TOTAL</span>
      <span style="color:var(--gold)">UGX <?= number_format($savedPlan['total_amount']) ?></span>
    </div>
    <?php if ($savedPlan['savings']>0): ?>
    <div style="background:rgba(233,168,32,.15);border-radius:var(--rs);padding:10px;text-align:center;font-size:.82rem;color:var(--gold);font-weight:700;margin-top:10px">
      🎉 You save UGX <?= number_format($savedPlan['savings']) ?> vs most expensive store!
    </div>
    <?php endif; ?>
    <div style="margin-top:16px;text-align:center;display:flex;gap:10px;justify-content:center">
      <button onclick="window.print()" style="background:var(--gold);color:var(--forest);border:none;padding:8px 18px;border-radius:var(--rs);font-weight:900;cursor:pointer;font-family:'Nunito',sans-serif">🖨️ Print</button>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($basketItems)): ?>
    <div class="empty-state card">
      <div class="ei">🛒</div><p>Your basket is empty.</p>
      <a href="browse.php" class="btn btn-primary" style="margin-top:14px">Start Shopping</a>
    </div>
  <?php else: ?>

  <div class="basket-grid">

    <!-- LEFT COLUMN -->
    <div>

      <!-- ITEMS -->
      <div class="card" style="margin-bottom:22px">
        <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
          <span>🛒 Items</span>
          <a href="browse.php" class="btn btn-sm btn-green">➕ Add More</a>
        </div>
        <?php foreach ($basketItems as $item):
          $bestP = $itemBestP[$item['product_id']] ?? 0;
        ?>
        <div class="basket-item">
          <div class="bi-info">
            <div class="bi-name"><?= htmlspecialchars($item['product_name']) ?></div>
            <div class="bi-unit"><?= htmlspecialchars($item['unit']) ?></div>
            <div class="bi-best">Best: <?= formatPrice($bestP) ?> each</div>
          </div>
          <!-- QTY — form posts to #store-panel anchor -->
          <div class="qty-controls">
            <form method="POST" action="basket.php#store-panel">
              <input type="hidden" name="action"     value="update_qty"/>
              <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>"/>
              <button type="submit" name="quantity" value="<?= $item['quantity']-1 ?>" class="qty-btn">−</button>
            </form>
            <span class="qty-num"><?= $item['quantity'] ?></span>
            <form method="POST" action="basket.php#store-panel">
              <input type="hidden" name="action"     value="update_qty"/>
              <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>"/>
              <button type="submit" name="quantity" value="<?= $item['quantity']+1 ?>" class="qty-btn">+</button>
            </form>
          </div>
          <div class="bi-price"><?= formatPrice($bestP * $item['quantity']) ?></div>
          <form method="POST" action="basket.php#store-panel">
            <input type="hidden" name="action"     value="remove"/>
            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>"/>
            <button type="submit" class="btn btn-sm btn-red" style="padding:5px 9px">🗑️</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- STORE COMPARISON PANEL -->
      <?php if (!empty($storeTotals)): ?>
      <div id="store-panel">
        <div class="sp-header">
          <div class="sp-title">🏬 Choose Your Store</div>
          <div class="sp-sub">Tap a store tab to see your full itemised price — then save your plan</div>
        </div>

        <!-- TABS -->
        <div class="sp-tabs" id="spTabs">
          <?php $rank=1; foreach ($storeTotals as $sid => $st):
            $isBest = $rank===1;
          ?>
          <button class="sp-tab <?= $rank===1?'active':'' ?>"
                  onclick="switchTab(<?= $sid ?>, this)"
                  data-sid="<?= $sid ?>">
            <?= htmlspecialchars($st['store']['short_name']) ?>
            <?php if ($isBest): ?><span class="best-pill">BEST</span><?php endif; ?>
            <span class="tab-total">UGX <?= number_format($st['total']) ?></span>
          </button>
          <?php $rank++; endforeach; ?>
        </div>

        <!-- TAB CONTENTS -->
        <?php $rank=1; foreach ($storeTotals as $sid => $st):
          $saving = $worstTotal - $st['total'];
        ?>
        <div class="sp-content <?= $rank===1?'active':'' ?>" id="tab-<?= $sid ?>">

          <?php foreach ($basketItems as $item):
            $price = $allPrices[$item['product_id']][$sid] ?? null;
          ?>
          <div class="sc-item">
            <div>
              <div class="sc-name"><?= htmlspecialchars($item['product_name']) ?></div>
              <div class="sc-qty"><?= htmlspecialchars($item['unit']) ?> × <?= $item['quantity'] ?></div>
            </div>
            <?php if ($price): ?>
            <div class="sc-price">UGX <?= number_format($price*$item['quantity']) ?></div>
            <?php else: ?>
            <div class="sc-na">Not available</div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>

          <div class="sc-total-row">
            <div>
              <div style="font-size:.66rem;color:var(--muted);font-weight:700;text-transform:uppercase">Total at <?= htmlspecialchars($st['store']['short_name']) ?></div>
              <div class="sc-total-num">UGX <?= number_format($st['total']) ?></div>
            </div>
            <?php if (!$st['has_all']): ?>
            <span class="sc-missing">⚠️ <?= $st['missing'] ?> item<?= $st['missing']>1?'s':'' ?> not available</span>
            <?php endif; ?>
          </div>

          <?php if ($saving > 0): ?>
          <div class="sc-savings">🎉 You save UGX <?= number_format($saving) ?> vs most expensive store!</div>
          <?php endif; ?>

          <form method="POST" action="basket.php#receipt">
            <input type="hidden" name="action"   value="save_plan"/>
            <input type="hidden" name="store_id" value="<?= $sid ?>"/>
            <button type="submit" class="sc-save-btn">
              🧾 Save Shopping Plan for <?= htmlspecialchars($st['store']['name']) ?>
            </button>
          </form>
        </div>
        <?php $rank++; endforeach; ?>

      </div>
      <?php endif; ?>

    </div>

    <!-- RIGHT SIDEBAR -->
    <div class="basket-sidebar">

      <!-- SUMMARY -->
      <div class="summary-card">
        <div class="sc-label">Best Possible Total</div>
        <div class="sc-amount"><?= formatPrice($basketTotal) ?></div>
        <div class="sc-sml">Shopping cheapest store per item</div>
        <?php if ($bestStore && $worstTotal > $bestStore['total']): ?>
        <div style="background:rgba(255,255,255,.1);border-radius:var(--rs);padding:10px;text-align:center;font-size:.8rem;color:var(--gold);font-weight:700;margin-bottom:12px">
          🎉 Save UGX <?= number_format($worstTotal-$bestStore['total']) ?><br>
          <span style="font-size:.72rem;opacity:.8">at <?= htmlspecialchars($bestStore['store']['name']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($userBudget > 0):
          $pct = min(100,round(($basketTotal/$userBudget)*100));
          $c   = $pct>90?'var(--red)':($pct>70?'var(--gold)':'var(--mint)');
        ?>
        <div style="font-size:.72rem;color:rgba(255,255,255,.5);margin-bottom:5px"><?= $pct ?>% of monthly budget</div>
        <div style="height:5px;background:rgba(255,255,255,.15);border-radius:99px;overflow:hidden">
          <div style="height:100%;width:<?= $pct ?>%;background:<?= $c ?>;border-radius:99px"></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- STORE RANKING -->
      <div class="rank-card">
        <div class="card-title">🏆 Store Rankings</div>
        <div style="font-size:.74rem;color:var(--muted);margin-bottom:10px">Tap a store to jump to its tab</div>
        <?php $rank=1; foreach ($storeTotals as $sid => $st):
          $isBest = $rank===1;
          $extra  = $worstTotal - $st['total'];
        ?>
        <div class="rank-row" onclick="switchTab(<?= $sid ?>, null); document.getElementById('store-panel').scrollIntoView({behavior:'smooth'})">
          <div style="display:flex;align-items:center;gap:10px">
            <div class="rank-num"><?= $rank ?></div>
            <div>
              <div style="font-weight:700;font-size:.86rem"><?= htmlspecialchars($st['store']['name']) ?></div>
              <?php if (!$st['has_all']): ?>
              <div style="font-size:.66rem;color:var(--red)"><?= $st['missing'] ?> item<?= $st['missing']>1?'s':'' ?> unavailable</div>
              <?php endif; ?>
            </div>
          </div>
          <div style="text-align:right">
            <div style="font-family:'Nunito',sans-serif;font-weight:900;color:<?= $isBest?'var(--leaf)':'var(--ink)' ?>;font-size:.9rem"><?= formatPrice($st['total']) ?></div>
            <?php if (!$isBest && $extra>0): ?>
            <div style="font-size:.66rem;color:var(--red)">+<?= formatPrice($worstTotal-$st['total']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php $rank++; endforeach; ?>
      </div>

      <!-- SMART ROUTE BUTTON -->
      <a href="route.php" style="display:flex;align-items:center;justify-content:center;gap:10px;background:linear-gradient(135deg,var(--forest),#2a5e40);color:#fff;border-radius:var(--r);padding:16px;text-decoration:none;font-family:'Nunito',sans-serif;font-weight:900;font-size:.9rem;transition:all .2s;border:1.5px solid rgba(255,255,255,.1)"
         onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
        <span style="font-size:1.3rem">🗺️</span>
        <div>
          <div>Get Smart Shopping Route</div>
          <div style="font-size:.72rem;color:rgba(255,255,255,.6);font-weight:600">Map your cheapest path around Mbarara</div>
        </div>
      </a>

    </div>
  </div>
  <?php endif; ?>
</div>

<script>
// Switch tab WITHOUT page reload and WITHOUT scrolling to top
function switchTab(sid, clickedBtn) {
  // Hide all content panels
  document.querySelectorAll('.sp-content').forEach(c => c.classList.remove('active'));
  document.querySelectorAll('.sp-tab').forEach(b => b.classList.remove('active'));

  // Show selected
  const content = document.getElementById('tab-' + sid);
  if (content) content.classList.add('active');

  // Activate button
  if (clickedBtn) {
    clickedBtn.classList.add('active');
  } else {
    // Called from sidebar rank card — find the right tab btn
    const btn = document.querySelector(`.sp-tab[data-sid="${sid}"]`);
    if (btn) btn.classList.add('active');
  }

  // Scroll the tab into view within the tab bar (horizontal scroll)
  const activeTab = document.querySelector('.sp-tab.active');
  if (activeTab) {
    activeTab.scrollIntoView({ behavior:'smooth', block:'nearest', inline:'center' });
  }

  // NO page scroll — user stays exactly where they are
}
</script>

<?php include '../includes/footer.php'; ?>