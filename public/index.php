<?php
/**
 * BAZARIO - Main Application Entry Point
 * Redirects to appropriate page based on user session
 */

if (session_status() === PHP_SESSION_NONE) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
}

// Check if user is already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    // Redirect to appropriate dashboard based on role
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../pages/dashboard.php");
    }
    exit;
}

// Not logged in, redirect to login page
header("Location: ../pages/login.php");
exit;
?>

