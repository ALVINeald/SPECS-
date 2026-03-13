<?php
// ============================================================
//  SPECS – Registration Page
//  File: register.php
// ============================================================
session_start();
require_once 'config/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Already logged in
if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/index.php' : 'user/index.php');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = $_POST['fullname'] ?? '';
    $email    = $_POST['email']    ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    $result = registerUser($conn, $fullname, $email, $password, $confirm);

    if ($result['success']) {
        redirect('user/index.php');
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Create Account – SPECS Mbarara</title>
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Nunito+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    :root{
      --forest:#18382a;--leaf:#2d6a4f;--mint:#52b788;--gold:#e9a820;
      --cream:#fdf8f2;--sand:#e8e2d9;--ink:#1c1a17;--muted:#7a7060;
      --white:#fff;--red:#e63946;--r:14px;--rs:8px;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{
      font-family:'Nunito Sans',sans-serif;
      min-height:100vh;display:flex;background:var(--cream);
    }
    .left-panel{
      width:42%;background:linear-gradient(160deg,var(--forest),var(--leaf));
      display:flex;flex-direction:column;justify-content:center;
      padding:48px;position:relative;overflow:hidden;
    }
    .left-panel::before{
      content:'';position:absolute;width:300px;height:300px;
      border-radius:50%;background:rgba(233,168,32,.1);
      top:-60px;right:-60px;
    }
    .brand{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:2.2rem;color:var(--white);margin-bottom:4px;
      position:relative;z-index:1;
    }
    .brand em{color:var(--gold);font-style:normal}
    .brand-sub{
      color:rgba(255,255,255,.5);font-size:.8rem;font-weight:600;
      letter-spacing:.08em;text-transform:uppercase;
      margin-bottom:36px;position:relative;z-index:1;
    }
    .perk{
      display:flex;align-items:center;gap:12px;
      margin-bottom:16px;position:relative;z-index:1;
    }
    .perk-icon{font-size:1.3rem}
    .perk-text{color:rgba(255,255,255,.75);font-size:.86rem}
    .perk-text strong{color:#fff;font-weight:700}
    .gold-box{
      background:rgba(233,168,32,.15);border:1px solid rgba(233,168,32,.3);
      border-radius:12px;padding:18px;margin-top:32px;
      position:relative;z-index:1;
    }
    .gold-box p{
      color:rgba(255,255,255,.7);font-size:.82rem;line-height:1.6;
      font-style:italic;
    }
    .gold-box strong{color:var(--gold)}

    .right-panel{
      flex:1;display:flex;align-items:center;
      justify-content:center;padding:40px 24px;overflow-y:auto;
    }
    .form-box{width:100%;max-width:420px;padding:8px 0}
    .form-title{
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1.65rem;color:var(--ink);margin-bottom:4px;
    }
    .form-sub{color:var(--muted);font-size:.86rem;margin-bottom:26px}
    .form-sub a{color:var(--leaf);font-weight:700}

    .alert{
      padding:12px 16px;border-radius:var(--rs);
      font-size:.85rem;font-weight:600;margin-bottom:18px;
    }
    .alert-error{background:#fdf0f0;border:1.5px solid #f5c6c6;color:#b91c1c}

    .fgrp{margin-bottom:15px}
    .flabel{
      display:block;font-size:.71rem;font-weight:800;color:var(--muted);
      text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px;
    }
    .finput{
      width:100%;background:var(--white);border:1.8px solid var(--sand);
      border-radius:var(--rs);padding:12px 14px;font-size:.95rem;
      color:var(--ink);outline:none;transition:border-color .2s;
      font-family:'Nunito Sans',sans-serif;
    }
    .finput:focus{border-color:var(--leaf)}
    .finput.error{border-color:var(--red)}
    .pw-wrap{position:relative}
    .pw-toggle{
      position:absolute;right:13px;top:50%;transform:translateY(-50%);
      background:none;border:none;cursor:pointer;font-size:1rem;color:var(--muted);
    }

    /* Password strength */
    .pw-strength{height:4px;border-radius:99px;background:var(--sand);margin-top:7px;overflow:hidden}
    .pw-bar{height:100%;border-radius:99px;transition:all .3s;width:0}
    .pw-hint{font-size:.72rem;color:var(--muted);margin-top:4px}

    /* Terms */
    .terms-row{
      display:flex;align-items:flex-start;gap:10px;
      margin:14px 0;font-size:.82rem;color:var(--muted);
    }
    .terms-row input{margin-top:2px;accent-color:var(--leaf)}
    .terms-row a{color:var(--leaf);font-weight:700}

    .btn-register{
      width:100%;background:var(--gold);color:var(--forest);
      border:none;border-radius:var(--rs);padding:13px;
      font-family:'Nunito',sans-serif;font-weight:900;
      font-size:1rem;cursor:pointer;transition:all .2s;margin-top:4px;
    }
    .btn-register:hover{background:#d4940f;transform:translateY(-1px)}

    .divider{
      display:flex;align-items:center;gap:12px;
      margin:18px 0;color:var(--muted);font-size:.78rem;font-weight:700;
    }
    .divider::before,.divider::after{content:'';flex:1;height:1px;background:var(--sand)}

    .btn-google{
      width:100%;background:var(--white);color:var(--ink);
      border:1.8px solid var(--sand);border-radius:var(--rs);
      padding:11px;font-family:'Nunito',sans-serif;font-weight:800;
      font-size:.9rem;cursor:pointer;transition:all .2s;
      display:flex;align-items:center;justify-content:center;gap:10px;
    }
    .btn-google:hover{border-color:#aaa}

    .login-link{
      text-align:center;margin-top:20px;
      font-size:.85rem;color:var(--muted);
    }
    .login-link a{color:var(--leaf);font-weight:800;font-family:'Nunito',sans-serif}

    @media(max-width:768px){
      .left-panel{display:none}
      .right-panel{padding:30px 20px}
    }
  </style>
</head>
<body>

<div class="left-panel">
  <div class="brand">SP<em>EC</em>S</div>
  <div class="brand-sub">Mbarara City · Uganda</div>

  <div class="perk">
    <span class="perk-icon">🆓</span>
    <span class="perk-text"><strong>Completely Free</strong> — No subscription needed</span>
  </div>
  <div class="perk">
    <span class="perk-icon">🏪</span>
    <span class="perk-text"><strong>7 Supermarkets</strong> — All major Mbarara stores</span>
  </div>
  <div class="perk">
    <span class="perk-icon">📦</span>
    <span class="perk-text"><strong>205+ Products</strong> — Updated regularly</span>
  </div>
  <div class="perk">
    <span class="perk-icon">🔔</span>
    <span class="perk-text"><strong>Price Alerts</strong> — Know when prices drop</span>
  </div>
  <div class="perk">
    <span class="perk-icon">🧾</span>
    <span class="perk-text"><strong>Shopping Plans</strong> — Save and share your list</span>
  </div>
  <div class="perk">
    <span class="perk-icon">💰</span>
    <span class="perk-text"><strong>Budget Tracker</strong> — Stay within your monthly budget</span>
  </div>

  <div class="gold-box">
    <p>"SPECS helped me save over <strong>UGX 45,000</strong> on my monthly groceries by showing me which store had the best prices for each item."</p>
  </div>
</div>

<div class="right-panel">
  <div class="form-box">
    <div class="form-title">Create your account</div>
    <div class="form-sub">
      Already have one? <a href="login.php">Sign in here</a>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="register.php" id="regForm">
      <div class="fgrp">
        <label class="flabel">Full Name</label>
        <input type="text" name="fullname" class="finput"
               placeholder="e.g. Tumwebaze Sarah"
               value="<?= isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : '' ?>"
               required/>
      </div>

      <div class="fgrp">
        <label class="flabel">Email Address</label>
        <input type="email" name="email" class="finput"
               placeholder="your@email.com"
               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
               required/>
      </div>

      <div class="fgrp">
        <label class="flabel">Password</label>
        <div class="pw-wrap">
          <input type="password" name="password" id="pwInput" class="finput"
                 placeholder="At least 6 characters"
                 oninput="checkStrength(this.value)" required/>
          <button type="button" class="pw-toggle" onclick="togglePw('pwInput')">👁️</button>
        </div>
        <div class="pw-strength"><div class="pw-bar" id="pwBar"></div></div>
        <div class="pw-hint" id="pwHint">Enter a password</div>
      </div>

      <div class="fgrp">
        <label class="flabel">Confirm Password</label>
        <div class="pw-wrap">
          <input type="password" name="confirm" id="cfInput" class="finput"
                 placeholder="Re-enter your password" required/>
          <button type="button" class="pw-toggle" onclick="togglePw('cfInput')">👁️</button>
        </div>
      </div>

      <div class="terms-row">
        <input type="checkbox" id="terms" required/>
        <label for="terms">
          I agree to the <a href="#">Terms of Service</a> and 
          <a href="#">Privacy Policy</a>
        </label>
      </div>

      <button type="submit" class="btn-register">Create My Account →</button>
    </form>

    <div class="divider">or</div>

    <button class="btn-google" onclick="googleRegister()">
      <img src="https://www.google.com/favicon.ico" width="18" height="18" alt="G"/>
      Sign up with Google
    </button>

    <div class="login-link">
      Already have an account? <a href="login.php">Sign in</a>
    </div>
  </div>
</div>

<script>
function togglePw(id) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}

function checkStrength(pw) {
  const bar  = document.getElementById('pwBar');
  const hint = document.getElementById('pwHint');
  let score  = 0;
  if (pw.length >= 6)  score++;
  if (pw.length >= 10) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;

  const levels = [
    {w:'0%',   c:'#ccc',     t:'Too short'},
    {w:'25%',  c:'#e63946',  t:'Weak'},
    {w:'50%',  c:'#f4a261',  t:'Fair'},
    {w:'75%',  c:'#e9a820',  t:'Good'},
    {w:'100%', c:'#2d6a4f',  t:'Strong ✅'},
  ];
  const l = levels[Math.min(score, 4)];
  bar.style.width      = l.w;
  bar.style.background = l.c;
  hint.textContent     = l.t;
  hint.style.color     = l.c;
}

function googleRegister() {
  alert('Google Sign-up requires Google OAuth setup.\nUse email/password for now.\n\nSetup: console.cloud.google.com');
}
</script>
</body>
</html>
