<?php
/**
 * Razorpay API Key Configuration
 *
 * Paste your Test Key Id in this file (see steps below).
 * - For simulated payments: Set 'auto_mock_if_no_secret' => true and ONLY fill 'key_id'.
 * - For full real Razorpay Checkout: Fill BOTH 'key_id' and 'key_secret', and set 'mock_test_payment' => false.
 * - For permanent simulation (no real Razorpay): Set 'mock_test_payment' => true (you can leave 'key_id' empty).
 *
 * Steps:
 *   1. Get both values from your Razorpay Dashboard (Developers → API Keys, TEST mode).
 *   2. Paste Key Id into 'key_id' below.
 *   3. Paste Key Secret into 'key_secret' if you want the real Checkout dialog.
 *      - Leave 'key_secret' blank for simulation until you are ready.
 *   4. Don't ever put your LIVE keys here for testing!
 *
 * Note:
 * - If only Key Id is filled and 'auto_mock_if_no_secret' is true, the payment is simulated.
 * - For the real Razorpay flow, BOTH Key Id and Key Secret (test) must be filled and 'mock_test_payment' set to false.
 * - To force simulation always (even without keys), use 'mock_test_payment' => true.
 *
 * Android-style (legacy) Checkout:
 * - 'use_legacy_checkout_without_order' => true creates orders on server only when needed, like native app flow.
 */

return [
    'mock_test_payment' => false,
    'auto_mock_if_no_secret' => false,
    'use_legacy_checkout_without_order' => true,

    // PASTE YOUR TEST KEY ID HERE (starts with rzp_test_)
    'key_id'     => 'rzp_test_KGwT3XcJybhKgu',

    // Leave empty to use key_id-only checkout (no server verification)
    'key_secret' => '',
];
