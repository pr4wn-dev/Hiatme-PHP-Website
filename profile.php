<?php
session_start();
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Instantiate HiatmeMethods regardless of cookie
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

// Check for auth token in cookie
$loggedIn = false;
$currentUser = null;
if (isset($_COOKIE['auth_token'])) {
    $userData = $hiatme_methods->ValidateToken($_COOKIE['auth_token']);
    if ($userData) {
        session_regenerate_id(true); // Regenerate session ID on valid token
        $loggedIn = true;
        $currentUser = [
            'email' => $userData['email'],
            'name' => $userData['name'],
            'phone' => $userData['phone'],
            'profile_picture' => $userData['profile_picture']
        ];
        error_log("profile.php: User logged in via token and session ID regenerated - " . $currentUser['email']);
    } else {
        setcookie('auth_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $_ENV['WEBSITE_NAME'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        error_log("profile.php: Invalid auth token, cookie cleared");
    }
} else {
    error_log("profile.php: No auth token found in cookie");
}

if (!$loggedIn) {
    error_log("profile.php: User not logged in, redirecting to index.php");
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Website - Profile</title>
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
        <main class="main-content">
            <h1>Profile</h1>
            <div id="profile-modal" class="modal profile-modal">
                <div class="modal-content">
                    <form id="profile-form" enctype="multipart/form-data">
                        <h2 class="form-title">Update Profile</h2>
                        <input type="hidden" name="current_email" value="<?php echo htmlspecialchars($currentUser['email']); ?>">
                        <div class="form-group avatar-section">
                            <img src="<?php echo htmlspecialchars($currentUser['profile_picture'] ?? 'images/avatar.png'); ?>" alt="Current Avatar" class="current-avatar" id="avatar-image">
                            <input type="file" id="profile-picture" name="profile_picture" accept="image/*" style="display: none;">
                            <label for="profile-picture">Change Profile Picture</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="profile-name" name="name" value="<?php echo htmlspecialchars($currentUser['name']); ?>" placeholder="Full Name" required>
                            <label for="profile-name">Full Name</label>
                        </div>
                        <div class="form-group">
                            <input type="email" id="profile-email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" placeholder="Email" required>
                            <label for="profile-email">Email</label>
                        </div>
                        <div class="form-group">
                            <input type="tel" id="profile-phone" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" placeholder="Phone Number">
                            <label for="profile-phone">Phone Number</label>
                        </div>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($hiatme_methods->GetCSRFToken()); ?>">
                        <button type="submit">Update Profile <span class="spinner"></span></button>
                        <div id="profile-message"></div>
                    </form>
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
        const initialUser = <?php echo $currentUser ? json_encode($currentUser) : 'null'; ?>;
        if (initialUser) {
            localStorage.setItem('currentUser', JSON.stringify(initialUser));
        } else {
            localStorage.removeItem('currentUser');
        }
    </script>
    <script src="scripts/menu.js"></script>
</body>
</html>