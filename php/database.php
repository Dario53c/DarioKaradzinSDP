<?php

declare(strict_types=1);

// Database Connection Constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'Fits_n_Finds');
define('DB_PORT', 3306);
define('GCS_BUCKET_NAME', 'fits-n-finds-dariokaradzin-ibu-project'); // Your GCS bucket name
define('GCP_PROJECT_ID', 'fits-n-finds'); // Your GCP Project ID (e.g., 'fits-n-finds-project-1234')
define('GOOGLE_APPLICATION_CREDENTIALS', 'C:/Users/Legion/Downloads/fits-n-finds-bbfd543804ac.json'); // Path to your GCP service account JSON file



// MySQL SSL/TLS Configuration (Uncomment and configure if your database requires SSL/TLS)
// To enable SSL:
// 1. Uncomment the 'define' lines below and provide the correct paths/values.
// 2. Uncomment the 'PDO::MYSQL_ATTR_SSL_CA' and other SSL options in the $options array.

// Path to the Certificate Authority (CA) certificate file
// define('DB_SSL_CA_CERT', '/path/to/your/ca-certificate.crt'); 

// Path to the client SSL certificate file (if client certificate authentication is required)
// define('DB_SSL_CLIENT_CERT', '/path/to/your/client-certificate.crt'); 

// Path to the client SSL key file (if client certificate authentication is required)
// define('DB_SSL_CLIENT_KEY', '/path/to/your/client-key.key'); 

// Set to true to verify the server's SSL certificate against the provided CA certificate.
// It is highly recommended to keep this true in production for security.
// define('DB_SSL_VERIFY_SERVER_CERT', true); 


// Database credentials from environment (using the defined constants)
$host = DB_HOST;
$username = DB_USER;
$password = DB_PASS;
$dbname = DB_NAME;
$port = DB_PORT;

// Construct DSN (Data Source Name)
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,   // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,         // Fetch results as associative arrays
    PDO::ATTR_EMULATE_PREPARES     => false,                    // Disable emulation for better security and performance
];

// Dynamically add SSL options if the constants are defined (indicating SSL is desired)
// Uncomment these lines when you enable SSL in the definitions above.
/*
if (defined('DB_SSL_CA_CERT')) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA_CERT;
    if (defined('DB_SSL_CLIENT_CERT')) {
        $options[PDO::MYSQL_ATTR_SSL_CERT] = DB_SSL_CLIENT_CERT;
    }
    if (defined('DB_SSL_CLIENT_KEY')) {
        $options[PDO::MYSQL_ATTR_SSL_KEY] = DB_SSL_CLIENT_KEY;
    }
    if (defined('DB_SSL_VERIFY_SERVER_CERT')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = DB_SSL_VERIFY_SERVER_CERT;
    }
}
*/

$pdo_conn = null;

try {
    // Attempt to create a new PDO instance
    $pdo_conn = new PDO($dsn, $username, $password, $options);
    
    // This file should strictly RETURN the connection.
    // No `echo`, `print`, `die`, or `header()` calls that output content
    // or send headers before Flight takes control.
    return $pdo_conn;

} catch (PDOException $e) {
    // Log the actual database connection error message for debugging
    error_log("Database connection error: " . $e->getMessage());
    
    // If the database connection fails, the application can't proceed.
    // Ensure this `die` always outputs JSON and sets a 500 status code.
    http_response_code(500); // Set HTTP status code for Internal Server Error
    die(json_encode([
        'status' => 'error',
        'message' => 'Internal Server Error: Database connection unavailable.'
    ]));
}

?>