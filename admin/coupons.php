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

// --- Pagination Setup ---
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$per_page = 10;

// For pagination, fetch COUNT(*) first for total coupons
$count_sql = "SELECT COUNT(*) as total FROM coupons";
$count_result = $conn->query($count_sql);
$total_coupons = 0;
if ($count_result && $row = $count_result->fetch_assoc()) {
    $total_coupons = (int)$row['total'];
}
$total_pages = max(ceil($total_coupons / $per_page), 1);
$start_index = ($page - 1) * $per_page;

// Main coupon SELECT with LIMIT/OFFSET for pagination
$sql = "SELECT 
            c.coupon_id,
            c.coupon_code,
            c.coupon_from_event_id,
            c.coupon_applied_to_event_id,
            c.coupon_user_id,
            c.coupon_discount,
            c.coupon_valid_till,
            c.coupon_is_used,
            c.coupon_created_at,
            e1.event_title AS event_from_title,
            e2.event_title AS event_applied_to_title,
            u.user_name AS coupon_user_name,
            u.user_email AS coupon_user_email
        FROM coupons c
            LEFT JOIN events e1 ON c.coupon_from_event_id = e1.event_id
            LEFT JOIN events e2 ON c.coupon_applied_to_event_id = e2.event_id
            LEFT JOIN users u ON c.coupon_user_id = u.user_id
        ORDER BY c.coupon_created_at DESC
        LIMIT $per_page OFFSET $start_index";

$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coupons Management</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<!-- <link rel="stylesheet" href="css/coupons.css"> -->
<style>
    body {
    margin: 0;
    background: #f4f6fb;
    overflow-x: hidden;
}

/* Main Layout */
.dashboard-main {
    padding: 40px;
}

.events-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.internal-header {
    margin: 0;
    color: #322053;
}

/* Create Button */
.create-event-btn {
    background: linear-gradient(90deg, #2d397a, #594285 90%);
    color: #fff;
    padding: 8px 20px;
    border: none;
    border-radius: 7px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: background .18s;
    letter-spacing: 0.02em;
    box-shadow: 0 2px 9px rgb(82 58 213 / 8%);
}
.create-event-btn:hover {
    background: linear-gradient(90deg, #594285, #2d397a 100%);
}
.create-event-link {
    text-decoration: none;
    display: inline-block;
}


/* Table Container */
.event-table-container {
    overflow-x: auto;
    margin-top: 20px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 10px rgba(44,62,80,0.09);
    padding: 16px;
}

/* Table */
table.event-table {
    border-collapse: collapse;
    min-width: 1000px;
    width: 100%;
}

/* Keyframes for row fade-in animation */
@keyframes fadeInRow {
    from {
        opacity: 0;
        transform: translateY(24px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.event-table th,
.event-table td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #e6e7f0;
    font-size: 15px;
    white-space: nowrap;
}

.event-table th {
    background: #f4f6fb;
    color: #322053;
    font-weight: 600;
}

.event-table tr:nth-child(even) {
    background: #f9fafe;
}

.event-table tr:hover {
    background: #f2f4fa;
    transition: background 0.1s;
}

/* Animate table rows to come in one by one */
.event-table tr {
    opacity: 0;
    animation-name: fadeInRow;
    animation-duration: 0.55s;
    animation-timing-function: cubic-bezier(0.27, 0.8, 0.43, 1.01);
    animation-fill-mode: forwards;
}

.event-table tr:nth-child(1) { animation-delay: 0.09s; }
.event-table tr:nth-child(2) { animation-delay: 0.18s; }
.event-table tr:nth-child(3) { animation-delay: 0.27s; }
.event-table tr:nth-child(4) { animation-delay: 0.36s; }
.event-table tr:nth-child(5) { animation-delay: 0.45s; }
.event-table tr:nth-child(6) { animation-delay: 0.54s; }
.event-table tr:nth-child(7) { animation-delay: 0.63s; }
.event-table tr:nth-child(8) { animation-delay: 0.72s; }
.event-table tr:nth-child(9) { animation-delay: 0.81s; }
.event-table tr:nth-child(10) { animation-delay: 0.90s; }
/* Add more if you expect more rows */

/* Buttons */
.edit-btn {
    border: none;
    border-radius: 5px;
    padding: 7px 16px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    background: linear-gradient(90deg, #327ac5 20%, #225085 80%);
    color: #fff;
    margin-right: 6px;
    transition: background 0.16s;
    text-decoration: none;
    display: inline-block;
}
.edit-btn:hover {
    background: linear-gradient(90deg, #225085, #327ac5 60%);
}

.delete-btn {
    border: none;
    border-radius: 5px;
    padding: 7px 16px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    background: linear-gradient(90deg, #e94242 20%, #b02626 80%);
    color: #fff;
    transition: background 0.16s;
    text-decoration: none;
    display: inline-block;
}
.delete-btn:hover {
    background: linear-gradient(90deg, #a51818, #e94242 60%);
}


/* Status colors */
.status-used {
    color: #c82f2f;
    font-weight: 600;
}
.status-unused {
    color: #197655;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 900px) {
    table.event-table { min-width: 800px; }
}

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
/* --- Used/Unused status for table --- */
.status-used {
    color: #356b19;
    font-weight: bold;
}
.status-unused {
    color: #bd5806;
    font-weight: bold;
}

@keyframes fadeInUpRowStagger {
    from { transform: translate3d(0, 12px, 0); opacity: 0; }
    to   { transform: translate3d(0, 0, 0); opacity: 1; }
}
@keyframes fadeInCellStagger {
    from { opacity: 0; }
    to   { opacity: 1; }
}

</style>
</head>
<body>

<div class="dashboard-main">
    <div class="events-header">
        <h2 class="internal-header">Coupons Management</h2>
        <a class="create-event-link" href="coupon_create.php">
            <button class="create-event-btn" type="button">
                Create Coupon
            </button>
        </a>
    </div>

    <div class="event-table-container">

        <?php if ($result && $result->num_rows > 0): ?>
        <table class="event-table">
            <thead>
                <tr>
                    <th>Sr No</th>
                    <th>ID</th>
                    <th>Code</th>
                    <th>From Event</th>
                    <th>Applied To</th>
                    <th>User</th>
                    <th>Discount</th>
                    <th>Valid Till</th>
                    <th>Used?</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $sr = $start_index + 1; ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $sr++; ?></td>
                    <td><?= (int)$row['coupon_id']; ?></td>
                    <td><?= esc($row['coupon_code']); ?></td>

                    <td>
                        <?php
                        if ($row['coupon_from_event_id']) {
                            echo esc($row['event_from_title'] ?: 'Event ID: '.$row['coupon_from_event_id']);
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>

                    <td>
                        <?php
                        if ($row['coupon_applied_to_event_id']) {
                            echo esc($row['event_applied_to_title'] ?: 'Event ID: '.$row['coupon_applied_to_event_id']);
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>

                    <td>
                        <?php
                        if ($row['coupon_user_id']) {
                            echo esc($row['coupon_user_name']) . "<br><small>" . esc($row['coupon_user_email']) . "</small>";
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>

                    <td><?= (int)$row['coupon_discount']; ?>%</td>
                    <td><?= esc($row['coupon_valid_till']); ?></td>

                    <td>
                        <?php if ($row['coupon_is_used'] == '1'): ?>
                            <span class="status-used">Used</span>
                        <?php else: ?>
                            <span class="status-unused">Not Used</span>
                        <?php endif; ?>
                    </td>

                    <td><?= esc($row['coupon_created_at']); ?></td>

                    <td>
                        <a class="edit-link" href="coupon_edit.php?id=<?= $row['coupon_id']; ?>">
                            <button class="edit-btn" type="button">
                                Edit
                            </button>
                        </a>

                        <a class="delete-link" href="coupon_delete.php?id=<?= $row['coupon_id']; ?>">
                            <button class="delete-btn" type="button">
                                Delete
                            </button>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
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

        <?php else: ?>
            <div style="text-align:center;padding:30px;color:#322053;">
                No coupons found.
            </div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>
