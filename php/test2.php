<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Assuming database.php correctly returns the PDO object
$pdo_conn = require_once __DIR__ . '/database.php';
require_once __DIR__ . "/classes/UserClass.php"; // Use require_once here too

// Simulate a logged-in user (if relevant for other tests)
// $_SESSION['user_id'] = 1; // You might not need this for email testing

// Instantiate your UserClass
$user = new User($pdo_conn);

$email = $user->getUserEmailById(2);
$verifyData = $user->getUserVerified(1);
echo "User Email: " . $email . "<br>";
echo "Verification Data: " . $verifyData;