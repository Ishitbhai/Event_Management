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
