<?php
include 'header.php';
include 'database/db_connect.php';

if (!isset($_GET['event_id'])) {
    echo "<div class='err-msg'>Event ID missing.</div>";
    exit();
}

$event_id = intval($_GET['event_id']);

$event_query = "SELECT e.*, 
                       c.category_name, 
                       u.user_name AS owner_name
                FROM events e
                LEFT JOIN category c ON e.event_category = c.category_id
                LEFT JOIN users u ON e.owner_id = u.user_id
                WHERE e.event_id = $event_id
                LIMIT 1";

$event_res = mysqli_query($conn, $event_query);

if (!$event_res || mysqli_num_rows($event_res) == 0) {
    echo "<div class='err-msg'>Event not found.</div>";
    exit();
}

$event = mysqli_fetch_assoc($event_res);

// Identify event status (lowercased)
$event_status = isset($event['event_status']) ? strtolower($event['event_status']) : '';

// Redirect to events page if event_status is draft
if ($event_status === 'draft') {
    header('Location: events.php');
    exit();
}

$is_completed_event = $event_status === "completed";
$is_ongoing_event = $event_status === "ongoing";
$is_upcoming_event = $event_status === "published";

// --------- SEAT AVAILABILITY & DEADLINE (for ongoing/upcoming) ----------
// Use event_seats for total seats, event_available_seats for available seats
$total_seats = isset($event['event_seats']) ? intval($event['event_seats']) : 0;
$seats_available = isset($event['event_available_seats']) ? intval($event['event_available_seats']) : null;
$booked_seats = 0;
$booking_deadline = !empty($event['booking_deadline']) ? $event['booking_deadline'] : null;

// Booked seats is total_seats - event_available_seats if both set and numeric
if (($is_ongoing_event || $is_upcoming_event) && $total_seats > 0 && is_numeric($seats_available)) {
    $booked_seats = max(0, $total_seats - $seats_available);
} else if ($total_seats > 0) {
    $booked_seats = 0;
    $seats_available = $total_seats;
} else {
    $booked_seats = 0;
    $seats_available = null;
}

$banner = !empty($event['event_banner_image']) 
    ? htmlspecialchars($event['event_banner_image']) 
    : 'assets/default-banner.png';

$gallery_html = '';
if (!empty($event['event_gallery_images'])) {
    $imgs = explode(',', $event['event_gallery_images']);
    foreach ($imgs as $img) {
        $img = trim($img);
        if ($img) {
            $gallery_html .= '<img src="'.htmlspecialchars($img).'" class="gallery-thumb" alt="Event gallery image">';
        }
    }
}

/* ---------- REVIEW SYSTEM ---------- */
$user_can_review = false;
$has_already_rated = false;
$error_msg = '';
$success_msg = '';
$user_rating = 0;
$user_review_text = '';

if ($is_completed_event && isset($_SESSION['user_id'])) {
    $logged_user_id = intval($_SESSION['user_id']);

    $participation_sql = "SELECT 1 FROM bookings 
                          WHERE event_id = $event_id 
                          AND user_id = $logged_user_id 
                          LIMIT 1";

    $participation_res = mysqli_query($conn, $participation_sql);

    if ($participation_res && mysqli_num_rows($participation_res) > 0) {

        $user_can_review = true;

        $review_chk_sql = "SELECT * FROM reviews 
                           WHERE event_id = $event_id 
                           AND user_id = $logged_user_id 
                           LIMIT 1";

        $review_chk_res = mysqli_query($conn, $review_chk_sql);

        if ($review_chk_res && mysqli_num_rows($review_chk_res) > 0) {
            $has_already_rated = true;
            $row_review = mysqli_fetch_assoc($review_chk_res);
            $user_rating = (int)$row_review['review_rating'];
            $user_review_text = $row_review['review_review'];
        }
    }
}

/* ---------- SUBMIT REVIEW ---------- */

if (
    $user_can_review &&
    !$has_already_rated &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['rating'], $_POST['review_text'])
) {

    $user_rating = max(1, min(5, intval($_POST['rating'])));
    $user_review_text = trim($_POST['review_text']);

    if ($user_rating < 1 || $user_rating > 5) {
        $error_msg = "Invalid rating selected.";
    } else {

        $stmt = $conn->prepare("INSERT INTO reviews (user_id, event_id, review_rating, review_review) VALUES (?, ?, ?, ?)");
        $rating_str = strval($user_rating);
        $stmt->bind_param("iiss", $logged_user_id, $event_id, $rating_str, $user_review_text);

        if ($stmt->execute()) {
            $success_msg = "Thank you! Your review has been submitted.";
            $has_already_rated = true;
        } else {
            $error_msg = "Error submitting review.";
        }

        $stmt->close();
    }
}

/* ---------- FETCH REVIEWS ---------- */

$review_sql = "SELECT r.*, u.user_name 
               FROM reviews r
               LEFT JOIN users u ON r.user_id = u.user_id
               WHERE r.event_id = $event_id
               ORDER BY r.reviewed_at DESC";

$review_res = mysqli_query($conn, $review_sql);

$all_reviews = [];
while ($review_res && $row = mysqli_fetch_assoc($review_res)) {
    $all_reviews[] = $row;
}

/* ---------- AVG RATING ---------- */
$avg_query = "SELECT AVG(review_rating) as avg_rating, COUNT(*) as total_reviews 
              FROM reviews 
              WHERE event_id = $event_id";

$avg_res = mysqli_query($conn, $avg_query);
$avg_data = mysqli_fetch_assoc($avg_res);

$avg_rating = $avg_data['avg_rating'] ? round($avg_data['avg_rating'], 1) : null;
$total_reviews = $avg_data['total_reviews'] ?? 0;
?>

<link rel="stylesheet" href="css/single_event.css">


<div class="event-details-main">

    <img src="<?php echo $banner; ?>" alt="Event banner" class="event-banner">

    <div class="event-content-wrap">

        <div class="event-title-main">
            <?php echo htmlspecialchars($event['event_title']); ?>
        </div>

        <div>
            <span class="event-category">
                <?php echo htmlspecialchars($event['category_name']); ?>
            </span>

            <?php if ($is_completed_event && $avg_rating !== null): ?>
                <span style="margin-left:20px;">
                    <?php
                    $full = floor($avg_rating);
                    $decimal = $avg_rating - $full;

                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $full) {
                            echo '<span class="star full">★</span>';
                        }
                        elseif ($i == $full + 1 && $decimal >= 0.3) {
                            echo '<span class="star half">★</span>';
                        }
                        else {
                            echo '<span class="star">★</span>';
                        }
                    }
                    ?>
                    <span style="margin-left:5px;font-weight:700;">
                        <?php echo $avg_rating; ?>/5
                    </span>
                    <span style="color:#999;">
                        (<?php echo $total_reviews; ?> reviews)
                    </span>
                </span>
            <?php endif; ?>
        </div>

        <!-- EVENT INFO: Show for ongoing or upcoming events -->
        <?php if ($is_ongoing_event || $is_upcoming_event): ?>
            <table class="event-info-table">
                <tr>
                    <th>Date:</th>
                    <td>
                        <?php 
                        echo !empty($event['event_date']) 
                            ? date("d M Y", strtotime($event['event_date'])) 
                            : 'N/A'; 
                        ?>
                        <?php if (!empty($event['event_time'])): ?>
                            <span style="color:#666; font-size:.97em;">at <?php echo htmlspecialchars($event['event_time']); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Total Seats:</th>
                    <td>
                        <?php echo $total_seats > 0 ? $total_seats : 'No seat limit'; ?>
                    </td>
                </tr>
                <tr>
                    <th>Booked Seats:</th>
                    <td>
                        <?php echo ($total_seats > 0 && is_numeric($booked_seats)) ? $booked_seats : '-'; ?>
                    </td>
                </tr>
                <tr>
                    <th>Seats Available:</th>
                    <td>
                        <?php
                        if ($total_seats > 0 && is_numeric($seats_available)) {
                            echo ($seats_available > 0)
                                ? "<span style='color:#218a5a;font-weight:bold;'>$seats_available</span>"
                                : "<span style='color:#d42e20;font-weight:bold;'>FULL</span>";
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                </tr>
                <?php if ($booking_deadline): ?>
                <tr>
                    <th>Booking Deadline:</th>
                    <td>
                        <?php echo date("d M Y", strtotime($booking_deadline)); ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>Status:</th>
                    <td>
                        <?php
                        if ($is_ongoing_event) echo '<span style="color:#25c982;font-weight:bold;">Ongoing</span>';
                        elseif ($is_upcoming_event) echo '<span style="color:#0495fa;font-weight:bold;">Upcoming</span>';
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Host:</th>
                    <td>
                        <?php echo htmlspecialchars($event['owner_name'] ?? "-"); ?>
                    </td>
                </tr>
            </table>
        <?php endif; ?>

        <div class="event-desc-main">
            <?php echo nl2br(htmlspecialchars($event['event_description'])); ?>
        </div>

        <?php if ($gallery_html): ?>
        <div class="event-gallery-row">
            <?php echo $gallery_html; ?>
        </div>
        <?php endif; ?>

        <div class="event-action-row">

            <?php if ($is_completed_event): ?>

                <?php if ($user_can_review && !$has_already_rated): ?>

                    <form method="post" class="review-form">
                        <div class="stars-row">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <input type="radio" name="rating" value="<?php echo $i; ?>" id="star-<?php echo $i; ?>" class="star-input">
                                <label for="star-<?php echo $i; ?>" class="star-label">★</label>
                            <?php endfor; ?>
                        </div>

                        <textarea name="review_text" rows="3" required style="width:100%;margin-top:10px;"></textarea>
                        <button type="submit" class="book-event-btn" style="margin-top:10px;">Submit Review</button>
                    </form>

                    <?php if ($error_msg): ?>
                        <div style="color:red;"><?php echo $error_msg; ?></div>
                    <?php elseif ($success_msg): ?>
                        <div style="color:green;"><?php echo $success_msg; ?></div>
                    <?php endif; ?>

                <?php elseif ($user_can_review && $has_already_rated): ?>

                    <div class="review-box">
                        <strong>Your Review:</strong><br>
                        <?php
                        for ($i=1;$i<=5;$i++){
                            echo $i <= $user_rating ? "★" : "☆";
                        }
                        ?>
                        <div style="margin-top:8px;">
                            <?php echo nl2br(htmlspecialchars($user_review_text)); ?>
                        </div>
                    </div>

                <?php endif; ?>

            <?php else: ?>
                <?php if ($is_ongoing_event || $is_upcoming_event): ?>
                    <?php
                        // Booking allowed only if seat is available and before deadline (if set)
                        $now_date = date('Y-m-d');
                        $can_book = true;
                        if ($total_seats > 0 && is_numeric($seats_available) && $seats_available <= 0) {
                            $can_book = false;
                        }
                        if ($booking_deadline && $now_date > $booking_deadline) {
                            $can_book = false;
                        }
                    ?>
                    <?php if ($can_book): ?>
                        <form method="post" action="book_event.php">
                            <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                            <button type="submit" class="book-event-btn"
                                <?php if ($total_seats > 0 && is_numeric($seats_available) && $seats_available <= 0) echo 'disabled style="opacity:.66;"'; ?>
                            >Book Now</button>
                        </form>
                    <?php else: ?>
                        <div style="color:#d42e20;font-weight:bold;">
                            Booking Closed
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

        </div>

        <?php if ($is_completed_event && count($all_reviews) > 0): ?>
            <div style="margin-top:25px;">
                <h3>Ratings & Reviews</h3>

                <?php foreach ($all_reviews as $review): ?>
                    <div class="review-box">
                        <span class="review-username">
                            <?php echo htmlspecialchars($review['user_name'] ?? 'User'); ?>
                        </span>
                        <?php
                        for ($i=1;$i<=5;$i++){
                            echo $i <= (int)$review['review_rating'] ? "★" : "☆";
                        }
                        ?>
                        <span style="color:#aaa;font-size:.9em;">
                            <?php echo date('d M Y', strtotime($review['reviewed_at'])); ?>
                        </span>

                        <div style="margin-top:5px;">
                            <?php echo nl2br(htmlspecialchars($review['review_review'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('.review-form');
    if (!form) return;

    var inputs = form.querySelectorAll('.star-input');
    var labels = form.querySelectorAll('.star-label');

    function updateStars() {
        var checked = form.querySelector('.star-input:checked');
        var value = checked ? parseInt(checked.value) : 0;

        labels.forEach(function(label, index) {
            if (index < value) {
                label.classList.add('checked');
            } else {
                label.classList.remove('checked');
            }
        });
    }

    inputs.forEach(function(input){
        input.addEventListener('change', updateStars);
    });
});
</script>
