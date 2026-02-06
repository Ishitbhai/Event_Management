<?php
    require('header.php');
    require_once('database/db_connect.php');

    // Fetch completed (past) event banner images with their IDs from the 'events' table
    $gallery_events = [];
    $sql = "SELECT event_id, event_banner_image FROM events 
            WHERE event_banner_image IS NOT NULL 
              AND event_banner_image <> '' 
              AND event_status = 'completed'
            LIMIT 8";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $gallery_events[] = [
                'event_id' => $row['event_id'],
                'event_banner_image' => $row['event_banner_image']
            ];
        }
    } 

    // Fetch services dynamically from the 'services' table
    $services = [];
    $service_sql = "SELECT service_id, service_title, service_description, service_image FROM services LIMIT 8";
    $service_result = mysqli_query($conn, $service_sql);
    if ($service_result && mysqli_num_rows($service_result) > 0) {
        while($service_row = mysqli_fetch_assoc($service_result)) {
            $services[] = [
                'service_id' => $service_row['service_id'],
                'service_title' => $service_row['service_title'],
                'service_description' => $service_row['service_description'],
                'service_image' => $service_row['service_image']
            ];
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
            <a href="events.php" class="hero-btn">Explore Events</a>
        </div>
    </section>

    <!-- SERVICES SECTION -->
    <section class="services-section">
        <h2>Our Services</h2>
        <div class="services-list">
            <?php if (!empty($services)): ?>
                <?php foreach($services as $service): ?>
                    <div class="service-card">
                        <?php if (!empty($service['service_image'])): ?>
                            <img src="<?php echo htmlspecialchars($service['service_image']); ?>" alt="<?php echo htmlspecialchars($service['service_title']); ?>" />
                        <?php else: ?>
                            <img src="images/logo.jpg" alt="<?php echo htmlspecialchars($service['service_title']); ?>" />
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars($service['service_title']); ?></h3>
                        <p>
                            <?php echo nl2br(htmlspecialchars($service['service_description'])); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No services available at this time.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- GALLERY SECTION -->
    <section class="gallery-section">
        <h2>Event Moments Gallery</h2>
        <div class="event-gallery">
            <?php foreach ($gallery_events as $idx => $event): ?>
                <a href="single_event.php?event_id=<?php echo (int)$event['event_id']; ?>">
                    <img src="<?php echo htmlspecialchars($event['event_banner_image']); ?>" class="gallery-photo" alt="Event Moment <?php echo $idx+1; ?>">
                </a>
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