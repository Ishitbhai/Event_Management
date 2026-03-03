<?php
require_once "../database/db_connect.php";
session_start();
require('sidebar.php');

// Initialize field and error variables
$service_title = '';
$service_description = '';
$service_image = '';
$errors = [];
$success = '';

$field_errors = [
    'service_title' => [],
    'service_description' => [],
    'service_image' => [],
    'general' => []
];

function validate_service_form(&$field_errors, &$service_title, &$service_description, &$service_image, &$img_arr) {
    // Title
    if ($service_title === '') {
        $field_errors['service_title'][] = "Service title is required.";
    } elseif (mb_strlen($service_title) < 2) {
        $field_errors['service_title'][] = "Service title must be at least 2 characters.";
    }
    // Description
    if ($service_description === '') {
        $field_errors['service_description'][] = "Service description is required.";
    } elseif (mb_strlen($service_description) < 8) {
        $field_errors['service_description'][] = "Service description must be at least 8 characters.";
    }
    // Image now required AND must be jpg/jpeg/png only
    if (!$img_arr || $img_arr['error'] === UPLOAD_ERR_NO_FILE) {
        $field_errors['service_image'][] = "Service image is required.";
    } else {
        $allowed_exts = ['jpg','jpeg','png'];
        $ext = strtolower(pathinfo($img_arr['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts)) {
            $field_errors['service_image'][] = "Image must be a JPG, JPEG, or PNG file.";
        }
        if ($img_arr['size'] > 2 * 1024 * 1024) {
            $field_errors['service_image'][] = "Image must be less than 2MB.";
        }
        if ($img_arr['error'] !== UPLOAD_ERR_OK) {
            $field_errors['service_image'][] = "Image upload failed. (" . $img_arr['error'] . ")";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_title = trim($_POST['service_title'] ?? '');
    $service_description = trim($_POST['service_description'] ?? '');
    $image_uploaded = false;
    $img = $_FILES['service_image'] ?? null;

    // Validate all fields
    validate_service_form($field_errors, $service_title, $service_description, $service_image, $img);

    // If image passed validation, try to move (server-side)
    if (
        empty($field_errors['service_image']) &&
        $img &&
        $img['error'] !== UPLOAD_ERR_NO_FILE &&
        $img['error'] === UPLOAD_ERR_OK
    ) {
        $ext = strtolower(pathinfo($img['name'], PATHINFO_EXTENSION));
        $nameRand = bin2hex(random_bytes(8));
        $img_name = "service_" . date("Ymd_His") . "_$nameRand.$ext";
        $img_target = "../images/" . $img_name;
        if (!is_dir(dirname($img_target))) {
            mkdir(dirname($img_target), 0777, true);
        }
        if (move_uploaded_file($img['tmp_name'], $img_target)) {
            // Only store the image filename in the database (no path)
            $service_image = $img_name;
            $image_uploaded = true;
        } else {
            $field_errors['service_image'][] = "Failed to save uploaded image.";
        }
    }

    // Collect all errors for general handling (not used for inline fields)
    foreach ($field_errors as $arr) {
        foreach ($arr as $err) {
            $errors[] = $err;
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO services (service_title, service_description, service_image) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $service_title, $service_description, $service_image);
            if ($stmt->execute()) {
                $success = "Service created successfully!";
                $service_title = $service_description = $service_image = '';
            } else {
                $field_errors['general'][] = "Database error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $field_errors['general'][] = "Database connection error.";
        }
    } else {
        // Remove uploaded image if DB insert failed
        if ($image_uploaded && file_exists("../images/$service_image")) {
            unlink("../images/$service_image");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Service</title>
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
.form-container {
    background: #fff;
    border-radius: 13px;
    box-shadow: 0 1px 8px rgba(44,62,80,0.08);
    width: 100%;
    max-width: 1000px;
    min-width: 0;
    margin: 40px auto 0;
    padding: 34px 36px 28px;
    box-sizing: border-box;
}

.form-container h2 {
    margin-top: 0;
    color: #322053;
    margin-bottom: 12px;
    font-size: 2rem;
}

.form-field {
    margin-bottom: 19px;
}

.form-field label {
    font-weight: 600;
    color: #473b6f;
    margin-bottom: 8px;
    display: block;
    font-size: 1rem;
}

.form-field input[type="text"],
.form-field textarea {
    width: 100%;
    padding: 11px 13px;
    border-radius: 7px;
    border: 1px solid #bfc4d1;
    font-size: 16px;
    box-sizing: border-box;
    resize: none;
}

.form-field textarea {
    min-height: 74px;
}

.form-field input[type="file"] {
    padding: 5px 0;
    font-size: 16px;
    border-radius: 7px;
    border: 1px solid #dfdfeb;
    background: #fafaff;
    width: 100%;
    box-sizing: border-box;
}

.form-actions {
    margin-top: 24px;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}

.form-actions button {
    font-size: 16px;
    border-radius: 7px;
    border: none;
    padding: 9px 27px;
    background: linear-gradient(90deg, #2d397a, #594285 90%);
    color: #fff;
    font-weight: 600;
    letter-spacing: 0.02em;
    box-shadow: 0 1px 6px #2d397a11;
    cursor: pointer;
    transition: background .17s;
}

.form-actions button:hover {
    background: linear-gradient(90deg, #594285, #2d397a 100%);
}

.preview-img {
    max-width: 170px;
    max-height: 96px;
    border-radius: 6px;
    display: block;
    margin-top: 9px;
    object-fit: contain;
    border: 1px solid #eee;
    width: 100%;
}

.error-message-inline-field {
    color: #b70c26;
    font-size: 15px;
    margin-top: 7px;
    margin-bottom: 0;
    line-height: 1.7;
}

.error-message-inline-general {
    color: #b70c26;
    font-size: 16px;
    padding: 10px 0 6px 0;
}

@media (max-width: 900px) {
    .form-container {
        max-width: 95vw;
        padding: 24px 14px 18px;
    }
}

@media (max-width: 700px) {
    .form-container {
        margin: 28px 6px 0 6px;
        padding: 14px 5vw 9px 5vw;
    }
    .form-container h2 {
        font-size: 1.4rem;
    }
}

@media (max-width: 500px) {
    .form-container {
        max-width: 100vw;
        margin: 18px 0 0 0;
        padding: 8vw 4vw 10vw 4vw;
        border-radius: 0;
        box-shadow: none;
    }
    .form-field label {
        font-size: 0.97rem;
    }
    .form-field input[type="text"],
    .form-field textarea,
    .form-field input[type="file"] {
        font-size: 15px;
        padding: 8px 7px;
    }
    .form-actions {
        flex-direction: column;
        gap: 10px;
    }
    .form-actions button {
        width: 100%;
        font-size: 15px;
        padding: 11px 0;
    }
    .preview-img {
        max-width: 100%;
        max-height: 30vw;
        margin-top: 6px;
    }
    .form-container h2 {
        font-size: 1.15rem;
    }
}


    </style>
    <!-- <link rel="stylesheet" href="css/events.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/service_create.css"> -->

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function setError(field, msgArr) {
            const errEl = document.getElementById(field + '_err');
            if (errEl) {
                errEl.innerHTML = (msgArr && msgArr.length > 0)
                    ? msgArr.map(e=>escapeHTML(e)).join('<br>')
                    : '';
            }
        }
        function escapeHTML(str) {
            return str.replace(/[&<>'"]/g, function(tag) {
                const charsToReplace = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                };
                return charsToReplace[tag] || tag;
            });
        }
        function validateTitle() {
            const v = document.getElementById('service_title').value.trim();
            let errs = [];
            if (v.length === 0) errs.push('Service title is required.');
            else if (v.length < 2) errs.push('Service title must be at least 2 characters.');
            setError('service_title', errs);
            return errs.length === 0;
        }
        function validateDesc() {
            const v = document.getElementById('service_description').value.trim();
            let errs = [];
            if (v.length === 0) errs.push('Service description is required.');
            else if (v.length < 8) errs.push('Service description must be at least 8 characters.');
            setError('service_description', errs);
            return errs.length === 0;
        }
        function validateImage(showPreview = false) {
            const input = document.getElementById('service_image');
            let errs = [];
            let allowedExts = ['jpg','jpeg','png'];
            if (input.files.length) {
                const file = input.files[0];
                const ext = file.name.split('.').pop().toLowerCase();
                if (!allowedExts.includes(ext)) {
                    errs.push('Image must be a JPG, JPEG, or PNG file.');
                }
                if (file.size > 2 * 1024 * 1024) {
                    errs.push('Image must be less than 2MB.');
                }
            } else {
                errs.push('Service image is required.');
            }
            setError('service_image', errs);

            // Preview logic
            const previewEl = document.getElementById('service_image_preview');
            if (showPreview) {
                if (input.files && input.files[0] && errs.length === 0) {
                    const url = URL.createObjectURL(input.files[0]);
                    if (previewEl) {
                        previewEl.src = url;
                        previewEl.style.display = 'block';
                    }
                } else if (previewEl) {
                    previewEl.src = "#";
                    previewEl.style.display = 'none';
                }
            }
            return errs.length === 0;
        }

        // On change
        document.getElementById('service_title').addEventListener('input', validateTitle);
        document.getElementById('service_description').addEventListener('input', validateDesc);
        document.getElementById('service_image').addEventListener('change', function(e){
            validateImage(true);
        });

        // On submit
        document.querySelector('form').addEventListener('submit', function(e){
            let valid = validateTitle();
            valid = validateDesc() && valid;
            valid = validateImage(true) && valid;
            if (!valid) {
                e.preventDefault();
            }
        });
    });
    </script>
</head>
<body>
    <div class="form-container">
        <h2>Create Service</h2>
        <?php if ($success): ?>
            <div class="success-message"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($field_errors['general'])): ?>
            <div class="error-message-inline-general">
                <?php foreach ($field_errors['general'] as $e) {
                    echo htmlspecialchars($e) . "<br>";
                } ?>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" autocomplete="off" novalidate>
            <div class="form-field">
                <label for="service_title">Service Title<span style="color:#b70c26">*</span></label>
                <input type="text" id="service_title" name="service_title" maxlength="100"
                       value="<?= htmlspecialchars($service_title) ?>">
                <div class="error-message-inline-field" id="service_title_err">
                    <?php if (!empty($field_errors['service_title'])):
                        foreach ($field_errors['service_title'] as $e) {
                            echo htmlspecialchars($e) . "<br>";
                        }
                    endif; ?>
                </div>
            </div>
            <div class="form-field">
                <label for="service_description">Service Description<span style="color:#b70c26">*</span></label>
                <textarea id="service_description" name="service_description" maxlength="500"><?= htmlspecialchars($service_description) ?></textarea>
                <div class="error-message-inline-field" id="service_description_err">
                    <?php if (!empty($field_errors['service_description'])):
                        foreach ($field_errors['service_description'] as $e) {
                            echo htmlspecialchars($e) . "<br>";
                        }
                    endif; ?>
                </div>
            </div>
            <div class="form-field">
                <label for="service_image">Service Image<span style="color:#b70c26">*</span> <span style="font-weight:400; font-size:13px;color:#B0B1BE;">(JPG, JPEG or PNG, max 2MB)</span></label>
                <input type="file" id="service_image" name="service_image" accept=".jpg,.jpeg,.png">
                <?php if ($service_image): ?>
                    <img class="preview-img" id="service_image_preview" src="<?= htmlspecialchars('../images/' . $service_image) ?>" alt="Service image">
                <?php else: ?>
                    <img class="preview-img" id="service_image_preview" style="display:none;" src="#" alt="Service image">
                <?php endif; ?>
                <div class="error-message-inline-field" id="service_image_err">
                    <?php if (!empty($field_errors['service_image'])):
                        foreach ($field_errors['service_image'] as $e) {
                            echo htmlspecialchars($e) . "<br>";
                        }
                    endif; ?>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit">Create Service</button>
                <a href="services.php" style="padding:9px 18px; background:#ebebef;color:#473b6f;text-decoration:none;border-radius:7px;font-weight:600;">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
