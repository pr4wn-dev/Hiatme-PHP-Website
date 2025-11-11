<?php
// includes/menu_config.php
if (!defined('IN_Hiatme')) {
    define('IN_Hiatme', true);
}

function getMenuItems($hiatme_methods) {
    $user = null;
    $token = $_COOKIE['auth_token'] ?? '';
    if ($token) {
        $user = $hiatme_methods->ValidateToken($token);
    }

    $menuItems = [
        [
            'label' => 'Home',
            'url' => 'index.php',
            'visible' => true
        ],
        [
            'label' => 'Profile',
            'url' => 'profile.php',
            'visible' => $user !== false,
            'id' => 'profile-btn'
        ],
        [
            'label' => 'Manage Users',
            'url' => 'manage_users.php',
            'visible' => $user && in_array($user['role'], ['Manager', 'Owner']),
            'id' => 'manage-users-btn'
        ],
        [
            'label' => 'Manage Vehicles',
            'url' => 'manage_vehicles.php',
            'visible' => $user && in_array($user['role'], ['Manager', 'Owner']),
            'id' => 'manage-vehicles-btn'
        ],
        [
            'label' => $user ? 'Logout' : 'Login',
            'url' => '#',
            'visible' => true,
            'id' => 'login-btn'
        ]
    ];

    error_log("Menu items generated: " . json_encode($menuItems));
    return $menuItems;
}

function renderMenu($hiatme_methods) {
    $menuItems = getMenuItems($hiatme_methods);
    $user = null;
    $token = $_COOKIE['auth_token'] ?? '';
    if ($token) {
        $user = $hiatme_methods->ValidateToken($token);
    }

    ob_start();
    ?>
    <div class="menu-container">
        <button class="menu-btn"></button>
        <div class="dropdown-menu">
            <div class="avatar-box">
                <div class="avatar-content">
                    <img src="<?php echo htmlspecialchars($user && isset($user['profile_picture']) && $user['profile_picture'] ? $user['profile_picture'] : 'images/avatar.png'); ?>" 
                         alt="Avatar" class="avatar-img">
                    <span class="avatar-label">
                        <?php echo htmlspecialchars($user && isset($user['name']) && isset($user['email']) ? $user['name'] . ' (' . $user['email'] . ')' : 'Guest@localhost'); ?>
                    </span>
                </div>
            </div>
            <?php foreach ($menuItems as $item): ?>
                <?php if ($item['visible']): ?>
                    <a href="<?php echo htmlspecialchars($item['url']); ?>" 
                       <?php echo isset($item['id']) ? 'id="' . htmlspecialchars($item['id']) . '"' : ''; ?>>
                        <?php echo htmlspecialchars($item['label']); ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    $output = ob_get_clean();
    error_log("Menu rendered: " . substr($output, 0, 100) . "...");
    return $output;
}
?>