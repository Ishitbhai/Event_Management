<?php
session_start();
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    ?>
    <script>
        window.location.href = 'login.php';
    </script>
    <?php
    exit();
}

require_once('sidebar.php');
require_once('../database/db_connect.php');

// Fetch users with user_id, name, email
$users = [];
$result = $conn->query("SELECT user_id, user_name, user_email FROM users");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Fetch events with event_id, event_available_seats, event_name if needed
$events = [];
$result = $conn->query("SELECT event_id, event_available_seats FROM events");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $events[$row['event_id']] = $row['event_available_seats'];
    }
}

$booking_status_options = ['pending', 'approved', 'rejected'];
$error = "";
$success = "";

// Handle Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $persons = isset($_POST['persons']) ? intval($_POST['persons']) : 0;
    $booking_status = isset($_POST['booking_status']) ? $_POST['booking_status'] : 'pending';

    // Validation
    if ($user_id <= 0 || !$users) {
        $error = "Invalid user selected.";
    } elseif ($event_id <= 0 || !isset($events[$event_id])) {
        $error = "Invalid event selected.";
    } elseif ($persons < 1) {
        $error = "Persons must be at least 1.";
    } elseif ($persons > $events[$event_id]) {
        $error = "Cannot book more persons than available seats.";
    } elseif (!in_array($booking_status, $booking_status_options)) {
        $error = "Invalid booking status.";
    } else {
        // Process booking
        $stmt = $conn->prepare("INSERT INTO bookings (event_id, user_id, persons, booking_status, booked_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("iiis", $event_id, $user_id, $persons, $booking_status);

        if ($stmt->execute()) {
            $stmt->close();

            // If approved, minus persons from event_available_seats
            if ($booking_status === 'approved') {
                $stmt2 = $conn->prepare("UPDATE events SET event_available_seats = event_available_seats - ? WHERE event_id=? AND event_available_seats >= ?");
                $stmt2->bind_param("iii", $persons, $event_id, $persons);
                $stmt2->execute();
                $stmt2->close();
            }

            // Redirect to bookings.php after successful booking
            echo "<script>window.location.href='bookings.php?success=1';</script>";
            exit();
        } else {
            if ($conn->errno === 1452) $error = "User/Event does not exist.";
            else $error = "Failed to create booking: ".$conn->error;
            $stmt->close();
        }
    }
}
?>

<!-- <link rel="stylesheet" href="css/booking_create.css"> -->
<style>
.booking-form-container {
    max-width: 490px;
    margin: 42px auto 0 auto;
    background: #fff;
    border-radius: 9px;
    box-shadow: 0 1px 9px #4b397622;
    padding: 34px 34px 29px;
}
.booking-form-container h2 {
    margin-top: 0;
    color: #312153;
    font-size: 1.44em;
}
.booking-form label {
    display: block;
    font-weight: 530;
    margin-bottom: 7px;
    color: #514178;
}
.booking-form select,
.booking-form input[type="number"] {
    display: block;
    width: 100%;
    font-size: 16px;
    padding: 8px 10px;
    margin-bottom: 17px;
    border: 1px solid #c2c5db;
    border-radius: 6px;
    background: #f7f8fb;
    color: #322053;
    transition: border .14s;
}
.booking-form select:focus,
.booking-form input[type="number"]:focus {
    border-color: #7353e5;
    outline: none;
    background: #fcfcff;
}
.booking-form button[type="submit"] {
    background: linear-gradient(88deg, #556cd6, #7867ea 90%);
    color: #fff;
    font-weight: 600;
    font-size: 17px;
    border: none;
    border-radius: 9px;
    padding: 12px 39px;
    cursor: pointer;
    box-shadow: 0 2px 9px #7b74e515;
    transition: background 0.20s;
}
.booking-form button[type="submit"]:hover {
    background: linear-gradient(88deg, #4538a4, #5944be 90%);
}
.booking-error {
    background: #ffeded;
    color: #b5141f;
    padding: 9px 16px;
    border-radius: 7px;
    margin-bottom: 14px;
    font-size: 15px;
}
.booking-succ {
    background: #e8fad6;
    color: #24890d;
    padding: 9px 16px;
    border-radius: 7px;
    margin-bottom: 14px;
    font-size: 15px;
}
.booking-form .form-desc {
    color: #938fb0;
    font-size: 14px;
    margin-bottom: 23px;
    display: block;
}
.back-to-bookings-btn {
    display: inline-block;
    margin-top: 8px;
    margin-bottom: 18px;
    padding: 9px 26px;
    background: #e9eafe;
    color: #433999;
    border-radius: 7px;
    border: none;
    font-size: 16px;
    text-decoration: none;
    font-weight: 510;
    transition: background 0.15s;
}
.back-to-bookings-btn:hover {
    background: #d0d4f5;
}
</style>
    <div class="booking-form-container">
        <h2>Create a Booking</h2>
        <a href="bookings.php" class="back-to-bookings-btn">&larr; Back to Bookings</a>
        <?php if($error): ?>
            <div class="booking-error"><?=htmlspecialchars($error)?></div>
        <?php endif; ?>
        <form class="booking-form" id="bookingForm" method="post" autocomplete="off">
            <span class="form-desc">Fill the details to create a booking. Seats availability is checked automatically.</span>

            <label for="user_id">User</label>
            <select name="user_id" id="user_id" required>
                <option value="">Select user</option>
                <?php foreach($users as $u): ?>
                    <option value="<?=$u['user_id']?>" <?=isset($_POST['user_id']) && $_POST['user_id'] == $u['user_id'] ? 'selected' : ''?>>
                        <?=htmlspecialchars($u['user_name'])?> (<?=htmlspecialchars($u['user_email'])?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="event_id">Event (ID)</label>
            <select name="event_id" id="event_id" required>
                <option value="">Select event</option>
                <?php foreach($events as $eid=>$aseats): ?>
                    <option value="<?=$eid?>" data-avail="<?=$aseats?>" <?=isset($_POST['event_id']) && $_POST['event_id'] == $eid ? 'selected' : ''?>>
                        <?=$eid?> (available seats: <?=$aseats?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="persons">Persons</label>
            <input type="number" name="persons" id="persons" min="1"
                value="<?=isset($_POST['persons']) ? intval($_POST['persons']) : ''?>" required
                autocomplete="off" placeholder="Number of persons">

            <label for="booking_status">Booking Status</label>
            <select name="booking_status" id="booking_status" required>
                <?php foreach($booking_status_options as $stat): ?>
                    <option value="<?=$stat?>" <?=(isset($_POST['booking_status']) && $_POST['booking_status'] == $stat ? 'selected' : '')?>><?=ucfirst($stat)?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Create Booking</button>
        </form>
    </div>
    <script>
        // Inline event_id => avail_seats mapping for JS validation
        const eventSeats = <?=json_encode($events)?>;

        function getSelectedAvailSeats() {
            var sel = document.getElementById('event_id');
            var eventId = sel.value;
            return eventSeats[eventId] || 0;
        }

        document.getElementById('event_id').addEventListener('change', function() {
            let personsInput = document.getElementById('persons');
            let avail = getSelectedAvailSeats();
            personsInput.max = avail>0 ? avail : 99999;
            let val = parseInt(personsInput.value || "0", 10);
            if (val>avail && avail>0) {
                personsInput.value = avail;
            }
            // Show/hide seat warning
        });

        document.getElementById('persons').addEventListener('input', function() {
            let avail = getSelectedAvailSeats();
            let val = parseInt(this.value || "0",10);
            if (val > avail && avail > 0) {
                this.value = avail;
            } else if (val < 1) {
                this.value = 1;
            }
        });

        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            // JS validation on submit
            let user = document.getElementById('user_id').value;
            let eventSel = document.getElementById('event_id');
            let eventId = eventSel.value;
            let avail = getSelectedAvailSeats();
            let persons = parseInt(document.getElementById('persons').value, 10);
            let status = document.getElementById('booking_status').value;

            if (!user) {
                alert("Please select a user.");
                e.preventDefault(); return false;
            }
            if (!eventId) {
                alert("Please select an event.");
                e.preventDefault(); return false;
            }
            if (!(persons>=1)) {
                alert("At least one person must be booked."); e.preventDefault(); return false;
            }
            if (persons > avail) {
                alert("Cannot book more persons than available seats.");
                e.preventDefault(); return false;
            }
            if (["pending","approved","rejected"].indexOf(status) === -1) {
                alert("Invalid booking status."); e.preventDefault(); return false;
            }
            // For 'approved', optionally warn if seats left will be very low.
        });
    </script>
</body>
</html>
