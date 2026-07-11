<?php
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: minor.php");
    exit;
}

// Prevent admin from placing orders
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
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
            <h1>❌ Admins Cannot Place Orders</h1>
            <p>Admin accounts are for managing orders and products only.</p>
            <p>Please use a regular customer account to place orders.</p>
            <a href='admin_dashboard.php' class='btn btn-primary mt-3'>Go to Admin Dashboard</a>
        </div>
    </body>
    </html>
    ");
}

require_once "config.php";
require_once __DIR__ . '/includes/notification_service.php';
require_once __DIR__ . '/includes/delivery_otp_service.php';

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$error_msg = $success_msg = "";

if ($product_id <= 0) {
    die("Invalid product. <a href='user_dashboard.php'>Go back to dashboard</a>");
}

// Get product details
$sql = "SELECT * FROM product WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$product_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($product_result) == 0) {
    die("Product not found");
}

$product = mysqli_fetch_assoc($product_result);

// Get user details
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $quantity = (int)$_POST['quantity'];
    $customer_name = sanitize_input($_POST['customer_name']);
    $customer_email = sanitize_input($_POST['customer_email']);
    $customer_phone = sanitize_input($_POST['customer_phone']);
    $street = sanitize_input($_POST['street']);
    $city = sanitize_input($_POST['city']);
    $state = sanitize_input($_POST['state']);
    $postal_code = sanitize_input($_POST['postal_code']);
    $country = sanitize_input($_POST['country']);
    $payment_method = 'COD';
    
    // Validation
    if ($quantity <= 0 || $quantity > $product['quantity']) {
        $error_msg = "Invalid quantity. Available stock: " . $product['quantity'];
    } elseif (empty($customer_name) || empty($customer_email) || empty($customer_phone) || 
              empty($street) || empty($city) || empty($state) || 
              empty($postal_code) || empty($country)) {
        $error_msg = "All address fields are required";
    } else {
        // Generate order number
        $order_number = "ORD" . time() . rand(100, 999);
        $total_amount = $product['price'] * $quantity;
        
        // Insert order
        $order_sql = "INSERT INTO orders (user_id, order_number, total_amount, customer_name, customer_email, customer_phone, street, city, state, postal_code, country, payment_method, status, placed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Order Placed', NOW())";
        $order_stmt = mysqli_prepare($conn, $order_sql);
        
        if ($order_stmt === false) {
            $error_msg = "Database error: " . mysqli_error($conn) . "<br><strong>Please ensure the database tables are created by running database_setup.sql</strong>";
        } else {
            mysqli_stmt_bind_param($order_stmt, "isdsssssssss", $user_id, $order_number, $total_amount, $customer_name, $customer_email, $customer_phone, $street, $city, $state, $postal_code, $country, $payment_method);
            
            if (mysqli_stmt_execute($order_stmt)) {
                $order_id = mysqli_insert_id($conn);
                
                // Insert order items
                $subtotal = $product['price'] * $quantity;
                $item_sql = "INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?)";
                $item_stmt = mysqli_prepare($conn, $item_sql);
                mysqli_stmt_bind_param($item_stmt, "iisidi", $order_id, $product_id, $product['name'], $product['price'], $quantity, $subtotal);
                mysqli_stmt_execute($item_stmt);
                
                // Update product quantity
                $new_qty = $product['quantity'] - $quantity;
                $update_sql = "UPDATE product SET quantity = ? WHERE id = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "ii", $new_qty, $product_id);
                mysqli_stmt_execute($update_stmt);
                
                // Log activity
                log_activity($conn, $user_id, "Order Placed", "Order #" . $order_number . " placed for " . $product['name']);
                
                // Create Order Placed notification for the customer and admins
                create_notification($conn, $user_id, $order_id, 'order_placed', "Order Placed!", "Your order #" . $order_number . " has been placed successfully. We will start processing it soon.", "track_order.php?order_id=" . $order_id);
                notify_admins($conn, $order_id, 'new_order', 'New Order Received', 'A new order #' . $order_number . ' has been placed.', 'admin_orders_manage.php');
                
                // Insert first status history entry
                $history_stmt = mysqli_prepare($conn, "INSERT INTO order_status_history (order_id, status, changed_by, note) VALUES (?, ?, ?, ?)");
                $status_text = "Order Placed";
                $changed_by = $user_id;
                $note = "Order created by customer";
                mysqli_stmt_bind_param($history_stmt, "isis", $order_id, $status_text, $changed_by, $note);
                mysqli_stmt_execute($history_stmt);

                // Generate and send the delivery OTP immediately after checkout to the customer's registered email.
                $otp_result = generate_delivery_otp($conn, $order_id, $user_id, null, true);
                if (is_array($otp_result) && !empty($otp_result['otp_id'])) {
                    $otp_sent = send_delivery_otp($conn, $otp_result['otp_id'], 'email');
                    if ($otp_sent) {
                        $success_msg = "Order placed successfully! Order #" . $order_number . " and the delivery OTP has been emailed to you.";
                    } else {
                        $success_msg = "Order placed successfully! Order #" . $order_number . " but the delivery OTP could not be emailed immediately. An admin can resend it later.";
                    }
                } else {
                    $success_msg = "Order placed successfully! Order #" . $order_number . " but OTP setup was skipped.";
                }
                header("refresh:2;url=orders_new.php");
            } else {
                $error_msg = "Error placing order: " . mysqli_error($conn);
            }
            mysqli_stmt_close($order_stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout - Bazario</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/BAZARIO_STYLES.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .checkout-container {
            max-width: 900px;
            margin: 40px auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .checkout-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .checkout-header h2 {
            color: #333;
            font-weight: 700;
        }
        
        .product-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 4px solid #001a33;
        }
        
        .product-summary h4 {
            color: #333;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #666;
        }
        
        .summary-label {
            font-weight: 600;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h4 {
            color: #333;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #001a33;
            display: flex;
            align-items: center;
        }
        
        .form-section h4 i {
            margin-right: 10px;
            color: #001a33;
        }
        
        .form-group label {
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #001a33;
            box-shadow: 0 0 0 0.2rem rgba(0, 26, 51, 0.25);
        }
        
        .btn-checkout {
            background: #001a33;
            border: none;
            border-radius: 8px;
            padding: 14px;
            font-weight: 600;
            width: 100%;
            color: white;
            margin-top: 30px;
            transition: all 0.3s;
        }
        
        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 26, 51, 0.3);
            background: #003366;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #001a33;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }
        
        .custom-control {
            display: block;
            min-height: 1.5rem;
            padding-left: 1.5rem;
            margin-bottom: 20px;
        }
        
        .custom-radio .custom-control-label::before {
            border-radius: 50%;
            border: 2px solid #ddd;
        }
        
        .custom-radio .custom-control-input:checked ~ .custom-control-label::before {
            background-color: #001a33;
            border-color: #001a33;
        }
        
        .custom-control-label {
            position: relative;
            margin-bottom: 0;
            vertical-align: top;
            cursor: pointer;
            padding-left: 0.5rem;
        }
        
        .custom-control-label::before {
            position: absolute;
            top: 0.25rem;
            left: -1.5rem;
            display: block;
            width: 1rem;
            height: 1rem;
            pointer-events: none;
            content: "";
            background-color: #fff;
            border: 2px solid #adb5bd;
        }
        
        .custom-radio .custom-control-input:focus ~ .custom-control-label::before {
            box-shadow: 0 0 0 0.2rem rgba(0, 26, 51, 0.25);
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-header">
            <h2><i class="fas fa-shopping-cart"></i> Checkout</h2>
        </div>
        
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <strong><i class="fas fa-check-circle"></i> Success!</strong> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <strong><i class="fas fa-exclamation-circle"></i> Error!</strong> <?php echo $error_msg; ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>
        
        <div class="product-summary">
            <h4><i class="fas fa-box"></i> Order Summary</h4>
            <div class="summary-row">
                <span class="summary-label">Product:</span>
                <span><?php echo htmlspecialchars($product['name']); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Unit Price:</span>
                <span>₹<?php echo number_format($product['price'], 2); ?></span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Available Stock:</span>
                <span><?php echo $product['quantity']; ?> units</span>
            </div>
        </div>
        
        <form method="post">
            <div class="form-section">
                <h4><i class="fas fa-info-circle"></i> Order Details</h4>
                
                <div class="form-group">
                    <label for="quantity">Quantity <span style="color: red;">*</span></label>
                    <input type="number" class="form-control" name="quantity" id="quantity" 
                           min="1" max="<?php echo $product['quantity']; ?>" value="1" required>
                    <small class="text-muted">Maximum available: <?php echo $product['quantity']; ?></small>
                </div>
            </div>
            
            <div class="form-section">
                <h4><i class="fas fa-user"></i> Delivery Information</h4>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="customer_name">Full Name <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" name="customer_name" id="customer_name" 
                               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="customer_email">Email Address <span style="color: red;">*</span></label>
                        <input type="email" class="form-control" name="customer_email" id="customer_email" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="customer_phone">Phone Number <span style="color: red;">*</span></label>
                    <input type="tel" class="form-control" name="customer_phone" id="customer_phone" 
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>Delivery OTP</label>
                    <div class="alert alert-info mb-0">
                        A one-time delivery OTP will be sent automatically to your registered email address after checkout.
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4><i class="fas fa-map-marker-alt"></i> Complete Delivery Address</h4>
                <p style="color: #888; font-size: 14px; margin-bottom: 20px;">Please enter your complete address including street, city, and other details</p>
                
                <div class="form-group">
                    <label for="street">Street Address <span style="color: red;">*</span></label>
                    <input type="text" class="form-control" name="street" id="street" 
                           placeholder="e.g., Main Street, Gandhi Road" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="city">City <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" name="city" id="city" 
                               placeholder="e.g., Mumbai, Delhi, Bangalore" required>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="state">State / Province <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" name="state" id="state" 
                               placeholder="e.g., Maharashtra, Delhi" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="postal_code">Postal Code / ZIP <span style="color: red;"></span></label>
                        <input type="text" class="form-control" name="postal_code" id="postal_code" 
                               placeholder="e.g., 400001">
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="country">Country <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" name="country" id="country" 
                               placeholder="e.g., India" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4><i class="fas fa-credit-card"></i> Payment Method</h4>
                <p style="color: #27ae60; font-weight: 600; margin-bottom: 15px;">
                    <i class="fas fa-info-circle"></i> Cash on Delivery (COD)
                </p>
                <p style="color: #666; font-size: 14px;">
                    Your order will be delivered to your address. You can pay when your order arrives at your doorstep.
                </p>
            </div>
            
            <button type="submit" class="btn btn-checkout">
                <i class="fas fa-credit-card"></i> Place Order
            </button>
        </form>
        
        <div class="back-link">
            <a href="user_dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>

<?php mysqli_close($conn); ?>

