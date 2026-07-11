<?php
require_once "admin_check.php";
require_once "config.php";

$username = htmlspecialchars($_SESSION['username']);
$sql = "SELECT * FROM product ORDER BY id DESC";
$result = $conn->query($sql);

if (!$result) {
    die("Database query failed: " . $conn->error);
}

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Bazario</title>
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
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, #001a33 0%, #003366 100%);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 2px;
            color: white;
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
            height: calc(100vh - 70px);
            overflow-y: auto;
        }
        
        .sidebar a, .sidebar button {
            display: block;
            width: 100%;
            color: rgba(255, 255, 255, 0.8);
            padding: 14px 20px;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
            border: none;
            background: none;
            text-align: left;
            cursor: pointer;
        }
        
        .sidebar a:hover, .sidebar button:hover {
            background: #003366;
            border-left-color: #3498db;
            color: white;
            padding-left: 24px;
        }
        
        .sidebar a.active {
            background: #003366;
            border-left-color: #667eea;
            color: white;
            font-weight: 600;
        }
        
        .sidebar a i, .sidebar button i {
            margin-right: 12px;
            width: 18px;
        }
        
        .content {
            margin-left: 250px;
            padding: 30px;
            flex: 1;
        }
        
        .section-title {
            font-size: 28px;
            font-weight: 700;
            color: #001a33;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btn-add {
            background: #001a33;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .btn-add:hover {
            background: #003366;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 26, 51, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .product-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
            border: 1px solid #e0e0e0;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .product-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .product-info {
            margin-bottom: 15px;
        }
        
        .product-info h5 {
            color: #001a33;
            margin-bottom: 8px;
        }
        
        .product-info p {
            color: #666;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .product-price {
            font-size: 20px;
            color: #27ae60;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .btn-action {
            padding: 8px 12px;
            font-size: 13px;
            margin-right: 5px;
            margin-top: 10px;
        }
        
        .btn-edit {
            background: #001a33;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
        }
        
        .btn-edit:hover {
            background: #003366;
            color: white;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-delete:hover {
            background: #c82333;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .user-info {
            color: rgba(255, 255, 255, 0.8);
            padding: 15px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: auto;
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            margin-top: 10px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #c0392b;
        }
        
        .admin-badge {
            background: #3498db;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            margin-left: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div style="display: flex; align-items: center; gap: 15px;">
            <i class="fas fa-shopping-bag" style="font-size: 28px;"></i>
            <span class="navbar-brand" style="margin: 0;">BAZARIO</span>
            <span style="opacity: 0.9; font-size: 12px; margin-left: auto;">Admin Dashboard</span>
        </div>
    </div>

    <div class="container-main">
        <div class="sidebar">
            <a href="admin_dashboard.php" class="active">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="admin_add_product.php">
                <i class="fas fa-plus-circle"></i> Add Product
            </a>
            <a href="admin_orders_manage.php">
                <i class="fas fa-shopping-bag"></i> Orders Management
            </a>
            <a href="admin_profile.php">
                <i class="fas fa-user-circle"></i> Admin Profile
            </a>
            
            <div class="user-info" style="margin-top: auto;">
                <p><strong><?php echo $username; ?></strong></p>
                <p class="text-muted" style="margin: 0; font-size: 12px;">
                    <span class="admin-badge">ADMIN</span>
                </p>
                <form action="logout.php" method="POST">
                    <button type="submit" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        <div class="content">
            <div class="section-title">
                <div>
                    <i class="fas fa-boxes"></i> Product Management
                </div>
                <a href="admin_add_product.php" class="btn-add">
                    <i class="fas fa-plus"></i> Add New Product
                </a>
            </div>

            <?php if (count($products) > 0): ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <?php if ($product['image']): ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image" style="width: 100%; height: 200px;">
                            <?php else: ?>
                                <div style="width: 100%; height: 200px; background: #e9ecef; border-radius: 5px; display: flex; align-items: center; justify-content: center; color: #999;">
                                    <i class="fas fa-image" style="font-size: 48px;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-info">
                                <h5><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category']); ?></p>
                                <p><strong>Quantity:</strong> <?php echo htmlspecialchars($product['quantity']); ?> items</p>
                                <div class="product-price">₹<?php echo number_format($product['price'], 2); ?></div>
                                <p><strong>Description:</strong></p>
                                <p style="font-size: 12px; color: #888;"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</p>
                            </div>
                            
                            <div>
                                <a href="admin_edit_product.php?id=<?php echo $product['id']; ?>" class="btn-edit btn-action">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="delete_product.php?id=<?php echo $product['id']; ?>" class="btn-delete btn-action" onclick="return confirm('Are you sure you want to delete this product?');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box"></i>
                    <h4>No Products Found</h4>
                    <p>Start by adding your first product</p>
                    <a href="admin_add_product.php" class="btn btn-primary mt-3">
                        <i class="fas fa-plus"></i> Add First Product
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
