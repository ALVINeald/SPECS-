<?php
// ============================================================
//  SPECS API – Get Products (search + filter)
//  File: api/get_products.php
// ============================================================
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit();
}

$search = isset($_GET['q'])   ? $conn->real_escape_string(trim($_GET['q'])) : '';
$catId  = isset($_GET['cat']) ? (int)$_GET['cat']  : 0;
$limit  = isset($_GET['limit'])? min(100, (int)$_GET['limit']) : 20;

$where = "WHERE p.active = 1";
if ($search) $where .= " AND (p.name LIKE '%$search%' OR p.description LIKE '%$search%')";
if ($catId)  $where .= " AND p.category_id = $catId";

$products = $conn->query("
    SELECT p.id, p.name, p.unit, p.description,
           c.id AS category_id, c.name AS category,
           MIN(pr.price) AS min_price,
           MAX(pr.price) AS max_price,
           (SELECT s.name FROM prices pr2 JOIN stores s ON pr2.store_id=s.id
            WHERE pr2.product_id=p.id ORDER BY pr2.price ASC LIMIT 1) AS cheapest_store
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN prices pr ON pr.product_id = p.id
    $where
    GROUP BY p.id
    ORDER BY p.name ASC
    LIMIT $limit
")->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success'  => true,
    'count'    => count($products),
    'products' => $products
]);
