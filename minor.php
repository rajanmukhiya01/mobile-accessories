<?php

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}

// Check if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: dashboard.php");
    exit;
}

require_once "config.php";

$username = $password = "";
$err = "";
$login_type = isset($_POST['login_type']) ? $_POST['login_type'] : 'user'; // default to user login

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $username = htmlspecialchars(trim($_POST['username']));
    $password = htmlspecialchars(trim($_POST['password']));
    $login_type = isset($_POST['login_type']) ? $_POST['login_type'] : 'user';

    if (empty($username) || empty($password)) {
        $err = "Please enter username and password";
    } else {
        // Simple query - just get the user
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                
                // Check password
                if ($password === $user['password']) {
                    // Set default role if not set
                    $user_role = isset($user['role']) && !empty($user['role']) ? $user['role'] : 'user';
                    
                    // Set session
                    $_SESSION["username"] = $user['username'];
                    $_SESSION["user_id"] = $user['id'];
                    $_SESSION["role"] = $user_role;
                    $_SESSION["loggedin"] = true;
                    
                    // Redirect based on role (ignore login_type tab selection)
                    if ($user_role === 'admin') {
                        header("Location: admin_dashboard.php");
                        exit;
                    } else {
                        header("Location: user_dashboard.php");
                        exit;
                    }
                } else {
                    $err = "Incorrect password. Please try again.";
                }
            } else {
                $err = "No account found with that username.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $err = "Database error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Login - Bazario Mobile Accessories</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/BAZARIO_STYLES.css?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-background"></div>
    
    <div class="login-wrapper">
        <div class="login-container">
            <!-- Header Section with Logo and App Name -->
            <div class="login-header">
                <div class="logo-container">
                    <div class="logo-circle">
                        <i class="fas fa-shopping-bag login-icon"></i>
                    </div>
                </div>
                <h1 class="brand-title">BAZARIO</h1>
                <p class="brand-subtitle">Mobile Accessories Management System</p>
                <div class="header-divider"></div>
            </div>
        
            <!-- Error Message -->
            <?php if (!empty($err)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <div class="alert-content">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Error!</strong> <?php echo $err; ?>
                    </div>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Toggle Buttons for Account Type -->
            <div class="login-toggle-section">
                <button id="user-toggle" class="btn btn-toggle-account active" onclick="switchBox('user');">
                    <i class="fas fa-user"></i>
                    <span class="toggle-text">User Account</span>
                </button>
                <button id="admin-toggle" class="btn btn-toggle-account" onclick="switchBox('admin');">
                    <i class="fas fa-user-shield"></i>
                    <span class="toggle-text">Admin Account</span>
                </button>
            </div>

            <!-- User Login Box -->
            <div class="account-box user-box active-box" id="user-box">
                <div class="box-header">
                    <div class="box-logo user-logo">
                        <i class="fas fa-user"></i>
                    </div>
                    <h3 class="box-title">User Login</h3>
                    <p class="box-description">Sign in to your account to access orders, profile and more</p>
                </div>
                <form action="" method="post" class="login-form">
                    <input type="hidden" name="login_type" value="user">
                    
                    <div class="form-group">
                        <label for="username-user" class="form-label">
                            <i class="fas fa-user-circle"></i> Username
                        </label>
                        <input type="text" class="form-control form-control-lg" name="username" id="username-user" 
                               placeholder="Enter your username" required>
                    </div>

                    <div class="form-group">
                        <label for="password-user" class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" class="form-control form-control-lg" name="password" id="password-user" 
                               placeholder="Enter your password" required>
                    </div>

                    <button type="submit" class="btn btn-login-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login as User
                    </button>
                </form>
                
                <div class="form-footer">
                    <p>Don't have an account? <a href="register.php" class="register-link">Register here</a></p>
                </div>
            </div>

            <!-- Admin Login Box -->
            <div class="account-box admin-box" id="admin-box">
                <div class="box-header">
                    <div class="box-logo admin-logo">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3 class="box-title">Admin Login</h3>
                    <p class="box-description">Enter admin credentials to manage the store</p>
                </div>
                <form action="" method="post" class="login-form">
                    <input type="hidden" name="login_type" value="admin">
                    
                    <div class="form-group">
                        <label for="username-admin" class="form-label">
                            <i class="fas fa-user-circle"></i> Username
                        </label>
                        <input type="text" class="form-control form-control-lg" name="username" id="username-admin" 
                               placeholder="Enter admin username" required>
                    </div>

                    <div class="form-group">
                        <label for="password-admin" class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" class="form-control form-control-lg" name="password" id="password-admin" 
                               placeholder="Enter admin password" required>
                    </div>

                    <button type="submit" class="btn btn-login-admin btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login as Admin
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeLogin();
        });

        function initializeLogin() {
            // Default: show user box
            showBox('user');
        }

        function switchBox(type) {
            showBox(type);
        }

        function showBox(type) {
            const userBox = document.getElementById('user-box');
            const adminBox = document.getElementById('admin-box');
            const userToggle = document.getElementById('user-toggle');
            const adminToggle = document.getElementById('admin-toggle');

            // Hide both boxes and remove active class
            userBox.classList.remove('active-box');
            adminBox.classList.remove('active-box');
            userToggle.classList.remove('active');
            adminToggle.classList.remove('active');

            if (type === 'user') {
                userBox.classList.add('active-box');
                userToggle.classList.add('active');
                document.getElementById('username-user').focus();
            } else {
                adminBox.classList.add('active-box');
                adminToggle.classList.add('active');
                document.getElementById('username-admin').focus();
            }
        }

        // Add smooth focus effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.closest('.form-group').classList.add('focused');
            });
            input.addEventListener('blur', function() {
                this.closest('.form-group').classList.remove('focused');
            });
        });
    </script>
</body>
<?php mysqli_close($conn); ?>
</html>
