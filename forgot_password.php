
<?php

// Start session for login detection and messages
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect to index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Include database connection
require_once 'database/db_connect.php';

// Load PHPMailer if needed for email
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Handle messages
$error = "";
$success = "";

// Step 1: Request Email (initial form)
$step = 1;

// Handle POST actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['forgot_email'])) {
        // Step 1: Email form submitted
        $email = mysqli_real_escape_string($conn, $_POST['forgot_email']);

        // Check if email exists
        $sql = "SELECT user_id, user_name FROM users WHERE user_email='$email' LIMIT 1";
        $result = mysqli_query($conn, $sql);

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            $reset_token = bin2hex(random_bytes(16));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Save token and expiry to DB (a new table or on the user table)
            // Here, store in users table for simplicity
            $update_sql = "UPDATE users SET user_token='$reset_token' WHERE user_id = {$user['user_id']}";
            mysqli_query($conn, $update_sql);

            // Send reset email via PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'ishitvadhavana@gmail.com';
                $mail->Password = 'pwxo zzsn bafo emhf';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('ishitvadhavana@gmail.com', 'AOne Hub');
                $mail->addAddress($email, $user['user_name']);
                $mail->isHTML(true);
                $mail->Subject = 'AOne Hub Password Reset';
                $mail->Body = "Hello {$user['user_name']},<br><br>
                    We received a password reset request for your AOne Hub account.<br>
                    Please click the link below to reset your password:<br>
                    <a href='http://localhost/event_management/reset_password.php?email=$email&token=$reset_token'>Reset Password</a><br><br>
                    If you did not request this, you can ignore this email.<br><br>
                    Thank you!<br>AOne Hub Team";

                $mail->send();
                $success = "A password reset link has been sent to your email address.";
            } catch (Exception $e) {
                $error = "Email could not be sent. Please try again later.";
            }

        } else {
            $error = "No user found with that email.";
        }
        $step = 1;
    } elseif (isset($_POST['reset_email'], $_POST['user_token'], $_POST['new_password'], $_POST['confirm_password'])) {
        // Step 2: Reset password form submitted
        $email = mysqli_real_escape_string($conn, $_POST['reset_email']);
        $token = mysqli_real_escape_string($conn, $_POST['user_token']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($new_password) || empty($confirm_password)) {
            $error = "Please enter and confirm your new password.";
            $step = 2;
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
            $step = 2;
        } else {
            // Validate token and expiry
            $sql = "SELECT user_id FROM users WHERE user_email='$email' AND user_token='$token'  LIMIT 1";
            $result = mysqli_query($conn, $sql);
            if ($result && mysqli_num_rows($result) === 1) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                // Update password and clear reset token
                $update_sql = "UPDATE users SET user_password='$hashed', user_token=NULL WHERE user_email='$email'";
                if (mysqli_query($conn, $update_sql)) {
                    $success = "Your password has been reset successfully! You can now <a href='login.php'>login</a>.";
                    $step = 3;
                } else {
                    $error = "Error resetting password. Please try again.";
                    $step = 2;
                }
            } else {
                $error = "Invalid or expired reset link.";
                $step = 2;
            }
        }
    }
} elseif (isset($_GET['reset'], $_GET['email'], $_GET['token'])) {
    // Step 2: From email link. Show form to change password.
    $email = mysqli_real_escape_string($conn, $_GET['email']);
    $token = mysqli_real_escape_string($conn, $_GET['token']);
    // Check validity
    $sql = "SELECT user_id FROM users WHERE user_email='$email' AND user_token='$token'  LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) === 1) {
        $step = 2; // Show password change form
    } else {
        $error = "Invalid or expired reset link.";
        $step = 2;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - AOne Hub</title>
    <link rel="stylesheet" href="css/login.css">
    
</head>
<body>
<div class="auth-container">
    <div class="auth-box">
        <h2>Forgot Password</h2>
        <?php if (!empty($error)) { ?>
            <p class="fp-error"><?php echo $error; ?></p>
        <?php } elseif (!empty($success)) { ?>
            <p class="fp-success"><?php echo $success; ?></p>
        <?php } ?>

        <?php if ($step == 1 && empty($success)) { ?>
            <form method="POST" action="">
                <label for="forgot_email">Enter your email address:</label><br>
                <input type="email" name="forgot_email" id="forgot_email" required class="fp-input" /><br>
                <button type="submit" class="fp-btn">Send Reset Link</button>
            </form>
            <div class="links">
                <a href="login.php">Back to Login</a>
            </div>
        <?php } ?>

        <?php if ($step == 2 && empty($success)) { ?>
            <form method="POST" action="">
                <input type="hidden" name="reset_email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                <input type="hidden" name="user_token" value="<?php echo isset($token) ? htmlspecialchars($token) : ''; ?>">
                <label for="new_password">New Password:</label><br>
                <input type="password" name="new_password" id="new_password" required class="fp-input-reset" /><br>
                <label for="confirm_password">Confirm New Password:</label><br>
                <input type="password" name="confirm_password" id="confirm_password" required class="fp-input-reset-confirm" /><br>
                <button type="submit" class="fp-btn">Reset Password</button>
            </form>
            <div class="links">
                <a href="login.php">Back to Login</a>
            </div>
        <?php } ?>

        <?php if ($step == 3 && !empty($success)) { ?>
            <div class="fp-mt20">
                <a href="login.php" class="login-btn">Return to Login</a>
            </div>
        <?php } ?>
    </div>
</div>
</body>
</html>
