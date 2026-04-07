<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

if (!isset($_SESSION['user_id'], $_SESSION['pending_event_checkout'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Session expired. Please create your event again.']);
    exit;
}

$pending = $_SESSION['pending_event_checkout'];
if ((int) $pending['owner_id'] !== (int) $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session.']);
    exit;
}

$config  = require __DIR__ . '/config/razorpay_config.php';
$keyId   = trim((string) ($config['key_id'] ?? ''));
$secret  = trim((string) ($config['key_secret'] ?? ''));

$expectedAmount = (int) ($_SESSION['razorpay_order_amount_paise'] ?? 0);
if ($expectedAmount < 100 || $expectedAmount !== (int) ($pending['amount_paise'] ?? 0)) {
    http_response_code(400);
    echo json_encode(['error' => 'Amount mismatch. Please go back and try again.']);
    exit;
}

// ── KEY-ONLY FLOW (no secret) ─────────────────────────────────────────────
if ($secret === '') {
    if (empty($_SESSION['razorpay_keyonly_checkout'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid checkout session.']);
        exit;
    }
    $paymentId = trim((string) ($input['razorpay_payment_id'] ?? ''));
    if ($paymentId === '' || strpos($paymentId, 'pay_') !== 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing payment ID from Razorpay.']);
        exit;
    }
    unset($_SESSION['razorpay_keyonly_checkout']);

// ── FULL ORDER + SIGNATURE FLOW (both keys) ───────────────────────────────
} else {
    $razorpay_order_id   = trim((string) ($input['razorpay_order_id'] ?? ''));
    $razorpay_payment_id = trim((string) ($input['razorpay_payment_id'] ?? ''));
    $razorpay_signature  = trim((string) ($input['razorpay_signature'] ?? ''));

    if ($razorpay_order_id === '' || $razorpay_payment_id === '' || $razorpay_signature === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Missing payment parameters.']);
        exit;
    }
    if (empty($_SESSION['razorpay_order_id']) || $razorpay_order_id !== $_SESSION['razorpay_order_id']) {
        http_response_code(400);
        echo json_encode(['error' => 'Order mismatch. Refresh and try again.']);
        exit;
    }
    $expectedSig = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, $secret);
    if (!hash_equals($expectedSig, $razorpay_signature)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payment signature.']);
        exit;
    }
    unset($_SESSION['razorpay_legacy_checkout']);
}

// ── SAVE EVENT ────────────────────────────────────────────────────────────
require_once __DIR__ . '/database/db_connect.php';
date_default_timezone_set('Asia/Kolkata');
mysqli_query($conn, "SET time_zone = '+05:30'");

$owner_id           = (int) $pending['owner_id'];
$title              = $pending['title'];
$description        = $pending['description'];
$category_id        = (int) $pending['category_id'];
$event_date         = $pending['event_date'];
$event_start_time   = $pending['event_start_time'];
$event_end_time     = $pending['event_end_time'];
$event_seats        = (int) $pending['event_seats'];
$available_seats    = (int) $pending['available_seats'];
$banner_path        = $pending['banner_path'];
$gallery_csv        = $pending['gallery_csv'] ?? '';
$event_reg_deadline = $pending['event_reg_deadline'];
$persons            = (int) $pending['persons'];
$event_price        = (int) $pending['event_price_int'];

$event_approval_status = 'pending';
$event_status          = 'draft';
$event_payment_status  = 'completed';

$conn->autocommit(false);

$stmt = mysqli_prepare($conn,
    "INSERT INTO events (owner_id, event_title, event_description, event_category,
        event_date, event_start_time, event_end_time, event_seats, event_available_seats,
        event_banner_image, event_gallery_images, event_registration_deadline,
        event_price, event_approval_status, event_status, event_paymeny_status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($stmt, 'ississsiisssisss',
    $owner_id, $title, $description, $category_id,
    $event_date, $event_start_time, $event_end_time,
    $event_seats, $available_seats,
    $banner_path, $gallery_csv, $event_reg_deadline,
    $event_price, $event_approval_status, $event_status, $event_payment_status
);

if (!mysqli_stmt_execute($stmt)) {
    $conn->rollback(); $conn->autocommit(true);
    http_response_code(500);
    echo json_encode(['error' => 'Could not save event: ' . mysqli_error($conn)]);
    exit;
}
$event_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

$booking_status = 'approved';
$bstmt = mysqli_prepare($conn,
    'INSERT INTO bookings (event_id, user_id, persons, booking_status) VALUES (?, ?, ?, ?)'
);
mysqli_stmt_bind_param($bstmt, 'iiis', $event_id, $owner_id, $persons, $booking_status);
if (!mysqli_stmt_execute($bstmt)) {
    $conn->rollback(); $conn->autocommit(true);
    http_response_code(500);
    echo json_encode(['error' => 'Could not save booking: ' . mysqli_error($conn)]);
    exit;
}
mysqli_stmt_close($bstmt);

$conn->commit();
$conn->autocommit(true);

// Mark coupon as used
if (!empty($pending['coupon_id'])) {
    $cu = mysqli_prepare($conn,
        "UPDATE coupons SET coupon_is_used='1', coupon_applied_to_event_id=? WHERE coupon_id=?"
    );
    mysqli_stmt_bind_param($cu, 'ii', $event_id, $pending['coupon_id']);
    mysqli_stmt_execute($cu);
    mysqli_stmt_close($cu);
}

// Auto-generate unique coupon if event price > 10000
$generated_coupon = null;
if ($event_price > 10000) {
    // Generate unique code
    do {
        $new_code = strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
        $chk = mysqli_prepare($conn, "SELECT coupon_id FROM coupons WHERE coupon_code = ? LIMIT 1");
        mysqli_stmt_bind_param($chk, 's', $new_code);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        $exists = mysqli_stmt_num_rows($chk) > 0;
        mysqli_stmt_close($chk);
    } while ($exists);

    $valid_till   = date('Y-m-d H:i:s', strtotime('+1 month'));
    $gen_discount = 10;
    $gc = mysqli_prepare($conn,
        "INSERT INTO coupons (coupon_code, coupon_from_event_id, coupon_user_id, coupon_discount, coupon_valid_till)
         VALUES (?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($gc, 'siiis', $new_code, $event_id, $owner_id, $gen_discount, $valid_till);
    if (mysqli_stmt_execute($gc)) {
        $generated_coupon = [
            'code'       => $new_code,
            'discount'   => $gen_discount,
            'valid_till' => $valid_till,
        ];
    }
    mysqli_stmt_close($gc);
}

unset(
    $_SESSION['pending_event_checkout'],
    $_SESSION['razorpay_order_id'],
    $_SESSION['razorpay_order_amount_paise'],
    $_SESSION['razorpay_keyonly_checkout'],
    $_SESSION['razorpay_legacy_checkout']
);

if ($generated_coupon) {
    $_SESSION['new_coupon_generated'] = $generated_coupon;
}

echo json_encode([
    'success'  => true,
    'event_id' => $event_id,
    'redirect' => 'payment_success.php?event_id=' . (int) $event_id,
]);
