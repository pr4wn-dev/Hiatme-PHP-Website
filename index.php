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

//boo 
// Check for auth token in cookie
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
            'role' => $userData['role'], // Add role
            'vehicles' => $userData['vehicles'] // Add vehicles
        ];
        error_log("index.php: User logged in via token - " . $currentUser['email'] . ", vehicles: " . json_encode($currentUser['vehicles']));
    } else {
        setcookie('auth_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $_ENV['WEBSITE_NAME'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        error_log("index.php: Invalid auth token, cookie cleared");
    }
} else {
    error_log("index.php: No auth token found in cookie");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Website</title>
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  OneSignalDeferred.push(async function(OneSignal) {
    await OneSignal.init({
      appId: "1bb48e3c-7bce-4e46-9b4b-37983c1abbf2",
    });
  });
</script>
</head>
<body class="index-page">
    <div class="content-wrapper">
        <header class="top-bar">
            <div class="top-bar-left">
                <?php echo renderMenu($hiatme_methods); ?>
                <img src="images/hereiam.png" alt="Website Logo" class="top-bar-logo">
            </div>
        </header>
        <main class="main-content">
            <!-- Welcome text and paragraph removed -->
        </main>
        <footer class="footer">
            <p>&copy 2025 Hiatme. All rights reserved.</p>
        </footer>
        <div class="landscape-message-wrapper"></div>
        <div class="window-size-message-wrapper"></div>
    </div>

    <div id="login-modal" class="modal">
        <div class="modal-content">
            <form id="login-form">
                <div class="form-toggle-buttons">
                    <button type="button" class="form-toggle-btn active" data-target="login-modal">Login</button>
                    <button type="button" class="form-toggle-btn" data-target="register-modal">Sign Up</button>
                </div>
                <h2 class="form-title">Login Form</h2>
                <div class="form-group">
                    <input type="email" id="email" placeholder="Email" required>
                    <label for="email">Email</label>
                </div>
                <div class="form-group password-container">
                    <input type="password" id="password" placeholder="Password" required>
                    <label for="password">Password</label>
                    <button type="button" class="toggle-password" data-target="password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($hiatme_methods->GetCSRFToken()); ?>">
                <button type="submit">Login <span class="spinner"></span></button>
                <p>Don't have an account? <a href="#" id="create-account-link">Sign up</a></p>
                <p><a href="#" id="forgot-password-link">Forgot Password?</a></p>
                <div id="login-message"></div>
            </form>
        </div>
    </div>

    <div id="register-modal" class="modal">
        <div class="modal-content">
            <form id="register-form">
                <div class="form-toggle-buttons">
                    <button type="button" class="form-toggle-btn" data-target="login-modal">Login</button>
                    <button type="button" class="form-toggle-btn active" data-target="register-modal">Sign Up</button>
                </div>
                <h2 class="form-title">Registration Form</h2>
                <div class="form-group">
                    <input type="text" id="reg-name" placeholder="Name" required>
                    <label for="reg-name">Name</label>
                </div>
                <div class="form-group">
                    <input type="email" id="reg-email" placeholder="Email" required>
                    <label for="reg-email">Email</label>
                </div>
                <div class="form-group">
                    <input type="tel" id="reg-phone" placeholder="Phone Number">
                    <label for="reg-phone">Phone Number</label>
                </div>
                <div class="form-group password-container">
                    <input type="password" id="reg-password" placeholder="Password" required>
                    <label for="reg-password">Password</label>
                    <button type="button" class="toggle-password" data-target="reg-password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="form-group password-container">
                    <input type="password" id="confirm-password" placeholder="Confirm Password" required>
                    <label for="confirm-password">Confirm Password</label>
                    <button type="button" class="toggle-password" data-target="confirm-password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($hiatme_methods->GetCSRFToken()); ?>">
                <button type="submit">Register <span class="spinner"></span></button>
                <p>Already have an account? <a href="#" id="back-to-login-link">Log in</a></p>
                <div id="register-message"></div>
            </form>
        </div>
    </div>

    <div id="forgot-password-modal" class="modal">
        <div class="modal-content">
            <form id="forgot-password-form">
                <h2 class="form-title">Forgot Password</h2>
                <p>Enter your email to receive a password reset link.</p>
                <div class="form-group">
                    <input type="email" id="forgot-email" placeholder="Email" required>
                    <label for="forgot-email">Email</label>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($hiatme_methods->GetCSRFToken()); ?>">
                <button type="submit">Send Reset Link <span class="spinner"></span></button>
                <p><a href="#" id="back-to-login-from-forgot-link">Back to log in</a></p>
                <div id="forgot-message"></div>
            </form>
        </div>
    </div>

    <div id="reset-password-modal" class="modal">
        <div class="modal-content">
            <form id="reset-password-form">
                <h2 class="form-title">Reset Password</h2>
                <p>Enter your new password below.</p>
                <input type="hidden" id="reset-token" name="token">
                <div class="form-group password-container">
                    <input type="password" id="new-password" placeholder="New Password" required>
                    <label for="new-password">New Password</label>
                    <button type="button" class="toggle-password" data-target="new-password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="form-group password-container">
                    <input type="password" id="confirm-new-password" placeholder="Confirm Password" required>
                    <label for="confirm-new-password">Confirm Password</label>
                    <button type="button" class="toggle-password" data-target="confirm-new-password">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($hiatme_methods->GetCSRFToken()); ?>">
                <button type="submit">Reset Password <span class="spinner"></span></button>
                <p><a href="#" id="back-to-login-from-reset-link">Back to log in</a></p>
                <div id="reset-message"></div>
            </form>
        </div>
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