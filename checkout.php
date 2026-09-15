<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$errors  = [];
$ordered = isset($_GET['ordered']);

if (isset($_POST['update_cart'])) {
    csrf_check();
    if (!empty($_POST['qty']) && is_array($_POST['qty'])) {
        foreach ($_POST['qty'] as $key => $qty) {
            $qty = (int)$qty;
            if (!isset($_SESSION['cart'][$key])) continue;
            if ($qty <= 0) unset($_SESSION['cart'][$key]);
            else $_SESSION['cart'][$key]['qty'] = $qty;
        }
    }
    header("Location: checkout.php");
    exit();
}

if (isset($_GET['remove'])) {
    unset($_SESSION['cart'][$_GET['remove']]);
    header("Location: checkout.php");
    exit();
}

$charges         = get_delivery_charges($conn);
$delivery_method = $_POST['delivery_method'] ?? ($_SESSION['delivery_method'] ?? 'Inside Dhaka');
if (!array_key_exists($delivery_method, $charges)) $delivery_method = 'Inside Dhaka';

$cart_items = [];
$subtotal   = 0.0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $key => $item) {
        $stmt = $conn->prepare(
            "SELECT p.id, p.name, p.price, p.discount_percent, ps.quantity AS stock
             FROM products p
             LEFT JOIN product_sizes ps ON p.id = ps.product_id AND ps.size = ?
             WHERE p.id = ?"
        );
        $stmt->bind_param("si", $item['size'], $item['id']);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$p) { unset($_SESSION['cart'][$key]); continue; }

        $sale_price = $p['price'] - ($p['price'] * $p['discount_percent'] / 100);
        $line_total = $sale_price * $item['qty'];
        $subtotal  += $line_total;

        $stock = (int)$p['stock'];
        $cart_items[$key] = [
            'product_id' => (int)$p['id'],
            'name'       => $p['name'],
            'size'       => $item['size'],
            'qty'        => (int)$item['qty'],
            'stock'      => $stock,
            'sale_price' => (float)$sale_price,
            'line_total' => (float)$line_total,
        ];

        if ($item['qty'] > $stock) {
            $errors[] = "Only {$stock} of \"" . htmlspecialchars($p['name']) . "\" (Size " . htmlspecialchars($item['size']) . ") left in stock.";
        }
    }
}

if (isset($_POST['place_order'])) {
    csrf_check();
    $user_id = (int)$_SESSION['user_id'];
    $address = trim($_POST['address'] ?? '');

    if ($address === '')     $errors[] = "Shipping address is required.";
    if (empty($cart_items))  $errors[] = "Your cart is empty.";

    $charges         = get_delivery_charges($conn);
    $delivery_method = $_POST['delivery_method'] ?? 'Inside Dhaka';
    if (!array_key_exists($delivery_method, $charges)) {
        $errors[] = "Please choose a valid delivery method.";
    }

    if (empty($errors)) {
        $vat             = $subtotal * 0.10;
        $delivery_charge = (float)$charges[$delivery_method];
        $final_total     = $subtotal + $vat + $delivery_charge;
        $_SESSION['delivery_method'] = $delivery_method;

        $conn->begin_transaction();
        try {
            $ins_order = $conn->prepare(
                "INSERT INTO orders (user_id, address, total, delivery_method, delivery_charge)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $ins_order->bind_param("isdss", $user_id, $address, $final_total, $delivery_method, $delivery_charge);
            $ins_order->execute();
            $order_id = $conn->insert_id;
            $ins_order->close();

            $dec = $conn->prepare(
                "UPDATE product_sizes SET quantity = quantity - ?
                 WHERE product_id = ? AND size = ? AND quantity >= ?"
            );
            $ins = $conn->prepare(
                "INSERT INTO order_items (order_id, product_id, size, qty, price)
                 VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($cart_items as $it) {
                $dec->bind_param("iisi", $it['qty'], $it['product_id'], $it['size'], $it['qty']);
                $dec->execute();
                if ($dec->affected_rows === 0) {
                    throw new Exception("Stock ran out for \"" . htmlspecialchars($it['name']) . "\" (Size " . htmlspecialchars($it['size']) . ").");
                }
                $ins->bind_param("iisid", $order_id, $it['product_id'], $it['size'], $it['qty'], $it['sale_price']);
                $ins->execute();
            }

            $dec->close();
            $ins->close();
            $conn->commit();

            unset($_SESSION['cart']);
            unset($_SESSION['delivery_method']);
            header("Location: checkout.php?ordered=1&order_id={$order_id}");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

$vat             = $subtotal * 0.10;
$delivery_charge = (float)($charges[$delivery_method] ?? 0);
$final_total     = $subtotal + $vat + $delivery_charge;
$just_placed     = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css?v=3">
</head>
<body>
<div class="container">
    <header>
        <a href="index.php" class="brand">Lunar Lifestyle</a>
        <div class="nav-links"><a href="account.php">My Account</a></div>
    </header>

    <h2>Your Cart</h2>

    <?php if ($ordered): ?>
        <div class="alert alert-success">
            ✅ Order placed successfully. Cash on Delivery.
            <?php if ($just_placed): ?>
                &nbsp;<a href="receipt.php?order_id=<?php echo $just_placed; ?>" target="_blank"><strong>Download your receipt</strong></a>.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $e) echo "• " . $e . "<br>"; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($cart_items)): ?>
        <form method="post">
            <?php echo csrf_field(); ?>
            <div class="table-wrap">
                <table>
                    <tr><th>Product</th><th>Size</th><th>Qty</th><th>Price</th><th></th></tr>
                    <?php foreach ($cart_items as $key => $it): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($it['name']); ?></td>
                            <td><b><?php echo htmlspecialchars($it['size']); ?></b></td>
                            <td>
                                <input type="number" name="qty[<?php echo htmlspecialchars($key); ?>]"
                                       value="<?php echo $it['qty']; ?>" min="0" max="<?php echo $it['stock']; ?>"
                                       style="width:80px; margin:0;">
                                <small class="text-muted">/ <?php echo $it['stock']; ?></small>
                            </td>
                            <td><?php echo number_format($it['line_total'], 2); ?> TK</td>
                            <td><a href="?remove=<?php echo urlencode($key); ?>" class="btn btn-danger btn-sm">×</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="vat-row"><td colspan="3" class="text-right">Subtotal</td><td colspan="2"><?php echo number_format($subtotal, 2); ?> TK</td></tr>
                    <tr class="vat-row"><td colspan="3" class="text-right">VAT (10%)</td><td colspan="2">+ <?php echo number_format($vat, 2); ?> TK</td></tr>
                    <tr class="vat-row"><td colspan="3" class="text-right">Delivery (<?php echo htmlspecialchars($delivery_method); ?>)</td><td colspan="2">+ <?php echo number_format($delivery_charge, 2); ?> TK</td></tr>
                    <tr><th colspan="3" class="text-right" style="font-size:16px;">Final Total</th><th colspan="2" style="font-size:16px; color:#e53e3e;"><?php echo number_format($final_total, 2); ?> TK</th></tr>
                </table>
            </div>
            <button type="submit" name="update_cart" class="btn mt-4">Update Cart</button>
        </form>

        <h3 class="mt-10">Delivery Details</h3>
        <form method="post" class="form-panel">
            <?php echo csrf_field(); ?>
            <label style="margin-top:0;">Delivery Area</label>
            <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:12px;">
                <?php foreach ($charges as $name => $charge): ?>
                    <label style="display:inline-flex; align-items:center; gap:8px; cursor:pointer; margin:0;">
                        <input type="radio" name="delivery_method"
                               value="<?php echo htmlspecialchars($name); ?>"
                               <?php echo $delivery_method === $name ? 'checked' : ''; ?>
                               onchange="this.form.submit()">
                        <span><strong><?php echo htmlspecialchars($name); ?></strong> &mdash; <?php echo number_format($charge, 2); ?> TK</span>
                    </label>
                <?php endforeach; ?>
            </div>

            <label>Shipping Address</label>
            <textarea name="address" rows="4" required placeholder="Enter full address for Cash on Delivery"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>

            <button type="submit" name="place_order" class="btn btn-lg mt-4">
                Place Order (COD) — <?php echo number_format($final_total, 2); ?> TK
            </button>
        </form>
    <?php else: ?>
        <p>Your cart is empty. <a href="index.php">Go shopping</a></p>
    <?php endif; ?>
</div>
</body>
</html>