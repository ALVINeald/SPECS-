<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'Price Alerts';
$uid       = (int)$_SESSION['user_id'];

// ── HANDLE ACTIONS ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $pid      = (int)$_POST['product_id'];
        $sid      = $_POST['store_id'] ? (int)$_POST['store_id'] : 'NULL';
        $target   = (int)$_POST['target_price'];
        $sidVal   = is_numeric($sid) ? $sid : 'NULL';

        if ($pid && $target > 0) {
            // Check if alert already exists
            $exists = $conn->query("SELECT id FROM alerts WHERE user_id=$uid AND product_id=$pid AND is_active=1")->num_rows;
            if ($exists) {
                setFlash('error', 'You already have an active alert for this product.');
            } else {
                $conn->query("INSERT INTO alerts (user_id, product_id, store_id, target_price) VALUES ($uid, $pid, $sidVal, $target)");
                setFlash('success', 'Price alert set! We will notify you when the price drops.');
            }
        } else {
            setFlash('error', 'Please select a product and set a target price.');
        }
        redirect('alerts.php');
    }

    if ($action === 'delete') {
        $aid = (int)$_POST['alert_id'];
        $conn->query("UPDATE alerts SET is_active=0 WHERE id=$aid AND user_id=$uid");
        setFlash('success', 'Alert removed.');
        redirect('alerts.php');
    }
}

// ── GET DATA ──────────────────────────────────────────────────
$alerts = $conn->query("
    SELECT a.*, p.name AS product_name, p.unit,
           s.name AS store_name,
           (SELECT MIN(pr.price) FROM prices pr WHERE pr.product_id=a.product_id) AS current_best,
           (SELECT s2.name FROM prices pr2 JOIN stores s2 ON pr2.store_id=s2.id WHERE pr2.product_id=a.product_id ORDER BY pr2.price ASC LIMIT 1) AS best_store
    FROM alerts a
    JOIN products p ON a.product_id = p.id
    LEFT JOIN stores s ON a.store_id = s.id
    WHERE a.user_id = $uid AND a.is_active = 1
    ORDER BY a.is_triggered DESC, a.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$products = $conn->query("SELECT id, name, unit FROM products WHERE active=1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$stores   = getStores($conn);

include '../includes/header.php';
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <h1>🔔 Price Alerts</h1>
      <p><?= count($alerts) ?> active alert<?= count($alerts)!=1?'s':'' ?></p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('addAlertModal').style.display='flex'">
      ➕ Set New Alert
    </button>
  </div>
</div>

<div class="ctr">
  <?php showFlash(); ?>

  <!-- HOW IT WORKS -->
  <?php if (empty($alerts)): ?>
  <div style="background:var(--cream);border:1.5px solid var(--sand);border-radius:var(--r);padding:22px;margin-bottom:22px;display:flex;align-items:flex-start;gap:16px">
    <div style="font-size:2rem">🔔</div>
    <div>
      <div style="font-weight:800;font-size:.95rem;margin-bottom:5px">How Price Alerts Work</div>
      <div style="font-size:.84rem;color:var(--muted);line-height:1.6">
        Set a target price for any product. When the price drops to or below your target, SPECS will flag it for you here.
        Great for tracking expensive items like cooking oil, rice or household goods.
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ALERTS LIST -->
  <?php if (empty($alerts)): ?>
    <div class="empty-state card">
      <div class="ei">🔔</div>
      <p>No alerts set yet. Click <strong>Set New Alert</strong> to get started!</p>
    </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
    <?php foreach ($alerts as $a):
      $met      = $a['current_best'] && $a['current_best'] <= $a['target_price'];
      $diff     = $a['current_best'] ? $a['current_best'] - $a['target_price'] : null;
      $progress = $a['current_best'] && $a['target_price'] ? min(100, round(($a['target_price'] / $a['current_best']) * 100)) : 0;
    ?>
    <div style="background:var(--white);border-radius:var(--r);border:1.5px solid <?= $a['is_triggered']?'#a3d4b5':($met?'var(--gold)':'var(--sand)') ?>;padding:20px">
      <!-- Status badge -->
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
        <div>
          <div style="font-weight:800;font-size:.94rem"><?= htmlspecialchars($a['product_name']) ?></div>
          <div style="font-size:.76rem;color:var(--muted)"><?= htmlspecialchars($a['unit']) ?></div>
        </div>
        <?php if ($a['is_triggered']): ?>
          <span class="badge badge-green">✅ Triggered!</span>
        <?php elseif ($met): ?>
          <span class="badge badge-yellow">⚡ Target Met!</span>
        <?php else: ?>
          <span class="badge badge-blue">👀 Watching</span>
        <?php endif; ?>
      </div>

      <!-- Store -->
      <div style="font-size:.78rem;color:var(--muted);margin-bottom:12px">
        📍 <?= $a['store_name'] ? htmlspecialchars($a['store_name']) : 'Any store' ?>
      </div>

      <!-- Price info -->
      <div style="display:flex;justify-content:space-between;margin-bottom:12px">
        <div>
          <div style="font-size:.68rem;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:2px">Your Target</div>
          <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.15rem;color:var(--gold)"><?= formatPrice($a['target_price']) ?></div>
        </div>
        <div style="text-align:right">
          <div style="font-size:.68rem;color:var(--muted);font-weight:700;text-transform:uppercase;margin-bottom:2px">Current Best</div>
          <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.15rem;color:<?= $met?'var(--leaf)':'var(--ink)' ?>"><?= $a['current_best'] ? formatPrice($a['current_best']) : '—' ?></div>
          <?php if ($a['best_store']): ?>
          <div style="font-size:.7rem;color:var(--muted)"><?= htmlspecialchars($a['best_store']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Progress bar -->
      <?php if ($a['current_best'] && !$met): ?>
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--muted);margin-bottom:3px">
          <span>Progress to target</span>
          <span><?= $progress ?>%</span>
        </div>
        <div style="height:5px;background:var(--sand);border-radius:99px;overflow:hidden">
          <div style="height:100%;width:<?= $progress ?>%;background:var(--gold);border-radius:99px"></div>
        </div>
        <div style="font-size:.72rem;color:var(--red);margin-top:3px">Still <?= formatPrice($diff) ?> above your target</div>
      </div>
      <?php endif; ?>

      <!-- Set when -->
      <div style="font-size:.72rem;color:var(--muted);margin-bottom:12px">Set <?= timeAgo($a['created_at']) ?></div>

      <!-- Remove -->
      <form method="POST">
        <input type="hidden" name="action"   value="delete"/>
        <input type="hidden" name="alert_id" value="<?= $a['id'] ?>"/>
        <button type="submit" class="btn btn-sm btn-red" style="width:100%;justify-content:center">🗑️ Remove Alert</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ADD ALERT MODAL -->
<div id="addAlertModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--white);border-radius:var(--r);padding:28px;width:100%;max-width:440px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="font-family:'Nunito',sans-serif;font-weight:900">🔔 Set Price Alert</h3>
      <button onclick="document.getElementById('addAlertModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add"/>
      <div class="fgrp">
        <label class="flabel">Product *</label>
        <select name="product_id" id="alertProduct" class="finput" required onchange="suggestPrice(this)">
          <option value="">Select a product...</option>
          <?php foreach ($products as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['unit'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fgrp">
        <label class="flabel">Store (optional)</label>
        <select name="store_id" class="finput">
          <option value="">Any store</option>
          <?php foreach ($stores as $s): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fgrp">
        <label class="flabel">Target Price (UGX) *</label>
        <input type="number" name="target_price" id="alertPrice" class="finput" placeholder="e.g. 4000" required/>
        <div style="font-size:.74rem;color:var(--muted);margin-top:4px" id="alertPriceHint">You will be notified when this product drops to this price or below.</div>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" style="flex:1">Set Alert</button>
        <button type="button" class="btn" style="background:var(--sand)" onclick="document.getElementById('addAlertModal').style.display='none'">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function suggestPrice(sel) {
  const pid = sel.value;
  if (!pid) return;
  fetch('../api/get_prices.php?product_id=' + pid)
    .then(r => r.json())
    .then(data => {
      if (data.length) {
        const min = Math.min(...data.map(d => d.price));
        document.getElementById('alertPrice').placeholder = 'Current best: UGX ' + min.toLocaleString();
        document.getElementById('alertPriceHint').textContent =
          'Current best price is UGX ' + min.toLocaleString() + '. Set a lower target to be notified when it drops.';
      }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
