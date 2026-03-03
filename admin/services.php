<?php
require_once("../database/db_connect.php");
session_start();
require('sidebar.php');

// Only admins allowed
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// ---- Handle delete request for Services and Why Aone Hub ----
$delete_success = null;
$delete_error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Services delete
    if (isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
        $delete_id = (int)$_POST['delete_id'];
        $stmt = $conn->prepare("SELECT service_image FROM services WHERE service_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->bind_result($img_name);
        if ($stmt->fetch()) {
            $stmt->close();
            $del_stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?");
            $del_stmt->bind_param("i", $delete_id);
            if ($del_stmt->execute()) {
                if (!empty($img_name)) {
                    $img_path = __DIR__ . "/../images/" . basename($img_name);
                    if (file_exists($img_path)) {
                        @unlink($img_path);
                    }
                }
                $delete_success = "Service deleted successfully.";
            } else {
                $delete_error = "Failed to delete service.";
            }
            $del_stmt->close();
        } else {
            $stmt->close();
            $delete_error = "Service not found.";
        }
    }

    // Handle Why Aone Hub delete
    if (isset($_POST['delete_why_id']) && is_numeric($_POST['delete_why_id'])) {
        $delete_why_id = (int)$_POST['delete_why_id'];
        $stmt = $conn->prepare("DELETE FROM why_aone_hub WHERE why_id = ?");
        $stmt->bind_param("i", $delete_why_id);
        if ($stmt->execute()) {
            $delete_success = "Why Aone Hub entry deleted successfully.";
        } else {
            $delete_error = "Failed to delete Why Aone Hub entry.";
        }
        $stmt->close();
    }
}

// ---- Fetch and sort services for display ----
$services = [];
$fetch_result = $conn->query("SELECT * FROM services");
if ($fetch_result && $fetch_result->num_rows > 0) {
    while ($row = $fetch_result->fetch_assoc()) {
        $services[] = $row;
    }
}
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$per_page = 10;
$total_services = count($services);
$total_pages = ceil($total_services / $per_page);
$start_index = ($page - 1) * $per_page;
$paged_services = array_slice($services, $start_index, $per_page);
$serial_start = $start_index + 1;

// ---- Fetch Why Aone Hub data ----
$why_rows = [];
$why_result = $conn->query("SELECT * FROM why_aone_hub ORDER BY why_id ASC");
if ($why_result && $why_result->num_rows > 0) {
    while ($row = $why_result->fetch_assoc()) {
        $why_rows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Services & Why Aone Hub</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        .main-section-block {
            background: #fff;
            border-radius: 12px;
            margin-bottom: 44px;
            box-shadow: 0 1px 10px rgba(44,62,80,0.06);
            padding: 30px 22px 32px 22px;
        }
        .main-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 22px;
            gap: 10px;
        }
        .main-section-header h1, .main-section-header h2 {
            font-size: 1.35rem;
            margin: 0;
            color: #322053;
            font-weight: 700;
        }
        .classic-pagination {
            margin-top: 18px;
        }
        @media (max-width: 960px) {
            .main-section-block { padding: 16px 2vw; }
        }
        body {
    background: #f8fafc;
    margin: 0;
    overflow-x: hidden;
}

/* Animation for sequential "come in" effect */
@keyframes tableValueIn {
    from {
        opacity: 0;
        transform: translateY(25px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Add animation to th and td in .service-table */
table.service-table th,
table.service-table td {
    /* animation will be set via nth-child for delay */
    opacity: 0;
    animation: tableValueIn 0.48s cubic-bezier(0.25,0.45,0.45,0.96) forwards;
}

/* Animate TH "come in" one by one */
table.service-table th:nth-child(1) { animation-delay: 0.06s; }
table.service-table th:nth-child(2) { animation-delay: 0.16s; }
table.service-table th:nth-child(3) { animation-delay: 0.26s; }
table.service-table th:nth-child(4) { animation-delay: 0.36s; }
table.service-table th:nth-child(5) { animation-delay: 0.46s; }
/* Add more nth-child rules if more columns exist */

/* Animate TDs "come in" one by one per row */
table.service-table tr {
    /* Used for more readable selectors below */
}

table.service-table td:nth-child(1) { animation-delay: 0.06s;}
table.service-table td:nth-child(2) { animation-delay: 0.16s;}
table.service-table td:nth-child(3) { animation-delay: 0.26s;}
table.service-table td:nth-child(4) { animation-delay: 0.36s;}
table.service-table td:nth-child(5) { animation-delay: 0.46s;}
/* Add more nth-child rules if more columns exist */

/* Apply subsequent row delays with JS, or simply set them all at once for initial load using the above for visual effect */

.service-table-container {
    overflow-x: auto;
    margin-top: 22px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 10px rgba(44,62,80,0.09);
    padding: 16px;
    width: 100%;
    box-sizing: border-box;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}
table.service-table {
    border-collapse: collapse;
    min-width: 860px;
    width: 100%;
}
.service-table th, .service-table td {
    padding: 11px 12px;
    text-align: left;
    border-bottom: 1px solid #e6e7f0;
    font-size: 16px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 440px;
    vertical-align: middle;
}
.service-table th.description-cell, .service-table td.description-cell {
    white-space: normal;
    max-width: 330px;
    min-width: 160px;
}
.service-table th, .service-table td {
    min-width: 90px;
}
.service-table th.service_image, .service-table td.service_image {
    min-width: 110px;
    max-width: 180px;
}
.service-table th {
    background: #f4f6fb;
    color: #322053;
    font-weight: 600;
    border-top: 1px solid #e6e7f0;
}
.service-table tr:nth-child(even) {
    background: #f9fafe;
}
.service-table tr:hover {
    background: #f2f4fa;
    transition: background 0.1s;
}
.service-table td .service-image-thumb {
    max-width: 76px;
    max-height: 56px;
    display: block;
    border-radius: 5px;
    margin-bottom:5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    background: #fff;
}
.internal-no-image {
    color: #ca3125;
    background: #ffd4d4;
    padding: 7px 13px;
    border-radius: 4px;
    font-size: 15px;
    font-style: italic;
    display: inline-block;
}
.service-table td .edit-btn,
.service-table td .delete-btn {
    border: none;
    border-radius: 5px;
    padding: 7px 16px;
    cursor: pointer;
    font-weight: 600;
    font-size: 15px;
    transition: background 0.18s;
    text-decoration: none;
    display: inline-block;
}
.service-table td .edit-btn {
    background: linear-gradient(90deg, #338bc0 20%, #2b67d0 80%);
    color: #fff;
    margin-right: 6px;
    box-shadow: 0 1px 3px rgba(60,75,170,0.07);
}
.service-table td .edit-btn:hover {
    background: linear-gradient(90deg, #234e8a 30%, #387add 100%);
}
.service-table td .delete-btn {
    background: linear-gradient(90deg, #e94242 20%, #b02626 80%);
    color: #fff;
    margin-left: 0;
    box-shadow: 0 1px 3px rgba(200,55,55,0.08);
}
.service-table td .delete-btn:hover {
    background: linear-gradient(90deg, #b52d2d 10%, #a12020 90%);
}
.add-service-btn {
    background: linear-gradient(90deg, #2d397a, #594285 90%);
    padding: 9px 22px;
    font-size: 16px;
    border: none;
    border-radius: 7px;
    color: #fff;
    font-weight: 600;
    box-shadow: 0 1px 6px rgba(60,180,80,0.11);
    cursor: pointer;
    transition: background 0.16s;
    margin-bottom: 0;
    text-decoration: none;
    float:right;
}
.add-service-btn:hover {
    text-decoration: none;
    color:#fff;
    background: linear-gradient(90deg, #594285, #2d397a 100%);
}
.internal-services-h1 {
    font-size: 2.14rem;
    font-weight: 700;
    color: #2b255d;
    margin: 0 0 23px 0;
    letter-spacing: 0.7px;
}
.classic-pagination {
    margin: 28px 0 10px 0;
    text-align: center;
}
.classic-pagination ul {
    list-style: none;
    padding-left: 0;
    display: inline-block;
    margin: 0;
    background: #f5f8fd;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(36,54,140,0.04);
    overflow: hidden;
}
.classic-pagination li {
    display: inline-block;
    margin: 0;
}
.classic-pagination a, .classic-pagination span {
    display: inline-block;
    color: #204886;
    padding: 8px 16px;
    font-size: 16px;
    text-decoration: none;
    font-weight: 600;
    outline: none;
    transition: color .13s;
    background: none;
}
.classic-pagination a:hover {
    color: #fff;
    background: #387add;
}
.classic-pagination .active {
    background: #2a70b4;
    color: #fff !important;
    border-radius: 0;
}
.classic-pagination .disabled {
    color: #8d97ab;
    pointer-events: none;
    background: #f5f8fd;
}
@media (max-width:1150px) {
    .service-table-container {padding: 4vw;}
    .internal-services-h1 {font-size: 1.38rem;}
    table.service-table {min-width: 780px;}
    .service-table th, .service-table td {font-size: 15px;}
}
@media (max-width: 750px) {
    .service-table-container {padding: 9px 1vw;}
    .service-table th, .service-table td {font-size: 13px;}
    .internal-services-h1 {font-size: 1.08rem;}
    .add-service-btn {padding: 7px 10px;font-size:14px;}
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
<!-- <link rel="stylesheet" href="css/services.css"> -->
<!-- <link rel="stylesheet" href="css/index.css"> -->
<!-- <link rel="stylesheet" href="css/events.css"> -->
</head>
<body>
    <div class="service-table-container">
        
        <?php if ($delete_success): ?>
            <div style="background:#e4fbe5;color:#09930c;padding:13px 13px 9px 13px;border-radius:5px;margin-bottom:13px;font-size:16px;">
                <?= esc($delete_success) ?>
            </div>
            <?php elseif ($delete_error): ?>
                <div style="background:#ffeced;color:#ba2b19;padding:13px 13px 9px 13px;border-radius:5px;margin-bottom:13px;font-size:16px;">
                    <?= esc($delete_error) ?>
                </div>
    <?php endif; ?>

    <!-- SERVICES TABLE -->
    <div class="main-section-block">
        <div class="main-section-header">
            <h1>Manage Services</h1>
            <a href="service_create.php" class="add-service-btn">+ Add New Service</a>
        </div>
        <table class="service-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ID</th>
                    <th>Service Name</th>
                    <th class="description-cell">Description</th>
                    <th class="service_image">Image</th>
                    <th style="text-align:center;min-width:130px;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            if (!empty($paged_services)) :
                $snum = $serial_start;
                foreach ($paged_services as $row): ?>
                <tr>
                    <td><?= $snum++; ?></td>
                    <td><?= esc($row['service_id']); ?></td>
                    <td><?= esc($row['service_title']); ?></td>
                    <td class="description-cell">
                        <div style="white-space:pre-line;"><?= esc($row['service_description']); ?></div>
                    </td>
                    <td class="service_image">
                        <?php
                            $img_name = !empty($row['service_image']) ? basename($row['service_image']) : '';
                            $img_path = !empty($img_name) ? '../images/' . $img_name : '';
                            if (!empty($img_name) && file_exists("../images/" . $img_name)) {
                                echo "<img src=\"" . esc($img_path) . "\" alt=\"service\" class=\"service-image-thumb\">";
                            } else {
                                echo "<span class='internal-no-image'>No image uploaded</span>";
                            }
                        ?>
                    </td>
                    <td style="text-align:center;">
                        <a href="service_edit.php?id=<?= esc($row['service_id']); ?>" class="edit-btn">Edit</a>
                        <form method="POST" action="" style="display:inline;margin:0;padding:0;" onsubmit="return confirm('Are you sure you want to delete this service?');">
                            <input type="hidden" name="delete_id" value="<?= esc($row['service_id']); ?>">
                            <button type="submit" class="delete-btn">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach;
            else: ?>
                <tr>
                    <td colspan="6" style="text-align:center;font-style:italic;background:#f2f3fb;color:#515575;font-size:17px;padding:38px 0;border-bottom:none;">
                        No services found.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>

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

    <!-- WHY AONE HUB TABLE -->
    <div class="main-section-block">
        <div class="main-section-header">
            <h2>Manage Why Aone Hub</h2>
            <a href="why_aone_hub_create.php" class="add-service-btn">+ Add New Why Aone Hub</a>
        </div>
        <table class="service-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th style="text-align:center;min-width:120px;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            if (!empty($why_rows)):
                $wnum = 1;
                foreach ($why_rows as $wrow): ?>
                <tr>
                    <td><?= $wnum++; ?></td>
                    <td><?= esc($wrow['why_id']); ?></td>
                    <td><?= esc($wrow['why_title']); ?></td>
                    <td>
                        <div style="white-space:pre-line;"><?= esc($wrow['why_description']); ?></div>
                    </td>
                    <td style="text-align:center;">
                        <a href="why_aone_hub_edit.php?id=<?= esc($wrow['why_id']); ?>" class="edit-btn">Edit</a>
                        <form method="POST" action="" style="display:inline;margin:0;padding:0;" onsubmit="return confirm('Are you sure you want to delete this Why Aone Hub entry?');">
                            <input type="hidden" name="delete_why_id" value="<?= esc($wrow['why_id']); ?>">
                            <button type="submit" class="delete-btn">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach;
            else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;font-style:italic;background:#f2f3fb;color:#515575;font-size:16px;padding:32px 0;border-bottom:none;">
                        No Why Aone Hub entries found.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>