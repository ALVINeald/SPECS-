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
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    :root{
      --forest:#18382a;--leaf:#2d6a4f;--mint:#52b788;--gold:#e9a820;
      --cream:#fdf8f2;--sand:#e8e2d9;--ink:#1c1a17;--muted:#7a7060;
      --white:#fff;--red:#e63946;--r:14px;--rs:8px;
    }

    html{width:100%;overflow-x:hidden;margin:0;padding:0}
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

    body{
      font-family:'Plus Jakarta Sans',sans-serif;
      min-height:100vh;width:100%;overflow-x:hidden;
      display:flex;align-items:center;justify-content:center;
      background:var(--forest);
      position:relative;
      padding:24px;
    }

    #doodle-bg{
      position:fixed;inset:0;width:100%;height:100%;
      pointer-events:none;
    }

    .glow-a{
      position:fixed;width:380px;height:380px;border-radius:50%;
      background:rgba(255,255,255,.03);top:-100px;right:-100px;
      pointer-events:none;
    }
    .glow-b{
      position:fixed;width:260px;height:260px;border-radius:50%;
      background:rgba(233,168,32,.05);bottom:-60px;left:-60px;
      pointer-events:none;
    }

    .form-box{
      position:relative;z-index:1;
      width:100%;max-width:400px;
      background:var(--cream);
      border-radius:20px;
      padding:2.5rem 2.25rem;
      border:1.5px solid rgba(255,255,255,.08);
      box-shadow:0 24px 60px rgba(0,0,0,.25);
    }

    .brand-row{text-align:center;margin-bottom:1.75rem}
    .brand{
      font-weight:800;font-size:1.55rem;color:var(--forest);
      letter-spacing:-0.02em;
    }
    .brand em{color:var(--gold);font-style:normal}
    .brand-sub{font-size:.8rem;color:var(--muted);margin-top:2px}

    .alert{
      padding:12px 16px;border-radius:var(--rs);
      font-size:.85rem;font-weight:600;margin-bottom:18px;
    }
    .alert-error{background:#fdf0f0;border:1.5px solid #f5c6c6;color:#b91c1c}
    .alert-success{background:#f0fdf4;border:1.5px solid #a3d4b5;color:#155724}

    .fgrp{margin-bottom:16px}
    .flabel{
      display:block;font-size:.72rem;font-weight:700;
      color:var(--muted);text-transform:uppercase;
      letter-spacing:.07em;margin-bottom:6px;
    }
    .finput{
      width:100%;background:var(--white);
      border:1.8px solid var(--sand);border-radius:var(--rs);
      padding:12px 14px;font-size:.95rem;color:var(--ink);
      outline:none;transition:border-color .2s;
      font-family:'Plus Jakarta Sans',sans-serif;
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
      font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;
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
      padding:11px;font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;
      font-size:.9rem;cursor:pointer;transition:all .2s;
      display:flex;align-items:center;justify-content:center;gap:10px;
    }
    .btn-google:hover{border-color:#aaa;background:#fafafa}

    .reg-link{text-align:center;margin-top:22px;font-size:.85rem;color:var(--muted)}
    .reg-link a{color:var(--leaf);font-weight:800}

    @media(max-width:480px){
      .form-box{padding:2rem 1.5rem}
    }
  </style>
</head>
<body>

<canvas id="doodle-bg"></canvas>
<div class="glow-a"></div>
<div class="glow-b"></div>

<div class="form-box">
  <div class="brand-row">
    <div class="brand">SP<em>EC</em>S</div>
    <div class="brand-sub">Mbarara City &middot; Uganda</div>
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

<script>
function togglePw() {
  const input = document.getElementById('pwInput');
  input.type = input.type === 'password' ? 'text' : 'password';
}

function googleLogin() {
  alert('Google login requires Google OAuth setup.\nUse email/password for now.\n\nSetup guide: console.cloud.google.com');
}

(function () {
  const canvas = document.getElementById('doodle-bg');
  const ctx = canvas.getContext('2d');

  function resize() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    draw();
  }

  function basket(cx, cy, s) {
    ctx.beginPath();
    ctx.moveTo(cx - 0.6 * s, cy - 0.3 * s);
    ctx.lineTo(cx + 0.6 * s, cy - 0.3 * s);
    ctx.lineTo(cx + 0.45 * s, cy + 0.5 * s);
    ctx.lineTo(cx - 0.45 * s, cy + 0.5 * s);
    ctx.closePath();
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(cx - 0.35 * s, cy - 0.3 * s);
    ctx.quadraticCurveTo(cx, cy - 0.9 * s, cx + 0.35 * s, cy - 0.3 * s);
    ctx.stroke();
  }

  function leaf(cx, cy, s) {
    ctx.beginPath();
    ctx.ellipse(cx, cy, 0.5 * s, 0.3 * s, Math.PI / 4, 0, Math.PI * 2);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(cx - 0.3 * s, cy + 0.2 * s);
    ctx.lineTo(cx + 0.3 * s, cy - 0.2 * s);
    ctx.stroke();
  }

  function receipt(cx, cy, s) {
    ctx.beginPath();
    ctx.rect(cx - 0.35 * s, cy - 0.6 * s, 0.7 * s, 1.2 * s);
    ctx.stroke();
    for (let i = 0; i < 3; i++) {
      ctx.beginPath();
      ctx.moveTo(cx - 0.2 * s, cy - 0.3 * s + i * 0.3 * s);
      ctx.lineTo(cx + 0.2 * s, cy - 0.3 * s + i * 0.3 * s);
      ctx.stroke();
    }
  }

  function coin(cx, cy, s) {
    ctx.beginPath();
    ctx.arc(cx, cy, 0.4 * s, 0, Math.PI * 2);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(cx, cy - 0.2 * s);
    ctx.lineTo(cx, cy + 0.2 * s);
    ctx.stroke();
  }

  function bag(cx, cy, s) {
    ctx.beginPath();
    ctx.moveTo(cx - 0.4 * s, cy - 0.4 * s);
    ctx.lineTo(cx + 0.4 * s, cy - 0.4 * s);
    ctx.lineTo(cx + 0.3 * s, cy + 0.5 * s);
    ctx.lineTo(cx - 0.3 * s, cy + 0.5 * s);
    ctx.closePath();
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(cx, cy - 0.4 * s, 0.15 * s, Math.PI, 0);
    ctx.stroke();
  }

  // Fixed positions (fractions of viewport), drawn once - no animation
  const items = [
    { x: 0.06, y: 0.10, s: 30, fn: basket },
    { x: 0.92, y: 0.12, s: 24, fn: leaf },
    { x: 0.95, y: 0.42, s: 28, fn: receipt },
    { x: 0.04, y: 0.48, s: 20, fn: coin },
    { x: 0.08, y: 0.86, s: 26, fn: bag },
    { x: 0.90, y: 0.82, s: 24, fn: basket },
    { x: 0.50, y: 0.05, s: 18, fn: leaf },
    { x: 0.50, y: 0.96, s: 20, fn: coin }
  ];

  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.strokeStyle = 'rgba(255,255,255,0.07)';
    ctx.lineWidth = 1.4;
    items.forEach(function (it) {
      it.fn(it.x * canvas.width, it.y * canvas.height, it.s);
    });
  }

  window.addEventListener('resize', resize);
  resize();
})();
</script>
</body>
</html>