<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

if (!isset($_SESSION['user_id'], $_SESSION['pending_event_checkout'])) {
    echo json_encode(['error' => 'Session expired.']); exit;
}

$pending = $_SESSION['pending_event_checkout'];
if ((int)$pending['owner_id'] !== (int)$_SESSION['user_id']) {
    echo json_encode(['error' => 'Invalid session.']); exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
$code = isset($input['coupon_code']) ? strtoupper(trim($input['coupon_code'])) : '';

if ($code === '') { echo json_encode(['error' => 'Enter a coupon code.']); exit; }

require_once __DIR__ . '/database/db_connect.php';

$uid = (int)$_SESSION['user_id'];
$stmt = mysqli_prepare($conn,
    "SELECT coupon_id, coupon_discount FROM coupons
     WHERE coupon_code = ? AND coupon_user_id = ? AND coupon_is_used = '0'
       AND coupon_valid_till >= NOW() LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'si', $code, $uid);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $coupon_id, $discount);
$found = mysqli_stmt_fetch($stmt);
mysqli_stmt_close($stmt);

if (!$found) {
    echo json_encode(['error' => 'Invalid, expired, or already used coupon.']); exit;
}

$original_price = (float)$pending['event_price_float'];
$discounted_price = $original_price * (1 - $discount / 100);
$discounted_price_int = max(1, (int)round($discounted_price));
$amount_paise = max(100, $discounted_price_int * 100);

$_SESSION['pending_event_checkout']['coupon_id'] = $coupon_id;
$_SESSION['pending_event_checkout']['coupon_code'] = $code;
$_SESSION['pending_event_checkout']['coupon_discount'] = $discount;
$_SESSION['pending_event_checkout']['event_price_int'] = $discounted_price_int;
$_SESSION['pending_event_checkout']['amount_paise'] = $amount_paise;
$_SESSION['razorpay_order_amount_paise'] = $amount_paise;

echo json_encode([
    'success'    => true,
    'discount'   => $discount,
    'new_price'  => number_format($discounted_price, 2, '.', ''),
    'new_paise'  => $amount_paise,
]);
