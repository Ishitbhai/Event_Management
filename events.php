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
    $out = '<div class="d-flex flex-nowrap overflow-auto gap-2 mt-2 animate__animated animate__fadeIn">';
    foreach ($images as $img) {
        $src = trim($img);
        if (!$src) continue;
        if (strpos($src, '://') === false && strpos($src, 'images/') !== 0) {
            $src = 'images/' . $src;
        }
        $out .= '<img src="' . htmlspecialchars($src) . '" class="rounded gallery-img border shadow-sm" style="width: 60px; height: 60px; object-fit: cover;" alt="Gallery Image">';
    }
    $out .= '</div>';
    return $out;
}
?>

<link rel="stylesheet" href="bootstrap/css/animate.min.css"/>

<link rel="stylesheet" href="css/events.css">
    

<div class="container-fluid px-0 events-header-banner shadow animate__animated animate__fadeInDown">
    <h1 class="mb-2 animate__fadeInDown animate__animated">🎉 Events at Aone Hub</h1>
    <p class="animate__fadeIn animate__animated">Discover what's happening, remember remarkable past events, and see what's coming up</p>
</div>
<div class="container my-3">
    <div class="d-flex justify-content-end mb-4">
        <a href="create_event.php" class="btn events-btn-create shadow animate__animated animate__bounceIn"><i class="bi bi-plus-circle"></i>  Create Event</a>
    </div>

    <!-- Section: Ongoing & Upcoming -->
    <div class="event-section-card mb-5 animate__animated animate__fadeInUp">
        <div class="event-section-title border-bottom pb-2 mb-4">
            <span>🟢 Ongoing &amp; Upcoming Events</span>
        </div>
        <div class="events-card-grid">
        <?php 
            $has_events = (count($ongoing_events) > 0 || count($upcoming_events) > 0);
            if(!$has_events): ?>
                <div class="text-center text-secondary py-5 w-100 animate__animated animate__fadeIn">
                    <span class="fs-5"><i class="bi bi-calendar-x"></i> No ongoing or upcoming events.</span>
                </div>
            <?php else: ?>
                <?php 
                foreach([...$ongoing_events, ...$upcoming_events] as $event): 
                    $badge = '';
                    $badgeClass = '';
                    if (strtolower($event['event_status'])==='ongoing') {
                        $badge = 'Ongoing';
                        $badgeClass = 'ongoing';
                        $dateRow = !empty($event['event_date']) ? '<span class="event-date-row">Started: '.htmlspecialchars(date('d M Y', strtotime($event['event_date']))).'</span>' : '';
                    } else {
                        $badge = 'Upcoming';
                        $badgeClass = 'upcoming';
                        $dateRow = !empty($event['event_date']) ? '<span class="event-date-row">Coming: '.htmlspecialchars(date('d M Y', strtotime($event['event_date']))).'</span>' : '';
                    }
                    ?>
                    <a href="single_event.php?event_id=<?php echo (int)$event['event_id']; ?>" class="text-decoration-none text-dark event-card animate__animated animate__fadeIn" tabindex="0">
                        <img src="<?php
                                if (!empty($event['event_banner_image'])) {
                                    $banner = $event['event_banner_image'];
                                    if (strpos($banner, '://') === false && strpos($banner, 'images/') !== 0) {
                                        $banner = 'images/' . $banner;
                                    }
                                    echo htmlspecialchars($banner);
                                } else {
                                    echo "assets/default-banner.png";
                                };
                            ?>"
                            class="event-card-image" alt="Banner">
                        <div class="event-card-body">
                            <div class="event-title-row">
                                <span class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></span>
                                <span class="event-badge <?php echo $badgeClass; ?>"><?php echo $badge; ?></span>
                            </div>
                            <?php echo $dateRow; ?>
                            <div class="event-description"><?php echo !empty($event['event_description']) ? htmlspecialchars($event['event_description']) : ''; ?></div>
                            <div class="event-status-row mb-1">
                                <span><strong>Status:</strong> <?php echo htmlspecialchars($event['event_status']); ?></span>
                            </div>
                            <div class="event-card-footer">
                                <?php 
                                    if (!empty($event['event_gallery_images'])) {
                                        echo render_gallery($event['event_gallery_images']);
                                    }
                                ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section: Past Events -->
    <div class="event-section-card animate__animated animate__fadeInUp">
        <div class="event-section-title border-bottom pb-2 mb-4">
            <span>🕰️ Past Events</span>
        </div>
        <div class="events-card-grid">
        <?php if(count($past_events) == 0): ?>
            <div class="text-center text-secondary py-5 w-100 animate__animated animate__fadeIn">
                <span class="fs-5"><i class="bi bi-emoji-frown"></i> No past events.</span>
            </div>
        <?php else: ?>
            <?php foreach($past_events as $event): ?>
                <a href="single_event.php?event_id=<?php echo (int)$event['event_id']; ?>" class="text-decoration-none text-dark event-card animate__animated animate__fadeIn" tabindex="0">
                    <img src="<?php
                            if (!empty($event['event_banner_image'])) {
                                $banner = $event['event_banner_image'];
                                if (strpos($banner, '://') === false && strpos($banner, 'images/') !== 0) {
                                    $banner = 'images/' . $banner;
                                }
                                echo htmlspecialchars($banner);
                            } else {
                                echo "assets/default-banner.png";
                            };
                        ?>"
                        class="event-card-image" alt="Banner">
                    <div class="event-card-body">
                        <div class="event-title-row">
                            <span class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></span>
                            <span class="event-badge past">Past</span>
                        </div>
                        <?php if (!empty($event['event_date'])): ?>
                            <span class="event-date-row">Date: <?php echo htmlspecialchars(date('d M Y', strtotime($event['event_date']))); ?></span>
                        <?php endif; ?>
                        <div class="event-description"><?php echo !empty($event['event_description']) ? htmlspecialchars($event['event_description']) : ''; ?></div>
                        <div class="event-status-row mb-1">
                            <span><strong>Status:</strong> <?php echo htmlspecialchars($event['event_status']); ?></span>
                        </div>
                        <div class="event-card-footer">
                            <?php
                                if (!empty($event['event_gallery_images'])) {
                                    echo render_gallery($event['event_gallery_images']);
                                }
                            ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</div>
<link rel="stylesheet" href="bootstrap/css/bootstrap-icons.css" />

<?php
include 'footer.php';
