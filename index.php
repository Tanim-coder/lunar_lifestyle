<?php
require 'config.php';

// Handle Add to Cart directly from the homepage
if (isset($_POST['add_to_cart'])) {
    $id = (int)$_POST['product_id'];
    $size = isset($_POST['size']) ? $_POST['size'] : ''; // Fallback if size is missing
    
    // FIX: Initialize the cart session array if it doesn't exist
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $cart_key = $id . '_' . $size; // Unique key for Product + Size combo
    
    if (!isset($_SESSION['cart'][$cart_key])) {
        $_SESSION['cart'][$cart_key] = ['id' => $id, 'size' => $size, 'qty' => 1];
    } else {
        $_SESSION['cart'][$cart_key]['qty']++;
    }
    header("Location: checkout.php");
    exit();
}

// FIX: Calculate total item quantity for the cart counter
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['qty'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css">
    <style>
        /* Quick style for the size dropdown on the homepage */
        .grid-size-select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            font-family: 'Inter', sans-serif;
            background-color: #f9f9f9;
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <a href="index.php" class="brand">Lunar Lifestyle</a>
        <div class="nav-links">
            <a href="checkout.php">Cart (<?php echo $cart_count; ?>)</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="account.php">My Account</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="product-grid">
        <?php
        // Fetch products that have at least one size in stock
        $result = $conn->query("SELECT p.*, SUM(ps.quantity) as total_qty 
                                FROM products p 
                                LEFT JOIN product_sizes ps ON p.id = ps.product_id 
                                GROUP BY p.id HAVING total_qty > 0 OR total_qty IS NULL");
                                
        while ($row = $result->fetch_assoc()) {
            $img = !empty($row['image']) ? $row['image'] : 'https://placehold.co/400x500?text=No+Image';
            $sale_price = $row['price'] - ($row['price'] * $row['discount_percent'] / 100);
            
            echo "<div class='product-card'>";
            
            // CLICKABLE AREA (To go Details Page)
            echo "<a href='product.php?id={$row['id']}' style='text-decoration:none; display:block;'>";
            echo "<img src='{$img}' alt='{$row['name']}'>";
            echo "<div class='product-title'>{$row['name']}</div>";
            
            if ($row['discount_percent'] > 0) {
                echo "<div class='product-price'><span class='original-price'>{$row['price']} TK</span> <span class='sale-price'>{$sale_price} TK</span><span class='discount-badge'>-{$row['discount_percent']}%</span></div>";
            } else {
                echo "<div class='product-price' style='font-weight:bold; color:#000;'>{$row['price']} TK</div>";
            }
            echo "</a>";

            // QUICK ADD TO CART FORM
            echo "<form method='post' action='index.php' style='margin-top: 15px;'>";
            echo "<input type='hidden' name='product_id' value='{$row['id']}'>";
            
            // Fetch available sizes for this specific product
            $sizes_res = $conn->query("SELECT size, quantity FROM product_sizes WHERE product_id={$row['id']} AND quantity > 0");
            
            if ($sizes_res->num_rows > 0) {
                echo "<select name='size' class='grid-size-select' required>";
                echo "<option value='' disabled selected>Select Size</option>";
                while ($sz = $sizes_res->fetch_assoc()) {
                    echo "<option value='{$sz['size']}'>{$sz['size']} ({$sz['quantity']} left)</option>";
                }
                echo "</select>";
                echo "<button type='submit' name='add_to_cart' class='btn' style='width: 100%;'>Add to Cart</button>";
            } else {
                echo "<p style='color: #e53e3e; font-weight: bold; margin-top: 10px;'>Out of Stock</p>";
            }
            echo "</form>";

            echo "</div>";
        }
        ?>
    </div>
</div>
</body>
</html>