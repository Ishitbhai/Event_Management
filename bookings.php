<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

// Admins can access event_requests.php, but only their own events as "owner"
// If admin (or any user), redirect directly to event_requests.php for all events (only from the main bookings page, not for owner's event links anymore)
if ($is_admin && !isset($_GET['event_id'])) {
    header("Location: event_requests.php");
    exit();
}

require_once('header.php');
require_once('database/db_connect.php');

// Fetch events where user booked, including booking status and event_status
$booked_sql = "SELECT events.*, bookings.persons, bookings.booking_status 
    FROM events 
    JOIN bookings ON events.event_id = bookings.event_id
    WHERE bookings.user_id = ?
    ORDER BY events.event_date DESC";
$stmt = $conn->prepare($booked_sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$booked_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// For bookings, NO draft or cancelled events: only published, ongoing, completed
$booked_categorized = [
    'published'  => [], // Upcoming
    'ongoing'    => [], // Ongoing
    'completed'  => []  // Completed
];
foreach ($booked_events as $event) {
    $status = isset($event['event_status']) ? strtolower($event['event_status']) : '';
    if ($status === 'cancelled' || $status === 'draft') continue; // Not for this section
    if (isset($booked_categorized[$status])) {
        $booked_categorized[$status][] = $event;
    } else {
        // Unknown status, treat as upcoming
        $booked_categorized['published'][] = $event;
    }
}

// Fetch events owned by this user (admin can view/manage their own events too)
$my_events_sql = "SELECT * FROM events WHERE owner_id = ? ORDER BY event_date DESC";
$stmt = $conn->prepare($my_events_sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$my_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// For all user-owned events, get the number of approved bookings (optional, can be used if you need total participants)
$event_ids = array_column($my_events, 'event_id');
$booked_seats = [];
if (!empty($event_ids)) {
    $placeholders = implode(',', array_fill(0, count($event_ids), '?'));
    $types = str_repeat('i', count($event_ids));
    $bookings_sql = "SELECT event_id, SUM(persons) as booked 
        FROM bookings 
        WHERE booking_status = 'approved' AND event_id IN ($placeholders) 
        GROUP BY event_id";
    $stmt = $conn->prepare($bookings_sql);
    $stmt->bind_param($types, ...$event_ids);
    $stmt->execute();
    $results = $stmt->get_result();
    while ($row = $results->fetch_assoc()) {
        $booked_seats[$row['event_id']] = intval($row['booked']);
    }
    $stmt->close();
}

// For MY EVENTS (As Owner), group all: must include draft and cancelled
$owner_events = [
    'draft'      => [],
    'published'  => [],
    'ongoing'    => [],
    'completed'  => [],
    'cancelled'  => []
];
foreach ($my_events as $event) {
    $status = isset($event['event_status']) ? strtolower($event['event_status']) : '';
    if (isset($owner_events[$status])) {
        $owner_events[$status][] = $event;
    } else {
        $owner_events['draft'][] = $event; // unknown as draft
    }
}

function echo_booked_cards($events, $label)
{
    foreach ($events as $event) {
        $status = strtolower($event['booking_status']);
        $status_text = ucfirst($status);
        $status_class = '';
        if ($status == "approved") $status_class = 'status-approved';
        elseif ($status == "pending") $status_class = 'status-pending';
        elseif ($status == "rejected") $status_class = 'status-rejected';
        else $status_class = 'status-pending';
        $banner = (!empty($event['event_banner_image'])) ? htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
        $event_url = 'single_event.php?event_id=' . urlencode($event['event_id']);
        ?>
        <a href="<?php echo $event_url; ?>" class="event-card">
            <img class="event-card-banner" src="<?php echo $banner; ?>" alt="Event Banner" loading="lazy" />
            <div class="event-content">
                <div class="event-date">
                    <?php echo htmlspecialchars(date("D, M d, Y", strtotime($event['event_date']))); ?>
                    <?php if(!empty($event['event_start_time'])): ?>
                        <span style="color:#888; font-size:0.97em;">
                            &middot;
                            <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                <div class="persons-info">Booked for: <b><?php echo intval($event['persons']); ?></b> <?php echo (intval($event['persons']) > 1) ? "persons" : "person"; ?></div>
                <span class="booking-status <?php echo $status_class; ?>">
                    <?php echo $status_text; ?> (<?php echo $label; ?>)
                </span>
            </div>
        </a>
        <?php
    }
}
?>

<style>
.bookings-container {
    max-width: 1100px;
    margin: 36px auto 36px auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 24px 0 rgba(50,50,100,0.07), 0 6px 36px 0 rgba(20,30,48,0.09);
    padding: 60px 48px 54px 48px; /* Increased padding */
    font-family: "Segoe UI", "Roboto", Arial, sans-serif;
}
.section-title {
    margin: 32px 0 22px 0;
    font-size: 1.3em;
    color: #4242a0;
    font-weight: 600;
    letter-spacing: 0.5px;
}
.events-subsection-title {
    margin: 16px 0 10px 0;
    font-size: 1.08em;
    color: #2672c8;
    font-weight: 600;
    letter-spacing: 0.3px;
}
/* Increase the gap and padding between event cards for more visual spacing */
.events-list {
    display: flex;
    flex-wrap: wrap;
    gap: 44px 40px;
    justify-content: flex-start;
    margin-bottom: 12px;
}
.event-card {
    flex: 1 1 calc(33.333% - 40px);
    max-width: calc(33.333% - 40px);
    background: #f7f8fa;
    border-radius: 9px;
    box-shadow: 0 2px 8px 0 rgba(43,43,72,0.06);
    padding: 16px 0 38px 0;
    transition: box-shadow 0.22s, top 0.22s, border 0.18s;
    cursor: pointer;
    text-decoration: none;
    color: #303048;
    position: relative;
    top:0;
    display: flex;
    flex-direction: column;
    box-sizing: border-box;
}
.event-card:hover {
    box-shadow: 0 6px 14px 0 rgba(64,64,112,0.15);
    top:-2px;
    background: #f2f3ff;
    border: 1px solid #cfdcff;
}
.event-card-banner {
    width: 95%;
    min-height: 155px;
    max-height: 185px;
    object-fit: cover;
    border-radius: 9px 9px 0 0;
    margin: 0 auto 27px auto;
    background: #eee;
    display: block;
    box-shadow: 0 1px 5px 0 rgba(30,30,50,0.03);
}
.event-content {
    flex: 1;
    padding: 32px 28px 0 28px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}
.event-date {
    color: #6363ba;
    font-size: 1.09em;
    margin-bottom: 14px;
    font-weight: 500;
}
.event-title {
    font-size: 1.22em;
    font-weight: 600;
    color: #263280;
    margin-bottom: 12px;
}
.event-detail {
    font-size: 1.03em;
    color: #646480;
    margin-bottom: 13px;
    line-height: 1.5;
}
.persons-info {
    color: #5f56a6;
    font-size: 1.025em;
    font-weight: 500;
    margin-top: 12px;
    margin-bottom: 6px;
}
.booking-status {
    display: inline-block;
    margin-top: 16px;
    font-size: 1.04em;
    font-weight: 600;
    border-radius: 8px;
    padding: 5px 14px;
}
.status-approved {
    color: #22813d;
    background: #e5fbe9;
    border: 1.5px solid #c7f4d0;
}
.status-pending {
    color: #9e8702;
    background: #fcfacf;
    border: 1.5px solid #e4d958;
}
.status-rejected {
    color: #bb2424;
    background: #ffeaea;
    border: 1.5px solid #fac0c0;
}
@media(max-width:1300px) {
    .bookings-container { max-width:98vw; }
}
@media(max-width:980px) {
    .event-card, .event-card[style] {
        flex: 1 1 48%;
        max-width: 48%;
    }
    .events-list {
        gap: 28px 18px;
    }
}
@media(max-width:900px) {
    .bookings-container { padding: 34px 5vw 26px 5vw;}
}
@media(max-width:650px) {
    .bookings-container { padding:18px 4vw 13px 4vw; }
    .events-list { gap:16px 0; }
    .event-card, .event-card[style] { flex:1 1 98vw; max-width:98vw; min-width:190px;}
    .event-card-banner { height: 96px; min-height:80px; }
    .event-content { padding:19px 7px 0 10px; }
}
</style>

<div class="bookings-container">
    <h2 style="text-align:center; color:#35356e; letter-spacing:1px;">My Event Bookings</h2>

    <div class="section-title"><span>&#128197;</span> Booked Events</div>

    <div class="events-subsection-title">&#128197; Upcoming Events</div>
    <div class="events-list">
    <?php if (count($booked_categorized['published'])): ?>
        <?php echo_booked_cards($booked_categorized['published'], "Upcoming"); ?>
    <?php else: ?>
        <div style="padding:16px 0 18px 5px;color:#999;font-size:1.01em;">No upcoming events.</div>
    <?php endif; ?>
    </div>

    <div class="events-subsection-title">&#128338; Ongoing Events</div>
    <div class="events-list">
    <?php if (count($booked_categorized['ongoing'])): ?>
        <?php echo_booked_cards($booked_categorized['ongoing'], "Ongoing"); ?>
    <?php else: ?>
        <div style="padding:16px 0 18px 5px;color:#999;font-size:1.01em;">No ongoing events.</div>
    <?php endif; ?>
    </div>

    <div class="events-subsection-title">&#9200; Completed Events</div>
    <div class="events-list">
    <?php if (count($booked_categorized['completed'])): ?>
        <?php echo_booked_cards($booked_categorized['completed'], "Completed"); ?>
    <?php else: ?>
        <div style="padding:16px 0 18px 5px;color:#999;font-size:1.01em;">No completed events.</div>
    <?php endif; ?>
    </div>

    <div class="section-title"><span>&#127881;</span> My Events (As Owner)</div>

    <div class="events-subsection-title">&#128195; Draft (Pending) Events</div>
    <div class="events-list">
    <?php if (count($owner_events['draft'])): ?>
        <?php foreach ($owner_events['draft'] as $event): ?>
            <?php
                $banner = (!empty($event['event_banner_image'])) ? htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
                // Only allow "event_requests.php" links if the user is the owner (including admin for their own events)
                $event_requests_url = 'event_requests.php?event_id=' . urlencode($event['event_id']);
                $seats = isset($event['event_seats']) ? intval($event['event_seats']) : 0;
                $available = isset($event['event_available_seats']) ? intval($event['event_available_seats']) : "-";
            ?>
            <a href="<?php echo $event_requests_url; ?>" class="event-card">
                <img class="event-card-banner" src="<?php echo $banner; ?>" alt="Event Banner" loading="lazy" />
                <div class="event-content">
                    <div class="event-date">
                        <?php echo htmlspecialchars(date("D, M d, Y", strtotime($event['event_date']))); ?>
                        <?php if(!empty($event['event_start_time'])): ?>
                            <span style="color:#888; font-size:0.97em;">
                                &middot; <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                    <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                    <div class="persons-info" style="color:#417b36; margin-bottom:7px;">Owner (Draft/Pending)</div>
                    <div class="persons-info" style="color:#2d4599; margin-bottom:5px;">
                        Available seats: 
                        <b>
                        <?php 
                            if ($available === "-" || $available === null) {
                                echo "Unlimited"; 
                            } else {
                                echo $available;
                            }
                        ?>
                        </b>
                        <?php if($seats > 0): ?>
                            <span style="color:#888; font-size:0.95em; margin-left:5px;">/ <?php echo $seats; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="padding:16px 0 18px 5px;color:#9ca;font-size:1.01em;">You have no draft (pending) events.</div>
    <?php endif; ?>
    </div>

    <div class="events-subsection-title">&#128197; Published (Upcoming) Events</div>
    <div class="events-list">
    <?php if (count($owner_events['published'])): ?>
        <?php foreach ($owner_events['published'] as $event): ?>
            <?php
                $banner = (!empty($event['event_banner_image'])) ? htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
                $event_requests_url = 'event_requests.php?event_id=' . urlencode($event['event_id']);
                $seats = isset($event['event_seats']) ? intval($event['event_seats']) : 0;
                $available = isset($event['event_available_seats']) ? intval($event['event_available_seats']) : "-";
            ?>
            <a href="<?php echo $event_requests_url; ?>" class="event-card">
                <img class="event-card-banner" src="<?php echo $banner; ?>" alt="Event Banner" loading="lazy" />
                <div class="event-content">
                    <div class="event-date">
                        <?php echo htmlspecialchars(date("D, M d, Y", strtotime($event['event_date']))); ?>
                        <?php if(!empty($event['event_start_time'])): ?>
                            <span style="color:#888; font-size:0.97em;">
                                &middot; <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                    <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                    <div class="persons-info" style="color:#417b36; margin-bottom:7px;">Owner (Published/Upcoming)</div>
                    <div class="persons-info" style="color:#2d4599; margin-bottom:5px;">
                        Available seats: 
                        <b>
                        <?php 
                            if ($available === "-" || $available === null) {
                                echo "Unlimited"; 
                            } else {
                                echo $available;
                            }
                        ?>
                        </b>
                        <?php if($seats > 0): ?>
                            <span style="color:#888; font-size:0.95em; margin-left:5px;">/ <?php echo $seats; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="padding:16px 0 18px 5px;color:#9ca;font-size:1.01em;">You have no published (upcoming) events yet.</div>
    <?php endif; ?>
    </div>

    <div class="events-subsection-title">&#128338; Ongoing Events</div>
    <div class="events-list">
    <?php if (count($owner_events['ongoing'])): ?>
        <?php foreach ($owner_events['ongoing'] as $event): ?>
            <?php
                $banner = (!empty($event['event_banner_image'])) ? htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
                $event_requests_url = 'event_requests.php?event_id=' . urlencode($event['event_id']);
                $seats = isset($event['event_seats']) ? intval($event['event_seats']) : 0;
                $available = isset($event['event_available_seats']) ? intval($event['event_available_seats']) : "-";
            ?>
            <a href="<?php echo $event_requests_url; ?>" class="event-card">
                <img class="event-card-banner" src="<?php echo $banner; ?>" alt="Event Banner" loading="lazy" />
                <div class="event-content">
                    <div class="event-date">
                        <?php echo htmlspecialchars(date("D, M d, Y", strtotime($event['event_date']))); ?>
                        <?php if(!empty($event['event_start_time'])): ?>
                            <span style="color:#888; font-size:0.97em;">
                                &middot; <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                    <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                    <div class="persons-info" style="color:#417b36; margin-bottom:7px;">Owner (Ongoing)</div>
                    <div class="persons-info" style="color:#2d4599; margin-bottom:5px;">
                        Available seats: 
                        <b>
                        <?php 
                            if ($available === "-" || $available === null) {
                                echo "Unlimited"; 
                            } else {
                                echo $available;
                            }
                        ?>
                        </b>
                        <?php if($seats > 0): ?>
                            <span style="color:#888; font-size:0.95em; margin-left:5px;">/ <?php echo $seats; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="padding:16px 0 18px 5px;color:#9ca;font-size:1.01em;">You have no ongoing events.</div>
    <?php endif; ?>
    </div>

    <div class="events-subsection-title">&#9200; Completed Events</div>
    <div class="events-list">
    <?php if (count($owner_events['completed'])): ?>
        <?php foreach ($owner_events['completed'] as $event): ?>
            <?php
                $banner = (!empty($event['event_banner_image'])) ? htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
                $event_requests_url = 'event_requests.php?event_id=' . urlencode($event['event_id']);
                $seats = isset($event['event_seats']) ? intval($event['event_seats']) : 0;
                $available = isset($event['event_available_seats']) ? intval($event['event_available_seats']) : "-";
            ?>
            <a href="<?php echo $event_requests_url; ?>" class="event-card">
                <img class="event-card-banner" src="<?php echo $banner; ?>" alt="Event Banner" loading="lazy" />
                <div class="event-content">
                    <div class="event-date">
                        <?php echo htmlspecialchars(date("D, M d, Y", strtotime($event['event_date']))); ?>
                        <?php if(!empty($event['event_start_time'])): ?>
                            <span style="color:#888; font-size:0.97em;">
                                &middot; <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                    <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                    <div class="persons-info" style="color:#417b36; margin-bottom:7px;">Owner (Completed)</div>
                    <div class="persons-info" style="color:#2d4599; margin-bottom:5px;">
                        Available seats: 
                        <b>
                        <?php 
                            if ($available === "-" || $available === null) {
                                echo "Unlimited"; 
                            } else {
                                echo $available;
                            }
                        ?>
                        </b>
                        <?php if($seats > 0): ?>
                            <span style="color:#888; font-size:0.95em; margin-left:5px;">/ <?php echo $seats; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="padding:16px 0 18px 5px;color:#9ca;font-size:1.01em;">You have not completed any events yet.</div>
    <?php endif; ?>
    </div>

    <div class="events-subsection-title" style="color:#c25;">&#10060; Cancelled Events</div>
    <div class="events-list">
    <?php if (count($owner_events['cancelled'])): ?>
        <?php foreach ($owner_events['cancelled'] as $event): ?>
            <?php
                $banner = (!empty($event['event_banner_image'])) ? htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
                $event_requests_url = 'event_requests.php?event_id=' . urlencode($event['event_id']);
                $seats = isset($event['event_seats']) ? intval($event['event_seats']) : 0;
                $available = isset($event['event_available_seats']) ? intval($event['event_available_seats']) : "-";
            ?>
            <a href="<?php echo $event_requests_url; ?>" class="event-card" style="border:1.5px solid #f66;background:#fff7f7;">
                <img class="event-card-banner" src="<?php echo $banner; ?>" alt="Event Banner" loading="lazy" />
                <div class="event-content">
                    <div class="event-date">
                        <?php echo htmlspecialchars(date("D, M d, Y", strtotime($event['event_date']))); ?>
                        <?php if(!empty($event['event_start_time'])): ?>
                            <span style="color:#888; font-size:0.97em;">
                                &middot; <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="event-title" style="color:#be2929;"><?php echo htmlspecialchars($event['event_title']); ?></div>
                    <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                    <div class="persons-info" style="color:#9e2323; margin-bottom:9px;">This event was cancelled.</div>
                    <div class="persons-info" style="color:#2d4599; margin-bottom:5px;">
                        Available seats: 
                        <b>
                        <?php 
                            if ($available === "-" || $available === null) {
                                echo "Unlimited"; 
                            } else {
                                echo $available;
                            }
                        ?>
                        </b>
                        <?php if($seats > 0): ?>
                            <span style="color:#888; font-size:0.95em; margin-left:5px;">/ <?php echo $seats; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="padding:16px 0 18px 5px;color:#db7a7a;font-size:1.01em;">You have no cancelled events.</div>
    <?php endif; ?>
    </div>
</div>

<?php require_once('footer.php'); ?>
