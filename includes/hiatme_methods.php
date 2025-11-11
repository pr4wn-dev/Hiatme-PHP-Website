<?php
// includes/hiatme_methods.php
class HiatmeMethods {
    private $dbConn;
    private $websiteName;
    private $randomKey;
    private $dbHost;
    private $dbUser;
    private $dbPass;
    private $dbName;
    private $usersTable;
    private $profilesTable;
    private $error_message;

public function __construct() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $this->websiteName = $_ENV['WEBSITE_NAME'];
    $this->randomKey = $_ENV['RANDOM_KEY'];
    $this->error_message = '';
    $this->usersTable = 'users';
    $this->profilesTable = 'profiles';
    $this->vehiclesTable = 'vehicles'; // Initialize here
    $this->ensureCSRFToken();
}

protected $vehiclesTable = 'vehicles';

public function ensureVehiclesTableExists() {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("ensureVehiclesTableExists: No database connection");
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $this->usersTable)) {
        $this->HandleError("Invalid users table name.");
        error_log("ensureVehiclesTableExists: Invalid users table name - {$this->usersTable}");
        return false;
    }

    // Verify users table exists
    $safeUsersTable = mysqli_real_escape_string($this->dbConn, $this->usersTable);
    $query = "SHOW TABLES LIKE '$safeUsersTable'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result === false || mysqli_num_rows($result) == 0) {
        $this->HandleError("Users table does not exist.");
        error_log("ensureVehiclesTableExists: Users table does not exist");
        return false;
    }

    // Check users.id column
    $query = "SHOW COLUMNS FROM {$this->usersTable} LIKE 'id'";
    $result = mysqli_query($this->dbConn, $query);
    if (!$result || mysqli_num_rows($result) == 0) {
        $this->HandleError("Users table missing id column.");
        error_log("ensureVehiclesTableExists: Users table missing id column");
        return false;
    }
    $column = mysqli_fetch_assoc($result);
    if ($column['Type'] !== 'int(11)' || strpos($column['Key'], 'PRI') === false) {
        $this->HandleError("Users table id column is not INT(11) PRIMARY KEY.");
        error_log("ensureVehiclesTableExists: Invalid id column type or key");
        return false;
    }

    $safeTable = mysqli_real_escape_string($this->dbConn, $this->vehiclesTable);
    $query = "SHOW TABLES LIKE '$safeTable'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result === false) {
        $this->HandleError("Failed to check vehicles table: " . mysqli_error($this->dbConn));
        error_log("ensureVehiclesTableExists: Failed to execute SHOW TABLES query: " . mysqli_error($this->dbConn));
        return false;
    }

    if (mysqli_num_rows($result) == 0) {
        $sql = "CREATE TABLE {$this->vehiclesTable} (
            vehicle_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            image_location VARCHAR(255) DEFAULT NULL,
            make VARCHAR(50) NOT NULL,
            model VARCHAR(50) NOT NULL,
            vin VARCHAR(17) NOT NULL,
            color VARCHAR(30) NOT NULL,
            license_plate VARCHAR(20) NOT NULL,
            year INT(4) NOT NULL,
            current_user_id INT(11) DEFAULT NULL,
            last_user_id INT(11) DEFAULT NULL,
            date_assigned TIMESTAMP DEFAULT NULL,
            date_last_used TIMESTAMP DEFAULT NULL,
            mileage DECIMAL(10,2) DEFAULT NULL,
            UNIQUE KEY vin (vin),
            UNIQUE KEY license_plate (license_plate),
            FOREIGN KEY (current_user_id) REFERENCES {$this->usersTable}(id) ON DELETE SET NULL,
            FOREIGN KEY (last_user_id) REFERENCES {$this->usersTable}(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!mysqli_query($this->dbConn, $sql)) {
            $this->HandleError("Failed to create vehicles table: " . mysqli_error($this->dbConn));
            error_log("ensureVehiclesTableExists: Failed to create vehicles table: " . mysqli_error($this->dbConn));
            return false;
        }
        error_log("ensureVehiclesTableExists: Vehicles table created successfully with mileage column");
    } else {
        // Check if mileage column exists
        $query = "SHOW COLUMNS FROM {$this->vehiclesTable} LIKE 'mileage'";
        $result = mysqli_query($this->dbConn, $query);
        if ($result === false) {
            $this->HandleError("Failed to check mileage column: " . mysqli_error($this->dbConn));
            error_log("ensureVehiclesTableExists: Failed to check mileage column: " . mysqli_error($this->dbConn));
            return false;
        }

        if (mysqli_num_rows($result) == 0) {
            // Add mileage column
            $sql = "ALTER TABLE {$this->vehiclesTable} ADD mileage DECIMAL(10,2) DEFAULT NULL";
            if (!mysqli_query($this->dbConn, $sql)) {
                $this->HandleError("Failed to add mileage column: " . mysqli_error($this->dbConn));
                error_log("ensureVehiclesTableExists: Failed to add mileage column: " . mysqli_error($this->dbConn));
                return false;
            }
            error_log("ensureVehiclesTableExists: Mileage column added successfully");
        }
    }

    return true;
}

private function ensureRewardsTableExists() {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("ensureRewardsTableExists: No database connection");
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $this->usersTable)) {
        $this->HandleError("Invalid users table name.");
        error_log("ensureRewardsTableExists: Invalid users table name - {$this->usersTable}");
        return false;
    }

    // Verify users table exists
    $safeUsersTable = mysqli_real_escape_string($this->dbConn, $this->usersTable);
    $query = "SHOW TABLES LIKE '$safeUsersTable'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result === false || mysqli_num_rows($result) == 0) {
        $this->HandleError("Users table does not exist.");
        error_log("ensureRewardsTableExists: Users table does not exist");
        return false;
    }

    // Check users.id column
    $query = "SHOW COLUMNS FROM {$this->usersTable} LIKE 'id'";
    $result = mysqli_query($this->dbConn, $query);
    if (!$result || mysqli_num_rows($result) == 0) {
        $this->HandleError("Users table missing id column.");
        error_log("ensureRewardsTableExists: Users table missing id column");
        return false;
    }
    $column = mysqli_fetch_assoc($result);
    if ($column['Type'] !== 'int(11)' || strpos($column['Key'], 'PRI') === false) {
        $this->HandleError("Users table id column is not INT(11) PRIMARY KEY.");
        error_log("ensureRewardsTableExists: Invalid id column type or key");
        return false;
    }

    $safeTable = mysqli_real_escape_string($this->dbConn, 'rewards');
    $query = "SHOW TABLES LIKE '$safeTable'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result === false) {
        $this->HandleError("Failed to check rewards table: " . mysqli_error($this->dbConn));
        error_log("ensureRewardsTableExists: Failed to execute SHOW TABLES query: " . mysqli_error($this->dbConn));
        return false;
    }

    if (mysqli_num_rows($result) == 0) {
        $sql = "CREATE TABLE rewards (
            reward_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            points INT(11) NOT NULL DEFAULT 0,
            reward_type VARCHAR(50) NOT NULL,
            status ENUM('active', 'inactive', 'redeemed') NOT NULL DEFAULT 'active',
            description TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES {$this->usersTable}(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!mysqli_query($this->dbConn, $sql)) {
            $this->HandleError("Failed to create rewards table: " . mysqli_error($this->dbConn));
            error_log("ensureRewardsTableExists: Failed to create rewards table: " . mysqli_error($this->dbConn));
            return false;
        }
        error_log("ensureRewardsTableExists: Rewards table created successfully");
    }

    return true;
}

private function ensureMileageTableExists() {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("ensureMileageTableExists: No database connection");
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $this->usersTable)) {
        $this->HandleError("Invalid users table name.");
        error_log("ensureMileageTableExists: Invalid users table name - {$this->usersTable}");
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $this->vehiclesTable)) {
        $this->HandleError("Invalid vehicles table name.");
        error_log("ensureMileageTableExists: Invalid vehicles table name - {$this->vehiclesTable}");
        return false;
    }

    // Verify users table exists
    $safeUsersTable = mysqli_real_escape_string($this->dbConn, $this->usersTable);
    $query = "SHOW TABLES LIKE '$safeUsersTable'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result === false || mysqli_num_rows($result) == 0) {
        $this->HandleError("Users table does not exist.");
        error_log("ensureMileageTableExists: Users table does not exist");
        return false;
    }

    // Verify vehicles table exists
    $safeVehiclesTable = mysqli_real_escape_string($this->dbConn, $this->vehiclesTable);
    $query = "SHOW TABLES LIKE '$safeVehiclesTable'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result === false || mysqli_num_rows($result) == 0) {
        $this->HandleError("Vehicles table does not exist.");
        error_log("ensureMileageTableExists: Vehicles table does not exist");
        return false;
    }

    // Check users.id column
    $query = "SHOW COLUMNS FROM {$this->usersTable} LIKE 'id'";
    $result = mysqli_query($this->dbConn, $query);
    if (!$result || mysqli_num_rows($result) == 0) {
        $this->HandleError("Users table missing id column.");
        error_log("ensureMileageTableExists: Users table missing id column");
        return false;
    }
    $column = mysqli_fetch_assoc($result);
    if ($column['Type'] !== 'int(11)' || strpos($column['Key'], 'PRI') === false) {
        $this->HandleError("Users table id column is not INT(11) PRIMARY KEY.");
        error_log("ensureMileageTableExists: Invalid users id column type or key");
        return false;
    }

    // Check vehicles.vehicle_id column
    $query = "SHOW COLUMNS FROM {$this->vehiclesTable} LIKE 'vehicle_id'";
    $result = mysqli_query($this->dbConn, $query);
    if (!$result || mysqli_num_rows($result) == 0) {
        $this->HandleError("Vehicles table missing vehicle_id column.");
        error_log("ensureMileageTableExists: Vehicles table missing vehicle_id column");
        return false;
    }
    $column = mysqli_fetch_assoc($result);
    if ($column['Type'] !== 'int(11)' || strpos($column['Key'], 'PRI') === false) {
        $this->HandleError("Vehicles table vehicle_id column is not INT(11) PRIMARY KEY.");
        error_log("ensureMileageTableExists: Invalid vehicle_id column type or key");
        return false;
    }

    $mileageTable = 'mileage_records';
    $safeTable = mysqli_real_escape_string($this->dbConn, $mileageTable);
    $query = "SHOW TABLES LIKE '$safeTable'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result === false) {
        $this->HandleError("Failed to check mileage table: " . mysqli_error($this->dbConn));
        error_log("ensureMileageTableExists: Failed to execute SHOW TABLES query: " . mysqli_error($this->dbConn));
        return false;
    }

    if (mysqli_num_rows($result) == 0) {
        $sql = "CREATE TABLE mileage_records (
            mileage_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            start_miles DECIMAL(10,2) DEFAULT NULL,
            start_miles_datetime DATETIME DEFAULT NULL,
            ending_miles DECIMAL(10,2) DEFAULT NULL,
            ending_miles_datetime DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES {$this->vehiclesTable}(vehicle_id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES {$this->usersTable}(id) ON DELETE CASCADE,
            INDEX idx_vehicle_id (vehicle_id),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!mysqli_query($this->dbConn, $sql)) {
            $this->HandleError("Failed to create mileage_records table: " . mysqli_error($this->dbConn));
            error_log("ensureMileageTableExists: Failed to create mileage_records table: " . mysqli_error($this->dbConn));
            return false;
        }
        error_log("ensureMileageTableExists: Mileage_records table created successfully with created_at column");
    } else {
        // Check if created_at column exists
        $query = "SHOW COLUMNS FROM mileage_records LIKE 'created_at'";
        $result = mysqli_query($this->dbConn, $query);
        if ($result === false) {
            $this->HandleError("Failed to check created_at column: " . mysqli_error($this->dbConn));
            error_log("ensureMileageTableExists: Failed to check created_at column: " . mysqli_error($this->dbConn));
            return false;
        }
        if (mysqli_num_rows($result) == 0) {
            // Add created_at column
            $sql = "ALTER TABLE mileage_records ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP";
            if (!mysqli_query($this->dbConn, $sql)) {
                $this->HandleError("Failed to add created_at column: " . mysqli_error($this->dbConn));
                error_log("ensureMileageTableExists: Failed to add created_at column: " . mysqli_error($this->dbConn));
                return false;
            }
            error_log("ensureMileageTableExists: Added created_at column successfully");
        }

        // Check and modify start_miles column to allow NULL
        $query = "SHOW COLUMNS FROM mileage_records LIKE 'start_miles'";
        $result = mysqli_query($this->dbConn, $query);
        if ($result === false) {
            $this->HandleError("Failed to check start_miles column: " . mysqli_error($this->dbConn));
            error_log("ensureMileageTableExists: Failed to check start_miles column: " . mysqli_error($this->dbConn));
            return false;
        }
        $column = mysqli_fetch_assoc($result);
        if ($column['Null'] === 'NO') {
            $sql = "ALTER TABLE mileage_records MODIFY start_miles DECIMAL(10,2) DEFAULT NULL";
            if (!mysqli_query($this->dbConn, $sql)) {
                $this->HandleError("Failed to modify start_miles to allow NULL: " . mysqli_error($this->dbConn));
                error_log("ensureMileageTableExists: Failed to modify start_miles: " . mysqli_error($this->dbConn));
                return false;
            }
            error_log("ensureMileageTableExists: Modified start_miles to allow NULL");
        }

        // Check and modify start_miles_datetime column to allow NULL
        $query = "SHOW COLUMNS FROM mileage_records LIKE 'start_miles_datetime'";
        $result = mysqli_query($this->dbConn, $query);
        if ($result === false) {
            $this->HandleError("Failed to check start_miles_datetime column: " . mysqli_error($this->dbConn));
            error_log("ensureMileageTableExists: Failed to check start_miles_datetime column: " . mysqli_error($this->dbConn));
            return false;
        }
        $column = mysqli_fetch_assoc($result);
        if ($column['Null'] === 'NO' && $column['Default'] === 'CURRENT_TIMESTAMP') {
            $sql = "ALTER TABLE mileage_records MODIFY start_miles_datetime DATETIME DEFAULT NULL";
            if (!mysqli_query($this->dbConn, $sql)) {
                $this->HandleError("Failed to modify start_miles_datetime to allow NULL: " . mysqli_error($this->dbConn));
                error_log("ensureMileageTableExists: Failed to modify start_miles_datetime: " . mysqli_error($this->dbConn));
                return false;
            }
            error_log("ensureMileageTableExists: Modified start_miles_datetime to allow NULL");
        }

        // Check and modify ending_miles column to allow NULL
        $query = "SHOW COLUMNS FROM mileage_records LIKE 'ending_miles'";
        $result = mysqli_query($this->dbConn, $query);
        if ($result === false) {
            $this->HandleError("Failed to check ending_miles column: " . mysqli_error($this->dbConn));
            error_log("ensureMileageTableExists: Failed to check ending_miles column: " . mysqli_error($this->dbConn));
            return false;
        }
        $column = mysqli_fetch_assoc($result);
        if ($column['Null'] === 'NO') {
            $sql = "ALTER TABLE mileage_records MODIFY ending_miles DECIMAL(10,2) DEFAULT NULL";
            if (!mysqli_query($this->dbConn, $sql)) {
                $this->HandleError("Failed to modify ending_miles to allow NULL: " . mysqli_error($this->dbConn));
                error_log("ensureMileageTableExists: Failed to modify ending_miles: " . mysqli_error($this->dbConn));
                return false;
            }
            error_log("ensureMileageTableExists: Modified ending_miles to allow NULL");
        }

        // Check and modify ending_miles_datetime column to allow NULL
        $query = "SHOW COLUMNS FROM mileage_records LIKE 'ending_miles_datetime'";
        $result = mysqli_query($this->dbConn, $query);
        if ($result === false) {
            $this->HandleError("Failed to check ending_miles_datetime column: " . mysqli_error($this->dbConn));
            error_log("ensureMileageTableExists: Failed to check ending_miles_datetime column: " . mysqli_error($this->dbConn));
            return false;
        }
        $column = mysqli_fetch_assoc($result);
        if ($column['Null'] === 'NO') {
            $sql = "ALTER TABLE mileage_records MODIFY ending_miles_datetime DATETIME DEFAULT NULL";
            if (!mysqli_query($this->dbConn, $sql)) {
                $this->HandleError("Failed to modify ending_miles_datetime to allow NULL: " . mysqli_error($this->dbConn));
                error_log("ensureMileageTableExists: Failed to modify ending_miles_datetime: " . mysqli_error($this->dbConn));
                return false;
            }
            error_log("ensureMileageTableExists: Modified ending_miles_datetime to allow NULL");
        }
    }

    return true;
}

private function ensureUsersTableExists() {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("ensureUsersTableExists: No database connection");
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $this->usersTable)) {
        $this->HandleError("Invalid users table name.");
        error_log("ensureUsersTableExists: Invalid table name - {$this->usersTable}");
        return false;
    }

    $safeTable = mysqli_real_escape_string($this->dbConn, $this->usersTable);
    $query = "SHOW TABLES LIKE '$safeTable'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result === false) {
        $this->HandleError("Failed to check users table: " . mysqli_error($this->dbConn));
        error_log("ensureUsersTableExists: Failed to execute SHOW TABLES query: " . mysqli_error($this->dbConn));
        return false;
    }

    if (mysqli_num_rows($result) == 0) {
        $sql = "CREATE TABLE {$this->usersTable} (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            password VARCHAR(255) NOT NULL,
            verification_token VARCHAR(255) DEFAULT NULL,
            is_verified TINYINT(1) DEFAULT 0,
            role ENUM('Client', 'Driver', 'Manager', 'Owner') NOT NULL DEFAULT 'Client',
            reset_token VARCHAR(255) DEFAULT NULL,
            reset_token_expiry TIMESTAMP NULL DEFAULT NULL,
            auth_token VARCHAR(255) DEFAULT NULL,
            last_login DATETIME DEFAULT NULL,
            onesignal_player_id VARCHAR(36) DEFAULT NULL,
            first_login DATETIME DEFAULT NULL,
            UNIQUE KEY email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!mysqli_query($this->dbConn, $sql)) {
            $this->HandleError("Failed to create users table: " . mysqli_error($this->dbConn));
            error_log("ensureUsersTableExists: Failed to create users table: " . mysqli_error($this->dbConn));
            return false;
        }
        error_log("ensureUsersTableExists: Users table created successfully");
    } else {
        // Ensure last_login and first_login are DATETIME
        $columns = ['last_login', 'first_login'];
        foreach ($columns as $column) {
            $query = "SHOW COLUMNS FROM {$this->usersTable} LIKE '$column'";
            $result = mysqli_query($this->dbConn, $query);
            if ($result && mysqli_num_rows($result) > 0) {
                $col_info = mysqli_fetch_assoc($result);
                if (strtoupper($col_info['Type']) !== 'DATETIME') {
                    $sql = "ALTER TABLE {$this->usersTable} MODIFY $column DATETIME DEFAULT NULL";
                    if (!mysqli_query($this->dbConn, $sql)) {
                        $this->HandleError("Failed to modify $column to DATETIME: " . mysqli_error($this->dbConn));
                        error_log("ensureUsersTableExists: Failed to modify $column to DATETIME: " . mysqli_error($this->dbConn));
                        return false;
                    }
                    error_log("ensureUsersTableExists: Modified $column to DATETIME");
                }
            }
        }
    }

    return true;
}

private function ensureProfilesTableExists() {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("ensureProfilesTableExists: No database connection");
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $this->profilesTable)) {
        $this->HandleError("Invalid profiles table name.");
        error_log("ensureProfilesTableExists: Invalid table name - {$this->profilesTable}");
        return false;
    }

    // Verify users table exists
    $safeUsersTable = mysqli_real_escape_string($this->dbConn, $this->usersTable);
    $query = "SHOW TABLES LIKE '$safeUsersTable'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result === false || mysqli_num_rows($result) == 0) {
        $this->HandleError("Users table does not exist.");
        error_log("ensureProfilesTableExists: Users table does not exist");
        return false;
    }

    // Check users.id column
    $query = "SHOW COLUMNS FROM {$this->usersTable} LIKE 'id'";
    $result = mysqli_query($this->dbConn, $query);
    if (!$result || mysqli_num_rows($result) == 0) {
        $this->HandleError("Users table missing id column.");
        error_log("ensureProfilesTableExists: Users table missing id column");
        return false;
    }
    $column = mysqli_fetch_assoc($result);
    if ($column['Type'] !== 'int(11)' || strpos($column['Key'], 'PRI') === false) {
        $this->HandleError("Users table id column is not INT(11) PRIMARY KEY.");
        error_log("ensureProfilesTableExists: Invalid id column type or key");
        return false;
    }

    $safeTable = mysqli_real_escape_string($this->dbConn, $this->profilesTable);
    $query = "SHOW TABLES LIKE '$safeTable'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result === false) {
        $this->HandleError("Failed to check profiles table: " . mysqli_error($this->dbConn));
        error_log("ensureProfilesTableExists: Failed to execute SHOW TABLES query: " . mysqli_error($this->dbConn));
        return false;
    }

    if (mysqli_num_rows($result) == 0) {
        $sql = "CREATE TABLE {$this->profilesTable} (
            profile_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            profile_picture VARCHAR(255) DEFAULT NULL,
            location VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES {$this->usersTable}(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!mysqli_query($this->dbConn, $sql)) {
            $this->HandleError("Failed to create profiles table: " . mysqli_error($this->dbConn));
            error_log("ensureProfilesTableExists: Failed to create profiles table: " . mysqli_error($this->dbConn));
            return false;
        }
        error_log("ensureProfilesTableExists: Profiles table created successfully");
    }

    return true;
}

private function ensureRateLimitsTableExists() {
        $tableName = 'rate_limits';
        if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $tableName)) {
            error_log("ensureRateLimitsTableExists: Invalid table name - $tableName");
            die(json_encode(['success' => false, 'message' => 'Invalid rate_limits table name']));
        }
        $safeTable = mysqli_real_escape_string($this->dbConn, $tableName);
        $query = "SHOW TABLES LIKE '$safeTable'";
        $result = mysqli_query($this->dbConn, $query);
        if ($result === false) {
            error_log("ensureRateLimitsTableExists: Failed to execute SHOW TABLES query: " . mysqli_error($this->dbConn));
            die(json_encode(['success' => false, 'message' => 'Failed to check rate_limits table: ' . mysqli_error($this->dbConn)]));
        }

        if (mysqli_num_rows($result) == 0) {
            $sql = "CREATE TABLE $tableName (
                id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                rate_key VARCHAR(255) NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                request_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )";
            if (!mysqli_query($this->dbConn, $sql)) {
                error_log("Failed to create rate_limits table: " . mysqli_error($this->dbConn));
                die(json_encode(['success' => false, 'message' => 'Rate limits table creation failed: ' . mysqli_error($this->dbConn)]));
            }
            error_log("ensureRateLimitsTableExists: Rate_limits table created successfully");
        }
}

public function SetWebsiteName($name) {
        $this->websiteName = $name;
}

public function SetRandomKey($key) {
        $this->randomKey = $key;
}

private function sanitize($str) {
        return mysqli_real_escape_string($this->dbConn, trim($str));
}

public function InitDB($host, $user, $pass, $dbname, $usersTable = 'users', $profilesTable = 'profiles') {
    $this->dbHost = $host;
    $this->dbUser = $user;
    $this->dbPass = $pass;
    $this->dbName = $dbname;

    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $usersTable)) {
        error_log("InitDB: Invalid users table name - $usersTable");
        die(json_encode(['success' => false, 'message' => 'Invalid users table name']));
    }
    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $profilesTable)) {
        error_log("InitDB: Invalid profiles table name - $profilesTable");
        die(json_encode(['success' => false, 'message' => 'Invalid profiles table name']));
    }

    $this->usersTable = $usersTable;
    $this->profilesTable = $profilesTable;
    $this->connectDB();

    if (!$this->ensureUsersTableExists()) {
        error_log("InitDB: Failed to create users table");
        die(json_encode(['success' => false, 'message' => 'Failed to create users table']));
    }

    if (!$this->ensureProfilesTableExists()) {
        error_log("InitDB: Failed to create profiles table");
        die(json_encode(['success' => false, 'message' => 'Failed to create profiles table']));
    }

    if (!$this->ensureVehiclesTableExists()) {
        error_log("InitDB: Failed to create vehicles table");
        die(json_encode(['success' => false, 'message' => 'Failed to create vehicles table']));
    }

    if (!$this->createVehicleIssuesTable()) {
        error_log("InitDB: Failed to create vehicle_issues table");
        die(json_encode(['success' => false, 'message' => 'Failed to create vehicle_issues table']));
    }

    if (!$this->createIssueResolutionsTable()) {
        error_log("InitDB: Failed to create issue_resolutions table");
        die(json_encode(['success' => false, 'message' => 'Failed to create issue_resolutions table']));
    }

    if (!$this->ensureMileageTableExists()) {
        error_log("InitDB: Failed to create mileage_records table");
        die(json_encode(['success' => false, 'message' => 'Failed to create mileage_records table']));
    }

    if (!$this->ensureRewardsTableExists()) {
        error_log("InitDB: Failed to create rewards table");
        die(json_encode(['success' => false, 'message' => 'Failed to create rewards table']));
    }

    $query = "SHOW COLUMNS FROM vehicle_issues LIKE 'resolution_id'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $query = "SELECT CONSTRAINT_NAME 
                  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                  WHERE TABLE_NAME = 'vehicle_issues' 
                  AND COLUMN_NAME = 'resolution_id' 
                  AND REFERENCED_TABLE_NAME = 'issue_resolutions'";
        $result = mysqli_query($this->dbConn, $query);
        if ($result && mysqli_num_rows($result) == 0) {
            $sql = "ALTER TABLE vehicle_issues 
                    ADD CONSTRAINT fk_vehicle_issues_resolution_id 
                    FOREIGN KEY (resolution_id) 
                    REFERENCES issue_resolutions(resolution_id) 
                    ON DELETE SET NULL";
            if (!mysqli_query($this->dbConn, $sql)) {
                error_log("InitDB: Failed to add resolution_id foreign key: " . mysqli_error($this->dbConn));
                die(json_encode(['success' => false, 'message' => 'Failed to add resolution_id foreign key']));
            }
            error_log("InitDB: Added resolution_id foreign key to vehicle_issues");
        }
    }
}

private function connectDB() {
    $this->dbConn = mysqli_connect($this->dbHost, $this->dbUser, $this->dbPass, $this->dbName);
    if (!$this->dbConn) {
        error_log("connectDB: Failed to connect to database - " . mysqli_connect_error());
        die(json_encode(['success' => false, 'message' => 'DB connection failed: ' . mysqli_connect_error()]));
    }
    mysqli_query($this->dbConn, "SET NAMES 'UTF8'");
    mysqli_query($this->dbConn, "SET time_zone = '-04:00'");
    date_default_timezone_set('America/New_York'); // Ensure PHP matches DB timezone (UTC-04:00)
    error_log("connectDB: Database connection successful for host=$this->dbHost, user=$this->dbUser, db=$this->dbName, timezone set to America/New_York");
}







private function validatePasswordStrength($password) {
        if (strlen($password) < 8) {
            $this->HandleError("Password must be at least 8 characters long.");
            return false;
        }
        if (!preg_match("/[A-Za-z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[^A-Za-z0-9]/", $password)) {
            $this->HandleError("Password must contain at least one letter, one number, and one special character.");
            return false;
        }
        return true;
}

private function hashPassword($password) {
        $options = ['cost' => 12];
        return password_hash($password, PASSWORD_DEFAULT, $options);
}

private function GenerateRateLimitKey($action, $identifier) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
    $clientIp = $_SERVER['HTTP_CLIENT_IP'] ?? null;

    if ($forwarded) {
        $ipList = explode(',', $forwarded);
        $clientIp = trim($ipList[0]);
        $ip = filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : $ip;
    } elseif ($clientIp) {
        $ip = filter_var($clientIp, FILTER_VALIDATE_IP) ? $clientIp : $ip;
    }

    $sessionId = session_id() ?: 'no_session';
    $key = "{$action}_{$identifier}_{$ip}_{$sessionId}";
    error_log("GenerateRateLimitKey: Generated key for action $action, identifier $identifier: $key (IP: $ip, Session: $sessionId)");
    return $key;
}

private function checkRateLimit($key, $type, $maxRequests, $timeWindow = 900) {
    $this->ensureRateLimitsTableExists();

    $query = "INSERT INTO rate_limits (rate_key, action_type) VALUES (?, ?)";
    $stmt = mysqli_prepare($this->dbConn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $key, $type);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $query = "SELECT COUNT(*) as count FROM rate_limits WHERE rate_key = ? AND action_type = ? AND request_time > NOW() - INTERVAL ? SECOND";
    $stmt = mysqli_prepare($this->dbConn, $query);
    mysqli_stmt_bind_param($stmt, "ssi", $key, $type, $timeWindow);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $count = $row['count'];
    mysqli_stmt_close($stmt);

    if ($count >= $maxRequests) {
        error_log("checkRateLimit: Rate limit exceeded for key=$key, type=$type, count=$count, max=$maxRequests");
        return false;
    }
    error_log("checkRateLimit: Request allowed for key=$key, type=$type, count=$count, max=$maxRequests");
    return true;
}

private function sendVerificationEmail($email, $token) {
        try {
            $verifyLink = "https://{$this->websiteName}/verify.php?token=" . urlencode($token);
            $subject = 'Verify Your Hiatme Account';
            $message = "Welcome! Please click to verify your account: $verifyLink";
            $headers = "From: Hiatme Team <noreply@{$this->websiteName}>\r\n";
            $headers .= "Reply-To: noreply@{$this->websiteName}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            error_log("sendVerificationEmail: Attempting to send email to $email with token $token");
            if (mail($email, $subject, $message, $headers)) {
                error_log("sendVerificationEmail: Email sent successfully to $email");
                return true;
            } else {
                $this->HandleError("Email failed: Unable to send via mail().");
                error_log("sendVerificationEmail: Failed to send email to $email - mail() returned false");
                return false;
            }
        } catch (Exception $e) {
            $this->HandleError("Email failed: {$e->getMessage()}");
            error_log("sendVerificationEmail: Exception while sending email to $email: " . $e->getMessage());
            return false;
        }
}

private function sendPasswordResetEmail($email, $token) {
        try {
            $resetLink = "https://{$this->websiteName}/index.php?reset_token=" . urlencode($token);
            $subject = 'Reset Your Hiatme Password';
            $message = "You requested a password reset. Click the link to reset your password: $resetLink\n\nIf you did not request this, please ignore this email.";
            $headers = "From: Hiatme Team <noreply@{$this->websiteName}>\r\n";
            $headers .= "Reply-To: noreply@{$this->websiteName}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            error_log("sendPasswordResetEmail: Attempting to send email to $email with token $token");
            if (mail($email, $subject, $message, $headers)) {
                error_log("sendPasswordResetEmail: Email sent successfully to $email");
                return true;
            } else {
                $this->HandleError("Email failed: Unable to send via mail().");
                error_log("sendPasswordResetEmail: Failed to send email to $email - mail() returned false");
                return false;
            }
        } catch (Exception $e) {
            $this->HandleError("Email failed: {$e->getMessage()}");
            error_log("sendPasswordResetEmail: Exception while sending email to $email: " . $e->getMessage());
            return false;
        }
}

public function RequestPasswordReset($email) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("RequestPasswordReset: Database connection lost for email - $email");
        return false;
    }

    $email = $this->sanitize($email);
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email) || strlen($email) > 255) {
        $this->HandleError("Invalid email format or too long (max 255 characters).");
        error_log("Invalid email format detected: $email");
        return false;
    }

    $rateLimitKey = $this->GenerateRateLimitKey('password_reset', $email);
    if (!$this->checkRateLimit($rateLimitKey, 'password_reset', 3, 900)) {
        $this->HandleError("Too many password reset requests. Please try again later.");
        error_log("RequestPasswordReset: Rate limit exceeded for key - $rateLimitKey");
        return false;
    }

    $query = "SHOW COLUMNS FROM {$this->usersTable} LIKE 'reset_token'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result === false || mysqli_num_rows($result) == 0) {
        $this->HandleError("Reset token column missing in users table.");
        return false;
    }

    $query = "SELECT id FROM {$this->usersTable} WHERE email = ? AND is_verified = 1";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare statement: " . mysqli_error($this->dbConn));
        error_log("RequestPasswordReset: Failed to prepare statement for email - $email: " . mysqli_error($this->dbConn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Database query failed: " . mysqli_stmt_error($stmt));
        error_log("RequestPasswordReset: Database query failed for email - $email: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) == 0) {
        $this->HandleError("No verified account found with that email.");
        error_log("RequestPasswordReset: No verified account found for email - $email");
        mysqli_stmt_close($stmt);
        return false;
    }

    mysqli_stmt_close($stmt);

    $maxAttempts = 5;
    $attempt = 0;
    do {
        $token = bin2hex(random_bytes(16));
        $query = "SELECT COUNT(*) FROM {$this->usersTable} WHERE reset_token = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if (!$stmt) {
            $this->HandleError("Failed to prepare token check: " . mysqli_error($this->dbConn));
            return false;
        }
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count = mysqli_fetch_row($result)[0];
        mysqli_stmt_close($stmt);
        $attempt++;
    } while ($count > 0 && $attempt < $maxAttempts);

    if ($attempt >= $maxAttempts) {
        $this->HandleError("Failed to generate unique reset token.");
        return false;
    }

    $expiry = gmdate('Y-m-d H:i:s', time() - 4 * 3600 + 3600); // UTC-04:00, 1 hour expiry
    $query = "UPDATE {$this->usersTable} SET reset_token = ?, reset_token_expiry = ? WHERE email = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare reset token statement: " . mysqli_error($this->dbConn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, "sss", $token, $expiry, $email);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Failed to generate reset token: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    mysqli_stmt_close($stmt);
    error_log("RequestPasswordReset: Reset token generated for email - $email, Token: $token, Expiry: $expiry");

    if (!$this->sendPasswordResetEmail($email, $token)) {
        $this->HandleError("Failed to send password reset email.");
        return false;
    }

    return true;
}

public function ResetPassword($token, $newPassword) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        return false;
    }

    $token = $this->sanitize($token);
    $query = "SELECT id, email, reset_token_expiry FROM {$this->usersTable} WHERE reset_token = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare statement: " . mysqli_error($this->dbConn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $token);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Database query failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) == 0) {
        $this->HandleError("Invalid or expired reset token.");
        mysqli_stmt_close($stmt);
        return false;
    }

    $row = mysqli_fetch_assoc($result);
    $userId = $row['id'];
    $email = $row['email'];
    $expiry = strtotime($row['reset_token_expiry']);
    mysqli_stmt_close($stmt);

    if ($expiry && time() > $expiry) {
        $this->HandleError("Reset token has expired.");
        return false;
    }

    if (!$this->validatePasswordStrength($newPassword)) {
        return false;
    }
    $hashedPassword = $this->hashPassword($newPassword);
    $query = "UPDATE {$this->usersTable} SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?";

    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare password update statement: " . mysqli_error($this->dbConn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, "si", $hashedPassword, $userId);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Failed to update password: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    mysqli_stmt_close($stmt);

    $query = "SELECT u.*, p.name FROM {$this->usersTable} u 
              LEFT JOIN {$this->profilesTable} p ON u.id = p.user_id 
              WHERE u.email = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare user fetch statement: " . mysqli_error($this->dbConn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Failed to fetch user details: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        $userRow = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return [
            'success' => true,
            'email' => $email,
            'name' => $userRow['name'] ?? 'Unknown'
        ];
    } else {
        $this->HandleError("Failed to fetch user details after password reset.");
        mysqli_stmt_close($stmt);
        return false;
    }
}

public function UpdateProfile($currentEmail, $name, $email, $phone, $profilePicture) {
        if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
            $this->HandleError("Database connection lost.");
            return false;
        }

        $this->ensureUsersTableExists();
        $this->ensureProfilesTableExists();

        $name = $this->sanitize($name);
        $email = $this->sanitize($email);
        $phone = $this->sanitize($phone);
        $currentEmail = $this->sanitize($currentEmail);

        if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email) || strlen($email) > 255) {
            $this->HandleError("Invalid email format or too long (max 255 characters).");
            return false;
        }

        if (strlen($name) > 100) {
            $this->HandleError("Full name must be 100 characters or less.");
            return false;
        }
        if ($phone && !preg_match("/^[0-9]{7,20}$/", $phone)) {
            $this->HandleError("Phone number must be 7-20 digits if provided.");
            return false;
        }

        if ($email !== $currentEmail) {
            $query = "SELECT email FROM {$this->usersTable} WHERE email = ?";
            $stmt = mysqli_prepare($this->dbConn, $query);
            if ($stmt === false) {
                $this->HandleError("Failed to prepare email check statement: " . mysqli_error($this->dbConn));
                return false;
            }

            mysqli_stmt_bind_param($stmt, "s", $email);
            if (!mysqli_stmt_execute($stmt)) {
                $this->HandleError("Failed to check email: " . mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
                return false;
            }

            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($result) > 0) {
                $this->HandleError("Email already taken.");
                mysqli_stmt_close($stmt);
                return false;
            }
            mysqli_stmt_close($stmt);
        }

        $query = "SELECT id FROM {$this->usersTable} WHERE email = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare user ID fetch statement: " . mysqli_error($this->dbConn));
            return false;
        }

        mysqli_stmt_bind_param($stmt, "s", $currentEmail);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Failed to fetch user ID: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }

        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) == 0) {
            $this->HandleError("User not found.");
            mysqli_stmt_close($stmt);
            return false;
        }

        $row = mysqli_fetch_assoc($result);
        $userId = $row['id'];
        mysqli_stmt_close($stmt);

        $profilePicturePath = null;
        if (!empty($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
            $profilePicture = $_FILES['profile_picture'];
            $ext = strtolower(pathinfo($profilePicture['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $this->HandleError("Invalid file extension. Only JPG, JPEG, PNG, and GIF are allowed.");
                return false;
            }

            if ($profilePicture['size'] === 0) {
                $this->HandleError("File is empty: Please upload a valid image.");
                return false;
            }

            $uploadDir = __DIR__ . '/../private/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true) || die(json_encode(['success' => false, 'message' => 'Failed to create upload directory']));
            }
            if (!is_writable($uploadDir)) {
                die(json_encode(['success' => false, 'message' => 'Upload directory not writable']));
            }

            if ($profilePicture['error'] !== UPLOAD_ERR_OK) {
                $this->HandleError("File upload error: " . $profilePicture['error']);
                return false;
            }

            if ($profilePicture['size'] > 5 * 1024 * 1024) {
                $this->HandleError("Image size must be less than 5MB.");
                return false;
            }

            $mimeType = mime_content_type($profilePicture['tmp_name']);
            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
            if (!array_key_exists($mimeType, $allowedTypes)) {
                $this->HandleError("Only JPEG, PNG, and GIF images are allowed.");
                return false;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $fileHandle = fopen($profilePicture['tmp_name'], 'rb');
            $magicBytes = fread($fileHandle, 8);
            fclose($fileHandle);
            $isValidMagic = false;
            if ($mimeType === 'image/jpeg') {
                $magicBytesHex = bin2hex($magicBytes);
                $isValidMagic = (substr($magicBytesHex, 0, 4) === 'ffd8');
            } elseif ($mimeType === 'image/png') {
                $magicBytesHex = bin2hex($magicBytes);
                $isValidMagic = (substr($magicBytesHex, 0, 8) === '89504e47');
            } elseif ($mimeType === 'image/gif') {
                $magicBytesHex = bin2hex($magicBytes);
                $isValidMagic = (substr($magicBytesHex, 0, 6) === '474946');
            }
            if (!$isValidMagic) {
                $this->HandleError("Invalid image file: File contents do not match the expected format.");
                return false;
            }

            try {
                switch ($mimeType) {
                    case 'image/jpeg':
                        $image = imagecreatefromjpeg($profilePicture['tmp_name']);
                        break;
                    case 'image/png':
                        $image = imagecreatefrompng($profilePicture['tmp_name']);
                        break;
                    case 'image/gif':
                        $image = imagecreatefromgif($profilePicture['tmp_name']);
                        break;
                    default:
                        throw new Exception("Unsupported image type");
                }
                if ($image === false) {
                    $this->HandleError("Invalid image: Could not process file.");
                    return false;
                }

                $width = imagesx($image);
                $height = imagesy($image);
                if ($width <= 0 || $height <= 0) {
                    $this->HandleError("Invalid image: No valid dimensions.");
                    imagedestroy($image);
                    return false;
                }

                $filename = hash('sha256', time() . random_bytes(16)) . '.' . $ext;
                $profilePicturePath = $uploadDir . $filename;

                switch ($mimeType) {
                    case 'image/jpeg':
                        imagejpeg($image, $profilePicturePath, 85);
                        break;
                    case 'image/png':
                        imagepng($image, $profilePicturePath, 9);
                        break;
                    case 'image/gif':
                        imagegif($image, $profilePicturePath);
                        break;
                }
                imagedestroy($image);

                chmod($profilePicturePath, 0644);

                if (!file_exists($profilePicturePath) || filesize($profilePicturePath) < 100) {
                    $this->HandleError("Failed to save processed image or file is corrupt.");
                    if (file_exists($profilePicturePath)) unlink($profilePicturePath);
                    return false;
                }

                $profilePicturePath = "https://{$this->websiteName}/serve_upload.php?file=" . $filename;
            } catch (Exception $e) {
                $this->HandleError("Image processing failed: " . $e->getMessage());
                return false;
            }
        }

        mysqli_begin_transaction($this->dbConn);
        try {
            $query = "UPDATE {$this->usersTable} SET email = ? WHERE id = ?";
            $stmt = mysqli_prepare($this->dbConn, $query);
            if ($stmt === false) {
                $this->HandleError("Failed to prepare email update statement: " . mysqli_error($this->dbConn));
                throw new Exception("Email update failed");
            }

            mysqli_stmt_bind_param($stmt, "si", $email, $userId);
            if (!mysqli_stmt_execute($stmt)) {
                $this->HandleError("Failed to update email: " . mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
                throw new Exception("Email update failed");
            }
            mysqli_stmt_close($stmt);

            $query = "UPDATE {$this->profilesTable} SET name = ?, email = ?, phone = ?" . ($profilePicturePath ? ", profile_picture = ?" : "") . " WHERE user_id = ?";
            $stmt = mysqli_prepare($this->dbConn, $query);
            if ($stmt === false) {
                $this->HandleError("Failed to prepare profile update statement: " . mysqli_error($this->dbConn));
                throw new Exception("Profile update failed");
            }

            if ($profilePicturePath) {
                mysqli_stmt_bind_param($stmt, "ssssi", $name, $email, $phone, $profilePicturePath, $userId);
            } else {
                mysqli_stmt_bind_param($stmt, "sssi", $name, $email, $phone, $userId);
            }

            if (!mysqli_stmt_execute($stmt)) {
                $this->HandleError("Failed to update profile: " . mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
                throw new Exception("Profile update failed");
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($this->dbConn);

            $query = "SELECT u.email, p.name, p.phone, p.profile_picture 
                      FROM {$this->usersTable} u 
                      LEFT JOIN {$this->profilesTable} p ON u.id = p.user_id 
                      WHERE u.id = ?";
            $stmt = mysqli_prepare($this->dbConn, $query);
            if ($stmt === false) {
                $this->HandleError("Failed to prepare user fetch statement: " . mysqli_error($this->dbConn));
                return false;
            }

            mysqli_stmt_bind_param($stmt, "i", $userId);
            if (!mysqli_stmt_execute($stmt)) {
                $this->HandleError("Failed to fetch updated user data: " . mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
                return false;
            }

            $result = mysqli_stmt_get_result($stmt);
            if (mysqli_num_rows($result) > 0) {
                $userRow = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                return [
                    'success' => true,
                    'user' => [
                        'email' => $userRow['email'],
                        'name' => $userRow['name'] ?? 'Unknown',
                        'phone' => $userRow['phone'] ?? '',
                        'profile_picture' => $userRow['profile_picture'] ?? ''
                    ]
                ];
            } else {
                $this->HandleError("Failed to fetch updated user data.");
                mysqli_stmt_close($stmt);
                return false;
            }
        } catch (Exception $e) {
            mysqli_rollback($this->dbConn);
            return false;
        }
}

public function RegisterUser($name, $email, $phone, $password) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        return false;
    }

    $this->ensureUsersTableExists();
    $this->ensureProfilesTableExists();
    $this->ensureRateLimitsTableExists();

    $email = $this->sanitize($email);
    $name = $this->sanitize($name);
    $phone = $this->sanitize($phone);

    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email) || strlen($email) > 255) {
        $this->HandleError("Invalid email format or too long (max 255 characters).");
        return false;
    }

    if (strlen($name) > 100) {
        $this->HandleError("Full name must be 100 characters or less.");
        return false;
    }
    if ($phone && !preg_match("/^[0-9]{7,20}$/", $phone)) {
        $this->HandleError("Phone number must be 7-20 digits if provided.");
        return false;
    }

    mysqli_begin_transaction($this->dbConn);
    try {
        $query = "SELECT email FROM {$this->usersTable} WHERE email = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare email check statement: " . mysqli_error($this->dbConn));
            throw new Exception("Email check failed");
        }

        mysqli_stmt_bind_param($stmt, "s", $email);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Failed to check email: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Email check failed");
        }

        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            $this->HandleError("Email already taken.");
            mysqli_stmt_close($stmt);
            throw new Exception("Duplicate email");
        }
        mysqli_stmt_close($stmt);

        if (!$this->validatePasswordStrength($password)) {
            return false;
        }

        $hashedPassword = $this->hashPassword($password);
        $token = bin2hex(random_bytes(16));
        $role = 'Client'; // Default role for new users
        $query = "INSERT INTO {$this->usersTable} (email, password, verification_token, role) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare user insert statement: " . mysqli_error($this->dbConn));
            throw new Exception("User insert failed");
        }

        mysqli_stmt_bind_param($stmt, "ssss", $email, $hashedPassword, $token, $role);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("User insert failed: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("User insert failed");
        }

        $userId = mysqli_insert_id($this->dbConn);
        mysqli_stmt_close($stmt);

        $profilePic = 'https://cdn.pixabay.com/photo/2020/07/01/12/58/icon-5359553_640.png';
        $query = "INSERT INTO {$this->profilesTable} (user_id, name, email, phone, profile_picture) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare profile insert statement: " . mysqli_error($this->dbConn));
            throw new Exception("Profile insert failed");
        }

        mysqli_stmt_bind_param($stmt, "issss", $userId, $name, $email, $phone, $profilePic);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Profile insert failed: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Profile insert failed");
        }

        mysqli_stmt_close($stmt);
        mysqli_commit($this->dbConn);

        if (!$this->sendVerificationEmail($email, $token)) {
            $this->HandleError("Failed to send verification email.");
            return false;
        }

        return true;
    } catch (Exception $e) {
        mysqli_rollback($this->dbConn);
        return false;
    }
}

public function Logout() {
        if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
            $this->HandleError("Database connection lost.");
            return false;
        }

        $token = $_COOKIE['auth_token'] ?? '';
        if (empty($token)) {
            return true;
        }

        $token = $this->sanitize($token);
        $query = "UPDATE {$this->usersTable} SET auth_token = NULL WHERE auth_token = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare statement: " . mysqli_error($this->dbConn));
            return false;
        }

        mysqli_stmt_bind_param($stmt, "s", $token);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Failed to clear auth token: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }

        mysqli_stmt_close($stmt);
        session_regenerate_id(true);
        return true;
}

public function VerifyUser($token) {
        if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
            $this->HandleError("Database connection lost.");
            return false;
        }

        $token = $this->sanitize($token);
        $query = "SELECT id, is_verified FROM {$this->usersTable} WHERE verification_token = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare statement: " . mysqli_error($this->dbConn));
            return false;
        }

        mysqli_stmt_bind_param($stmt, "s", $token);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Database query failed: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }

        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $userId = $row['id'];
            if ($row['is_verified'] == 1) {
                $this->HandleError("Token already verified.");
                mysqli_stmt_close($stmt);
                return false;
            }

            $update = "UPDATE {$this->usersTable} SET is_verified = 1, verification_token = NULL WHERE id = ?";
            $updateStmt = mysqli_prepare($this->dbConn, $update);
            if ($updateStmt === false) {
                $this->HandleError("Failed to prepare verification update statement: " . mysqli_error($this->dbConn));
                mysqli_stmt_close($stmt);
                return false;
            }

            mysqli_stmt_bind_param($updateStmt, "i", $userId);
            if (mysqli_stmt_execute($updateStmt)) {
                mysqli_stmt_close($updateStmt);
                mysqli_stmt_close($stmt);
                return true;
            } else {
                $this->HandleError("Verification update failed: " . mysqli_stmt_error($updateStmt));
                mysqli_stmt_close($updateStmt);
                mysqli_stmt_close($stmt);
                return false;
            }
        } else {
            $this->HandleError("Invalid token.");
            mysqli_stmt_close($stmt);
            return false;
        }
}

public function UpdateUserRole($user_id, $role) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        return false;
    }

    $user_id = $this->sanitize($user_id);
    $role = $this->sanitize($role);

    if (!in_array($role, ['Client', 'Driver', 'Manager', 'Owner'])) {
        $this->HandleError("Invalid role specified.");
        return false;
    }

    $query = "UPDATE {$this->usersTable} SET role = ? WHERE id = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare role update query: " . mysqli_error($this->dbConn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'si', $role, $user_id);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Role update failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected_rows === 0) {
        $this->HandleError("No user found with the specified ID.");
        return false;
    }

    error_log("UpdateUserRole: Successfully updated role to '$role' for user_id: $user_id");
    return true;
}

public function SearchUsers($query = '', $offset = 0, $limit = 25) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("SearchUsers: Database connection failed");
        return false;
    }

    $this->ensureUsersTableExists();
    $this->ensureProfilesTableExists();

    $query = $this->sanitize($query);
    $search_clause = '';
    $params = [];
    $param_types = '';

    if ($query !== '') {
        if (preg_match('/^first_login:NOT:(\d{4}-\d{2}-\d{2})$/', $query, $matches)) {
            // Filter users who haven't logged in on the specified date
            $search_clause = "WHERE (DATE(u.first_login) != ? OR u.first_login IS NULL) AND u.is_verified = 1";
            $params = [$matches[1]];
            $param_types = 's';
        } else {
            // Existing search logic for email/name/phone
            $search_clause = "WHERE u.email LIKE ? OR p.name LIKE ? OR p.phone LIKE ?";
            $like_query = "%$query%";
            $params = [$like_query, $like_query, $like_query];
            $param_types = 'sss';
        }
    } else {
        $search_clause = "WHERE u.is_verified = 1";
    }

    // Count total users
    $count_query = "SELECT COUNT(DISTINCT u.id) as total 
                    FROM {$this->usersTable} u 
                    LEFT JOIN {$this->profilesTable} p ON u.id = p.user_id 
                    $search_clause";
    $count_stmt = mysqli_prepare($this->dbConn, $count_query);
    if ($count_stmt === false) {
        $this->HandleError("Failed to prepare count query: " . mysqli_error($this->dbConn));
        error_log("SearchUsers: Count query preparation failed: " . mysqli_error($this->dbConn));
        return false;
    }

    if ($param_types) {
        mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    }

    if (!mysqli_stmt_execute($count_stmt)) {
        $this->HandleError("Count query failed: " . mysqli_stmt_error($count_stmt));
        error_log("SearchUsers: Count query execution failed: " . mysqli_stmt_error($count_stmt));
        mysqli_stmt_close($count_stmt);
        return false;
    }

    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_users = mysqli_fetch_assoc($count_result)['total'];
    mysqli_stmt_close($count_stmt);
    error_log("SearchUsers: Total users counted: $total_users");

    // Fetch users
    $select_query = "SELECT u.id, u.email, u.role, u.is_verified, 
                            p.name, p.phone, p.profile_picture, 
                            p.created_at, p.updated_at, 
                            u.first_login, 
                            COUNT(CASE WHEN r.status = 'active' THEN r.reward_id END) as reward_count 
                     FROM {$this->usersTable} u 
                     LEFT JOIN {$this->profilesTable} p ON u.id = p.user_id 
                     LEFT JOIN rewards r ON u.id = r.user_id 
                     $search_clause 
                     GROUP BY u.id, u.email, u.role, u.is_verified, 
                              p.name, p.phone, p.profile_picture, 
                              p.created_at, p.updated_at, 
                              u.first_login 
                     ORDER BY u.email ASC 
                     LIMIT ? OFFSET ?";
    $select_stmt = mysqli_prepare($this->dbConn, $select_query);
    if ($select_stmt === false) {
        $this->HandleError("Failed to prepare select query: " . mysqli_error($this->dbConn));
        error_log("SearchUsers: Select query preparation failed: " . mysqli_error($this->dbConn));
        return false;
    }

    $limit = (int)$limit;
    $offset = (int)$offset;
    if ($param_types) {
        $param_types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
        mysqli_stmt_bind_param($select_stmt, $param_types, ...$params);
    } else {
        mysqli_stmt_bind_param($select_stmt, 'ii', $limit, $offset);
    }

    if (!mysqli_stmt_execute($select_stmt)) {
        $this->HandleError("Select query failed: " . mysqli_stmt_error($select_stmt));
        error_log("SearchUsers: Select query execution failed: " . mysqli_stmt_error($select_stmt));
        mysqli_stmt_close($select_stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($select_stmt);
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    mysqli_stmt_close($select_stmt);
    error_log("SearchUsers: Fetched " . count($users) . " users");

    return [
        'users' => $users,
        'total' => $total_users
    ];
}

public function SearchVehicles($query = '', $offset = 0, $limit = 25) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("SearchVehicles: Database connection failed");
        return false;
    }

    $this->ensureVehiclesTableExists();

    $query = $this->sanitize($query);
    $search_clause = '';
    $params = [];
    $param_types = '';

    if ($query !== '') {
        $search_clause = "WHERE v.license_plate LIKE ? OR v.make LIKE ? OR v.model LIKE ? OR v.vin LIKE ?";
        $like_query = "%$query%";
        $params = [$like_query, $like_query, $like_query, $like_query];
        $param_types = 'ssss';
    }

    // Count total vehicles (simplified, no JOIN)
    $count_query = "SELECT COUNT(*) as total FROM {$this->vehiclesTable} v $search_clause";
    $count_stmt = mysqli_prepare($this->dbConn, $count_query);
    if ($count_stmt === false) {
        $this->HandleError("Failed to prepare count query: " . mysqli_error($this->dbConn));
        error_log("SearchVehicles: Count query preparation failed: " . mysqli_error($this->dbConn));
        return false;
    }

    if ($query !== '') {
        mysqli_stmt_bind_param($count_stmt, $param_types, ...$params);
    }

    if (!mysqli_stmt_execute($count_stmt)) {
        $this->HandleError("Count query failed: " . mysqli_stmt_error($count_stmt));
        error_log("SearchVehicles: Count query execution failed: " . mysqli_stmt_error($count_stmt));
        mysqli_stmt_close($count_stmt);
        return false;
    }

    $count_result = mysqli_stmt_get_result($count_stmt);
    $total_vehicles = (int)mysqli_fetch_assoc($count_result)['total'];
    mysqli_stmt_close($count_stmt);
    error_log("SearchVehicles: Total vehicles counted: $total_vehicles");

    // Fetch vehicles
    $select_query = "SELECT v.vehicle_id, v.image_location, v.make, v.model, v.vin, v.color, 
                            v.license_plate, v.year, v.current_user_id, p1.name as current_user_name, 
                            v.last_user_id, p2.name as last_user_name, v.date_assigned, v.date_last_used 
                     FROM {$this->vehiclesTable} v 
                     LEFT JOIN {$this->profilesTable} p1 ON v.current_user_id = p1.user_id 
                     LEFT JOIN {$this->profilesTable} p2 ON v.last_user_id = p2.user_id 
                     $search_clause 
                     ORDER BY v.vehicle_id ASC 
                     LIMIT ? OFFSET ?";
    $select_stmt = mysqli_prepare($this->dbConn, $select_query);
    if ($select_stmt === false) {
        $this->HandleError("Failed to prepare select query: " . mysqli_error($this->dbConn));
        error_log("SearchVehicles: Select query preparation failed: " . mysqli_error($this->dbConn));
        return false;
    }

    $limit = (int)$limit;
    $offset = (int)$offset;
    if ($query !== '') {
        $param_types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
        mysqli_stmt_bind_param($select_stmt, $param_types, ...$params);
    } else {
        mysqli_stmt_bind_param($select_stmt, 'ii', $limit, $offset);
    }

    if (!mysqli_stmt_execute($select_stmt)) {
        $this->HandleError("Select query failed: " . mysqli_stmt_error($select_stmt));
        error_log("SearchVehicles: Select query execution failed: " . mysqli_stmt_error($select_stmt));
        mysqli_stmt_close($select_stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($select_stmt);
    $vehicles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $vehicles[] = $row;
    }
    mysqli_stmt_close($select_stmt);
    error_log("SearchVehicles: Fetched " . count($vehicles) . " vehicles");

    return [
        'success' => true,
        'vehicles' => $vehicles,
        'total' => $total_vehicles,
        'current_page' => ($offset / $limit) + 1,
        'total_pages' => max(1, ceil($total_vehicles / $limit))
    ];
}

public function UpdateVehicle($vehicle_id, $vehicle_data) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("UpdateVehicle: Database connection failed");
        return false;
    }

    $this->ensureVehiclesTableExists();

    $vehicle_id = $this->sanitize($vehicle_id);
    $make = $this->sanitize($vehicle_data['make'] ?? '');
    $model = $this->sanitize($vehicle_data['model'] ?? '');
    $vin = $this->sanitize($vehicle_data['vin'] ?? '');
    $color = $this->sanitize($vehicle_data['color'] ?? '');
    $license_plate = $this->sanitize($vehicle_data['license_plate'] ?? '');
    $year = (int)($vehicle_data['year'] ?? 0);

    if (empty($make) || strlen($make) > 50) {
        $this->HandleError("Make is required and must be 50 characters or less.");
        return false;
    }
    if (empty($model) || strlen($model) > 50) {
        $this->HandleError("Model is required and must be 50 characters or less.");
        return false;
    }
    if (empty($vin) || !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/', $vin)) {
        $this->HandleError("VIN is required and must be 17 alphanumeric characters (excluding I, O, Q).");
        return false;
    }
    if (empty($color) || strlen($color) > 30) {
        $this->HandleError("Color is required and must be 30 characters or less.");
        return false;
    }
    if (empty($license_plate) || strlen($license_plate) > 20) {
        $this->HandleError("License plate is required and must be 20 characters or less.");
        return false;
    }
    if ($year < 1900 || $year > date('Y') + 1) {
        $this->HandleError("Year must be between 1900 and " . (date('Y') + 1) . ".");
        return false;
    }

    // Check for duplicate VIN or license plate
    $query = "SELECT vehicle_id FROM {$this->vehiclesTable} WHERE (vin = ? OR license_plate = ?) AND vehicle_id != ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare duplicate check: " . mysqli_error($this->dbConn));
        return false;
    }
    mysqli_stmt_bind_param($stmt, "ssi", $vin, $license_plate, $vehicle_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        $this->HandleError("VIN or license plate already exists.");
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);

    // Update vehicle
    $query = "UPDATE {$this->vehiclesTable} SET make = ?, model = ?, vin = ?, color = ?, license_plate = ?, year = ? WHERE vehicle_id = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare update: " . mysqli_error($this->dbConn));
        return false;
    }
    mysqli_stmt_bind_param($stmt, "sssssii", $make, $model, $vin, $color, $license_plate, $year, $vehicle_id);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Failed to update vehicle: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected_rows === 0) {
        $this->HandleError("No vehicle found with the specified ID.");
        return false;
    }

    error_log("UpdateVehicle: Successfully updated vehicle_id: $vehicle_id");
    return true;
}

public function CreateVehicle($vehicle_data) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("CreateVehicle: Database connection failed");
        return false;
    }

    $this->ensureVehiclesTableExists();

    $make = $this->sanitize($vehicle_data['make'] ?? '');
    $model = $this->sanitize($vehicle_data['model'] ?? '');
    $vin = $this->sanitize(trim($vehicle_data['vin'] ?? '')); // Trim whitespace
    $color = $this->sanitize($vehicle_data['color'] ?? '');
    $license_plate = $this->sanitize($vehicle_data['license_plate'] ?? '');
    $year = (int)($vehicle_data['year'] ?? 0);
    $image_location = $this->sanitize($vehicle_data['image_location'] ?? '');

    // Validation
    if (empty($make) || strlen($make) > 50) {
        $this->HandleError("Make is required and must be 50 characters or less.");
        return false;
    }
    if (empty($model) || strlen($model) > 50) {
        $this->HandleError("Model is required and must be 50 characters or less.");
        return false;
    }
    // VIN validation: 17 characters, alphanumeric, excluding I, O, Q
    if (empty($vin) || !preg_match('/^[A-HJ-NPR-Z0-9]{17}$/i', $vin)) {
        $this->HandleError("VIN is required and must be exactly 17 alphanumeric characters (excluding I, O, Q).");
        error_log("CreateVehicle: VIN validation failed for VIN: '$vin'");
        return false;
    }
    if (empty($color) || strlen($color) > 30) {
        $this->HandleError("Color is required and must be 30 characters or less.");
        return false;
    }
    if (empty($license_plate) || strlen($license_plate) > 20) {
        $this->HandleError("License plate is required and must be 20 characters or less.");
        return false;
    }
    if ($year < 1900 || $year > 2025) {
        $this->HandleError("Year must be between 1900 and 2025.");
        return false;
    }
    if (!empty($image_location) && strlen($image_location) > 255) {
        $this->HandleError("Image location must be 255 characters or less.");
        return false;
    }

    // Check for VIN or license plate conflicts
    $query = "SELECT vehicle_id FROM {$this->vehiclesTable} WHERE vin = ? OR license_plate = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare conflict check query: " . mysqli_error($this->dbConn));
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $vin, $license_plate);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Conflict check failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) > 0) {
        $this->HandleError("VIN or license plate already in use by another vehicle.");
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);

    // Insert vehicle
    $query = "INSERT INTO {$this->vehiclesTable} (make, model, vin, color, license_plate, year, image_location) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare insert query: " . mysqli_error($this->dbConn));
        return false;
    }

    $image_location = $image_location ?: null; // Convert empty string to NULL for nullable field
    mysqli_stmt_bind_param($stmt, 'sssssis', $make, $model, $vin, $color, $license_plate, $year, $image_location);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Vehicle creation failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    mysqli_stmt_close($stmt);
    error_log("CreateVehicle: Successfully created vehicle with VIN: $vin");
    return true;
}

public function DeleteVehicle($vehicle_id) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("DeleteVehicle: Database connection failed");
        return false;
    }

    $this->ensureVehiclesTableExists();

    $vehicle_id = $this->sanitize($vehicle_id);

    // Check if vehicle exists
    $query = "SELECT vehicle_id FROM {$this->vehiclesTable} WHERE vehicle_id = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare existence check query: " . mysqli_error($this->dbConn));
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $vehicle_id);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Existence check failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) === 0) {
        $this->HandleError("No vehicle found with the specified ID.");
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);

    // Delete vehicle
    $query = "DELETE FROM {$this->vehiclesTable} WHERE vehicle_id = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare delete query: " . mysqli_error($this->dbConn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'i', $vehicle_id);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Vehicle deletion failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected_rows === 0) {
        $this->HandleError("No vehicle found with the specified ID.");
        return false;
    }

    error_log("DeleteVehicle: Successfully deleted vehicle_id: $vehicle_id");
    return true;
}

public function AddVehicleIssue($vehicle_id, $issue_type, $description = null) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("AddVehicleIssue: Database connection failed");
        return false;
    }

    // Validate issue_type
    $valid_types = ['Brakes', 'Tires', 'Engine', 'Transmission', 'Suspension', 'Electrical', 'Custom'];
    if (!in_array($issue_type, $valid_types)) {
        $this->HandleError("Invalid issue type.");
        error_log("AddVehicleIssue: Invalid issue type: $issue_type");
        return false;
    }

    $vehicle_id = $this->sanitize($vehicle_id);
    $issue_type = $this->sanitize($issue_type);
    $description = $description ? $this->sanitize($description) : null;

    // Check if vehicle exists
    $query = "SELECT vehicle_id FROM {$this->vehiclesTable} WHERE vehicle_id = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare vehicle check query: " . mysqli_error($this->dbConn));
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $vehicle_id);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Vehicle check failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) === 0) {
        $this->HandleError("No vehicle found with the specified ID.");
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);

    // Insert issue
    $query = "INSERT INTO vehicle_issues (vehicle_id, issue_type, description, status) VALUES (?, ?, ?, 'Open')";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare insert query: " . mysqli_error($this->dbConn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'iss', $vehicle_id, $issue_type, $description);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Issue creation failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    mysqli_stmt_close($stmt);
    error_log("AddVehicleIssue: Successfully added issue for vehicle_id: $vehicle_id, type: $issue_type");
    return true;
}

public function ResolveVehicleIssue($issue_id) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("ResolveVehicleIssue: Database connection failed");
        return false;
    }

    $issue_id = $this->sanitize($issue_id);

    // Update issue
    $query = "UPDATE vehicle_issues SET status = 'Resolved', resolved_at = CURRENT_TIMESTAMP WHERE issue_id = ? AND status = 'Open'";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare update query: " . mysqli_error($this->dbConn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'i', $issue_id);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Issue resolution failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected_rows === 0) {
        $this->HandleError("No open issue found with the specified ID.");
        return false;
    }

    error_log("ResolveVehicleIssue: Successfully resolved issue_id: $issue_id");
    return true;
}

public function ResolveVehicleIssueWithDetails($issue_id, $resolution_data) {
        if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
            $this->HandleError("Database connection lost.");
            error_log("ResolveVehicleIssueWithDetails: Database connection failed");
            return false;
        }

        $issue_id = $this->sanitize($issue_id);
        $parts_replaced = $this->sanitize($resolution_data['parts_replaced'] ?? '');
        $work_done = $this->sanitize($resolution_data['work_done'] ?? '');
        $invoice_number = $this->sanitize($resolution_data['invoice_number'] ?? '');
        $labor_hours = isset($resolution_data['labor_hours']) ? (float)$resolution_data['labor_hours'] : null; // NEW
        $repair_cost = isset($resolution_data['repair_cost']) ? (float)$resolution_data['repair_cost'] : null; // NEW
        $mechanic_notes = $this->sanitize($resolution_data['mechanic_notes'] ?? ''); // NEW
        $repair_category = $this->sanitize($resolution_data['repair_category'] ?? ''); // NEW

        if (empty($work_done)) {
            $this->HandleError("Work done description is required.");
            error_log("ResolveVehicleIssueWithDetails: Work done is required for issue_id: $issue_id");
            return false;
        }
        if (!empty($invoice_number) && strlen($invoice_number) > 50) {
            $this->HandleError("Invoice number must be 50 characters or less.");
            error_log("ResolveVehicleIssueWithDetails: Invalid invoice number for issue_id: $issue_id");
            return false;
        }
        if ($labor_hours !== null && ($labor_hours < 0 || $labor_hours > 99.99)) { // NEW: Validate labor hours
            $this->HandleError("Labor hours must be between 0 and 99.99.");
            error_log("ResolveVehicleIssueWithDetails: Invalid labor hours for issue_id: $issue_id");
            return false;
        }
        if ($repair_cost !== null && ($repair_cost < 0 || $repair_cost > 999999.99)) { // NEW: Validate repair cost
            $this->HandleError("Repair cost must be between 0 and 999999.99.");
            error_log("ResolveVehicleIssueWithDetails: Invalid repair cost for issue_id: $issue_id");
            return false;
        }
        if (!empty($repair_category) && !in_array($repair_category, ['Maintenance', 'Emergency', 'Cosmetic', 'Performance', 'Other'])) { // NEW: Validate category
            $this->HandleError("Invalid repair category.");
            error_log("ResolveVehicleIssueWithDetails: Invalid repair category for issue_id: $issue_id");
            return false;
        }

        mysqli_begin_transaction($this->dbConn);
        try {
            // Insert resolution details
            $query = "INSERT INTO issue_resolutions (issue_id, parts_replaced, work_done, invoice_number, labor_hours, repair_cost, mechanic_notes, repair_category) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($this->dbConn, $query);
            if ($stmt === false) {
                $this->HandleError("Failed to prepare resolution insert query: " . mysqli_error($this->dbConn));
                throw new Exception("Resolution insert failed");
            }
            $parts_replaced = $parts_replaced ?: null;
            $invoice_number = $invoice_number ?: null;
            $mechanic_notes = $mechanic_notes ?: null;
            $repair_category = $repair_category ?: null;
            mysqli_stmt_bind_param($stmt, 'isssddss', $issue_id, $parts_replaced, $work_done, $invoice_number, $labor_hours, $repair_cost, $mechanic_notes, $repair_category);
            if (!mysqli_stmt_execute($stmt)) {
                $this->HandleError("Resolution insert failed: " . mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
                throw new Exception("Resolution insert failed");
            }
            $resolution_id = mysqli_insert_id($this->dbConn);
            mysqli_stmt_close($stmt);

            // Update issue
            $query = "UPDATE vehicle_issues SET status = 'Resolved', resolved_at = CURRENT_TIMESTAMP, resolution_id = ? WHERE issue_id = ? AND status = 'Open'";
            $stmt = mysqli_prepare($this->dbConn, $query);
            if ($stmt === false) {
                $this->HandleError("Failed to prepare issue update query: " . mysqli_error($this->dbConn));
                throw new Exception("Issue update failed");
            }
            mysqli_stmt_bind_param($stmt, 'ii', $resolution_id, $issue_id);
            if (!mysqli_stmt_execute($stmt)) {
                $this->HandleError("Issue resolution failed: " . mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
                throw new Exception("Issue resolution failed");
            }
            $affected_rows = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected_rows === 0) {
                $this->HandleError("No open issue found with the specified ID.");
                throw new Exception("No open issue");
            }

            mysqli_commit($this->dbConn);
            error_log("ResolveVehicleIssueWithDetails: Successfully resolved issue_id: $issue_id with resolution_id: $resolution_id");
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->dbConn);
            error_log("ResolveVehicleIssueWithDetails: Failed for issue_id: $issue_id - " . $e->getMessage());
            return false;
        }
}

public function EditIssueResolution($issue_id, $resolution_data) {
        if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
            $this->HandleError("Database connection lost.");
            error_log("EditIssueResolution: Database connection failed");
            return false;
        }

        $issue_id = $this->sanitize($issue_id);
        $parts_replaced = $this->sanitize($resolution_data['parts_replaced'] ?? '');
        $work_done = $this->sanitize($resolution_data['work_done'] ?? '');
        $invoice_number = $this->sanitize($resolution_data['invoice_number'] ?? '');
        $labor_hours = isset($resolution_data['labor_hours']) ? (float)$resolution_data['labor_hours'] : null; // NEW
        $repair_cost = isset($resolution_data['repair_cost']) ? (float)$resolution_data['repair_cost'] : null; // NEW
        $mechanic_notes = $this->sanitize($resolution_data['mechanic_notes'] ?? ''); // NEW
        $repair_category = $this->sanitize($resolution_data['repair_category'] ?? ''); // NEW

        if (empty($work_done)) {
            $this->HandleError("Work done description is required.");
            error_log("EditIssueResolution: Work done is required for issue_id: $issue_id");
            return false;
        }
        if (!empty($invoice_number) && strlen($invoice_number) > 50) {
            $this->HandleError("Invoice number must be 50 characters or less.");
            error_log("EditIssueResolution: Invalid invoice number for issue_id: $issue_id");
            return false;
        }
        if ($labor_hours !== null && ($labor_hours < 0 || $labor_hours > 99.99)) { // NEW: Validate labor hours
            $this->HandleError("Labor hours must be between 0 and 99.99.");
            error_log("EditIssueResolution: Invalid labor hours for issue_id: $issue_id");
            return false;
        }
        if ($repair_cost !== null && ($repair_cost < 0 || $repair_cost > 999999.99)) { // NEW: Validate repair cost
            $this->HandleError("Repair cost must be between 0 and 999999.99.");
            error_log("EditIssueResolution: Invalid repair cost for issue_id: $issue_id");
            return false;
        }
        if (!empty($repair_category) && !in_array($repair_category, ['Maintenance', 'Emergency', 'Cosmetic', 'Performance', 'Other'])) { // NEW: Validate category
            $this->HandleError("Invalid repair category.");
            error_log("EditIssueResolution: Invalid repair category for issue_id: $issue_id");
            return false;
        }

        // Check if issue exists and has a resolution
        $query = "SELECT resolution_id FROM vehicle_issues WHERE issue_id = ? AND status = 'Resolved'";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare issue check query: " . mysqli_error($this->dbConn));
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'i', $issue_id);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Issue check failed: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) === 0) {
            $this->HandleError("No resolved issue found with the specified ID.");
            mysqli_stmt_close($stmt);
            return false;
        }
        $row = mysqli_fetch_assoc($result);
        $resolution_id = $row['resolution_id'];
        mysqli_stmt_close($stmt);

        // Update resolution details
        $query = "UPDATE issue_resolutions SET parts_replaced = ?, work_done = ?, invoice_number = ?, labor_hours = ?, repair_cost = ?, mechanic_notes = ?, repair_category = ? WHERE resolution_id = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare resolution update query: " . mysqli_error($this->dbConn));
            return false;
        }
        $parts_replaced = $parts_replaced ?: null;
        $invoice_number = $invoice_number ?: null;
        $mechanic_notes = $mechanic_notes ?: null;
        $repair_category = $repair_category ?: null;
        mysqli_stmt_bind_param($stmt, 'sssddssi', $parts_replaced, $work_done, $invoice_number, $labor_hours, $repair_cost, $mechanic_notes, $repair_category, $resolution_id);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Resolution update failed: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected_rows === 0) {
            $this->HandleError("No resolution found with the specified ID.");
            return false;
        }

        error_log("EditIssueResolution: Successfully updated resolution for issue_id: $issue_id, resolution_id: $resolution_id");
        return true;
}

public function GetVehicleIssues($vehicle_id) {
        if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
            $this->HandleError("Database connection lost.");
            error_log("GetVehicleIssues: Database connection failed");
            return false;
        }

        $vehicle_id = $this->sanitize($vehicle_id);

        $query = "SELECT vehicle_id FROM {$this->vehiclesTable} WHERE vehicle_id = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare vehicle check query: " . mysqli_error($this->dbConn));
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'i', $vehicle_id);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Vehicle check failed: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) === 0) {
            $this->HandleError("No vehicle found with the specified ID.");
            mysqli_stmt_close($stmt);
            return false;
        }
        mysqli_stmt_close($stmt);

        $query = "SELECT vi.issue_id, vi.issue_type, vi.description, vi.created_at, vi.resolved_at, vi.status,
                         ir.resolution_id, ir.parts_replaced, ir.work_done, ir.invoice_number,
                         ir.labor_hours, ir.repair_cost, ir.mechanic_notes, ir.repair_category
                  FROM vehicle_issues vi
                  LEFT JOIN issue_resolutions ir ON vi.resolution_id = ir.resolution_id
                  WHERE vi.vehicle_id = ?
                  ORDER BY vi.created_at DESC";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare select query: " . mysqli_error($this->dbConn));
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'i', $vehicle_id);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Select query failed: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }

        $result = mysqli_stmt_get_result($stmt);
        $issues = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $issues[] = $row;
        }
        mysqli_stmt_close($stmt);
        error_log("GetVehicleIssues: Fetched " . count($issues) . " issues for vehicle_id: $vehicle_id");

        return [
            'success' => true,
            'issues' => $issues
        ];
}

private function createVehicleIssuesTable() {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("createVehicleIssuesTable: Database connection failed");
        return false;
    }

    if (!$this->ensureVehiclesTableExists()) {
        $this->HandleError("Failed to ensure vehicles table exists.");
        error_log("createVehicleIssuesTable: Failed to ensure vehicles table");
        return false;
    }

    $query = "SHOW COLUMNS FROM {$this->vehiclesTable} LIKE 'vehicle_id'";
    $result = mysqli_query($this->dbConn, $query);
    if (!$result || mysqli_num_rows($result) == 0) {
        $this->HandleError("Vehicles table missing vehicle_id column or incorrect structure.");
        error_log("createVehicleIssuesTable: Vehicles table missing vehicle_id column");
        return false;
    }
    $column = mysqli_fetch_assoc($result);
    if ($column['Type'] !== 'int(11)' || strpos($column['Key'], 'PRI') === false) {
        $this->HandleError("Vehicles table vehicle_id column is not INT(11) PRIMARY KEY.");
        error_log("createVehicleIssuesTable: Invalid vehicle_id column type or key");
        return false;
    }

    $query = "SHOW TABLES LIKE 'vehicle_issues'";
    $result = mysqli_query($this->dbConn, $query);
    if ($result && mysqli_num_rows($result) == 0) {
        $sql = "CREATE TABLE vehicle_issues (
            issue_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            vehicle_id INT(11) NOT NULL,
            issue_type VARCHAR(50) NOT NULL,
            description TEXT,
            priority ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME,
            status ENUM('Open', 'Resolved') NOT NULL DEFAULT 'Open',
            resolution_id BIGINT UNSIGNED DEFAULT NULL,
            FOREIGN KEY (vehicle_id) REFERENCES {$this->vehiclesTable}(vehicle_id) ON DELETE CASCADE,
            INDEX idx_vehicle_id (vehicle_id),
            INDEX idx_status (status),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!mysqli_query($this->dbConn, $sql)) {
            $this->HandleError("Failed to create vehicle_issues table: " . mysqli_error($this->dbConn));
            error_log("createVehicleIssuesTable: Failed to create table: " . mysqli_error($this->dbConn));
            return false;
        }
        error_log("createVehicleIssuesTable: Table created successfully");
    }

    return true;
}

private function createIssueResolutionsTable() {
        if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
            $this->HandleError("Database connection lost.");
            error_log("createIssueResolutionsTable: Database connection failed");
            return false;
        }

        $query = "SHOW TABLES LIKE 'issue_resolutions'";
        $result = mysqli_query($this->dbConn, $query);
        if ($result && mysqli_num_rows($result) > 0) {
            error_log("createIssueResolutionsTable: issue_resolutions table already exists");
            return true;
        }

        $sql = "CREATE TABLE issue_resolutions (
            resolution_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            issue_id BIGINT UNSIGNED NOT NULL,
            parts_replaced TEXT,
            work_done TEXT NOT NULL,
            invoice_number VARCHAR(50),
            labor_hours DECIMAL(4,2) DEFAULT NULL, -- NEW: Track labor hours
            repair_cost DECIMAL(10,2) DEFAULT NULL, -- NEW: Track repair cost
            mechanic_notes TEXT, -- NEW: Additional notes
            repair_category VARCHAR(50), -- NEW: Categorize repair
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (issue_id) REFERENCES vehicle_issues(issue_id) ON DELETE CASCADE,
            INDEX idx_issue_id (issue_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!mysqli_query($this->dbConn, $sql)) {
            $this->HandleError("Failed to create issue_resolutions table: " . mysqli_error($this->dbConn));
            error_log("createIssueResolutionsTable: Failed to create table: " . mysqli_error($this->dbConn));
            return false;
        }

        error_log("createIssueResolutionsTable: Table created successfully");
        return true;
}







public function SubmitStartMileage() {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("SubmitStartMileage: Database connection lost");
        echo json_encode(['success' => false, 'message' => 'Database connection lost', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    $mileageId = isset($_POST['mileage_id']) ? $this->sanitize($_POST['mileage_id']) : '';
    $startMiles = isset($_POST['start_miles']) ? $this->sanitize($_POST['start_miles']) : '';
    $startMilesDatetime = isset($_POST['start_miles_datetime']) ? $this->sanitize($_POST['start_miles_datetime']) : '';
    $token = isset($_COOKIE['auth_token']) ? $this->sanitize($_COOKIE['auth_token']) : ($_SESSION['auth_token'] ?? '');
    if (empty($token) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $this->sanitize($matches[1]);
            error_log("SubmitStartMileage: Retrieved auth_token from Authorization header: $token");
        }
    }

    error_log("SubmitStartMileage: Received mileage_id: $mileageId, start_miles: $startMiles, start_miles_datetime: $startMilesDatetime, auth_token: " . ($token ? $token : 'none'));

    // Validate inputs
    if (empty($token)) {
        $this->HandleError("No authentication token provided.");
        error_log("SubmitStartMileage: No auth token provided");
        echo json_encode(['success' => false, 'message' => 'No authentication token provided. Please log in again.', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    if (!is_numeric($mileageId) || $mileageId <= 0) {
        $this->HandleError("Invalid mileage ID.");
        error_log("SubmitStartMileage: Invalid mileage_id: $mileageId");
        echo json_encode(['success' => false, 'message' => 'Invalid mileage ID', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    if (!is_numeric($startMiles) || $startMiles < 0 || $startMiles > 999999.99) {
        $this->HandleError("Start miles must be a number between 0 and 999999.99.");
        error_log("SubmitStartMileage: Invalid start_miles: $startMiles");
        echo json_encode(['success' => false, 'message' => 'Invalid start miles', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $startMilesDatetime)) {
        $this->HandleError("Invalid start miles datetime format. Use YYYY-MM-DD HH:MM:SS.");
        error_log("SubmitStartMileage: Invalid start_miles_datetime format: $startMilesDatetime");
        echo json_encode(['success' => false, 'message' => 'Invalid start miles datetime format', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    // Validate datetime
    try {
        $datetime = new DateTime($startMilesDatetime, new DateTimeZone('America/New_York'));
        $currentTime = new DateTime('now', new DateTimeZone('America/New_York'));
        if ($datetime > $currentTime) {
            $this->HandleError("Start miles datetime cannot be in the future.");
            error_log("SubmitStartMileage: Start miles datetime in future: $startMilesDatetime");
            echo json_encode(['success' => false, 'message' => 'Start miles datetime cannot be in the future', 'csrf_token' => $this->GetCSRFToken()]);
            exit;
        }
    } catch (Exception $e) {
        $this->HandleError("Invalid start miles datetime: " . $e->getMessage());
        error_log("SubmitStartMileage: Invalid start_miles_datetime: $startMilesDatetime, error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Invalid start miles datetime', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    // Rate limiting
    $rateLimitKey = $this->GenerateRateLimitKey('submit_start_mileage', $token);
    if (!$this->checkRateLimit($rateLimitKey, 'submit_start_mileage', 5, 900)) {
        $this->HandleError("Too many start mileage submission attempts.");
        error_log("SubmitStartMileage: Rate limit exceeded for key: $rateLimitKey");
        echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    mysqli_begin_transaction($this->dbConn);
    try {
        // Validate user
        $query = "SELECT id, role FROM {$this->usersTable} WHERE auth_token = ? AND is_verified = 1";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare user query: " . mysqli_error($this->dbConn));
            error_log("SubmitStartMileage: Failed to prepare user query for token: $token: " . mysqli_error($this->dbConn));
            throw new Exception("User query preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "s", $token);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("User query failed: " . mysqli_stmt_error($stmt));
            error_log("SubmitStartMileage: User query failed for token: $token: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("User query execution failed");
        }

        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) <= 0) {
            $this->HandleError("Invalid or unverified user.");
            error_log("SubmitStartMileage: No user found for token: $token");
            mysqli_stmt_close($stmt);
            throw new Exception("Authentication failed");
        }

        $row = mysqli_fetch_assoc($result);
        $userId = $row['id'];
        $userRole = $row['role'];
        $allowedRoles = ['driver', 'manager', 'owner'];
        if (!in_array(strtolower($userRole), $allowedRoles)) {
            $this->HandleError("Only Drivers, Managers, or Owners can submit mileage.");
            error_log("SubmitStartMileage: Unauthorized role for user_id: $userId, role: $userRole");
            mysqli_stmt_close($stmt);
            throw new Exception("Unauthorized role");
        }
        mysqli_stmt_close($stmt);

        // Verify mileage record exists and belongs to the user
        $query = "SELECT vehicle_id, user_id FROM mileage_records WHERE mileage_id = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare mileage record query: " . mysqli_error($this->dbConn));
            error_log("SubmitStartMileage: Failed to prepare mileage record query for mileage_id: $mileageId: " . mysqli_error($this->dbConn));
            throw new Exception("Mileage record query preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "i", $mileageId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Mileage record query failed: " . mysqli_stmt_error($stmt));
            error_log("SubmitStartMileage: Mileage record query failed for mileage_id: $mileageId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Mileage record query execution failed");
        }

        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) <= 0) {
            $this->HandleError("No mileage record found with the specified ID.");
            error_log("SubmitStartMileage: No mileage record found for mileage_id: $mileageId");
            mysqli_stmt_close($stmt);
            throw new Exception("No mileage record found");
        }

        $row = mysqli_fetch_assoc($result);
        $vehicleId = $row['vehicle_id'];
        if ($row['user_id'] != $userId) {
            $this->HandleError("Mileage record does not belong to this user.");
            error_log("SubmitStartMileage: Mileage record $mileageId does not belong to user_id: $userId");
            mysqli_stmt_close($stmt);
            throw new Exception("Mileage record not owned");
        }
        mysqli_stmt_close($stmt);

        // Update the mileage record with start_miles and start_miles_datetime
        $query = "UPDATE mileage_records SET start_miles = ?, start_miles_datetime = ? WHERE mileage_id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare mileage update query: " . mysqli_error($this->dbConn));
            error_log("SubmitStartMileage: Failed to prepare update query for mileage_id: $mileageId: " . mysqli_error($this->dbConn));
            throw new Exception("Mileage update preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "dsii", $startMiles, $startMilesDatetime, $mileageId, $userId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Mileage update failed: " . mysqli_stmt_error($stmt));
            error_log("SubmitStartMileage: Update failed for mileage_id: $mileageId, user_id: $userId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Mileage update execution failed");
        }
        mysqli_stmt_close($stmt);

        // Store auth_token in session as fallback
        $_SESSION['auth_token'] = $token;
        error_log("SubmitStartMileage: Stored auth_token in session for user_id: $userId, token: $token");

        mysqli_commit($this->dbConn);
        $response = [
            'success' => true,
            'message' => 'Start mileage submitted successfully',
            'mileage_id' => (int)$mileageId,
            'vehicle_id' => (int)$vehicleId,
            'user_id' => (int)$userId,
            'start_miles' => (float)$startMiles,
            'start_miles_datetime' => $startMilesDatetime,
            'csrf_token' => $this->GetCSRFToken()
        ];
        error_log("SubmitStartMileage: Successfully updated mileage_id: $mileageId for vehicle_id: $vehicleId, user_id: $userId, start_miles: $startMiles, start_miles_datetime: $startMilesDatetime");
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        mysqli_rollback($this->dbConn);
        error_log("SubmitStartMileage: Transaction failed for mileage_id: $mileageId, user_id: $userId: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $this->GetErrorMessage() ?: 'Failed to submit start mileage', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }
}

public function SubmitEndMileage() {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("SubmitEndMileage: Database connection lost");
        echo json_encode(['success' => false, 'message' => 'Database connection lost', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    $vehicleId = isset($_POST['vehicle_id']) ? $this->sanitize($_POST['vehicle_id']) : '';
    $endingMiles = isset($_POST['ending_miles']) ? $this->sanitize($_POST['ending_miles']) : '';
    $endingMilesDatetime = isset($_POST['ending_miles_datetime']) ? $this->sanitize($_POST['ending_miles_datetime']) : '';
    $token = isset($_COOKIE['auth_token']) ? $this->sanitize($_COOKIE['auth_token']) : ($_SESSION['auth_token'] ?? '');
    if (empty($token) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $this->sanitize($matches[1]);
            error_log("SubmitEndMileage: Retrieved auth_token from Authorization header: $token");
        }
    }

    error_log("SubmitEndMileage: Received vehicle_id: $vehicleId, ending_miles: $endingMiles, ending_miles_datetime: $endingMilesDatetime, auth_token: " . ($token ? $token : 'none'));

    // Validate inputs
    if (empty($token)) {
        $this->HandleError("No authentication token provided.");
        error_log("SubmitEndMileage: No auth token provided");
        echo json_encode(['success' => false, 'message' => 'No authentication token provided. Please log in again.', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    if (!is_numeric($vehicleId) || $vehicleId <= 0) {
        $this->HandleError("Invalid vehicle ID.");
        error_log("SubmitEndMileage: Invalid vehicle_id: $vehicleId");
        echo json_encode(['success' => false, 'message' => 'Invalid vehicle ID', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    if (!is_numeric($endingMiles) || $endingMiles < 0 || $endingMiles > 999999.99) {
        $this->HandleError("Ending miles must be a number between 0 and 999999.99.");
        error_log("SubmitEndMileage: Invalid ending_miles: $endingMiles");
        echo json_encode(['success' => false, 'message' => 'Invalid ending miles', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $endingMilesDatetime)) {
        $this->HandleError("Invalid ending miles datetime format. Use YYYY-MM-DD HH:MM:SS.");
        error_log("SubmitEndMileage: Invalid ending_miles_datetime format: $endingMilesDatetime");
        echo json_encode(['success' => false, 'message' => 'Invalid ending miles datetime format', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    // Validate datetime
    try {
        $datetime = new DateTime($endingMilesDatetime, new DateTimeZone('America/New_York'));
        $currentTime = new DateTime('now', new DateTimeZone('America/New_York'));
        if ($datetime > $currentTime) {
            $this->HandleError("Ending miles datetime cannot be in the future.");
            error_log("SubmitEndMileage: Ending miles datetime in future: $endingMilesDatetime");
            echo json_encode(['success' => false, 'message' => 'Ending miles datetime cannot be in the future', 'csrf_token' => $this->GetCSRFToken()]);
            exit;
        }
    } catch (Exception $e) {
        $this->HandleError("Invalid ending miles datetime: " . $e->getMessage());
        error_log("SubmitEndMileage: Invalid ending_miles_datetime: $endingMilesDatetime, error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Invalid ending miles datetime', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    // Rate limiting
    $rateLimitKey = $this->GenerateRateLimitKey('submit_end_mileage', $token);
    if (!$this->checkRateLimit($rateLimitKey, 'submit_end_mileage', 5, 900)) {
        $this->HandleError("Too many end mileage submission attempts.");
        error_log("SubmitEndMileage: Rate limit exceeded for key: $rateLimitKey");
        echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    mysqli_begin_transaction($this->dbConn);
    try {
        // Validate user and role
        $query = "SELECT id, role FROM {$this->usersTable} WHERE auth_token = ? AND is_verified = 1";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare user query: " . mysqli_error($this->dbConn));
            error_log("SubmitEndMileage: Failed to prepare user query for token: $token: " . mysqli_error($this->dbConn));
            throw new Exception("User query preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "s", $token);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("User query failed: " . mysqli_stmt_error($stmt));
            error_log("SubmitEndMileage: User query failed for token: $token: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("User query execution failed");
        }

        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) <= 0) {
            $this->HandleError("Invalid or unverified user.");
            error_log("SubmitEndMileage: No user found for token: $token");
            mysqli_stmt_close($stmt);
            throw new Exception("Authentication failed");
        }

        $row = mysqli_fetch_assoc($result);
        $userId = $row['id'];
        $userRole = $row['role'];
        $allowedRoles = ['driver', 'manager', 'owner'];
        if (!in_array(strtolower($userRole), $allowedRoles)) {
            $this->HandleError("Only Drivers, Managers, or Owners can submit mileage.");
            error_log("SubmitEndMileage: Unauthorized role for user_id: $userId, role: $userRole");
            mysqli_stmt_close($stmt);
            throw new Exception("Unauthorized role");
        }
        mysqli_stmt_close($stmt);

        // Verify vehicle exists and is assigned to the user
        $query = "SELECT vehicle_id, current_user_id FROM {$this->vehiclesTable} WHERE vehicle_id = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare vehicle query: " . mysqli_error($this->dbConn));
            error_log("SubmitEndMileage: Failed to prepare vehicle query for vehicle_id: $vehicleId: " . mysqli_error($this->dbConn));
            throw new Exception("Vehicle query preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "i", $vehicleId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Vehicle query failed: " . mysqli_stmt_error($stmt));
            error_log("SubmitEndMileage: Vehicle query failed for vehicle_id: $vehicleId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Vehicle query execution failed");
        }

        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) <= 0) {
            $this->HandleError("No vehicle found with the specified ID.");
            error_log("SubmitEndMileage: No vehicle found for vehicle_id: $vehicleId");
            mysqli_stmt_close($stmt);
            throw new Exception("No vehicle found");
        }

        $row = mysqli_fetch_assoc($result);
        if ($row['current_user_id'] != $userId) {
            $this->HandleError("Vehicle is not assigned to this user.");
            error_log("SubmitEndMileage: Vehicle $vehicleId not assigned to user_id: $userId, current_user_id: {$row['current_user_id']}");
            mysqli_stmt_close($stmt);
            throw new Exception("Vehicle not assigned");
        }
        mysqli_stmt_close($stmt);

        // Find the most recent mileage record with start_miles set for this user and vehicle
        $query = "SELECT mileage_id, start_miles, start_miles_datetime FROM mileage_records 
                  WHERE vehicle_id = ? AND user_id = ? 
                  AND start_miles IS NOT NULL AND start_miles_datetime IS NOT NULL 
                  AND (ending_miles IS NULL OR ending_miles_datetime IS NULL) 
                  ORDER BY start_miles_datetime DESC LIMIT 1";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare mileage record query: " . mysqli_error($this->dbConn));
            error_log("SubmitEndMileage: Failed to prepare mileage record query for vehicle_id: $vehicleId, user_id: $userId: " . mysqli_error($this->dbConn));
            throw new Exception("Mileage record query preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "ii", $vehicleId, $userId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Mileage record query failed: " . mysqli_stmt_error($stmt));
            error_log("SubmitEndMileage: Mileage record query failed for vehicle_id: $vehicleId, user_id: $userId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Mileage record query execution failed");
        }

        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) <= 0) {
            $this->HandleError("No mileage record with start miles found for this user and vehicle.");
            error_log("SubmitEndMileage: No mileage record with start miles found for vehicle_id: $vehicleId, user_id: $userId");
            mysqli_stmt_close($stmt);
            throw new Exception("No suitable mileage record");
        }

        $row = mysqli_fetch_assoc($result);
        $mileageId = $row['mileage_id'];
        $startMiles = $row['start_miles'];
        $startMilesDatetime = $row['start_miles_datetime'];
        mysqli_stmt_close($stmt);

        // Validate ending_miles is greater than or equal to start_miles
        if ($endingMiles < $startMiles) {
            $this->HandleError("Ending miles must be greater than or equal to start miles ($startMiles).");
            error_log("SubmitEndMileage: Ending miles $endingMiles less than start miles $startMiles for mileage_id: $mileageId");
            throw new Exception("Invalid ending miles");
        }

        // Validate ending_miles_datetime is after start_miles_datetime
        try {
            $endDatetime = new DateTime($endingMilesDatetime, new DateTimeZone('America/New_York'));
            $startDatetime = new DateTime($startMilesDatetime, new DateTimeZone('America/New_York'));
            if ($endDatetime <= $startDatetime) {
                $this->HandleError("Ending miles datetime must be after start miles datetime ($startMilesDatetime).");
                error_log("SubmitEndMileage: Ending datetime $endingMilesDatetime not after start datetime $startMilesDatetime for mileage_id: $mileageId");
                throw new Exception("Invalid ending datetime");
            }
        } catch (Exception $e) {
            $this->HandleError("Invalid datetime comparison: " . $e->getMessage());
            error_log("SubmitEndMileage: Datetime comparison error for mileage_id: $mileageId: " . $e->getMessage());
            throw new Exception("Datetime comparison failed");
        }

        // Update the mileage record with ending_miles and ending_miles_datetime
        $query = "UPDATE mileage_records SET ending_miles = ?, ending_miles_datetime = ? WHERE mileage_id = ? AND vehicle_id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare mileage update query: " . mysqli_error($this->dbConn));
            error_log("SubmitEndMileage: Failed to prepare update query for mileage_id: $mileageId: " . mysqli_error($this->dbConn));
            throw new Exception("Mileage update preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "dsiii", $endingMiles, $endingMilesDatetime, $mileageId, $vehicleId, $userId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Mileage update failed: " . mysqli_stmt_error($stmt));
            error_log("SubmitEndMileage: Update failed for mileage_id: $mileageId, vehicle_id: $vehicleId, user_id: $userId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Mileage update execution failed");
        }
        mysqli_stmt_close($stmt);

        // Update vehicle mileage in the vehicles table
        $query = "UPDATE {$this->vehiclesTable} SET mileage = ? WHERE vehicle_id = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare vehicle mileage update query: " . mysqli_error($this->dbConn));
            error_log("SubmitEndMileage: Failed to prepare vehicle mileage update for vehicle_id: $vehicleId: " . mysqli_error($this->dbConn));
            throw new Exception("Vehicle mileage update preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "di", $endingMiles, $vehicleId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Vehicle mileage update failed: " . mysqli_stmt_error($stmt));
            error_log("SubmitEndMileage: Vehicle mileage update failed for vehicle_id: $vehicleId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Vehicle mileage update execution failed");
        }
        mysqli_stmt_close($stmt);

        // Store auth_token in session as fallback
        $_SESSION['auth_token'] = $token;
        error_log("SubmitEndMileage: Stored auth_token in session for user_id: $userId, token: $token");

        mysqli_commit($this->dbConn);
        $response = [
            'success' => true,
            'message' => 'End mileage submitted successfully',
            'mileage_id' => (int)$mileageId,
            'vehicle_id' => (int)$vehicleId,
            'user_id' => (int)$userId,
            'start_miles' => (float)$startMiles,
            'start_miles_datetime' => $startMilesDatetime,
            'ending_miles' => (float)$endingMiles,
            'ending_miles_datetime' => $endingMilesDatetime,
            'csrf_token' => $this->GetCSRFToken()
        ];
        error_log("SubmitEndMileage: Successfully updated mileage_id: $mileageId for vehicle_id: $vehicleId, user_id: $userId, ending_miles: $endingMiles, ending_miles_datetime: $endingMilesDatetime");
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        mysqli_rollback($this->dbConn);
        error_log("SubmitEndMileage: Transaction failed for vehicle_id: $vehicleId, user_id: $userId: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $this->GetErrorMessage() ?: 'Failed to submit end mileage', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }
}

public function GetVehicleMileageRecords($vehicle_id, $user_id) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("GetVehicleMileageRecords: Database connection failed");
        return false;
    }

    $vehicle_id = $this->sanitize($vehicle_id);
    $user_id = $this->sanitize($user_id);

    // Verify vehicle exists and is assigned to user
    $query = "SELECT vehicle_id FROM {$this->vehiclesTable} WHERE vehicle_id = ? AND current_user_id = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare vehicle check query: " . mysqli_error($this->dbConn));
        error_log("GetVehicleMileageRecords: Failed to prepare vehicle check query: " . mysqli_error($this->dbConn));
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $vehicle_id, $user_id);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Vehicle check failed: " . mysqli_stmt_error($stmt));
        error_log("GetVehicleMileageRecords: Vehicle check failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) === 0) {
        $this->HandleError("No vehicle found with the specified ID for this user.");
        error_log("GetVehicleMileageRecords: No vehicle found for vehicle_id: $vehicle_id, user_id: $user_id");
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);

    // Fetch the most recent mileage record
    $query = "SELECT mileage_id, vehicle_id, user_id, start_miles, start_miles_datetime, ending_miles, ending_miles_datetime, created_at
              FROM mileage_records
              WHERE vehicle_id = ? AND user_id = ?
              ORDER BY created_at DESC
              LIMIT 1";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare mileage record query: " . mysqli_error($this->dbConn));
        error_log("GetVehicleMileageRecords: Failed to prepare mileage record query: " . mysqli_error($this->dbConn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $vehicle_id, $user_id);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Mileage record query failed: " . mysqli_stmt_error($stmt));
        error_log("GetVehicleMileageRecords: Mileage record query failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    $mileage_record = null;
    if ($row = mysqli_fetch_assoc($result)) {
        $mileage_record = [
            'mileage_id' => (int)$row['mileage_id'],
            'vehicle_id' => (int)$row['vehicle_id'],
            'user_id' => (int)$row['user_id'],
            'start_miles' => $row['start_miles'] !== null ? (float)$row['start_miles'] : null,
            'start_miles_datetime' => $row['start_miles_datetime'],
            'ending_miles' => $row['ending_miles'] !== null ? (float)$row['ending_miles'] : null,
            'ending_miles_datetime' => $row['ending_miles_datetime'],
            'created_at' => $row['created_at']
        ];
    }
    mysqli_stmt_close($stmt);
    error_log("GetVehicleMileageRecords: Fetched " . ($mileage_record ? 1 : 0) . " mileage record for vehicle_id: $vehicle_id, user_id: $user_id");

    return [
        'success' => true,
        'mileage_record' => $mileage_record
    ];
}

public function AddMileageRecord($vehicle_id, $user_id) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("AddMileageRecord: Database connection failed");
        return false;
    }

    // Ensure mileage table exists
    if (!$this->ensureMileageTableExists()) {
        $this->HandleError("Failed to ensure mileage_records table exists.");
        error_log("AddMileageRecord: Failed to ensure mileage_records table");
        return false;
    }

    $vehicle_id = $this->sanitize($vehicle_id);
    $user_id = $this->sanitize($user_id);

    // Validate inputs
    if (!is_numeric($vehicle_id) || $vehicle_id <= 0) {
        $this->HandleError("Invalid vehicle ID.");
        error_log("AddMileageRecord: Invalid vehicle_id: $vehicle_id");
        return ['success' => false, 'message' => 'Invalid vehicle ID'];
    }

    if (!is_numeric($user_id) || $user_id <= 0) {
        $this->HandleError("Invalid user ID.");
        error_log("AddMileageRecord: Invalid user_id: $user_id");
        return ['success' => false, 'message' => 'Invalid user ID'];
    }

    // Verify vehicle exists
    $query = "SELECT vehicle_id FROM {$this->vehiclesTable} WHERE vehicle_id = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare vehicle check query: " . mysqli_error($this->dbConn));
        error_log("AddMileageRecord: Failed to prepare vehicle check for vehicle_id: $vehicle_id");
        return ['success' => false, 'message' => 'Failed to verify vehicle'];
    }
    mysqli_stmt_bind_param($stmt, 'i', $vehicle_id);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Vehicle check failed: " . mysqli_stmt_error($stmt));
        error_log("AddMileageRecord: Vehicle check failed for vehicle_id: $vehicle_id: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Vehicle verification failed'];
    }
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) === 0) {
        $this->HandleError("No vehicle found with the specified ID.");
        error_log("AddMileageRecord: No vehicle found for vehicle_id: $vehicle_id");
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'No vehicle found with the specified ID'];
    }
    mysqli_stmt_close($stmt);

    // Verify user exists
    $query = "SELECT id FROM {$this->usersTable} WHERE id = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare user check query: " . mysqli_error($this->dbConn));
        error_log("AddMileageRecord: Failed to prepare user check for user_id: $user_id");
        return ['success' => false, 'message' => 'Failed to verify user'];
    }
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("User check failed: " . mysqli_stmt_error($stmt));
        error_log("AddMileageRecord: User check failed for user_id: $user_id: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'User verification failed'];
    }
    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) === 0) {
        $this->HandleError("No user found with the specified ID.");
        error_log("AddMileageRecord: No user found for user_id: $user_id");
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'No user found with the specified ID'];
    }
    mysqli_stmt_close($stmt);

    // Insert mileage record with NULL values for mileage and times, created_at defaults to CURRENT_TIMESTAMP
    $query = "INSERT INTO mileage_records (vehicle_id, user_id, start_miles, start_miles_datetime, ending_miles, ending_miles_datetime) VALUES (?, ?, NULL, NULL, NULL, NULL)";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare mileage insert query: " . mysqli_error($this->dbConn));
        error_log("AddMileageRecord: Failed to prepare insert query for vehicle_id: $vehicle_id, user_id: $user_id");
        return ['success' => false, 'message' => 'Failed to prepare mileage record insertion'];
    }

    mysqli_stmt_bind_param($stmt, 'ii', $vehicle_id, $user_id);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Mileage record creation failed: " . mysqli_stmt_error($stmt));
        error_log("AddMileageRecord: Insert failed for vehicle_id: $vehicle_id, user_id: $user_id: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return ['success' => false, 'message' => 'Mileage record creation failed'];
    }

    $mileage_id = mysqli_insert_id($this->dbConn);
    mysqli_stmt_close($stmt);
    error_log("AddMileageRecord: Successfully added mileage record ID: $mileage_id for vehicle_id: $vehicle_id, user_id: $user_id with NULL mileage and times, created_at set to CURRENT_TIMESTAMP");

    return [
        'success' => true,
        'mileage_id' => $mileage_id
    ];
}

public function AssignVehicleByVinAllowIncompleteEndingMiles() {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Database connection lost");
        echo json_encode(['success' => false, 'message' => 'Database connection lost', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    $vinSuffix = isset($_POST['vin_suffix']) ? $this->sanitize($_POST['vin_suffix']) : '';
    $token = isset($_COOKIE['auth_token']) ? $this->sanitize($_COOKIE['auth_token']) : ($_SESSION['auth_token'] ?? '');
    if (empty($token) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $this->sanitize($matches[1]);
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Retrieved auth_token from Authorization header: $token");
        }
    }

    error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Received vin_suffix: $vinSuffix, auth_token: " . ($token ? $token : 'none'));

    if (empty($token)) {
        $this->HandleError("No authentication token provided.");
        error_log("AssignVehicleByVinAllowIncompleteEndingMiles: No auth token provided");
        echo json_encode(['success' => false, 'message' => 'No authentication token provided. Please log in again.', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    if (empty($vinSuffix) || strlen($vinSuffix) != 6 || !preg_match('/^[A-HJ-NPR-Z0-9]{6}$/i', $vinSuffix)) {
        $this->HandleError("Invalid VIN suffix format.");
        error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Invalid VIN suffix format: $vinSuffix");
        echo json_encode(['success' => false, 'message' => 'Invalid VIN suffix', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    $rateLimitKey = $this->GenerateRateLimitKey('assign_vehicle', $token);
    if (!$this->checkRateLimit($rateLimitKey, 'assign_vehicle', 5, 900)) {
        $this->HandleError("Too many vehicle assignment attempts.");
        error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Rate limit exceeded for key: $rateLimitKey");
        echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    mysqli_begin_transaction($this->dbConn);
    try {
        // Validate user and role
        $query = "SELECT id, role FROM {$this->usersTable} WHERE auth_token = ? AND is_verified = 1";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare user query: " . mysqli_error($this->dbConn));
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Failed to prepare user query for token: $token: " . mysqli_error($this->dbConn));
            throw new Exception("User query preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "s", $token);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("User query failed: " . mysqli_stmt_error($stmt));
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: User query failed for token: $token: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("User query execution failed");
        }

        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) <= 0) {
            $this->HandleError("Invalid or unverified user.");
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: No user found for token: $token");
            mysqli_stmt_close($stmt);
            throw new Exception("Authentication failed");
        }

        $row = mysqli_fetch_assoc($result);
        $userId = $row['id'];
        $userRole = $row['role'];
        $allowedRoles = ['driver', 'manager', 'owner'];
        if (!in_array(strtolower($userRole), $allowedRoles)) {
            $this->HandleError("Only Drivers, Managers, or Owners can assign vehicles.");
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Unauthorized role for user_id: $userId, role: $userRole");
            mysqli_stmt_close($stmt);
            throw new Exception("Unauthorized role");
        }
        mysqli_stmt_close($stmt);

        // Check for incomplete mileage records, only validating start_miles and start_miles_datetime
        $query = "SELECT mileage_id, vehicle_id, start_miles, start_miles_datetime 
                  FROM mileage_records 
                  WHERE user_id = ? 
                  AND (start_miles IS NULL OR start_miles_datetime IS NULL)";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare mileage check query: " . mysqli_error($this->dbConn));
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Failed to prepare mileage check for user_id: $userId: " . mysqli_error($this->dbConn));
            throw new Exception("Mileage check preparation failed");
        }
        mysqli_stmt_bind_param($stmt, "i", $userId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Mileage check failed: " . mysqli_stmt_error($stmt));
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Mileage check failed for user_id: $userId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Mileage check execution failed");
        }
        $result = mysqli_stmt_get_result($stmt);
        $incompleteRecords = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $missingFields = [];
            if (is_null($row['start_miles'])) $missingFields[] = 'start_miles';
            if (is_null($row['start_miles_datetime'])) $missingFields[] = 'start_miles_datetime';
            if (!empty($missingFields)) {
                $incompleteRecords[] = [
                    'mileage_id' => (int)$row['mileage_id'],
                    'vehicle_id' => (int)$row['vehicle_id'],
                    'missing_fields' => $missingFields
                ];
            }
        }
        mysqli_stmt_close($stmt);
        if (!empty($incompleteRecords)) {
            $this->HandleError("You have incomplete mileage records (start miles or start datetime). Please complete them before assigning a vehicle.");
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Incomplete mileage records found for user_id: $userId: " . json_encode($incompleteRecords));
            throw new Exception("Incomplete mileage records exist");
        }

        // Find vehicle by VIN suffix (case-insensitive, with length check)
        $query = "SELECT v.vehicle_id, v.vin, v.make, v.model, v.color, v.license_plate, v.year, v.image_location, v.current_user_id, v.last_user_id, v.date_assigned, v.date_last_used, v.mileage, p.name AS current_user_name 
                  FROM {$this->vehiclesTable} v 
                  LEFT JOIN {$this->profilesTable} p ON v.current_user_id = p.user_id 
                  WHERE LENGTH(v.vin) >= 6 AND LOWER(RIGHT(v.vin, 6)) = LOWER(?)";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare vehicle query: " . mysqli_error($this->dbConn));
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Failed to prepare vehicle query for vin_suffix: $vinSuffix: " . mysqli_error($this->dbConn));
            throw new Exception("Vehicle query preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "s", $vinSuffix);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Vehicle query failed: " . mysqli_stmt_error($stmt));
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Vehicle query failed for vin_suffix: $vinSuffix: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Vehicle query execution failed");
        }

        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) <= 0) {
            $this->HandleError("No vehicle found with VIN suffix $vinSuffix.");
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: No vehicle found for vin_suffix: $vinSuffix");
            mysqli_stmt_close($stmt);
            throw new Exception("No vehicle found");
        }
        if (mysqli_num_rows($result) > 1) {
            $this->HandleError("Multiple vehicles found with VIN suffix $vinSuffix.");
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Ambiguous VIN suffix: $vinSuffix, found multiple vehicles");
            mysqli_stmt_close($stmt);
            throw new Exception("Multiple vehicles match VIN suffix");
        }

        $row = mysqli_fetch_assoc($result);
        $vehicleId = $row['vehicle_id'];
        if (!is_numeric($vehicleId) || $vehicleId <= 0) {
            $this->HandleError("Invalid vehicle ID retrieved for VIN suffix $vinSuffix.");
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Invalid vehicle_id: $vehicleId for vin_suffix: $vinSuffix");
            mysqli_stmt_close($stmt);
            throw new Exception("Invalid vehicle ID");
        }
        $previousUserId = $row['current_user_id'] ? (int)$row['current_user_id'] : null;
        $currentUserName = $row['current_user_name'] ?? 'Unknown';
        $vehicleDetails = [
            'vehicle_id' => (int)$row['vehicle_id'],
            'image_location' => $row['image_location'],
            'make' => $row['make'],
            'model' => $row['model'],
            'vin' => $row['vin'],
            'color' => $row['color'],
            'license_plate' => $row['license_plate'],
            'year' => (int)$row['year'],
            'current_user_id' => $previousUserId,
            'last_user_id' => $row['last_user_id'] ? (int)$row['last_user_id'] : null,
            'date_assigned' => $row['date_assigned'],
            'date_last_used' => $row['date_last_used'],
            'mileage' => $row['mileage'] ? (float)$row['mileage'] : null
        ];
        mysqli_stmt_close($stmt);

        // Check if vehicle is already assigned to another user
        if ($previousUserId !== null && $previousUserId != $userId) {
            $this->HandleError("Vehicle is already assigned to $currentUserName.");
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Vehicle_id: $vehicleId is already assigned to user_id: $previousUserId (name: $currentUserName), cannot assign to user_id: $userId");
            throw new Exception("Vehicle already assigned");
        }

        // Unassign user from other vehicles
        $currentTime = gmdate('Y-m-d H:i:s', time() - 4 * 3600); // UTC-04:00
        $query = "UPDATE {$this->vehiclesTable} SET current_user_id = NULL, last_user_id = ?, date_last_used = ? WHERE current_user_id = ? AND vehicle_id != ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare unassignment query: " . mysqli_error($this->dbConn));
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Failed to prepare unassignment query for user_id: $userId: " . mysqli_error($this->dbConn));
            throw new Exception("Unassignment preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "isii", $userId, $currentTime, $userId, $vehicleId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Unassignment failed: " . mysqli_stmt_error($stmt));
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Unassignment failed for user_id: $userId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Unassignment execution failed");
        }
        $unassignedCount = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Unassigned user_id: $userId from $unassignedCount other vehicles");

        // Assign user to the vehicle, updating last_user_id if there was a previous user
        $query = "UPDATE {$this->vehiclesTable} SET current_user_id = ?, last_user_id = ?, date_assigned = ? WHERE vehicle_id = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare vehicle assignment query: " . mysqli_error($this->dbConn));
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Failed to prepare vehicle assignment query for vehicle_id: $vehicleId: " . mysqli_error($this->dbConn));
            throw new Exception("Assignment preparation failed");
        }

        $lastUserId = $previousUserId ?? null;
        mysqli_stmt_bind_param($stmt, "iisi", $userId, $lastUserId, $currentTime, $vehicleId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Vehicle assignment failed: " . mysqli_stmt_error($stmt));
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Vehicle assignment failed for vehicle_id: $vehicleId, user_id: $userId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Assignment execution failed");
        }
        mysqli_stmt_close($stmt);

        // Add mileage record if assignment is successful
        $mileageResult = $this->AddMileageRecord($vehicleId, $userId);
        if (!$mileageResult || !$mileageResult['success'] || !isset($mileageResult['mileage_id'])) {
            $this->HandleError("Failed to add mileage record: " . ($this->GetErrorMessage() ?: 'Unknown error'));
            error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Failed to add mileage record for vehicle_id: $vehicleId, user_id: $userId");
            throw new Exception("Mileage record creation failed");
        }
        $mileageId = $mileageResult['mileage_id'];

        // Store auth_token in session as fallback
        $_SESSION['auth_token'] = $token;
        error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Stored auth_token in session for user_id: $userId, token: $token");

        mysqli_commit($this->dbConn);
        $response = [
            'success' => true,
            'message' => 'Vehicle assigned successfully',
            'vehicle' => $vehicleDetails,
            'user_id' => (int)$userId,
            'mileage_id' => (int)$mileageId,
            'csrf_token' => $this->GetCSRFToken()
        ];
        error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Vehicle assigned successfully for user_id: $userId, vehicle_id: $vehicleId, vin: {$row['vin']}, vin_suffix: $vinSuffix, role: $userRole, mileage_id: $mileageId, previous_user_id: " . ($previousUserId ?? 'none') . " at $currentTime");
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        mysqli_rollback($this->dbConn);
        $response = [
            'success' => false,
            'message' => $this->GetErrorMessage() ?: 'Failed to assign vehicle',
            'csrf_token' => $this->GetCSRFToken()
        ];
        if ($e->getMessage() === "Incomplete mileage records exist") {
            $response['incomplete_records'] = $incompleteRecords;
        } elseif ($e->getMessage() === "Vehicle already assigned") {
            $response['assigned_to'] = $currentUserName;
        }
        error_log("AssignVehicleByVinAllowIncompleteEndingMiles: Transaction failed for user_id: $userId, vehicle_id: $vehicleId: " . $e->getMessage());
        echo json_encode($response);
        exit;
    }
}

public function AssignVehicleByVin() {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("AssignVehicleByVin: Database connection lost");
        echo json_encode(['success' => false, 'message' => 'Database connection lost', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    $vinSuffix = isset($_POST['vin_suffix']) ? $this->sanitize($_POST['vin_suffix']) : '';
    $token = isset($_COOKIE['auth_token']) ? $this->sanitize($_COOKIE['auth_token']) : ($_SESSION['auth_token'] ?? '');
    if (empty($token) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $this->sanitize($matches[1]);
            error_log("AssignVehicleByVin: Retrieved auth_token from Authorization header: $token");
        }
    }

    error_log("AssignVehicleByVin: Received vin_suffix: $vinSuffix, auth_token: " . ($token ? $token : 'none'));

    if (empty($token)) {
        $this->HandleError("No authentication token provided.");
        error_log("AssignVehicleByVin: No auth token provided");
        echo json_encode(['success' => false, 'message' => 'No authentication token provided. Please log in again.', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    if (empty($vinSuffix) || strlen($vinSuffix) != 6 || !preg_match('/^[A-HJ-NPR-Z0-9]{6}$/i', $vinSuffix)) {
        $this->HandleError("Invalid VIN suffix format.");
        error_log("AssignVehicleByVin: Invalid VIN suffix format: $vinSuffix");
        echo json_encode(['success' => false, 'message' => 'Invalid VIN suffix', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    $rateLimitKey = $this->GenerateRateLimitKey('assign_vehicle', $token);
    if (!$this->checkRateLimit($rateLimitKey, 'assign_vehicle', 5, 900)) {
        $this->HandleError("Too many vehicle assignment attempts.");
        error_log("AssignVehicleByVin: Rate limit exceeded for key: $rateLimitKey");
        echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.', 'csrf_token' => $this->GetCSRFToken()]);
        exit;
    }

    mysqli_begin_transaction($this->dbConn);
    try {
        // Validate user and role
        $query = "SELECT id, role FROM {$this->usersTable} WHERE auth_token = ? AND is_verified = 1";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare user query: " . mysqli_error($this->dbConn));
            error_log("AssignVehicleByVin: Failed to prepare user query for token: $token: " . mysqli_error($this->dbConn));
            throw new Exception("User query preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "s", $token);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("User query failed: " . mysqli_stmt_error($stmt));
            error_log("AssignVehicleByVin: User query failed for token: $token: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("User query execution failed");
        }

        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) <= 0) {
            $this->HandleError("Invalid or unverified user.");
            error_log("AssignVehicleByVin: No user found for token: $token");
            mysqli_stmt_close($stmt);
            throw new Exception("Authentication failed");
        }

        $row = mysqli_fetch_assoc($result);
        $userId = $row['id'];
        $userRole = $row['role'];
        $allowedRoles = ['driver', 'manager', 'owner'];
        if (!in_array(strtolower($userRole), $allowedRoles)) {
            $this->HandleError("Only Drivers, Managers, or Owners can assign vehicles.");
            error_log("AssignVehicleByVin: Unauthorized role for user_id: $userId, role: $userRole");
            mysqli_stmt_close($stmt);
            throw new Exception("Unauthorized role");
        }
        mysqli_stmt_close($stmt);

        // Check for any incomplete mileage records for this user
        $query = "SELECT mileage_id, vehicle_id, start_miles, start_miles_datetime, ending_miles, ending_miles_datetime 
                  FROM mileage_records 
                  WHERE user_id = ? 
                  AND (start_miles IS NULL OR start_miles_datetime IS NULL OR ending_miles IS NULL OR ending_miles_datetime IS NULL)";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare mileage check query: " . mysqli_error($this->dbConn));
            error_log("AssignVehicleByVin: Failed to prepare mileage check for user_id: $userId: " . mysqli_error($this->dbConn));
            throw new Exception("Mileage check preparation failed");
        }
        mysqli_stmt_bind_param($stmt, "i", $userId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Mileage check failed: " . mysqli_stmt_error($stmt));
            error_log("AssignVehicleByVin: Mileage check failed for user_id: $userId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Mileage check execution failed");
        }
        $result = mysqli_stmt_get_result($stmt);
        $incompleteRecords = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $missingFields = [];
            if (is_null($row['start_miles'])) $missingFields[] = 'start_miles';
            if (is_null($row['start_miles_datetime'])) $missingFields[] = 'start_miles_datetime';
            if (is_null($row['ending_miles'])) $missingFields[] = 'ending_miles';
            if (is_null($row['ending_miles_datetime'])) $missingFields[] = 'ending_miles_datetime';
            $incompleteRecords[] = [
                'mileage_id' => (int)$row['mileage_id'],
                'vehicle_id' => (int)$row['vehicle_id'],
                'user_id' => (int)$row['user_id'],
                'missing_fields' => $missingFields
            ];
        }
        mysqli_stmt_close($stmt);
        if (!empty($incompleteRecords)) {
            $this->HandleError("You have incomplete mileage records. Please complete them before assigning a vehicle.");
            error_log("AssignVehicleByVin: Incomplete mileage records found for user_id: $userId: " . json_encode($incompleteRecords));
            throw new Exception("Incomplete mileage records exist");
        }

        // Find vehicle by VIN suffix (case-insensitive, with length check)
        $query = "SELECT v.vehicle_id, v.vin, v.make, v.model, v.color, v.license_plate, v.year, v.image_location, v.current_user_id, v.last_user_id, v.date_assigned, v.date_last_used, v.mileage, p.name AS current_user_name 
                  FROM {$this->vehiclesTable} v 
                  LEFT JOIN {$this->profilesTable} p ON v.current_user_id = p.user_id 
                  WHERE LENGTH(v.vin) >= 6 AND LOWER(RIGHT(v.vin, 6)) = LOWER(?)";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare vehicle query: " . mysqli_error($this->dbConn));
            error_log("AssignVehicleByVin: Failed to prepare vehicle query for vin_suffix: $vinSuffix: " . mysqli_error($this->dbConn));
            throw new Exception("Vehicle query preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "s", $vinSuffix);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Vehicle query failed: " . mysqli_stmt_error($stmt));
            error_log("AssignVehicleByVin: Vehicle query failed for vin_suffix: $vinSuffix: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Vehicle query execution failed");
        }

        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) <= 0) {
            $this->HandleError("No vehicle found with VIN suffix $vinSuffix.");
            error_log("AssignVehicleByVin: No vehicle found for vin_suffix: $vinSuffix");
            mysqli_stmt_close($stmt);
            throw new Exception("No vehicle found");
        }
        if (mysqli_num_rows($result) > 1) {
            $this->HandleError("Multiple vehicles found with VIN suffix $vinSuffix.");
            error_log("AssignVehicleByVin: Ambiguous VIN suffix: $vinSuffix, found multiple vehicles");
            mysqli_stmt_close($stmt);
            throw new Exception("Multiple vehicles match VIN suffix");
        }

        $row = mysqli_fetch_assoc($result);
        $vehicleId = $row['vehicle_id'];
        if (!is_numeric($vehicleId) || $vehicleId <= 0) {
            $this->HandleError("Invalid vehicle ID retrieved for VIN suffix $vinSuffix.");
            error_log("AssignVehicleByVin: Invalid vehicle_id: $vehicleId for vin_suffix: $vinSuffix");
            mysqli_stmt_close($stmt);
            throw new Exception("Invalid vehicle ID");
        }
        $previousUserId = $row['current_user_id'] ? (int)$row['current_user_id'] : null;
        $currentUserName = $row['current_user_name'] ?? 'Unknown';
        mysqli_stmt_close($stmt);

        // Check if vehicle is already assigned to another user
        if ($previousUserId !== null && $previousUserId != $userId) {
            $this->HandleError("Vehicle is already assigned to $currentUserName.");
            error_log("AssignVehicleByVin: Vehicle_id: $vehicleId is already assigned to user_id: $previousUserId (name: $currentUserName), cannot assign to user_id: $userId");
            throw new Exception("Vehicle already assigned");
        }

        // Unassign user from other vehicles
        $currentTime = gmdate('Y-m-d H:i:s', time() - 4 * 3600); // UTC-04:00
        $query = "UPDATE {$this->vehiclesTable} SET current_user_id = NULL, last_user_id = ?, date_last_used = ? WHERE current_user_id = ? AND vehicle_id != ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare unassignment query: " . mysqli_error($this->dbConn));
            error_log("AssignVehicleByVin: Failed to prepare unassignment query for user_id: $userId: " . mysqli_error($this->dbConn));
            throw new Exception("Unassignment preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "isii", $userId, $currentTime, $userId, $vehicleId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Unassignment failed: " . mysqli_stmt_error($stmt));
            error_log("AssignVehicleByVin: Unassignment failed for user_id: $userId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Unassignment execution failed");
        }
        $unassignedCount = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        error_log("AssignVehicleByVin: Unassigned user_id: $userId from $unassignedCount other vehicles");

        // Assign user to the vehicle, updating last_user_id if there was a previous user
        $query = "UPDATE {$this->vehiclesTable} SET current_user_id = ?, last_user_id = ?, date_assigned = ? WHERE vehicle_id = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare vehicle assignment query: " . mysqli_error($this->dbConn));
            error_log("AssignVehicleByVin: Failed to prepare vehicle assignment query for vehicle_id: $vehicleId: " . mysqli_error($this->dbConn));
            throw new Exception("Assignment preparation failed");
        }

        $lastUserId = $previousUserId ?? null;
        mysqli_stmt_bind_param($stmt, "iisi", $userId, $lastUserId, $currentTime, $vehicleId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Vehicle assignment failed: " . mysqli_stmt_error($stmt));
            error_log("AssignVehicleByVin: Vehicle assignment failed for vehicle_id: $vehicleId, user_id: $userId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Assignment execution failed");
        }
        mysqli_stmt_close($stmt);

        // Add mileage record if assignment is successful
        $mileageResult = $this->AddMileageRecord($vehicleId, $userId);
        if (!$mileageResult || !$mileageResult['success'] || !isset($mileageResult['mileage_id'])) {
            $this->HandleError("Failed to add mileage record: " . ($this->GetErrorMessage() ?: 'Unknown error'));
            error_log("AssignVehicleByVin: Failed to add mileage record for vehicle_id: $vehicleId, user_id: $userId");
            throw new Exception("Mileage record creation failed");
        }
        $mileageId = $mileageResult['mileage_id'];

        // Re-fetch vehicle details to ensure current_user_id reflects the update
        $query = "SELECT vehicle_id, vin, make, model, color, license_plate, year, image_location, current_user_id, last_user_id, date_assigned, date_last_used, mileage 
                  FROM {$this->vehiclesTable} WHERE vehicle_id = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare vehicle re-fetch query: " . mysqli_error($this->dbConn));
            error_log("AssignVehicleByVin: Failed to prepare vehicle re-fetch query for vehicle_id: $vehicleId: " . mysqli_error($this->dbConn));
            throw new Exception("Vehicle re-fetch query preparation failed");
        }

        mysqli_stmt_bind_param($stmt, "i", $vehicleId);
        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Vehicle re-fetch query failed: " . mysqli_stmt_error($stmt));
            error_log("AssignVehicleByVin: Vehicle re-fetch query failed for vehicle_id: $vehicleId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            throw new Exception("Vehicle re-fetch query execution failed");
        }

        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $vehicleDetails = [
            'vehicle_id' => (int)$row['vehicle_id'],
            'image_location' => $row['image_location'],
            'make' => $row['make'],
            'model' => $row['model'],
            'vin' => $row['vin'],
            'color' => $row['color'],
            'license_plate' => $row['license_plate'],
            'year' => (int)$row['year'],
            'current_user_id' => (int)$row['current_user_id'],
            'last_user_id' => $row['last_user_id'] ? (int)$row['last_user_id'] : null,
            'date_assigned' => $row['date_assigned'],
            'date_last_used' => $row['date_last_used'],
            'mileage' => $row['mileage'] ? (float)$row['mileage'] : null,
            'mileage_record' => [
                'mileage_id' => (int)$mileageId,
                'vehicle_id' => (int)$vehicleId,
                'user_id' => (int)$userId
            ]
        ];
        mysqli_stmt_close($stmt);

        // Store auth_token in session as fallback
        $_SESSION['auth_token'] = $token;
        error_log("AssignVehicleByVin: Stored auth_token in session for user_id: $userId, token: $token");

        mysqli_commit($this->dbConn);
        $response = [
            'success' => true,
            'message' => 'Vehicle assigned successfully',
            'vehicle' => $vehicleDetails,
            'user_id' => (int)$userId,
            'mileage_id' => (int)$mileageId,
            'csrf_token' => $this->GetCSRFToken()
        ];
        error_log("AssignVehicleByVin: Vehicle assigned successfully for user_id: $userId, vehicle_id: $vehicleId, vin: {$row['vin']}, vin_suffix: $vinSuffix, role: $userRole, mileage_id: $mileageId, current_user_id: {$vehicleDetails['current_user_id']}, previous_user_id: " . ($previousUserId ?? 'none') . " at $currentTime");
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        mysqli_rollback($this->dbConn);
        $response = [
            'success' => false,
            'message' => $this->GetErrorMessage() ?: 'Failed to assign vehicle',
            'csrf_token' => $this->GetCSRFToken()
        ];
        if ($e->getMessage() === "Incomplete mileage records exist") {
            $response['incomplete_records'] = $incompleteRecords;
        } elseif ($e->getMessage() === "Vehicle already assigned") {
            $response['assigned_to'] = $currentUserName;
        }
        error_log("AssignVehicleByVin: Transaction failed for user_id: $userId, vehicle_id: $vehicleId: " . $e->getMessage());
        echo json_encode($response);
        exit;
    }
}

public function handleRequest() {
    header('Content-Type: application/json');
    header('Connection: keep-alive');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
        $response = ['success' => false, 'message' => 'Invalid request', 'csrf_token' => $this->GetCSRFToken()];
        error_log("handleRequest: Invalid request - Method: {$_SERVER['REQUEST_METHOD']}, Action: " . ($_POST['action'] ?? 'none'));
        echo json_encode($response);
        exit;
    }

    $action = $_POST['action'];
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $this->ensureCSRFToken();
    if (empty($csrfToken) || $csrfToken !== $_SESSION['csrf_token'] || (time() - $_SESSION['csrf_timestamp']) > 1800) {
        $response = ['success' => false, 'message' => 'Session expired or invalid request', 'csrf_token' => $this->GetCSRFToken()];
        error_log("handleRequest: Invalid or expired CSRF token for action - $action, provided: $csrfToken, expected: {$_SESSION['csrf_token']}");
        echo json_encode($response);
        exit;
    }
    $this->error_message = '';

    // Regenerate CSRF token for next request
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_timestamp'] = time();
    error_log("handleRequest: New CSRF token generated for action - $action: " . $_SESSION['csrf_token']);

    switch ($action) {
        case 'validate_csrf_token':
            $this->validateCSRFToken($csrfToken);
            break;
        case 'login':
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $loginResult = $this->Login($email, $password);
            if ($loginResult && $loginResult['success']) {
                setcookie('auth_token', $loginResult['auth_token'], [
                    'expires' => time() + 86400,
                    'path' => '/',
                    'domain' => $this->websiteName,
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                $response = [
                    'success' => true,
                    'email' => $loginResult['email'],
                    'name' => $loginResult['name'],
                    'phone' => $loginResult['phone'],
                    'profile_picture' => $loginResult['profile_picture'],
                    'role' => $loginResult['role'],
                    'user_id' => $loginResult['user_id'],
                    'auth_token' => $loginResult['auth_token'],
                    'vehicles' => $loginResult['vehicles'],
                    'csrf_token' => $_SESSION['csrf_token']
                ];
                error_log("handleRequest: Login response for $email: " . json_encode($response));
                echo json_encode($response);
            } else {
                $response = ['success' => false, 'message' => $this->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Login failed for $email: " . json_encode($response));
                echo json_encode($response);
            }
            break;
        case 'register':
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? $_POST['password'];
            if ($password !== $confirm) {
                $response = ['success' => false, 'message' => 'Passwords do not match', 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Register failed - Passwords do not match for $email");
                echo json_encode($response);
            } elseif ($this->RegisterUser($name, $email, $phone, $password)) {
                $response = [
                    'success' => true,
                    'message' => 'Registration successful. Please check your email to verify your account.',
                    'csrf_token' => $_SESSION['csrf_token']
                ];
                error_log("handleRequest: Register successful for $email");
                echo json_encode($response);
            } else {
                $response = ['success' => false, 'message' => $this->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Register failed for $email: " . json_encode($response));
                echo json_encode($response);
            }
            break;
        case 'verify':
            if (isset($_POST['token'])) {
                if ($this->VerifyUser($_POST['token'])) {
                    $response = [
                        'success' => true,
                        'message' => 'Account verified successfully',
                        'csrf_token' => $_SESSION['csrf_token']
                    ];
                    error_log("handleRequest: Verification successful");
                    echo json_encode($response);
                } else {
                    $response = ['success' => false, 'message' => $this->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']];
                    error_log("handleRequest: Verification failed: " . json_encode($response));
                    echo json_encode($response);
                }
            } else {
                $response = ['success' => false, 'message' => 'No token provided', 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Verification failed - No token provided");
                echo json_encode($response);
            }
            break;
        case 'forgot_password':
            $email = $_POST['email'] ?? '';
            if ($this->RequestPasswordReset($email)) {
                $response = [
                    'success' => true,
                    'message' => 'Password reset link sent to your email.',
                    'csrf_token' => $_SESSION['csrf_token']
                ];
                error_log("handleRequest: Password reset requested for $email");
                echo json_encode($response);
            } else {
                $response = ['success' => false, 'message' => $this->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Password reset failed for $email: " . json_encode($response));
                echo json_encode($response);
            }
            break;
        case 'reset_password':
            $token = $_POST['token'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            if ($newPassword !== $confirmPassword) {
                $response = ['success' => false, 'message' => 'Passwords do not match', 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Password reset failed - Passwords do not match");
                echo json_encode($response);
            } else {
                $result = $this->ResetPassword($token, $newPassword);
                if ($result && $result['success']) {
                    $response = [
                        'success' => true,
                        'message' => 'Password reset successfully. You are now logged in.',
                        'email' => $result['email'],
                        'name' => $result['name'],
                        'csrf_token' => $_SESSION['csrf_token']
                    ];
                    error_log("handleRequest: Password reset successful for {$result['email']}");
                    echo json_encode($response);
                } else {
                    $response = ['success' => false, 'message' => $this->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']];
                    error_log("handleRequest: Password reset failed: " . json_encode($response));
                    echo json_encode($response);
                }
            }
            break;
        case 'update_profile':
            $currentEmail = $_POST['current_email'] ?? '';
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $profilePicture = $_FILES['profile_picture'] ?? null;
            $result = $this->UpdateProfile($currentEmail, $name, $email, $phone, $profilePicture);
            if ($result && $result['success']) {
                $response = [
                    'success' => true,
                    'message' => 'Profile updated successfully.',
                    'user' => $result['user'],
                    'csrf_token' => $_SESSION['csrf_token']
                ];
                error_log("handleRequest: Profile updated for $email");
                echo json_encode($response);
            } else {
                $response = ['success' => false, 'message' => $this->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Profile update failed for $email: " . json_encode($response));
                echo json_encode($response);
            }
            break;
        case 'logout':
            if ($this->Logout()) {
                setcookie('auth_token', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => $this->websiteName,
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
                $response = [
                    'success' => true,
                    'message' => 'Logged out successfully',
                    'csrf_token' => $_SESSION['csrf_token']
                ];
                error_log("handleRequest: Logout successful");
                echo json_encode($response);
            } else {
                $response = ['success' => false, 'message' => $this->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Logout failed: " . json_encode($response));
                echo json_encode($response);
            }
            break;
        case 'assign_vehicle_by_vin':
        case 'assign_vehicle_by_vin_allow_incomplete':
            // Call the appropriate assignment function
            if ($action === 'assign_vehicle_by_vin') {
                $this->AssignVehicleByVin();
            } else {
                $this->AssignVehicleByVinAllowIncompleteEndingMiles();
            }
            break;
        case 'add_mileage_record':
            $vehicle_id = $_POST['vehicle_id'] ?? '';
            $user_id = $_POST['user_id'] ?? '';
            $result = $this->AddMileageRecord($vehicle_id, $user_id);
            if ($result && $result['success']) {
                $response = [
                    'success' => true,
                    'message' => 'Mileage record added successfully.',
                    'mileage_id' => $result['mileage_id'],
                    'csrf_token' => $_SESSION['csrf_token']
                ];
                error_log("handleRequest: Mileage record added for vehicle_id: $vehicle_id, user_id: $user_id, mileage_id: {$result['mileage_id']}");
                echo json_encode($response);
            } else {
                $response = ['success' => false, 'message' => $this->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Mileage record addition failed for vehicle_id: $vehicle_id, user_id: $user_id: " . json_encode($response));
                echo json_encode($response);
            }
            break;
        case 'submit_start_mileage':
            $this->SubmitStartMileage();
            break;
        case 'submit_end_mileage':
            $this->SubmitEndMileage();
            break;
        case 'get_vehicle_issues':
            $vehicle_id = $_POST['vehicle_id'] ?? '';
            $auth_token = $_POST['auth_token'] ?? $_SERVER['HTTP_AUTH_TOKEN'] ?? '';

            // Validate token
            $userData = $this->ValidateToken($auth_token);
            if (!$userData) {
                $response = ['success' => false, 'message' => $this->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Invalid token for get_vehicle_issues, vehicle_id: $vehicle_id");
                echo json_encode($response);
                exit;
            }

            // Check if user has permission (e.g., driver, manager, or owner)
            $allowedRoles = ['driver', 'manager', 'owner'];
            if (!in_array(strtolower($userData['role']), $allowedRoles)) {
                $response = ['success' => false, 'message' => 'Unauthorized access', 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Unauthorized get_vehicle_issues for user: {$userData['email']}, role: {$userData['role']}");
                echo json_encode($response);
                exit;
            }

            // Fetch vehicle issues
            $result = $this->GetVehicleIssues($vehicle_id);
            if ($result && $result['success']) {
                $response = [
                    'success' => true,
                    'issues' => $result['issues'],
                    'csrf_token' => $_SESSION['csrf_token']
                ];
                error_log("handleRequest: Fetched " . count($result['issues']) . " issues for vehicle_id: $vehicle_id");
                echo json_encode($response);
            } else {
                $response = ['success' => false, 'message' => $this->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Failed to fetch issues for vehicle_id: $vehicle_id: " . $this->GetErrorMessage());
                echo json_encode($response);
            }
            break;
        case 'add_vehicle_issue':
            $vehicle_id = $_POST['vehicle_id'] ?? '';
            $issue_type = $_POST['issue_type'] ?? '';
            $description = $_POST['description'] ?? null;
            $auth_token = $_POST['auth_token'] ?? $_SERVER['HTTP_AUTH_TOKEN'] ?? '';

            // Validate token
            $userData = $this->ValidateToken($auth_token);
            if (!$userData) {
                $response = ['success' => false, 'message' => $this->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Invalid token for add_vehicle_issue, vehicle_id: $vehicle_id");
                echo json_encode($response);
                exit;
            }

            // Check if user has permission (e.g., driver, manager, or owner)
            $allowedRoles = ['driver', 'manager', 'owner'];
            if (!in_array(strtolower($userData['role']), $allowedRoles)) {
                $response = ['success' => false, 'message' => 'Unauthorized access', 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Unauthorized add_vehicle_issue for user: {$userData['email']}, role: {$userData['role']}");
                echo json_encode($response);
                exit;
            }

            // Add vehicle issue
            $result = $this->AddVehicleIssue($vehicle_id, $issue_type, $description);
            if ($result) {
                $response = [
                    'success' => true,
                    'message' => 'Issue added successfully',
                    'csrf_token' => $_SESSION['csrf_token']
                ];
                error_log("handleRequest: Added issue for vehicle_id: $vehicle_id, issue_type: $issue_type");
                echo json_encode($response);
            } else {
                $response = ['success' => false, 'message' => $this->GetErrorMessage(), 'csrf_token' => $_SESSION['csrf_token']];
                error_log("handleRequest: Failed to add issue for vehicle_id: $vehicle_id: " . $this->GetErrorMessage());
                echo json_encode($response);
            }
            break;
        default:
            $response = ['success' => false, 'message' => 'Invalid request', 'csrf_token' => $_SESSION['csrf_token']];
            error_log("handleRequest: Invalid action - $action");
            echo json_encode($response);
            break;
    }
    exit;
}

public function Login($email, $password) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("Login: Database connection lost for email: $email");
        return false;
    }

    $this->ensureUsersTableExists();
    $this->ensureProfilesTableExists();
    $this->ensureVehiclesTableExists();
    $this->ensureMileageTableExists();

    $email = $this->sanitize($email);

    $rateLimitKey = $this->GenerateRateLimitKey('login', $email);
    if (!$this->checkRateLimit($rateLimitKey, 'login', 5, 900)) {
        $this->HandleError("Too many login attempts. Please try again later.");
        error_log("Login: Rate limit exceeded for key: $rateLimitKey");
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $this->HandleError("Invalid email format.");
        error_log("Login: Invalid email format: $email");
        return false;
    }

    $query = "SELECT u.*, p.name, p.phone, p.profile_picture 
              FROM {$this->usersTable} u 
              LEFT JOIN {$this->profilesTable} p ON u.id = p.user_id 
              WHERE u.email = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare statement: " . mysqli_error($this->dbConn));
        error_log("Login: Failed to prepare statement for email: $email");
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Database query failed: " . mysqli_stmt_error($stmt));
        error_log("Login: Database query failed for email: $email: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) <= 0) {
        $this->HandleError("Email not found.");
        error_log("Login: Email not found: $email");
        mysqli_stmt_close($stmt);
        return false;
    }

    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!isset($row['is_verified']) || !$row['is_verified']) {
        $this->HandleError("Please verify your email before logging in.");
        error_log("Login: Email not verified: $email");
        return false;
    }

    if ($password !== null && !password_verify($password, $row['password'])) {
        $this->HandleError("Incorrect password.");
        error_log("Login: Incorrect password for email: $email");
        return false;
    }

    $authToken = bin2hex(random_bytes(32));
    $currentTime = gmdate('Y-m-d H:i:s', time() - 4 * 3600); // UTC-04:00

    // Check if first_login needs updating (NULL or not from today)
    $currentDate = gmdate('Y-m-d', time() - 4 * 3600); // Current date in UTC-04:00
    $query = "SELECT first_login, DATE(first_login) AS first_login_date FROM {$this->usersTable} WHERE email = ?";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare first login check: " . mysqli_error($this->dbConn));
        error_log("Login: Failed to prepare first login check for email: $email");
        return false;
    }
    mysqli_stmt_bind_param($stmt, "s", $email);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("First login check failed: " . mysqli_stmt_error($stmt));
        error_log("Login: First login check failed for email: $email: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    $result = mysqli_stmt_get_result($stmt);
    $row_first_login = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $update_query = "UPDATE {$this->usersTable} SET auth_token = ?, last_login = ?";
    $param_types = "ss";
    $params = [$authToken, $currentTime];

    if (is_null($row_first_login['first_login']) || $row_first_login['first_login_date'] !== $currentDate) {
        $update_query .= ", first_login = ?";
        $param_types .= "s";
        $params[] = $currentTime;
        error_log("Login: Updating first_login for email: $email, currentDate: $currentDate, previous first_login: " . ($row_first_login['first_login'] ?? 'NULL'));
    } else {
        error_log("Login: No first_login update needed for email: $email, currentDate: $currentDate, first_login_date: " . ($row_first_login['first_login_date'] ?? 'NULL'));
    }
    $update_query .= " WHERE email = ?";
    $param_types .= "s";
    $params[] = $email;

    $stmt = mysqli_prepare($this->dbConn, $update_query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare auth token statement: " . mysqli_error($this->dbConn));
        error_log("Login: Failed to prepare auth token update for email: $email");
        return false;
    }

    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Failed to set auth token or login times: " . mysqli_stmt_error($stmt));
        error_log("Login: Failed to update auth token for email: $email: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    mysqli_stmt_close($stmt);

    // Set auth_token cookie
    $cookie_set = setcookie('auth_token', $authToken, [
        'expires' => time() + 86400, // 24 hours
        'path' => '/',
        'domain' => $this->websiteName,
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    if (!$cookie_set) {
        $this->HandleError("Failed to set auth_token cookie.");
        error_log("Login: Failed to set auth_token cookie for email: $email, token: $authToken");
        return false;
    }
    error_log("Login: Set auth_token cookie for email: $email, token: $authToken");

    // Store auth_token in session as fallback
    $_SESSION['auth_token'] = $authToken;
    session_regenerate_id(true);

    // Fetch vehicles based on user role
    $userId = $row['id'];
    $userRole = strtolower($row['role']); // Case-insensitive role check
    $vehicles = [];
    $allowedRoles = ['driver', 'manager', 'owner'];

    if (in_array($userRole, $allowedRoles)) {
        // Fetch ALL vehicles for driver, manager, or owner
        $query = "SELECT vehicle_id, image_location, make, model, vin, color, license_plate, year, current_user_id, last_user_id, date_assigned, date_last_used, mileage 
                  FROM {$this->vehiclesTable}";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare vehicle query: " . mysqli_error($this->dbConn));
            error_log("Login: Failed to prepare vehicle query for user_id: $userId");
            return false;
        }

        if (!mysqli_stmt_execute($stmt)) {
            $this->HandleError("Vehicle query failed: " . mysqli_stmt_error($stmt));
            error_log("Login: Vehicle query failed for user_id: $userId: " . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            return false;
        }

        $result = mysqli_stmt_get_result($stmt);
        while ($vehicleRow = mysqli_fetch_assoc($result)) {
            // Fetch the most recent mileage record for each vehicle
            $mileageResult = $this->GetVehicleMileageRecords($vehicleRow['vehicle_id'], $userId);
            $mileage_record = ($mileageResult && $mileageResult['success']) ? $mileageResult['mileage_record'] : null;
            
            $vehicles[] = [
                'vehicle_id' => (int)$vehicleRow['vehicle_id'],
                'image_location' => $vehicleRow['image_location'],
                'make' => $vehicleRow['make'],
                'model' => $vehicleRow['model'],
                'vin' => $vehicleRow['vin'],
                'color' => $vehicleRow['color'],
                'license_plate' => $vehicleRow['license_plate'],
                'year' => (int)$vehicleRow['year'],
                'current_user_id' => $vehicleRow['current_user_id'] ? (int)$vehicleRow['current_user_id'] : null,
                'last_user_id' => $vehicleRow['last_user_id'] ? (int)$vehicleRow['last_user_id'] : null,
                'date_assigned' => $vehicleRow['date_assigned'],
                'date_last_used' => $vehicleRow['date_last_used'],
                'mileage' => $vehicleRow['mileage'] ? (float)$vehicleRow['mileage'] : null,
                'mileage_record' => $mileage_record
            ];
        }
        mysqli_stmt_close($stmt);
        error_log("Login: Fetched " . count($vehicles) . " vehicles for user_id: $userId with role: $userRole");
    } else {
        error_log("Login: No vehicles fetched for user_id: $userId with role: $userRole (not driver, manager, or owner)");
    }

    $response = [
        'success' => true,
        'email' => $email,
        'name' => $row['name'] ?? 'Unknown',
        'phone' => $row['phone'] ?? '',
        'profile_picture' => $row['profile_picture'] ?? '',
        'role' => $row['role'],
        'user_id' => (int)$userId,
        'auth_token' => $authToken,
        'vehicles' => $vehicles
    ];
    error_log("Login: Successful for email: $email, user_id: $userId, role: {$row['role']}, vehicles: " . count($vehicles));
    return $response;
}

private function ensureCSRFToken() {
    $maxTokenAge = 1800; // 30 minutes in seconds
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_timestamp']) || (time() - $_SESSION['csrf_timestamp']) > $maxTokenAge) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_timestamp'] = time();
        error_log("CSRF token generated: " . $_SESSION['csrf_token'] . " at " . gmdate('Y-m-d H:i:s', time() - 4 * 3600));
    }
}

public function GetCSRFToken() {
        $this->ensureCSRFToken();
        return $_SESSION['csrf_token'];
}

public function GetFreshCSRFToken() {
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        error_log("GetFreshCSRFToken: Invalid request method: " . $_SERVER['REQUEST_METHOD'] . " at " . gmdate('Y-m-d H:i:s', time() - 4 * 3600));
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_timestamp'] = time();
    error_log("GetFreshCSRFToken: Fresh CSRF token generated: " . $_SESSION['csrf_token'] . " at " . gmdate('Y-m-d H:i:s', time() - 4 * 3600));
    echo json_encode(['success' => true, 'csrf_token' => $_SESSION['csrf_token']]);
    exit;
}

public function validateCSRFToken($token) {
        header('Content-Type: application/json');
        $this->ensureCSRFToken();
        if ($token === $_SESSION['csrf_token'] && (time() - $_SESSION['csrf_timestamp']) <= 1800) {
            error_log("validateCSRFToken: Token validated successfully: $token");
            echo json_encode(['success' => true]);
        } else {
            error_log("validateCSRFToken: Invalid or expired token: $token");
            echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token']);
        }
        exit;
}

public function ValidateToken($token) {
    if (!$this->dbConn || !mysqli_ping($this->dbConn)) {
        $this->HandleError("Database connection lost.");
        error_log("ValidateToken: Database connection lost");
        return false;
    }

    if (empty($token)) {
        $this->HandleError("No token provided.");
        error_log("ValidateToken: No token provided");
        return false;
    }

    $token = $this->sanitize($token);
    $query = "SELECT u.*, p.name, p.phone, p.profile_picture 
              FROM {$this->usersTable} u 
              LEFT JOIN {$this->profilesTable} p ON u.id = p.user_id 
              WHERE u.auth_token = ? AND u.is_verified = 1";
    $stmt = mysqli_prepare($this->dbConn, $query);
    if ($stmt === false) {
        $this->HandleError("Failed to prepare statement: " . mysqli_error($this->dbConn));
        error_log("ValidateToken: Failed to prepare statement: " . mysqli_error($this->dbConn));
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $token);
    if (!mysqli_stmt_execute($stmt)) {
        $this->HandleError("Database query failed: " . mysqli_stmt_error($stmt));
        error_log("ValidateToken: Query failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($result) <= 0) {
        $this->HandleError("Invalid token or unverified user.");
        error_log("ValidateToken: No user found for token: $token");
        mysqli_stmt_close($stmt);
        return false;
    }

    $row = mysqli_fetch_assoc($result);
    $userId = $row['id'];
    error_log("ValidateToken: User found - email: {$row['email']}, user_id: $userId");
    mysqli_stmt_close($stmt);

    // Fetch vehicles assigned to the current user
    $vehicles = [];
    if (!$this->ensureVehiclesTableExists()) {
        $this->HandleError("Failed to ensure vehicles table exists.");
        error_log("ValidateToken: Failed to ensure vehicles table for user_id: $userId");
        // Continue with empty vehicles array to avoid breaking login
        error_log("ValidateToken: Proceeding with empty vehicles array due to table error");
    } else {
        $query = "SELECT vehicle_id, image_location, make, model, vin, color, license_plate, year, current_user_id, last_user_id, date_assigned, date_last_used, mileage 
                  FROM {$this->vehiclesTable} 
                  WHERE current_user_id = ?";
        $stmt = mysqli_prepare($this->dbConn, $query);
        if ($stmt === false) {
            $this->HandleError("Failed to prepare vehicle query: " . mysqli_error($this->dbConn));
            error_log("ValidateToken: Failed to prepare vehicle query for user_id: $userId: " . mysqli_error($this->dbConn));
            // Continue with empty vehicles array
            error_log("ValidateToken: Proceeding with empty vehicles array due to query preparation error");
        } else {
            mysqli_stmt_bind_param($stmt, "i", $userId);
            if (!mysqli_stmt_execute($stmt)) {
                $this->HandleError("Vehicle query failed: " . mysqli_stmt_error($stmt));
                error_log("ValidateToken: Vehicle query failed for user_id: $userId: " . mysqli_stmt_error($stmt));
                mysqli_stmt_close($stmt);
                // Continue with empty vehicles array
                error_log("ValidateToken: Proceeding with empty vehicles array due to query execution error");
            } else {
                $result = mysqli_stmt_get_result($stmt);
                while ($vehicleRow = mysqli_fetch_assoc($result)) {
                    $vehicles[] = [
                        'vehicle_id' => (int)$vehicleRow['vehicle_id'],
                        'image_location' => $vehicleRow['image_location'],
                        'make' => $vehicleRow['make'],
                        'model' => $vehicleRow['model'],
                        'vin' => $vehicleRow['vin'],
                        'color' => $vehicleRow['color'],
                        'license_plate' => $vehicleRow['license_plate'],
                        'year' => (int)$vehicleRow['year'],
                        'current_user_id' => $vehicleRow['current_user_id'] ? (int)$vehicleRow['current_user_id'] : null,
                        'last_user_id' => $vehicleRow['last_user_id'] ? (int)$vehicleRow['last_user_id'] : null,
                        'date_assigned' => $vehicleRow['date_assigned'],
                        'date_last_used' => $vehicleRow['date_last_used'],
                        'mileage' => $vehicleRow['mileage'] ? (float)$vehicleRow['mileage'] : null
                    ];
                }
                mysqli_stmt_close($stmt);
                error_log("ValidateToken: Fetched " . count($vehicles) . " vehicles for user_id: $userId");
            }
        }
    }

    $response = [
        'email' => $row['email'],
        'name' => $row['name'] ?? 'Unknown',
        'phone' => $row['phone'] ?? '',
        'profile_picture' => $row['profile_picture'] ?? '',
        'role' => $row['role'],
        'vehicles' => $vehicles
    ];
    error_log("ValidateToken: Returning response for email: {$row['email']}: " . json_encode($response));
    return $response;
}

private function HandleError($err) {
        $this->error_message .= $err . "\n";
}

public function GetErrorMessage() {
        return htmlentities($this->error_message);
}

}
?>