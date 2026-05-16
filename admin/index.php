<?php
// ============================================================
//  SPECS – Admin Dashboard
//  File: admin/index.php
// ============================================================
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireAdmin();

$pageTitle = 'Admin Dashboard';

// ── STATS ────────────────────────────────────────────────────
$totalProducts  = $conn->query("SELECT COUNT(*) AS t FROM products WHERE active=1")->fetch_assoc()['t'];
$totalUsers     = $conn->query("SELECT COUNT(*) AS t FROM users WHERE role='user'")->fetch_assoc()['t'];
$totalStores    = $conn->query("SELECT COUNT(*) AS t FROM stores WHERE active=1")->fetch_assoc()['t'];
$totalAlerts    = $conn->query("SELECT COUNT(*) AS t FROM alerts WHERE is_active=1")->fetch_assoc()['t'];
$totalPrices    = $conn->query("SELECT COUNT(*) AS t FROM prices")->fetch_assoc()['t'];
$triggeredAlerts= $conn->query("SELECT COUNT(*) AS t FROM alerts WHERE is_triggered=1 AND is_active=1")->fetch_assoc()['t'];

// ── RECENT PRICE CHANGES ─────────────────────────────────────
$recentChanges = $conn->query("
    SELECT ph.*, p.name AS product_name, s.name AS store_name,
           u.fullname AS changed_by_name
    FROM price_history ph
    JOIN products p ON ph.product_id = p.id
    JOIN stores s   ON ph.store_id   = s.id
    LEFT JOIN users u ON ph.changed_by = u.id
    ORDER BY ph.changed_at DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// ── RECENT USERS ─────────────────────────────────────────────
$recentUsers = $conn->query("
    SELECT id, fullname, email, role, created_at, last_login
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// ── MOST ALERTED PRODUCTS ────────────────────────────────────
$topAlerts = $conn->query("
    SELECT p.name, COUNT(a.id) AS alert_count
    FROM alerts a
    JOIN products p ON a.product_id = p.id
    WHERE a.is_active = 1
    GROUP BY p.id
    ORDER BY alert_count DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto">
    <h1>📊 Admin Dashboard</h1>
    <p>Welcome back, <?= htmlspecialchars($_SESSION['fullname']) ?>! Here's what's happening on SPECS.</p>
  </div>
</div>

<div class="ctr">
  <?php showFlash(); ?>

  <!-- STATS CARDS -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:28px">
    <?php
    $stats = [
      ['🛒', 'Products',       $totalProducts,   'admin/products.php', 'var(--forest)'],
      ['👥', 'Users',          $totalUsers,      'admin/users.php',    'var(--leaf)'],
      ['🏬', 'Stores',         $totalStores,     'admin/stores.php',   '#2196F3'],
      ['🔔', 'Active Alerts',  $totalAlerts,     'admin/alerts.php',   'var(--gold)'],
      ['💰', 'Price Records',  $totalPrices,     'admin/prices.php',   '#9c27b0'],
      ['⚡', 'Triggered',      $triggeredAlerts, 'admin/alerts.php',   'var(--red)'],
    ];
    foreach ($stats as $s): ?>
    <a href="../<?= $s[3] ?>" style="text-decoration:none">
      <div style="background:var(--white);border:1.5px solid var(--sand);border-radius:var(--r);padding:20px;transition:all .2s;cursor:pointer"
           onmouseover="this.style.borderColor='<?= $s[4] ?>'" onmouseout="this.style.borderColor='var(--sand)'">
        <div style="font-size:1.5rem;margin-bottom:8px"><?= $s[0] ?></div>
        <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.7rem;color:<?= $s[4] ?>"><?= number_format($s[2]) ?></div>
        <div style="font-size:.76rem;color:var(--muted);font-weight:600;margin-top:2px"><?= $s[1] ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- QUICK ACTIONS -->
  <div class="card" style="margin-bottom:24px">
    <div class="card-title">⚡ Quick Actions</div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="products.php" class="btn btn-green">➕ Add Product</a>
      <a href="prices.php"   class="btn btn-primary">💰 Update Prices</a>
      <a href="stores.php"   class="btn btn-green">🏬 Manage Stores</a>
      <a href="users.php"    class="btn btn-green">👥 View Users</a>
      <a href="reports.php"  class="btn btn-primary">📋 Download Report</a>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">

    <!-- RECENT PRICE CHANGES -->
    <div class="card">
      <div class="card-title">🕐 Recent Price Changes</div>
      <?php if (empty($recentChanges)): ?>
        <div class="empty-state"><div class="ei">💰</div><p>No price changes yet</p></div>
      <?php else: ?>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th>Product</th>
              <th>Store</th>
              <th>Old Price</th>
              <th>New Price</th>
              <th>Change</th>
              <th>When</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentChanges as $ch): 
              $pct = $ch['change_pct'];
              $up  = $pct > 0;
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($ch['product_name']) ?></strong></td>
              <td><?= htmlspecialchars($ch['store_name']) ?></td>
              <td style="color:var(--muted)"><?= formatPrice($ch['old_price']) ?></td>
              <td><strong><?= formatPrice($ch['new_price']) ?></strong></td>
              <td>
                <span class="badge <?= $up ? 'badge-red' : 'badge-green' ?>">
                  <?= $up ? '▲' : '▼' ?> <?= abs($pct) ?>%
                </span>
              </td>
              <td style="color:var(--muted);font-size:.78rem"><?= timeAgo($ch['changed_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- RIGHT COLUMN -->
    <div style="display:flex;flex-direction:column;gap:18px">

      <!-- TOP ALERTED PRODUCTS -->
      <div class="card">
        <div class="card-title">🔔 Most Watched Products</div>
        <?php if (empty($topAlerts)): ?>
          <div class="empty-state" style="padding:20px"><div class="ei">🔔</div><p>No alerts yet</p></div>
        <?php else: ?>
          <?php foreach ($topAlerts as $ta): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--sand)">
            <span style="font-size:.85rem;font-weight:600"><?= htmlspecialchars($ta['name']) ?></span>
            <span class="badge badge-yellow"><?= $ta['alert_count'] ?> alerts</span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- RECENT USERS -->
      <div class="card">
        <div class="card-title">👥 Recent Users</div>
        <?php foreach ($recentUsers as $u): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--sand)">
          <div style="width:32px;height:32px;border-radius:50%;background:var(--mint);display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;font-size:.85rem;flex-shrink:0">
            <?= strtoupper(substr($u['fullname'],0,1)) ?>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:.84rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($u['fullname']) ?></div>
            <div style="font-size:.72rem;color:var(--muted)"><?= htmlspecialchars($u['email']) ?></div>
          </div>
          <span class="badge <?= $u['role']==='admin'?'badge-red':'badge-green' ?>"><?= $u['role'] ?></span>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:12px">
          <a href="users.php" class="btn btn-sm btn-green">View All Users</a>
        </div>
      </div>

    </div>
  </div>

  <!-- ADMIN LOG -->
  <?php
  $logs = $conn->query("
      SELECT al.*, u.fullname AS admin_name
      FROM admin_logs al
      JOIN users u ON al.admin_id = u.id
      ORDER BY al.created_at DESC
      LIMIT 6
  ")->fetch_all(MYSQLI_ASSOC);
  ?>
  <?php if (!empty($logs)): ?>
  <div class="card">
    <div class="card-title">📋 Admin Activity Log</div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>Admin</th><th>Action</th><th>Details</th><th>Time</th></tr></thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td><strong><?= htmlspecialchars($log['admin_name']) ?></strong></td>
            <td><span class="badge badge-blue"><?= htmlspecialchars($log['action']) ?></span></td>
            <td style="font-size:.82rem;color:var(--muted)"><?= htmlspecialchars($log['details'] ?? '—') ?></td>
            <td style="font-size:.78rem;color:var(--muted)"><?= timeAgo($log['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>
