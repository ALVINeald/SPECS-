<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'My Basket';
$uid       = (int)$_SESSION['user_id'];

// ── HANDLE ACTIONS ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_qty') {
        $pid = (int)$_POST['product_id'];
        $qty = (int)$_POST['quantity'];
        if ($qty <= 0) {
            $conn->query("DELETE FROM basket WHERE user_id=$uid AND product_id=$pid");
        } else {
            $conn->query("UPDATE basket SET quantity=$qty WHERE user_id=$uid AND product_id=$pid");
        }
        redirect('basket.php');
    }

    if ($action === 'remove') {
        $pid = (int)$_POST['product_id'];
        $conn->query("DELETE FROM basket WHERE user_id=$uid AND product_id=$pid");
        setFlash('success', 'Item removed from basket.');
        redirect('basket.php');
    }

    if ($action === 'clear') {
        $conn->query("DELETE FROM basket WHERE user_id=$uid");
        setFlash('success', 'Basket cleared.');
        redirect('basket.php');
    }

    if ($action === 'save_plan') {
        $store_id = (int)$_POST['store_id'];
        // Build items snapshot
        $items = $conn->query("
            SELECT b.quantity, p.name, p.unit,
                   COALESCE((SELECT pr.price FROM prices pr WHERE pr.product_id=b.product_id AND pr.store_id=$store_id LIMIT 1),
                            (SELECT MIN(pr2.price) FROM prices pr2 WHERE pr2.product_id=b.product_id)) AS price
            FROM basket b
            JOIN products p ON b.product_id = p.id
            WHERE b.user_id = $uid
        ")->fetch_all(MYSQLI_ASSOC);

        $total    = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
        $ref      = generatePlanRef($conn);
        $json     = $conn->real_escape_string(json_encode($items));

        // Calculate savings vs most expensive store
        $maxTotal = $conn->query("
            SELECT SUM(max_p * b.quantity) AS t FROM basket b
            JOIN (SELECT product_id, MAX(price) AS max_p FROM prices GROUP BY product_id) mp
            ON mp.product_id = b.product_id
            WHERE b.user_id = $uid
        ")->fetch_assoc()['t'] ?? $total;
        $savings = max(0, $maxTotal - $total);

        $conn->query("
            INSERT INTO store_plans (user_id, store_id, plan_ref, items_json, total_amount, savings)
            VALUES ($uid, $store_id, '$ref', '$json', $total, $savings)
        ");

        setFlash('success', "Shopping plan $ref saved! Download it below.");
        redirect('basket.php?plan=' . $ref);
    }
}

// ── GET BASKET ITEMS ──────────────────────────────────────────
$basketItems = $conn->query("
    SELECT b.*, p.name AS product_name, p.unit, c.name AS cat_name
    FROM basket b
    JOIN products p  ON b.product_id  = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE b.user_id = $uid
    ORDER BY c.name, p.name
")->fetch_all(MYSQLI_ASSOC);

// ── GET PRICES FOR ALL BASKET ITEMS ──────────────────────────
$stores = getStores($conn);
$storeTotals = [];
$allPrices   = [];

if (!empty($basketItems)) {
    $pids = implode(',', array_column($basketItems, 'product_id'));
    $priceRows = $conn->query("SELECT product_id, store_id, price FROM prices WHERE product_id IN ($pids)")->fetch_all(MYSQLI_ASSOC);
    foreach ($priceRows as $pr) {
        $allPrices[$pr['product_id']][$pr['store_id']] = $pr['price'];
    }

    // Calculate total per store
    foreach ($stores as $s) {
        $total = 0;
        foreach ($basketItems as $item) {
            $p = $allPrices[$item['product_id']][$s['id']] ?? null;
            if ($p) $total += $p * $item['quantity'];
        }
        if ($total > 0) $storeTotals[$s['id']] = ['store' => $s, 'total' => $total];
    }
    asort($storeTotals); // Sort by cheapest
}

$bestStore  = !empty($storeTotals) ? reset($storeTotals) : null;
$worstTotal = !empty($storeTotals) ? max(array_column($storeTotals, 'total')) : 0;
$userBudget = (int)($_SESSION['monthly_budget'] ?? 0);

// Show saved plan if redirected
$savedPlan = null;
if (isset($_GET['plan'])) {
    $ref = $conn->real_escape_string($_GET['plan']);
    $savedPlan = $conn->query("
        SELECT sp.*, s.name AS store_name
        FROM store_plans sp
        JOIN stores s ON sp.store_id = s.id
        WHERE sp.plan_ref = '$ref' AND sp.user_id = $uid
        LIMIT 1
    ")->fetch_assoc();
}

include '../includes/header.php';
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <h1>🛒 My Basket</h1>
      <p><?= count($basketItems) ?> items in your basket</p>
    </div>
    <?php if (!empty($basketItems)): ?>
    <form method="POST" onsubmit="return confirm('Clear all items from basket?')">
      <input type="hidden" name="action" value="clear"/>
      <button type="submit" class="btn btn-red btn-sm">🗑️ Clear Basket</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="ctr">
  <?php showFlash(); ?>

  <!-- SAVED PLAN RECEIPT -->
  <?php if ($savedPlan): ?>
  <div style="background:var(--forest);color:#fff;border-radius:var(--r);padding:28px;margin-bottom:24px;font-family:'Nunito',sans-serif" id="receiptBox">
    <div style="text-align:center;border-bottom:2px dashed rgba(255,255,255,.3);padding-bottom:18px;margin-bottom:18px">
      <div style="font-size:1.5rem;font-weight:900;color:var(--gold)">SPECS</div>
      <div style="font-size:.72rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.1em">Mbarara City Shopping Plan</div>
      <div style="font-size:.8rem;color:rgba(255,255,255,.6);margin-top:4px"><?= $savedPlan['plan_ref'] ?></div>
      <div style="font-size:.76rem;color:rgba(255,255,255,.45)"><?= date('d M Y, H:i', strtotime($savedPlan['created_at'])) ?></div>
    </div>
    <div style="margin-bottom:6px;font-size:.82rem;color:rgba(255,255,255,.6)">🏬 Store: <strong style="color:#fff"><?= htmlspecialchars($savedPlan['store_name']) ?></strong></div>
    <?php
    $planItems = json_decode($savedPlan['items_json'], true);
    foreach ($planItems as $pi): ?>
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.1);font-size:.84rem">
      <span><?= htmlspecialchars($pi['name']) ?> (<?= $pi['unit'] ?>) × <?= $pi['quantity'] ?></span>
      <span style="font-weight:700">UGX <?= number_format($pi['price'] * $pi['quantity']) ?></span>
    </div>
    <?php endforeach; ?>
    <div style="display:flex;justify-content:space-between;padding:14px 0 6px;font-size:1rem;font-weight:900">
      <span>TOTAL</span>
      <span style="color:var(--gold)">UGX <?= number_format($savedPlan['total_amount']) ?></span>
    </div>
    <?php if ($savedPlan['savings'] > 0): ?>
    <div style="background:rgba(233,168,32,.15);border-radius:var(--rs);padding:10px;text-align:center;font-size:.82rem;color:var(--gold);font-weight:700">
      🎉 You save UGX <?= number_format($savedPlan['savings']) ?> vs most expensive store!
    </div>
    <?php endif; ?>
    <div style="margin-top:16px;text-align:center">
      <button onclick="printReceipt()" class="btn btn-sm" style="background:var(--gold);color:var(--forest)">🖨️ Print / Save as PDF</button>
      <button onclick="shareReceipt('<?= $savedPlan['plan_ref'] ?>')" class="btn btn-sm" style="background:rgba(255,255,255,.15);color:#fff;margin-left:8px">📤 Share</button>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($basketItems)): ?>
    <div class="empty-state card">
      <div class="ei">🛒</div>
      <p>Your basket is empty.</p>
      <a href="browse.php" class="btn btn-primary" style="margin-top:14px">Start Shopping</a>
    </div>
  <?php else: ?>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">

    <!-- BASKET ITEMS -->
    <div>
      <div class="card" style="margin-bottom:16px">
        <div class="card-title">🛒 Items</div>
        <?php foreach ($basketItems as $item):
          $bestPrice = !empty($allPrices[$item['product_id']]) ? min($allPrices[$item['product_id']]) : 0;
        ?>
        <div style="display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--sand)">
          <div style="flex:1">
            <div style="font-weight:700;font-size:.92rem"><?= htmlspecialchars($item['product_name']) ?></div>
            <div style="font-size:.76rem;color:var(--muted)"><?= htmlspecialchars($item['unit']) ?> · <?= htmlspecialchars($item['cat_name']) ?></div>
            <div style="font-size:.8rem;color:var(--leaf);font-weight:700;margin-top:2px">Best: <?= formatPrice($bestPrice) ?> each</div>
          </div>
          <!-- Quantity controls -->
          <form method="POST" style="display:flex;align-items:center;gap:6px">
            <input type="hidden" name="action"     value="update_qty"/>
            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>"/>
            <button type="submit" name="quantity" value="<?= $item['quantity']-1 ?>"
                    class="btn btn-sm" style="background:var(--sand);width:28px;height:28px;padding:0;display:flex;align-items:center;justify-content:center;font-size:1rem">−</button>
            <span style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1rem;min-width:24px;text-align:center"><?= $item['quantity'] ?></span>
            <button type="submit" name="quantity" value="<?= $item['quantity']+1 ?>"
                    class="btn btn-sm" style="background:var(--sand);width:28px;height:28px;padding:0;display:flex;align-items:center;justify-content:center;font-size:1rem">+</button>
          </form>
          <div style="text-align:right;min-width:90px">
            <div style="font-family:'Nunito',sans-serif;font-weight:900;color:var(--forest)"><?= formatPrice($bestPrice * $item['quantity']) ?></div>
          </div>
          <form method="POST">
            <input type="hidden" name="action"     value="remove"/>
            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>"/>
            <button type="submit" class="btn btn-sm btn-red">🗑️</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- STORE COMPARISON -->
      <div class="card">
        <div class="card-title">🏬 Best Store for Your Full Basket</div>
        <?php foreach ($storeTotals as $sid => $st):
          $isBest = $bestStore && $sid == array_key_first($storeTotals);
          $saving = $worstTotal - $st['total'];
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;border-radius:var(--rs);margin-bottom:8px;background:<?= $isBest?'#f0fdf4':'var(--cream)' ?>;border:1.5px solid <?= $isBest?'#a3d4b5':'var(--sand)' ?>">
          <div>
            <strong><?= htmlspecialchars($st['store']['name']) ?></strong>
            <?php if ($isBest): ?>
              <span style="background:#d4edda;color:#155724;font-size:.66rem;font-weight:800;padding:2px 7px;border-radius:99px;margin-left:6px">BEST</span>
            <?php endif; ?>
            <div style="font-size:.74rem;color:var(--muted)"><?= ucfirst($st['store']['tier']) ?></div>
          </div>
          <div style="text-align:right">
            <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1rem;color:<?= $isBest?'var(--leaf)':'var(--ink)' ?>"><?= formatPrice($st['total']) ?></div>
            <?php if (!$isBest && $saving > 0): ?>
            <div style="font-size:.72rem;color:var(--red)">+<?= formatPrice($saving) ?> more</div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- RIGHT: SUMMARY & SAVE PLAN -->
    <div style="display:flex;flex-direction:column;gap:16px">

      <!-- SUMMARY -->
      <div class="card">
        <div class="card-title">💰 Summary</div>
        <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.8rem;color:var(--forest)">
          <?= formatPrice($bestStore ? $bestStore['total'] : 0) ?>
        </div>
        <div style="font-size:.78rem;color:var(--muted);margin-bottom:12px">Best total (<?= $bestStore ? htmlspecialchars($bestStore['store']['name']) : 'N/A' ?>)</div>

        <?php if ($worstTotal > 0 && $bestStore): 
          $totalSavings = $worstTotal - $bestStore['total'];
        ?>
        <?php if ($totalSavings > 0): ?>
        <div style="background:#f0fdf4;border-radius:var(--rs);padding:10px;margin-bottom:12px;font-size:.82rem;color:#155724;font-weight:700">
          🎉 You save <?= formatPrice($totalSavings) ?> vs most expensive store!
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($userBudget > 0 && $bestStore): 
          $pct = min(100, round(($bestStore['total'] / $userBudget) * 100));
        ?>
        <div style="font-size:.78rem;color:var(--muted);margin-bottom:5px">Budget: <?= $pct ?>% used</div>
        <div style="height:6px;background:var(--sand);border-radius:99px;overflow:hidden;margin-bottom:12px">
          <div style="height:100%;width:<?= $pct ?>%;background:<?= $pct>90?'var(--red)':'var(--leaf)' ?>;border-radius:99px"></div>
        </div>
        <?php endif; ?>

        <a href="browse.php" class="btn btn-green btn-sm" style="width:100%;justify-content:center;margin-bottom:8px">➕ Add More Items</a>
      </div>

      <!-- SAVE PLAN -->
      <?php if ($bestStore): ?>
      <div class="card">
        <div class="card-title">🧾 Save Shopping Plan</div>
        <p style="font-size:.82rem;color:var(--muted);margin-bottom:14px">Save your basket as a shareable shopping receipt — like an MTN MoMo receipt!</p>
        <form method="POST">
          <input type="hidden" name="action" value="save_plan"/>
          <div class="fgrp">
            <label class="flabel">Choose Store</label>
            <select name="store_id" class="finput">
              <?php foreach ($storeTotals as $sid => $st): ?>
              <option value="<?= $sid ?>"><?= htmlspecialchars($st['store']['name']) ?> — <?= formatPrice($st['total']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">🧾 Generate Plan</button>
        </form>
      </div>
      <?php endif; ?>

    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function printReceipt() {
  const receiptBox = document.getElementById('receiptBox');
  const w = window.open('', '_blank');
  w.document.write('<html><head><title>SPECS Shopping Plan</title><style>body{font-family:sans-serif;padding:20px;background:#18382a;color:#fff}*{box-sizing:border-box}</style></head><body>');
  w.document.write(receiptBox.innerHTML);
  w.document.write('</body></html>');
  w.document.close();
  w.print();
}

function shareReceipt(ref) {
  const text = 'My SPECS Shopping Plan ' + ref + ' — Mbarara City, Uganda';
  if (navigator.share) {
    navigator.share({ title: 'SPECS Shopping Plan', text: text });
  } else {
    navigator.clipboard.writeText(text);
    showToast('Plan reference copied to clipboard!');
  }
}
</script>

<?php include '../includes/footer.php'; ?>
