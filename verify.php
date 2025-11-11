<?php
// verify.php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once 'includes/hiatme_methods.php';

// Initialize HiatmeMethods with environment variables only
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

// Handle token verification
$token = $_GET['token'] ?? '';
$message = '';

if ($token) {
    if ($hiatme_methods->VerifyUser($token)) {
        // Use raw HTML for the success message with a clickable link
        $message = 'Account verified successfully! You can now <a href="index.php">log in</a>.';
        error_log("verify.php: Token verified successfully - $token");
    } else {
        // Escape error message to prevent XSS, since it includes dynamic content
        $message = "Verification failed: " . htmlspecialchars($hiatme_methods->GetErrorMessage() ?: "Invalid or expired token.");
        error_log("verify.php: Token verification failed - $token");
    }
} else {
    $message = "No verification token provided.";
    error_log("verify.php: No token provided in request");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account</title>
    <link rel="stylesheet" href="styles/main.css">
</head>
<body>
    <div class="content-wrapper">
        <main class="main-content">
            <h1>Account Verification</h1>
            <!-- Output message without htmlspecialchars for success case -->
            <p><?php echo $message; ?></p>
        </main>
        <footer class="footer">
            <p>© 2025 My Website. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>