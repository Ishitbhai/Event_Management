
<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    ?>
    <script>
        window.location.href="index.php";
    </script>
    <?php
    exit();
}

require_once 'database/db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

const FP_OTP_EXPIRY_SEC = 120;
const FP_RESEND_COOLDOWN_SEC = 60;
/** Max OTP emails (initial send + resends) per 24h window; not related to wrong guesses */
const FP_MAX_OTP_SENDS = 3;
const FP_SEND_LIMIT_RESET_SEC = 86400;
const FP_VERIFIED_TTL_SEC = 900;

function fp_fetch_reset_row(mysqli $conn, string $email, string $token): ?array
{
    $stmt = $conn->prepare(
        'SELECT pr.id, pr.user_id, pr.token, pr.otp_hash, pr.otp_expires_at, pr.last_sent_at, pr.last_attempt, pr.attempt_count, pr.verified_at,
                (pr.verified_at IS NOT NULL OR pr.otp_expires_at > NOW()) AS otp_or_verified_ok
         FROM password_reset_tokens pr
         INNER JOIN users u ON u.user_id = pr.user_id
         WHERE u.user_email = ? AND pr.token = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ss', $email, $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

/**
 * If attempt_count >= max and last_attempt is at least 24h ago in DB, reset counter (uses MySQL NOW()).
 */
function fp_apply_send_limit_reset(mysqli $conn, array $row): array
{
    $id = (int) $row['id'];
    if ($id <= 0) {
        return $row;
    }
    $max = (int) FP_MAX_OTP_SENDS;
    $sec = (int) FP_SEND_LIMIT_RESET_SEC;
    $sql = 'UPDATE password_reset_tokens SET attempt_count = 0, last_attempt = NULL
            WHERE id = ? AND attempt_count >= ? AND last_attempt IS NOT NULL
            AND TIMESTAMPDIFF(SECOND, last_attempt, NOW()) >= ?';
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('iii', $id, $max, $sec);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $row['attempt_count'] = 0;
            $row['last_attempt'] = null;
        }
        $stmt->close();
    }
    return $row;
}

/**
 * True when send/resend must be blocked: attempt_count >= max AND last_attempt within last 24h (DB clock).
 * If attempt_count < max, only the 60s resend rule applies (checked separately).
 */
function fp_is_send_limited(mysqli $conn, int $row_id): bool
{
    if ($row_id <= 0) {
        return false;
    }
    $max = (int) FP_MAX_OTP_SENDS;
    $sec = (int) FP_SEND_LIMIT_RESET_SEC;
    $sql = 'SELECT (attempt_count >= ? AND last_attempt IS NOT NULL
            AND TIMESTAMPDIFF(SECOND, last_attempt, NOW()) < ?) AS blocked
            FROM password_reset_tokens WHERE id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iii', $max, $sec, $row_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$res || !array_key_exists('blocked', $res)) {
        return false;
    }
    return (int) $res['blocked'] === 1;
}

function fp_bump_send_count(mysqli $conn, int $row_id): void
{
    $stmt = $conn->prepare('UPDATE password_reset_tokens SET attempt_count = attempt_count + 1, last_attempt = NOW() WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $row_id);
        $stmt->execute();
        $stmt->close();
    }
}

/** Uses DB clock so resend cooldown matches MySQL (avoids PHP vs DB timezone skew). */
function fp_seconds_until_resend(mysqli $conn, int $row_id): int
{
    if ($row_id <= 0) {
        return 0;
    }
    $c = (int) FP_RESEND_COOLDOWN_SEC;
    $sql = 'SELECT GREATEST(0, LEAST(?, ? - TIMESTAMPDIFF(SECOND, last_sent_at, NOW()))) AS w FROM password_reset_tokens WHERE id = ? LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('iii', $c, $c, $row_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$res || !isset($res['w'])) {
        return 0;
    }
    return (int) $res['w'];
}

function fp_configure_mailer(PHPMailer $mail): void
{
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'ishitvadhavana@gmail.com';
    $mail->Password = 'pwxo zzsn bafo emhf';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->setFrom('ishitvadhavana@gmail.com', 'AOne Hub');
    $mail->isHTML(true);
}

function fp_send_otp_mail(string $email, string $user_name, string $otp_plain): bool
{
    $mail = new PHPMailer(true);
    try {
        fp_configure_mailer($mail);
        $mail->addAddress($email, $user_name);
        $mail->Subject = 'AOne Hub Password Reset Code';
        $mail->Body = "Hello {$user_name},<br><br>"
            . "Your password reset code is: <strong>{$otp_plain}</strong><br>"
            . 'It expires in 2 minutes.<br><br>'
            . 'If you did not request this, you can ignore this email.<br><br>'
            . 'Thank you!<br>AOne Hub Team';
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function fp_json_exit(array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

$error = '';
$success = '';
$step = 1;
$email_val = '';
$session_token = '';
$fp_meta_last_sent = '';
$fp_meta_otp_expires = '';

$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['fp_verify_otp'])) {
        $email = trim((string) $_POST['reset_email']);
        $token = trim((string) $_POST['user_token']);
        $otp_in = preg_replace('/\D/', '', (string) $_POST['otp_code']);
        $email_val = htmlspecialchars($email);
        $step = 2;

        $row = fp_fetch_reset_row($conn, $email, $token);
        if (!$row) {
            $error = 'Invalid session. Please start again from your email.';
            unset($_SESSION['fp_reset_token'], $_SESSION['fp_reset_email']);
            $step = 1;
            if ($is_ajax) {
                fp_json_exit(['error' => $error, 'success' => '', 'step' => $step]);
            }
        } else {
            if (!empty($row['verified_at'])) {
                $success = 'Already verified. Enter your new password below.';
                $step = 3;
                $_SESSION['fp_reset_email'] = $email;
                $_SESSION['fp_reset_token'] = $token;
            } elseif (!(int) $row['otp_or_verified_ok']) {
                $error = 'This code has expired. Resend a new code after the cooldown.';
            } elseif (strlen($otp_in) !== 6) {
                $error = 'Please enter the 6-digit code.';
            } elseif (!password_verify($otp_in, $row['otp_hash'])) {
                $error = 'Incorrect code. You can keep trying until the code expires (2 minutes), then use Resend.';
            } else {
                $rid = (int) $row['id'];
                $stmt = $conn->prepare('UPDATE password_reset_tokens SET verified_at = NOW() WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('i', $rid);
                    $stmt->execute();
                    $stmt->close();
                }
                $_SESSION['fp_reset_email'] = $email;
                $_SESSION['fp_reset_token'] = $token;
                $success = 'Code verified. Choose your new password.';
                $step = 3;
            }
        }
        if ($is_ajax) {
            fp_json_exit([
                'error' => $error,
                'success' => $success,
                'step' => $step,
            ]);
        }
    } elseif (isset($_POST['fp_resend_otp'])) {
        $email = trim((string) $_POST['reset_email']);
        $token = trim((string) $_POST['user_token']);
        $email_val = htmlspecialchars($email);
        $step = 2;
        $row = null;

        $row = fp_fetch_reset_row($conn, $email, $token);
        if (!$row) {
            $error = 'Invalid session. Please enter your email again.';
            unset($_SESSION['fp_reset_token'], $_SESSION['fp_reset_email']);
            $step = 1;
        } else {
            $row = fp_apply_send_limit_reset($conn, $row);
            if (fp_is_send_limited($conn, (int) $row['id'])) {
                $error = 'Too many code requests. You can request a new code again after 24 hours.';
            } else {
                $wait = fp_seconds_until_resend($conn, (int) $row['id']);
                if ($wait > 0) {
                    $error = 'You can resend a code in ' . $wait . ' second' . ($wait === 1 ? '' : 's') . '.';
                } else {
                    $otp_plain = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $otp_hash = password_hash($otp_plain, PASSWORD_DEFAULT);
                    $rid = (int) $row['id'];
                    $stmt = $conn->prepare(
                        'UPDATE password_reset_tokens SET otp_hash = ?, otp_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), last_sent_at = NOW(), verified_at = NULL WHERE id = ?'
                    );
                    if ($stmt) {
                        $expiry = FP_OTP_EXPIRY_SEC;
                        $stmt->bind_param('sii', $otp_hash, $expiry, $rid);
                        $stmt->execute();
                        $stmt->close();
                    }
                    $stmt2 = $conn->prepare('SELECT user_name FROM users WHERE user_id = ? LIMIT 1');
                    $uname = '';
                    if ($stmt2) {
                        $uid = (int) $row['user_id'];
                        $stmt2->bind_param('i', $uid);
                        $stmt2->execute();
                        $r2 = $stmt2->get_result()->fetch_assoc();
                        $stmt2->close();
                        $uname = $r2['user_name'] ?? 'User';
                    }
                    if (fp_send_otp_mail($email, $uname, $otp_plain)) {
                        fp_bump_send_count($conn, $rid);
                        $success = 'A new code has been sent to your email.';
                        $step = 2;
                        $row = fp_fetch_reset_row($conn, $email, $token);
                        if ($row) {
                            $fp_meta_last_sent = $row['last_sent_at'];
                            $fp_meta_otp_expires = $row['otp_expires_at'];
                        }
                    } else {
                        $error = 'Email could not be sent. Please try again later.';
                    }
                }
            }
        }
        if ($is_ajax) {
            $payload = ['error' => $error, 'success' => $success, 'step' => $step];
            if ($row && $error === '' && $success !== '') {
                $row = fp_fetch_reset_row($conn, $email, $token) ?: $row;
                $payload['resend_in_sec'] = fp_seconds_until_resend($conn, (int) $row['id']);
                $payload['otp_expires_at'] = $row['otp_expires_at'] ?? '';
            }
            fp_json_exit($payload);
        }
    } elseif (isset($_POST['forgot_email'])) {
        $email = trim((string) $_POST['forgot_email']);
        $row = null;
        $session_token = '';
        $email_val = htmlspecialchars($email);

        $stmt = $conn->prepare('SELECT user_id, user_name FROM users WHERE user_email = ? LIMIT 1');
        if (!$stmt) {
            $error = 'Service unavailable. If the problem persists, contact support.';
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $ures = $stmt->get_result();
            $user = $ures ? $ures->fetch_assoc() : null;
            $stmt->close();

            if (!$user) {
                $error = 'No user found with that email.';
            } else {
                $user_id = (int) $user['user_id'];
                $stmt_pr = $conn->prepare('SELECT * FROM password_reset_tokens WHERE user_id = ? LIMIT 1');
                $existing = null;
                if ($stmt_pr) {
                    $stmt_pr->bind_param('i', $user_id);
                    $stmt_pr->execute();
                    $existing = $stmt_pr->get_result()->fetch_assoc();
                    $stmt_pr->close();
                }

                if ($existing) {
                    $existing = fp_apply_send_limit_reset($conn, $existing);
                    if (fp_is_send_limited($conn, (int) $existing['id'])) {
                        $error = 'Too many code requests. You can request a new code again after 24 hours.';
                    } elseif (fp_seconds_until_resend($conn, (int) $existing['id']) > 0) {
                        $w = fp_seconds_until_resend($conn, (int) $existing['id']);
                        $error = 'Please wait ' . $w . ' second' . ($w === 1 ? '' : 's') . ' before requesting another code.';
                    }
                }

                if ($error === '') {
                    $otp_plain = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $otp_hash = password_hash($otp_plain, PASSWORD_DEFAULT);
                    $new_token = bin2hex(random_bytes(32));
                    $db_ok = false;
                    $saved_id = 0;

                    if ($existing) {
                        $pid = (int) $existing['id'];
                        $stmt_up = $conn->prepare(
                            'UPDATE password_reset_tokens SET token = ?, otp_hash = ?, otp_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND), last_sent_at = NOW(), verified_at = NULL WHERE id = ?'
                        );
                        if ($stmt_up) {
                            $expiry = FP_OTP_EXPIRY_SEC;
                            $stmt_up->bind_param('ssii', $new_token, $otp_hash, $expiry, $pid);
                            $db_ok = $stmt_up->execute();
                            $stmt_up->close();
                            $saved_id = $pid;
                        }
                    } else {
                        $stmt_in = $conn->prepare(
                            'INSERT INTO password_reset_tokens (user_id, token, otp_hash, otp_expires_at, last_sent_at, attempt_count, last_attempt, verified_at)
                             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW(), 0, NULL, NULL)'
                        );
                        if ($stmt_in) {
                            $expiry = FP_OTP_EXPIRY_SEC;
                            $stmt_in->bind_param('issi', $user_id, $new_token, $otp_hash, $expiry);
                            $db_ok = $stmt_in->execute();
                            $saved_id = (int) $conn->insert_id;
                            $stmt_in->close();
                        }
                    }

                    if (!$db_ok || $saved_id <= 0) {
                        $error = 'Could not save reset data. Ask the administrator to run database/password_reset_tokens.sql.';
                    } elseif (fp_send_otp_mail($email, $user['user_name'], $otp_plain)) {
                        fp_bump_send_count($conn, $saved_id);
                        $_SESSION['fp_reset_email'] = $email;
                        $_SESSION['fp_reset_token'] = $new_token;
                        $success = 'We sent a 6-digit code to your email. It expires in 2 minutes.';
                        $step = 2;
                        $session_token = $new_token;
                        $row = fp_fetch_reset_row($conn, $email, $new_token);
                        if ($row) {
                            $fp_meta_last_sent = $row['last_sent_at'];
                            $fp_meta_otp_expires = $row['otp_expires_at'];
                        }
                    } else {
                        $error = 'Email could not be sent. Please try again later.';
                    }
                }
            }
        }
        if ($is_ajax) {
            $payload = [
                'error' => $error,
                'success' => $success,
                'step' => $step,
                'token' => $session_token,
                'email' => $email_val,
            ];
            if ($error === '' && $session_token !== '') {
                $r = fp_fetch_reset_row($conn, $email, $session_token);
                if ($r) {
                    $payload['resend_in_sec'] = fp_seconds_until_resend($conn, (int) $r['id']);
                    $payload['otp_expires_at'] = $r['otp_expires_at'];
                }
            }
            fp_json_exit($payload);
        }
    } elseif (isset($_POST['reset_email'], $_POST['user_token'], $_POST['new_password'], $_POST['confirm_password'])) {
        $email = trim((string) $_POST['reset_email']);
        $token = trim((string) $_POST['user_token']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $email_val = htmlspecialchars($email);

        if (empty($new_password) || empty($confirm_password)) {
            $error = 'Please enter and confirm your new password.';
            $step = 3;
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
            $step = 3;
        } else {
            $row = fp_fetch_reset_row($conn, $email, $token);
            if (!$row || empty($row['verified_at'])) {
                $error = 'Please verify your code first.';
                $step = 2;
            } elseif (time() - strtotime($row['verified_at']) > FP_VERIFIED_TTL_SEC) {
                $error = 'Verification expired. Please start over.';
                $step = 1;
                unset($_SESSION['fp_reset_token'], $_SESSION['fp_reset_email']);
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE users SET user_password = ? WHERE user_id = ?');
                if ($stmt) {
                    $uid = (int) $row['user_id'];
                    $stmt->bind_param('si', $hashed, $uid);
                    if ($stmt->execute()) {
                        $stmt->close();
                        $stmt_del = $conn->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
                        if ($stmt_del) {
                            $stmt_del->bind_param('i', $uid);
                            $stmt_del->execute();
                            $stmt_del->close();
                        }
                        unset($_SESSION['fp_reset_token'], $_SESSION['fp_reset_email']);
                        $success = "Your password has been reset successfully! You can now <a href='login.php'>login</a>.";
                        $step = 4;
                    } else {
                        $error = 'Error resetting password. Please try again.';
                        $step = 3;
                    }
                } else {
                    $error = 'Error resetting password. Please try again.';
                    $step = 3;
                }
            }
        }
        if ($is_ajax) {
            fp_json_exit([
                'error' => $error,
                'success' => $success,
                'step' => $step,
            ]);
        }
    }
}

if (!isset($_SESSION['fp_reset_token']) || !isset($_SESSION['fp_reset_email'])) {
    if ($step === 2 || $step === 3) {
        $step = 1;
    }
} else {
    $email_val = htmlspecialchars($_SESSION['fp_reset_email']);
    $session_token = $_SESSION['fp_reset_token'];
    $chk = fp_fetch_reset_row($conn, $_SESSION['fp_reset_email'], $_SESSION['fp_reset_token']);
    if (!$chk) {
        unset($_SESSION['fp_reset_token'], $_SESSION['fp_reset_email']);
        $step = 1;
    } else {
        $chk = fp_apply_send_limit_reset($conn, $chk);
        if (!empty($chk['verified_at']) && (time() - strtotime($chk['verified_at']) <= FP_VERIFIED_TTL_SEC)) {
            $step = 3;
        } elseif (empty($chk['verified_at'])) {
            $step = 2;
            $fp_meta_last_sent = $chk['last_sent_at'];
            $fp_meta_otp_expires = $chk['otp_expires_at'];
        } else {
            unset($_SESSION['fp_reset_token'], $_SESSION['fp_reset_email']);
            $step = 1;
        }
    }
}

$fp_boot_resend = 0;
$fp_boot_otp_exp = '';
if ($step === 2 && !empty($_SESSION['fp_reset_email']) && !empty($_SESSION['fp_reset_token'])) {
    $brow = fp_fetch_reset_row($conn, $_SESSION['fp_reset_email'], $_SESSION['fp_reset_token']);
    if ($brow && empty($brow['verified_at'])) {
        $fp_boot_resend = fp_seconds_until_resend($conn, (int) $brow['id']);
        $fp_boot_otp_exp = (string) $brow['otp_expires_at'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - AOne Hub</title>
    <!-- Add Bootstrap CSS -->
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Corrected: Use the same CSS as login page -->
    <!-- <link rel="stylesheet" href="css/login.css"> -->
     <style>
        body {
    min-height: 100vh;
    background: linear-gradient(135deg, #9796f0 0%, #fbc7d4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
    /* Smooth bg animation */
    animation: bgMove 12s ease-in-out infinite alternate;
}

@keyframes bgMove {
    0% {
        background-position: 0% 50%;
    }
    100% {
        background-position: 100% 50%;
    }
}

.auth-container {
    width: 100vw;
    min-height: 100vh;
    padding: 0;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    /* Fade-in animation */
    opacity: 0;
    animation: fadeIn 1s ease 0.2s forwards;
    box-sizing: border-box;
}

@keyframes fadeIn {
    to {
        opacity: 1;
    }
}

.auth-box {
    background: #fff;
    padding: 2.5rem 0; /* Remove horizontal padding (symmetric 0 left/right) */
    border-radius: 1rem;
    max-width: 410px;
    width: 100%;
    box-shadow: 0 6px 36px rgba(38, 38, 94, 0.14);
    border: 0;
    /* Slide-in animation */
    transform: translateY(60px) scale(0.97);
    opacity: 0;
    animation: boxAppear 0.9s cubic-bezier(.6, .11, .42, .98) 0.3s forwards;
    margin: 0 auto;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
}

@keyframes boxAppear {
    to {
        transform: none;
        opacity: 1;
    }
}

.auth-box form,
.auth-box > form {
    width: 90%;
    margin-left: auto;
    margin-right: auto;
    box-sizing: border-box;
}

.auth-box h2 {
    font-size: 2.1rem;
    font-weight: 700;
    color: #333b4a;
    margin-bottom: 0.3em;
    text-align: center;
    font-family: 'Roboto Slab', serif;
    letter-spacing: 0.5px;
    transform: translateY(20px);
    animation: fadeUp 0.76s 0.6s forwards;
}
.auth-box p {
    color: #596376;
    font-size: 1rem;
    margin-bottom: 2rem;
    text-align: center;
    opacity: 0;
    transform: translateY(20px);
    animation: fadeUp 0.75s 0.75s forwards;
}
@keyframes fadeUp {
    to {
        opacity: 1;
        transform: none;
    }
}

.input-row {
    display: flex;
    gap: 1rem;
    width: 100%;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}

.input-row .input-half {
    flex: 1 1 0;
    min-width: 0;
}
.input-row .input-half input {
    width: 100%;
    box-sizing: border-box;
    margin-left: 0;
    margin-right: 0;
    padding-left: 0;
    padding-right: 0;
}

/* Remove textarea styles: Address is now type="text" input, uses input styles */

/* Animations for all input fields */
.auth-box input[type="text"],
.auth-box input[type="email"],
.auth-box input[type="password"],
.auth-box input[type="tel"] {
    border-radius: 0.5rem;
    border: 1px solid #ced4da;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: border-color 0.18s, box-shadow 0.18s;
    background: #f5f8fc;
    color: #282828;
    box-sizing: border-box;
    margin-left: 0;
    margin-right: 0;
    width: 100%;
}

.auth-box input[type="text"]:focus,
.auth-box input[type="email"]:focus,
.auth-box input[type="password"]:focus,
.auth-box input[type="tel"]:focus {
    border-color: #7349e2;
    background: #eef2fa;
    outline: none;
    box-shadow: 0 2px 12px rgba(94, 92, 230, 0.12);
}

.auth-box button,
.auth-box input[type="submit"] {
    width: 100%;
    padding: 0.85rem;
    border: none;
    border-radius: 2rem;
    font-size: 1.05rem;
    background: linear-gradient(90deg, #5e5ce6, #fa709a 99%);
    color: #fff;
    font-weight: 600;
    transition: background 0.22s, transform 0.15s, box-shadow 0.2s;
    margin-top: 0.25rem;
    box-shadow: 0 3px 16px rgba(254, 98, 131, 0.09);
    box-sizing: border-box;
    margin-left: 0;
    margin-right: 0;
    display: block;
}

.auth-box button:hover,
.auth-box input[type="submit"]:hover {
    background: linear-gradient(90deg, #34307a 0%, #d53369 100%);
    transform: translateY(-2px) scale(1.03);
    opacity: 0.97;
    box-shadow: 0 5px 18px rgba(117, 82, 235, 0.14);
}

/* Link styling */
.links {
    margin-top: 1.2rem;
    font-size: 0.96rem;
    text-align: center;
    color: #868e96;
    opacity: 0;
    animation: fadeIn 1s 1.2s forwards;
}
.links a {
    color: #5e5ce6;
    text-decoration: underline;
    font-weight: 600;
    transition: color 0.18s;
    word-break: break-word;
    margin-left: 4px;
}
.links a:hover {
    color: #ae47f3;
    text-decoration: underline;
    letter-spacing: 0.7px;
}

/* Validation and error messages */
.error,
.fp-error {
    color: #e53935;
    font-size: 1rem;
    margin: 0.2em 0 0.6em 0;
    display: block;
    transition: color 0.22s;
}

input.error,
textarea.error {
    border-color: #e53935 !important;
    background: #fff5f6;
}
.fp-success {
    color: #25c685;
    font-size: 1rem;
    margin-top: 0.2em;
}
.fp-input,
.fp-input-reset,
.fp-input-reset-confirm {
    border-radius: 0.45rem;
    padding: 0.73rem 1rem;
    border: 1px solid #bdc7df;
    font-size: 1rem;
    background: #f7f9fa;
    width: 100%;
    margin-bottom: 1.2rem;
    margin-top: 0.7em;
    margin-left: 0;
    margin-right: 0;
    box-sizing: border-box;
}
.fp-btn {
    width: 100%;
    padding: 0.7rem;
    border-radius: 1.3rem;
    font-size: 1rem;
    background: #6a82fb;
    color: #fff;
    border: none;
    font-weight: 600;
    margin-top: 0.6em;
    box-shadow: 0 2px 6px rgba(100,100,120,0.12);
    transition: background 0.16s;
    margin-left: 0;
    margin-right: 0;
    box-sizing: border-box;
}
.fp-btn:hover {
    background: #b06ab3;
}

.fp-mt20 { margin-top: 1.4rem; }

.fp-otp-code {
    letter-spacing: 0.35em;
    font-size: 1.35rem;
    text-align: center;
    font-weight: 600;
}
.fp-timer-hint {
    font-size: 0.9rem;
    color: #596376;
    text-align: center;
    margin-top: 0.35rem;
}
#fp-resend-btn[disabled] { opacity: 0.55; cursor: not-allowed; }

/* Bootstrap-style responsiveness */
@media (max-width: 992px) {
    .auth-box {
        max-width: 500px;
        padding: 2rem 0; /* Remove horizontal padding for symmetry */
    }
    .auth-box form,
    .auth-box > form {
        width: 98%;
    }
}
@media (max-width: 768px) {
    .auth-box {
        max-width: 98vw;
        padding: 1.5rem 0; /* Remove horizontal padding */
        border-radius: 0.7rem;
    }
    .auth-box form,
    .auth-box > form {
        width: 99%;
    }
    .input-row {
        flex-direction: column;
        gap: 0.25rem;
        margin-bottom: 1rem;
    }
    .auth-box h2 {
        font-size: 1.3rem;
    }
    .auth-box p {
        font-size: 0.97rem;
        margin-bottom: 1.2rem;
    }
}
@media (max-width: 480px) {
    .auth-box {
        max-width: 99vw;
        min-width: 0;
        padding: 1rem 0; /* Zero side padding */
        border-radius: 0.5rem;
    }
    .auth-box form,
    .auth-box > form {
        width: 99vw;
    }
    .auth-box h2 {
        font-size: 1rem;
    }
    .auth-box p,
    .links {
        font-size: 0.88rem;
    }
    .fp-btn,
    .auth-box button,
    .auth-box input[type="submit"] {
        font-size: 0.98rem;
        padding: 0.65rem;
        border-radius: 0.95rem;
        margin-left: 0;
        margin-right: 0;
    }
    .fp-input,
    .fp-input-reset,
    .fp-input-reset-confirm {
        font-size: 0.94rem;
        padding: 0.5rem 0.8rem;
        width: 100%;
        margin-left: 0;
        margin-right: 0;
    }
}
@media (max-height: 480px) {
    body {
        flex-direction: column;
        padding: 5vw 0;
    }
}

.auth-box {
    padding-left: 1.2rem !important;
    padding-right: 1.2rem !important;
}
@media (max-width: 768px) {
    .auth-box {
        padding-left: 0.7rem !important;
        padding-right: 0.7rem !important;
    }
}
.input-row.row {
    margin-left: 0;
    margin-right: 0;
}
.auth-box .row {
    --bs-gutter-x: 0 !important;
}
     </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-box">
        <h2 class="mb-4 text-center">Forgot Password</h2>
        <div id="ajax-messages">
            <?php if (!empty($error)) { ?>
                <div class="alert alert-danger text-center fp-error"><?php echo $error; ?></div>
            <?php } elseif (!empty($success)) { ?>
                <div class="alert alert-success text-center fp-success"><?php echo $success; ?></div>
            <?php } ?>
        </div>

        <?php
        $show_email = ($step === 1);
        $show_otp = ($step === 2);
        $show_pwd = ($step === 3);
        $show_done = ($step === 4);
        ?>
        <div id="fp-section-email" class="mt-3" style="<?php echo $show_email ? '' : 'display:none;'; ?>">
            <form method="POST" action="" id="forgot-form" autocomplete="off">
                <div class="mb-3">
                    <label for="forgot_email" class="form-label">Enter your email address:</label>
                    <input type="email" name="forgot_email" id="forgot_email" class="form-control fp-input" value="<?php echo $email_val; ?>" />
                </div>
                <button type="submit" class="btn btn-primary w-100 fp-btn">Send OTP</button>
            </form>
            <div class="links text-center mt-3">
                <a href="login.php" class="link-secondary">Back to Login</a>
            </div>
        </div>

        <div id="fp-section-otp" style="<?php echo $show_otp ? '' : 'display:none;'; ?>">
            <form method="POST" action="" class="mt-3" id="verify-otp-form" autocomplete="one-time-code">
                <input type="hidden" name="fp_verify_otp" value="1">
                <input type="hidden" name="reset_email" id="fp_hidden_email" value="<?php echo htmlspecialchars($email_val); ?>">
                <input type="hidden" name="user_token" id="fp_hidden_token" value="<?php echo htmlspecialchars($session_token); ?>">
                <p class="text-muted small text-center mb-2">Enter the 6-digit code we emailed you.</p>
                <div class="mb-3">
                    <label for="otp_code" class="form-label">Verification code</label>
                    <input type="text" name="otp_code" id="otp_code" class="form-control fp-input fp-otp-code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" />
                </div>
                <p id="fp-otp-expire-hint" class="fp-timer-hint"></p>
                <button type="submit" class="btn btn-primary w-100 fp-btn">Verify code</button>
            </form>
            <div class="text-center mt-2">
                <button type="button" class="btn btn-link p-0" id="fp-resend-btn">Resend code</button>
                <p id="fp-resend-hint" class="fp-timer-hint mb-0"></p>
            </div>
            <div class="links text-center mt-3">
                <a href="login.php" class="link-secondary">Back to Login</a>
            </div>
        </div>

        <div id="fp-section-password" style="<?php echo $show_pwd ? '' : 'display:none;'; ?>">
            <form method="POST" action="" class="mt-3" id="reset-form" autocomplete="off">
                <input type="hidden" name="reset_email" id="pwd_hidden_email" value="<?php echo htmlspecialchars($email_val); ?>">
                <input type="hidden" name="user_token" id="pwd_hidden_token" value="<?php echo htmlspecialchars($session_token); ?>">
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password:</label>
                    <input type="password" name="new_password" id="new_password" class="form-control fp-input-reset" />
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password:</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control fp-input-reset-confirm" />
                </div>
                <button type="submit" class="btn btn-success w-100 fp-btn">Reset Password</button>
            </form>
            <div class="links text-center mt-3">
                <a href="login.php" class="link-secondary">Back to Login</a>
            </div>
        </div>

        <div id="fp-section-done" class="fp-mt20 text-center mt-4" style="<?php echo $show_done ? '' : 'display:none;'; ?>">
            <a href="login.php" class="btn btn-primary login-btn">Return to Login</a>
        </div>
    </div>
</div>
<!-- Bootstrap JS Bundle (Optional, if you use Bootstrap JS) -->
<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
window.FP_BOOT = <?php echo json_encode([
    'resend_in_sec' => (int) $fp_boot_resend,
    'otp_expires_at' => $fp_boot_otp_exp,
    'resend_cooldown_sec' => FP_RESEND_COOLDOWN_SEC,
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var fpResendInterval = null;
    var fpOtpInterval = null;

    function showMessage(type, msg) {
        var el = document.getElementById('ajax-messages');
        if (!el) return;
        if (type === 'success') {
            el.innerHTML = '<div class="alert alert-success text-center fp-success">' + msg + '</div>';
        } else if (type === 'error') {
            el.innerHTML = '<div class="alert alert-danger text-center fp-error">' + msg + '</div>';
        }
    }

    function setSectionVisibility(email, otp, pwd, done) {
        var sE = document.getElementById('fp-section-email');
        var sO = document.getElementById('fp-section-otp');
        var sP = document.getElementById('fp-section-password');
        var sD = document.getElementById('fp-section-done');
        if (sE) sE.style.display = email ? 'block' : 'none';
        if (sO) sO.style.display = otp ? 'block' : 'none';
        if (sP) sP.style.display = pwd ? 'block' : 'none';
        if (sD) sD.style.display = done ? 'block' : 'none';
    }

    function clearFpTimers() {
        if (fpResendInterval) clearInterval(fpResendInterval);
        if (fpOtpInterval) clearInterval(fpOtpInterval);
        fpResendInterval = null;
        fpOtpInterval = null;
    }

    function startFpTimers(resendInSec, otpExpiresAtStr) {
        clearFpTimers();
        var resendBtn = document.getElementById('fp-resend-btn');
        var resendHint = document.getElementById('fp-resend-hint');
        var otpHint = document.getElementById('fp-otp-expire-hint');
        var bootCfg = window.FP_BOOT || {};
        var cooldownCap = parseInt(bootCfg.resend_cooldown_sec, 10);
        if (!(cooldownCap > 0)) cooldownCap = 60;
        var resendSec = Math.min(cooldownCap, Math.max(0, parseInt(resendInSec, 10) || 0));
        var unlockMs = Date.now() + resendSec * 1000;
        var deadlineMs = 0;
        if (otpExpiresAtStr) {
            deadlineMs = Date.parse(String(otpExpiresAtStr).replace(' ', 'T'));
            if (isNaN(deadlineMs)) deadlineMs = Date.now() + 120000;
        } else {
            deadlineMs = Date.now() + 120000;
        }

        function tick() {
            var rs = Math.max(0, Math.ceil((unlockMs - Date.now()) / 1000));
            var os = Math.max(0, Math.ceil((deadlineMs - Date.now()) / 1000));
            if (resendBtn) {
                resendBtn.disabled = rs > 0;
            }
            if (resendHint) {
                resendHint.textContent = rs > 0 ? ('Resend available in ' + rs + 's') : '';
            }
            if (otpHint) {
                otpHint.textContent = os > 0 ? ('Code expires in ' + os + 's') : 'This code has expired. Use Resend for a new code.';
            }
        }
        tick();
        fpResendInterval = setInterval(tick, 1000);
        fpOtpInterval = fpResendInterval;
    }

    function postAjax(body, onDone) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '', true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    onDone(JSON.parse(xhr.responseText));
                } catch (e) {
                    onDone({ error: 'Unexpected response. Please try again.' });
                }
            } else {
                onDone({ error: 'Request failed. Please try again.' });
            }
        };
        xhr.send(body);
    }

    var boot = window.FP_BOOT || {};
    function fpCooldownCap() {
        var s = parseInt((window.FP_BOOT || {}).resend_cooldown_sec, 10);
        return (s > 0) ? s : 60;
    }
    if (document.getElementById('fp-section-otp') && document.getElementById('fp-section-otp').style.display !== 'none') {
        startFpTimers(boot.resend_in_sec || 0, boot.otp_expires_at || '');
    }

    var forgotForm = document.getElementById('forgot-form');
    if (forgotForm) {
        forgotForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var emailField = document.getElementById('forgot_email');
            var email = emailField.value.trim();
            if (!email) {
                showMessage('error', 'Please enter your email address.');
                emailField.focus();
                return;
            }
            postAjax('forgot_email=' + encodeURIComponent(email), function(res) {
                if (res.error) {
                    showMessage('error', res.error);
                    return;
                }
                if (res.success) {
                    showMessage('success', res.success);
                    document.getElementById('fp_hidden_email').value = res.email || email;
                    document.getElementById('fp_hidden_token').value = res.token || '';
                    document.getElementById('pwd_hidden_email').value = res.email || email;
                    document.getElementById('pwd_hidden_token').value = res.token || '';
                    setSectionVisibility(false, true, false, false);
                    document.getElementById('otp_code').value = '';
                    startFpTimers(fpCooldownCap(), res.otp_expires_at || '');
                }
            });
        });
    }

    var verifyOtpForm = document.getElementById('verify-otp-form');
    if (verifyOtpForm) {
        verifyOtpForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var email = document.getElementById('fp_hidden_email').value.trim();
            var token = document.getElementById('fp_hidden_token').value.trim();
            var otp = document.getElementById('otp_code').value.replace(/\D/g, '');
            if (otp.length !== 6) {
                showMessage('error', 'Please enter the 6-digit code.');
                return;
            }
            var body = 'fp_verify_otp=1&reset_email=' + encodeURIComponent(email) +
                '&user_token=' + encodeURIComponent(token) + '&otp_code=' + encodeURIComponent(otp);
            postAjax(body, function(res) {
                if (res.error) {
                    showMessage('error', res.error);
                    if (res.step === 1) {
                        clearFpTimers();
                        setSectionVisibility(true, false, false, false);
                    }
                    return;
                }
                if (res.success) {
                    showMessage('success', res.success);
                    clearFpTimers();
                    document.getElementById('pwd_hidden_email').value = email;
                    document.getElementById('pwd_hidden_token').value = token;
                    setSectionVisibility(false, false, true, false);
                }
            });
        });
    }

    var resendBtn = document.getElementById('fp-resend-btn');
    if (resendBtn) {
        resendBtn.addEventListener('click', function() {
            var email = document.getElementById('fp_hidden_email').value.trim();
            var token = document.getElementById('fp_hidden_token').value.trim();
            if (!email || !token) return;
            var body = 'fp_resend_otp=1&reset_email=' + encodeURIComponent(email) + '&user_token=' + encodeURIComponent(token);
            postAjax(body, function(res) {
                if (res.error) {
                    showMessage('error', res.error);
                    if (res.step === 1) {
                        clearFpTimers();
                        setSectionVisibility(true, false, false, false);
                    }
                    return;
                }
                if (res.success) {
                    showMessage('success', res.success);
                    startFpTimers(fpCooldownCap(), res.otp_expires_at || '');
                }
            });
        });
    }

    var resetForm = document.getElementById('reset-form');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var pwd1 = document.getElementById('new_password');
            var pwd2 = document.getElementById('confirm_password');
            if (!pwd1.value) {
                showMessage('error', 'Please enter your new password.');
                pwd1.focus();
                return;
            }
            if (!pwd2.value) {
                showMessage('error', 'Please confirm your new password.');
                pwd2.focus();
                return;
            }
            if (pwd1.value !== pwd2.value) {
                showMessage('error', 'Passwords do not match.');
                pwd2.focus();
                return;
            }
            var email = document.getElementById('pwd_hidden_email').value.trim();
            var token = document.getElementById('pwd_hidden_token').value.trim();
            var data = 'reset_email=' + encodeURIComponent(email) +
                '&user_token=' + encodeURIComponent(token) +
                '&new_password=' + encodeURIComponent(pwd1.value) +
                '&confirm_password=' + encodeURIComponent(pwd2.value);
            postAjax(data, function(res) {
                if (res.error) {
                    showMessage('error', res.error);
                    if (res.step === 1) {
                        setSectionVisibility(true, false, false, false);
                    } else if (res.step === 2) {
                        setSectionVisibility(false, true, false, false);
                    }
                    return;
                }
                if (res.success) {
                    showMessage('success', res.success);
                    setSectionVisibility(false, false, false, true);
                }
            });
        });
    }
});
</script>
</body>
</html>
