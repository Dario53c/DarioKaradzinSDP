<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$pdo_conn = require_once __DIR__ . '/database.php'; 

// Include your domain classes
require_once __DIR__ . '/classes/UserClass.php';
require_once __DIR__ . '/classes/ItemClass.php';

use Google\Cloud\Storage\StorageClient; 

// --- Register Services with Flight ---
Flight::set('user', new User($pdo_conn));
Flight::set('item', new Item($pdo_conn));


// --- Basic Root Route ---
Flight::route('/', function () {
    Flight::json(['message' => 'Welcome to Fits n Finds API!']);
});


// --- User Authentication Routes ---

// Login Route: POST /login
// Expects JSON: { "email": "...", "password": "..." }
Flight::route('POST /login', function() {
    $request = Flight::request();
    $data = $request->data->getData();

    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    if (empty($email) || empty($password)) {
        Flight::json(['status' => 'error', 'message' => 'Email and password are required.'], 400);
        return;
    }

    $user = Flight::get('user');
    $result = $user->login($email, $password);

    if ($result['status'] === 'success') {
        $_SESSION['user_id'] = $result['user_data']['id'];
        $_SESSION['username'] = $result['user_data']['username'];
        Flight::json($result, 200);
    } else {
        Flight::json($result, 401); // Unauthorized
    }
});

Flight::route('POST /register', function() {
    $request = Flight::request();
    $data = $request->data->getData();

    $username = $data['username'] ?? null;
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;

    $user = Flight::get('user'); // Get the User service
    $result = $user->register($username, $email, $password);

    if ($result['status'] === 'success') {
        Flight::json($result, 201); // Created
    } else {
        if ($result['message'] === 'Username or email already taken!') {
             Flight::json($result, 409); // Conflict
        } else {
             Flight::json($result, 400); // Bad Request
        }
    }
});

// Logout Route: GET /logout
Flight::route('GET /logout', function() {
    session_unset();
    session_destroy(); // Destroy all session data
});


// User Status Route: GET /user/status
Flight::route('GET /user/status', function() {
    if (isset($_SESSION['username'])) {
        Flight::json([
            'loggedIn' => true,
            'username' => $_SESSION['username'],
            'user_id' => $_SESSION['user_id']
        ], 200);
    } else {

        Flight::json([
            'loggedIn' => false,
            'username' => null,
            'user_id' => null
        ], 200);
    }
});

Flight::route('POST /post-item', function() {
    $request = Flight::request();
    $data = $request->data->getData();

    error_log("Received JSON data for post-item: " . print_r($data, true));

    if (!isset($_SESSION['user_id'])) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required. Please log in to post an item.'], 401);
        return;
    }

    $seller_id = $_SESSION['user_id'];
    $item = Flight::get('item');

    // Perform basic validation before passing to ItemClass
    $requiredFields = ['category', 'name', 'description', 'brand', 'condition', 'price', 'image_path'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            Flight::json(['status' => 'error', 'message' => "Missing or empty required field: " . $field], 400);
            return;
        }
    }

    // Ensure price is a valid number
    if (!is_numeric($data['price']) || $data['price'] < 0) {
        Flight::json(['status' => 'error', 'message' => 'Invalid price provided.'], 400);
        return;
    }

    $result = $item->postItem($data, $seller_id); // Pass the decoded JSON data

    // Check the result from the ItemClass method
    if ($result['status'] === 'success') {
        Flight::json($result, 201); // 201 Created status for successful item creation
    } else {
        // Return error message from the ItemClass (e.g., database error or validation error from postItem)
        $statusCode = (isset($result['message']) && strpos($result['message'], 'Invalid category ID') !== false) ? 400 : 500;
        Flight::json($result, $statusCode);
    }
});

Flight::route('GET /items', function() {
    $item = Flight::get('item');
    $items = $item->getAllItems();

    if ($items) {
        Flight::json(['status' => 'success', 'items' => $items], 200);
    } else {
        Flight::json(['status' => 'error', 'message' => 'No items found.'], 404);
    }
});

Flight::route('GET /items/@id', function($id) {
    $item = Flight::get('item');
    $singleItem = $item->getItemById($id);

    if ($singleItem) {
        Flight::json(['status' => 'success', 'item' => $singleItem], 200);
    } else {
        Flight::json(['status' => 'error', 'message' => 'Item not found.'], 404);
    }
});


Flight::route('POST /items/sell', function() {

    if (!isset($_SESSION['user_id'])) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required. Please log in to mark items as sold.'], 401);
        return;
    }

    $request = Flight::request();
    $data = $request->data->getData();
    if (!isset($data['item_ids']) || !is_array($data['item_ids']) || empty($data['item_ids'])) {
        Flight::json(['status' => 'error', 'message' => 'Invalid or empty array of item IDs provided.'], 400);
        return;
    }

    $itemIds = array_map('intval', $data['item_ids']);

    $itemService = Flight::get('item');

    // 6. Call the markItemsAsSold method
    $result = $itemService->markItemsAsSold($itemIds);

    // 7. Return JSON Response based on the result
    if ($result) { // markItemsAsSold returns true on success (if at least one row affected)
        Flight::json(['status' => 'success', 'message' => 'Thank you for your purchase'], 200);
    } else {
        // This could mean a database error, or no items were found/affected (e.g., already sold)
        Flight::json(['status' => 'error', 'message' => 'Failed to mark selected items as sold. They might not exist or already be sold.'], 500);
    }
});




//KEEP AT THE BOTTOM OF ALL ROUTES
// Catch-all route for undefined endpoints
Flight::route('/*', function() {
    Flight::json(['status' => 'error', 'message' => 'Endpoint not found.'], 404);
});


// Start the Flight application
Flight::start();
