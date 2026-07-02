<?php
// ============================================================
//  SPECS – Logout Handler
//  File: logout.php
// ============================================================
session_start();
require_once 'includes/functions.php';

// Clear all session data
$_SESSION = [];
session_unset();

// Delete the session cookie from the browser (not just the data)
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

// Make sure nothing about this response is cached
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Redirect to login with message
header("Location: login.php?msg=You+have+been+signed+out+successfully");
exit();
