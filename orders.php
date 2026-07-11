<?php
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: minor.php");
    exit;
}

// Prevent admin from accessing customer order pages
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin_orders_manage.php");
    exit;
}

// Unified "My Orders" interface - redirect to orders_new.php
// This ensures all user-facing order links point to a single unified interface
header("Location: orders_new.php");
exit;

require_once "config.php";
require_once __DIR__ . '/includes/notification_service.php';

$user_id = $_SESSION['user_id'];

// Handle AJAX update check request
if (isset($_GET['check_updates']) && $_GET['check_updates'] == 1) {
    $unread_count = get_unread_notifications_count($conn, $user_id);
    // Get latest notification timestamp for this user
    $sql = "SELECT MAX(created_at) as last_notif FROM notifications WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    $last_notif = null;
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        $last_notif = $row['last_notif'];
        mysqli_stmt_close($stmt);
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'notification_count' => intval($unread_count),
        'last_notification_at' => $last_notif
    ]);
    mysqli_close($conn);
    exit;
}

$unread_count = get_unread_notifications_count($conn, $user_id);
$recent_notifications = get_user_notifications($conn, $user_id, 5, 0);
$username = htmlspecialchars($_SESSION['username']);
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Get last notification timestamp for initial page load
$last_notification_at = null;
$sql_last = "SELECT MAX(created_at) as last_notif FROM notifications WHERE user_id = ?";
$stmt_last = mysqli_prepare($conn, $sql_last);
if ($stmt_last) {
    mysqli_stmt_bind_param($stmt_last, "i", $user_id);
    mysqli_stmt_execute($stmt_last);
    $res_last = mysqli_stmt_get_result($stmt_last);
    $row_last = mysqli_fetch_assoc($res_last);
    $last_notification_at = $row_last['last_notif'];
    mysqli_stmt_close($stmt_last);
}

// Get success/error messages
$success_msg = '';
$error_msg = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'status_updated') {
        $success_msg = "Order status updated successfully!";
    }
}

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'invalid_status') {
        $error_msg = "Invalid status selected.";
    } elseif ($_GET['error'] === 'order_not_found') {
        $error_msg = "Order not found.";
    } elseif ($_GET['error'] === 'update_failed') {
        $error_msg = "Failed to update order status. Please try again.";
    }
}

// Get orders based on user role
if ($is_admin) {
    // Admin sees all orders with customer usernames
    $sql = "SELECT o.*, u.username as user_username FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            ORDER BY o.created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_execute($stmt);
} else {
    // Regular user sees only their orders
    $sql = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
}

$orders_result = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Orders - Bazario</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/BAZARIO_STYLES.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, #001a33 0%, #003366 100%);
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .container-main {
            display: flex;
            min-height: calc(100vh - 70px);
        }
        
        .sidebar {
            width: 250px;
            background: #001a33;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: calc(100vh - 70px);
            overflow-y: auto;
        }
        
        .sidebar a, .sidebar button {
            display: block;
            width: 100%;
            color: #ecf0f1;
            padding: 15px 20px;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            border: none;
            background: none;
            text-align: left;
            cursor: pointer;
            font-size: 15px;
        }
        
        .sidebar a:hover, .sidebar button:hover {
            background: #003366;
            border-left-color: #3498db;
            padding-left: 30px;
        }
        
        .sidebar a i, .sidebar button i {
            margin-right: 10px;
            width: 20px;
        }
        
        .sidebar-logout-btn {
            display: block;
            width: 100%;
            color: #ecf0f1;
            padding: 15px 20px;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            border: none;
            background: none;
            text-align: left;
            cursor: pointer;
            font-size: 15px;
        }
        
        .sidebar-logout-btn:hover {
            background: #003366;
            border-left-color: #e74c3c;
            padding-left: 30px;
        }
        
        .sidebar-logout-btn i {
            margin-right: 10px;
            width: 20px;
        }
        
        .content {
            margin-left: 250px;
            padding: 30px;
            flex: 1;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 15px 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar-brand {
            font-size: 24px;
            font-weight: 700;
            color: white;
            margin: 0;
        }
        
        .nav-links {
            display: flex;
            gap: 10px;
            align-items: center;
            margin: 0;
        }
        
        .nav-links a, .nav-links button {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 4px;
            transition: all 0.3s;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 500;
        }
        
        .nav-links a:hover, .nav-links button:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.1) !important;
        }
        
        .logout-btn:hover {
            background: rgba(255,0,0,0.3) !important;
        }
        
        .container-main {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .page-title {
            font-size: 32px;
            font-weight: 700;
            color: white;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .order-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            transition: all 0.3s;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .order-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .order-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-number {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .order-date {
            font-size: 13px;
            color: #888;
        }
        
        .order-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-processing {
            background: #cfe2ff;
            color: #084298;
        }
        
        .status-shipped {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .status-delivered {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #842029;
        }
        
        .order-body {
            padding: 25px;
        }
        
        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .info-item {
            border-left: 3px solid #667eea;
            padding-left: 15px;
        }
        
        .info-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .info-value {
            font-size: 15px;
            color: #333;
            font-weight: 500;
        }
        
        .order-items {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-top: 2px solid #e0e0e0;
        }
        
        .items-title {
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .item-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .item-qty {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }
        
        .item-price {
            font-weight: 700;
            color: #667eea;
            font-size: 14px;
        }
        
        .order-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
            font-weight: 700;
            font-size: 16px;
            color: #333;
        }
        
        .order-total span:last-child {
            color: #667eea;
            font-size: 18px;
        }
        
        .empty-state {
            background: white;
            border-radius: 12px;
            padding: 60px 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .empty-state i {
            font-size: 80px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .empty-state p {
            font-size: 18px;
            color: #888;
            margin-bottom: 25px;
        }
        
        .btn-shop {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-shop:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-wrap: wrap;
            }
            
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .order-info {
                grid-template-columns: 1fr;
            }
        }
        
        .payment-badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .payment-cod {
            background: #fff3cd;
            color: #856404;
        }
        
        .payment-online {
            background: #cfe2ff;
            color: #084298;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5568d3 0%, #6a3f8a 100%);
            border: none;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php if ($is_admin): ?>
        <!-- Navigation for Admin -->
        <nav class="navbar">
            <span class="navbar-brand">
                <i class="fas fa-mobile-alt"></i> Mobile Accessories
            </span>
            <div class="nav-links">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <!-- Notification Bell -->
                    <div style="position: relative;">
                        <a href="notifications.php" style="color: white; text-decoration: none; position: relative;">
                            <i class="fas fa-bell"></i>
                            <?php if (!empty($unread_count) && $unread_count > 0): ?>
                                <span style="position: absolute; top: -6px; right: -10px; background: #e74c3c; color: white; font-size: 11px; padding: 2px 6px; border-radius: 12px;"><?php echo (int)$unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>

                    <a href="admin_dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="profile.php">
                        <i class="fas fa-user-circle"></i> Profile
                    </a>
                    <form action="logout.php" method="POST" style="margin: 0; display: inline;">
                        <button type="submit" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
        </nav>
    <?php else: ?>
        <!-- Header for Regular Users -->
        <div class="header" style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-mobile-alt"></i> Mobile Accessories
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <!-- Notification Bell -->
                <a href="notifications.php" style="color: white; text-decoration: none; position: relative;">
                    <i class="fas fa-bell" style="font-size: 18px;"></i>
                    <?php if (!empty($unread_count) && $unread_count > 0): ?>
                        <span style="position: absolute; top: -6px; right: -10px; background: #e74c3c; color: white; font-size: 11px; padding: 2px 6px; border-radius: 12px;"><?php echo (int)$unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="profile.php" style="color: white; text-decoration: none;">
                    <i class="fas fa-user-circle" style="font-size: 18px;"></i>
                </a>
            </div>
        </div>
        
        <!-- Sidebar for Regular Users -->
        <div class="container-main">
            <div class="sidebar">
                <a href="user_dashboard.php">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="orders.php">
                    <i class="fas fa-shopping-bag"></i> My Orders
                </a>
                <a href="profile.php">
                    <i class="fas fa-user-circle"></i> Profile
                </a>
                <form action="logout.php" method="POST" style="margin: 0; padding: 0;">
                    <button type="submit" class="sidebar-logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
            <div class="content">
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container-main">
        <h1 class="page-title">
            <i class="fas fa-shopping-bag"></i>
            My Orders
        </h1>
        
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <strong>Success!</strong> <?php echo $success_msg; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <strong>Error!</strong> <?php echo $error_msg; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (mysqli_num_rows($orders_result) > 0): ?>
            <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                            <div class="order-date">
                                <?php if ($is_admin && isset($order['user_username'])): ?>
                                    By <strong><?php echo htmlspecialchars($order['user_username']); ?></strong> • 
                                <?php endif; ?>
                                <?php echo date('M d, Y • h:i A', strtotime($order['created_at'])); ?>
                            </div>
                        </div>
                        <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                            <?php echo htmlspecialchars($order['status']); ?>
                        </span>
                    </div>
                    
                    <div class="order-body">
                        <div class="order-info">
                            <div class="info-item">
                                <div class="info-label">Customer Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['customer_name'] ?? 'Not provided'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['customer_email'] ?? 'Not provided'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($order['customer_phone'] ?? 'Not provided'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment Method</div>
                                <div class="info-value">
                                    <?php if (($order['payment_method'] ?? 'COD') == 'COD'): ?>
                                        <span class="payment-badge payment-cod"><i class="fas fa-money-bill"></i> Cash on Delivery</span>
                                    <?php else: ?>
                                        <span class="payment-badge payment-online"><i class="fas fa-credit-card"></i> Online Payment</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745;">
                            <div style="font-weight: 700; color: #333; margin-bottom: 15px; font-size: 14px;">📍 Delivery Address</div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                <div>
                                    <div style="font-size: 11px; color: #888; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;">Address</div>
                                    <div style="font-size: 14px; color: #333; font-weight: 500;"><?php 
                                        $address_display = !empty($order['street']) ? htmlspecialchars($order['street']) : 'Address not provided';
                                        echo $address_display;
                                    ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: #888; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;">City</div>
                                    <div style="font-size: 14px; color: #333; font-weight: 500;"><?php echo htmlspecialchars($order['city'] ?? 'Not specified'); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: #888; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;">State</div>
                                    <div style="font-size: 14px; color: #333; font-weight: 500;"><?php echo htmlspecialchars($order['state'] ?? 'Not specified'); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: #888; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;">Postal Code</div>
                                    <div style="font-size: 14px; color: #333; font-weight: 500;"><?php echo htmlspecialchars($order['postal_code'] ?? 'Not provided'); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 11px; color: #888; text-transform: uppercase; font-weight: 600; margin-bottom: 5px;">Country</div>
                                    <div style="font-size: 14px; color: #333; font-weight: 500;"><?php echo htmlspecialchars($order['country'] ?? 'Not specified'); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($is_admin): ?>
                        <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2196F3;">
                            <form method="post" action="update_order_status.php" style="display: flex; align-items: center; gap: 15px;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <div style="flex: 1;">
                                    <label style="font-weight: 600; color: #333; margin-bottom: 8px; display: block; font-size: 13px;">Update Delivery Status:</label>
                                    <select name="status" class="form-control" style="display: inline-block; width: auto; min-width: 200px;">
                                        <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Confirmed" <?php echo $order['status'] === 'Confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="Shipped" <?php echo $order['status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="Delivered" <?php echo $order['status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="Cancelled" <?php echo $order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary" style="margin-top: 22px;">
                                    <i class="fas fa-save"></i> Update Status
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <div class="order-items">
                            <div class="items-title">📦 Items Ordered</div>
                            <?php 
                                $items_sql = "SELECT * FROM order_items WHERE order_id = ?";
                                $items_stmt = mysqli_prepare($conn, $items_sql);
                                mysqli_stmt_bind_param($items_stmt, "i", $order['id']);
                                mysqli_stmt_execute($items_stmt);
                                $items_result = mysqli_stmt_get_result($items_stmt);
                                
                                while ($item = mysqli_fetch_assoc($items_result)):
                            ?>
                                <div class="item-row">
                                    <div>
                                        <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        <div class="item-qty">Qty: <?php echo $item['quantity']; ?> × ₹<?php echo number_format($item['price'], 2); ?></div>
                                    </div>
                                    <div class="item-price">₹<?php echo number_format($item['subtotal'], 2); ?></div>
                                </div>
                            <?php endwhile; ?>
                            
                            <div class="order-total">
                                <span>Total Amount:</span>
                                <span>₹<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <?php if ($is_admin): ?>
                    <p>No orders have been placed yet</p>
                    <a href="admin_dashboard.php" class="btn-shop">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                <?php else: ?>
                    <p>You haven't placed any orders yet</p>
                    <a href="user_dashboard.php" class="btn-shop">
                        <i class="fas fa-shopping-bag"></i> Browse Products
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <!-- End orders container -->
    
    <?php if (!$is_admin): ?>
        </div>
        <!-- End content -->
        </div>
        <!-- End container-main -->
    <?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Real-time status update checker
        let lastNotificationCount = <?php echo $unread_count; ?>;
        let lastNotificationAt = <?php echo json_encode($last_notification_at); ?>;
        let updateCheckInterval = null;
        
        function checkForStatusUpdates() {
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?check_updates=1', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            })
            .then(response => response.json())
            .then(data => {
                if (!data || !data.success) return;

                // Prefer detecting by latest notification timestamp
                if (data.last_notification_at && (!lastNotificationAt || data.last_notification_at > lastNotificationAt)) {
                    lastNotificationAt = data.last_notification_at;
                    lastNotificationCount = data.notification_count || lastNotificationCount;
                    showUpdateNotification('New order status update! Refreshing page...');
                    setTimeout(() => { location.reload(); }, 1500);
                    return;
                }

                // Fallback: detect by unread count
                if (data.notification_count > lastNotificationCount) {
                    lastNotificationCount = data.notification_count;
                    showUpdateNotification('New order status update! Refreshing page...');
                    setTimeout(() => { location.reload(); }, 1500);
                }
            })
            .catch(error => console.log('Update check error:', error));
        }
        
        function showUpdateNotification(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-info alert-dismissible fade show';
            alertDiv.style.position = 'fixed';
            alertDiv.style.top = '70px';
            alertDiv.style.left = '0';
            alertDiv.style.right = '0';
            alertDiv.style.zIndex = '9999';
            alertDiv.style.margin = '0';
            alertDiv.style.borderRadius = '0';
            alertDiv.innerHTML = `
                <div class="container">
                    <i class="fas fa-bell"></i> <strong>Update!</strong> ${message}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            `;
            document.body.insertBefore(alertDiv, document.body.firstChild);
            
            // Auto dismiss after 10 seconds
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 10000);
        }
        
        // Start checking for updates every 5 seconds
        function startUpdateChecks() {
            // Check immediately on page load
            checkForStatusUpdates();
            
            // Then check every 5 seconds
            updateCheckInterval = setInterval(checkForStatusUpdates, 5000);
        }
        
        // Stop checking when page is unloaded
        window.addEventListener('beforeunload', function() {
            if (updateCheckInterval) {
                clearInterval(updateCheckInterval);
            }
        });
        
        // Start checking when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startUpdateChecks();
        });
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>

