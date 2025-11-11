<?php
// includes/hiatme_config.php
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', '1800'); // 30 minutes to match CSRF token expiry
ini_set('session.gc_probability', '1'); // 1% chance of garbage collection
ini_set('session.gc_divisor', '100'); // per 100 session starts

// Add CORS headers
header('Access-Control-Allow-Origin: *'); // Adjust to specific origins in production
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-CSRF-Token, Content-Type, User-Agent');
header('Access-Control-Max-Age: 86400');

session_start();
require_once dirname(__FILE__) . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__FILE__) . '/..');
$dotenv->load();

if (!file_exists(dirname(__FILE__) . '/hiatme_methods.php')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: hiatme_methods.php not found']);
    exit;
}
require_once(dirname(__FILE__) . '/hiatme_methods.php');

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data received: " . json_encode($_POST));
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($csrfToken)) {
        // Generate a new token if none provided to handle new sessions
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_timestamp'] = time();
        error_log("No CSRF token provided, generated new: " . $_SESSION['csrf_token']);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No CSRF token provided, please retry', 'csrf_token' => $_SESSION['csrf_token']]);
        exit;
    }
    if ($csrfToken !== $_SESSION['csrf_token']) {
        error_log("CSRF token validation failed - Provided: $csrfToken, Expected: " . ($_SESSION['csrf_token'] ?? 'none'));
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token', 'csrf_token' => $_SESSION['csrf_token'] ?? '']);
        exit;
    }
    $hiatme_methods->handleRequest();
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_csrf_token') {
    $hiatme_methods->GetFreshCSRFToken();
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method or action']);
    exit;
}
?>