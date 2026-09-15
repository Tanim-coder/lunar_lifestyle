<?php
require 'config.php';

if (isset($_POST['add_to_cart'])) {
    csrf_check();
    $id   = (int)$_POST['product_id'];
    $size = $_POST['size'] ?? '';

    if ($id > 0 && $size !== '') {
        add_to_cart($conn, $id, $size, 1);
    }
    header("Location: checkout.php");
    exit();
}

$cart_count     = cart_count();
$active_banners = get_active_banners($conn);      // <-- now an array
$banner_ms      = get_banner_interval($conn);     // 2000 by default
$all_categories = get_categories($conn, true);

$current_slug = isset($_GET['category']) ? trim($_GET['category']) : '';
$current_cat  = null;
if ($current_slug !== '') {
    $current_cat = get_category_by_slug($conn, $current_slug);
    if (!$current_cat) $current_slug = '';
}

if ($current_cat) {
    $stmt = $conn->prepare("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug, SUM(ps.quantity) AS total_qty
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN product_sizes ps ON p.id = ps.product_id
        WHERE p.category_id = ?
        GROUP BY p.id
        HAVING total_qty > 0
        ORDER BY p.id DESC
    ");
    $stmt->bind_param("i", $current_cat['id']);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("
        SELECT p.*, c.name AS category_name, c.slug AS category_slug, SUM(ps.quantity) AS total_qty
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN product_sizes ps ON p.id = ps.product_id
        GROUP BY p.id
        HAVING total_qty > 0
        ORDER BY p.id DESC
    ");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_cat ? htmlspecialchars($current_cat['name']) . ' - ' : ''; ?>LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css?v=4">
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

    <?php if (!empty($all_categories)): ?>
        <nav class="category-bar">
            <a href="index.php" class="<?php echo $current_slug === '' ? 'active' : ''; ?>">All</a>
            <?php foreach ($all_categories as $cat): $slug = $cat['slug']; ?>
                <a href="index.php?category=<?php echo urlencode($slug); ?>"
                   class="<?php echo $current_slug === $slug ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <!-- =====================================================
         HERO CAROUSEL
         ===================================================== -->
    <?php if (!empty($active_banners) && !$current_cat): ?>
        <div class="hero-banner" id="heroCarousel"
             style="--hero-interval: <?php echo (int)$banner_ms; ?>ms;">
            <div class="hero-slides">
                <?php foreach ($active_banners as $i => $b):
                    $img_src  = htmlspecialchars($b['image']);
                    $alt_text = htmlspecialchars($b['alt_text'] !== '' ? $b['alt_text'] : 'Banner ' . ($i + 1));
                    $link     = trim($b['link_url'] ?? '');
                    $has_link = $link !== '';
                    $active   = $i === 0 ? ' is-active' : '';
                ?>
                    <div class="hero-slide<?php echo $active; ?>" data-index="<?php echo $i; ?>">
                        <?php if ($has_link): ?>
                            <a href="<?php echo htmlspecialchars($link); ?>">
                                <img src="<?php echo $img_src; ?>" alt="<?php echo $alt_text; ?>">
                            </a>
                        <?php else: ?>
                            <img src="<?php echo $img_src; ?>" alt="<?php echo $alt_text; ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (count($active_banners) > 1): ?>
                <button type="button" class="hero-nav hero-prev" aria-label="Previous slide">&#8249;</button>
                <button type="button" class="hero-nav hero-next" aria-label="Next slide">&#8250;</button>

                <div class="hero-dots" role="tablist">
                    <?php foreach ($active_banners as $i => $b): ?>
                        <button type="button"
                                class="hero-dot<?php echo $i === 0 ? ' is-active' : ''; ?>"
                                data-index="<?php echo $i; ?>"
                                aria-label="Go to slide <?php echo $i + 1; ?>"></button>
                    <?php endforeach; ?>
                </div>

                <div class="hero-progress is-running"></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($current_cat): ?>
        <h2 class="section-heading" style="margin-top:0;">
            <?php echo htmlspecialchars($current_cat['name']); ?>
        </h2>
    <?php endif; ?>

    <div class="product-grid">
        <?php
        if ($result->num_rows === 0) {
            $msg = $current_cat ? 'No products in this category yet.' : 'No products available yet.';
            echo "<p class='text-center text-muted' style='grid-column:1/-1; padding:60px 0;'>{$msg}</p>";
        }

        while ($row = $result->fetch_assoc()) {
            $img = !empty($row['image']) ? $row['image'] : 'https://placehold.co/400x500?text=No+Image';
            $sale_price = $row['price'] - ($row['price'] * $row['discount_percent'] / 100);
        ?>
            <div class="product-card">
                <a href="product.php?id=<?php echo $row['id']; ?>" class="image-frame">
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
                </a>
                <div class="product-body">
                    <?php if (!empty($row['category_name'])): ?>
                        <div class="product-category"><?php echo htmlspecialchars($row['category_name']); ?></div>
                    <?php endif; ?>
                    <a href="product.php?id=<?php echo $row['id']; ?>" class="product-title">
                        <?php echo htmlspecialchars($row['name']); ?>
                    </a>
                    <?php if ($row['discount_percent'] > 0): ?>
                        <div class="product-price">
                            <span class="original-price"><?php echo htmlspecialchars($row['price']); ?> TK</span>
                            <span class="sale-price"><?php echo htmlspecialchars($sale_price); ?> TK</span>
                            <span class="discount-badge">-<?php echo (int)$row['discount_percent']; ?>%</span>
                        </div>
                    <?php else: ?>
                        <div class="product-price"><?php echo htmlspecialchars($row['price']); ?> TK</div>
                    <?php endif; ?>

                    <form method="post" action="index.php">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="product_id" value="<?php echo $row['id']; ?>">
                        <?php
                        $sizes_res = $conn->query("SELECT size, quantity FROM product_sizes WHERE product_id={$row['id']} AND quantity > 0");
                        if ($sizes_res->num_rows > 0): ?>
                            <select name="size" class="grid-size-select" required>
                                <option value="" disabled selected>Select Size</option>
                                <?php while ($sz = $sizes_res->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($sz['size']); ?>">
                                        <?php echo htmlspecialchars($sz['size']); ?> (<?php echo (int)$sz['quantity']; ?> left)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" name="add_to_cart" class="btn btn-block">Add to Cart</button>
                        <?php else: ?>
                            <p class="text-danger" style="font-weight:600; margin:0;">Out of Stock</p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('heroCarousel');
    if (!root) return;

    var slides = root.querySelectorAll('.hero-slide');
    if (slides.length <= 1) return;

    var dots     = root.querySelectorAll('.hero-dot');
    var prevBtn  = root.querySelector('.hero-prev');
    var nextBtn  = root.querySelector('.hero-next');
    var progress = root.querySelector('.hero-progress');

    var interval = parseInt(
        getComputedStyle(root).getPropertyValue('--hero-interval'),
        10
    ) || 2000;

    var current = 0;
    var timer   = null;

    function goTo(index) {
        current = (index + slides.length) % slides.length;

        slides.forEach(function (s, i) {
            s.classList.toggle('is-active', i === current);
        });
        dots.forEach(function (d, i) {
            d.classList.toggle('is-active', i === current);
        });

        // Restart progress bar
        if (progress) {
            progress.classList.remove('is-running');
            // Force reflow so the animation restarts
            void progress.offsetWidth;
            progress.classList.add('is-running');
        }
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    function start() {
        stop();
        timer = setInterval(next, interval);
        if (progress) progress.classList.add('is-running');
    }

    function stop() {
        if (timer) { clearInterval(timer); timer = null; }
    }

    function pause() {
        root.classList.add('is-paused');
        stop();
    }

    function resume() {
        root.classList.remove('is-paused');
        start();
    }

    // Dot clicks
    dots.forEach(function (dot) {
        dot.addEventListener('click', function () {
            var idx = parseInt(dot.getAttribute('data-index'), 10) || 0;
            goTo(idx);
            resume();
        });
    });

    // Arrow clicks
    if (prevBtn) prevBtn.addEventListener('click', function () { prev(); resume(); });
    if (nextBtn) nextBtn.addEventListener('click', function () { next(); resume(); });

    // Pause on hover, resume on leave
    root.addEventListener('mouseenter', pause);
    root.addEventListener('mouseleave', resume);

    // Pause when the tab is hidden
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) stop();
        else start();
    });

    // Touch swipe (basic)
    var startX = 0, endX = 0;
    root.addEventListener('touchstart', function (e) {
        startX = e.changedTouches[0].screenX;
    }, { passive: true });
    root.addEventListener('touchend', function (e) {
        endX = e.changedTouches[0].screenX;
        var diff = startX - endX;
        if (Math.abs(diff) > 50) {
            diff > 0 ? next() : prev();
        }
        resume();
    });

    // Kick it off
    start();
})();
</script>
</body>
</html>