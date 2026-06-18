<?php
// ============================================================
//  SPECS – API: Get prices for a product
//  File: api/get_prices.php
// ============================================================
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$pid = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if (!$pid) { echo json_encode([]); exit(); }

$rows = $conn->query("
    SELECT pr.price, s.name AS store_name, s.short_name, s.tier, s.address
    FROM prices pr
    JOIN stores s ON pr.store_id = s.id
    WHERE pr.product_id = $pid AND s.active = 1
    ORDER BY pr.price ASC
")->fetch_all(MYSQLI_ASSOC);

echo json_encode($rows);
