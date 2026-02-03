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

/* FETCH CATEGORIES */
$category_query = "SELECT * FROM category";
$category_res = mysqli_query($conn, $category_query);
$categories = [];
while ($row = mysqli_fetch_assoc($category_res)) {
    $categories[] = $row;
}

$errors = [];
$success = false;

/* INIT VARIABLES */
$title = $description = $category_id = $start_datetime = $end_datetime = $reg_deadline = '';
$event_seats = $persons = 0;

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

    /* CATEGORY SEAT LIMIT */
    $cat_stmt = mysqli_prepare($conn, "SELECT category_seats FROM category WHERE category_id = ?");
    mysqli_stmt_bind_param($cat_stmt, "i", $category_id);
    mysqli_stmt_execute($cat_stmt);
    mysqli_stmt_bind_result($cat_stmt, $category_max_seats);
    mysqli_stmt_fetch($cat_stmt);
    mysqli_stmt_close($cat_stmt);

    if ($event_seats > $category_max_seats) {
        $errors[] = "Event seats exceed category limit.";
    }

    if ($persons > $event_seats) {
        $errors[] = "Family persons cannot exceed total seats.";
    }

    $available_seats = $event_seats - $persons;

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

    if (!empty($_FILES['banner_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            $errors[] = "Invalid banner image format.";
        } else {
            $banner_path = 'images/banner_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['banner_image']['tmp_name'], $banner_path);
        }
    } else {
        $errors[] = "Banner image is required.";
    }

    if (!empty($_FILES['gallery_images']['name'][0])) {
        foreach ($_FILES['gallery_images']['tmp_name'] as $i => $tmp) {
            $ext = strtolower(pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $path = 'images/gallery_' . uniqid() . '.' . $ext;
                move_uploaded_file($tmp, $path);
                $gallery_paths[] = $path;
            }
        }
    }
 
    $gallery_csv = implode(',', $gallery_paths);

    /* INSERT EVENT AND OWNER'S BOOKINGS */
    if (empty($errors)) {
        $conn->autocommit(false); // Start transaction

        // 1. Insert into events
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
                event_registration_deadline
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "issssssiisss",
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
            $event_reg_deadline
        );

        $event_ok = false;
        $booking_ok = false;

        if (mysqli_stmt_execute($stmt)) {
            $event_ok = true;
            $event_id = mysqli_insert_id($conn);

            // 2. Insert booking records (for the owner and family persons)
            $booking_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO bookings (event_id, user_id, persons) VALUES (?, ?, ?)"
            );
            mysqli_stmt_bind_param($booking_stmt, "iii", $event_id, $owner_id, $persons);

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

        if ($event_ok && $booking_ok) {
            $conn->commit();
            $success = true;
        } else {
            $conn->rollback();
        }
        $conn->autocommit(true);
    }
}
?>


<link rel="stylesheet" href="css/create_event.css">

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
        <?php if ($success): ?>
            <div class="msg-success">
                <h3 style="margin-top:2px;">Event Created Successfully!</h3>
                <p>We will inform you when the event is approved.</p>
                <a href="events.php" class="create-event-btn" style="width:auto;display:inline-block;margin-top:13px;">View Events</a>
            </div>
        <?php else: ?>
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
                        <select name="category_id" id="category_id" required class="input-select" onchange="updateMaxSeats()">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>"
                                    data-max-seats="<?php echo $cat['category_seats']; ?>"
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
                        <button type="submit" class="create-event-btn">Create Event</button>
                    </td>
                </tr>
            </table>
        </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    function updateMaxSeats() {
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
            // recalculate available seats
            var available = parseInt(eventSeatsInput.value) - (parseInt(personsInput.value) || 0);
            document.getElementById('available-seats-span').textContent = (available >= 0 ? available : 0);
            personsInput.setAttribute('max', eventSeatsInput.value);
        }
    }

    document.getElementById('category_id').addEventListener('change', function(){
        updateMaxSeats();
    });

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
            }
            if (!valid) {
                $('#gallery_images-error').text("Invalid image format in gallery. Allowed: jpg, jpeg, png, gif, webp.").show();
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
    });

</script>
<?php include 'footer.php'; ?>
