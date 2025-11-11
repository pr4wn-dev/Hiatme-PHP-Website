<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php'; // Load composer autoloader
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // Load .env from /home/kem4tgiu3b1p/public_html/
$dotenv->load();
require_once 'includes/hiatme_methods.php';
$hiatme_methods = new HiatmeMethods();

$filename = $_GET['file'] ?? '';
if (!preg_match('/^[a-f0-9]{64}\.(jpg|png|gif)$/', $filename)) {
    http_response_code(403);
    exit('Invalid file');
}

$filePath = __DIR__ . '/private/uploads/' . $filename; // /home/kem4tgiu3b1p/public_html/private/uploads/
if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found');
}

header('Content-Type: ' . mime_content_type($filePath));
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
?>