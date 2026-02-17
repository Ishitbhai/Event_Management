<?php
require_once("../database/db_connect.php");
session_start();
require('sidebar.php');

// Only allow admin access
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}

function getEventTitleWithId($conn, $event_id) {
    $stmt = $conn->prepare("SELECT event_title FROM events WHERE event_id = ?");
    if (!$stmt) return 'Unknown';
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $stmt->bind_result($event_title);
    if ($stmt->fetch()) {
        $stmt->close();
        // Show as [id] title
        return "<span style='color:#9982c5;'>[{$event_id}]</span> " . htmlspecialchars($event_title);
    }
    $stmt->close();
    return 'Unknown';
}

function getUserNameWithId($conn, $user_id) {
    $stmt = $conn->prepare("SELECT user_name FROM users WHERE user_id = ?");
    if (!$stmt) return 'Unknown';
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($user_name);
    if ($stmt->fetch()) {
        $stmt->close();
        // Show as [id] name
        return "<span style='color:#9982c5;'>[{$user_id}]</span> " . htmlspecialchars($user_name);
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
    <link rel="stylesheet" href="css/events.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/reviews.css">
    
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
                <th>Review ID</th>
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
        $result = $conn->query("SELECT * FROM reviews ORDER BY reviewed_at DESC");
        // Counter for Sr No
        $sr_no = 1;
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $review_id = (int)$row['review_id'];
                $user_html = getUserNameWithId($conn, $row['user_id']); // Shows [id] Name
                $event_html = getEventTitleWithId($conn, $row['event_id']); // Shows [id] Title
                $rating = intval($row['review_rating']);
                $review = htmlspecialchars($row['review_review']);
                $reviewed_at = date("Y-m-d H:i", strtotime($row['reviewed_at']));
                echo "<tr>";
                echo "<td>" . $sr_no++ . "</td>";
                echo "<td>{$review_id}</td>";
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
</div>
</body>
</html>
