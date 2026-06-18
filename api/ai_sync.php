<?php
// ============================================================
//  SPECS API – AI Sync
//  Suggests price updates and detects unusual price patterns
//  File: api/ai_sync.php
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

$action = $_GET['action'] ?? 'summary';

// ── PRICE ANOMALY DETECTION ───────────────────────────────────
if ($action === 'anomalies') {
    // Find products where one store is 40%+ more expensive than another
    $rows = $conn->query("
        SELECT p.id, p.name, p.unit,
               MIN(pr.price) AS min_price,
               MAX(pr.price) AS max_price,
               ROUND(((MAX(pr.price) - MIN(pr.price)) / MIN(pr.price)) * 100, 1) AS variance_pct,
               (SELECT s.name FROM prices pr2 JOIN stores s ON pr2.store_id=s.id WHERE pr2.product_id=p.id ORDER BY pr2.price DESC LIMIT 1) AS expensive_store,
               (SELECT s.name FROM prices pr3 JOIN stores s ON pr3.store_id=s.id WHERE pr3.product_id=p.id ORDER BY pr3.price ASC  LIMIT 1) AS cheap_store
        FROM prices pr
        JOIN products p ON pr.product_id = p.id
        WHERE p.active = 1
        GROUP BY p.id
        HAVING variance_pct >= 40
        ORDER BY variance_pct DESC
        LIMIT 20
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success'   => true,
        'action'    => 'anomalies',
        'count'     => count($rows),
        'anomalies' => $rows,
        'message'   => count($rows) . ' products have 40%+ price variance across stores'
    ]);
    exit();
}

// ── STALE PRICES (not updated in 30+ days) ────────────────────
if ($action === 'stale') {
    $rows = $conn->query("
        SELECT p.name, p.unit, s.name AS store_name, pr.price,
               pr.updated_at,
               DATEDIFF(NOW(), pr.updated_at) AS days_old
        FROM prices pr
        JOIN products p ON pr.product_id = p.id
        JOIN stores   s ON pr.store_id   = s.id
        WHERE DATEDIFF(NOW(), pr.updated_at) > 30
        ORDER BY days_old DESC
        LIMIT 30
    ")->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'action'  => 'stale',
        'count'   => count($rows),
        'stale'   => $rows,
        'message' => count($rows) . ' prices have not been updated in 30+ days'
    ]);
    exit();
}

// ── SUMMARY (default) ─────────────────────────────────────────
$totalProducts  = $conn->query("SELECT COUNT(*) AS t FROM products WHERE active=1")->fetch_assoc()['t'];
$totalPrices    = $conn->query("SELECT COUNT(*) AS t FROM prices")->fetch_assoc()['t'];
$totalAlerts    = $conn->query("SELECT COUNT(*) AS t FROM alerts WHERE is_active=1 AND is_triggered=0")->fetch_assoc()['t'];
$triggeredToday = $conn->query("SELECT COUNT(*) AS t FROM alerts WHERE is_triggered=1 AND DATE(triggered_at)=CURDATE()")->fetch_assoc()['t'];

$anomalyCount = $conn->query("
    SELECT COUNT(*) AS t FROM (
        SELECT p.id FROM prices pr JOIN products p ON pr.product_id=p.id
        WHERE p.active=1 GROUP BY p.id
        HAVING ROUND(((MAX(pr.price)-MIN(pr.price))/MIN(pr.price))*100,1) >= 40
    ) sub
")->fetch_assoc()['t'];

$staleCount = $conn->query("
    SELECT COUNT(*) AS t FROM prices WHERE DATEDIFF(NOW(), updated_at) > 30
")->fetch_assoc()['t'];

$recentChanges = $conn->query("
    SELECT COUNT(*) AS t FROM price_history
    WHERE changed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch_assoc()['t'];

logAdminAction($conn, 'AI_SYNC', 'system', 0, 'AI Sync summary requested');

echo json_encode([
    'success'          => true,
    'action'           => 'summary',
    'timestamp'        => date('Y-m-d H:i:s'),
    'stats' => [
        'total_products'   => (int)$totalProducts,
        'total_prices'     => (int)$totalPrices,
        'active_alerts'    => (int)$totalAlerts,
        'triggered_today'  => (int)$triggeredToday,
        'price_anomalies'  => (int)$anomalyCount,
        'stale_prices'     => (int)$staleCount,
        'changes_this_week'=> (int)$recentChanges,
    ],
    'recommendations' => [
        $anomalyCount > 0 ? "⚠️ $anomalyCount products have unusual price variance — check ?action=anomalies" : "✅ No price anomalies detected",
        $staleCount > 0   ? "⏰ $staleCount prices not updated in 30+ days — check ?action=stale"             : "✅ All prices are recent",
        $totalAlerts > 0  ? "🔔 $totalAlerts alerts waiting — run check_alerts.php to process"                : "✅ No pending alerts",
    ]
]);
