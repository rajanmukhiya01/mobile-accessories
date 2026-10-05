<?php
function render_user_navbar($user, $unread_count = 0, $class = 'navbar', $brand_text = 'BAZARIO', $show_menu = false, $active_page = 'dashboard', $show_profile_link = true): string
{
    $badge = $unread_count > 0 ? '<span style="position: absolute; top: -6px; right: -10px; background: #e74c3c; color: white; font-size: 11px; padding: 2px 6px; border-radius: 12px;">' . (int) $unread_count . '</span>' : '';
    $avatar = function_exists('get_user_avatar_html') ? get_user_avatar_html($user, 'sm') : '';
    $nav_class = $class === 'navbar-top' ? 'navbar' : $class;
    $profile_link = $show_profile_link ? '<a href="profile.php" style="color: white; text-decoration: none; display: flex; align-items: center;">' . $avatar . '</a>' : '';

    return '<div class="' . htmlspecialchars($nav_class) . '" role="banner">'
        . '<div style="display: flex; align-items: center; gap: 15px; width: 100%;">'
        . '<i class="fas fa-shopping-bag" style="font-size: 28px;"></i>'
        . '<span class="navbar-brand" style="margin: 0;">' . htmlspecialchars($brand_text) . '</span>'
        . '<span style="opacity: 0.9; font-size: 12px; margin-left: 12px;">Online Shopping Store</span>'
        . '<div style="margin-left: auto; display: flex; align-items: center; gap: 12px;">'
        . '<div style="position: relative;">'
        . '<a href="notifications.php" style="color: white; text-decoration: none; position: relative;">'
        . '<i class="fas fa-bell" style="font-size: 18px;"></i>' . $badge
        . '</a>'
        . '</div>'
        . $profile_link
        . '</div>'
        . '</div>'
        . '</div>';
}

function render_user_sidebar($active_page = 'dashboard'): string
{
    $items = [
        ['dashboard', 'user_dashboard.php', 'fas fa-home', 'Dashboard'],
        ['shop', 'user_dashboard.php', 'fas fa-store', 'Shop'],
        ['announcements', 'announcements.php', 'fas fa-bullhorn', 'Announcements'],
        ['orders', 'orders_new.php', 'fas fa-shopping-bag', 'My Orders'],
        ['notifications', 'notifications.php', 'fas fa-bell', 'Notifications'],
        ['profile', 'profile.php', 'fas fa-user', 'Profile'],
    ];

    $html = '<div class="sidebar">';
    foreach ($items as $item) {
        [$id, $href, $icon, $label] = $item;
        $active = $active_page === $id ? 'active' : '';
        $html .= '<a href="' . $href . '" class="' . $active . '"><i class="' . $icon . '"></i> ' . $label . '</a>';
    }

    $html .= '<form action="logout.php" method="POST" style="margin: 0; padding: 0;">'
        . '<button type="submit" class="sidebar-logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>'
        . '</form></div>';

    return $html;
}
