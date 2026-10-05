<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: minor.php');
    exit;
}

require_once 'config.php';
require_once __DIR__ . '/includes/notification_service.php';
require_once __DIR__ . '/components/user_layout.php';

$user_id = $_SESSION['user_id'];
$user = null;
$unread_count = get_unread_notifications_count($conn, $user_id);

$stmt = $conn->prepare('SELECT * FROM users WHERE id = ?');
if ($stmt) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

ensure_announcements_table($conn);

$announcement_rows = [];
if ($conn) {
    $res = $conn->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $announcement_rows[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Announcements - Bazario</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/BAZARIO_STYLES.css">
    <style>
        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar, .navbar-top {
            background: linear-gradient(135deg, #001a33 0%, #003366 100%);
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1100;
            width: 100%;
            min-height: 64px;
        }
        .container-main {
            display: flex;
            min-height: calc(100vh - 64px);
        }
        .sidebar {
            width: 250px;
            background: #001a33;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            left: 0;
            top: 64px;
            height: calc(100vh - 64px);
            overflow-y: auto;
        }
        .sidebar a, .sidebar button, .sidebar-logout-btn {
            display: block;
            width: 100%;
            color: #ecf0f1;
            padding: 15px 20px;
            text-decoration: none;
            border-left: 4px solid transparent;
            border: none;
            background: none;
            text-align: left;
            cursor: pointer;
            font-size: 15px;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar button:hover, .sidebar-logout-btn:hover,
        .sidebar a.active {
            background: #003366;
            border-left-color: #3498db;
            padding-left: 30px;
            color: white;
        }
        .content {
            margin-left: 250px;
            padding: 30px;
            flex: 1;
            background: #f8f9fa;
        }
        .sidebar a:hover,
        .sidebar button:hover,
        .sidebar-logout-btn:hover,
        .sidebar a.active {
            background: #003366;
            border-left-color: #3498db;
            padding-left: 30px;
            color: #fff;
        }
        .announcement-header {
            background: linear-gradient(135deg, #001a33 0%, #003366 100%);
            border-radius: 14px;
            color: white;
            padding: 24px 30px;
            margin-bottom: 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        .announcement-header h1 {
            font-size: 32px;
            margin-bottom: 6px;
            font-weight: 700;
        }
        .announcement-card {
            background: white;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }
        .announcement-badge {
            display: inline-block;
            background: #e8f4ff;
            color: #003366;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 14px;
        }
        .article-title {
            font-size: 28px;
            font-weight: 700;
            color: #001a33;
            margin-bottom: 12px;
        }
        .meta {
            color: #666;
            font-size: 13px;
            margin-bottom: 18px;
        }
        .announcement-body {
            color: #333;
            line-height: 1.8;
            font-size: 16px;
        }
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .content { margin-left: 0; padding: 20px; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=4">
</head>
<body>
    <?php echo render_user_navbar($user, $unread_count, 'navbar', 'BAZARIO', false, 'announcements', false); ?>
    <div class="container-main">
        <?php echo render_user_sidebar('announcements'); ?>
        <div class="content">
            <div class="announcement-header">
                <h1><i class="fas fa-bullhorn"></i> Announcements</h1>
                <p class="mb-0">Latest updates, offers, and system notices</p>
            </div>

            <?php if (empty($announcement_rows)): ?>
                <div class="announcement-card text-center">
                    <i class="fas fa-bullhorn fa-3x mb-3" style="color: #001a33; opacity: 0.7;"></i>
                    <h3 style="color: #001a33;">No active announcements</h3>
                    <p class="mb-0 text-muted">There are currently no announcements to display.</p>
                </div>
            <?php else: ?>
                <?php foreach ($announcement_rows as $announcement): ?>
                    <div class="announcement-card">
                        <div class="announcement-badge">
                            <i class="fas fa-bullhorn"></i> Announcement
                        </div>
                        <?php if (!empty($announcement['image'])): ?>
                            <div style="margin-bottom: 18px; text-align: center;">
                                <img src="<?php echo htmlspecialchars($announcement['image']); ?>" alt="<?php echo htmlspecialchars($announcement['title'] ?? 'Announcement'); ?>" style="max-width: 100%; max-height: 320px; border-radius: 12px; object-fit: cover; box-shadow: 0 8px 20px rgba(0,0,0,0.08);">
                            </div>
                        <?php endif; ?>
                        <div class="article-title"><?php echo htmlspecialchars($announcement['title'] ?? 'Announcement'); ?></div>
                        <div class="meta">
                            Posted on <?php echo date('F d, Y', strtotime($announcement['created_at'] ?? date('Y-m-d'))); ?>
                        </div>
                        <div class="announcement-body">
                            <?php echo nl2br(htmlspecialchars($announcement['message'] ?? 'No message available.')); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
