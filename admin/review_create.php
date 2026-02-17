<?php
require_once("../database/db_connect.php");
session_start();
require('sidebar.php');

// Only admins allowed
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}

// --- Helpers ---
function getEventOptions($conn, $selected = null) {
    $out = "";
    $result = $conn->query("SELECT event_id, event_title FROM events WHERE event_status = 'completed' ORDER BY event_title ASC");
    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {
            $sel = ($selected !== null && $selected == $r['event_id']) ? "selected" : "";
            $label = htmlspecialchars($r['event_title']) . " (ID: " . (int)$r['event_id'] . ")";
            $out .= '<option value="'.(int)$r['event_id'].'" '.$sel.'>'.$label.'</option>';
        }
    }
    return $out ?: "<option disabled>No completed events found</option>";
}
function getUserOptions($conn, $selected = null) {
    $out = "";
    $result = $conn->query("SELECT user_id, user_name, user_email FROM users ORDER BY user_name ASC");
    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {
            $sel = ($selected !== null && $selected == $r['user_id']) ? "selected" : "";
            $label = htmlspecialchars($r['user_name']) . " (" . htmlspecialchars($r['user_email']) . ")";
            $out .= '<option value="'.(int)$r['user_id'].'" '.$sel.'>'.$label.'</option>';
        }
    }
    return $out ?: "<option disabled>No users found</option>";
}

// --- POST handler ---
// Separate errors for each field
$errors_user_id = "";
$errors_event_id = "";
$errors_rating = "";
$errors_review = "";
$errors_general = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id_raw = $_POST['user_id'] ?? '';
    $event_id_raw = $_POST['event_id'] ?? '';
    $review_rating_raw = $_POST['review_rating'] ?? '';
    $review_review = trim($_POST['review_review'] ?? '');

    // Separate validation for each
    if ($user_id_raw === "" || !ctype_digit($user_id_raw) || (int)$user_id_raw < 1) {
        $errors_user_id = "User is required.";
    } else {
        // Exists validation
        $user_id = (int)$user_id_raw;
        $stmt = $conn->prepare("SELECT 1 FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows < 1) $errors_user_id = "Selected user does not exist.";
        $stmt->close();
    }

    if ($event_id_raw === "" || !ctype_digit($event_id_raw) || (int)$event_id_raw < 1) {
        $errors_event_id = "Event is required.";
    } else {
        $event_id = (int)$event_id_raw;
        $stmt2 = $conn->prepare("SELECT 1 FROM events WHERE event_id = ? AND event_status = 'completed'");
        $stmt2->bind_param("i", $event_id);
        $stmt2->execute();
        $stmt2->store_result();
        if ($stmt2->num_rows < 1) $errors_event_id = "Selected event does not exist or is not completed.";
        $stmt2->close();
    }

    if ($review_rating_raw === "" || !ctype_digit($review_rating_raw) || (int)$review_rating_raw < 1 || (int)$review_rating_raw > 5) {
        $errors_rating = "Rating must be between 1 and 5.";
    }

    if ($review_review === "") {
        $errors_review = "Review cannot be empty.";
    }

    // If no errors, insert
    if (!$errors_user_id && !$errors_event_id && !$errors_rating && !$errors_review) {
        $user_id = (int)$user_id_raw;
        $event_id = (int)$event_id_raw;
        $review_rating = (int)$review_rating_raw;
        $stmt = $conn->prepare("INSERT INTO reviews (user_id, event_id, review_rating, review_review, reviewed_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iiis", $user_id, $event_id, $review_rating, $review_review);
        if ($stmt->execute()) {
            $success = true;
            $new_id = $conn->insert_id;
            header("Location: reviews.php?msg=created");
            exit;
        } else {
            $errors_general[] = "Failed to save review.";
        }
        $stmt->close();
    }
}

// For value repopulation after submit error
function old($key) {
    return htmlspecialchars($_POST[$key] ?? '');
}
function oldSelected($key, $val) {
    return (isset($_POST[$key]) && $_POST[$key] == $val) ? "selected" : "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Event Review</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="css/events.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/review_create.css">
    <script src="js/jquery-4.0.0.min.js"></script>
</head>
<body>
<div class="review-form-wrapper">
    <h2>Create Event Review</h2>
    <?php
    if (!empty($errors_general)) {
        echo "<div class='error-message-inline'>";
        foreach ($errors_general as $e) {
            echo htmlspecialchars($e) . "<br>";
        }
        echo "</div>";
    }
    ?>
    <form method="post" action="review_create.php" autocomplete="off" id="reviewForm" novalidate>
        <label class="rf-label" for="user_id">User <span style="font-weight:400;font-size:13px;color:#7b6fa5"></span></label>
        <select id="user_id" name="user_id" class="rf-select" onchange="validateUserField()">
            <option value="">-- Select User --</option>
            <?= getUserOptions($conn, $_POST['user_id'] ?? null); ?>
        </select>
        <div class="rf-error" id="user_id_error" style="<?= $errors_user_id ? 'display:block;' : '' ?>"><?= htmlspecialchars($errors_user_id) ?></div>

        <label class="rf-label" for="event_id">Event <span style="font-weight:400;font-size:13px;color:#7b6fa5"></span></label>
        <select id="event_id" name="event_id" class="rf-select" onchange="validateEventField()">
            <option value="">-- Select Event --</option>
            <?= getEventOptions($conn, $_POST['event_id'] ?? null); ?>
        </select>
        <div class="rf-error" id="event_id_error" style="<?= $errors_event_id ? 'display:block;' : '' ?>"><?= htmlspecialchars($errors_event_id) ?></div>

        <label class="rf-label" for="review_rating">Rating</label>
        <select id="review_rating" name="review_rating" class="rf-select" onchange="validateRatingField()">
            <option value="">-- Select Rating --</option>
            <?php
                for ($i=5;$i>=1;$i--) {
                    $sel = oldSelected('review_rating', $i);
                    echo "<option value=\"$i\" $sel>$i Star".($i>1?'s':'')."</option>";
                }
            ?>
        </select>
        <div class="rf-error" id="review_rating_error" style="<?= $errors_rating ? 'display:block;' : '' ?>"><?= htmlspecialchars($errors_rating) ?></div>

        <label class="rf-label" for="review_review">Review</label>
        <textarea id="review_review" name="review_review" class="rf-textarea" oninput="validateReviewField()"><?= old('review_review'); ?></textarea>
        <div class="rf-error" id="review_review_error" style="<?= $errors_review ? 'display:block;' : '' ?>"><?= htmlspecialchars($errors_review) ?></div>

        <div class="rf-btn-row">
            <button type="submit" class="create-btn">Submit Review</button>
            <a href="reviews.php" class="cancel-btn">Cancel</a>
        </div>
    </form>
</div>
<script>
function validateUserField() {
    let value = $('#user_id').val();
    if (!value || !/^\d+$/.test(value) || parseInt(value,10) < 1) {
        $('#user_id_error').text('User is required.').show();
        return false;
    } else {
        $('#user_id_error').text('').hide();
        return true;
    }
}
function validateEventField() {
    let value = $('#event_id').val();
    if (!value || !/^\d+$/.test(value) || parseInt(value,10) < 1) {
        $('#event_id_error').text('Event is required.').show();
        return false;
    } else {
        $('#event_id_error').text('').hide();
        return true;
    }
}
function validateRatingField() {
    let value = $('#review_rating').val();
    if (!value || !/^[1-5]$/.test(value)) {
        $('#review_rating_error').text('Rating must be between 1 and 5.').show();
        return false;
    } else {
        $('#review_rating_error').text('').hide();
        return true;
    }
}
function validateReviewField() {
    let value = $('#review_review').val();
    if ($.trim(value) === '') {
        $('#review_review_error').text('Review cannot be empty.').show();
        return false;
    } else {
        $('#review_review_error').text('').hide();
        return true;
    }
}

$(document).ready(function(){
    $('#user_id').on('change', validateUserField);
    $('#event_id').on('change', validateEventField);
    $('#review_rating').on('change', validateRatingField);
    $('#review_review').on('input', validateReviewField);

    // Prevent submit if any field is invalid (optional, disable this for pure on-change if you'd like)
    $('#reviewForm').on('submit', function(e){
        let valid = true;
        if (!validateUserField()) valid = false;
        if (!validateEventField()) valid = false;
        if (!validateRatingField()) valid = false;
        if (!validateReviewField()) valid = false;
        if (!valid) e.preventDefault();
    });
});
</script>
</body>
</html>
