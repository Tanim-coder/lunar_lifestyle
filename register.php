<?php
require 'config.php';

$message = "";
$isError = false;

if (isset($_POST['register'])) {
    csrf_check();
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];

    if ($name === '' || $email === '' || $pass === '') {
        $message = "All fields are required.";
        $isError = true;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $isError = true;
    } elseif (strlen($pass) < 6) {
        $message = "Password must be at least 6 characters.";
        $isError = true;
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $hash);
        if ($stmt->execute()) {
            $message = "Registration successful. <a href='login.php'>Login here</a>";
        } else {
            $message = "That email is already registered.";
            $isError = true;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css?v=3">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <h2>Create Account</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $isError ? 'error' : 'success'; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post">
            <?php echo csrf_field(); ?>
            <label style="margin-top:0;">Full Name</label>
            <input type="text" name="name" required>

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" minlength="6" required>

            <button type="submit" name="register" class="btn btn-block mt-4">Register</button>

            <p class="meta">Already have an account? <a href="login.php">Login</a></p>
        </form>
    </div>
</div>
</body>
</html>