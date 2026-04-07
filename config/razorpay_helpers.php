<?php

/**
 * True when payment should use simulated flow (no Razorpay API / no Checkout).
 * Includes: mock_test_payment, or key_id only when auto_mock_if_no_secret is on.
 */
function razorpay_effective_mock(array $config): bool
{
    if (!empty($config['mock_test_payment'])) {
        return true;
    }
    $kid = trim((string) ($config['key_id'] ?? ''));
    $sec = trim((string) ($config['key_secret'] ?? ''));
    if (!empty($config['auto_mock_if_no_secret']) && $kid !== '' && $sec === '') {
        return true;
    }

    return false;
}

/**
 * Fetch payment from Razorpay (server-side verification — requires Key ID + Key Secret).
 *
 * @return array<string,mixed>|null
 */
function razorpay_fetch_payment(string $paymentId, string $keyId, string $secret): ?array
{
    $paymentId = trim($paymentId);
    if ($paymentId === '' || strpos($paymentId, 'pay_') !== 0) {
        return null;
    }
    $ch = curl_init('https://api.razorpay.com/v1/payments/' . rawurlencode($paymentId));
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . base64_encode($keyId . ':' . $secret),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) {
        return null;
    }
    $data = json_decode($body, true);

    return is_array($data) ? $data : null;
}
