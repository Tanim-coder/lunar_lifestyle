<?php
require 'config.php';

if (!isset($_GET['id'])) { header("Location: index.php"); exit(); }
$id = (int)$_GET['id'];

if (isset($_POST['add_to_cart'])) {
    csrf_check();
    $size = $_POST['size'] ?? '';
    if ($size !== '') add_to_cart($conn, $id, $size, 1);
    header("Location: checkout.php");
    exit();
}

$cart_count = cart_count();

$stmt = $conn->prepare(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    die("<div class='container' style='padding:80px 0; text-align:center;'><h2>Product not found.</h2><a href='index.php' class='btn'>Back to store</a></div>");
}

$sale_price = $product['price'] - ($product['price'] * $product['discount_percent'] / 100);
$img = !empty($product['image']) ? $product['image'] : 'https://placehold.co/600x800?text=No+Image';

$sizes_stmt = $conn->prepare("SELECT size, quantity FROM product_sizes WHERE product_id = ? AND quantity > 0");
$sizes_stmt->bind_param("i", $id);
$sizes_stmt->execute();
$sizes_res = $sizes_stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css?v=3">
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
            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
        </div>

        <div class="product-info-col">
            <?php if (!empty($product['category_name'])): ?>
                <a href="index.php?category=<?php echo urlencode($product['category_slug']); ?>"
                   class="prod-category">
                    <?php echo htmlspecialchars($product['category_name']); ?>
                </a>
            <?php endif; ?>

            <h1 class="prod-title"><?php echo htmlspecialchars($product['name']); ?></h1>

            <div class="prod-price-box">
                <?php if ($product['discount_percent'] > 0): ?>
                    <span class="prod-original-price"><?php echo htmlspecialchars($product['price']); ?> TK</span>
                    <span class="prod-sale-price"><?php echo htmlspecialchars($sale_price); ?> TK</span>
                    <span class="prod-discount-badge">-<?php echo (int)$product['discount_percent']; ?>%</span>
                <?php else: ?>
                    <span class="prod-sale-price"><?php echo htmlspecialchars($product['price']); ?> TK</span>
                <?php endif; ?>
            </div>

            <div class="prod-description">
                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
            </div>

            <form method="post" action="product.php?id=<?php echo $id; ?>">
                <?php echo csrf_field(); ?>
                <?php if ($sizes_res->num_rows > 0): ?>
                    <label class="size-label">Select Size</label>
                    <select name="size" class="select-size-dropdown" required>
                        <option value="" disabled selected>Choose a size...</option>
                        <?php while ($sz = $sizes_res->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($sz['size']); ?>">
                                <?php echo htmlspecialchars($sz['size']); ?> (<?php echo (int)$sz['quantity']; ?> in stock)
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