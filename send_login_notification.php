<?php
// public_html/send_login_notification.php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/kem4tgiu3b1p/public_html/log.txt');
error_reporting(E_ALL);

try {
    // Log start
    error_log("send_login_notification: Script started at " . date('Y-m-d H:i:s'), 3, "/home/kem4tgiu3b1p/public_html/log.txt");

    // Load dependencies
    require_once dirname(__FILE__) . '/includes/hiatme_methods.php';
    require_once dirname(__FILE__) . '/vendor/autoload.php';

    // Load environment variables
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__FILE__));
    $dotenv->load();
    error_log("send_login_notification: Environment variables loaded", 3, "/home/kem4tgiu3b1p/public_html/log.txt");

    // Initialize HiatmeMethods
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
    error_log("send_login_notification: HiatmeMethods initialized", 3, "/home/kem4tgiu3b1p/public_html/log.txt");

    // Set timezone to EDT (UTC-04:00)
    date_default_timezone_set('America/New_York');
    $current_time = date('H:i:s');
    $current_date = date('Y-m-d');
    $morning_cutoff = '07:00:00'; // 7 AM
    error_log("send_login_notification: Current time: $current_time, Date: $current_date", 3, "/home/kem4tgiu3b1p/public_html/log.txt");

    // Exit if before 7 AM
    if ($current_time < $morning_cutoff) {
        error_log("send_login_notification: Not past 7 AM EDT yet ($current_time). Exiting.", 3, "/home/kem4tgiu3b1p/public_html/log.txt");
        exit;
    }

    // Query users who haven't logged in today using SearchUsers
    $query = "first_login:NOT:$current_date"; // Custom query to filter users not logged in today
    $users_result = $hiatme_methods->SearchUsers($query, 0, 1000); // Large limit to get all users
    if ($users_result === false) {
        throw new Exception("Failed to fetch users: " . $hiatme_methods->GetErrorMessage());
    }
    $users_not_logged_in = $users_result['users'];
    error_log("send_login_notification: Found " . count($users_not_logged_in) . " users who haven't logged in today", 3, "/home/kem4tgiu3b1p/public_html/log.txt");

    if (empty($users_not_logged_in)) {
        error_log("send_login_notification: No users found who haven't logged in today ($current_date). Exiting.", 3, "/home/kem4tgiu3b1p/public_html/log.txt");
        exit;
    }

    // Send OneSignal notification for each user
    $app_id = "1bb48e3c-7bce-4e46-9b4b-37983c1abbf2";
    $api_key = "os_v2_app_do2i4pd3zzheng2lg6mdygv36l7x3dn33uuuyk4z3httiahxcwtn4ctdyegsywrvea35bgv4fcfpp5iravnr2zc2kzwhmfjhsf6n66a";
    $url = "https://hiatme.com";

    foreach ($users_not_logged_in as $user) {
        $name = $user['name'] ?? 'User ' . $user['id'];
        $message = "User $name hasn't logged in today!";
        error_log("send_login_notification: Preparing notification for $name", 3, "/home/kem4tgiu3b1p/public_html/log.txt");

        $content = ["en" => $message];
        $fields = [
            "app_id" => $app_id,
            "included_segments" => ["Active Subscriptions"],
            "contents" => $content,
            "url" => $url
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json; charset=utf-8",
            "Authorization: Basic $api_key"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $curl_error = curl_error($ch);
            $curl_errno = curl_errno($ch);
            curl_close($ch);
            throw new Exception("Curl error for user $name: $curl_error (Error code: $curl_errno)");
        }

        curl_close($ch);

        if ($http_code >= 400) {
            throw new Exception("OneSignal API error for user $name: HTTP $http_code - $response");
        }

        error_log("send_login_notification: OneSignal response for user $name: $response", 3, "/home/kem4tgiu3b1p/public_html/log.txt");
    }

    error_log("send_login_notification: Completed successfully for " . count($users_not_logged_in) . " users.", 3, "/home/kem4tgiu3b1p/public_html/log.txt");
} catch (Exception $e) {
    error_log("send_login_notification: Fatal error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine(), 3, "/home/kem4tgiu3b1p/public_html/log.txt");
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal Server Error']);
    exit;
}
?>