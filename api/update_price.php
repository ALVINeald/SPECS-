<?php
// ============================================================
//  SPECS API – Update Price
//  File: api/update_price.php
// ============================================================
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Admin only
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']);
    exit();
}

$pid      = (int)($_POST['product_id'] ?? 0);
$sid      = (int)($_POST['store_id']   ?? 0);
$newPrice = (int)($_POST['new_price']  ?? 0);
$uid      = (int)$_SESSION['user_id'];

if (!$pid || !$sid || $newPrice <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

// Get old price
$old = $conn->query("SELECT price FROM prices WHERE product_id=$pid AND store_id=$sid")->fetch_assoc();
$oldPrice = $old ? (int)$old['price'] : 0;

// Update price
$conn->query("
    INSERT INTO prices (product_id, store_id, price, updated_by)
    VALUES ($pid, $sid, $newPrice, $uid)
    ON DUPLICATE KEY UPDATE price=$newPrice, updated_by=$uid, updated_at=NOW()
");

// Log the change
if ($oldPrice && $oldPrice != $newPrice) {
    $pct = round((($newPrice - $oldPrice) / $oldPrice) * 100, 2);
    $conn->query("
        INSERT INTO price_history (product_id, store_id, old_price, new_price, change_pct, reason, changed_by)
        VALUES ($pid, $sid, $oldPrice, $newPrice, $pct, 'API update', $uid)
    ");
    // Trigger alert check
    $conn->query("CALL sp_check_alerts($pid, $sid, $newPrice)");
}

logAdminAction($conn, 'UPDATE_PRICE', 'price', $pid, "Via API: Store #$sid UGX $oldPrice → $newPrice");

echo json_encode([
    'success'   => true,
    'message'   => 'Price updated',
    'old_price' => $oldPrice,
    'new_price' => $newPrice,
    'change_pct'=> $oldPrice ? round((($newPrice - $oldPrice) / $oldPrice) * 100, 1) : 0
]);
