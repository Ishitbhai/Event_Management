<?php
session_start();
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
?>
<?php
require_once('sidebar.php');
require_once('../database/db_connect.php');

// Fetch booking_id from query parameter
$book_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$error = "";
$success = "";

// Fetch all users for dropdown
$users = [];
$user_result = $conn->query("SELECT user_id, user_name, user_email FROM users");
if ($user_result) {
    while ($row = $user_result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Fetch events and available_seats for dropdown and max calculation
$events = [];
$event_result = $conn->query("SELECT event_id, event_available_seats FROM events");
if ($event_result) {
    while ($row = $event_result->fetch_assoc()) {
        $events[$row['event_id']] = $row['event_available_seats'];
    }
}

// Fetch booking details
$booking = null;
if ($book_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE book_id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $booking_result = $stmt->get_result();
    if ($booking_result && $booking_result->num_rows > 0) {
        $booking = $booking_result->fetch_assoc();
    }
    $stmt->close();
}

if (!$booking) {
    $error = "Booking not found.";
}

// Booking status options
$booking_status_options = ['pending', 'approved', 'rejected'];

// For current event's max, we want available_seats + the persons currently booked if booking is still approved on this event
$persons_approved = ($booking && $booking['event_id'] && $booking['booking_status'] === 'approved') ? (int)$booking['persons'] : 0;
$event_id_current = ($booking) ? (int)$booking['event_id'] : 0;
$event_max = (isset($events[$event_id_current]) ? ($events[$event_id_current] + $persons_approved) : 1);
$event_max = max(1, $event_max);

// Handle Form POST (Update booking)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $booking) {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $persons_new = isset($_POST['persons']) ? intval($_POST['persons']) : 0;
    $booking_status_new = isset($_POST['booking_status']) ? $_POST['booking_status'] : 'pending';
    $persons_old = intval($booking['persons']);
    $event_id_old = intval($booking['event_id']);
    $booking_status_old = $booking['booking_status'];

    // For validation: calculate the event's new max allowed for this particular edit
    $target_event_avail = 0;
    if (isset($events[$event_id])) {
        $target_event_avail = (int)$events[$event_id];
    }

    // If this booking is approved and it's the same event, add in the existing persons to max for validation
    $max_allowed = 1;
    if ($event_id === $event_id_old && $booking_status_old === 'approved') {
        $max_allowed = $target_event_avail + $persons_old;
    } else {
        $max_allowed = $target_event_avail;
    }
    $max_allowed = max(1, $max_allowed);

    // Validation
    if ($user_id <= 0) {
        $error = "Invalid user selected.";
    } elseif ($event_id <= 0 || !isset($events[$event_id])) {
        $error = "Invalid event selected.";
    } elseif ($persons_new < 1) {
        $error = "Persons must be at least 1.";
    } elseif (!in_array($booking_status_new, $booking_status_options)) {
        $error = "Invalid booking status.";
    } elseif ($persons_new > $max_allowed) {
        $error = "Persons must not be greater than the maximum allowed ($max_allowed) for this event.";
    } else {
        // Always fetch up-to-date available seats for chosen event (excluding this booking if still approved)
        $event_avail_seats = 0;
        $cur_seats_query = $conn->prepare("SELECT event_available_seats FROM events WHERE event_id=?");
        $cur_seats_query->bind_param("i", $event_id);
        $cur_seats_query->execute();
        $cur_seats_query->bind_result($event_avail_seats);
        $cur_seats_query->fetch();
        $cur_seats_query->close();

        $can_update = true;

        // If approved, persons_new must not be greater than available seats
        if ($booking_status_new === 'approved' && $persons_new > $event_avail_seats && !($event_id === $event_id_old && $booking_status_old === 'approved' && $persons_new <= ($event_avail_seats + $persons_old))) {
            $can_update = false;
            $error = "Cannot approve: number of persons is greater than available seats in event ($event_avail_seats).";
        } else {
            if ($event_id != $event_id_old) {
                // Changing event: Must restore seats in old event (if old status approved), subtract from new event (if now approved)
                if ($booking_status_old === 'approved') {
                    // Restore to old event
                    $stmt = $conn->prepare("UPDATE events SET event_available_seats = event_available_seats + ? WHERE event_id = ?");
                    $stmt->bind_param("ii", $persons_old, $event_id_old);
                    $stmt->execute();
                    $stmt->close();
                }
                if ($booking_status_new === 'approved') {
                    // Check available seats in new event
                    if ($persons_new > $event_avail_seats) {
                        $can_update = false;
                        $error = "Cannot book more persons than available seats in the selected event.";
                    } else {
                        $stmt = $conn->prepare("UPDATE events SET event_available_seats = event_available_seats - ? WHERE event_id = ? AND event_available_seats >= ?");
                        $stmt->bind_param("iii", $persons_new, $event_id, $persons_new);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            } else { // same event
                if ($booking_status_old === 'approved' && $booking_status_new === 'approved') {
                    $diff = $persons_new - $persons_old;
                    if ($diff > 0) {
                        if ($diff > $event_avail_seats) {
                            $can_update = false;
                            $error = "Not enough seats available for increased persons.";
                        } else {
                            $stmt = $conn->prepare("UPDATE events SET event_available_seats = event_available_seats - ? WHERE event_id = ? AND event_available_seats >= ?");
                            $stmt->bind_param("iii", $diff, $event_id, $diff);
                            $stmt->execute();
                            $stmt->close();
                        }
                    } elseif ($diff < 0) {
                        $restore = abs($diff);
                        $stmt = $conn->prepare("UPDATE events SET event_available_seats = event_available_seats + ? WHERE event_id = ?");
                        $stmt->bind_param("ii", $restore, $event_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                } elseif (
                    ($booking_status_old !== 'approved') && $booking_status_new === 'approved'
                ) {
                    if ($persons_new > $event_avail_seats) {
                        $can_update = false;
                        $error = "Cannot approve: not enough available seats in event.";
                    } else {
                        $stmt = $conn->prepare("UPDATE events SET event_available_seats = event_available_seats - ? WHERE event_id = ? AND event_available_seats >= ?");
                        $stmt->bind_param("iii", $persons_new, $event_id, $persons_new);
                        $stmt->execute();
                        $stmt->close();
                    }
                } elseif (
                    $booking_status_old === 'approved' && $booking_status_new !== 'approved'
                ) {
                    $stmt = $conn->prepare("UPDATE events SET event_available_seats = event_available_seats + ? WHERE event_id = ?");
                    $stmt->bind_param("ii", $persons_old, $event_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        if ($can_update && empty($error)) {
            $stmt = $conn->prepare("UPDATE bookings SET user_id=?, event_id=?, persons=?, booking_status=?, updated_at=NOW() WHERE book_id=?");
            $stmt->bind_param("iiisi", $user_id, $event_id, $persons_new, $booking_status_new, $book_id);

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: bookings.php?success=1");
                exit();
            } else {
                $error = "Failed to update booking: ".$conn->error;
                $stmt->close();
            }
        }
        // Refresh booking object for redisplay of form
        $stmt = $conn->prepare("SELECT * FROM bookings WHERE book_id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $booking_result = $stmt->get_result();
        $booking = $booking_result->fetch_assoc();
        $stmt->close();
    }
}

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!-- <link rel="stylesheet" href="css/booking_edit.css"> -->
<style>
    .booking-form-container {
    max-width: 470px;
    margin: 35px auto 0 auto;
    background: #f8faff;
    border-radius: 18px;
    box-shadow: 0 4px 20px 0 rgb(118 95 193 / 12%);
    padding: 32px 36px 30px 36px;
    min-width: 0;
}

@media (max-width: 767px) {
    .booking-form-container {
        padding: 22px 10px 20px 10px;
        max-width: 97vw;
        border-radius: 11px;
    }
    .booking-form label, .booking-form input, .booking-form select, .booking-form button {
        font-size: 0.98em !important;
    }
}

@media (max-width:480px) {
    .booking-form-container {
        padding: 10px 3vw 20px 3vw;
        box-shadow: none;
    }
    .booking-form button {
        width: 100%;
        min-width: 0;
    }
}

.booking-form-container h2 {
    margin: 0 0 22px 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: #483774;
    text-align: center;
}

.back-to-bookings-btn {
    display: inline-block;
    margin-bottom: 19px;
    color: #6a61d0;
    font-size: 1rem;
    text-decoration: none;
    border-radius: 15px;
    background: #f0f0fa;
    padding: 6px 17px;
    transition: background .13s, color .13s;
}

.back-to-bookings-btn:hover {
    background: #e6e8ff;
    color: #3b26b7;
}

.booking-error {
    color: #af2838;
    background: #ffeaea;
    border: 1px solid #ffe1e1;
    padding: 10px 16px;
    border-radius: 7px;
    margin-bottom: 14px;
    font-size: 1rem;
    text-align: center;
}

.booking-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.booking-form label {
    font-weight: 500;
    margin-bottom: 4px;
    margin-top: 3px;
    color: #593a8d;
    font-size: 1.05em;
}

.booking-form input[type="text"],
.booking-form input[type="number"],
.booking-form select {
    border: 1.2px solid #dbe1e7;
    border-radius: 7px;
    padding: 9px 13px;
    font-size: 1.04em;
    margin-bottom: 1.5px;
    background: #fff;
    transition: border-color .13s;
    outline: none;
    width: 100%;
    box-sizing: border-box;
}

.booking-form input:read-only {
    background: #f6f5fb !important;
    color: #8d8d9f;
}

.booking-form input[type="number"]::-webkit-inner-spin-button,
.booking-form input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.booking-form input[type="number"] {
    appearance: textfield;
    -webkit-appearance: none;
}

.booking-form input:focus,
.booking-form select:focus {
    border-color: #a3bbde;
    background: #f9faff;
}

.booking-form select {
    cursor: pointer;
    background: #f7f7fd;
}

.booking-form button {
    background: linear-gradient(90deg, #7270e8, #9b53ce);
    color: #fff;
    font-weight: 600;
    border: none;
    border-radius: 9px;
    font-size: 1.1rem;
    padding: 13px 0;
    cursor: pointer;
    margin-top: 18px;
    transition: box-shadow .15s, background .18s;
    box-shadow: 0 2px 7px 0 rgb(144 120 236 / 12%);
    min-width: 150px;
    align-self: center;
}

.booking-form button:hover,
.booking-form button:focus {
    background: linear-gradient(90deg, #847ff2, #b884ea);
    box-shadow: 0 3px 12px 0 rgb(144 120 236 / 17%);
}

.form-desc {
    margin-bottom: 14px;
    font-size: 1.02em;
    color: #817fc5;
    display: block;
    text-align: center;
}

#max-val {
    font-weight: bold;
    color: #6a5ace;
}

@media (max-width: 530px) {
    .booking-form input, .booking-form select, .booking-form button {
        font-size: 0.99em !important;
        padding-left: 8px;
        padding-right: 8px;
    }
    .booking-form label, .form-desc {
        font-size: 0.98em !important;
    }
    .booking-form-container h2 {
        font-size: 1.22rem;
    }
}

@media (max-width: 370px) {
    .booking-form-container {
        padding-left: 1vw;
        padding-right: 1vw;
    }
    .booking-form label, .form-desc {
        font-size: 0.94em !important;
    }
}

</style>
<div class="booking-form-container">
    <h2>Edit Booking</h2>
    <a href="bookings.php" class="back-to-bookings-btn">&larr; Back to Bookings</a>
    <?php if($error): ?>
        <div class="booking-error"><?=esc($error)?></div>
    <?php endif; ?>
    <?php if($booking): ?>
    <form class="booking-form" id="bookingForm" method="post" autocomplete="off">
        <span class="form-desc">Edit booking details. Persons cannot exceed the event maximum, and approvals require enough available seats.</span>

        <label for="book_id">Booking ID</label>
        <input type="text" name="book_id" id="book_id" value="<?=esc($booking['book_id'])?>" readonly>

        <label for="user_id">User</label>
        <select name="user_id" id="user_id" required>
            <option value="">Select user</option>
            <?php foreach($users as $u): ?>
                <option value="<?=$u['user_id']?>" <?=$booking['user_id'] == $u['user_id'] ? 'selected' : ''?>>
                    <?=esc($u['user_name'])?> (<?=esc($u['user_email'])?>)
                </option>
            <?php endforeach; ?>
        </select>

        <label for="event_id">Event (ID)</label>
        <select name="event_id" id="event_id" required>
            <option value="">Select event</option>
            <?php foreach($events as $eid=>$aseats): ?>
                <option
                    value="<?=$eid?>"
                    <?=$booking['event_id'] == $eid ? 'selected' : ''?>
                    data-avail="<?=$aseats?>"
                >Event #<?=$eid?> (available seats: <?=$aseats?>)</option>
            <?php endforeach; ?>
        </select>

        <label for="persons">Persons</label>
        <input type="number"
               name="persons"
               id="persons"
               min="1"
               max="<?=$event_max?>"
               value="<?=esc($booking['persons'])?>"
               required
               autocomplete="off"
               placeholder="Number of persons"
        >

        <div style="margin:8px 0 4px 0;font-size:13px;color:#995">
            Max allowed for this event: <span id="max-val"><?=$event_max?></span>
        </div>

        <label for="booking_status">Booking Status</label>
        <select name="booking_status" id="booking_status" required>
            <?php foreach($booking_status_options as $stat): ?>
                <option value="<?=$stat?>" <?=($booking['booking_status'] == $stat ? 'selected' : '')?>>
                    <?=ucfirst($stat)?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="booked_at">Booked At</label>
        <input type="text" value="<?=esc($booking['booked_at'])?>" readonly>

        <label for="updated_at">Last Updated</label>
        <input type="text" value="<?=esc($booking['updated_at'])?>" readonly>

        <button type="submit">Update Booking</button>
    </form>
    <?php endif; ?>
</div>

<script>
const personsInput = document.getElementById('persons');
const eventSelect = document.getElementById('event_id');
const maxSpan = document.getElementById('max-val');
const origPersons = parseInt(personsInput.value, 10);

function updateMax() {
    const selected = eventSelect.options[eventSelect.selectedIndex];
    let avail = parseInt(selected && selected.dataset && selected.dataset.avail ? selected.dataset.avail : 1, 10);
    <?php if ($booking && $booking['event_id'] && $booking['booking_status'] === 'approved'): ?>
        if (parseInt(eventSelect.value, 10) === <?=json_encode(intval($booking['event_id']))?>) {
            avail += <?=json_encode(intval($booking['persons']))?>;
        }
    <?php endif; ?>
    if (avail < 1) avail = 1;
    personsInput.max = avail;
    maxSpan.textContent = avail;
    if (parseInt(personsInput.value, 10) > avail) {
        personsInput.value = avail;
    }
}
eventSelect && eventSelect.addEventListener('change', updateMax);
personsInput && personsInput.addEventListener('input', function() {
    // Ensure input does not go over max
    if (parseInt(personsInput.value, 10) > parseInt(personsInput.max, 10)) {
        personsInput.value = personsInput.max;
    }
});
</script>

