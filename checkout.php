<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['remove'])) {
    unset($_SESSION['cart'][$_GET['remove']]);
    header("Location: checkout.php");
    exit();
}

if (isset($_POST['place_order'])) {
    $user_id = $_SESSION['user_id'];
    $address = $conn->real_escape_string($_POST['address']);
    $subtotal = 0;

    // Calculate Subtotal First (with discounts applied)
    foreach ($_SESSION['cart'] as $key => $item) {
        $p = $conn->query("SELECT price, discount_percent FROM products WHERE id={$item['id']}")->fetch_assoc();
        $sale_price = $p['price'] - ($p['price'] * $p['discount_percent'] / 100);
        $subtotal += $sale_price * $item['qty'];
    }

    if ($subtotal > 0) {
        $vat = $subtotal * 0.10;
        $final_total = $subtotal + $vat;

        // Insert Order
        $conn->query("INSERT INTO orders (user_id, address, total) VALUES ($user_id, '$address', $final_total)");
        $order_id = $conn->insert_id;

        // Insert Order Items & Reduce Stock from the specific size!
        foreach ($_SESSION['cart'] as $key => $item) {
            $id = $item['id'];
            $qty = $item['qty'];
            $size = $item['size'];

            $p = $conn->query("SELECT price, discount_percent FROM products WHERE id=$id")->fetch_assoc();
            $sale_price = $p['price'] - ($p['price'] * $p['discount_percent'] / 100);
            
            $conn->query("INSERT INTO order_items (order_id, product_id, size, qty, price) VALUES ($order_id, $id, '$size', $qty, $sale_price)");
            $conn->query("UPDATE product_sizes SET quantity = quantity - $qty WHERE product_id=$id AND size='$size'");
        }
        
        unset($_SESSION['cart']);
        echo "<script>alert('Order placed successfully! Cash on Delivery.'); window.location='account.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Checkout - LUNAR LIFESTYLE</title><link rel="stylesheet" href="lunar.css"></head>
<body>
<div class="container">
    <header>
        <a href="index.php" class="brand">Lunar Lifestyle</a>
    </header>

    <h2>Your Cart</h2>
    <?php if (!empty($_SESSION['cart'])): ?>
    <table>
        <tr><th>Product</th><th>Size</th><th>Qty</th><th>Price</th><th>Action</th></tr>
        <?php
        $subtotal = 0;
        foreach ($_SESSION['cart'] as $key => $item) {
            $p = $conn->query("SELECT name, price, discount_percent FROM products WHERE id={$item['id']}")->fetch_assoc();
            $sale_price = $p['price'] - ($p['price'] * $p['discount_percent'] / 100);
            $item_total = $sale_price * $item['qty'];
            $subtotal += $item_total;
            
            echo "<tr>
                    <td>{$p['name']}</td>
                    <td><b>{$item['size']}</b></td>
                    <td>{$item['qty']}</td>
                    <td>{$item_total} TK</td>
                    <td><a href='?remove=$key' class='btn btn-danger'>Remove</a></td>
                  </tr>";
        }
        $vat = $subtotal * 0.10;
        $final_total = $subtotal + $vat;
        ?>
        <tr class="vat-row"><td colspan="3" style="text-align: right;">Subtotal</td><td colspan="2"><?php echo number_format($subtotal, 2); ?> TK</td></tr>
        <tr class="vat-row"><td colspan="3" style="text-align: right;">VAT (10%)</td><td colspan="2">+ <?php echo number_format($vat, 2); ?> TK</td></tr>
        <tr><th colspan="3" style="text-align: right; font-size: 16px;">Final Total</th><th colspan="2" style="font-size: 16px; color: #e53e3e;"><?php echo number_format($final_total, 2); ?> TK</th></tr>
    </table>

    <h3 style="margin-top:40px;">Delivery Details</h3>
    <form method="post">
        <label>Shipping Address</label>
        <textarea name="address" rows="4" required placeholder="Enter full address for Cash on Delivery"></textarea>
        <button type="submit" name="place_order" class="btn" style="padding: 15px 30px; font-size: 16px;">Place Order (COD)</button>
    </form>
    <?php else: ?>
        <p>Your cart is empty. <a href="index.php">Go shopping</a></p>
    <?php endif; ?>
</div>
</body>
</html>