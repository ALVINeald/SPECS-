<?php
// ============================================================
//  SPECS – Logout Handler
//  File: logout.php
// ============================================================
session_start();
require_once 'includes/functions.php';

// Clear all session data
session_unset();
session_destroy();

// Redirect to login with message
header("Location: login.php?msg=You+have+been+signed+out+successfully");
exit();
?>
