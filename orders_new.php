<?php
require_once 'config.php';
require_once __DIR__ . '/includes/notification_service.php';
require_once __DIR__ . '/includes/user_common.php';
require_once __DIR__ . '/components/user_layout.php';

ensure_logged_in_user();

$context = get_current_user_context($conn);
$user_id = $context['user_id'];
$username = $context['username'];
$is_admin = $context['is_admin'];
$user = $context['user'];
$unread_count = $context['unread_count'];
$last_notification_at = $context['last_notification_at'];

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

// Get status filter from query string
$status_filter = isset($_GET['status']) ? htmlspecialchars($_GET['status']) : 'all';
$search_query = isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '';

// Build SQL query
if ($is_admin) {
    $sql = "SELECT o.*, u.username, u.email FROM orders o JOIN users u ON o.user_id = u.id WHERE 1=1";
} else {
    $sql = "SELECT o.* FROM orders o WHERE o.user_id = ?";
}

// Add status filter
if ($status_filter !== 'all') {
    $sql .= " AND o.status = ?";
}

// Add search filter (Order ID, Address, or City)
if (!empty($search_query)) {
    $sql .= " AND (o.id LIKE ? OR o.street LIKE ? OR o.city LIKE ?)";
}

$sql .= " ORDER BY COALESCE(o.placed_at, o.created_at) DESC";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("SQL Error: " . mysqli_error($conn));
}

// Bind parameters
$param_types = "";
$params = [];

if (!$is_admin) {
    $param_types .= "i";
    $params[] = $user_id;
}

if ($status_filter !== 'all') {
    $param_types .= "s";
    $params[] = $status_filter;
}

if (!empty($search_query)) {
    $param_types .= "sss";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($params)) {
    $refs = [];
    foreach ($params as &$param) {
        $refs[] = &$param;
    }
    call_user_func_array([$stmt, 'bind_param'], array_merge([$param_types], $refs));
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}
mysqli_stmt_close($stmt);

// Get status counts for tabs
$sql_counts = $is_admin ? 
    "SELECT status, COUNT(*) as count FROM orders GROUP BY status" :
    "SELECT status, COUNT(*) as count FROM orders WHERE user_id = ? GROUP BY status";

$stmt_counts = mysqli_prepare($conn, $sql_counts);
if (!$is_admin) {
    mysqli_stmt_bind_param($stmt_counts, "i", $user_id);
}
mysqli_stmt_execute($stmt_counts);
$result_counts = mysqli_stmt_get_result($stmt_counts);

$status_counts = [];
while ($row = mysqli_fetch_assoc($result_counts)) {
    $status_counts[$row['status']] = $row['count'];
}
mysqli_stmt_close($stmt_counts);

// Get total count
$total_orders = array_sum($status_counts);
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
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-top {
            background: linear-gradient(135deg, #1a3a52 0%, #2c5aa0 100%);
            color: white;
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1100;
            width: 100%;
            min-height: 64px;
        }

        .navbar-brand-text {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .navbar-icons {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .navbar-icons a {
            color: white;
            font-size: 20px;
            text-decoration: none;
            transition: all 0.3s;
            position: relative;
        }

        .navbar-icons a:hover {
            color: #ffd700;
            transform: scale(1.1);
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .sidebar {
            width: 250px;
            background: #001a33;
            min-height: calc(100vh - 64px);
            padding: 20px 0;
            position: fixed;
            left: 0;
            top: 64px;
            z-index: 900;
            overflow-y: auto;
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
        }

        .sidebar a {
            display: block;
            color: #ecf0f1;
            padding: 15px 20px;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .sidebar a:hover, .sidebar a.active {
            background: rgba(44, 90, 160, 0.2);
            color: #ffd700;
            border-left-color: #2c5aa0;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            width: calc(100% - 250px);
            padding-top: 24px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1a3a52;
            margin-bottom: 10px;
        }

        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .search-box {
            position: relative;
            margin-bottom: 15px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #2c5aa0;
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }

        .search-box i {
            position: absolute;
            right: 12px;
            top: 12px;
            color: #999;
        }

        .status-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .status-tab {
            padding: 8px 16px;
            border-radius: 20px;
            border: 2px solid #e0e0e0;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 13px;
        }

        .status-tab:hover {
            border-color: #2c5aa0;
            color: #2c5aa0;
        }

        .status-tab.active {
            background: linear-gradient(135deg, #2c5aa0 0%, #1a3a52 100%);
            color: white;
            border-color: #2c5aa0;
        }

        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .order-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 4px solid #2c5aa0;
        }

        .order-card:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            transform: translateY(-3px);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .order-id {
            font-weight: 700;
            color: #1a3a52;
            font-size: 16px;
        }

        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-placed {
            background: #e3f2fd;
            color: #1976d2;
        }

        .status-processing {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-shipped {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .status-delivered {
            background: #e8f5e9;
            color: #388e3c;
        }

        .status-cancelled {
            background: #ffebee;
            color: #c62828;
        }

        .order-details {
            font-size: 13px;
            line-height: 1.8;
            color: #555;
        }

        .order-details-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .order-details-label {
            font-weight: 600;
            color: #333;
            min-width: 100px;
        }

        .order-details-value {
            color: #666;
            text-align: right;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state-icon {
            font-size: 60px;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #999;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #aaa;
            margin-bottom: 20px;
        }

        .btn-browse {
            background: linear-gradient(135deg, #2c5aa0 0%, #1a3a52 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-browse:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(44, 90, 160, 0.3);
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
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <div class="navbar-top">
        <div class="navbar-brand-text">
            <i class="fas fa-shopping-bag"></i> Mobile Accessories
        </div>
        <div class="navbar-icons">
            <a href="notifications.php" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" title="Profile">
                <?php echo get_user_avatar_html($user, 'sm'); ?>
            </a>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <a href="user_dashboard.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'user_dashboard') !== false ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Home
        </a>
        <a href="orders_new.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'orders_new') !== false ? 'active' : ''; ?>">
            <i class="fas fa-shopping-bag"></i> My Orders
        </a>
        <a href="profile.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'profile') !== false ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> Profile
        </a>
        <a href="logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1>
                <i class="fas fa-shopping-bag"></i> My Orders
            </h1>
            <p style="color: #666;">Track and manage your orders</p>
        </div>

        <?php if ($total_orders > 0): ?>
            <!-- Filters Section -->
            <div class="filters-section">
                <div class="search-box">
                    <form method="GET" id="searchForm">
                        <input type="text" name="search" placeholder="Search by Order ID, Address, or City..." 
                               value="<?php echo $search_query; ?>" id="searchInput">
                        <i class="fas fa-search"></i>
                    </form>
                </div>

                <!-- Status Tabs -->
                <div class="status-tabs">
                    <a href="?status=all" class="status-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                        All (<?php echo $total_orders; ?>)
                    </a>
                    <?php foreach (['Order Placed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'] as $status): ?>
                        <a href="?status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search_query); ?>" 
                           class="status-tab <?php echo $status_filter === $status ? 'active' : ''; ?>">
                            <?php echo $status; ?> (<?php echo isset($status_counts[$status]) ? $status_counts[$status] : 0; ?>)
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Orders Grid -->
            <div class="orders-grid">
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                            // Fetch OTP status for this order
                            $otp_status = null;
                            $otp_stmt = mysqli_prepare($conn, "SELECT status FROM delivery_otps WHERE order_id = ? LIMIT 1");
                            if ($otp_stmt) {
                                mysqli_stmt_bind_param($otp_stmt, 'i', $order['id']);
                                mysqli_stmt_execute($otp_stmt);
                                $otp_res = mysqli_stmt_get_result($otp_stmt);
                                $otp_row = mysqli_fetch_assoc($otp_res);
                                if ($otp_row) $otp_status = $otp_row['status'];
                                mysqli_stmt_close($otp_stmt);
                            }
                        ?>
                        <div class="order-card" data-order-id="<?php echo $order['id']; ?>">
                            <div class="order-header">
                                <div class="order-id">#<?php echo $order['id']; ?></div>
                                <div class="order-status status-<?php echo str_replace(' ', '-', strtolower($order['status'])); ?>">
                                    <?php echo $order['status']; ?>
                                </div>
                            </div>
                            <div class="order-otp-status">
                                <?php if ($otp_status === 'pending'): ?>
                                    <span class="badge badge-warning">OTP Pending</span>
                                <?php elseif ($otp_status === 'sent'): ?>
                                    <span class="badge badge-info">OTP Sent</span>
                                <?php elseif ($otp_status === 'verified'): ?>
                                    <span class="badge badge-success">Verified</span>
                                <?php elseif ($otp_status === 'expired'): ?>
                                    <span class="badge badge-secondary">Expired</span>
                                <?php else: ?>
                                    <!-- No OTP for this order -->
                                <?php endif; ?>
                            </div>
                            <?php if (!$is_admin && $otp_status && $otp_status !== 'verified'): ?>
                                <div class="order-otp-actions" style="margin-top:8px;">
                                    <span class="text-muted">The delivery OTP will be emailed to your registered address.</span>
                                </div>
                            <?php elseif ($is_admin && $otp_status && $otp_status !== 'verified'): ?>
                                <div class="order-otp-actions" style="margin-top:8px;">
                                    <a class="btn btn-sm btn-outline-primary" href="admin_otp_manage.php">Manage OTP</a>
                                </div>
                            <?php endif; ?>

                            <div class="order-details">
                                <div class="order-details-row">
                                    <span class="order-details-label">Date:</span>
                                    <span class="order-details-value"><?php echo format_order_datetime($order, 'M d, Y'); ?></span>
                                </div>
                                <div class="order-details-row">
                                    <span class="order-details-label">Total:</span>
                                    <span class="order-details-value">Rs. <?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                                <div class="order-details-row">
                                    <span class="order-details-label">Address:</span>
                                    <span class="order-details-value"><?php 
                                        $address_parts = [];
                                        if (!empty($order['street'])) $address_parts[] = $order['street'];
                                        if (!empty($order['city'])) $address_parts[] = $order['city'];
                                        $address = !empty($address_parts) ? implode(', ', $address_parts) : 'Address not provided';
                                        echo substr(htmlspecialchars($address), 0, 30) . '...';
                                    ?></span>
                                </div>
                                <div class="order-details-row">
                                    <span class="order-details-label">City:</span>
                                    <span class="order-details-value"><?php echo htmlspecialchars($order['city'] ?? 'Not specified'); ?></span>
                                </div>
                                <div class="order-details-row">
                                    <span class="order-details-label">Delivery Date:</span>
                                    <span class="order-details-value"><?php echo !empty($order['delivered_at']) ? date('M d, Y', strtotime($order['delivered_at'])) : 'Pending'; ?></span>
                                </div>
                                <div class="order-details-row">
                                    <span class="order-details-label">Delivery Time:</span>
                                    <span class="order-details-value"><?php echo !empty($order['delivered_at']) ? date('H:i', strtotime($order['delivered_at'])) : 'Pending'; ?></span>
                                </div>
                                <?php if ($is_admin && isset($order['username'])): ?>
                                    <div class="order-details-row">
                                        <span class="order-details-label">Customer:</span>
                                        <span class="order-details-value"><?php echo $order['username']; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <div class="empty-state-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h3>No orders found</h3>
                        <p>Try adjusting your search or filters</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <h3>You haven't placed any orders yet</h3>
                <p>Start shopping to see your orders here</p>
                <a href="user_dashboard.php" class="btn-browse">
                    <i class="fas fa-arrow-right"></i> Browse Products
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Auto-submit search
        const searchInput = document.getElementById('searchInput');
        const searchForm = document.getElementById('searchForm');
        if (searchInput && searchForm) {
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    searchForm.submit();
                }
            });
        }

        let lastNotificationCount = <?php echo (int)$unread_count; ?>;
        let lastNotificationAt = <?php echo json_encode($last_notification_at); ?>;

        // Real-time polling for order updates
        function checkOrderUpdates() {
            fetch('orders_new.php?check_updates=1&ts=' + Date.now(), {
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
            .catch(error => console.log('Update check error:', error));
        }

        // Poll every 10 seconds
        setInterval(checkOrderUpdates, 10000);
    </script>

    <?php mysqli_close($conn); ?>
</body>
</html>

