<?php
/**
 * BAZARIO Admin Order Management
 * Admin interface to view all orders and update their status with notifications
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}

// Check if user is logged in and is admin
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: minor.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("location: minor.php");
    exit;
}

require_once "config.php";
require_once __DIR__ . '/includes/notification_service.php';

$admin_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Fetch admin user details for avatar display
$admin_user_sql = "SELECT * FROM users WHERE id = ?";
$admin_user_stmt = mysqli_prepare($conn, $admin_user_sql);
mysqli_stmt_bind_param($admin_user_stmt, "i", $admin_id);
mysqli_stmt_execute($admin_user_stmt);
$admin_user_result = mysqli_stmt_get_result($admin_user_stmt);
$admin_user = mysqli_fetch_assoc($admin_user_result);
mysqli_stmt_close($admin_user_stmt);

// Handle status update via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $order_id = (int)$_POST['order_id'];
    $new_status = sanitize_input($_POST['new_status']);
    $note = sanitize_input($_POST['note'] ?? '');
    
    $allowed_statuses = ['Order Placed', 'Confirmed', 'Processing', 'Packing', 'Out for Delivery', 'Delivered', 'Cancelled'];
    
    if (in_array($new_status, $allowed_statuses)) {
        $result = update_order_status($conn, $order_id, $new_status, $admin_id, $note);

        header('Content-Type: application/json');
        // Include notification_created flag for debugging and visibility
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message'],
            'notification_created' => isset($result['notification_created']) ? (bool)$result['notification_created'] : null
        ]);
        exit;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Fetch all orders
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';

$sql = "SELECT o.*, u.username, u.email FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (o.order_number LIKE '%$search%' OR u.username LIKE '%$search%' OR o.customer_phone LIKE '%$search%')";
}

if (!empty($filter_status)) {
    $sql .= " AND o.status = '$filter_status'";
}

$sql .= " ORDER BY COALESCE(o.placed_at, o.created_at) DESC";

$result = mysqli_query($conn, $sql);
if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}

$all_statuses = ['Order Placed', 'Confirmed', 'Processing', 'Packing', 'Out for Delivery', 'Delivered', 'Cancelled'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Management - Bazario Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/BAZARIO_STYLES.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, #001a33 0%, #003366 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: 700;
        }

        .navbar-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .navbar-links a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            transition: opacity 0.3s;
        }

        .navbar-links a:hover {
            opacity: 0.8;
        }

        .container-main {
            margin: 30px;
        }

        .section-title {
            font-size: 28px;
            font-weight: 700;
            color: #001a33;
            margin-bottom: 25px;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-row input,
        .filter-row select {
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }

        .filter-row button {
            background: #001a33;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .filter-row button:hover {
            background: #003366;
        }

        .orders-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background: #001a33;
            color: white;
        }

        .table thead th {
            padding: 15px;
            font-weight: 600;
            border: none;
            text-align: left;
        }

        .table tbody td {
            padding: 15px;
            border-color: #e0e0e0;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .order-id {
            font-weight: 700;
            color: #001a33;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-orderplaced {
            background: #cfe2ff;
            color: #084298;
        }

        .status-processing {
            background: #cfe2ff;
            color: #084298;
        }

        .status-confirmed {
            background: #cfe2ff;
            color: #084298;
        }

        .status-packing {
            background: #fff3cd;
            color: #856404;
        }

        .status-outfordelivery {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-delivery {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-delivered {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #842029;
        }

        .action-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s;
        }

        .action-btn:hover {
            background: #2980b9;
            color: white;
            text-decoration: none;
        }

        /* Modal */
        .modal-header {
            background: #001a33 !important;
            color: white !important;
        }

        .modal-body {
            padding: 25px;
        }

        .form-group label {
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .form-control {
            border-color: #e0e0e0;
            border-radius: 5px;
        }

        .form-control:focus {
            border-color: #001a33;
            box-shadow: 0 0 0 0.2rem rgba(0, 26, 51, 0.25);
        }

        .btn-primary-custom {
            background: #001a33;
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-primary-custom:hover {
            background: #003366;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .alert {
            border-radius: 5px;
            margin-bottom: 20px;
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
    <div class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-shopping-bag"></i>
            <span>BAZARIO</span>
            <span style="font-size: 12px; opacity: 0.8;">Admin Dashboard</span>
        </div>
        <div class="navbar-links">
            <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="admin_profile.php" style="display: flex; align-items: center; gap: 5px;"><?php echo get_user_avatar_html($admin_user, 'sm'); ?> Profile</a>
            <form action="logout.php" method="POST" style="margin: 0; display: inline;">
                <button type="submit" style="background: none; border: none; color: white; cursor: pointer; text-decoration: none;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </div>

    <div class="container-main">
        <div class="section-title">
            <i class="fas fa-list"></i> Order Management
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="get" class="filter-row">
                <input type="text" name="search" placeholder="Search order number, customer, or phone..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 200px;">
                
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach ($all_statuses as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo $filter_status == $status ? 'selected' : ''; ?>>
                            <?php echo $status; ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="admin_orders_manage.php" style="background: #666; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; font-weight: 600;">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </form>
        </div>

        <!-- Orders Table -->
        <?php if (count($orders) > 0): ?>
            <div class="orders-table">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Current Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr data-order-id="<?php echo $order['id']; ?>">
                                <td class="order-id">#<?php echo htmlspecialchars($order['order_number']); ?></td>
                                <td>
                                    <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($order['customer_name'] ?? 'Not provided'); ?></div>
                                    <small style="color: #999;"><?php echo htmlspecialchars($order['customer_phone'] ?? 'Not provided'); ?></small>
                                </td>
                                <td>
                                    <strong>₹<?php echo number_format($order['total_amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace([' '], [''], $order['status'])); ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo format_order_datetime($order, 'M d, Y'); ?></small>
                                </td>
                                <td>
                                    <button class="action-btn" onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>', '<?php echo htmlspecialchars($order['status']); ?>')">
                                        <i class="fas fa-edit"></i> Update Status
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>No Orders Found</h4>
                <p>No orders match your search criteria</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Order Status</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Order Number</label>
                        <input type="text" class="form-control" id="modalOrderNumber" readonly>
                    </div>

                    <div class="form-group">
                        <label>Current Status</label>
                        <input type="text" class="form-control" id="modalCurrentStatus" readonly>
                    </div>

                    <div class="form-group">
                        <label for="newStatus">New Status</label>
                        <select class="form-control" id="newStatus">
                            <option value="">-- Select Status --</option>
                            <?php foreach ($all_statuses as $status): ?>
                                <option value="<?php echo $status; ?>"><?php echo $status; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="statusNote">Note (Optional)</label>
                        <textarea class="form-control" id="statusNote" rows="3" placeholder="Add a note about this status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn-primary-custom" onclick="updateStatus()">Update Status</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        let currentOrderId = null;

        function openStatusModal(orderId, orderNumber, currentStatus) {
            currentOrderId = orderId;
            document.getElementById('modalOrderNumber').value = orderNumber;
            document.getElementById('modalCurrentStatus').value = currentStatus;
            document.getElementById('newStatus').value = '';
            document.getElementById('statusNote').value = '';
            $('#statusModal').modal('show');
        }

        function updateStatus() {
            const newStatus = document.getElementById('newStatus').value;
            const note = document.getElementById('statusNote').value;

            if (!newStatus) {
                alert('Please select a new status');
                return;
            }

            // AJAX request
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('order_id', currentOrderId);
            formData.append('new_status', newStatus);
            formData.append('note', note);

            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    $('#statusModal').modal('hide');

                    // Update the status badge in the table
                    const statusRow = document.querySelector(`tr[data-order-id="${currentOrderId}"]`);
                    if (statusRow) {
                        const statusBadge = statusRow.querySelector('.status-badge');
                        if (statusBadge) {
                            statusBadge.className = 'status-badge';
                            const statusClass = 'status-' + newStatus.toLowerCase().replace(/\s+/g, '');
                            statusBadge.classList.add(statusClass);
                            statusBadge.textContent = newStatus;
                        }
                    }

                    // Show non-blocking toast about notification creation
                    const toast = document.createElement('div');
                    toast.className = 'alert alert-sm ' + (data.notification_created ? 'alert-success' : 'alert-warning');
                    toast.style.position = 'fixed';
                    toast.style.right = '20px';
                    toast.style.top = '80px';
                    toast.style.zIndex = '1050';
                    toast.style.minWidth = '220px';
                    toast.innerHTML = `<strong>${data.notification_created ? 'Notification sent' : 'Notification not created'}</strong><div style="font-size:12px;margin-top:6px;">${data.message}</div>`;
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 5000);

                    // Reload page after 1 second to sync with server
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the order status');
            });
        }
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>

