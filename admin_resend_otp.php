<?php
require_once "includes/admin_check.php";
require_once "config.php";
require_once __DIR__ . '/includes/notification_service.php';
require_once __DIR__ . '/includes/delivery_otp_service.php';

// Admin endpoint to resend or regenerate OTP for an order
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_orders_manage.php');
    exit;
}

// CSRF check
$csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!verify_csrf_token($csrf)) {
    header('Location: admin_orders_manage.php?error=csrf_invalid');
    exit;
}

$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : 'resend'; // resend or regen

if ($order_id <= 0) {
    header('Location: admin_orders_manage.php?error=invalid_order');
    exit;
}

// Check order exists
$sql = "SELECT id, user_id FROM orders WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order) {
    header('Location: admin_orders_manage.php?error=order_not_found');
    exit;
}

if ($action === 'regen') {
    // Delete existing OTP and generate new
    mysqli_query($conn, "DELETE FROM delivery_otps WHERE order_id = {$order_id}");
    generate_delivery_otp($conn, $order_id, $order['user_id']);
    header('Location: admin_orders_manage.php?success=otp_regenerated');
    exit;
} else {
    // Resend existing OTP via preferred method
    $sql = "SELECT id, method FROM delivery_otps WHERE order_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $otp_row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$otp_row) {
        header('Location: admin_orders_manage.php?error=no_otp');
        exit;
    }

    $method = $otp_row['method'] ?: 'email';
    $ok = send_delivery_otp($conn, $otp_row['id'], $method);
    header('Location: admin_orders_manage.php?success=' . ($ok ? 'otp_resent' : 'otp_send_failed'));
    exit;
}

?>
