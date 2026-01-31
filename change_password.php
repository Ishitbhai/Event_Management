<?php
// Start session for login detection
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'database/db_connect.php';

// Function to safely output html
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Process form submission
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $old_pw = $_POST['old_password'] ?? '';
    $new_pw = $_POST['new_password'] ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    // Fetch user password hash
    $q = "SELECT user_password FROM users WHERE user_id=?";
    $stmt = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res && $row = mysqli_fetch_assoc($res)) {
        $pw_hash = $row['user_password'];
        if (!password_verify($old_pw, $pw_hash)) {
            $msg = '<div class="err">Old password is incorrect.</div>';
        } else {
            if ($new_pw !== $confirm_pw) {
                $msg = '<div class="err">New passwords do not match.</div>';
            } else {
                // Password must be at least 8 characters and contain upper/lower/special/digit
                if (strlen($new_pw) < 8) {
                    $msg = '<div class="err">Password must be at least 8 characters.</div>';
                } elseif (
                    !preg_match('/[A-Z]/', $new_pw) ||
                    !preg_match('/[a-z]/', $new_pw) ||
                    !preg_match('/[0-9]/', $new_pw) ||
                    !preg_match('/[^a-zA-Z0-9]/', $new_pw)
                ) {
                    $msg = '<div class="err">Password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.</div>';
                } else {
                    // All good, update
                    $hash = password_hash($new_pw, PASSWORD_DEFAULT);
                    $update_q = "UPDATE users SET user_password=? WHERE user_id=?";
                    $ustmt = mysqli_prepare($conn, $update_q);
                    mysqli_stmt_bind_param($ustmt, "si", $hash, $user_id);
                    if (mysqli_stmt_execute($ustmt)) {
                        // Redirect after a second
                        echo "<script>
                        setTimeout(function(){
                            window.location.href='profile.php';
                        }, 1200);
                        </script>";
                        $msg = '<div class="ok">Password changed successfully. Redirecting...</div>';
                    } else {
                        $msg = '<div class="err">Failed to update password. Please try again.</div>';
                    }
                }
            }
        }
    } else {
        $msg = '<div class="err">User not found.</div>';
    }
}
?>

<?php include 'header.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<link rel="stylesheet" href="css/change_password.css">

<div class="cpw-container">
    <h2>Change Password</h2>
    <form id="changePwForm" method="post" autocomplete="off" novalidate>
        <?php if ($msg) echo $msg; ?>
        <div class="cpw-row">
            <label class="cpw-label" for="old_password">Old Password:</label>
            <input class="cpw-input" type="password" name="old_password" id="old_password" required autocomplete="current-password">
        </div>
        <div class="cpw-row">
            <label class="cpw-label" for="new_password">New Password:</label>
            <input class="cpw-input" type="password" name="new_password" id="new_password" required autocomplete="new-password"
            minlength="8">
        </div>
        <div class="cpw-row">
            <label class="cpw-label" for="confirm_password">Confirm New Password:</label>
            <input class="cpw-input" type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password">
        </div>
        <div class="cpw-actions">
            <button type="submit">Update Password</button>
        </div>
    </form>
    <div class="cpw-links">
        <a href="profile.php">Back to Profile</a>
    </div>
</div>

<?php include 'footer.php'; ?>
