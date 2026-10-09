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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name']);
    $category = sanitize_input($_POST['category']);
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    $description = sanitize_input($_POST['description']);
    
    $errors = [];
    
    // Validate inputs
    if (empty($name)) {
        $errors[] = "Product name is required";
    }
    
    if (empty($category)) {
        $errors[] = "Category is required";
    }
    
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0";
    }
    
    if ($quantity < 0) {
        $errors[] = "Quantity cannot be negative";
    }
    
    // Validate image
    if (!isset($_FILES['image']) || $_FILES['image']['error'] != 0) {
        $errors[] = "Product image is required";
    } else {
        $image_errors = validate_image($_FILES['image']);
        $errors = array_merge($errors, $image_errors);
    }
    
    // If no errors, process the upload and insert into database
    if (empty($errors)) {
        $new_filename = generate_unique_filename($_FILES['image']['name']);
        $upload_path = "uploads/" . $new_filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            // Insert into database using prepared statement
            $sql = "INSERT INTO product (name, category, price, quantity, description, image) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssdiss", $name, $category, $price, $quantity, $description, $new_filename);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Log activity
                    log_activity($conn, $_SESSION['user_id'], "Added Product", "Added new product: " . $name);
                    
                    // Redirect to dashboard with success message
                    $_SESSION['success_message'] = "Product added successfully!";
                    header("Location: user_dashboard.php");
                    exit;
                } else {
                    $errors[] = "Database error: " . mysqli_error($conn);
                    // Delete uploaded file if database insert failed
                    if (file_exists($upload_path)) {
                        unlink($upload_path);
                    }
                }
                mysqli_stmt_close($stmt);
            } else {
                $errors[] = "Failed to prepare statement";
            }
        } else {
            $errors[] = "Failed to upload image file";
        }
    }
    
    // If there are errors, store them in session and redirect back
    if (!empty($errors)) {
        $_SESSION['product_errors'] = $errors;
        $_SESSION['product_form_data'] = $_POST;
        header("Location: product.html");
        exit;
    }
}

mysqli_close($conn);

// If accessed directly (not via POST), redirect to product form
header("Location: product.html");
exit;
?>

