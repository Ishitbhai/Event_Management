<?php
require_once("../database/db_connect.php");
session_start();
require('sidebar.php');

// Only allow admin access
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}

// Get only the event title (no id)
function getEventTitle($conn, $event_id) {
    $stmt = $conn->prepare("SELECT event_title FROM events WHERE event_id = ?");
    if (!$stmt) return 'Unknown';
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $stmt->bind_result($event_title);
    if ($stmt->fetch()) {
        $stmt->close();
        return htmlspecialchars($event_title);
    }
    $stmt->close();
    return 'Unknown';
}

// Get only the user email (no id)
function getUserEmail($conn, $user_id) {
    $stmt = $conn->prepare("SELECT user_email FROM users WHERE user_id = ?");
    if (!$stmt) return 'Unknown';
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($user_email);
    if ($stmt->fetch()) {
        $stmt->close();
        return htmlspecialchars($user_email);
    }
    $stmt->close();
    return 'Unknown';
}

// Handle delete
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();
    header("Location: reviews.php?msg=deleted");
    exit();
}

// Pagination logic
$reviews = [];
$res = $conn->query("SELECT * FROM reviews ORDER BY reviewed_at DESC");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $reviews[] = $row;
    }
}

$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0
    ? (int)$_GET['page']
    : 1;
$per_page = 10;
$total_reviews = count($reviews);
$total_pages = ceil($total_reviews / $per_page);
$start_index = ($page - 1) * $per_page;
$paged_reviews = array_slice($reviews, $start_index, $per_page);
$serial_start = $start_index + 1;

$msg = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === "deleted") $msg = "<div class='success-message'>Review deleted successfully.</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reviews Management</title>
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

/* --- Responsive Reviews Table Styles --- */

/* Animations */
@keyframes fadeInUpRow {
  from {
    opacity: 0;
    transform: translateY(18px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
@keyframes fadeInUpCell {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Main Container */
.review-table-container {
  margin: 40px auto 0;
  width: 98%;
  max-width: 1100px;
  background: #fff;
  border-radius: 13px;
  box-shadow: 0 1px 8px rgba(44,62,80,0.09);
  padding: 34px 28px 32px;
  overflow-x: auto;
  transition: padding 0.25s;
}

/* Responsive Table Base */
.review-table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  min-width: 650px;
  transition: min-width 0.2s;
}
.review-table th,
.review-table td {
  text-align: left;
  padding: 11px 13px;
  vertical-align: middle;
  font-size: 16px;
  word-break: break-word;
  transition: padding 0.2s, font-size 0.2s;
}

/* Animations for table rows and cells */
.review-table tbody tr {
  opacity: 0;
  animation: fadeInUpRow 0.6s forwards;
}
.review-table tbody tr:nth-child(1) { animation-delay: 0.05s; }
.review-table tbody tr:nth-child(2) { animation-delay: 0.12s; }
.review-table tbody tr:nth-child(3) { animation-delay: 0.19s; }
.review-table tbody tr:nth-child(4) { animation-delay: 0.26s; }
.review-table tbody tr:nth-child(5) { animation-delay: 0.33s; }
.review-table tbody tr:nth-child(6) { animation-delay: 0.4s; }
.review-table tbody tr:nth-child(7) { animation-delay: 0.47s; }
.review-table tbody tr:nth-child(8) { animation-delay: 0.54s; }
.review-table tbody tr:nth-child(9) { animation-delay: 0.61s; }
.review-table tbody tr:nth-child(10) { animation-delay: 0.68s; }
.review-table tbody tr td {
  opacity: 0;
  animation: fadeInUpCell 0.48s forwards;
  animation-delay: inherit;
}
.review-table tbody tr td:nth-child(1) { animation-delay: calc(inherit + 0.02s); }
.review-table tbody tr td:nth-child(2) { animation-delay: calc(inherit + 0.05s); }
.review-table tbody tr td:nth-child(3) { animation-delay: calc(inherit + 0.08s); }
.review-table tbody tr td:nth-child(4) { animation-delay: calc(inherit + 0.11s); }
.review-table tbody tr td:nth-child(5) { animation-delay: calc(inherit + 0.14s); }
.review-table tbody tr td:nth-child(6) { animation-delay: calc(inherit + 0.17s); }
.review-table tbody tr td:nth-child(7) { animation-delay: calc(inherit + 0.20s); }
.review-table tbody tr td:nth-child(8) { animation-delay: calc(inherit + 0.23s); }

.review-table th {
  background: #fafaff;
  color: #473b6f;
  font-weight: 700;
  border-bottom: 2px solid #f1f1f9;
}
.review-table tbody tr:nth-child(even) {
  background: #faf8ff;
}
.review-table td {
  border-bottom: 1px solid #f2f2f6;
}
.star {
  color: #ffd234;
  font-size: 20px;
  margin-right: 1px;
  transition: font-size 0.2s;
}

/* Button Styles */
.delete-btn,
.edit-btn,
.create-review-btn {
  font-weight: 600;
  border-radius: 5px;
  cursor: pointer;
  transition: background 0.16s, font-size 0.2s, padding 0.2s, width 0.2s;
  border: none;
}
.delete-btn {
  padding: 7px 16px;
  font-size: 15px;
  background: linear-gradient(90deg, #e94242 20%, #b02626 80%);
  color: #fff;
  margin-left: 0;
  box-shadow: 0 1px 3px rgba(200,55,55,0.07);
}
.delete-btn:hover {
  background: linear-gradient(90deg, #a51818, #e94242 60%);
}
.edit-btn {
  text-decoration:none;
  padding: 7px 16px;
  font-size: 15px;
  background: linear-gradient(90deg, #327ac5 20%, #225085 80%);
  color: #fff;
  margin-right: 8px;
  box-shadow: 0 1px 3px rgba(50,122,197,0.07);
}
.edit-btn:hover {
  background: linear-gradient(90deg, #225085, #327ac5 60%);
}
.create-review-btn {
  background: linear-gradient(90deg, #2d397a, #594285 90%);
  color: #fff;
  padding: 8px 20px;
  border-radius: 7px;
  font-size: 16px;
  font-weight: 700;
  margin-left: 6px;
  transition: background .18s, font-size 0.2s, padding 0.2s, width 0.2s;
  letter-spacing: 0.02em;
  box-shadow: 0 2px 9px rgb(82 58 213 / 8%);
}
.create-review-btn:hover {
  background: linear-gradient(90deg, #594285, #2d397a 100%);
}

/* Flex Header Responsive */
.reviews-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  flex-wrap: wrap;
  gap: 10px;
}
.review-table .id-label {
  color: #9982c5; 
  font-size: 90%; 
  margin-right: 4px;
}

/* Classic Pagination Responsive */
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
.classic-pagination a,
.classic-pagination span {
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
  transition: background 0.13s, font-size 0.2s, padding 0.2s;
  opacity: 0;
  animation: fadeInCellStagger 0.17s both;
}
.classic-pagination li:nth-child(1) a, .classic-pagination li:nth-child(1) span { animation-delay: 0.09s; opacity: 1;}
.classic-pagination li:nth-child(2) a, .classic-pagination li:nth-child(2) span { animation-delay: 0.15s; opacity: 1;}
.classic-pagination li:nth-child(3) a, .classic-pagination li:nth-child(3) span { animation-delay: 0.21s; opacity: 1;}
.classic-pagination li:nth-child(4) a, .classic-pagination li:nth-child(4) span { animation-delay: 0.27s; opacity: 1;}
.classic-pagination li:nth-child(5) a, .classic-pagination li:nth-child(5) span { animation-delay: 0.33s; opacity: 1;}
.classic-pagination li:last-child a,
.classic-pagination li:last-child span {
  border-right: 0;
}
.classic-pagination a:hover {
  background: #e9e9e9;
  color: #111;
}
.classic-pagination .active,
.classic-pagination .active:hover,
.classic-pagination .active:focus {
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

/**************************
    RESPONSIVENESS (NEW)
***************************/

/* Big laptops/desktops */
@media (max-width: 1200px) {
  .review-table-container {
      padding: 28px 10px 26px;
      max-width: 98vw;
  }
  .review-table {
      min-width: 520px;
  }
}

/* Laptops and below */
@media (max-width: 1000px) {
  .review-table-container {
      padding: 20px 4px 11px;
  }
  .review-table th,
  .review-table td {
      font-size: 15px;
      padding: 8px 7px;
  }
  .review-table {
      min-width: 400px;
  }
  .star { font-size: 18px; }
  .create-review-btn { font-size: 15px; padding: 7px 15px;}
}

/* Tablet breakpoint */
@media (max-width: 800px) {
  .review-table th,
  .review-table td {
      font-size: 14px;
      padding: 7px 5px;
  }
  .review-table {
      min-width: 320px;
  }
  .create-review-btn {
      font-size: 14px;
      padding: 7px 12px;
  }
  .edit-btn,
  .delete-btn {
      font-size: 13px;
      padding: 7px 8px;
  }
  .reviews-header {
      gap: 6px;
      font-size: 98%;
  }
}

/* All mobile: stack header, remove left margin, tighter buttons */
@media (max-width: 650px) {
  .reviews-header {
      flex-direction: column;
      align-items: stretch;
      gap: 5px 0;
      font-size: 15px;
      margin-bottom: 11px;
  }
  .create-review-btn {
      margin-left: 0;
      margin-bottom: 8px;
      width: 100%;
  }
}

/* Tablets & below: Table as Cards */
@media (max-width: 600px) {
  .review-table-container {
      padding: 7px 2px 8px;
      min-width: 0;
      max-width: 100vw;
      overflow-x: auto;
  }
  /* Table as cards! */
  .review-table,
  .review-table thead,
  .review-table tbody,
  .review-table th,
  .review-table td,
  .review-table tr {
      display: block;
      width: 100%;
      min-width: 0;
  }
  
  .review-table thead {
      display: none;
  }
  .review-table tr {
      background: #fff;
      margin-bottom: 18px;
      border-radius: 9px;
      box-shadow: 0 2px 7px rgba(44,62,80,0.08);
      padding: 10px 5px 9px 7px;
      overflow-x: auto;
  }
  .review-table td {
      border-bottom: none;
      position: relative;
      padding: 10px 4px 10px 44%;
      font-size: 15px;
      min-width: unset;
      word-break: break-word;
  }
  .review-table td:before {
      content: attr(data-label);
      position: absolute;
      left: 12px;
      top: 9px;
      width: 43%;
      min-width: 90px;
      font-weight: 700;
      letter-spacing: 0.01em;
      color: #473b6f;
      font-size: 14px;
      opacity: 0.92;
      white-space: pre-wrap;
  }
  .star { font-size: 17px; }
  .edit-btn,
  .delete-btn {
      width: 100%;
      margin: 6px 0 0 0;
      padding: 10px 4px;
      font-size: 14px;
      min-width: unset;
      box-sizing: border-box;
  }
  .create-review-btn {
      font-size: 13px;
      padding: 9px 10px;
      width: 100%;
      margin-bottom: 10px;
  }
  .classic-pagination ul { 
      display: block; 
      border-radius: 3px;
  }
  .classic-pagination a,
  .classic-pagination span {
      float: none;
      display: inline-block;
      padding: 8px 8px;
      font-size: 15px;
      min-width: 24px;
  }
}

/* Small phones (<480px): even tighter */
@media (max-width: 480px) {
  .review-table-container {
      padding: 4px 1px 5px;
      width: 100vw;
      min-width: unset;
      max-width: 100vw;
  }
  .review-table tr {
      box-shadow: 0 1px 5px rgba(44, 62, 80, 0.02);
      padding: 3px 1px 7px 1px;
  }
  .classic-pagination a,
  .classic-pagination span {
      font-size: 14px;
      padding: 6px 4px;
      min-width: 20px;
  }
  .create-review-btn {
      font-size: 12px;
      padding: 8px 6px;
  }
  .edit-btn,
  .delete-btn {
      font-size: 12px;
      padding: 9px 2px;
  }
  .review-table td {
      padding: 8px 3px 8px 46%;
      font-size: 14px;
  }
  .review-table td:before {
      font-size: 13px;
      left: 8px;
      top: 7px;
      min-width: 62px;
      width: 44%;
  }
}

/* Ensures pagination/btns readable even on the tiniest screens */
@media (max-width: 370px) {
  .classic-pagination a,
  .classic-pagination span {
      font-size: 12px;
      padding: 5px 2px;
      min-width: 18px;
  }
  .create-review-btn, .edit-btn, .delete-btn {
      font-size: 11px;
      padding: 7px 2px;
  }
  .review-table td,
  .review-table td:before {
      font-size: 11px;
      padding: 6px 2px 6px 50%;
      left: 3px;
      min-width: 47px;
  }
}

/* Accessibility: extra horizontal scroll on excess columns */
@media (max-width: 400px) {
  .review-table-container {
      overflow-x: auto;
  }
}

@media (pointer: coarse) {
  /* Make interactive controls easier to touch */
  .create-review-btn, .edit-btn, .delete-btn {
    min-height: 40px;
  }
}

/* Utility: Prevent overflow on all screens */
.review-table-container {
  overflow-x: auto;
}
    </style>
    <!-- <link rel="stylesheet" href="css/events.css"> -->
    <!-- <link rel="stylesheet" href="css/index.css"> -->
    <!-- <link rel="stylesheet" href="css/reviews.css"> -->
    
</head>
<body>
<div class="review-table-container">
    <div class="reviews-header">
      <h2 style="margin:0;color:#322053;">Event Reviews</h2>
      <button class="create-review-btn" onclick="window.location.href='review_create.php'">+ Create Review</button>
    </div>
    <?php if ($msg) echo $msg; ?>

    <table class="review-table">
        <thead>
            <tr>
                <th>Sr No</th>
                <th style='display:none'>Review ID</th>
                <th>User</th>
                <th>Event</th>
                <th>Rating</th>
                <th>Review</th>
                <th>Reviewed At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php
        if (!empty($paged_reviews)) {
            $sr_no = $serial_start;
            foreach ($paged_reviews as $row) {
                $review_id = (int)$row['review_id'];
                $user_html = getUserEmail($conn, $row['user_id']); // Shows user email only
                $event_html = getEventTitle($conn, $row['event_id']); // Shows event title only
                $rating = intval($row['review_rating']);
                $review = htmlspecialchars($row['review_review']);
                $reviewed_at = date("Y-m-d H:i", strtotime($row['reviewed_at']));
                echo "<tr>";
                echo "<td>" . $sr_no++ . "</td>";
                echo "<td style='display:none'>{$review_id}</td>";
                echo "<td>{$user_html}</td>";
                echo "<td>{$event_html}</td>";
                echo "<td>";
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $rating) echo "<span class='star'>&#9733;</span>";
                    else echo "<span class='star' style='color:#e5e5e5;'>&#9733;</span>";
                }
                echo "</td>";
                echo "<td>{$review}</td>";
                echo "<td>{$reviewed_at}</td>";
                echo "<td style='white-space:nowrap;'>
                        <a href='review_edit.php?id={$review_id}' class='edit-btn'>Edit</a>
                        <form method='GET' onsubmit=\"return confirm('Are you sure you want to delete this review?');\" style='display:inline;'>
                            <input type='hidden' name='delete_id' value='{$review_id}'>
                            <button type='submit' class='delete-btn'>Delete</button>
                        </form>
                      </td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='8' style='color:#673f79;text-align:center;padding:28px 0;'>No reviews found.</td></tr>";
        }
        ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="classic-pagination">
            <ul>
            <?php
                // Classic paging: prev, 1 2 3 ... n, next
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
                    // Classic window: always show first, prev, ... window ..., last, next
                    if ($page < 6) {
                        // 1 2 3 4 5 ... n
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
</body>
</html>
