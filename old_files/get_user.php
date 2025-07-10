<?php
session_start();

// Assuming you store the username in the session after login
if (isset($_SESSION['username'])) {
    echo json_encode([
        'loggedIn' => true,
        'username' => $_SESSION['username']
    ]);
} else {
    echo json_encode([
        'loggedIn' => false,
        'username' => null
    ]);
}
?>
