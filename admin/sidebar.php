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
    <?php
    // Determine the current page in a robust way
    $current_page = basename($_SERVER['PHP_SELF']);
    ?>
    <div class="sidebar-container">
        <aside class="sidebar">
            <div class="sidebar-logo">
                Aone hub
            </div>
            <nav>
                <a href="index.php" <?php if ($current_page == 'index.php') echo 'class="active"'; ?>>Dashboard</a>
                <a href="events.php" <?php if ($current_page == 'events.php') echo 'class="active"'; ?>>Events</a>
                <a href="bookings.php" <?php if ($current_page == 'bookings.php' || $current_page == 'bookings.php') echo 'class="active"'; ?>>Bookings</a>
                <a href="#" <?php if ($current_page == 'categories.php' || $current_page == 'categories.php') echo 'class="active"'; ?>>Categories</a>
                <a href="#" <?php if ($current_page == 'users.php' || $current_page == 'users.php') echo 'class="active"'; ?>>Users</a>
                <a href="#" <?php if ($current_page == 'services.php' || $current_page == 'services.php') echo 'class="active"'; ?>>Servives</a>
                <a href="#" <?php if ($current_page == 'reviews.php' || $current_page == 'reviews.php') echo 'class="active"'; ?>>Reviews</a>
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