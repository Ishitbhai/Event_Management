<?php
session_start();
require_once('sidebar.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once('../database/db_connect.php');

$fields = [
    'event_title',
    'user_email',
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
$search_user_email  = isset($_GET['search_user_email'])  ? trim($_GET['search_user_email'])  : '';
$search_event_title = isset($_GET['search_event_title']) ? trim($_GET['search_event_title']) : '';
$search_booked_at   = isset($_GET['search_booked_at'])   ? trim($_GET['search_booked_at'])   : '';

$where  = [];
$params = [];
$types  = '';

if ($search_user_email !== '') {
    $where[]  = "u.user_email LIKE ?";
    $params[] = '%' . $search_user_email . '%';
    $types   .= 's';
}
if ($search_event_title !== '') {
    $where[]  = "e.event_title LIKE ?";
    $params[] = '%' . $search_event_title . '%';
    $types   .= 's';
}
if ($search_booked_at !== '') {
    $where[]  = "DATE(b.booked_at) = ?";
    $params[] = $search_booked_at;
    $types   .= 's';
}

$fetch_sql = "SELECT b.*, e.event_title, u.user_email
     FROM bookings b
     LEFT JOIN events e ON b.event_id = e.event_id
     LEFT JOIN users u ON b.user_id = u.user_id";
if ($where) {
    $fetch_sql .= " WHERE " . implode(' AND ', $where);
}
$fetch_sql .= " ORDER BY b.booked_at DESC";

$bookings = [];
$booking_columns = [];

if ($where) {
    $stmt = $conn->prepare($fetch_sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $booking_columns = array_keys($result->fetch_assoc());
            $result->data_seek(0);
            while ($row = $result->fetch_assoc()) $bookings[] = $row;
        }
        $stmt->close();
    }
} else {
    $fetch_result = $conn->query($fetch_sql);
    if ($fetch_result && $fetch_result->num_rows > 0) {
        $booking_columns = array_keys($fetch_result->fetch_assoc());
        $fetch_result->data_seek(0);
        while ($row = $fetch_result->fetch_assoc()) $bookings[] = $row;
    }
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
    <style>
        body {
        margin: 0;
        background: #f4f6fb;
        }
        .dashboard-main {
            padding: 40px;
        }
        .dashboard-header {
            margin-bottom: 30px;
        }
        .dashboard-header h2 {
            margin: 0;
            color: #322053;
        }
        .dashboard-header p {
            color: #6c757d;
            margin-top: 8px;
        }
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }
        @media (max-width: 980px) {
            .dashboard-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 700px) {
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
        }
        .dashboard-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            transition: box-shadow 0.3s ease, transform 0.5s cubic-bezier(.68,-0.55,.27,1.55);
            cursor: pointer;
            opacity: 0;
            transform: translateY(40px) scale(0.93);
            animation: fadeInUp 0.8s cubic-bezier(.68,-0.55,.27,1.55) forwards;
        }
        .dashboard-card:nth-child(1) { animation-delay: 0.10s; }
        .dashboard-card:nth-child(2) { animation-delay: 0.20s; }
        .dashboard-card:nth-child(3) { animation-delay: 0.30s; }
        .dashboard-card:nth-child(4) { animation-delay: 0.40s; }
        .dashboard-card:nth-child(5) { animation-delay: 0.50s; }
        .dashboard-card:nth-child(6) { animation-delay: 0.60s; }
        .dashboard-card:nth-child(7) { animation-delay: 0.70s; }
        .dashboard-card:nth-child(8) { animation-delay: 0.80s; }
        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(40px) scale(0.93);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .dashboard-card:hover {
            transform: translateY(-6px) scale(1.04);
            box-shadow: 0 12px 25px rgba(0,0,0,0.15);
            z-index: 1;
        }
        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .card-number {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 8px;
            transition: color 0.5s;
        }
        .card-link {
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            color: black;
        }
        .events { border-left: 6px solid #5236d6; }
        .bookings { border-left: 6px solid #197655; }
        .users { border-left: 6px solid #c82f2f; }
        .services { border-left: 6px solid #197655; }
        .reviews { border-left: 6px solid #c82f2f; }
        .categories { border-left: 6px solid #5236d6; }
        .coupons { border-left: 6px solid #c82f2f; }
        .settings { border-left: 6px solid #5236d6; }

        .events .card-title { color: #5236d6; }
        .bookings .card-title { color: #197655; }
        .users .card-title { color: #c82f2f; }
        .services .card-title { color: #197655; }
        .reviews .card-title { color: #c82f2f; }
        .categories .card-title { color: #5236d6; }
        .coupons .card-title { color: #c82f2f; }
        .settings .card-title { color: #5236d6; }

        /* -------------------- */
        /* Events Styles        */
        /* -------------------- */

        body {
            overflow-x: hidden;
        }

        /* Animation Keyframes */
        @keyframes fadeInUpRowStagger {
            from {
                opacity: 0;
                transform: translateY(24px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @keyframes fadeInCellStagger {
            from {
                opacity: 0;
                transform: scale(0.98) translateY(20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* Event Table Animations */

        .event-table-container {
            overflow-x: auto;
            margin-top: 10px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 10px rgba(44,62,80,0.09);
            padding: 16px;
            width: 100%;
            box-sizing: border-box;
            animation: fadeInUpRowStagger 0.45s both;
        }

        table.event-table {
            border-collapse: collapse;
            min-width: 1200px;
            width: 100%;
        }

        /* Staggered Animation for table rows (appearing one by one) */
        .event-table tr {
            opacity: 0;
            animation: fadeInUpRowStagger 0.44s both;
        }
        .event-table tr:nth-child(1)   { animation-delay: 0.07s; opacity: 1;}
        .event-table tr:nth-child(2)   { animation-delay: 0.14s; opacity: 1;}
        .event-table tr:nth-child(3)   { animation-delay: 0.21s; opacity: 1;}
        .event-table tr:nth-child(4)   { animation-delay: 0.28s; opacity: 1;}
        .event-table tr:nth-child(5)   { animation-delay: 0.35s; opacity: 1;}
        .event-table tr:nth-child(6)   { animation-delay: 0.42s; opacity: 1;}
        .event-table tr:nth-child(7)   { animation-delay: 0.49s; opacity: 1;}
        .event-table tr:nth-child(8)   { animation-delay: 0.56s; opacity: 1;}
        .event-table tr:nth-child(9)   { animation-delay: 0.63s; opacity: 1;}
        .event-table tr:nth-child(10)  { animation-delay: 0.70s; opacity: 1;}
        .event-table tr:nth-child(11)  { animation-delay: 0.77s; opacity: 1;}
        .event-table tr:nth-child(12)  { animation-delay: 0.84s; opacity: 1;}
        /* Add more nth-childs as needed for up to your typical page size */

        /* Staggered Animation for table cells */
        .event-table th, .event-table td {
            padding: 9px 10px;
            text-align: left;
            border-bottom: 1px solid #e6e7f0;
            font-size: 15px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 440px;
            vertical-align: middle;
            opacity: 0;
            animation: fadeInCellStagger 0.35s both;
        }
        .event-table tr:nth-child(1) th, 
        .event-table tr:nth-child(1) td { animation-delay: 0.10s; opacity: 1;}
        .event-table tr:nth-child(2) th, 
        .event-table tr:nth-child(2) td { animation-delay: 0.18s; opacity: 1;}
        .event-table tr:nth-child(3) th, 
        .event-table tr:nth-child(3) td { animation-delay: 0.27s; opacity: 1;}
        .event-table tr:nth-child(4) th, 
        .event-table tr:nth-child(4) td { animation-delay: 0.36s; opacity: 1;}
        .event-table tr:nth-child(5) th, 
        .event-table tr:nth-child(5) td { animation-delay: 0.45s; opacity: 1;}
        .event-table tr:nth-child(6) th, 
        .event-table tr:nth-child(6) td { animation-delay: 0.54s; opacity: 1;}
        .event-table tr:nth-child(7) th, 
        .event-table tr:nth-child(7) td { animation-delay: 0.63s; opacity: 1;}
        .event-table tr:nth-child(8) th, 
        .event-table tr:nth-child(8) td { animation-delay: 0.72s; opacity: 1;}
        .event-table tr:nth-child(9) th, 
        .event-table tr:nth-child(9) td { animation-delay: 0.81s; opacity: 1;}
        .event-table tr:nth-child(10) th, 
        .event-table tr:nth-child(10) td { animation-delay: 0.90s; opacity: 1;}
        .event-table tr:nth-child(11) th, 
        .event-table tr:nth-child(11) td { animation-delay: 0.99s; opacity: 1;}
        .event-table tr:nth-child(12) th, 
        .event-table tr:nth-child(12) td { animation-delay: 1.06s; opacity: 1;}
        /* Add more as you need */

        .event-table th.description-cell, .event-table td.description-cell {
            white-space: normal;
            max-width: 330px;
            min-width: 180px;
        }
        .event-table th, .event-table td {
            min-width: 100px;
        }
        .event-table th.event_banner_image, .event-table td.event_banner_image,
        .event-table th.event_gallery_images, .event-table td.event_gallery_images {
            min-width: 120px;
            max-width: 200px;
        }
        .event-table th {
            background: #f4f6fb;
            color: #322053;
            font-weight: 600;
            border-top: 1px solid #e6e7f0;
        }
        .event-table tr:nth-child(even) {
            background: #f9fafe;
        }
        .event-table tr:hover {
            background: #f2f4fa;
            transition: background 0.25s;
        }
        .event-table td .event-banner-thumb,
        .event-table td .event-gallery-thumb {
            max-width: 85px;
            max-height: 56px;
            display: block;
            border-radius: 5px;
            margin-bottom:5px;
            transition: box-shadow 0.25s;
            box-shadow: 0 2px 9px rgba(44,62,80,0.07);
            opacity: 0;
            animation: fadeInCellStagger 0.32s both;
        }
        .event-table tr:nth-child(1) .event-banner-thumb,
        .event-table tr:nth-child(1) .event-gallery-thumb { animation-delay: 0.17s; opacity: 1;}
        .event-table tr:nth-child(2) .event-banner-thumb,
        .event-table tr:nth-child(2) .event-gallery-thumb { animation-delay: 0.32s; opacity: 1;}
        .event-table tr:nth-child(3) .event-banner-thumb,
        .event-table tr:nth-child(3) .event-gallery-thumb { animation-delay: 0.47s; opacity: 1;}
        /* etc., for more rows if you wish */

        .table-edit-select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background: #fff url("data:image/svg+xml;utf8,<svg fill='gray' height='22' viewBox='0 0 24 24' width='22'><path d='M7 10l5 5 5-5z' /></svg>") no-repeat right 12px center/1.2em 1.2em;
            border: 1px solid #bfc4d1;
            padding: 7px 29px 7px 12px;
            font-size: 15px;
            border-radius: 5px;
            color: #312153;
            min-width: 112px;
            outline: none;
            transition: border .15s, box-shadow .18s;
            cursor: pointer;
            margin-right: 5px;
            opacity: 0;
            animation: fadeInCellStagger 0.39s both;
        }
        .event-table tr:nth-child(1) .table-edit-select { animation-delay: 0.16s; opacity: 1;}
        .event-table tr:nth-child(2) .table-edit-select { animation-delay: 0.27s; opacity: 1;}
        .event-table tr:nth-child(3) .table-edit-select { animation-delay: 0.37s; opacity: 1;}
        /* etc., if you want per-row select input animation */

        .table-edit-select:focus {
            border-color: #523ad5;
            background-color: #fafbff;
            box-shadow: 0 0 2.4px #b6aff5;
        }
        .inline-dropdown-spinner {
            vertical-align: middle; 
            margin-left: 6px; 
            height: 20px;
            width: 20px;
            display: inline-block;
            opacity: 0;
            animation: fadeInCellStagger 0.28s both;
        }
        .event-table td .delete-btn {
            border: none;
            border-radius: 5px;
            padding: 7px 16px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: background 0.22s, transform 0.14s;
            background: linear-gradient(90deg, #e94242 20%, #b02626 80%);
            color: #fff;
            margin-left: 0;
            box-shadow: 0 1px 3px rgba(200,55,55,0.07);
            opacity: 0;
            animation: fadeInCellStagger 0.32s both;
        }
        .event-table tr:nth-child(1) .delete-btn { animation-delay: 0.16s; opacity: 1;}
        .event-table tr:nth-child(2) .delete-btn { animation-delay: 0.27s; opacity: 1;}
        .event-table tr:nth-child(3) .delete-btn { animation-delay: 0.37s; opacity: 1;}
        .event-table td .delete-btn:hover {
            background: linear-gradient(90deg, #a51818, #e94242 60%);
            transform: scale(1.07);
        }
        .event-table td .edit-btn {
            border: none;
            border-radius: 5px;
            padding: 7px 16px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: background 0.18s, transform 0.14s;
            background: linear-gradient(90deg, #327ac5 20%, #225085 80%);
            color: #fff;
            margin-right: 8px;
            box-shadow: 0 1px 3px rgba(50,122,197,0.07);
            opacity: 0;
            animation: fadeInCellStagger 0.39s both;
        }
        .event-table tr:nth-child(1) .edit-btn { animation-delay: 0.16s; opacity: 1;}
        .event-table tr:nth-child(2) .edit-btn { animation-delay: 0.27s; opacity: 1;}
        .event-table tr:nth-child(3) .edit-btn { animation-delay: 0.37s; opacity: 1;}
        .event-table td .edit-btn:hover {
            background: linear-gradient(90deg, #225085, #327ac5 60%);
            transform: scale(1.07);
        }
        .events-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .create-event-btn {
            background: linear-gradient(90deg, #2d397a, #594285 90%);
            color: #fff;
            padding: 8px 20px;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-left: 6px;
            transition: background .18s, box-shadow .20s;
            letter-spacing: 0.02em;
            box-shadow: 0 2px 9px rgb(82 58 213 / 8%);
            opacity: 0;
            animation: fadeInUpRowStagger 0.49s 0.19s both;
        }
        .create-event-btn:hover {
            background: linear-gradient(90deg, #594285, #2d397a 100%);
            box-shadow: 0 4px 12px rgb(82 58 213 / 16%);
        }
        .alert-message {
            padding: 8px 16px;
            border-radius: 6px;
            margin: 12px 0;
            font-size: 15px;
            color: #fff;
            opacity: 0;
            animation: fadeInUpRowStagger 0.37s 0.12s both;
        }
        .alert-success { background: #27a74e; }
        .alert-error { background: #c82f2f; }

        @media (max-width: 900px) {
            table.event-table { min-width: 800px; font-size: 14px; }
        }
        .internal-header {
            margin: 0;
            color: #322053;
            opacity: 0;
            animation: fadeInUpRowStagger 0.33s 0.08s both;
        }
        .internal-no-events {
            text-align: center;
            color: #322053;
            padding: 32px 5px;
            font-size: 1.08em;
            opacity: 0;
            animation: fadeInUpRowStagger 0.33s 0.23s both;
        }
        .internal-description-cell-div {
            white-space: normal;
            max-width: 330px;
            overflow-x: auto;
        }
        .internal-created-updated {
            white-space: normal;
            font-size: 13px;
            color: #57597A;
        }
        .internal-no-image {
            color: #c82f2f;
            font-size: 12px;
        }

        body { overflow-x: hidden; }
        .success-message {
            color: #228a36;
            background: #e8fdeb;
            border: 1px solid #a8dfb1;
            padding: 9px 15px;
            border-radius: 7px;
            margin: 15px 0 14px 0;
            font-weight: 600;
            font-size: 16px;
            max-width: 390px;
            /* Make visible always for this context: */
            display: block;
            opacity: 0;
            animation: fadeInUpRowStagger 0.32s 0.21s both;
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
            opacity: 0;
            animation: fadeInUpRowStagger 0.32s 0.27s both;
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
            opacity: 0;
            animation: fadeInCellStagger 0.27s 0.13s both;
        }
        .booking-status-select:focus {
            border-color: #aa97eb;
            background: #f3f0fa;
        }
        .booking-status-select::-ms-expand {
            display: none;
        }

        /* ---------------------------- */
        /* Pagination Styles Separated  */
        /* ---------------------------- */
        /* Classic Pagination Style */
        .classic-pagination {
            margin: 20px 0 0 0;
            text-align: center;
            opacity: 0;
            animation: fadeInUpRowStagger 0.35s 0.33s both;
        }
        .classic-pagination ul {
            display: inline-block;
            padding: 0;
            margin: 0;
            border: 1px solid #bbb;
            border-radius: 4px;
            background: #fafafa;
        }
        .classic-pagination li {
            display: inline;
        }
        .classic-pagination a, .classic-pagination span {
            color: #222;
            float: left;
            padding: 6px 16px;
            text-decoration: none;
            background: none;
            border-right: 1px solid #ddd;
            font-size: 15px;
            line-height: 24px;
            min-width: 30px;
            box-sizing: border-box;
            border-radius: 0;
            transition: background 0.13s;
            opacity: 0;
            animation: fadeInCellStagger 0.17s both;
        }
        .classic-pagination li:nth-child(1) a, .classic-pagination li:nth-child(1) span { animation-delay: 0.09s; opacity: 1;}
        .classic-pagination li:nth-child(2) a, .classic-pagination li:nth-child(2) span { animation-delay: 0.15s; opacity: 1;}
        .classic-pagination li:nth-child(3) a, .classic-pagination li:nth-child(3) span { animation-delay: 0.21s; opacity: 1;}
        .classic-pagination li:nth-child(4) a, .classic-pagination li:nth-child(4) span { animation-delay: 0.27s; opacity: 1;}
        .classic-pagination li:nth-child(5) a, .classic-pagination li:nth-child(5) span { animation-delay: 0.33s; opacity: 1;}
        /* Add more for more paginated buttons */

        .classic-pagination li:last-child a,
        .classic-pagination li:last-child span {
            border-right: 0;
        }
        .classic-pagination a:hover {
            background: #e9e9e9;
            color: #111;
        }
        .classic-pagination .active, .classic-pagination .active:hover, .classic-pagination .active:focus {
            background: #f1f1f1;
            font-weight: 700;
            color: #184090;
            cursor: default;
        }
        .classic-pagination .disabled,
        .classic-pagination .disabled:hover {
            background: none !important;
            color: #bbb !important;
            cursor: default;
            pointer-events: none;
        }

        @media (max-width: 600px) {
            .classic-pagination ul { display: block; }
            .classic-pagination a, .classic-pagination span {
                float: none;
                display: inline-block;
                padding: 7px 10px;
                font-size: 15px;
            }
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
    <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 18px;">
        <h2 class="internal-header" style="margin: 0; white-space: nowrap;">Manage Bookings</h2>
        <form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; flex: 1; justify-content: center;">
            <input type="text" name="search_user_email" value="<?=esc($search_user_email)?>" placeholder="User Email" style="padding:6px 8px; border-radius:5px; border:1px solid #d1d2dd; font-size:14px;">
            <input type="text" name="search_event_title" value="<?=esc($search_event_title)?>" placeholder="Event Title" style="padding:6px 8px; border-radius:5px; border:1px solid #d1d2dd; font-size:14px;">
            <input type="date" name="search_booked_at" value="<?=esc($search_booked_at)?>" style="padding:5px 8px; border-radius:5px; border:1px solid #d1d2dd; font-size:14px;">
            <button type="submit" style="background:linear-gradient(90deg, #594285, #2d397a 90%);color:#fff;font-weight:700;padding:7px 16px;border-radius:6px;border:none;font-size:14px;">Search</button>
            <?php if ($search_user_email || $search_event_title || $search_booked_at): ?>
                <a href="<?=esc($_SERVER['PHP_SELF'])?>" style="background:#f4f6fb;border:1px solid #c9c9de;color:#312053;font-size:14px;padding:6px 11px;border-radius:6px;text-decoration:none;">Clear</a>
            <?php endif; ?>
        </form>
        <button class="create-booking-btn create-event-btn" type="button" style="white-space: nowrap;">New Booking</button>
    </div>
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
                        'event_title'    => 'Event Title',
                        'user_email'     => 'User Email',
                        'persons'        => 'Persons',
                        'booking_status' => 'Booking Status',
                        'booked_at'      => 'Booked At',
                        'updated_at'     => 'Updated At'
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
                        <?php
                        $sp = $_GET;
                        $max_page_links = 5;
                        $page_window = floor($max_page_links / 2);
                        $start_page = max(1, $page - $page_window);
                        $end_page = min($total_pages, $page + $page_window);
                        if ($end_page - $start_page + 1 < $max_page_links) {
                            if ($start_page == 1) $end_page = min($total_pages, $start_page + $max_page_links - 1);
                            if ($end_page == $total_pages) $start_page = max(1, $end_page - $max_page_links + 1);
                        }
                        if ($page > 1): $sp['page'] = $page - 1; ?>
                            <li><a href="?<?=http_build_query($sp)?>" class="pagination-btn-go prev">Prev</a></li>
                        <?php endif; ?>
                        <?php if ($start_page > 1): $sp['page'] = 1; ?>
                            <li><a href="?<?=http_build_query($sp)?>"><?= 1 ?></a></li>
                            <?php if ($start_page > 2): ?><li><span>&#8230;</span></li><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($p = $start_page; $p <= $end_page; $p++): $sp['page'] = $p; ?>
                            <li>
                                <?php if ($p == $page): ?>
                                    <span class="current-page"><?= $p ?></span>
                                <?php else: ?>
                                    <a href="?<?=http_build_query($sp)?>"><?= $p ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor; ?>
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?><li><span>&#8230;</span></li><?php endif; ?>
                            <?php $sp['page'] = $total_pages; ?>
                            <li><a href="?<?=http_build_query($sp)?>"><?= $total_pages ?></a></li>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): $sp['page'] = $page + 1; ?>
                            <li><a href="?<?=http_build_query($sp)?>" class="pagination-btn-go next">Next</a></li>
                        <?php endif; ?>
                    </ul>   
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
