<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireAdmin();
$pageTitle = 'Manage Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle_active') {
        $uid    = (int)$_POST['user_id'];
        $status = (int)$_POST['current_status'];
        $new    = $status ? 0 : 1;
        $conn->query("UPDATE users SET is_active=$new WHERE id=$uid");
        setFlash('success', 'User status updated.');
        redirect('users.php');
    }
    if ($action === 'change_role') {
        $uid  = (int)$_POST['user_id'];
        $role = $conn->real_escape_string($_POST['role']);
        $conn->query("UPDATE users SET role='$role' WHERE id=$uid");
        logAdminAction($conn, 'CHANGE_ROLE', 'user', $uid, "Changed role to $role");
        setFlash('success', 'User role updated.');
        redirect('users.php');
    }
}

$search = isset($_GET['q']) ? $conn->real_escape_string(trim($_GET['q'])) : '';
$role   = isset($_GET['role']) ? $conn->real_escape_string($_GET['role']) : '';
$where  = "WHERE 1=1";
if ($search) $where .= " AND (fullname LIKE '%$search%' OR email LIKE '%$search%')";
if ($role)   $where .= " AND role = '$role'";

$users = $conn->query("
    SELECT u.*,
      (SELECT COUNT(*) FROM basket b WHERE b.user_id=u.id) AS basket_count,
      (SELECT COUNT(*) FROM alerts a WHERE a.user_id=u.id AND a.is_active=1) AS alert_count
    FROM users u
    $where
    ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto">
    <h1>👥 Users</h1>
    <p><?= count($users) ?> users found</p>
  </div>
</div>

<div class="ctr">
  <?php showFlash(); ?>

  <div class="card" style="margin-bottom:20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1;min-width:200px">
        <label class="flabel">Search</label>
        <input type="text" name="q" class="finput" placeholder="Name or email..." value="<?= htmlspecialchars($search) ?>"/>
      </div>
      <div>
        <label class="flabel">Role</label>
        <select name="role" class="finput">
          <option value="">All Roles</option>
          <option value="user"    <?= $role=='user'   ?'selected':'' ?>>Consumer</option>
          <option value="admin"   <?= $role=='admin'  ?'selected':'' ?>>Admin</option>
          <option value="manager" <?= $role=='manager'?'selected':'' ?>>Manager</option>
        </select>
      </div>
      <button type="submit" class="btn btn-green">🔍 Search</button>
      <a href="users.php" class="btn" style="background:var(--sand)">Clear</a>
    </form>
  </div>

  <div class="card">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr><th>User</th><th>Email</th><th>Role</th><th>Budget</th><th>Basket</th><th>Alerts</th><th>Last Login</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:9px">
                <div style="width:34px;height:34px;border-radius:50%;background:var(--mint);display:flex;align-items:center;justify-content:center;font-weight:900;color:#fff;font-size:.85rem;flex-shrink:0">
                  <?= strtoupper(substr($u['fullname'],0,1)) ?>
                </div>
                <strong><?= htmlspecialchars($u['fullname']) ?></strong>
              </div>
            </td>
            <td style="font-size:.82rem"><?= htmlspecialchars($u['email']) ?></td>
            <td><span class="badge <?= $u['role']==='admin'?'badge-red':($u['role']==='manager'?'badge-yellow':'badge-green') ?>"><?= $u['role'] ?></span></td>
            <td><?= $u['monthly_budget'] ? formatPrice($u['monthly_budget']) : '—' ?></td>
            <td><?= $u['basket_count'] ?> items</td>
            <td><?= $u['alert_count'] ?> alerts</td>
            <td style="font-size:.78rem;color:var(--muted)"><?= $u['last_login'] ? timeAgo($u['last_login']) : 'Never' ?></td>
            <td>
              <span class="badge <?= $u['is_active'] ? 'badge-green' : 'badge-red' ?>">
                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td>
              <div style="display:flex;gap:5px;flex-wrap:wrap">
                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action"         value="toggle_active"/>
                  <input type="hidden" name="user_id"        value="<?= $u['id'] ?>"/>
                  <input type="hidden" name="current_status" value="<?= $u['is_active'] ?>"/>
                  <button type="submit" class="btn btn-sm <?= $u['is_active']?'btn-red':'btn-green' ?>">
                    <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
