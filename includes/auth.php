<?php
// ============================================================
//  SPECS – Authentication Handler
//  File: includes/auth.php
// ============================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── LOGIN ────────────────────────────────────────────────────
function loginUser($conn, $email, $password) {
    $email = $conn->real_escape_string(trim($email));

    // Find user by email
    $result = $conn->query("
        SELECT id, fullname, email, password_hash, role, 
               profile_picture, monthly_budget, is_active
        FROM users 
        WHERE email = '$email' 
        LIMIT 1
    ");

    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'No account found with that email address.'];
    }

    $user = $result->fetch_assoc();

    // Check if account is active
    if (!$user['is_active']) {
        return ['success' => false, 'message' => 'Your account has been deactivated. Contact admin.'];
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Incorrect password. Please try again.'];
    }

    // Set session variables
    $_SESSION['user_id']         = $user['id'];
    $_SESSION['fullname']        = $user['fullname'];
    $_SESSION['email']           = $user['email'];
    $_SESSION['role']            = $user['role'];
    $_SESSION['profile_picture'] = $user['profile_picture'];
    $_SESSION['monthly_budget']  = $user['monthly_budget'];

    // Update last login time
    $conn->query("UPDATE users SET last_login = NOW() WHERE id = {$user['id']}");

    return ['success' => true, 'role' => $user['role']];
}

// ── REGISTER ─────────────────────────────────────────────────
function registerUser($conn, $fullname, $email, $password, $confirm) {
    // Validate inputs
    $fullname = trim($fullname);
    $email    = trim($email);

    if (empty($fullname) || empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'All fields are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }

    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
    }

    if ($password !== $confirm) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }

    // Check if email already exists
    $emailSafe = $conn->real_escape_string($email);
    $check = $conn->query("SELECT id FROM users WHERE email = '$emailSafe' LIMIT 1");
    if ($check->num_rows > 0) {
        return ['success' => false, 'message' => 'An account with this email already exists.'];
    }

    // Hash password
    $hash     = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $nameSafe = $conn->real_escape_string($fullname);

    // Insert new user
    $conn->query("
        INSERT INTO users (fullname, email, password_hash, role, email_verified, is_active)
        VALUES ('$nameSafe', '$emailSafe', '$hash', 'user', 1, 1)
    ");

    if ($conn->affected_rows > 0) {
        $newId = $conn->insert_id;
        // Auto login after registration
        $_SESSION['user_id']        = $newId;
        $_SESSION['fullname']       = $fullname;
        $_SESSION['email']          = $email;
        $_SESSION['role']           = 'user';
        $_SESSION['monthly_budget'] = 0;
        return ['success' => true];
    }

    return ['success' => false, 'message' => 'Registration failed. Please try again.'];
}

// ── LOGOUT ───────────────────────────────────────────────────
function logoutUser() {
    session_unset();
    session_destroy();
    redirect('../login.php?msg=You+have+been+logged+out');
}

// ── GOOGLE OAUTH LOGIN ───────────────────────────────────────
// Handles login via Google OAuth (called after Google redirects back)
function loginWithGoogle($conn, $googleData) {
    $google_id = $conn->real_escape_string($googleData['sub']);
    $email     = $conn->real_escape_string($googleData['email']);
    $fullname  = $conn->real_escape_string($googleData['name']);
    $picture   = $conn->real_escape_string($googleData['picture'] ?? '');

    // Check if user already exists with this Google ID
    $result = $conn->query("
        SELECT * FROM users 
        WHERE oauth_provider = 'google' AND oauth_id = '$google_id'
        LIMIT 1
    ");

    if ($result->num_rows > 0) {
        // Existing Google user — log them in
        $user = $result->fetch_assoc();
        $_SESSION['user_id']         = $user['id'];
        $_SESSION['fullname']        = $user['fullname'];
        $_SESSION['email']           = $user['email'];
        $_SESSION['role']            = $user['role'];
        $_SESSION['profile_picture'] = $user['profile_picture'];
        $_SESSION['monthly_budget']  = $user['monthly_budget'];
        $conn->query("UPDATE users SET last_login = NOW() WHERE id = {$user['id']}");
        return ['success' => true, 'role' => $user['role']];
    }

    // Check if email already registered normally
    $emailCheck = $conn->query("SELECT * FROM users WHERE email = '$email' LIMIT 1");
    if ($emailCheck->num_rows > 0) {
        // Link Google to existing account
        $user = $emailCheck->fetch_assoc();
        $conn->query("
            UPDATE users 
            SET oauth_provider = 'google', oauth_id = '$google_id', 
                profile_picture = '$picture', last_login = NOW()
            WHERE id = {$user['id']}
        ");
        $_SESSION['user_id']         = $user['id'];
        $_SESSION['fullname']        = $user['fullname'];
        $_SESSION['email']           = $user['email'];
        $_SESSION['role']            = $user['role'];
        $_SESSION['profile_picture'] = $picture;
        $_SESSION['monthly_budget']  = $user['monthly_budget'];
        return ['success' => true, 'role' => $user['role']];
    }

    // New user via Google — create account
    $conn->query("
        INSERT INTO users 
            (fullname, email, oauth_provider, oauth_id, profile_picture, role, email_verified, is_active)
        VALUES 
            ('$fullname', '$email', 'google', '$google_id', '$picture', 'user', 1, 1)
    ");

    if ($conn->affected_rows > 0) {
        $newId = $conn->insert_id;
        $_SESSION['user_id']         = $newId;
        $_SESSION['fullname']        = $googleData['name'];
        $_SESSION['email']           = $googleData['email'];
        $_SESSION['role']            = 'user';
        $_SESSION['profile_picture'] = $googleData['picture'] ?? '';
        $_SESSION['monthly_budget']  = 0;
        return ['success' => true, 'role' => 'user'];
    }

    return ['success' => false, 'message' => 'Google login failed. Please try again.'];
}

// ── FORGOT PASSWORD ──────────────────────────────────────────
function forgotPassword($conn, $email) {
    $email = $conn->real_escape_string(trim($email));
    $result = $conn->query("SELECT id FROM users WHERE email = '$email' LIMIT 1");
    
    if ($result->num_rows === 0) {
        // Don't reveal if email exists (security)
        return ['success' => true, 'message' => 'If that email exists, a reset link has been sent.'];
    }

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $conn->query("
        INSERT INTO password_resets (email, token, expires_at)
        VALUES ('$email', '$token', '$expires')
    ");

    // In production: send email with reset link
    // mail($email, 'SPECS Password Reset', 'Click: http://yoursite.com/reset.php?token=' . $token);

    return ['success' => true, 'message' => 'Password reset link sent to your email.', 'token' => $token];
}

// ── RESET PASSWORD ───────────────────────────────────────────
function resetPassword($conn, $token, $newPassword, $confirm) {
    if ($newPassword !== $confirm) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }
    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters.'];
    }

    $token = $conn->real_escape_string($token);
    $result = $conn->query("
        SELECT * FROM password_resets 
        WHERE token = '$token' AND used = 0 AND expires_at > NOW()
        LIMIT 1
    ");

    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid or expired reset link.'];
    }

    $reset = $result->fetch_assoc();
    $hash  = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $email = $conn->real_escape_string($reset['email']);

    $conn->query("UPDATE users SET password_hash = '$hash' WHERE email = '$email'");
    $conn->query("UPDATE password_resets SET used = 1 WHERE token = '$token'");

    return ['success' => true, 'message' => 'Password reset successfully. You can now log in.'];
}
