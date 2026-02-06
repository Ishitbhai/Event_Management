<?php
session_start();
include 'header.php';
include 'database/db_connect.php';

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function show_error($msg) {
    echo "<div class='err-msg' style='max-width:430px;margin:55px auto 0 auto;padding:16px 23px;border-radius:12px;background:#ffdede;color:#b2181e;text-align:center;font-family:sans-serif;'>" . htmlspecialchars($msg) . "</div>";
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

    // Re-fetch seats on every POST
    $available_seats = isset($event['event_available_seats']) && $event['event_available_seats'] !== "" 
        ? (int)$event['event_available_seats'] 
        : (isset($event['event_seats']) ? (int)$event['event_seats'] : 0);

    // If no seats left, instantly inform user
    if ($available_seats <= 0) {
        show_error("Sorry, there are no seats available for this event.");
    }

    if (!isset($_POST['attendee_count'])): ?>
        <link rel="stylesheet" href="css/single_event.css">
        <div class="event-details-main" style="max-width:430px;padding:40px 32px;margin:55px auto 0 auto;">
            <div class="event-title-main" style="margin-bottom:18px;text-align:center">
                Book Event: <?php echo htmlspecialchars($event['event_title']); ?>
            </div>
            <form method="post" action="book_event.php" style="text-align:center;">
                <input type="hidden" name="event_id" value="<?php echo (int)$event_id; ?>">
                <label style="font-weight:600;font-size:1.09em;">How many of you will come?</label><br>
                <input
                    type="number"
                    name="attendee_count"
                    min="1"
                    max="<?php echo (int)$available_seats; ?>"
                    value="1"
                    required
                    style="padding:7px 15px;font-size:1.09em;margin:14px 0 26px 0;width:90px;border-radius:7px;border:1px solid #bbb;"
                ><br>
                <button class="book-event-btn" type="submit" style="min-width:155px;">Book Now</button>
            </form>
        </div>
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
        <link rel="stylesheet" href="css/single_event.css">
        <div class="event-details-main" style="max-width:430px;padding:40px 32px;margin:55px auto 0 auto;text-align:center;">
            <div class="event-title-main" style="margin-bottom:25px">Already Booked!</div>
            <div style="font-size:1.15em;margin-bottom:18px;">
                You have already submitted a booking for <strong><?php echo htmlspecialchars($event['event_title']); ?></strong>.<br>
            </div>
            <div style="background:#e5f9fb;padding:15px 14px 14px 14px;border-radius:8px;color:#197655;max-width:340px;margin:0 auto;font-size:1.02em;">
                Redirecting to the event page in <span id="countdown">5</span> seconds...
            </div>
            <div style="margin-top:28px;text-align:center;">
                <a href="single_event.php?event_id=<?php echo (int)$event_id; ?>" class="create-event-btn" style="background:#6b59c3;color:#fff;border:none;border-radius:7px;padding:11px 25px;font-size:1.1em;cursor:pointer;text-decoration:none;">
                    Go to Event Page Now
                </a>
            </div>
        </div>
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
        <link rel="stylesheet" href="css/single_event.css">
        <div class="event-details-main" style="max-width:430px;padding:40px 32px;margin:55px auto 0 auto;text-align:center;">
            <div class="event-title-main" style="margin-bottom:25px">Booking Submitted!</div>
            <div style="font-size:1.15em;margin-bottom:18px;">
                Thank you for booking your spot for <strong><?php echo htmlspecialchars($event['event_title']); ?></strong>.<br>
                <span style="display:inline-block;margin:19px 0 6px 0;">👥 <b>Total Persons:</b> <?php echo (int)$attendee_count; ?></span>
            </div>
            <div style="background:#e5f9fb;padding:15px 14px 14px 14px;border-radius:8px;color:#197655;max-width:340px;margin:0 auto;font-size:1.02em;">
                Please check daily for your booking approval status.
            </div>
            <div style="margin-top:28px;text-align:center;">
                <a href="single_event.php?event_id=<?php echo (int)$event_id; ?>" class="create-event-btn" style="background:#6b59c3;color:#fff;border:none;border-radius:7px;padding:11px 25px;font-size:1.1em;cursor:pointer;text-decoration:none;">
                    Go to Event Page
                </a>
            </div>
        </div>
        <?php
        include 'footer.php';
        exit();
    else:
        show_error("Failed to submit booking. Please try again.");
    endif;
} else {
    show_error("Invalid access. Please book via the event page.");
}

include 'footer.php';
