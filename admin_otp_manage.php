<?php
require_once __DIR__ . '/includes/admin_check.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notification_service.php';

// Fetch OTP records
$sql = "SELECT d.*, u.username, u.email, o.status AS order_status FROM delivery_otps d JOIN users u ON d.user_id = u.id JOIN orders o ON d.order_id = o.id ORDER BY d.generated_at DESC";
$res = mysqli_query($conn, $sql);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin - Delivery OTPs</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="p-4">
    <div class="container">
        <h2>Delivery OTP Management</h2>
        <p>List of delivery OTPs and actions.</p>

        <table class="table table-striped table-sm">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Order</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>Method</th>
                    <th>Generated</th>
                    <th>Expires</th>
                    <th>Attempts</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($res)): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo '<a href="orders.php?order_id=' . $row['order_id'] . '">#' . $row['order_id'] . '</a>'; ?></td>
                        <td><?php echo htmlspecialchars($row['username']) . '<br><small>' . htmlspecialchars($row['email']) . '</small>'; ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                        <td><?php echo htmlspecialchars($row['method']); ?></td>
                        <td><?php echo $row['generated_at']; ?></td>
                        <td><?php echo $row['expires_at']; ?></td>
                        <td><?php echo $row['attempts'] . '/' . $row['max_attempts']; ?></td>
                        <td>
                            <form method="post" action="admin_resend_otp.php" style="display:inline">
                                <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>" />
                                <input type="hidden" name="action" value="resend" />
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>" />
                                <button class="btn btn-sm btn-outline-primary" type="submit">Resend</button>
                            </form>
                            <form method="post" action="admin_resend_otp.php" style="display:inline;margin-left:6px;">
                                <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>" />
                                <input type="hidden" name="action" value="regen" />
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>" />
                                <button class="btn btn-sm btn-outline-danger" type="submit">Regenerate</button>
                            </form>
                            <a class="btn btn-sm btn-secondary" href="admin_otp_logs.php?otp_id=<?php echo $row['id']; ?>">Logs</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
