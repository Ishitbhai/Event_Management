<?php
session_start();
require_once('header.php');
require_once('database/db_connect.php');

// Check login via session, but get user and role/type from the users table, not from session
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user info from database (get user_type/role)
$user_sql = "SELECT * FROM users WHERE user_id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
if ($user_result->num_rows < 1) {
    // No such user in DB, force log out
    session_destroy();
    header("Location: login.php");
    exit();
}
$user_row = $user_result->fetch_assoc();
$user_type = isset($user_row['user_type']) ? $user_row['user_type'] : 'user';
$user_stmt->close();

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
                    <?php echo $status_text; ?> (<?php echo $label; ?>)
                </span>
            </div>
        </a>
        <?php
    }
}
?>

<link rel="stylesheet" href="css/bookings.css">



<div class="bookings-container">
    <h2 class="bookings-title">My Event Bookings</h2>

    <div class="section-title"><span>&#128197;</span> Booked Events</div>

    <div class="events-subsection-title">&#128197; Upcoming Events</div>
    <div class="events-list">
    <?php if (count($booked_categorized['published'])): ?>
        <?php echo_booked_cards($booked_categorized['published'], "Upcoming"); ?>
    <?php else: ?>
        <div class="bookings-empty-message">No upcoming events.</div>
    <?php endif; ?>
    </div>

    <div class="events-subsection-title">&#128338; Ongoing Events</div>
    <div class="events-list">
    <?php if (count($booked_categorized['ongoing'])): ?>
        <?php echo_booked_cards($booked_categorized['ongoing'], "Ongoing"); ?>
    <?php else: ?>
        <div class="bookings-empty-message">No ongoing events.</div>
    <?php endif; ?>
    </div>

    <div class="events-subsection-title">&#9200; Completed Events</div>
    <div class="events-list">
    <?php if (count($booked_categorized['completed'])): ?>
        <?php echo_booked_cards($booked_categorized['completed'], "Completed"); ?>
    <?php else: ?>
        <div class="bookings-empty-message">No completed events.</div>
    <?php endif; ?>
    </div>

    <?php if ($user_type === 'admin' || $user_type === 'owner'): ?>

        <div class="section-title"><span>&#127881;</span> My Events (As Owner)</div>

        <div class="events-subsection-title">&#128195; Draft (Pending) Events</div>
        <div class="events-list">
        <?php if (count($owner_events['draft'])): ?>
            <?php foreach ($owner_events['draft'] as $event): ?>
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
                                <span class="event-time">
                                    &middot; <?php echo htmlspecialchars(date("g:i a", strtotime($event['event_start_time']))); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="event-title"><?php echo htmlspecialchars($event['event_title']); ?></div>
                        <div class="event-detail"><?php echo nl2br(htmlspecialchars(mb_strimwidth($event['event_description'], 0, 96, "..."))); ?></div>
                        <div class="persons-info persons-info-owner-draft">Owner (Draft/Pending)</div>
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
            <div class="owner-empty-message">You have no draft (pending) events.</div>
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

        <div class="events-subsection-title cancelled-section-title">&#10060; Cancelled Events</div>
        <div class="events-list">
        <?php if (count($owner_events['cancelled'])): ?>
            <?php foreach ($owner_events['cancelled'] as $event): ?>
                <?php
                    $banner = (!empty($event['event_banner_image'])) ? htmlspecialchars($event['event_banner_image']) : 'images/no_banner.png';
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
