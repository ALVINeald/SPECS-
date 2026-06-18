<?php
/**
 * api/simulate_prices.php
 * Drop into api/simulate_prices.php
 *
 * GET ?action=simulate (default) -> shifts ~50 random prices by +/-5-15%,
 *   clamped to +/-30% of the session baseline to avoid runaway drift over a long demo.
 * GET ?action=reset -> restores all prices to the session baseline, then clears it.
 *
 * Relies on the existing tr_price_update trigger (AFTER UPDATE on prices, fires when
 * OLD.price != NEW.price) to auto-log into price_history - confirmed via SHOW TRIGGERS,
 * so this file does NOT touch price_history directly.
 *
 * Uses mysqli ($conn from config/db.php) with prepared statements for every write.
 */
require_once '../includes/auth.php'; // also requires config/db.php and starts the session

// auth.php has no dedicated admin-check helper, so the guard is inline here,
// matching the $_SESSION['role'] convention set by loginUser() in auth.php.
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}

$action = $_GET['action'] ?? 'simulate';

if ($action === 'reset') {
    if (empty($_SESSION['sim_baseline'])) {
        echo json_encode(['success' => false, 'message' => 'No baseline stored yet - nothing to reset.']);
        exit;
    }

    $update = $conn->prepare("UPDATE prices SET price = ? WHERE id = ?");
    foreach ($_SESSION['sim_baseline'] as $priceId => $basePrice) {
        $priceId = (int)$priceId;
        $update->bind_param("di", $basePrice, $priceId);
        $update->execute();
    }
    unset($_SESSION['sim_baseline']);

    echo json_encode(['success' => true, 'message' => 'Prices reset to baseline.']);
    exit;
}

// action === simulate
// Lazy-capture baseline snapshot on the very first call of a session
if (empty($_SESSION['sim_baseline'])) {
    $baseline = [];
    $snapshot = $conn->query("SELECT id, price FROM prices");
    while ($row = $snapshot->fetch_assoc()) {
        $baseline[$row['id']] = (float)$row['price'];
    }
    $_SESSION['sim_baseline'] = $baseline;
}

$rows = [];
$result = $conn->query("
    SELECT p.id, p.price, pr.name AS product_name, s.name AS store_name
    FROM prices p
    JOIN products pr ON pr.id = p.product_id
    JOIN stores s ON s.id = p.store_id
    ORDER BY RAND()
    LIMIT 50
");
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$update = $conn->prepare("UPDATE prices SET price = ? WHERE id = ?");
$changes = [];

foreach ($rows as $row) {
    $priceId  = (int)$row['id'];
    $oldPrice = (float)$row['price'];
    $baseline = $_SESSION['sim_baseline'][$priceId] ?? $oldPrice;

    $pct = mt_rand(500, 1500) / 10000;        // 0.05 - 0.15
    $direction = (mt_rand(0, 1) === 1) ? 1 : -1;
    $newPrice = $oldPrice + ($oldPrice * $pct * $direction);

    // Clamp to within +/-30% of the original baseline so prices stay
    // believable even after many cycles in a long demo.
    $minBound = $baseline * 0.7;
    $maxBound = $baseline * 1.3;
    $newPrice = round(max($minBound, min($maxBound, $newPrice)), 2);

    if ($newPrice != $oldPrice) {
        $update->bind_param("di", $newPrice, $priceId);
        $update->execute();
        $changes[] = [
            'product'    => $row['product_name'],
            'store'      => $row['store_name'],
            'old_price'  => $oldPrice,
            'new_price'  => $newPrice,
            'pct_change' => round((($newPrice - $oldPrice) / $oldPrice) * 100, 1),
        ];
    }
}

echo json_encode(['success' => true, 'changes' => $changes]);
