<?php
require_once __DIR__ . '/mail_helper.php';

function ensure_delivery_otp_verification_schema($conn) {
    $otp_column = mysqli_query($conn, "SHOW COLUMNS FROM delivery_otps LIKE 'verified_at'");
    if (!$otp_column) {
        return false;
    }

    if (mysqli_num_rows($otp_column) === 0 && !mysqli_query($conn, 'ALTER TABLE delivery_otps ADD COLUMN verified_at DATETIME NULL AFTER max_attempts')) {
        return false;
    }

    $order_column = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'delivered_at'");
    if (!$order_column) {
        return false;
    }

    if (mysqli_num_rows($order_column) === 0 && !mysqli_query($conn, 'ALTER TABLE orders ADD COLUMN delivered_at DATETIME NULL')) {
        return false;
    }

    return true;
}

function generate_delivery_otp($conn, $order_id, $user_id, $method = null, $send_immediately = false) {
    if (!$conn || !$order_id || !$user_id) {
        return false;
    }

    $existing = mysqli_query($conn, "SELECT id, status, expires_at FROM delivery_otps WHERE order_id = $order_id LIMIT 1");
    if ($existing && $row = mysqli_fetch_assoc($existing)) {
        if ($row['status'] === 'pending' && (!empty($row['expires_at']) && strtotime($row['expires_at']) > time())) {
            $result = ['otp_id' => (int)$row['id'], 'otp' => null];
            if ($send_immediately) {
                $result['sent'] = send_delivery_otp($conn, (int)$row['id'], $method ?: 'email');
            }
            return $result;
        }
    }

    $otp_plain = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_hash = password_hash($otp_plain, PASSWORD_DEFAULT);
    $otp_encrypted = base64_encode($otp_plain);
    $now = date('Y-m-d H:i:s');
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    $method = $method ?: 'email';

    $sql = "INSERT INTO delivery_otps (order_id, user_id, otp_encrypted, otp_hash, method, generated_at, expires_at, status, attempts, max_attempts)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, 5)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'iisssss', $order_id, $user_id, $otp_encrypted, $otp_hash, $method, $now, $expires_at);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }

    $otp_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $result = ['otp_id' => (int)$otp_id, 'otp' => $otp_plain];
    if ($send_immediately) {
        $result['sent'] = send_delivery_otp($conn, $otp_id, $method);
    }

    return $result;
}

function send_delivery_otp($conn, $otp_id, $method = 'email') {
    if (!$conn || !$otp_id) {
        return false;
    }

    $sql = "SELECT d.*, u.email, u.username, o.order_number FROM delivery_otps d
            JOIN users u ON d.user_id = u.id
            JOIN orders o ON d.order_id = o.id
            WHERE d.id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'i', $otp_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $otp = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$otp) {
        return false;
    }

    if (!empty($otp['expires_at']) && strtotime($otp['expires_at']) <= time()) {
        mysqli_query($conn, "UPDATE delivery_otps SET status = 'expired' WHERE id = " . (int)$otp_id);
        return false;
    }

    $otp_plain = base64_decode($otp['otp_encrypted']);
    $subject = 'Your Bazario delivery OTP';
    $body = '<h3>Your delivery OTP</h3><p>Your OTP for order <strong>#' . htmlspecialchars($otp['order_number']) . '</strong> is: <strong>' . htmlspecialchars($otp_plain) . '</strong></p><p>This OTP expires in 30 minutes.</p>';
    $altBody = 'Your delivery OTP for order #' . $otp['order_number'] . ' is ' . $otp_plain . '. It expires in 30 minutes.';

    $sent = send_email_smtp($otp['email'], $subject, $body, $altBody);

    if ($sent) {
        $update_sql = "UPDATE delivery_otps SET status = 'sent', method = ?, generated_at = generated_at WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, 'si', $method, $otp_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);

        $order_id = (int)$otp['order_id'];
        create_notification($conn, (int)$otp['user_id'], $order_id, 'delivery_otp_sent', 'Delivery OTP Sent', 'A delivery OTP has been sent to your email for order #' . $otp['order_number'] . '.', 'track_order.php?order_id=' . $order_id);
        return true;
    }

    return false;
}

function verify_delivery_otp($conn, $order_id, $entered_otp, $ip = null) {
    if (!$conn || !$order_id || empty($entered_otp)) {
        return ['success' => false, 'message' => 'Invalid request.'];
    }

    if (!ensure_delivery_otp_verification_schema($conn)) {
        return ['success' => false, 'message' => 'Unable to verify OTP at this time.'];
    }

    $sql = "SELECT * FROM delivery_otps WHERE order_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['success' => false, 'message' => 'Database error.'];
    }

    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $otp = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$otp) {
        return ['success' => false, 'message' => 'No OTP found for this order.'];
    }

    if ($otp['status'] === 'verified') {
        return ['success' => true, 'message' => 'OTP already verified.'];
    }

    if (!empty($otp['expires_at']) && strtotime($otp['expires_at']) < time()) {
        mysqli_query($conn, "UPDATE delivery_otps SET status = 'expired' WHERE id = {$otp['id']}");
        return ['success' => false, 'message' => 'OTP has expired.'];
    }

    $attempts = (int)$otp['attempts'] + 1;
    mysqli_query($conn, "UPDATE delivery_otps SET attempts = $attempts WHERE id = {$otp['id']}");

    if ($attempts > (int)$otp['max_attempts']) {
        mysqli_query($conn, "UPDATE delivery_otps SET status = 'expired' WHERE id = {$otp['id']}");
        return ['success' => false, 'message' => 'Too many attempts. Please request a new OTP.'];
    }

    if (password_verify($entered_otp, $otp['otp_hash'])) {
        mysqli_query($conn, "UPDATE delivery_otps SET status = 'verified', verified_at = NOW() WHERE id = {$otp['id']}");
        mysqli_query($conn, "UPDATE orders SET status = 'Delivered', delivered_at = NOW() WHERE id = $order_id");
        $ip_value = substr((string)($ip ?? ''), 0, 45);
        $log_result = 'success';
        $log_stmt = mysqli_prepare($conn, 'INSERT INTO otp_verification_logs (otp_id, user_id, attempt_time, ip_address, result) VALUES (?, ?, NOW(), ?, ?)');
        if ($log_stmt) {
            mysqli_stmt_bind_param($log_stmt, 'iiss', $otp['id'], $otp['user_id'], $ip_value, $log_result);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);
        }
        create_notification($conn, (int)$otp['user_id'], $order_id, 'delivery_otp_verified', 'Delivery OTP Verified', 'Your delivery OTP was verified successfully for order #' . $order_id . '.', 'track_order.php?order_id=' . $order_id);
        notify_admins($conn, $order_id, 'delivery_otp_verified', 'Delivery OTP Verified', 'A customer verified the delivery OTP for order #' . $order_id . '.', 'admin_orders_manage.php');
        return ['success' => true, 'message' => 'OTP verified successfully. Order marked as delivered.'];
    }

    $ip_value = substr((string)($ip ?? ''), 0, 45);
    $log_result = 'failed';
    $log_stmt = mysqli_prepare($conn, 'INSERT INTO otp_verification_logs (otp_id, user_id, attempt_time, ip_address, result) VALUES (?, ?, NOW(), ?, ?)');
    if ($log_stmt) {
        mysqli_stmt_bind_param($log_stmt, 'iiss', $otp['id'], $otp['user_id'], $ip_value, $log_result);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);
    }
    return ['success' => false, 'message' => 'Incorrect OTP. Please try again.'];
}
