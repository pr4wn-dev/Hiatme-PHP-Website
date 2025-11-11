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

// Helper function to ensure vehicle_issues table exists
function ensureVehicleIssuesTable($hiatme_methods) {
    $reflection = new ReflectionClass($hiatme_methods);
    $method = $reflection->getMethod('createVehicleIssuesTable');
    $method->setAccessible(true);
    return $method->invoke($hiatme_methods);
}

// Check for auth token
$loggedIn = false;
$currentUser = null;
if (isset($_COOKIE['auth_token'])) {
    $userData = $hiatme_methods->ValidateToken($_COOKIE['auth_token']);
    if ($userData) {
        $loggedIn = true;
        $currentUser = [
            'email' => $userData['email'], // Changed from 'username' to 'email'
            'name' => $userData['name'],
            'phone' => $userData['phone'],
            'profile_picture' => $userData['profile_picture'],
            'role' => $userData['role']
        ];
        error_log("api/search_vehicles.php: User authenticated - " . $currentUser['email']); // Updated to use 'email'
    } else {
        setcookie('auth_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $_ENV['WEBSITE_NAME'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        error_log("api/search_vehicles.php: Invalid auth token, cookie cleared");
    }
} else {
    error_log("api/search_vehicles.php: No auth token found");
}

// Check if user is authorized
if (!$loggedIn || !in_array($currentUser['role'] ?? 'Client', ['Manager', 'Owner'])) {
    error_log("api/search_vehicles.php: Unauthorized - loggedIn: " . ($loggedIn ? 'true' : 'false') . ", role: " . ($currentUser['role'] ?? 'none'));
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'csrf_token' => $_SESSION['csrf_token'] ?? '']);
    exit;
}

// Ensure CSRF token exists
$maxTokenAge = 1800; // 30 minutes
if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_timestamp']) || (time() - $_SESSION['csrf_timestamp']) > $maxTokenAge) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_timestamp'] = time();
    error_log("api/search_vehicles.php: Initialized CSRF token: " . $_SESSION['csrf_token']);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'get_csrf_token') {
        // Return fresh CSRF token
        $hiatme_methods->GetFreshCSRFToken();
        exit;
    } elseif (isset($_GET['action']) && $_GET['action'] === 'get_issues') {
        // Fetch vehicle issues
        $vehicle_id = isset($_GET['vehicle_id']) ? trim($_GET['vehicle_id']) : '';
        error_log("api/search_vehicles.php: Get issues request - vehicle_id: '$vehicle_id'");

        if (!$vehicle_id) {
            error_log("api/search_vehicles.php: Missing vehicle_id for get_issues");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid vehicle_id', 'csrf_token' => $_SESSION['csrf_token']]);
            exit;
        }

        try {
            $result = $hiatme_methods->GetVehicleIssues($vehicle_id);
            if ($result === false) {
                error_log("api/search_vehicles.php: Get issues failed: " . $hiatme_methods->GetErrorMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $hiatme_methods->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'issues' => $result['issues'],
                'csrf_token' => $_SESSION['csrf_token']
            ]);
        } catch (Exception $e) {
            error_log("api/search_vehicles.php: Exception in get_issues: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error', 'csrf_token' => $_SESSION['csrf_token']]);
            exit;
        }
    } else {
        // Search and pagination parameters
        $query = isset($_GET['query']) ? trim($_GET['query']) : '';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $vehicles_per_page = 25;
        $offset = ($page - 1) * $vehicles_per_page;

        error_log("api/search_vehicles.php: Search query: '$query', page: $page, offset: $offset");

        try {
            // Search vehicles
            $result = $hiatme_methods->SearchVehicles($query, $offset, $vehicles_per_page);
            if ($result === false) {
                error_log("api/search_vehicles.php: Search failed: " . $hiatme_methods->GetErrorMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $hiatme_methods->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']]);
                exit;
            }

            $vehicles = $result['vehicles'];
            $total_vehicles = $result['total'];
            $total_pages = ceil($total_vehicles / $vehicles_per_page);

            error_log("api/search_vehicles.php: Found " . count($vehicles) . " vehicles, total: $total_vehicles");

            echo json_encode([
                'success' => true,
                'vehicles' => $vehicles,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'total_vehicles' => $total_vehicles,
                'csrf_token' => $_SESSION['csrf_token']
            ]);
        } catch (Exception $e) {
            error_log("api/search_vehicles.php: Exception: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error', 'csrf_token' => $_SESSION['csrf_token']]);
            exit;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    error_log("api/search_vehicles.php: CSRF received: '$csrf_token', expected: '" . ($_SESSION['csrf_token'] ?? 'none') . "'");

    if (empty($_SESSION['csrf_token']) || empty($csrf_token) || $csrf_token !== $_SESSION['csrf_token'] || (time() - $_SESSION['csrf_timestamp']) > $maxTokenAge) {
        error_log("api/search_vehicles.php: CSRF validation failed");
        http_response_code(403);
        $newCsrfToken = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $newCsrfToken;
        $_SESSION['csrf_timestamp'] = time();
        echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token', 'csrf_token' => $newCsrfToken]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        error_log("api/search_vehicles.php: Failed to parse JSON input");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input', 'csrf_token' => $_SESSION['csrf_token']]);
        exit;
    }

    $action = $input['action'] ?? '';
    $newCsrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $newCsrfToken;
    $_SESSION['csrf_timestamp'] = time();
    error_log("api/search_vehicles.php: New CSRF token: $newCsrfToken");

    if ($action === 'create_vehicle') {
        $vehicle_data = $input['vehicle_data'] ?? [];
        error_log("api/search_vehicles.php: Create vehicle request");

        if (empty($vehicle_data)) {
            error_log("api/search_vehicles.php: Missing vehicle_data");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid vehicle data', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        try {
            if ($hiatme_methods->CreateVehicle($vehicle_data)) {
                error_log("api/search_vehicles.php: Vehicle created successfully");
                echo json_encode(['success' => true, 'csrf_token' => $newCsrfToken]);
            } else {
                $error = $hiatme_methods->GetErrorMessage();
                error_log("api/search_vehicles.php: Vehicle creation failed: " . ($error ?: 'Unknown error'));
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $error ?: 'Failed to create vehicle', 'csrf_token' => $newCsrfToken]);
            }
        } catch (Exception $e) {
            error_log("api/search_vehicles.php: Exception in vehicle creation: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error', 'csrf_token' => $newCsrfToken]);
            exit;
        }
    } elseif ($action === 'delete_vehicle') {
        $vehicle_id = $input['vehicle_id'] ?? '';
        error_log("api/search_vehicles.php: Delete vehicle request - vehicle_id: '$vehicle_id'");

        if (!$vehicle_id) {
            error_log("api/search_vehicles.php: Missing vehicle_id");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid vehicle_id', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        try {
            if ($hiatme_methods->DeleteVehicle($vehicle_id)) {
                error_log("api/search_vehicles.php: Vehicle deleted successfully for vehicle_id: $vehicle_id");
                echo json_encode(['success' => true, 'csrf_token' => $newCsrfToken]);
            } else {
                $error = $hiatme_methods->GetErrorMessage();
                error_log("api/search_vehicles.php: Vehicle deletion failed: " . ($error ?: 'Unknown error'));
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $error ?: 'Failed to delete vehicle', 'csrf_token' => $newCsrfToken]);
            }
        } catch (Exception $e) {
            error_log("api/search_vehicles.php: Exception in vehicle deletion: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error', 'csrf_token' => $newCsrfToken]);
            exit;
        }
    } elseif ($action === 'add_issue') {
        $vehicle_id = $input['vehicle_id'] ?? '';
        $issue_type = $input['issue_type'] ?? '';
        $description = $input['description'] ?? null;
        error_log("api/search_vehicles.php: Add issue request - vehicle_id: '$vehicle_id', issue_type: '$issue_type'");

        if (!$vehicle_id || !$issue_type) {
            error_log("api/search_vehicles.php: Missing vehicle_id or issue_type");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid vehicle_id or issue_type', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        if (!ensureVehicleIssuesTable($hiatme_methods)) {
            error_log("api/search_vehicles.php: Failed to ensure vehicle_issues table");
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to initialize issues table', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        try {
            if ($hiatme_methods->AddVehicleIssue($vehicle_id, $issue_type, $description)) {
                error_log("api/search_vehicles.php: Issue added successfully for vehicle_id: $vehicle_id");
                echo json_encode(['success' => true, 'csrf_token' => $newCsrfToken]);
            } else {
                $error = $hiatme_methods->GetErrorMessage();
                error_log("api/search_vehicles.php: Issue creation failed: " . ($error ?: 'Unknown error'));
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $error ?: 'Failed to add issue', 'csrf_token' => $newCsrfToken]);
            }
        } catch (Exception $e) {
            error_log("api/search_vehicles.php: Exception in issue creation: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error', 'csrf_token' => $newCsrfToken]);
            exit;
        }
    } elseif ($action === 'resolve_issue') {
        $issue_id = $input['issue_id'] ?? '';
        error_log("api/search_vehicles.php: Resolve issue request - issue_id: '$issue_id'");

        if (!$issue_id) {
            error_log("api/search_vehicles.php: Missing issue_id");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid issue_id', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        if (!ensureVehicleIssuesTable($hiatme_methods)) {
            error_log("api/search_vehicles.php: Failed to ensure vehicle_issues table");
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to initialize issues table', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        try {
            if ($hiatme_methods->ResolveVehicleIssue($issue_id)) {
                error_log("api/search_vehicles.php: Issue resolved successfully for issue_id: $issue_id");
                echo json_encode(['success' => true, 'csrf_token' => $newCsrfToken]);
            } else {
                $error = $hiatme_methods->GetErrorMessage();
                error_log("api/search_vehicles.php: Issue resolution failed: " . ($error ?: 'Unknown error'));
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $error ?: 'Failed to resolve issue', 'csrf_token' => $newCsrfToken]);
            }
        } catch (Exception $e) {
            error_log("api/search_vehicles.php: Exception in issue resolution: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error', 'csrf_token' => $newCsrfToken]);
            exit;
        }
    } elseif ($action === 'resolve_issue_with_details') {
        $issue_id = $input['issue_id'] ?? '';
        $resolution_data = $input['resolution_data'] ?? [];
        error_log("api/search_vehicles.php: Resolve issue with details request - issue_id: '$issue_id'");
        error_log("api/search_vehicles.php: Resolution data received: " . json_encode($resolution_data));

        if (!$issue_id || empty($resolution_data)) {
            error_log("api/search_vehicles.php: Missing issue_id or resolution_data");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid issue_id or resolution data', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        // Validate new resolution data fields
        $parts_replaced = isset($resolution_data['parts_replaced']) ? trim($resolution_data['parts_replaced']) : '';
        $work_done = isset($resolution_data['work_done']) ? trim($resolution_data['work_done']) : '';
        $invoice_number = isset($resolution_data['invoice_number']) ? trim($resolution_data['invoice_number']) : '';
        $labor_hours = isset($resolution_data['labor_hours']) ? $resolution_data['labor_hours'] : null;
        $repair_cost = isset($resolution_data['repair_cost']) ? $resolution_data['repair_cost'] : null;
        $mechanic_notes = isset($resolution_data['mechanic_notes']) ? trim($resolution_data['mechanic_notes']) : '';
        $repair_category = isset($resolution_data['repair_category']) ? trim($resolution_data['repair_category']) : '';

        if (!$work_done) {
            error_log("api/search_vehicles.php: Missing required work_done");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Work done is required', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        if (!$repair_category) {
            error_log("api/search_vehicles.php: Missing required repair_category");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Repair category is required', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        if ($invoice_number && !preg_match('/^[A-Za-z0-9-]{1,50}$/', $invoice_number)) {
            error_log("api/search_vehicles.php: Invalid invoice_number format");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invoice number must be alphanumeric with dashes, max 50 characters', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        if ($labor_hours !== null && (!is_numeric($labor_hours) || $labor_hours < 0 || $labor_hours > 99.99)) {
            error_log("api/search_vehicles.php: Invalid labor_hours value");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Labor hours must be between 0 and 99.99', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        if ($repair_cost !== null && (!is_numeric($repair_cost) || $repair_cost < 0 || $repair_cost > 999999.99)) {
            error_log("api/search_vehicles.php: Invalid repair_cost value");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Repair cost must be between 0 and 999999.99', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        $allowed_categories = ['Maintenance', 'Emergency', 'Cosmetic', 'Performance', 'Other'];
        if (!in_array($repair_category, $allowed_categories)) {
            error_log("api/search_vehicles.php: Invalid repair_category: '$repair_category'");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid repair category', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        $validated_resolution_data = [
            'parts_replaced' => $parts_replaced,
            'work_done' => $work_done,
            'invoice_number' => $invoice_number,
            'labor_hours' => $labor_hours !== null ? floatval($labor_hours) : null,
            'repair_cost' => $repair_cost !== null ? floatval($repair_cost) : null,
            'mechanic_notes' => $mechanic_notes,
            'repair_category' => $repair_category
        ];

        if (!ensureVehicleIssuesTable($hiatme_methods)) {
            error_log("api/search_vehicles.php: Failed to ensure vehicle_issues table");
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to initialize issues table', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        try {
            if ($hiatme_methods->ResolveVehicleIssueWithDetails($issue_id, $validated_resolution_data)) {
                error_log("api/search_vehicles.php: Issue resolved with details successfully for issue_id: $issue_id");
                echo json_encode(['success' => true, 'csrf_token' => $newCsrfToken]);
            } else {
                $error = $hiatme_methods->GetErrorMessage();
                error_log("api/search_vehicles.php: Issue resolution with details failed: " . ($error ?: 'Unknown error'));
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $error ?: 'Failed to resolve issue', 'csrf_token' => $newCsrfToken]);
            }
        } catch (Exception $e) {
            error_log("api/search_vehicles.php: Exception in issue resolution with details: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error', 'csrf_token' => $newCsrfToken]);
            exit;
        }
    } elseif ($action === 'edit_issue_resolution') {
        $issue_id = $input['issue_id'] ?? '';
        $resolution_data = $input['resolution_data'] ?? [];
        error_log("api/search_vehicles.php: Edit issue resolution request - issue_id: '$issue_id'");
        error_log("api/search_vehicles.php: Resolution data received: " . json_encode($resolution_data));

        if (!$issue_id || empty($resolution_data)) {
            error_log("api/search_vehicles.php: Missing issue_id or resolution_data");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid issue_id or resolution data', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        // Validate new resolution data fields
        $parts_replaced = isset($resolution_data['parts_replaced']) ? trim($resolution_data['parts_replaced']) : '';
        $work_done = isset($resolution_data['work_done']) ? trim($resolution_data['work_done']) : '';
        $invoice_number = isset($resolution_data['invoice_number']) ? trim($resolution_data['invoice_number']) : '';
        $labor_hours = isset($resolution_data['labor_hours']) ? $resolution_data['labor_hours'] : null;
        $repair_cost = isset($resolution_data['repair_cost']) ? $resolution_data['repair_cost'] : null;
        $mechanic_notes = isset($resolution_data['mechanic_notes']) ? trim($resolution_data['mechanic_notes']) : '';
        $repair_category = isset($resolution_data['repair_category']) ? trim($resolution_data['repair_category']) : '';

        if (!$work_done) {
            error_log("api/search_vehicles.php: Missing required work_done");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Work done is required', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        if (!$repair_category) {
            error_log("api/search_vehicles.php: Missing required repair_category");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Repair category is required', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        if ($invoice_number && !preg_match('/^[A-Za-z0-9-]{1,50}$/', $invoice_number)) {
            error_log("api/search_vehicles.php: Invalid invoice_number format");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invoice number must be alphanumeric with dashes, max 50 characters', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        if ($labor_hours !== null && (!is_numeric($labor_hours) || $labor_hours < 0 || $labor_hours > 99.99)) {
            error_log("api/search_vehicles.php: Invalid labor_hours value");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Labor hours must be between 0 and 99.99', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        if ($repair_cost !== null && (!is_numeric($repair_cost) || $repair_cost < 0 || $repair_cost > 999999.99)) {
            error_log("api/search_vehicles.php: Invalid repair_cost value");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Repair cost must be between 0 and 999999.99', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        $allowed_categories = ['Maintenance', 'Emergency', 'Cosmetic', 'Performance', 'Other'];
        if (!in_array($repair_category, $allowed_categories)) {
            error_log("api/search_vehicles.php: Invalid repair_category: '$repair_category'");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid repair category', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        $validated_resolution_data = [
            'parts_replaced' => $parts_replaced,
            'work_done' => $work_done,
            'invoice_number' => $invoice_number,
            'labor_hours' => $labor_hours !== null ? floatval($labor_hours) : null,
            'repair_cost' => $repair_cost !== null ? floatval($repair_cost) : null,
            'mechanic_notes' => $mechanic_notes,
            'repair_category' => $repair_category
        ];

        try {
            if ($hiatme_methods->EditIssueResolution($issue_id, $validated_resolution_data)) {
                error_log("api/search_vehicles.php: Issue resolution edited successfully for issue_id: $issue_id");
                echo json_encode(['success' => true, 'csrf_token' => $newCsrfToken]);
            } else {
                $error = $hiatme_methods->GetErrorMessage();
                error_log("api/search_vehicles.php: Issue resolution edit failed: " . ($error ?: 'Unknown error'));
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $error ?: 'Failed to edit resolution', 'csrf_token' => $newCsrfToken]);
            }
        } catch (Exception $e) {
            error_log("api/search_vehicles.php: Exception in issue resolution edit: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error', 'csrf_token' => $newCsrfToken]);
            exit;
        }
    } elseif ($action === 'update_vehicle') {
        $vehicle_id = $input['vehicle_id'] ?? '';
        $vehicle_data = $input['vehicle_data'] ?? [];
        error_log("api/search_vehicles.php: Vehicle update request - vehicle_id: '$vehicle_id'");

        if (!$vehicle_id || empty($vehicle_data)) {
            error_log("api/search_vehicles.php: Missing vehicle_id or vehicle_data");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid vehicle_id or data', 'csrf_token' => $newCsrfToken]);
            exit;
        }

        try {
            if ($hiatme_methods->UpdateVehicle($vehicle_id, $vehicle_data)) {
                error_log("api/search_vehicles.php: Vehicle update successful for vehicle_id: $vehicle_id");
                echo json_encode(['success' => true, 'csrf_token' => $newCsrfToken]);
            } else {
                $error = $hiatme_methods->GetErrorMessage();
                error_log("api/search_vehicles.php: Vehicle update failed: " . ($error ?: 'Unknown error'));
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => $error ?: 'Failed to update vehicle', 'csrf_token' => $newCsrfToken]);
            }
        } catch (Exception $e) {
            error_log("api/search_vehicles.php: Exception in vehicle update: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error', 'csrf_token' => $newCsrfToken]);
            exit;
        }
    } else {
        error_log("api/search_vehicles.php: Invalid action: " . ($action ?: 'none'));
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action', 'csrf_token' => $newCsrfToken]);
        exit;
    }
} else {
    error_log("api/search_vehicles.php: Invalid method: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed', 'csrf_token' => $_SESSION['csrf_token'] ?? '']);
}
?>