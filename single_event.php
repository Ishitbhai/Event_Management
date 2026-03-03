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

$is_completed_event   = $event_status === "completed";
$is_ongoing_event    = $event_status === "ongoing";
$is_upcoming_event   = $event_status === "published";

// --------- SEAT AVAILABILITY & DEADLINE (for ongoing/upcoming) ----------
$total_seats     = isset($event['event_seats']) ? intval($event['event_seats']) : 0;
$seats_available = isset($event['event_available_seats']) ? intval($event['event_available_seats']) : null;
$booked_seats    = 0;
$booking_deadline = !empty($event['booking_deadline']) ? $event['booking_deadline'] : null;

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
            $gallery_html .= '<img src="images/'.htmlspecialchars($img).'" class="gallery-thumb rounded shadow-sm me-2 mb-2" alt="Event gallery image">';
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

<!-- Bootstrap CDN (Classic, muted colors, responsive, some subtle animation) -->
<link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">

<!-- <link rel="stylesheet" href="css/single_event.css"> -->
<style>
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
<div class="event-details-main shadow-lg animate__animated animate__fadeInDown">

    <img src="images/<?php echo $banner; ?>" alt="Event banner" class="event-banner">

    <div class="event-content-wrap">

        <div class="event-title-main">
            <?php echo htmlspecialchars($event['event_title']); ?>
        </div>

        <div>
            <span class="event-category">
                <?php echo htmlspecialchars($event['category_name']); ?>
            </span>

            <?php if ($is_completed_event && $avg_rating !== null): ?>
                <span class="ms-2 align-middle">
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
                    <span class="ms-1 fw-bold">
                        <?php echo $avg_rating; ?>/5
                    </span>
                    <span class="text-muted">
                        (<?php echo $total_reviews; ?> reviews)
                    </span>
                </span>
            <?php endif; ?>
        </div>

        <!-- EVENT INFO: Show for ongoing or upcoming events -->
        <?php if ($is_ongoing_event || $is_upcoming_event): ?>
            <table class="event-info-table table table-borderless align-middle mt-4 mb-3">
                <tr>
                    <th>Date:</th>
                    <td>
                        <?php 
                        echo !empty($event['event_date']) 
                            ? date("d M Y", strtotime($event['event_date'])) 
                            : 'N/A'; 
                        ?>
                        <?php if (!empty($event['event_time'])): ?>
                            <span class="text-muted ms-1 small">at <?php echo htmlspecialchars($event['event_time']); ?></span>
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
                                ? "<span class='fw-bold text-success'>$seats_available</span>"
                                : "<span class='fw-bold text-danger'>FULL</span>";
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
                        if ($is_ongoing_event) echo '<span class="fw-bold text-primary">Ongoing</span>';
                        elseif ($is_upcoming_event) echo '<span class="fw-bold text-secondary">Upcoming</span>';
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

        <div class="event-desc-main mt-3 mb-2">
            <?php echo nl2br(htmlspecialchars($event['event_description'])); ?>
        </div>

        <?php if ($gallery_html): ?>
        <div class="event-gallery-row my-2">
            <?php echo $gallery_html; ?>
        </div>
        <?php endif; ?>

        <div class="event-action-row">
            <div class="action-btn-wrapper">
                <?php if ($is_completed_event): ?>
                    <?php if ($user_can_review && !$has_already_rated): ?>
                        <form method="post" class="review-form my-3 w-100" style="max-width:430px;">
                            <div class="stars-row mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" id="star-<?php echo $i; ?>" class="star-input">
                                    <label for="star-<?php echo $i; ?>" class="star-label">★</label>
                                <?php endfor; ?>
                            </div>
                            <textarea name="review_text" class="form-control mb-2" rows="3" required placeholder="Write your feedback here..."></textarea>
                            <button type="submit" class="book-event-btn btn mt-2 py-2 px-4 w-100">Submit Review</button>
                        </form>
                        <?php if ($error_msg): ?>
                            <div class="text-danger fw-semibold mb-2 w-100 text-center"><?php echo $error_msg; ?></div>
                        <?php elseif ($success_msg): ?>
                            <div class="text-success fw-semibold mb-2 w-100 text-center"><?php echo $success_msg; ?></div>
                        <?php endif; ?>
                    <?php elseif ($user_can_review && $has_already_rated): ?>
                        <div class="review-box w-100" style="max-width:430px;margin-left:auto;margin-right:auto;">
                            <span class="fw-semibold">Your Review:</span><br>
                            <?php
                            for ($i=1;$i<=5;$i++){
                                echo $i <= $user_rating ? "★" : "☆";
                            }
                            ?>
                            <div class="mt-2">
                                <?php echo nl2br(htmlspecialchars($user_review_text)); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($is_ongoing_event || $is_upcoming_event): ?>
                        <?php
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
                            <form method="post" action="book_event.php" class="w-100" style="max-width:320px;">
                                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                                <button type="submit" class="book-event-btn btn w-100 mb-3"
                                    <?php if ($total_seats > 0 && is_numeric($seats_available) && $seats_available <= 0) echo 'disabled style="opacity:.66;"'; ?>
                                >Book Now</button>
                            </form>
                        <?php else: ?>
                            <div class="fw-bold text-danger pt-2 w-100 mb-3 text-center">
                                Booking Closed
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_completed_event && count($all_reviews) > 0): ?>
            <div class="mt-4">
                <h4 class="mb-3 fw-bold">Ratings & Reviews</h4>
                <?php foreach ($all_reviews as $review): ?>
                    <div class="review-box mb-3">
                        <span class="review-username">
                            <?php echo htmlspecialchars($review['user_name'] ?? 'User'); ?>
                        </span>
                        <?php
                        for ($i=1;$i<=5;$i++){
                            echo $i <= (int)$review['review_rating'] ? "★" : "☆";
                        }
                        ?>
                        <span class="text-muted small ms-1">
                            <?php echo date('d M Y', strtotime($review['reviewed_at'])); ?>
                        </span>

                        <div class="mt-2">
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
