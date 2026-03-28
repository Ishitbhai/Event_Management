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
<style>
    /* ----------- Restrict styles to about us only ----------- */
.aboutus-hero,
.aboutus-hero *,
.aboutus-content,
.aboutus-content *,
.aboutus-container,
.aboutus-container *,
.aboutus-section,
.aboutus-section *,
.aboutus-stats,
.aboutus-stat-box,
.aboutus-team,
.aboutus-member,
.aboutus-member *,
.aboutus-member:before,
.aboutus-member:hover:before,
.aboutus-member:hover,
.aboutus-slider-bg,
.aboutus-slide,
.aboutus-slide.active,
.aboutus-anim-bg,
.aboutus-content h2,
.aboutus-content p,
.aboutus-slider-dots,
.aboutus-slider-dot {
    box-sizing: inherit;
}

/* Remove or scope reset styles */
.aboutus-hero, .aboutus-content, .aboutus-section, .aboutus-slider-bg,
.aboutus-slide, .aboutus-anim-bg, .aboutus-slider-dots, .aboutus-slider-dot,
.aboutus-team, .aboutus-member, .aboutus-stat-box, .aboutus-stats, .aboutus-container {
    /* Do NOT set font-family or background for body/global outside aboutus */
    /* No global margin/padding resets here. */
}

.aboutus-hero {
    height: 90vh;
    min-height: 420px;
    background: linear-gradient(115deg, #7c3aed 0%, #f3e8ff 100%), linear-gradient(rgba(0,0,0,0.60), rgba(0,0,0,0.52));
    background-blend-mode: overlay, normal;
    background-size: cover;
    background-position: center;
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.aboutus-slider-bg {
    position: absolute;
    inset: 0;
    z-index: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
}
.aboutus-slide {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    opacity: 0;
    transition: opacity 1.3s cubic-bezier(.5,1.4,.6,1);
    z-index: 0;
}
.aboutus-slide.active {
    opacity: 1;
    transition: opacity 1.2s cubic-bezier(.4,1.3,.7,1);
}
.aboutus-anim-bg {
    position: absolute;
    inset:0;
    z-index:1;
    pointer-events:none;
}
.aboutus-hero-content {
    position:relative;
    z-index:2;
    width:100%;
    display: flex;
    flex-direction: column;
    align-items:center;
    justify-content: center;
    animation: aboutusHeroFadeIn 1.2s cubic-bezier(.5,1.4,.6,1) both;
}
@keyframes aboutusHeroFadeIn {
    from { opacity:0; transform:translateY(40px) scale(.97);}
    to { opacity:1; transform:none;}
}
.aboutus-hero h1 {
    font-size:2.8rem;
    letter-spacing:2px;
    margin-bottom:18px;
    font-weight:700;
    animation: aboutusFadeUp .9s .2s both;
    text-shadow: 0 4px 18px rgba(75,36,169,.12);
}
.aboutus-hero p {
    margin-top:0;
    font-size:1.18rem;
    max-width:650px;
    margin-inline:auto;
    animation: aboutusFadeUp .9s .5s both;
}
.aboutus-hero-cta-btn {
    margin-top:30px;
    padding:13px 38px;
    border:none;
    background: linear-gradient(96deg, #fff34f 5%, #ffd700 98%);
    color:#581c87;
    font-size:1.14rem;
    font-weight:bold;
    border-radius:32px;
    cursor:pointer;
    transition:.19s box-shadow, .19s background, .19s color;
    box-shadow:0 4px 20px rgba(249,226,120,.14);
    animation: aboutusFadeUp .9s .8s both;
    outline: none;
}
.aboutus-hero-cta-btn:hover, .aboutus-hero-cta-btn:focus {
    background: linear-gradient(96deg, #d3b900 5%, #ffe178 98%);
    color: #251c2b;
    box-shadow: 0 8px 30px rgba(255,215,64,.22);
}
@keyframes aboutusFadeUp {
  0% { opacity:0; transform:translateY(25px);}
  100% { opacity:1; transform:none;}
}
.aboutus-anim-bg span {
    position:absolute;
    border-radius:50%;
    pointer-events:none;
    opacity: .18;
    background: #c7bfff;
    animation: aboutusFloatAnim 10s ease-in-out infinite alternate;
}
.aboutus-anim-bg span.c1 {
    width:128px; height:128px; left:7vw; top:12vh; animation-delay: 0s;
}
.aboutus-anim-bg span.c2 {
    width:60px; height:60px; right:15vw; top:20vh; animation-delay: .7s;
    background: #a77ce7;
}
.aboutus-anim-bg span.c3 {
    width:48px; height:48px; left:18vw; bottom:10vh; animation-delay: 1.7s;
    background: #a7ffd0;
}
.aboutus-anim-bg span.c4 {
    width:70px; height:70px; right:10vw; bottom:12vh; animation-delay: 1.2s;
    background: #fde2b7;
}
.aboutus-anim-bg span.c5 {
    width:40px; height:40px; left:40vw; top:14vh; animation-delay: 2.4s;
    background: #ffe5eb;
}
@keyframes aboutusFloatAnim {
    0% { transform:translateY(0) scale(1);}
    50% { transform:translateY(-30px) scale(1.07);}
    100% { transform:translateY(17px) scale(.94);}
}

/* Content */
.aboutus-content {
    background:#f8f9fa;
    padding:70px 5vw 40px 5vw;
    width:100%;
}
.aboutus-container {
    max-width:1120px;
    margin:auto;
}
.aboutus-section {
    margin-bottom:53px;
}
.aboutus-section h2 {
    font-size:2rem;
    color:#7c3aed;
    margin-bottom:13px;
    letter-spacing:0.02em;
    font-weight:600;
    position:relative;
    text-shadow:0 1px 0 #eee;
    animation: aboutusFadeUp .7s .14s both;
}
.aboutus-section p {
    line-height:1.8;
    color:#374151;
    font-size:1.06rem;
    animation: aboutusFadeUp .7s .25s both;
}
.aboutus-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(155px,1fr));
    gap:22px;
    margin-top:30px;
}
.aboutus-stat-box {
    background: #fff;
    padding: 2.2rem 0.7rem;
    text-align: center;
    border-radius: 15px;
    box-shadow: 0 4px 18px rgba(124,58,237,0.055);
    transition: .16s box-shadow, .16s background;
    animation: aboutusFadeUp .7s .28s both;
}
.aboutus-stat-box:hover {
    background: #fafbff;
    box-shadow:0 8px 38px rgba(124,58,237,0.09);
}
.aboutus-stat-box h3 {
    font-size:2.1rem;
    color:#8e44ad;
    margin-bottom:8px;
    font-weight:700;
}
.aboutus-stat-box p {
    margin-top:0;
    color:#767676;
    font-size:.98rem;
    letter-spacing:.01em;
}

.aboutus-team {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:24px;
    margin-top:30px;
    animation: aboutusFadeUp .7s .18s both;
}

.aboutus-member {
    background: #fff;
    padding: 22px 18px;
    border-radius: 12px;
    text-align:center;
    transition:.2s;
    box-shadow:0 4px 18px rgba(60,43,120,0.07);
    animation: aboutusFadeUp .7s .4s both;
    position:relative;
    z-index:1;
}
.aboutus-member:before {
    content:'';
    position:absolute;
    left:50%;
    top:0; bottom:0;
    width:110%;
    transform:translateX(-50%) scaleY(0);
    background:linear-gradient(120deg, #f8fafc 0%, #d9e2fb 100%);
    opacity:0.15;
    border-radius:14px;
    z-index:-1;
    transition:.17s transform cubic-bezier(.5,.1,.45,1.6);
}
.aboutus-member:hover:before {
    transform:translateX(-50%) scaleY(1);
}
.aboutus-member:hover {
    transform:translateY(-10px) scale(1.035);
    box-shadow:0 10px 32px rgba(60,43,120,0.10);
}
.aboutus-member h3 {
    margin-bottom:8px;
    color:#2c3e50;
    font-weight:600;
    font-size:1.21em;
}
.aboutus-member p {
    color:#777;
    font-size:1.02em;
    margin-bottom:0;
}

/* FOOTER */
footer{
    background:#2c3e50;
    color:#fff;
    text-align:center;
    padding:30px 0;
}

/* Responsiveness */
@media (max-width: 900px) {
    .aboutus-hero h1 {font-size:2.1rem;}
    .aboutus-container {padding:0 2vw;}
}
@media (max-width: 600px) {
    .aboutus-hero {height: 62vh; min-height: 300px;}
    .aboutus-hero h1 {font-size:1.22rem;}
    .aboutus-hero p {font-size:0.98rem;}
    .aboutus-hero-cta-btn {padding:11px 1.3em; font-size:.97rem;}
    .aboutus-content {padding:42px 1vw 18px 1vw;}
    .aboutus-section h2 {font-size:1.15rem;}
    .aboutus-stat-box {padding: 1.35rem 0.45rem;}
    .aboutus-team {gap: 15px;}
}
.aboutus-slider-dots {
    position: absolute;
    bottom: 18px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 7px;
    z-index: 3;
}
.aboutus-slider-dot {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: rgba(255,255,255,0.55);
    border: none;
    outline: none;
    cursor:pointer;
    transition: background .2s;
    display: block;
    padding: 0;
}
.aboutus-slider-dot.active,
.aboutus-slider-dot:focus-visible {
    background:#fff34f;
}

</style>


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