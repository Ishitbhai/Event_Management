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

// ---- Handle delete request ----
$delete_success = null;
$delete_error = null;

// Process POST delete request securely
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id']) && is_numeric($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    // Find the image filename for deletion
    $stmt = $conn->prepare("SELECT service_image FROM services WHERE service_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->bind_result($img_name);
    if ($stmt->fetch()) {
        $stmt->close();
        // Delete from DB
        $del_stmt = $conn->prepare("DELETE FROM services WHERE service_id = ?");
        $del_stmt->bind_param("i", $delete_id);
        if ($del_stmt->execute()) {
            // Delete image file if it exists and non-empty
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Services</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body {
            background: #f8fafc;
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            overflow-x: hidden;
        }
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
    </style>
</head>
<body>
<div class="service-table-container">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:30px;gap:20px;">
        <h1 class="internal-services-h1">Manage Services</h1>
        <a href="service_create.php" class="add-service-btn">+ Add New Service</a>
    </div>

    <?php if ($delete_success): ?>
        <div style="background:#e4fbe5;color:#09930c;padding:13px 13px 9px 13px;border-radius:5px;margin-bottom:13px;font-size:16px;">
            <?= esc($delete_success) ?>
        </div>
    <?php elseif ($delete_error): ?>
        <div style="background:#ffeced;color:#ba2b19;padding:13px 13px 9px 13px;border-radius:5px;margin-bottom:13px;font-size:16px;">
            <?= esc($delete_error) ?>
        </div>
    <?php endif; ?>

    <table class="service-table">
        <thead>
            <tr>
                <th>#</th>
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
                <td colspan="5" style="text-align:center;font-style:italic;background:#f2f3fb;color:#515575;font-size:17px;padding:38px 0;border-bottom:none;">
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
</body>
</html>