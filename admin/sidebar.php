<?php
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <!-- Load all Bootstrap CSS files from ../bootstrap/css/ -->
    <?php
    $bootstrap_css_dir = realpath(__DIR__ . '/../bootstrap/css/');
    if ($bootstrap_css_dir && is_dir($bootstrap_css_dir)) {
        $css_files = glob($bootstrap_css_dir . '/*.css');
        foreach ($css_files as $css_file) {
            $relative_path = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', $css_file);
            // Adapt path to relative-for-inclusion: output as "../bootstrap/css/..."
            $href = "../bootstrap/css/" . basename($css_file);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($href) . '">' . "\n";
        }
    }
    ?>
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
                <a href="categories.php" <?php if ($current_page == 'categories.php' || $current_page == 'categories.php') echo 'class="active"'; ?>>Categories</a>
                <a href="users.php" <?php if ($current_page == 'users.php' || $current_page == 'users.php') echo 'class="active"'; ?>>Users</a>
                <a href="services.php" <?php if ($current_page == 'services.php' || $current_page == 'services.php') echo 'class="active"'; ?>>Services</a>
                <a href="reviews.php" <?php if ($current_page == 'reviews.php' || $current_page == 'reviews.php') echo 'class="active"'; ?>>Reviews</a>
                <a href="coupons.php" <?php if ($current_page == 'coupons.php') echo 'class="active"'; ?>>Coupons</a>
                <a href="about_us.php" <?php if ($current_page == 'about_us.php') echo 'class="active"'; ?>>About Us</a>
                <a href="contact.php" <?php if ($current_page == 'contact.php') echo 'class="active"'; ?>>Contact</a>
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