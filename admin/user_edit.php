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
    'confirm_user_password' => '',
    'registered_at' => '',
    'last_login' => '',
];

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Improved phone validation: allow any 10 digits except all 0/1, repeating >6, incrementing runs, etc
function is_invalid_phone($phone) {
    $phone = preg_replace('/\D/', '', $phone);

    // Must be exactly 10 digits numeric
    if (strlen($phone) !== 10) return true;

    // Must not be all zeros or ones, or start with 0 or 1 (commonly for mobile, adjust as per locale)
    if (preg_match('/^(0{10}|1{10})$/', $phone)) return true;

    // Must not have one digit repeated 6 or more times (e.g. 1111110000)
    if (preg_match('/(\d)\1{5,}/', $phone)) return true;

    // Must not contain common incrementing patterns (to block fake/test numbers)
    if (preg_match('/012345|123456|234567|345678|456789/', $phone)) return true;

    return false;
}

// --- Password strength validator for server-side (one uppercase, one lowercase, one digit, one special char, min 8 chars) ---
function is_invalid_password($password) {
    if (strlen($password) < 8)
        return true;
    if (!preg_match('/[A-Z]/', $password))
        return true;
    if (!preg_match('/[a-z]/', $password))
        return true;
    if (!preg_match('/[0-9]/', $password))
        return true;
    if (!preg_match('/[\W_]/', $password)) // special char (including underscore)
        return true;
    return false;
}

// Validate timestamp, must be in "YYYY-MM-DDTHH:MM" (HTML5-local-datetime format)
function is_invalid_timestamp($dtstr) {
    return !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dtstr);
}

function datetime_local_to_mysql($dtstr) {
    if (empty($dtstr)) return null;
    // Convert from 'YYYY-MM-DDTHH:MM' to 'YYYY-MM-DD HH:MM:SS'
    return date('Y-m-d H:i:s', strtotime($dtstr));
}

// Fetch user_id from GET
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($user_id <= 0) {
    $error = "Invalid User ID.";
} else {
    // Fetch user data from DB
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();
    } else {
        $user = null;
        $error = "User not found.";
    }
    $stmt->close();
}

$orig_values = [
    'user_name' => $user['user_name'] ?? '',
    'user_email' => $user['user_email'] ?? '',
    'user_phone_number' => $user['user_phone_number'] ?? '',
    'user_address' => $user['user_address'] ?? '',
    'user_status' => $user['user_status'] ?? 'active',
    'user_type' => $user['user_type'] ?? 'user',
    'registered_at' => !empty($user['registered_at']) ? substr($user['registered_at'],0,16) : '',
    'last_login' => !empty($user['last_login']) ? substr($user['last_login'],0,16) : '',
];

// On submit, validate fields and update user in DB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id > 0 && $user) {
    $user_name = trim($_POST['user_name'] ?? '');
    $user_email = trim($_POST['user_email'] ?? '');
    $user_phone_number = trim($_POST['user_phone_number'] ?? '');
    $user_address = trim($_POST['user_address'] ?? '');
    $user_status = $_POST['user_status'] ?? 'active';
    $user_type = $_POST['user_type'] ?? 'user';
    $user_password = $_POST['user_password'] ?? '';
    $confirm_user_password = $_POST['confirm_user_password'] ?? '';
    $registered_at = trim($_POST['registered_at'] ?? '');
    $last_login = trim($_POST['last_login'] ?? '');

    // Retain/edit values
    $orig_values = [
        'user_name' => $user_name,
        'user_email' => $user_email,
        'user_phone_number' => $user_phone_number,
        'user_address' => $user_address,
        'user_status' => $user_status,
        'user_type' => $user_type,
        'registered_at' => $registered_at,
        'last_login' => $last_login,
    ];

    // Field validations
    if (empty($user_name)) {
        $field_errors['user_name'] = 'Full Name is required.';
    } elseif (mb_strlen($user_name) < 2) {
        $field_errors['user_name'] = 'Full Name must be at least 2 characters.';
    }
    if (empty($user_email)) {
        $field_errors['user_email'] = 'Email Address is required.';
    } elseif (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $field_errors['user_email'] = 'Please enter a valid Email Address.';
    } else {
        // Check if email already exists for other users
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_email=? AND user_id<>?");
        $stmt->bind_param("si", $user_email, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $field_errors['user_email'] = 'Email already exists.';
        }
        $stmt->close();
    }
    if (empty($user_phone_number)) {
        $field_errors['user_phone_number'] = 'Phone number is required.';
    } else {
        // Accept dashes/spaces but must be 10 digits after stripping
        $digits_only = preg_replace('/\D/', '', $user_phone_number);
        if (is_invalid_phone($digits_only)) {
            $field_errors['user_phone_number'] = 'Please enter a valid 10-digit phone number.';
        }
    }

    // Address optional but sanitize
    $user_address = trim($user_address);

    // --- Validate registered_at & last_login ---
    $now = date('Y-m-d\TH:i'); // Current in local form
    if (empty($registered_at)) {
        $field_errors['registered_at'] = 'Registered At is required.';
    } elseif (is_invalid_timestamp($registered_at)) {
        $field_errors['registered_at'] = 'Registered At value is invalid.';
    } elseif ($registered_at > $now) {
        $field_errors['registered_at'] = 'Registered At cannot be in the future.';
    }
    if (empty($last_login)) {
        $field_errors['last_login'] = 'Last Login is required.';
    } elseif (is_invalid_timestamp($last_login)) {
        $field_errors['last_login'] = 'Last Login value is invalid.';
    }
    // Only check this validation if both are not already invalid
    if (empty($field_errors['registered_at']) && empty($field_errors['last_login'])) {
        if ($last_login < $registered_at) {
            $field_errors['last_login'] = 'Last Login cannot be before Registered At.';
        }
    }

    // Password logic
    $update_password = false;
    if (!empty($user_password) || !empty($confirm_user_password)) {
        if (empty($user_password)) {
            $field_errors['user_password'] = "Password is required if changing.";
        } elseif (is_invalid_password($user_password)) {
            $field_errors['user_password'] = "Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a digit, and a special character.";
        }
        if (empty($confirm_user_password)) {
            $field_errors['confirm_user_password'] = "Please confirm password.";
        }
        if ($user_password !== $confirm_user_password) {
            $field_errors['confirm_user_password'] = "Passwords do not match.";
        }
        if (empty($field_errors['user_password']) && empty($field_errors['confirm_user_password']))
            $update_password = true;
    }

    // Status/type (not validated further, enums)
    if (!in_array($user_status, ['active','inactive'])) {
        $user_status = 'active';
    }
    if (!in_array($user_type, ['admin','user'])) {
        $user_type = 'user';
    }

    // Check for errors
    $has_error = false;
    foreach($field_errors as $fe)
        if (!empty($fe)) { $has_error = true; break; }

    if (!$has_error) {
        // Update query
        $upd_registered_at = datetime_local_to_mysql($registered_at);
        $upd_last_login = datetime_local_to_mysql($last_login);
        if ($update_password) {
            $password_hash = password_hash($user_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET user_name=?, user_email=?, user_phone_number=?, user_address=?, user_status=?, user_type=?, user_password=?, registered_at=?, last_login=? WHERE user_id=?");
            $stmt->bind_param("sssssssssi", $user_name, $user_email, $user_phone_number, $user_address, $user_status, $user_type, $password_hash, $upd_registered_at, $upd_last_login, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET user_name=?, user_email=?, user_phone_number=?, user_address=?, user_status=?, user_type=?, registered_at=?, last_login=? WHERE user_id=?");
            $stmt->bind_param("ssssssssi", $user_name, $user_email, $user_phone_number, $user_address, $user_status, $user_type, $upd_registered_at, $upd_last_login, $user_id);
        }
        if ($stmt->execute()) {
            $success = "User updated successfully.";
            // Refetch updated user data
            $stmt->close();
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();
            $orig_values = [
                'user_name' => $user['user_name'] ?? '',
                'user_email' => $user['user_email'] ?? '',
                'user_phone_number' => $user['user_phone_number'] ?? '',
                'user_address' => $user['user_address'] ?? '',
                'user_status' => $user['user_status'] ?? 'active',
                'user_type' => $user['user_type'] ?? 'user',
                'registered_at' => !empty($user['registered_at']) ? substr($user['registered_at'],0,16) : '',
                'last_login' => !empty($user['last_login']) ? substr($user['last_login'],0,16) : '',
            ];
            $field_errors = [
                'user_name' => '',
                'user_email' => '',
                'user_phone_number' => '',
                'user_address' => '',
                'user_password' => '',
                'confirm_user_password' => '',
                'registered_at' => '',
                'last_login' => '',
            ];
        } else {
            $error = "Failed to update user. Try again.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <style>
        /* Add user_create style overrides for form appearance/fields as needed */
.userform-label {
    font-size: 15px;
    color: #3b365c;
    font-weight: 500;
    margin-bottom: 5px;
    display: block;
}
.userform-input, .userform-select {
    width: 100%;
    padding: 9px 13px;
    border-radius: 7px;
    border: 1.3px solid #d2d2e2;
    font-size: 15.5px;
    box-sizing: border-box;
    margin-bottom: 2px;
    background: #fff;
    transition: border 0.2s;
}
.userform-input:focus, .userform-select:focus {
    outline: none;
    border-color: #2d397a;
}
.userform-error {
    color: #e71515;
    font-size: 12.5px;
    margin-bottom: 9px;
    margin-top: 1px;
}
.userform-success {
    background: #d4ffe5;
    color: #176c2b;
    border-radius: 8px;
    padding: 12px 20px;
    margin-bottom: 15px;
    text-align: center;
    font-size: 16px;
    width: 100%;
    max-width: 435px;
    margin-left: auto;
    margin-right: auto;
    display: block;
}
.userform-fail {
    background: #ffd3d6;
    color: #9c2a2a;
    border-radius: 8px;
    padding: 12px 20px;
    margin-bottom: 15px;
    text-align: center;
    font-size: 16px;
    max-width: 435px;
    margin-left: auto;
    margin-right: auto;
    display: block;
    width: 100%;
}
.userform-form {
    background: #f7f8fe;
    padding: 30px 25px 20px 25px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(70,65,110,0.06);
    max-width: 435px;
    margin: 0 auto;
    /* Add relative pos so .userform-heading sits nicely inside it if needed */
}
.userform-heading {
    font-size: 1.5em;
    margin-bottom: 22px;
    font-weight: 700;
    text-align: center;
    /* Optionally add background, border or shadow for visual emphasis */
    padding: 7px 0 17px 0;
    color: #2d397a;
}
.userform-btn {
    background: linear-gradient(90deg,#2d397a,#594285);
    color: #fff;
    padding: 8px 0;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
    font-size: 16.5px;
    width: 100%;
    transition: background 0.2s;
    margin-top: 7px;
    margin-bottom: 2px;
}
.userform-btn:hover {
    background: linear-gradient(90deg, #594285, #2d397a);
}body {
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

    </style>
    <!-- <link rel="stylesheet" href="css/index.css"> -->
    <!-- <link rel="stylesheet" href="css/users.css"> -->
    <!-- <link rel="stylesheet" href="css/user_edit.css"> -->
    <script src="js/jquery-4.0.0.min.js"></script>

</head>
<body>
<div class="dashboard-main" style="max-width:510px; margin:40px auto 0 auto;">
    <?php if ($error): ?>
        <div class="userform-fail"><?php echo esc($error); ?></div>
    <?php elseif ($success): ?>
        <div class="userform-success"><?php echo esc($success); ?></div>
    <?php endif; ?>
    <?php if (!$user): ?>
        <a href="users.php" class="create-event-btn" style="text-decoration:none;">&larr; Back to Users</a>
    <?php else: ?>
    <form method="POST" autocomplete="off" class="userform-form" id="edituserform" novalidate>
        <div class="userform-heading">Edit User</div>
        <div style="margin-bottom:17px;">
            <label for="user_name" class="userform-label">Full Name</label>
            <input type="text" name="user_name" id="user_name" class="userform-input" value="<?php echo esc($orig_values['user_name']); ?>" required>
            <div class="userform-error" id="user_name_err"><?php echo esc($field_errors['user_name']); ?></div>
        </div>
        <div style="margin-bottom:17px;">
            <label for="user_email" class="userform-label">Email Address</label>
            <input type="text" name="user_email" id="user_email" class="userform-input" value="<?php echo esc($orig_values['user_email']); ?>" required>
            <div class="userform-error" id="user_email_err"><?php echo esc($field_errors['user_email']); ?></div>
        </div>
        <div style="margin-bottom:17px;">
            <label for="user_phone_number" class="userform-label">Phone Number</label>
            <input type="text" name="user_phone_number" id="user_phone_number" class="userform-input" maxlength="10" value="<?php echo esc($orig_values['user_phone_number']); ?>" required>
            <div class="userform-error" id="user_phone_number_err"><?php echo esc($field_errors['user_phone_number']); ?></div>
        </div>
        <div style="margin-bottom:17px;">
            <label for="user_address" class="userform-label">Address</label>
            <input type="text" name="user_address" id="user_address" class="userform-input" value="<?php echo esc($orig_values['user_address']); ?>">
            <div class="userform-error" id="user_address_err"><?php echo esc($field_errors['user_address']); ?></div>
        </div>
        <div style="margin-bottom:17px;">
            <label for="user_status" class="userform-label">User Status</label>
            <select name="user_status" id="user_status" class="userform-select">
                <option value="active" <?php if($orig_values['user_status']=='active') echo 'selected'; ?>>Active</option>
                <option value="inactive" <?php if($orig_values['user_status']=='inactive') echo 'selected'; ?>>Inactive</option>
            </select>
        </div>
        <div style="margin-bottom:17px;">
            <label for="user_type" class="userform-label">User Type</label>
            <select name="user_type" id="user_type" class="userform-select">
                <option value="user" <?php if($orig_values['user_type']=='user') echo 'selected'; ?>>User</option>
                <option value="admin" <?php if($orig_values['user_type']=='admin') echo 'selected'; ?>>Admin</option>
            </select>
        </div>
        <div style="margin-bottom:17px;">
            <label for="registered_at" class="userform-label">Registered At</label>
            <input type="datetime-local" name="registered_at" id="registered_at" class="userform-input" value="<?php echo esc($orig_values['registered_at']); ?>" max="<?php echo date('Y-m-d\TH:i'); ?>">
            <div class="userform-error" id="registered_at_err"><?php echo esc($field_errors['registered_at']); ?></div>
        </div>
        <div style="margin-bottom:17px;">
            <label for="last_login" class="userform-label">Last Login</label>
            <input type="datetime-local" name="last_login" id="last_login" class="userform-input" value="<?php echo esc($orig_values['last_login']); ?>">
            <div class="userform-error" id="last_login_err"><?php echo esc($field_errors['last_login']); ?></div>
        </div>
        <div style="margin-bottom:17px;">
            <label for="user_password" class="userform-label">New Password <span style="font-weight:400;color:#b3b3b3;">(Leave blank to keep current)</span></label>
            <input type="password" name="user_password" id="user_password" class="userform-input" value="">
            <div class="userform-error" id="user_password_err"><?php echo esc($field_errors['user_password']); ?></div>
        </div>
        <div style="margin-bottom:24px;">
            <label for="confirm_user_password" class="userform-label">Confirm New Password</label>
            <input type="password" name="confirm_user_password" id="confirm_user_password" class="userform-input" value="">
            <div class="userform-error" id="confirm_user_password_err"><?php echo esc($field_errors['confirm_user_password']); ?></div>
        </div>
        <button type="submit" class="userform-btn" id="submitBtn">Update User</button>
    </form>
    <div style="margin-top:23px; text-align:center;">
        <a href="users.php" class="edit-btn" style="text-decoration:none;padding:9px 26px;">&larr; Back to Users</a>
    </div>
    <?php endif; ?>
</div>

<script>
// Client-side phone validation matches server
function is_invalid_email(email) {
    return !/^([a-zA-Z0-9_\.-]+)@([a-zA-Z0-9-]+\.)+([a-zA-Z0-9]{2,})$/.test(email);
}
function is_invalid_phone(phone) {
    var cleaned = phone.replace(/\D/g, '');
    if (cleaned.length !== 10) return true;
    if (/^(0{10}|1{10})$/.test(cleaned)) return true;
    if (/(\d)\1{5,}/.test(cleaned)) return true;
    if (/012345|123456|234567|345678|456789/.test(cleaned)) return true;
    return false;
}

// Password validation: 1 uppercase, 1 lowercase, 1 digit, 1 special char, min 8 chars
function is_invalid_password(password) {
    if (password.length < 8) return true;
    if (!/[A-Z]/.test(password)) return true;
    if (!/[a-z]/.test(password)) return true;
    if (!/[0-9]/.test(password)) return true;
    if (!/[\W_]/.test(password)) return true;
    return false;
}

// Timestamp validation in JS, matches server
function is_invalid_datetime_local(val) {
    if (!val) return true;
    // Accept YYYY-MM-DDTHH:MM
    return !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(val);
}

function validate_field(field) {
    let v = field.val();
    let errId = "#" + field.attr('id') + "_err";
    let id = field.attr('id');
    let error = '';

    if (id === "user_name") {
        if (!v.trim()) error = "Full Name is required.";
        else if (v.length < 2) error = "Full Name must be at least 2 characters.";
    }
    else if (id === "user_email") {
        if (!v.trim()) error = "Email Address is required.";
        else if (is_invalid_email(v.trim())) error = "Please enter a valid Email Address.";
    }
    else if (id === "user_phone_number") {
        if (!v.trim()) error = "Phone number is required.";
        else if (is_invalid_phone(v)) error = "Please enter a valid 10-digit phone number.";
    }
    else if (id === "registered_at") {
        if (!v) error = "Registered At is required.";
        else if (is_invalid_datetime_local(v)) error = "Registered At value is invalid.";
        else if (v > new Date().toISOString().substring(0,16)) error = "Registered At cannot be in the future.";
    }
    else if (id === "last_login") {
        if (!v) error = "Last Login is required.";
        else if (is_invalid_datetime_local(v)) error = "Last Login value is invalid.";
        else {
            let reg = $('#registered_at').val();
            if (reg && !is_invalid_datetime_local(reg) && v < reg)
                error = "Last Login cannot be before Registered At.";
        }
    }
    else if (id === "user_password") {
        let other = $('#confirm_user_password').val();
        if (v || other) {
            if (!v) error = "Password is required if changing.";
            else if (is_invalid_password(v))
                error = "Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a digit, and a special character.";
        }
    }
    else if (id === "confirm_user_password") {
        let pass = $('#user_password').val();
        if (pass || v) {
            if (!v) error = "Please confirm password.";
            else if (pass !== v) error = "Passwords do not match.";
        }
    }

    $(errId).text(error);
    return error === '';
}

function validate_all() {
    let ok = true;
    $('#user_name, #user_email, #user_phone_number, #registered_at, #last_login, #user_password, #confirm_user_password').each(function() {
        if (!validate_field($(this))) ok = false;
    });
    return ok;
}

$(function(){
    $('#user_name, #user_email, #user_phone_number, #registered_at, #last_login, #user_password, #confirm_user_password').on('change input blur', function() {
        validate_field($(this));
    });

    $('#edituserform').on('submit', function(e) {
        $('.userform-error').text('');
        let valid = true;

        valid = validate_all();

        if (!valid) {
            e.preventDefault();
            let $firstError = $('.userform-error').filter(function(){ return $(this).text() !== ""; }).first();
            if ($firstError.length) {
                $('html,body').animate({scrollTop: $firstError.offset().top-70}, 200);
            }
            return false;
        }
    });
});
</script>
</body>
</html>
