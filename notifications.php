<?php
/**
 * BAZARIO - Notification Center & Preferences Page
 * Display notifications and manage user notification preferences
 * File: notifications.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: minor.php");
    exit;
}

require_once "config.php";
require_once __DIR__ . '/includes/notification_service.php';

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

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

// Fetch user details for avatar display
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = mysqli_prepare($conn, $user_sql);
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$user = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($user_stmt);

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        $notification_id = intval($_POST['notification_id']);
        if (mark_notification_read($conn, $notification_id)) {
            $success_msg = "Notification marked as read";
        }
    } elseif ($_POST['action'] === 'mark_all_read') {
        if (mark_all_read($conn, $user_id)) {
            $success_msg = "All notifications marked as read";
        }
    } elseif ($_POST['action'] === 'update_preferences') {
        $preferences = [];
        
        // Email preferences
        $preferences['email_on_order_placed'] = isset($_POST['email_order_placed']) ? 1 : 0;
        $preferences['email_on_order_confirmed'] = isset($_POST['email_order_confirmed']) ? 1 : 0;
        $preferences['email_on_processing'] = isset($_POST['email_processing']) ? 1 : 0;
        $preferences['email_on_packing'] = isset($_POST['email_packing']) ? 1 : 0;
        $preferences['email_on_shipped'] = isset($_POST['email_shipped']) ? 1 : 0;
        $preferences['email_on_delivered'] = isset($_POST['email_delivered']) ? 1 : 0;
        

        
        if (update_notification_preferences($conn, $user_id, $preferences)) {
            $success_msg = "Notification preferences updated successfully";
        } else {
            $error_msg = "Failed to update preferences";
        }
    }
}

// Get notifications
$notifications = get_user_notifications($conn, $user_id, 20, 0);
$unread_count = get_unread_notifications_count($conn, $user_id);

// Get preferences
$preferences = get_notification_preferences($conn, $user_id);
if (!$preferences) {
    create_default_preferences($conn, $user_id);
    $preferences = get_notification_preferences($conn, $user_id);
}

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
    <title>Notifications - Bazario</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/BAZARIO_STYLES.css">
    <style>
        .notification-header {
            background: linear-gradient(135deg, #001a33 0%, #003366 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        
        .tabs-container {
            margin-bottom: 30px;
        }
        
        .tab-nav {
            display: flex;
            gap: 0;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .tab-nav button {
            background: none;
            border: none;
            padding: 15px 30px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab-nav button.active {
            color: #001a33;
            border-bottom-color: #001a33;
        }
        
        .tab-nav button:hover {
            color: #001a33;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .notification-card {
            background: white;
            border-left: 4px solid #001a33;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .notification-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .notification-card.unread {
            background-color: rgba(52, 152, 219, 0.05);
            border-left-color: #3498db;
        }
        
        .notification-card.read {
            opacity: 0.7;
        }
        
        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
        }
        
        .notification-body {
            flex: 1;
        }
        
        .notification-title {
            color: #001a33;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .notification-message {
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .notification-time {
            color: #999;
            font-size: 12px;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-shrink: 0;
        }
        
        .notification-actions button {
            background: none;
            border: none;
            cursor: pointer;
            color: #999;
            padding: 5px 10px;
        }
        
        .notification-actions button:hover {
            color: #001a33;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .preference-group {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .preference-group h4 {
            color: #001a33;
            margin-bottom: 15px;
            font-weight: 600;
            border-bottom: 2px solid #f5f5f5;
            padding-bottom: 10px;
        }
        
        .preference-row {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .preference-row:last-child {
            border-bottom: none;
        }
        
        .preference-label {
            flex: 1;
            color: #333;
        }
        
        .preference-label small {
            display: block;
            color: #999;
            margin-top: 3px;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #001a33;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
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
    <!-- Navbar -->
    <div class="navbar" style="background: linear-gradient(135deg, #001a33 0%, #003366 100%); color: white; padding: 15px 30px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div style="display: flex; align-items: center; gap: 10px; font-size: 24px; font-weight: 700;">
            <i class="fas fa-shopping-bag"></i>
            <span>BAZARIO</span>
        </div>
        <div style="display: flex; gap: 20px; align-items: center;">
            <a href="user_dashboard.php" style="color: white; text-decoration: none; font-size: 14px;"><i class="fas fa-home"></i> Shop</a>
            <a href="orders_new.php" style="color: white; text-decoration: none; font-size: 14px;"><i class="fas fa-shopping-bag"></i> My Orders</a>
            <a href="profile.php" style="color: white; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 5px;"><?php echo get_user_avatar_html($user, 'sm'); ?> Profile</a>
            <form action="logout.php" method="POST" style="margin: 0; display: inline;">
                <button type="submit" style="background: none; border: none; color: white; cursor: pointer; font-size: 14px;"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
        </div>
    </div>

    <div class="main-container" style="max-width: 1200px; margin: 30px auto; padding: 0 20px;">
        <!-- Header -->
        <div class="notification-header" style="background: linear-gradient(135deg, #001a33 0%, #003366 100%); color: white; padding: 40px 0; margin-bottom: 30px;">
            <h1 style="margin-bottom: 5px;">Notifications</h1>
            <p style="opacity: 0.9; margin: 0;">Stay updated with your orders and exclusive offers</p>
        </div>

        <div class="content-wrapper">
            <!-- Success/Error Messages -->
            <?php if ($success_msg): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                    <button type="button" class="close" data-dismiss="alert">×</button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-times-circle"></i> <?php echo $error_msg; ?>
                    <button type="button" class="close" data-dismiss="alert">×</button>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs-container">
                <div class="tab-nav">
                    <button class="tab-button active" data-tab="notifications">
                        <i class="fas fa-bell"></i> Notifications
                        <?php if ($unread_count > 0): ?>
                            <span class="badge badge-danger ml-2"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="tab-button" data-tab="preferences">
                        <i class="fas fa-sliders-h"></i> Preferences
                    </button>
                </div>
            </div>

            <!-- Notifications Tab -->
            <div id="notifications-tab" class="tab-content active">
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0;">Recent Notifications</h3>
                        <?php if ($unread_count > 0): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    Mark All as Read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <div class="empty-state">
                                <i class="fas fa-bell-slash"></i>
                                <h4>No Notifications Yet</h4>
                                <p>Your notifications will appear here when you place an order.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): 
                                $status = $notif['is_read'] ? 'read' : 'unread';
                                $icon_class = $notif['icon_class'] ?? 'fa-info-circle';
                                $icon_bg_color = [
                                    'fa-box' => '#3498db',
                                    'fa-shopping-cart' => '#2ecc71',
                                    'fa-check-circle' => '#27ae60',
                                    'fa-cogs' => '#3498db',
                                    'fa-boxes' => '#e67e22',
                                    'fa-truck' => '#e67e22',
                                    'fa-check-double' => '#27ae60',
                                    'fa-envelope' => '#9b59b6',
                                    'fa-shield-alt' => '#16a085',
                                    'fa-sync-alt' => '#f39c12',
                                    'fa-times-circle' => '#e74c3c',
                                    'fa-info-circle' => '#001a33'
                                ];
                                $bg_color = $icon_bg_color[$icon_class] ?? '#001a33';
                            ?>
                            <div class="notification-card <?php echo $status; ?>" onclick="openNotification(<?php echo $notif['id']; ?>)">
                                <div style="display: flex; gap: 15px;">
                                    <div class="notification-icon" style="background-color: <?php echo $bg_color; ?>;">
                                        <i class="fas <?php echo $icon_class; ?>"></i>
                                    </div>
                                    <div class="notification-body" style="flex: 1;">
                                        <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                        <div class="notification-message"><?php echo htmlspecialchars($notif['body']); ?></div>
                                        <div class="notification-time">
                                            <?php 
                                            $created = strtotime($notif['created_at']);
                                            $now = time();
                                            $diff = $now - $created;
                                            
                                            if ($diff < 60) {
                                                echo "Just now";
                                            } elseif ($diff < 3600) {
                                                echo floor($diff / 60) . "m ago";
                                            } elseif ($diff < 86400) {
                                                echo floor($diff / 3600) . "h ago";
                                            } else {
                                                echo date('M d \a\t h:i A', $created);
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="notification-actions">
                                        <?php if (!$notif['is_read']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                                <button type="submit" title="Mark as read" onclick="event.stopPropagation();">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="track_order.php?order_id=<?php echo $notif['order_id']; ?>" 
                                           title="View Order" 
                                           onclick="event.stopPropagation();"
                                           style="color: #999; text-decoration: none;">
                                            <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Preferences Tab -->
            <div id="preferences-tab" class="tab-content">
                <form id="preferences-form" method="POST">
                    <input type="hidden" name="action" value="update_preferences">

                    <!-- Email Preferences -->
                    <div class="preference-group">
                        <h4>Email Notifications</h4>
                        
                        <div class="preference-row">
                            <div class="preference-label">
                                <strong>Order Placed</strong>
                                <small>Get notified when your order is confirmed</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="email_order_placed" 
                                    <?php echo (isset($preferences['email_on_order_placed']) && $preferences['email_on_order_placed']) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="preference-row">
                            <div class="preference-label">
                                <strong>Order Confirmed</strong>
                                <small>Notify me when payment is received and order is confirmed</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="email_order_confirmed" 
                                    <?php echo (isset($preferences['email_on_order_confirmed']) && $preferences['email_on_order_confirmed']) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="preference-row">
                            <div class="preference-label">
                                <strong>Processing</strong>
                                <small>Get updates when your order is being processed</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="email_processing" 
                                    <?php echo (isset($preferences['email_on_processing']) && $preferences['email_on_processing']) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="preference-row">
                            <div class="preference-label">
                                <strong>Packing</strong>
                                <small>Notify me when your order is being packed</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="email_packing" 
                                    <?php echo (isset($preferences['email_on_packing']) && $preferences['email_on_packing']) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="preference-row">
                            <div class="preference-label">
                                <strong>Out for Delivery</strong>
                                <small>Get notified when your order is out for delivery</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="email_shipped" 
                                    <?php echo (isset($preferences['email_on_shipped']) && $preferences['email_on_shipped']) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="preference-row">
                            <div class="preference-label">
                                <strong>Delivered</strong>
                                <small>Confirm notification when your order is delivered</small>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="email_delivered" 
                                    <?php echo (isset($preferences['email_on_delivered']) && $preferences['email_on_delivered']) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" id="save-preferences-btn">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                    <span id="save-status" style="margin-left: 15px; font-weight: bold;"></span>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        let lastNotificationCount = <?php echo (int)$unread_count; ?>;
        let lastNotificationAt = <?php echo json_encode($last_notification_at); ?>;
        let updateCheckInterval = null;

        function showUpdateNotification(message) {
            if (document.getElementById('live-update-banner')) {
                return;
            }
            const alertDiv = document.createElement('div');
            alertDiv.id = 'live-update-banner';
            alertDiv.className = 'alert alert-info alert-dismissible fade show';
            alertDiv.style.position = 'fixed';
            alertDiv.style.top = '0';
            alertDiv.style.left = '0';
            alertDiv.style.right = '0';
            alertDiv.style.zIndex = '9999';
            alertDiv.style.borderRadius = '0';
            alertDiv.style.margin = '0';
            alertDiv.innerHTML = `<div class="container"><i class="fas fa-bell"></i> <strong>Update!</strong> ${message}<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>`;
            document.body.insertBefore(alertDiv, document.body.firstChild);
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 4000);
        }

        function checkForNotifications() {
            fetch('notifications.php?check_updates=1&ts=' + Date.now(), {
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
                    showUpdateNotification('You have a new notification. Refreshing now…');
                    setTimeout(() => location.reload(), 1200);
                }
            })
            .catch(error => console.log('Notification check error:', error));
        }

        // Tab switching
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tabName = this.getAttribute('data-tab');
                
                // Hide all tabs
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                
                // Remove active class from all buttons
                document.querySelectorAll('.tab-button').forEach(btn => {
                    btn.classList.remove('active');
                });
                
                // Show selected tab
                document.getElementById(tabName + '-tab').classList.add('active');
                this.classList.add('active');
            });
        });

        // Handle preferences form submission
        document.getElementById('preferences-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const btn = document.getElementById('save-preferences-btn');
            const status = document.getElementById('save-status');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            status.textContent = '';
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Check if successful
                if (html.includes('Notification preferences updated successfully')) {
                    status.style.color = '#28a745';
                    status.textContent = '✓ Saved successfully!';
                    btn.innerHTML = '<i class="fas fa-save"></i> Save Preferences';
                    btn.disabled = false;
                    
                    // Clear message after 3 seconds
                    setTimeout(() => {
                        status.textContent = '';
                    }, 3000);
                } else if (html.includes('Failed to update preferences')) {
                    status.style.color = '#dc3545';
                    status.textContent = '✗ Failed to save. Please try again.';
                    btn.innerHTML = '<i class="fas fa-save"></i> Save Preferences';
                    btn.disabled = false;
                } else {
                    status.style.color = '#28a745';
                    status.textContent = '✓ Saved successfully!';
                    btn.innerHTML = '<i class="fas fa-save"></i> Save Preferences';
                    btn.disabled = false;
                    
                    setTimeout(() => {
                        status.textContent = '';
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                status.style.color = '#dc3545';
                status.textContent = '✗ Error saving preferences';
                btn.innerHTML = '<i class="fas fa-save"></i> Save Preferences';
                btn.disabled = false;
            });
        });

        function openNotification(notificationId) {
            // Open order tracking page or details
            console.log('Opening notification:', notificationId);
        }

        document.addEventListener('DOMContentLoaded', function() {
            checkForNotifications();
            updateCheckInterval = setInterval(checkForNotifications, 10000);
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
