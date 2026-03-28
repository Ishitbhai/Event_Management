<?php
// Password reset is handled on forgot_password.php using email OTP.
// This URL is kept so old email links still land somewhere sensible.

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

header('Location: forgot_password.php');
exit;
