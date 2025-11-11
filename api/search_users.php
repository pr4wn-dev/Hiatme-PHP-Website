<?php
header('Content-Type: application/json');

// Initialize session and environment
session_start();
require_once '../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Initialize HiatmeMethods
require_once '../includes/hiatme_methods.php';
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

// Check for auth token
$loggedIn = false;
$currentUser = null;
if (isset($_COOKIE['auth_token'])) {
    $userData = $hiatme_methods->ValidateToken($_COOKIE['auth_token']);
    if ($userData) {
        $loggedIn = true;
        $currentUser = [
            'email' => $userData['email'],
            'name' => $userData['name'],
            'phone' => $userData['phone'],
            'profile_picture' => $userData['profile_picture'],
            'role' => $userData['role']
        ];
        error_log("api/search_users.php: User authenticated - " . $currentUser['email']);
    } else {
        setcookie('auth_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $_ENV['WEBSITE_NAME'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        error_log("api/search_users.php: Invalid auth token, cookie cleared");
    }
} else {
    error_log("api/search_users.php: No auth token found");
}

// Check if user is authorized
if (!$loggedIn || !in_array($currentUser['role'] ?? 'Client', ['Manager', 'Owner'])) {
    error_log("api/search_users.php: Unauthorized - loggedIn: " . ($loggedIn ? 'true' : 'false') . ", role: " . ($currentUser['role'] ?? 'none'));
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'get_csrf_token') {
        // Return fresh CSRF token
        $hiatme_methods->GetFreshCSRFToken();
        exit;
    }

    // Search and pagination parameters
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $users_per_page = 25;
    $offset = ($page - 1) * $users_per_page;

    error_log("api/search_users.php: Search query: '$query', page: $page, offset: $offset");

    try {
        // Search users
        $result = $hiatme_methods->SearchUsers($query, $offset, $users_per_page);
        if ($result === false) {
            error_log("api/search_users.php: Search failed: " . $hiatme_methods->GetErrorMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $hiatme_methods->GetErrorMessage()]);
            exit;
        }

        $users = $result['users'];
        $total_users = $result['total'];
        $total_pages = ceil($total_users / $users_per_page);

        error_log("api/search_users.php: Found " . count($users) . " users, total: $total_users");

        // Generate new CSRF token for next request
        $new_csrf_token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $new_csrf_token;
        $_SESSION['csrf_timestamp'] = time();
        error_log("api/search_users.php: New CSRF token generated: $new_csrf_token");

        echo json_encode([
            'success' => true,
            'users' => $users,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'total_users' => $total_users,
            'csrf_token' => $new_csrf_token
        ]);
    } catch (Exception $e) {
        error_log("api/search_users.php: Exception: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Role update functionality
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    error_log("api/search_users.php: CSRF received: '$csrf_token', expected: '" . ($_SESSION['csrf_token'] ?? 'none') . "'");

    if (empty($_SESSION['csrf_token']) || empty($csrf_token) || $csrf_token !== $_SESSION['csrf_token']) {
        error_log("api/search_users.php: CSRF validation failed");
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        error_log("api/search_users.php: Failed to parse JSON input");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        exit;
    }

    $user_id = $input['user_id'] ?? '';
    $role = $input['role'] ?? '';
    error_log("api/search_users.php: Role update request - user_id: '$user_id', role: '$role'");

    if (!$user_id || !$role || !in_array($role, ['Client', 'Driver', 'Manager', 'Owner'])) {
        error_log("api/search_users.php: Missing or invalid user_id or role");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user_id or role']);
        exit;
    }

    try {
        if ($hiatme_methods->UpdateUserRole($user_id, $role)) {
            error_log("api/search_users.php: Role update successful for user_id: $user_id");
            // Generate new CSRF token
            $new_csrf_token = bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $new_csrf_token;
            $_SESSION['csrf_timestamp'] = time();
            error_log("api/search_users.php: New CSRF token: $new_csrf_token");
            echo json_encode(['success' => true, 'csrf_token' => $new_csrf_token]);
        } else {
            $error = $hiatme_methods->GetErrorMessage();
            error_log("api/search_users.php: Role update failed: " . ($error ?: 'Unknown error'));
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $error ?: 'Failed to update role']);
        }
    } catch (Exception $e) {
        error_log("api/search_users.php: Exception in role update: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error']);
        exit;
    }
} else {
    error_log("api/search_users.php: Invalid method: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>