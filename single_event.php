<?php
include 'header.php';
include 'database/db_connect.php';

// Use event_id in URL, not event_code
if (!isset($_GET['event_id'])) {
    echo "<div class='err-msg'>Event ID missing.</div>";
    exit();
}

$event_id = intval($_GET['event_id']);
$event_query = "SELECT e.*, c.category_name AS category_name, u.user_name AS owner_name
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

// Banner image logic
$banner = !empty($event['event_banner_image']) ? htmlspecialchars($event['event_banner_image']) : 'assets/default-banner.png';
// Gallery images logic
$gallery_html = '';
if (!empty($event['event_gallery_images'])) {
    $imgs = explode(',', $event['event_gallery_images']);
    foreach($imgs as $img) {
        $img = trim($img);
        if ($img)
            $gallery_html .= '<img src="'.htmlspecialchars($img).'" class="gallery-thumb" alt="Event gallery image">';
    }
}
?>
<link rel="stylesheet" href="css/single_event.css">
<div class="event-details-main">
    <img src="<?php echo $banner; ?>" alt="Event banner" class="event-banner">
    <div class="event-content-wrap">
        <div class="event-title-main"><?php echo htmlspecialchars($event['event_title']); ?></div>
        <div>
            <span class="event-category"><?php echo htmlspecialchars($event['category_name']); ?></span>
        </div>
        <div class="event-meta-row">
            <span><strong>Organized by:</strong> <?php echo htmlspecialchars($event['owner_name']); ?></span>
            <span><strong>Date:</strong> <?php echo htmlspecialchars(date('d M, Y', strtotime($event['event_date']))); ?></span>
            <span><strong>Time:</strong> <?php echo htmlspecialchars($event['event_start_time']); ?> - <?php echo htmlspecialchars($event['event_end_time']); ?></span>
        </div>

        <table class="event-info-table">
            <tr>
                <td><strong>Seats:</strong></td>
                <td><?php echo (int)$event['event_seats']; ?></td>
                <td><strong>Available:</strong></td>
                <td>
                    <?php
                        // Shows available seats if such field exists, otherwise just show '-'
                        echo isset($event['event_available_seats']) ? (int)$event['event_available_seats'] : '-';
                    ?>
                </td>
            </tr>
            <tr>
                <td><strong>Reg. deadline:</strong></td>
                <td colspan="3"><?php echo htmlspecialchars($event['event_registration_deadline']); ?></td>
            </tr>
        </table>

        <?php if ($gallery_html): ?>
        <div class="event-gallery-row">
            <?= $gallery_html ?>
        </div>
        <?php endif; ?>

        <div class="event-desc-main"><?php echo nl2br(htmlspecialchars($event['event_description'])); ?></div>
        
        <div class="event-action-row">
            <form method="post" action="book_event.php" style="display:inline;">
                <input type="hidden" name="event_id" value="<?php echo (int)$event['event_id']; ?>">
                <button type="submit" class="book-event-btn">Book Now</button>
            </form>
        </div>
    </div>
</div>
