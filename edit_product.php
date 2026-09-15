<?php
require 'config.php';
if (!isset($_SESSION['admin'])) { header("Location: admin_login.php"); exit(); }

if (!isset($_GET['id'])) { header("Location: admin_panel.php"); exit(); }
$id = (int)$_GET['id'];

// Handle the update request
if (isset($_POST['edit_product'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $desc = $conn->real_escape_string($_POST['description']);
    $price = $_POST['price'];
    $discount = (int)$_POST['discount_percent'];
    
    // Base update query
    $updateQuery = "UPDATE products SET name='$name', description='$desc', price='$price', discount_percent='$discount'";
    
    if (!empty($_FILES["image"]["name"])) {
        if (!file_exists('uploads')) { mkdir('uploads', 0777, true); }
        $target = "uploads/" . time() . "_" . basename($_FILES["image"]["name"]);
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target)) {
            $updateQuery .= ", image='$target'";
        }
    }
    
    $updateQuery .= " WHERE id=$id";
    $conn->query($updateQuery);
    
    // Update Sizes
    $sizes = ['S' => $_POST['qty_S'], 'M' => $_POST['qty_M'], 'L' => $_POST['qty_L'], 'XL' => $_POST['qty_XL']];
    foreach($sizes as $sz => $qty) {
        $qty = (int)$qty;
        $chk = $conn->query("SELECT id FROM product_sizes WHERE product_id=$id AND size='$sz'");
        if($chk->num_rows > 0) {
            $conn->query("UPDATE product_sizes SET quantity=$qty WHERE product_id=$id AND size='$sz'");
        } else {
            $conn->query("INSERT INTO product_sizes (product_id, size, quantity) VALUES ($id, '$sz', $qty)");
        }
    }
    
    header("Location: admin_panel.php");
    exit();
}

$product = $conn->query("SELECT * FROM products WHERE id=$id")->fetch_assoc();
if(!$product) { die("Product not found."); }

// Fetch current size quantities
$sz_data = ['S'=>0, 'M'=>0, 'L'=>0, 'XL'=>0];
$s_res = $conn->query("SELECT size, quantity FROM product_sizes WHERE product_id=$id");
while($s = $s_res->fetch_assoc()) {
    $sz_data[$s['size']] = $s['quantity'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Product - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css">
</head>
<body>
<div class="container">
    <header>
        <span class="brand">Admin Panel</span>
        <div class="nav-links">
            <a href="admin_panel.php" class="btn" style="background:#555;">Back to Inventory</a>
        </div>
    </header>

    <h2>Edit Product: <?php echo $product['name']; ?></h2>
    <form method="post" enctype="multipart/form-data" style="background:#fff; padding:20px; border:1px solid #eaeaea; max-width: 600px;">
        <label>Product Name</label>
        <input type="text" name="name" value="<?php echo $product['name']; ?>" required>
        
        <label>Description</label>
        <textarea name="description" rows="3"><?php echo $product['description']; ?></textarea>
        
        <div style="display: flex; gap: 10px;">
            <div style="flex: 1;">
                <label>Base Price (TK)</label>
                <input type="number" name="price" value="<?php echo $product['price']; ?>" required>
            </div>
            <div style="flex: 1;">
                <label>Discount %</label>
                <input type="number" name="discount_percent" value="<?php echo $product['discount_percent']; ?>" min="0" max="100" required>
            </div>
        </div>
        
        <label style="display:block; margin-top:15px; font-weight: 600; color:#333;">Update Stock Quantities by Size:</label>
        <div style="display:flex; gap:10px; margin-bottom: 10px; background: #f9f9f9; padding: 10px; border: 1px solid #eee;">
            <div style="flex:1;"><label style="font-size: 12px;">S</label><input type="number" name="qty_S" value="<?php echo $sz_data['S']; ?>" min="0"></div>
            <div style="flex:1;"><label style="font-size: 12px;">M</label><input type="number" name="qty_M" value="<?php echo $sz_data['M']; ?>" min="0"></div>
            <div style="flex:1;"><label style="font-size: 12px;">L</label><input type="number" name="qty_L" value="<?php echo $sz_data['L']; ?>" min="0"></div>
            <div style="flex:1;"><label style="font-size: 12px;">XL</label><input type="number" name="qty_XL" value="<?php echo $sz_data['XL']; ?>" min="0"></div>
        </div>
        
        <label style="display:block; margin-top:15px;">Update Product Image (Leave blank to keep current):</label><br>
        <?php if($product['image']): ?>
            <img src="<?php echo $product['image']; ?>" height="100" style="margin: 10px 0; border: 1px solid #ccc;"><br>
        <?php endif; ?>
        <input type="file" name="image" accept="image/*" style="margin:10px 0;"><br>
        
        <button type="submit" name="edit_product" class="btn btn-success" style="margin-top: 10px;">Save Changes</button>
    </form>
</div>
</body>
</html>