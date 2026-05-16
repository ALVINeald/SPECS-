<?php
// ============================================================
//  SPECS – Helper Functions
//  File: includes/functions.php
// ============================================================

// ── FORMAT PRICE ─────────────────────────────────────────────
// Formats a number as UGX price e.g. UGX 12,500
function formatPrice($amount) {
    if ($amount === null) return 'N/A';
    return 'UGX ' . number_format($amount);
}

// ── FORMAT SHORT PRICE ────────────────────────────────────────
// Just the number with commas e.g. 12,500
function shortPrice($amount) {
    if ($amount === null) return 'N/A';
    return number_format($amount);
}

// ── SANITIZE INPUT ────────────────────────────────────────────
// Cleans user input to prevent XSS attacks
function clean($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// ── REDIRECT ─────────────────────────────────────────────────
function redirect($url) {
    header("Location: $url");
    exit();
}

// ── IS LOGGED IN ─────────────────────────────────────────────
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// ── IS ADMIN ─────────────────────────────────────────────────
function isAdmin() {
    return isset($_SESSION['role']) && 
           ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager');
}

// ── REQUIRE LOGIN ────────────────────────────────────────────
// Use at top of any page that needs login
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('../login.php?msg=Please+log+in+to+continue');
    }
}

// ── REQUIRE ADMIN ────────────────────────────────────────────
// Use at top of any admin page
function requireAdmin() {
    if (!isLoggedIn()) {
        redirect('../login.php?msg=Please+log+in');
    }
    if (!isAdmin()) {
        redirect('../user/index.php?msg=Access+denied');
    }
}

// ── GET CURRENT USER ─────────────────────────────────────────
function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'name'     => $_SESSION['fullname'],
        'email'    => $_SESSION['email'],
        'role'     => $_SESSION['role'],
        'avatar'   => $_SESSION['profile_picture'] ?? null,
        'budget'   => $_SESSION['monthly_budget'] ?? 0,
    ];
}

// ── GET AVATAR LETTER ────────────────────────────────────────
// Returns first letter of user's name for avatar display
function avatarLetter() {
    if (isset($_SESSION['fullname'])) {
        return strtoupper(substr($_SESSION['fullname'], 0, 1));
    }
    return 'U';
}

// ── GET BASKET COUNT ─────────────────────────────────────────
function getBasketCount($conn) {
    if (!isLoggedIn()) return 0;
    $uid = (int)$_SESSION['user_id'];
    $result = $conn->query("SELECT SUM(quantity) AS total FROM basket WHERE user_id = $uid");
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

// ── GET ALERTS COUNT ─────────────────────────────────────────
function getAlertsCount($conn) {
    if (!isLoggedIn()) return 0;
    $uid = (int)$_SESSION['user_id'];
    $result = $conn->query("SELECT COUNT(*) AS total FROM alerts WHERE user_id = $uid AND is_active = 1");
    $row = $result->fetch_assoc();
    return $row['total'] ?? 0;
}

// ── GET ALL CATEGORIES ───────────────────────────────────────
function getCategories($conn) {
    $result = $conn->query("SELECT * FROM categories ORDER BY name");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// ── GET ALL STORES ───────────────────────────────────────────
function getStores($conn) {
    $result = $conn->query("SELECT * FROM stores WHERE active = 1 ORDER BY name");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// ── GET BEST PRICE FOR PRODUCT ───────────────────────────────
function getBestPrice($conn, $product_id) {
    $pid = (int)$product_id;
    $result = $conn->query("
        SELECT p.price, s.name AS store_name, s.short_name
        FROM prices p
        JOIN stores s ON p.store_id = s.id
        WHERE p.product_id = $pid
        ORDER BY p.price ASC
        LIMIT 1
    ");
    return $result->fetch_assoc();
}

// ── GET ALL PRICES FOR PRODUCT ───────────────────────────────
function getProductPrices($conn, $product_id) {
    $pid = (int)$product_id;
    $result = $conn->query("
        SELECT p.price, s.id AS store_id, s.name AS store_name, s.short_name, s.tier
        FROM prices p
        JOIN stores s ON p.store_id = s.id
        WHERE p.product_id = $pid
        ORDER BY p.price ASC
    ");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// ── GENERATE STORE PLAN REFERENCE ────────────────────────────
// Generates unique receipt ref like SPECS-2025-00142
function generatePlanRef($conn) {
    $year = date('Y');
    $result = $conn->query("SELECT COUNT(*) AS total FROM store_plans");
    $row = $result->fetch_assoc();
    $num = str_pad(($row['total'] + 1), 5, '0', STR_PAD_LEFT);
    return "SPECS-$year-$num";
}

// ── TIME AGO ─────────────────────────────────────────────────
// Converts timestamp to "2 hours ago" format
function timeAgo($datetime) {
    $now  = new DateTime();
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

// ── LOG ADMIN ACTION ─────────────────────────────────────────
function logAdminAction($conn, $action, $target_type = null, $target_id = null, $details = null) {
    if (!isLoggedIn()) return;
    $admin_id    = (int)$_SESSION['user_id'];
    $action      = $conn->real_escape_string($action);
    $target_type = $target_type ? "'" . $conn->real_escape_string($target_type) . "'" : 'NULL';
    $target_id   = $target_id ? (int)$target_id : 'NULL';
    $details     = $details ? "'" . $conn->real_escape_string($details) . "'" : 'NULL';
    $ip          = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $conn->query("
        INSERT INTO admin_logs (admin_id, action, target_type, target_id, details, ip_address)
        VALUES ($admin_id, '$action', $target_type, $target_id, $details, '$ip')
    ");
}

// ── PRICE CHANGE PERCENTAGE ──────────────────────────────────
function priceChange($old, $new) {
    if ($old == 0) return 0;
    return round((($new - $old) / $old) * 100, 1);
}

// ── SET FLASH MESSAGE ────────────────────────────────────────
// Shows a one-time message on the next page
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// ── SHOW FLASH MESSAGE ───────────────────────────────────────
function showFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $color = $flash['type'] === 'success' ? '#d4edda' : 
                ($flash['type'] === 'error'   ? '#f8d7da' : '#fff3cd');
        $border = $flash['type'] === 'success' ? '#a3d4b5' :
                 ($flash['type'] === 'error'   ? '#fcc'    : '#ffd666');
        $text  = $flash['type'] === 'success' ? '#155724' :
                ($flash['type'] === 'error'   ? '#721c24' : '#856404');
        echo "<div style='background:$color;border:1.5px solid $border;color:$text;
              padding:12px 18px;border-radius:8px;margin-bottom:16px;font-size:.88rem;font-weight:600'>
              {$flash['message']}</div>";
    }
}
