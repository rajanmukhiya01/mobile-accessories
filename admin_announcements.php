<?php
require_once 'admin_check.php';
require_once 'config.php';

ensure_announcements_table($conn);

$admin_id = $_SESSION['user_id'] ?? 0;
$success_msg = '';
$error_msg = '';

$announcement_types = ['General', 'New Product', 'Discount / Offer', 'Important Notice', 'Order Information', 'Delivery Information', 'Maintenance', 'Other'];
$priorities = ['Normal', 'Important', 'High Priority'];
$status_options = ['published', 'draft', 'archived'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['id'])) {
        $id = (int) $_POST['id'];
        $stmt = $conn->prepare('SELECT image FROM announcements WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $image = $stmt->get_result()->fetch_assoc()['image'] ?? null;
            $stmt->close();

            if ($image && file_exists($image)) {
                @unlink($image);
            }

            $del = $conn->prepare('DELETE FROM announcements WHERE id = ?');
            if ($del) {
                $del->bind_param('i', $id);
                $del->execute();
                $del->close();
                $success_msg = 'Announcement deleted successfully.';
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'toggle_publish' && !empty($_POST['id'])) {
        $id = (int) $_POST['id'];
        $status = isset($_POST['status']) ? $_POST['status'] : 'published';
        $stmt = $conn->prepare('UPDATE announcements SET status = ?, is_active = ?, published_at = IF(published_at IS NULL, NOW(), published_at), updated_at = NOW() WHERE id = ?');
        if ($stmt) {
            $active = ($status === 'published') ? 1 : 0;
            $stmt->bind_param('sii', $status, $active, $id);
            $stmt->execute();
            $stmt->close();
            $success_msg = 'Announcement publication status updated.';
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'save' && !empty($_POST['title'])) {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $title = trim($_POST['title']);
        $message = trim($_POST['message']);
        $type = in_array($_POST['announcement_type'] ?? '', $announcement_types, true) ? $_POST['announcement_type'] : 'General';
        $priority = in_array($_POST['priority'] ?? '', $priorities, true) ? $_POST['priority'] : 'Normal';
        $status = in_array($_POST['status'] ?? '', $status_options, true) ? $_POST['status'] : 'published';
        $start_at = !empty($_POST['start_at']) ? $_POST['start_at'] : null;
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $remove_image = !empty($_POST['remove_image']);
        $existing_image = null;

        if ($id > 0) {
            $sel = $conn->prepare('SELECT image FROM announcements WHERE id = ?');
            if ($sel) {
                $sel->bind_param('i', $id);
                $sel->execute();
                $row = $sel->get_result()->fetch_assoc();
                $existing_image = $row['image'] ?? null;
                $sel->close();
            }
        }

        $image_path = $existing_image;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $upload_dir = __DIR__ . '/uploads/announcements';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed, true)) {
                $error_msg = 'Only JPG, PNG, GIF, and WEBP images are allowed.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error_msg = 'Image must be 5MB or smaller.';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $safe_name = uniqid('ann_', true) . '.' . strtolower($ext);
                $target = $upload_dir . '/' . $safe_name;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $relative_target = 'uploads/announcements/' . $safe_name;
                    if ($remove_image !== true && $existing_image && file_exists($existing_image) && $existing_image !== $relative_target) {
                        @unlink($existing_image);
                    }
                    $image_path = $relative_target;
                } else {
                    $error_msg = 'Image upload failed.';
                }
            }
        } elseif ($remove_image && $existing_image) {
            if (file_exists($existing_image)) {
                @unlink($existing_image);
            }
            $image_path = null;
        }

        if (empty($error_msg)) {
            if ($id > 0) {
                $stmt = $conn->prepare('UPDATE announcements SET title = ?, message = ?, image = ?, announcement_type = ?, priority = ?, status = ?, is_active = ?, start_at = ?, expires_at = ?, updated_at = NOW(), created_by = ? WHERE id = ?');
                if ($stmt) {
                    $active = ($status === 'published') ? 1 : 0;
                    $stmt->bind_param('ssssssissi', $title, $message, $image_path, $type, $priority, $status, $active, $start_at, $expires_at, $admin_id, $id);
                    $stmt->execute();
                    $stmt->close();
                    $success_msg = 'Announcement updated successfully.';
                }
            } else {
                $stmt = $conn->prepare('INSERT INTO announcements (title, message, image, announcement_type, priority, status, is_active, created_by, start_at, expires_at, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                if ($stmt) {
                    $active = ($status === 'published') ? 1 : 0;
                    $stmt->bind_param('ssssssiiss', $title, $message, $image_path, $type, $priority, $status, $active, $admin_id, $start_at, $expires_at);
                    $stmt->execute();
                    $stmt->close();
                    $success_msg = 'Announcement created successfully.';
                }
            }
        }
    }
}

$edit_announcement = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    $stmt = $conn->prepare('SELECT * FROM announcements WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $edit_id);
        $stmt->execute();
        $edit_announcement = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$announcements = [];
$res = $conn->query('SELECT * FROM announcements ORDER BY created_at DESC');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $announcements[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Announcements - Bazario</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/BAZARIO_STYLES.css">
    <style>
        body { background: #f5f7fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar { background: linear-gradient(135deg, #001a33 0%, #003366 100%); color: white; padding: 15px 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .navbar-brand { font-size: 24px; font-weight: 700; letter-spacing: 2px; color: white; }
        .container-main { display: flex; min-height: calc(100vh - 70px); }
        .sidebar { width: 250px; background: #001a33; padding: 20px 0; box-shadow: 2px 0 10px rgba(0,0,0,0.1); position: fixed; height: calc(100vh - 70px); overflow-y: auto; }
        .sidebar a, .sidebar button { display: block; width: 100%; color: rgba(255,255,255,0.8); padding: 14px 20px; text-decoration: none; transition: all 0.3s; border-left: 4px solid transparent; border: none; background: none; text-align: left; cursor: pointer; }
        .sidebar a:hover, .sidebar button:hover { background: #003366; border-left-color: #3498db; color: white; padding-left: 24px; }
        .sidebar a.active { background: #003366; border-left-color: #667eea; color: white; font-weight: 600; }
        .sidebar a i, .sidebar button i { margin-right: 12px; width: 18px; }
        .content { margin-left: 250px; padding: 30px; flex: 1; }
        .section-title { font-size: 28px; font-weight: 700; color: #001a33; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .panel { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; }
        .status-pill { display: inline-block; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .status-published { background: #d4f8e8; color: #0f9d58; }
        .status-draft { background: #fff3cd; color: #b7791f; }
        .status-archived { background: #f8d7da; color: #b02a37; }
        .priority-badge { display: inline-block; padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .priority-normal { background: #e8f1ff; color: #1d4ed8; }
        .priority-important { background: #fff3cd; color: #b7791f; }
        .priority-high { background: #fde2e2; color: #c62828; }
        .announcement-image { width: 100%; max-width: 200px; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid #e0e0e0; }
        .table td, .table th { vertical-align: middle; }
        @media (max-width: 768px) { .container-main { display: block; } .sidebar { position: static; width: 100%; height: auto; } .content { margin-left: 0; padding: 20px; } }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=4">
</head>
<body>
    <?php $adminBasePath = ''; require __DIR__ . '/components/admin_heading_bar.php'; ?>

    <div class="container-main">
        <div class="sidebar">
            <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="admin_add_product.php"><i class="fas fa-plus-circle"></i> Add Product</a>
            <a href="admin_orders_manage.php"><i class="fas fa-shopping-bag"></i> Orders Management</a>
            <a href="admin_announcements.php" class="active"><i class="fas fa-bullhorn"></i> Announcements</a>
            <a href="admin_profile.php"><i class="fas fa-user-circle"></i> Admin Profile</a>
            <div class="user-info" style="margin-top: auto; padding: 15px 20px; color: rgba(255,255,255,0.8); border-top: 1px solid rgba(255,255,255,0.2);">
                <p><strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></strong></p>
                <form action="auth/logout.php" method="POST">
                    <button type="submit" class="logout-btn" style="background: #e74c3c; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; width: 100%; margin-top: 10px;"> <i class="fas fa-sign-out-alt"></i> Logout </button>
                </form>
            </div>
        </div>

        <div class="content">
            <div class="section-title">
                <div><i class="fas fa-bullhorn"></i> Announcement Management</div>
                <a href="admin_announcements.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Announcement</a>
            </div>

            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <div class="panel">
                <h4 class="mb-3"><?php echo $edit_announcement ? 'Edit Announcement' : 'Create Announcement'; ?></h4>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">
                    <?php if ($edit_announcement): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$edit_announcement['id']; ?>">
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Title</label>
                                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($edit_announcement['title'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Message</label>
                                <textarea class="form-control" name="message" rows="6" required><?php echo htmlspecialchars($edit_announcement['message'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Announcement Type</label>
                                <select class="form-control" name="announcement_type">
                                    <?php foreach ($announcement_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo (($edit_announcement['announcement_type'] ?? 'General') === $type) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Priority</label>
                                <select class="form-control" name="priority">
                                    <?php foreach ($priorities as $priority): ?>
                                        <option value="<?php echo htmlspecialchars($priority); ?>" <?php echo (($edit_announcement['priority'] ?? 'Normal') === $priority) ? 'selected' : ''; ?>><?php echo htmlspecialchars($priority); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control" name="status">
                                    <?php foreach ($status_options as $item): ?>
                                        <option value="<?php echo htmlspecialchars($item); ?>" <?php echo (($edit_announcement['status'] ?? 'published') === $item) ? 'selected' : ''; ?>><?php echo ucfirst(htmlspecialchars($item)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="datetime-local" class="form-control" name="start_at" value="<?php echo htmlspecialchars($edit_announcement['start_at'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Expiry Date</label>
                                <input type="datetime-local" class="form-control" name="expires_at" value="<?php echo htmlspecialchars($edit_announcement['expires_at'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Announcement Image</label>
                                <input type="file" class="form-control-file" name="image" accept="image/*">
                                <?php if (!empty($edit_announcement['image'])): ?>
                                    <div class="mt-2">
                                        <img src="<?php echo htmlspecialchars($edit_announcement['image']); ?>" class="announcement-image" alt="Announcement preview">
                                        <div class="mt-2">
                                            <label><input type="checkbox" name="remove_image" value="1"> Remove image</label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary"><?php echo $edit_announcement ? 'Update Announcement' : 'Save Announcement'; ?></button>
                        <?php if ($edit_announcement): ?>
                            <a href="admin_announcements.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="panel">
                <h4 class="mb-3">Announcement List</h4>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Start</th>
                                <th>Expiry</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($announcements)): ?>
                                <tr><td colspan="9" class="text-center text-muted">No announcements yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($announcements as $item): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($item['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($item['image']); ?>" class="announcement-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                            <?php else: ?>
                                                <div class="announcement-image d-flex align-items-center justify-content-center bg-light text-muted">No Image</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['title']); ?></td>
                                        <td><?php echo htmlspecialchars($item['announcement_type'] ?? 'General'); ?></td>
                                        <td><span class="priority-badge priority-<?php echo strtolower(str_replace(' ', '-', $item['priority'] ?? 'Normal')); ?>"><?php echo htmlspecialchars($item['priority'] ?? 'Normal'); ?></span></td>
                                        <td><span class="status-pill status-<?php echo htmlspecialchars($item['status'] ?? 'published'); ?>"><?php echo ucfirst(htmlspecialchars($item['status'] ?? 'published')); ?></span></td>
                                        <td><?php echo !empty($item['start_at']) ? date('d M Y', strtotime($item['start_at'])) : '—'; ?></td>
                                        <td><?php echo !empty($item['expires_at']) ? date('d M Y', strtotime($item['expires_at'])) : '—'; ?></td>
                                        <td><?php echo date('d M Y', strtotime($item['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="admin_announcements.php?edit=<?php echo (int)$item['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this announcement?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="toggle_publish">
                                                    <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo (($item['status'] ?? 'published') === 'published') ? 'draft' : 'published'; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-<?php echo (($item['status'] ?? 'published') === 'published') ? 'eye-slash' : 'eye'; ?>"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
