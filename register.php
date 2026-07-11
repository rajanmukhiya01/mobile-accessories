<?php

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}

// Logout any existing session to show clean registration page
session_destroy();

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}

require_once "config.php";

$username = $email = $phone = $dob = $password = $confirm_password = "";
$username_err = $email_err = $phone_err = $dob_err = $password_err = $confirm_password_err = "";
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $dob = trim($_POST['dob']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate Username
    if (empty($username)) {
        $username_err = "Username cannot be blank";
    } else {
        $sql = "SELECT id FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) == 1) {
                $username_err = "This username is already taken";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Validate Email
    if (empty($email)) {
        $email_err = "Email cannot be blank";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Invalid email format";
    } else {
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) == 1) {
                $email_err = "This email is already registered";
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Validate Phone
    if (empty($phone)) {
        $phone_err = "Phone number cannot be blank";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $phone_err = "Phone number must be 10 digits";
    }

    // Validate DOB
    if (empty($dob)) {
        $dob_err = "Date of Birth cannot be blank";
    } else {
        $age = date_diff(date_create($dob), date_create('today'))->y;
        if ($age < 18) {
            $dob_err = "You must be 18 years old or above";
        }
    }

    // Validate Password
    if (empty($password)) {
        $password_err = "Password cannot be blank";
    } elseif (strlen($password) < 6) {
        $password_err = "Password must be at least 6 characters";
    }

    // Confirm Password
    if (empty($confirm_password)) {
        $confirm_password_err = "Please confirm your password";
    } elseif ($password !== $confirm_password) {
        $confirm_password_err = "Passwords do not match";
    }

    // If no errors, insert user
    if (empty($username_err) && empty($email_err) && empty($phone_err) && empty($dob_err) && empty($password_err) && empty($confirm_password_err)) {
        $sql = "INSERT INTO users (username, email, phone, dob, password) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sssss", $username, $email, $phone, $dob, $password);
            
            if (mysqli_stmt_execute($stmt)) {
                $new_user_id = mysqli_insert_id($conn);
                notify_admins($conn, 0, 'new_user', 'New User Registered', 'A new user account was created.', 'admin_dashboard.php');
                $success_msg = "Registration successful! Redirecting to login...";
                header("refresh:2;url=minor.php");
            } else {
                echo "Error: " . $conn->error;
            }
            mysqli_stmt_close($stmt);
        }
    }
}
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - Mobile Accessories</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/BAZARIO_STYLES.css?v=2">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="register-header">
            <h2>📱 Mobile Accessories</h2>
            <p>Create your account</p>
        </div>
        
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>
        
        <form action="" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                <span class="text-danger"><?php echo $username_err; ?></span>
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                <span class="text-danger"><?php echo $email_err; ?></span>
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" class="form-control" name="phone" placeholder="10-digit number" value="<?php echo htmlspecialchars($phone); ?>" required>
                <span class="text-danger"><?php echo $phone_err; ?></span>
            </div>
            
            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date" class="form-control" name="dob" value="<?php echo htmlspecialchars($dob); ?>" required>
                <span class="text-danger"><?php echo $dob_err; ?></span>
            </div>
            
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Password</label>
                    <input type="password" class="form-control" name="password" required>
                    <span class="text-danger"><?php echo $password_err; ?></span>
                </div>
                <div class="form-group col-md-6">
                    <label>Confirm Password</label>
                    <input type="password" class="form-control" name="confirm_password" required>
                    <span class="text-danger"><?php echo $confirm_password_err; ?></span>
                </div>
            </div>
            
            <button type="submit" class="btn btn-login">Register</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="minor.php">Login here</a>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
