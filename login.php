<?php
// ============================================================
//  SPECS – Login Page
//  File: login.php
// ============================================================
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Already logged in — redirect
if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/index.php' : 'user/index.php');
}

$error   = '';
$success = '';

if (isset($_GET['msg'])) {
    $success = clean($_GET['msg']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = $_POST['email']    ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $result = loginUser($conn, $email, $password);
        if ($result['success']) {
            if ($result['role'] === 'admin' || $result['role'] === 'manager') {
                redirect('admin/index.php');
            } else {
                redirect('user/index.php');
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sign In – SPECS Mbarara</title>
  <link rel="icon" href="/specs/assets/images/favicon.ico"/>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Nunito+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    :root{
      --forest:#18382a;--leaf:#2d6a4f;--mint:#52b788;--gold:#e9a820;
      --cream:#fdf8f2;--sand:#e8e2d9;--ink:#1c1a17;--muted:#7a7060;
      --white:#fff;--red:#e63946;--r:14px;--rs:8px;
    }

    html{
      width:100%;overflow-x:hidden;
      margin:0;padding:0;
    }

    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

    body{
      font-family:'Nunito Sans',sans-serif;
      min-height:100vh;
      width:100%;
      overflow-x:hidden;
      display:flex;
      background:var(--cream);
      margin:0;
      padding:0;
    }

    /* ── LEFT PANEL ── */
    .left-panel{
      width:45%;
      background:var(--forest);
      display:flex;
      flex-direction:column;
      justify-content:center;
      padding:48px;
      position:relative;
      overflow:hidden;
      flex-shrink:0;
    }
    .left-panel::before{
      content:'';position:absolute;
      width:380px;height:380px;border-radius:50%;
      background:rgba(255,255,255,.04);
      top:-80px;right:-80px;
    }
    .left-panel::after{
      content:'';position:absolute;
      width:260px;height:260px;border-radius:50%;
      background:rgba(233,168,32,.07);
      bottom:-60px;left:-40px;
    }
    .brand{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:2.4rem;color:var(--white);
      margin-bottom:6px;position:relative;z-index:1;
    }
    .brand em{color:var(--gold);font-style:normal}
    .brand-sub{
      color:rgba(255,255,255,.5);font-size:.82rem;
      font-weight:600;letter-spacing:.08em;text-transform:uppercase;
      margin-bottom:38px;position:relative;z-index:1;
    }
    .feature{
      display:flex;align-items:flex-start;gap:14px;
      margin-bottom:22px;position:relative;z-index:1;
    }
    .fi{
      width:40px;height:40px;border-radius:10px;
      background:rgba(255,255,255,.09);
      display:flex;align-items:center;justify-content:center;
      font-size:1.2rem;flex-shrink:0;
    }
    .ft{color:rgba(255,255,255,.75);font-size:.84rem;line-height:1.5}
    .ft strong{color:var(--white);font-weight:700;display:block;font-size:.9rem}
    .stores-strip{
      display:flex;gap:8px;flex-wrap:wrap;
      margin-top:36px;position:relative;z-index:1;
    }
    .store-chip{
      background:rgba(255,255,255,.08);
      color:rgba(255,255,255,.55);
      font-size:.68rem;font-weight:700;
      padding:4px 10px;border-radius:99px;
      border:1px solid rgba(255,255,255,.1);
    }

    /* ── RIGHT PANEL ── */
    .right-panel{
      flex:1;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:40px 24px;
      min-width:0;
    }
    .form-box{width:100%;max-width:420px}
    .form-title{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1.7rem;color:var(--ink);margin-bottom:4px;
    }
    .form-sub{color:var(--muted);font-size:.86rem;margin-bottom:28px}
    .form-sub a{color:var(--leaf);font-weight:700}

    .alert{
      padding:12px 16px;border-radius:var(--rs);
      font-size:.85rem;font-weight:600;margin-bottom:18px;
    }
    .alert-error{background:#fdf0f0;border:1.5px solid #f5c6c6;color:#b91c1c}
    .alert-success{background:#f0fdf4;border:1.5px solid #a3d4b5;color:#155724}

    .fgrp{margin-bottom:16px}
    .flabel{
      display:block;font-size:.72rem;font-weight:800;
      color:var(--muted);text-transform:uppercase;
      letter-spacing:.07em;margin-bottom:6px;
    }
    .finput{
      width:100%;background:var(--white);
      border:1.8px solid var(--sand);border-radius:var(--rs);
      padding:12px 14px;font-size:.95rem;color:var(--ink);
      outline:none;transition:border-color .2s;
      font-family:'Nunito Sans',sans-serif;
    }
    .finput:focus{border-color:var(--leaf)}
    .pw-wrap{position:relative}
    .pw-toggle{
      position:absolute;right:13px;top:50%;transform:translateY(-50%);
      background:none;border:none;cursor:pointer;font-size:1rem;color:var(--muted);
    }
    .forgot{text-align:right;margin-top:5px;font-size:.78rem}
    .forgot a{color:var(--leaf);font-weight:700}

    .btn-login{
      width:100%;background:var(--forest);color:var(--white);
      border:none;border-radius:var(--rs);padding:13px;
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1rem;cursor:pointer;transition:all .2s;margin-top:4px;
    }
    .btn-login:hover{background:var(--leaf);transform:translateY(-1px)}

    .divider{
      display:flex;align-items:center;gap:12px;
      margin:20px 0;color:var(--muted);font-size:.78rem;font-weight:700;
    }
    .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--sand)}

    .btn-google{
      width:100%;background:var(--white);color:var(--ink);
      border:1.8px solid var(--sand);border-radius:var(--rs);
      padding:11px;font-family:'Nunito',sans-serif;font-weight:800;
      font-size:.9rem;cursor:pointer;transition:all .2s;
      display:flex;align-items:center;justify-content:center;gap:10px;
    }
    .btn-google:hover{border-color:#aaa;background:#fafafa}

    .reg-link{
      text-align:center;margin-top:22px;
      font-size:.85rem;color:var(--muted);
    }
    .reg-link a{color:var(--leaf);font-weight:800;font-family:'Nunito',sans-serif}


    @media(max-width:768px){
      .left-panel{display:none}
      .right-panel{padding:30px 20px}
    }
  </style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
  <div class="brand">SP<em>EC</em>S</div>
  <div class="brand-sub">Mbarara City · Uganda</div>

  <div class="feature">
    <div class="fi">🛒</div>
    <div class="ft">
      <strong>Compare Prices</strong>
      Browse 205+ products across 7 supermarkets and find the best deals instantly.
    </div>
  </div>
  <div class="feature">
    <div class="fi">🔔</div>
    <div class="ft">
      <strong>Price Alerts</strong>
      Set your target price and get notified when products drop below your budget.
    </div>
  </div>
  <div class="feature">
    <div class="fi">🧾</div>
    <div class="ft">
      <strong>Smart Shopping Plans</strong>
      Save and share your shopping list like an MTN MoMo receipt.
    </div>
  </div>
  <div class="feature">
    <div class="fi">📈</div>
    <div class="ft">
      <strong>Price Trends</strong>
      Track how prices change over time across Mbarara supermarkets.
    </div>
  </div>

  <div class="stores-strip">
    <span class="store-chip">FRESCO</span>
    <span class="store-chip">Kirimi</span>
    <span class="store-chip">Day to Day</span>
    <span class="store-chip">Apple D2D</span>
    <span class="store-chip">Amazon Express</span>
    <span class="store-chip">Golf Course</span>
    <span class="store-chip">Central Market</span>
  </div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
  <div class="form-box">
    <div class="form-title">Welcome back 👋</div>
    <div class="form-sub">
      Don't have an account? <a href="register.php">Create one free</a>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off">
      <div class="fgrp">
        <label class="flabel">Email Address</label>
        <input type="email" name="email" class="finput"
               placeholder="your@email.com"
               autocomplete="off"
               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
               required/>
      </div>
      <div class="fgrp">
        <label class="flabel">Password</label>
        <div class="pw-wrap">
          <input type="password" name="password" id="pwInput"
                 class="finput" placeholder="Enter your password"
                 autocomplete="new-password" required/>
          <button type="button" class="pw-toggle" onclick="togglePw()">👁️</button>
        </div>
        <div class="forgot"><a href="forgot.php">Forgot password?</a></div>
      </div>
      <button type="submit" class="btn-login">Sign In to SPECS</button>
    </form>

    <div class="divider">or continue with</div>

    <button class="btn-google" onclick="googleLogin()">
      <img src="https://www.google.com/favicon.ico" width="18" height="18" alt="G"/>
      Continue with Google
    </button>

    <div class="reg-link">
      New to SPECS? <a href="register.php">Create a free account</a>
    </div>


  </div>
</div>

<script>
function togglePw() {
  const input = document.getElementById('pwInput');
  input.type = input.type === 'password' ? 'text' : 'password';
}

function googleLogin() {
  alert('Google login requires Google OAuth setup.\nUse email/password for now.\n\nSetup guide: console.cloud.google.com');
}
</script>
</body>
</html>