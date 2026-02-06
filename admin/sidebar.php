<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="css/sidebar.css">
    <script src="js/sidebar.js"></script>

</head>
<body>
    <div class="sidebar-container">
        <aside class="sidebar">
            <div class="sidebar-logo">
                Aone hub
            </div>
            <nav>
                <a href="index.php" class="active">Dashboard</a>
                <a href="#">Events</a>
                <a href="#">Bookings</a>
                <a href="#">Users</a>
                <a href="#">Servives</a>
                <a href="#">Reviews</a>
                <a href="logout.php">Logout</a>
            </nav>
            <div class="sidebar-footer">
                &copy; <?php echo date("Y"); ?> Event Management
            </div>
        </aside>
        <div class="main-content">
            <div class="page-header">
                <?php
                // Provide a default heading, or echo a $page_heading variable if set
                if (isset($page_heading) && $page_heading) {
                    echo htmlspecialchars($page_heading);
                } else {
                    echo "Admin Panel";
                }
                ?>
                <button class="profile-btn" title="Profile" onclick="window.location.href='profile.php'">
                    <span class="profile-icon">&#128100;</span>
                </button>
            </div>