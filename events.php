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

<!-- <link rel="stylesheet" href="css/events.css"> -->
 <style>
    .events-header-banner {
    background: linear-gradient(90deg, #ffe4e1 0%, #e0eaff 100%);
    color: #342355;
    padding: 2.5rem 0 1rem 0;
    text-align: center;
    border-radius: 0 0 2rem 2rem;
    box-shadow: 0 4px 30px #cab0ffc9;
    margin-bottom: 2.5rem;
    font-family: 'Montserrat',sans-serif;
    animation: fadeInDown 1s;
}

.events-header-banner h1 {
    font-size: 2.2rem;
    letter-spacing: 0.04em;
    font-weight: 700;
}
.events-header-banner p {
    font-size: 1.12rem;
    color: #344057;
    margin-bottom: 0;
}

.events-btn-create {
    background: linear-gradient(85deg, #e070a8 0%, #62c0f6 100%);
    color: #fff !important;
    border: none;
    border-radius: 30px;
    font-size: 1.18rem;
    font-weight:600;
    padding: .85rem 2.5rem;
    box-shadow: 0 2px 18px #c083e166;
    transition: background 0.22s, box-shadow 0.13s;
    margin-bottom: 17px;
}
.events-btn-create:hover, 
.events-btn-create:focus {
    background:linear-gradient(85deg, #c95b96 0%, #378cae 100%);
    color:#fff!important;
    box-shadow:0 5px 20px #e2c5fd82;
}
.event-section-card {
    border-radius: 1rem;
    background: #ffffffcb;
    box-shadow: 0px 10px 36px #d5e3ff54;
    margin-bottom: 3rem;
    padding: 2rem 2rem 1.5rem 2rem;
    animation: fadeInUp 0.8s;
}
.event-section-title {
    font-family: 'Montserrat',sans-serif;
    font-weight: 700;
    font-size: 1.28rem;
    letter-spacing:.6px;
    color: #425472;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 8px;
}
.events-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(340px, 1fr));
    gap: 1.45rem;
}
@media (max-width: 700px) {
    .event-section-card {
        padding: 1.05rem .7rem;
    }
    .events-header-banner h1 {
        font-size: 1.35rem;
    }
    .events-btn-create {
        padding: .55rem 1.2rem;
        font-size: 1rem;
    }
}
.event-card {
    border-radius: 0.92rem;
    overflow:hidden;
    background: #fff;
    box-shadow: 0 2px 18px #c4d7fa28;
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.2s, transform 0.13s;
    cursor: pointer;
    animation:fadeIn 0.64s;
    min-height: 100%;
    height:100%;
    position: relative;
}
.event-card:hover {
    box-shadow: 0 10px 36px #929cf050;
    transform: translateY(-6px) scale(1.018);
}
.event-card-image {
    width: 100%;
    height: 180px;
    object-fit: cover;
    background: #f3eaff;
    border-bottom: 2px solid #e0eafd;
    transition: filter 0.18s;
}
.event-card:hover .event-card-image {
    filter: brightness(1.06) saturate(1.1);
}
.event-card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 1rem 1.1rem 1.1rem 1.1rem;
}
.event-title-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 0.5rem;
}
.event-title {
    font-size: 1.28rem;
    font-weight: 700;
    color: #5e3689;
    margin-bottom: 0;
    font-family: inherit;
}
.event-badge {
    display: inline-block;
    padding: .15rem .85rem;
    font-weight: 600;
    border-radius: 12px;
    font-size: .82em;
    margin-left: 8px;
    margin-bottom: 0;
    letter-spacing: 0.3px;
}
.event-badge.ongoing {
    background: #fffadd;
    color: #d6b10e;
    border: 1px solid #fff7ae;
}
.event-badge.upcoming {
    background: #def6e2;
    color: #038436;
    border: 1px solid #b8efd3;
}
.event-badge.past {
    background: #e9ecf2;
    color: #7d8694;
    border: 1px solid #d2dae4;
}
.event-date-row, .event-status-row {
    color: #45506a;
    font-size: .94em;
    margin-bottom: .35rem;
    font-weight: 500;
}
.event-description {
    color: #505372;
    font-size: 1.02em;
    margin-bottom: 0.7rem;
    flex:1;
    min-height: 32px;
    /* line clamp */
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.event-card-footer {
    padding-top: 10px;
}
.event-gallery-slider {
    margin-top: .4rem;
}
.event-gallery-slider img {
    margin-right: 3px;
    border-radius: 0.45rem;
    border: 1.5px solid #e0d7fa;
}
/* Animation keyframes for fadeInDown and fadeInUp */
@keyframes fadeInDown {
    from { opacity: 0; transform: translateY(-20px);}
    to { opacity: 1; transform: translateY(0);}
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px);}
    to { opacity: 1; transform: translateY(0);}
}
@keyframes fadeIn {
    from { opacity: 0;}
    to { opacity: 1;}
}
 </style>
    

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
