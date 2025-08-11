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

$cartItems = $item->getCartItems(1); // Store the returned array in a variable

// Check if any items were found before trying to loop
if (empty($cartItems)) {
    echo "No items found in the cart.";
} else {
    // Loop through each item in the array
    foreach ($cartItems as $cartItem) {
        // Now you can echo specific properties of each item
        echo "Item ID: " . htmlspecialchars($cartItem['id']) . "<br>";
        echo "Name: " . htmlspecialchars($cartItem['name']) . "<br>";
        echo "Price: $" . htmlspecialchars($cartItem['price']) . "<br>";
        echo "Image URL: " . htmlspecialchars($cartItem['image_url']) . "<br>";
        echo "--------------------------<br>";
    }
}
?>