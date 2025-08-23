<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$pdo_conn = require_once __DIR__ . '/database.php';
require_once __DIR__ . "/classes/UserClass.php";
require_once __DIR__ . "/classes/ItemClass.php";

$user = new User($pdo_conn);
$item = new Item($pdo_conn);

// Set the Content-Type header to application/json
header('Content-Type: application/json');

// Get the user ID from the session, default to null if not set
$userId = $_SESSION['user_id'] ?? null;

// If there's no user ID, return an error
if (!$userId) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in.']);
    exit;
}

$imageURL = $item->getUserProfileImageURL($userId);

// Check if a valid URL was returned
if ($imageURL) {
    // Return a success JSON response with the image URL
    echo json_encode(['status' => 'success', 'imageUrl' => $imageURL]);
} else {
    // Return an error JSON response if no image URL was found or an error occurred
    echo json_encode(['status' => 'error', 'message' => 'No profile image found.']);
}
?>