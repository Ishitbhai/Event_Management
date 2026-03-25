<?php
session_start();
require_once('sidebar.php');

// Only admins allowed
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    ?>
    <script>
        window.location.href = 'login.php';
    </script>
    <?php
    exit();
}

require_once('../database/db_connect.php');

// --- Edit contact info functionality ---
$contact_errors = [];
$contact_success = false;
$message_success = false;
$message_error = '';

// --- Handle status update for contact message, like events.php ---
$status_update_success = false;
$status_update_error = '';
if (isset($_POST['update_message_status'])) {
    $msg_id = (int)($_POST['message_id'] ?? 0);
    $valid_values = ['1', '0', 1, 0, 'true', 'false', true, false];
    $new_status = in_array($_POST['new_status'], ['1', 1, 'true', true], true) ? '1' : '0';
    $stmt = $conn->prepare("UPDATE contact_messages SET is_read=? WHERE contact_message_id=?");
    $stmt->bind_param("si", $new_status, $msg_id);
    if ($stmt->execute()) {
        $status_update_success = true;
    } else {
        $status_update_error = "Failed to update status.";
    }
    $stmt->close();
}

// Handle usual POSTs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_message_status'])) {

    // Handle edit contact info
    if (isset($_POST['edit_contact_info'])) {
        // Sanitize
        $contact_address = trim($_POST['contact_address'] ?? '');
        $contact_phone   = trim($_POST['contact_phone'] ?? '');
        $contact_email   = trim($_POST['contact_email'] ?? '');

        // Allow multi-line working hours; remove surrounding whitespace only, do not collapse lines
        $working_hours   = isset($_POST['working_hours']) ? rtrim(str_replace("\r", "", $_POST['working_hours'])) : '';

        // Validation
        if ($contact_address === '') $contact_errors['contact_address'] = "Address required";
        if ($contact_phone === '') {
            $contact_errors['contact_phone'] = "Phone required";
        } elseif (!preg_match('/^\d{10}$/', $contact_phone)) {
            $contact_errors['contact_phone'] = "Phone must be exactly 10 digits";
        }
        if ($contact_email === '' || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) 
            $contact_errors['contact_email'] = "Valid email required";
        if (trim($working_hours) === '') $contact_errors['working_hours'] = "Working hours required";

        if (!$contact_errors) {
            $stmt = $conn->prepare("UPDATE contact SET contact_address=?, contact_phone=?, contact_email=?, working_hours=? WHERE contact_id=1");
            $stmt->bind_param("ssss", $contact_address, $contact_phone, $contact_email, $working_hours);
            if ($stmt->execute()) {
                $contact_success = true;
            }
            $stmt->close();
        }
    }

    // Handle message sending
    if (isset($_POST['send_new_message'])) {
        $smtp_username = 'ishitvadhavana@gmail.com';
        $smtp_password = 'pwxo zzsn bafo emhf';
        $smtp_host = 'smtp.gmail.com';
        $smtp_port = 587;
        $smtp_secure = 'tls';
        $from_name = 'AOne Hub Admin';

        $to = trim($_POST['msg_to_email'] ?? '');
        $subject = trim($_POST['msg_subject'] ?? '');
        $body = trim($_POST['msg_body'] ?? '');

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '' || $body === '') {
            $message_error = 'All fields required with valid To email.';
        } else {
            require_once __DIR__ . '/../vendor/autoload.php';
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = $smtp_host;
                $mail->SMTPAuth = true;
                $mail->Username = $smtp_username;
                $mail->Password = $smtp_password;
                $mail->SMTPSecure = $smtp_secure;
                $mail->Port = $smtp_port;
                $mail->setFrom($smtp_username, $from_name);
                $mail->addAddress($to);
                $mail->isHTML(false);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->send();
                $message_success = true;
            } catch (\Exception $e) {
                $message_error = 'Message could not be sent. Error: ' . $mail->ErrorInfo;
            }
        }
    }
}

// Fetch current contact info
$contact = [
    'contact_address' => '',
    'contact_phone' => '',
    'contact_email' => '',
    'working_hours' => ''
];
$res = $conn->query("SELECT contact_address, contact_phone, contact_email, working_hours FROM contact WHERE contact_id=1 LIMIT 1");
if ($res && $res->num_rows > 0) {
    $contact = $res->fetch_assoc();
}

// --- Fetch contact messages ---
$contact_messages = [];
$res_msg = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
if ($res_msg && $res_msg->num_rows > 0) {
    while($row = $res_msg->fetch_assoc()) {
        $contact_messages[] = $row;
    }
}

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// --- Helper for preserving line breaks markup ---
function markup_newlines($text) {
    // esc first, then nl2br
    return nl2br(esc($text));
}

// --- Pagination using improved logic and classic-pagination style ---
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) ? (int)$_GET['page'] : 1;
$per_page =10;
$total_msgs = count($contact_messages);
$total_pages = ceil($total_msgs / $per_page);
$start_index = ($page - 1) * $per_page;
$paged_msgs = array_slice($contact_messages, $start_index, $per_page);
$serial_start = $start_index + 1;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Messages</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <script src="../bootstrap/js/jquery-3.7.1.min.js"></script>

    <!-- <link rel="stylesheet" href="css/contact.css"> -->
    <!-- <link rel="stylesheet" href="css/events.css"> -->
    <style>
        body { margin: 0; background: #f4f6fb;}
.dashboard-main {padding: 40px;}
.dashboard-header {margin-bottom: 30px;}
.dashboard-header h2 {margin: 0; color: #322053;}
.dashboard-header p {color: #6c757d; margin-top: 8px;}
.dashboard-card {
    background: #fff;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    margin-bottom: 36px;
}
.contact-info-list {
    display: flex;
    flex-wrap: wrap;
    gap: 38px;
    font-size: 17px;
    margin-top: 18px;
    margin-bottom: 8px;
}
.contact-info-item {
    min-width: 210px;
    padding: 14px 24px 14px 0;
    line-height: 1.6;
}
.btn {
    background: linear-gradient(90deg,#2d397a,#594285 90%);
    color: #fff;
    padding: 8px 24px;
    border: none;
    border-radius: 7px; font-size: 15px; font-weight: 700;
    cursor: pointer;
    transition: background .16s;
}
.btn:hover {background: linear-gradient(90deg,#594285,#2d397a 100%);}
.contact-table {
    width:100%; border-collapse:collapse; background: #fff; margin-top:24px; border-radius:14px 14px 0 0; overflow:hidden; box-shadow: 0 1px 10px rgba(50,32,83,0.06);
}

/* Animation for table values appearing one by one, equally for each column */
@keyframes contactTableFadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Animate only the contact-table th and td in both head and body */
.contact-table th,
.contact-table td {
    opacity: 0;
    animation: contactTableFadeIn 0.48s cubic-bezier(0.25,0.45,0.45,0.96) forwards;
}

/* Equal animation delay for each column */
.contact-table tr th,
.contact-table tr td {
    animation-delay: 0s;
}
.contact-table tr th:nth-child(1),
.contact-table tr td:nth-child(1) { animation-delay: 0.06s;}
.contact-table tr th:nth-child(2),
.contact-table tr td:nth-child(2) { animation-delay: 0.14s;}
.contact-table tr th:nth-child(3),
.contact-table tr td:nth-child(3) { animation-delay: 0.22s;}
.contact-table tr th:nth-child(4),
.contact-table tr td:nth-child(4) { animation-delay: 0.30s;}
.contact-table tr th:nth-child(5),
.contact-table tr td:nth-child(5) { animation-delay: 0.38s;}
/* Add more nth-child rules as needed for more columns, with steps of +0.08s or as desired */

.contact-table th, .contact-table td {
    padding: 12px 14px;
    text-align:left;
}
.contact-table th {
    background: #f4f6fb;
    color: #594285;
    font-size: 15px;
    font-weight: 700;
    border-bottom: 2px solid #ececec;
}
.contact-table tr:not(:last-child) td {
    border-bottom: 1px solid #f0f0f0;
}
.contact-table td {
    font-size: 15px;
    vertical-align: top;
}
.status-read { color: #18793a; font-weight: bold;}
.status-unread { color: #a5092c; font-weight: bold;}
.edit-contact-form-popup-bg {
    display: none;
    position:fixed; left:0;top:0;width:100vw;height:100vh;background:rgba(34,36,82,0.18);z-index:22;align-items:center;justify-content:center;
}
.edit-contact-form-popup {
    background: #fff;
    border-radius:14px;
    box-shadow:0 8px 40px rgba(50,32,83,0.07);
    padding:38px 44px;
    max-width:570px;
    width:97vw;
    margin:auto;
}
.form-group { margin-bottom: 20px;}
.form-group label { font-weight:600; color:#594285; margin-bottom:6px; display:block;}
.form-group input[type="text"], .form-group input[type="email"], .form-group textarea {
    width:100%; padding:8px 10px; border-radius:6px; border:1px solid #ddd; font-size:15px; background:#fafbff; box-sizing:border-box;
}
.form-group input[type="text"]:focus, .form-group input[type="email"]:focus, .form-group textarea:focus {
    outline:none; border-color:#7090f5; background:#fff;
}
.form-group textarea {
    min-height: 86px;
    resize: vertical;
    margin-top: 3px;
}
.form-error { color: #a5092c; font-size: 14px; padding: 6px 0 0 2px;}
.success-msg {background: #dbfadd; color: #18793a; border-radius:4px; padding:9px 14px; margin-bottom:14px;}
.error-msg {background: #fde8e4; color: #a5092c; border-radius:4px; padding:9px 14px; margin-bottom:14px;}
.action-btn-table {
    display: flex;
    gap: 7px;
    align-items: center;
}
.msg-btn {
    background: #fff;
    color: #594285;
    border: 1.5px solid #594285;
    padding: 6px 16px;
    border-radius:7px;
    font-weight:700;
    font-size: 14px;
    cursor: pointer;
    transition: background .2s, color .2s;
}
.msg-btn:hover {
    background: #f2f0fa;
}
.action-btn-table .btn {
    padding: 4px 11px;
    font-size: 14px;
}
@media (max-width:900px) {
    .edit-contact-form-popup {
        max-width: 99vw;
        padding: 18px 8px;
    }
    .contact-info-list {flex-direction: column;gap:13px;}
    .contact-info-item {padding:11px 0;}
}
@media (max-width:700px) {
    .dashboard-main {padding: 8px;}
    .edit-contact-form-popup {max-width: 99vw;padding: 10px;}
}
.message-status-form {
    display: inline-block;
    margin: 0;
}
.message-status-select {
    padding: 4px 9px;
    border-radius: 5px;
    font-size: 14px;
    border: 1px solid #c4b6e4;
    margin: 0;
    background: #faf6ff;
    transition: border-color 0.13s;
}
.message-status-select.read   { color: #18793a; font-weight: 700; border-color: #b8ecbb;}
.message-status-select.unread { color: #a5092c; font-weight: 700; border-color: #fcc5d3;}
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

    
</head>
<body>
<div class="dashboard-main">
    <div class="dashboard-header" style="display: flex; justify-content: space-between; align-items: end;">
        <div>
            <h2>Contact Messages</h2>
        </div>
        <div style="display:flex; gap:9px;">
            <button class="btn" type="button" id="editContactBtn">Edit Contact Info</button>
            <button class="msg-btn" type="button" id="newMessageBtn">
                <span style="font-size:18px;vertical-align:middle;">&#9993;</span> Message
            </button>
        </div>
    </div>
    <div class="dashboard-card">
        <h3 style="margin-top: 0; color:#594285; font-size:20px; margin-bottom:0;">Contact Information</h3>
        <div class="contact-info-list">
            <div class="contact-info-item"><b>Address:</b> <br><?= esc($contact['contact_address']) ?></div>
            <div class="contact-info-item"><b>Phone:</b> <br><?= esc($contact['contact_phone']) ?></div>
            <div class="contact-info-item"><b>Email:</b> <br><?= esc($contact['contact_email']) ?></div>
            <div class="contact-info-item"><b>Working Hours:</b> <br>
                <span style="white-space:pre-line;"><?= markup_newlines($contact['working_hours']) ?></span>
            </div>
        </div>
        <?php if ($contact_success): ?>
            <div class="success-msg" style="margin-bottom:0;">Contact information updated.</div>
        <?php endif; ?>
    </div>
    <div class="dashboard-card" style="overflow-x:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:19px;color:#594285;">Messages</h3>
        </div>
        <?php if ($status_update_success): ?>
            <div class="success-msg" style="margin-bottom:10px;">Status updated!</div>
        <?php elseif ($status_update_error): ?>
            <div class="error-msg" style="margin-bottom:10px;"><?= esc($status_update_error) ?></div>
        <?php endif; ?>
        <table class="contact-table">
            <thead>
                <tr>
                    <th>Sr No</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Received At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($paged_msgs) === 0): ?>
                    <tr><td colspan="8" style="text-align:center; color:#888;">No messages found.</td></tr>
                <?php else: ?>
                    <?php foreach($paged_msgs as $i => $msg): ?>
                        <tr>
                            <td><?= $serial_start + $i ?></td>
                            <td><?= esc($msg['contact_message_full_name']) ?></td>
                            <td><?= esc($msg['contact_message_email']) ?></td>
                            <td><?= esc($msg['contact_message_subject']) ?></td>
                            <td style="max-width:340px;white-space:pre-line;"><?= nl2br(esc($msg['contact_message'])) ?></td>
                            <td>
                                <!-- Dropdown START -->
                                <form class="message-status-form" method="post" action="" onchange="this.submit();">
                                    <input type="hidden" name="update_message_status" value="1" />
                                    <input type="hidden" name="message_id" value="<?= (int)$msg['contact_message_id'] ?>" />
                                    <select name="new_status" class="message-status-select <?= $msg['is_read'] == '1' ? 'read' : 'unread' ?>">
                                        <option value="1" <?= $msg['is_read'] == '1' ? 'selected' : '' ?>>Read</option>
                                        <option value="0" <?= $msg['is_read'] == '0' ? 'selected' : '' ?>>Unread</option>
                                    </select>
                                </form>
                                <!-- Dropdown END -->
                            </td>
                            <td><?= esc(date('Y-m-d H:i',strtotime($msg['created_at']))) ?></td>
                            <td>
                                <div class="action-btn-table">
                                    <button class="msg-btn" title="Message" onclick="messageUser('<?= esc($msg['contact_message_email']) ?>','<?= esc($msg['contact_message_full_name']) ?>');event.stopPropagation();">
                                        &#9993; Message
                                    </button>
                                    <button class="btn" style="background:#a5092c;padding:4px 11px;font-size:14px;" title="Delete" onclick="deleteMsg(<?= (int)$msg['contact_message_id'] ?>);event.stopPropagation();">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Improved Classic Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="classic-pagination">
                <ul>
                <?php
                    // Previous Button
                    if ($page > 1) {
                        echo '<li><a href="?page=' . ($page-1) . '">&laquo; Prev</a></li>';
                    } else {
                        echo '<li><span class="disabled">&laquo; Prev</span></li>';
                    }

                    // Show all page numbers for <=15, else window & first/last/ellipsis (classic style)
                    if ($total_pages <= 15) {
                        for ($p = 1; $p <= $total_pages; $p++) {
                            if ($page == $p) {
                                echo '<li><span class="active">' . $p . '</span></li>';
                            } else {
                                echo '<li><a href="?page=' . $p . '">' . $p . '</a></li>';
                            }
                        }
                    } else {
                        if ($page < 6) {
                            // 1 2 3 4 5 6 ... n
                            for ($p = 1; $p <= 6; $p++) {
                                if ($page == $p) {
                                    echo '<li><span class="active">' . $p . '</span></li>';
                                } else {
                                    echo '<li><a href="?page=' . $p . '">' . $p . '</a></li>';
                                }
                            }
                            echo '<li><span>...</span></li>';
                            echo '<li><a href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        } elseif ($page > $total_pages - 5) {
                            // 1 ... n-5 n-4 n-3 n-2 n-1 n
                            echo '<li><a href="?page=1">1</a></li>';
                            echo '<li><span>...</span></li>';
                            for ($p = $total_pages-5; $p <= $total_pages; $p++) {
                                if ($page == $p) {
                                    echo '<li><span class="active">' . $p . '</span></li>';
                                } else {
                                    echo '<li><a href="?page=' . $p . '">' . $p . '</a></li>';
                                }
                            }
                        } else {
                            // 1 ... page-2 page-1 page page+1 page+2 ... n
                            echo '<li><a href="?page=1">1</a></li>';
                            echo '<li><span>...</span></li>';
                            for ($p = $page-2; $p <= $page+2; $p++) {
                                if ($page == $p) {
                                    echo '<li><span class="active">' . $p . '</span></li>';
                                } else {
                                    echo '<li><a href="?page=' . $p . '">' . $p . '</a></li>';
                                }
                            }
                            echo '<li><span>...</span></li>';
                            echo '<li><a href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                    }

                    // Next Button
                    if ($page < $total_pages) {
                        echo '<li><a href="?page=' . ($page+1) . '">Next &raquo;</a></li>';
                    } else {
                        echo '<li><span class="disabled">Next &raquo;</span></li>';
                    }
                ?>
                </ul>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Edit Contact info Popup Form -->
<div class="edit-contact-form-popup-bg" id="editContactPopup">
    <form class="edit-contact-form-popup" method="post" action="" autocomplete="off" id="editContactForm" onsubmit="return validateContactForm();">
        <input type="hidden" name="edit_contact_info" value="1" />
        <h3 style="margin-top:0; color:#594285;">Edit Contact Information</h3>
        <div class="form-group">
            <label for="contact_address">Address</label>
            <input type="text" id="contact_address" name="contact_address" value="<?= esc($contact['contact_address']) ?>">
            <div class="form-error" id="contact_address_error">
                <?php if (isset($contact_errors['contact_address'])): ?>
                    <?= esc($contact_errors['contact_address']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group">
            <label for="contact_phone">Phone</label>
            <input type="text" id="contact_phone" name="contact_phone" value="<?= esc($contact['contact_phone']) ?>">
            <div class="form-error" id="contact_phone_error">
                <?php if (isset($contact_errors['contact_phone'])): ?>
                    <?= esc($contact_errors['contact_phone']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group">
            <label for="contact_email">Email</label>
            <input type="email" id="contact_email" name="contact_email" value="<?= esc($contact['contact_email']) ?>">
            <div class="form-error" id="contact_email_error">
                <?php if (isset($contact_errors['contact_email'])): ?>
                    <?= esc($contact_errors['contact_email']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group">
            <label for="working_hours">Working Hours</label>
            <textarea id="working_hours" name="working_hours" rows="4"><?= esc($contact['working_hours']) ?></textarea>
            <div class="form-error" id="working_hours_error">
                <?php if (isset($contact_errors['working_hours'])): ?>
                    <?= esc($contact_errors['working_hours']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div style="text-align:right;display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="btn" style="background:#aaa;color:#fff;" onclick="closeEditContactPopup()">Cancel</button>
            <button type="submit" class="btn">Save</button>
        </div>
    </form>
</div>

<!-- SEND NEW MESSAGE Modal -->
<div class="edit-contact-form-popup-bg" id="newMessagePopup" style="display:none;">
    <div class="edit-contact-form-popup" style="max-width:555px;">
        <h3 style="margin-top:0; color:#594285;">Send New Message</h3>
        <?php if ($message_success): ?>
            <div class="success-msg">Message sent successfully!</div>
        <?php elseif ($message_error): ?>
            <div class="error-msg"><?= esc($message_error) ?></div>
        <?php endif; ?>
        <form id="newMsgForm" autocomplete="off" method="post" action="">
            <input type="hidden" name="send_new_message" value="1" />
            <div class="form-group">
                <label for="msg_to_email">To Email</label>
                <input type="email" id="msg_to_email" name="msg_to_email" required value="<?= esc($_POST['msg_to_email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="msg_subject">Subject</label>
                <input type="text" id="msg_subject" name="msg_subject" required value="<?= esc($_POST['msg_subject'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="msg_body">Message</label>
                <textarea id="msg_body" name="msg_body" required rows="4" style="width:100%;border-radius:5px;border:1px solid #ddd;font-size:15px;padding:8px;"><?= esc($_POST['msg_body'] ?? '') ?></textarea>
            </div>
            <div style="text-align:right;display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn" style="background:#aaa;color:#fff;" onclick="closeNewMessagePopup()">Cancel</button>
                <button type="submit" class="btn">Send</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Open contact info edit popup
    document.getElementById('editContactBtn').onclick = function() {
        document.getElementById('editContactPopup').style.display='flex';
    };
    function closeEditContactPopup() {
        document.getElementById('editContactPopup').style.display='none';
    }

    // Open new message popup
    document.getElementById('newMessageBtn').onclick = function() {
        document.getElementById('msg_to_email').value = '';
        document.getElementById('msg_subject').value = '';
        document.getElementById('msg_body').value = '';
        document.getElementById('newMessagePopup').style.display='flex';
    };
    function closeNewMessagePopup() {
        document.getElementById('newMessagePopup').style.display='none';
    }

    // Message button in each row
    function messageUser(email, name) {
        document.getElementById('msg_to_email').value = email;
        document.getElementById('msg_subject').value = '';
        document.getElementById('msg_body').value = '';
        document.getElementById('newMessagePopup').style.display='flex';
        setTimeout(function() { document.getElementById('msg_subject').focus(); }, 100);
    }

    // Delete message (placeholder)
    function deleteMsg(msgId) {
        if (confirm('Are you sure you want to delete this message?')) {
            alert('Deleted message ID: ' + msgId + '\n(Update backend to implement this action.)');
        }
    }

    // --- POPUP CLOSE BEHAVIOR CHANGES ---
    // Don't close pop-up if form has errors

    // Utility: checks visible .form-error elements for the main popup
    function formHasVisibleErrors() {
        // Only for edit contact popup
        var hasErr = false;
        $('#editContactForm .form-error').each(function() {
            if ($(this).text().trim().length > 0) hasErr = true;
        });
        return hasErr;
    }

    // Overriding close on ESC for edit contact, only if no errors
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            // Only close if no visible errors on main form
            if ($('#editContactPopup').css('display') !== 'none') {
                if (!formHasVisibleErrors()) closeEditContactPopup();
            } else {
                closeNewMessagePopup();
            }
        }
    });

    // Only close on clicking backdrop if no errors shown
    document.getElementById('editContactPopup').onclick = function(e){
        if (e.target === this) {
            if (!formHasVisibleErrors()) closeEditContactPopup();
        }
    };
    document.getElementById('newMessagePopup').onclick = function(e){
        if (e.target === this) closeNewMessagePopup();
    };

    // ------- jQuery VALIDATION FOR CONTACT FORM -------
    function showError(id, msg) {
        $('#' + id).text(msg);
    }
    function clearError(id) {
        $('#' + id).text('');
    }

    function validateContactForm() {
        var valid = true;

        // Address
        var addr = $.trim($('#contact_address').val());
        if (addr.length === 0) {
            showError('contact_address_error', 'Address required');
            valid = false;
        } else {
            clearError('contact_address_error');
        }

        // Phone
        var phone = $.trim($('#contact_phone').val());
        if (phone.length === 0) {
            showError('contact_phone_error', 'Phone required');
            valid = false;
        } else if (!/^\d{10}$/.test(phone)) {
            showError('contact_phone_error', 'Phone must be exactly 10 digits');
            valid = false;
        } else {
            clearError('contact_phone_error');
        }

        // Email
        var email = $.trim($('#contact_email').val());
        var email_valid = /^[\w\.\-]+@([\w\-]+\.)+[a-zA-Z]{2,7}$/;
        if (email.length === 0) {
            showError('contact_email_error', 'Email required');
            valid = false;
        } else if (!email_valid.test(email)) {
            showError('contact_email_error', 'Valid email required');
            valid = false;
        } else {
            clearError('contact_email_error');
        }

        // Working hours (allow multiline textarea)
        var wh = $('#working_hours').val();
        if ($.trim(wh).length === 0) {
            showError('working_hours_error', 'Working hours required');
            valid = false;
        } else {
            clearError('working_hours_error');
        }

        return valid;
    }

    $(function(){
        // Live validation for contact form fields (now with 'input' event for up-to-date validation)
        $('#contact_address').on('input change keyup blur', function() {
            if ($.trim(this.value) === "") {
                showError('contact_address_error', 'Address required');
            } else {
                clearError('contact_address_error');
            }
        });

        $('#contact_phone').on('input change keyup blur', function() {
            var val = $.trim(this.value);
            if (val === "") {
                showError('contact_phone_error', 'Phone required');
            } else if (!/^\d{10}$/.test(val)) {
                showError('contact_phone_error', 'Phone must be exactly 10 digits');
            } else {
                clearError('contact_phone_error');
            }
        });

        $('#contact_email').on('input change keyup blur', function() {
            var val = $.trim(this.value);
            var email_valid = /^[\w\.\-]+@([\w\-]+\.)+[a-zA-Z]{2,7}$/;
            if (val === "") {
                showError('contact_email_error', 'Email required');
            } else if (!email_valid.test(val)) {
                showError('contact_email_error', 'Valid email required');
            } else {
                clearError('contact_email_error');
            }
        });

        $('#working_hours').on('input change keyup blur', function() {
            var val = $(this).val();
            if ($.trim(val) === "") {
                showError('working_hours_error', 'Working hours required');
            } else {
                clearError('working_hours_error');
            }
        });

        // Prevent edit popup from being closed by ESC/backdrop if there are visible errors
        $('#editContactForm input, #editContactForm textarea').on('input change', function() {
            // This callback only exists to recalculate errors, so closing is correctly blocked if relevant.
            // No-op: popup close logic already checks for errors.
        });
    });

</script>
</body>
</html>
