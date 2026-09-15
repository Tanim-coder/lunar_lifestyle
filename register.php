<?php
require 'config.php';

if(isset($_POST['register'])){
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $pass = $_POST['password'];
    
    $conn->query("INSERT INTO users(name,email,password) VALUES('$name','$email','$pass')");
    $message = "Registration Successful. <a href='login.php' style='color: #4f46e5;'>Login here</a>";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css">
</head>
<body style="display: flex; justify-content: center; align-items: center; height: 100vh;">
<div style="background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 400px;">
    <h2 style="text-align: center; margin-bottom: 20px;">Create Account</h2>
    <?php if(isset($message)) echo "<p style='text-align:center; margin-bottom:15px;'>$message</p>"; ?>
    <form method="post">
        <label>Full Name</label>
        <input type="text" name="name" required>
        <label>Email</label>
        <input type="email" name="email" required>
        <label>Password</label>
        <input type="password" name="password" required>
        <button type="submit" name="register" class="btn" style="width: 100%; margin-top: 10px;">Register</button>
    </form>
    <p style="text-align: center; margin-top: 20px; font-size: 14px;">
        Already have an account? <a href="login.php" style="color: #000; font-weight: 600;">Login</a>
    </p>
</div>
</body>
</html>