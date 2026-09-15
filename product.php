<?php
require 'config.php';

// Redirect to home if no product ID is provided
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = (int)$_GET['id'];

// Handle Add to Cart
if (isset($_POST['add_to_cart'])) {
    $size = isset($_POST['size']) ? $_POST['size'] : '';
    
    // Initialize the cart session array if it doesn't exist
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

// Calculate total item quantity for the cart counter
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += $item['qty'];
    }
}

// Fetch Product Details
$result = $conn->query("SELECT * FROM products WHERE id = $id");
if ($result->num_rows == 0) {
    die("<div style='text-align:center; padding: 50px; font-family: sans-serif;'><h2>Product not found.</h2><a href='index.php'>Go back to store</a></div>");
}
$product = $result->fetch_assoc();

// Calculate sale price if there is a discount
$sale_price = $product['price'] - ($product['price'] * $product['discount_percent'] / 100);
$img = !empty($product['image']) ? $product['image'] : 'https://placehold.co/600x800?text=No+Image';

// Fetch Available Sizes
$sizes_res = $conn->query("SELECT size, quantity FROM product_sizes WHERE product_id = $id AND quantity > 0");
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $product['name']; ?> - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css">
    <style>
        .single-product-wrapper {
            display: flex;
            gap: 60px;
            margin-top: 40px;
            margin-bottom: 80px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        
        .product-image-col {
            flex: 1;
            min-width: 350px;
            max-width: 550px;
        }
        
        .product-image-col img {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            object-fit: cover;
        }

        .product-info-col {
            flex: 1;
            min-width: 350px;
            display: flex;
            flex-direction: column;
        }

        .prod-title {
            font-size: 36px;
            font-weight: 600;
            margin: 0 0 10px 0;
            color: #111;
        }

        .prod-price-box {
            font-size: 24px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .prod-original-price {
            text-decoration: line-through;
            color: #888;
            font-size: 20px;
        }

        .prod-sale-price {
            font-weight: 700;
            color: #000;
        }

        .prod-discount-badge {
            background-color: #e53e3e;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
        }

        .prod-description {
            font-size: 16px;
            color: #555;
            line-height: 1.8;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid #eaeaea;
        }

        .size-label {
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 1px;
        }

        .select-size-dropdown {
            width: 100%;
            max-width: 400px;
            padding: 15px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 25px;
            background-color: #fcfcfc;
            cursor: pointer;
        }

        .add-btn {
            background-color: #000;
            color: #fff;
            padding: 18px 30px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            max-width: 400px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }

        .add-btn:hover {
            background-color: #333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .out-of-stock-msg {
            color: #e53e3e;
            font-size: 18px;
            font-weight: bold;
            padding: 15px 0;
        }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .single-product-wrapper { gap: 30px; }
            .prod-title { font-size: 28px; }
            .add-btn, .select-size-dropdown { max-width: 100%; }
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

    <div class="single-product-wrapper">
        
        <div class="product-image-col">
            <img src="<?php echo $img; ?>" alt="<?php echo $product['name']; ?>">
        </div>

        <div class="product-info-col">
            <h1 class="prod-title"><?php echo $product['name']; ?></h1>
            
            <div class="prod-price-box">
                <?php if ($product['discount_percent'] > 0): ?>
                    <span class="prod-original-price"><?php echo $product['price']; ?> TK</span>
                    <span class="prod-sale-price"><?php echo $sale_price; ?> TK</span>
                    <span class="prod-discount-badge">-<?php echo $product['discount_percent']; ?>%</span>
                <?php else: ?>
                    <span class="prod-sale-price"><?php echo $product['price']; ?> TK</span>
                <?php endif; ?>
            </div>

            <div class="prod-description">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
            </div>

            <form method="post" action="product.php?id=<?php echo $id; ?>">
                <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                
                <?php if ($sizes_res->num_rows > 0): ?>
                    <label class="size-label">Select Size</label>
                    <select name="size" class="select-size-dropdown" required>
                        <option value="" disabled selected>Choose a size...</option>
                        <?php while ($sz = $sizes_res->fetch_assoc()): ?>
                            <option value="<?php echo $sz['size']; ?>">
                                <?php echo $sz['size']; ?> (<?php echo $sz['quantity']; ?> in stock)
                            </option>
                        <?php endwhile; ?>
                    </select>
                    
                    <button type="submit" name="add_to_cart" class="add-btn">Add to Cart</button>
                <?php else: ?>
                    <p class="out-of-stock-msg">Currently Out of Stock</p>
                <?php endif; ?>
            </form>

        </div>
    </div>
</div>
</body>
</html>