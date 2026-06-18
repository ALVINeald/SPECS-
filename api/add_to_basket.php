<?php
// ============================================================
//  SPECS API – Add to Basket (AJAX, no page reload)
//  File: api/add_to_basket.php
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

$uid = (int)$_SESSION['user_id'];
$pid = (int)($_POST['product_id'] ?? 0);
$qty = max(1, (int)($_POST['quantity'] ?? 1));

if (!$pid) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit();
}

// Check product exists
$product = $conn->query("SELECT id, name, unit FROM products WHERE id=$pid AND active=1")->fetch_assoc();
if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit();
}

// Add or update basket
$conn->query("
    INSERT INTO basket (user_id, product_id, quantity)
    VALUES ($uid, $pid, $qty)
    ON DUPLICATE KEY UPDATE quantity = quantity + $qty
");

// Get updated basket count
$count = getBasketCount($conn);

// Get best price for this product
$bestPrice = $conn->query("SELECT MIN(price) AS p FROM prices WHERE product_id=$pid")->fetch_assoc()['p'];

echo json_encode([
    'success'      => true,
    'message'      => "'{$product['name']}' added to basket",
    'basket_count' => $count,
    'best_price'   => (int)$bestPrice,
    'product_name' => $product['name']
]);
