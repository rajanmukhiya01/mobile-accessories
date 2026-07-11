<?php
if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: minor.php");
    exit;
}

// Prevent admin from accessing user profile
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
            <h1>❌ Admin Cannot Access User Profile</h1>
            <p>This is a user profile page.</p>
            <p>Admins do not have personal profiles in the customer system.</p>
            <a href='admin_dashboard.php' class='btn btn-primary mt-3'>Go to Admin Dashboard</a>
        </div>
    </body>
    </html>
    ");
}

require_once "config.php";
require_once __DIR__ . '/includes/notification_service.php';
require_once __DIR__ . '/components/user_layout.php';

$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$result = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
$unread_count = get_unread_notifications_count($conn, $user_id);

if (mysqli_num_rows($result) == 0) {
    die("User not found");
}

$user = mysqli_fetch_assoc($result);
$success_msg = $error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update'])) {
        $name = sanitize_input($_POST['name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone'] ?? '');
        $username = sanitize_input($_POST['username']);
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
            mysqli_stmt_bind_param($stmt, "si", $email, $user_id);
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
        if (empty($username) || strlen($username) < 4) {
            $errors[] = "Username must be at least 4 characters";
        } else {
            // Check if username is already used by another user
            $check_username = "SELECT id FROM users WHERE username = ? AND id != ?";
            $stmt = mysqli_prepare($conn, $check_username);
            mysqli_stmt_bind_param($stmt, "si", $username, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $errors[] = "Username is already taken";
            }
            mysqli_stmt_close($stmt);
        }
        
        // Validate new password if provided
        if ($new_password !== null && strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters";
        }
        
        if (empty($errors)) {
            // Build dynamic UPDATE query based on whether password was changed
            if ($new_password !== null) {
                $sql = "UPDATE users SET name=?, email=?, phone=?, username=?, password=? WHERE id=?";
                $stmt = mysqli_prepare($conn, $sql);
                
                if ($stmt === false) {
                    $error_msg = "Database error: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($stmt, "sssssi", $name, $email, $phone, $username, $new_password, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        log_activity($conn, $user_id, "Profile Update", "User updated profile and password");
                        create_notification($conn, $user_id, 0, 'password_changed', 'Password Changed', 'Your password was changed successfully.', 'profile.php');
                        $success_msg = "Profile and password updated successfully!";
                        $_SESSION['username'] = $username;
                        
                        // Refresh user data from database
                        $refresh_query = "SELECT * FROM users WHERE id = ?";
                        $refresh_stmt = mysqli_prepare($conn, $refresh_query);
                        mysqli_stmt_bind_param($refresh_stmt, "i", $user_id);
                        mysqli_stmt_execute($refresh_stmt);
                        $refresh_result = mysqli_stmt_get_result($refresh_stmt);
                        $user = mysqli_fetch_assoc($refresh_result);
                        mysqli_stmt_close($refresh_stmt);
                    } else {
                        $error_msg = "Error updating profile: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                // Update without password
                $sql = "UPDATE users SET name=?, email=?, phone=?, username=? WHERE id=?";
                $stmt = mysqli_prepare($conn, $sql);
                
                if ($stmt === false) {
                    $error_msg = "Database error: " . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $phone, $username, $user_id);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        log_activity($conn, $user_id, "Profile Update", "User updated profile");
                        create_notification($conn, $user_id, 0, 'profile_updated', 'Profile Updated', 'Your profile information was updated successfully.', 'profile.php');
                        $success_msg = "Profile updated successfully!";
                        $_SESSION['username'] = $username;
                        
                        // Refresh user data from database
                        $refresh_query = "SELECT * FROM users WHERE id = ?";
                        $refresh_stmt = mysqli_prepare($conn, $refresh_query);
                        mysqli_stmt_bind_param($refresh_stmt, "i", $user_id);
                        mysqli_stmt_execute($refresh_stmt);
                        $refresh_result = mysqli_stmt_get_result($refresh_stmt);
                        $user = mysqli_fetch_assoc($refresh_result);
                        mysqli_stmt_close($refresh_stmt);
                    } else {
                        $error_msg = "Error updating profile: " . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        } else {
            $error_msg = implode("<br>", $errors);
        }
    }
    
    // Handle profile picture upload (for both file and camera)
    if (isset($_POST['upload_picture'])) {
        // Debug logging
        error_log("=== PROFILE PICTURE UPLOAD ===");
        error_log("POST data: " . json_encode($_POST));
        error_log("FILES keys: " . json_encode(array_keys($_FILES)));
        
        // Check if file exists in $_FILES
        if (isset($_FILES['profile_picture']) && !empty($_FILES['profile_picture']['tmp_name'])) {
            error_log("File found in FILES: " . $_FILES['profile_picture']['name']);
            error_log("File size: " . $_FILES['profile_picture']['size']);
            error_log("File type: " . $_FILES['profile_picture']['type']);
            error_log("File tmp: " . $_FILES['profile_picture']['tmp_name']);
            
            $upload_result = upload_profile_picture($conn, $user_id, $_FILES['profile_picture']);
            error_log("Upload result: " . json_encode($upload_result));
            
            if ($upload_result['success']) {
                $success_msg = $upload_result['message'];
                log_activity($conn, $user_id, "Profile Picture Updated", "User uploaded a new profile picture");
                
                // Refresh user data from database
                $refresh_query = "SELECT * FROM users WHERE id = ?";
                $refresh_stmt = mysqli_prepare($conn, $refresh_query);
                mysqli_stmt_bind_param($refresh_stmt, "i", $user_id);
                mysqli_stmt_execute($refresh_stmt);
                $refresh_result = mysqli_stmt_get_result($refresh_stmt);
                $user = mysqli_fetch_assoc($refresh_result);
                mysqli_stmt_close($refresh_stmt);
                
                // If AJAX request, return JSON response
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => $upload_result['message']]);
                    exit;
                }
            } else {
                $error_msg = $upload_result['message'];
                
                // If AJAX request, return JSON response
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => $upload_result['message']]);
                    exit;
                }
            }
        } else {
            error_log("No file found in FILES");
            $error_msg = "No file selected for upload";
            
            // If AJAX request, return JSON response
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
                exit;
            }
        }
    }
    
    if (isset($_POST['delete'])) {
        // Log the deletion before deleting the user
        log_activity($conn, $user_id, "Account Deletion", "User deleted their account");
        
        // Delete all user's products first
        $products_result = mysqli_query($conn, "SELECT image FROM product");
        while ($product = mysqli_fetch_assoc($products_result)) {
            $image_path = "uploads/" . $product['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        // Delete user (cascade will handle activity_log)
        $delete_sql = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $delete_sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        session_destroy();
        header("Location: minor.php");
        exit;
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile Settings - Mobile Accessories</title>
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
        
            .navbar-top {
            background: linear-gradient(135deg, #001a33 0%, #003366 100%);
            color: white;
            padding: 15px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 6px 18px rgba(0,0,0,0.12);
            position: sticky;
            top: 0;
            z-index: 1100;
            min-height: 64px;
        }

        .navbar-brand-text {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .nav-menu {
            display: flex;
            gap: 24px;
            align-items: center;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .nav-menu li {
            list-style: none;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            color: #ffd700;
        }

        .navbar-icons {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .navbar-icons a {
            color: white;
            text-decoration: none;
            font-size: 18px;
            position: relative;
        }

        .navbar-icons a:hover {
            color: #ffd700;
        }

        .notification-badge {
            position: absolute;
            top: -7px;
            right: -8px;
            background: #ff5a5f;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .navbar-top {
                flex-wrap: wrap;
                gap: 12px;
            }

            .nav-menu {
                gap: 12px;
                width: 100%;
                flex-wrap: wrap;
            }

            .navbar-icons {
                margin-left: auto;
                width: auto;
            }
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
            font-size: 15px;
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
            background: #34495e;
            border-left-color: #dc3545;
            padding-left: 30px;
        }
        
        .sidebar-logout-btn i {
            margin-right: 10px;
            width: 20px;
        }
        
        .content {
            margin-left: 250px;
            padding: 30px;
            flex: 1;
            padding-top: 24px;
        }
        
        .settings-container {
            max-width: 800px;
            margin: 40px;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .settings-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .settings-header h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .section {
            margin-bottom: 35px;
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
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
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
        
        @media (max-width: 600px) {
            #cameraModal {
                padding: 10px;
            }
            
            #cameraModal > div > div {
                max-width: 100% !important;
                border-radius: 12px !important;
            }
            
            #cameraModal button {
                padding: 6px 10px;
                font-size: 12px;
            }
        }

    </style>
</head>
<body>
    <?php if ($is_admin): ?>
        <!-- For admin users, show simplified navbar -->
        <div class="header">
            <i class="fas fa-mobile-alt"></i> Profile Settings
        </div>
    <?php else: ?>
        <?php echo render_user_navbar($user, $unread_count, 'navbar-top', 'BAZARIO', false, 'profile'); ?>
        <div class="container-main">
            <?php echo render_user_sidebar('profile'); ?>
            <div class="content">
    <?php endif; ?>
    
    <div class="settings-container">
        <div class="settings-header">
            <h2><i class="fas fa-user-cog"></i> Profile Settings</h2>
            <p style="color: #666; margin: 0;">Manage your account information</p>
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
        
        <!-- Profile Picture Upload Form (SEPARATE - Must be outside main form) -->
        <form method="post" enctype="multipart/form-data" id="pictureUploadForm">
            <div class="profile-picture-section">
                <div class="profile-picture-container" style="position: relative;">
                    <?php echo get_user_avatar_html($user, 'xl', 'profile-avatar clickable-avatar'); ?>
                    <div style="position: absolute; bottom: 5px; right: 5px; background: #667eea; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); transition: all 0.3s ease;" id="changePhotoIconBtn" title="Click to change photo">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                <div class="profile-picture-info">
                    <p style="margin-bottom: 12px; color: #666;">
                        <small><strong><?php echo htmlspecialchars($user['name'] ?? $user['username']); ?></strong></small>
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
                    <div id="progressBar" style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                </div>
                <p style="text-align: center; color: #666; font-size: 12px; margin: 0;">Uploading...</p>
            </div>
        </form>
        
        <div class="user-info-display">
            <p><strong>Account Created:</strong> <?php echo date('F d, Y', strtotime($user['created_at'])); ?></p>
            <?php if (!empty($user['last_login'])): ?>
                <p><strong>Last Login:</strong> <?php echo date('F d, Y g:i A', strtotime($user['last_login'])); ?></p>
            <?php endif; ?>
        </div>
        
        <form method="post">
            <div class="section">
                <h3><i class="fas fa-info-circle"></i> Personal Information</h3>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="name">Full Name <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" name="name" id="name" 
                               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="username">Username <span style="color: red;">*</span></label>
                        <input type="text" class="form-control" name="username" id="username" 
                               value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="email">Email Address <span style="color: red;">*</span></label>
                        <input type="email" class="form-control" name="email" id="email" 
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group col-md-6">
                        <label for="phone">Phone Number</label>
                        <input type="tel" class="form-control" name="phone" id="phone" 
                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                               placeholder="10-digit number">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="dob">Date of Birth (Read-only)</label>
                    <input type="date" class="form-control" value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>" disabled>
                    <small class="text-muted">Date of birth cannot be changed</small>
                </div>
            </div>
            
            <!-- Profile Picture Upload Section - REMOVED (now separate form above) -->
            
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
                <button type="submit" name="delete" class="btn btn-danger" 
                        onclick="return confirm('⚠️ WARNING: This will permanently delete your account and all your products. This action cannot be undone. Are you absolutely sure?');">
                    <i class="fas fa-trash"></i> Delete Account
                </button>
                <a href="<?php echo $is_admin ? 'admin_dashboard.php' : 'user_dashboard.php'; ?>" class="btn back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </form>
    </div>
    <!-- End settings-container -->
    
    <?php if (!$is_admin): ?>
        </div>
        <!-- End content -->
        </div>
        <!-- End container-main -->
    <?php endif; ?>
    
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
                
                <!-- Camera View -->
                <div id="cameraSection" style="margin-bottom: 20px;">
                    <video id="cameraFeed" style="width: 100%; border-radius: 12px; background: #000; display: none;" playsinline autoplay muted></video>
                    <canvas id="captureCanvas" style="display: none;"></canvas>
                    
                    <!-- Camera Placeholder/Loading -->
                    <div id="cameraPlaceholder" style="background: #f0f0f0; border-radius: 12px; padding: 40px 20px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 300px;">
                        <i class="fas fa-video" style="font-size: 48px; color: #999; margin-bottom: 12px;"></i>
                        <p style="color: #666; margin: 0; font-size: 14px;">Initializing camera...</p>
                    </div>
                    
                    <!-- Preview of Captured Image -->
                    <img id="capturedImagePreview" src="" alt="Captured" style="width: 100%; border-radius: 12px; display: none; margin-bottom: 20px;">
                </div>
                
                <!-- Permissions Message -->
                <div id="permissionMessage" style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-bottom: 20px; color: #856404; display: none; font-size: 13px;">
                    <i class="fas fa-info-circle"></i> Camera access is required to take a photo. Please allow access when prompted.
                </div>
                
                <!-- Error Message -->
                <div id="cameraError" style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 12px; margin-bottom: 20px; color: #721c24; display: none; font-size: 13px;">
                    <i class="fas fa-exclamation-circle"></i> <span id="cameraErrorText"></span>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" id="closeCameraBtn2" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" id="retakePhotoBtn" class="btn btn-warning btn-sm" style="display: none;">
                        <i class="fas fa-redo"></i> Retake
                    </button>
                    <button type="button" id="capturePhotoBtn" class="btn btn-primary btn-sm">
                        <i class="fas fa-camera"></i> Capture
                    </button>
                    <button type="button" id="usePhotoBtn" class="btn btn-success btn-sm" style="display: none;">
                        <i class="fas fa-check"></i> Use Photo
                    </button>
                </div>
                
                <!-- Upload Progress (for camera modal) -->
                <div id="cameraUploadProgress" style="display: none; margin-top: 20px;">
                    <div style="background: #f0f0f0; border-radius: 8px; height: 8px; overflow: hidden;">
                        <div id="cameraProgressBar" style="background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                    </div>
                    <p style="text-align: center; color: #666; font-size: 12px; margin: 8px 0 0 0;">Uploading...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // ===== CAMERA CAPTURE SYSTEM =====
        let cameraStream = null;
        let capturedBlob = null;
        
        // Get DOM Elements
        const fileInput = document.getElementById('profilePictureInput');
        const changePhotoBtn = document.getElementById('changePhotoBtn');
        const takePhotoBtn = document.getElementById('takePhotoBtn');
        const changePhotoIconBtn = document.getElementById('changePhotoIconBtn');
        const cameraModal = document.getElementById('cameraModal');
        const closeCameraBtn = document.getElementById('closeCameraBtn');
        const closeCameraBtn2 = document.getElementById('closeCameraBtn2');
        const cameraFeed = document.getElementById('cameraFeed');
        const captureCanvas = document.getElementById('captureCanvas');
        const capturedImagePreview = document.getElementById('capturedImagePreview');
        const capturePhotoBtn = document.getElementById('capturePhotoBtn');
        const retakePhotoBtn = document.getElementById('retakePhotoBtn');
        const usePhotoBtn = document.getElementById('usePhotoBtn');
        const cameraSection = document.getElementById('cameraSection');
        const cameraPlaceholder = document.getElementById('cameraPlaceholder');
        const permissionMessage = document.getElementById('permissionMessage');
        const cameraError = document.getElementById('cameraError');
        const cameraErrorText = document.getElementById('cameraErrorText');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressBar = document.getElementById('progressBar');
        const previewContainer = document.getElementById('previewContainer');
        const previewImageEl = document.getElementById('previewImage');
        const uploadForm = document.getElementById('pictureUploadForm');
        const cancelPreviewBtn = document.getElementById('cancelPreviewBtn');
        const uploadStatus = document.getElementById('uploadStatus');
                const avatar = document.querySelector('.clickable-avatar');
        
        // ===== FILE UPLOAD FUNCTIONS =====
        
        // Open file picker when Change Photo button is clicked
        if (changePhotoBtn) {
            changePhotoBtn.addEventListener('click', function(e) {
                e.preventDefault();
                fileInput.click();
            });
        }
        
        // Open file picker when camera icon is clicked (for file upload)
        if (changePhotoIconBtn) {
            changePhotoIconBtn.addEventListener('click', function(e) {
                e.preventDefault();
                fileInput.click();
            });
        }
        
        // Open file picker when avatar image is clicked
        if (avatar) {
            avatar.style.cursor = 'pointer';
            avatar.addEventListener('click', function(e) {
                e.preventDefault();
                fileInput.click();
            });
        }
        
        // Preview image when file is selected
        function handleFilePreview(event) {
            const file = event.target.files[0];

            if (!file) {
                previewContainer.style.display = 'none';
                return;
            }

            // Validate file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size exceeds 5MB limit. Please choose a smaller image.');
                fileInput.value = '';
                previewContainer.style.display = 'none';
                return;
            }

            // Validate file type
            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!validTypes.includes(file.type)) {
                alert('Invalid file format. Please use JPG, PNG, or WEBP images.');
                fileInput.value = '';
                previewContainer.style.display = 'none';
                return;
            }

            // Show preview
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImageEl.src = e.target.result;
                previewContainer.style.display = 'block';
                uploadStatus.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
        
        // Cancel preview and clear file input
        if (cancelPreviewBtn) {
            cancelPreviewBtn.addEventListener('click', function() {
                fileInput.value = '';
                previewContainer.style.display = 'none';
                uploadStatus.style.display = 'none';
            });
        }

        // Attach file input change listener
        if (fileInput) {
            fileInput.addEventListener('change', handleFilePreview);
        }
        
        // Handle form submission for file uploads
        if (uploadForm) {
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Check if this is an upload_picture submission
                const submitBtn = e.submitter;
                if (!submitBtn || submitBtn.name !== 'upload_picture') return;
                
                // Check if file is selected
                if (!fileInput.files || !fileInput.files[0]) {
                    alert('Please select a photo to upload');
                    return;
                }
                
                const formData = new FormData();
                formData.append('profile_picture', fileInput.files[0]);
                formData.append('upload_picture', '1');
                
                // Show upload progress
                uploadProgress.style.display = 'block';
                progressBar.style.width = '0%';
                
                let progress = 0;
                const progressInterval = setInterval(() => {
                    if (progress < 90) {
                        progress += Math.random() * 30;
                        if (progress > 90) progress = 90;
                        progressBar.style.width = progress + '%';
                    }
                }, 100);
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    clearInterval(progressInterval);
                    
                    if (response.ok) {
                        return response.json();
                    } else {
                        throw new Error('Server error: ' + response.status);
                    }
                })
                .then(data => {
                    progressBar.style.width = '100%';
                    
                    if (data.success) {
                        setTimeout(() => {
                            location.reload();
                        }, 800);
                    } else {
                        clearInterval(progressInterval);
                        uploadProgress.style.display = 'none';
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-check"></i> Save Picture';
                        alert('Error: ' + (data.message || 'Upload failed'));
                        fileInput.value = '';
                        previewContainer.style.display = 'none';
                    }
                })
                .catch(error => {
                    clearInterval(progressInterval);
                    uploadProgress.style.display = 'none';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> Save Picture';
                    alert('Error uploading photo: ' + error.message);
                    console.error('Upload error:', error);
                });
            });
        }
        
        // ===== CAMERA CAPTURE FUNCTIONS =====
        
        // Open camera modal
        if (takePhotoBtn) {
            takePhotoBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openCameraModal();
            });
        }
        
        async function openCameraModal() {
            cameraModal.style.display = 'flex';
            permissionMessage.style.display = 'block';
            cameraError.style.display = 'none';
            capturePhotoBtn.style.display = 'inline-block';
            retakePhotoBtn.style.display = 'none';
            usePhotoBtn.style.display = 'none';
            capturedImagePreview.style.display = 'none';
            cameraPlaceholder.style.display = 'flex';
            cameraFeed.style.display = 'none';
            
            try {
                // Request camera access
                const constraints = {
                    video: {
                        facingMode: 'user', // Front camera on mobile
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                };
                
                cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
                cameraFeed.srcObject = cameraStream;
                
                // Wait for video to load
                cameraFeed.onloadedmetadata = function() {
                    cameraFeed.play();
                    cameraPlaceholder.style.display = 'none';
                    cameraFeed.style.display = 'block';
                    permissionMessage.style.display = 'none';
                };
                
            } catch (error) {
                handleCameraError(error);
            }
        }
        
        function handleCameraError(error) {
            cameraPlaceholder.style.display = 'flex';
            cameraFeed.style.display = 'none';
            cameraError.style.display = 'block';
            permissionMessage.style.display = 'none';
            
            if (error.name === 'NotAllowedError') {
                cameraErrorText.textContent = 'Camera access was denied. Please enable camera permissions in your browser settings.';
            } else if (error.name === 'NotFoundError') {
                cameraErrorText.textContent = 'No camera found on this device. Please use a device with a camera.';
            } else if (error.name === 'NotSupportedError') {
                cameraErrorText.textContent = 'Your browser does not support camera access. Please use a modern browser like Chrome, Firefox, Safari, or Edge.';
            } else {
                cameraErrorText.textContent = 'Error accessing camera: ' + error.message;
            }
        }
        
        // Capture photo from camera
        if (capturePhotoBtn) {
            capturePhotoBtn.addEventListener('click', function() {
                if (!cameraStream || !cameraFeed.srcObject) {
                    alert('Camera is not active. Please try again.');
                    return;
                }
                
                const context = captureCanvas.getContext('2d');
                captureCanvas.width = cameraFeed.videoWidth;
                captureCanvas.height = cameraFeed.videoHeight;
                
                // Draw video frame to canvas
                context.drawImage(cameraFeed, 0, 0, captureCanvas.width, captureCanvas.height);
                
                // Convert canvas to blob and show preview
                captureCanvas.toBlob(function(blob) {
                    capturedBlob = blob;
                    
                    // Create preview URL
                    const previewUrl = URL.createObjectURL(blob);
                    capturedImagePreview.src = previewUrl;
                    
                    // Show preview, hide feed
                    cameraFeed.style.display = 'none';
                    capturedImagePreview.style.display = 'block';
                    
                    // Update buttons
                    capturePhotoBtn.style.display = 'none';
                    retakePhotoBtn.style.display = 'inline-block';
                    usePhotoBtn.style.display = 'inline-block';
                }, 'image/jpeg', 0.95);
            });
        }
        
        // Retake photo
        if (retakePhotoBtn) {
            retakePhotoBtn.addEventListener('click', function() {
                capturedImagePreview.style.display = 'none';
                cameraFeed.style.display = 'block';
                capturePhotoBtn.style.display = 'inline-block';
                retakePhotoBtn.style.display = 'none';
                usePhotoBtn.style.display = 'none';
                
                // Clear blob
                if (capturedBlob) {
                    URL.revokeObjectURL(capturedImagePreview.src);
                    capturedBlob = null;
                }
            });
        }
        
        // Upload captured photo
        if (usePhotoBtn) {
            usePhotoBtn.addEventListener('click', async function() {
                if (!capturedBlob) {
                    alert('No photo captured. Please take a photo first.');
                    return;
                }

                uploadCapturedPhoto();
            });
        }
        
        async function uploadCapturedPhoto() {
            if (!capturedBlob) return;
            
            // Show upload progress
            const cameraUploadProgress = document.getElementById('cameraUploadProgress');
            const cameraProgressBar = document.getElementById('cameraProgressBar');
            cameraUploadProgress.style.display = 'block';
            cameraProgressBar.style.width = '0%';
            
            try {
                const formData = new FormData();
                formData.append('profile_picture', capturedBlob, 'camera_capture.jpg');
                formData.append('upload_picture', '1');
                
                // Simulate progress
                let progress = 0;
                const progressInterval = setInterval(() => {
                    if (progress < 90) {
                        progress += Math.random() * 30;
                        if (progress > 90) progress = 90;
                        cameraProgressBar.style.width = progress + '%';
                    }
                }, 100);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                clearInterval(progressInterval);
                cameraProgressBar.style.width = '100%';
                
                if (response.ok) {
                    const data = await response.json();
                    
                    if (data.success) {
                        // Close modal after short delay
                        setTimeout(() => {
                            closeCameraModal();
                            // Reload page to show updated avatar
                            location.reload();
                        }, 800);
                    } else {
                        clearInterval(progressInterval);
                        cameraUploadProgress.style.display = 'none';
                        alert('Error: ' + (data.message || 'Upload failed'));
                    }
                } else {
                    clearInterval(progressInterval);
                    cameraUploadProgress.style.display = 'none';
                    alert('Server error. Status: ' + response.status);
                }
                
            } catch (error) {
                cameraUploadProgress.style.display = 'none';
                alert('Error uploading photo: ' + error.message);
                console.error('Upload error:', error);
            }
            
            // Clean up blob
            capturedBlob = null;
        }
        
        // Close camera modal
        function closeCameraModal() {
            cameraModal.style.display = 'none';
            
            // Stop camera stream
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
            
            // Reset preview
            capturedImagePreview.style.display = 'none';
            cameraFeed.style.display = 'none';
            cameraPlaceholder.style.display = 'flex';
            capturePhotoBtn.style.display = 'inline-block';
            retakePhotoBtn.style.display = 'none';
            usePhotoBtn.style.display = 'none';
            cameraError.style.display = 'none';
            uploadProgress.style.display = 'none';
        }
        
        // Close buttons
        if (closeCameraBtn) {
            closeCameraBtn.addEventListener('click', closeCameraModal);
        }
        if (closeCameraBtn2) {
            closeCameraBtn2.addEventListener('click', closeCameraModal);
        }
        
        // Close modal when clicking outside
        if (cameraModal) cameraModal.addEventListener('click', function(e) {
            if (e.target === cameraModal) {
                closeCameraModal();
            }
        });
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
            }
        });
    </script>
</body>
</html>

