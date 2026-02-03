<?php
    require('header.php');
    require_once('database/db_connect.php');

    // Fetch banner images from the 'events' table
    $gallery_images = [];
    $sql = "SELECT event_banner_image FROM events WHERE event_banner_image IS NOT NULL AND event_banner_image <> '' LIMIT 4";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $gallery_images[] = $row['event_banner_image'];
        }
    }

?>

<link rel="stylesheet" href="css/index.css">

<main class="main-content">

    <section class="hero">
        <div class="hero-image-bg"></div>
        <div class="hero-content">
            <img src="images/logo.jpg" class="main-photo" alt="Main Event Photo">
            <h1>Welcome to Aone Hub</h1>
            <p>
                Experience unforgettable moments and seamless event management.<br>
                Whether you want to plan, join, or showcase - we bring your events to life!
            </p>
            <a href="#" class="hero-btn">Explore Events</a>
        </div>
    </section>

    <!-- SERVICES SECTION -->
    <section class="services-section">
        <h2>Our Services</h2>
        <div class="services-list">
            <div class="service-card">
                <img src="images/logo.jpg" alt="Event Planning" />
                <h3>Event Planning</h3>
                <p>
                    From concept to execution, our expert team helps you plan every detail for birthdays, weddings, conferences, & more.
                </p>
            </div>
            <div class="service-card">
                <img src="images/logo.jpg" alt="Venue Booking" />
                <h3>Venue Booking</h3>
                <p>
                    Easily search and book the perfect venue for your special occasion, tailored to your needs and style.
                </p>
            </div>
            <div class="service-card">
                <img src="images/logo.jpg" alt="Event Management" />
                <h3>Event Management</h3>
                <p>
                    We handle logistics, registrations, and coordination—so you can enjoy a smooth, hassle-free event.
                </p>
            </div>
            <div class="service-card">
                <img src="images/logo.jpg" alt="24/7 Support" />
                <h3>24/7 Support</h3>
                <p>
                    Our dedicated support team is always available to address queries and ensure your event success.
                </p>
            </div>
        </div>
    </section>

    <!-- GALLERY SECTION -->
    <section class="gallery-section">
        <h2>Event Moments Gallery</h2>
        <div class="event-gallery">
            <?php foreach ($gallery_images as $idx => $img): ?>
                <img src="<?php echo htmlspecialchars($img); ?>" class="gallery-photo" alt="Event Moment <?php echo $idx+1; ?>">
            <?php endforeach; ?>
        </div>
    </section>

    <?php if (!isset($_SESSION['user_id'])): ?>
    <section class="cta">
        <h2>Ready to Create Unforgettable Memories?</h2>
        <p>Register now or log in to organize, manage, and enjoy exclusive events with Aone Hub.</p>
        <div class="cta-buttons">
            <a href="register.php" class="register-btn">Register</a>
            <a href="login.php" class="login-btn">Login</a>
        </div>
    </section>
    <?php endif; ?>
</main>

<?php
    require('footer.php');
?>