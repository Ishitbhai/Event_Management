<?php
    require('header.php');
    require_once('database/db_connect.php');

    // Fetch about_us content where about_us_id = 1
    $about_us = [
        "about_us_title" => "",
        "about_us_title_text" => "",
        "about_us_images" => "",
        "about_us_who_we_are" => "",
        "about_us_experience" => 0,
        "about_us_team_members" => 0,
        "about_us_team_member_1" => "",
        "about_us_team_member_1_role" => "",
        "about_us_team_member_2" => "",
        "about_us_team_member_2_role" => "",
        "about_us_team_member_3" => "",
        "about_us_team_member_3_role" => "",
        "about_us_team_member_4" => "",
        "about_us_team_member_4_role" => ""
    ];
    $sql_about = "SELECT * FROM about_us WHERE about_us_id=1 LIMIT 1";
    $result_about = mysqli_query($conn, $sql_about);
    if ($result_about && mysqli_num_rows($result_about) > 0) {
        $about_us = mysqli_fetch_assoc($result_about);
    }

    // Prepare hero images (slider support)
    $hero_images = [];
    if (!empty($about_us['about_us_images'])) {
        $raw_images = explode(',', $about_us['about_us_images']);
        foreach ($raw_images as $img) {
            $trimmed = trim($img);
            if ($trimmed) {
                $hero_images[] = 'images/' . htmlspecialchars($trimmed);
            }
        }
    }
    if (empty($hero_images)) {
        $hero_images[] = "images/logo.jpg";
    }

    // Get the number of completed events
    $completed_events_count = 0;
    $sql_events = "SELECT COUNT(*) AS total FROM events WHERE event_status='completed'";
    $result_events = mysqli_query($conn, $sql_events);
    if ($result_events && $row = mysqli_fetch_assoc($result_events)) {
        $completed_events_count = (int)$row["total"];
    }

    // Find unique happy clients who booked at least one completed event, by owner_id (users)
    $happy_clients_count = 0;
    $happy_client_ids = [];
    $sql_happy = "SELECT DISTINCT owner_id
                  FROM events
                  WHERE event_status='completed' AND owner_id IS NOT NULL AND owner_id <> ''";
    $result_happy = mysqli_query($conn, $sql_happy);
    if ($result_happy) {
        while($row = mysqli_fetch_assoc($result_happy)) {
            $happy_client_ids[] = $row["owner_id"];
        }
    }
    $happy_clients_count = count($happy_client_ids);

    // Team members: collect filled members only
    $team_members = [];
    for ($i = 1; $i <= 4; $i++) {
        $name = trim($about_us["about_us_team_member_".$i] ?? "");
        $role = trim($about_us["about_us_team_member_".$i."_role"] ?? "");
        if (!empty($name)) {
            $team_members[] = [
                "name" => htmlspecialchars($name),
                "role" => htmlspecialchars($role)
            ];
        }
    }
?>
<link rel="stylesheet" href="css/about_us.css">

<div class="aboutus-hero">
    <div class="aboutus-slider-bg" aria-hidden="true">
        <?php foreach ($hero_images as $idx => $img_path): ?>
            <div class="aboutus-slide<?php echo $idx === 0 ? ' active' : ''; ?>" style="background-image: url('<?php echo $img_path;?>');"></div>
        <?php endforeach; ?>
    </div>
    <div class="aboutus-anim-bg" aria-hidden="true">
        <span class="c1"></span>
        <span class="c2"></span>
        <span class="c3"></span>
        <span class="c4"></span>
        <span class="c5"></span>
    </div>
    <div class="aboutus-hero-content">
        <h1><?php echo htmlspecialchars($about_us['about_us_title'] ?: "Elite Event Management"); ?></h1>
        <p><?php echo htmlspecialchars($about_us['about_us_title_text'] ?: "Designing unforgettable celebrations with creativity, elegance & perfection."); ?></p>
        <button class="aboutus-hero-cta-btn" onclick="location.href='events.php'">Plan Your Event</button>
    </div>
    <?php if (count($hero_images) > 1): ?>
        <div class="aboutus-slider-dots">
        <?php foreach ($hero_images as $idx => $img_path): ?>
            <button 
                class="aboutus-slider-dot<?php echo $idx === 0 ? ' active' : ''; ?>" 
                data-slide="<?php echo $idx;?>"
                aria-label="Go to slide <?php echo ($idx+1); ?>"
                tabindex="0"></button>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
(function(){
    <?php if (count($hero_images) > 1): ?>
    const slides = document.querySelectorAll('.aboutus-slide');
    const dots = document.querySelectorAll('.aboutus-slider-dot');
    let curr = 0, TIMER = null;
    const interval = 4500; // ms

    function showSlide(idx) {
        slides[curr].classList.remove('active');
        dots[curr] && dots[curr].classList.remove('active');
        curr = idx;
        slides[curr].classList.add('active');
        dots[curr] && dots[curr].classList.add('active');
    }
    function nextSlide() {
        let next = (curr+1)%slides.length;
        showSlide(next);
    }
    function autoScrollStart() {
        if (TIMER) clearInterval(TIMER);
        TIMER = setInterval(nextSlide, interval);
    }
    dots.forEach((dot, idx) => {
        dot.addEventListener('click', () => {
            showSlide(idx);
            autoScrollStart();
        });
    });
    autoScrollStart();
    <?php endif; ?>
})();
</script>
<div class="aboutus-content">
    <div class="aboutus-container">
        <div class="aboutus-section">
            <h2>Who We Are</h2>
            <p>
                <?php echo htmlspecialchars($about_us['about_us_who_we_are'] ?: "Elite Event Management is a premium event planning company specializing in luxury weddings, corporate events, private parties and grand celebrations. We combine creativity, technology and flawless execution to deliver unforgettable experiences."); ?>
            </p>
        </div>

        <div class="aboutus-section">
            <h2>Our Achievements</h2>
            <div class="aboutus-stats">
                <div class="aboutus-stat-box">
                    <h3><span class="countup" data-target="<?php echo $completed_events_count; ?>">0</span></h3>
                    <p>Events Completed</p>
                </div>
                <div class="aboutus-stat-box">
                    <h3><span class="countup" data-target="<?php echo $happy_clients_count; ?>">0</span></h3>
                    <p>Happy Clients</p>
                </div>
                <div class="aboutus-stat-box">
                    <h3><span class="countup" data-target="<?php echo htmlspecialchars($about_us['about_us_experience']); ?>">0</span>+</h3>
                    <p>Years Experience</p>
                </div>
                <div class="aboutus-stat-box">
                    <h3><span class="countup" data-target="<?php echo htmlspecialchars($about_us['about_us_team_members']); ?>">0</span>+</h3>
                    <p>Professional Team</p>
                </div>
            </div>
        </div>

        <?php if (count($team_members) > 0): ?>
        <div class="aboutus-section">
            <h2>Meet Our Team</h2>
            <div class="aboutus-team">
                <?php foreach($team_members as $member): ?>
                <div class="aboutus-member">
                    <h3><?php echo $member['name']; ?></h3>
                    <?php if (!empty($member['role'])): ?>
                        <p><?php echo $member['role']; ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
<script>
// CountUp animation for stats
document.addEventListener("DOMContentLoaded", function() {
    function animateCountUp(el, target, duration = 1300) {
        let start = 0;
        // If target is not a valid number, just set text.
        target = parseInt(target, 10);
        if (isNaN(target)) {
            el.textContent = '0';
            return;
        }
        if (target === 0) {
            el.textContent = '0';
            return;
        }
        let startTime = null;
        function step(ts) {
            if (!startTime) startTime = ts;
            let progress = ts - startTime;
            let percent = Math.min(progress / duration, 1);
            let current = Math.floor(percent * (target - start) + start);
            el.textContent = current;
            if (percent < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target;
            }
        }
        requestAnimationFrame(step);
    }

    // Animate each countup span when the page loads/in view
    let counters = document.querySelectorAll('.countup');
    let started = false; // Make sure animation runs once

    // Simple intersection observer for better user experience
    function runCounters() {
        if (started) return;
        started = true;
        counters.forEach(function(counter) {
            let target = counter.getAttribute('data-target');
            animateCountUp(counter, target);
        });
    }

    if ('IntersectionObserver' in window) {
        let observer = new IntersectionObserver(function(entries, obs) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    runCounters();
                    obs.disconnect();
                }
            });
        }, { threshold: 0.18 });
        if (counters.length > 0) {
            observer.observe(counters[0].closest('.aboutus-stats'));
        }
    } else {
        // Fallback for old browsers
        runCounters();
    }
});
</script>
<?php
    include('footer.php');
?>