<?php
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: minor.php");
    exit;
}

// Restrict to admin only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
        <link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css'>
        <style>
            body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f8f9fa; }
            .error-container { text-align: center; padding: 40px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .error-container h1 { color: #dc3545; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <h1>❌ Access Denied</h1>
            <p>Only administrators can access this page.</p>
            <a href='user_dashboard.php' class='btn btn-primary mt-3'>Go to Dashboard</a>
        </div>
    </body>
    </html>
    ");
}

require_once "config.php";

if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // Sanitize input
    
    // First, get the product details
    $sql = "SELECT name, image FROM product WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $product = mysqli_fetch_assoc($result);
            $product_name = $product['name'];
            $image_path = "uploads/" . $product['image'];
            
            // Delete the image file if it exists
            if (!empty($product['image']) && file_exists($image_path)) {
                unlink($image_path);
            }
            
            // Delete product from database
            $delete_sql = "DELETE FROM product WHERE id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            
            if ($delete_stmt) {
                mysqli_stmt_bind_param($delete_stmt, "i", $id);
                
                if (mysqli_stmt_execute($delete_stmt)) {
                    // Log activity
                    log_activity($conn, $_SESSION['user_id'], "Deleted Product", "Deleted product: " . $product_name);
                    
                    $_SESSION['success_message'] = "Product deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Error deleting product: " . mysqli_error($conn);
                }
                mysqli_stmt_close($delete_stmt);
            }
        } else {
            $_SESSION['error_message'] = "Product not found";
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Database error occurred";
    }
} else {
    $_SESSION['error_message'] = "No product ID provided";
}

mysqli_close($conn);
header("Location: user_dashboard.php");
exit;
?>

