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
    <!-- <link rel="stylesheet" href="css/login.css"> -->
    <script src="js/jquery-4.0.0.min.js"></script>
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
