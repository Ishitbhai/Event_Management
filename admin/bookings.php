<?php
session_start();
require_once('sidebar.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once('../database/db_connect.php');

$fields = [
    'book_id',
    'event_id',
    'user_id',
    'persons',
    'booking_status',
    'booked_at',
    'updated_at'
];

$error_message = '';
$success_message = '';

// Handle deletion request
if (
    isset($_POST['delete_booking_id']) && is_numeric($_POST['delete_booking_id'])
) {
    $delete_book_id = intval($_POST['delete_booking_id']);

    // Fetch booking's persons, event_id, status before deletion
    $stmt = $conn->prepare("SELECT persons, event_id, booking_status FROM bookings WHERE book_id = ?");
    $stmt->bind_param("i", $delete_book_id);
    $stmt->execute();
    $stmt->bind_result($del_persons, $del_event_id, $del_booking_status);
    $has_row = $stmt->fetch();
    $stmt->close();

    if ($has_row) {
        // If approved, restore seats for its event
        if ($del_booking_status === 'approved' && $del_event_id > 0 && $del_persons > 0) {
            $stmt = $conn->prepare("UPDATE events SET event_available_seats = event_available_seats + ? WHERE event_id = ?");
            $stmt->bind_param("ii", $del_persons, $del_event_id);
            $stmt->execute();
            $stmt->close();
        }

        // Now delete the booking
        $stmt = $conn->prepare("DELETE FROM bookings WHERE book_id = ?");
        $stmt->bind_param("i", $delete_book_id);
        if ($stmt->execute()) {
            $stmt->close();
            $success_message = "Booking deleted successfully.";
            // Redirect to this page to avoid reposting
            header('Location: '.$_SERVER['PHP_SELF'].(isset($_POST['page']) ? '?page='.intval($_POST['page']) : ''));
            exit();
        } else {
            $error_message = "Failed to delete booking.";
            $stmt->close();
        }
    } else {
        $error_message = "Booking not found.";
    }
}

// Postback for changing booking status
if (
    isset($_POST['booking_id']) && is_numeric($_POST['booking_id']) &&
    isset($_POST['new_status'])
) {
    $book_id = intval($_POST['booking_id']);
    $new_status = $_POST['new_status'];
    $allowed_statuses = ['pending', 'approved', 'rejected'];
    if (in_array($new_status, $allowed_statuses)) {
        // Fetch previous status, persons and event_id
        $stmt = $conn->prepare("SELECT booking_status, persons, event_id FROM bookings WHERE book_id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $stmt->bind_result($prev_status, $persons, $event_id);
        $stmt->fetch();
        $stmt->close();

        $persons = (int)$persons;
        $event_id = (int)$event_id;

        $allow_approval = true;
        // Only operate on available seats if persons and event_id are present
        if ($event_id > 0 && $persons > 0) {
            // If changing from 'pending' or 'rejected' to 'approved': check seats and subtract persons
            if (
                ($prev_status === 'pending' && $new_status === 'approved') ||
                ($prev_status === 'rejected' && $new_status === 'approved')
            ) {
                // Fetch available seats
                $stmt = $conn->prepare("SELECT event_available_seats FROM events WHERE event_id = ?");
                $stmt->bind_param("i", $event_id);
                $stmt->execute();
                $stmt->bind_result($available_seats);
                $stmt->fetch();
                $stmt->close();

                if ($persons > $available_seats) {
                    // Error: not enough seats, do NOT update status, show message (later)
                    $error_message = "Cannot approve booking: not enough available seats for this event.";
                    $allow_approval = false;
                }
            }
        }

        // Only update if not blocked by seat issue
        if ($allow_approval) {
            // Update the booking status
            $stmt = $conn->prepare("UPDATE bookings SET booking_status=?, updated_at=NOW() WHERE book_id=?");
            $stmt->bind_param("si", $new_status, $book_id);
            $stmt->execute();
            $stmt->close();

            if ($event_id > 0 && $persons > 0) {
                // If changing from 'pending' or 'rejected' to 'approved': subtract persons
                if (
                    ($prev_status === 'pending' && $new_status === 'approved') ||
                    ($prev_status === 'rejected' && $new_status === 'approved')
                ) {
                    $stmt = $conn->prepare("UPDATE events SET event_available_seats = event_available_seats - ? WHERE event_id = ?");
                    $stmt->bind_param("ii", $persons, $event_id);
                    $stmt->execute();
                    $stmt->close();
                }
                // If changing from 'approved' to 'pending' or 'rejected': add persons back
                elseif (
                    $prev_status === 'approved' && ($new_status === 'pending' || $new_status === 'rejected')
                ) {
                    $stmt = $conn->prepare("UPDATE events SET event_available_seats = event_available_seats + ? WHERE event_id = ?");
                    $stmt->bind_param("ii", $persons, $event_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            // After successful update, redirect.
            header('Location: '.$_SERVER['PHP_SELF'].(isset($_POST['page']) ? '?page='.intval($_POST['page']) : ''));
            exit();
        }
    }
    // If reached here and $error_message was set, we DON'T redirect, instead reload below with error displayed.
}

$bookings = [];
$booking_columns = [];
$fetch_result = $conn->query("SELECT * FROM bookings ORDER BY booked_at DESC");
if ($fetch_result && $fetch_result->num_rows > 0) {
    $booking_columns = array_keys($fetch_result->fetch_assoc());
    $fetch_result->data_seek(0);
    while ($row = $fetch_result->fetch_assoc()) {
        $bookings[] = $row;
    }
} elseif ($fetch_result) {
    $booking_columns = [];
}

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$booking_status_options = ['pending', 'approved', 'rejected'];

$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0
    ? (int)$_GET['page']
    : 1;
$per_page = 10;
$total_bookings = count($bookings);
$total_pages = ceil($total_bookings / $per_page);
$start_index = ($page - 1) * $per_page;
$paged_bookings = array_slice($bookings, $start_index, $per_page);
$serial_start = $start_index + 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Bookings</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="css/events.css">
    <link rel="stylesheet" href="css/index.css">
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let createBtn = document.querySelector('.create-booking-btn');
        if (createBtn) {
            createBtn.addEventListener('click', function() {
                window.location.href = 'booking_create.php';
            });
        }
        document.querySelectorAll('.edit-booking-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                let tr = btn.closest('tr[data-booking-id]');
                if (tr) {
                    let bid = tr.getAttribute('data-booking-id');
                    window.location.href = 'booking_edit.php?booking_id=' + bid;
                }
            });
        });
        // On status select change, submit the closest form (full page refresh)
        document.querySelectorAll('.booking-status-select').forEach(function(sel){
            sel.addEventListener('change', function(){
                let form = this.closest('form');
                if (form) {
                    form.submit();
                }
            });
        });

        // Delete buttons
        document.querySelectorAll('.delete-booking-btn').forEach(function(btn){
            btn.addEventListener('click', function(e){
                let tr = btn.closest('tr[data-booking-id]');
                let bid = tr ? tr.getAttribute('data-booking-id') : '';
                if (bid && confirm('Are you sure you want to delete this booking?')) {
                    // Post deletion via hidden form
                    var deleteForm = document.getElementById('delete-booking-form');
                    deleteForm.querySelector('input[name="delete_booking_id"]').value = bid;
                    // If paged, keep page
                    let pageInput = deleteForm.querySelector('input[name="page"]');
                    if (pageInput) pageInput.value = <?= esc($page) ?>;
                    deleteForm.submit();
                }
            });
        });
    });
    </script>
</head>
<body>
<div class="dashboard-main">
    <div class="events-header">
        <h2 class="internal-header">Manage Bookings</h2>
        <button class="create-booking-btn create-event-btn" type="button">
            New Booking
        </button>
    </div>
    <div class="event-table-container">
        <?php if (!empty($error_message)): ?>
            <div class="error-message-inline"><?= esc($error_message) ?></div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div id="success-message-box" class="success-message"><?= esc($success_message) ?></div>
        <?php endif; ?>

        <!-- Hidden delete form -->
        <form id="delete-booking-form" method="post" style="display:none;">
            <input type="hidden" name="delete_booking_id" value="">
            <?php if ($page > 1): ?>
                <input type="hidden" name="page" value="<?= esc($page) ?>">
            <?php endif; ?>
        </form>

        <?php if ($total_bookings === 0): ?>
            <div class="internal-no-events">
                No bookings available.
            </div>
        <?php else: ?>
            <table class="event-table">
                <thead>
                <tr>
                    <th>Sr. No.</th>
                    <?php
                    $col_headings = [
                        'book_id'     => 'Booking ID',
                        'event_id'    => 'Event ID',
                        'user_id'     => 'User ID',
                        'persons'     => 'Persons',
                        'booking_status' => 'Booking Status',
                        'booked_at'   => 'Booked At',
                        'updated_at'  => 'Updated At'
                    ];
                    foreach ($fields as $col): ?>
                        <th class="<?= esc($col) ?>">
                            <?= esc($col_headings[$col] ?? $col) ?>
                        </th>
                    <?php endforeach; ?>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $snum = $serial_start;
                foreach ($paged_bookings as $bk): ?>
                    <tr data-booking-id="<?= esc($bk['book_id']) ?>">
                        <td><?= $snum++ ?></td>
                        <?php foreach ($fields as $col): ?>
                            <td class="<?= esc($col) ?>">
                                <?php if ($col === 'booking_status'): ?>
                                    <form method="post" style="margin: 0; display: inline;">
                                        <input type="hidden" name="booking_id" value="<?= esc($bk['book_id']) ?>">
                                        <?php if ($page > 1): ?>
                                            <input type="hidden" name="page" value="<?= esc($page) ?>">
                                        <?php endif; ?>
                                        <select class="booking-status-select" name="new_status">
                                            <?php foreach ($booking_status_options as $status): ?>
                                                <option value="<?= esc($status) ?>" <?= ($bk[$col] === $status) ? 'selected' : '' ?>>
                                                    <?= ucfirst($status) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php else: ?>
                                    <?= esc($bk[$col]) ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <button class="edit-booking-btn edit-btn" type="button">Edit</button>
                            <button class="delete-booking-btn delete-btn" type="button" style="margin-left:6px;">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($total_pages > 1): ?>
                <div class="classic-pagination">
                    <ul>
                        <?php if ($page > 1): ?>
                            <li>
                                <a href="?page=<?= ($page-1) ?>" class="pagination-btn-go prev">Prev</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        // Calculate pages displayed for pagination bar (windowed, e.g., show ... if many pages)
                        $max_page_links = 5;
                        $page_window = floor($max_page_links / 2);
                        $start_page = max(1, $page - $page_window);
                        $end_page = min($total_pages, $page + $page_window);
                        if ($end_page - $start_page + 1 < $max_page_links) {
                            // Expand window as needed
                            if ($start_page == 1) $end_page = min($total_pages, $start_page + $max_page_links - 1);
                            if ($end_page == $total_pages) $start_page = max(1, $end_page - $max_page_links + 1);
                        }
                        if ($start_page > 1): ?>
                            <li><a href="?page=1">1</a></li>
                            <?php if ($start_page > 2): ?>
                                <li><span>…</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php for ($p = $start_page; $p <= $end_page; $p++): ?>
                            <li>
                                <?php if ($p == $page): ?>
                                    <span class="current-page"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="?page=<?= $p ?>"><?= $p ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor; ?>
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li><span>…</span></li>
                            <?php endif; ?>
                            <li><a href="?page=<?= $total_pages ?>"><?= $total_pages ?></a></li>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li>
                                <a href="?page=<?= ($page+1) ?>" class="pagination-btn-go next">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>