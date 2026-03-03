<?php
session_start();
require_once('sidebar.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once('../database/db_connect.php');

// Add event_price to the fields list
$fields = [
    'event_id',
    'owner_id',
    'event_category',
    'event_title',
    'event_description',
    'event_date',
    'event_start_time',
    'event_end_time',
    'event_seats',
    'event_available_seats',
    'event_registration_deadline',
    'event_approval_status',
    'event_status',
    'event_paymeny_status',
    'event_price', // <-- new column
    'event_banner_image',
    'event_gallery_images',
    'event_is_featured',
    'created_at',
    'updated_at'
];

// --- Handle AJAX update for dropdown in event table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['table_ajax_update'])) {
    $output = ['status' => 0, 'msg' => ''];
    $eid = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $col = isset($_POST['col']) ? $_POST['col'] : '';
    $val = isset($_POST['val']) ? $_POST['val'] : '';
    if (in_array($col, ['event_status', 'event_approval_status', 'event_paymeny_status', 'event_is_featured']) && $eid > 0){

        $extraSet = '';
        $types = 'si';
        $vals = [$val, $eid];
        if ($col === 'event_approval_status') {
            if ($val === 'approved') {
                $extraSet = ', event_status = ?';
                $types = 'ssi';
                $vals = [$val, 'published', $eid];
            } elseif ($val === 'rejected') {
                $extraSet = ', event_status = ?';
                $types = 'ssi';
                $vals = [$val, 'cancelled', $eid];
            } elseif ($val === 'pending') {
                $extraSet = ', event_status = ?';
                $types = 'ssi';
                $vals = [$val, 'draft', $eid];
            }
        }
        // Add `updated_at` field to be set to the current timestamp
        $stmt = $conn->prepare("UPDATE events SET `$col`=?$extraSet, updated_at=CURRENT_TIMESTAMP WHERE event_id=?");
        if ($stmt) {
            $stmt->bind_param($types, ...$vals);
            if ($stmt->execute()) {
                $output['status'] = 1;
                $output['msg'] = "Updated!";
                if ($col === 'event_approval_status') {
                    if ($val === 'approved') {
                        $output['event_status'] = 'published';
                    } elseif ($val === 'rejected') {
                        $output['event_status'] = 'cancelled';
                    } elseif ($val === 'pending') {
                        $output['event_status'] = 'draft';
                    }
                }
            } else {
                $output['msg'] = "Error while updating database.";
            }
            $stmt->close();
        } else {
            $output['msg'] = "Failed to prepare statement.";
        }
    } else {
        $output['msg'] = "Invalid operation.";
    }
    header('Content-Type: application/json');
    echo json_encode($output);
    exit;
}

// ---- Fetch and sort events for display ----
$events = [];
$event_columns = [];
$fetch_result = $conn->query("SELECT * FROM events");
if ($fetch_result && $fetch_result->num_rows > 0) {
    $event_columns = array_keys($fetch_result->fetch_assoc());
    $fetch_result->data_seek(0);
    while ($row = $fetch_result->fetch_assoc()) {
        $events[] = $row;
    }
} elseif ($fetch_result) {
    $event_columns = [];
}

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Sort events by event_start_time in ascending order
usort($events, function($a, $b) {
    $dateCmp = strcmp($a['event_date'], $b['event_date']);
    if ($dateCmp !== 0) {
        return $dateCmp;
    }
    $a_time = $a['event_start_time'] ?? '';
    $b_time = $b['event_start_time'] ?? '';
    return strcmp($a_time, $b_time);
});

$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0
    ? (int)$_GET['page']          
    : 1;
$per_page = 10;                   
$total_events = count($events);   
$total_pages = ceil($total_events / $per_page); 
$start_index = ($page - 1) * $per_page;         
$paged_events = array_slice($events, $start_index, $per_page); 
$serial_start = $start_index + 1; 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Events</title>
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
    <!-- <link rel="stylesheet" href="css/events.css"> -->
    <!-- <link rel="stylesheet" href="css/index.css"> -->
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // dropdown change logic
        document.querySelectorAll('.table-edit-select').forEach(function(el){
            el.addEventListener('change', function(){
                const selectEl = this;
                const event_id = selectEl.getAttribute('data-eid');
                const col = selectEl.getAttribute('data-col');
                const val = selectEl.value;
                const cell = selectEl.parentElement;
                let spinner = cell.querySelector('.inline-dropdown-spinner');
                if (!spinner) {
                    spinner = document.createElement('span');
                    spinner.className = 'inline-dropdown-spinner';
                    spinner.style.display = '';
                    cell.appendChild(spinner);
                } else {
                    spinner.style.display = '';
                }
                selectEl.disabled = true;
                fetch(window.location.pathname + window.location.search, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        table_ajax_update: 1,
                        event_id: event_id,
                        col: col,
                        val: val
                    })
                }).then(r=>r.json())
                .then(data=>{
                    spinner.style.display = 'none';
                    selectEl.disabled = false;
                    window.location.reload();
                }).catch(err=>{
                    spinner.style.display = 'none';
                    selectEl.disabled = false;
                    window.location.reload();
                });
            });
        });

        // Create Event button logic
        let createBtn = document.querySelector('.create-event-btn');
        if (createBtn) {
            createBtn.addEventListener('click', function() {
                window.location.href = 'event_create.php';
            });
        }

        // Edit buttons event delegation
        document.querySelectorAll('.edit-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                let tr = btn.closest('tr[data-event-id]');
                if (tr) {
                    let eid = tr.getAttribute('data-event-id');
                    window.location.href = 'events_edit.php?event_id=' + eid;
                }
            });
        });

        // Delete buttons event delegation
        document.querySelectorAll('.delete-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                let tr = btn.closest('tr[data-event-id]');
                if (tr) {
                    let eid = tr.getAttribute('data-event-id');
                    if (confirm('Are you sure you want to delete this event?')) {
                        window.location.href = '?delete_event_id=' + eid;
                    }
                }
            });
        });

    });
    </script>
</head>
<body>
    <div class="dashboard-main">
        <div class="events-header">
            <h2 class="internal-header">Manage Events</h2>
            <button class="create-event-btn" type="button">
                Create Event
            </button>
        </div>
        <div class="event-table-container">
        <?php if ($total_events === 0): ?>
            <div class="internal-no-events">
                No events available.
            </div>
        <?php else: ?>
            <table class="event-table">
                <thead>
                    <tr>
                        <th>Sr. No.</th>
                        <?php
                        $col_headings = [
                            'event_id' => 'Event ID',
                            'owner_id' => 'Owner ID',
                            'event_category' => 'Category',
                            'event_title' => 'Title',
                            'event_description' => 'Description',
                            'event_date' => 'Event Date',
                            'event_start_time' => 'Start Time',
                            'event_end_time' => 'End Time',
                            'event_seats' => 'Seats',
                            'event_available_seats' => 'Available Seats',
                            'event_registration_deadline' => 'Registration Deadline',
                            'event_approval_status' => 'Approval Status',
                            'event_status' => 'Status',
                            'event_paymeny_status' => 'Payment Status',
                            'event_price' => 'Event Price', // Add heading for event_price
                            'event_banner_image' => 'Banner',
                            'event_gallery_images' => 'Gallery',
                            'event_is_featured' => 'Featured',
                            'created_at' => 'Created At',
                            'updated_at' => 'Updated At'
                        ];
                        foreach ($fields as $col): ?>
                            <th class="<?php echo esc($col); echo $col==='event_description'?' description-cell':'';?>">
                                <?= esc($col_headings[$col] ?? $col) ?>
                            </th>
                        <?php endforeach; ?>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $snum = $serial_start;
                    foreach ($paged_events as $ev): ?>
                        <tr data-event-id="<?php echo (int)$ev['event_id']; ?>">
                            <td><?= $snum++; ?></td>
                            <?php foreach ($fields as $col): ?>
                                <td class="<?= esc($col) ?><?= $col === 'event_description' ? ' description-cell' : '' ?>">
                                <?php
                                    // Banner image (image only, no name)
                                    if ($col === 'event_banner_image') {
                                        $img_name = !empty($ev[$col]) ? basename($ev[$col]) : '';
                                        $img_path = !empty($img_name) ? '../images/' . $img_name : '';
                                        if (!empty($img_name) && file_exists("../images/" . $img_name)) {
                                            echo "<img src=\"" . esc($img_path) . "\" alt=\"banner\" class=\"event-banner-thumb\">";
                                        } else {
                                            echo "<span class='internal-no-image'>No image uploaded</span>";
                                        }
                                    }
                                    // Gallery images (images only, comma separated)
                                    else if ($col === 'event_gallery_images') {
                                        if (!empty($ev[$col])) {
                                            $gallery = explode(',', $ev[$col]);
                                            $hasImg = false;
                                            foreach ($gallery as $gimg) {
                                                $gimg_name = trim(basename($gimg));
                                                if ($gimg_name) {
                                                    $gimgSrc = '../images/' . $gimg_name;
                                                    if (file_exists("../images/" . $gimg_name)) {
                                                        $hasImg = true;
                                                        echo "<img src=\"" . esc($gimgSrc) . "\" class=\"event-gallery-thumb\" alt=\"gallery\">";
                                                    }
                                                }
                                            }
                                            if (!$hasImg) {
                                                echo "<span class='internal-no-image'>No image uploaded</span>";
                                            }
                                        } else {
                                            echo "<span class='internal-no-image'>No image uploaded</span>";
                                        }
                                    }
                                    // Dropdown fields for event table
                                    else if ($col === 'event_approval_status') {
                                        $opts = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
                                        echo '<select class="table-edit-select" data-eid="' . (int)$ev['event_id'] . '" data-col="event_approval_status">';
                                        foreach ($opts as $k => $v)
                                            echo '<option value="' . esc($k) . '"' . ($ev[$col] === $k ? ' selected' : '') . '>' . esc($v) . '</option>';
                                        echo '</select>';
                                        echo '<span class="inline-dropdown-spinner" style="display:none;"></span>';
                                    }
                                    else if ($col === 'event_status') {
                                        $opts = [
                                            'draft' => 'Draft',
                                            'published' => 'Published',
                                            'ongoing' => 'Ongoing',
                                            'cancelled' => 'Cancelled',
                                            'completed' => 'Completed'
                                        ];
                                        echo '<select class="table-edit-select" data-eid="' . (int)$ev['event_id'] . '" data-col="event_status">';
                                        foreach ($opts as $k => $v)
                                            echo '<option value="' . esc($k) . '"' . ($ev[$col] === $k ? ' selected' : '') . '>' . esc($v) . '</option>';
                                        echo '</select>';
                                        echo '<span class="inline-dropdown-spinner" style="display:none;"></span>';
                                    }
                                    else if ($col === 'event_paymeny_status') {
                                        $opts = ['pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed'];
                                        echo '<select class="table-edit-select" data-eid="' . (int)$ev['event_id'] . '" data-col="event_paymeny_status">';
                                        foreach ($opts as $k => $v)
                                            echo '<option value="' . esc($k) . '"' . ($ev[$col] === $k ? ' selected' : '') . '>' . esc($v) . '</option>';
                                        echo '</select>';
                                        echo '<span class="inline-dropdown-spinner" style="display:none;"></span>';
                                    }
                                    // Featured dropdown (0 / 1)
                                    else if ($col === 'event_is_featured') {
                                        $opts = ['0' => 'No', '1' => 'Yes'];
                                        echo '<select class="table-edit-select" data-eid="' . (int)$ev['event_id'] . '" data-col="event_is_featured">';
                                        foreach ($opts as $k => $v)
                                            echo '<option value="' . esc($k) . '"' . ($ev[$col] == $k ? ' selected' : '') . '>' . esc($v) . '</option>';
                                        echo '</select>';
                                        echo '<span class="inline-dropdown-spinner" style="display:none;"></span>';
                                    }
                                    else if ($col === 'event_description') {
                                        echo '<div class="internal-description-cell-div">'.esc($ev[$col]).'</div>';
                                    }
                                    else if ($col === 'created_at' || $col === 'updated_at') {
                                        $dt = esc($ev[$col]);
                                        if ($dt) {
                                            echo '<span class="internal-created-updated">'. $dt .'</span>';
                                        } else {
                                            echo '-';
                                        }
                                    }
                                    // Custom cell for event_price: display as money if numeric
                                    else if ($col === 'event_price') {
                                        $price = $ev[$col];
                                        if (is_numeric($price)) {
                                            echo '<span class="internal-event-price">&#8377; '.esc(number_format($price, 2)).'</span>';
                                        } else {
                                            echo esc($price);
                                        }
                                    }
                                    else {
                                        echo esc($ev[$col]);
                                    }
                                ?>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <button
                                    class="edit-btn"
                                    type="button"
                                >Edit</button>
                                <button 
                                    class="delete-btn"
                                    type="button"
                                >Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
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
    <?php
    // --- Delete event handler (GET param for quick admin use) ---
    if (isset($_GET['delete_event_id']) && is_numeric($_GET['delete_event_id'])) {
        $del_id = intval($_GET['delete_event_id']);
        if ($del_id > 0) {
            // First, delete all bookings associated with this event
            $stmt_b = $conn->prepare("DELETE FROM bookings WHERE event_id=?");
            if ($stmt_b) {
                $stmt_b->bind_param("i", $del_id);
                $stmt_b->execute();
                $stmt_b->close();
            }
            // Then, delete the event itself
            $stmt = $conn->prepare("DELETE FROM events WHERE event_id=?");
            if ($stmt) {
                $stmt->bind_param("i", $del_id);
                $stmt->execute();
                $stmt->close();
                echo "<script>window.location.href=window.location.pathname;</script>";
                exit;
            }
        }
    }
    ?>
</body>
</html>
