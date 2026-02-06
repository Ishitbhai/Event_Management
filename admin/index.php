<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}

$page_heading = "Dashboard";
require('sidebar.php');

// Database connection
require_once("../database/db_connect.php");

// Fetch dynamic dashboard numbers
$dashboard_counts = [
    'events' => 0,
    'bookings' => 0,
    'users' => 0,
    'services' => 0,
    'reviews' => 0
];

// Total Events
$res = $conn->query("SELECT COUNT(*) as cnt FROM events");
if ($row = $res->fetch_assoc()) $dashboard_counts['events'] = (int)$row['cnt'];

// Total Bookings
$res = $conn->query("SELECT COUNT(*) as cnt FROM bookings");
if ($row = $res->fetch_assoc()) $dashboard_counts['bookings'] = (int)$row['cnt'];

// All Users (now count ALL users including admin, owner, etc.)
$res = $conn->query("SELECT COUNT(*) as cnt FROM users");
if ($row = $res->fetch_assoc()) $dashboard_counts['users'] = (int)$row['cnt'];

// All Services
$res = $conn->query("SELECT COUNT(*) as cnt FROM services");
if ($row = $res->fetch_assoc()) $dashboard_counts['services'] = (int)$row['cnt'];

// All Reviews
$res = $conn->query("SELECT COUNT(*) as cnt FROM reviews");
if ($row = $res->fetch_assoc()) $dashboard_counts['reviews'] = (int)$row['cnt'];
?>
<link rel="stylesheet" href="css/index.css">
<div class="dashboard-main">

    <div class="dashboard-header">
        <h2>Welcome <?php echo htmlspecialchars($_SESSION['admin_user_name']); ?> 👋</h2>
        <p>Here's an overview of your system.</p>
    </div>

    <div class="dashboard-cards">

        <div class="dashboard-card events">
            <div class="card-title">Total Events</div>
            <div class="card-number"><?php echo $dashboard_counts['events']; ?></div>
            <a href="manage_events.php" class="card-link">Manage events →</a>
        </div>
        
        <div class="dashboard-card bookings">
            <div class="card-title">Total Bookings</div>
            <div class="card-number"><?php echo $dashboard_counts['bookings']; ?></div>
            <a href="manage_bookings.php" class="card-link">Manage bookings →</a>
        </div>
        
        <div class="dashboard-card users">
            <div class="card-title">Registered Users</div>
            <div class="card-number"><?php echo $dashboard_counts['users']; ?></div>
            <a href="manage_users.php" class="card-link">Manage users →</a>
        </div>
        
        <div class="dashboard-card services">
            <div class="card-title">All Services</div>
            <div class="card-number"><?php echo $dashboard_counts['services']; ?></div>
            <a href="manage_services.php" class="card-link">Manage Services →</a>
        </div>

        <div class="dashboard-card reviews">
            <div class="card-title">All Reviews</div>
            <div class="card-number"><?php echo $dashboard_counts['reviews']; ?></div>
            <a href="manage_reviews.php" class="card-link">Manage Reviews →</a>
        </div>
        
    </div>

</div>

    </div>
    </div>
</body>
</html>
