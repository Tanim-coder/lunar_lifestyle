<?php
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$db   = "lunar_lifestyle";

// Connect to MySQL
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Create Database and Select it
$conn->query("CREATE DATABASE IF NOT EXISTS $db");
$conn->select_db($db);

// 1. Users Table
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255)
)");

// 2. Admin Table & Default Admin Account
$conn->query("CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE,
    password VARCHAR(255)
)");
$conn->query("INSERT IGNORE INTO admin (username, password) VALUES ('admin', 'admin123')");

// 3. Products Table (Base product details)
$conn->query("CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    description TEXT,
    price DECIMAL(10,2),
    discount_percent INT DEFAULT 0,
    image VARCHAR(255)
)");

// 4. Product Sizes Table (Manages stock for individual sizes)
$conn->query("CREATE TABLE IF NOT EXISTS product_sizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    size VARCHAR(50),
    quantity INT DEFAULT 0
)");

// 5. Orders Table
$conn->query("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    address TEXT,
    total DECIMAL(10,2),
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// 6. Order Items Table (Includes specific size ordered)
$conn->query("CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    product_id INT,
    size VARCHAR(50),
    qty INT,
    price DECIMAL(10,2)
)");
?>