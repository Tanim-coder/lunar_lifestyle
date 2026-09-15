<?php
require 'config.php';

$error = "";

if (isset($_POST['login'])) {
    csrf_check();
    $login_id = trim($_POST['login_id']);
    $pass     = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->bind_param("s", $login_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($admin && password_verify($pass, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = $admin['username'];
        header("Location: admin_panel.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $login_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($pass, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user']    = $user['name'];
        header("Location: index.php");
        exit();
    }

    $error = "Invalid credentials. Please try again.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css?v=3">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <h2>Lunar Lifestyle Login</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <?php echo csrf_field(); ?>
            <label for="login_id" style="margin-top:0;">Email or Username</label>
            <input type="text" id="login_id" name="login_id" placeholder="Enter email or admin username" required autocomplete="username">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">

            <button type="submit" name="login" class="btn btn-block mt-4">Login</button>

            <p class="meta">Don't have an account? <a href="register.php">Register here</a></p>
        </form>
    </div>
</div>
</body>
</html>