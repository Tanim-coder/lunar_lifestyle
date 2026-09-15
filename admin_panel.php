<?php
require 'config.php';
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit(); }

if (!file_exists('uploads')) { mkdir('uploads', 0777, true); }

$banner_message = "";
$banner_error   = "";

/* ---------- CATEGORY: Add ---------- */
if (isset($_POST['add_category'])) {
    csrf_check();
    $name = trim($_POST['cat_name']);
    if ($name === '') { header("Location: admin_panel.php?cat_error=1"); exit(); }
    $slug = slugify($name);

    $check = $conn->prepare("SELECT id FROM categories WHERE slug = ?");
    $check->bind_param("s", $slug);
    $check->execute();
    if ($check->get_result()->num_rows > 0) $slug .= '-' . time();
    $check->close();

    $order = (int)($_POST['cat_order'] ?? 0);
    $stmt = $conn->prepare("INSERT INTO categories (name, slug, sort_order, is_active) VALUES (?, ?, ?, 1)");
    $stmt->bind_param("ssi", $name, $slug, $order);
    $stmt->execute();
    $stmt->close();

    header("Location: admin_panel.php?cat_added=1");
    exit();
}

/* ---------- CATEGORY: Update ---------- */
if (isset($_POST['update_category'])) {
    csrf_check();
    $id    = (int)$_POST['cat_id'];
    $name  = trim($_POST['cat_name']);
    $order = (int)$_POST['cat_order'];
    if ($name !== '') {
        $stmt = $conn->prepare("UPDATE categories SET name=?, sort_order=? WHERE id=?");
        $stmt->bind_param("sii", $name, $order, $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin_panel.php?cat_updated=1");
    exit();
}

/* ---------- CATEGORY: Toggle ---------- */
if (isset($_POST['toggle_category'])) {
    csrf_check();
    $id = (int)$_POST['toggle_category'];
    $stmt = $conn->prepare("UPDATE categories SET is_active = 1 - is_active WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_panel.php");
    exit();
}

/* ---------- CATEGORY: Delete ---------- */
if (isset($_POST['del_category'])) {
    csrf_check();
    $id = (int)$_POST['del_category'];
    $s = $conn->prepare("UPDATE products SET category_id = NULL WHERE category_id = ?");
    $s->bind_param("i", $id); $s->execute(); $s->close();
    $s = $conn->prepare("DELETE FROM categories WHERE id=?");
    $s->bind_param("i", $id); $s->execute(); $s->close();
    header("Location: admin_panel.php?cat_deleted=1");
    exit();
}

/* ---------- PRODUCT: Add ---------- */
if (isset($_POST['add_product'])) {
    csrf_check();
    $name     = trim($_POST['name']);
    $desc     = trim($_POST['description']);
    $price    = (float)$_POST['price'];
    $discount = max(0, min(100, (int)$_POST['discount_percent']));
    $cat_id   = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

    $imagePath = "";
    if (!empty($_FILES["image"]["name"])) {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES["image"]["name"]));
        $target = "uploads/" . time() . "_" . $safe;
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target)) $imagePath = $target;
    }

    $stmt = $conn->prepare(
        "INSERT INTO products (category_id, name, description, price, discount_percent, image)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issdis", $cat_id, $name, $desc, $price, $discount, $imagePath);
    $stmt->execute();
    $pid = $conn->insert_id;
    $stmt->close();

    $sizes = [
        'S'  => max(0, (int)($_POST['qty_S']  ?? 0)),
        'M'  => max(0, (int)($_POST['qty_M']  ?? 0)),
        'L'  => max(0, (int)($_POST['qty_L']  ?? 0)),
        'XL' => max(0, (int)($_POST['qty_XL'] ?? 0)),
    ];
    $sz = $conn->prepare("INSERT INTO product_sizes (product_id, size, quantity) VALUES (?, ?, ?)");
    foreach ($sizes as $s => $q) { $sz->bind_param("isi", $pid, $s, $q); $sz->execute(); }
    $sz->close();

    header("Location: admin_panel.php");
    exit();
}

/* ---------- PRODUCT: Delete ---------- */
if (isset($_POST['del_product'])) {
    csrf_check();
    $id = (int)$_POST['del_product'];
    $s = $conn->prepare("DELETE FROM products WHERE id=?");
    $s->bind_param("i", $id); $s->execute(); $s->close();
    $s = $conn->prepare("DELETE FROM product_sizes WHERE product_id=?");
    $s->bind_param("i", $id); $s->execute(); $s->close();
    header("Location: admin_panel.php");
    exit();
}

/* ---------- ORDER: Update status ---------- */
if (isset($_POST['update_order_status'])) {
    csrf_check();
    $order_id = (int)$_POST['order_id'];
    $status   = $_POST['status'] ?? 'Pending';
    if (!in_array($status, ['Pending','Confirmed','Shipped','Delivered'], true)) $status = 'Pending';
    $s = $conn->prepare("UPDATE orders SET status=? WHERE id=?");
    $s->bind_param("si", $status, $order_id);
    $s->execute(); $s->close();
    header("Location: admin_panel.php");
    exit();
}

/* ---------- ORDER: Delete ---------- */
if (isset($_POST['del_order'])) {
    csrf_check();
    $id = (int)$_POST['del_order'];
    $s = $conn->prepare("DELETE FROM orders WHERE id=?");
    $s->bind_param("i", $id); $s->execute(); $s->close();
    $s = $conn->prepare("DELETE FROM order_items WHERE order_id=?");
    $s->bind_param("i", $id); $s->execute(); $s->close();
    header("Location: admin_panel.php");
    exit();
}

/* =========================================================
   BANNER: Add  (multiple banners can be active simultaneously)
   ========================================================= */
if (isset($_POST['add_banner'])) {
    csrf_check();
    $alt  = trim($_POST['alt_text'] ?? '');
    $link = trim($_POST['link_url'] ?? '');
    if ($link !== '' && !preg_match('#^https?://#i', $link) && strpos($link, '/') !== 0) {
        $link = preg_replace('/[^A-Za-z0-9._\/?=&-]/', '', $link);
    }

    $sort_order = (int)($_POST['banner_order'] ?? 0);

    [$path, $err] = handle_banner_upload($_FILES['banner_image'] ?? []);
    if ($err) {
        $banner_error = $err;
    } else {
        $make_active = !empty($_POST['make_active']) ? 1 : 0;
        $stmt = $conn->prepare(
            "INSERT INTO banners (image, alt_text, link_url, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssii", $path, $alt, $link, $make_active, $sort_order);
        $stmt->execute();
        $stmt->close();

        header("Location: admin_panel.php?banner_added=1");
        exit();
    }
}

/* ---------- BANNER: Activate (non-exclusive) ---------- */
if (isset($_POST['activate_banner'])) {
    csrf_check();
    activate_banner($conn, (int)$_POST['activate_banner']);
    header("Location: admin_panel.php?banner_status_changed=1");
    exit();
}

/* ---------- BANNER: Deactivate ---------- */
if (isset($_POST['deactivate_banner'])) {
    csrf_check();
    deactivate_banner($conn, (int)$_POST['deactivate_banner']);
    header("Location: admin_panel.php?banner_status_changed=1");
    exit();
}

/* ---------- BANNER: Update sort order ---------- */
if (isset($_POST['update_banner_order'])) {
    csrf_check();
    $id    = (int)$_POST['banner_id'];
    $order = (int)$_POST['banner_sort_order'];
    $s = $conn->prepare("UPDATE banners SET sort_order=? WHERE id=?");
    $s->bind_param("ii", $order, $id);
    $s->execute(); $s->close();
    header("Location: admin_panel.php");
    exit();
}

/* ---------- BANNER: Delete ---------- */
if (isset($_POST['del_banner'])) {
    csrf_check();
    $id = (int)$_POST['del_banner'];
    $s = $conn->prepare("SELECT image FROM banners WHERE id=?");
    $s->bind_param("i", $id); $s->execute();
    $row = $s->get_result()->fetch_assoc(); $s->close();
    if ($row) {
        delete_banner_file($row['image']);
        $s = $conn->prepare("DELETE FROM banners WHERE id=?");
        $s->bind_param("i", $id); $s->execute(); $s->close();
    }
    header("Location: admin_panel.php");
    exit();
}

/* ---------- SETTINGS: Delivery charges ---------- */
if (isset($_POST['update_delivery_settings'])) {
    csrf_check();
    $inside  = max(0, (float)$_POST['delivery_inside_dhaka']);
    $outside = max(0, (float)$_POST['delivery_outside_dhaka']);
    set_setting($conn, 'delivery_inside_dhaka',  (string)$inside);
    set_setting($conn, 'delivery_outside_dhaka', (string)$outside);

    if (isset($_POST['banner_interval_ms'])) {
        $ms = max(500, min(60000, (int)$_POST['banner_interval_ms']));
        set_setting($conn, 'banner_interval_ms', (string)$ms);
    }

    header("Location: admin_panel.php?settings_updated=1");
    exit();
}

/* ---------- Fetch data ---------- */
$orders          = fetch_all_assoc($conn->query("SELECT * FROM orders ORDER BY created_at DESC"));
$categories_list = get_categories($conn, false);
$categories_map  = [];
foreach ($categories_list as $c) $categories_map[(int)$c['id']] = $c['name'];

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

$banners          = fetch_all_assoc($conn->query("SELECT * FROM banners ORDER BY is_active DESC, sort_order ASC, id DESC"));
$active_banner_count = count(array_filter($banners, fn($b) => (int)$b['is_active'] === 1));

$banner_added           = isset($_GET['banner_added']);
$banner_status_changed  = isset($_GET['banner_status_changed']);
$settings_updated       = isset($_GET['settings_updated']);
$cat_added              = isset($_GET['cat_added']);
$cat_updated            = isset($_GET['cat_updated']);
$cat_deleted            = isset($_GET['cat_deleted']);
$cat_error              = isset($_GET['cat_error']);

$charges      = get_delivery_charges($conn);
$banner_ms    = get_banner_interval($conn);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css?v=4">
</head>
<body>
<div class="container container-wide">
    <header>
        <div class="brand">Admin Dashboard</div>
        <div class="nav-links">
            <a href="index.php" target="_blank">View Store</a>
            <a href="logout.php">Logout</a>
        </div>
    </header>

    <h2 class="section-heading" style="margin-top:0;">Categories</h2>

    <?php if ($cat_added): ?><div class="alert alert-success">✅ Category added.</div><?php endif; ?>
    <?php if ($cat_updated): ?><div class="alert alert-success">✅ Category updated.</div><?php endif; ?>
    <?php if ($cat_deleted): ?><div class="alert alert-success">✅ Category deleted.</div><?php endif; ?>
    <?php if ($cat_error): ?><div class="alert alert-error">Category name is required.</div><?php endif; ?>

    <form method="post" class="form-panel" style="max-width:800px;">
        <?php echo csrf_field(); ?>
        <div class="form-row">
            <div>
                <label style="margin-top:0;">New Category Name</label>
                <input type="text" name="cat_name" placeholder="e.g. Hoodies" maxlength="100" required>
            </div>
            <div>
                <label style="margin-top:0;">Sort Order</label>
                <input type="number" name="cat_order" value="0" min="0">
            </div>
        </div>
        <button type="submit" name="add_category" class="btn mt-4">Add Category</button>
    </form>

    <?php if (!empty($categories_list)): ?>
        <div class="table-wrap mt-6">
            <table>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th style="width:80px;">Order</th>
                    <th style="width:100px;">Products</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:280px;">Actions</th>
                </tr>
                <?php foreach ($categories_list as $cat):
                    $cid = (int)$cat['id'];
                    $cnt_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products WHERE category_id = ?");
                    $cnt_stmt->bind_param("i", $cid);
                    $cnt_stmt->execute();
                    $cnt = (int)$cnt_stmt->get_result()->fetch_assoc()['cnt'];
                    $cnt_stmt->close();
                ?>
                    <tr>
                        <td>#<?php echo $cid; ?></td>
                        <td>
                            <form method="post" style="display:flex; gap:8px; align-items:center; margin:0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="cat_id" value="<?php echo $cid; ?>">
                                <input type="text" name="cat_name" value="<?php echo htmlspecialchars($cat['name']); ?>" required
                                       style="margin:0; min-height:32px; padding:4px 8px;">
                                <input type="number" name="cat_order" value="<?php echo (int)$cat['sort_order']; ?>" min="0"
                                       style="margin:0; min-height:32px; padding:4px 8px; width:70px;">
                                <button type="submit" name="update_category" class="btn btn-sm btn-success">Save</button>
                            </form>
                        </td>
                        <td><code style="font-size:12px; background:#f4f4f4; padding:2px 6px; border-radius:3px;"><?php echo htmlspecialchars($cat['slug']); ?></code></td>
                        <td><?php echo (int)$cat['sort_order']; ?></td>
                        <td><?php echo $cnt; ?></td>
                        <td>
                            <?php if ($cat['is_active']): ?>
                                <span class="badge bg-confirmed">Active</span>
                            <?php else: ?>
                                <span class="badge bg-pending">Hidden</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex flex-gap-2 flex-wrap">
                                <form method="post" style="display:inline;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="toggle_category" value="<?php echo $cid; ?>">
                                    <button type="submit" class="btn btn-sm btn-neutral"><?php echo $cat['is_active'] ? 'Hide' : 'Show'; ?></button>
                                </form>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="del_category" value="<?php echo $cid; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                                <a href="index.php?category=<?php echo urlencode($cat['slug']); ?>" class="btn btn-sm btn-outline" target="_blank">View</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted mt-4">No categories yet.</p>
    <?php endif; ?>

    <hr>

    <!-- =====================================================
         SETTINGS (delivery + banner rotation)
         ===================================================== -->
    <h2 class="section-heading">Settings</h2>

    <?php if ($settings_updated): ?><div class="alert alert-success">✅ Settings updated.</div><?php endif; ?>

    <form method="post" class="form-panel" style="max-width:800px;">
        <?php echo csrf_field(); ?>

        <h4 style="margin-top:0;">Delivery Charges</h4>
        <div class="form-row">
            <div>
                <label>Inside Dhaka (TK)</label>
                <input type="number" name="delivery_inside_dhaka" value="<?php echo htmlspecialchars((string)$charges['Inside Dhaka']); ?>" min="0" step="0.01" required>
            </div>
            <div>
                <label>Outside Dhaka (TK)</label>
                <input type="number" name="delivery_outside_dhaka" value="<?php echo htmlspecialchars((string)$charges['Outside Dhaka']); ?>" min="0" step="0.01" required>
            </div>
        </div>

        <h4 class="mt-6">Banner Rotation</h4>
        <label>Slide Duration (milliseconds)</label>
        <input type="number" name="banner_interval_ms" value="<?php echo (int)$banner_ms; ?>" min="500" max="60000" step="100">
        <p class="text-muted" style="font-size:13px; margin-top:6px;">
            Default <strong>2000 ms</strong> (2 seconds). Minimum 500 ms, maximum 60000 ms.
        </p>

        <button type="submit" name="update_delivery_settings" class="btn mt-4">Save Settings</button>
    </form>

    <hr>

    <!-- =====================================================
         BANNERS (multi-active carousel)
         ===================================================== -->
    <h2 class="section-heading">Homepage Banner Carousel (16:9)</h2>

    <p class="text-muted" style="margin-bottom:16px;">
        You currently have <strong><?php echo $active_banner_count; ?></strong> active banner<?php echo $active_banner_count === 1 ? '' : 's'; ?>.
        All active banners rotate in the storefront carousel every
        <strong><?php echo number_format($banner_ms / 1000, 1); ?>s</strong>
        (ordered by Sort Order, then ID).
    </p>

    <?php if ($banner_added): ?><div class="alert alert-success">✅ Banner uploaded successfully.</div><?php endif; ?>
    <?php if ($banner_status_changed): ?><div class="alert alert-success">✅ Banner status updated.</div><?php endif; ?>
    <?php if ($banner_error): ?><div class="alert alert-error"><?php echo htmlspecialchars($banner_error); ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-panel" style="max-width:800px;">
        <?php echo csrf_field(); ?>
        <label style="margin-top:0;">Banner Image</label>
        <p class="text-muted" style="font-size:13px; margin:0 0 8px 0;">
            Recommended: <strong>1920 × 1080 px</strong> (16:9). Max 5 MB. JPG, PNG, GIF, or WEBP.
        </p>
        <input type="file" name="banner_image" accept="image/*" required>

        <div class="form-row">
            <div>
                <label>Alt Text (optional)</label>
                <input type="text" name="alt_text" placeholder="e.g. Spring collection" maxlength="255">
            </div>
            <div>
                <label>Sort Order (lower = first)</label>
                <input type="number" name="banner_order" value="0" min="0">
            </div>
        </div>

        <label>Link URL (optional)</label>
        <input type="text" name="link_url" placeholder="e.g. product.php?id=5" maxlength="500">

        <label style="margin-top:12px;">
            <input type="checkbox" name="make_active" value="1" checked>
            Activate this banner (it will join the rotation)
        </label>

        <button type="submit" name="add_banner" class="btn mt-4">Upload Banner</button>
    </form>

    <?php if (!empty($banners)): ?>
        <h3 class="mt-8">Banner Library</h3>
        <div class="table-wrap">
            <table>
                <tr>
                    <th style="width:220px;">Preview</th>
                    <th>Alt / Link</th>
                    <th style="width:80px;">Order</th>
                    <th>Uploaded</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:240px;">Actions</th>
                </tr>
                <?php foreach ($banners as $b): ?>
                    <tr>
                        <td>
                            <div class="banner-preview">
                                <img src="<?php echo htmlspecialchars($b['image']); ?>" alt="<?php echo htmlspecialchars($b['alt_text']); ?>">
                            </div>
                        </td>
                        <td>
                            <div><strong>Alt:</strong> <?php echo $b['alt_text'] !== '' ? htmlspecialchars($b['alt_text']) : '<em class="text-muted">— none —</em>'; ?></div>
                            <div style="margin-top:6px; font-size:13px; color:#555;">
                                <strong>Link:</strong>
                                <?php echo $b['link_url'] !== ''
                                    ? '<code style="background:#f4f4f4; padding:2px 6px; border-radius:3px;">' . htmlspecialchars($b['link_url']) . '</code>'
                                    : '<em class="text-muted">— none —</em>'; ?>
                            </div>
                        </td>
                        <td>
                            <form method="post" style="display:flex; gap:6px; margin:0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="banner_id" value="<?php echo (int)$b['id']; ?>">
                                <input type="number" name="banner_sort_order" value="<?php echo (int)($b['sort_order'] ?? 0); ?>" min="0"
                                       style="margin:0; min-height:30px; padding:4px 6px; width:60px;">
                                <button type="submit" name="update_banner_order" class="btn btn-sm">Save</button>
                            </form>
                        </td>
                        <td><small><?php echo htmlspecialchars($b['created_at']); ?></small></td>
                        <td>
                            <?php if ($b['is_active']): ?>
                                <span class="badge bg-confirmed">Active</span>
                            <?php else: ?>
                                <span class="badge bg-pending">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex flex-gap-2 flex-wrap">
                                <?php if (!$b['is_active']): ?>
                                    <form method="post" style="display:inline;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="activate_banner" value="<?php echo (int)$b['id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm">Activate</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" style="display:inline;">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="deactivate_banner" value="<?php echo (int)$b['id']; ?>">
                                        <button type="submit" class="btn btn-neutral btn-sm">Deactivate</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this banner?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="del_banner" value="<?php echo (int)$b['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

    <hr>

    <h2 class="section-heading">Add New Product</h2>
    <form method="post" enctype="multipart/form-data" class="form-panel">
        <?php echo csrf_field(); ?>
        <label style="margin-top:0;">Product Name</label>
        <input type="text" name="name" placeholder="Product Name" required>

        <label>Category</label>
        <select name="category_id">
            <option value="">— Uncategorized —</option>
            <?php foreach ($categories_list as $cat): ?>
                <option value="<?php echo (int)$cat['id']; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?><?php echo $cat['is_active'] ? '' : ' (hidden)'; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Description</label>
        <textarea name="description" rows="3"></textarea>

        <div class="form-row">
            <div>
                <label>Base Price (TK)</label>
                <input type="number" name="price" placeholder="0.00" min="0" step="0.01" required>
            </div>
            <div>
                <label>Discount % (0–100)</label>
                <input type="number" name="discount_percent" value="0" min="0" max="100" required>
            </div>
        </div>

        <label class="mt-4" style="font-weight:600;">Stock Quantities by Size</label>
        <div class="size-grid">
            <div><label>S</label><input type="number" name="qty_S"  value="0" min="0"></div>
            <div><label>M</label><input type="number" name="qty_M"  value="0" min="0"></div>
            <div><label>L</label><input type="number" name="qty_L"  value="0" min="0"></div>
            <div><label>XL</label><input type="number" name="qty_XL" value="0" min="0"></div>
        </div>

        <label>Product Image</label>
        <input type="file" name="image" accept="image/*">

        <button type="submit" name="add_product" class="btn mt-4">Upload Product</button>
    </form>

    <hr>

    <h2 class="section-heading">Order Management</h2>
    <div class="table-wrap">
        <table>
            <tr>
                <th style="width:120px;">Order #</th>
                <th style="width:100px;">Customer</th>
                <th style="width:200px;">Address</th>
                <th>Items Ordered</th>
                <th style="width:100px;">Total</th>
                <th style="width:220px;">Status</th>
                <th style="width:160px;">Action</th>
            </tr>
            <?php foreach ($orders as $o): $o_id = (int)$o['id']; ?>
                <tr>
                    <td>
                        <strong>#<?php echo $o_id; ?></strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($o['created_at']); ?></small>
                    </td>
                    <td>User #<?php echo (int)$o['user_id']; ?></td>
                    <td>
                        <?php echo htmlspecialchars($o['address']); ?>
                        <div style="margin-top:6px; font-size:13px;">
                            <span class="badge bg-shipped" style="font-size:10px;"><?php echo htmlspecialchars($o['delivery_method'] ?? 'Inside Dhaka'); ?></span>
                            <span class="text-muted">+<?php echo htmlspecialchars($o['delivery_charge'] ?? '0'); ?> TK</span>
                        </div>
                    </td>
                    <td>
                        <?php foreach ($items_by_order[$o_id] ?? [] as $item): ?>
                            <div style="margin-bottom:8px; border-bottom:1px dashed #ccc; padding-bottom:4px;">
                                <strong><?php echo htmlspecialchars($item['name'] ?? 'Deleted Product'); ?></strong><br>
                                <span class="text-muted" style="font-size:13px;">
                                    Size: <b><?php echo htmlspecialchars($item['size']); ?></b> |
                                    Qty: <b><?php echo (int)$item['qty']; ?></b>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <td style="font-weight:bold;"><?php echo htmlspecialchars($o['total']); ?> TK</td>
                    <td>
                        <form method="post" style="display:flex; gap:6px; margin:0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="order_id" value="<?php echo $o_id; ?>">
                            <select name="status" style="padding:4px 6px; margin:0; min-height:32px; font-size:12px;">
                                <?php foreach (['Pending','Confirmed','Shipped','Delivered'] as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php echo $o['status']===$st?'selected':''; ?>><?php echo $st; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="update_order_status" class="btn btn-sm">Update</button>
                        </form>
                    </td>
                    <td>
                        <div class="flex flex-gap-2 flex-wrap">
                            <a href="receipt.php?order_id=<?php echo $o_id; ?>" class="btn btn-sm btn-outline" target="_blank">Receipt</a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this order?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="del_order" value="<?php echo $o_id; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <hr>

    <h2 class="section-heading">Inventory</h2>
    <div class="table-wrap">
        <table>
            <tr>
                <th style="width:80px;">Image</th>
                <th>Name</th>
                <th style="width:140px;">Category</th>
                <th>Available Sizes &amp; Stock</th>
                <th style="width:100px;">Price</th>
                <th style="width:180px;">Action</th>
            </tr>
            <?php
            $prods = $conn->query("SELECT * FROM products ORDER BY id DESC");
            while ($p = $prods->fetch_assoc()):
                $p_id = (int)$p['id'];
                $img = $p['image']
                    ? "<img src='" . htmlspecialchars($p['image']) . "' style='width:60px; height:60px; object-fit:cover; border-radius:4px;'>"
                    : "<span class='text-muted'>No Img</span>";

                $cat_label = (!empty($p['category_id']) && isset($categories_map[(int)$p['category_id']]))
                    ? htmlspecialchars($categories_map[(int)$p['category_id']])
                    : "<em class='text-muted'>Uncategorized</em>";

                $sres = $conn->query("SELECT size, quantity FROM product_sizes WHERE product_id={$p_id}");
                $sizes_html = "";
                while ($sz = $sres->fetch_assoc()) {
                    $sizes_html .= "<span style='display:inline-block; background:#eee; padding:3px 8px; border-radius:12px; margin:2px; font-size:11px;'>"
                                 . htmlspecialchars($sz['size']) . ": <b>" . (int)$sz['quantity'] . "</b></span>";
                }
                if ($sizes_html === "") $sizes_html = "<span class='text-danger'>Out of stock</span>";
            ?>
                <tr>
                    <td><?php echo $img; ?></td>
                    <td style="font-weight:500;"><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo $cat_label; ?></td>
                    <td><?php echo $sizes_html; ?></td>
                    <td><?php echo htmlspecialchars($p['price']); ?> TK</td>
                    <td>
                        <div class="flex flex-gap-2 flex-wrap">
                            <a href="edit_product.php?id=<?php echo $p_id; ?>" class="btn btn-success btn-sm">Edit</a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="del_product" value="<?php echo $p_id; ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>
</body>
</html>