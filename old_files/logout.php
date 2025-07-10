<?php
session_start();
session_destroy(); // Destroy all session data
echo 'Logged out';
?>
