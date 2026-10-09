<?php
$adminBasePath = $adminBasePath ?? '';
$adminHeadingUser = $admin_user ?? ($admin ?? ['username' => ($_SESSION['username'] ?? 'Admin')]);
$adminHeadingAvatar = function_exists('get_user_avatar_html')
    ? get_user_avatar_html($adminHeadingUser, 'sm')
    : '<i class="fas fa-user-circle"></i>';
?>
<style>
    .admin-heading-bar {
        background: linear-gradient(135deg, #001a33 0%, #003366 100%);
        color: #fff;
        padding: 15px 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .admin-heading-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        font-size: 24px;
        font-weight: 700;
    }

    .admin-heading-context {
        font-size: 12px;
        font-weight: 400;
        opacity: 0.8;
    }

    .admin-heading-links {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .admin-heading-links a,
    .admin-heading-links button {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        color: #fff;
        font: inherit;
        font-size: 14px;
        text-decoration: none;
        transition: opacity 0.3s;
    }

    .admin-heading-links a:hover,
    .admin-heading-links button:hover {
        color: #fff;
        opacity: 0.8;
        text-decoration: none;
    }

    .admin-heading-links form {
        margin: 0;
    }

    .admin-heading-links button {
        padding: 0;
        border: 0;
        background: none;
        cursor: pointer;
    }

    .admin-heading-links .avatar-sm {
        width: 32px;
        height: 32px;
        border: 2px solid #e0e0e0;
        border-radius: 50%;
        object-fit: cover;
    }

    .admin-heading-links .avatar-default {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
    }

    @media (max-width: 640px) {
        .admin-heading-bar {
            align-items: flex-start;
            flex-direction: column;
            gap: 14px;
            padding: 15px 20px;
        }

        .admin-heading-brand {
            flex-wrap: wrap;
            font-size: 22px;
        }

        .admin-heading-links {
            width: 100%;
            flex-wrap: wrap;
            gap: 14px;
        }
    }
</style>
<header class="admin-heading-bar">
    <div class="admin-heading-brand">
        <i class="fas fa-shopping-bag"></i>
        <span>BAZARIO</span>
        <span class="admin-heading-context">Admin Dashboard</span>
    </div>
    <nav class="admin-heading-links" aria-label="Admin navigation">
        <a href="<?php echo htmlspecialchars($adminBasePath . 'admin_dashboard.php', ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-home"></i> Dashboard</a>
        <a href="<?php echo htmlspecialchars($adminBasePath . 'admin_profile.php', ENT_QUOTES, 'UTF-8'); ?>"><?php echo $adminHeadingAvatar; ?> Profile</a>
        <form action="<?php echo htmlspecialchars($adminBasePath . 'auth/logout.php', ENT_QUOTES, 'UTF-8'); ?>" method="post">
            <button type="submit"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </form>
    </nav>
</header>