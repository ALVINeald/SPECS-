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


    /* ══════════════════════════════
       GLASS TERMS MODAL
    ══════════════════════════════ */
    #terms-overlay {
      position: fixed;
      inset: 0;
      z-index: 9999;
      background: rgba(0,0,0,.45);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
      animation: overlayFadeIn .25s ease;
    }
    #terms-overlay.open { display: flex; }

    @keyframes overlayFadeIn {
      from { opacity: 0; }
      to   { opacity: 1; }
    }

    #terms-glass {
      width: 100%;
      max-width: 560px;
      max-height: 88vh;
      background: rgba(24, 56, 42, 0.82);
      backdrop-filter: blur(40px) saturate(180%);
      -webkit-backdrop-filter: blur(40px) saturate(180%);
      border: 1px solid rgba(255,255,255,.2);
      border-radius: 24px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      box-shadow: 0 24px 64px rgba(0,0,0,.5);
      animation: glassSlideUp .32s cubic-bezier(.34,1.2,.64,1);
    }

    @keyframes glassSlideUp {
      from { transform: translateY(40px) scale(.97); opacity: 0; }
      to   { transform: translateY(0)    scale(1);   opacity: 1; }
    }

    .tg-header {
      padding: 22px 26px 16px;
      border-bottom: 1px solid rgba(255,255,255,.12);
      flex-shrink: 0;
    }
    .tg-logo {
      font-family: 'Nunito', sans-serif;
      font-weight: 900;
      font-size: 1.1rem;
      color: var(--gold);
      margin-bottom: 2px;
    }
    .tg-logo em { color: #fff; font-style: normal; }
    .tg-title {
      font-family: 'Nunito', sans-serif;
      font-weight: 900;
      font-size: 1.15rem;
      color: #fff;
      margin-bottom: 3px;
    }
    .tg-date { font-size: .72rem; color: rgba(255,255,255,.45); }

    .tg-body {
      flex: 1;
      overflow-y: auto;
      padding: 20px 26px;
      scrollbar-width: thin;
      scrollbar-color: rgba(255,255,255,.2) transparent;
    }
    .tg-body::-webkit-scrollbar { width: 5px; }
    .tg-body::-webkit-scrollbar-track { background: transparent; }
    .tg-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,.2); border-radius: 99px; }

    .tg-section { margin-bottom: 20px; }
    .tg-section-title {
      font-family: 'Nunito', sans-serif;
      font-weight: 900;
      font-size: .88rem;
      color: var(--gold);
      margin-bottom: 7px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .tg-section p {
      font-size: .82rem;
      color: rgba(255,255,255,.8);
      line-height: 1.7;
    }
    .tg-section ul {
      list-style: none;
      padding: 0;
    }
    .tg-section ul li {
      font-size: .82rem;
      color: rgba(255,255,255,.8);
      line-height: 1.7;
      padding: 3px 0;
      display: flex;
      align-items: flex-start;
      gap: 7px;
    }
    .tg-section ul li::before {
      content: '›';
      color: var(--gold);
      font-weight: 900;
      flex-shrink: 0;
    }
    .tg-divider {
      height: 1px;
      background: rgba(255,255,255,.1);
      margin: 16px 0;
    }

    .tg-footer {
      padding: 16px 26px 22px;
      border-top: 1px solid rgba(255,255,255,.12);
      flex-shrink: 0;
    }
    .tg-agree-row {
      display: flex;
      align-items: center;
      gap: 12px;
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.18);
      border-radius: 12px;
      padding: 13px 16px;
      margin-bottom: 12px;
      cursor: pointer;
    }
    .tg-agree-row input[type="checkbox"] {
      width: 18px; height: 18px;
      accent-color: var(--gold);
      cursor: pointer;
      flex-shrink: 0;
    }
    .tg-agree-label {
      font-size: .84rem;
      color: rgba(255,255,255,.85);
      font-weight: 600;
      cursor: pointer;
    }
    .tg-btn-accept {
      width: 100%;
      background: var(--gold);
      color: var(--forest);
      border: none;
      border-radius: 12px;
      padding: 13px;
      font-family: 'Nunito', sans-serif;
      font-weight: 900;
      font-size: .95rem;
      cursor: pointer;
      transition: all .2s;
      opacity: .5;
      pointer-events: none;
    }
    .tg-btn-accept.ready {
      opacity: 1;
      pointer-events: all;
    }
    .tg-btn-accept.ready:hover {
      background: #f0b422;
      transform: translateY(-1px);
    }
    .tg-btn-close {
      width: 100%;
      background: rgba(255,255,255,.08);
      border: 1px solid rgba(255,255,255,.15);
      color: rgba(255,255,255,.6);
      border-radius: 12px;
      padding: 10px;
      font-family: 'Nunito', sans-serif;
      font-weight: 700;
      font-size: .82rem;
      cursor: pointer;
      margin-top: 8px;
      transition: all .18s;
    }
    .tg-btn-close:hover { background: rgba(255,255,255,.15); color: #fff; }

    @media(max-width:480px) {
      #terms-glass { border-radius: 20px 20px 0 0; }
      #terms-overlay { align-items: flex-end; padding: 0; }
    }
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

      <!-- TERMS ROW -->
      <div class="terms-row">
        <input type="checkbox" id="terms" required/>
        <label for="terms">
          I have read and agree to the
          <a href="#" onclick="openTerms(event)">Terms &amp; Conditions</a>
        </label>
      </div>
      <div id="terms-hint" style="font-size:.72rem;color:var(--muted);margin-top:4px;display:none">
        ✅ You have agreed to the Terms &amp; Conditions
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

<!-- ══════════════════════════════
     TERMS & CONDITIONS GLASS MODAL
══════════════════════════════ -->
<div id="terms-overlay" onclick="handleOverlayClick(event)">
  <div id="terms-glass">

    <!-- HEADER -->
    <div class="tg-header">
      <div class="tg-logo">SP<em>EC</em>S</div>
      <div class="tg-title">Terms &amp; Conditions</div>
      <div class="tg-date">Effective Date: March 2026 &nbsp;·&nbsp; Mbarara City, Uganda</div>
    </div>

    <!-- SCROLLABLE BODY -->
    <div class="tg-body">

      <div class="tg-section">
        <div class="tg-section-title">📋 1. About SPECS</div>
        <p>SPECS (Supermarket Pricing Estimation &amp; Comparison System) is a free price comparison platform built for shoppers in Mbarara City, Uganda. SPECS helps you compare product prices across supermarkets, track price trends, set price alerts and plan your shopping route to save money.</p>
      </div>

      <div class="tg-divider"></div>

      <div class="tg-section">
        <div class="tg-section-title">✅ 2. Acceptance of Terms</div>
        <p>By creating a SPECS account and using this system, you confirm that:</p>
        <ul>
          <li>You are at least 18 years of age or have parental consent</li>
          <li>The information you provide during registration is accurate and truthful</li>
          <li>You will use SPECS only for lawful personal shopping purposes</li>
          <li>You agree to be bound by these Terms &amp; Conditions</li>
        </ul>
      </div>

      <div class="tg-divider"></div>

      <div class="tg-section">
        <div class="tg-section-title">🛒 3. Use of the System</div>
        <p>SPECS grants you a free, non-exclusive licence to use the platform. You agree not to:</p>
        <ul>
          <li>Attempt to hack, modify or reverse-engineer the system</li>
          <li>Upload false, misleading or harmful content</li>
          <li>Use automated bots or scrapers to extract price data</li>
          <li>Impersonate another user or SPECS administrator</li>
          <li>Use the system for any commercial resale purpose without written consent</li>
        </ul>
      </div>

      <div class="tg-divider"></div>

      <div class="tg-section">
        <div class="tg-section-title">💰 4. Price Data Accuracy</div>
        <p>Prices displayed on SPECS are collected and updated by our administrators. While we strive for accuracy, SPECS does not guarantee that prices shown reflect real-time store prices at the time of your visit. Always verify prices in-store before making a purchase decision.</p>
      </div>

      <div class="tg-divider"></div>

      <div class="tg-section">
        <div class="tg-section-title">🔒 5. Privacy &amp; Data</div>
        <p>SPECS collects your name, email address and shopping preferences to provide personalised services. We do not sell your personal data to third parties. Your data is stored securely and used only to improve your experience on the platform.</p>
        <ul>
          <li>Your basket and alert data is private and visible only to you</li>
          <li>Administrators can view anonymised usage statistics</li>
          <li>You may delete your account at any time from Account Settings</li>
        </ul>
      </div>

      <div class="tg-divider"></div>

      <div class="tg-section">
        <div class="tg-section-title">🔔 6. Price Alerts</div>
        <p>Price alerts are a courtesy notification service. SPECS does not guarantee delivery of alerts at any specific time. Alert triggers depend on when administrators update prices in the system. You may set or remove alerts at any time from your dashboard.</p>
      </div>

      <div class="tg-divider"></div>

      <div class="tg-section">
        <div class="tg-section-title">⚠️ 7. Limitation of Liability</div>
        <p>SPECS is provided "as is" for informational and educational purposes. We are not liable for any financial loss, incorrect purchases or decisions made based on prices displayed on the platform. Use of SPECS is entirely at your own discretion.</p>
      </div>

      <div class="tg-divider"></div>

      <div class="tg-section">
        <div class="tg-section-title">📝 8. Changes to Terms</div>
        <p>SPECS reserves the right to update these Terms &amp; Conditions at any time. Continued use of the platform after changes are posted constitutes your acceptance of the new terms. We will notify users of significant changes via email.</p>
      </div>

      <div class="tg-divider"></div>

      <div class="tg-section">
        <div class="tg-section-title">📍 9. Governing Law</div>
        <p>These terms are governed by the laws of the Republic of Uganda. Any disputes arising from the use of SPECS shall be subject to the jurisdiction of courts in Mbarara City, Uganda.</p>
      </div>

      <div class="tg-divider"></div>

      <div class="tg-section">
        <div class="tg-section-title">📬 10. Contact</div>
        <p>For questions about these Terms &amp; Conditions, contact the SPECS team at <strong style="color:var(--gold)">admin@specs.ug</strong> or visit Bishop Stuart University, Mbarara.</p>
      </div>

    </div>

    <!-- FOOTER: AGREE + BUTTONS -->
    <div class="tg-footer">
      <div class="tg-agree-row" onclick="toggleAgree()">
        <input type="checkbox" id="modalAgree" onchange="toggleAcceptBtn()"/>
        <label class="tg-agree-label" for="modalAgree">
          I have read and understood the Terms &amp; Conditions and agree to be bound by them
        </label>
      </div>
      <button class="tg-btn-accept" id="acceptBtn" onclick="acceptTerms()">
        ✅ I Agree — Continue Registration
      </button>
      <button class="tg-btn-close" onclick="closeTerms()">
        Close without agreeing
      </button>
    </div>

  </div>
</div>

<script>
function openTerms(e) {
  e.preventDefault();
  document.getElementById('terms-overlay').classList.add('open');
  document.body.style.overflow = 'hidden';
  // Reset modal state
  document.getElementById('modalAgree').checked = false;
  document.getElementById('acceptBtn').classList.remove('ready');
}

function closeTerms() {
  document.getElementById('terms-overlay').classList.remove('open');
  document.body.style.overflow = '';
}

function handleOverlayClick(e) {
  if (e.target === document.getElementById('terms-overlay')) closeTerms();
}

function toggleAgree() {
  const cb = document.getElementById('modalAgree');
  cb.checked = !cb.checked;
  toggleAcceptBtn();
}

function toggleAcceptBtn() {
  const cb  = document.getElementById('modalAgree');
  const btn = document.getElementById('acceptBtn');
  btn.classList.toggle('ready', cb.checked);
}

function acceptTerms() {
  if (!document.getElementById('modalAgree').checked) return;

  // Tick the register form checkbox
  const regCheckbox = document.getElementById('terms');
  regCheckbox.checked = true;

  // Show hint
  const hint = document.getElementById('terms-hint');
  hint.style.display = 'block';

  closeTerms();
}

// Escape key closes
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeTerms();
});
</script>

</body>
</html>