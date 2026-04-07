<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'], $_SESSION['pending_event_checkout'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Session expired. Please start again from Create Event.']);
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

if ($keyId === '') {
    http_response_code(503);
    echo json_encode(['error' => 'Razorpay Key ID is not configured.']);
    exit;
}

$amountPaise = (int) ($pending['amount_paise'] ?? 0);
if ($amountPaise < 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payment amount.']);
    exit;
}

$_SESSION['razorpay_order_amount_paise'] = $amountPaise;

// Key-only mode: open Razorpay checkout without a server-side order
if ($secret === '') {
    unset($_SESSION['razorpay_order_id'], $_SESSION['razorpay_legacy_checkout']);
    $_SESSION['razorpay_keyonly_checkout'] = true;
    echo json_encode([
        'keyonly'  => true,
        'key_id'   => $keyId,
        'amount'   => $amountPaise,
        'currency' => 'INR',
    ]);
    exit;
}

// Both keys: create a Razorpay order server-side
unset($_SESSION['razorpay_keyonly_checkout']);
$_SESSION['razorpay_legacy_checkout'] = true;

$payload = json_encode([
    'amount'          => $amountPaise,
    'currency'        => 'INR',
    'receipt'         => 'evt_' . bin2hex(random_bytes(6)),
    'payment_capture' => 1,
]);

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($keyId . ':' . $secret),
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false || $code < 200 || $code >= 300) {
    http_response_code(502);
    echo json_encode(['error' => 'Could not create Razorpay order. Try again later.']);
    exit;
}

$data = json_decode($body, true);
if (empty($data['id'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Invalid response from Razorpay.']);
    exit;
}

$_SESSION['razorpay_order_id']            = $data['id'];
$_SESSION['razorpay_order_amount_paise']  = (int) $data['amount'];

echo json_encode([
    'keyonly'  => false,
    'key_id'   => $keyId,
    'order_id' => $data['id'],
    'amount'   => (int) $data['amount'],
    'currency' => $data['currency'] ?? 'INR',
]);
