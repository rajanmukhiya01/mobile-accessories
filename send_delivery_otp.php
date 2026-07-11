<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config.php";
require_once "includes/notification_service.php";
require_once "includes/delivery_otp_service.php";

// Only logged in users can request sending OTP to their selected method
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$method = 'email';

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order']);
    exit;
}

// Verify order ownership
$sql = "SELECT user_id, status FROM orders WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order || $order['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get OTP record
$sql = "SELECT * FROM delivery_otps WHERE order_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$otp_row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$otp_row || $otp_row['status'] === 'expired' || ($otp_row['expires_at'] && strtotime($otp_row['expires_at']) < time())) {
    mysqli_query($conn, "DELETE FROM delivery_otps WHERE order_id = {$order_id}");
    $otp_row = null;
    $newOtp = generate_delivery_otp($conn, $order_id, $user_id, null, false);
    if ($newOtp === false) {
        echo json_encode(['success' => false, 'message' => 'OTP record not found and could not be regenerated. Contact support.']);
        exit;
    }

    $sql = "SELECT * FROM delivery_otps WHERE order_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $otp_row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if (!$otp_row) {
    echo json_encode(['success' => false, 'message' => 'OTP record not found. Contact support.']);
    exit;
}

// Send OTP
$ok = send_delivery_otp($conn, $otp_row['id'], $method);
if ($ok) {
    echo json_encode(['success' => true, 'message' => 'OTP sent via ' . strtoupper($method)]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Check contact details or try another method.']);
}

?>
