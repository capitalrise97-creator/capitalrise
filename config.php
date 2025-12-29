<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'capitalrise_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8");

// Function to get setting value
function getSetting($key, $conn) {
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    return null;
}

// Function to log activity
function logActivity($user_id, $user_type, $action, $details, $conn) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $device_info = $_SERVER['HTTP_USER_AGENT'];
    
    $sql = "INSERT INTO activity_logs (user_id, user_type, action, details, ip_address, device_info) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $user_id, $user_type, $action, $details, $ip_address, $device_info);
    $stmt->execute();
}

// Check if user is logged in
function isUserLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'user';
}

// Check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
}

// Generate unique ID
function generateUniqueID($prefix, $conn) {
    $unique = false;
    $new_id = '';
    
    while (!$unique) {
        $random = mt_rand(1000, 9999);
        $new_id = $prefix . $random;
        
        // Check if exists in users table
        $sql = "SELECT id FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $new_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $unique = true;
        }
    }
    
    return $new_id;
}
?>