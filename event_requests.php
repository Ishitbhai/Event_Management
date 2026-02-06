<?php
session_start();
require_once('header.php');
require_once('database/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Get user_type from database
$user_q = $conn->prepare("SELECT user_type FROM users WHERE user_id = ?");
$user_q->bind_param('i', $user_id);
$user_q->execute();
$user_rs = $user_q->get_result();
$user_row = $user_rs->fetch_assoc();
$user_q->close();

if (!$user_row || !in_array($user_row['user_type'], ['owner', 'admin'])) {
    header("Location: events.php");
    exit();
}
$user_type = $user_row['user_type'];


$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if (!$event_id) {
    header("Location: events.php");
    exit();
}

// Get the event
$event_q = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
$event_q->bind_param('i', $event_id);
$event_q->execute();
$event_rs = $event_q->get_result();
$event = $event_rs->fetch_assoc();
$event_q->close();

if (!$event) {
    header("Location: events.php");
    exit();
}

// --- Ownership Restriction Logic ---
// Only owner of an event can access it, and only admin of an "admin-created" event can access their own (and not owner events).
// Assume: 
//   - owner_id on events is the user who created it.
//   - if owner_id's user_type is 'owner', it's an owner event; if 'admin', it's an admin event
//   - An admin cannot access owner events and vice versa.

$event_owner_id = isset($event['owner_id']) ? intval($event['owner_id']) : 0;

// Get event owner's type
$owner_type = "";
if ($event_owner_id) {
    $owner_q = $conn->prepare("SELECT user_type FROM users WHERE user_id = ?");
    $owner_q->bind_param('i', $event_owner_id);
    $owner_q->execute();
    $owner_rs = $owner_q->get_result();
    $owner_row = $owner_rs->fetch_assoc();
    $owner_q->close();
    $owner_type = $owner_row ? $owner_row['user_type'] : "";
}

$forbidden = false;

// If user is owner, only allow events where owner is owner and owner_id matches user_id
if ($user_type === 'owner') {
    if ($owner_type !== 'owner' || $event_owner_id !== $user_id) {
        $forbidden = true;
    }
} elseif ($user_type === 'admin') {
    // If user is admin, only allow events where owner is admin and owner_id matches user_id
    if ($owner_type !== 'admin' || $event_owner_id !== $user_id) {
        $forbidden = true;
    }
}
if ($forbidden) {
    header("Location: events.php");
    exit();
}

// -------- REST OF ORIGINAL LOGIC --------

// Actions: Approve/Reject booking
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['book_id'])) {
    $action = $_POST['action'];
    $book_id = intval($_POST['book_id']);

    // Get info of booking record
    $booking_q = $conn->prepare("SELECT * FROM bookings WHERE book_id = ? AND event_id = ?");
    $booking_q->bind_param('ii', $book_id, $event_id);
    $booking_q->execute();
    $booking_rs = $booking_q->get_result();
    $booking = $booking_rs->fetch_assoc();
    $booking_q->close();

    if ($booking) {
        if ($action === 'approve' && $booking['booking_status'] === 'pending') {
            // Approve flow
            $persons = intval($booking['persons']);
            $available_seats = intval($event['event_available_seats']);

            if ($persons > $available_seats) {
                $msg = '<div style="color:#bb2424;">Not enough available seats for this request.</div>';
            } else {
                // Transaction
                $conn->autocommit(false);
                $success = true;

                // 1. Update booking status
                $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'approved' WHERE book_id = ?");
                $stmt->bind_param('i', $book_id);
                $success = $success && $stmt->execute();
                $stmt->close();

                // 2. Decrement available seats
                $stmt = $conn->prepare("UPDATE events SET event_available_seats = event_available_seats - ? WHERE event_id = ?");
                $stmt->bind_param('ii', $persons, $event_id);
                $success = $success && $stmt->execute();
                $stmt->close();

                if ($success) {
                    $conn->commit();
                    $msg = '<div style="color:#22813d;">Booking approved successfully!</div>';
                    $event['event_available_seats'] -= $persons;
                } else {
                    $conn->rollback();
                    $msg = '<div style="color:#bb2424;">An error occurred, please try again.</div>';
                }
                $conn->autocommit(true);
            }
        } elseif ($action === 'reject' && $booking['booking_status'] === 'pending') {
            // Reject flow
            $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'rejected' WHERE book_id = ?");
            $stmt->bind_param('i', $book_id);
            if ($stmt->execute()) {
                $msg = '<div style="color:#22813d;">Booking rejected successfully!</div>';
            } else {
                $msg = '<div style="color:#bb2424;">Failed to reject booking.</div>';
            }
            $stmt->close();
        }
    }
}

// Get all bookings for this event, joined with user for name and email
$sql = "SELECT b.*, u.user_name as user_name, u.user_email as user_email
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        WHERE b.event_id = ?
        ORDER BY 
            CASE b.booking_status
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'rejected' THEN 3
                ELSE 4
            END, b.book_id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $event_id);
$stmt->execute();
$rs = $stmt->get_result();
$bookings = [];
while ($row = $rs->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();

// Event title for display
$event_title = isset($event['event_title']) ? htmlspecialchars($event['event_title']) : '';

?>

<link rel="stylesheet" href="css/create_event.css">
<style>
.req-table-container {
    max-width: 880px;
    margin: 34px auto 32px auto;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 18px 0 rgba(60,70,140,0.11);
    padding: 36px 32px 42px 32px;
    font-family: "Segoe UI", "Roboto", Arial, sans-serif;
}

.req-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.req-table th, .req-table td {
    border: 1px solid #ddd;
    padding: 11px 12px;
    text-align: center;
}

.req-table th {
    background-color: #f3f5ff;
    color: #324283;
    font-weight: 600;
    font-size: 1.04em;
}

.req-table td {
    font-size: 1.01em;
}

.status-pill {
    padding: 4px 12px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 1em;
}
.status-pending { background: #fcfacf; color: #9e8702; border: 1.5px solid #e4d958; }
.status-approved { background: #e5fbe9; color: #22813d; border: 1.5px solid #c7f4d0;}
.status-rejected { background: #ffeaea; color: #bb2424; border: 1.5px solid #fac0c0; }

.action-btn {
    margin: 0 3.5px;
    padding: 4px 13px;
    border-radius: 7px;
    border: none;
    font-size: 0.99em;
    font-weight: 500;
    cursor: pointer;
}
.action-approve { background: #22813d; color: #fff;}
.action-approve:disabled { background: #b9e9c5; cursor: not-allowed;}
.action-reject { background: #bb2424; color: #fff;}
.action-reject:disabled { background: #f7bcce; cursor: not-allowed;}
@media(max-width:900px) {
    .req-table-container { padding: 20px 3vw; }
    .req-table th, .req-table td { padding: 9px 2.5vw;}
}
</style>

<div class="req-table-container">
    <div style="margin-bottom:18px;">
        <a href="bookings.php" style="font-size:1.05em;text-decoration:none;color:#7b63e6;">&#8592; Back to My Events</a>
    </div>
    <h2 style="text-align:center;color:#4242a0;margin-bottom:22px;font-size:1.25em;">
        Booking Requests for: "<span style="color:#417bb4;"><?php echo $event_title; ?></span>"
    </h2>
    <?php if ($msg) echo '<div style="text-align:center;margin-bottom:17px;font-size:1.09em;">'.$msg.'</div>'; ?>

    <?php if (count($bookings)): ?>
    <table class="req-table">
        <tr>
            <th>#</th>
            <th>User Name</th>
            <th>Email</th>
            <th>Persons</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($bookings as $i => $b): 
            $pill_class = ($b['booking_status']=='pending' ? 'status-pending' : ($b['booking_status']=='approved' ? 'status-approved' : 'status-rejected'));
        ?>
        <tr>
            <td><?php echo $i+1; ?></td>
            <td><?php echo htmlspecialchars($b['user_name'] ?? 'User #'.$b['user_id']); ?></td>
            <td>
                <?php 
                    if (!empty($b['user_email'])) {
                        echo htmlspecialchars($b['user_email']);
                    } else {
                        echo '<span style="color:#999;">(no email)</span>';
                    }
                ?>
            </td>
            <td><?php echo intval($b['persons']); ?></td>
            <td>
                <span class="status-pill <?php echo $pill_class; ?>">
                    <?php echo ucfirst($b['booking_status']); ?>
                </span>
            </td>
            <td>
                <?php if ($b['booking_status'] == 'pending'): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="book_id" value="<?php echo intval($b['book_id']); ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="action-btn action-approve" 
                        <?php if ($event['event_available_seats'] < $b['persons']) echo "disabled"; ?>
                        >Approve</button>
                    </form>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="book_id" value="<?php echo intval($b['book_id']); ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="action-btn action-reject">Reject</button>
                    </form>
                <?php else: ?>
                    <span style="color:#bbb;">---</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <div style="margin:30px 0 0 3px; font-size:1.07em; color:#5c5c79;">
        Event available seats: <b><?php echo intval($event['event_available_seats']); ?></b>
    </div>
    <?php else: ?>
    <div style="text-align:center;color:#999;font-size:1.1em;padding:43px 0;">No bookings yet for this event.</div>
    <?php endif; ?>
</div>

<?php require_once('footer.php'); ?>
