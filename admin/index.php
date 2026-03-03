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

// Fetch dynamic dashboard numbers (add about_us and contact for completeness)
$dashboard_counts = [
    'events' => 0,
    'bookings' => 0,
    'users' => 0,
    'services' => 0,
    'reviews' => 0,
    'categories' => 0,
    'coupons' => 0,
    'about_us' => 0,
    'contact' => 0
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
$res = $conn->query("SELECT COUNT(*) as cnt FROM coupons");
if ($row = $res->fetch_assoc()) $dashboard_counts['coupons'] = (int)$row['cnt'];

// About Us (dummy/optional)
$res = $conn->query("SELECT COUNT(*) as cnt FROM about_us");
if ($res && ($row = $res->fetch_assoc())) $dashboard_counts['about_us'] = (int)$row['cnt'];

// Contact (dummy/optional)
$res = $conn->query("SELECT COUNT(*) as cnt FROM contact");
if ($res && ($row = $res->fetch_assoc())) $dashboard_counts['contact'] = (int)$row['cnt'];
?>


<!-- <link rel="stylesheet" href="css/index.css"> -->
<style>
body {
    margin: 0;
    background: #f4f6fb;
}
.dashboard-main {
    padding: 40px;
}
.dashboard-header {
    margin-bottom: 30px;
}
.dashboard-header h2 {
    margin: 0;
    color: #322053;
}
.dashboard-header p {
    color: #6c757d;
    margin-top: 8px;
}
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
}
@media (max-width: 980px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 700px) {
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
}
.dashboard-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    transition: box-shadow 0.3s ease, transform 0.5s cubic-bezier(.68,-0.55,.27,1.55);
    cursor: pointer;
    opacity: 0;
    transform: translateY(40px) scale(0.93);
    animation: fadeInUp 0.8s cubic-bezier(.68,-0.55,.27,1.55) forwards;
}
.dashboard-card:nth-child(1) { animation-delay: 0.10s; }   /* Events */
.dashboard-card:nth-child(2) { animation-delay: 0.20s; }   /* Bookings */
.dashboard-card:nth-child(3) { animation-delay: 0.30s; }   /* Categories */
.dashboard-card:nth-child(4) { animation-delay: 0.40s; }   /* Users */
.dashboard-card:nth-child(5) { animation-delay: 0.50s; }   /* Services */
.dashboard-card:nth-child(6) { animation-delay: 0.60s; }   /* Reviews */
.dashboard-card:nth-child(7) { animation-delay: 0.70s; }   /* Coupons */
.dashboard-card:nth-child(8) { animation-delay: 0.80s; }   /* About Us */
.dashboard-card:nth-child(9) { animation-delay: 0.90s; }   /* Contact */
@keyframes fadeInUp {
    0% {
        opacity: 0;
        transform: translateY(40px) scale(0.93);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
.dashboard-card:hover {
    transform: translateY(-6px) scale(1.04);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    z-index: 1;
}
.card-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
}
.card-number {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 8px;
    transition: color 0.5s;
}
.card-link {
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    color: black;
}

/* Color sequence:
   1. events       - blue
   2. bookings     - green
   3. categories   - red
   4. users        - green
   5. services     - red
   6. reviews      - blue
   7. coupons      - red
   8. about_us     - blue
   9. contact      - green
*/

.dashboard-card.events      { border-left: 6px solid #5236d6; } /* blue */
.dashboard-card.bookings    { border-left: 6px solid #197655; } /* green */
.dashboard-card.categories  { border-left: 6px solid #c82f2f; } /* red */
.dashboard-card.users       { border-left: 6px solid #197655; } /* green */
.dashboard-card.services    { border-left: 6px solid #c82f2f; } /* red */
.dashboard-card.reviews     { border-left: 6px solid #5236d6; } /* blue */
.dashboard-card.coupons     { border-left: 6px solid #c82f2f; } /* red */
.dashboard-card.about_us    { border-left: 6px solid #5236d6; } /* blue */
.dashboard-card.contact     { border-left: 6px solid #197655; } /* green */

.dashboard-card.events .card-title      { color: #5236d6; }
.dashboard-card.bookings .card-title    { color: #197655; }
.dashboard-card.categories .card-title  { color: #c82f2f; }
.dashboard-card.users .card-title       { color: #197655; }
.dashboard-card.services .card-title    { color: #c82f2f; }
.dashboard-card.reviews .card-title     { color: #5236d6; }
.dashboard-card.coupons .card-title     { color: #c82f2f; }
.dashboard-card.about_us .card-title    { color: #5236d6; }
.dashboard-card.contact .card-title     { color: #197655; }
</style>
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
        <div class="dashboard-card categories shadow-sm">
            <div class="card-title">All Categories</div>
            <div class="card-number" data-num="<?php echo $dashboard_counts['categories']; ?>">0</div>
            <a href="categories.php" class="card-link">Manage Categories →</a>
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
        <div class="dashboard-card coupons shadow-sm">
            <div class="card-title">All Coupons</div>
            <div class="card-number" data-num="<?php echo $dashboard_counts['coupons']; ?>">0</div>
            <a href="coupons.php" class="card-link">Manage Coupons →</a>
        </div>
        <div class="dashboard-card about_us shadow-sm">
            <div class="card-title">About Us</div>
            <div class="card-number" data-num="<?php echo $dashboard_counts['about_us']; ?>">0</div>
            <a href="about_us.php" class="card-link">Manage About Us →</a>
        </div>
        <div class="dashboard-card contact shadow-sm">
            <div class="card-title">Contact</div>
            <div class="card-number" data-num="<?php echo $dashboard_counts['contact']; ?>">0</div>
            <a href="contact.php" class="card-link">Manage Contact →</a>
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
