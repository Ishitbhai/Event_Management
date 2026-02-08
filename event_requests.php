<?php
session_start();
require_once('header.php');
require_once('database/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Get user_type from database
$user_q = $conn->prepare("SELECT user_type FROM users WHERE user_id = ?");
$user_q->bind_param('i', $user_id);
$user_q->execute();
$user_rs = $user_q->get_result();
$user_row = $user_rs->fetch_assoc();
$user_q->close();

if (!$user_row || !in_array($user_row['user_type'], ['owner', 'admin'])) {
    header("Location: events.php");
    exit();
}
$user_type = $user_row['user_type'];

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if (!$event_id) {
    header("Location: events.php");
    exit();
}

// Always fetch up-to-date event record from DB for images, as instructed
$event_q = $conn->prepare("SELECT * FROM events WHERE event_id = ?");
$event_q->bind_param('i', $event_id);
$event_q->execute();
$event_rs = $event_q->get_result();
$event = $event_rs->fetch_assoc();
$event_q->close();

if (!$event) {
    header("Location: events.php");
    exit();
}

// --- Ownership Restriction Logic ---
$event_owner_id = isset($event['owner_id']) ? intval($event['owner_id']) : 0;
$owner_type = "";
if ($event_owner_id) {
    $owner_q = $conn->prepare("SELECT user_type FROM users WHERE user_id = ?");
    $owner_q->bind_param('i', $event_owner_id);
    $owner_q->execute();
    $owner_rs = $owner_q->get_result();
    $owner_row = $owner_rs->fetch_assoc();
    $owner_q->close();
    $owner_type = $owner_row ? $owner_row['user_type'] : "";
}
$forbidden = false;
if ($user_type === 'owner') {
    if ($owner_type !== 'owner' || $event_owner_id !== $user_id) {
        $forbidden = true;
    }
} elseif ($user_type === 'admin') {
    if ($owner_type !== 'admin' || $event_owner_id !== $user_id) {
        $forbidden = true;
    }
}
if ($forbidden) {
    header("Location: events.php");
    exit();
}

$event_title = isset($event['event_title']) ? htmlspecialchars($event['event_title']) : '';

// ---------- Gallery/Banner Images Logic ----------
$img_msg = '';

// Custom: fetch as plain comma list, no json, no brackets, no quotes
function fetch_images_from_db($conn, $event_id) {
    $updated_event_q = $conn->prepare("SELECT event_banner_image, event_gallery_images FROM events WHERE event_id=?");
    $updated_event_q->bind_param('i', $event_id);
    $updated_event_q->execute();
    $updated_event_rs = $updated_event_q->get_result();
    $updated_event = $updated_event_rs->fetch_assoc();
    $updated_event_q->close();
    $banner = $updated_event && isset($updated_event['event_banner_image']) ? trim($updated_event['event_banner_image']) : '';
    $gallery_cstr = $updated_event && isset($updated_event['event_gallery_images']) ? $updated_event['event_gallery_images'] : '';
    $gallery = [];
    if (!empty($gallery_cstr) && is_string($gallery_cstr)) {
        $arr = explode(',', $gallery_cstr);
        foreach ($arr as $img) {
            $even_cleaner = trim($img, " \t\n\r\0\x0B'\"");
            if ($even_cleaner !== '') $gallery[] = $even_cleaner;
        }
    }
    return [$banner, $gallery];
}

list($banner_image, $gallery_images) = fetch_images_from_db($conn, $event_id);

// Handle image upload and delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Banner upload/replace
    if (isset($_POST['upload_banner']) && isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $img_msg = '<div class="img-msg img-msg-error">Invalid banner image type.</div>';
        } else {
            $filename = "banner_" . $event_id . "_" . time() . "." . $ext;
            $dest = "images/" . $filename;
            if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $dest)) {
                $upd = $conn->prepare("UPDATE events SET event_banner_image=? WHERE event_id=?");
                $upd->bind_param('si', $filename, $event_id);
                if ($upd->execute()) {
                    $img_msg = '<div class="img-msg img-msg-success">Banner image updated.</div>';
                } else {
                    $img_msg = '<div class="img-msg img-msg-error">Failed to update banner image.</div>';
                }
                $upd->close();
            } else {
                $img_msg = '<div class="img-msg img-msg-error">Failed to upload banner image.</div>';
            }
        }
    }
    // Gallery upload (one or more)
    if (isset($_POST['upload_gallery']) && isset($_FILES['gallery_images'])) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $files = $_FILES['gallery_images'];
        if (is_array($files['name'])) {
            $count = count($files['name']);
        } else {
            $count = $files['name'] ? 1 : 0;
        }
        $uploads = [];
        for ($i=0; $i<$count; $i++) {
            if (!isset($files['name'][$i]) || !isset($files['error'][$i]) || $files['name'][$i] === '' || $files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;
            $filename = "gallery_" . $event_id . "_" . time() . "_" . mt_rand(1000,9999) . "." . $ext;
            $dest = "images/" . $filename;
            if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                $uploads[] = $filename;
            }
        }
        // Fetch current gallery, then merge with new uploads
        list($_b, $gallery_images) = fetch_images_from_db($conn, $event_id);
        if (!is_array($gallery_images)) $gallery_images = [];
        if ($uploads) {
            $gallery_images = array_merge($gallery_images, $uploads);
            // Remove empties and duplicates
            $gallery_images = array_unique(array_values(array_filter($gallery_images, function($v) {
                return is_string($v) && trim($v) !== '';
            })));
            // Save as plain string, only comma separator, no quotes or brackets
            $str = implode(",", $gallery_images);
            $upd = $conn->prepare("UPDATE events SET event_gallery_images=? WHERE event_id=?");
            $upd->bind_param('si', $str, $event_id);
            if ($upd->execute()) {
                $img_msg = '<div class="img-msg img-msg-success">Gallery images added.</div>';
            } else {
                $img_msg = '<div class="img-msg img-msg-error">Failed to add gallery image.</div>';
            }
            $upd->close();
        } elseif ($count && empty($uploads)) {
            $img_msg = '<div class="img-msg img-msg-error">No valid images uploaded to gallery.</div>';
        }
    }
    // Delete single gallery image
    if (isset($_POST['delete_gallery']) && isset($_POST['del_filename'])) {
        $del_file = basename($_POST['del_filename']);
        list($_b, $gallery_images) = fetch_images_from_db($conn, $event_id);
        $gallery_images_lc = array_map('strtolower', $gallery_images);
        $idx = array_search(strtolower($del_file), $gallery_images_lc);
        if ($idx !== false) {
            // --- UNLINK the image file from disk before updating DB ---
            $image_path = "images/" . $del_file;
            if (is_file($image_path)) {
                @unlink($image_path);
            }
            unset($gallery_images[$idx]);
            $gallery_images = array_values($gallery_images);
            $gallery_images = array_filter($gallery_images, function($v) {
                return is_string($v) && trim($v) !== '';
            });
            $str = implode(",", $gallery_images);
            $upd = $conn->prepare("UPDATE events SET event_gallery_images=? WHERE event_id=?");
            $upd->bind_param('si', $str, $event_id);
            if ($upd->execute()) {
                $img_msg = '<div class="img-msg img-msg-success">Gallery image deleted.</div>';
            } else {
                $img_msg = '<div class="img-msg img-msg-error">Failed to delete gallery image.</div>';
            }
            $upd->close();
        } else {
            $img_msg = '<div class="img-msg img-msg-error">Gallery image not found for deletion. (DB mismatch error)</div>';
        }
    }
    list($banner_image, $gallery_images) = fetch_images_from_db($conn, $event_id);
}

// --------- Booking Workflow ---------
$msg = '';
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'], $_POST['book_id'])
) {
    $action = $_POST['action'];
    $book_id = intval($_POST['book_id']);

    $booking_q = $conn->prepare("SELECT * FROM bookings WHERE book_id = ? AND event_id = ?");
    $booking_q->bind_param('ii', $book_id, $event_id);
    $booking_q->execute();
    $booking_rs = $booking_q->get_result();
    $booking = $booking_rs->fetch_assoc();
    $booking_q->close();

    if ($booking) {
        if ($action === 'approve' && $booking['booking_status'] === 'pending') {
            $persons = intval($booking['persons']);
            $available_seats = intval($event['event_available_seats']);
            if ($persons > $available_seats) {
                $msg = '<div class="msg msg-error">Not enough available seats for this request.</div>';
            } else {
                $conn->autocommit(false);
                $success = true;
                $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'approved' WHERE book_id = ?");
                $stmt->bind_param('i', $book_id);
                $success = $success && $stmt->execute();
                $stmt->close();
                $stmt = $conn->prepare("UPDATE events SET event_available_seats = event_available_seats - ? WHERE event_id = ?");
                $stmt->bind_param('ii', $persons, $event_id);
                $success = $success && $stmt->execute();
                $stmt->close();
                if ($success) {
                    $conn->commit();
                    $msg = '<div class="msg msg-success">Booking approved successfully!</div>';
                    $event['event_available_seats'] -= $persons;
                } else {
                    $conn->rollback();
                    $msg = '<div class="msg msg-error">An error occurred, please try again.</div>';
                }
                $conn->autocommit(true);
            }
        } elseif ($action === 'reject' && $booking['booking_status'] === 'pending') {
            $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'rejected' WHERE book_id = ?");
            $stmt->bind_param('i', $book_id);
            if ($stmt->execute()) {
                $msg = '<div class="msg msg-success">Booking rejected successfully!</div>';
            } else {
                $msg = '<div class="msg msg-error">Failed to reject booking.</div>';
            }
            $stmt->close();
        }
    }
}

// Get all bookings for this event, joined with user for name and email
$sql = "SELECT b.*, u.user_name as user_name, u.user_email as user_email
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        WHERE b.event_id = ?
        ORDER BY 
            CASE b.booking_status
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'rejected' THEN 3
                ELSE 4
            END, b.book_id ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $event_id);
$stmt->execute();
$rs = $stmt->get_result();
$bookings = [];
while ($row = $rs->fetch_assoc()) {
    $bookings[] = $row;
}
$stmt->close();
?>

<link rel="stylesheet" href="css/create_event.css">
<link rel="stylesheet" href="css/event_requests.css">

<style>
    /* Modal styles for Images Manage popup */
    .modal-overlay {
        display: none;
        position: fixed;
        z-index: 999;
        left: 0; top: 0; width: 100vw; height: 100vh;
        background: rgba(37,40,57,0.35);
    }
    .modal-content {
        position: absolute;
        left: 0; right: 0; top: 58px;
        margin: 0 auto;
        background: #fff;
        border-radius: 10px;
        max-width: 740px;
        width: 96vw;
        box-shadow: 0 2px 26px #b3b7e4;
        padding: 22px 28px 9px 28px;
        animation: modal-in 0.16s;
    }
    @keyframes modal-in {
        0% { transform:translateY(-60px); opacity:0; }
        100% { transform:translateY(0); opacity: 1; }
    }
    .modal-close-btn {
        position: absolute; right:18px; top:18px; font-size:1.1em; 
        background: none; border: none; color: #c02d2d; cursor: pointer;
        font-weight:600; padding:6px;
    }
    @media (max-width: 600px) {
        .modal-content { max-width: 99vw; padding: 7vw 6vw 2vw 6vw;}
    }
    /* Also keep the original box class for non-modal option */
    .standalone-images-manage {
        margin: 35px 0 55px 0; 
        max-width: 680px; 
        background: #f7f8ff; 
        padding: 22px 28px 9px 28px; 
        border-radius: 10px; 
        box-shadow: 0 4px 18px #e3e6fa;
    }
    .back-to-events-link {
        font-size:1.05em;
        text-decoration:none;
        color:#7b63e6;
        margin-bottom:24px;
        display:inline-block;
    }
    .page-title {
        text-align:center;
        color:#4242a0;
        margin-bottom:18px;
        font-size:1.25em;
    }
    .event-title-span {
        color:#417bb4;
    }

    /* Modal trigger button */
    .manage-images-btn {
        padding:7px 19px;
        background:#6179de;
        color:#fff;
        font-weight:600;
        border-radius:7px;
        border:none;
        box-shadow:0 2px 8px #d2d6fe;
        cursor:pointer; 
        font-size:1.03em;
        margin: 21px 0 12px 0;
        display:block;
        margin-left:auto;
    }

    /* Bookings/Requests Table Info  */
    .req-table-info {
        margin:30px 0 0 3px;
        font-size:1.07em;
        color:#5c5c79;
    }
    .no-bookings-yet {
        text-align:center;
        color:#999;
        font-size:1.1em;
        padding:43px 0;
    }

    /* Booking message/info */
    .msg {
        text-align:center;
        margin-bottom:17px;
        font-size:1.09em;
    }
    .msg-success {
        color: #22813d;
    }
    .msg-error {
        color: #bb2424;
    }
    /* Images messages */
    .img-msg {
        margin-bottom:14px;
        text-align:center;
        font-weight:500;
    }
    .img-msg-success {
        color:#22813d;
    }
    .img-msg-error {
        color:#bb2424;
    }
    /* Banner Manage */
    .banner-manage {
        margin-bottom:26px;
    }
    .banner-manage-img {
        max-width:260px;
        max-height:130px;
        border-radius:7px;
        box-shadow:0 2px 8px #dfe0f2;
    }
    .banner-missing {
        color:#e24;
    }
    .banner-none {
        color:#aab;
    }

    .banner-form-file {
        margin-bottom:5px;
    }
    .replace-banner-btn {
        margin-left:7px;
        padding:4px 15px;
    }
    /* HR */
    .images-modal-hr {
        margin:16px 0 20px 0;
        border-top:1px solid #eaeaf6;
    }
    /* Gallery Manage */
    .gallery-manage {
    }
    .gallery-manage-title {
        font-weight: bold;
    }
    .gallery-manage-sub {
        font-size: 0.95em;
        color: #888;
    }
    .gallery-images-box {
        margin:13px 0 17px 0;
        display:flex;
        flex-wrap:wrap;
        gap:18px;
    }
    .gallery-img-container {
        display:inline-block;
        text-align:center;
        position:relative;
    }
    .gallery-missing {
        color:#e95d32;
        font-size:0.97em;
    }
    .gallery-img {
        width:110px;
        height:82px;
        object-fit:cover;
        border-radius:6px;
        border:1px solid #e5e5f2;
        box-shadow:0 2px 8px #ededf9;
    }
    .delete-gallery-form {
        margin-top:7px;
    }
    .delete-gallery-btn {
        background:#d23c3c;
        color:#fff;
        border:none;
        border-radius:4px;
        padding:2px 10px;
        font-size:0.97em;
        cursor:pointer;
    }
    .gallery-add-form {
        margin:0;
    }
    .gallery-add-btn {
        margin-left:7px;
        padding:4px 15px;
    }
</style>

<div class="req-table-container">
    <a href="bookings.php" class="back-to-events-link">&#8592; Back to My Events</a>
    <h2 class="page-title">
        Booking Requests for: "<span class="event-title-span"><?php echo $event_title; ?></span>"
    </h2>

    <!-- Bookings/Requests Table -->
    <?php if ($msg) echo $msg; ?>

    <?php if (count($bookings)): ?>
    <table class="req-table">
        <tr>
            <th>#</th>
            <th>User Name</th>
            <th>Email</th>
            <th>Persons</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($bookings as $i => $b): 
            $pill_class = ($b['booking_status']=='pending' ? 'status-pending' : ($b['booking_status']=='approved' ? 'status-approved' : 'status-rejected'));
        ?>
        <tr>
            <td><?php echo $i+1; ?></td>
            <td><?php echo htmlspecialchars($b['user_name'] ?? 'User #'.$b['user_id']); ?></td>
            <td>
                <?php 
                    if (!empty($b['user_email'])) {
                        echo htmlspecialchars($b['user_email']);
                    } else {
                        echo '<span style="color:#999;">(no email)</span>';
                    }
                ?>
            </td>
            <td><?php echo intval($b['persons']); ?></td>
            <td>
                <span class="status-pill <?php echo $pill_class; ?>">
                    <?php echo ucfirst($b['booking_status']); ?>
                </span>
            </td>
            <td>
                <?php if ($b['booking_status'] == 'pending'): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="book_id" value="<?php echo intval($b['book_id']); ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="action-btn action-approve"
                        <?php if ($event['event_available_seats'] < $b['persons']) echo "disabled"; ?>
                        >Approve</button>
                    </form>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="book_id" value="<?php echo intval($b['book_id']); ?>">
                        <input type="hidden" name="action" value="reject">
                        <button type="submit" class="action-btn action-reject">Reject</button>
                    </form>
                <?php else: ?>
                    <span style="color:#bbb;">---</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <div class="req-table-info">
        Event available seats: <b><?php echo intval($event['event_available_seats']); ?></b>
    </div>
    <?php else: ?>
    <div class="no-bookings-yet">No bookings yet for this event.</div>
    <?php endif; ?>

    <!-- Images manage modal trigger button (MOVED HERE) -->
    <button id="open-images-modal" class="manage-images-btn">Manage Images</button>
</div>

<!-- Modal Overlay for Event Images Manage - default hidden -->
<div class="modal-overlay" id="images-manage-modal">
    <div class="modal-content">

        <button class="modal-close-btn" id="close-images-modal" title="Close">&times; Close</button>
        <h3 style="margin-bottom:14px;">Event Images</h3>
        <?php if ($img_msg) echo $img_msg; ?>

        <div class="banner-manage">
            <strong>Banner Image</strong> <small>(only one, can only replace)</small><br>
            <div class="banner-manage-img-wrap">
                <?php
                if ($banner_image && is_file('images/'.basename($banner_image))):
                ?>
                    <img src="images/<?php echo htmlspecialchars(basename($banner_image)); ?>" alt="Banner" class="banner-manage-img">
                <?php elseif ($banner_image && !is_file('images/'.basename($banner_image))): ?>
                    <span class="banner-missing">Banner image "<?php echo htmlspecialchars(basename($banner_image)); ?>" not found on disk.</span>
                <?php else: ?>
                    <span class="banner-none">No banner image.</span>
                <?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="banner_image" accept="image/*" required class="banner-form-file">
                <button type="submit" name="upload_banner" class="replace-banner-btn">Replace Banner</button>
            </form>
        </div>
        <hr class="images-modal-hr">
        <div class="gallery-manage">
            <span class="gallery-manage-title">Gallery Images</span>
            <small class="gallery-manage-sub">(can add new or delete individual)</small>
            <div class="gallery-images-box">
                <?php
                if ($gallery_images && is_array($gallery_images) && count($gallery_images)):
                    foreach ($gallery_images as $gi):
                        $gi_clean = basename(trim($gi));
                        if (!is_file("images/$gi_clean")): ?>
                            <div class="gallery-img-container">
                                <span class="gallery-missing">[Missing: <?php echo htmlspecialchars($gi_clean); ?>]</span>
                            </div>
                        <?php continue; endif;
                ?>
                    <div class="gallery-img-container">
                        <img src="images/<?php echo htmlspecialchars($gi_clean); ?>" alt="Gallery" class="gallery-img">
                        <form method="post" class="delete-gallery-form">
                            <input type="hidden" name="del_filename" value="<?php echo htmlspecialchars($gi_clean); ?>">
                            <button type="submit" name="delete_gallery" class="delete-gallery-btn">Delete</button>
                        </form>
                    </div>
                <?php endforeach; else: ?>
                    <span style="color:#aaa;">No images in gallery.</span>
                <?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data" class="gallery-add-form">
                <input type="file" name="gallery_images[]" accept="image/*" multiple required>
                <button type="submit" name="upload_gallery" class="gallery-add-btn">Add to Gallery</button>
            </form>
        </div>
    </div>
</div>

<!-- Fallback: Standalone images manage box for non-JS (will only show if JS disabled) -->
<noscript>
<div class="standalone-images-manage">
    <h3 style="margin-bottom:14px;">Event Images</h3>
    <?php if ($img_msg) echo $img_msg; ?>

    <div class="banner-manage">
        <strong>Banner Image</strong> <small>(only one, can only replace)</small><br>
        <div class="banner-manage-img-wrap">
            <?php
            if ($banner_image && is_file('images/'.basename($banner_image))):
            ?>
                <img src="images/<?php echo htmlspecialchars(basename($banner_image)); ?>" alt="Banner" class="banner-manage-img">
            <?php elseif ($banner_image && !is_file('images/'.basename($banner_image))): ?>
                <span class="banner-missing">Banner image "<?php echo htmlspecialchars(basename($banner_image)); ?>" not found on disk.</span>
            <?php else: ?>
                <span class="banner-none">No banner image.</span>
            <?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="banner_image" accept="image/*" required class="banner-form-file">
            <button type="submit" name="upload_banner" class="replace-banner-btn">Replace Banner</button>
        </form>
    </div>
    <hr class="images-modal-hr">
    <div class="gallery-manage">
        <span class="gallery-manage-title">Gallery Images</span>
        <small class="gallery-manage-sub">(can add new or delete individual)</small>
        <div class="gallery-images-box">
            <?php
            if ($gallery_images && is_array($gallery_images) && count($gallery_images)):
                foreach ($gallery_images as $gi):
                    $gi_clean = basename(trim($gi));
                    if (!is_file("images/$gi_clean")): ?>
                        <div class="gallery-img-container">
                            <span class="gallery-missing">[Missing: <?php echo htmlspecialchars($gi_clean); ?>]</span>
                        </div>
                    <?php continue; endif;
            ?>
                <div class="gallery-img-container">
                    <img src="images/<?php echo htmlspecialchars($gi_clean); ?>" alt="Gallery" class="gallery-img">
                    <form method="post" class="delete-gallery-form">
                        <input type="hidden" name="del_filename" value="<?php echo htmlspecialchars($gi_clean); ?>">
                        <button type="submit" name="delete_gallery" class="delete-gallery-btn">Delete</button>
                    </form>
                </div>
            <?php endforeach; else: ?>
                <span style="color:#aaa;">No images in gallery.</span>
            <?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data" class="gallery-add-form">
            <input type="file" name="gallery_images[]" accept="image/*" multiple required>
            <button type="submit" name="upload_gallery" class="gallery-add-btn">Add to Gallery</button>
        </form>
    </div>
</div>
</noscript>

<script>
// Modal logic for images manage popup
(function() {
    var imagesModal = document.getElementById("images-manage-modal");
    var openBtn = document.getElementById("open-images-modal");
    var closeBtn = document.getElementById("close-images-modal");
    if (imagesModal && openBtn && closeBtn) {
        openBtn.addEventListener('click', function(e){
            imagesModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
        closeBtn.addEventListener('click', function(){
            imagesModal.style.display = 'none';
            document.body.style.overflow = '';
        });
        imagesModal.addEventListener('click', function(ev){
            if (ev.target === imagesModal) {
                imagesModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    }
})();
</script>

<?php require_once('footer.php'); ?>
