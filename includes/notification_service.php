<?php
/**
 * Notification service helper functions.
 * This file provides the notification API used by the application.
 */

function get_unread_notifications_count($conn, $user_id) {
    $sql = "SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        mysqli_stmt_close($stmt);
        return (int)$row['unread_count'];
    }
    mysqli_stmt_close($stmt);
    return 0;
}

function get_user_notifications($conn, $user_id, $limit = 20, $offset = 0) {
    $sql = "SELECT id, user_id, order_id, title, body, notification_type, link, is_read, created_at, read_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'iii', $user_id, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['icon_class'] = get_notification_icon($row['notification_type']);
        $row['message'] = $row['body'];
        $notifications[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $notifications;
}

function mark_notification_read($conn, $notification_id) {
    $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $notification_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function mark_all_read($conn, $user_id) {
    $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function get_notification_preferences($conn, $user_id) {
    $sql = "SELECT * FROM notification_preferences WHERE user_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $preferences = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $preferences ?: false;
}

function create_default_preferences($conn, $user_id) {
    $sql = "INSERT IGNORE INTO notification_preferences (user_id) VALUES (?)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function update_notification_preferences($conn, $user_id, $preferences) {
    $defaults = [
        'email_on_order_placed' => 0,
        'email_on_processing' => 0,
        'email_on_packing' => 0,
        'email_on_out_for_delivery' => 0,
        'email_on_delivered' => 0,
        'sms_on_order_placed' => 0,
        'sms_on_processing' => 0,
        'sms_on_packing' => 0,
        'sms_on_out_for_delivery' => 0,
        'sms_on_delivered' => 0
    ];

    $values = [];
    foreach ($defaults as $key => $default) {
        $values[$key] = isset($preferences[$key]) ? (int)$preferences[$key] : $default;
    }

    $sql = "UPDATE notification_preferences SET 
        email_on_order_placed = ?,
        email_on_processing = ?,
        email_on_packing = ?,
        email_on_out_for_delivery = ?,
        email_on_delivered = ?,
        sms_on_order_placed = ?,
        sms_on_processing = ?,
        sms_on_packing = ?,
        sms_on_out_for_delivery = ?,
        sms_on_delivered = ?,
        updated_at = NOW()
        WHERE user_id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param(
        $stmt,
        'iiiiiiiii i',
        $values['email_on_order_placed'],
        $values['email_on_processing'],
        $values['email_on_packing'],
        $values['email_on_out_for_delivery'],
        $values['email_on_delivered'],
        $values['sms_on_order_placed'],
        $values['sms_on_processing'],
        $values['sms_on_packing'],
        $values['sms_on_out_for_delivery'],
        $values['sms_on_delivered'],
        $user_id
    );

    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function get_admin_user_ids($conn) {
    $sql = "SELECT id FROM users WHERE role = 'admin'";
    $result = mysqli_query($conn, $sql);
    $ids = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $ids[] = (int)$row['id'];
        }
    }
    return $ids;
}

function notify_admins($conn, $order_id, $type, $title, $message, $link = null) {
    $admin_ids = get_admin_user_ids($conn);
    foreach ($admin_ids as $admin_id) {
        create_notification($conn, $admin_id, $order_id, $type, $title, $message, $link);
    }
    return count($admin_ids);
}

function create_notification($conn, $user_id, $order_id, $type, $title, $message, $link = null) {
    $sql = "INSERT INTO notifications (user_id, order_id, title, body, notification_type, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    $order_id = (int)($order_id ?: 0);
    $link = $link ?: '';
    mysqli_stmt_bind_param($stmt, 'iissss', $user_id, $order_id, $title, $message, $type, $link);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

function get_notification_icon($type) {
    $map = [
        'order_placed' => 'fa-shopping-cart',
        'order_confirmed' => 'fa-check-circle',
        'processing' => 'fa-cogs',
        'packing' => 'fa-box',
        'out_for_delivery' => 'fa-truck',
        'delivery_otp_sent' => 'fa-envelope',
        'delivery_otp_verified' => 'fa-shield-alt',
        'order_delivered' => 'fa-check-double',
        'order_cancelled' => 'fa-times-circle',
        'profile_updated' => 'fa-user-edit',
        'password_changed' => 'fa-lock',
        'new_order' => 'fa-boxes',
        'new_user' => 'fa-user-plus',
        'low_stock' => 'fa-exclamation-triangle',
        'out_of_stock' => 'fa-ban',
        'Out for Delivery' => 'fa-truck',
        'delivered' => 'fa-check-double',
        'cancelled' => 'fa-times-circle',
        'order_status_updated' => 'fa-sync-alt'
    ];
    return isset($map[$type]) ? $map[$type] : 'fa-info-circle';
}

function get_notification_for_status($status) {
    $map = [
        'Order Placed' => [
            'type' => 'order_placed',
            'title' => 'Order Placed',
            'message' => 'Your order has been placed successfully.'
        ],
        'Confirmed' => [
            'type' => 'order_confirmed',
            'title' => 'Order Confirmed',
            'message' => 'Your order has been confirmed.'
        ],
        'Processing' => [
            'type' => 'processing',
            'title' => 'Order Processing',
            'message' => 'Your order is being processed.'
        ],
        'Packing' => [
            'type' => 'packing',
            'title' => 'Order Packing',
            'message' => 'Your order is being packed.'
        ],
        'Out for Delivery' => [
            'type' => 'out_for_delivery',
            'title' => 'Out for Delivery',
            'message' => 'Your order is out for delivery.'
        ],
        'Delivered' => [
            'type' => 'order_delivered',
            'title' => 'Order Delivered',
            'message' => 'Your order has been delivered.'
        ],
        'Cancelled' => [
            'type' => 'order_cancelled',
            'title' => 'Order Cancelled',
            'message' => 'Your order has been cancelled.'
        ]
    ];
    return isset($map[$status]) ? $map[$status] : ['type' => 'notification', 'title' => 'Notification', 'message' => 'You have a new notification.'];
}

function get_order_status_history($conn, $order_id) {
    $sql = "SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at DESC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }

    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $history = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $history[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $history;
}

function update_order_status($conn, $order_id, $new_status, $changed_by, $note = '') {
    if (!$conn || empty($order_id) || empty($new_status)) {
        return ['success' => false, 'message' => 'Missing order status information.'];
    }

    $valid_statuses = ['Order Placed', 'Confirmed', 'Processing', 'Packing', 'Out for Delivery', 'Delivered', 'Cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        return ['success' => false, 'message' => 'Invalid status provided.'];
    }

    $timestamp_column = null;
    switch ($new_status) {
        case 'Confirmed':
            $timestamp_column = 'confirmed_at';
            break;
        case 'Processing':
            $timestamp_column = 'processing_at';
            break;
        case 'Packing':
            $timestamp_column = 'packing_at';
            break;
        case 'Out for Delivery':
            $timestamp_column = 'shipped_at';
            break;
        case 'Delivered':
            $timestamp_column = 'delivered_at';
            break;
        case 'Cancelled':
            $timestamp_column = 'cancelled_at';
            break;
    }

    $update_sql = "UPDATE orders SET status = ?";
    $params = [$new_status];
    $types = 's';

    if ($timestamp_column) {
        $column_check = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE '" . mysqli_real_escape_string($conn, $timestamp_column) . "'");
        if ($column_check && mysqli_num_rows($column_check) > 0) {
            $update_sql .= ", {$timestamp_column} = IF(COALESCE({$timestamp_column}, '0000-00-00 00:00:00') = '0000-00-00 00:00:00', NOW(), {$timestamp_column})";
        }
    }

    $update_sql .= " WHERE id = ?";
    $types .= 'i';
    $params[] = $order_id;

    $stmt = mysqli_prepare($conn, $update_sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Failed to prepare status update query.'];
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    $update_result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$update_result) {
        return ['success' => false, 'message' => 'Failed to update order status.'];
    }

    $history_sql = "INSERT INTO order_status_history (order_id, status, changed_by, note, created_at) VALUES (?, ?, ?, ?, NOW())";
    $history_stmt = mysqli_prepare($conn, $history_sql);
    if ($history_stmt) {
        mysqli_stmt_bind_param($history_stmt, 'iiss', $order_id, $new_status, $changed_by, $note);
        mysqli_stmt_execute($history_stmt);
        mysqli_stmt_close($history_stmt);
    }

    $notification_created = false;
    $user_query = "SELECT user_id FROM orders WHERE id = ? LIMIT 1";
    $user_stmt = mysqli_prepare($conn, $user_query);
    if ($user_stmt) {
        mysqli_stmt_bind_param($user_stmt, 'i', $order_id);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        if ($user_row = mysqli_fetch_assoc($user_result)) {
            $notification = get_notification_for_status($new_status);
            $notification_created = create_notification(
                $conn,
                (int)$user_row['user_id'],
                $order_id,
                $notification['type'],
                $notification['title'],
                $notification['message'],
                'track_order.php?order_id=' . $order_id
            );
        }
        mysqli_stmt_close($user_stmt);
    }

    return [
        'success' => true,
        'message' => 'Order status updated successfully.',
        'notification_created' => (bool)$notification_created
    ];
}

function get_estimated_delivery_date($status) {
    $baseDate = new DateTime('now');

    switch ($status) {
        case 'Order Placed':
        case 'Confirmed':
            $baseDate->modify('+2 days');
            break;
        case 'Processing':
        case 'Packing':
            $baseDate->modify('+3 days');
            break;
        case 'Out for Delivery':
            $baseDate->modify('+1 day');
            break;
        case 'Delivered':
        case 'Cancelled':
            return null;
        default:
            $baseDate->modify('+3 days');
            break;
    }

    return $baseDate->format('M d, Y');
}
