<?php
/**
 * admin/simulator.php
 * AI Price Simulator - demo feature for SPECS presentation.
 * Drop this file into admin/simulator.php
 *
 * Confirmed against the real auth.php/db.php: uses mysqli ($conn), and
 * gates admin access via $_SESSION['role'] since auth.php has no
 * dedicated admin-check function.
 */
require_once '../includes/auth.php'; // also requires config/db.php and starts the session

// Standard admin guard (functions.php) — also sends the no-store headers
// that keep this page out of the browser's back/forward cache.
requireAdmin();

require_once '../includes/header.php';
?>

<style>
  .sim-wrap {
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1rem;
    font-family: 'Nunito', sans-serif;
  }
  .sim-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
  }
  .sim-header h1 {
    color: var(--forest);
    font-size: 1.6rem;
    margin: 0;
  }
  .sim-status {
    font-size: 0.85rem;
    font-weight: 700;
    padding: 0.35rem 0.9rem;
    border-radius: 999px;
    background: var(--sand);
    color: var(--muted);
  }
  .sim-status.live {
    background: var(--mint);
    color: #fff;
  }
  .sim-controls {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
  }
  .sim-btn {
    padding: 0.7rem 1.4rem;
    border-radius: 8px;
    border: none;
    font-weight: 700;
    font-family: 'Nunito', sans-serif;
    cursor: pointer;
    transition: transform 0.1s ease;
  }
  .sim-btn:active { transform: scale(0.97); }
  .sim-btn.start { background: var(--leaf); color: #fff; }
  .sim-btn.stop { background: var(--gold); color: #1c1a17; }
  .sim-btn.reset { background: transparent; color: var(--muted); border: 1px solid var(--sand); }
  .sim-btn:disabled { opacity: 0.5; cursor: not-allowed; }

  .sim-log {
    background: var(--cream);
    border: 1px solid var(--sand);
    border-radius: 12px;
    padding: 1rem;
    height: 420px;
    overflow-y: auto;
    font-size: 0.9rem;
  }
  .sim-log-entry {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0.25rem;
    border-bottom: 1px solid var(--sand);
    animation: fadeIn 0.3s ease;
  }
  .sim-log-entry:last-child { border-bottom: none; }
  .sim-log-product { color: var(--ink); font-weight: 600; }
  .sim-log-store { color: var(--muted); font-size: 0.8rem; }
  .sim-log-price.up { color: #c0392b; }
  .sim-log-price.down { color: var(--leaf); }
  .sim-log-empty { color: var(--muted); text-align: center; padding: 2rem 0; }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
  }
</style>

<div class="sim-wrap">
  <div class="sim-header">
    <h1>AI Price Simulator</h1>
    <span class="sim-status" id="simStatus">Idle</span>
  </div>

  <p style="color: var(--muted); margin-bottom: 1.25rem;">
    Simulates stores adjusting prices in real time (±5–15% per cycle, every 2 seconds,
    ~50 prices per cycle). Changes write straight to the live database, so the
    comparison pages and trend charts update as it runs.
  </p>

  <div class="sim-controls">
    <button class="sim-btn start" id="startBtn">Start Simulation</button>
    <button class="sim-btn stop" id="stopBtn" disabled>Stop</button>
    <button class="sim-btn reset" id="resetBtn">Reset to Baseline</button>
  </div>

  <div class="sim-log" id="simLog">
    <div class="sim-log-empty">No changes yet. Click "Start Simulation" to begin.</div>
  </div>
</div>

<script src="../assets/js/simulator.js"></script>

<?php require_once '../includes/footer.php'; ?>
