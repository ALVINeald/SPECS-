<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();

$pageTitle = 'My Account';
$uid       = (int)$_SESSION['user_id'];
$user      = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fullname = $conn->real_escape_string(trim($_POST['fullname']));
        $budget   = (int)$_POST['monthly_budget'];
        $conn->query("UPDATE users SET fullname='$fullname', monthly_budget=$budget WHERE id=$uid");
        $_SESSION['fullname'] = $fullname;
        setFlash('success', 'Profile updated successfully!');
        redirect('account.php');
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $dbUser = $conn->query("SELECT password_hash FROM users WHERE id=$uid")->fetch_assoc();
        if (!password_verify($current, $dbUser['password_hash'])) {
            setFlash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            setFlash('error', 'New password must be at least 6 characters.');
        } elseif ($new !== $confirm) {
            setFlash('error', 'New passwords do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            $hashE = $conn->real_escape_string($hash);
            $conn->query("UPDATE users SET password_hash='$hashE' WHERE id=$uid");
            setFlash('success', 'Password changed successfully!');
        }
        redirect('account.php');
    }
}

// Stats for this user
$basketCount  = getBasketCount($conn);
$alertsCount  = getAlertsCount($conn);
$plansCount   = $conn->query("SELECT COUNT(*) AS t FROM store_plans WHERE user_id=$uid")->fetch_assoc()['t'];
$basketTotal  = $conn->query("
    SELECT SUM((SELECT MIN(pr.price) FROM prices pr WHERE pr.product_id=b.product_id) * b.quantity) AS t
    FROM basket b WHERE b.user_id=$uid
")->fetch_assoc()['t'] ?? 0;

include '../includes/header.php';
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto">
    <h1>👤 My Account</h1>
    <p>Manage your profile and preferences</p>
  </div>
</div>

<div class="ctr">
  <?php showFlash(); ?>

  <!-- PROFILE HEADER CARD -->
  <div style="background:linear-gradient(135deg,var(--forest),var(--leaf));border-radius:var(--r);padding:28px;margin-bottom:22px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
    <div style="width:64px;height:64px;border-radius:50%;background:var(--gold);display:flex;align-items:center;justify-content:center;font-family:'Nunito',sans-serif;font-weight:900;font-size:1.5rem;color:var(--forest);flex-shrink:0">
      <?= strtoupper(substr($user['name'],0,1)) ?>
    </div>
    <div style="flex:1">
      <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.3rem;color:#fff"><?= htmlspecialchars($user['name']) ?></div>
      <div style="color:rgba(255,255,255,.6);font-size:.85rem"><?= htmlspecialchars($user['email']) ?></div>
      <div style="color:rgba(255,255,255,.45);font-size:.76rem;margin-top:3px">Member since <?= date('F Y', strtotime($user['created_at'])) ?></div>
    </div>
    <div style="display:flex;gap:16px">
      <?php
      $sumStats = [
        ['🛒', $basketCount, 'Items'],
        ['🔔', $alertsCount, 'Alerts'],
        ['🧾', $plansCount,  'Plans'],
      ];
      foreach ($sumStats as $ss): ?>
      <div style="text-align:center">
        <div style="font-size:1rem"><?= $ss[0] ?></div>
        <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.2rem;color:var(--gold)"><?= $ss[1] ?></div>
        <div style="font-size:.68rem;color:rgba(255,255,255,.5)"><?= $ss[2] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;flex-wrap:wrap">

    <!-- UPDATE PROFILE -->
    <div class="card">
      <div class="card-title">✏️ Update Profile</div>
      <form method="POST">
        <input type="hidden" name="action" value="update_profile"/>
        <div class="fgrp">
          <label class="flabel">Full Name</label>
          <input type="text" name="fullname" class="finput" value="<?= htmlspecialchars($user['name']) ?>" required/>
        </div>
        <div class="fgrp">
          <label class="flabel">Email Address</label>
          <input type="email" class="finput" value="<?= htmlspecialchars($user['email']) ?>" disabled style="background:var(--cream);color:var(--muted)"/>
          <div style="font-size:.72rem;color:var(--muted);margin-top:3px">Email cannot be changed</div>
        </div>
        <div class="fgrp">
          <label class="flabel">Monthly Budget (UGX)</label>
          <input type="number" name="monthly_budget" class="finput"
                 value="<?= $user['budget'] ?>" placeholder="e.g. 200000"/>
          <div style="font-size:.72rem;color:var(--muted);margin-top:3px">Used to track your grocery spending</div>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </form>
    </div>

    <!-- CHANGE PASSWORD -->
    <div class="card">
      <div class="card-title">🔒 Change Password</div>
      <form method="POST">
        <input type="hidden" name="action" value="change_password"/>
        <div class="fgrp">
          <label class="flabel">Current Password</label>
          <input type="password" name="current_password" class="finput" required/>
        </div>
        <div class="fgrp">
          <label class="flabel">New Password</label>
          <input type="password" name="new_password" class="finput" placeholder="At least 6 characters" required/>
        </div>
        <div class="fgrp">
          <label class="flabel">Confirm New Password</label>
          <input type="password" name="confirm_password" class="finput" required/>
        </div>
        <button type="submit" class="btn btn-green">Change Password</button>
      </form>
    </div>

  </div>

  <!-- BASKET SUMMARY -->
  <div class="card" style="margin-top:20px">
    <div class="card-title">🛒 My Shopping Summary</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px">
      <?php
      $summaryItems = [
        ['🛒', 'Items in Basket',   $basketCount,              'basket.php'],
        ['💰', 'Basket Total',      formatPrice($basketTotal), 'basket.php'],
        ['🔔', 'Active Alerts',     $alertsCount,              'alerts.php'],
        ['🧾', 'Shopping Plans',    $plansCount,               'basket.php'],
      ];
      foreach ($summaryItems as $si): ?>
      <a href="<?= $si[3] ?>" style="text-decoration:none">
        <div style="background:var(--cream);border-radius:var(--rs);padding:16px;transition:all .2s"
             onmouseover="this.style.background='var(--sand)'" onmouseout="this.style.background='var(--cream)'">
          <div style="font-size:1.3rem;margin-bottom:6px"><?= $si[0] ?></div>
          <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.2rem;color:var(--forest)"><?= $si[2] ?></div>
          <div style="font-size:.74rem;color:var(--muted)"><?= $si[1] ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- SIGN OUT -->
  <div style="margin-top:20px;text-align:center">
    <a href="../logout.php" class="btn btn-red">🚪 Sign Out</a>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
