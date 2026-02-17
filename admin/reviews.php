<?php
require_once("../database/db_connect.php");
session_start();
require('sidebar.php');


// Only admins allowed - example check, adapt as needed
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}
?>

<h1>Reviews</h1>