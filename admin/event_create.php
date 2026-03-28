<?php
session_start();
// Check if the logged-in user is an admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    ?>
    <script>
        window.location.href = 'login.php';
    </script>
    <?php
    exit();
}

require_once('sidebar.php');
require_once('../database/db_connect.php');

// Timezone safety
date_default_timezone_set('Asia/Kolkata');
mysqli_query($conn, "SET time_zone = '+05:30'");

// Fetch event categories
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

$title = $description = $category_id = $start_datetime = $end_datetime = $reg_deadline = '';
$event_seats = $persons = 0;
$event_price = 0.0;

// Fetch all users who are not user_type='user' (i.e. show possible owners, other than end-users)
$user_query = "SELECT user_id, user_email, user_name FROM users WHERE user_type != 'user'";
$user_res = mysqli_query($conn, $user_query);
$owners = [];
while ($row = mysqli_fetch_assoc($user_res)) {
    $owners[] = $row;
}

function fix_datetime_local($dt_local) {
    if (!$dt_local) return false;
    $dt = str_replace('T', ' ', $dt_local);
    $ts = strtotime($dt);
    if (!$ts) return false;
    return date('Y-m-d H:i:s', $ts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use the owner_id sent by the admin from dropdown (not session)
    $owner_id     = isset($_POST['owner_id']) ? (int)$_POST['owner_id'] : 0;
    $title        = trim($_POST['title']);
    $description  = trim($_POST['description']);
    $category_id  = (int)$_POST['category_id'];
    $start_datetime = $_POST['start_datetime'];
    $end_datetime   = $_POST['end_datetime'];
    $reg_deadline   = $_POST['reg_deadline'];
    $event_seats  = (int)$_POST['event_seats'];
    $persons      = (int)$_POST['persons'];
    $event_price  = isset($_POST['event_price']) ? floatval($_POST['event_price']) : 0.0;

    // Required fields check
    if (empty($owner_id) || empty($title) || empty($description) || empty($category_id) ||
        empty($start_datetime) || empty($end_datetime) ||
        empty($event_seats) || empty($persons) || empty($reg_deadline)) {
        $errors[] = "Please complete all required fields.";
    }

    // Confirm owner is not user_type='user'
    $owner_check_stmt = mysqli_prepare($conn, "SELECT user_type FROM users WHERE user_id = ?");
    mysqli_stmt_bind_param($owner_check_stmt, "i", $owner_id);
    mysqli_stmt_execute($owner_check_stmt);
    mysqli_stmt_bind_result($owner_check_stmt, $found_type);
    mysqli_stmt_fetch($owner_check_stmt);
    mysqli_stmt_close($owner_check_stmt);
    if (!isset($found_type) || $found_type === "user") {
        $errors[] = "Selected event owner must not be a 'user' account.";
    }

    $event_start_datetime = fix_datetime_local($start_datetime);
    $event_end_datetime   = fix_datetime_local($end_datetime);
    $event_date           = $event_start_datetime ? date('Y-m-d', strtotime($event_start_datetime)) : '';
    $event_reg_deadline   = $reg_deadline ? date('Y-m-d H:i:s', strtotime($reg_deadline.' 23:59:59')) : '';

    if (!$event_start_datetime || !$event_end_datetime || !$event_reg_deadline) {
        $errors[] = "Invalid date/time format entered.";
    }

    // Datetime validation
    if ($event_start_datetime && $event_end_datetime && strtotime($event_start_datetime) >= strtotime($event_end_datetime)) {
        $errors[] = "End datetime must be after start datetime.";
    }

    // REMOVE: if ($event_date && strtotime($event_date) < strtotime('+7 days')) { ... }
    // (Removed as per instruction: start_date does NOT have to be at least 1 week after now.)

    if ($event_reg_deadline && strtotime($event_reg_deadline) > strtotime($event_start_datetime)) {
        $errors[] = "Registration deadline must be before event start time.";
    }
    // The following check is REMOVED in accordance with instruction:
    // if ($event_reg_deadline && strtotime($event_reg_deadline) < strtotime('today')) {
    //     $errors[] = "Registration deadline cannot be in the past.";
    // }

    // Category seat limit
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

    // Calculate default price if not manually set
    if ($event_price <= 0 && !empty($price_per_hour) && $event_start_datetime && $event_end_datetime) {
        $hours = max(1, ceil((strtotime($event_end_datetime) - strtotime($event_start_datetime)) / 3600));
        $event_price = $price_per_hour * $hours;
    }

    // Event time conflict check
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

    // IMAGE UPLOADS
    $banner_path = '';
    $gallery_paths = [];

    if (!empty($_FILES['banner_image']['name'])) {
        $file_name = $_FILES['banner_image']['name'];
        if (strpos($file_name, '/') !== false || strpos($file_name, '\\') !== false) {
            $errors[] = "Invalid banner image: the file name must not contain a path.";
        }
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $errors[] = "Invalid banner image format.";
        } else {
            $banner_filename = 'banner_' . uniqid() . '.' . $ext;
            $banner_storage_path = '../images/' . $banner_filename;
            if (!is_dir('../images')) {
                mkdir('../images', 0755, true);
            }
            if (!move_uploaded_file($_FILES['banner_image']['tmp_name'], $banner_storage_path)) {
                $errors[] = "Failed to upload banner image.";
            } else {
                $banner_path = $banner_filename;
            }
        }
    } else {
        $errors[] = "Banner image is required.";
    }
    // Gallery image uploads
    if (!empty($_FILES['gallery_images']['name'][0])) {
        foreach ($_FILES['gallery_images']['tmp_name'] as $i => $tmp) {
            $gallery_file_name = $_FILES['gallery_images']['name'][$i];
            if (strpos($gallery_file_name, '/') !== false || strpos($gallery_file_name, '\\') !== false) {
                $errors[] = "Invalid gallery image: file name must not contain a path.";
                continue;
            }
            $ext = strtolower(pathinfo($gallery_file_name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $gallery_filename = 'gallery_' . uniqid() . '.' . $ext;
                $gallery_storage_path = '../images/' . $gallery_filename;
                if (!is_dir('../images')) {
                    mkdir('../images', 0755, true);
                }
                if (!move_uploaded_file($tmp, $gallery_storage_path)) {
                    $errors[] = "Failed to upload one of the gallery images.";
                    continue;
                }
                $gallery_paths[] = $gallery_filename;
            }
        }
    }

    $gallery_csv = implode(',', $gallery_paths);

    // Insert event and booking for owner in bookings table
    if (empty($errors)) {
        $conn->autocommit(false);

        $event_approval_status = 'approved';
        $event_status = 'published';
        $event_payment_status = 'completed';

        // Insert event
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
                event_approval_status,
                event_status,
                event_paymeny_status,
                event_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            "ississsiissssssd",
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
            $event_approval_status,
            $event_status,
            $event_payment_status,
            $event_price
        );

        $event_ok = false;
        $booking_ok = false;
        $event_id = null;

        if (mysqli_stmt_execute($stmt)) {
            $event_ok = true;
            $event_id = mysqli_insert_id($conn);

            // Insert corresponding booking
            $booking_status = 'approved';
            $booking_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO bookings (user_id, event_id, persons, booking_status) VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $booking_stmt,
                "iiis",
                $owner_id,
                $event_id,
                $persons,
                $booking_status
            );
            if (mysqli_stmt_execute($booking_stmt)) {
                $booking_ok = true;
            } else {
                $errors[] = "Booking insertion failed: " . mysqli_error($conn);
            }
            mysqli_stmt_close($booking_stmt);
        } else {
            $errors[] = "Database error: " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);

        if ($event_ok && $booking_ok && empty($errors)) {
            $conn->commit();
            $success = true;
        } else {
            $conn->rollback();
        }
        $conn->autocommit(true);
    }
}
?>


<div class="create-event-wrapper">
    <div class="outside-back-btn-wrapper">
        <a href="events.php" class="create-event-btn back-btn">&#8592; Back to Events</a>
    </div>
    <div class="create-container-centered">
        <?php if (!empty($errors)): ?>
            <div class="msg-error">
                <?php foreach ($errors as $err) echo htmlspecialchars($err).'<br>'; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="msg-success">
                <h3>Event Created Successfully!</h3>
                <p>Event created by admin and is automatically approved, published, and payment marked as completed.</p>
                <a href="events.php" class="create-event-btn">View Events</a>
            </div>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data" id="create-event-form" autocomplete="off" novalidate>
            <table class="form-table">
                <tr>
                    <td>
                        <label class="form-label" for="owner_id">Event Owner</label>
                        <select name="owner_id" id="owner_id" required class="input-select">
                            <option value="">Select Owner</option>
                            <?php foreach ($owners as $owner): ?>
                                <option value="<?php echo $owner['user_id']; ?>"
                                    <?php if (isset($owner_id) && $owner_id == $owner['user_id']) echo ' selected'; ?>>
                                    <?php echo htmlspecialchars($owner['user_email'] . " (" . $owner['user_name'] . ")"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label id="owner_id-error" class="field-error"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="title">Event Title</label>
                        <input type="text" name="title" id="title" value="<?php echo isset($title)?htmlspecialchars($title):'' ?>" required class="input-text" placeholder="Event title">
                        <label id="title-error" class="field-error"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="description">Description</label>
                        <textarea name="description" id="description" rows="3" required class="input-area" placeholder="Event description"><?php echo isset($description)?htmlspecialchars($description):'' ?></textarea>
                        <label id="description-error" class="field-error"></label>
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
                        <label id="category_id-error" class="field-error"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="start_datetime">
                            Start Datetime
                            <!-- removed one week from today validation -->
                        </label>
                        <input type="datetime-local" name="start_datetime" id="start_datetime" value="<?php echo isset($start_datetime)?htmlspecialchars($start_datetime):'' ?>" required class="input-text">
                        <label id="start_datetime-error" class="field-error"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="end_datetime">End Datetime</label>
                        <input type="datetime-local" name="end_datetime" id="end_datetime" value="<?php echo isset($end_datetime)?htmlspecialchars($end_datetime):'' ?>" required class="input-text">
                        <label id="end_datetime-error" class="field-error"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label">Max seats for chosen category:</label>
                        <span id="max-seats-caption">
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
                        <label class="form-label" for="event_seats">
                            Total seats to open for this event:
                            <span class="field-hint">(can be less than max)</span>
                        </label>
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
                            required class="input-number">
                        <label id="event_seats-error" class="field-error"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="persons">How many family persons/seats you want to take?</label>
                        <input type="number" name="persons" min="1" max="<?php echo isset($event_seats) ? htmlspecialchars($event_seats) : '9999'; ?>"
                            id="persons-input" value="<?php echo isset($persons)?htmlspecialchars($persons):'' ?>"
                            required class="input-number">
                        <label id="persons-error" class="field-error"></label>
                        <span>
                            (Available: <span id="available-seats-span">
                            <?php
                                if (isset($event_seats) && isset($persons)) {
                                    $avail = intval($event_seats) - intval($persons);
                                    echo ($avail > 0 ? $avail : 0);
                                } else {
                                    echo '-';
                                }
                            ?>
                        </span> seats after booking)
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="banner_image">Banner Image</label>
                        <input type="file" name="banner_image" id="banner_image" accept=".jpg,.jpeg,.png,.gif,.webp" required class="input-file">
                        <label id="banner_image-error" class="field-error"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="gallery_images">
                            Gallery Images <span class="field-hint field-hint2">(JPG/PNG/GIF/WEBP, multiple allowed)</span>
                        </label>
                        <input type="file" name="gallery_images[]" id="gallery_images" accept=".jpg,.jpeg,.png,.gif,.webp" multiple class="input-file">
                        <label id="gallery_images-error" class="field-error"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="reg_deadline">Registration Deadline</label>
                        <input type="date" name="reg_deadline" id="reg_deadline" value="<?php echo isset($reg_deadline)?htmlspecialchars($reg_deadline):'' ?>" required class="input-text">
                        <label id="reg_deadline-error" class="field-error"></label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="form-label" for="event_price">Event Price (&#8377;) <span class="field-hint">(auto-calculated, you can override)</span></label>
                        <input type="number" step="0.01" min="0" name="event_price" id="event_price" value="<?php echo isset($event_price) ? htmlspecialchars(number_format($event_price, 2, '.', '')) : '0.00'; ?>" class="input-number">
                        <label id="event_price-error" class="field-error"></label>
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
<style>
body {
    margin: 0;
    background: #f4f6fb;
}
.dashboard-main {
    padding: 40px;
}
.dashboard-header {
    margin-bottom: 30px;
}
.dashboard-header h2 {
    margin: 0;
    color: #322053;
}
.dashboard-header p {
    color: #6c757d;
    margin-top: 8px;
}
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 25px;
}
@media (max-width: 980px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 700px) {
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
}
.dashboard-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.06);
    transition: box-shadow 0.3s ease, transform 0.5s cubic-bezier(.68,-0.55,.27,1.55);
    cursor: pointer;
    opacity: 0;
    transform: translateY(40px) scale(0.93);
    animation: fadeInUp 0.8s cubic-bezier(.68,-0.55,.27,1.55) forwards;
}
.dashboard-card:nth-child(1) { animation-delay: 0.10s; }
.dashboard-card:nth-child(2) { animation-delay: 0.20s; }
.dashboard-card:nth-child(3) { animation-delay: 0.30s; }
.dashboard-card:nth-child(4) { animation-delay: 0.40s; }
.dashboard-card:nth-child(5) { animation-delay: 0.50s; }
.dashboard-card:nth-child(6) { animation-delay: 0.60s; }
.dashboard-card:nth-child(7) { animation-delay: 0.70s; }
.dashboard-card:nth-child(8) { animation-delay: 0.80s; }
@keyframes fadeInUp {
    0% {
        opacity: 0;
        transform: translateY(40px) scale(0.93);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
.dashboard-card:hover {
    transform: translateY(-6px) scale(1.04);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
    z-index: 1;
}
.card-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
}
.card-number {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 8px;
    transition: color 0.5s;
}
.card-link {
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    color: black;
}
.events { border-left: 6px solid #5236d6; }
.bookings { border-left: 6px solid #197655; }
.users { border-left: 6px solid #c82f2f; }
.services { border-left: 6px solid #197655; }
.reviews { border-left: 6px solid #c82f2f; }
.categories { border-left: 6px solid #5236d6; }
.coupons { border-left: 6px solid #c82f2f; }
.settings { border-left: 6px solid #5236d6; }

.events .card-title { color: #5236d6; }
.bookings .card-title { color: #197655; }
.users .card-title { color: #c82f2f; }
.services .card-title { color: #197655; }
.reviews .card-title { color: #c82f2f; }
.categories .card-title { color: #5236d6; }
.coupons .card-title { color: #c82f2f; }
.settings .card-title { color: #5236d6; }
.create-event-wrapper {
    position: relative;
    max-width: 860px;
    margin: 0 auto;
}
.outside-back-btn-wrapper {
    margin-top: 16px;
    margin-bottom: 15px;
}
.create-event-btn {
    background: #246be4;
    color: white;
    border: 0;
    border-radius: 5px;
    padding: 0.55em 1.5em;
    transition: background 0.17s;
    font-size: 1.09em;
    text-align: center;
    display: inline-block;
    text-decoration: none;
    cursor: pointer;
}
.create-event-btn:hover, .create-event-btn:focus {
    background: #1855b7;
    text-decoration: none;
    color: #fff;
}
.create-event-btn.back-btn {
    background: #888;
    color: #eee;
    margin-bottom: 8px;
}
.create-event-btn.back-btn:hover {
    background: #444;
    color: #fff;
}

.msg-error {
    background: #ffecee;
    border-left: 5px solid #e01e37;
    color: #a2001c;
    padding: 13px 15px 10px 15px;
    border-radius: 4px;
    font-size: 1.07em;
    margin-bottom: 14px;
    margin-top: 15px;
}

.msg-success {
    border-left: 5px solid #278946;
    background: #eafaea;
    color: #185b28;
    border-radius: 4px;
    padding: 14px 16px 12px 16px;
    font-size: 1.12em;
    margin-top: 20px;
    margin-bottom: 15px;
}
.create-container-centered {
    background: #fff;
    border-radius: 8px;
    border: 1.5px solid #e6f0ff;
    box-shadow: 0 5px 30px rgba(34, 73, 139, 0.09), 0 1.5px 6px rgba(34, 73, 139, 0.13);
    margin-bottom: 35px;
    max-width: 660px;
    margin-left: auto;
    margin-right: auto;
    margin-top: 10px;
    padding: 32px 34px 36px 34px;
}

.form-table {
    width: 100%;
    max-width: 610px;
    border-collapse: collapse;
}

.form-label {
    font-weight: 500;
    color: #334;
    display: block;
    margin-bottom: 0.25em;
    font-size: 1.02em;
}
.input-select, .input-text, .input-number, .input-area, .input-file {
    width: 98%;
    padding: 8px 9px;
    border: 1.4px solid #acbadc;
    border-radius: 4px;
    font-size: 1.01em;
    background: #fafdff;
    margin-bottom: 4px;
    box-sizing: border-box;
}
.input-select:focus, .input-text:focus, .input-number:focus, .input-area:focus, .input-file:focus {
    outline: 2px solid #3c89ee;
    border-color: #3476ba;
    background: #f2f7fe;
}
.input-number, .input-text {
    display: inline-block;
    width: 50%;
}

.input-area {
    width: 97.5%;
    min-height: 74px;
    max-width: 99%;
    resize: vertical;
}

.field-error {
    font-size: 0.98em;
    color: #cd233f;
    display: none;
    margin-top: 3px;
}

.input-invalid {
    border: 1.5px solid #cd233f !important;
    background: #fff8f7 !important;
}
#max-seats-caption {
    font-weight: 600;
    margin-left: 5px;
    color: #2363b9;
}
.field-hint, .form-label .field-hint {
    font-size: .95em;
    color: #377fd9;
    font-weight: normal;
    margin-left: 2px;
}
.form-label .field-hint2 {
    color: #6ca3f7;
}
#available-seats-span {
    font-size: .98em;
    color: #196bb5;
}
.msg-success h3 {
    margin-top: 2px;
}
.msg-success .create-event-btn {
    width: auto;
    display: inline-block;
    margin-top: 13px;
}
@media (max-width: 650px) {
    .create-container-centered {
        padding: 9px 5.5vw 19px 5.5vw;
    }
    .form-table td, .form-table th {
        padding: 0.6em 0.1em;
    }

}
body {
    overflow-x: hidden;
}

</style>
<!-- <link rel="stylesheet" href="css/index.css"> -->
<!-- <link rel="stylesheet" href="css/event_create.css"> -->
<script src="js/jquery-4.0.0.min.js"></script>
<script>
    function updateMaxSeatsAndPrice() {
        var sel = document.getElementById('category_id');
        var i = sel.selectedIndex;
        var option = sel.options[i];
        var maxSeats = option.getAttribute('data-max-seats');
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
        updateEventPrice();
    }

    function updateEventPrice() {
        var sel = document.getElementById('category_id');
        var option = sel.options[sel.selectedIndex];
        var pricePerHour = parseFloat(option.getAttribute('data-price-per-hour')) || 0;
        var startVal = document.getElementById('start_datetime').value;
        var endVal = document.getElementById('end_datetime').value;
        var priceInput = document.getElementById('event_price');
        if (priceInput && startVal && endVal && pricePerHour) {
            var start = new Date(startVal);
            var end = new Date(endVal);
            var hours = Math.ceil((end - start) / (1000 * 60 * 60));
            if (hours < 1) hours = 1;
            priceInput.value = (hours * pricePerHour).toFixed(2);
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
        $('#owner_id').on('change blur', function() {
            var val = $(this).val();
            if (!val) {
                $('#owner_id-error').text("Please select an event owner.").show();
                $(this).addClass("input-invalid");
            } else {
                $('#owner_id-error').hide();
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
            // removed 1 week ahead validation
            var valid = !!val;
            if (!val) {
                $('#start_datetime-error').text("Start datetime is required.").show();
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
                eventStart.setHours(0,0,0,0);
                valid = (deadline <= eventStart);
            }
            if (!val) {
                $('#reg_deadline-error').text("Registration deadline required.").show();
                $(this).addClass("input-invalid");
            } else if (!valid) {
                $('#reg_deadline-error').text("Deadline must be before event start date.").show();
                $(this).addClass("input-invalid");
            } else {
                $('#reg_deadline-error').hide();
                $(this).removeClass("input-invalid");
            }
        });

        $('#create-event-form').on('submit', function(e){
            $('#owner_id').trigger('blur');
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
    });

</script>

