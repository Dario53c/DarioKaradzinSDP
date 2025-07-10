<?php
// Database connection settings
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'Fits_n_Finds';

// Create a new MySQLi connection
$conn = new mysqli($host, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate form data
    $newUsername = mysqli_real_escape_string($conn, $_POST['newUsername']);
    $newEmail = mysqli_real_escape_string($conn, $_POST['newEmail']);
    $newPassword = mysqli_real_escape_string($conn, $_POST['newPassword']);
    
    // Hash the password before storing it
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Check if the username or email already exists
    $checkQuery = "SELECT * FROM users WHERE username='$newUsername' OR email='$newEmail'";
    $result = $conn->query($checkQuery);

    if ($result->num_rows > 0) {
        // Username or email already exists
        echo "Username or email already taken!";
        exit;
    }

    // Prepare the SQL query to insert the new user into the database
    $sql = "INSERT INTO users (username, email, password_hash) VALUES ('$newUsername', '$newEmail', '$hashedPassword')";

    // Execute the query and check if it was successful
    if ($conn->query($sql) === TRUE) {
        echo "Registration successful";  // Response message
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;  // Handle any errors
    }
}

// Close the connection
$conn->close();
?>
