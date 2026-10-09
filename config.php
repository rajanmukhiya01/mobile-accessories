<?php
/**
 * Database Configuration File
 * Contains database connection settings and helper functions
 */

// Load local secrets from ignored .env; process/server environment takes precedence.
$localEnvFile = __DIR__ . DIRECTORY_SEPARATOR . '.env';
if (is_file($localEnvFile)) {
    $localEnv = @parse_ini_file($localEnvFile, false, INI_SCANNER_RAW);
    if (is_array($localEnv)) {
        foreach ($localEnv as $envName => $envValue) {
            if (getenv($envName) === false && is_string($envValue)) {
                putenv($envName . '=' . $envValue);
                $_ENV[$envName] = $envValue;
            }
        }
    }
}

define('DB_SERVER', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_USERNAME', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'Mproject');

if (!defined('SMTP_HOST')) define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
if (!defined('SMTP_PORT')) define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', getenv('SMTP_USER') ?: '');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', getenv('SMTP_PASS') ?: '');
if (!defined('SMTP_SECURE')) define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');
if (!defined('MAIL_FROM')) define('MAIL_FROM', getenv('MAIL_FROM') ?: SMTP_USERNAME);
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Bazario');
// Create database connection (use TCP host and explicit port)
$conn = @mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);

// Check connection and provide clear troubleshooting hints
if ($conn === false) {
    $err = mysqli_connect_error();
    die("ERROR: Could not connect to database. " . $err . "\nHint: Start MySQL (XAMPP Control Panel) and ensure host=127.0.0.1 port=3306 and credentials in config.php are correct.");
}

// Set charset to utf8mb4 for better security and emoji support
mysqli_set_charset($conn, "utf8mb4");

function ensure_announcements_table($conn) {
    $table_sql = "CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        image VARCHAR(255) NULL,
        announcement_type VARCHAR(50) NOT NULL DEFAULT 'General',
        priority VARCHAR(20) NOT NULL DEFAULT 'Normal',
        status VARCHAR(20) NOT NULL DEFAULT 'published',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        start_at DATETIME NULL,
        expires_at DATETIME NULL,
        published_at DATETIME NULL,
        INDEX idx_status (status),
        INDEX idx_type (announcement_type),
        INDEX idx_priority (priority),
        INDEX idx_dates (start_at, expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if (!mysqli_query($conn, $table_sql)) {
        return false;
    }

    $columns = [
        ['image', "ALTER TABLE announcements ADD COLUMN image VARCHAR(255) NULL AFTER message"],
        ['announcement_type', "ALTER TABLE announcements ADD COLUMN announcement_type VARCHAR(50) NOT NULL DEFAULT 'General' AFTER image"],
        ['priority', "ALTER TABLE announcements ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'Normal' AFTER announcement_type"],
        ['status', "ALTER TABLE announcements ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'published' AFTER priority"],
        ['is_active', "ALTER TABLE announcements ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER status"],
        ['created_by', "ALTER TABLE announcements ADD COLUMN created_by INT NULL AFTER is_active"],
        ['start_at', "ALTER TABLE announcements ADD COLUMN start_at DATETIME NULL AFTER updated_at"],
        ['expires_at', "ALTER TABLE announcements ADD COLUMN expires_at DATETIME NULL AFTER start_at"],
        ['published_at', "ALTER TABLE announcements ADD COLUMN published_at DATETIME NULL AFTER expires_at"]
    ];

    foreach ($columns as $column) {
        [$name, $alter_sql] = $column;
        $check = mysqli_query($conn, "SHOW COLUMNS FROM announcements LIKE '" . mysqli_real_escape_string($conn, $name) . "'");
        if ($check && mysqli_num_rows($check) === 0) {
            mysqli_query($conn, $alter_sql);
        }
    }

    $check = mysqli_query($conn, "SELECT id FROM announcements LIMIT 1");
    if ($check && mysqli_num_rows($check) === 0) {
        mysqli_query($conn, "INSERT INTO announcements (title, message, announcement_type, priority, status, is_active, start_at, expires_at) VALUES
            ('Welcome to Bazario', 'Thank you for shopping with Bazario. Explore our latest mobile accessories and enjoy great offers.', 'General', 'Important', 'published', 1, NOW(), NULL),
            ('New Arrivals', 'Fresh accessories and premium mobile gear are now available in stock. Check them out today!', 'New Product', 'Normal', 'published', 1, NOW(), NULL)");
    }

    return true;
}

function ensure_order_status_history_table($conn) {
    $table_sql = "CREATE TABLE IF NOT EXISTS order_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        status VARCHAR(50) NOT NULL,
        changed_by INT NULL,
        note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_created_at (created_at),
        CONSTRAINT fk_order_status_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    return mysqli_query($conn, $table_sql) === true;
}

ensure_announcements_table($conn);

// OTP configuration
if (!defined('OTP_EXPIRY_MINUTES')) {
    define('OTP_EXPIRY_MINUTES', 30); // OTP validity in minutes
}
// Encryption key for storing reversible OTP (change to a strong random value in production)
if (!defined('OTP_ENC_KEY')) {
    // Prefer environment variable for secrets in production
    $envOtpKey = getenv('OTP_ENC_KEY');
    if ($envOtpKey !== false && !empty($envOtpKey)) {
        define('OTP_ENC_KEY', $envOtpKey);
    } else {
        define('OTP_ENC_KEY', 'please_change_this_to_a_secure_random_key');
    }
}
if (!function_exists('sanitize_input')) {
    /**
     * Helper function to sanitize input
     */
    function sanitize_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

/**
 * Helper function to log user activity
 */
function log_activity($conn, $user_id, $action, $description = '') {
    $sql = "INSERT INTO activity_log (user_id, action, description) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $action, $description);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Helper function to validate image file
 */
function validate_image($file) {
    $errors = [];
    
    // Check if file exists
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        $errors[] = "No file uploaded";
        return $errors;
    }
    
    // Check file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $max_size) {
        $errors[] = "File size must be less than 5MB";
    }
    
    // Check file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = "Only JPG, PNG, GIF, and WEBP files are allowed";
    }
    
    // Check if it's actually an image
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $errors[] = "File is not a valid image";
    }
    
    return $errors;
}

/**
 * Helper function to generate unique filename
 */
function generate_unique_filename($original_filename) {
    $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * CSRF token helpers
 */
function get_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Helper function to check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

/**
 * Helper function to redirect to login page
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: minor.php");
        exit;
    }
}

/**
 * Helper function to get user statistics
 */
function get_user_stats($conn, $user_id) {
    $stats = [
        'total_products' => 0,
        'total_value' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0
    ];
    
    // Total products
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM product");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['total_products'] = $row['count'];
    }
    
    // Total inventory value
    $result = mysqli_query($conn, "SELECT SUM(price * quantity) as total FROM product");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['total_value'] = $row['total'] ?? 0;
    }
    
    // Low stock items (quantity < 10)
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM product WHERE quantity > 0 AND quantity < 10");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['low_stock'] = $row['count'];
    }
    
    // Out of stock items
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM product WHERE quantity = 0");
    if ($result) {
        $row = mysqli_fetch_assoc($result);
        $stats['out_of_stock'] = $row['count'];
    }
    
    return $stats;
}

/**
 * Helper function to format currency
 */
function format_currency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Get the best available order timestamp.
 */
function get_order_datetime($order) {
    if (!empty($order['placed_at']) && $order['placed_at'] !== '0000-00-00 00:00:00') {
        return $order['placed_at'];
    }

    if (!empty($order['created_at']) && $order['created_at'] !== '0000-00-00 00:00:00') {
        return $order['created_at'];
    }

    return null;
}

/**
 * Format order date/time using placed_at if available, otherwise created_at.
 */
function format_order_datetime($order, $format = 'M d, Y \a\t h:i A') {
    $datetime = get_order_datetime($order);
    return $datetime ? date($format, strtotime($datetime)) : 'Date not available';
}

// ========================================
// PROFILE PICTURE MANAGEMENT FUNCTIONS
// ========================================

function ensure_profile_picture_column($conn) {
    $column_check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if (!$column_check) {
        return false;
    }

    if (mysqli_num_rows($column_check) > 0) {
        return true;
    }

    return mysqli_query($conn, 'ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL') === true;
}

/**
 * Get profile picture path for a user
 * Returns the image path if exists, or null if no profile picture
 */
function get_profile_picture_path($user) {
    if (empty($user['profile_picture'])) {
        return null;
    }

    $stored_path = str_replace('\\', '/', $user['profile_picture']);
    if (strpos($stored_path, '..') !== false || preg_match('/^[A-Za-z]:/', $stored_path)) {
        return null;
    }

    $filesystem_path = __DIR__ . '/' . ltrim($stored_path, '/');
    return is_file($filesystem_path) ? $stored_path : null;
}

/**
 * Validate profile picture file
 */
function validate_profile_picture($file) {
    $errors = [];

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $upload_error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE) {
            $errors[] = 'The selected image is too large. Please choose a smaller image.';
        } elseif ($upload_error === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'No file selected. Please choose a profile picture.';
        } else {
            $errors[] = 'Unable to receive the profile picture. Please try again.';
        }
        return $errors;
    }

    if (empty($file['tmp_name']) || !is_file($file['tmp_name']) || !is_readable($file['tmp_name'])) {
        $errors[] = 'Unable to read the uploaded image. Please try again.';
        return $errors;
    }

    $max_size = 5 * 1024 * 1024;
    $actual_size = filesize($file['tmp_name']);
    if ($actual_size === false || $actual_size > $max_size) {
        $errors[] = 'The selected image is too large. Please choose an image under 5MB.';
    }

    $allowed_extensions = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    if ($finfo) {
        finfo_close($finfo);
    }

    $image_info = @getimagesize($file['tmp_name']);
    if (!isset($allowed_extensions[$extension]) || $mime_type !== $allowed_extensions[$extension] || $image_info === false || ($image_info['mime'] ?? '') !== $mime_type) {
        $errors[] = 'Please upload a valid JPG, JPEG, PNG, or WEBP image.';
    }

    return $errors;
}


/**
 * Upload and save profile picture
 */
function upload_profile_picture($conn, $user_id, $file) {
    $validation_errors = validate_profile_picture($file);
    if (!empty($validation_errors)) {
        return ['success' => false, 'message' => implode(', ', $validation_errors)];
    }

    if (!ensure_profile_picture_column($conn)) {
        return ['success' => false, 'message' => 'Unable to prepare profile picture storage in the account database.'];
    }

    $user_id = (int) $user_id;
    $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        return ['success' => false, 'message' => 'Unable to prepare the profile picture upload folder.'];
    }

    if (!is_writable($upload_dir)) {
        return ['success' => false, 'message' => 'The profile picture folder is not writable by the server.'];
    }

    $user_stmt = mysqli_prepare($conn, 'SELECT profile_picture FROM users WHERE id = ?');
    if (!$user_stmt) {
        return ['success' => false, 'message' => 'Unable to load the account for this profile picture.'];
    }

    mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    $user_row = $user_result ? mysqli_fetch_assoc($user_result) : null;
    mysqli_stmt_close($user_stmt);
    if (!$user_row) {
        return ['success' => false, 'message' => 'The account for this profile picture was not found.'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'user_' . $user_id . '_profile_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $filesystem_path = $upload_dir . DIRECTORY_SEPARATOR . $filename;
    $stored_path = 'uploads/profiles/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $filesystem_path)) {
        return ['success' => false, 'message' => 'Unable to upload the profile picture. Please try again.'];
    }

    $update_stmt = mysqli_prepare($conn, 'UPDATE users SET profile_picture = ? WHERE id = ?');
    if (!$update_stmt) {
        @unlink($filesystem_path);
        return ['success' => false, 'message' => 'Unable to save the profile picture to your account.'];
    }

    mysqli_stmt_bind_param($update_stmt, 'si', $stored_path, $user_id);
    $updated = mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);
    if (!$updated) {
        @unlink($filesystem_path);
        return ['success' => false, 'message' => 'Unable to save the profile picture to your account.'];
    }

    $old_path = str_replace('\\', '/', (string) ($user_row['profile_picture'] ?? ''));
    if (preg_match('/^uploads\/profiles\/user_' . $user_id . '_profile_[a-f0-9]+\.(jpg|jpeg|png|webp)$/i', $old_path)) {
        $old_filesystem_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $old_path);
        if (is_file($old_filesystem_path)) {
            @unlink($old_filesystem_path);
        }
    }

    return ['success' => true, 'message' => 'Profile picture uploaded successfully!', 'path' => $stored_path];
}

/**
 * Get avatar HTML for a user (returns img tag or icon)
 */
function get_user_avatar_html($user, $size = 'md', $class = '') {
    $profile_pic = get_profile_picture_path($user);
    
    // Map sizes to CSS classes
    $size_classes = [
        'sm' => 'avatar-sm',
        'md' => 'avatar-md',
        'lg' => 'avatar-lg',
        'xl' => 'avatar-xl'
    ];
    
    $avatar_class = isset($size_classes[$size]) ? $size_classes[$size] : $size_classes['md'];
    if (!empty($class)) {
        $avatar_class .= ' ' . $class;
    }
    
    if ($profile_pic) {
        $safe_pic = htmlspecialchars($profile_pic);
        $safe_name = htmlspecialchars($user['name'] ?? $user['username'] ?? 'User');
        return "<img src=\"{$safe_pic}\" alt=\"{$safe_name}\" class=\"{$avatar_class}\" />";
    } else {
        return "<div class=\"{$avatar_class} avatar-default\"><i class=\"fas fa-user\"></i></div>";
    }
}
?>
