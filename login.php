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
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';

  function resize() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    draw();
  }

  function cart(cx, cy, s, c, rot) {
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(rot); ctx.strokeStyle = c;
    ctx.beginPath(); ctx.rect(-0.5 * s, -0.4 * s, 1 * s, 0.6 * s); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(-0.5 * s, -0.4 * s); ctx.lineTo(-0.65 * s, -0.65 * s); ctx.stroke();
    ctx.beginPath(); ctx.arc(-0.25 * s, 0.35 * s, 0.1 * s, 0, Math.PI * 2); ctx.stroke();
    ctx.beginPath(); ctx.arc(0.25 * s, 0.35 * s, 0.1 * s, 0, Math.PI * 2); ctx.stroke();
    ctx.restore();
  }

  function apple(cx, cy, s, c, rot) {
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(rot); ctx.strokeStyle = c;
    ctx.beginPath(); ctx.arc(0, 0, 0.4 * s, 0, Math.PI * 2); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(0, -0.4 * s); ctx.quadraticCurveTo(0.15 * s, -0.6 * s, 0.1 * s, -0.7 * s); ctx.stroke();
    ctx.beginPath(); ctx.ellipse(0.22 * s, -0.55 * s, 0.12 * s, 0.06 * s, -0.6, 0, Math.PI * 2); ctx.stroke();
    ctx.restore();
  }

  function tagSmile(cx, cy, s, c, rot) {
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(rot); ctx.strokeStyle = c;
    ctx.beginPath();
    ctx.moveTo(-0.5 * s, -0.3 * s); ctx.lineTo(0.1 * s, -0.3 * s); ctx.lineTo(0.5 * s, 0);
    ctx.lineTo(0.1 * s, 0.3 * s); ctx.lineTo(-0.5 * s, 0.3 * s); ctx.closePath(); ctx.stroke();
    ctx.beginPath(); ctx.arc(-0.25 * s, 0, 0.07 * s, 0, Math.PI * 2); ctx.stroke();
    ctx.beginPath(); ctx.arc(0, -0.05 * s, 0.18 * s, 0.15 * Math.PI, 0.85 * Math.PI); ctx.stroke();
    ctx.restore();
  }

  function bagHeart(cx, cy, s, c, rot) {
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(rot); ctx.strokeStyle = c;
    ctx.beginPath();
    ctx.moveTo(-0.4 * s, -0.4 * s); ctx.lineTo(0.4 * s, -0.4 * s); ctx.lineTo(0.3 * s, 0.5 * s);
    ctx.lineTo(-0.3 * s, 0.5 * s); ctx.closePath(); ctx.stroke();
    ctx.beginPath(); ctx.arc(0, -0.4 * s, 0.15 * s, Math.PI, 0); ctx.stroke();
    ctx.beginPath();
    const hs = 0.12 * s;
    ctx.moveTo(0, 0.15 * s);
    ctx.bezierCurveTo(-hs, -0.05 * s, -hs * 2, 0.15 * s, 0, 0.32 * s);
    ctx.bezierCurveTo(hs * 2, 0.15 * s, hs, -0.05 * s, 0, 0.15 * s);
    ctx.stroke();
    ctx.restore();
  }

  function basket(cx, cy, s, c, rot) {
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(rot); ctx.strokeStyle = c;
    ctx.beginPath();
    ctx.moveTo(-0.6 * s, -0.3 * s); ctx.lineTo(0.6 * s, -0.3 * s); ctx.lineTo(0.45 * s, 0.5 * s);
    ctx.lineTo(-0.45 * s, 0.5 * s); ctx.closePath(); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(-0.35 * s, -0.3 * s); ctx.quadraticCurveTo(0, -0.9 * s, 0.35 * s, -0.3 * s); ctx.stroke();
    ctx.restore();
  }

  function coin(cx, cy, s, c, rot) {
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(rot); ctx.strokeStyle = c;
    ctx.beginPath(); ctx.arc(0, 0, 0.4 * s, 0, Math.PI * 2); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(0, -0.18 * s); ctx.lineTo(0, 0.18 * s); ctx.stroke();
    ctx.restore();
  }

  function leaf(cx, cy, s, c, rot) {
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(rot); ctx.strokeStyle = c;
    ctx.beginPath(); ctx.ellipse(0, 0, 0.5 * s, 0.3 * s, Math.PI / 4, 0, Math.PI * 2); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(-0.3 * s, 0.2 * s); ctx.lineTo(0.3 * s, -0.2 * s); ctx.stroke();
    ctx.restore();
  }

  function receipt(cx, cy, s, c, rot) {
    ctx.save(); ctx.translate(cx, cy); ctx.rotate(rot); ctx.strokeStyle = c;
    ctx.beginPath(); ctx.rect(-0.32 * s, -0.55 * s, 0.64 * s, 1.1 * s); ctx.stroke();
    for (let i = 0; i < 3; i++) {
      ctx.beginPath();
      ctx.moveTo(-0.18 * s, -0.28 * s + i * 0.28 * s);
      ctx.lineTo(0.18 * s, -0.28 * s + i * 0.28 * s);
      ctx.stroke();
    }
    ctx.restore();
  }

  const gold = 'rgba(233,168,32,0.16)';
  const mint = 'rgba(82,183,136,0.16)';
  const white = 'rgba(255,255,255,0.12)';
  const shapes = [cart, apple, tagSmile, bagHeart, basket, coin, leaf, receipt];
  const colors = [gold, mint, white];

  const items = [];
  for (let i = 0; i < 26; i++) {
    items.push({
      x: Math.random(),
      y: Math.random(),
      s: 14 + Math.random() * 16,
      rot: (Math.random() - 0.5) * 0.6,
      fn: shapes[Math.floor(Math.random() * shapes.length)],
      c: colors[Math.floor(Math.random() * colors.length)]
    });
  }

  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.lineWidth = 1.5;
    items.forEach(function (it) {
      it.fn(it.x * canvas.width, it.y * canvas.height, it.s, it.c, it.rot);
    });
  }

  window.addEventListener('resize', resize);
  resize();
})();
</script>
</body>
</html>