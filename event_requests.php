<?php
session_start();
require_once('header.php');
require_once('database/db_connect.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ?>
    <script>
        window.location.href="login.php";
    </script>
    <?php
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
    ?>
    <script>
        window.location.href="events.php";
    </script>
    <?php
    exit();
}
$user_type = $user_row['user_type'];

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if (!$event_id) {
    ?>
    <script>
        window.location.href="events.php";
    </script>
    <?php
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
    ?>
    <script>
        window.location.href="events.php";
    </script>
    <?php
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
    ?>
    <script>
        window.location.href="events.php";
    </script>
    <?php
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

// Fetch coupon for this event (generated from this event)
$event_coupon = null;
$cq = $conn->prepare("SELECT coupon_code, coupon_discount, coupon_is_used, coupon_valid_till, coupon_applied_to_event_id FROM coupons WHERE coupon_from_event_id = ? LIMIT 1");
$cq->bind_param('i', $event_id);
$cq->execute();
$cr = $cq->get_result();
if ($cr->num_rows > 0) {
    $event_coupon = $cr->fetch_assoc();
}
$cq->close();
?>

<!-- Bootstrap 5 CDN -->
<link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
<!-- Animate.css for subtle element animations -->
<link rel="stylesheet" href="bootstrap/css/animate.min.css"/>


<style>
    body {
    background: linear-gradient(110deg, #f4f7fa 0%, #e0e3f6 100%);
}
.card-glass {
    background: rgba(255,255,255,.87);
    border-radius: 1.1rem;
    box-shadow: 0 6px 32px 0 #8181c733, 0 1.5px 5px #3a289025;
    backdrop-filter: blur(1.5px);
    margin-top: 38px;
    margin-bottom: 44px;
    padding: 2.4rem 1.2rem 2.5rem 1.2rem;
    animation: fadeInUpB 0.98s cubic-bezier(.23,1.19,.61,.91) both;
}
@media (min-width: 600px) {
    .card-glass { padding: 3.2rem 3.9rem 3.5rem 3.9rem; }
}
@media (max-width:540px) {
    .card-glass { padding: 1.2rem 0.2rem 1.2rem 0.2rem; box-shadow: 0 3px 15px #8181c71a; }
}
@keyframes fadeInUpB {
    from { opacity: 0; transform: translateY(48px); }
    to { opacity: 1; transform: translateY(0);}
}
.glow-header {
    background: linear-gradient(93deg, #658bf2 5%, #e18dfe 90%);
    color: #fff;
    font-weight: 900;
    padding: 1.6rem 2.3rem;
    border-radius: 0 0 48px 0;
    margin-bottom: 22px;
    text-shadow: 0 2px 13px #66598944;
    box-shadow: 0 1.5px 7px #6158ec11;
    text-align: center;
    letter-spacing: 0.2px;
    font-size: 1.37em;
    animation: fadeInGlow .8s 0.08s both;
}
@keyframes fadeInGlow {
    from { opacity: 0; filter: blur(3.5px);}
    to { opacity: 1; filter: blur(0);}
}
.back-to-events-link {
    color: #7a56de; font-weight: 500; font-size: 1.13em;
    text-decoration: none; display: inline-flex; align-items: center;
    margin-bottom: 1.15rem;
    transition: color 0.18s;
}
.back-to-events-link:hover { color: #5c23e2; text-decoration: underline; }
.status-pill {
    border-radius: 16px;
    font-size: 1em;
    font-weight: 600;
    padding: 3.5px 16px;
    text-align: center;
    transition: background 0.2s;
    animation: fadeInStatus 0.65s;
}
@keyframes fadeInStatus {
    from { opacity: 0;}
    to {opacity:1;}
}
.status-pending { background: #fef6be; color: #9e8702; border: 1.5px solid #e4d958;}
.status-approved { background: #d9fadb; color: #068b25; border: 1.5px solid #97dd91;}
.status-rejected { background: #fedfdf; color: #db3049; border: 1.5px solid #f8b4b4;}
.animate-btn-glow {
    box-shadow: 0 0 18px #6262ce55 !important;
    filter: brightness(1.15);
    animation: btnPulse .8s alternate infinite;
}
@keyframes btnPulse {
    0% { box-shadow:0 0 18px #6262ce38;}
    100% { box-shadow:0 0 34px #6262ce80;}
}
.img-msg-success,.msg-success { color: #068b25;}
.img-msg-error,.msg-error { color: #db3049;}
/* Modal style overriding bootstrap for custom glass look */
#images-manage-modal .modal-dialog {
    max-width: 730px;
    margin: 40px auto;
}
#images-manage-modal .modal-content {
    background: rgba(255,255,255,0.97);
    border-radius: 18px;
    box-shadow: 0 6px 32px #c4bcef44;
    padding: 1.1rem 1.5rem;
    border: none;
    animation: fadeInUpB 0.69s both;
}
#images-manage-modal .modal-header {
    border-bottom: none;
    padding-bottom: 0;
}
.gallery-images-box {
    flex-wrap: wrap;
    gap: 16px;
    display: flex;
    margin-top: 10px;
}
.gallery-img-container {
    display:inline-block;
    text-align:center;
    position:relative;
    margin-bottom: 7px;
    animation: fadeInScale 0.55s;
}
@keyframes fadeInScale {
    from { transform: scale(.85); opacity:0;}
    to   { transform: scale(1); opacity:1;}
}
.gallery-img {
    width: 115px;
    height: 86px;
    object-fit: cover;
    border-radius: 11px;
    border: 1px solid #e4e8f7;
    box-shadow: 0 2px 10px #e4d6fc26;
    transition: scale .14s, border 0.14s;
}
.gallery-img:hover {
    scale: 1.04;
    border-color: #728aec;
}
.delete-gallery-btn {
    background: #e84a3f !important;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding:2.5px 13px;
    font-size:0.98em;
    margin-top:5px;
    cursor:pointer;
    transition: box-shadow .18s;
}
.delete-gallery-btn:hover {
    box-shadow:0 2px 7px #e94a3f55,0 1px 3px #e84a3f44;
}
.gallery-add-btn {
    margin-left:9px;
    padding:5px 22px;
    font-weight: 600;
    background: #5d82ec;
    color:#fff;
    border-radius: 7px;
    border: none;
    box-shadow: 0 1.5px 6px #7f7ecf38;
    transition: background 0.15s, box-shadow 0.18s;
}
.gallery-add-btn:hover { background: #456cff; }
.replace-banner-btn {
    margin-left:9px;
    padding:5px 18px;
    font-weight: 600;
    background: #ffb44f;
    color:#372401;
    border-radius: 7px;
    border: none;
    box-shadow: 0 1.5px 6px #e8ca8d38;
    transition: background 0.15s, box-shadow 0.18s;
}
.replace-banner-btn:hover { background: #ffaa20; color: #312000;}
.banner-manage-img {
    max-width:260px;
    max-height:130px;
    border-radius:7px;
    box-shadow:0 2px 8px #dfe0f2;
    margin-bottom: 1rem;
}
.banner-missing { color: #e06622; }
.banner-none { color: #aaa;}
.req-table-info {
    margin: 15px 0 2px 0; font-size: 1.07em;
    color: #555889;
    text-align: right;
    font-weight: 500;
    letter-spacing:0.25px;
}
.no-bookings-yet {
    text-align:center;
    color:#888;
    font-size:1.16em;
    padding:40px 0;
    font-weight:500;
    opacity:0.93;
    animation: fadeInUpB .67s;
}
input[type="file"]::-webkit-file-upload-button,
input[type="file"]::file-selector-button {
    font-family: inherit;
    background: #ededfd;
    border: 1.1px solid #b6b8e6;
    border-radius: 5px;
    padding: 3px 9px;
    font-size:0.96em;
    color: #46538c;
    margin-right: 8px;
}

/* Responsive tweaks for tables/cards/forms on mobile */
@media (max-width:650px) {
    .glow-header { font-size: 1.02em; padding:0.95rem 0.5rem;}
    .req-table-info { text-align:center;}
}
</style>



<div class="container">
    <div class="card card-glass shadow animate__animated animate__fadeInUp">
        <a href="bookings.php" class="back-to-events-link mb-2">
            <span class="me-2">&#8592;</span>Back to My Events
        </a>
        <h2 class="glow-header mb-4">
            Booking Requests for: "<span class="event-title-span"><?php echo $event_title; ?></span>"
        </h2>

        <?php if ($msg): ?>
            <div class="alert <?php echo strpos($msg, 'error') !== false ? 'alert-danger animate__animated animate__shakeX' : 'alert-success animate__animated animate__fadeInDown'; ?> mt-2 mb-4">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <?php if (count($bookings)): ?>
        <div class="table-responsive">
        <table class="table table-hover align-middle rounded-4 shadow-sm bg-white animate__animated animate__fadeIn">
            <thead>
                <tr style="background:linear-gradient(90deg,#eef2fe 60%,#fcf5f8 100%);">
                    <th>#</th>
                    <th>User Name</th>
                    <th>Email</th>
                    <th>Persons</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bookings as $i => $b): 
                $pill_class = ($b['booking_status']=='pending' ? 'status-pending' : ($b['booking_status']=='approved' ? 'status-approved' : 'status-rejected'));
                ?>
                <tr>
                    <td><?php echo $i+1; ?></td>
                    <td><?php echo htmlspecialchars($b['user_name'] ?? 'User #' . $b['user_id']); ?></td>
                    <td>
                        <?php 
                            if (!empty($b['user_email'])) {
                                echo htmlspecialchars($b['user_email']);
                            } else {
                                echo '<span class="text-secondary small">(no email)</span>';
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
                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="book_id" value="<?php echo intval($b['book_id']); ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit"
                                    class="btn btn-success btn-sm px-3 fw-bold shadow-sm animate-btn-glow"
                                    <?php if ($event['event_available_seats'] < $b['persons']) echo "disabled"; ?>
                                >
                                    <span class="bi bi-check-circle"></span> Approve
                                </button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="book_id" value="<?php echo intval($b['book_id']); ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit"
                                    class="btn btn-danger btn-sm px-3 fw-bold shadow-sm"
                                >
                                    <span class="bi bi-x-circle"></span> Reject
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                            <span class="text-muted">---</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="req-table-info">
            Event available seats: <b><?php echo intval($event['event_available_seats']); ?></b>
        </div>
        <?php else: ?>
        <div class="no-bookings-yet animate__animated animate__fadeIn">
            No bookings yet for this event.
        </div>
        <?php endif; ?>

        <div class="text-end">
            <!-- Images manage modal trigger button -->
            <button id="open-images-modal"
                class="btn btn-primary btn-lg mt-4 mb-1 px-4 py-2 fw-bold shadow animate-btn-glow"
                data-bs-toggle="modal"
                data-bs-target="#images-manage-modal"
            >
                <span class="bi bi-images"></span> Manage Images
            </button>
        </div>

        <?php if ($event_coupon): ?>
        <div class="mt-4 p-4 rounded-4" style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;">
            <div style="font-size:13px;text-transform:uppercase;letter-spacing:.08em;opacity:.85;margin-bottom:6px;">🎟 Reward Coupon Generated for This Event</div>
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <div style="font-size:26px;font-weight:900;letter-spacing:.15em;font-family:monospace;background:rgba(255,255,255,.18);border-radius:8px;padding:6px 18px;border:2px dashed rgba(255,255,255,.5);">
                    <?php echo htmlspecialchars($event_coupon['coupon_code']); ?>
                </div>
                <div>
                    <div style="font-size:15px;font-weight:700;"><?php echo (int)$event_coupon['coupon_discount']; ?>% Discount</div>
                    <div style="font-size:13px;opacity:.9;">Valid till: <?php echo date('d M Y', strtotime($event_coupon['coupon_valid_till'])); ?></div>
                </div>
                <div>
                    <?php if ($event_coupon['coupon_is_used'] === '1'): ?>
                        <span style="background:#ef4444;border-radius:20px;padding:4px 14px;font-size:13px;font-weight:700;">Used</span>
                        <?php if ($event_coupon['coupon_applied_to_event_id']): ?>
                            <div style="font-size:12px;opacity:.85;margin-top:4px;">Applied to Event #<?php echo (int)$event_coupon['coupon_applied_to_event_id']; ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="background:#10b981;border-radius:20px;padding:4px 14px;font-size:13px;font-weight:700;">Not Used</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Overlay for Event Images Manage - Bootstrap Modal -->
<div class="modal fade" id="images-manage-modal" tabindex="-1" aria-labelledby="modalTitleImages" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header border-0">
        <h3 class="modal-title fs-4" id="modalTitleImages">Event Images</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <?php if ($img_msg) echo $img_msg; ?>

        <div class="banner-manage mb-4">
            <strong>Banner Image</strong> <small class="text-muted">(only one, can only replace)</small>
            <div class="banner-manage-img-wrap my-2">
                <?php if ($banner_image && is_file('images/'.basename($banner_image))): ?>
                    <img src="images/<?php echo htmlspecialchars(basename($banner_image)); ?>" alt="Banner" class="banner-manage-img">
                <?php elseif ($banner_image && !is_file('images/'.basename($banner_image))): ?>
                    <span class="banner-missing">Banner image "<?php echo htmlspecialchars(basename($banner_image)); ?>" not found on disk.</span>
                <?php else: ?>
                    <span class="banner-none">No banner image.</span>
                <?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center flex-wrap">
                <input type="file" name="banner_image" accept="image/*" required class="form-control form-control-sm w-auto banner-form-file" style="max-width:220px;">
                <button type="submit" name="upload_banner" class="replace-banner-btn">Replace Banner</button>
            </form>
        </div>

        <hr class="my-4 images-modal-hr">

        <div class="gallery-manage">
            <span class="gallery-manage-title fs-5">Gallery Images</span>
            <small class="gallery-manage-sub ms-2">(can add new or delete individual)</small>
            <div class="gallery-images-box mt-2 mb-3">
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
                        <img src="images/<?php echo htmlspecialchars($gi_clean); ?>" alt="Gallery" class="gallery-img mb-2 animate__animated animate__zoomIn">
                        <form method="post" class="delete-gallery-form">
                            <input type="hidden" name="del_filename" value="<?php echo htmlspecialchars($gi_clean); ?>">
                            <button type="submit" name="delete_gallery" class="delete-gallery-btn">Delete</button>
                        </form>
                    </div>
                <?php endforeach; else: ?>
                    <span style="color:#aaa;">No images in gallery.</span>
                <?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data" class="gallery-add-form mt-2 d-flex gap-2 align-items-center flex-wrap">
                <input type="file" name="gallery_images[]" accept="image/*" multiple required class="form-control form-control-sm w-auto" style="max-width:220px;">
                <button type="submit" name="upload_gallery" class="gallery-add-btn">Add to Gallery</button>
            </form>
        </div>
      </div>
    </div>
  </div>
</div>


<!-- Fallback: Standalone images manage box for non-JS (will only show if JS disabled) -->
<noscript>
<div class="container mt-4">
<div class="standalone-images-manage card card-body shadow">
    <h3 class="fs-4 mb-3">Event Images</h3>
    <?php if ($img_msg) echo $img_msg; ?>

    <div class="banner-manage mb-4">
        <strong>Banner Image</strong> <small class="text-muted">(only one, can only replace)</small>
        <div class="banner-manage-img-wrap my-2">
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
        <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center flex-wrap">
            <input type="file" name="banner_image" accept="image/*" required class="form-control form-control-sm w-auto banner-form-file" style="max-width:220px;">
            <button type="submit" name="upload_banner" class="replace-banner-btn">Replace Banner</button>
        </form>
    </div>
    <hr class="images-modal-hr my-4">
    <div class="gallery-manage">
        <span class="gallery-manage-title fs-5">Gallery Images</span>
        <small class="gallery-manage-sub ms-2">(can add new or delete individual)</small>
        <div class="gallery-images-box mt-2 mb-3">
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
                    <img src="images/<?php echo htmlspecialchars($gi_clean); ?>" alt="Gallery" class="gallery-img mb-2">
                    <form method="post" class="delete-gallery-form">
                        <input type="hidden" name="del_filename" value="<?php echo htmlspecialchars($gi_clean); ?>">
                        <button type="submit" name="delete_gallery" class="delete-gallery-btn">Delete</button>
                    </form>
                </div>
            <?php endforeach; else: ?>
                <span style="color:#aaa;">No images in gallery.</span>
            <?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data" class="gallery-add-form mt-2 d-flex gap-2 align-items-center flex-wrap">
            <input type="file" name="gallery_images[]" accept="image/*" multiple required class="form-control form-control-sm w-auto" style="max-width:220px;">
            <button type="submit" name="upload_gallery" class="gallery-add-btn">Add to Gallery</button>
        </form>
    </div>
</div>
</div>
</noscript>

<link rel="stylesheet" href="bootstrap/css/bootstrap-icons.min.css">
<!-- <script src="js/event_requests.js"></script> -->
<script>
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
