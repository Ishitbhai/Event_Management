<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="css/sidebar.css">

</head>
<body style="margin:0; padding:0;">
    
    <div class="sidebar-container">
        <aside class="sidebar">
            <div class="sidebar-logo">
                Aone hub
            </div>
            <nav>
                <a href="#" class="active">Dashboard</a>
                <a href="#">Events</a>
                <a href="#">Create Event</a>
                <a href="#">Bookings</a>
                <a href="#">Users</a>
                <a href="#">Reviews</a>
                <a href="#">Reports</a>
                <a href="#">Settings</a>
                <a href="#">Logout</a>
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
            </div>
            <!-- Your page content goes here -->
        </div>
    </div>
</body>
</html>
