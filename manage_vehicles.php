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

// Initialize CSRF token only if not set by API
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_timestamp'] = time();
    error_log("manage_vehicles.php: Initialized CSRF token: " . $_SESSION['csrf_token']);
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
        error_log("manage_vehicles.php: User logged in - " . $currentUser['email']);
    } else {
        setcookie('auth_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $_ENV['WEBSITE_NAME'],
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        error_log("manage_vehicles.php: Invalid auth token, cookie cleared");
    }
}

if (!$loggedIn || !in_array($currentUser['role'], ['Manager', 'Owner'])) {
    error_log("manage_vehicles.php: Access denied - role: " . ($currentUser['role'] ?? 'none'));
    header('Location: index.php');
    exit;
}

// Pagination
$vehicles_per_page = 25;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $vehicles_per_page;

// Fetch vehicles
$result = $hiatme_methods->SearchVehicles('', $offset, $vehicles_per_page);
if ($result === false) {
    error_log("manage_vehicles.php: SearchVehicles failed: " . $hiatme_methods->GetErrorMessage());
    $vehicles = [];
    $total_vehicles = 0;
    $total_pages = 1;
    $current_page = 1;
} else {
    $vehicles = $result['vehicles'];
    $total_vehicles = $result['total'];
    $total_pages = $result['total_pages'];
    $current_page = $result['current_page'];
}

// Limit pagination buttons
$max_buttons = 5;
$start_page = max(1, $current_page - floor($max_buttons / 2));
$end_page = min($total_pages, $start_page + $max_buttons - 1);
$start_page = max(1, $end_page - $max_buttons + 1);

error_log("manage_vehicles.php: Loaded " . count($vehicles) . " vehicles, total: $total_vehicles, page: $current_page");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Website - Manage Vehicles</title>
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
        <main class="main-content vehicle-management">
            <div class="vehicle-management-form">
                <h2 class="form-title">Vehicle Management</h2>
                <div class="search-bar">
                    <div class="create-button-container">
                        <button id="create-vehicle-btn" class="create-vehicle-btn">Create +</button>
                    </div>
                    <input type="text" id="vehicle-search" placeholder="Search vehicles by license plate, make, model, or VIN...">
                </div>
                <div class="desktop-only">
                    <div class="vehicle-table-container">
                        <table class="vehicle-table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Actions</th>
                                    <th style="width: 200px;">Current User</th>
                                    <th style="width: 150px;">VIN</th>
                                    <th style="width: 100px;">Year</th>
                                    <th style="width: 150px;">Make</th>
                                    <th style="width: 150px;">Model</th>
                                    <th style="width: 150px;">License Plate</th>
                                </tr>
                            </thead>
                            <tbody id="vehicle-table-body">
                                <?php if (empty($vehicles) && $result === false): ?>
                                    <tr>
                                        <td style="width: 120px;"></td>
                                        <td style="width: 200px;" colspan="6"><?php echo htmlentities($hiatme_methods->GetErrorMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                    </tr>
                                <?php elseif (empty($vehicles)): ?>
                                    <tr>
                                        <td style="width: 120px;"></td>
                                        <td style="width: 200px;" colspan="6">No vehicles found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <tr data-vehicle-id="<?php echo htmlentities($vehicle['vehicle_id'] ?? 'unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-image-location="<?php echo htmlentities($vehicle['image_location'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-make="<?php echo htmlentities($vehicle['make'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-model="<?php echo htmlentities($vehicle['model'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-vin="<?php echo htmlentities($vehicle['vin'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-color="<?php echo htmlentities($vehicle['color'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-license-plate="<?php echo htmlentities($vehicle['license_plate'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-year="<?php echo htmlentities($vehicle['year'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-current-user-id="<?php echo htmlentities($vehicle['current_user_id'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-current-user-name="<?php echo htmlentities($vehicle['current_user_name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-last-user-id="<?php echo htmlentities($vehicle['last_user_id'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-last-user-name="<?php echo htmlentities($vehicle['last_user_name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-date-assigned="<?php echo htmlentities($vehicle['date_assigned'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"
                                            data-date-last-used="<?php echo htmlentities($vehicle['date_last_used'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
                                            <td style="width: 120px;">
                                                <div class="action-buttons">
                                                    <button class="view-details-btn" data-tooltip="View Details" id="view-details-<?php echo htmlentities($vehicle['vehicle_id'] ?? 'unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"><i class="fas fa-eye"></i></button>
                                                    <button class="issues-vehicle-btn" data-tooltip="Issues" id="issues-<?php echo htmlentities($vehicle['vehicle_id'] ?? 'unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"><i class="fas fa-wrench"></i></button>
                                                    <button class="delete-vehicle-btn" data-tooltip="Delete" id="delete-<?php echo htmlentities($vehicle['vehicle_id'] ?? 'unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                            <td style="width: 200px;"><?php echo htmlentities($vehicle['current_user_name'] ?? 'None', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                            <td style="width: 150px;"><?php echo htmlentities(substr($vehicle['vin'] ?? 'Unknown', -6), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                            <td style="width: 100px;"><?php echo htmlentities($vehicle['year'] ?? 'Unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                            <td style="width: 150px;"><?php echo htmlentities($vehicle['make'] ?? 'Unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                            <td style="width: 150px;"><?php echo htmlentities($vehicle['model'] ?? 'Unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                            <td style="width: 150px;"><?php echo htmlentities($vehicle['license_plate'] ?? 'Unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="vehicle-cards mobile-only" id="vehicle-cards"><p>Loading cards...</p></div>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?>" class="pagination-btn">« Previous</a>
                    <?php endif; ?>
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="pagination-btn <?php echo $i === $current_page ? 'active' : ''; ?>">
                            <?php echo htmlentities($i, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>" class="pagination-btn">Next »</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vehicle Details Modal -->
            <div id="vehicle-details-modal" class="modal">
                <div class="modal-content">
                    <span class="close-modal">&times</span>
                    <div class="vehicle-details">
                        <h2 id="modal-make-model"></h2>
                        <div class="form-group">
                            <input type="text" id="modal-make" class="modal-input" placeholder="Make" required>
                            <label for="modal-make">Make</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="modal-model" class="modal-input" placeholder="Model" required>
                            <label for="modal-model">Model</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="modal-vin" class="modal-input" placeholder="VIN" required>
                            <label for="modal-vin">VIN</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="modal-color" class="modal-input" placeholder="Color" required>
                            <label for="modal-color">Color</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="modal-license-plate" class="modal-input" placeholder="License Plate" required>
                            <label for="modal-license-plate">License Plate</label>
                        </div>
                        <div class="form-group">
                            <input type="number" id="modal-year" class="modal-input" placeholder="Year" min="1900" max="2025" required>
                            <label for="modal-year">Year</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="modal-image-location" class="modal-input" placeholder="Image Location (Optional)">
                            <label for="modal-image-location">Image Location (Optional)</label>
                        </div>
                        <p><strong>Current User:</strong> <span id="modal-current-user-name"></span></p>
                        <p><strong>Last User:</strong> <span id="modal-last-user-name"></span></p>
                        <p><strong>Date Assigned:</strong> <span id="modal-date-assigned"></span></p>
                        <p><strong>Date Last Used:</strong> <span id="modal-date-last-used"></span></p>
                        <button id="save-vehicle-btn">Save</button>
                    </div>
                    <div id="modal-message"></div>
                </div>
            </div>

            <!-- Create Vehicle Modal -->
            <div id="create-vehicle-modal" class="modal">
                <div class="modal-content">
                    <span class="close-modal">&times</span>
                    <div class="vehicle-details">
                        <h2>Create New Vehicle</h2>
                        <div class="form-group">
                            <input type="text" id="create-make" class="modal-input" placeholder="Make" required>
                            <label for="create-make">Make</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="create-model" class="modal-input" placeholder="Model" required>
                            <label for="create-model">Model</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="create-vin" class="modal-input" placeholder="VIN" required>
                            <label for="create-vin">VIN</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="create-color" class="modal-input" placeholder="Color" required>
                            <label for="create-color">Color</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="create-license-plate" class="modal-input" placeholder="License Plate" required>
                            <label for="create-license-plate">License Plate</label>
                        </div>
                        <div class="form-group">
                            <input type="number" id="create-year" class="modal-input" placeholder="Year" min="1900" max="2025" required>
                            <label for="create-year">Year</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="create-image-location" class="modal-input" placeholder="Image Location (Optional)">
                            <label for="create-image-location">Image Location (Optional)</label>
                        </div>
                        <button id="create-vehicle-save-btn">Create Vehicle</button>
                    </div>
                    <div id="create-modal-message"></div>
                </div>
            </div>

            <!-- Vehicle Issues Modal -->
            <div id="vehicle-issues-modal" class="modal">
                <div class="modal-content">
                    <span class="close-modal">&times</span>
                    <div class="vehicle-issues">
                        <h2 id="issues-modal-make-model"></h2>
                        <div class="form-group">
                            <select id="issue-type" class="modal-input" required>
                                <option value="" selected>Select Issue Type</option>
                                <option value="Brakes">Brakes</option>
                                <option value="Tires">Tires</option>
                                <option value="Engine">Engine</option>
                                <option value="Transmission">Transmission</option>
                                <option value="Suspension">Suspension</option>
                                <option value="Electrical">Electrical</option>
                                <option value="Custom">Custom</option>
                            </select>
                            <label for="issue-type">Issue Type</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="issue-description" class="modal-input" placeholder="Description (Optional)">
                            <label for="issue-description">Description (Optional)</label>
                        </div>
                        <button id="add-issue-btn">Add Issue</button>
                        <h3>Issues</h3>
                        <div id="issues-list"></div>
                        <div id="issues-modal-message"></div>
                    </div>
                </div>
            </div>

            <!-- Issue Resolution Modal -->
            <div id="issue-resolution-modal" class="modal">
                <div class="modal-content vehicle-issues-resolution">
                    <span class="close-modal">&times</span>
                    <h2 id="resolution-modal-title">Resolve Issue</h2>
                    <form id="resolution-form">
                        <div class="form-group">
                            <input type="text" id="resolution-work-done" class="modal-input" placeholder="Work Done" required>
                            <label for="resolution-work-done">Work Done</label>
                        </div>
                        <div class="form-group">
                            <input type="text" id="resolution-invoice-number" class="modal-input" placeholder="Invoice Number">
                            <label for="resolution-invoice-number">Invoice Number</label>
                        </div>
                        <div class="form-group">
                            <input type="number" id="resolution-labor-hours" class="modal-input" placeholder="Labor Hours" step="0.01" min="0" max="99.99">
                            <label for="resolution-labor-hours">Labor Hours</label>
                        </div>
                        <div class="form-group">
                            <input type="number" id="resolution-repair-cost" class="modal-input" placeholder="Repair Cost ($)" step="0.01" min="0" max="999999.99">
                            <label for="resolution-repair-cost">Repair Cost ($)</label>
                        </div>
                        <div class="form-group">
                            <textarea id="resolution-mechanic-notes" class="modal-input" placeholder="Mechanic Notes"></textarea>
                            <label for="resolution-mechanic-notes">Mechanic Notes</label>
                        </div>
                        <div class="form-group">
                            <select id="resolution-repair-category" class="modal-input" required>
                                <option value="" selected>Select Category</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Cosmetic">Cosmetic</option>
                                <option value="Performance">Performance</option>
                                <option value="Other">Other</option>
                            </select>
                            <label for="resolution-repair-category">Repair Category</label>
                        </div>
                        <label for="parts-replaced" class="section-label">Parts Replaced</label>
                        <div class="parts-checkboxes" id="parts-replaced"></div>
                        <button type="button" id="save-resolution-btn">Save Resolution</button>
                        <button type="button" id="edit-resolution-btn" style="display: none;">Update Resolution</button>
                        <div id="resolution-modal-message"></div>
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
        const initialUser = <?php echo $currentUser ? json_encode($currentUser, JSON_UNESCAPED_UNICODE) : 'null'; ?>;
        if (initialUser) {
            localStorage.setItem('currentUser', JSON.stringify(initialUser));
        } else {
            localStorage.removeItem('currentUser');
        }
        // Ensure CSRF token is set in localStorage
        const serverCsrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        localStorage.setItem('csrf_token', serverCsrfToken);
        console.log('Initial CSRF token set:', serverCsrfToken);
    </script>
    <script src="scripts/menu.js"></script>
    <script src="scripts/manage_vehicles.js"></script>
</body>
</html>