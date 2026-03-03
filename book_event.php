<?php
session_start();
include 'header.php';
include 'database/db_connect.php';
?>



<link rel="stylesheet" href="bootstrap/css/all.min.css" />

<style>
    body {
    background: linear-gradient(120deg,#f9fafc 0%, #e7e7f0 100%);
    min-height: 100vh;
    font-family: 'Montserrat', 'Segoe UI', Arial, sans-serif;
    margin: 0;
    padding: 0;
}
.event-details-main.book-custom {
    max-width: 440px;
    width: 96vw;
    margin: 60px auto 0 auto;
    /* Add padding for internal spacing */
    padding: 28px 26px 32px 26px;
    background: rgba(255,255,255,0.97);
    border-radius: 28px;
    box-shadow: 0 5px 38px 0 #3322a022, 0 1.5px 4.5px #3322a026;
    overflow: hidden;
    animation: fade-in-up .95s cubic-bezier(.23,1.09,.51,.95);
    position: relative;
    box-sizing: border-box;
}
.event-details-space-bottom {
    height: 38px;
}
@keyframes fade-in-up {
    from { opacity:0; transform: translateY(48px);}
    to   { opacity:1; transform: translateY(0);}
}
.event-title-main.book-custom {
    font-size: 1.65em;
    font-weight: 900;
    background: linear-gradient(112deg,#6b59c3 0%,#4091ec 93%);
    color: #fff;
    padding: 36px 28px 26px 28px; /* Increased horizontal padding */
    letter-spacing: 0.3px;
    border-radius: 0 0 67px 0;
    margin: 0 0 15px 0;
    text-shadow: 0 2px 12px #3331;
    text-align: center;
    animation: title-anim 1s 0.1s backwards;
    box-sizing: border-box;
}
@keyframes title-anim {
    from { opacity:0; transform: scale(.95);}
    to   { opacity:1; transform: scale(1);}
}
.event-title-main.book-custom.booking  { margin-bottom: 22px;}
.event-title-main.book-custom.already  { background: #30b99a; }
.event-title-main.book-custom.submitted { background: linear-gradient(120deg,#34c378 0%,#5dd6e6 100%);}
.book-custom-form {
    padding: 35px 18px 26px 18px; /* more space for side */
    text-align:center;
    animation: form-move .9s .11s backwards;
    box-sizing: border-box;
}
@keyframes form-move {
    from { opacity: 0; transform: translateY(30px);}
    to   { opacity: 1; transform: translateY(0);}
}
.book-custom-label {
    font-weight: 700;
    font-size: 1.15em;
    color: #222a4a;
    letter-spacing:.01em;
    margin-bottom: 5px;
    display: inline-block;
    animation: fadein .85s .18s backwards;
    padding-left: 8px;
    padding-right: 8px;
    box-sizing: border-box;
}
@keyframes fadein { from{opacity:0;} to{opacity:1;} }
.book-custom-input {
    padding: 10px 16px;
    font-size:1.12em;
    margin:18px 0 21px 0;
    width: 130px;
    border-radius: 9px;
    border: 1.7px solid #babada;
    transition: box-shadow .23s,border-color .23s;
    background: #f9fafd;
    text-align: center;
    outline: none;
    box-shadow: 0 0 0 0px #9cc1ff66;
    box-sizing: border-box;
}
.book-custom-input:focus {
    border-color: #6b59c3;
    box-shadow: 0 0 0 2.5px #6b59c345;
}
.book-event-btn {
    min-width: 165px;
    background: linear-gradient(110deg,#6b59c3 30%, #6bbefc 90%);
    color: #fff;
    border: none;
    border-radius: 7px;
    padding: 12px 10px; /* side padding for button */
    font-size: 1.13em;
    font-weight: 700;
    box-shadow: 0 3px 19px -8px #377df055;
    cursor: pointer;
    transition: background .19s,box-shadow .21s;
    margin-bottom: 8px;
    margin-top: 4px;
    animation: btnjump .7s .2s backwards;
    box-sizing: border-box;
}
@keyframes btnjump {
    from {transform: scale(.98);}
    to   {transform: scale(1);}
}
.book-event-btn:hover {
    background: linear-gradient(115deg,#4e3da7 18%, #39b1f7 98%);
    box-shadow: 0 6px 28px -2px #6b59c330;
}
.err-msg {
    max-width:440px;
    margin:58px auto 0 auto;
    padding:19px 28px;
    border-radius:16px;
    background: linear-gradient(110deg,#ffdde4 0%,#ffe6ce 100%);
    color:#ac191f;
    text-align:center;
    font-family:'Montserrat',sans-serif;
    font-size:1.14em;
    letter-spacing:.03px;
    box-shadow: 0 2px 18px 0 #a1010122;
    animation: fade-in-down .77s;
    box-sizing: border-box;
}
@keyframes fade-in-down {
    from { opacity:0; transform:translateY(-27px);}
    to   { opacity:1; transform:translateY(0);}
}
.book-custom-msg {
    font-size:1.16em;
    margin-bottom:20px;
    color:#5a4877;
    font-weight:500;
    animation: fade-in-left .76s .05s backwards;
    padding-left: 10px;
    padding-right: 10px;
    box-sizing: border-box;
}
@keyframes fade-in-left {
    from { opacity:0; transform:translateX(-35px);}
    to   { opacity:1; transform:translateX(0);}
}
.book-custom-total {
    display:inline-block;
    margin:19px 0 6px 0;
    background: #fff6cc;
    padding:8px 22px;
    border-radius:9px;
    font-size:1.03em;
    color:#723f13;
    box-shadow: 0 1.5px 4px #f8d23a66;
    font-weight:600;
    animation: badge-bounce .7s .32s backwards;
    box-sizing: border-box;
}
@keyframes badge-bounce {
    0%   {transform: scale(.85);}
    60%  {transform: scale(1.07);}
    100% {transform: scale(1);}
}
.book-custom-note {
    background: linear-gradient(101deg,#e4f6fb 0%,#e4fee8 97%);
    padding:21px 21px 18px 21px;
    border-radius:11px;
    color:#197655;
    max-width:87vw;
    margin:0 auto;
    font-size:1.09em;
    box-shadow: 0 2px 8px 0 #4ec99a0e;
    line-height:1.63;
    animation: fade-in-up .85s .22s backwards;
    box-sizing: border-box;
}
.book-custom-link {
    margin-top:32px;
    text-align:center;
    animation: fadein .7s .28s backwards;
    padding-left: 8px;
    padding-right: 8px;
    box-sizing: border-box;
}
.create-event-btn.book-custom-link {
    background: linear-gradient(110deg,#6b59c3 66%, #5dc9e6 94%);
    color:#fff;
    border:none;
    border-radius:9px;
    padding:13px 38px;
    font-size:1.16em;
    font-weight: 700;
    cursor:pointer;
    text-decoration:none;
    transition:background .15s,box-shadow .22s 0.08s;
    box-shadow:0 2.5px 14px 0 #a451ae33;
    display:inline-block;
    margin-top:10px;
    box-sizing: border-box;
}
.create-event-btn.book-custom-link:hover {
    background: linear-gradient(110deg,#3d2793 10%, #38b4c9 88%);
    box-shadow:0 6px 24px 0 #8569c933;
    color: #e4fafd;
}
::-webkit-input-placeholder { color:#a4a3b6a3; opacity:1;}
::-moz-placeholder { color:#a4a3b6a3; opacity:1;}
:-ms-input-placeholder { color:#a4a3b6a3; opacity:1;}
::placeholder { color:#a4a3b6a3; opacity:1;}
/* Responsive styles */
@media (max-width: 670px) {
    .event-details-main.book-custom {
        max-width: 99vw;
        padding:12px 5vw 17px 5vw; /* More side and inside spacing for box */
        box-shadow: 0 4px 11px #3322a025;
        margin:28px auto;
        border-radius:19px;
        box-sizing: border-box;
    }
    .event-details-space-bottom {
        height: 27px;
    }
    .event-title-main.book-custom,
    .event-title-main.book-custom.submitted,
    .event-title-main.book-custom.already {
        font-size:1.22em;
        padding: 23px 11vw 17px 11vw;
        border-radius:0 0 33px 0;
        box-sizing: border-box;
    }
    .book-custom-form { padding:15px 7vw 22px 7vw; box-sizing: border-box; }
    .book-custom-note { font-size:0.99em; padding-left: 7vw; padding-right: 7vw;}
    .err-msg { max-width:98vw; padding-left:8vw; padding-right:8vw; }
    .book-custom-input { font-size:1.02em; width: 90vw; max-width:135px; padding-left:10px; padding-right:10px;}
    .book-event-btn { min-width: 109px; font-size:.99em; padding:10px 7px; }
    .create-event-btn.book-custom-link { font-size:1.08em; padding-left:10vw; padding-right:10vw;}
    .book-custom-msg, .book-custom-link { padding-left:5vw; padding-right:5vw;}
}
@media (max-width:440px) {
    .event-details-main.book-custom { border-radius: 6px; padding:8px 1.5vw 10px 1.5vw;}
    .event-title-main.book-custom { font-size: 1.09em; padding-left:6vw; padding-right:6vw;}
    .event-details-space-bottom { height: 21px;}
    .book-custom-form { padding:11px 4vw 14px 4vw }
    .create-event-btn.book-custom-link { padding:11px 8vw;}
    .book-custom-total {padding:4.5px 10vw;}
    .book-custom-note {padding-left:3vw; padding-right:3vw;}
}

body {
    background: #F7F7F8 !important;
}
.event-details-main {
    max-width: 900px;
    margin: 40px auto 55px auto;
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 5px 24px 0 #2221;
    font-family: 'Segoe UI', 'Montserrat', sans-serif;
    animation: fade-in-down .8s;
    overflow: hidden;
}

@keyframes fade-in-down {
    from { opacity: 0; transform: translateY(-40px); }
    to { opacity: 1; transform: translateY(0); }
}

.event-banner {
    width: 100%; height: 340px;
    object-fit: cover;
    border-radius: 0 0 0 0;
    background: #eef0f3;
    border-top-left-radius: 18px;
    border-top-right-radius: 18px;
    transition: box-shadow .25s;
    box-shadow: 0 0 28px -11px #0002;
    animation: img-appear .8s .12s backwards;
}
@keyframes img-appear {
    from { opacity:0; filter: grayscale(80%) blur(4px);}
    to { opacity:1; filter: grayscale(0) blur(0);}
}
.event-content-wrap {
    padding: 33px 36px 0 36px;
}

.event-title-main {
    font-size:2.2em;
    color:#25282b;
    font-weight:800;
    letter-spacing:.3px;
    margin-bottom:10px;
    line-height:1.1;
    animation: fade-in-right .7s;
}
@keyframes fade-in-right {
    from { opacity:0; transform:translateX(-40px);}
    to { opacity:1; transform:translateX(0);}
}
.event-category {
    display:inline-block;
    background:#e5e7ec;
    color:#696868;
    padding:3px 13px 3px 12px;
    font-weight:600;
    border-radius:999px;
    font-size:.99em;
    margin-bottom:10px;
    margin-right:8px;
    letter-spacing:.03em;
}

.event-info-table {
    width:100%;
    border-collapse:collapse;
    margin: 20px 0 20px 0; 
    max-width: 500px;
}
.event-info-table th, .event-info-table td {
    padding:7px 12px 7px 0;
    border:none;
    vertical-align:middle;
    font-weight:400;
    font-size:1.06em;
    color:#54575d;
}
.event-info-table th {
    width:130px;
    color:#232323;
    font-weight:500;
    background:transparent;
}
.event-info-table tr {
    transition: background .15s;
}
.event-info-table tr:hover {
    background: #f4f5fa;
}

.event-desc-main {
    font-size:1.16em;
    line-height:1.701;
    margin-bottom:18px;
    color:#23262b;
    font-family:Georgia, 'Times New Roman', Times, serif;
    background: #f8f9fa;
    padding: 16px 19px;
    border-radius: 8px;
    box-shadow: 0 1px 8px 0 #0001;
    animation: fade-in-up .6s;
}
@keyframes fade-in-up {
    from { opacity:0; transform:translateY(30px);}
    to { opacity:1; transform:translateY(0);}
}

.event-gallery-row {
    margin:22px 0 33px 0;
    text-align:left;
    display: flex;
    flex-wrap: wrap;
}

.gallery-thumb {
    width:69px; 
    height:69px; 
    object-fit:cover;
    border-radius:12px;
    margin-right:9px;
    margin-bottom:6px;
    border:1.5px solid #e3e5e8;
    box-shadow:0 2px 12px -2px #2221;
    transition: transform .14s cubic-bezier(.25,1.8,.9,.35), box-shadow .2s;
    will-change: transform;
    background: #fff;
}
.gallery-thumb:hover {
    transform: scale(1.06) translateY(-2px) rotateZ(-1deg);
    box-shadow:0 7px 28px -5px #0c2b4140;
}

.event-action-row {
    text-align:center; 
    margin:38px 0 0 0;
}
.action-btn-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    margin-top:12px;
    margin-bottom:0;
    flex-wrap: wrap;
    width:100%;
}
.book-event-btn {
    background: linear-gradient(90deg,#393939 0,#4e555b 100%);
    color: #fff !important;
    font-size:1.15em;
    font-weight:700;
    border: none;
    border-radius:40px;
    padding: 13px 40px;
    box-shadow:0 5px 20px -8px #3334;
    transition: background .16s, box-shadow .17s, color .13s, transform 0.17s;
    cursor: pointer;
    letter-spacing:.03em;
    font-family:inherit;
    position: relative;
    z-index:1;
    overflow:hidden;
    margin-left:auto;
    margin-right:auto;
    display: block;
    max-width: 300px;
}
.book-event-btn:hover, .book-event-btn:focus {
    background: linear-gradient(90deg, #23262b 0, #30343c 100%);
    color: #e9bc6a !important;
    box-shadow: 0 8px 32px -7px #2226;
    outline: none;
    transform: translateY(-1px) scale(1.05);
}
.book-event-btn:active {
    box-shadow: 0 2px 7px 0 #2222;
    transform: scale(0.97);
}
.err-msg {
    font-family:sans-serif;
    background: #fff8f8;
    color:#b2181e;
    padding:18px 28px;
    max-width:457px;
    margin:55px auto 0 auto;
    border-radius:15px;
    text-align:center;
    box-shadow:0 3px 14px 0 #c098bb17;
    letter-spacing:.03em;
    font-weight:500;
    font-size:1.1em;
    animation: fade-in-down .8s;
}

.stars-row { 
    display:flex; 
    gap:3px; 
    align-items:center; 
    margin-top:9px;
}
.star-input { display:none; }
.star-label { 
    font-size:1.85em; 
    color:#e5e6e8; 
    cursor:pointer;
    transition:color .2s, transform .2s;
    user-select:none;
}
.star-label.checked, .star-label:hover, .star-label:focus { 
    color: #dbb43d; 
    transform: scale(1.15);
}

.star { 
    position:relative; 
    display:inline-block; 
    font-size:1.23em; 
    color:#d7d2c9; 
    margin-right:1.5px;
}
.star.full    { color:#dbb43d; }
.star.half::before {
    content:"★";
    position:absolute;
    left:0;
    width:50%;
    overflow:hidden;
    color:#dbb43d;
}

.review-box {
    border:1.25px solid #ebeaec;
    background:#f9f8fa;
    border-radius:9px;
    padding:14px 18px;
    margin-bottom:18px;
    box-shadow: 0 1px 8px 0 #2221;
    animation: fade-in-up .7s;
}

.review-username {
    font-weight:600;
    color:#415a77;
    margin-right: 14px;
    font-size: 1.04em;
}
@media (max-width:991px) {
    .event-details-main { 
        max-width: 97vw;
        box-shadow: 0 3px 8px -1px #2222;
    } 
    .event-content-wrap { padding:20px 7vw 0 7vw;}
    .event-banner {height:180px;}
    .book-event-btn { font-size: 1em; padding: 11px 22px;}
}
@media (max-width:600px) {
    .event-details-main { margin:23px 0; border-radius:0;}
    .event-content-wrap { padding:13px 5vw 0 5vw;}
    .event-banner {height:120px; border-radius:0;}
    .event-title-main { font-size:1.21em;}
    .book-event-btn { max-width:99vw; width:100%;padding:11px 3vw;}
}
.form-control:focus, textarea:focus {
    border-color: #b1babf;
    box-shadow: 0 0 0 .11rem #dbb43d50 !important;
}
.review-form textarea {
    resize:vertical;
    animation: fade-in-down .8s .1s backwards;
}
</style>

<?php

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function show_error($msg) {
    ?>
    <div class='err-msg animate__animated animate__shakeX'><i class="fa-solid fa-triangle-exclamation"></i> <?php echo $msg; ?></div>
    <div class="event-details-space-bottom"></div>
    <?php
    include 'footer.php'; 
    exit();
}

if (!is_logged_in()) {
    show_error("You must be logged in to book the event. <a href='login.php'>Login here</a>.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
    $event_id = (int)$_POST['event_id'];
    $user_id = (int)$_SESSION['user_id'];

    // Get event details by ID
    $event_sql = "SELECT * FROM events WHERE event_id=$event_id LIMIT 1";
    $event_result = mysqli_query($conn, $event_sql);
    if (!$event_result || mysqli_num_rows($event_result) == 0) {
        show_error("Invalid event or event not found.");
    }
    $event = mysqli_fetch_assoc($event_result);

    // Check event date
    $event_date_str = isset($event['event_date']) ? $event['event_date'] : null;
    if (!$event_date_str) {
        show_error("Event date is not available.");
    }
    $event_date = date_create($event_date_str);
    $now_date = date_create(date('Y-m-d'));

    if ($event_date < $now_date) {
        show_error("This event has already occurred. <a href='single_event.php?event_id=" . (int)$event_id . "'>View event</a>.");
    }

    // Re-fetch seats on every POST
    $available_seats = isset($event['event_available_seats']) && $event['event_available_seats'] !== "" 
        ? (int)$event['event_available_seats'] 
        : (isset($event['event_seats']) ? (int)$event['event_seats'] : 0);

    // If no seats left, instantly inform user
    if ($available_seats <= 0) {
        show_error("Sorry, there are no seats available for this event.");
    }

    if (!isset($_POST['attendee_count'])): ?>
        <div class="event-details-main book-custom animate__animated animate__fadeInDown">
            <div class="event-title-main book-custom booking">
                <i class="fa-solid fa-calendar-check fa-fw" style="color:#fff6a6;"></i>
                Book Event: <?php echo htmlspecialchars($event['event_title']); ?>
            </div>
            <form method="post" action="book_event.php" class="book-custom-form" autocomplete="off">
                <input type="hidden" name="event_id" value="<?php echo (int)$event_id; ?>">
                <label class="book-custom-label" for="attendee_count"><i class="fa-solid fa-users"></i> How many of you will come?</label><br>
                <input
                    type="number"
                    id="attendee_count"
                    name="attendee_count"
                    min="1"
                    max="<?php echo (int)$available_seats; ?>"
                    value="1"
                    required
                    class="book-custom-input"
                ><br>
                <button class="book-event-btn animate__animated animate__pulse" type="submit"><i class="fa-solid fa-ticket"></i> Book Now</button>
            </form>
        </div>
        <div class="event-details-space-bottom"></div>
        <?php
        include 'footer.php';
        exit();
    endif;

    $attendee_count = (int)$_POST['attendee_count'];
    if ($attendee_count < 1) {
        show_error("The number of persons must be at least 1.");
    }

    // Always get the latest available_seats again before proceeding to booking logic
    $event_result_check = mysqli_query($conn, $event_sql);
    $event_check = $event_result_check && mysqli_num_rows($event_result_check) ? mysqli_fetch_assoc($event_result_check) : $event;

    // Check event date again just before booking (in case of race condition)
    $event_date_check_str = isset($event_check['event_date']) ? $event_check['event_date'] : null;
    if (!$event_date_check_str) {
        show_error("Event date is not available.");
    }
    $event_date_check = date_create($event_date_check_str);
    $now_date2 = date_create(date('Y-m-d'));
    if ($event_date_check < $now_date2) {
        show_error("This event has already occurred. <a href='single_event.php?event_id=" . (int)$event_id . "'>View event</a>.");
    }

    $current_seats = isset($event_check['event_available_seats']) && $event_check['event_available_seats'] !== "" 
        ? (int)$event_check['event_available_seats'] 
        : (isset($event_check['event_seats']) ? (int)$event_check['event_seats'] : 0);

    if ($current_seats <= 0) {
        show_error("Sorry, there are no seats available for this event.");
    }
    if ($attendee_count > $current_seats) {
        show_error("Requested number of persons ($attendee_count) is more than available seats ($current_seats). Please reduce your count.");
    }

    // Check if user has already booked this event
    $check_sql = "SELECT * FROM bookings WHERE user_id=$user_id AND event_id=$event_id LIMIT 1";
    $check_res = mysqli_query($conn, $check_sql);
    if ($check_res && mysqli_num_rows($check_res) > 0) {
        // User has already submitted a booking; redirect in 3 seconds
        ?>
        <div class="event-details-main book-custom animate__animated animate__bounceIn">
            <div class="event-title-main book-custom already"><i class="fa-solid fa-circle-check"></i> Already Booked!</div>
            <div class="book-custom-msg">
                <i class="fa-solid fa-calendar-days"></i> You have already submitted a booking for <strong><?php echo htmlspecialchars($event['event_title']); ?></strong>.
            </div>
            <div class="book-custom-note">
                <i class="fa-regular fa-circle-dot"></i>
                Redirecting to the event page in <span id="countdown">5</span> seconds...
            </div>
            <div class="book-custom-link">
                <a href="single_event.php?event_id=<?php echo (int)$event_id; ?>" class="create-event-btn book-custom-link">
                    <i class="fa-solid fa-arrow-right"></i> Go to Event Page Now
                </a>
            </div>
        </div>
        <div class="event-details-space-bottom"></div>
        <script>
            var c = 5;
            var cd = document.getElementById('countdown');
            var interval = setInterval(function() {
                c--;
                if (cd) cd.textContent = c;
                if (c <= 0) {
                    clearInterval(interval);
                    window.location.href = "single_event.php?event_id=<?php echo (int)$event_id; ?>";
                }
            }, 1000);
        </script>
        <?php
        include 'footer.php';
        exit();
    }

    // Only insert into bookings table if seats check passed above
    $booking_sql = "INSERT INTO bookings (user_id, event_id, persons)
        VALUES ($user_id, $event_id, $attendee_count)";
    $insert_event_booking = mysqli_query($conn, $booking_sql);

    if ($insert_event_booking): ?>
        <div class="event-details-main book-custom animate__animated animate__fadeInDown">
            <div class="event-title-main book-custom submitted"><i class="fa-solid fa-circle-check"></i> Booking Submitted!</div>
            <div class="book-custom-msg">
                <i class="fa-solid fa-thumbs-up"></i> Thank you for booking your spot for <strong><?php echo htmlspecialchars($event['event_title']); ?></strong>.<br>
                <span class="book-custom-total">👥 <b>Total Persons:</b> <?php echo (int)$attendee_count; ?></span>
            </div>
            <div class="book-custom-note">
                <i class="fa-solid fa-bell"></i> Please check daily for your booking approval status.
            </div>
            <div class="book-custom-link">
                <a href="single_event.php?event_id=<?php echo (int)$event_id; ?>" class="create-event-btn book-custom-link">
                    <i class="fa-solid fa-arrow-right"></i> Go to Event Page
                </a>
            </div>
        </div>
        <div class="event-details-space-bottom"></div>
        <?php
        include 'footer.php';
        exit();
    else:
        show_error("<b>Failed to submit booking.</b> Please try again.");
    endif;
} else {
    show_error("Invalid access. Please book via the event page.");
}

?>
<div class="event-details-space-bottom"></div>
<?php
include 'footer.php';
