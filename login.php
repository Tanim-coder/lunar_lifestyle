<?php
require 'config.php';

if(isset($_POST['login'])){
    // We call this login_id because it can be an Email (for users) OR a Username (for admins)
    $login_id = $conn->real_escape_string($_POST['login_id']); 
    $pass = $_POST['password'];
    
    // 1. Check if the user is an Admin first
    $admin_res = $conn->query("SELECT * FROM admin WHERE username='$login_id' AND password='$pass'");
    
    if($admin_res->num_rows == 1){
        $_SESSION['admin'] = $login_id; // Set admin session
        header("Location: admin_panel.php"); // Redirect to Admin Dashboard
        exit();
    } 
    
    // 2. If not an admin, check if they are a Customer
    $user_res = $conn->query("SELECT * FROM users WHERE email='$login_id' AND password='$pass'");
    
    if($user_res->num_rows == 1){
        $user = $user_res->fetch_assoc();
        $_SESSION['user_id'] = $user['id']; // Set customer ID for cart/orders
        $_SESSION['user'] = $user['name'];
        header("Location: index.php"); // Redirect to Storefront
        exit();
    } 
    
    // 3. If no match in either table, throw an error
    $error = "Invalid Credentials. Please try again.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - LUNAR LIFESTYLE</title>
    <link rel="stylesheet" href="lunar.css">
</head>
<body style="display: flex; justify-content: center; align-items: center; height: 100vh;">
<div style="background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 400px;">
    <h2 style="text-align: center; margin-bottom: 20px;">Lunar Lifestyle Login</h2>
    
    <?php if(isset($error)) echo "<p style='color: #d9534f; background: #fdf2f2; padding: 10px; border-radius: 4px; text-align:center; font-size: 14px;'>$error</p>"; ?>
    
    <form method="post" action="login.php" id="loginForm">
        <label for="login_id">Email or Username</label>
        <input type="text" id="login_id" name="login_id" placeholder="Enter email or admin username" required autocomplete="username">
        
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter password" required autocomplete="current-password">
        
        <button type="submit" name="login" class="btn" style="margin-top: 15px; width: 100%;">Login</button>
        
        <div style="text-align: center; margin-top: 20px; font-size: 14px;">
            Don't have an account? <a href="register.php" style="color: #4f46e5; text-decoration: none; font-weight: 600;">Register here</a>
        </div>
    </form>
</div>
</body>
</html>