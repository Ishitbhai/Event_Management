<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aone hub</title>
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">
</head>
<body>

<header class="header">
    <div class="logo">
        <span>Aone</span>Hub
    </div>

    <nav class="navbar">
        <a href="index.php">Home</a>
        <a href="events.php">Events</a>
        <a href="#">Services</a>
        <a href="bookings.php">Bookings</a>
        <a href="#">Reviews</a>

        <div class="dropdown">
            <a href="#" class="dropbtn">More ▾</a>
            <div class="dropdown-content">
                <a href="#">About Us</a>
                <a href="#">Contact</a>
                <a href="#">Help</a>
            </div>
        </div>
    </nav>

    <div class="auth-buttons">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="auth-btn profile-btn" title="Profile">
                <img src="images/user.png" alt="Profile" />
                <span>Profile</span>
            </a>
            <a href="logout.php" class="auth-btn logout-btn" title="Logout">
                <img src="images/logout.png" alt="Logout" />
                <span>Logout</span>
            </a>
        <?php else: ?>
            <a href="login.php" class="login">Login</a>
            <a href="register.php" class="register">Register</a>
        <?php endif; ?>
    </div>
</header>

