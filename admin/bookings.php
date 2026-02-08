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

// Postback for changing booking status.
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
    <style>
    body { overflow-x: hidden; }
    .success-message {
        color: #228a36;
        background: #e8fdeb;
        border: 1px solid #a8dfb1;
        padding: 9px 15px;
        border-radius: 7px;
        margin: 15px 0 14px 0;
        display: none;
        font-weight: 600;
        font-size: 16px;
        max-width: 390px;
    }
    .error-message-inline {
        color: #b70c26;
        background: #fff0f0;
        border: 1px solid #e1c2c7;
        padding: 9px 15px;
        border-radius: 7px;
        margin: 15px 0 14px 0;
        font-weight: 600;
        font-size: 16px;
        max-width: 490px;
        display: block;
    }
    .booking-status-select {
        padding: 6px 20px 6px 10px;
        font-size: 14px;
        border-radius: 18px;
        border: 1px solid #dad7f6;
        background: #f7f6fc;
        color: #473b6f;
        outline: none;
        min-width: 108px;
        font-weight: 500;
        transition: border-color .15s;
        cursor: pointer;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
    }
    .booking-status-select:focus {
        border-color: #aa97eb;
        background: #f3f0fa;
    }
    .booking-status-select::-ms-expand {
        display: none;
    }
    </style>
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
                    window.location.href = 'bookings_edit.php?booking_id=' + bid;
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
        <div id="success-message-box" class="success-message"></div>
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
                            <th class="<?php echo esc($col); ?>">
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
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <?php if ($page > 1): ?>
                    <button class="pagination-btn-go prev" onclick="window.location.href='?page=<?=($page-1)?>'">
                        <span class="go-arrow-prev">&#8592;</span> Prev
                    </button>
                <?php endif; ?>
                <span class="pagination-page-indicator">
                    Page <?= $page ?> of <?= $total_pages ?>
                </span>
                <?php if ($page < $total_pages): ?>
                    <button class="pagination-btn-go next" onclick="window.location.href='?page=<?=($page+1)?>'">
                        Next <span class="go-arrow">&#8594;</span>
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
        </div>
    </div>
</body>
</html>