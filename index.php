<?php
    require('header.php');
    require_once('database/db_connect.php');

    // Fetch ONLY featured event banner images with their IDs and titles from the 'events' table
    $gallery_events = [];
    $sql = "SELECT event_id, event_banner_image, event_title
            FROM events
            WHERE event_is_featured = 1";

    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        while($row = mysqli_fetch_assoc($result)) {
            $gallery_events[] = [
                'event_id' => $row['event_id'],
                'event_banner_image' => $row['event_banner_image'],
                'event_title' => isset($row['event_title']) ? $row['event_title'] : ''  
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

    <!-- GALLERY SECTION (grid style for ONLY featured events) -->
    <section class="gallery-section-modern">
        <div class="container">
            <h2 class="gallery-title-modern animate__animated animate__fadeInDown mb-4">
                <i class="fa-regular fa-images me-2"></i>Event Memories Gallery
            </h2>
            <?php
            // Only display gallery if there are any featured events
            if (!empty($gallery_events)):
            ?>
                <div class="row g-3 justify-content-center">
                    <?php foreach ($gallery_events as $idx => $event): ?>
                        <div class="col-md-4 col-sm-6 col-12">
                            <div class="event-memory-card animate__animated animate__fadeInUp" style="animation-delay: <?php echo 0.12*$idx + 0.19; ?>s;">
                                <a href="single_event.php?event_id=<?php echo (int)$event['event_id']; ?>" class="d-block">
                                    <div class="gallery-img-box">
                                        <img src="images/<?php echo htmlspecialchars($event['event_banner_image']); ?>"
                                             class="event-gallery-thumb"
                                             alt="<?php echo htmlspecialchars($event['event_title']) ?: 'Event Moment '.($idx+1); ?>">
                                    </div>
                                    <?php if (!empty($event['event_title'])): ?>
                                        <div class="gallery-caption">
                                            <i class="fa-solid fa-calendar-days me-1 text-primary"></i>
                                            <?php echo htmlspecialchars($event['event_title']); ?>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="gallery-empty-state text-center mt-4 mb-1">
                    <img src="images/logo.jpg" style="width:86px;border-radius:12px;box-shadow:0 2px 10px #23345a07;">
                    <div class="gallery-caption mt-2 text-muted">
                        No featured event memories found.
                    </div>
                </div>
            <?php endif; ?>
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

<?php require('footer.php'); ?>


<style>
body, .main-content { 
    background: #f7f8fa;
    color: #293041;
}
a { text-decoration: none; }
section { scroll-margin-top: 76px; }
.hero-bg-grad {
    background: linear-gradient(120deg, #1c2230 0%, #343b4a 100%);
    min-height: 430px;
    padding: 0;
    position: relative;
    overflow: hidden;
}
.hero-glass {
    margin-top: 58px;
    background: rgba(255,255,255,.10);
    box-shadow: 0 4px 44px -7px #232b401f;
    border-radius: 30px;
    padding: 44px 30px;
    max-width: 670px;
    margin-left: auto;
    margin-right: auto;
    position: relative;
    z-index: 2;
    -webkit-backdrop-filter: blur(9px);
    backdrop-filter: blur(9px);
}
.hero-glass img {
    width: 225px;
    max-width: 75vw;
    height: 122px;
    border-radius: 17px;
    box-shadow: 0 4px 18px -8px #0e136430;
    border: 2px solid #fff;
    margin-bottom: 36px;
    object-fit: cover;
    filter: grayscale(0.03) contrast(0.98);
    animation: fadeInDown 1s;
}
.hero-glass h1 {
    font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
    font-weight: 800;
    font-size: 2.3rem;
    letter-spacing: .021em;
    margin-bottom: 12px;
    color: #eaeef5;
    text-shadow: 0 1px 14px rgba(42,51,110,0.11);
}
.hero-glass p {
    color: #cdd3e1;
    font-size: 1.18rem;
    margin-bottom: 28px;
    opacity: .89;
    text-shadow: 0 2px 12px #222f4433;
}
.hero-cta-btn {
    display: inline-block;
    background: #24365c;
    color: #f9fafa;
    font-weight: 600;
    font-size: 1.05rem;
    letter-spacing: .12em;
    padding: 13px 41px;
    border-radius: 27px;
    transition: all .18s cubic-bezier(.4,1.7,.6,1);
    text-decoration: none;
    box-shadow: 0 2px 18px 0 #384e7a23;
    border: 1.1px solid #313c58;
    margin-top: 6px;
    margin-bottom: 10px;
    transform: translateY(0);
    animation: fadeInUp 1.2s;
}
.hero-cta-btn:hover, .hero-cta-btn:focus {
    background: #363e54;
    color: #fff;
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 7px 22px 2px #40557114;
}
@keyframes fadeInDown {
    0% { opacity: 0; transform: translateY(-60px);}
    100% { opacity: 1; transform: translateY(0);}
}
@keyframes fadeInUp {
    0% { opacity: 0; transform: translateY(40px);}
    100% { opacity: 1; transform: translateY(0);}
}

/* --- SERVICES SECTION --- */
.services-section {
    background: linear-gradient(121deg, #e2e5ea 70%, #ecf2f8 100%);
    padding: 60px 0 44px 0;
    border-top: 1.3px solid #e0e4f2;
}
.services-section h2 {
    text-align: center;
    font-size: 2.08rem;
    font-weight: 700;
    color: #2a324d;
    letter-spacing: .08em;
    margin-bottom: 38px;
    text-shadow: 0 3px 17px #c3c7e022;
    animation: fadeInDown .7s;
}
.service-card {
    background: rgba(250,252,255,0.70);
    border-radius: 20px;
    box-shadow: 0 3px 23px 0 #232c3120;
    padding: 30px 22px 26px 22px;
    margin-bottom: 30px;
    margin-top: 10px;
    transition: transform .18s, box-shadow .19s;
    text-align: left;
    min-height: 248px;
    border: 1px solid #e2e4ef;
    overflow: hidden;
}
.service-card:hover {
    box-shadow: 0 9px 38px #1c283b1f;
    transform: translateY(-5px) scale(1.025);
    border-color: #c8d2ee;
}
.service-img {
    width: 54px;
    height: 54px;
    border-radius: 13px;
    object-fit: cover;
    box-shadow: 0 2px 8px #232a3615;
    background: #fafbfc;
    margin-bottom: 14px;
    transition: transform .15s;
    filter: grayscale(0.13) contrast(0.96);
}
.service-card:hover .service-img {
    transform: scale(1.07) rotate(-2deg);
}
.service-title {
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 8px;
    letter-spacing: .02em;
    color: #20283a;
}
.service-desc {
    color: #35405a;
    font-size: 1.02rem;
    opacity: .92;
}

/* --- Gallery Section (Modern grid style) --- */
.gallery-section-modern {
    background: #f5f6fa;
    padding: 58px 0 47px 0;
    min-height: 290px;
    border-top: 1.2px solid #ebeffa;
}
.gallery-title-modern {
    font-size: 2.05rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-align: center;
    margin-bottom: 36px;
    color: #232745;
    text-shadow: 0 2px 18px #aabbcd18;
    animation: fadeInDown 0.8s;
}
.event-memory-card {
    background: #f9fafb;
    box-shadow: 0 3px 18px #32405c14;
    border-radius: 23px;
    overflow: hidden;
    min-height: 220px;
    padding: 0;
    margin-bottom: 13px;
    transition: box-shadow .18s, transform .17s;
    border: 1.2px solid #e6ebf5;
    position: relative;
}
.event-memory-card:hover {
    box-shadow: 0 8px 34px #2a344014;
    transform: translateY(-4px) scale(1.025);
    border-color: #ccd1e7;
}
.gallery-img-box {
    padding: 0;
    background: #f5f7fa;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 168px;
}
.event-gallery-thumb {
    width: 100%;
    max-width: 100%;
    height: 168px;
    object-fit: cover;
    border-radius: 0;
    border-bottom: 1px solid #e2e7ef;
    transition: transform .19s;
    filter: grayscale(0.07) contrast(1.01) brightness(.97);
}
.event-memory-card:hover .event-gallery-thumb {
    transform: scale(1.038) rotate(-1deg);
    filter: grayscale(0.01) contrast(1.03) brightness(1.02);
}
.gallery-caption {
    font-size: 1.09rem;
    text-align: center;
    color: #243057;
    font-weight: 500;
    margin: 0;
    padding: 13px 7px 7px 7px;
    background: none;
    line-height: 1.30;
    letter-spacing: .01em;
    min-height: 22px;
    white-space: normal;
}
.gallery-empty-state {
    background: #f3f5fa;
    display: inline-block;
    border-radius:18px;
    padding: 26px 30px;
    box-shadow: 0 2px 14px #93aac614;
    border: 1px solid #e5ebf2;
}
.gallery-empty-state img {
    opacity: .77;
}

/* --- CTA --- */
.cta-section {
    background: linear-gradient(111deg, #242a38 32%, #383a48 120%);
    padding: 56px 0 37px 0;
    color: #deebf9;
    position: relative;
    text-align: center;
    border-top: 1.5px solid #e0e5f4;
}
.cta-section h2 {
    font-size: 1.6rem;
    font-weight: 800;
    margin-bottom: 16px;
    letter-spacing: .03em;
    text-shadow: 0 2px 15px #2d34518a;
    animation: fadeInUp 1s;
}
.cta-section p {
    font-size: 1.15rem;
    margin-bottom: 29px;
    color: #bcd1e7;
    opacity: .96;
    text-shadow: 0 2px 9px #131c2631;
}
.cta-btn, .cta-btn.secondary  {
    margin: 0 8px;
    font-weight: 600;
    padding: 13px 32px;
    font-size: 1.03rem;
    border-radius: 22px;
    box-shadow: 0 3px 16px #05080b24;
    border: none;
    letter-spacing: .02em;
    color: #24293b;
    background: #fafbfc;
    text-decoration: none;
    display: inline-block;
    transition: background .17s, box-shadow .15s, color .15s, transform .18s;
}
.cta-btn:hover {
    color: #1d2337;
    background: #e5e8ef;
    transform: translateY(-2px) scale(1.02) !important;
    box-shadow: 0 6px 23px #c5d1e628;
}
.cta-btn.secondary {
    background: #303647;
    color: #efefee;
    border: 1.5px solid #e5e8ef;
}
.cta-btn.secondary:hover {
    background: #23263b;
    color: #e5e8ef;
    border-color: #d6dae5;
}
/* Small devices */
@media (max-width: 1000px) {
    .gallery-section-modern, .gallery-title-modern { font-size: 1.07rem;}
    .gallery-img-box { min-height: 120px; }
    .event-gallery-thumb { height: 120px;}
    .event-memory-card { min-height: 156px; }
    .gallery-title-modern { font-size: 1.25rem;}
    .services-section h2, .cta-section h2 { font-size: 1.16rem;}
    .hero-glass h1 { font-size: 1.62rem;}
    .hero-glass { max-width: 99vw; padding:25px 4vw;}
}
@media (max-width: 767px) {
    .service-card { min-height: 210px; padding: 19px 7px;}
    .event-memory-card { min-height: 118px; }
    .event-gallery-thumb, .gallery-img-box { height: 80px; min-height:80px; }
    .gallery-section-modern { padding: 19px 0 23px 0;}
    .gallery-caption { font-size: .97rem; padding: 7px 3px 3px 3px;}
}

/* Remove old .gallery-section/.gallery-slider/.gallery-photo rules to avoid conflicting styles */
</style>