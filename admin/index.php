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
    'reviews' => 0,
    'categories' => 0,
    'coupons' => 0,
    'settings' => 0 // Added for settings
];

// Total Events
$res = $conn->query("SELECT COUNT(*) as cnt FROM events");
if ($row = $res->fetch_assoc()) $dashboard_counts['events'] = (int)$row['cnt'];

// Total Bookings
$res = $conn->query("SELECT COUNT(*) as cnt FROM bookings");
if ($row = $res->fetch_assoc()) $dashboard_counts['bookings'] = (int)$row['cnt'];

// Total Users
$res = $conn->query("SELECT COUNT(*) as cnt FROM users");
if ($row = $res->fetch_assoc()) $dashboard_counts['users'] = (int)$row['cnt'];

// Total Services
$res = $conn->query("SELECT COUNT(*) as cnt FROM services");
if ($row = $res->fetch_assoc()) $dashboard_counts['services'] = (int)$row['cnt'];

// Total Reviews
$res = $conn->query("SELECT COUNT(*) as cnt FROM reviews");
if ($row = $res->fetch_assoc()) $dashboard_counts['reviews'] = (int)$row['cnt'];

// Total Categories
$res = $conn->query("SELECT COUNT(*) as cnt FROM category");
if ($row = $res->fetch_assoc()) $dashboard_counts['categories'] = (int)$row['cnt'];

// Total Coupons
// $res = $conn->query("SELECT COUNT(*) as cnt FROM coupons");
if ($row = $res->fetch_assoc()) $dashboard_counts['coupons'] = (int)$row['cnt'];

// Total Settings (if there's a settings table, otherwise just show as 1 for presence)
// $res = $conn->query("SELECT COUNT(*) as cnt FROM settings");
if ($res && ($row = $res->fetch_assoc())) {
    $dashboard_counts['settings'] = (int)$row['cnt'];
} else {
    $dashboard_counts['settings'] = 1; // Fallback if table does not exist or for non-count settings
}
?>


<link rel="stylesheet" href="css/index.css">





<div class="dashboard-main">
    <div class="dashboard-header">
        <h2>Welcome <?php echo htmlspecialchars($_SESSION['admin_user_name']); ?> 👋</h2>
        <p>Here's an overview of your system.</p>
    </div>
    <div class="dashboard-cards">
        <div class="dashboard-card events shadow-sm">
            <div class="card-title">Total Events</div>
            <div class="card-number" data-num="<?php echo $dashboard_counts['events']; ?>">0</div>
            <a href="events.php" class="card-link">Manage events →</a>
        </div>
        <div class="dashboard-card bookings shadow-sm">
            <div class="card-title">Total Bookings</div>
            <div class="card-number" data-num="<?php echo $dashboard_counts['bookings']; ?>">0</div>
            <a href="bookings.php" class="card-link">Manage bookings →</a>
        </div>
        <div class="dashboard-card users shadow-sm">
            <div class="card-title">Registered Users</div>
            <div class="card-number" data-num="<?php echo $dashboard_counts['users']; ?>">0</div>
            <a href="users.php" class="card-link">Manage users →</a>
        </div>
        <div class="dashboard-card services shadow-sm">
            <div class="card-title">All Services</div>
            <div class="card-number" data-num="<?php echo $dashboard_counts['services']; ?>">0</div>
            <a href="services.php" class="card-link">Manage Services →</a>
        </div>
        <div class="dashboard-card reviews shadow-sm">
            <div class="card-title">All Reviews</div>
            <div class="card-number" data-num="<?php echo $dashboard_counts['reviews']; ?>">0</div>
            <a href="reviews.php" class="card-link">Manage Reviews →</a>
        </div>
        <div class="dashboard-card categories shadow-sm">
            <div class="card-title">All Categories</div>
            <div class="card-number" data-num="<?php echo $dashboard_counts['categories']; ?>">0</div>
            <a href="categories.php" class="card-link">Manage Categories →</a>
        </div>
        <div class="dashboard-card coupons shadow-sm">
            <div class="card-title">All Coupons</div>
            <div class="card-number" data-num="<?php echo $dashboard_counts['coupons']; ?>">0</div>
            <a href="coupons.php" class="card-link">Manage Coupons →</a>
        </div>
        <div class="dashboard-card settings shadow-sm">
            <div class="card-title">Settings</div>
            <div class="card-number" data-num="<?php echo $dashboard_counts['settings']; ?>">0</div>
            <a href="settings.php" class="card-link">Manage Settings →</a>
        </div>
    </div>
</div>

<!-- Bootstrap JS (for completeness, in case popovers, etc. are used later) -->
<script src="../bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- Animated count-up for Dashboard numbers -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Animate each card-number element using a count up
    document.querySelectorAll('.card-number').forEach(function(el) {
        const target = parseInt(el.getAttribute('data-num'), 10);
        if (isNaN(target)) return;
        let start = 0;
        const duration = 1000; // ms
        const startTimestamp = performance.now();

        function step(currentTime) {
            const progress = Math.min((currentTime - startTimestamp) / duration, 1);
            el.textContent = Math.floor(progress * (target - start) + start);
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target;
            }
        }
        requestAnimationFrame(step);
    });
});
</script>
