<?php
/**
 * Admin Authentication Middleware
 * Ensures only admin users can access admin pages
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}

require_once __DIR__ . '/config.php';

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../minor.php");
    exit;
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
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
            <h1>❌ Access Denied</h1>
            <p>Only administrators can access this page.</p>
            <a href='../minor.php' class='btn btn-primary mt-3'>Go to Login</a>
        </div>
    </body>
    </html>
    ");
    exit;
}
?>

