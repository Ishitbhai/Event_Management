<?php
session_start();
// Unset all admin session variables
$_SESSION = array();
session_destroy();

// Redirect to admin login
header("Location: login.php");
exit();
?>
