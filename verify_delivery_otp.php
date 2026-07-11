<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "config.php";
require_once "includes/notification_service.php";
require_once "includes/delivery_otp_service.php";

$message = '';
$isSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $entered_otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    if ($order_id <= 0 || empty($entered_otp)) {
        $message = 'Please enter the order ID and OTP.';
    } else {
        $result = verify_delivery_otp($conn, $order_id, $entered_otp, $ip);
        $isSuccess = $result['success'];
        $message = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delivery Verification</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container" style="max-width:480px;">
    <h2 class="mb-3">Delivery Verification</h2>
    <p class="text-muted">Enter the OTP received by email to confirm delivery.</p>
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $isSuccess ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="order_id">Order ID</label>
            <input type="number" class="form-control" id="order_id" name="order_id" required>
        </div>
        <div class="form-group">
            <label for="otp">Delivery OTP</label>
            <input type="text" class="form-control" id="otp" name="otp" maxlength="6" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Verify OTP</button>
    </form>
</div>
</body>
</html>
