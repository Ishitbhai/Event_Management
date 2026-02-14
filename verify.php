<?php
// Start session first to check for user session and to show messages
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

$error = '';
$success = '';

// Check if email and token are provided
if (isset($_GET['email']) && isset($_GET['token'])) {
    $user_email = mysqli_real_escape_string($conn, $_GET['email']);
    $user_token = mysqli_real_escape_string($conn, $_GET['token']);

    // Ensure that user_status is NOT already active when verifying
    $sql = "SELECT * FROM users WHERE user_email='$user_email' AND user_token='$user_token' AND (user_status IS NULL OR user_status != 'active') LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        // Update user as verified
        $update_sql = "UPDATE users SET user_status='active', user_token=NULL WHERE user_email='$user_email'";
        if (mysqli_query($conn, $update_sql)) {
            $success = "Your email has been verified successfully! You can now <a href='login.php'>login</a>.";
        } else {
            $error = "Verification failed. Please try again later.";
        }
    } else { 
        // Check if account is already active
        $check_active_sql = "SELECT * FROM users WHERE user_email='$user_email' AND user_status='active' LIMIT 1";
        $check_active_res = mysqli_query($conn, $check_active_sql);
        if ($check_active_res && mysqli_num_rows($check_active_res) > 0) {
            $error = "This account is already verified. You can <a href='login.php'>login</a>.";
        } else {
            $error = "Invalid verification link or expired token.";
        }
    }
} else {
    $error = "Invalid request.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification - AOne Hub</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>

<div class="auth-container">
    <div class="auth-box" style="text-align:center; padding:30px;">
        <h2>Email Verification</h2>
        <?php if (!empty($error)) { ?>
            <p style="color:red;"><?php echo $error; ?></p>
        <?php } elseif (!empty($success)) { ?>
            <p style="color:green;"><?php echo $success; ?></p>
        <?php } ?>
    </div>
</div>

</body>
</html>
