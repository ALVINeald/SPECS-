<?php
// ============================================================
//  SPECS – Admin Products Management
//  File: admin/products.php
// ============================================================
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireAdmin();

$pageTitle = 'Manage Products';

// ── HANDLE ACTIONS ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ADD PRODUCT
    if ($action === 'add') {
        $name     = $conn->real_escape_string(trim($_POST['name']));
        $unit     = $conn->real_escape_string(trim($_POST['unit']));
        $cat_id   = (int)$_POST['category_id'];
        $base     = (int)$_POST['base_price'];
        $desc     = $conn->real_escape_string(trim($_POST['description'] ?? ''));

        if ($name && $unit && $cat_id && $base) {
            $conn->query("INSERT INTO products (name,unit,category_id,base_price,description) VALUES ('$name','$unit',$cat_id,$base,'$desc')");
            $newId = $conn->insert_id;

            // Auto-insert base price for all stores
            $stores = $conn->query("SELECT id FROM stores WHERE active=1");
            while ($s = $stores->fetch_assoc()) {
                $conn->query("INSERT IGNORE INTO prices (product_id,store_id,price,updated_by) VALUES ($newId,{$s['id']},$base,{$_SESSION['user_id']})");
            }

            logAdminAction($conn, 'ADD_PRODUCT', 'product', $newId, "Added: $name ($unit)");
            setFlash('success', "Product '$name' added successfully! Set store-specific prices in the Prices tab.");
        } else {
            setFlash('error', 'Please fill in all required fields.');
        }
        redirect('products.php');
    }

    // DELETE PRODUCT
    if ($action === 'delete') {
        $pid  = (int)$_POST['product_id'];
        $name = $conn->query("SELECT name FROM products WHERE id=$pid")->fetch_assoc()['name'] ?? '';
        $conn->query("UPDATE products SET active=0 WHERE id=$pid");
        logAdminAction($conn, 'DELETE_PRODUCT', 'product', $pid, "Deactivated: $name");
        setFlash('success', "Product '$name' has been removed.");
        redirect('products.php');
    }

    // EDIT PRODUCT
    if ($action === 'edit') {
        $pid  = (int)$_POST['product_id'];
        $name = $conn->real_escape_string(trim($_POST['name']));
        $unit = $conn->real_escape_string(trim($_POST['unit']));
        $cat  = (int)$_POST['category_id'];
        $base = (int)$_POST['base_price'];
        $desc = $conn->real_escape_string(trim($_POST['description'] ?? ''));
        $conn->query("UPDATE products SET name='$name',unit='$unit',category_id=$cat,base_price=$base,description='$desc' WHERE id=$pid");
        logAdminAction($conn, 'EDIT_PRODUCT', 'product', $pid, "Edited: $name");
        setFlash('success', "Product updated successfully.");
        redirect('products.php');
    }
}

// ── GET DATA ──────────────────────────────────────────────────
$categories = getCategories($conn);
$catFilter  = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$search     = isset($_GET['q'])   ? $conn->real_escape_string(trim($_GET['q'])) : '';

$where = "WHERE p.active = 1";
if ($catFilter) $where .= " AND p.category_id = $catFilter";
if ($search)    $where .= " AND p.name LIKE '%$search%'";

$products = $conn->query("
    SELECT p.*, c.name AS cat_name,
           (SELECT MIN(pr.price) FROM prices pr WHERE pr.product_id=p.id) AS min_price,
           (SELECT MAX(pr.price) FROM prices pr WHERE pr.product_id=p.id) AS max_price
    FROM products p
    JOIN categories c ON p.category_id = c.id
    $where
    ORDER BY c.name, p.name
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div>
      <h1>🛒 Products</h1>
      <p><?= count($products) ?> products shown</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('addModal').style.display='flex'">
      ➕ Add New Product
    </button>
  </div>
</div>

<div class="ctr">
  <?php showFlash(); ?>

  <!-- SEARCH & FILTER -->
  <div class="card" style="margin-bottom:20px">
    <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1;min-width:200px">
        <label class="flabel">Search Products</label>
        <input type="text" name="q" class="finput" placeholder="e.g. Sugar, Milk..." value="<?= htmlspecialchars($search) ?>"/>
      </div>
      <div style="min-width:180px">
        <label class="flabel">Filter by Category</label>
        <select name="cat" class="finput">
          <option value="0">All Categories</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $catFilter==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-green">🔍 Search</button>
      <a href="products.php" class="btn" style="background:var(--sand)">Clear</a>
    </form>
  </div>

  <!-- PRODUCTS TABLE -->
  <div class="card">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>#</th>
            <th>Product Name</th>
            <th>Unit</th>
            <th>Category</th>
            <th>Base Price</th>
            <th>Min Price</th>
            <th>Max Price</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $i => $p): ?>
          <tr>
            <td style="color:var(--muted);font-size:.78rem"><?= $p['id'] ?></td>
            <td>
              <strong><?= htmlspecialchars($p['name']) ?></strong>
              <?php if ($p['description']): ?>
                <div style="font-size:.73rem;color:var(--muted)"><?= htmlspecialchars(substr($p['description'],0,60)) ?>...</div>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($p['unit']) ?></td>
            <td><span class="badge badge-blue"><?= htmlspecialchars($p['cat_name']) ?></span></td>
            <td><?= formatPrice($p['base_price']) ?></td>
            <td class="price-best"><?= $p['min_price'] ? formatPrice($p['min_price']) : '—' ?></td>
            <td class="price-high"><?= $p['max_price'] ? formatPrice($p['max_price']) : '—' ?></td>
            <td>
              <div style="display:flex;gap:6px">
                <button class="btn btn-sm btn-green"
                  onclick="editProduct(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', '<?= addslashes($p['unit']) ?>', <?= $p['category_id'] ?>, <?= $p['base_price'] ?>, '<?= addslashes($p['description'] ?? '') ?>')">
                  ✏️ Edit
                </button>
                <form method="POST" onsubmit="return confirm('Remove this product?')">
                  <input type="hidden" name="action"     value="delete"/>
                  <input type="hidden" name="product_id" value="<?= $p['id'] ?>"/>
                  <button type="submit" class="btn btn-sm btn-red">🗑️</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ADD PRODUCT MODAL -->
<div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--white);border-radius:var(--r);padding:28px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="font-family:'Nunito',sans-serif;font-weight:900">➕ Add New Product</h3>
      <button onclick="document.getElementById('addModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add"/>
      <div class="fgrp">
        <label class="flabel">Product Name *</label>
        <input type="text" name="name" class="finput" placeholder="e.g. White Sugar" required/>
      </div>
      <div class="fgrp">
        <label class="flabel">Unit *</label>
        <input type="text" name="unit" class="finput" placeholder="e.g. 1 kg, 500 ml, 6 pcs" required/>
      </div>
      <div class="fgrp">
        <label class="flabel">Category *</label>
        <select name="category_id" class="finput" required>
          <option value="">Select category...</option>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fgrp">
        <label class="flabel">Base Price (UGX) *</label>
        <input type="number" name="base_price" class="finput" placeholder="e.g. 4500" required/>
      </div>
      <div class="fgrp">
        <label class="flabel">Description</label>
        <input type="text" name="description" class="finput" placeholder="Brief description (optional)"/>
      </div>
      <div style="display:flex;gap:10px;margin-top:6px">
        <button type="submit" class="btn btn-primary" style="flex:1">Add Product</button>
        <button type="button" class="btn" style="background:var(--sand)" onclick="document.getElementById('addModal').style.display='none'">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT PRODUCT MODAL -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--white);border-radius:var(--r);padding:28px;width:100%;max-width:480px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
      <h3 style="font-family:'Nunito',sans-serif;font-weight:900">✏️ Edit Product</h3>
      <button onclick="document.getElementById('editModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer">×</button>
    </div>
    <form method="POST" id="editForm">
      <input type="hidden" name="action"     value="edit"/>
      <input type="hidden" name="product_id" id="editId"/>
      <div class="fgrp">
        <label class="flabel">Product Name *</label>
        <input type="text" name="name" id="editName" class="finput" required/>
      </div>
      <div class="fgrp">
        <label class="flabel">Unit *</label>
        <input type="text" name="unit" id="editUnit" class="finput" required/>
      </div>
      <div class="fgrp">
        <label class="flabel">Category *</label>
        <select name="category_id" id="editCat" class="finput" required>
          <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fgrp">
        <label class="flabel">Base Price (UGX) *</label>
        <input type="number" name="base_price" id="editPrice" class="finput" required/>
      </div>
      <div class="fgrp">
        <label class="flabel">Description</label>
        <input type="text" name="description" id="editDesc" class="finput"/>
      </div>
      <div style="display:flex;gap:10px;margin-top:6px">
        <button type="submit" class="btn btn-green" style="flex:1">Save Changes</button>
        <button type="button" class="btn" style="background:var(--sand)" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function editProduct(id, name, unit, catId, price, desc) {
  document.getElementById('editId').value    = id;
  document.getElementById('editName').value  = name;
  document.getElementById('editUnit').value  = unit;
  document.getElementById('editCat').value   = catId;
  document.getElementById('editPrice').value = price;
  document.getElementById('editDesc').value  = desc;
  document.getElementById('editModal').style.display = 'flex';
}
</script>

<?php include '../includes/footer.php'; ?>
