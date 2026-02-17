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
    <link rel="stylesheet" href="css/services.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/events.css">
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