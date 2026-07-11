<?php
require_once __DIR__ . '/includes/admin_check.php';
require_once __DIR__ . '/config.php';

$otp_id = isset($_GET['otp_id']) ? intval($_GET['otp_id']) : 0;
if ($otp_id <= 0) {
    echo "Invalid OTP id";
    exit;
}

$sql = "SELECT l.*, u.username FROM otp_verification_logs l LEFT JOIN users u ON l.user_id = u.id WHERE l.otp_id = ? ORDER BY l.attempt_time DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $otp_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OTP Verification Logs</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="p-4">
    <div class="container">
        <h3>OTP Verification Logs for OTP #<?php echo $otp_id; ?></h3>
        <a href="admin_otp_manage.php" class="btn btn-sm btn-link">Back to OTPs</a>
        <table class="table table-sm table-striped mt-3">
            <thead>
                <tr><th>Time</th><th>User</th><th>IP</th><th>Success</th><th>Note</th></tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($res)): ?>
                    <tr>
                        <td><?php echo $row['attempt_time']; ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
                        <td><?php echo $row['success'] ? 'Yes' : 'No'; ?></td>
                        <td><?php echo htmlspecialchars($row['note']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
