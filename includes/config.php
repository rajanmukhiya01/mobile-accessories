<?php
/**
 * Database Configuration File
 * Contains database connection settings and helper functions
 */

// Database credentials
define('DB_SERVER', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'Mproject');

// Create database connection (use TCP host and explicit port)
$conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);

// Check connection and provide clear troubleshooting hints
if ($conn === false) {
    $err = mysqli_connect_error();
    die("ERROR: Could not connect to database. " . $err . "\nHint: Start MySQL (XAMPP Control Panel) and ensure host=127.0.0.1 port=3306 and credentials in config.php are correct.");
}

// Set charset to utf8mb4 for better security and emoji support
mysqli_set_charset($conn, "utf8mb4");

/**
 * Helper function to sanitize input
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Helper function to log user activity
 */
function log_activity($conn, $user_id, $action, $description = '') {
    $sql = "INSERT INTO activity_log (user_id, action, description) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $action, $description);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Helper function to validate image file
 */
function validate_image($file) {
    $errors = [];
    
    // Check if file exists
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        $errors[] = "No file uploaded";
        return $errors;
    }
    
    // Check file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $max_size) {
        $errors[] = "File size must be less than 5MB";
    }
    
    // Check file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = "Only JPG, PNG, GIF, and WEBP files are allowed";
    }
    
    // Check if it's actually an image
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $errors[] = "File is not a valid image";
    }
    
    return $errors;
}

/**
 * Helper function to generate unique filename
 */
function generate_unique_filename($original_filename) {
    $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Helper function to check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

/**
 * Helper function to redirect to login page
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: ../public/index.php");
        exit;
    }
}

/**
 * Helper function to get user statistics
 */
function get_user_stats($conn, $user_id) {
    $stats = [
        'total_products' => 0,
        'total_value' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0
    ];
    
    // Total products
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM product");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['total_products'] = $row['count'];
    }
    
    // Total inventory value
    $result = mysqli_query($conn, "SELECT SUM(price * quantity) as total FROM product");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['total_value'] = $row['total'] ?? 0;
    }
    
    // Low stock items (quantity < 10)
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM product WHERE quantity > 0 AND quantity < 10");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['low_stock'] = $row['count'];
    }
    
    // Out of stock items
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM product WHERE quantity = 0");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['out_of_stock'] = $row['count'];
    }
    
    return $stats;
}

/**
 * Helper function to format currency
 */
function format_currency($amount) {
    return '₹' . number_format($amount, 2);
}
?>
