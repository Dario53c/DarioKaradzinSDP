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
if(file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); // Assuming .env is in the project root
    $dotenv->load();
}

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

Flight::route('GET /user/profile-image', function() {
    if (!isset($_SESSION['user_id'])) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required. Please log in to view your profile.'], 401);
        return;
    }

    $userId = $_SESSION['user_id'];
    $item = Flight::get('item'); // This is the fix: get the 'item' service
    
    // Call the method on the correct service instance
    $imageURL = $item->getUserProfileImageURL($userId);

    if ($imageURL) {
        Flight::json(['status' => 'success', 'imageUrl' => $imageURL], 200);
    } else {
        Flight::json(['status' => 'error', 'message' => 'User profile image not found.'], 404);
    }
});
Flight::route('GET /user/verified', function() {
    if (!isset($_SESSION['user_id'])) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required. Please log in to view your profile.'], 401);
        return;
    }

    $userId = $_SESSION['user_id'];
    $user = Flight::get('user');
    $verifyData = $user->getUserVerified($userId); // This calls the UserClass method

    // **THIS IS THE CRITICAL BLOCK**
    if ($verifyData !== null) { // Using the refined logic as discussed
        Flight::json(['status' => 'success', 'verified' => $verifyData], 200);
    } else {
        // This 'else' block is sending the 404.
        Flight::json(['status' => 'error', 'message' => 'User profile not found or verification status could not be retrieved.'], 404);
    }
});

Flight::route('GET /user/details', function() {
    if (!isset($_SESSION['user_id'])) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required. Please log in to view your profile.'], 401);
        return;
    }

    $userId = $_SESSION['user_id'];
    $user = Flight::get('user');
    $userData = $user->getUserById($userId);

    if ($userData) {
        Flight::json(['status' => 'success', 'user' => $userData], 200);
    } else {
        Flight::json(['status' => 'error', 'message' => 'User details not found.'], 404);
    }
});

Flight::route('POST /user/update-profile-image', function() {
    if (!isset($_SESSION['user_id'])) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required. Please log in to update your profile image.'], 401);
        return;
    }

    $userId = $_SESSION['user_id'];
    $user = Flight::get('user');

    $imageURL = Flight::request()->data->imageUrl;

    // Validate that a URL was provided
    if (!$imageURL) {
        Flight::json(['status' => 'error', 'message' => 'Image URL is missing.'], 400);
        return;
    }

    // Call the method to update the profile image
    $result = $user->updateProfileImage($userId, $imageURL);

    // Correctly handle the boolean result from the update function
    if ($result) {
        Flight::json(['status' => 'success', 'message' => 'Profile image updated successfully.'], 200);
    } else {
        Flight::json(['status' => 'error', 'message' => 'Failed to update profile image in the database.'], 500);
    }
});

Flight::route('POST /user/edit-profile', function() {
    if (!isset($_SESSION['user_id'])) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required. Please log in to update your profile.'], 401);
        return;
    }

    $userId = $_SESSION['user_id'];
    $user = Flight::get('user');

    $aboutMe = Flight::request()->data->aboutMe;
    $username = Flight::request()->data->username;
    $email = Flight::request()->data->email;

    // Validate that aboutMe is provided
    if (!$aboutMe || !$username || !$email) {
        Flight::json(['status' => 'error', 'message' => 'Empty field value detected.'], 400);
        return;
    }

    // Call the method to update the about me section
    $result = $user->updateProfile($userId, $aboutMe, $email, $username);

    if ($result) {
        Flight::json(['status' => 'success', 'message' => 'Profile updated successfully.'], 200);
    } else {
        Flight::json(['status' => 'error', 'message' => 'Failed to update profile in the database.'], 500);
    }
});

Flight::route('POST /user/change-password', function() {
    if (!isset($_SESSION['user_id'])) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required. Please log in to change your password.'], 401);
        return;
    }

    $userId = $_SESSION['user_id'];
    $user = Flight::get('user');

    $currentPassword = Flight::request()->data->currentPassword;
    $newPassword = Flight::request()->data->newPassword;

    // Validate that current and new passwords are provided
    if (!$currentPassword || !$newPassword) {
        Flight::json(['status' => 'error', 'message' => 'Current and new passwords are required.'], 400);
        return;
    }

    // Call the method to change the password
    $result = $user->changePassword($userId, $currentPassword, $newPassword);

    if ($result) {
        Flight::json(['status' => 'success', 'message' => 'Password changed successfully.'], 200);
    } else {
        Flight::json(['status' => 'error', 'message' => 'Current password is incorrect.'], 500);
    }
});

Flight::route('GET /user/send-verification-email', function() {
    if (!isset($_SESSION['user_id'])) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required. Please log in to send verification email.'], 401);
        return;
    }

    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $user = Flight::get('user');
    $userEmail = $user->getUserEmailById($userId);

    if ($userEmail === null) {
        Flight::json(['status' => 'error', 'message' => 'User not found.'], 404);
        return;
    }
    // Generate a random secret for testing the email function
    $testSecret = $user->getSecretById($userId);

    // Call the method to send the verification email
    $result = $user->sendMailjetVerificationEmail($userEmail, $testSecret, $username);

    if ($result) {
        Flight::json(['status' => 'success', 'message' => 'Verification email sent successfully.'], 200);
    } else {
        Flight::json(['status' => 'error', 'message' => 'Failed to send verification email.'], 500);
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

Flight::route('POST /items/sell', function() {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required. Please log in.'], 401);
        return;
    }

    $request = Flight::request();
    $data = $request->data->getData();

    if (!isset($data['item_ids']) || !is_array($data['item_ids']) || empty($data['item_ids'])) {
        Flight::json(['status' => 'error', 'message' => 'Invalid or empty array of item IDs provided.'], 400);
        return;
    }
    if (!isset($data['total_price']) || !is_numeric($data['total_price']) || floatval($data['total_price']) < 0) {
        Flight::json(['status' => 'error', 'message' => 'Invalid or missing total price.'], 400);
        return;
    }
    
    // Updated validation for shipping_details using array syntax
    if (!isset($data['shipping_details']) || !is_array($data['shipping_details']) ||
        !isset($data['shipping_details']['name']) || !isset($data['shipping_details']['address']) ||
        !is_array($data['shipping_details']['address']) || !isset($data['shipping_details']['address']['street']) ||
        !isset($data['shipping_details']['address']['city']) || !isset($data['shipping_details']['address']['zip'])) {
        Flight::json(['status' => 'error', 'message' => 'Invalid or missing shipping details.'], 400);
        return;
    }
    
    $itemIds = array_map('intval', $data['item_ids']);
    $totalPrice = floatval($data['total_price']);
    $shippingDetails = $data['shipping_details'];
    
    $itemService = Flight::get('item');
    $result = $itemService->createOrder($userId, $itemIds, $totalPrice, $shippingDetails);

    if ($result['status'] === 'success') {
        Flight::json($result, 200);
    } else {
        Flight::json($result, 500);
    }
});

Flight::route('POST /items/putItemInCart', function() {

    // Check if user is authenticated
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required. Please log in to add items to cart.'], 401);
        return;
    }

    // Check if item_id is provided in the request
    $request = Flight::request();
    $data = $request->data->getData();
    $itemId = $data['item_id'] ?? null;
    if (!$itemId) {
        Flight::json(['status' => 'error', 'message' => 'Item ID is required.'], 400);
        return;
    }

    // Call the putItemInCart method
    $itemService = Flight::get('item');
    $result = $itemService->putItemInCart($userId, $itemId);

    // Check the 'status' key from the returned array for success or failure
    if ($result['status'] === 'success') {
        Flight::json($result, 200);
    } else {
        // If the status is 'error', use the specific message from the returned array
        // We'll also use a specific HTTP status code for the 'already in cart' scenario
        $statusCode = 500; // Default to a server error
        if ($result['message'] === 'Item is already in your cart.') {
            $statusCode = 409; // 409 Conflict is the correct HTTP status for this scenario
        }
        
        Flight::json($result, $statusCode);
    }
});

Flight::route('GET /items/cart', function() {
    // Check if user is authenticated
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required. Please log in to view your cart.'], 401);
        return;
    }

    // Get the cart items for the authenticated user
    $itemService = Flight::get('item');
    $cartItems = $itemService->getCartItems($userId);

    // Return an empty array on success if no items are found
    if (empty($cartItems)) {
        Flight::json(['status' => 'success', 'message' => 'No items found in your cart.', 'cart_items' => []], 200);
    } else {
        Flight::json(['status' => 'success', 'cart_items' => $cartItems], 200);
    }
});



Flight::route('DELETE /items/removeItemFromCart/@itemId', function($itemId) {
    // 1. Check for authenticated user
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        Flight::json(['status' => 'error', 'message' => 'Authentication required.'], 401);
        return;
    }

    // 2. Validate the provided itemId
    if (!is_numeric($itemId) || intval($itemId) <= 0) {
        Flight::json(['status' => 'error', 'message' => 'Invalid item ID provided.'], 400);
        return;
    }

    // 3. Call the method to remove the item
    $itemService = Flight::get('item');
    $result = $itemService->removeItemFromCart($userId, $itemId);

    // 4. Return JSON response based on the result
    if ($result['status'] === 'success') {
        Flight::json($result, 200);
    } else {
        // Use a 404 if the item wasn't in the cart to begin with
        $statusCode = ($result['message'] === 'Item not found in cart.') ? 404 : 500;
        Flight::json($result, $statusCode);
    }
});


Flight::route('GET /verify-email', function() {
    // Get email and secret from URL query parameters
    $email = Flight::request()->query->email;
    $secret = Flight::request()->query->secret;

    $user = Flight::get('user'); // Get the User service instance

    // Prepare default status for the HTML output
    $title = 'Verification Failed';
    $message = 'An error occurred during verification. Please check your link or try again.';
    $is_success = false;
    $color = 'red';

    if (!$email || !$secret) {
        $message = 'Verification link is incomplete. Email or secret is missing.';
        error_log("Verification failed: Missing email or secret in URL. Email: " . ($email ?? 'N/A') . ", Secret: " . ($secret ?? 'N/A'));
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Invalid email format in the verification link.';
        error_log("Verification failed for email: " . $email . " - Invalid email format.");
    } else {
        // Retrieve the stored secret from the database for the given email
        $storedSecret = $user->getVerificationSecretByEmail($email);

        if ($storedSecret === null) {
            $message = 'User not found, already verified, or verification link is invalid/expired.';
            error_log("Verification failed for email: " . $email . " - Secret not found in DB or user already verified.");
        } else if ($secret !== $storedSecret) {
            $message = 'Verification secret does not match. This link may be invalid or tampered with.';
            error_log("Verification failed for email: " . $email . " - Secret mismatch. Provided: " . $secret . " | Stored: " . $storedSecret);
        } else {
            // Secrets match, proceed to verify the user
            $verifyResult = $user->verifyUserByEmail($email);

            if ($verifyResult['status'] === 'success') {
                $title = 'Congratulations!';
                $message = 'Your email has been successfully verified. You can now log in.';
                $is_success = true;
                $color = 'green';
                error_log("Email verified successfully for: " . $email);
            } else {
                // Verification failed for another reason (e.g., database error)
                $message = 'Verification failed: ' . $verifyResult['message'];
                error_log("Verification failed for email: " . $email . " - " . $verifyResult['message']);
            }
        }
    }

    // --- Render HTML directly ---
    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>" . htmlspecialchars($title) . " - Fits & Finds</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background-color: #f4f4f4;
                text-align: center;
            }
            .container {
                background-color: #ffffff;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                max-width: 500px;
                width: 90%;
            }
            h1 {
                color: " . ($is_success ? 'green' : 'red') . ";
                margin-bottom: 20px;
            }
            p {
                color: #555;
                line-height: 1.6;
            }
            .home-link {
                display: inline-block;
                margin-top: 25px;
                padding: 10px 20px;
                background-color: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                transition: background-color 0.3s ease;
            }
            .home-link:hover {
                background-color: #0056b3;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>" . htmlspecialchars($title) . "</h1>
            <p>" . htmlspecialchars($message) . "</p>";
    
    if ($is_success) {
        echo "<p>You can now return to the application and log in.</p>";
    }
    
    echo "
        <a href='http://".$_SERVER['HTTP_HOST'].dirname(dirname($_SERVER['SCRIPT_NAME']))."' class='home-link'>Go to Homepage</a>
        </div>
    </body>
    </html>";
    // --- End HTML render ---
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

\Stripe\Stripe::setApiKey($_ENV['CLIENT_SECRET']);
Flight::route('POST /create-payment-intent', function() {
    // Retrieve the JSON body from the request
    $jsonStr = Flight::request()->body;
    $data = json_decode($jsonStr);

    // Basic validation
    if (!isset($data->amount)) {
        Flight::json(['status' => 'error', 'message' => 'Amount is missing from the request.'], 400);
        return;
    }

    $amountInCents = intval($data->amount);

    if ($amountInCents <= 0) {
        Flight::json(['status' => 'error', 'message' => 'Amount must be a positive integer.'], 400);
        return;
    }

    try {
        // Create the PaymentIntent with the Stripe API
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $amountInCents,
            'currency' => 'usd', // Adjust to your currency
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            // You can add more options here, like `description`, `metadata`, etc.
        ]);

        // Return the client secret to the frontend
        Flight::json([
            'status' => 'success',
            'clientSecret' => $paymentIntent->client_secret,
        ]);

    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe API Error: ' . $e->getMessage());
        Flight::json(['status' => 'error', 'message' => 'Failed to create payment intent.'], 500);
    }
});

//KEEP AT THE BOTTOM OF ALL ROUTES
// Catch-all route for undefined endpoints
Flight::route('/*', function() {
    Flight::json(['status' => 'error', 'message' => 'Endpoint not found.'], 404);
});


// Start the Flight application
Flight::start();
