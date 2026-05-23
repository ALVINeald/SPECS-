<?php
require_once 'config/db.php';

$passwords = [
    'admin@specs.ug'   => 'admin123',
    'alvin@specs.ug'   => 'specsmbarara2025',
    'manager@specs.ug' => 'manager123',
    'sarah@gmail.com'  => 'user123',
    'john@yahoo.com'   => 'user123',
    'grace@gmail.com'  => 'user123',
];

foreach ($passwords as $email => $pass) {
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
    $stmt->bind_param('ss', $hash, $email);
    $stmt->execute();
    echo "Updated: $email<br>";
}

echo "<br><strong>All passwords set! <a href='login.php'>Go to login</a></strong>";