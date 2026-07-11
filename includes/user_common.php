<?php
function ensure_logged_in_user(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: minor.php');
        exit;
    }
}

function get_current_user_context($conn): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : '';
    $is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

    $user = null;
    $unread_count = 0;
    $last_notification_at = null;

    if ($user_id > 0) {
        $user_sql = 'SELECT * FROM users WHERE id = ?';
        $user_stmt = mysqli_prepare($conn, $user_sql);
        if ($user_stmt) {
            mysqli_stmt_bind_param($user_stmt, 'i', $user_id);
            mysqli_stmt_execute($user_stmt);
            $user_result = mysqli_stmt_get_result($user_stmt);
            $user = mysqli_fetch_assoc($user_result);
            mysqli_stmt_close($user_stmt);
        }

        if (function_exists('get_unread_notifications_count')) {
            $unread_count = get_unread_notifications_count($conn, $user_id);
        }

        $sql_last = 'SELECT MAX(created_at) as last_notif FROM notifications WHERE user_id = ?';
        $stmt_last = mysqli_prepare($conn, $sql_last);
        if ($stmt_last) {
            mysqli_stmt_bind_param($stmt_last, 'i', $user_id);
            mysqli_stmt_execute($stmt_last);
            $res_last = mysqli_stmt_get_result($stmt_last);
            $row_last = mysqli_fetch_assoc($res_last);
            $last_notification_at = $row_last['last_notif'] ?? null;
            mysqli_stmt_close($stmt_last);
        }
    }

    return [
        'user_id' => $user_id,
        'username' => $username,
        'is_admin' => $is_admin,
        'user' => $user,
        'unread_count' => $unread_count,
        'last_notification_at' => $last_notification_at,
    ];
}

function is_active_nav(string $current_path, string $target): string
{
    return strpos(basename($current_path), $target) !== false ? 'active' : '';
}
