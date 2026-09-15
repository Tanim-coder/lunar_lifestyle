<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "lunar_lifestyle";

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$conn->query("CREATE DATABASE IF NOT EXISTS $db");
$conn->select_db($db);

/* ---------- Tables ---------- */
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255)
)");

$conn->query("CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE,
    password VARCHAR(255)
)");

$conn->query("CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    description TEXT,
    price DECIMAL(10,2),
    discount_percent INT DEFAULT 0,
    image VARCHAR(255)
)");

$conn->query("CREATE TABLE IF NOT EXISTS product_sizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    size VARCHAR(50),
    quantity INT DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    address TEXT,
    total DECIMAL(10,2),
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    size VARCHAR(50),
    qty INT,
    price DECIMAL(10,2)
)");

$conn->query("CREATE TABLE IF NOT EXISTS banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255) DEFAULT '',
    link_url VARCHAR(500) DEFAULT '',
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
)");

$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('delivery_inside_dhaka',  '60'),
    ('delivery_outside_dhaka', '120'),
    ('banner_interval_ms',     '2000')
");

/* ---------- Migration: products.category_id ---------- */
$chk = $conn->query("SHOW COLUMNS FROM products LIKE 'category_id'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE products ADD COLUMN category_id INT NULL AFTER id");
}

/* ---------- Migration: orders delivery columns ---------- */
$chk = $conn->query("SHOW COLUMNS FROM orders LIKE 'delivery_method'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE orders ADD COLUMN delivery_method VARCHAR(50) DEFAULT 'Inside Dhaka' AFTER total");
    $conn->query("ALTER TABLE orders ADD COLUMN delivery_charge DECIMAL(10,2) DEFAULT 0 AFTER delivery_method");
}

/* ---------- Migration: banners.sort_order ---------- */
$chk = $conn->query("SHOW COLUMNS FROM banners LIKE 'sort_order'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE banners ADD COLUMN sort_order INT DEFAULT 0 AFTER is_active");
}

/* ---------- Seed default categories ---------- */
$seed_categories = [
    'Shirts'     => 1,
    'Katua'      => 2,
    'Panjabi'    => 3,
    'T-Shirt'    => 4,
    'Pant'       => 5,
    'Knit Polos' => 6,
    'Winter'     => 7,
];
foreach ($seed_categories as $name => $order) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $slug = trim($slug, '-');
    $stmt = $conn->prepare(
        "INSERT IGNORE INTO categories (name, slug, sort_order, is_active) VALUES (?, ?, ?, 1)"
    );
    $stmt->bind_param("ssi", $name, $slug, $order);
    $stmt->execute();
    $stmt->close();
}

/* ---------- Seed / migrate admin ---------- */
$defaultAdminHash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT IGNORE INTO admin (username, password) VALUES ('admin', ?)");
$stmt->bind_param("s", $defaultAdminHash);
$stmt->execute();
$stmt->close();

$res = $conn->query("SELECT id, password FROM admin WHERE username='admin'");
if ($row = $res->fetch_assoc()) {
    if (strlen($row['password']) < 60) {
        $newHash = password_hash($row['password'], PASSWORD_DEFAULT);
        $u = $conn->prepare("UPDATE admin SET password=? WHERE id=?");
        $u->bind_param("si", $newHash, $row['id']);
        $u->execute();
        $u->close();
    }
}

/* ---------- Helpers ---------- */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf']) . '">';
}

function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(403);
        die("Invalid CSRF token.");
    }
}

function fetch_all_assoc(mysqli_result $res): array {
    $out = [];
    while ($row = $res->fetch_assoc()) $out[] = $row;
    return $out;
}

function add_to_cart(mysqli $conn, int $product_id, string $size, int $qty = 1): bool {
    if ($qty < 1) return false;

    $stmt = $conn->prepare("SELECT quantity FROM product_sizes WHERE product_id=? AND size=?");
    $stmt->bind_param("is", $product_id, $size);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || (int)$row['quantity'] <= 0) return false;

    $available = (int)$row['quantity'];
    $key = $product_id . '_' . $size;

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $current = $_SESSION['cart'][$key]['qty'] ?? 0;
    $new = min($current + $qty, $available);

    $_SESSION['cart'][$key] = ['id' => $product_id, 'size' => $size, 'qty' => $new];
    return true;
}

function cart_count(): int {
    if (empty($_SESSION['cart'])) return 0;
    $n = 0;
    foreach ($_SESSION['cart'] as $it) $n += (int)($it['qty'] ?? 0);
    return $n;
}

/* =========================================================
   BANNER HELPERS (updated for multi-banner carousel)
   ========================================================= */

/**
 * Return ALL active banners ordered by sort_order, then id.
 * Used by index.php to build the carousel.
 */
function get_active_banners(mysqli $conn): array {
    return fetch_all_assoc(
        $conn->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")
    );
}

/**
 * Backward-compatible: return just the first active banner (or null).
 */
function get_active_banner(mysqli $conn): ?array {
    $all = get_active_banners($conn);
    return $all[0] ?? null;
}

/**
 * Activate one banner. Other banners remain untouched — multiple can be active.
 */
function activate_banner(mysqli $conn, int $id): void {
    $s = $conn->prepare("UPDATE banners SET is_active = 1 WHERE id = ?");
    $s->bind_param("i", $id);
    $s->execute();
    $s->close();
}

function deactivate_banner(mysqli $conn, int $id): void {
    $s = $conn->prepare("UPDATE banners SET is_active = 0 WHERE id = ?");
    $s->bind_param("i", $id);
    $s->execute();
    $s->close();
}

function handle_banner_upload(array $file): array {
    if (empty($file['name'])) return [null, "No file uploaded."];
    if ($file['error'] !== UPLOAD_ERR_OK) return [null, "Upload failed (error code {$file['error']})."];
    if ($file['size'] > 5 * 1024 * 1024) return [null, "Image must be smaller than 5 MB."];

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) return [null, "File is not a valid image."];

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!isset($allowed[$info[2]])) return [null, "Only JPG, PNG, GIF, or WEBP allowed."];

    if (!file_exists('uploads')) { mkdir('uploads', 0777, true); }

    $filename = 'banner_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$info[2]];
    $target   = 'uploads/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return [null, "Could not save the uploaded file."];
    }
    return [$target, null];
}

function delete_banner_file(string $path): void {
    if ($path && file_exists($path) && strpos($path, 'uploads/') === 0) {
        @unlink($path);
    }
}

/**
 * Banner rotation interval in milliseconds (default 2000 = 2s).
 */
function get_banner_interval(mysqli $conn): int {
    return max(500, (int)get_setting($conn, 'banner_interval_ms', '2000'));
}

/* ---------- Settings helpers ---------- */
function get_setting(mysqli $conn, string $key, string $default = ''): string {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? $row['setting_value'] : $default;
}

function set_setting(mysqli $conn, string $key, string $value): void {
    $stmt = $conn->prepare(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
    );
    $stmt->bind_param("ss", $key, $value);
    $stmt->execute();
    $stmt->close();
}

function get_delivery_charges(mysqli $conn): array {
    return [
        'Inside Dhaka'  => (float)get_setting($conn, 'delivery_inside_dhaka', '60'),
        'Outside Dhaka' => (float)get_setting($conn, 'delivery_outside_dhaka', '120'),
    ];
}

/* ---------- Category helpers ---------- */
function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text === '' ? 'category-' . time() : $text;
}

function get_categories(mysqli $conn, bool $active_only = false): array {
    $sql = "SELECT * FROM categories";
    if ($active_only) $sql .= " WHERE is_active = 1";
    $sql .= " ORDER BY sort_order ASC, name ASC";
    return fetch_all_assoc($conn->query($sql));
}

function get_category_by_slug(mysqli $conn, string $slug): ?array {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}