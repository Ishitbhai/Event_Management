<?php
session_start();
require_once('header.php');
require_once('database/db_connect.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle cancellation for events in draft or pending only
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['cancel_event_id']) &&
    is_numeric($_POST['cancel_event_id']) &&
    isset($_POST['cancel_owner_event'])
) {
    $cancel_event_id = intval($_POST['cancel_event_id']);

    $check_sql = "SELECT * FROM events WHERE event_id = ? AND owner_id = ? AND (event_status = 'draft' OR event_status = 'pending')";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('ii', $cancel_event_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $update_sql = "UPDATE events SET event_approval_status = 'rejected', event_status = 'cancelled' WHERE event_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('i', $cancel_event_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
    $check_stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$user_sql = "SELECT * FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_result->num_rows < 1) {
    session_destroy();
    header("Location: login.php");
    exit();
}
$user_row = $user_result->fetch_assoc();
$user_type = isset($user_row['user_type']) ? $user_row['user_type'] : 'user';
$user_stmt->close();

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

$booked_categorized = [
    'published'  => [],
    'ongoing'    => [],
    'completed'  => []
];
foreach ($booked_events as $event) {
    $status = isset($event['event_status']) ? strtolower($event['event_status']) : '';
    if ($status === 'cancelled' || $status === 'draft') continue;
    if (isset($booked_categorized[$status])) {
        $booked_categorized[$status][] = $event;
    } else {
        $booked_categorized['published'][] = $event;
    }
}

$my_events_sql = "SELECT * FROM events WHERE owner_id = ? ORDER BY event_date DESC";
$stmt = $conn->prepare($my_events_sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$my_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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

$owner_events = [
    'draft'      => [],
    'pending'    => [],
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
        $owner_events['draft'][] = $event;
    }
}

// NO LABEL echo_booked_cards
function echo_booked_cards($events)
{
    foreach ($events as $event) {
        $status = strtolower($event['booking_status']);
        $status_text = ucfirst($status);
        $status_class = '';
        if ($status == "approved") $status_class = 'status-approved';
        elseif ($status == "pending") $status_class = 'status-pending';
        elseif ($status == "rejected") $status_class = 'status-rejected';
        else $status_class = 'status-pending';
        $banner = (!empty($event['event_banner_image'])) ? 'images/' . htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
        $event_url = 'single_event.php?event_id=' . urlencode($event['event_id']);
        ?>
        <a href="<?php echo $event_url; ?>" class="event-card">
            <img class="event-card-banner" src="<?php echo $banner; ?>" alt="Event Banner" loading="lazy" />
            <div class="event-content">
                <div class="event-date">
                    <?php echo htmlspecialchars(date("D, M d, Y", strtotime($event['event_date']))); ?>
                    <?php if(!empty($event['event_start_time'])): ?>
                        <span class="event-time">
                            &middot;
                            <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                <div class="persons-info">Booked for: <b><?php echo intval($event['persons']); ?></b> <?php echo (intval($event['persons']) > 1) ? "persons" : "person"; ?></div>
                <span class="booking-status <?php echo $status_class; ?>">
                    <?php echo $status_text; ?>
                </span>
            </div>
        </a>
        <?php
    }
}
?>

<!-- <link rel="stylesheet" href="css/bookings.css"> -->

<style>
    @keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px);}
    to   { opacity: 1; transform: translateY(0);}
}
@keyframes subtleShadow {
    0%   { box-shadow: 0 2px 8px #ede9e9; }
    70%  { box-shadow: 0 16px 16px #ecebeb44; }
    100% { box-shadow: 0 2px 8px #ede9e9; }
}

:root {
    --cls-bg:         #fcfcfc;
    --cls-card:       #f9f9f9;
    --cls-contrast:   #e7e7e9;
    --cls-border:     #d1d1d7;
    --cls-head-text:  #24242d;
    --cls-sub-text:   #343440;
    --cls-muted:      #7b7e84;
    --cls-date:       #434350;
    --cls-title:      #2a2a2e;
    --cls-separator:  #ececec;
    --cls-owner:      #353541;
    --cls-error:      #992a2a;
    --cls-ok:         #227037;
    --cls-pending:    #876f01;
    --cls-reject:     #a82828;
    --cls-light:      #fff;
}

.bookings-container {
    max-width: 1600px;      
    margin: 40px auto;
    background: var(--cls-bg);
    border-radius: 18px;
    box-shadow: 0 2px 28px 0 #e6e5e3b0;
    padding: 58px 34px 50px 34px;
    font-family: "Segoe UI", "Roboto", Arial, sans-serif;
    animation: fadeIn 800ms cubic-bezier(.31,1.12,.43,1.05) 1;
    transition: padding .2s, box-shadow .28s;
}

.section-title {
    margin: 32px 0 22px 0;
    font-size: 1.43em;
    color: var(--cls-head-text);
    font-family: "Segoe UI", Arial, sans-serif;
    font-weight: 700;
    text-align: left; 
    letter-spacing: 0.7px;
    border-bottom: 1.5px solid var(--cls-separator);
    padding-bottom: 8px;
    background: none;
}

.section-title span {
    margin-right: 8px;
    font-size: 1.09em;
}

.events-subsection-title {
    margin: 16px 0 10px 0;
    font-size: 1.08em;
    color: var(--cls-muted);
    font-weight: 600;
    letter-spacing: 0.3px;
    border-left: 4px solid var(--cls-separator);
    padding-left: 12px;
    background: #fff;
    text-align: left; 
}

.cancelled-section-title {
    color: var(--cls-error);
    border-left: 4px solid var(--cls-error);
    background: #faf3f6;
}

.events-list {
    display: flex;
    flex-wrap: wrap;
    gap: 30px 25px;
    justify-content: flex-start;
    align-items: stretch;
    margin-bottom: 16px;
    width: 100%;
    transition: gap .18s;
}

.event-card,
.event-card[style] {
    flex: 1 1 320px;
    min-width: 220px;
    max-width: 370px;
    background: var(--cls-card);
    border-radius: 13px;
    padding: 11px 0 29px 0;
    margin-left: 0;   
    margin-right: 32px;  
    box-sizing: border-box;
    border: 1.6px solid var(--cls-border);
    box-shadow: 0 3px 16px #eee 0, 0 2px 16px #eaeaea03;
    cursor: pointer;
    text-decoration: none;
    color: var(--cls-sub-text);
    position: relative;
    display: flex;
    flex-direction: column;
    transition: box-shadow 0.22s, border .18s, background .16s, transform .14s;
    animation: fadeIn 700ms cubic-bezier(.41,1.04,.37,1.07) 1;
}
.events-list > .event-card:last-child,
.events-list > .event-card[style]:last-child {
    margin-right: 0 !important;
}
@media (max-width: 1200px) {
    .bookings-container { max-width:98vw; }
}
@media (max-width: 900px) {
    .bookings-container { padding: 24px 6vw 18px 6vw;}
    .events-list { gap:18px 0;}
    .event-card, .event-card[style] {
        flex: 1 1 98vw;
        max-width: 98vw;
        min-width: 170px;
        padding:9px 0 12px 0;
        margin-bottom: 9px;
        margin-right: 0 !important;
    }
    .event-card-banner { min-height: 62px; height:77px;}
    .event-content { padding: 11px 7px 0 9px; }
}
@media (max-width: 640px) {
    .bookings-container { padding: 5vw 2vw 2vw 2vw;}
    .bookings-title { font-size:1.1em;}
    .section-title { font-size: 1.02em;}
    .events-list { flex-direction:column; gap:11px 0;}
    .event-card, .event-card[style] {
        min-width:95vw; max-width:99vw;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    .event-title, .event-title-cancelled {font-size:.93em;}
}
@media (max-width: 465px) {
    .bookings-container { padding: 2vw 1vw 1vw 1vw;}
}
.event-card-banner {
    width: 96%;
    min-height: 110px;
    max-height: 180px;
    object-fit: cover;
    border-radius: 11px 11px 0 0;
    margin: 0 auto 19px auto;
    background: #eaeaea;
    display: block;
    filter: grayscale(8%);
    opacity: 0.92;
}
.event-card:hover .event-card-banner {
    filter: grayscale(5%) brightness(1.05);
    opacity: 1;
    transition: filter .11s, opacity .18s;
}

.event-content {
    flex: 1;
    padding: 25px 17px 0 17px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    border-radius: 0 0 10px 10px;
    transition: background 0.18s;
    background: none;
}

.event-date {
    color: var(--cls-date);
    font-size: 1.04em;
    margin-bottom: 11px;
    font-weight: 500;
    letter-spacing: 0.02em;
    display:flex; align-items:center; gap:.48em;
    font-family: 'Segoe UI', Arial, sans-serif;
}
.event-date .event-time {
    color: var(--cls-muted);
    font-size: 0.93em;
    margin-left: .2em;
}
.event-title,
.event-title-cancelled {
    font-size: 1.19em;
    font-weight: 600;
    color: var(--cls-title);
    margin-bottom: 7px;
        e-height: 1.21em;
    letter-spacing: 0.01em;
    font-family: 'Georgia', 'Segoe UI', Arial, sans-serif;
}
.event-title-cancelled { color: var(--cls-error);}
.event-detail {
    font-size: 1em;
    color: var(--cls-owner);
    line-height: 1.44;
    min-height: 22px;
    margin-bottom: 9px;
    padding: 2px 5px;
    border-left: 2px solid var(--cls-separator);
}

.persons-info {
    color: var(--cls-muted);
    font-size: 0.97em;
    font-weight: 500;
    margin-top: 9px;
    margin-bottom: 4px;
    letter-spacing: .01em;
}

.persons-info-owner-draft,
.persons-info-owner-published,
.persons-info-owner-ongoing,
.persons-info-owner-completed {
    color: var(--cls-owner);
    margin-bottom: 5px;
    font-weight: 600;
    letter-spacing: 0.01em;
}
.persons-info-owner-cancelled {
    color: var(--cls-error);
    margin-bottom: 5px;
    font-weight: 600;
}

.persons-info-available-seats {
    color: var(--cls-date);
    margin-bottom: 3px;
    font-size: 0.98em;
    font-weight: 500;
}
.persons-info-available-seats .total-seats {
    color: var(--cls-muted);
    font-size:0.95em;
    margin-left:5px;
    font-weight:400;
}

.booking-status {
    display: inline-block;
    margin-top: 13px;
    font-size: 0.96em;
    font-weight: 700;
    border-radius: 7px;
    padding: 4px 12px;
    border: 1.2px solid var(--cls-border);
    background: #fbfbfb;
    color: var(--cls-muted);
    letter-spacing:0.04em;
    animation: fadeIn 1.7s;
}
.status-approved {
    color: var(--cls-ok);
    border-color: #c8e7d1;
    background: #f8fcfa;
}
.status-pending {
    color: var(--cls-pending);
    border-color: #f1eac7;
    background: #fefdec;
}
.status-rejected {
    color: var(--cls-reject);
    border-color: #ead0d0;
    background: #fdf5f5;
}

.bookings-title {
    text-align:left;
    color:var(--cls-title);
    letter-spacing:1px;
    margin-bottom: 2em;
    font-style:italic;
    font-size:1.63em;
    font-weight:500;
    background: none;
}

.bookings-empty-message,
.owner-empty-message,
.cancelled-empty-message {
    padding:15px 0 13px 5px;
    font-size:1em;
    color: var(--cls-muted);
    border-left: 4px solid var(--cls-border);
    background: #f7f7f7;
    border-radius: 5px;
    margin: 0 0 8px 0;
    font-weight:400;
    animation: fadeIn 1.4s;
    text-align:left;
}
.bookings-empty-message { color: var(--cls-muted);}
.owner-empty-message   { color: #42524a;}
.cancelled-empty-message { color: var(--cls-error); border-left: 4px solid var(--cls-error); background:#faf4f4;}

.owner-cancel-btn {
    padding:.37em .92em;
    background: var(--cls-error);
    color: #fff;
    border:none;
    border-radius:4px;
    cursor:pointer;
    font-size: 0.99em;
    font-weight:500;
    margin-top: 4px;
    letter-spacing:0.01em;
    box-shadow: 0 2px 7px #e0bcbc18;
    transition: background 0.15s, box-shadow 0.18s, transform 0.18s;
}
.owner-cancel-btn:hover, .owner-cancel-btn:focus {
    background: #4e1616;
    box-shadow: 0 4px 14px #e0bcbc3a;
    transform: translateY(-2px) scale(1.04);
}

::-webkit-scrollbar {
    width: 8px;
    background: var(--cls-bg);
}
::-webkit-scrollbar-thumb {
    background: #e5e5ec;
    border-radius: 4px;
}

</style>
<div class="bookings-container">
    <h2 class="bookings-title">My Event Bookings</h2>

    <div class="section-title"><span>&#128197;</span> Booked Events</div>

    <div class="events-subsection-title">&#128197; Upcoming Events</div>
    <div class="events-list">
    <?php if (count($booked_categorized['published'])): ?>
        <?php echo_booked_cards($booked_categorized['published']); ?>
    <?php else: ?>
        <div class="bookings-empty-message">No upcoming events.</div>
    <?php endif; ?>
    </div>

    <div class="events-subsection-title">&#128338; Ongoing Events</div>
    <div class="events-list">
    <?php if (count($booked_categorized['ongoing'])): ?>
        <?php echo_booked_cards($booked_categorized['ongoing']); ?>
    <?php else: ?>
        <div class="bookings-empty-message">No ongoing events.</div>
    <?php endif; ?>
    </div>

    <div class="events-subsection-title">&#9200; Completed Events</div>
    <div class="events-list">
    <?php if (count($booked_categorized['completed'])): ?>
        <?php echo_booked_cards($booked_categorized['completed']); ?>
    <?php else: ?>
        <div class="bookings-empty-message">No completed events.</div>
    <?php endif; ?>
    </div>

    <?php if ($user_type === 'admin' || $user_type === 'owner'): ?>

        <div class="section-title"><span>&#127881;</span> My Events (As Owner)</div>

        <!-- DRAFT AND PENDING EVENTS: Cancel Button Provided -->
        <div class="events-subsection-title">&#128195; Draft (Pending) Events</div>
        <div class="events-list">
        <?php 
            $cancelable_events = array_merge($owner_events['draft'], $owner_events['pending']);
        ?>
        <?php if (count($cancelable_events)): ?>
            <?php foreach ($cancelable_events as $event): ?>
                <?php
                    $banner = (!empty($event['event_banner_image'])) ? 'images/' . htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
                    $event_requests_url = 'event_requests.php?event_id=' . urlencode($event['event_id']);
                    $seats = isset($event['event_seats']) ? intval($event['event_seats']) : 0;
                    $available = isset($event['event_available_seats']) ? intval($event['event_available_seats']) : "-";
                    $event_status_disp = ucfirst(htmlspecialchars($event['event_status']));
                ?>
                <a href="<?php echo $event_requests_url; ?>" class="event-card">
                    <img class="event-card-banner" src="<?php echo $banner; ?>" alt="Event Banner" loading="lazy" />
                    <div class="event-content">
                        <div class="event-date">
                            <?php echo htmlspecialchars(date("D, M d, Y", strtotime($event['event_date']))); ?>
                            <?php if(!empty($event['event_start_time'])): ?>
                                <span class="event-time">
                                    &middot; <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                        <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                        <div class="persons-info persons-info-owner-draft">
                            Owner (<?php echo $event_status_disp === 'Draft' ? 'Draft' : 'Pending'; ?>)
                        </div>
                        <div class="persons-info persons-info-available-seats">
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
                                <span class="total-seats">/ <?php echo $seats; ?></span>
                            <?php endif; ?>
                        </div>
                        <!-- Cancel Button -->
                        <form method="post" style="margin-top:10px;text-align:right;">
                            <input type="hidden" name="cancel_event_id" value="<?php echo intval($event['event_id']); ?>">
                            <input type="hidden" name="cancel_owner_event" value="1">
                            <button type="submit" onclick="return confirm('Are you sure you want to cancel this event? This action cannot be undone.')" class="owner-cancel-btn">Cancel Event</button>
                        </form>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="owner-empty-message">You have no draft (pending) events.</div>
        <?php endif; ?>
        </div>

        <!-- PUBLISHED EVENTS (NO CANCEL) -->
        <div class="events-subsection-title">&#128197; Published (Upcoming) Events</div>
        <div class="events-list">
        <?php if (count($owner_events['published'])): ?>
            <?php foreach ($owner_events['published'] as $event): ?>
                <?php
                    $banner = (!empty($event['event_banner_image'])) ? 'images/' . htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
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
                                <span class="event-time">
                                    &middot; <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                        <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                        <div class="persons-info persons-info-owner-published">Owner (Published/Upcoming)</div>
                        <div class="persons-info persons-info-available-seats">
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
                                <span class="total-seats">/ <?php echo $seats; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="owner-empty-message">You have no published (upcoming) events yet.</div>
        <?php endif; ?>
        </div>

        <!-- ONGOING EVENTS (NO CANCEL) -->
        <div class="events-subsection-title">&#128338; Ongoing Events</div>
        <div class="events-list">
        <?php if (count($owner_events['ongoing'])): ?>
            <?php foreach ($owner_events['ongoing'] as $event): ?>
                <?php
                    $banner = (!empty($event['event_banner_image'])) ? 'images/' . htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
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
                                <span class="event-time">
                                    &middot; <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                        <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                        <div class="persons-info persons-info-owner-ongoing">Owner (Ongoing)</div>
                        <div class="persons-info persons-info-available-seats">
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
                                <span class="total-seats">/ <?php echo $seats; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="owner-empty-message">You have no ongoing events.</div>
        <?php endif; ?>
        </div>

        <!-- COMPLETED EVENTS (NO CANCEL) -->
        <div class="events-subsection-title">&#9200; Completed Events</div>
        <div class="events-list">
        <?php if (count($owner_events['completed'])): ?>
            <?php foreach ($owner_events['completed'] as $event): ?>
                <?php
                    $banner = (!empty($event['event_banner_image'])) ? 'images/' . htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
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
                                <span class="event-time">
                                    &middot; <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                        <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                        <div class="persons-info persons-info-owner-completed">Owner (Completed)</div>
                        <div class="persons-info persons-info-available-seats">
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
                                <span class="total-seats">/ <?php echo $seats; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="owner-empty-message">You have not completed any events yet.</div>
        <?php endif; ?>
        </div>

        <!-- CANCELLED EVENTS (NO CANCEL) -->
        <div class="events-subsection-title cancelled-section-title">&#10060; Cancelled Events</div>
        <div class="events-list">
        <?php if (count($owner_events['cancelled'])): ?>
            <?php foreach ($owner_events['cancelled'] as $event): ?>
                <?php
                    $banner = (!empty($event['event_banner_image'])) ? 'images/' . htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
                    $event_requests_url = 'event_requests.php?event_id=' . urlencode($event['event_id']);
                    $seats = isset($event['event_seats']) ? intval($event['event_seats']) : 0;
                    $available = isset($event['event_available_seats']) ? intval($event['event_available_seats']) : "-";
                ?>
                <a href="<?php echo $event_requests_url; ?>" class="event-card event-card-cancelled">
                    <img class="event-card-banner" src="<?php echo $banner; ?>" alt="Event Banner" loading="lazy" />
                    <div class="event-content">
                        <div class="event-date">
                            <?php echo htmlspecialchars(date("D, M d, Y", strtotime($event['event_date']))); ?>
                            <?php if(!empty($event['event_start_time'])): ?>
                                <span class="event-time">
                                    &middot; <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="event-title event-title-cancelled"><?php echo htmlspecialchars($event['event_title']); ?></div>
                        <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                        <div class="persons-info persons-info-owner-cancelled">This event was cancelled.</div>
                        <div class="persons-info persons-info-available-seats">
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
                                <span class="total-seats">/ <?php echo $seats; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="cancelled-empty-message">You have no cancelled events.</div>
        <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once('footer.php'); ?>
