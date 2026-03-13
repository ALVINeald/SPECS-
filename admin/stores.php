<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
requireAdmin();
$pageTitle = 'Manage Stores';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit') {
        $id      = (int)$_POST['store_id'];
        $name    = $conn->real_escape_string(trim($_POST['name']));
        $short   = $conn->real_escape_string(trim($_POST['short_name']));
        $addr    = $conn->real_escape_string(trim($_POST['address']));
        $tier    = $conn->real_escape_string($_POST['tier']);
        $phone   = $conn->real_escape_string(trim($_POST['phone'] ?? ''));
        $active  = isset($_POST['active']) ? 1 : 0;
        $conn->query("UPDATE stores SET name='$name',short_name='$short',address='$addr',tier='$tier',phone='$phone',active=$active WHERE id=$id");
        logAdminAction($conn, 'EDIT_STORE', 'store', $id, "Edited: $name");
        setFlash('success', "Store '$name' updated successfully.");
        redirect('stores.php');
    }

    if ($action === 'add') {
        $name  = $conn->real_escape_string(trim($_POST['name']));
        $short = $conn->real_escape_string(trim($_POST['short_name']));
        $addr  = $conn->real_escape_string(trim($_POST['address']));
        $tier  = $conn->real_escape_string($_POST['tier']);
        $conn->query("INSERT INTO stores (name,short_name,address,tier) VALUES ('$name','$short','$addr','$tier')");
        logAdminAction($conn, 'ADD_STORE', 'store', $conn->insert_id, "Added: $name");
        setFlash('success', "Store '$name' added!");
        redirect('stores.php');
    }
}

$stores = $conn->query("
    SELECT s.*,
      (SELECT COUNT(DISTINCT pr.product_id) FROM prices pr WHERE pr.store_id=s.id) AS product_count
    FROM stores s ORDER BY s.tier DESC, s.name
")->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>

<div class="ph">
  <div style="max-width:1240px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
    <div><h1>🏬 Stores</h1><p>Manage Mbarara supermarkets</p></div>
    <button class="btn btn-primary" onclick="document.getElementById('addStoreModal').style.display='flex'">➕ Add Store</button>
  </div>
</div>

<div class="ctr">
  <?php showFlash(); ?>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px">
    <?php foreach ($stores as $s):
      $tierColors = ['premium'=>'#856404','mid'=>'#004085','budget'=>'#155724','market'=>'#721c24'];
      $tierBg     = ['premium'=>'#fff3cd','mid'=>'#cce5ff','budget'=>'#d4edda','market'=>'#f8d7da'];
      $tc = $tierColors[$s['tier']] ?? '#333';
      $tb = $tierBg[$s['tier']]     ?? '#eee';
    ?>
    <div style="background:var(--white);border-radius:var(--r);border:1.5px solid var(--sand);padding:22px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
        <div>
          <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1rem"><?= htmlspecialchars($s['name']) ?></div>
          <div style="font-size:.76rem;color:var(--muted)"><?= htmlspecialchars($s['address']) ?></div>
        </div>
        <span style="background:<?= $tb ?>;color:<?= $tc ?>;font-size:.66rem;font-weight:800;padding:3px 10px;border-radius:99px;text-transform:uppercase">
          <?= $s['tier'] ?>
        </span>
      </div>
      <div style="display:flex;gap:14px;margin-bottom:16px">
        <div style="text-align:center">
          <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.2rem;color:var(--forest)"><?= $s['product_count'] ?></div>
          <div style="font-size:.7rem;color:var(--muted)">Products</div>
        </div>
        <div style="text-align:center">
          <div style="font-family:'Nunito',sans-serif;font-weight:900;font-size:1.2rem;color:<?= $s['active'] ? 'var(--leaf)' : 'var(--red)' ?>">
            <?= $s['active'] ? '✅' : '❌' ?>
          </div>
          <div style="font-size:.7rem;color:var(--muted)"><?= $s['active'] ? 'Active' : 'Inactive' ?></div>
        </div>
        <?php if ($s['phone']): ?>
        <div style="text-align:center">
          <div style="font-size:.8rem;font-weight:700"><?= htmlspecialchars($s['phone']) ?></div>
          <div style="font-size:.7rem;color:var(--muted)">Phone</div>
        </div>
        <?php endif; ?>
      </div>
      <button class="btn btn-sm btn-green" style="width:100%"
        onclick="editStore(<?= $s['id'] ?>, '<?= addslashes($s['name']) ?>', '<?= addslashes($s['short_name']) ?>', '<?= addslashes($s['address']) ?>', '<?= $s['tier'] ?>', '<?= addslashes($s['phone']??'') ?>', <?= $s['active'] ?>)">
        ✏️ Edit Store
      </button>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- EDIT MODAL -->
<div id="editStoreModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--white);border-radius:var(--r);padding:28px;width:100%;max-width:460px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3 style="font-family:'Nunito',sans-serif;font-weight:900">✏️ Edit Store</h3>
      <button onclick="document.getElementById('editStoreModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action"   value="edit"/>
      <input type="hidden" name="store_id" id="esId"/>
      <div class="fgrp"><label class="flabel">Store Name</label><input type="text" name="name" id="esName" class="finput" required/></div>
      <div class="fgrp"><label class="flabel">Short Name</label><input type="text" name="short_name" id="esShort" class="finput" required/></div>
      <div class="fgrp"><label class="flabel">Address</label><input type="text" name="address" id="esAddr" class="finput"/></div>
      <div class="fgrp">
        <label class="flabel">Tier</label>
        <select name="tier" id="esTier" class="finput">
          <option value="premium">Premium</option>
          <option value="mid">Mid</option>
          <option value="budget">Budget</option>
          <option value="market">Market</option>
        </select>
      </div>
      <div class="fgrp"><label class="flabel">Phone</label><input type="text" name="phone" id="esPhone" class="finput" placeholder="Optional"/></div>
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
        <input type="checkbox" name="active" id="esActive"/>
        <label for="esActive" style="font-weight:700;font-size:.88rem">Store is Active</label>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-green" style="flex:1">Save Changes</button>
        <button type="button" class="btn" style="background:var(--sand)" onclick="document.getElementById('editStoreModal').style.display='none'">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD MODAL -->
<div id="addStoreModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--white);border-radius:var(--r);padding:28px;width:100%;max-width:460px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
      <h3 style="font-family:'Nunito',sans-serif;font-weight:900">➕ Add New Store</h3>
      <button onclick="document.getElementById('addStoreModal').style.display='none'" style="background:none;border:none;font-size:1.4rem;cursor:pointer">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add"/>
      <div class="fgrp"><label class="flabel">Store Name *</label><input type="text" name="name" class="finput" required/></div>
      <div class="fgrp"><label class="flabel">Short Name *</label><input type="text" name="short_name" class="finput" placeholder="e.g. Fresco" required/></div>
      <div class="fgrp"><label class="flabel">Address</label><input type="text" name="address" class="finput"/></div>
      <div class="fgrp">
        <label class="flabel">Tier</label>
        <select name="tier" class="finput">
          <option value="premium">Premium</option>
          <option value="mid">Mid</option>
          <option value="budget">Budget</option>
          <option value="market">Market</option>
        </select>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" style="flex:1">Add Store</button>
        <button type="button" class="btn" style="background:var(--sand)" onclick="document.getElementById('addStoreModal').style.display='none'">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
function editStore(id,name,short,addr,tier,phone,active) {
  document.getElementById('esId').value    = id;
  document.getElementById('esName').value  = name;
  document.getElementById('esShort').value = short;
  document.getElementById('esAddr').value  = addr;
  document.getElementById('esTier').value  = tier;
  document.getElementById('esPhone').value = phone;
  document.getElementById('esActive').checked = active == 1;
  document.getElementById('editStoreModal').style.display = 'flex';
}
</script>
<?php include '../includes/footer.php'; ?>
