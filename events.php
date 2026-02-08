<?php
include 'header.php';
include 'database/db_connect.php';

// Fetch events and sort by status
$events_query = "SELECT * FROM events ORDER BY event_id DESC";
$result = mysqli_query($conn, $events_query);

$past_events = [];
$ongoing_events = [];
$upcoming_events = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($event = mysqli_fetch_assoc($result)) {
        if (isset($event['event_status'])) {
            $status = strtolower($event['event_status']);
            if ($status == 'completed') {
                $past_events[] = $event;
            } elseif ($status == 'ongoing') {
                $ongoing_events[] = $event;
            } elseif ($status == 'published') {
                $upcoming_events[] = $event;
            }
        }
    }
}

function render_gallery($event_gallery_images) {
    if (empty($event_gallery_images)) return '';
    $images = explode(',', $event_gallery_images);
    $out = '<div class="event-gallery-slider">';
    foreach ($images as $idx => $img) {
        $src = trim($img);
        if (!$src) continue;
        // Add images/ path if not already present or if not an absolute URL
        if (strpos($src, '://') === false && strpos($src, 'images/') !== 0) {
            $src = 'images/' . $src;
        }
        $out .= '<img src="' . htmlspecialchars($src) . '" class="gallery-img" alt="Gallery Image">';
    }
    $out .= '</div>';
    return $out;
}
?>

    <link rel="stylesheet" href="css/events.css">
    <script src="js/events.js"></script>
    <div class="event-header-main">
        <h1 style="margin:0;font-size:2.5em;letter-spacing:.5px;">🎉 Events at Aone Hub</h1>
        <p style="font-size: 1.17em;opacity:.91;font-weight:400;">Discover what's happening, remember remarkable past events, and see what's coming up</p>
    </div>
    <div class="main-btn-row">
        <a href="create_event.php" class="create-event-btn">+ Create Event</a>
    </div>
    <div class="row">
        <div class="container">
            <h2 style="color:#1e9e6b;font-weight:800;font-size:1.27em;margin-bottom:19px;">
                <span style="vertical-align:middle;">🟢 Ongoing &amp; Upcoming Events</span>
            </h2>
            <ul class="events-list">
                <?php 
                    $has_events = (count($ongoing_events) > 0 || count($upcoming_events) > 0);
                    if(!$has_events): ?>
                    <li style="text-align:center;opacity:.70;">No ongoing or upcoming events.</li>
                <?php else: ?>
                    <?php foreach($ongoing_events as $event): ?>
                        <li class="event-item event-link" style="cursor:pointer;">
                            <a href="single_event.php?event_id=<?php echo (int)$event['event_id']; ?>" style="text-decoration:none;color:inherit;display:block;">
                            <?php if (!empty($event['event_banner_image'])): 
                                $banner = $event['event_banner_image'];
                                // Add images/ path if not already present or if not an absolute URL
                                if (strpos($banner, '://') === false && strpos($banner, 'images/') !== 0) {
                                    $banner = 'images/' . $banner;
                                }
                            ?>
                                <img src="<?php echo htmlspecialchars($banner); ?>" class="event-banner-thumb" alt="Banner">
                            <?php else: ?>
                                <img src="assets/default-banner.png" class="event-banner-thumb" alt="Banner">
                            <?php endif; ?>
                            <div class="event-main-content">
                                <div class="event-title-bar">
                                    <strong><?php echo htmlspecialchars($event['event_title']); ?></strong>
                                    <span class="event-badge ongoing">Ongoing</span>
                                </div>
                                <?php if (!empty($event['event_date'])): ?>
                                    <span class="event-date-row">Started: <?php echo htmlspecialchars(date('d M Y', strtotime($event['event_date']))); ?></span>
                                <?php endif; ?>
                                <div class="event-description">
                                    <?php if (!empty($event['event_description'])) echo htmlspecialchars($event['event_description']); ?>
                                </div>
                                <div class="status-row">
                                    <span>Status: <?php echo htmlspecialchars($event['event_status']); ?></span>
                                </div>
                                <?php 
                                    if (!empty($event['event_gallery_images'])) {
                                        echo render_gallery($event['event_gallery_images']);
                                    }
                                ?>
                            </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php foreach($upcoming_events as $event): ?>
                        <li class="event-item event-link" style="cursor:pointer;">
                            <a href="single_event.php?event_id=<?php echo (int)$event['event_id']; ?>" style="text-decoration:none;color:inherit;display:block;">
                            <?php if (!empty($event['event_banner_image'])): 
                                $banner = $event['event_banner_image'];
                                // Add images/ path if not already present or if not an absolute URL
                                if (strpos($banner, '://') === false && strpos($banner, 'images/') !== 0) {
                                    $banner = 'images/' . $banner;
                                }
                            ?>
                                <img src="<?php echo htmlspecialchars($banner); ?>" class="event-banner-thumb" alt="Banner">
                            <?php else: ?>
                                <img src="assets/default-banner.png" class="event-banner-thumb" alt="Banner">
                            <?php endif; ?>
                            <div class="event-main-content">
                                <div class="event-title-bar">
                                    <strong><?php echo htmlspecialchars($event['event_title']); ?></strong>
                                    <span class="event-badge upcoming">Upcoming</span>
                                </div>
                                <?php if (!empty($event['event_date'])): ?>
                                    <span class="event-date-row">Coming: <?php echo htmlspecialchars(date('d M Y', strtotime($event['event_date']))); ?></span>
                                <?php endif; ?>
                                <div class="event-description">
                                    <?php if (!empty($event['event_description'])) echo htmlspecialchars($event['event_description']); ?>
                                </div>
                                <div class="status-row">
                                    <span>Status: <?php echo htmlspecialchars($event['event_status']); ?></span>
                                </div>
                                <?php 
                                    if (!empty($event['event_gallery_images'])) {
                                        echo render_gallery($event['event_gallery_images']);
                                    }
                                ?>
                            </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <div class="container">
            <h2 style="color:#924cff;font-weight:800;font-size:1.35em;margin-bottom:19px;">
                <span style="vertical-align:middle;">🕰️ Past Events</span>
            </h2>
            <ul class="events-list">
                <?php if(count($past_events) == 0): ?>
                    <li style="text-align:center;opacity:.66;">No past events.</li>
                <?php else: ?>
                    <?php foreach($past_events as $event): ?>
                        <li class="event-item event-link" style="cursor:pointer;">
                            <a href="single_event.php?event_id=<?php echo (int)$event['event_id']; ?>" style="text-decoration:none;color:inherit;display:block;">
                            <?php if (!empty($event['event_banner_image'])): 
                                $banner = $event['event_banner_image'];
                                // Add images/ path if not already present or if not an absolute URL
                                if (strpos($banner, '://') === false && strpos($banner, 'images/') !== 0) {
                                    $banner = 'images/' . $banner;
                                }
                            ?>
                                <img src="<?php echo htmlspecialchars($banner); ?>" class="event-banner-thumb" alt="Banner">
                            <?php else: ?>
                                <img src="assets/default-banner.png" class="event-banner-thumb" alt="Banner">
                            <?php endif; ?>
                            <div class="event-main-content">
                                <div class="event-title-bar">
                                    <strong><?php echo htmlspecialchars($event['event_title']); ?></strong>
                                    <span class="event-badge past">Past</span>
                                </div>
                                <?php if (!empty($event['event_date'])): ?>
                                    <span class="event-date-row">Date: <?php echo htmlspecialchars(date('d M Y', strtotime($event['event_date']))); ?></span>
                                <?php endif; ?>
                                <div class="event-description">
                                    <?php if (!empty($event['event_description'])) echo htmlspecialchars($event['event_description']); ?>
                                </div>
                                <div class="status-row">
                                    <span>Status: <?php echo htmlspecialchars($event['event_status']); ?></span>
                                </div>
                                <?php 
                                    if (!empty($event['event_gallery_images'])) {
                                        echo render_gallery($event['event_gallery_images']);
                                    }
                                ?>
                            </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
<?php
include 'footer.php';
