<?php
// ============================================================
//  SPECS API – Set Price Alert
//  File: api/set_alert.php
// ============================================================
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in first']);
    exit();
}

$uid     = (int)$_SESSION['user_id'];
$pid     = (int)($_POST['product_id']   ?? 0);
$sid     = $_POST['store_id'] ? (int)$_POST['store_id'] : null;
$target  = (int)($_POST['target_price'] ?? 0);

if (!$pid || $target <= 0) {
    echo json_encode(['success' => false, 'message' => 'Product and target price are required']);
    exit();
}

// Check product exists
$product = $conn->query("SELECT name, unit FROM products WHERE id=$pid AND active=1")->fetch_assoc();
if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit();
}

// Check duplicate alert
$exists = $conn->query("SELECT id FROM alerts WHERE user_id=$uid AND product_id=$pid AND is_active=1")->num_rows;
if ($exists) {
    echo json_encode(['success' => false, 'message' => "You already have an active alert for '{$product['name']}'."]);
    exit();
}

$sidVal = $sid ? $sid : 'NULL';
$conn->query("INSERT INTO alerts (user_id, product_id, store_id, target_price) VALUES ($uid, $pid, $sidVal, $target)");
$alertId = $conn->insert_id;

// Get current best price so user can see how far they are
$currentBest = $conn->query("SELECT MIN(price) AS p FROM prices WHERE product_id=$pid")->fetch_assoc()['p'];
$met = $currentBest && $currentBest <= $target;

echo json_encode([
    'success'      => true,
    'alert_id'     => $alertId,
    'product_name' => $product['name'],
    'target_price' => $target,
    'current_best' => (int)$currentBest,
    'already_met'  => $met,
    'message'      => $met
        ? "Alert set! The price is already at your target at {$currentBest} UGX."
        : "Alert set! You will be notified when '{$product['name']}' drops to UGX " . number_format($target) . "."
]);
