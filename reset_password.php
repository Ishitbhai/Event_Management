<?php
// Start session for messages and login detection
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

// Initialize variables
$error = "";
$success = "";
$step = 1;
$email = '';
$token = '';

// Check for link from email (GET)
if (isset($_GET['email'], $_GET['token'])) {
    $email = mysqli_real_escape_string($conn, $_GET['email']);
    $token = mysqli_real_escape_string($conn, $_GET['token']);
    // Check validity of token and if it's not expired
    $sql = "SELECT user_id FROM users WHERE user_email='$email' AND user_token='$token'  LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) === 1) {
        $step = 2; // Show reset form
    } else {
        $error = "Invalid or expired password reset link.";
        $step = 0;
    }
}

function is_strong_password($password) {
    // At least one uppercase, one lowercase, one digit, one special char, at least 6 chars
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/', $password);
}

// Handle form POST (password reset)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_email'], $_POST['user_token'])) {
    $email = mysqli_real_escape_string($conn, $_POST['reset_email']);
    $token = mysqli_real_escape_string($conn, $_POST['user_token']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please complete all password fields.";
        $step = 2;
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
        $step = 2;
    } elseif (!is_strong_password($new_password)) {
        $error = "Password must be at least 6 characters and contain at least one uppercase letter, one lowercase letter, one digit, and one special character.";
        $step = 2;
    } else {
        // Re-check token validity
        $sql = "SELECT user_id FROM users WHERE user_email='$email' AND user_token='$token'  LIMIT 1";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) === 1) {
            // Hash the new password
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            // Update password and clear reset token
            $update_sql = "UPDATE users SET user_password='$hashed', user_token=NULL WHERE user_email='$email'";
            if (mysqli_query($conn, $update_sql)) {
                $success = "Your password has been reset successfully. You can now <a href='login.php'>log in</a>.";
                $step = 3;
            } else {
                $error = "Error resetting password. Please try again.";
                $step = 2;
            }
        } else {
            $error = "Invalid or expired reset link.";
            $step = 0;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - AOne Hub</title>
    <!-- Bootstrap CSS -->
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Your custom style if needed -->
    <link rel="stylesheet" href="css/login.css">
    <script src="js/jquery-4.0.0.min.js"></script>
    <script>
    $(document).ready(function() {
        function validatePasswordStrength(pw) {
            // Must have 1 upper, 1 lower, 1 digit, 1 special, min 6 chars
            var regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{6,}$/;
            return regex.test(pw);
        }
        $('#resetForm').on('submit', function(e) {
            var pw = $('#new_password').val();
            var cpw = $('#confirm_password').val();
            var $pwWarn = $('#pw_warning');
            $pwWarn.text('');
            if (!validatePasswordStrength(pw)) {
                $pwWarn.text('Password must be at least 6 characters and contain at least one uppercase letter, one lowercase letter, one digit, and one special character.');
                e.preventDefault();
                return false;
            } else if (pw !== cpw) {
                $pwWarn.text("Passwords do not match.");
                e.preventDefault();
                return false;
            }
        });
        $('#new_password').on('input', function() {
            var pw = $(this).val();
            var $pwWarn = $('#pw_warning');
            if (!validatePasswordStrength(pw)) {
                $pwWarn.text('Password must be at least 6 characters and contain at least one uppercase letter, one lowercase letter, one digit, and one special character.');
            } else {
                $pwWarn.text('');
            }
        });
    });
    </script>
</head>
<body class="bg-light">
<div class="container min-vh-100 d-flex align-items-center justify-content-center">
    <div class="auth-box card shadow p-4" style="max-width: 400px; width: 100%;">
        <h2 class="text-center mb-4">Reset Password</h2>
        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger text-center"><?php echo $error; ?></div>
        <?php } elseif (!empty($success)) { ?>
            <div class="alert alert-success text-center"><?php echo $success; ?></div>
        <?php } ?>

        <?php if ($step === 2 && empty($success)) { ?>
            <form method="POST" action="" id="resetForm" autocomplete="off">
                <input type="hidden" name="reset_email" value="<?php echo htmlspecialchars($email); ?>">
                <input type="hidden" name="user_token" value="<?php echo htmlspecialchars($token); ?>">

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password:</label>
                    <input type="password" name="new_password" id="new_password" class="form-control" autocomplete="new-password" />
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password:</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" autocomplete="new-password" />
                </div>
                <div id="pw_warning" class="text-danger mb-3" style="font-size:0.98em;"></div>
                <button type="submit" class="btn btn-success w-100">Reset Password</button>
            </form>
            <div class="links text-center mt-3">
                <a href="login.php" class="link-secondary">Back to Login</a>
            </div>
        <?php } elseif ($step === 3 && !empty($success)) { ?>
            <div class="text-center mt-4">
                <a href="login.php" class="btn btn-primary login-btn">Return to Login</a>
            </div>
        <?php } elseif ($step === 0) { ?>
            <div class="links text-center mt-3">
                <a href="forgot_password.php" class="link-secondary">Request another reset link</a>
            </div>
        <?php } ?>
    </div>
</div>
<!-- Bootstrap JS Bundle (Optional) -->
<script src="bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
