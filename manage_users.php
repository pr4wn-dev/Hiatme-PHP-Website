<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once 'includes/hiatme_methods.php';
require_once 'includes/menu_config.php';
$hiatme_methods = new HiatmeMethods();
$hiatme_methods->SetWebsiteName($_ENV['WEBSITE_NAME']);
$hiatme_methods->InitDB(
    $_ENV['DB_HOST'],
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    $_ENV['DB_NAME'],
    'users',
    'profiles'
);
$hiatme_methods->SetRandomKey($_ENV['RANDOM_KEY']);

// Initialize CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_timestamp'] = time();
    error_log("manage_users.php: Initialized CSRF token: " . $_SESSION['csrf_token']);
}

// Check authentication
$loggedIn = false;
$currentUser = null;
if (isset($_COOKIE['auth_token'])) {
    $userData = $hiatme_methods->ValidateToken($_COOKIE['auth_token']);
    if ($userData) {
        session_regenerate_id(true);
        $loggedIn = true;
        $currentUser = [
            'email' => $userData['email'],
            'name' => $userData['name'],
            'phone' => $userData['phone'],
            'profile_picture' => $userData['profile_picture'],
            'role' => $userData['role']
        ];
        error_log("manage_users.php: User logged in - " . $currentUser['email']);
    } else {
        setcookie('auth_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $_ENV['WEBSITE_NAME'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        error_log("manage_users.php: Invalid auth token, cookie cleared");
    }
}

if (!$loggedIn || !in_array($currentUser['role'], ['Manager', 'Owner'])) {
    error_log("manage_users.php: Access denied - role: " . ($currentUser['role'] ?? 'none'));
    header('Location: index.php');
    exit;
}

// Pagination
$users_per_page = 25;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $users_per_page;

// Fetch users
$result = $hiatme_methods->SearchUsers('', $offset, $users_per_page);
if ($result === false) {
    error_log("manage_users.php: SearchUsers failed: " . $hiatme_methods->GetErrorMessage());
    $users = [];
    $total_users = 0;
} else {
    $users = $result['users'];
    $total_users = $result['total'];
}
$total_pages = $total_users > 0 ? ceil($total_users / $users_per_page) : 1;
error_log("manage_users.php: Loaded " . count($users) . " users, total: $total_users, page: $page");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Website - Manage Users</title>
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <div class="content-wrapper">
        <header class="top-bar">
            <div class="top-bar-left">
                <?php echo renderMenu($hiatme_methods); ?>
                <img src="images/hereiam.png" alt="Website Logo" class="top-bar-logo">
            </div>
        </header>
        <main class="main-content user-management">
            <div class="user-management-form">
                <h2 class="form-title">User Management</h2>
                <div class="search-bar">
                    <input type="text" id="user-search" placeholder="Search users by email, name, or phone...">
                </div>
                <div class="desktop-only">
                    <div class="user-table-container">
                        <table class="user-table">
                            <thead>
                                <tr>
                                    <th>Actions</th>
                                    <th>Email</th>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Rewards</th>
                                    <th>Verified</th>
                                </tr>
                            </thead>
                            <tbody id="user-table-body">
                                <?php if (empty($users) && $result === false): ?>
                                    <tr><td colspan="6">Error loading users: <?php echo htmlentities($hiatme_methods->GetErrorMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td></tr>
                                <?php elseif (empty($users)): ?>
                                    <tr><td colspan="6">No users found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr data-user-id="<?php echo htmlentities($user['id'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-email="<?php echo htmlentities($user['email'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-name="<?php echo htmlentities($user['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-phone="<?php echo htmlentities($user['phone'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-role="<?php echo htmlentities($user['role'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-verified="<?php echo isset($user['is_verified']) && $user['is_verified'] ? 'Yes' : 'No'; ?>"
                                            data-picture="<?php echo htmlentities($user['profile_picture'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-created="<?php echo htmlentities($user['created_at'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-updated="<?php echo htmlentities($user['updated_at'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-reward-count="<?php echo htmlentities($user['reward_count'] ?? '0', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="view-details-btn" data-tooltip="View Details" id="view-details-<?php echo htmlentities($user['id'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                            <td><?php echo htmlentities($user['email'] ?? 'Unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                            <td><?php echo htmlentities($user['name'] ?? 'Unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                            <td><?php echo htmlentities($user['role'] ?? 'Unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                            <td><?php echo htmlentities($user['reward_count'] ?? '0', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                            <td><?php echo isset($user['is_verified']) && $user['is_verified'] ? 'Yes' : 'No'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="user-cards mobile-only" id="user-cards"><p>Loading cards...</p></div>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn">« Previous</a>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo htmlentities($i, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn">Next »</a>
                    <?php endif; ?>
                </div>
            </div>

            <div id="user-details-modal" class="modal">
                <div class="modal-content">
                    <span class="close-modal">&times</span>
                    <div class="user-details">
                        <img id="modal-avatar" src="" alt="Avatar" class="current-avatar">
                        <h2 id="modal-name"></h2>
                        <div class="form-group">
                            <select id="modal-role" class="modal-input">
                                <option value="" selected>Select Role</option>
                                <option value="Manager">Manager</option>
                                <option value="Owner">Owner</option>
                                <option value="Driver">Driver</option>
                                <option value="Client">Client</option>
                            </select>
                            <label for="modal-role">Role</label>
                        </div>
                        <button id="save-role-btn">Save</button>
                        <p><strong>Email:</strong> <span id="modal-email"></span></p>
                        <p><strong>Phone:</strong> <span id="modal-phone"></span></p>
                        <p><strong>Joined:</strong> <span id="modal-created"></span></p>
                        <p><strong>Last Updated:</strong> <span id="modal-updated"></span></p>
                        <p><strong>Verified:</strong> <span id="modal-verified"></span></p>
                        <h3>Rewards</h3>
                        <div id="modal-rewards"><p>Placeholder for rewards data</p></div>
                    </div>
                    <div id="modal-message"></div>
                </div>
            </div>
        </main>
        <footer class="footer">
            <p>&copy 2025 Hiatme. All rights reserved.</p>
        </footer>
        <div class="landscape-message-wrapper"></div>
        <div class="window-size-message-wrapper"></div>
    </div>

    <script>
        const initialUser = <?php echo $currentUser ? json_encode($currentUser, JSON_UNESCAPED_UNICODE) : 'null'; ?>;
        if (initialUser) {
            localStorage.setItem('currentUser', JSON.stringify(initialUser));
        } else {
            localStorage.removeItem('currentUser');
        }
        localStorage.setItem('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
    </script>
    <script src="scripts/menu.js"></script>
    <script src="scripts/manage_users.js"></script>
</body>
</html>