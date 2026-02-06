<?php
session_start();
include 'header.php';
include 'database/db_connect.php';


?>

<link rel="stylesheet" href="css/book_event.css">

<?php

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function show_error($msg) {
    ?>
    <div class='err-msg'><?php echo $msg; ?></div>
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
        <link rel="stylesheet" href="css/single_event.css">
        <div class="event-details-main book-custom">
            <div class="event-title-main book-custom booking">
                Book Event: <?php echo htmlspecialchars($event['event_title']); ?>
            </div>
            <form method="post" action="book_event.php" class="book-custom-form">
                <input type="hidden" name="event_id" value="<?php echo (int)$event_id; ?>">
                <label class="book-custom-label">How many of you will come?</label><br>
                <input
                    type="number"
                    name="attendee_count"
                    min="1"
                    max="<?php echo (int)$available_seats; ?>"
                    value="1"
                    required
                    class="book-custom-input"
                ><br>
                <button class="book-event-btn" type="submit">Book Now</button>
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
        <link rel="stylesheet" href="css/single_event.css">
        <div class="event-details-main book-custom">
            <div class="event-title-main book-custom already">Already Booked!</div>
            <div class="book-custom-msg">
                You have already submitted a booking for <strong><?php echo htmlspecialchars($event['event_title']); ?></strong>.<br>
            </div>
            <div class="book-custom-note">
                Redirecting to the event page in <span id="countdown">5</span> seconds...
            </div>
            <div class="book-custom-link">
                <a href="single_event.php?event_id=<?php echo (int)$event_id; ?>" class="create-event-btn book-custom-link">
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
        <div class="event-details-main book-custom">
            <div class="event-title-main book-custom submitted">Booking Submitted!</div>
            <div class="book-custom-msg">
                Thank you for booking your spot for <strong><?php echo htmlspecialchars($event['event_title']); ?></strong>.<br>
                <span class="book-custom-total">👥 <b>Total Persons:</b> <?php echo (int)$attendee_count; ?></span>
            </div>
            <div class="book-custom-note">
                Please check daily for your booking approval status.
            </div>
            <div class="book-custom-link">
                <a href="single_event.php?event_id=<?php echo (int)$event_id; ?>" class="create-event-btn book-custom-link">
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
