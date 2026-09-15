<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders = fetch_all_assoc($stmt->get_result());
$stmt->close();

$items_by_order = [];
if ($orders) {
    $ids   = array_column($orders, 'id');
    $in    = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = $conn->prepare(
        "SELECT oi.order_id, oi.size, oi.qty, p.name
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id IN ($in)"
    );
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    foreach (fetch_all_assoc($stmt->get_result()) as $it) {
        $items_by_order[$it['order_id']][] = $it;
    }
    $stmt->close();
}

$badge_map = [
    'Pending'   => 'bg-pending',
    'Confirmed' => 'bg-confirmed',
    'Shipped'   => 'bg-shipped',
    'Delivered' => 'bg-delivered',
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css?v=3">
</head>
<body>
<div class="container">
    <header>
        <a href="index.php" class="brand">Lunar Lifestyle</a>
        <div class="nav-links"><a href="logout.php">Logout</a></div>
    </header>

    <h2>My Orders</h2>

    <?php if (empty($orders)): ?>
        <p class="text-muted">You have no orders yet. <a href="index.php">Start shopping</a>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Items Ordered</th>
                    <th>Delivery</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Receipt</th>
                </tr>
                <?php foreach ($orders as $row):
                    $badgeClass = $badge_map[$row['status']] ?? 'bg-pending';
                    $order_id   = (int)$row['id'];
                ?>
                    <tr>
                        <td><strong>#<?php echo $order_id; ?></strong></td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <td>
                            <?php foreach ($items_by_order[$order_id] ?? [] as $item): ?>
                                <div class="mb-2">
                                    <strong><?php echo htmlspecialchars($item['name'] ?? 'Deleted Product'); ?></strong><br>
                                    <small class="text-muted">
                                        Size: <?php echo htmlspecialchars($item['size']); ?> |
                                        Qty: <?php echo (int)$item['qty']; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <span class="badge bg-shipped" style="font-size:10px;">
                                <?php echo htmlspecialchars($row['delivery_method'] ?? 'Inside Dhaka'); ?>
                            </span><br>
                            <small class="text-muted">+<?php echo htmlspecialchars($row['delivery_charge'] ?? '0'); ?> TK</small>
                        </td>
                        <td style="font-weight:bold;"><?php echo htmlspecialchars($row['total']); ?> TK</td>
                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                        <td>
                            <a href="receipt.php?order_id=<?php echo $order_id; ?>" class="btn btn-sm btn-outline" target="_blank">View</a>
                            <a href="receipt.php?order_id=<?php echo $order_id; ?>&download=1" class="btn btn-sm mt-2">Download</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
</body>
</html>