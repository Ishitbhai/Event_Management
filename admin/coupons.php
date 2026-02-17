<?php
require_once("../database/db_connect.php");
session_start();
require('sidebar.php');

// Only admins allowed
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}

// Fetch all coupons
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
        ORDER BY c.coupon_created_at DESC";

$result = $conn->query($sql);

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Coupons Management</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="css/coupons.css">
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
                <?php $sr = 1; ?>
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

        <?php else: ?>
            <div style="text-align:center;padding:30px;color:#322053;">
                No coupons found.
            </div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>
