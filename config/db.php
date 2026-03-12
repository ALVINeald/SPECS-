<?php
define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'specs_db');
define('DB_CHARSET', 'utf8mb4');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset(DB_CHARSET);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

$conn->query("SET time_zone = '+03:00'");
?>
```
5. Save it

---

## STEP 5 — Set Up the Database
1. Open your browser
2. Go to:
```
http://localhost/phpmyadmin