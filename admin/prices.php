<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireAdmin();
$pageTitle = 'Manage Prices';

// ── HANDLE PRICE UPDATE ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_price') {
        $pid      = (int)$_POST['product_id'];
        $sid      = (int)$_POST['store_id'];
        $newPrice = (int)$_POST['new_price'];
        $uid      = (int)$_SESSION['user_id'];

        if ($newPrice > 0) {
            // Get old price
            $old = $conn->query("SELECT price FROM prices WHERE product_id=$pid AND store_id=$sid")->fetch_assoc();
            $oldPrice = $old ? $old['price'] : 0;

            // Update price
            $conn->query("
                INSERT INTO prices (product_id, store_id, price, updated_by)
                VALUES ($pid, $sid, $newPrice, $uid)
                ON DUPLICATE KEY UPDATE price=$newPrice, updated_by=$uid, updated_at=NOW()
            ");

            // Log the change
            if ($oldPrice && $oldPrice != $newPrice) {
                $pct = round((($newPrice - $oldPrice) / $oldPrice) * 100, 2);
                $conn->query("
                    INSERT INTO price_history (product_id, store_id, old_price, new_price, change_pct, reason, changed_by)
                    VALUES ($pid, $sid, $oldPrice, $newPrice, $pct, 'Manual admin update', $uid)
                ");
                // Check alerts
                $conn->query("CALL sp_check_alerts($pid, $sid, $newPrice)");
            }

            logAdminAction($conn, 'UPDATE_PRICE', 'price', $pid, "Product #$pid @ Store #$sid: UGX $oldPrice → UGX $newPrice");
            setFlash('success', 'Price updated successfully!');
        }
        redirect('prices.php?cat=' . ($_GET['cat'] ?? '') . '&store=' . ($_GET['store'] ?? ''));
    }
}

// ── FILTERS ──────────────────────────────────────────────────
$catFilter   = isset($_GET['cat'])   ? (int)$_GET['cat']   : 0;
$storeFilter = isset($_GET['store']) ? (int)$_GET['store'] : 0;
$search      = isset($_GET['q'])     ? $conn->real_escape_string(trim($_GET['q'])) : '';

$categories = getCategories($conn);
$stores     = getStores($conn);

$where = "WHERE p.active = 1";
if ($catFilter)   $where .= " AND p.category_id = $catFilter";
if ($search)      $where .= " AND p.name LIKE '%$search%'";

$products = $conn->query("
    SELECT p.id, p.name, p.unit, c.name AS cat_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    $where
    ORDER BY c.name, p.name
    LIMIT 60
")->fetch_all(MYSQLI_ASSOC);

// Get all prices for displayed products
$allPrices = [];
if (!empty($products)) {
    $pids = implode(',', array_column($products, 'id'));
    $priceRows = $conn->query("SELECT product_id, store_id, price FROM prices WHERE product_id IN ($pids)")->fetch_all(MYSQLI_ASSOC);
    foreach ($priceRows as $pr) {
        $allPrices[$pr['product_id']][$pr['store_id']] = $pr['price'];
    }
}

include '../includes/header.php';
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto">
    <h1>💰 Prices</h1>
    <p>Update product prices for each store</p>
  </div>
</div>

<div class="ctr">
  <?php showFlash(); ?>

  <!-- FILTERS -->
  <div class="card" style="margin-bottom:20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1;min-width:180px">
        <label class="flabel">Search</label>
        <input type="text" name="q" class="finput" placeholder="Product name..." value="<?= htmlspecialchars($search) ?>"/>
      </div>
      <div style="min-width:180px">
        <label class="flabel">Category</label>
        <select name="cat" class="finput">
          <option value="0">All Categories</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $catFilter==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-green">🔍 Filter</button>
      <a href="prices.php" class="btn" style="background:var(--sand)">Clear</a>
    </form>
  </div>

  <!-- PRICE TABLE -->
  <div class="card">
    <div style="margin-bottom:14px;font-size:.82rem;color:var(--muted)">
      💡 Click any price to update it. Showing <?= count($products) ?> products.
    </div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>Product</th>
            <th>Unit</th>
            <?php foreach ($stores as $s): ?>
            <th><?= htmlspecialchars($s['short_name']) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($p['name']) ?></strong>
              <div style="font-size:.72rem;color:var(--muted)"><?= htmlspecialchars($p['cat_name']) ?></div>
            </td>
            <td style="font-size:.8rem;color:var(--muted)"><?= htmlspecialchars($p['unit']) ?></td>
            <?php foreach ($stores as $s):
              $price = $allPrices[$p['id']][$s['id']] ?? null;
              // Color by tier
              $storeMin = !empty($allPrices[$p['id']]) ? min($allPrices[$p['id']]) : 0;
              $cls = '';
              if ($price && $storeMin) {
                if ($price == $storeMin) $cls = 'style="color:#155724;font-weight:800"';
                elseif ($price > $storeMin * 1.15) $cls = 'style="color:#721c24"';
              }
            ?>
            <td <?= $cls ?>>
              <?php if ($price): ?>
                <span style="cursor:pointer;border-bottom:1px dashed var(--muted)"
                      onclick="openPriceEdit(<?= $p['id'] ?>, <?= $s['id'] ?>, '<?= addslashes($p['name']) ?>', '<?= addslashes($s['name']) ?>', <?= $price ?>)">
                  <?= number_format($price) ?>
                </span>
              <?php else: ?>
                <span style="color:var(--muted);cursor:pointer"
                      onclick="openPriceEdit(<?= $p['id'] ?>, <?= $s['id'] ?>, '<?= addslashes($p['name']) ?>', '<?= addslashes($s['name']) ?>', 0)">
                  —
                </span>
              <?php endif; ?>
            </td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- PRICE EDIT MODAL -->
<div id="priceModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--white);border-radius:var(--r);padding:28px;width:100%;max-width:380px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3 style="font-family:'Nunito',sans-serif;font-weight:900">💰 Update Price</h3>
      <button onclick="document.getElementById('priceModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer">×</button>
    </div>
    <div id="priceModalInfo" style="background:var(--cream);border-radius:var(--rs);padding:12px;margin-bottom:16px;font-size:.85rem"></div>
    <form method="POST">
      <input type="hidden" name="action"     value="update_price"/>
      <input type="hidden" name="product_id" id="pmPid"/>
      <input type="hidden" name="store_id"   id="pmSid"/>
      <div class="fgrp">
        <label class="flabel">New Price (UGX)</label>
        <input type="number" name="new_price" id="pmPrice" class="finput" placeholder="Enter new price" required/>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" style="flex:1">Update Price</button>
        <button type="button" class="btn" style="background:var(--sand)" onclick="document.getElementById('priceModal').style.display='none'">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function openPriceEdit(pid, sid, pname, sname, currentPrice) {
  document.getElementById('pmPid').value   = pid;
  document.getElementById('pmSid').value   = sid;
  document.getElementById('pmPrice').value = currentPrice || '';
  document.getElementById('priceModalInfo').innerHTML =
    '<strong>' + pname + '</strong><br>Store: ' + sname +
    (currentPrice ? '<br>Current: UGX ' + currentPrice.toLocaleString() : '<br><em>No price set yet</em>');
  document.getElementById('priceModal').style.display = 'flex';
}
</script>

<?php include '../includes/footer.php'; ?>
