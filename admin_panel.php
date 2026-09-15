<?php
require 'config.php';
// Check if admin is logged in (using 'admin' session from your login.php)
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }

// Create uploads directory if missing
if (!file_exists('uploads')) { mkdir('uploads', 0777, true); }

// Add Product & Initial Size
if (isset($_POST['add_product'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $desc = $conn->real_escape_string($_POST['description']);
    $price = $_POST['price'];
    $discount = (int)$_POST['discount_percent'];
    
    // Image Upload
    $imagePath = "";
    if (!empty($_FILES["image"]["name"])) {
        $target = "uploads/" . time() . "_" . basename($_FILES["image"]["name"]);
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target)) {
            $imagePath = $target;
        }
    }

    $conn->query("INSERT INTO products (name, description, price, discount_percent, image) 
                  VALUES ('$name', '$desc', '$price', '$discount', '$imagePath')");
    $pid = $conn->insert_id;
    
    // Insert Size Quantities
    $sizes = ['S' => $_POST['qty_S'], 'M' => $_POST['qty_M'], 'L' => $_POST['qty_L'], 'XL' => $_POST['qty_XL']];
    foreach($sizes as $sz => $qty) {
        $qty = (int)$qty;
        $conn->query("INSERT INTO product_sizes (product_id, size, quantity) VALUES ($pid, '$sz', $qty)");
    }
}

// --- Delete Product ---
if (isset($_GET['del_product'])) {
    $id = (int)$_GET['del_product'];
    $conn->query("DELETE FROM products WHERE id=$id");
    $conn->query("DELETE FROM product_sizes WHERE product_id=$id"); // Clean up associated sizes
    header("Location: admin_panel.php");
    exit();
}

// --- Update Order Status ---
if (isset($_POST['update_order_status'])) {
    $order_id = (int)$_POST['order_id'];
    $status = $conn->real_escape_string($_POST['status']);
    $conn->query("UPDATE orders SET status='$status' WHERE id=$order_id");
}

// --- Delete Order ---
if (isset($_GET['del_order'])) {
    $id = (int)$_GET['del_order'];
    $conn->query("DELETE FROM orders WHERE id=$id");
    $conn->query("DELETE FROM order_items WHERE order_id=$id"); // Clean up associated items
    header("Location: admin_panel.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css">
</head>
<body>
<div class="container" style="max-width: 1400px;">
    <header>
        <div class="brand">Admin Dashboard</div>
        <div class="nav-links">
            <a href="index.php" target="_blank">View Store</a>
            <a href="logout.php">Logout</a>
        </div>
    </header>

    <h2>Add New Product</h2>
    <form method="post" enctype="multipart/form-data" style="background:#fff; padding:20px; border:1px solid #eaeaea;">
        <input type="text" name="name" placeholder="Product Name" required>
        <textarea name="description" placeholder="Description" rows="3"></textarea>
        
        <div style="display: flex; gap: 10px;">
            <input type="number" name="price" placeholder="Base Price (TK)" required>
            <input type="number" name="discount_percent" placeholder="Discount % (e.g. 10 for 10% off)" value="0" min="0" max="100" required>
        </div>
        
        <label style="display:block; margin-top:15px; font-weight: 600; color:#333;">Stock Quantities by Size:</label>
        <div style="display:flex; gap:10px; margin-bottom: 10px; background: #f9f9f9; padding: 10px; border: 1px solid #eee;">
            <input type="number" name="qty_S" placeholder="Size S Qty" value="0" min="0">
            <input type="number" name="qty_M" placeholder="Size M Qty" value="0" min="0">
            <input type="number" name="qty_L" placeholder="Size L Qty" value="0" min="0">
            <input type="number" name="qty_XL" placeholder="Size XL Qty" value="0" min="0">
        </div>

        <label style="display:block; margin-top:10px;">Product Image:</label>
        <input type="file" name="image" accept="image/*" style="margin:10px 0;"><br>
        <button type="submit" name="add_product" class="btn">Upload Product</button>
    </form>

    <hr style="border: 0; border-top: 1px solid #eaeaea; margin: 40px 0;">

    <h2>Order Management</h2>
    <div style="overflow-x: auto;">
        <table>
            <tr>
                <th>Order #</th>
                <th>Customer ID</th>
                <th>Address</th>
                <th>Items Ordered</th>
                <th>Total</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php
            $orders = $conn->query("SELECT * FROM orders ORDER BY created_at DESC");
            while ($o = $orders->fetch_assoc()) {
                
                // Fetch specific items for this order
                $o_id = $o['id'];
                $items_res = $conn->query("SELECT oi.size, oi.qty, p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $o_id");
                
                $items_html = "";
                while ($item = $items_res->fetch_assoc()) {
                    $p_name = $item['name'] ? $item['name'] : 'Deleted Product';
                    $items_html .= "<div style='margin-bottom: 8px; border-bottom: 1px dashed #ccc; padding-bottom: 5px;'>
                                        <strong>{$p_name}</strong><br>
                                        <span style='color: #444; font-size: 13px;'>Size: <b>{$item['size']}</b> | Qty: <b>{$item['qty']}</b></span>
                                    </div>";
                }

                echo "<tr>
                        <td><strong>#{$o['id']}</strong><br><small>{$o['created_at']}</small></td>
                        <td>User #{$o['user_id']}</td>
                        <td style='max-width: 200px;'>{$o['address']}</td>
                        <td style='min-width: 250px;'>{$items_html}</td>
                        <td style='font-weight: bold;'>{$o['total']} TK</td>
                        <td>
                            <form method='post' style='display:flex; gap: 5px;'>
                                <input type='hidden' name='order_id' value='{$o['id']}'>
                                <select name='status' style='padding:5px; border: 1px solid #ccc;'>
                                    <option value='Pending' " . ($o['status']=='Pending'?'selected':'') . ">Pending</option>
                                    <option value='Confirmed' " . ($o['status']=='Confirmed'?'selected':'') . ">Confirmed</option>
                                    <option value='Shipped' " . ($o['status']=='Shipped'?'selected':'') . ">Shipped</option>
                                    <option value='Delivered' " . ($o['status']=='Delivered'?'selected':'') . ">Delivered</option>
                                </select>
                                <button type='submit' name='update_order_status' class='btn' style='padding:5px 10px; font-size: 12px;'>Update</button>
                            </form>
                        </td>
                        <td>
                            <a href='?del_order={$o['id']}' class='btn btn-danger' style='padding:5px 10px; font-size: 12px;'>Delete</a>
                        </td>
                      </tr>";
            }
            ?>
        </table>
    </div>

    <hr style="border: 0; border-top: 1px solid #eaeaea; margin: 40px 0;">

    <h2>Inventory</h2>
    <div style="overflow-x: auto;">
        <table>
            <tr>
                <th>Image</th>
                <th>Name</th>
                <th>Available Sizes & Stock</th>
                <th>Price</th>
                <th>Action</th>
            </tr>
            <?php
            $prods = $conn->query("SELECT * FROM products ORDER BY id DESC");
            while ($p = $prods->fetch_assoc()) {
                $img = $p['image'] ? "<img src='{$p['image']}' height='60' style='border-radius:4px; object-fit:cover; width:60px;'>" : "No Img";
                
                // Fetch sizes for this product
                $p_id = $p['id'];
                $sizes_res = $conn->query("SELECT * FROM product_sizes WHERE product_id = $p_id");
                $sizes_html = "";
                while($sz = $sizes_res->fetch_assoc()){
                     $sizes_html .= "<span style='display:inline-block; background:#eee; padding:3px 8px; border-radius:12px; margin:2px; font-size:12px;'>Size {$sz['size']}: <b>{$sz['quantity']}</b></span>";
                }
                if(empty($sizes_html)) $sizes_html = "<span style='color:red;'>Out of stock / No sizes</span>";

                echo "<tr>
                        <td>{$img}</td>
                        <td style='font-weight: 500;'>{$p['name']}</td>
                        <td>{$sizes_html}</td>
                        <td>{$p['price']} TK</td>
                        <td style='display: flex; gap: 8px; align-items: center;'>
                            <a href='edit_product.php?id={$p['id']}' class='btn' style='padding:5px 10px; font-size: 12px; background-color: #28a745; color: #fff; text-decoration: none; border-radius: 4px;'>Edit</a>
                            <a href='?del_product={$p['id']}' class='btn btn-danger' style='padding:5px 10px; font-size: 12px;' onclick=\"return confirm('Are you sure you want to delete this product?');\">Delete</a>
                        </td>
                      </tr>";
            }
            ?>
        </table>
    </div>

</div>
</body>
</html>