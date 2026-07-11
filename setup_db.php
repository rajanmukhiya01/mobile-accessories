<?php
/**
 * Database Setup & Migration Script
 * Run this once to set up or update your database
 */

require_once "config.php";

$success = false;
$message = "";
$status = [];

// 1. Check if role column exists
$check_role = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'role'");
if (mysqli_num_rows($check_role) == 0) {
    $status[] = "Adding 'role' column to users table...";
    $alter_sql = "ALTER TABLE users ADD COLUMN role ENUM('admin', 'user') DEFAULT 'user' AFTER password";
    if (mysqli_query($conn, $alter_sql)) {
        $status[] = "✓ 'role' column added successfully";
    } else {
        $status[] = "✗ Error adding 'role' column: " . mysqli_error($conn);
    }
} else {
    $status[] = "✓ 'role' column already exists";
}

// 2. Check and create admin user
$admin_check = mysqli_query($conn, "SELECT id FROM users WHERE username = 'admin'");
if (mysqli_num_rows($admin_check) == 0) {
    $status[] = "Creating admin user...";
    $admin_sql = "INSERT INTO users (username, email, phone, dob, password, name, role) 
                  VALUES ('admin', 'admin@example.com', '9876543210', '2000-01-01', 'admin123', 'Admin User', 'admin')";
    if (mysqli_query($conn, $admin_sql)) {
        $status[] = "✓ Admin user created (Username: admin, Password: admin123)";
    } else {
        $status[] = "✗ Error creating admin user: " . mysqli_error($conn);
    }
} else {
    $status[] = "✓ Admin user already exists";
    // Update admin role if needed
    $update_admin = mysqli_query($conn, "UPDATE users SET role = 'admin' WHERE username = 'admin'");
    $status[] = "✓ Admin role confirmed";
}

// 3. Check and create test user
$user_check = mysqli_query($conn, "SELECT id FROM users WHERE username = 'testuser'");
if (mysqli_num_rows($user_check) == 0) {
    $status[] = "Creating test user...";
    $user_sql = "INSERT INTO users (username, email, phone, dob, password, name, role) 
                 VALUES ('testuser', 'user@example.com', '1234567890', '2001-05-15', 'user123', 'Test User', 'user')";
    if (mysqli_query($conn, $user_sql)) {
        $status[] = "✓ Test user created (Username: testuser, Password: user123)";
    } else {
        $status[] = "✗ Error creating test user: " . mysqli_error($conn);
    }
} else {
    $status[] = "✓ Test user already exists";
    // Update test user role if needed
    $update_user = mysqli_query($conn, "UPDATE users SET role = 'user' WHERE username = 'testuser'");
    $status[] = "✓ Test user role confirmed";
}

// 4. Verify database structure
$status[] = "\n=== Database Verification ===";
$users_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
$users_row = mysqli_fetch_assoc($users_result);
$status[] = "Total users in database: " . $users_row['count'];

$products_result = mysqli_query($conn, "SELECT COUNT(*) as count FROM product");
$products_row = mysqli_fetch_assoc($products_result);
$status[] = "Total products in database: " . $products_row['count'];

$success = true;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Setup</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        
        .setup-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .setup-header h2 {
            color: #333;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .setup-header i {
            font-size: 50px;
            color: #667eea;
            margin-bottom: 15px;
        }
        
        .status-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.8;
            color: #333;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .status-list div {
            margin-bottom: 8px;
        }
        
        .btn-continue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: transform 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-continue:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .credentials-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .credentials-box h5 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .credential-item {
            background: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        
        .credential-item strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <i class="fas fa-database"></i>
            <h2>Database Setup</h2>
            <p>Initializing database structure and demo data</p>
        </div>
        
        <div class="status-list">
            <?php foreach ($status as $msg): ?>
                <div><?php echo htmlspecialchars($msg); ?></div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> Database setup completed successfully!
            </div>
            
            <div class="credentials-box">
                <h5><i class="fas fa-key"></i> Test Credentials</h5>
                
                <div style="margin-bottom: 15px;">
                    <p style="font-weight: 600; color: #333; margin-bottom: 8px;">Admin Account:</p>
                    <div class="credential-item">
                        <strong>Username:</strong> admin<br>
                        <strong>Password:</strong> admin123
                    </div>
                </div>
                
                <div>
                    <p style="font-weight: 600; color: #333; margin-bottom: 8px;">User Account:</p>
                    <div class="credential-item">
                        <strong>Username:</strong> testuser<br>
                        <strong>Password:</strong> user123
                    </div>
                </div>
            </div>
            
            <a href="minor.php" class="btn-continue" style="margin-top: 25px;">
                <i class="fas fa-sign-in-alt"></i> Go to Login Page
            </a>
        <?php else: ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i> Some issues occurred during setup. Please check the status above.
            </div>
            <a href="setup_db.php" class="btn-continue">
                <i class="fas fa-redo"></i> Retry Setup
            </a>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
