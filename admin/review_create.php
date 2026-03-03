<?php
require_once("../database/db_connect.php");
session_start();
require('sidebar.php');

// Only admins allowed
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}

// --- Helpers ---
function getEventOptions($conn, $selected = null) {
    $out = "";
    $result = $conn->query("SELECT event_id, event_title FROM events WHERE event_status = 'completed' ORDER BY event_title ASC");
    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {
            $sel = ($selected !== null && $selected == $r['event_id']) ? "selected" : "";
            $label = htmlspecialchars($r['event_title']) . " (ID: " . (int)$r['event_id'] . ")";
            $out .= '<option value="'.(int)$r['event_id'].'" '.$sel.'>'.$label.'</option>';
        }
    }
    return $out ?: "<option disabled>No completed events found</option>";
}
function getUserOptions($conn, $selected = null) {
    $out = "";
    $result = $conn->query("SELECT user_id, user_name, user_email FROM users ORDER BY user_name ASC");
    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {
            $sel = ($selected !== null && $selected == $r['user_id']) ? "selected" : "";
            $label = htmlspecialchars($r['user_name']) . " (" . htmlspecialchars($r['user_email']) . ")";
            $out .= '<option value="'.(int)$r['user_id'].'" '.$sel.'>'.$label.'</option>';
        }
    }
    return $out ?: "<option disabled>No users found</option>";
}

// --- POST handler ---
// Separate errors for each field
$errors_user_id = "";
$errors_event_id = "";
$errors_rating = "";
$errors_review = "";
$errors_general = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id_raw = $_POST['user_id'] ?? '';
    $event_id_raw = $_POST['event_id'] ?? '';
    $review_rating_raw = $_POST['review_rating'] ?? '';
    $review_review = trim($_POST['review_review'] ?? '');

    // Separate validation for each
    if ($user_id_raw === "" || !ctype_digit($user_id_raw) || (int)$user_id_raw < 1) {
        $errors_user_id = "User is required.";
    } else {
        // Exists validation
        $user_id = (int)$user_id_raw;
        $stmt = $conn->prepare("SELECT 1 FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows < 1) $errors_user_id = "Selected user does not exist.";
        $stmt->close();
    }

    if ($event_id_raw === "" || !ctype_digit($event_id_raw) || (int)$event_id_raw < 1) {
        $errors_event_id = "Event is required.";
    } else {
        $event_id = (int)$event_id_raw;
        $stmt2 = $conn->prepare("SELECT 1 FROM events WHERE event_id = ? AND event_status = 'completed'");
        $stmt2->bind_param("i", $event_id);
        $stmt2->execute();
        $stmt2->store_result();
        if ($stmt2->num_rows < 1) $errors_event_id = "Selected event does not exist or is not completed.";
        $stmt2->close();
    }

    if ($review_rating_raw === "" || !ctype_digit($review_rating_raw) || (int)$review_rating_raw < 1 || (int)$review_rating_raw > 5) {
        $errors_rating = "Rating must be between 1 and 5.";
    }

    if ($review_review === "") {
        $errors_review = "Review cannot be empty.";
    }

    // If no errors, insert
    if (!$errors_user_id && !$errors_event_id && !$errors_rating && !$errors_review) {
        $user_id = (int)$user_id_raw;
        $event_id = (int)$event_id_raw;
        $review_rating = (int)$review_rating_raw;
        $stmt = $conn->prepare("INSERT INTO reviews (user_id, event_id, review_rating, review_review, reviewed_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $user_id, $event_id, $review_rating, $review_review);
        if ($stmt->execute()) {
            $success = true;
            $new_id = $conn->insert_id;
            header("Location: reviews.php?msg=created");
            exit;
        } else {
            $errors_general[] = "Failed to save review.";
        }
        $stmt->close();
    }
}

// For value repopulation after submit error
function old($key) {
    return htmlspecialchars($_POST[$key] ?? '');
}
function oldSelected($key, $val) {
    return (isset($_POST[$key]) && $_POST[$key] == $val) ? "selected" : "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Event Review</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
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
.review-form-wrapper {
    margin:40px auto 0;
    background:#fff;
    border-radius:14px;
    box-shadow:0 1px 8px rgba(44,62,80,0.09);
    padding:35px 25px 30px;
    max-width: 480px;
    width: 100%;
    box-sizing: border-box;
    transition: padding 0.2s, max-width 0.2s;
}
.review-form-wrapper h2 {
    color:#322053;
    margin-top:0;
    font-size: 1.6rem;
    word-break: break-word;
}
.rf-label {
    font-weight:600;
    display:block;
    margin-bottom:6px;
    color: #322053;
    font-size: 1rem;
    word-break: break-word;
}
.rf-input, .rf-select, .rf-textarea {
    width: 100%;
    padding: 10px 12px;
    font-size: 15px;
    border-radius: 7px;
    border: 1px solid #dad7f6;
    margin-bottom: 18px;
    box-sizing: border-box;
    min-width: 0;
    background: #fff;
}
.rf-select {
    background: #f8f6ff;
    color: #38285d;
}
.rf-textarea {
    min-height: 70px;
    resize: vertical;
    word-break: break-word;
}
.success-message {
    color: #228a36;
    background: #e8fdeb;
    border: 1px solid #a8dfb1;
    padding: 9px 15px;
    border-radius: 7px;
    margin: 13px 0 16px 0;
    font-weight: 600;
    font-size: 16px;
    max-width: 390px;
    width: 100%;
    display: block;
    box-sizing: border-box;
    word-break: break-word;
}
.error-message-inline {
    color: #b70c26;
    background: #fff0f0;
    border: 1px solid #e1c2c7;
    padding: 9px 15px;
    border-radius: 7px;
    margin: 10px 0 14px 0;
    font-weight: 600;
    font-size: 15px;
    max-width: 460px;
    width: 100%;
    display: block;
    box-sizing: border-box;
    word-break: break-word;
}
.rf-error {
    color: #b70c26;
    background: #fff0f0;
    border: 1px solid #e1c2c7;
    border-radius: 6px;
    padding: 6px 12px;
    font-size: 14px;
    font-weight: 500;
    margin: -11px 0 11px 0;
    display: none;
    word-break: break-word;
}

.rf-btn-row {
    margin-top: 18px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.create-btn {
    background: linear-gradient(90deg, #2d397a, #594285 90%);
    color: #fff;
    padding: 9px 25px;
    border: none;
    border-radius: 7px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: background .18s;
    letter-spacing: 0.01em;
    box-shadow: 0 2px 9px rgb(82 58 213 / 8%);
    width: auto;
    min-width: 100px;
    max-width: 100%;
    box-sizing: border-box;
}
.create-btn:hover { 
    background: linear-gradient(90deg, #594285, #2d397a 100%);
}
.cancel-btn {
    background: #f5f5f7;
    color: #58526b;
    border: none;
    border-radius: 7px;
    padding: 9px 20px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    transition: background .15s;
    width: auto;
    min-width: 100px;
    max-width: 100%;
    box-sizing: border-box;
}
.cancel-btn:hover {
    background: #e5e4ee;
}

/* Responsive styles */
@media (max-width: 900px) {
    .review-form-wrapper {
        max-width: 90vw;
        padding: 25px 18px 22px;
    }
    .success-message, .error-message-inline {
        max-width: 100%;
    }
}
@media (max-width: 600px) {
    .review-form-wrapper {
        padding: 16px 6vw 15px;
        max-width: 100vw;
    }
    .rf-btn-row {
        flex-direction: column;
        gap: 13px;
    }
    .create-btn,
    .cancel-btn {
        width: 100%;
        min-width: 0;
        font-size: 17px;
        padding: 11px 10px;
        box-sizing: border-box;
    }
    .rf-label {
        font-size: 0.97rem;
    }
    .rf-input, .rf-select, .rf-textarea {
        font-size: 16px;
        padding: 11px 10px;
    }
}
@media (max-width: 400px) {
    .review-form-wrapper {
        padding: 7px 2vw 8px;
    }
    .success-message, .error-message-inline {
        font-size: 14px;
        padding: 7px 7px;
    }
    .rf-label {
        font-size: 0.95rem;
    }
}
    </style>
    <!-- <link rel="stylesheet" href="css/events.css"> -->
    <!-- <link rel="stylesheet" href="css/index.css"> -->
    <!-- <link rel="stylesheet" href="css/review_create.css"> -->
    <script src="js/jquery-4.0.0.min.js"></script>
</head>
<body>
<div class="review-form-wrapper">
    <h2>Create Event Review</h2>
    <?php
    if (!empty($errors_general)) {
        echo "<div class='error-message-inline'>";
        foreach ($errors_general as $e) {
            echo htmlspecialchars($e) . "<br>";
        }
        echo "</div>";
    }
    ?>
    <form method="post" action="review_create.php" autocomplete="off" id="reviewForm" novalidate>
        <label class="rf-label" for="user_id">User <span style="font-weight:400;font-size:13px;color:#7b6fa5"></span></label>
        <select id="user_id" name="user_id" class="rf-select" onchange="validateUserField()">
            <option value="">-- Select User --</option>
            <?= getUserOptions($conn, $_POST['user_id'] ?? null); ?>
        </select>
        <div class="rf-error" id="user_id_error" style="<?= $errors_user_id ? 'display:block;' : '' ?>"><?= htmlspecialchars($errors_user_id) ?></div>

        <label class="rf-label" for="event_id">Event <span style="font-weight:400;font-size:13px;color:#7b6fa5"></span></label>
        <select id="event_id" name="event_id" class="rf-select" onchange="validateEventField()">
            <option value="">-- Select Event --</option>
            <?= getEventOptions($conn, $_POST['event_id'] ?? null); ?>
        </select>
        <div class="rf-error" id="event_id_error" style="<?= $errors_event_id ? 'display:block;' : '' ?>"><?= htmlspecialchars($errors_event_id) ?></div>

        <label class="rf-label" for="review_rating">Rating</label>
        <select id="review_rating" name="review_rating" class="rf-select" onchange="validateRatingField()">
            <option value="">-- Select Rating --</option>
            <?php
                for ($i=5;$i>=1;$i--) {
                    $sel = oldSelected('review_rating', $i);
                    echo "<option value=\"$i\" $sel>$i Star".($i>1?'s':'')."</option>";
                }
            ?>
        </select>
        <div class="rf-error" id="review_rating_error" style="<?= $errors_rating ? 'display:block;' : '' ?>"><?= htmlspecialchars($errors_rating) ?></div>

        <label class="rf-label" for="review_review">Review</label>
        <textarea id="review_review" name="review_review" class="rf-textarea" oninput="validateReviewField()"><?= old('review_review'); ?></textarea>
        <div class="rf-error" id="review_review_error" style="<?= $errors_review ? 'display:block;' : '' ?>"><?= htmlspecialchars($errors_review) ?></div>

        <div class="rf-btn-row">
            <button type="submit" class="create-btn">Submit Review</button>
            <a href="reviews.php" class="cancel-btn">Cancel</a>
        </div>
    </form>
</div>
<script>
function validateUserField() {
    let value = $('#user_id').val();
    if (!value || !/^\d+$/.test(value) || parseInt(value,10) < 1) {
        $('#user_id_error').text('User is required.').show();
        return false;
    } else {
        $('#user_id_error').text('').hide();
        return true;
    }
}
function validateEventField() {
    let value = $('#event_id').val();
    if (!value || !/^\d+$/.test(value) || parseInt(value,10) < 1) {
        $('#event_id_error').text('Event is required.').show();
        return false;
    } else {
        $('#event_id_error').text('').hide();
        return true;
    }
}
function validateRatingField() {
    let value = $('#review_rating').val();
    if (!value || !/^[1-5]$/.test(value)) {
        $('#review_rating_error').text('Rating must be between 1 and 5.').show();
        return false;
    } else {
        $('#review_rating_error').text('').hide();
        return true;
    }
}
function validateReviewField() {
    let value = $('#review_review').val();
    if ($.trim(value) === '') {
        $('#review_review_error').text('Review cannot be empty.').show();
        return false;
    } else {
        $('#review_review_error').text('').hide();
        return true;
    }
}

$(document).ready(function(){
    $('#user_id').on('change', validateUserField);
    $('#event_id').on('change', validateEventField);
    $('#review_rating').on('change', validateRatingField);
    $('#review_review').on('input', validateReviewField);

    // Prevent submit if any field is invalid (optional, disable this for pure on-change if you'd like)
    $('#reviewForm').on('submit', function(e){
        let valid = true;
        if (!validateUserField()) valid = false;
        if (!validateEventField()) valid = false;
        if (!validateRatingField()) valid = false;
        if (!validateReviewField()) valid = false;
        if (!valid) e.preventDefault();
    });
});
</script>
</body>
</html>
