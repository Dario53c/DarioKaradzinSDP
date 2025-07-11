<?php
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');

// Load the environment variables from the .env file
$dotenv->load();
// Set the Content-Type header to application/json
header('Content-Type: application/json');

// Get the JSON string from the environment variable
// Use getenv() for robust access, or $_ENV[] if you're sure it's populated.
$jsonString = $_ENV['GOOGLE_APPLICATION_CREDENTIALS_JSON'];

// --- IMPORTANT: Error Handling ---
if ($jsonString === false || empty($jsonString)) {
    // Environment variable not set or is empty
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Environment variable GOOGLE_APPLICATION_CREDENTIALS_JSON is not set or is empty.']);
    exit;
}

// Optionally, you can validate if it's actual JSON before echoing
// This prevents sending malformed JSON to the client if the env var was set incorrectly
$decodedJson = json_decode($jsonString);
if (json_last_error() !== JSON_ERROR_NONE) {
    // The content is not valid JSON
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Content of MY_JSON_ENV_VAR is not valid JSON.', 'json_error' => json_last_error_msg()]);
    exit;
}

// Echo the raw JSON string
echo $jsonString;

// Always exit after sending a response to prevent extra output
exit;

?>