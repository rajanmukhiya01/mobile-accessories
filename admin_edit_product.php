<?php
require_once "admin_check.php";
require_once "config.php";

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error_msg = $success_msg = "";
$errors = [];

// Fetch product details
$sql = "SELECT * FROM product WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result->num_rows == 0) {
    die("Product not found");
}

$product = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name']);
    $category = sanitize_input($_POST['category']);
    $price = floatval($_POST['price']);
    $quantity = intval($_POST['quantity']);
    $description = sanitize_input($_POST['description']);
    $current_image = $product['image'];
    
    // Validate inputs
    if (empty($name) || empty($category) || $price <= 0 || $quantity < 0) {
        $errors[] = "Please fill all required fields with valid values";
    } else {
        // Handle new image upload if provided
        $new_image = $current_image;
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $image_errors = validate_image($_FILES['image']);
            
            if (empty($image_errors)) {
                $new_filename = generate_unique_filename($_FILES['image']['name']);
                $upload_path = "uploads/" . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    // Delete old image if exists
                    if (file_exists($current_image) && $current_image != "") {
                        unlink($current_image);
                    }
                    $new_image = $upload_path;
                } else {
                    $errors[] = "Error uploading new image";
                }
            } else {
                $errors = array_merge($errors, $image_errors);
            }
        }
        
        // If no errors, update database
        if (empty($errors)) {
            $sql = "UPDATE product SET name = ?, category = ?, price = ?, quantity = ?, description = ?, image = ? WHERE id = ?";
            
            if ($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssidisi", $name, $category, $price, $quantity, $description, $new_image, $product_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $success_msg = "Product updated successfully!";
                    log_activity($conn, $_SESSION['user_id'], 'EDIT_PRODUCT', "Edited product: $name");
                    
                    // Update product array for display
                    $product['name'] = $name;
                    $product['category'] = $category;
                    $product['price'] = $price;
                    $product['quantity'] = $quantity;
                    $product['description'] = $description;
                    $product['image'] = $new_image;
                } else {
                    $errors[] = "Error updating product";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Product - Admin Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .container-main {
            display: flex;
            min-height: calc(100vh - 70px);
        }
        
        .sidebar {
            width: 250px;
            background: #2c3e50;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: fixed;
            height: calc(100vh - 70px);
            overflow-y: auto;
        }
        
        .sidebar a, .sidebar button {
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
        }
        
        .sidebar a:hover, .sidebar button:hover {
            background: #34495e;
            border-left-color: #667eea;
            padding-left: 30px;
        }
        
        .sidebar a i, .sidebar button i {
            margin-right: 10px;
            width: 20px;
        }
        
        .sidebar a.active {
            background: #34495e;
            border-left-color: #667eea;
        }
        
        .content {
            margin-left: 250px;
            padding: 30px;
            flex: 1;
        }
        
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 700px;
            margin: 0 auto;
        }
        
        .form-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
        }
        
        .form-title i {
            margin-right: 10px;
            color: #667eea;
        }
        
        .form-group label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 1px solid #ddd;
            padding: 10px 15px;
            border-radius: 5px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }
        
        .form-control.form-control-file {
            padding: 10px;
        }
        
        .image-preview {
            margin-top: 15px;
            text-align: center;
        }
        
        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s;
            margin-top: 20px;
            width: 100%;
            margin-right: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-back {
            color: #667eea;
            text-decoration: none;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .btn-back:hover {
            color: #764ba2;
        }
        
        .alert {
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .user-info {
            color: #ecf0f1;
            padding: 15px 20px;
            border-top: 1px solid #34495e;
            margin-top: auto;
        }
        
        .logout-btn {
            background: #dc3545;
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
            background: #c82333;
        }
        
        .admin-badge {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <i class="fas fa-user-shield"></i> Admin Dashboard - Mobile Accessories
    </div>

    <div class="container-main">
        <div class="sidebar">
            <a href="admin_dashboard.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="admin_add_product.php">
                <i class="fas fa-plus-circle"></i> Add Product
            </a>
            <a href="admin_orders_manage.php">
                <i class="fas fa-shopping-bag"></i> Orders Management
            </a>
            <a href="admin_profile.php">
                <i class="fas fa-user"></i> Profile
            </a>
            
            <div class="user-info" style="margin-top: auto;">
                <p><strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
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
            <a href="admin_dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

            <div class="form-container">
                <div class="form-title">
                    <i class="fas fa-edit"></i> Edit Product
                </div>

                <?php if (!empty($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <strong>Errors:</strong>
                        <ul style="margin-bottom: 0; padding-left: 20px;">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Product Name <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="category">Category <span style="color: red;">*</span></label>
                        <select class="form-control" id="category" name="category" required>
                            <option value="">Select a category</option>
                            <option value="Phones" <?php echo ($product['category'] == 'Phones') ? 'selected' : ''; ?>>Phones</option>
                            <option value="Accessories" <?php echo ($product['category'] == 'Accessories') ? 'selected' : ''; ?>>Accessories</option>
                            <option value="Chargers" <?php echo ($product['category'] == 'Chargers') ? 'selected' : ''; ?>>Chargers</option>
                            <option value="Protectors" <?php echo ($product['category'] == 'Protectors') ? 'selected' : ''; ?>>Protectors</option>
                            <option value="Cases" <?php echo ($product['category'] == 'Cases') ? 'selected' : ''; ?>>Cases</option>
                            <option value="Headphones" <?php echo ($product['category'] == 'Headphones') ? 'selected' : ''; ?>>Headphones</option>
                            <option value="Cables" <?php echo ($product['category'] == 'Cables') ? 'selected' : ''; ?>>Cables</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="price">Price (NPR) <span style="color: red;">*</span></label>
                        <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="quantity">Quantity <span style="color: red;">*</span></label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="<?php echo htmlspecialchars($product['quantity']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Description <span style="color: red;">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="image">Product Image (Leave blank to keep current)</label>
                        <input type="file" class="form-control form-control-file" id="image" name="image" accept="image/*" onchange="previewImage(event)">
                        <small class="text-muted">Accepted formats: JPG, PNG, GIF (Max 5MB)</small>
                        
                        <div class="image-preview">
                            <p style="margin-top: 15px; font-weight: 600; color: #666;">Current Image:</p>
                            <?php if ($product['image']): ?>
                                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php else: ?>
                                <p style="color: #999;">No image available</p>
                            <?php endif; ?>
                            <div id="newPreview" style="display: none; margin-top: 15px;">
                                <p style="font-weight: 600; color: #666;">New Image Preview:</p>
                                <img id="previewImg" src="" alt="Preview">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Update Product
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function() {
                    const newPreview = document.getElementById('newPreview');
                    const img = document.getElementById('previewImg');
                    img.src = reader.result;
                    newPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>
