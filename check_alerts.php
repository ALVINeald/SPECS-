<?php
// ============================================================
//  SPECS API – Check & Trigger Price Alerts
//  File: api/check_alerts.php
// ============================================================
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Admin only — can also be called by a cron job
if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']);
    exit();
}

// Get all active, untriggered alerts
$alerts = $conn->query("
    SELECT a.id, a.user_id, a.product_id, a.store_id, a.target_price,
           u.email AS user_email, u.fullname,
           p.name AS product_name, p.unit
    FROM alerts a
    JOIN users u    ON a.user_id    = u.id
    JOIN products p ON a.product_id = p.id
    WHERE a.is_active = 1 AND a.is_triggered = 0
")->fetch_all(MYSQLI_ASSOC);

$triggered = [];
$checked   = 0;

foreach ($alerts as $alert) {
    $checked++;
    $pid = $alert['product_id'];
    $sid = $alert['store_id'];

    // Get current best price
    $whereStore = $sid ? "AND pr.store_id = $sid" : "";
    $row = $conn->query("
        SELECT MIN(pr.price) AS best_price,
               s.name AS store_name
        FROM prices pr
        JOIN stores s ON pr.store_id = s.id
        WHERE pr.product_id = $pid $whereStore
        LIMIT 1
    ")->fetch_assoc();

    $currentPrice = (int)($row['best_price'] ?? 0);

    if ($currentPrice > 0 && $currentPrice <= $alert['target_price']) {
        // Mark as triggered
        $conn->query("UPDATE alerts SET is_triggered=1, triggered_at=NOW() WHERE id={$alert['id']}");

        $triggered[] = [
            'alert_id'     => $alert['id'],
            'user'         => $alert['fullname'],
            'email'        => $alert['user_email'],
            'product'      => $alert['product_name'],
            'target_price' => $alert['target_price'],
            'current_price'=> $currentPrice,
            'store'        => $row['store_name'] ?? 'N/A'
        ];
    }
}

logAdminAction($conn, 'CHECK_ALERTS', 'alerts', 0, "Checked $checked alerts, triggered " . count($triggered));

echo json_encode([
    'success'         => true,
    'alerts_checked'  => $checked,
    'alerts_triggered'=> count($triggered),
    'triggered'       => $triggered
]);
