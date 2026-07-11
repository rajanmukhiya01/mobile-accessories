<?php
require_once 'config.php';
require_once __DIR__ . '/includes/notification_service.php';
require_once __DIR__ . '/includes/user_common.php';
require_once __DIR__ . '/components/user_layout.php';


ensure_logged_in_user();

$context = get_current_user_context($conn);
$user_id = $context['user_id'];
$username = $context['username'];
$user = $context['user'];
$unread_count = $context['unread_count'];
$last_notification_at = $context['last_notification_at'];

// Prevent admin from accessing customer shop
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
            <h1>❌ Admin Cannot Access Shop</h1>
            <p>Admins can only view and manage orders, not place them.</p>
            <p>Please switch to a regular user account to shop.</p>
            <a href='admin_dashboard.php' class='btn btn-primary mt-3'>Go to Admin Dashboard</a>
        </div>
    </body>
    </html>
    ");
}

if (isset($_GET['check_updates']) && $_GET['check_updates'] == 1) {
    $unread_count = get_unread_notifications_count($conn, $user_id);
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

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'notification_count' => intval($unread_count),
        'last_notification_at' => $last_notification_at
    ]);
    mysqli_close($conn);
    exit;
}

// Get notifications
$recent_notifications = get_user_notifications($conn, $user_id, 5, 0);

// Fetch all products
$sql = "SELECT * FROM product ORDER BY id DESC";
$result = $conn->query($sql);

if (!$result) {
    die("Database query failed: " . $conn->error);
}

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop - Bazario</title>
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
            position: sticky;
            top: 0;
            z-index: 1100;
            width: 100%;
            min-height: 64px;
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
            top: 64px;
            left: 0;
            height: calc(100vh - 64px);
            z-index: 900;
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
        
        .content {
            margin-left: 250px;
            padding: 30px;
            flex: 1;
            background: #f8f9fa;
            padding-top: 24px;
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
        
        .welcome-section {
            background: white;
            margin: 30px;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .welcome-title {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }
        
        .welcome-subtitle {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 10px;
            color: white;
            text-align: center;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .products-section {
            padding: 30px;
        }
        
        .section-header {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 30px;
            padding: 0;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
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
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f0f0f0;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-category {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .product-name {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
            min-height: 40px;
            line-height: 1.4;
        }
        
        .product-desc {
            font-size: 13px;
            color: #888;
            margin-bottom: 12px;
            line-height: 1.5;
        }
        
        .product-stock {
            font-size: 12px;
            margin-bottom: 12px;
        }
        
        .product-stock.in-stock {
            color: #28a745;
        }
        
        .product-stock.out-stock {
            color: #dc3545;
        }
        
        .product-price {
            font-size: 22px;
            font-weight: 700;
            color: #27ae60;
            margin-bottom: 15px;
        }
        
        .btn-shop {
            display: block;
            width: 100%;
            background: #001a33;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-shop:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 26, 51, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .btn-shop:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: white;
        }
        
        .empty-state i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.8;
        }
        
        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        /* Avatar Styles */
        .avatar-sm { width: 32px; height: 32px; }
        .avatar-md { width: 48px; height: 48px; }
        .avatar-lg { width: 64px; height: 64px; }
        .avatar-xl { width: 80px; height: 80px; }
        
        .avatar-sm,
        .avatar-md,
        .avatar-lg,
        .avatar-xl {
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e0e0e0;
            display: block;
        }
        
        .avatar-default {
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            border: 2px solid #e0e0e0;
        }
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .welcome-section {
                margin: 20px;
                padding: 25px;
            }
            
            .section-header {
                padding: 0;
            }
            
            .sidebar {
                width: 200px;
            }
            
            .content {
                margin-left: 200px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->

    <div class="navbar">
        <div style="display: flex; align-items: center; gap: 15px; width: 100%;">
            <i class="fas fa-shopping-bag" style="font-size: 28px;"></i>
            <span class="navbar-brand" style="margin: 0;">BAZARIO</span>
            <span style="opacity: 0.9; font-size: 12px; margin-left: 12px;">Online Shopping Store</span>

            <div style="margin-left: auto; display: flex; align-items: center; gap: 12px;">
                <!-- Notification Bell -->
                <div style="position: relative;">
                    <a href="notifications.php" style="color: white; text-decoration: none; position: relative;">
                        <i class="fas fa-bell" style="font-size: 18px;"></i>
                        <?php if (!empty($unread_count) && $unread_count > 0): ?>
                            <span style="position: absolute; top: -6px; right: -10px; background: #e74c3c; color: white; font-size: 11px; padding: 2px 6px; border-radius: 12px;"><?php echo (int)$unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Quick dropdown (desktop) -->
                <div style="position: relative;">
                    <div style="background: transparent; color: white;">
                        <div style="position: absolute; right: 0; top: 36px; width: 320px; background: white; color: #333; border-radius: 6px; box-shadow: 0 6px 18px rgba(0,0,0,0.12); display: none; z-index: 50;" id="notif-dropdown">
                            <div style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 700;">Recent notifications</div>
                            <div style="max-height: 260px; overflow: auto;">
                                <?php if (!empty($recent_notifications)): ?>
                                    <?php foreach ($recent_notifications as $rn): ?>
                                        <div style="padding: 10px 12px; border-bottom: 1px solid #f5f5f5; display: flex; justify-content: space-between; align-items: center;">
                                            <div style="flex: 1; margin-right: 8px;">
                                                <div style="font-weight: 600; color: #001a33; font-size: 13px;"><?php echo htmlspecialchars($rn['title']); ?></div>
                                                <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($rn['message']); ?></div>
                                            </div>
                                            <div style="font-size: 11px; color: #999;">
                                                <?php echo date('M d', strtotime($rn['created_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="padding: 14px; text-align: center; color: #999;">No recent notifications</div>
                                <?php endif; ?>
                            </div>
                            <div style="padding: 8px; text-align: center;"><a href="notifications.php">View all</a></div>
                        </div>
                    </div>
                </div>

                <!-- Profile quick link -->
                <a href="profile.php" style="color: white; text-decoration: none; display: flex; align-items: center;">
                    <?php echo get_user_avatar_html($user, 'sm'); ?>
                </a>
            </div>
        </div>
    </div>   

    <!-- Main Container with Sidebar -->
    <div class="container-main">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <a href="user_dashboard.php">
                <i class="fas fa-home"></i> Home
            </a>
            <a href="orders_new.php">
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

        <!-- Content Area -->
        <div class="content">

    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1 class="welcome-title">
            <i class="fas fa-wave-hand"></i> Welcome, <?php echo htmlspecialchars($user['name'] ?? $username); ?>!
        </h1>
        <p class="welcome-subtitle">Browse our premium collection of mobile accessories</p>
        
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($products); ?></div>
                <div class="stat-label">Products Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">100%</div>
                <div class="stat-label">Genuine Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">�</div>
                <div class="stat-label" style="font-size: 14px;">Secure Payment</div>
            </div>
        </div>
    </div>

    <!-- Products Section -->
    <div class="products-section">
        <h2 class="section-header"><i class="fas fa-shopping-bag"></i> Featured Products</h2>
        
        <?php if (count($products) > 0): ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <?php if ($product['image']): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                        <?php else: ?>
                            <div class="product-image" style="display: flex; align-items: center; justify-content: center; color: #999;">
                                <i class="fas fa-image" style="font-size: 48px;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-info">
                            <span class="product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-desc"><?php echo htmlspecialchars(substr($product['description'], 0, 60)); ?>...</p>
                            
                            <?php if ($product['quantity'] > 0): ?>
                                <div class="product-stock in-stock">
                                    <i class="fas fa-check-circle"></i> In Stock (<?php echo $product['quantity']; ?>)
                                </div>
                            <?php else: ?>
                                <div class="product-stock out-stock">
                                    <i class="fas fa-times-circle"></i> Out of Stock
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>
                            
                            <?php if ($product['quantity'] > 0): ?>
                                <a href="checkout.php?product_id=<?php echo $product['id']; ?>" class="btn-shop">
                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                </a>
                            <?php else: ?>
                                <button class="btn-shop" disabled>
                                    <i class="fas fa-ban"></i> Out of Stock
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box"></i>
                <h3>No Products Available</h3>
                <p>No products available at the moment. Please check back later!</p>
            </div>
        <?php endif; ?>
    </div>
    <!-- End Products Section -->
    
    </div>
    <!-- End Content -->
    </div>
    <!-- End Container Main -->

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        let lastNotificationCount = <?php echo (int)$unread_count; ?>;
        let lastNotificationAt = <?php echo json_encode($last_notification_at); ?>;
        let updateCheckInterval = null;

        function checkForShopUpdates() {
            fetch('user_dashboard.php?check_updates=1&ts=' + Date.now(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            })
            .then(response => response.json())
            .then(data => {
                if (!data || !data.success) return;
                const newCount = Number(data.notification_count || 0);
                const latestAt = data.last_notification_at || null;
                if ((latestAt && (!lastNotificationAt || latestAt > lastNotificationAt)) || newCount > lastNotificationCount) {
                    lastNotificationCount = newCount;
                    lastNotificationAt = latestAt;
                    location.reload();
                }
            })
            .catch(error => console.log('Shop update check error:', error));
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateCheckInterval = setInterval(checkForShopUpdates, 10000);
        });

        window.addEventListener('beforeunload', function() {
            if (updateCheckInterval) {
                clearInterval(updateCheckInterval);
            }
        });
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>

