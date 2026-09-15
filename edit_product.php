<?php
require 'config.php';
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['id'])) { header("Location: admin_panel.php"); exit(); }

$id = (int)$_GET['id'];

if (isset($_POST['edit_product'])) {
    csrf_check();
    $name     = trim($_POST['name']);
    $desc     = trim($_POST['description']);
    $price    = (float)$_POST['price'];
    $discount = max(0, min(100, (int)$_POST['discount_percent']));
    $cat_id   = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    $updateQuery = "UPDATE products SET category_id=?, name=?, description=?, price=?, discount_percent=?";
    $params      = [$cat_id, $name, $desc, $price, $discount];
    $types       = "issdi";

    if (!empty($_FILES["image"]["name"])) {
        if (!file_exists('uploads')) { mkdir('uploads', 0777, true); }
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES["image"]["name"]));
        $target = "uploads/" . time() . "_" . $safe;
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target)) {
            $updateQuery .= ", image=?";
            $params[] = $target;
            $types   .= "s";
        }
    }
    $updateQuery .= " WHERE id=?";
    $params[] = $id;
    $types   .= "i";

    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    $sizes = [
        'S'  => max(0, (int)($_POST['qty_S']  ?? 0)),
        'M'  => max(0, (int)($_POST['qty_M']  ?? 0)),
        'L'  => max(0, (int)($_POST['qty_L']  ?? 0)),
        'XL' => max(0, (int)($_POST['qty_XL'] ?? 0)),
    ];
    foreach ($sizes as $s => $q) {
        $chk = $conn->prepare("SELECT id FROM product_sizes WHERE product_id=? AND size=?");
        $chk->bind_param("is", $id, $s);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $chk->close();
            $u = $conn->prepare("UPDATE product_sizes SET quantity=? WHERE product_id=? AND size=?");
            $u->bind_param("iis", $q, $id, $s);
            $u->execute();
            $u->close();
        } else {
            $chk->close();
            $i = $conn->prepare("INSERT INTO product_sizes (product_id, size, quantity) VALUES (?, ?, ?)");
            $i->bind_param("isi", $id, $s, $q);
            $i->execute();
            $i->close();
        }
    }

    header("Location: admin_panel.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM products WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$product) { die("Product not found."); }

$sz_data = ['S'=>0, 'M'=>0, 'L'=>0, 'XL'=>0];
$sres = $conn->query("SELECT size, quantity FROM product_sizes WHERE product_id={$id}");
while ($s = $sres->fetch_assoc()) {
    if (array_key_exists($s['size'], $sz_data)) $sz_data[$s['size']] = (int)$s['quantity'];
}

$categories_list = get_categories($conn, false);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css?v=3">
</head>
<body>
<div class="container">
    <header>
        <span class="brand">Admin Panel</span>
        <div class="nav-links">
            <a href="admin_panel.php" class="btn btn-neutral btn-sm">Back to Inventory</a>
        </div>
    </header>

    <h2>Edit Product: <?php echo htmlspecialchars($product['name']); ?></h2>
    <form method="post" enctype="multipart/form-data" class="form-panel" style="max-width:640px;">
        <?php echo csrf_field(); ?>
        <label style="margin-top:0;">Product Name</label>
        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>

        <label>Category</label>
        <select name="category_id">
            <option value="">— Uncategorized —</option>
            <?php foreach ($categories_list as $cat):
                $selected = ((int)$product['category_id'] === (int)$cat['id']);
            ?>
                <option value="<?php echo (int)$cat['id']; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?><?php echo $cat['is_active'] ? '' : ' (hidden)'; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Description</label>
        <textarea name="description" rows="3"><?php echo htmlspecialchars($product['description']); ?></textarea>

        <div class="form-row">
            <div>
                <label>Base Price (TK)</label>
                <input type="number" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" min="0" step="0.01" required>
            </div>
            <div>
                <label>Discount %</label>
                <input type="number" name="discount_percent" value="<?php echo (int)$product['discount_percent']; ?>" min="0" max="100" required>
            </div>
        </div>

        <label class="mt-4" style="font-weight:600;">Update Stock Quantities by Size</label>
        <div class="size-grid">
            <div><label>S</label><input type="number" name="qty_S"  value="<?php echo $sz_data['S'];  ?>" min="0"></div>
            <div><label>M</label><input type="number" name="qty_M"  value="<?php echo $sz_data['M'];  ?>" min="0"></div>
            <div><label>L</label><input type="number" name="qty_L"  value="<?php echo $sz_data['L'];  ?>" min="0"></div>
            <div><label>XL</label><input type="number" name="qty_XL" value="<?php echo $sz_data['XL']; ?>" min="0"></div>
        </div>

        <label>Update Product Image (Leave blank to keep current)</label>
        <?php if ($product['image']): ?>
            <img src="<?php echo htmlspecialchars($product['image']); ?>" style="height:100px; margin:8px 0; border:1px solid #eaeaea; border-radius:4px;">
        <?php endif; ?>
        <input type="file" name="image" accept="image/*">

        <button type="submit" name="edit_product" class="btn btn-success mt-4">Save Changes</button>
    </form>
</div>
</body>
</html>