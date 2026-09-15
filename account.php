<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Account - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css">
</head>
<body>
<div class="container">
    <header>
        <a href="index.php" class="brand">Lunar Lifestyle</a>
        <div class="nav-links"><a href="logout.php">Logout</a></div>
    </header>

    <h2>My Orders</h2>
    <div style="overflow-x: auto;">
        <table>
            <tr>
                <th>Order ID</th>
                <th>Date</th>
                <th>Items Ordered</th>
                <th>Total</th>
                <th>Status</th>
            </tr>
            <?php
            $res = $conn->query("SELECT * FROM orders WHERE user_id=$user_id ORDER BY created_at DESC");
            while ($row = $res->fetch_assoc()) {
                $badgeClass = $row['status'] == 'Confirmed' ? 'bg-confirmed' : 'bg-pending';
                
                // Fetch specific items for this order
                $order_id = $row['id'];
                $items_res = $conn->query("SELECT oi.size, oi.qty, p.name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = $order_id");
                
                $items_html = "";
                while ($item = $items_res->fetch_assoc()) {
                    $p_name = $item['name'] ? $item['name'] : 'Deleted Product';
                    $items_html .= "<div style='margin-bottom: 8px;'>
                                        <strong>{$p_name}</strong><br>
                                        <small style='color: #666;'>Size: {$item['size']} | Qty: {$item['qty']}</small>
                                    </div>";
                }

                echo "<tr>
                        <td>#{$row['id']}</td>
                        <td>{$row['created_at']}</td>
                        <td>{$items_html}</td>
                        <td style='font-weight: bold;'>{$row['total']} TK</td>
                        <td><span class='badge {$badgeClass}'>{$row['status']}</span></td>
                      </tr>";
            }
            ?>
        </table>
    </div>
</div>
</body>
</html>