<?php
$app_id = "1bb48e3c-7bce-4e46-9b4b-37983c1abbf2"; // Replace with your OneSignal App ID
$api_key = "os_v2_app_do2i4pd3zzheng2lg6mdygv36l7x3dn33uuuyk4z3httiahxcwtn4ctdyegsywrvea35bgv4fcfpp5iravnr2zc2kzwhmfjhsf6n66a"; // Replace with your OneSignal REST API Key
$message = "New content available!";
$url = "https://yourwebsite.com/post"; // URL to open when clicked

$content = [
  "en" => $message
];
$fields = [
  "app_id" => $app_id,
  "included_segments" => ["Active Subscriptions"],
  "contents" => $content,
  "url" => $url
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  "Content-Type: application/json",
  "Authorization: Basic $api_key"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
$response = curl_exec($ch);
curl_close($ch);

echo $response; // For debugging
?>