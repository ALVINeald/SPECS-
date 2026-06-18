<?php
/**
 * api/get_live_prices.php
 * Drop into api/get_live_prices.php
 *
 * GET ?product_id=X&store_id=Y  -> single price
 * GET ?ids=1,2,3                -> multiple prices.id values, returns array
 * No params                     -> ALL current prices (id, price, product_id, store_id)
 *
 * Public, read-only endpoint - no admin check (consumer pages need this to
 * poll live prices during the demo). Uses mysqli ($conn from config/db.php).
 */
require_once '../config/db.php';
header('Content-Type: application/json');

$prices = [];

if (!empty($_GET['ids'])) {
    // Cast every id to int before use - safe to concatenate into IN() without
    // a prepared statement since nothing but validated integers reach the query.
    $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
    if (!empty($ids)) {
        $idList = implode(',', $ids);
        $result = $conn->query("SELECT id, price, product_id, store_id FROM prices WHERE id IN ($idList)");
        while ($row = $result->fetch_assoc()) {
            $prices[] = $row;
        }
    }

} elseif (!empty($_GET['product_id']) && !empty($_GET['store_id'])) {
    $productId = (int)$_GET['product_id'];
    $storeId   = (int)$_GET['store_id'];
    $stmt = $conn->prepare("SELECT id, price, product_id, store_id FROM prices WHERE product_id = ? AND store_id = ?");
    $stmt->bind_param("ii", $productId, $storeId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $prices[] = $row;
    }

} else {
    $result = $conn->query("SELECT id, price, product_id, store_id FROM prices");
    while ($row = $result->fetch_assoc()) {
        $prices[] = $row;
    }
}

echo json_encode(['success' => true, 'prices' => $prices]);
