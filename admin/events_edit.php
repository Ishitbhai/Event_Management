
<?php
session_start();
require_once('sidebar.php');
require_once('../database/db_connect.php');

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get event_id from URL
$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($event_id <= 0) {
    echo "<div class=\"error-message-inline\">No valid event selected.</div>";
    exit;
}

// Fetch event data
$event = [];
$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows === 1) {
    $event = $result->fetch_assoc();
} else {
    echo "<div class=\"error-message-inline\">Event not found.</div>";
    exit;
}
$stmt->close();

// Fetch all users for owner_id dropdown EXCEPT those with user_type = 'user'
$all_users = [];
$user_query = $conn->query("SELECT user_id, user_name FROM users WHERE user_type <> 'user'");
if ($user_query) {
    while ($row = $user_query->fetch_assoc()) {
        $all_users[] = $row;
    }
}

// Fetch all categories for event_category dropdown AND their seat max for later lookup
$all_categories = [];
$category_max_seats = [];
$cat_query = $conn->query("SELECT category_id, category_name, category_seats FROM category");
if ($cat_query) {
    while ($cat_query_row = $cat_query->fetch_assoc()) {
        $all_categories[] = $cat_query_row;
        $category_max_seats[$cat_query_row['category_id']] = isset($cat_query_row['category_seats']) ? intval($cat_query_row['category_seats']) : null;
    }
}

// Helper to escape HTML
function esc($x) { return htmlspecialchars($x ?? '', ENT_QUOTES, 'UTF-8'); }

// Find field list for events table dynamically (ensures showing all fields)
$columns = [];
$res = $conn->query("SHOW COLUMNS FROM events");
$column_types = [];
while ($col = $res->fetch_assoc()) {
    $columns[] = $col['Field'];
    $column_types[$col['Field']] = $col['Type'];
}

// Map: event_category is integer (ID), but display category_name in dropdown.
function getCategoryIdByName($all_categories, $name) {
    foreach ($all_categories as $cat) {
        if ($cat['category_name'] == $name) return $cat['category_id'];
    }
    return null;
}
function getCategoryNameById($all_categories, $id) {
    foreach ($all_categories as $cat) {
        if ($cat['category_id'] == $id) return $cat['category_name'];
    }
    return null;
}

function datetime_local($dt) {
    if (!$dt) return '';
    if (strpos($dt,'T') !== false) return substr($dt,0,16);
    return str_replace(' ','T',substr($dt,0,16));
}

// --- Validation & update ---
$update_success = false;
$field_errors = [];
$field_errors_js = [];

$conflict_err = '';
$event_seats_max_for_category = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event'])) {

    $banner_upload_dir =  '../images/';
    $gallery_upload_dir = '../images/';
    $banner_filename = $event['event_banner_image'];
    $gallery_filenames = $event['event_gallery_images'];

    // --- Banner Image Upload & Replace logic ---
    if (
        isset($_FILES['event_banner_image']) &&
        is_uploaded_file($_FILES['event_banner_image']['tmp_name'])
    ) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES['event_banner_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $field_errors['event_banner_image'] = "Banner must be an image file (jpg, jpeg, png, gif, webp).";
        } else {
            $new_banner = uniqid('banner_').'.'.$ext;
            if (!move_uploaded_file($_FILES['event_banner_image']['tmp_name'], $banner_upload_dir.$new_banner)) {
                $field_errors['event_banner_image'] = "Failed to upload banner image.";
            } else {
                // DO NOT REMOVE/UNLINK ANY OLD IMAGE UNDER ANY CIRCUMSTANCES!
                $banner_filename = $new_banner;
            }
        }
    }

    // --- Gallery Images Upload & Replace logic ---
    $new_gallery_names = [];
    if (
        isset($_FILES['event_gallery_images']) &&
        isset($_FILES['event_gallery_images']['name']) &&
        is_array($_FILES['event_gallery_images']['name']) &&
        count(array_filter($_FILES['event_gallery_images']['name'])) > 0
    ) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        // DO NOT REMOVE/UNLINK ANY OLD GALLERY IMAGE EVER!
        foreach ($_FILES['event_gallery_images']['name'] as $idx => $gname) {
            if ($gname == '') continue;
            $ext = strtolower(pathinfo($gname, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $field_errors['event_gallery_images'] = "All gallery images must be image files (jpg, jpeg, png, gif, webp).";
                break;
            }
            $new_gallery = uniqid('gallery_') . '_' . $idx . '.' . $ext;
            if (!move_uploaded_file($_FILES['event_gallery_images']['tmp_name'][$idx], $gallery_upload_dir.$new_gallery)) {
                $field_errors['event_gallery_images'] = "Failed to upload one or more gallery images.";
                break;
            }
            $new_gallery_names[] = $new_gallery;
        }
        if (empty($field_errors['event_gallery_images'])) {
            $gallery_filenames = implode(';', $new_gallery_names);
        }
    }

    unset($_POST['event_date']);
    $auto_event_date = '';
    if (isset($_POST['event_start_time']) && $_POST['event_start_time'] != '') {
        $dt = $_POST['event_start_time'];
        $parts = preg_split('/[ T]/', $dt);
        $auto_event_date = $parts[0];
        $_POST['event_date'] = $auto_event_date;
    }

    $selected_category_id = isset($_POST['event_category']) ? intval($_POST['event_category']) : (isset($event['event_category']) ? intval($event['event_category']) : null);
    $event_seats_max_for_category = isset($category_max_seats[$selected_category_id]) ? $category_max_seats[$selected_category_id] : null;
    $new_event_start = isset($_POST['event_start_time']) ? $_POST['event_start_time'] : '';
    $new_event_end = isset($_POST['event_end_time']) ? $_POST['event_end_time'] : '';
    $new_created_at = isset($_POST['created_at']) ? $_POST['created_at'] : '';
    $new_event_seats = isset($_POST['event_seats']) ? intval($_POST['event_seats']) : null;
    $new_updated_at = isset($_POST['updated_at']) ? $_POST['updated_at'] : '';

    if ($new_event_start && $new_event_end && strtotime($new_event_start) >= strtotime($new_event_end)) {
        $field_errors['event_start_time'] = "Event Start Time must be before Event End Time.";
        $field_errors['event_end_time'] = "Event Start Time must be before Event End Time.";
    }
    if (isset($_POST['event_seats']) && isset($_POST['event_available_seats']) && intval($_POST['event_available_seats']) > intval($_POST['event_seats'])) {
        $field_errors['event_available_seats'] = "Available seats must not be greater than Event Seats.";
        $field_errors['event_seats'] = "Available seats must not be greater than Event Seats.";
    }
    if ($event_seats_max_for_category !== null && $new_event_seats !== null && $new_event_seats > $event_seats_max_for_category) {
        $field_errors['event_seats'] = "Event Seats cannot exceed the maximum allowed for this category (" . intval($event_seats_max_for_category) . ").";
    }
    if ($new_created_at) {
        $created_ts = strtotime($new_created_at);
        $date_fields = ['event_start_time', 'event_end_time'];
        foreach ($date_fields as $df) {
            if (!empty($_POST[$df])) {
                $target = strtotime($_POST[$df]);
                if ($target && $created_ts && $target < $created_ts) {
                    $human_label = ucwords(str_replace('_',' ', $df));
                    $field_errors[$df] = $human_label . " cannot be before Created At.";
                }
            }
        }
    }
    if (empty($field_errors)) {
        $start_changed = isset($_POST['event_start_time']) && datetime_local($_POST['event_start_time']) !== datetime_local($event['event_start_time']);
        $end_changed   = isset($_POST['event_end_time']) && datetime_local($_POST['event_end_time']) !== datetime_local($event['event_end_time']);
        $date_changed  = ($auto_event_date && $auto_event_date !== $event['event_date']);
        $category_changed = ($selected_category_id !== null && intval($event['event_category']) !== $selected_category_id);

        if ($category_changed) {
            $event['event_category'] = $selected_category_id;
            if ($event_seats_max_for_category !== null && $new_event_seats > $event_seats_max_for_category) {
                $_POST['event_seats'] = $event_seats_max_for_category;
            }
        }

        if ($date_changed || $start_changed || $end_changed) {
            $date = $auto_event_date;
            $start = $_POST['event_start_time'];
            $end = $_POST['event_end_time'];
            $q = $conn->prepare("SELECT event_id FROM events WHERE event_id != ? AND event_date = ? AND ((? < event_end_time AND ? > event_start_time))");
            $q->bind_param("isss", $event_id, $date, $start, $end);
            $q->execute();
            $qres = $q->get_result();
            if ($qres && $qres->num_rows > 0) {
                $field_errors['event_start_time'] = "Conflict: There is another event with overlapping date/time.";
                $field_errors['event_end_time'] = "Conflict: There is another event with overlapping date/time.";
            }
            $q->close();
        }
    }

    if (empty($field_errors)) {
        $update_fields = [];
        $update_params = [];
        $update_types = '';
        $ordered_columns = [];
        foreach ($columns as $i => $col) {
            if ($col === 'updated_at') {
                if (in_array('created_at', $columns) && !in_array('created_at', $ordered_columns)) {
                    $ordered_columns[] = 'created_at';
                }
                $ordered_columns[] = $col;
            } elseif ($col !== 'created_at') {
                $ordered_columns[] = $col;
            }
        }
        if (in_array('created_at', $columns) && !in_array('created_at', $ordered_columns)) {
            $ordered_columns[] = 'created_at';
        }

        foreach ($ordered_columns as $col) {
            if ($col == 'event_id') continue;

            if ($col == 'updated_at') {
                if (isset($_POST['updated_at']) && trim($_POST['updated_at']) !== '' && $_POST['updated_at'] !== $event['updated_at']) {
                    $update_types .= 's';
                    $update_params[] = $_POST['updated_at'];
                    $update_fields[] = "updated_at = ?";
                } else {
                    $update_fields[] = "updated_at = CURRENT_TIMESTAMP";
                }
                continue;
            }

            if ($col == 'created_at') {
                $value = isset($_POST['created_at']) ? $_POST['created_at'] : (isset($event['created_at']) ? $event['created_at'] : null);
                $update_types .= 's';
                $update_params[] = $value;
                $update_fields[] = "created_at = ?";
                continue;
            }

            if ($col == 'event_category') {
                if (isset($_POST['event_category'])) {
                    $v = $_POST['event_category'];
                    $update_types .= 'i';
                    $update_params[] = intval($v);
                    $update_fields[] = "$col = ?";
                } else {
                    $update_types .= 'i';
                    $update_params[] = isset($event[$col]) ? intval($event[$col]) : null;
                    $update_fields[] = "$col = ?";
                }
                continue;
            }
            if ($col == 'owner_id') {
                if (isset($_POST['owner_id'])) {
                    $v = $_POST['owner_id'];
                    $update_types .= 'i';
                    $update_params[] = intval($v);
                    $update_fields[] = "$col = ?";
                } else {
                    $update_types .= 'i';
                    $update_params[] = isset($event[$col]) ? intval($event[$col]) : null;
                    $update_fields[] = "$col = ?";
                }
                continue;
            }

            if ($col == 'event_date') {
                $ev_date = $auto_event_date;
                $update_types .= 's';
                $update_params[] = $ev_date;
                $update_fields[] = "event_date = ?";
                continue;
            }

            if ($col == 'event_banner_image') {
                $update_types .= 's';
                $update_params[] = $banner_filename;
                $update_fields[] = "$col = ?";
                continue;
            }
            if ($col == 'event_gallery_images') {
                $update_types .= 's';
                $update_params[] = $gallery_filenames;
                $update_fields[] = "$col = ?";
                continue;
            }

            $value = isset($_POST[$col]) ? $_POST[$col] : (isset($event[$col]) ? $event[$col] : null);

            if ($col == 'event_seats') {
                if ($event_seats_max_for_category !== null && intval($value) > $event_seats_max_for_category) {
                    $value = $event_seats_max_for_category;
                }
                $update_types .= 'i';
                $update_params[] = intval($value);
                $update_fields[] = "$col = ?";
                continue;
            }
            if ($col == 'event_available_seats') {
                $update_types .= 'i';
                $update_params[] = intval($value);
                $update_fields[] = "$col = ?";
                continue;
            }

            $update_types .= 's';
            $update_params[] = $value;
            $update_fields[] = "$col = ?";
        }
        $update_params[] = $event_id;
        $update_types .= 'i';

        $sql = "UPDATE events SET " . implode(',', $update_fields) . " WHERE event_id=?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param($update_types, ...$update_params);
            if ($stmt->execute()) {
                $update_success = true;
                $stmt->close();
                $stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
                $stmt->bind_param("i", $event_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows === 1) $event = $res->fetch_assoc();
                $stmt->close();
            } else {
                $field_errors['_global'] = "Failed to update event: " . esc($stmt->error);
            }
        } else {
            $field_errors['_global'] = "Prepare failed: " . esc($conn->error);
        }
    }
}

if (!empty($field_errors)) {
    $field_errors_js = array_keys($field_errors);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Event</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="css/event_edit.css">
    <link rel="stylesheet" href="css/index.css">
    <script src="js/jquery-4.0.0.min.js"></script>
    <script>
    var categoryMaxSeats = <?php echo json_encode($category_max_seats); ?>;
    var phpFieldErrors = <?php echo json_encode($field_errors); ?>;
    $(document).ready(function () {
        function enforceSeatsMax() {
            var catId = $("#event_category").val();
            var maxSeats = categoryMaxSeats[catId];
            if (maxSeats && !isNaN(maxSeats)) {
                $("#event_seats").attr("max", maxSeats);
                var currentVal = parseInt($("#event_seats").val(), 10);
                if (currentVal > maxSeats) {
                    $("#event_seats").val(maxSeats);
                }
            } else {
                $("#event_seats").removeAttr("max");
            }
        }

        enforceSeatsMax();
        $("#event_category").on("change", function() {
            enforceSeatsMax();
        });

        var seats = document.getElementById('event_seats');
        var available = document.getElementById('event_available_seats');
        if (seats && available) {
            seats.addEventListener('input', function() {
                enforceSeatsMax();
                if (parseInt(available.value) > parseInt(seats.value)) {
                    available.value = seats.value;
                }
                available.max = seats.value;
            });
            available.addEventListener('input', function() {
                if (parseInt(available.value) > parseInt(seats.value)) {
                    available.value = seats.value;
                }
            });
        }

        // Client-side validation
        $(".edit-event-form").submit(function(e) {
            $(".error-msg.form-js").remove();

            let createdAt = $("#created_at").val();
            let eventStartTime = $("#event_start_time").val();
            let eventEndTime = $("#event_end_time").val();
            let errorFields = {};

            function parseDate(dt) {
                if (!dt) return null;
                let d = new Date(dt);
                if (d.toString().toLowerCase().indexOf('invalid') !== -1) {
                    if (/^\d{4}-\d{2}-\d{2}$/.test(dt)) return new Date(dt+"T00:00");
                    return null;
                }
                return d;
            }

            let cAt = parseDate(createdAt);
            let eStart = parseDate(eventStartTime);
            let eEnd = parseDate(eventEndTime);

            if (eStart && eEnd && eStart >= eEnd) {
                errorFields['event_start_time'] = "Event Start Time must be before Event End Time.";
                errorFields['event_end_time'] = "Event Start Time must be before Event End Time.";
            }
            if (cAt) {
                if (eStart && eStart < cAt) errorFields['event_start_time'] = "Event Start Time cannot be before Created At.";
                if (eEnd && eEnd < cAt) errorFields['event_end_time'] = "Event End Time cannot be before Created At.";
            }
            var selectedCat = $("#event_category").val();
            var maxSeats = categoryMaxSeats[selectedCat];
            var eventSeats = parseInt($("#event_seats").val(), 10);
            if (maxSeats && !isNaN(maxSeats) && eventSeats > maxSeats) {
                errorFields['event_seats'] = "Event Seats cannot exceed the maximum allowed for this category ("+maxSeats+").";
            }
            var availableSeats = parseInt($("#event_available_seats").val(), 10);
            if ($("#event_seats").length && $("#event_available_seats").length && availableSeats > eventSeats) {
                errorFields['event_available_seats'] = "Available seats must not be greater than Event Seats.";
                errorFields['event_seats'] = "Available seats must not be greater than Event Seats.";
            }

            $(".error-msg.form-js").remove();

            let focused = false;
            for (let field_id in errorFields) {
                let inp = $("#" + field_id);
                if (inp.length) {
                    $("<div class='error-msg form-js'>"+errorFields[field_id]+"</div>").insertAfter(inp);
                    if (!focused) { inp.focus(); focused = true; }
                }
            }

            if (Object.keys(errorFields).length > 0) {
                e.preventDefault();
                return false;
            }
        });
    });
    </script>
</head>
<body>
    <form class="edit-event-form" method="post" enctype="multipart/form-data">
        <h2>Edit Event </h2>
        <?php if ($update_success): ?>
            <div class="success-msg">Event updated successfully!</div>
        <?php endif; ?>
        <?php
        if (!empty($field_errors['_global'])) {
            echo '<div class="error-msg">'.esc($field_errors['_global']).'</div>';
        }
        ?>

        <?php
        echo '<div class="form-row">';
        echo '<label for="event_id">Event ID</label>';
        echo '<input type="text" name="event_id" id="event_id" class="readonly-event-id" value="'.esc($event['event_id']).'" readonly>';
        echo '</div>';

        $field_rows = [];
        foreach ($columns as $col) {
            if ($col == 'event_id') continue;
            $label = ucwords(str_replace('_',' ', $col));
            $v = isset($event[$col]) ? $event[$col] : '';
            ob_start();

            if ($col == 'owner_id') {
                echo '<div class="form-row">';
                echo "<label for=\"".esc($col)."\">".esc($label)."</label>";
                echo '<select name="owner_id" id="owner_id">';
                foreach ($all_users as $user) {
                    $selected = ($v == $user['user_id']) ? 'selected' : '';
                    echo '<option value="'.esc($user['user_id']).'" '.$selected.'>'.esc($user['user_id'])." (".esc($user['user_name']).")</option>";
                }
                echo '</select>';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
            }
            elseif ($col == 'event_category') {
                echo '<div class="form-row">';
                echo "<label for=\"".esc($col)."\">".esc($label)."</label>";
                echo '<select name="event_category" id="event_category">';
                foreach ($all_categories as $cat) {
                    $selected = ($v == $cat['category_id']) ? 'selected' : '';
                    $max_seats_txt = (isset($cat['category_seats']) && $cat['category_seats'] !== null) ? ' (Max Seats: '.esc($cat['category_seats']).')' : '';
                    echo '<option value="'.esc($cat['category_id']).'" '.$selected.'>'.esc($cat['category_name']).esc($max_seats_txt).'</option>';
                }
                echo '</select>';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
            }
            elseif ($col == 'event_approval_status') {
                echo '<div class="form-row">';
                echo "<label for=\"event_approval_status\">Event Approval Status</label>";
                $approval_opts = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
                echo '<select name="event_approval_status" id="event_approval_status">';
                foreach ($approval_opts as $k => $vv) {
                    $selected = ($v == $k) ? 'selected' : '';
                    echo '<option value="'.$k.'" '.$selected.'>'.$vv.'</option>';
                }
                echo '</select>';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
            }
            elseif ($col == 'event_status') {
                echo '<div class="form-row">';
                echo "<label for=\"event_status\">Event Status</label>";
                $status_opts = [
                    'draft' => 'Draft', 'published'=>'Published', 'ongoing'=>'Ongoing',
                    'cancelled'=>'Cancelled','completed'=>'Completed'
                ];
                echo '<select name="event_status" id="event_status">';
                foreach ($status_opts as $k => $vv) {
                    $selected = ($v == $k) ? 'selected' : '';
                    echo '<option value="'.$k.'" '.$selected.'>'.$vv.'</option>';
                }
                echo '</select>';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
            }
            elseif ($col == 'event_paymeny_status') {
                echo '<div class="form-row">';
                echo "<label for=\"event_paymeny_status\">Event Paymeny Status</label>";
                $pay_opts = ['pending'=>'Pending','completed'=>'Completed','failed'=>'Failed'];
                echo '<select name="event_paymeny_status" id="event_paymeny_status">';
                foreach ($pay_opts as $k => $vv) {
                    $selected = ($v == $k) ? 'selected' : '';
                    echo '<option value="'.$k.'" '.$selected.'>'.$vv.'</option>';
                }
                echo '</select>';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
            }
            elseif ($col == 'event_title') {
                echo '<div class="form-row">';
                echo "<label for=\"event_title\">Event Title</label>";
                echo '<input type="text" name="event_title" id="event_title" required value="'.esc($v).'">';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
            }
            elseif ($col == 'event_description') {
                echo '<div class="form-row">';
                echo "<label for=\"event_description\">Event Description</label>";
                echo '<textarea name="event_description" id="event_description" rows="4" required>'.esc($v).'</textarea>';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
            }
            elseif ($col == 'event_start_time' || $col == 'event_end_time') {
                echo '<div class="form-row">';
                echo "<label for=\"".esc($col)."\">".esc($label)."</label>";
                $input_val = datetime_local($v);
                echo '<input type="datetime-local" name="'.esc($col).'" id="'.esc($col).'" required value="'.esc($input_val).'">';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
            }
            elseif ($col == 'event_registration_deadline') {
                echo '<div class="form-row">';
                echo "<label for=\"event_registration_deadline\">Event Registration Deadline</label>";
                $input_val = datetime_local($v);
                echo '<input type="datetime-local" name="event_registration_deadline" id="event_registration_deadline" required value="'.esc($input_val).'">';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
            }
            elseif ($col == 'event_seats') {
                $maxSeatsAttr = '';
                $maxSeats = null;
                if ($event_seats_max_for_category !== null) {
                    $maxSeats = (int)$event_seats_max_for_category;
                    $maxSeatsAttr = ' max="' . esc($maxSeats) . '"';
                }
                echo '<div class="form-row">';
                echo "<label for=\"event_seats\">Event Seats</label>";
                echo '<input type="number" name="event_seats" id="event_seats" min="0"' . $maxSeatsAttr . ' required value="'.esc($v).'">';
                if ($maxSeats !== null) {
                    echo '<span class="max-seats-info">Max allowed: ' . esc($maxSeats) . '</span>';
                }
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
            }
            elseif ($col == 'event_available_seats') {
                $max = esc(isset($_POST['event_seats']) ? $_POST['event_seats'] : $event['event_seats']);
                echo '<div class="form-row">';
                echo "<label for=\"event_available_seats\">Event Available Seats</label>";
                echo '<input type="number" name="event_available_seats" id="event_available_seats" min="0" required value="'.esc($v).'"';
                if ($max !== '') echo ' max="'.esc($max).'"';
                echo '>';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
            }
            elseif ($col == 'event_banner_image') {
                echo '<div class="form-row">';
                echo "<label for=\"event_banner_image\">Event Banner Image</label>";
                echo '<input type="file" name="event_banner_image" id="event_banner_image" accept="image/*">';
                echo '<div>';
                if (!empty($event['event_banner_image'])) {
                    echo '<span class="file-current-info">Current: '.esc($event['event_banner_image']).'</span>';
                }
                echo '<span class="file-desc-message">';
                echo 'If you don\'t change this, the old banner will remain.<br>';
                echo '</span>';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
                echo '</div>';
            }
            elseif ($col == 'event_gallery_images') {
                echo '<div class="form-row">';
                echo "<label for=\"event_gallery_images\">Event Gallery Images</label>";
                echo '<input type="file" name="event_gallery_images[]" id="event_gallery_images" accept="image/*" multiple>';
                echo '<div>';
                if (!empty($event['event_gallery_images'])) {
                    $gallery_arr = explode(';', $event['event_gallery_images']);
                    echo '<span class="file-current-info">Current: '.implode(', ', array_map('esc', array_filter($gallery_arr))).'</span>';
                }
                echo '<span class="file-desc-message">';
                echo "If you don't change this, your old gallery images will remain.<br>";
                echo '</span>';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
                echo '</div>';
            }
            elseif ($col == 'created_at') {
                // will place below
            }
            elseif ($col == 'updated_at') {
                echo '<div class="form-row">';
                echo '<label for="updated_at">Updated At</label>';
                echo '<input type="datetime-local" name="updated_at" id="updated_at" value="'.esc($v).'" required>';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '<span class="updated-at-desc">Leave unchanged to use current time, or enter custom date/time.</span>';
                echo '</div>';
            }
            else {
                echo '<div class="form-row">';
                echo "<label for=\"".esc($col)."\">".esc($label)."</label>";
                echo '<input type="text" name="'.esc($col).'" id="'.esc($col).'" value="'.esc($v).'">';
                if (!empty($field_errors[$col])) echo '<div class="error-msg">'.esc($field_errors[$col]).'</div>';
                echo '</div>';
            }
            $field_rows[$col] = ob_get_clean();
        }

        foreach ($columns as $idx => $col) {
            if ($col == 'event_id') continue;
            if ($col == 'updated_at') {
                if (in_array('created_at', $columns)) {
                    echo '<div class="form-row">';
                    echo '<label for="created_at">Created At</label>';
                    $raw_val = isset($event['created_at']) ? $event['created_at'] : '';
                    echo '<input type="datetime-local" name="created_at" id="created_at" value="'.esc($raw_val).'" required>';
                    if (!empty($field_errors['created_at'])) echo '<div class="error-msg">'.esc($field_errors['created_at']).'</div>';
                    echo '</div>';
                }
                echo isset($field_rows['updated_at']) ? $field_rows['updated_at'] : '';
            } elseif ($col == 'created_at') {
                // skip, already handled above
            }
            elseif ($col == 'event_date') {
                // skip, do not show on form
            }
            else {
                echo isset($field_rows[$col]) ? $field_rows[$col] : '';
            }
        }
        if (in_array('created_at', $columns) && !in_array('updated_at', $columns)) {
            echo '<div class="form-row">';
            echo '<label for="created_at">Created At</label>';
            $raw_val = isset($event['created_at']) ? $event['created_at'] : '';
            echo '<input type="datetime-local" name="created_at" id="created_at" value="'.esc($raw_val).'" required>';
            if (!empty($field_errors['created_at'])) echo '<div class="error-msg">'.esc($field_errors['created_at']).'</div>';
            echo '</div>';
        }
        ?>
        <div class="form-submit-row">
            <button class="btn-primary" type="submit" name="edit_event">Update Event</button>
        </div>
        <div class="back-link-wrap">
            <a href="events.php" class="back-link">&larr; Back to Events List</a>
        </div>
        <div class="js-hint-info" style="margin-top:30px;color: #666; font-size:14px;">

        </div>
    </form>
</body>
</html>

