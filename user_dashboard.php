<?php
require_once 'config.php';
require_once __DIR__ . '/includes/notification_service.php';
require_once __DIR__ . '/includes/user_common.php';
require_once __DIR__ . '/components/user_layout.php';


ensure_logged_in_user();

$context = get_current_user_context($conn);
$user_id = $context['user_id'];
$username = $context['username'];
$user = $context['user'];
$unread_count = $context['unread_count'];
$last_notification_at = $context['last_notification_at'];

// Prevent admin from accessing customer shop
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Access Denied</title>
        <link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css'>
        <style>
            body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f8f9fa; }
            .error-container { text-align: center; padding: 40px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .error-container h1 { color: #dc3545; margin-bottom: 20px; }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <h1>❌ Admin Cannot Access Shop</h1>
            <p>Admins can only view and manage orders, not place them.</p>
            <p>Please switch to a regular user account to shop.</p>
            <a href='admin_dashboard.php' class='btn btn-primary mt-3'>Go to Admin Dashboard</a>
        </div>
    </body>
    </html>
    ");
}

if (isset($_GET['check_updates']) && $_GET['check_updates'] == 1) {
    $unread_count = get_unread_notifications_count($conn, $user_id);
    $last_notification_at = null;
    $sql_last = "SELECT MAX(created_at) as last_notif FROM notifications WHERE user_id = ?";
    $stmt_last = mysqli_prepare($conn, $sql_last);
    if ($stmt_last) {
        mysqli_stmt_bind_param($stmt_last, "i", $user_id);
        mysqli_stmt_execute($stmt_last);
        $res_last = mysqli_stmt_get_result($stmt_last);
        $row_last = mysqli_fetch_assoc($res_last);
        $last_notification_at = $row_last['last_notif'];
        mysqli_stmt_close($stmt_last);
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'notification_count' => intval($unread_count),
        'last_notification_at' => $last_notification_at
    ]);
    mysqli_close($conn);
    exit;
}

// Get notifications
$recent_notifications = get_user_notifications($conn, $user_id, 5, 0);

function build_user_dashboard_url(array $updates = []): string {
    $query = $_GET;
    foreach ($updates as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }

    $url = 'user_dashboard.php';
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
}

$search_term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$selected_category = isset($_GET['category']) ? trim((string) $_GET['category']) : '';
$selected_availability = isset($_GET['availability']) ? (string) $_GET['availability'] : 'all';
$selected_availability = in_array($selected_availability, ['all', 'in_stock', 'out_of_stock'], true) ? $selected_availability : 'all';
$min_price = isset($_GET['min_price']) ? trim((string) $_GET['min_price']) : '';
$max_price = isset($_GET['max_price']) ? trim((string) $_GET['max_price']) : '';
$sort_option = isset($_GET['sort']) ? (string) $_GET['sort'] : 'default';
$sort_option = in_array($sort_option, ['default', 'price_asc', 'price_desc', 'newest', 'name_asc'], true) ? $sort_option : 'default';

$category_query = $conn->query("SELECT DISTINCT category FROM product WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
$category_options = [];
if ($category_query) {
    while ($category_row = $category_query->fetch_assoc()) {
        $value = trim((string) ($category_row['category'] ?? ''));
        if ($value !== '') {
            $category_options[] = $value;
        }
    }
}

$where_clauses = [];
$binding_values = [];
$binding_types = '';

if ($search_term !== '') {
    $like_term = '%' . $search_term . '%';
    $where_clauses[] = '(name LIKE ? OR category LIKE ? OR description LIKE ?)';
    $binding_values[] = $like_term;
    $binding_values[] = $like_term;
    $binding_values[] = $like_term;
    $binding_types .= 'sss';
}

if ($selected_category !== '') {
    $where_clauses[] = 'category = ?';
    $binding_values[] = $selected_category;
    $binding_types .= 's';
}

if ($min_price !== '' && is_numeric($min_price)) {
    $where_clauses[] = 'price >= ?';
    $binding_values[] = (float) $min_price;
    $binding_types .= 'd';
}

if ($max_price !== '' && is_numeric($max_price)) {
    $where_clauses[] = 'price <= ?';
    $binding_values[] = (float) $max_price;
    $binding_types .= 'd';
}

if ($selected_availability === 'in_stock') {
    $where_clauses[] = 'quantity > 0';
} elseif ($selected_availability === 'out_of_stock') {
    $where_clauses[] = 'quantity <= 0';
}

$sort_clause = 'ORDER BY id DESC';
if ($sort_option === 'price_asc') {
    $sort_clause = 'ORDER BY price ASC, id DESC';
} elseif ($sort_option === 'price_desc') {
    $sort_clause = 'ORDER BY price DESC, id DESC';
} elseif ($sort_option === 'newest') {
    $sort_clause = 'ORDER BY id DESC';
} elseif ($sort_option === 'name_asc') {
    $sort_clause = 'ORDER BY name ASC, id DESC';
}

$sql = 'SELECT * FROM product';
if (!empty($where_clauses)) {
    $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
}
$sql .= ' ' . $sort_clause;

$stmt = $conn->prepare($sql);
$products = [];

if ($stmt) {
    if (!empty($binding_values)) {
        $stmt->bind_param($binding_types, ...$binding_values);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
} else {
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shop - Bazario</title>
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
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
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
            min-height: calc(100vh - 70px);
        }
        
        .sidebar {
            width: 250px;
            background: #001a33;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 64px;
            left: 0;
            height: calc(100vh - 64px);
            z-index: 900;
            overflow-y: auto;
        }

        .sidebar a,
        .sidebar button,
        .sidebar-logout-btn {
            display: block;
            width: 100%;
            color: #ecf0f1;
            padding: 15px 20px;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            border: none;
            background: none;
            text-align: left;
            cursor: pointer;
            font-size: 15px;
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

        .sidebar a i,
        .sidebar button i,
        .sidebar-logout-btn i {
            margin-right: 10px;
            width: 20px;
        }
        
        .content {
            margin-left: 250px;
            padding: 30px;
            flex: 1;
            background: #f8f9fa;
            padding-top: 24px;
        }
        
        .sidebar-logout-btn {
            display: block;
            width: 100%;
            color: #ecf0f1;
            padding: 15px 20px;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            border: none;
            background: none;
            text-align: left;
            cursor: pointer;
            font-size: 15px;
        }
        
        .sidebar-logout-btn:hover {
            background: #003366;
            border-left-color: #e74c3c;
            padding-left: 30px;
        }
        
        .sidebar-logout-btn i {
            margin-right: 10px;
            width: 20px;
        }
        
        .welcome-section {
            background: white;
            margin: 30px;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .welcome-title {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 10px;
        }
        
        .welcome-subtitle {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 10px;
            color: white;
            text-align: center;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }
        
        .products-section {
            padding: 30px;
        }
        
        .section-header {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 30px;
            padding: 0;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }
        
        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f0f0f0;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-category {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .product-name {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
            min-height: 40px;
            line-height: 1.4;
        }
        
        .product-desc {
            font-size: 13px;
            color: #888;
            margin-bottom: 12px;
            line-height: 1.5;
        }
        
        .product-stock {
            font-size: 12px;
            margin-bottom: 12px;
        }
        
        .product-stock.in-stock {
            color: #28a745;
        }
        
        .product-stock.out-stock {
            color: #dc3545;
        }
        
        .product-price {
            font-size: 22px;
            font-weight: 700;
            color: #27ae60;
            margin-bottom: 15px;
        }
        
        .btn-shop {
            display: block;
            width: 100%;
            background: #001a33;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-shop:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 26, 51, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .btn-shop:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: white;
        }
        
        .empty-state i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.8;
        }
        
        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .product-toolbar {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #eaeaea;
            border-radius: 14px;
            padding: 18px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.04);
            margin-bottom: 24px;
        }

        .product-search-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .search-row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input-wrap {
            position: relative;
            flex: 1 1 320px;
        }

        .search-input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #7b7b7b;
        }

        .search-input-wrap input {
            width: 100%;
            border: 1px solid #dfe4ea;
            border-radius: 10px;
            padding: 12px 14px 12px 42px;
            font-size: 15px;
            outline: none;
        }

        .search-input-wrap input:focus {
            border-color: #001a33;
            box-shadow: 0 0 0 3px rgba(0, 26, 51, 0.08);
        }

        .toolbar-btn,
        .filter-toggle,
        .clear-filters-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            border-radius: 10px;
            padding: 12px 18px;
            font-weight: 600;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar-btn,
        .filter-toggle {
            background: #001a33;
            color: white;
        }

        .clear-filters-btn {
            background: #eef2f7;
            color: #24364d;
        }

        .toolbar-btn:hover,
        .filter-toggle:hover,
        .clear-filters-btn:hover {
            transform: translateY(-1px);
            text-decoration: none;
        }

        .filter-panel {
            display: none;
            border-top: 1px solid #edf1f4;
            padding-top: 18px;
            margin-top: 6px;
        }

        .filter-panel.visible {
            display: block;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }

        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-field label {
            font-size: 12px;
            font-weight: 700;
            color: #4c5d76;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .filter-field select,
        .filter-field input {
            width: 100%;
            border: 1px solid #dfe4ea;
            border-radius: 9px;
            padding: 10px 12px;
            background: white;
            color: #1b2430;
        }

        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .active-filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-top: 12px;
        }

        .active-filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eef5ff;
            color: #003366;
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .active-filter-pill a {
            color: #003366;
            text-decoration: none;
            font-weight: 700;
        }

        .results-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .results-count {
            font-size: 15px;
            color: #49566b;
            font-weight: 600;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.04);
            color: #4c5d76;
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
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
            }
            
            .welcome-section {
                margin: 20px;
                padding: 25px;
            }
            
            .section-header {
                padding: 0;
            }
            
            .sidebar {
                width: 200px;
            }
            
            .content {
                margin-left: 200px;
            }
        }
    </style>
    <link rel="stylesheet" href="assets/css/responsive.css?v=4">
</head>
<body>
    <!-- Header -->

    <div class="navbar">
        <div style="display: flex; align-items: center; gap: 15px; width: 100%;">
            <i class="fas fa-shopping-bag" style="font-size: 28px;"></i>
            <span class="navbar-brand" style="margin: 0;">BAZARIO</span>
            <span style="opacity: 0.9; font-size: 12px; margin-left: 12px;">Online Shopping Store</span>

            <div style="margin-left: auto; display: flex; align-items: center; gap: 12px;">
                <!-- Notification Bell -->
                <div style="position: relative;">
                    <a href="notifications.php" style="color: white; text-decoration: none; position: relative;">
                        <i class="fas fa-bell" style="font-size: 18px;"></i>
                        <?php if (!empty($unread_count) && $unread_count > 0): ?>
                            <span style="position: absolute; top: -6px; right: -10px; background: #e74c3c; color: white; font-size: 11px; padding: 2px 6px; border-radius: 12px;"><?php echo (int)$unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Quick dropdown (desktop) -->
                <div style="position: relative;">
                    <div style="background: transparent; color: white;">
                        <div style="position: absolute; right: 0; top: 36px; width: 320px; background: white; color: #333; border-radius: 6px; box-shadow: 0 6px 18px rgba(0,0,0,0.12); display: none; z-index: 50;" id="notif-dropdown">
                            <div style="padding: 12px; border-bottom: 1px solid #eee; font-weight: 700;">Recent notifications</div>
                            <div style="max-height: 260px; overflow: auto;">
                                <?php if (!empty($recent_notifications)): ?>
                                    <?php foreach ($recent_notifications as $rn): ?>
                                        <div style="padding: 10px 12px; border-bottom: 1px solid #f5f5f5; display: flex; justify-content: space-between; align-items: center;">
                                            <div style="flex: 1; margin-right: 8px;">
                                                <div style="font-weight: 600; color: #001a33; font-size: 13px;"><?php echo htmlspecialchars($rn['title']); ?></div>
                                                <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($rn['message']); ?></div>
                                            </div>
                                            <div style="font-size: 11px; color: #999;">
                                                <?php echo date('M d', strtotime($rn['created_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="padding: 14px; text-align: center; color: #999;">No recent notifications</div>
                                <?php endif; ?>
                            </div>
                            <div style="padding: 8px; text-align: center;"><a href="notifications.php">View all</a></div>
                        </div>
                    </div>
                </div>

                <!-- Profile quick link -->
                <a href="profile.php" style="color: white; text-decoration: none; display: flex; align-items: center;">
                    <?php echo get_user_avatar_html($user, 'sm'); ?>
                </a>
            </div>
        </div>
    </div>   

    <!-- Main Container with Sidebar -->
    <div class="container-main">
        <?php echo render_user_sidebar('dashboard'); ?>

        <!-- Content Area -->
        <div class="content">

    <!-- Welcome Section -->
    <div class="welcome-section">
        <h1 class="welcome-title">
            <i class="fas fa-wave-hand"></i> Welcome, <?php echo htmlspecialchars($user['name'] ?? $username); ?>!
        </h1>
        <p class="welcome-subtitle">Browse our premium collection of mobile accessories</p>
        
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($products); ?></div>
                <div class="stat-label">Products Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">100%</div>
                <div class="stat-label">Genuine Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">�</div>
                <div class="stat-label" style="font-size: 14px;">Secure Payment</div>
            </div>
        </div>
    </div>

    <!-- Products Section -->
    <div class="products-section">
        <h2 class="section-header"><i class="fas fa-shopping-bag"></i> Featured Products</h2>

        <div class="product-toolbar">
            <form method="GET" class="product-search-form" id="productSearchForm">
                <div class="search-row">
                    <div class="search-input-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Search products..." aria-label="Search products">
                    </div>
                    <button type="submit" class="toolbar-btn"><i class="fas fa-search"></i> Search</button>
                    <a href="user_dashboard.php" class="clear-filters-btn"><i class="fas fa-times"></i> Clear</a>
                    <button type="button" class="filter-toggle" id="filterToggle"><i class="fas fa-sliders-h"></i> Filters</button>
                </div>

                <div class="filter-panel <?php echo ($search_term !== '' || $selected_category !== '' || $min_price !== '' || $max_price !== '' || $selected_availability !== 'all' || $sort_option !== 'default') ? 'visible' : ''; ?>" id="filterPanel">
                    <div class="filter-grid">
                        <div class="filter-field">
                            <label for="categoryFilter">Category</label>
                            <select id="categoryFilter" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($category_options as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $selected_category === $category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label for="availabilityFilter">Availability</label>
                            <select id="availabilityFilter" name="availability">
                                <option value="all" <?php echo $selected_availability === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="in_stock" <?php echo $selected_availability === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                                <option value="out_of_stock" <?php echo $selected_availability === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label for="minPrice">Min Price</label>
                            <input type="number" id="minPrice" name="min_price" min="0" step="1" value="<?php echo htmlspecialchars($min_price); ?>" placeholder="0">
                        </div>

                        <div class="filter-field">
                            <label for="maxPrice">Max Price</label>
                            <input type="number" id="maxPrice" name="max_price" min="0" step="1" value="<?php echo htmlspecialchars($max_price); ?>" placeholder="5000">
                        </div>

                        <div class="filter-field">
                            <label for="sortFilter">Sort By</label>
                            <select id="sortFilter" name="sort">
                                <option value="default" <?php echo $sort_option === 'default' ? 'selected' : ''; ?>>Default</option>
                                <option value="price_asc" <?php echo $sort_option === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_desc" <?php echo $sort_option === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="newest" <?php echo $sort_option === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                <option value="name_asc" <?php echo $sort_option === 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="toolbar-btn"><i class="fas fa-check"></i> Apply Filters</button>
                        <a href="user_dashboard.php" class="clear-filters-btn"><i class="fas fa-redo"></i> Clear All Filters</a>
                    </div>
                </div>
            </form>

            <?php
            $active_filters = [];
            if ($search_term !== '') {
                $active_filters[] = ['label' => 'Search: ' . $search_term, 'url' => build_user_dashboard_url(['q' => null])];
            }
            if ($selected_category !== '') {
                $active_filters[] = ['label' => 'Category: ' . $selected_category, 'url' => build_user_dashboard_url(['category' => null])];
            }
            if ($selected_availability !== 'all') {
                $active_filters[] = ['label' => 'Availability: ' . ($selected_availability === 'in_stock' ? 'In Stock' : 'Out of Stock'), 'url' => build_user_dashboard_url(['availability' => null])];
            }
            if ($min_price !== '') {
                $active_filters[] = ['label' => 'Min: ₹' . number_format((float) $min_price, 2), 'url' => build_user_dashboard_url(['min_price' => null])];
            }
            if ($max_price !== '') {
                $active_filters[] = ['label' => 'Max: ₹' . number_format((float) $max_price, 2), 'url' => build_user_dashboard_url(['max_price' => null])];
            }
            if ($sort_option !== 'default') {
                $sort_label = 'Sort: Default';
                if ($sort_option === 'price_asc') { $sort_label = 'Sort: Price: Low to High'; }
                if ($sort_option === 'price_desc') { $sort_label = 'Sort: Price: High to Low'; }
                if ($sort_option === 'newest') { $sort_label = 'Sort: Newest'; }
                if ($sort_option === 'name_asc') { $sort_label = 'Sort: Name: A to Z'; }
                $active_filters[] = ['label' => $sort_label, 'url' => build_user_dashboard_url(['sort' => null])];
            }
            ?>

            <?php if (!empty($active_filters)): ?>
                <div class="active-filter-bar">
                    <strong style="font-size: 13px; color: #4c5d76;">Active filters:</strong>
                    <?php foreach ($active_filters as $filter): ?>
                        <span class="active-filter-pill">
                            <?php echo htmlspecialchars($filter['label']); ?>
                            <a href="<?php echo htmlspecialchars($filter['url']); ?>" aria-label="Remove <?php echo htmlspecialchars($filter['label']); ?>">×</a>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="results-summary">
            <div class="results-count"><?php echo count($products); ?> product<?php echo count($products) === 1 ? '' : 's'; ?> found</div>
            <?php if (!empty($search_term) || $selected_category !== '' || $selected_availability !== 'all' || $min_price !== '' || $max_price !== ''): ?>
                <a href="user_dashboard.php" class="clear-filters-btn"><i class="fas fa-times"></i> Clear Search & Filters</a>
            <?php endif; ?>
        </div>
        
        <?php if (count($products) > 0): ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <?php if ($product['image']): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                        <?php else: ?>
                            <div class="product-image" style="display: flex; align-items: center; justify-content: center; color: #999;">
                                <i class="fas fa-image" style="font-size: 48px;"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-info">
                            <span class="product-category"><?php echo htmlspecialchars($product['category']); ?></span>
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-desc"><?php echo htmlspecialchars(substr($product['description'], 0, 60)); ?>...</p>
                            
                            <?php if ($product['quantity'] > 0): ?>
                                <div class="product-stock in-stock">
                                    <i class="fas fa-check-circle"></i> In Stock (<?php echo $product['quantity']; ?>)
                                </div>
                            <?php else: ?>
                                <div class="product-stock out-stock">
                                    <i class="fas fa-times-circle"></i> Out of Stock
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>
                            
                            <?php if ($product['quantity'] > 0): ?>
                                <a href="checkout.php?product_id=<?php echo $product['id']; ?>" class="btn-shop">
                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                </a>
                            <?php else: ?>
                                <button class="btn-shop" disabled>
                                    <i class="fas fa-ban"></i> Out of Stock
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search fa-3x" style="margin-bottom: 12px; opacity: 0.7;"></i>
                <h3 style="margin-bottom: 8px;">No products found matching your search.</h3>
                <p style="margin-bottom: 18px;">Try a different keyword or clear the filters.</p>
                <a href="user_dashboard.php" class="clear-filters-btn"><i class="fas fa-times"></i> Clear Search & Filters</a>
            </div>
        <?php endif; ?>
    </div>
    <!-- End Products Section -->
    
    </div>
    <!-- End Content -->
    </div>
    <!-- End Container Main -->

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        let lastNotificationCount = <?php echo (int)$unread_count; ?>;
        let lastNotificationAt = <?php echo json_encode($last_notification_at); ?>;
        let updateCheckInterval = null;

        function checkForShopUpdates() {
            fetch('user_dashboard.php?check_updates=1&ts=' + Date.now(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            })
            .then(response => response.json())
            .then(data => {
                if (!data || !data.success) return;
                const newCount = Number(data.notification_count || 0);
                const latestAt = data.last_notification_at || null;
                if ((latestAt && (!lastNotificationAt || latestAt > lastNotificationAt)) || newCount > lastNotificationCount) {
                    lastNotificationCount = newCount;
                    lastNotificationAt = latestAt;
                    location.reload();
                }
            })
            .catch(error => console.log('Shop update check error:', error));
        }

        const filterToggle = document.getElementById('filterToggle');
        const filterPanel = document.getElementById('filterPanel');

        if (filterToggle && filterPanel) {
            filterToggle.addEventListener('click', function() {
                filterPanel.classList.toggle('visible');
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateCheckInterval = setInterval(checkForShopUpdates, 10000);
        });

        window.addEventListener('beforeunload', function() {
            if (updateCheckInterval) {
                clearInterval(updateCheckInterval);
            }
        });
    </script>
</body>
</html>

<?php mysqli_close($conn); ?>

