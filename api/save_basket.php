<?php
// ============================================================
//  SPECS API – Save Basket as Shopping Plan
//  File: api/save_basket.php
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
$storeId = (int)($_POST['store_id'] ?? 0);

if (!$storeId) {
    echo json_encode(['success' => false, 'message' => 'Store is required']);
    exit();
}

// Get basket items
$items = $conn->query("
    SELECT b.quantity, p.name, p.unit,
           COALESCE(
               (SELECT pr.price FROM prices pr WHERE pr.product_id=b.product_id AND pr.store_id=$storeId LIMIT 1),
               (SELECT MIN(pr2.price) FROM prices pr2 WHERE pr2.product_id=b.product_id)
           ) AS price
    FROM basket b
    JOIN products p ON b.product_id = p.id
    WHERE b.user_id = $uid
")->fetch_all(MYSQLI_ASSOC);

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Basket is empty']);
    exit();
}

$total    = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $items));
$ref      = generatePlanRef($conn);
$json     = $conn->real_escape_string(json_encode($items));

// Max total for savings calculation
$maxTotal = $conn->query("
    SELECT SUM(max_p * b.quantity) AS t FROM basket b
    JOIN (SELECT product_id, MAX(price) AS max_p FROM prices GROUP BY product_id) mp
    ON mp.product_id = b.product_id
    WHERE b.user_id = $uid
")->fetch_assoc()['t'] ?? $total;

$savings  = max(0, $maxTotal - $total);
$storeName = $conn->query("SELECT name FROM stores WHERE id=$storeId")->fetch_assoc()['name'] ?? '';

$conn->query("
    INSERT INTO store_plans (user_id, store_id, plan_ref, items_json, total_amount, savings)
    VALUES ($uid, $storeId, '$ref', '$json', $total, $savings)
");

echo json_encode([
    'success'    => true,
    'plan_ref'   => $ref,
    'store_name' => $storeName,
    'total'      => (int)$total,
    'savings'    => (int)$savings,
    'items'      => $items,
    'message'    => "Shopping plan $ref saved!"
]);
