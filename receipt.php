<?php
require 'config.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    http_response_code(400);
    die("Order ID is required.");
}

/* ---------- Fetch order ---------- */
$stmt = $conn->prepare(
    "SELECT o.*, u.name AS customer_name, u.email AS customer_email
     FROM orders o
     LEFT JOIN users u ON o.user_id = u.id
     WHERE o.id = ?"
);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    die("Order not found.");
}

/* ---------- Authorization ---------- */
$is_admin = isset($_SESSION['admin']);
$is_owner = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$order['user_id'];

if (!$is_admin && !$is_owner) {
    http_response_code(403);
    die("You are not authorized to view this receipt.");
}

/* ---------- Fetch items ---------- */
$stmt = $conn->prepare(
    "SELECT oi.*, p.name
     FROM order_items oi
     LEFT JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id = ?"
);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = fetch_all_assoc($stmt->get_result());
$stmt->close();

/* ---------- Compute breakdown ---------- */
$items_subtotal = 0.0;
foreach ($items as $it) {
    $items_subtotal += (float)$it['price'] * (int)$it['qty'];
}
$vat             = $items_subtotal * 0.10;
$delivery_charge = (float)($order['delivery_charge'] ?? 0);
$grand_total     = (float)$order['total'];
$delivery_method = $order['delivery_method'] ?? 'Inside Dhaka';
$payment_method  = 'Cash on Delivery';

/* ---------- Download mode ---------- */
$download = isset($_GET['download']);

if ($download) {
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="receipt-order-' . $order_id . '.html"');
} else {
    header('Content-Type: text/html; charset=utf-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt #<?php echo $order_id; ?> - LUNAR LIFESTYLE</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 24px;
            color: #111;
            font-size: 14px;
            line-height: 1.5;
        }
        .receipt {
            max-width: 760px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            padding: 40px;
        }
        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111;
            padding-bottom: 20px;
            margin-bottom: 24px;
            gap: 20px;
            flex-wrap: wrap;
        }
        .brand {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin: 0 0 4px 0;
        }
        .brand-sub {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .receipt-meta { text-align: right; font-size: 13px; }
        .receipt-meta strong { display: block; font-size: 18px; margin-bottom: 4px; }
        .receipt-meta .date { color: #666; }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
        .info-block h4 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #666;
            margin: 0 0 6px 0;
            font-weight: 600;
        }
        .info-block p { margin: 0; font-size: 14px; line-height: 1.5; }
        .info-block .muted { color: #777; font-size: 13px; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #eaeaea; }
        th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
            background: #fafafa;
            font-weight: 600;
        }
        td.num, th.num { text-align: right; }
        td.item-name { font-weight: 600; }
        td .small { color: #777; font-size: 12px; display: block; }

        .totals { margin-left: auto; width: 320px; max-width: 100%; }
        .totals tr td { border: none; padding: 6px 10px; }
        .totals tr td:first-child { color: #666; }
        .totals tr td:last-child { text-align: right; font-weight: 500; }
        .totals tr.grand td {
            border-top: 2px solid #111;
            padding-top: 12px;
            font-size: 16px;
            font-weight: 700;
            color: #111;
        }
        .totals tr.grand td:last-child { color: #e53e3e; }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            text-transform: capitalize;
        }
        .status-Pending    { background: #f0ad4e; }
        .status-Confirmed  { background: #5cb85c; }
        .status-Shipped    { background: #4299e1; }
        .status-Delivered  { background: #38a169; }

        .footer-note {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #ddd;
            font-size: 12px;
            color: #777;
            text-align: center;
        }

        .actions {
            max-width: 760px;
            margin: 20px auto 0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .actions button, .actions a {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 4px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }
        .btn-primary { background: #111; color: #fff; }
        .btn-primary:hover { background: #333; }
        .btn-outline { background: #fff; color: #111; border: 1px solid #ccc; }
        .btn-outline:hover { background: #f5f5f5; }

        @media print {
            body { background: #fff; padding: 0; }
            .receipt { box-shadow: none; border-radius: 0; padding: 20px; }
            .actions { display: none; }
        }

        @media (max-width: 600px) {
            .grid { grid-template-columns: 1fr; }
            .receipt { padding: 24px; }
        }
    </style>
</head>
<body>

<?php if (!$download): ?>
    <div class="actions">
        <a href="receipt.php?order_id=<?php echo $order_id; ?>&download=1" class="btn-outline">⬇ Download HTML</a>
        <button onclick="window.print()" class="btn-primary">🖨 Print / Save as PDF</button>
    </div>
<?php endif; ?>

<div class="receipt">
    <div class="receipt-header">
        <div>
            <h1 class="brand">Lunar Lifestyle</h1>
            <div class="brand-sub">Order Receipt</div>
        </div>
        <div class="receipt-meta">
            <strong>Receipt #<?php echo $order_id; ?></strong>
            <div class="date"><?php echo htmlspecialchars($order['created_at']); ?></div>
        </div>
    </div>

    <div class="grid">
        <div class="info-block">
            <h4>Billed To</h4>
            <p>
                <strong><?php echo htmlspecialchars($order['customer_name'] ?? 'Customer'); ?></strong><br>
                <span class="muted"><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></span>
            </p>
        </div>
        <div class="info-block">
            <h4>Ship To</h4>
            <p><?php echo nl2br(htmlspecialchars($order['address'])); ?></p>
        </div>
    </div>

    <div class="grid">
        <div class="info-block">
            <h4>Payment Method</h4>
            <p><?php echo htmlspecialchars($payment_method); ?></p>
        </div>
        <div class="info-block">
            <h4>Delivery Method</h4>
            <p>
                <?php echo htmlspecialchars($delivery_method); ?>
                <span class="muted">(<?php echo number_format($delivery_charge, 2); ?> TK)</span>
            </p>
        </div>
    </div>

    <div class="info-block" style="margin-bottom: 20px;">
        <h4>Status</h4>
        <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
            <?php echo htmlspecialchars($order['status']); ?>
        </span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Size</th>
                <th class="num">Qty</th>
                <th class="num">Unit Price</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $it):
                $p_name = $it['name'] ?? 'Deleted Product';
                $unit   = (float)$it['price'];
                $line   = $unit * (int)$it['qty'];
            ?>
                <tr>
                    <td class="item-name"><?php echo htmlspecialchars($p_name); ?></td>
                    <td><?php echo htmlspecialchars($it['size']); ?></td>
                    <td class="num"><?php echo (int)$it['qty']; ?></td>
                    <td class="num"><?php echo number_format($unit, 2); ?> TK</td>
                    <td class="num"><?php echo number_format($line, 2); ?> TK</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td>Subtotal</td>
            <td><?php echo number_format($items_subtotal, 2); ?> TK</td>
        </tr>
        <tr>
            <td>VAT (10%)</td>
            <td>+ <?php echo number_format($vat, 2); ?> TK</td>
        </tr>
        <tr>
            <td>Delivery (<?php echo htmlspecialchars($delivery_method); ?>)</td>
            <td>+ <?php echo number_format($delivery_charge, 2); ?> TK</td>
        </tr>
        <tr class="grand">
            <td>Grand Total</td>
            <td><?php echo number_format($grand_total, 2); ?> TK</td>
        </tr>
    </table>

    <div class="footer-note">
        Thank you for shopping with Lunar Lifestyle.<br>
        This receipt was generated on <?php echo date('F j, Y \a\t g:i A'); ?>.
    </div>
</div>
</body>
</html>