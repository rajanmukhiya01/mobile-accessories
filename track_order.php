<?php
/**
 * BAZARIO - Order Tracking Page
 * Display modern, practical order status and delivery information.
 */

function resolve_product_image_src($image_value) {
    if (empty($image_value)) {
        return null;
    }

    $raw = trim($image_value);
    if ($raw === '') {
        return null;
    }

    if (preg_match('/^https?:\/\//i', $raw) || strpos($raw, 'data:image/') === 0) {
        return $raw;
    }

    if (strpos($raw, '/') === 0) {
        $raw = ltrim($raw, '/');
    }

    $candidates = [];
    if (strpos($raw, 'uploads/') === 0) {
        $candidates[] = $raw;
    } else {
        $candidates[] = 'uploads/' . ltrim($raw, '/');
        $candidates[] = $raw;
    }

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && file_exists($candidate)) {
            return $candidate;
        }
    }

    return null;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: minor.php");
    exit;
}

if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    die("<!DOCTYPE html><html><body><div style='font-family:Arial;padding:40px;'>Admin access is restricted.</div></body></html>");
}

require_once "config.php";
require_once __DIR__ . '/includes/notification_service.php';

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

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

$user_sql_fetch = "SELECT * FROM users WHERE id = ?";
$user_stmt_fetch = mysqli_prepare($conn, $user_sql_fetch);
mysqli_stmt_bind_param($user_stmt_fetch, "i", $user_id);
mysqli_stmt_execute($user_stmt_fetch);
$user_result_fetch = mysqli_stmt_get_result($user_stmt_fetch);
$current_user = mysqli_fetch_assoc($user_result_fetch);
mysqli_stmt_close($user_stmt_fetch);

$order_sql = "SELECT o.*, u.name as user_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ? AND o.user_id = ?";
$order_stmt = mysqli_prepare($conn, $order_sql);
mysqli_stmt_bind_param($order_stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($order_stmt);
$order_result = mysqli_stmt_get_result($order_stmt);
$order = mysqli_fetch_assoc($order_result);
mysqli_stmt_close($order_stmt);

if (!$order) {
    die("Order not found");
}

$order_timestamp = get_order_datetime($order);
$placed_at_display = $order_timestamp ? date('M d, Y \a\t h:i A', strtotime($order_timestamp)) : 'Processing';

$items_sql = "SELECT oi.*, p.image AS product_image, p.name AS product_name_from_db
              FROM order_items oi
              LEFT JOIN product p ON oi.product_id = p.id
              WHERE oi.order_id = ?
              ORDER BY oi.id ASC";
$items_stmt = mysqli_prepare($conn, $items_sql);
mysqli_stmt_bind_param($items_stmt, "i", $order_id);
mysqli_stmt_execute($items_stmt);
$items_result = mysqli_stmt_get_result($items_stmt);
$items = [];
while ($row = mysqli_fetch_assoc($items_result)) {
    $image_src = resolve_product_image_src($row['product_image'] ?? null);
    $items[] = [
        'id' => $row['id'],
        'product_id' => $row['product_id'],
        'product_name' => !empty($row['product_name']) ? $row['product_name'] : ($row['product_name_from_db'] ?? 'Product'),
        'variant' => $row['variant'] ?? '',
        'quantity' => (int)($row['quantity'] ?? 0),
        'price' => (float)($row['price'] ?? 0),
        'subtotal' => (float)($row['subtotal'] ?? 0),
        'image_src' => $image_src,
    ];
}
mysqli_stmt_close($items_stmt);

$history = get_order_status_history($conn, $order_id);
$estimated_delivery = get_estimated_delivery_date($order['status']);
$delivery_days = null;
if ($order['status'] === 'Delivered' && !empty($order['delivered_at']) && $order_timestamp) {
    $placed_time = strtotime($order_timestamp);
    $delivered_time = strtotime($order['delivered_at']);
    if ($placed_time && $delivered_time) {
        $delivery_days = floor(($delivered_time - $placed_time) / 86400);
    }
}

$steps = [
    ['key' => 'Order Placed', 'label' => 'Order Placed', 'icon' => 'fa-box', 'description' => 'Your order has been placed successfully.'],
    ['key' => 'Confirmed', 'label' => 'Order Confirmed', 'icon' => 'fa-check-circle', 'description' => 'Payment has been confirmed and your order is verified.'],
    ['key' => 'Processing', 'label' => 'Processing', 'icon' => 'fa-cogs', 'description' => 'We are preparing your order.'],
    ['key' => 'Shipped', 'label' => 'Shipped', 'icon' => 'fa-truck', 'description' => 'Your package is on the way.'],
    ['key' => 'Out for Delivery', 'label' => 'Out for Delivery', 'icon' => 'fa-shipping-fast', 'description' => 'Your order is on the final delivery route.'],
    ['key' => 'Delivered', 'label' => 'Delivered', 'icon' => 'fa-home', 'description' => 'Your order has been delivered successfully.']
];

$progress_statuses = ['Order Placed', 'Confirmed', 'Processing', 'Shipped', 'Out for Delivery', 'Delivered'];
$completed_index = -1;
foreach ($progress_statuses as $index => $status) {
    if ($order['status'] === $status) {
        $completed_index = $index;
        break;
    }
    if (in_array($order['status'], ['Confirmed','Processing','Shipped','Out for Delivery','Delivered']) && $status === 'Confirmed') {
        $completed_index = 0;
    }
}

if ($order['status'] === 'Confirmed') {
    $completed_index = 1;
} elseif ($order['status'] === 'Processing') {
    $completed_index = 2;
} elseif ($order['status'] === 'Shipped') {
    $completed_index = 3;
} elseif ($order['status'] === 'Out for Delivery') {
    $completed_index = 4;
} elseif ($order['status'] === 'Delivered') {
    $completed_index = 5;
} elseif ($order['status'] === 'Order Placed') {
    $completed_index = 0;
} else {
    $completed_index = -1;
}

$otp_status = 'Not Requested';
$otp_sql = "SELECT status, expires_at FROM delivery_otps WHERE order_id = ? LIMIT 1";
$otp_stmt = mysqli_prepare($conn, $otp_sql);
mysqli_stmt_bind_param($otp_stmt, 'i', $order_id);
mysqli_stmt_execute($otp_stmt);
$otp_result = mysqli_stmt_get_result($otp_stmt);
$otp_row = mysqli_fetch_assoc($otp_result);
mysqli_stmt_close($otp_stmt);
if ($otp_row) {
    $otp_status = ucfirst($otp_row['status'] ?? 'Pending');
}

$delivery_verification = $order['status'] === 'Delivered' ? 'Verified' : 'Pending';

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
        body { background: #f5f7fb; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #1f2937; }
        .navbar-top { background: linear-gradient(135deg, #001a33 0%, #003366 100%); color: white; padding: 15px 24px; display:flex; justify-content:space-between; align-items:center; box-shadow: 0 6px 18px rgba(0,0,0,0.12); position: sticky; top: 0; z-index: 1100; min-height: 64px; }
        .navbar-brand-text { font-size: 20px; font-weight: 700; letter-spacing: 0.3px; }
        .navbar-icons { display:flex; gap:16px; align-items:center; }
        .navbar-icons a { color:white; font-size:18px; text-decoration:none; position:relative; }
        .navbar-icons a:hover { color:#ffd700; }
        .notification-badge { position:absolute; top:-7px; right:-8px; background:#ff5a5f; width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; }
        .sidebar { width: 250px; background:#001a33; min-height: calc(100vh - 60px); padding: 20px 0; position:fixed; left:0; top:60px; overflow-y:auto; }
        .sidebar a { display:block; color:#ecf0f1; padding:14px 20px; text-decoration:none; transition:all 0.3s; border-left:4px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.08); color:#ffd700; border-left-color:#2c5aa0; }
        .main-content { margin-left:250px; padding:28px; padding-top: 24px; }
        .card { border:0; border-radius:16px; box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
        .hero-card { background: linear-gradient(135deg, #0f3b62 0%, #1f6f9e 100%); color:white; padding:24px; border-radius:20px; margin-bottom:22px; }
        .status-pill { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border-radius:999px; background:rgba(255,255,255,0.16); font-weight:600; }
        .timeline { display:flex; gap:12px; overflow-x:auto; padding:8px 2px 4px; }
        .timeline-step { min-width: 140px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:14px; padding:14px; position:relative; flex:1; }
        .timeline-step.completed { background:#ecfdf3; border-color:#34d399; }
        .timeline-step.active { background:#eff6ff; border-color:#60a5fa; }
        .timeline-step .step-icon { width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; background:#cbd5e1; margin-bottom:10px; }
        .timeline-step.completed .step-icon { background:#10b981; }
        .timeline-step.active .step-icon { background:#2563eb; }
        .timeline-step small { color:#64748b; display:block; margin-top:4px; }
        .info-grid { display:grid; gap:16px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .info-box { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:16px; }
        .muted { color:#64748b; font-size:13px; }
        .item-row { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid #f1f5f9; }
        .item-row:last-child { border-bottom:0; }
        .product-thumb-wrap { width:70px; height:70px; border-radius:14px; overflow:hidden; background:#f3f4f6; display:flex; align-items:center; justify-content:center; flex-shrink:0; border:1px solid #e5e7eb; }
        .product-thumb { width:100%; height:100%; object-fit:cover; display:block; }
        .product-thumb-placeholder { width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#64748b; font-size:11px; text-align:center; padding:6px; }
        .product-thumb-placeholder i { font-size:20px; margin-bottom:4px; }
        .avatar-sm { width:32px; height:32px; border-radius:50%; object-fit:cover; border:2px solid #fff; }
        .avatar-default { width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:white; display:flex; align-items:center; justify-content:center; }
        @media (max-width: 992px) { .sidebar { display:none; } .main-content { margin-left:0; padding:16px; } }
        @media (max-width: 768px) { .timeline { flex-direction:column; } .timeline-step { min-width:auto; } }
    </style>
</head>
<body>
    <div class="navbar-top">
        <div class="navbar-brand-text"><i class="fas fa-shopping-bag"></i> Mobile Accessories</div>
        <div class="navbar-icons">
            <a href="notifications.php" title="Notifications"><i class="fas fa-bell"></i><?php if ($unread_count > 0): ?><span class="notification-badge"><?php echo $unread_count; ?></span><?php endif; ?></a>
            <a href="profile.php" title="Profile"><?php echo get_user_avatar_html($current_user, 'sm'); ?></a>
        </div>
    </div>

    <div class="sidebar">
        <a href="user_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="user_dashboard.php"><i class="fas fa-store"></i> Shop</a>
        <a href="orders_new.php" class="active"><i class="fas fa-box"></i> My Orders</a>
        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="hero-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="status-pill"><i class="fas fa-map-marked-alt"></i> Order Tracking</div>
                    <h2 class="mt-3 mb-1">Order #<?php echo htmlspecialchars($order['order_number']); ?></h2>
                    <p class="mb-0" style="opacity:0.9;">Placed on <?php echo htmlspecialchars($placed_at_display); ?></p>
                </div>
                <div class="text-end">
                    <div class="status-pill"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($order['status']); ?></div>
                    <div class="mt-2 small">Estimated delivery: <?php echo htmlspecialchars($estimated_delivery ?: 'Pending'); ?></div>
                </div>
            </div>
        </div>

        <div class="card p-4 mb-4">
            <h4 class="mb-3"><i class="fas fa-route"></i> Delivery Progress</h4>
            <div class="timeline">
                <?php foreach ($steps as $index => $step): ?>
                    <?php $isCompleted = $index <= $completed_index; $isActive = $index === $completed_index; ?>
                    <div class="timeline-step <?php echo $isCompleted ? 'completed' : ''; ?> <?php echo $isActive ? 'active' : ''; ?>">
                        <div class="step-icon"><i class="fas <?php echo $step['icon']; ?>"></i></div>
                        <strong><?php echo htmlspecialchars($step['label']); ?></strong>
                        <small><?php echo htmlspecialchars($step['description']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="info-grid mb-4">
            <div class="info-box">
                <div class="muted">Order Date</div>
                <div class="fw-bold mt-1"><?php echo htmlspecialchars($placed_at_display); ?></div>
            </div>
            <div class="info-box">
                <div class="muted">Order Total</div>
                <div class="fw-bold mt-1">₹<?php echo number_format($order['total_amount'], 2); ?></div>
            </div>
            <div class="info-box">
                <div class="muted">Payment Method</div>
                <div class="fw-bold mt-1"><?php echo htmlspecialchars($order['payment_method'] ?? 'COD'); ?></div>
            </div>
            <div class="info-box">
                <div class="muted">Payment Status</div>
                <div class="fw-bold mt-1">Paid</div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card p-4 mb-4">
                    <h4 class="mb-3"><i class="fas fa-map-marker-alt"></i> Delivery Details</h4>
                    <div class="info-grid">
                        <div class="info-box">
                            <div class="muted">Delivery Address</div>
                            <div class="mt-1 fw-semibold">
                                <?php
                                $address_lines = [];
                                if (!empty($order['street'])) $address_lines[] = htmlspecialchars($order['street']);
                                $city_parts = [];
                                if (!empty($order['city'])) $city_parts[] = htmlspecialchars($order['city']);
                                if (!empty($order['state'])) $city_parts[] = htmlspecialchars($order['state']);
                                if (!empty($order['postal_code'])) $city_parts[] = htmlspecialchars($order['postal_code']);
                                if (!empty($city_parts)) $address_lines[] = implode(', ', $city_parts);
                                if (!empty($order['country'])) $address_lines[] = htmlspecialchars($order['country']);
                                echo !empty($address_lines) ? implode('<br>', $address_lines) : 'Address not provided';
                                ?>
                            </div>
                        </div>
                        <div class="info-box">
                            <div class="muted">Estimated Delivery</div>
                            <div class="mt-1 fw-semibold"><?php echo htmlspecialchars($estimated_delivery ?: 'Pending'); ?></div>
                        </div>
                        <div class="info-box">
                            <div class="muted">OTP Status</div>
                            <div class="mt-1 fw-semibold"><?php echo htmlspecialchars($otp_status); ?></div>
                        </div>
                        <div class="info-box">
                            <div class="muted">Delivery Verification</div>
                            <div class="mt-1 fw-semibold"><?php echo htmlspecialchars($delivery_verification); ?></div>
                        </div>
                    </div>
                    <?php if ($order['status'] === 'Out for Delivery' || $otp_status !== 'Not Requested'): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fas fa-shield-alt"></i> A delivery OTP has been generated for this order. Please verify it when the parcel reaches the final delivery stage.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card p-4">
                    <h4 class="mb-3"><i class="fas fa-box-open"></i> Order Items</h4>
                    <?php foreach ($items as $item): ?>
                        <div class="item-row">
                            <div class="product-thumb-wrap">
                                <?php if (!empty($item['image_src'])): ?>
                                    <img class="product-thumb" src="<?php echo htmlspecialchars($item['image_src']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                <?php else: ?>
                                    <div class="product-thumb-placeholder">
                                        <i class="fas fa-image"></i>
                                        <span>No Image Available</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <?php if (!empty($item['variant'])): ?>
                                    <div class="muted">Variant: <?php echo htmlspecialchars($item['variant']); ?></div>
                                <?php endif; ?>
                                <div class="muted">Qty: <?php echo (int)$item['quantity']; ?> • Unit price: ₹<?php echo number_format($item['price'], 2); ?></div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">₹<?php echo number_format($item['subtotal'], 2); ?></div>
                                <div class="muted">Total</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card p-4 mb-4">
                    <h4 class="mb-3"><i class="fas fa-receipt"></i> Order Summary</h4>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="muted">Order Number</span><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></div>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="muted">Order Date</span><strong><?php echo htmlspecialchars($placed_at_display); ?></strong></div>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="muted">Payment Method</span><strong><?php echo htmlspecialchars($order['payment_method'] ?? 'COD'); ?></strong></div>
                    <div class="d-flex justify-content-between py-2 border-bottom"><span class="muted">Contact</span><strong><?php echo htmlspecialchars($order['customer_phone'] ?? 'Not provided'); ?></strong></div>
                    <div class="d-flex justify-content-between py-2"><span class="muted">Total</span><strong>₹<?php echo number_format($order['total_amount'], 2); ?></strong></div>
                </div>

                <div class="card p-4">
                    <h4 class="mb-3"><i class="fas fa-headset"></i> Need Help?</h4>
                    <p class="muted mb-3">Our support team can help with delivery or product questions.</p>
                    <a href="mailto:support@bazario.com" class="btn btn-primary w-100"><i class="fas fa-envelope"></i> Contact Support</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        let lastNotificationCount = <?php echo (int)$unread_count; ?>;
        let lastNotificationAt = <?php echo json_encode($last_notification_at); ?>;
        let updateCheckInterval = null;

        function checkForOrderUpdates() {
            fetch('track_order.php?order_id=<?php echo (int)$order_id; ?>&check_updates=1&ts=' + Date.now(), {
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
            .catch(error => console.log('Track order update error:', error));
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateCheckInterval = setInterval(checkForOrderUpdates, 10000);
        });

        window.addEventListener('beforeunload', function() {
            if (updateCheckInterval) {
                clearInterval(updateCheckInterval);
            }
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>
