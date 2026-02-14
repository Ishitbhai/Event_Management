<?php
    require('header.php');
    require_once('database/db_connect.php');

    // Fetch completed (past) event banner images with their IDs from the 'events' table
    $gallery_events = [];
    $sql = "SELECT event_id, event_banner_image FROM events 
            WHERE event_banner_image IS NOT NULL 
              AND event_banner_image <> '' 
              AND event_status = 'completed'
            ORDER BY event_id DESC
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

<link rel="stylesheet" href="bootstrap/css/bootstrap.min">
<link rel="stylesheet" href="bootstrap/css/animate.min.css"/>
<!-- <link rel="stylesheet" href="bootstrap/css/all.min.css"> -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="bootstrap/css/swiper-bundle.min.css"/>
<link rel="stylesheet" href="css/index.css">

<main class="main-content">

    <!-- HERO SECTION -->
    <section class="hero-bg-grad w-100 pb-5">
        <div class="container">
            <div class="hero-glass shadow animate__animated animate__fadeInDown">
                <img src="images/logo.jpg" alt="Aone Hub Main Banner" class="d-block mx-auto" />
                <h1 class="animate__animated animate__fadeInDown animate__delay-1s">
                    Welcome to Aone Hub
                </h1>
                <p class="animate__animated animate__fadeIn animate__delay-2s">
                    Make event planning simple.&nbsp;
                    <span style="color:#e6ebf2">Connect, organize, and relive moments with confidence.</span>
                </p>
                <a href="events.php" class="hero-cta-btn shadow animate__animated animate__fadeInUp animate__delay-2s">
                    <i class="fa-regular fa-calendar-check me-2"></i> Explore Events
                </a>
            </div>
        </div>
    </section>

    <!-- SERVICES SECTION -->
    <section class="services-section">
        <h2 class="animate__animated animate__fadeInDown mb-4">
            <i class="fa-solid fa-layer-group me-2"></i>Our Services
        </h2>
        <div class="container">
            <div class="row justify-content-center gy-3">
            <?php if (!empty($services)): ?>
                <?php foreach($services as $i => $service): ?>
                    <div class="col-lg-4 col-md-6 col-12 d-flex align-items-stretch animate__animated animate__fadeInUp" style="animation-delay: <?php echo 0.1*$i + 0.3; ?>s;">
                        <div class="service-card shadow-sm w-100 h-100">
                            <img src="<?php echo !empty($service['service_image']) ? 'images/'.htmlspecialchars($service['service_image']) : 'images/logo.jpg'; ?>"
                                 alt="<?php echo htmlspecialchars($service['service_title']); ?>"
                                 class="service-img mb-2"/>
                            <div>
                                <h3 class="service-title"><?php echo htmlspecialchars($service['service_title']); ?></h3>
                                <p class="service-desc"><?php echo nl2br(htmlspecialchars($service['service_description'])); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                  <span class="lead text-muted">No services available at this time.</span>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- GALLERY SECTION (slider) -->
    <section class="gallery-section">
        <h2>
            <i class="fa-regular fa-images me-2"></i>
            Recent Event Moments
        </h2>
        <div class="gallery-slider">
            <div class="swiper event-moments-swiper">
                <div class="swiper-wrapper">
                    <?php if (!empty($gallery_events)): ?>
                        <?php foreach ($gallery_events as $idx => $event): ?>
                        <div class="swiper-slide">
                            <a href="single_event.php?event_id=<?php echo (int)$event['event_id']; ?>">
                                <img src="images/<?php echo htmlspecialchars($event['event_banner_image']); ?>" class="gallery-photo shadow" alt="Event Moment <?php echo $idx+1; ?>">
                            </a>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="swiper-slide">
                            <img src="images/logo.jpg" class="gallery-photo shadow" alt="No Event Gallery">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        </div>
    </section>

    <?php if (!isset($_SESSION['user_id'])): ?>
    <section class="cta-section animate__animated animate__fadeInUp">
        <div class="container">
            <h2>
                <i class="fa-solid fa-user-plus me-2"></i>
                Join the Experience
            </h2>
            <p>Register or log in to access exclusive events, manage your bookings, and share memories on <span style="font-weight:600;color:#e5e5ea;">Aone Hub</span>.</p>
            <a href="register.php" class="cta-btn fw-bold">Register</a>
            <a href="login.php" class="cta-btn secondary fw-bold ms-1">Login</a>
        </div>
    </section>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<script>
// Swiper for Event Gallery
document.addEventListener('DOMContentLoaded', function() {
    new Swiper('.event-moments-swiper', {
        loop: true,
        slidesPerView: 1,
        spaceBetween: 14,
        breakpoints: {
            600: { slidesPerView: 2 },
            900: { slidesPerView: 3 },
        },
        autoplay: {
            delay: 3400,
            disableOnInteraction: false,
        },
        pagination: { el: '.swiper-pagination', clickable: true },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php require('footer.php'); ?>