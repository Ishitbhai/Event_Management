<?php
include 'header.php';
include 'database/db_connect.php';

/* TIMEZONE SAFETY */
date_default_timezone_set('Asia/Kolkata');
mysqli_query($conn, "SET time_zone = '+05:30'");

/* SESSION CHECK */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/* FETCH CATEGORIES (also get price_per_hour for each category) */
$category_query = "SELECT * FROM category";
$category_res = mysqli_query($conn, $category_query);
$categories = [];
$category_price_per_hour_map = [];
while ($row = mysqli_fetch_assoc($category_res)) {
    $categories[] = $row;
    $category_price_per_hour_map[$row['category_id']] = $row['category_price_per_hour'];
}

$errors = [];
$success = false;

/* INIT VARIABLES */
$title = $description = $category_id = $start_datetime = $end_datetime = $reg_deadline = '';
$event_seats = $persons = 0;
$event_price = 0.0;

/* HELPER FUNCTION TO FIX HTML5 DATETIME LOCAL */
function fix_datetime_local($dt_local) {
    if (!$dt_local) return false;
    $dt = str_replace('T', ' ', $dt_local);
    $ts = strtotime($dt);
    if (!$ts) return false;
    return date('Y-m-d H:i:s', $ts);
}

/* FORM SUBMISSION */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* BASIC INPUTS */
    $owner_id     = $_SESSION['user_id'];
    $title        = trim($_POST['title']);
    $description  = trim($_POST['description']);
    $category_id  = (int)$_POST['category_id'];
    $start_datetime = $_POST['start_datetime'];
    $end_datetime   = $_POST['end_datetime'];
    $reg_deadline   = $_POST['reg_deadline'];
    $event_seats  = (int)$_POST['event_seats'];
    $persons      = (int)$_POST['persons']; 

    /* REQUIRED FIELDS CHECK */
    if (empty($title) || empty($description) || empty($category_id) ||
        empty($start_datetime) || empty($end_datetime) ||
        empty($event_seats) || empty($persons) || empty($reg_deadline)) {
        $errors[] = "Please complete all required fields.";
    }

    /* DATETIME CONVERSION */
    $event_start_datetime = fix_datetime_local($start_datetime);
    $event_end_datetime   = fix_datetime_local($end_datetime);
    $event_date           = $event_start_datetime ? date('Y-m-d', strtotime($event_start_datetime)) : '';
    $event_reg_deadline   = $reg_deadline ? date('Y-m-d H:i:s', strtotime($reg_deadline.' 23:59:59')) : '';

    if (!$event_start_datetime || !$event_end_datetime || !$event_reg_deadline) {
        $errors[] = "Invalid date/time format entered.";
    }

    /* DATETIME VALIDATION */
    if ($event_start_datetime && $event_end_datetime && strtotime($event_start_datetime) >= strtotime($event_end_datetime)) {
        $errors[] = "End datetime must be after start datetime.";
    }

    if ($event_date && strtotime($event_date) < strtotime('+7 days')) {
        $errors[] = "Event start date must be at least one week from today.";
    }

    if ($event_reg_deadline && strtotime($event_reg_deadline) > strtotime($event_start_datetime)) {
        $errors[] = "Registration deadline must be before event start time.";
    }

    if ($event_reg_deadline && strtotime($event_reg_deadline) < strtotime('today')) {
        $errors[] = "Registration deadline cannot be in the past.";
    }

    /* CATEGORY SEAT AND PRICE CHECK */
    $cat_stmt = mysqli_prepare($conn, "SELECT category_seats, category_price_per_hour FROM category WHERE category_id = ?");
    mysqli_stmt_bind_param($cat_stmt, "i", $category_id);
    mysqli_stmt_execute($cat_stmt);
    mysqli_stmt_bind_result($cat_stmt, $category_max_seats, $price_per_hour);
    mysqli_stmt_fetch($cat_stmt);
    mysqli_stmt_close($cat_stmt);

    if ($event_seats > $category_max_seats) {
        $errors[] = "Event seats exceed category limit.";
    }

    if ($persons > $event_seats) {
        $errors[] = "Family persons cannot exceed total seats.";
    }

    $available_seats = $event_seats - $persons;

    // Calculate price (price_per_hour * hours)
    $event_price = 0.0;
    if (!empty($price_per_hour) && $event_start_datetime && $event_end_datetime) {
        $start = strtotime($event_start_datetime);
        $end = strtotime($event_end_datetime);
        $hours = max(1, ceil(($end - $start) / 3600)); // Minimum 1 hour, round up
        $event_price = $price_per_hour * $hours;
    }

    /* EVENT TIME CONFLICT CHECK */
    if (empty($errors)) {
        $conflict_stmt = mysqli_prepare(
            $conn,
            "SELECT event_id
             FROM events
             WHERE event_date = ?
               AND event_approval_status != 'rejected'
               AND event_status != 'cancelled'
               AND (? < event_end_time)
               AND (? > event_start_time)
             LIMIT 1"
        );
        mysqli_stmt_bind_param(
            $conflict_stmt,
            "sss",
            $event_date,
            $event_start_datetime,
            $event_end_datetime
        );
        mysqli_stmt_execute($conflict_stmt);
        mysqli_stmt_store_result($conflict_stmt);

        if (mysqli_stmt_num_rows($conflict_stmt) > 0) {
            $errors[] = "This time slot is already booked. Choose another.";
        }
        mysqli_stmt_close($conflict_stmt);
    }

    /* IMAGE UPLOADS */
    $banner_path = '';
    $gallery_paths = [];

    // Banner image upload check for path in filename
    if (!empty($_FILES['banner_image']['name'])) {
        // Check if path is included in the uploaded banner image filename
        $file_name = $_FILES['banner_image']['name'];
        if (strpos($file_name, '/') !== false || strpos($file_name, '\\') !== false) {
            $errors[] = "Invalid banner image: the file name must not contain a path.";
        }
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $errors[] = "Invalid banner image format.";
        } else {
            // Store in 'images/' but only save filename in DB
            $banner_filename = 'banner_' . uniqid() . '.' . $ext;
            $banner_storage_path = 'images/' . $banner_filename;
            if (!is_dir('images')) {
                mkdir('images', 0755, true);
            }
            if (!move_uploaded_file($_FILES['banner_image']['tmp_name'], $banner_storage_path)) {
                $errors[] = "Failed to upload banner image.";
            } else {
                $banner_path = $banner_filename; // Only filename saved to DB
            }
        }
    } else {
        $errors[] = "Banner image is required.";
    }

    // Gallery images upload check for path in filenames
    if (!empty($_FILES['gallery_images']['name'][0])) {
        foreach ($_FILES['gallery_images']['tmp_name'] as $i => $tmp) {
            $gallery_file_name = $_FILES['gallery_images']['name'][$i];
            // Check if path is included in the uploaded gallery image filename
            if (strpos($gallery_file_name, '/') !== false || strpos($gallery_file_name, '\\') !== false) {
                $errors[] = "Invalid gallery image: file name must not contain a path.";
                continue;
            }
            $ext = strtolower(pathinfo($gallery_file_name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $gallery_filename = 'gallery_' . uniqid() . '.' . $ext;
                $gallery_storage_path = 'images/' . $gallery_filename;
                if (!is_dir('images')) {
                    mkdir('images', 0755, true);
                }
                if (!move_uploaded_file($tmp, $gallery_storage_path)) {
                    $errors[] = "Failed to upload one of the gallery images.";
                    continue;
                }
                $gallery_paths[] = $gallery_filename; // Only filename saved to DB
            }
        }
    }

    $gallery_csv = implode(',', $gallery_paths);

    /* INSERT EVENT AND OWNER'S BOOKINGS */
    if (empty($errors)) {
        $conn->autocommit(false); // Start transaction

        // 1. Insert into events (now adding event_price)
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO events (
                owner_id,
                event_title,
                event_description,
                event_category,
                event_date,
                event_start_time,
                event_end_time,
                event_seats,
                event_available_seats,
                event_banner_image,
                event_gallery_images,
                event_registration_deadline,
                event_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "issssssiisssd",
            $owner_id,
            $title,
            $description,
            $category_id,
            $event_date,
            $event_start_datetime,
            $event_end_datetime,
            $event_seats,
            $available_seats,
            $banner_path,
            $gallery_csv,
            $event_reg_deadline,
            $event_price
        );

        $event_ok = false;
        $booking_ok = false;

        if (mysqli_stmt_execute($stmt)) {
            $event_ok = true;
            $event_id = mysqli_insert_id($conn);

            // 2. Insert booking record (for the owner and family persons) with booking_status='approved'
            $booking_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO bookings (event_id, user_id, persons, booking_status) VALUES (?, ?, ?, ?)"
            );
            $booking_status = 'approved';
            mysqli_stmt_bind_param($booking_stmt, "iiis", $event_id, $owner_id, $persons, $booking_status);

            if (mysqli_stmt_execute($booking_stmt)) {
                $booking_ok = true;
            } else {
                $errors[] = "Booking insertion failed: " . mysqli_error($conn);
            }
            mysqli_stmt_close($booking_stmt);

            // If event and booking insertion succeeded, redirect to payment
            if ($event_ok && $booking_ok) {
                $conn->commit();
                // Redirect to payment.php with event_id, event_price can be retrieved there if needed
                header("Location: payment.php?event_id=" . urlencode($event_id));
                exit();
            } else {
                $conn->rollback();
            }
            $conn->autocommit(true);

        } else {
            $errors[] = "Database error: " . mysqli_error($conn);
            mysqli_stmt_close($stmt);
        }
    }
}
?>


<!-- <link rel="stylesheet" href="css/create_event.css"> -->

<style>
    .create-container-centered {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 18px 0 rgba(19, 70, 157, 0.15);
    margin: 30px auto 32px auto;
    max-width: 650px;
    padding: 30px 32px 26px 32px;
}
/* Table layout and cells */ 
.form-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 14px;
}
.form-table td {
    padding-bottom: 0;
}
/* Labels and fields */
.form-label {
    display: block;
    font-weight: 500;
    color: #184675;
    margin-bottom: 7px;
    letter-spacing: .01em;
}
.input-text, .input-number, .input-area, .input-select, .input-file {
    width: 100%;
    font-size: 1.09em;
    border-radius: 4px;
    padding: 8px 10px;
    border: 1px solid #b6cbdb;
    transition: border-color 0.15s;
    background: #f8fcff;
    color: #142c42;
    margin-bottom: 4px;
}
.input-text:focus, .input-number:focus, .input-area:focus, .input-select:focus {
    border-color: #2494f5;
    outline: none;
    background: #f2f9ff;
}
.input-invalid,
.input-invalid:focus {
    border-color: #ce3030 !important;
    background: #fff3f3 !important;
}
.input-file {
    padding: 6px 10px 4px 0;
    background: transparent;
    border: none;
}
.input-file:focus {
    outline: 1px dotted #549dc7;
}
/* Buttons */
.create-event-btn {
    background: linear-gradient(90deg, #2494f5 0%, #1e73be 100%);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 12px 36px;
    font-size: 1.12em;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.18s, box-shadow 0.18s, transform 0.12s;
    box-shadow: 0 3px 10px 0 rgba(36,148,245,0.11), 0 1.5px 5px 0 rgba(25, 85, 144, 0.05);
    text-decoration: none !important;
    outline: none;
    letter-spacing: 0.03em;
    display: inline-block;
}
.create-event-btn:hover,
.create-event-btn:focus {
    background: linear-gradient(90deg, #176ab0 0%, #105688 100%);
    color: #fff;
    transform: translateY(-2px) scale(1.025);
    box-shadow: 0 6px 20px 0 rgba(36,148,245,0.14), 0 2.5px 8px 0 rgba(25, 85, 144, 0.13);
    text-decoration: none !important;
}
.back-btn {
    background: transparent;
    color: #2173cb;
    border: 1px solid #b6cbdb;
    margin-bottom: 12px;
    font-weight: 500;
    font-size: 1em;
    padding: 8px 20px;
    transition: color 0.13s, border 0.13s;
    text-decoration: none !important;
}
.back-btn:hover {
    color: #101b2d;
    border-color: #2978c9;
    background: #ecf6fe;
    text-decoration: none !important;
}
.outside-back-btn-wrapper {
    padding: 25px 0 16px 2px;
    max-width: 650px;
    margin: 0 auto;
}
.msg-error {
    background: #ffecf0;
    border: 1px solid #fc8d99;
    color: #a21d37;
    border-radius: 5px;
    padding: 14px 20px 9px 18px;
    font-weight: 500;
    margin-bottom: 26px;
    font-size: 1.02em;
}
.msg-success {
    background: #e9fcee;
    border: 1px solid #6bdea7;
    color: #15895f;
    border-radius: 6px;
    padding: 23px 28px 16px 20px;
    font-size: 1.08em;
    margin: 19px 0 9px 0;
    text-align: center;
}
.field-error {
    color: #ce3030;
    font-size: .99em;
    display: block;
    margin-top: 0px;
    margin-bottom: 5px;
    min-height: 16px;
}
.event-code-span {
    display: inline-block;
    font-family: "Fira Mono", "SFMono-Regular", monospace;
    font-size: 1.37em;
    color: #145280;
    padding: 3px 13px 3px 13px;
    background: #e2f0ff;
    border: 1px dashed #2494f5;
    border-radius: 6px;
    letter-spacing: 0.09em;
    margin-top: 5px;
    margin-bottom: 2px;
    font-weight: bold;
}
.code-note {
    color: #7f7f7f;
    font-size: .98em;
    margin-top: 7px;
    margin-bottom: 6px;
}
@media (max-width: 755px) {
    .create-container-centered {
        padding: 10px 5vw 12px 5vw;
        max-width: 98vw;
    }
    .outside-back-btn-wrapper { max-width: 97vw; padding:16px 0 10px 0;}
    .create-event-btn {
        padding: 11px 10vw;
        font-size: 1.03em;
    }
}
@media (max-width: 530px) {
    .create-container-centered {
        padding: 0 2vw 4vw 2vw;
        box-shadow: none;
        font-size: 99%;
    }
    .form-label, .code-note { font-size: 97%; }
    .create-event-btn {
        padding: 11px 0;
        width: 100%;
        font-size: 99%;
    }
}
</style>

<div style="position:relative; max-width:860px; margin:0 auto;">
    <div class="outside-back-btn-wrapper">
        <a href="events.php" class="create-event-btn back-btn">&#8592; Back to Events</a>
    </div>
    <div class="create-container-centered">
        <?php if (!empty($errors)): ?>
            <div class="msg-error">
                <?php foreach ($errors as $err) echo htmlspecialchars($err).'<br>'; ?>
            </div>
        <?php endif; ?>
        <?php // Remove the after-success UI because we redirect on real success ?>
        <?php if (!$success): ?>
        <form method="post" enctype="multipart/form-data" id="create-event-form" autocomplete="off" novalidate>
            <input type="hidden" name="owner_id" value="<?php echo $_SESSION['user_id']; ?>">
            <table class="form-table">
                <tr>
                    <td>
                        <label class="form-label" for="title">Event Title</label>
                        <input type="text" name="title" id="title" value="<?php echo isset($title)?htmlspecialchars($title):'' ?>" required class="input-text" placeholder="Event title">
                        <label id="title-error" class="field-error" style="display:none;"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="description">Description</label>
                        <textarea name="description" id="description" rows="3" required class="input-area" placeholder="Event description"><?php echo isset($description)?htmlspecialchars($description):'' ?></textarea>
                        <label id="description-error" class="field-error" style="display:none;"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="category_id">Category</label>
                        <select name="category_id" id="category_id" required class="input-select" onchange="updateMaxSeatsAndPrice()">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>"
                                    data-max-seats="<?php echo $cat['category_seats']; ?>"
                                    data-price-per-hour="<?php echo isset($cat['category_price_per_hour']) ? htmlspecialchars($cat['category_price_per_hour']) : 0; ?>"
                                    <?php if (isset($category_id) && $category_id == $cat['category_id']) echo ' selected'; ?>>
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label id="category_id-error" class="field-error" style="display:none;"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="start_datetime">Start Datetime <span style="font-size:.96em;color:#377fd9;">(at least one week from today)</span></label>
                        <input type="datetime-local" name="start_datetime" id="start_datetime" value="<?php echo isset($start_datetime)?htmlspecialchars($start_datetime):'' ?>" required class="input-text">
                        <label id="start_datetime-error" class="field-error" style="display:none;"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="end_datetime">End Datetime</label>
                        <input type="datetime-local" name="end_datetime" id="end_datetime" value="<?php echo isset($end_datetime)?htmlspecialchars($end_datetime):'' ?>" required class="input-text">
                        <label id="end_datetime-error" class="field-error" style="display:none;"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label">Max seats for chosen category:</label>
                        <span id="max-seats-caption" style="font-weight:600;margin-left:5px;color:#2363b9;">
                            <?php
                                if (isset($category_id)) {
                                    foreach ($categories as $cat) {
                                        if ($cat['category_id'] == $category_id) {
                                            echo intval($cat['category_seats']);
                                            break;
                                        }
                                    }
                                } else {
                                    echo 'Choose category';
                                }
                            ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="event_seats">Total seats to open for this event: <span style="font-size:.96em;color:#377fd9;">(can be less than max)</span></label>
                        <input type="number" name="event_seats" min="1" max="<?php
                            if (isset($category_id)) {
                                foreach ($categories as $cat) {
                                    if ($cat['category_id'] == $category_id) {
                                        echo intval($cat['category_seats']);
                                        break;
                                    }
                                }
                            } else {
                                echo '9999';
                            }
                        ?>"
                            id="event_seats_input"
                            value="<?php echo isset($event_seats)?htmlspecialchars($event_seats):'' ?>"
                            required class="input-number" style="width:50%;display:inline-block;">
                        <label id="event_seats-error" class="field-error" style="display:none;"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="persons">How many family persons/seats you want to take?</label>
                        <input type="number" name="persons" min="1" max="<?php echo isset($event_seats) ? htmlspecialchars($event_seats) : '9999'; ?>"
                            id="persons-input" value="<?php echo isset($persons)?htmlspecialchars($persons):'' ?>"
                            required class="input-number" style="width:50%;display:inline-block;">
                        <label id="persons-error" class="field-error" style="display:none;"></label>
                        <span style="font-size:.98em;color:#196bb5;">(Available: <span id="available-seats-span">
                            <?php
                                if (isset($event_seats) && isset($persons)) {
                                    $avail = intval($event_seats) - intval($persons);
                                    echo ($avail > 0 ? $avail : 0);
                                } else {
                                    echo '-';
                                }
                            ?>
                        </span> seats after booking)</span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="banner_image">Banner Image</label>
                        <input type="file" name="banner_image" id="banner_image" accept=".jpg,.jpeg,.png,.gif,.webp" required class="input-file">
                        <label id="banner_image-error" class="field-error" style="display:none;"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="gallery_images">Gallery Images <span style="font-size:.97em;color:#6ca3f7;">(JPG/PNG/GIF/WEBP, multiple allowed)</span></label>
                        <input type="file" name="gallery_images[]" id="gallery_images" accept=".jpg,.jpeg,.png,.gif,.webp" multiple class="input-file">
                        <label id="gallery_images-error" class="field-error" style="display:none;"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="reg_deadline">Registration Deadline</label>
                        <input type="date" name="reg_deadline" id="reg_deadline" value="<?php echo isset($reg_deadline)?htmlspecialchars($reg_deadline):'' ?>" required class="input-text">
                        <label id="reg_deadline-error" class="field-error" style="display:none;"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="event_price">Event Price (calculated):</label>
                        <input type="text" name="event_price" id="event_price" readonly value="<?php echo isset($event_price) ? htmlspecialchars(number_format($event_price, 2)) : '0.00'; ?>" class="input-text" style="background:#f6f6f6;">
                    </td>
                </tr>
                <tr>
                    <td>
                        <button type="submit" class="create-event-btn">Create Event</button>
                    </td>
                </tr>
            </table>
        </form>
        <?php endif; ?>
    </div>
</div>

<script src="js/jquery-4.0.0.min.js"></script>
<script>
    function updateMaxSeatsAndPrice() {
        var sel = document.getElementById('category_id');
        var i = sel.selectedIndex;
        var option = sel.options[i];
        var maxSeats = option.getAttribute('data-max-seats');
        var pricePerHour = parseFloat(option.getAttribute('data-price-per-hour')) || 0;
        var maxSpan = document.getElementById('max-seats-caption');
        var eventSeatsInput = document.getElementById('event_seats_input');
        var personsInput = document.getElementById('persons-input');
        if (maxSeats) {
            maxSpan.textContent = maxSeats;
            eventSeatsInput.setAttribute('max', maxSeats);
            personsInput.setAttribute('max', eventSeatsInput.value ? eventSeatsInput.value : maxSeats);
        } else {
            maxSpan.textContent = 'Choose category';
            eventSeatsInput.setAttribute('max', 9999);
            personsInput.removeAttribute('max');
        }
        if (!eventSeatsInput.value) {
            personsInput.value = '';
            document.getElementById('available-seats-span').textContent = '-';
        } else {
            var available = parseInt(eventSeatsInput.value) - (parseInt(personsInput.value) || 0);
            document.getElementById('available-seats-span').textContent = (available >= 0 ? available : 0);
            personsInput.setAttribute('max', eventSeatsInput.value);
        }
        // Also recalculate price if possible
        updateEventPrice();
    }

    function updateEventPrice() {
        var categorySel = document.getElementById('category_id');
        var catOption = categorySel.options[categorySel.selectedIndex];
        var pricePerHour = parseFloat(catOption.getAttribute('data-price-per-hour')) || 0;
        var startVal = document.getElementById('start_datetime').value;
        var endVal = document.getElementById('end_datetime').value;
        var priceInput = document.getElementById('event_price');
        if (!priceInput) return;
        if (startVal && endVal && pricePerHour) {
            var start = new Date(startVal);
            var end = new Date(endVal);
            var diffMs = end - start;
            var hours = Math.ceil(diffMs / (1000 * 60 * 60));
            if (hours < 1) hours = 1;
            var amount = hours * pricePerHour;
            priceInput.value = amount.toFixed(2);
        } else {
            priceInput.value = "0.00";
        }
    }

    document.getElementById('category_id').addEventListener('change', function(){
        updateMaxSeatsAndPrice();
    });

    document.getElementById('start_datetime').addEventListener('change', updateEventPrice);
    document.getElementById('end_datetime').addEventListener('change', updateEventPrice);

    document.getElementById('event_seats_input').addEventListener('input', function(){
        var max = this.getAttribute('max');
        if (parseInt(this.value) > parseInt(max)) {
            this.value = max;
        }
        var personsInput = document.getElementById('persons-input');
        personsInput.setAttribute('max', this.value);
        // Clear persons if more than event_seats
        if (parseInt(personsInput.value) > parseInt(this.value)) {
            personsInput.value = this.value;
        }
        var available = parseInt(this.value || 0) - (parseInt(personsInput.value) || 0);
        document.getElementById('available-seats-span').textContent = (!isNaN(available) && available >= 0 ? available : 0);
    });

    document.getElementById('persons-input').addEventListener('input', function(){
        var eventSeats = parseInt(document.getElementById('event_seats_input').value) || 0;
        var val = parseInt(this.value) || 0;
        if (val > eventSeats) {
            this.value = eventSeats;
            val = eventSeats;
        }
        var avail = eventSeats - val;
        document.getElementById('available-seats-span').textContent = (avail >= 0 ? avail : 0);
    });

    $(document).ready(function(){
        function isValidImg(filename) {
            return (/\.(jpe?g|png|gif|webp)$/i).test(filename);
        }

        $('#title').on('input blur', function() {
            var val = $(this).val().trim();
            if (val.length < 3) {
                $('#title-error').text("Event title must be at least 3 characters.").show();
                $(this).addClass("input-invalid");
            } else {
                $('#title-error').hide();
                $(this).removeClass("input-invalid");
            }
        });
        $('#description').on('input blur', function() {
            var val = $(this).val().trim();
            if (val.length < 8) {
                $('#description-error').text("Description is required (min 8 chars).").show();
                $(this).addClass("input-invalid");
            } else {
                $('#description-error').hide();
                $(this).removeClass("input-invalid");
            }
        });
        $('#category_id').on('change blur', function() {
            var val = $(this).val();
            if (!val) {
                $('#category_id-error').text("Please select a category.").show();
                $(this).addClass("input-invalid");
            } else {
                $('#category_id-error').hide();
                $(this).removeClass("input-invalid");
            }
        });

        $('#start_datetime').on('change blur', function() {
            var val = $(this).val();
            var valid = false;
            if (val) {
                var entered = new Date(val);
                var today = new Date();
                today.setHours(0,0,0,0);
                var minDate = new Date(today);
                minDate.setDate(minDate.getDate() + 7);
                entered.setHours(0,0,0,0);
                valid = entered >= minDate;
            }
            if (!val) {
                $('#start_datetime-error').text("Start datetime is required.").show();
                $(this).addClass("input-invalid");
            } else if (!valid) {
                $('#start_datetime-error').text("Event start datetime must be at least one week from today.").show();
                $(this).addClass("input-invalid");
            } else {
                $('#start_datetime-error').hide();
                $(this).removeClass("input-invalid");
            }
            $('#end_datetime').trigger('blur');
            updateEventPrice();
        });
        $('#end_datetime').on('change blur', function() {
            var s = $('#start_datetime').val();
            var e = $(this).val();
            if (!e) {
                $('#end_datetime-error').text("End datetime is required.").show();
                $(this).addClass("input-invalid");
            } else if (s && e && s >= e) {
                $('#end_datetime-error').text("End datetime must be after start datetime.").show();
                $(this).addClass("input-invalid");
            } else {
                $('#end_datetime-error').hide();
                $(this).removeClass("input-invalid");
            }
            updateEventPrice();
        });

        $('#event_seats_input').on('input blur', function() {
            var cat_max = parseInt($('#category_id option:selected').attr('data-max-seats') || '9999', 10);
            var val = parseInt($(this).val(),10);
            if (!val || val < 1) {
                $('#event_seats-error').text("Event seats must be at least 1.").show();
                $(this).addClass("input-invalid");
            } else if (val > cat_max) {
                $('#event_seats-error').text("Cannot exceed max seats for this category.").show();
                $(this).addClass("input-invalid");
                $(this).val(cat_max);
            } else {
                $('#event_seats-error').hide();
                $(this).removeClass("input-invalid");
            }
            $('#persons-input').attr('max', val || cat_max);
            var personsVal = parseInt($('#persons-input').val(), 10) || 0;
            if (personsVal > val) {
                $('#persons-input').val(val);
                personsVal = val;
            }
            var available = val - personsVal;
            $('#available-seats-span').text((available >= 0 ? available : 0));
        });

        $('#persons-input').on('input blur', function() {
            var eventSeats = parseInt($('#event_seats_input').val(), 10) || 0;
            var val = parseInt($(this).val(), 10) || 0;
            if (!val || val < 1) {
                $('#persons-error').text("You must book at least 1 seat.").show();
                $(this).addClass("input-invalid");
            } else if (val > eventSeats) {
                $('#persons-error').text("Cannot exceed the event seats you set.").show();
                $(this).addClass("input-invalid");
                $(this).val(eventSeats);
                val = eventSeats;
            } else {
                $('#persons-error').hide();
                $(this).removeClass("input-invalid");
            }
            var available = eventSeats - val;
            $('#available-seats-span').text((available >= 0 ? available : 0));
        });

        $('#banner_image').on('change blur', function() {
            var files = this.files;
            if (!files || files.length === 0) {
                $('#banner_image-error').text("Please select a banner image.").show();
                $(this).addClass("input-invalid");
            }
            else if (!isValidImg(files[0].name)) {
                $('#banner_image-error').text("Invalid image format. Allowed: jpg, jpeg, png, gif, webp.").show();
                $(this).addClass("input-invalid");
            }
            // Check for path inclusion in file name (should not have / or \)
            else if (files[0].name.indexOf('/') !== -1 || files[0].name.indexOf('\\') !== -1) {
                $('#banner_image-error').text("Invalid image name: must not contain a path.").show();
                $(this).addClass("input-invalid");
            }
            else {
                $('#banner_image-error').hide();
                $(this).removeClass("input-invalid");
            }
        });
        $('#gallery_images').on('change blur', function() {
            var files = this.files;
            var valid = true;
            for (var i = 0; i < files.length; i++) {
                if (!isValidImg(files[i].name)) {
                    valid = false;
                    break;
                }
                // Check for path inclusion in file name
                if (files[i].name.indexOf('/') !== -1 || files[i].name.indexOf('\\') !== -1) {
                    valid = false;
                    break;
                }
            }
            if (!valid) {
                $('#gallery_images-error').text("Invalid image in gallery (bad name or format). Name must not contain a path. Allowed: jpg, jpeg, png, gif, webp.").show();
                $(this).addClass("input-invalid");
            } else {
                $('#gallery_images-error').hide();
                $(this).removeClass("input-invalid");
            }
        });
        $('#reg_deadline').on('change blur', function() {
            var val = $(this).val();
            var startDatetimeVal = $('#start_datetime').val();
            var valid = false;
            if (val && startDatetimeVal) {
                var deadline = new Date(val);
                var eventStart = new Date(startDatetimeVal);
                var today = new Date();
                today.setHours(0,0,0,0);
                eventStart.setHours(0,0,0,0);
                valid = (deadline <= eventStart && deadline >= today);
            }
            if (!val) {
                $('#reg_deadline-error').text("Registration deadline required.").show();
                $(this).addClass("input-invalid");
            } else if (!valid) {
                $('#reg_deadline-error').text("Deadline must be before event start date and not in the past.").show();
                $(this).addClass("input-invalid");
            } else {
                $('#reg_deadline-error').hide();
                $(this).removeClass("input-invalid");
            }
        });

        $('#create-event-form').on('submit', function(e){
            $('#title').trigger('blur');
            $('#description').trigger('blur');
            $('#category_id').trigger('blur');
            $('#start_datetime').trigger('blur');
            $('#end_datetime').trigger('blur');
            $('#event_seats_input').trigger('blur');
            $('#persons-input').trigger('blur');
            $('#banner_image').trigger('blur');
            $('#gallery_images').trigger('blur');
            $('#reg_deadline').trigger('blur');
            if ($('.input-invalid').length > 0) {
                e.preventDefault();
            }
        });

        // Init on load
        updateMaxSeatsAndPrice();
        updateEventPrice();
    });

</script>
<?php include 'footer.php'; ?>
