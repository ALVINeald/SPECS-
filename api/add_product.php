<?php
// ============================================================
//  SPECS API – Add Product
//  File: api/add_product.php
// ============================================================
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorised']);
    exit();
}

$name    = trim($_POST['name']        ?? '');
$unit    = trim($_POST['unit']        ?? '');
$catId   = (int)($_POST['category_id'] ?? 0);
$base    = (int)($_POST['base_price']  ?? 0);
$desc    = trim($_POST['description']  ?? '');
$uid     = (int)$_SESSION['user_id'];

if (!$name || !$unit || !$catId || !$base) {
    echo json_encode(['success' => false, 'message' => 'Name, unit, category and base price are required']);
    exit();
}

// Check duplicate
$exists = $conn->query("SELECT id FROM products WHERE name='" . $conn->real_escape_string($name) . "' AND active=1")->num_rows;
if ($exists) {
    echo json_encode(['success' => false, 'message' => "Product '$name' already exists"]);
    exit();
}

$nameE = $conn->real_escape_string($name);
$unitE = $conn->real_escape_string($unit);
$descE = $conn->real_escape_string($desc);

$conn->query("INSERT INTO products (name, unit, category_id, base_price, description) VALUES ('$nameE','$unitE',$catId,$base,'$descE')");
$newId = $conn->insert_id;

// Auto-insert base price for all active stores
$stores = $conn->query("SELECT id FROM stores WHERE active=1");
$inserted = 0;
while ($s = $stores->fetch_assoc()) {
    $conn->query("INSERT IGNORE INTO prices (product_id, store_id, price, updated_by) VALUES ($newId, {$s['id']}, $base, $uid)");
    $inserted++;
}

logAdminAction($conn, 'ADD_PRODUCT', 'product', $newId, "Via API: $name ($unit)");

echo json_encode([
    'success'        => true,
    'message'        => "Product '$name' added",
    'product_id'     => $newId,
    'stores_seeded'  => $inserted
]);
