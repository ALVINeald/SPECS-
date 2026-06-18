<?php
// ============================================================
//  SPECS – Forgot Password Page
//  File: forgot.php
// ============================================================
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Already logged in
if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/index.php' : 'user/index.php');
}

$step    = $_GET['step'] ?? 'request'; // request | sent | reset | done
$token   = $_GET['token'] ?? '';
$error   = '';
$success = '';

// ── STEP 1: REQUEST RESET ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        $emailE = $conn->real_escape_string($email);
        $user   = $conn->query("SELECT id, fullname FROM users WHERE email='$emailE' AND is_active=1")->fetch_assoc();

        if ($user) {
            // Generate token
            $token     = bin2hex(random_bytes(32));
            $expires   = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $uid       = $user['id'];

            // Delete old tokens for this user
            $conn->query("DELETE FROM password_resets WHERE user_id=$uid");

            // Save token
            $conn->query("INSERT INTO password_resets (user_id, token, expires_at) VALUES ($uid, '$token', '$expires')");

            // In a real system you would email the link.
            // For localhost we show it directly.
            $resetLink = "http://localhost/specs/forgot.php?step=reset&token=$token";
            $success   = $resetLink;
        }

        // Always redirect to 'sent' step — don't reveal if email exists
        header("Location: forgot.php?step=sent&email=" . urlencode($email));
        exit();
    }
}

// ── STEP 3: SUBMIT NEW PASSWORD ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    $token       = $_POST['token'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';
    $tokenE      = $conn->real_escape_string($token);

    // Validate token
    $reset = $conn->query("
        SELECT pr.*, u.fullname FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token='$tokenE' AND pr.expires_at > NOW() AND pr.used=0
    ")->fetch_assoc();

    if (!$reset) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($newPassword !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $hashE = $conn->real_escape_string($hash);
        $uid   = (int)$reset['user_id'];

        // Update password
        $conn->query("UPDATE users SET password_hash='$hashE' WHERE id=$uid");

        // Mark token as used
        $conn->query("UPDATE password_resets SET used=1 WHERE token='$tokenE'");

        header("Location: forgot.php?step=done");
        exit();
    }
}

// ── VALIDATE TOKEN FOR RESET STEP ────────────────────────────
$resetUser = null;
if ($step === 'reset' && $token) {
    $tokenE    = $conn->real_escape_string($token);
    $resetUser = $conn->query("
        SELECT pr.*, u.fullname, u.email FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token='$tokenE' AND pr.expires_at > NOW() AND pr.used=0
    ")->fetch_assoc();

    if (!$resetUser) {
        $step  = 'expired';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password – SPECS Mbarara</title>
  <link rel="icon" href="/specs/assets/images/favicon.ico"/>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Nunito+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    :root{
      --forest:#18382a;--leaf:#2d6a4f;--mint:#52b788;--gold:#e9a820;
      --cream:#fdf8f2;--sand:#e8e2d9;--ink:#1c1a17;--muted:#7a7060;
      --white:#fff;--red:#e63946;--r:14px;--rs:8px;
    }
    html,body{ margin:0; padding:0; width:100%; overflow-x:hidden; }
    *,*::before,*::after{ box-sizing:border-box; margin:0; padding:0; }
    body{
      font-family:'Nunito Sans',sans-serif;
      min-height:100vh; background:var(--cream);
      display:flex; align-items:center; justify-content:center;
      padding:24px;
    }

    .fp-box{
      width:100%; max-width:440px;
      background:var(--white);
      border-radius:var(--r);
      border:1.5px solid var(--sand);
      overflow:hidden;
      box-shadow:0 8px 32px rgba(0,0,0,.1);
    }

    /* ── HEADER ── */
    .fp-header{
      background:linear-gradient(135deg,var(--forest),var(--leaf));
      padding:28px 28px 24px;
      text-align:center;
    }
    .fp-brand{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1.6rem;color:#fff;margin-bottom:4px;
    }
    .fp-brand em{ color:var(--gold);font-style:normal; }
    .fp-icon{ font-size:2.2rem;margin-bottom:8px;display:block; }
    .fp-title{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1.1rem;color:#fff;margin-bottom:4px;
    }
    .fp-sub{ font-size:.8rem;color:rgba(255,255,255,.6); }

    /* ── BODY ── */
    .fp-body{ padding:28px; }

    .alert{
      padding:12px 16px;border-radius:var(--rs);
      font-size:.85rem;font-weight:600;margin-bottom:18px;
    }
    .alert-error  { background:#fdf0f0;border:1.5px solid #f5c6c6;color:#b91c1c; }
    .alert-success{ background:#f0fdf4;border:1.5px solid #a3d4b5;color:#155724; }
    .alert-info   { background:#fff9e6;border:1.5px solid #ffe082;color:#856404; }

    .fgrp{ margin-bottom:16px; }
    .flabel{
      display:block;font-size:.72rem;font-weight:800;
      color:var(--muted);text-transform:uppercase;
      letter-spacing:.07em;margin-bottom:6px;
    }
    .finput{
      width:100%;background:var(--cream);
      border:1.8px solid var(--sand);border-radius:var(--rs);
      padding:12px 14px;font-size:.95rem;color:var(--ink);
      outline:none;transition:border-color .2s;
      font-family:'Nunito Sans',sans-serif;
    }
    .finput:focus{ border-color:var(--leaf);background:var(--white); }

    .pw-wrap{ position:relative; }
    .pw-toggle{
      position:absolute;right:13px;top:50%;transform:translateY(-50%);
      background:none;border:none;cursor:pointer;font-size:1rem;color:var(--muted);
    }

    /* Password strength */
    .pw-strength{ height:4px;border-radius:99px;background:var(--sand);margin-top:7px;overflow:hidden; }
    .pw-bar{ height:100%;border-radius:99px;transition:all .3s;width:0; }
    .pw-hint{ font-size:.72rem;color:var(--muted);margin-top:4px; }

    .btn-submit{
      width:100%;background:var(--forest);color:var(--white);
      border:none;border-radius:var(--rs);padding:13px;
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1rem;cursor:pointer;transition:all .2s;margin-top:4px;
    }
    .btn-submit:hover{ background:var(--leaf);transform:translateY(-1px); }

    .back-link{
      text-align:center;margin-top:18px;
      font-size:.84rem;color:var(--muted);
    }
    .back-link a{ color:var(--leaf);font-weight:700; }

    /* ── SUCCESS STATE ── */
    .fp-success{
      text-align:center;padding:32px 28px;
    }
    .success-icon{ font-size:3rem;margin-bottom:14px;display:block; }
    .success-title{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1.2rem;color:var(--forest);margin-bottom:8px;
    }
    .success-text{ font-size:.86rem;color:var(--muted);line-height:1.7;margin-bottom:20px; }

    /* Reset link box (localhost only) */
    .reset-link-box{
      background:#f0fdf4;border:1.5px solid #a3d4b5;
      border-radius:var(--rs);padding:14px;
      font-size:.78rem;color:#155724;
      word-break:break-all;margin-bottom:16px;
    }
    .reset-link-box strong{ display:block;margin-bottom:6px;font-size:.8rem; }

    .step-dots{
      display:flex;justify-content:center;gap:8px;margin-bottom:20px;
    }
    .dot{
      width:8px;height:8px;border-radius:50%;
      background:var(--sand);transition:all .2s;
    }
    .dot.active{ background:var(--gold);transform:scale(1.3); }
    .dot.done  { background:var(--leaf); }
  </style>
</head>
<body>

<div class="fp-box">

  <!-- HEADER -->
  <div class="fp-header">
    <div class="fp-brand">SP<em>EC</em>S</div>
    <?php if ($step === 'request'): ?>
      <span class="fp-icon">🔐</span>
      <div class="fp-title">Forgot Your Password?</div>
      <div class="fp-sub">Enter your email and we will send you a reset link</div>
    <?php elseif ($step === 'sent'): ?>
      <span class="fp-icon">📧</span>
      <div class="fp-title">Check Your Email</div>
      <div class="fp-sub">Reset instructions have been sent</div>
    <?php elseif ($step === 'reset'): ?>
      <span class="fp-icon">🔑</span>
      <div class="fp-title">Set New Password</div>
      <div class="fp-sub">Choose a strong new password</div>
    <?php elseif ($step === 'done'): ?>
      <span class="fp-icon">✅</span>
      <div class="fp-title">Password Reset!</div>
      <div class="fp-sub">You can now log in with your new password</div>
    <?php elseif ($step === 'expired'): ?>
      <span class="fp-icon">⏰</span>
      <div class="fp-title">Link Expired</div>
      <div class="fp-sub">This reset link is no longer valid</div>
    <?php endif; ?>
  </div>

  <!-- STEP DOTS -->
  <div style="padding:16px 28px 0">
    <div class="step-dots">
      <div class="dot <?= in_array($step,['request'])        ? 'active' : 'done' ?>"></div>
      <div class="dot <?= in_array($step,['sent'])           ? 'active' : ($step==='request' ? '' : 'done') ?>"></div>
      <div class="dot <?= in_array($step,['reset'])          ? 'active' : ($step==='done' ? 'done' : '') ?>"></div>
      <div class="dot <?= in_array($step,['done'])           ? 'active' : '' ?>"></div>
    </div>
  </div>

  <!-- ── STEP 1: REQUEST ── -->
  <?php if ($step === 'request'): ?>
  <div class="fp-body">
    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="forgot.php?step=request" autocomplete="off">
      <div class="fgrp">
        <label class="flabel">Your Email Address</label>
        <input type="email" name="email" class="finput"
               placeholder="your@email.com"
               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
               required autocomplete="off"/>
      </div>
      <button type="submit" class="btn-submit">📧 Send Reset Link</button>
    </form>

    <div class="back-link">
      Remembered it? <a href="login.php">Back to Login</a>
    </div>
  </div>

  <!-- ── STEP 2: SENT ── -->
  <?php elseif ($step === 'sent'): ?>
  <div class="fp-success">
    <span class="success-icon">📬</span>
    <div class="success-title">Reset Link Generated!</div>
    <div class="success-text">
      If <strong><?= htmlspecialchars($_GET['email'] ?? '') ?></strong> is registered on SPECS,
      a password reset link has been created.
    </div>

    <?php
    // For localhost — show the reset link directly since we can't send email
    $emailE    = $conn->real_escape_string($_GET['email'] ?? '');
    $resetRow  = $conn->query("
        SELECT pr.token FROM password_resets pr
        JOIN users u ON pr.user_id=u.id
        WHERE u.email='$emailE' AND pr.used=0
        ORDER BY pr.created_at DESC LIMIT 1
    ")->fetch_assoc();
    if ($resetRow): ?>
    <div class="reset-link-box">
      <strong>🖥️ Since this is localhost — click your reset link below:</strong>
      <a href="forgot.php?step=reset&token=<?= $resetRow['token'] ?>"
         style="color:var(--leaf);font-weight:700;word-break:break-all">
        Click here to reset your password →
      </a>
    </div>
    <?php endif; ?>

    <a href="login.php" class="btn-submit" style="display:block;text-align:center;text-decoration:none;padding:13px">
      ← Back to Login
    </a>
  </div>

  <!-- ── STEP 3: RESET FORM ── -->
  <?php elseif ($step === 'reset' && $resetUser): ?>
  <div class="fp-body">
    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="alert alert-info" style="margin-bottom:16px">
      👤 Resetting password for <strong><?= htmlspecialchars($resetUser['fullname']) ?></strong>
    </div>

    <form method="POST" action="forgot.php?step=reset" autocomplete="off">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>"/>

      <div class="fgrp">
        <label class="flabel">New Password</label>
        <div class="pw-wrap">
          <input type="password" name="new_password" id="pwNew" class="finput"
                 placeholder="At least 6 characters"
                 oninput="checkStrength(this.value)" required autocomplete="new-password"/>
          <button type="button" class="pw-toggle" onclick="togglePw('pwNew')">👁️</button>
        </div>
        <div class="pw-strength"><div class="pw-bar" id="pwBar"></div></div>
        <div class="pw-hint" id="pwHint">Enter a new password</div>
      </div>

      <div class="fgrp">
        <label class="flabel">Confirm New Password</label>
        <div class="pw-wrap">
          <input type="password" name="confirm_password" id="pwConfirm" class="finput"
                 placeholder="Re-enter your new password"
                 required autocomplete="new-password"/>
          <button type="button" class="pw-toggle" onclick="togglePw('pwConfirm')">👁️</button>
        </div>
      </div>

      <button type="submit" class="btn-submit">🔑 Reset My Password</button>
    </form>

    <div class="back-link">
      <a href="login.php">Cancel — Back to Login</a>
    </div>
  </div>

  <!-- ── STEP 4: DONE ── -->
  <?php elseif ($step === 'done'): ?>
  <div class="fp-success">
    <span class="success-icon">🎉</span>
    <div class="success-title">Password Reset Successfully!</div>
    <div class="success-text">
      Your password has been updated. You can now log in to SPECS with your new password.
    </div>
    <a href="login.php" class="btn-submit" style="display:block;text-align:center;text-decoration:none;padding:13px">
      → Go to Login
    </a>
  </div>

  <!-- ── EXPIRED ── -->
  <?php elseif ($step === 'expired'): ?>
  <div class="fp-success">
    <span class="success-icon">⏰</span>
    <div class="success-title">Link Expired or Invalid</div>
    <div class="success-text">
      This password reset link has expired or already been used.<br>
      Reset links are valid for <strong>1 hour</strong> only.
    </div>
    <a href="forgot.php" class="btn-submit" style="display:block;text-align:center;text-decoration:none;padding:13px">
      🔄 Request New Reset Link
    </a>
  </div>
  <?php endif; ?>

</div>

<script>
function togglePw(id) {
  const el = document.getElementById(id);
  el.type  = el.type === 'password' ? 'text' : 'password';
}

function checkStrength(pw) {
  const bar  = document.getElementById('pwBar');
  const hint = document.getElementById('pwHint');
  if (!bar) return;
  let score = 0;
  if (pw.length >= 6)          score++;
  if (pw.length >= 10)         score++;
  if (/[A-Z]/.test(pw))        score++;
  if (/[0-9]/.test(pw))        score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const levels = [
    {w:'0%',   c:'#ccc',          t:'Too short'},
    {w:'25%',  c:'var(--red)',    t:'Weak'},
    {w:'50%',  c:'var(--amber,#f4a261)', t:'Fair'},
    {w:'75%',  c:'var(--gold)',   t:'Good'},
    {w:'100%', c:'var(--leaf)',   t:'Strong ✅'},
  ];
  const l = levels[Math.min(score, 4)];
  bar.style.width      = l.w;
  bar.style.background = l.c;
  hint.textContent     = l.t;
  hint.style.color     = l.c;
}
</script>
</body>
</html>
