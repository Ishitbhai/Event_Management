
<?php
session_start();
require_once('sidebar.php');
require_once('../database/db_connect.php');

// --- Initialize error messages ---
$error = '';
$success = '';
$field_errors = [
    'user_name' => '',
    'user_email' => '',
    'user_phone_number' => '',
    'user_address' => '',
    'user_password' => '',
    'confirm_user_password' => ''
];

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function is_invalid_phone($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    if (strlen($phone) !== 10) return true;
    if (preg_match('/^(0|1)+$/', $phone)) return true;
    if (preg_match('/(\d)\1{5,}/', $phone)) return true;
    if (preg_match('/012345|123456|234567|345678|456789/', $phone)) return true;
    return false;
}

// On submit, validate fields and retain error messages
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_name = trim($_POST['user_name'] ?? '');
    $user_email = trim($_POST['user_email'] ?? '');
    $user_phone_number = trim($_POST['user_phone_number'] ?? '');
    $user_address = trim($_POST['user_address'] ?? '');
    $user_password = $_POST['user_password'] ?? '';
    $confirm_user_password = $_POST['confirm_user_password'] ?? '';
    $user_status = $_POST['user_status'] ?? 'active';
    $user_type = $_POST['user_type'] ?? 'user';

    // Field validations
    if (empty($user_name)) {
        $field_errors['user_name'] = 'Full Name is required.';
    } elseif (mb_strlen($user_name) < 2) {
        $field_errors['user_name'] = 'Full Name must be at least 2 characters.';
    }
    if (empty($user_email)) {
        $field_errors['user_email'] = 'Email Address is required.';
    } elseif (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $field_errors['user_email'] = 'Invalid email address!';
    }
    if (empty($field_errors['user_email'])) {
        $q = $conn->prepare("SELECT user_id FROM users WHERE user_email=? LIMIT 1");
        $q->bind_param("s", $user_email);
        $q->execute();
        $q->store_result();
        if ($q->num_rows > 0) {
            $field_errors['user_email'] = 'Email already exists!';
        }
        $q->close();
    }
    if (empty($user_phone_number)) {
        $field_errors['user_phone_number'] = 'Phone number is required.';
    } elseif (!preg_match('/^\d{10}$/', preg_replace('/\D/', '', $user_phone_number))) {
        $field_errors['user_phone_number'] = 'Phone number must be exactly 10 digits.';
    } elseif (is_invalid_phone($user_phone_number)) {
        $field_errors['user_phone_number'] = 'Please enter a valid phone number.';
    }
    if (empty($user_address)) {
        $field_errors['user_address'] = 'Address is required.';
    }
    if (empty($user_password)) {
        $field_errors['user_password'] = 'Password is required.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $user_password)) {
        $field_errors['user_password'] = 'Password must be at least 8 chars, 1 uppercase, 1 lowercase, 1 digit, 1 special char.';
    }
    if (empty($confirm_user_password)) {
        $field_errors['confirm_user_password'] = 'Confirm Password is required.';
    } elseif ($user_password !== $confirm_user_password) {
        $field_errors['confirm_user_password'] = 'Passwords do not match!';
    }

    $has_errors = count(array_filter($field_errors)) > 0;

    if (!$has_errors) {
        $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);
        $user_token = bin2hex(random_bytes(16));

        $stmt = $conn->prepare("INSERT INTO users (user_name, user_email, user_phone_number, user_address, user_password, user_token, user_status, user_type, registered_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssss", $user_name, $user_email, $user_phone_number, $user_address, $hashed_password, $user_token, $user_status, $user_type);
        if ($stmt->execute()) {
            // Instead of displaying success here, redirect to users.php and store success in session
            $_SESSION['success_message'] = "User created successfully!";
            header("Location: users.php");
            exit;
        } else {
            $error = "Error: Could not create user. Please try again.";
        }
        $stmt->close();
    } else {
        $error = "Please correct the errors below.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create User</title>
    <style>
        
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
/* --- Animations for user table, values come in "one by one" --- */
@keyframes fadeInUpRowStaggerUser {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
@keyframes fadeInCellStaggerUser {
  from {
    opacity: 0;
    transform: scale(0.97) translateY(10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

body {
    font-size: 16px;
}
.dashboard-main {
    /* Ref: bookings.php plus more width, NOT full width (room for sidebar) */
    max-width: 1600px;
    min-width: 1020px;
    margin: 40px auto 0 auto;
    padding: 0 38px 38px 38px;
    font-size: 16px;
    /* Animate container */
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.60s 0.08s both;
}
.event-table-container {
    overflow-x:auto;
    margin-top:10px;
    background:#fff;
    border-radius:12px;
    box-shadow:0 1px 10px rgba(44,62,80,0.09);
    padding:24px 20px 24px 20px;
    /* Animate container */
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.45s 0.10s both;
}
table.event-table {
    border-collapse: collapse;
    min-width:1500px;
    width:100%;
    font-size: 16px;
    /* Animate table coming in */
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.45s 0.15s both;
}
/* Animate rows one by one */
.event-table tr {
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.37s both;
}
.event-table tr:nth-child(1) { animation-delay: 0.18s; }
.event-table tr:nth-child(2) { animation-delay: 0.23s; }
.event-table tr:nth-child(3) { animation-delay: 0.28s; }
.event-table tr:nth-child(4) { animation-delay: 0.33s; }
.event-table tr:nth-child(5) { animation-delay: 0.38s; }
.event-table tr:nth-child(n+6) { animation-delay: 0.43s; }
/* Animate each cell staggered inside their row */
.event-table th,
.event-table td {
    padding:10px 13px;
    border-bottom:1px solid #e6e7f0;
    font-size: 16px;
    white-space:nowrap;
    opacity: 0;
    animation: fadeInCellStaggerUser 0.28s both;
}
.event-table th:nth-child(1), .event-table td:nth-child(1) { animation-delay: 0.14s; }
.event-table th:nth-child(2), .event-table td:nth-child(2) { animation-delay: 0.19s; }
.event-table th:nth-child(3), .event-table td:nth-child(3) { animation-delay: 0.24s; }
.event-table th:nth-child(4), .event-table td:nth-child(4) { animation-delay: 0.29s; }
.event-table th:nth-child(5), .event-table td:nth-child(5) { animation-delay: 0.34s; }
.event-table th:nth-child(n+6), .event-table td:nth-child(n+6) { animation-delay: 0.39s; }
.event-table th{
    background:#f4f6fb;
    color:#322053;
    font-weight:600;
    font-size: 16px;
}
.event-table tr:nth-child(even){
    background:#f9fafe;
}
.create-event-btn{
    background:linear-gradient(90deg,#2d397a,#594285);
    color:#fff;
    padding:7px 20px;
    border:none;
    border-radius:7px;
    font-weight:700;
    cursor:pointer;
    font-size: 16px;
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.39s 0.32s both;
}
.table-edit-select {
    padding:7px 13px;
    border-radius:6px;
    border:1px solid #ccc;
    font-size: 16px;
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.36s 0.36s both;
}
.edit-btn{
    background:#327ac5;
    color:#fff;
    border:none;
    padding:7px 14px;
    border-radius:5px;
    cursor:pointer;
    font-size: 16px;
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.33s 0.40s both;
}
.delete-btn{
    background:#e94242;
    color:#fff;
    border:none;
    padding:7px 14px;
    border-radius:5px;
    cursor:pointer;
    font-size: 16px;
    opacity: 0;
    animation: fadeInUpRowStaggerUser 0.33s 0.44s both;
}

    /* Animations for user table, values come in "one by one" */
    @keyframes fadeInUpRowStaggerUser {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    @keyframes fadeInCellStaggerUser {
      from {
        opacity: 0;
        transform: scale(0.97) translateY(10px);
      }
      to {
        opacity: 1;
        transform: scale(1) translateY(0);
      }
    }

    html, body {
        font-size: 16px;
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        width: 100%;
    }

    body {
        min-height: 100vh;
        background: #f4f6fb;
    }

    .dashboard-main {
        max-width: 1600px;
        width: 100%;
        margin: 40px auto 0 auto;
        padding: 0 24px 24px 24px;
        font-size: 16px;
        box-sizing: border-box;
        background: transparent; /* Don't force bg, only for the table-container */
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.60s 0.08s both;
    }

    .event-table-container {
        overflow-x: auto;
        margin-top: 16px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 10px rgba(44,62,80,0.09);
        padding: 24px 12px 24px 12px;
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.45s 0.10s both;
        transition: box-shadow 0.2s;
    }

    table.event-table {
        border-collapse: collapse;
        min-width: 1200px;
        width: 100%;
        font-size: 16px;
        background: #fff;
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.45s 0.15s both;
        transition: font-size 0.25s;
    }
    /* Responsive: Table min-width is 900px on small screens, but can scroll. */
    @media (max-width: 1020px) {
        .dashboard-main {
            min-width: 0;
            padding: 0 4vw 24px 4vw;
        }
        .event-table-container {
            padding: 12px 3vw 16px 3vw;
            overflow-x: auto;
        }
        table.event-table {
            min-width: 900px;
            font-size: 15px;
        }
    }
    @media (max-width: 700px) {
        .dashboard-main {
            min-width: 0;
            padding: 0 1vw 8vw 1vw;
        }
        .event-table-container {
            padding: 4px 0 10px 0;
        }
        table.event-table {
            min-width:650px;
            font-size: 14px;
        }
    }
    /* Hide some columns on small screens with horizontal scrolling */
    @media (max-width: 600px) {
        .event-table th:nth-child(2), .event-table td:nth-child(2),
        .event-table th:nth-child(5), .event-table td:nth-child(5),
        .event-table th:nth-child(6), .event-table td:nth-child(6),
        .event-table th:nth-child(9), .event-table td:nth-child(9),
        .event-table th:nth-child(10), .event-table td:nth-child(10)
        {
            display: none;
        }
        .dashboard-main {
            padding: 0 0vw 14vw 0vw;
        }
    }

    /* Animations */
    .event-table tr {
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.37s both;
    }
    .event-table tr:nth-child(1) { animation-delay: 0.18s; }
    .event-table tr:nth-child(2) { animation-delay: 0.23s; }
    .event-table tr:nth-child(3) { animation-delay: 0.28s; }
    .event-table tr:nth-child(4) { animation-delay: 0.33s; }
    .event-table tr:nth-child(5) { animation-delay: 0.38s; }
    .event-table tr:nth-child(n+6) { animation-delay: 0.43s; }
    .event-table th,
    .event-table td {
        padding: 8px 6px;
        border-bottom: 1px solid #e6e7f0;
        font-size: inherit;
        white-space: nowrap;
        overflow: auto;
        opacity: 0;
        animation: fadeInCellStaggerUser 0.28s both;
    }
    .event-table th:nth-child(1), .event-table td:nth-child(1) { animation-delay: 0.14s; }
    .event-table th:nth-child(2), .event-table td:nth-child(2) { animation-delay: 0.19s; }
    .event-table th:nth-child(3), .event-table td:nth-child(3) { animation-delay: 0.24s; }
    .event-table th:nth-child(4), .event-table td:nth-child(4) { animation-delay: 0.29s; }
    .event-table th:nth-child(5), .event-table td:nth-child(5) { animation-delay: 0.34s; }
    .event-table th:nth-child(n+6), .event-table td:nth-child(n+6) { animation-delay: 0.39s; }
    .event-table th{
        background: #f4f6fb;
        color: #322053;
        font-weight: 600;
        font-size: inherit;
        text-align: left;
        position: sticky;
        top: 0;
        z-index: 2;
    }
    .event-table tr:nth-child(even){
        background: #f9fafe;
    }

    .create-event-btn{
        background: linear-gradient(90deg,#2d397a,#594285);
        color: #fff;
        padding: 7px 20px;
        border: none;
        border-radius: 7px;
        font-weight: 700;
        cursor: pointer;
        font-size: 16px;
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.39s 0.32s both;
        transition: background 0.15s;
    }
    .create-event-btn:hover, .edit-btn:hover {
        filter: brightness(1.13);
    }
    .edit-btn, .delete-btn {
        font-size: 16px;
        padding: 7px 14px;
        border-radius:5px;
        border:none;
        cursor:pointer;
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.33s 0.40s both;
        transition: filter 0.13s;
    }
    .edit-btn {
        background: #327ac5;
        color: #fff;
        margin-right: 4px;
    }
    .delete-btn {
        background: #e94242;
        color: #fff;
        margin-right: 0;
        animation-delay: 0.44s;
    }
    .table-edit-select {
        padding: 7px 13px;
        border-radius: 6px;
        border: 1px solid #ccc;
        font-size: inherit;
        min-width: 80px;
        opacity: 0;
        animation: fadeInUpRowStaggerUser 0.36s 0.36s both;
    }

    /* Responsive Header/Button row */
    .responsive-manage-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 0;
    }
    .responsive-manage-header h2 {
        font-size: 1.25em;
        margin: 0 0 8px 0;
    }
    @media (max-width: 700px) {
        .responsive-manage-header h2 {
            font-size: 1.1em;
        }
        .create-event-btn {
            font-size: 14px;
            padding: 7px 10px;
        }
    }
    @media (max-width: 480px) {
        .responsive-manage-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
    /* Responsive Message for no users */
    .no-user-msg {
        font-size: 15px;
        padding: 6vw 2vw 6vw 2vw;
        text-align: center;
        color: #722a2a;
    }

    /* Scrollbar styling for table containers */
    .event-table-container {
        scrollbar-width: thin;
        scrollbar-color: #b3b3e7 #f4f6fb;
    }
    .event-table-container::-webkit-scrollbar {
        height: 8px;
        background: #f4f6fb;
    }
    .event-table-container::-webkit-scrollbar-thumb {
        background: #babdea;
        border-radius: 4px;
    }
    .event-create-main-box {
    background: #fff;
    max-width: 560px;
    margin: 0 auto 0 auto;
    border-radius: 12px;
    padding: 40px 38px 38px 38px;
    box-shadow: 0 1px 10px rgba(44,62,80,0.09);
}
.form-label {
    font-weight: 500;
    margin-bottom: 4px;
}
.form-section {
    margin-bottom: 22px;
}
.form-control {
    padding: 9px 15px;
    font-size: 16px;
    border: 1px solid #dddddd;
    border-radius: 7px;
    width: 100%;
}
.btn-save {
    background: linear-gradient(90deg,#2d397a,#594285);
    color: #fff;
    border: none;
    padding: 9px 26px;
    border-radius: 7px;
    font-weight: 700;
    font-size: 17px;
    cursor: pointer;
}
.form-error, .text-danger {
    color: #e94242;
    margin-top: 3px;
    font-size: 13px;
}
.is-invalid { border-color: #e94242 !important; }

    </style>
    <!-- <link rel="stylesheet" href="css/events.css"> -->
    <!-- <link rel="stylesheet" href="css/index.css"> -->
    <!-- <link rel="stylesheet" href="css/users.css"> -->
    <!-- <link rel="stylesheet" href="css/user_create.css"> -->
    
    <script>
    // Client-side validation logic
    let formSubmitted = false;
    document.addEventListener("DOMContentLoaded", function() {
        const form = document.getElementById('user-create-form');
        const errorMsg = {
            user_name: {
                required: "Full Name is required.",
                minlen: "Full Name must be at least 2 characters."
            },
            user_email: {
                required: "Email Address is required.",
                invalid: "Invalid email address format."
            },
            user_phone_number: {
                required: "Phone number is required.",
                format: "Phone number must be exactly 10 digits.",
                invalid: "Please enter a valid phone number."
            },
            user_address: {
                required: "Address is required."
            },
            user_password: {
                required: "Password is required.",
                invalid: "Password must be at least 8 chars, 1 uppercase, 1 lowercase, 1 digit, 1 special char."
            },
            confirm_user_password: {
                required: "Confirm Password is required.",
                mismatch: "Passwords do not match!"
            }
        };

        function phoneCustomValidation(val) {
            let digits = val.replace(/\D/g, "");
            if (digits.length !== 10) return errorMsg.user_phone_number.format;
            if (/^(0|1)+$/.test(digits)) return errorMsg.user_phone_number.invalid;
            if (/(\d)\1{5,}/.test(digits)) return errorMsg.user_phone_number.invalid;
            if (/012345|123456|234567|345678|456789/.test(digits)) return errorMsg.user_phone_number.invalid;
            return "";
        }

        function validateField(field) {
            let val = field.value.trim();
            let name = field.name;
            let errDiv = document.getElementById(name + "_error");
            let err = "";

            if (name === "user_name") {
                if (!val) err = errorMsg.user_name.required;
                else if (val.length < 2) err = errorMsg.user_name.minlen;
            } else if (name === "user_email") {
                if (!val) err = errorMsg.user_email.required;
                else {
                    let valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
                    if (!valid) err = errorMsg.user_email.invalid;
                }
            } else if (name === "user_phone_number") {
                if (!val) err = errorMsg.user_phone_number.required;
                else {
                    let customErr = phoneCustomValidation(val);
                    if (customErr) err = customErr;
                }
            } else if (name === "user_address") {
                if (!val) err = errorMsg.user_address.required;
            } else if (name === "user_password") {
                if (!val) err = errorMsg.user_password.required;
                else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(val)) err = errorMsg.user_password.invalid;
            } else if (name === "confirm_user_password") {
                let pwd = document.getElementById('user_password').value;
                if (!val) err = errorMsg.confirm_user_password.required;
                else if (val !== pwd) err = errorMsg.confirm_user_password.mismatch;
            }
            if (errDiv) {
                errDiv.textContent = err;
                errDiv.style.display = err ? 'block' : 'none';
            }
            if (err) field.classList.add('is-invalid');
            else field.classList.remove('is-invalid');
            return err;
        }

        // Attach change listeners for validation (edit to always validate on change)
        Array.from(form.elements).forEach(function(elem) {
            if (["user_name", "user_email", "user_phone_number", "user_address", "user_password", "confirm_user_password"].includes(elem.name)) {
                elem.addEventListener('change', function(e) {
                    validateField(this, e); // added e=validation on change
                });
                elem.addEventListener('input', function(e) {
                    validateField(this, e); // instant feedback as well
                });
            }
        });

        // On change: after one submit, continual validate
        form.addEventListener('submit', function(e) {
            let hasError = false;
            formSubmitted = true;
            Array.from(form.elements).forEach(function(elem) {
                if (["user_name", "user_email", "user_phone_number", "user_address", "user_password", "confirm_user_password"].includes(elem.name)) {
                    let err = validateField(elem, e);
                    if (err) hasError = true;
                }
            });
            if (hasError) {
                e.preventDefault();
            }
        });
    });
    </script>
</head>
<body>
<div class="dashboard-main">
    <div class="event-create-main-box">
        <h2 style="margin-bottom: 18px;">Create User</h2>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger py-2" style="margin-bottom: 17px;"><?= esc($error) ?></div>
        <?php endif; ?>

        <form method="post" id="user-create-form" autocomplete="off" novalidate>
            <div class="form-section">
                <label class="form-label" for="user_name">Full Name</label>
                <input type="text" class="form-control<?= $field_errors['user_name'] ? ' is-invalid' : '' ?>" id="user_name" name="user_name" required value="<?= esc($_POST['user_name'] ?? '') ?>">
                <div id="user_name_error" class="form-error" style="<?= $field_errors['user_name'] ? '' : 'display:none;' ?>"><?= esc($field_errors['user_name']) ?></div>
            </div>
            <div class="form-section">
                <label class="form-label" for="user_email">Email Address</label>
                <input type="email" class="form-control<?= $field_errors['user_email'] ? ' is-invalid' : '' ?>" id="user_email" name="user_email" required value="<?= esc($_POST['user_email'] ?? '') ?>">
                <div id="user_email_error" class="form-error" style="<?= $field_errors['user_email'] ? '' : 'display:none;' ?>"><?= esc($field_errors['user_email']) ?></div>
            </div>
            <div class="form-section">
                <label class="form-label" for="user_phone_number">Phone Number</label>
                <input type="tel" class="form-control<?= $field_errors['user_phone_number'] ? ' is-invalid' : '' ?>" id="user_phone_number" name="user_phone_number" required pattern="\d{10}" value="<?= esc($_POST['user_phone_number'] ?? '') ?>">
                <div id="user_phone_number_error" class="form-error" style="<?= $field_errors['user_phone_number'] ? '' : 'display:none;' ?>"><?= esc($field_errors['user_phone_number']) ?></div>
            </div>
            <div class="form-section">
                <label class="form-label" for="user_address">Address</label>
                <input type="text" class="form-control<?= $field_errors['user_address'] ? ' is-invalid' : '' ?>" id="user_address" name="user_address" required value="<?= esc($_POST['user_address'] ?? '') ?>">
                <div id="user_address_error" class="form-error" style="<?= $field_errors['user_address'] ? '' : 'display:none;' ?>"><?= esc($field_errors['user_address']) ?></div>
            </div>
            <div class="form-section">
                <label class="form-label" for="user_password">Password</label>
                <input type="password" class="form-control<?= $field_errors['user_password'] ? ' is-invalid' : '' ?>" id="user_password" name="user_password" required>
                <div id="user_password_error" class="form-error" style="<?= $field_errors['user_password'] ? '' : 'display:none;' ?>"><?= esc($field_errors['user_password']) ?></div>
            </div>
            <div class="form-section">
                <label class="form-label" for="confirm_user_password">Confirm Password</label>
                <input type="password" class="form-control<?= $field_errors['confirm_user_password'] ? ' is-invalid' : '' ?>" id="confirm_user_password" name="confirm_user_password" required>
                <div id="confirm_user_password_error" class="form-error" style="<?= $field_errors['confirm_user_password'] ? '' : 'display:none;' ?>"><?= esc($field_errors['confirm_user_password']) ?></div>
            </div>
            <div class="form-section">
                <label class="form-label" for="user_status">Status</label>
                <select class="form-control" id="user_status" name="user_status">
                    <option value="active" <?= (($_POST['user_status'] ?? '') === 'active') ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= (($_POST['user_status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="form-section">
                <label class="form-label" for="user_type">User Type</label>
                <select class="form-control" id="user_type" name="user_type">
                    <option value="user"   <?= (($_POST['user_type'] ?? '') === 'user') ? 'selected' : '' ?>>User</option>
                    <option value="admin"  <?= (($_POST['user_type'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <button type="submit" class="btn-save">Create User</button>
            <a href="users.php" class="btn btn-secondary" style="margin-left:10px;vertical-align:middle;">Return to users</a>
        </form>
    </div>
</div>
</body>
</html>
