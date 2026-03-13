<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireAdmin();
$pageTitle = 'Price Alerts';

$alerts = $conn->query("
    SELECT a.*, u.fullname AS user_name, u.email AS user_email,
           p.name AS product_name, p.unit,
           s.name AS store_name,
           (SELECT pr.price FROM prices pr WHERE pr.product_id=a.product_id AND (pr.store_id=a.store_id OR a.store_id IS NULL) ORDER BY pr.price ASC LIMIT 1) AS current_price
    FROM alerts a
    JOIN users u    ON a.user_id    = u.id
    JOIN products p ON a.product_id = p.id
    LEFT JOIN stores s ON a.store_id = s.id
    ORDER BY a.is_triggered DESC, a.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto">
    <h1>🔔 Price Alerts</h1>
    <p><?= count($alerts) ?> total alerts</p>
  </div>
</div>

<div class="ctr">
  <div class="card">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr><th>User</th><th>Product</th><th>Store</th><th>Target Price</th><th>Current Price</th><th>Status</th><th>Created</th></tr>
        </thead>
        <tbody>
          <?php foreach ($alerts as $a):
            $met = $a['current_price'] && $a['current_price'] <= $a['target_price'];
          ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($a['user_name']) ?></strong>
              <div style="font-size:.73rem;color:var(--muted)"><?= htmlspecialchars($a['user_email']) ?></div>
            </td>
            <td><?= htmlspecialchars($a['product_name']) ?> <span style="color:var(--muted);font-size:.78rem">(<?= $a['unit'] ?>)</span></td>
            <td><?= $a['store_name'] ? htmlspecialchars($a['store_name']) : '<em style="color:var(--muted)">Any store</em>' ?></td>
            <td><strong><?= formatPrice($a['target_price']) ?></strong></td>
            <td><?= $a['current_price'] ? formatPrice($a['current_price']) : '—' ?></td>
            <td>
              <?php if ($a['is_triggered']): ?>
                <span class="badge badge-green">✅ Triggered</span>
              <?php elseif ($met): ?>
                <span class="badge badge-yellow">⚡ Met!</span>
              <?php elseif ($a['is_active']): ?>
                <span class="badge badge-blue">👀 Watching</span>
              <?php else: ?>
                <span class="badge" style="background:#eee">Inactive</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.78rem;color:var(--muted)"><?= timeAgo($a['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
