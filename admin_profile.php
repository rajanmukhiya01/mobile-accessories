<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "admin_check.php";
require_once "config.php";

$admin_id = $_SESSION['user_id'];
$username = htmlspecialchars($_SESSION['username']);

// Fetch admin details
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$admin) {
    die("Admin account not found");
}

$success_msg = $error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update'])) {
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone'] ?? '');
        $username_input = sanitize_input($_POST['username']);
        $new_password = !empty($_POST['password']) ? sanitize_input($_POST['password']) : null;
        
        $errors = [];
        
        // Validate name
        if (empty($name) || strlen($name) < 3) {
            $errors[] = "Name must be at least 3 characters";
        }
        
        // Validate email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email is required";
        } else {
            // Check if email is already used by another user
            $check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = mysqli_prepare($conn, $check_email);
            mysqli_stmt_bind_param($stmt, "si", $email, $admin_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $errors[] = "Email is already in use by another account";
            }
            mysqli_stmt_close($stmt);
        }
        
        // Validate phone (optional, but must be 10 digits if provided)
        if (!empty($phone) && !preg_match('/^[0-9]{10}$/', $phone)) {
            $errors[] = "Phone number must be 10 digits (or leave empty)";
        }
        
        // Validate username
        if (empty($username_input) || strlen($username_input) < 4) {
            $errors[] = "Username must be at least 4 characters";
        } else {
            // Check if username is already used by another user
            $check_username = "SELECT id FROM users WHERE username = ? AND id != ?";
            $stmt = mysqli_prepare($conn, $check_username);
            mysqli_stmt_bind_param($stmt, "si", $username_input, $admin_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $errors[] = "Username is already taken";
            }
            mysqli_stmt_close($stmt);
        }
        
        // Validate new password if provided
        if ($new_password && strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        
        if (count($errors) === 0) {
            // Build update query dynamically
            $update_fields = ["name = ?", "email = ?", "phone = ?", "username = ?"];
            $types = "ssss";
            $params = [$name, $email, $phone, $username_input];
            
            if ($new_password) {
                $update_fields[] = "password = ?";
                $types .= "s";
                $params[] = password_hash($new_password, PASSWORD_BCRYPT);
            }
            
            $params[] = $admin_id;
            $types .= "i";
            
            $update_sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $stmt = mysqli_prepare($conn, $update_sql);
            
            if (!$stmt) {
                $error_msg = "Database error: " . mysqli_error($conn);
            } else {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                if (mysqli_stmt_execute($stmt)) {
                    create_notification($conn, $admin_id, 0, 'profile_updated', 'Profile Updated', 'Your admin profile was updated successfully.', 'admin_profile.php');
                    $success_msg = "Profile updated successfully!";
                    
                    // Refresh admin data
                    $refresh_sql = "SELECT * FROM users WHERE id = ?";
                    $refresh_stmt = mysqli_prepare($conn, $refresh_sql);
                    mysqli_stmt_bind_param($refresh_stmt, "i", $admin_id);
                    mysqli_stmt_execute($refresh_stmt);
                    $refresh_result = mysqli_stmt_get_result($refresh_stmt);
                    $admin = mysqli_fetch_assoc($refresh_result);
                    mysqli_stmt_close($refresh_stmt);
                    
                    // Update session username if changed
                    if ($username_input !== $_SESSION['username']) {
                        $_SESSION['username'] = $username_input;
                    }
                } else {
                    $error_msg = "Error updating profile: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        } else {
            $error_msg = implode(", ", $errors);
        }
    } elseif (isset($_POST['upload_picture'])) {
        // Handle profile picture upload
        if (!isset($_FILES['profile_picture']) || empty($_FILES['profile_picture']['tmp_name'])) {
            $error_msg = "No file selected. Please choose a file to upload.";
        } else {
            $upload_result = upload_profile_picture($conn, $admin_id, $_FILES['profile_picture']);
            if ($upload_result === true) {
                $success_msg = "Profile picture updated successfully!";
                // Refresh admin data to get updated picture
                $refresh_sql = "SELECT * FROM users WHERE id = ?";
                $refresh_stmt = mysqli_prepare($conn, $refresh_sql);
                mysqli_stmt_bind_param($refresh_stmt, "i", $admin_id);
                mysqli_stmt_execute($refresh_stmt);
                $refresh_result = mysqli_stmt_get_result($refresh_stmt);
                $admin = mysqli_fetch_assoc($refresh_result);
                mysqli_stmt_close($refresh_stmt);
            } else {
                $error_msg = $upload_result;
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
    <title>Admin Profile - Bazario</title>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, #001a33 0%, #003366 100%);
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .container-main {
            display: flex;
            max-width: 100%;
            margin: 0;
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
        
        .sidebar a i, .sidebar button i {
            margin-right: 12px;
            width: 18px;
        }
        
        .content {
            margin-left: 250px;
            padding: 30px;
            flex: 1;
            width: calc(100% - 250px);
        }
        
        .settings-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            max-width: 900px;
            margin: 0 auto;
        }
        
        .settings-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .settings-header h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .section h3 {
            color: #555;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
        }
        
        .section h3 i {
            margin-right: 10px;
            color: #667eea;
        }
        
        .form-group label {
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            flex: 1;
            min-width: 150px;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-update {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .back-btn {
            background: #27ae60;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        
        .back-btn:hover {
            background: #229954;
            transform: translateY(-2px);
            color: white;
            text-decoration: none;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
        }
        
        .user-info-display {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .user-info-display p {
            margin: 5px 0;
            color: #666;
        }
        
        .user-info-display strong {
            color: #333;
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
        
        .profile-picture-section {
            display: flex;
            align-items: center;
            gap: 15px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
            box-shadow: none;
        }
        
        .profile-picture-container {
            flex-shrink: 0;
            position: relative;
        }
        
        .profile-avatar {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, filter 0.3s ease;
        }
        
        .clickable-avatar {
            cursor: pointer;
        }
        
        .clickable-avatar:hover {
            transform: scale(1.05);
            filter: brightness(0.95);
        }
        
        #changePhotoIconBtn:hover {
            background: #5568d3 !important;
            transform: scale(1.1);
        }
        
        .profile-picture-info {
            flex: 1;
        }
        
        .profile-picture-info .btn {
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .profile-picture-info .btn-primary {
            background: #667eea;
            border-color: #667eea;
        }
        
        .profile-picture-info .btn-primary:hover {
            background: #5568d3;
            border-color: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .profile-picture-info .btn-info {
            background: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }
        
        .profile-picture-info .btn-info:hover {
            background: #138496;
            border-color: #138496;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(23, 162, 184, 0.3);
        }
        
        #previewContainer {
            background: #f8f9fa;
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        
        #previewImage {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        
        /* Camera Modal Styling */
        #cameraModal button {
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        #cameraModal .btn-primary {
            background: #667eea;
            color: white;
        }
        
        #cameraModal .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        #cameraModal .btn-success {
            background: #28a745;
            color: white;
        }
        
        #cameraModal .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        
        #cameraModal .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        #cameraModal .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }
        
        #cameraModal .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        #cameraModal .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }
        
        #cameraModal .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        #cameraFeed {
            object-fit: cover;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
            padding: 0 15px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .content {
                margin-left: 200px;
                width: calc(100% - 200px);
            }
            
            .settings-container {
                padding: 20px;
            }
            
            #cameraModal button {
                padding: 6px 10px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 600px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }
            
            .content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <div class="header">
        <i class="fas fa-shopping-bag"></i> BAZARIO Admin Profile
    </div>

    <!-- Main Container with Sidebar -->
    <div class="container-main">
        <!-- Sidebar Navigation -->
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
            <a href="admin_profile.php" class="active">
                <i class="fas fa-user-circle"></i> Admin Profile
            </a>
            
            <div style="margin-top: auto; padding: 20px; border-top: 1px solid #003366;">
                <p style="color: rgba(255,255,255,0.8); margin: 0 0 10px 0;"><strong><?php echo htmlspecialchars($admin['name'] ?? $username); ?></strong></p>
                <p style="color: rgba(255,255,255,0.6); margin: 0 0 15px 0; font-size: 12px;">
                    <span style="background: #667eea; padding: 3px 8px; border-radius: 4px;">ADMIN</span>
                </p>
                <form action="logout.php" method="POST" style="margin: 0;">
                    <button type="submit" style="display: block; width: 100%; background: #e74c3c; color: white; border: none; padding: 8px; border-radius: 6px; cursor: pointer; font-size: 13px;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">
            <div class="settings-container">
                <div class="settings-header">
                    <h2><i class="fas fa-user-cog"></i> Admin Profile Settings</h2>
                    <p style="color: #666; margin: 0;">Manage your admin account information</p>
                </div>
                
                <?php if (!empty($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <strong><i class="fas fa-check-circle"></i> Success!</strong> <?php echo $success_msg; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong><i class="fas fa-exclamation-circle"></i> Error!</strong> <?php echo $error_msg; ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Picture Upload Form (SEPARATE) -->
                <form method="post" enctype="multipart/form-data" id="pictureUploadForm">
                    <div class="profile-picture-section">
                        <div class="profile-picture-container" style="position: relative;">
                            <?php echo get_user_avatar_html($admin, 'xl', 'profile-avatar clickable-avatar'); ?>
                            <div style="position: absolute; bottom: 5px; right: 5px; background: #667eea; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); transition: all 0.3s ease;" id="changePhotoIconBtn" title="Click to change photo">
                                <i class="fas fa-camera"></i>
                            </div>
                        </div>
                        <div class="profile-picture-info">
                            <p style="margin-bottom: 12px; color: #666;">
                                <small><strong><?php echo htmlspecialchars($admin['name'] ?? $admin['username']); ?></strong></small>
                            </p>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="button" id="changePhotoBtn" class="btn btn-sm btn-primary" style="font-size: 11px; padding: 5px 10px;">
                                    <i class="fas fa-upload"></i> Upload
                                </button>
                                <button type="button" id="takePhotoBtn" class="btn btn-sm btn-info" style="font-size: 11px; padding: 5px 10px;">
                                    <i class="fas fa-video"></i> Camera
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hidden File Input -->
                    <input type="file" id="profilePictureInput" name="profile_picture" accept=".jpg,.jpeg,.png,.webp" style="display: none;">
                    
                    <!-- Image Preview -->
                    <div id="previewContainer" style="display: none; margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <label style="font-weight: 600; margin-bottom: 10px; display: block;">Preview:</label>
                        <div style="text-align: center; margin-bottom: 15px;">
                            <img id="previewImage" src="" alt="Preview" style="max-width: 200px; max-height: 200px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        </div>
                        <div style="display: flex; gap: 8px; justify-content: center;">
                            <button type="submit" name="upload_picture" class="btn btn-primary btn-sm">
                                <i class="fas fa-check"></i> Save
                            </button>
                            <button type="button" id="cancelPreviewBtn" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                    
                    <div id="uploadProgress" style="display: none; margin: 15px 0; padding: 12px; border-radius: 6px; text-align: center;">
                        <div style="background: #f0f0f0; border-radius: 8px; height: 8px; overflow: hidden; margin-bottom: 10px;">
                            <div id="progressBar" style="background: linear-gradient(90deg, #667eea, #764ba2); height: 100%; width: 0%; transition: width 0.3s;"></div>
                        </div>
                        <span id="progressText" style="font-size: 12px; color: #666;">Uploading...</span>
                    </div>
                </form>
                
                <!-- Admin Information Form -->
                <form method="post">
                    <div class="section">
                        <h3><i class="fas fa-shield-alt"></i> Admin Account Information</h3>
                        <div class="user-info-display">
                            <p><strong>Account Type:</strong> Administrator</p>
                            <p><strong>Account Status:</strong> Active</p>
                            <p><strong>Member Since:</strong> <?php echo date('F d, Y', strtotime($admin['created_at'])); ?></p>
                            <?php if (!empty($admin['last_login'])): ?>
                                <p><strong>Last Login:</strong> <?php echo date('F d, Y g:i A', strtotime($admin['last_login'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h3><i class="fas fa-info-circle"></i> Personal Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="name">Full Name <span style="color: red;">*</span></label>
                                <input type="text" class="form-control" name="name" id="name" 
                                       value="<?php echo htmlspecialchars($admin['name'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group col-md-6">
                                <label for="username">Username <span style="color: red;">*</span></label>
                                <input type="text" class="form-control" name="username" id="username" 
                                       value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="email">Email Address <span style="color: red;">*</span></label>
                                <input type="email" class="form-control" name="email" id="email" 
                                       value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group col-md-6">
                                <label for="phone">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" id="phone" 
                                       value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>" 
                                       placeholder="10-digit number">
                            </div>
                        </div>
                    </div>
                    
                    <div class="section">
                        <h3><i class="fas fa-lock"></i> Password</h3>
                        <div class="form-group">
                            <label for="password">New Password <span style="color: #999;">(Optional)</span></label>
                            <input type="password" class="form-control" name="password" id="password" 
                                   placeholder="Leave empty to keep current password">
                            <small class="text-muted">Minimum 6 characters. Leave empty to keep your current password.</small>
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" name="update" class="btn btn-update">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <a href="admin_dashboard.php" class="btn back-btn">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Camera Capture Modal -->
    <div id="cameraModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 9999; overflow-y: auto;">
        <div style="display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 100vh; padding: 20px;">
            <div style="background: white; border-radius: 16px; padding: 20px; max-width: 500px; width: 100%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                <!-- Camera Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0; font-size: 20px; color: #333;">Take Profile Photo</h3>
                    <button type="button" id="closeCameraBtn" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999; padding: 0; width: 30px; height: 30px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Camera Feed -->
                <div id="cameraFeedContainer" style="width: 100%; margin-bottom: 20px;">
                    <video id="cameraFeed" width="100%" height="400" style="border-radius: 12px; background: #000; display: block; object-fit: cover;"></video>
                </div>
                
                <!-- Camera Captured Image -->
                <div id="capturedImageContainer" style="width: 100%; margin-bottom: 20px; display: none;">
                    <img id="capturedImage" src="" alt="Captured" style="width: 100%; border-radius: 12px; display: block;">
                </div>
                
                <!-- Camera Controls -->
                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    <button type="button" id="capturePhotoBtn" class="btn-primary" style="padding: 10px 20px; border-radius: 8px; border: none; background: #667eea; color: white; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-camera"></i> Capture
                    </button>
                    <button type="button" id="retakePhotoBtn" class="btn-warning" style="padding: 10px 20px; border-radius: 8px; border: none; background: #ffc107; color: #333; cursor: pointer; font-weight: 600; display: none;">
                        <i class="fas fa-redo"></i> Retake
                    </button>
                    <button type="button" id="usePhotoBtn" class="btn-success" style="padding: 10px 20px; border-radius: 8px; border: none; background: #28a745; color: white; cursor: pointer; font-weight: 600; display: none;">
                        <i class="fas fa-check"></i> Use Photo
                    </button>
                    <button type="button" id="cancelCameraBtn" class="btn-secondary" style="padding: 10px 20px; border-radius: 8px; border: none; background: #6c757d; color: white; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.min.js"></script>
    <script>
        // Profile Picture Upload Handlers
        const changePhotoBtn = document.getElementById('changePhotoBtn');
        const takePhotoBtn = document.getElementById('takePhotoBtn');
        const changePhotoIconBtn = document.getElementById('changePhotoIconBtn');
        const profilePictureInput = document.getElementById('profilePictureInput');
        const previewContainer = document.getElementById('previewContainer');
        const previewImageEl = document.getElementById('previewImage');
        const cancelPreviewBtn = document.getElementById('cancelPreviewBtn');
        const cameraModal = document.getElementById('cameraModal');
        
        // File input change handler
        if (profilePictureInput) {
            profilePictureInput.addEventListener('change', handleFilePreview);
        }
        
        // Button click handlers
        if (changePhotoBtn) changePhotoBtn.addEventListener('click', () => profilePictureInput.click());
        if (changePhotoIconBtn) changePhotoIconBtn.addEventListener('click', () => profilePictureInput.click());
        if (takePhotoBtn) takePhotoBtn.addEventListener('click', openCameraModal);
        if (cancelPreviewBtn) cancelPreviewBtn.addEventListener('click', () => previewContainer.style.display = 'none');
        
        function handleFilePreview(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Client-side validation
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }
            
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Only JPG, PNG, and WEBP files are allowed');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = (event) => {
                previewImageEl.src = event.target.result;
                previewContainer.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
        
        // Camera Modal Functions
        function openCameraModal() {
            cameraModal.style.display = 'flex';
            startCamera();
        }
        
        function closeCameraModal() {
            cameraModal.style.display = 'none';
            stopCamera();
        }
        
        document.getElementById('closeCameraBtn')?.addEventListener('click', closeCameraModal);
        document.getElementById('cancelCameraBtn')?.addEventListener('click', closeCameraModal);
        
        let mediaStream = null;
        const video = document.getElementById('cameraFeed');
        const capturedImage = document.getElementById('capturedImage');
        const cameraFeedContainer = document.getElementById('cameraFeedContainer');
        const capturedImageContainer = document.getElementById('capturedImageContainer');
        const capturePhotoBtn = document.getElementById('capturePhotoBtn');
        const retakePhotoBtn = document.getElementById('retakePhotoBtn');
        const usePhotoBtn = document.getElementById('usePhotoBtn');
        
        async function startCamera() {
            try {
                mediaStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', ideal: { width: 1280, height: 720 } }
                });
                video.srcObject = mediaStream;
            } catch (error) {
                alert('Unable to access camera. Please check permissions.');
                closeCameraModal();
            }
        }
        
        function stopCamera() {
            if (mediaStream) {
                mediaStream.getTracks().forEach(track => track.stop());
                mediaStream = null;
            }
        }
        
        capturePhotoBtn?.addEventListener('click', () => {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            canvas.toBlob((blob) => {
                const url = URL.createObjectURL(blob);
                capturedImage.src = url;
                cameraFeedContainer.style.display = 'none';
                capturedImageContainer.style.display = 'block';
                capturePhotoBtn.style.display = 'none';
                retakePhotoBtn.style.display = 'inline-block';
                usePhotoBtn.style.display = 'inline-block';
                
                // Store blob for upload
                profilePictureInput.files = new DataTransfer().items.add(new File([blob], 'camera_photo.jpg', { type: 'image/jpeg' })).files;
            }, 'image/jpeg', 0.9);
        });
        
        retakePhotoBtn?.addEventListener('click', () => {
            cameraFeedContainer.style.display = 'block';
            capturedImageContainer.style.display = 'none';
            capturePhotoBtn.style.display = 'inline-block';
            retakePhotoBtn.style.display = 'none';
            usePhotoBtn.style.display = 'none';
        });
        
        usePhotoBtn?.addEventListener('click', uploadCapturedPhoto);
        
        async function uploadCapturedPhoto() {
            const file = profilePictureInput.files[0];
            if (!file) return;
            
            const formData = new FormData();
            formData.append('profile_picture', file);
            formData.append('upload_picture', '1');
            
            const progressBar = document.getElementById('progressBar');
            const uploadProgress = document.getElementById('uploadProgress');
            const progressText = document.getElementById('progressText');
            
            uploadProgress.style.display = 'block';
            progressBar.style.width = '0%';
            
            try {
                const response = await fetch('admin_profile.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });
                
                progressBar.style.width = '100%';
                setTimeout(() => {
                    uploadProgress.style.display = 'none';
                    closeCameraModal();
                    location.reload();
                }, 500);
            } catch (error) {
                alert('Upload failed: ' + error.message);
                uploadProgress.style.display = 'none';
            }
        }
    </script>
</body>
</html>

