<?php
function render_user_navbar($user, $unread_count = 0, $class = 'navbar-top', $brand_text = 'Mobile Accessories', $show_menu = false, $active_page = 'dashboard'): string
{
    $badge = $unread_count > 0 ? '<span class="notification-badge">' . (int) $unread_count . '</span>' : '';
    $avatar = function_exists('get_user_avatar_html') ? get_user_avatar_html($user, 'sm') : '';

    $menu_html = '';
    if ($show_menu) {
        $menu_items = [
            ['dashboard', 'user_dashboard.php', 'Dashboard'],
            ['shop', 'user_dashboard.php', 'Shop'],
            ['orders', 'orders_new.php', 'My Orders'],
        ];

        foreach ($menu_items as $item) {
            [$key, $href, $label] = $item;
            $active = $active_page === $key ? ' active' : '';
            $menu_html .= '<li><a href="' . htmlspecialchars($href) . '" class="' . $active . '">' . htmlspecialchars($label) . '</a></li>';
        }
    }

    return '<div class="' . htmlspecialchars($class) . '" role="banner">'
        . '<div class="navbar-brand-text"><i class="fas fa-shopping-bag"></i> ' . htmlspecialchars($brand_text) . '</div>'
        . ($show_menu ? '<ul class="nav-menu">' . $menu_html . '</ul>' : '')
        . '<div class="navbar-icons">'
        . '<a href="notifications.php" title="Notifications"><i class="fas fa-bell"></i>' . $badge . '</a>'
        . '<a href="profile.php" title="Profile">' . $avatar . '</a>'
        . '</div></div>';
}

function render_user_sidebar($active_page = 'dashboard'): string
{
    $items = [
        ['dashboard', 'user_dashboard.php', 'fas fa-home', 'Dashboard'],
        ['shop', 'user_dashboard.php', 'fas fa-store', 'Shop'],
        ['orders', 'orders_new.php', 'fas fa-shopping-bag', 'My Orders'],
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
