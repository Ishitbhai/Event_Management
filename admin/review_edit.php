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

// Value repopulation for form
function old($key, $arr = null) {
    if ($arr === null) {
        return htmlspecialchars($_POST[$key] ?? '');
    } else {
        return htmlspecialchars($arr[$key] ?? '');
    }
}
function oldSelected($key, $val, $arr = null) {
    $data = $arr ?? $_POST;
    return (isset($data[$key]) && $data[$key] == $val) ? "selected" : "";
}

// --- GET review details ---
$errors = [];
$success = false;
$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($review_id < 1) {
    die("Invalid review ID");
}

$stmt = $conn->prepare("SELECT * FROM reviews WHERE review_id = ?");
$stmt->bind_param("i", $review_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows < 1) {
    die("Review not found");
}
$review = $result->fetch_assoc();
$stmt->close();

// --- POST handler for editing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    $review_rating = isset($_POST['review_rating']) && $_POST['review_rating'] !== '' ? (int)$_POST['review_rating'] : null;
    $review_review = isset($_POST['review_review']) ? trim($_POST['review_review']) : null;

    // Separated validation for each input
    if ($user_id < 1) {
        $errors['user_id'] = "User is required.";
    } else {
        $stmt = $conn->prepare("SELECT 1 FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows < 1) {
            $errors['user_id'] = "Selected user does not exist.";
        }
        $stmt->close();
    }

    if ($event_id < 1) {
        $errors['event_id'] = "Event is required.";
    } else {
        $stmt2 = $conn->prepare("SELECT 1 FROM events WHERE event_id = ? AND event_status = 'completed'");
        $stmt2->bind_param("i", $event_id);
        $stmt2->execute();
        $stmt2->store_result();
        if ($stmt2->num_rows < 1) {
            $errors['event_id'] = "Selected event does not exist or is not completed.";
        }
        $stmt2->close();
    }

    if (!is_numeric($review_rating) || $review_rating < 1 || $review_rating > 5) {
        $errors['review_rating'] = "Rating must be between 1 and 5.";
    }

    if ($review_review === null || $review_review === "") {
        $errors['review_review'] = "Review cannot be empty.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE reviews SET user_id = ?, event_id = ?, review_rating = ?, review_review = ? WHERE review_id = ?");
        $stmt->bind_param("iiisi", $user_id, $event_id, $review_rating, $review_review, $review_id);
        if ($stmt->execute()) {
            $success = true;
            header("Location: reviews.php?msg=updated");
            exit;
        } else {
            $errors['db'] = "Failed to update review.";
        }
        $stmt->close();
    }
}

// If not posted or errors, repopulate with old or current db values:
$display = [];  
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $display = $_POST;
} else {
    $display = $review;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Event Review</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="css/events.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/review_edit.css">
    <script src="js/jquery-4.0.0.min.js"></script>
</head>
<body>
<div class="review-form-wrapper">
    <h2>Edit Event Review</h2>
    <?php
    if (!empty($errors['db'])) {
        echo "<div class='error-message-inline'>";
        echo htmlspecialchars($errors['db']) . "<br>";
        echo "</div>";
    }
    ?>
    <form method="post" action="review_edit.php?id=<?= $review_id ?>" autocomplete="off" id="reviewForm" novalidate>
        <label class="rf-label" for="user_id">User</label>
        <select id="user_id" name="user_id" class="rf-select" required>
            <option value="">-- Select User --</option>
            <?= getUserOptions($conn, $display['user_id'] ?? null); ?>
        </select>
        <div class="rf-error" id="user_id_error" style="display:<?= !empty($errors['user_id']) ? 'block' : 'none'; ?>;">
            <?php if (!empty($errors['user_id'])) echo htmlspecialchars($errors['user_id']); ?>
        </div>

        <label class="rf-label" for="event_id">Event</label>
        <select id="event_id" name="event_id" class="rf-select" required>
            <option value="">-- Select Event --</option>
            <?= getEventOptions($conn, $display['event_id'] ?? null); ?>
        </select>
        <div class="rf-error" id="event_id_error" style="display:<?= !empty($errors['event_id']) ? 'block' : 'none'; ?>;">
            <?php if (!empty($errors['event_id'])) echo htmlspecialchars($errors['event_id']); ?>
        </div>

        <label class="rf-label" for="review_rating">Rating</label>
        <select id="review_rating" name="review_rating" class="rf-select" required>
            <option value="">-- Select Rating --</option>
            <?php
                for ($i=5;$i>=1;$i--) {
                    $sel = oldSelected('review_rating', $i, $display);
                    echo "<option value=\"$i\" $sel>$i Star".($i>1?'s':'')."</option>";
                }
            ?>
        </select>
        <div class="rf-error" id="review_rating_error" style="display:<?= !empty($errors['review_rating']) ? 'block' : 'none'; ?>;">
            <?php if (!empty($errors['review_rating'])) echo htmlspecialchars($errors['review_rating']); ?>
        </div>

        <label class="rf-label" for="review_review">Review</label>
        <textarea id="review_review" name="review_review" class="rf-textarea" required><?= old('review_review', $display); ?></textarea>
        <div class="rf-error" id="review_review_error" style="display:<?= !empty($errors['review_review']) ? 'block' : 'none'; ?>;">
            <?php if (!empty($errors['review_review'])) echo htmlspecialchars($errors['review_review']); ?>
        </div>

        <div class="rf-btn-row">
            <button type="submit" class="create-btn">Save Changes</button>
            <a href="reviews.php" class="cancel-btn">Cancel</a>
        </div>
    </form>
</div>
<script>
$(document).ready(function () {
    // Per-field validation: on change/input, show error for that field only

    function validateUserField() {
        let value = $('#user_id').val();
        if (!value || isNaN(value) || parseInt(value) < 1) {
            $('#user_id_error').text('User is required.').show();
        } else {
            $('#user_id_error').text('').hide();
        }
    }

    function validateEventField() {
        let value = $('#event_id').val();
        if (!value || isNaN(value) || parseInt(value) < 1) {
            $('#event_id_error').text('Event is required.').show();
        } else {
            $('#event_id_error').text('').hide();
        }
    }

    function validateRatingField() {
        let value = $('#review_rating').val();
        let n = parseInt(value, 10);
        if (
            value === "" ||
            !/^[1-5]$/.test(value) ||
            isNaN(n) ||
            n < 1 ||
            n > 5
        ) {
            $('#review_rating_error').text('Rating must be between 1 and 5.').show();
        } else {
            $('#review_rating_error').text('').hide();
        }
    }

    function validateReviewField() {
        let value = $('#review_review').val();
        if ($.trim(value).length === 0) {
            $('#review_review_error').text('Review cannot be empty.').show();
        } else {
            $('#review_review_error').text('').hide();
        }
    }

    // Attach per-field validation handlers
    $('#user_id').on('change', validateUserField);
    $('#event_id').on('change', validateEventField);
    $('#review_rating').on('change', validateRatingField);

    // Display review error on BOTH change and input (fix for all cases)
    $('#review_review').on('input change', validateReviewField);

    // Initial error field display if server-side POST errors happened
    <?php if (!empty($errors['user_id'])): ?>
        $('#user_id_error').show();
    <?php endif; ?>
    <?php if (!empty($errors['event_id'])): ?>
        $('#event_id_error').show();
    <?php endif; ?>
    <?php if (!empty($errors['review_rating'])): ?>
        $('#review_rating_error').show();
    <?php endif; ?>
    <?php if (!empty($errors['review_review'])): ?>
        $('#review_review_error').show();
    <?php endif; ?>

    // Final validation on submit (calls each separate validator, in case user skipped field)
    $('#reviewForm').on('submit', function (e) {
        let isFormValid = true;

        validateUserField();
        if ($('#user_id_error').text() !== '') isFormValid = false;

        validateEventField();
        if ($('#event_id_error').text() !== '') isFormValid = false;

        validateRatingField();
        if ($('#review_rating_error').text() !== '') isFormValid = false;

        validateReviewField();
        if ($('#review_review_error').text() !== '') isFormValid = false;

        if (!isFormValid) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>
