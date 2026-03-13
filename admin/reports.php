<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireAdmin();
$pageTitle = 'Reports';

// ── WEEKLY PRICE AUDIT DATA ───────────────────────────────────
$weekChanges = $conn->query("
    SELECT ph.*, p.name AS product_name, p.unit,
           s.name AS store_name
    FROM price_history ph
    JOIN products p ON ph.product_id = p.id
    JOIN stores   s ON ph.store_id   = s.id
    WHERE ph.changed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY ABS(ph.change_pct) DESC
")->fetch_all(MYSQLI_ASSOC);

// ── CONSUMER SAVINGS ─────────────────────────────────────────
$savings = $conn->query("
    SELECT u.fullname, u.email,
           COUNT(b.id) AS basket_items,
           u.monthly_budget
    FROM users u
    LEFT JOIN basket b ON b.user_id = u.id
    WHERE u.role = 'user' AND u.is_active = 1
    GROUP BY u.id
    ORDER BY basket_items DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

// ── MOST EXPENSIVE PRICE DIFFERENCES ─────────────────────────
$priceDiffs = $conn->query("
    SELECT p.name, p.unit,
           MIN(pr.price) AS min_p, MAX(pr.price) AS max_p,
           (MAX(pr.price) - MIN(pr.price)) AS diff
    FROM prices pr
    JOIN products p ON pr.product_id = p.id
    GROUP BY p.id
    ORDER BY diff DESC
    LIMIT 15
")->fetch_all(MYSQLI_ASSOC);

// ── STORE SUMMARY ────────────────────────────────────────────
$storeSummary = $conn->query("
    SELECT s.name, s.tier,
           COUNT(pr.id) AS price_count,
           AVG(pr.price) AS avg_price,
           MIN(pr.price) AS min_price,
           MAX(pr.price) AS max_price
    FROM stores s
    JOIN prices pr ON pr.store_id = s.id
    GROUP BY s.id
    ORDER BY avg_price ASC
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';

// ── HANDLE DOWNLOAD ──────────────────────────────────────────
if (isset($_GET['download'])) {
    $type = $_GET['download'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=specs_' . $type . '_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');

    if ($type === 'price_audit') {
        fputcsv($out, ['Product','Unit','Store','Old Price','New Price','Change %','Date']);
        foreach ($weekChanges as $r) {
            fputcsv($out, [$r['product_name'],$r['unit'],$r['store_name'],$r['old_price'],$r['new_price'],$r['change_pct'],$r['changed_at']]);
        }
    } elseif ($type === 'price_diff') {
        fputcsv($out, ['Product','Unit','Cheapest (UGX)','Most Expensive (UGX)','Difference (UGX)']);
        foreach ($priceDiffs as $r) {
            fputcsv($out, [$r['name'],$r['unit'],$r['min_p'],$r['max_p'],$r['diff']]);
        }
    } elseif ($type === 'users') {
        fputcsv($out, ['Name','Email','Basket Items','Monthly Budget']);
        foreach ($savings as $r) {
            fputcsv($out, [$r['fullname'],$r['email'],$r['basket_items'],$r['monthly_budget']]);
        }
    }
    fclose($out);
    exit();
}
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto">
    <h1>📋 Reports</h1>
    <p>Download weekly audits and consumer savings reports</p>
  </div>
</div>

<div class="ctr">
  <!-- DOWNLOAD BUTTONS -->
  <div class="card" style="margin-bottom:24px">
    <div class="card-title">⬇️ Download Reports (CSV)</div>
    <div style="display:flex;gap:12px;flex-wrap:wrap">
      <a href="reports.php?download=price_audit" class="btn btn-primary">📊 Weekly Price Audit</a>
      <a href="reports.php?download=price_diff"  class="btn btn-green">💰 Price Differences</a>
      <a href="reports.php?download=users"       class="btn btn-green">👥 User Report</a>
    </div>
  </div>

  <!-- STORE SUMMARY -->
  <div class="card" style="margin-bottom:24px">
    <div class="card-title">🏬 Store Price Summary</div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Store</th><th>Tier</th><th>Products</th><th>Avg Price</th><th>Cheapest</th><th>Most Expensive</th></tr></thead>
        <tbody>
          <?php foreach ($storeSummary as $s): ?>
          <tr>
            <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
            <td><span class="badge badge-blue"><?= $s['tier'] ?></span></td>
            <td><?= $s['price_count'] ?></td>
            <td><?= formatPrice(round($s['avg_price'])) ?></td>
            <td class="price-best"><?= formatPrice($s['min_price']) ?></td>
            <td class="price-high"><?= formatPrice($s['max_price']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- BIGGEST PRICE DIFFERENCES -->
  <div class="card" style="margin-bottom:24px">
    <div class="card-title">🔥 Biggest Price Differences Across Stores</div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Product</th><th>Unit</th><th>Cheapest</th><th>Most Expensive</th><th>Difference</th></tr></thead>
        <tbody>
          <?php foreach ($priceDiffs as $d): ?>
          <tr>
            <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
            <td><?= htmlspecialchars($d['unit']) ?></td>
            <td class="price-best"><?= formatPrice($d['min_p']) ?></td>
            <td class="price-high"><?= formatPrice($d['max_p']) ?></td>
            <td><span class="badge badge-yellow">UGX <?= number_format($d['diff']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- WEEKLY PRICE CHANGES -->
  <div class="card">
    <div class="card-title">🕐 Price Changes This Week (<?= count($weekChanges) ?> changes)</div>
    <?php if (empty($weekChanges)): ?>
      <div class="empty-state"><div class="ei">📊</div><p>No price changes this week</p></div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Product</th><th>Store</th><th>Old Price</th><th>New Price</th><th>Change</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($weekChanges as $ch):
            $up = $ch['change_pct'] > 0;
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($ch['product_name']) ?></strong> <span style="color:var(--muted);font-size:.78rem">(<?= $ch['unit'] ?>)</span></td>
            <td><?= htmlspecialchars($ch['store_name']) ?></td>
            <td style="color:var(--muted)"><?= formatPrice($ch['old_price']) ?></td>
            <td><strong><?= formatPrice($ch['new_price']) ?></strong></td>
            <td><span class="badge <?= $up?'badge-red':'badge-green' ?>"><?= $up?'▲':'▼' ?> <?= abs($ch['change_pct']) ?>%</span></td>
            <td style="font-size:.78rem;color:var(--muted)"><?= date('d M Y', strtotime($ch['changed_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
