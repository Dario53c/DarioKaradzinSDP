<?php
session_start();

ini_set('display_errors', 1); // Ensure errors are displayed during testing
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// >>> IMPORTANT: Add this line at the very top of test.php <<<
require_once __DIR__ . '/../vendor/autoload.php';

// Assuming database.php correctly returns the PDO object
$pdo_conn = require_once __DIR__ . '/database.php';
require_once __DIR__ . "/classes/UserClass.php"; // Use require_once here too

// Simulate a logged-in user (if relevant for other tests)
// $_SESSION['user_id'] = 1; // You might not need this for email testing

// Instantiate your UserClass
$user = new User($pdo_conn);

// Generate a random secret for testing the email function
$testSecret = bin2hex(random_bytes(16));

// Define the recipient for your test email
$testEmail = 'enadario@gmail.com'; // **CHANGE THIS TO YOUR ACTUAL TEST EMAIL**
$testName = 'Dario Karadzin';

echo "Attempting to send email to: " . $testEmail . "<br>";
echo "Using secret: " . $testSecret . "<br>";

// Call the sendMailjetVerificationEmail method
$emailSent = $user->sendMailjetVerificationEmail($testEmail, $testSecret, $testName);

if ($emailSent) {
    echo "Email sent successfully!<br>";
} else {
    echo "Failed to send email. Check PHP error logs for details.<br>";
}

// ... (any other test code you had) ...
?>