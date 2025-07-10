<?php

declare(strict_types=1);

// Database Connection Constants
define('DB_HOST', 'db-mysql-fra1-89577-do-user-23308169-0.e.db.ondigitalocean.com');
define('DB_USER', 'doadmin');
define('DB_PASS', 'AVNS_GBHNsnOJX2k3gsg-hqw');
define('DB_NAME', 'defaultdb');
define('DB_PORT', 25060);
define('DB_SSL_CA_CERT', __DIR__ . '/../ca-certificate.crt');
define('DB_SSL_VERIFY_SERVER_CERT', true);

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


if (defined('DB_SSL_CA_CERT')) {
    $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA_CERT;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = DB_SSL_VERIFY_SERVER_CERT;
}


$pdo_conn = null;

try {

    $pdo_conn = new PDO($dsn, $username, $password, $options);
    return $pdo_conn;

} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'status' => 'error',
        'message' => 'Internal Server Error: Database connection unavailable.'
    ]));
}

?>