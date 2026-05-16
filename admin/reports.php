<?php
// ============================================================
//  SPECS – Admin Reports Page
//  File: admin/reports.php
// ============================================================
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if (!isLoggedIn()) {
    redirect('/specs/login.php');
}
if (!isAdmin()) {
    redirect('/specs/user/index.php');
}

$report_type = $_GET['report'] ?? 'price_summary';
$date_from   = $_GET['date_from'] ?? date('Y-m-01');
$date_to     = $_GET['date_to']   ?? date('Y-m-d');

// ── Handles CSV download (must run before any output) ────────
if (isset($_GET['download']) && $_GET['download'] === 'csv') {
    $filename = 'specs_report_' . $report_type . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');

    switch ($report_type) {
        case 'price_summary':
            fputcsv($out, ['Product', 'Category', 'Unit', 'Min Price (UGX)', 'Max Price (UGX)', 'Avg Price (UGX)', 'Stores Listed']);
            $rows = $conn->query("
                SELECT p.name, c.name AS category, p.unit,
                       MIN(pr.price) AS min_p, MAX(pr.price) AS max_p,
                       ROUND(AVG(pr.price)) AS avg_p, COUNT(pr.store_id) AS stores
                FROM prices pr
                JOIN products p  ON pr.product_id = p.id
                JOIN categories c ON p.category_id = c.id
                WHERE p.active = 1
                GROUP BY p.id ORDER BY p.name
            ");
            while ($r = $rows->fetch_assoc()) {
                fputcsv($out, [$r['name'], $r['category'], $r['unit'],
                               number_format($r['min_p']), number_format($r['max_p']), number_format($r['avg_p']), $r['stores']]);
            }
            break;

        case 'store_comparison':
            fputcsv($out, ['Store', 'Tier', 'Products Listed', 'Avg Price (UGX)', 'Min Price (UGX)', 'Max Price (UGX)']);
            $rows = $conn->query("
                SELECT s.name, s.tier, COUNT(pr.id) AS total,
                       ROUND(AVG(pr.price)) AS avg_p, MIN(pr.price) AS min_p, MAX(pr.price) AS max_p
                FROM prices pr
                JOIN stores s ON pr.store_id = s.id
                WHERE s.active = 1
                GROUP BY s.id ORDER BY avg_p
            ");
            while ($r = $rows->fetch_assoc()) {
                fputcsv($out, [$r['name'], $r['tier'], $r['total'],
                               number_format($r['avg_p']), number_format($r['min_p']), number_format($r['max_p'])]);
            }
            break;

        case 'price_changes':
            fputcsv($out, ['Date', 'Product', 'Store', 'Old Price', 'New Price', 'Change %', 'Reason']);
            $stmt = $conn->prepare("
                SELECT ph.changed_at, p.name AS product, s.name AS store,
                       ph.old_price, ph.new_price, ph.change_pct, ph.reason
                FROM price_history ph
                JOIN products p ON ph.product_id = p.id
                JOIN stores s   ON ph.store_id = s.id
                WHERE DATE(ph.changed_at) BETWEEN ? AND ?
                ORDER BY ph.changed_at DESC
            ");
            $stmt->bind_param('ss', $date_from, $date_to);
            $stmt->execute();
            $rows = $stmt->get_result();
            while ($r = $rows->fetch_assoc()) {
                fputcsv($out, [date('d/m/Y', strtotime($r['changed_at'])), $r['product'], $r['store'],
                               number_format($r['old_price']), number_format($r['new_price']),
                               $r['change_pct'] . '%', $r['reason'] ?? '—']);
            }
            break;

        case 'user_activity':
            fputcsv($out, ['User', 'Email', 'Role', 'Alerts Set', 'Basket Items', 'Last Login', 'Joined']);
            $rows = $conn->query("
                SELECT u.fullname, u.email, u.role,
                       (SELECT COUNT(*) FROM alerts a WHERE a.user_id = u.id)   AS alerts,
                       (SELECT COUNT(*) FROM basket b WHERE b.user_id = u.id)   AS basket,
                       u.last_login, u.created_at
                FROM users u WHERE u.is_active = 1 ORDER BY u.created_at DESC
            ");
            while ($r = $rows->fetch_assoc()) {
                fputcsv($out, [$r['fullname'], $r['email'], $r['role'],
                               $r['alerts'], $r['basket'],
                               $r['last_login'] ? date('d/m/Y', strtotime($r['last_login'])) : 'Never',
                               date('d/m/Y', strtotime($r['created_at']))]);
            }
            break;
    }
    fclose($out);
    exit;
}

// ── Fetch report data ────────────────────────────────────────
$report_data    = [];
$report_headers = [];
$report_title   = '';

switch ($report_type) {
    case 'price_summary':
        $report_title   = 'Price Summary – All Products';
        $report_headers = ['Product', 'Category', 'Unit', 'Min Price', 'Max Price', 'Avg Price', 'Stores'];
        $result = $conn->query("
            SELECT p.name, c.name AS category, p.unit,
                   MIN(pr.price) AS min_p, MAX(pr.price) AS max_p,
                   ROUND(AVG(pr.price)) AS avg_p, COUNT(pr.store_id) AS stores
            FROM prices pr
            JOIN products p  ON pr.product_id = p.id
            JOIN categories c ON p.category_id = c.id
            WHERE p.active = 1
            GROUP BY p.id ORDER BY p.name
        ");
        while ($r = $result->fetch_assoc()) {
            $report_data[] = [
                $r['name'], $r['category'], $r['unit'],
                'UGX ' . number_format($r['min_p']),
                'UGX ' . number_format($r['max_p']),
                'UGX ' . number_format($r['avg_p']),
                $r['stores']
            ];
        }
        break;

    case 'store_comparison':
        $report_title   = 'Store Price Comparison';
        $report_headers = ['Store', 'Tier', 'Products Listed', 'Avg Price', 'Min Price', 'Max Price'];
        $result = $conn->query("
            SELECT s.name, s.tier, COUNT(pr.id) AS total,
                   ROUND(AVG(pr.price)) AS avg_p, MIN(pr.price) AS min_p, MAX(pr.price) AS max_p
            FROM prices pr
            JOIN stores s ON pr.store_id = s.id
            WHERE s.active = 1
            GROUP BY s.id ORDER BY avg_p
        ");
        while ($r = $result->fetch_assoc()) {
            $report_data[] = [
                $r['name'], ucfirst($r['tier']), $r['total'],
                'UGX ' . number_format($r['avg_p']),
                'UGX ' . number_format($r['min_p']),
                'UGX ' . number_format($r['max_p'])
            ];
        }
        break;

    case 'price_changes':
        $report_title   = 'Price Changes (' . date('d M Y', strtotime($date_from)) . ' – ' . date('d M Y', strtotime($date_to)) . ')';
        $report_headers = ['Date', 'Product', 'Store', 'Old Price', 'New Price', 'Change %', 'Reason'];
        $stmt = $conn->prepare("
            SELECT ph.changed_at, p.name AS product, s.name AS store,
                   ph.old_price, ph.new_price, ph.change_pct, ph.reason
            FROM price_history ph
            JOIN products p ON ph.product_id = p.id
            JOIN stores s   ON ph.store_id = s.id
            WHERE DATE(ph.changed_at) BETWEEN ? AND ?
            ORDER BY ph.changed_at DESC
        ");
        $stmt->bind_param('ss', $date_from, $date_to);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($r = $result->fetch_assoc()) {
            $pct   = floatval($r['change_pct']);
            $badge = $pct > 0 ? '▲ ' : '▼ ';
            $report_data[] = [
                date('d/m/Y', strtotime($r['changed_at'])),
                $r['product'], $r['store'],
                'UGX ' . number_format($r['old_price']),
                'UGX ' . number_format($r['new_price']),
                $badge . abs($pct) . '%',
                $r['reason'] ?? '—'
            ];
        }
        break;

    case 'user_activity':
        $report_title   = 'User Activity Report';
        $report_headers = ['Name', 'Email', 'Role', 'Alerts', 'Basket Items', 'Last Login', 'Joined'];
        $result = $conn->query("
            SELECT u.fullname, u.email, u.role,
                   (SELECT COUNT(*) FROM alerts a WHERE a.user_id = u.id)   AS alerts,
                   (SELECT COUNT(*) FROM basket b WHERE b.user_id = u.id)   AS basket,
                   u.last_login, u.created_at
            FROM users u WHERE u.is_active = 1 ORDER BY u.created_at DESC
        ");
        while ($r = $result->fetch_assoc()) {
            $report_data[] = [
                $r['fullname'], $r['email'], ucfirst($r['role']),
                $r['alerts'], $r['basket'],
                $r['last_login'] ? date('d M Y', strtotime($r['last_login'])) : 'Never',
                date('d M Y', strtotime($r['created_at']))
            ];
        }
        break;
}

$page_title = 'Reports';
require_once '../includes/header.php';
?>

<style>
.report-card      { background:#fff; border-radius:12px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:24px; }
.report-filters   { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
.filter-group     { display:flex; flex-direction:column; gap:4px; }
.filter-group label { font-size:.78rem; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.05em; }
.filter-group select,
.filter-group input { padding:8px 12px; border:1px solid var(--sand); border-radius:8px; font-size:.9rem; color:var(--ink); background:#fff; }
.btn-generate     { background:var(--leaf); color:#fff; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer; }
.btn-generate:hover { background:var(--forest); }
.btn-csv          { background:var(--gold); color:var(--ink); border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
.btn-share        { background:var(--mint); color:#fff; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer; }
.report-actions   { display:flex; gap:10px; flex-wrap:wrap; margin-top:16px; }
.report-table-wrap{ overflow-x:auto; margin-top:20px; }
.report-table     { width:100%; border-collapse:collapse; font-size:.88rem; }
.report-table th  { background:var(--forest); color:#fff; padding:10px 14px; text-align:left; font-weight:600; white-space:nowrap; }
.report-table td  { padding:9px 14px; border-bottom:1px solid var(--sand); color:var(--ink); }
.report-table tr:hover td { background:var(--cream); }
.report-table tr:last-child td { border-bottom:none; }
.report-meta      { font-size:.82rem; color:var(--muted); margin-top:8px; }
.no-data          { text-align:center; padding:40px; color:var(--muted); }
</style>

<div class="admin-content">
    <div class="page-header">
        <h1>📊 Reports</h1>
        <p>Generate and export data reports for SPECS.</p>
    </div>

    <!-- Filter Form -->
    <div class="report-card">
        <form method="GET" action="reports.php">
            <div class="report-filters">
                <div class="filter-group">
                    <label>Report Type</label>
                    <select name="report">
                        <option value="price_summary"    <?= $report_type === 'price_summary'    ? 'selected' : '' ?>>Price Summary</option>
                        <option value="store_comparison" <?= $report_type === 'store_comparison' ? 'selected' : '' ?>>Store Comparison</option>
                        <option value="price_changes"    <?= $report_type === 'price_changes'    ? 'selected' : '' ?>>Price Changes</option>
                        <option value="user_activity"    <?= $report_type === 'user_activity'    ? 'selected' : '' ?>>User Activity</option>
                    </select>
                </div>
                <?php if (in_array($report_type, ['price_changes'])): ?>
                <div class="filter-group">
                    <label>From</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="filter-group">
                    <label>To</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <?php endif; ?>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-generate">Generate Report</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Report Results -->
    <div class="report-card">
        <h2 style="margin:0 0 4px;color:var(--forest)"><?= htmlspecialchars($report_title) ?></h2>
        <p class="report-meta"><?= count($report_data) ?> record(s) found</p>

        <div class="report-actions">
            <a href="?report=<?= urlencode($report_type) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>&download=csv"
               class="btn-csv">⬇ Download CSV</a>
            <?php if (!empty($report_data)): ?>
            <button class="btn-share" onclick="shareReport()">🔗 Share Report</button>
            <?php endif; ?>
        </div>

        <?php if (empty($report_data)): ?>
            <div class="no-data">No data found for the selected report and date range.</div>
        <?php else: ?>
        <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <tr>
                        <?php foreach ($report_headers as $h): ?>
                        <th><?= htmlspecialchars($h) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                        <td><?= htmlspecialchars($cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function shareReport() {
    const title   = <?= json_encode($report_title) ?>;
    const records = <?= count($report_data) ?>;
    const text    = `SPECS Report: ${title}\n${records} records\nGenerated on <?= date('d M Y') ?>`;
    const url     = window.location.href;

    if (navigator.share) {
        navigator.share({ title, text, url })
            .catch(err => console.log('Share cancelled', err));
    } else {
        navigator.clipboard.writeText(url).then(() => {
            alert('Report link copied to clipboard!');
        });
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>