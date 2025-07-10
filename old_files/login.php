<?php
session_start(); // Start the session

$db = new mysqli('localhost', 'root', '', 'Fits_n_Finds');
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$email = $db->real_escape_string($_POST['loginEmail']);
$password = $_POST['loginPassword'];

$sql = "SELECT id, username, password_hash FROM users WHERE email = '$email'";
$result = $db->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    if (password_verify($password, $row['password_hash'])) {
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        echo json_encode(['status' => 'success', 'message' => 'Login successful!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect password!']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Email not found!']);
}

$db->close();
?>
