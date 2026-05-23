<?php
require_once 'config/db.php';

$result = $conn->query("SELECT COUNT(*) AS total FROM products");
$row = $result->fetch_assoc();

$result2 = $conn->query("SELECT COUNT(*) AS total FROM prices");
$row2 = $result2->fetch_assoc();

$result3 = $conn->query("SELECT COUNT(*) AS total FROM stores");
$row3 = $result3->fetch_assoc();

echo "<h2>SPECS Database Test</h2>";
echo "✅ Database connected!<br><br>";
echo "📦 Products: <strong>" . $row['total'] . "</strong><br>";
echo "💰 Prices: <strong>" . $row2['total'] . "</strong><br>";
echo "🏬 Stores: <strong>" . $row3['total'] . "</strong><br>";
?>
```

**6.** Save the file

**7.** Make sure **Laragon is running** — open Laragon and click **Start All**

**8.** Open your browser and go to:
```
http://localhost/specs/test.php