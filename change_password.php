<?php
// Start session for login detection
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'database/db_connect.php';

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $old_pw = $_POST['old_password'] ?? '';
    $new_pw = $_POST['new_password'] ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';

    $q = "SELECT user_password FROM users WHERE user_id=?";
    $stmt = mysqli_prepare($conn, $q);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res && $row = mysqli_fetch_assoc($res)) {
        $pw_hash = $row['user_password'];
        if (!password_verify($old_pw, $pw_hash)) {
            $msg = '<div class="profile-error-msg">Old password is incorrect.</div>';
        } else {
            if ($new_pw !== $confirm_pw) {
                $msg = '<div class="profile-error-msg">New passwords do not match.</div>';
            } else {
                if (strlen($new_pw) < 8) {
                    $msg = '<div class="profile-error-msg">Password must be at least 8 characters.</div>';
                } elseif (
                    !preg_match('/[A-Z]/', $new_pw) ||
                    !preg_match('/[a-z]/', $new_pw) ||
                    !preg_match('/[0-9]/', $new_pw) ||
                    !preg_match('/[^a-zA-Z0-9]/', $new_pw)
                ) {
                    $msg = '<div class="profile-error-msg">Password must contain at least one uppercase letter, one lowercase letter, one digit, and one special character.</div>';
                } else {
                    $hash = password_hash($new_pw, PASSWORD_DEFAULT);
                    $update_q = "UPDATE users SET user_password=? WHERE user_id=?";
                    $ustmt = mysqli_prepare($conn, $update_q);
                    mysqli_stmt_bind_param($ustmt, "si", $hash, $user_id);
                    if (mysqli_stmt_execute($ustmt)) {
                        echo "<script>
                        setTimeout(function(){
                            window.location.href='profile.php';
                        }, 1200);
                        </script>";
                        $msg = '<div class="profile-success-msg">Password changed successfully. Redirecting...</div>';
                    } else {
                        $msg = '<div class="profile-error-msg">Failed to update password. Please try again.</div>';
                    }
                }
            }
        }
    } else {
        $msg = '<div class="profile-error-msg">User not found.</div>';
    }
}
?>

<?php include 'header.php'; ?>

<link rel="stylesheet" href="css/change_password.css">

<div class="profile-bg-main">
    <div class="cpw-card-cool shadow">
        <div class="cpw-title">Change Password</div>
        <form class="cpw-cool-form" method="post" autocomplete="off" novalidate>
            <?php if ($msg) echo $msg; ?>
            <div class="mb-3">
                <label for="old_password" class="form-label">Old Password</label>
                <input type="password" 
                       class="form-control" 
                       id="old_password"
                       name="old_password"
                       required
                       autocomplete="current-password"
                       placeholder="Enter old password">
            </div>
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <input type="password" 
                       class="form-control" 
                       id="new_password" 
                       name="new_password"
                       required
                       minlength="8"
                       autocomplete="new-password"
                       placeholder="New password">
            </div>
            <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirm New Password</label>
                <input type="password" 
                       class="form-control" 
                       id="confirm_password" 
                       name="confirm_password"
                       required
                       autocomplete="new-password"
                       placeholder="Re-enter new password">
            </div>
            <button type="submit" class="cpw-btn-save mt-3 mb-2">Update Password</button>
        </form>
        <a href="profile.php" class="cpw-form-footer-link">&larr; Back to Profile</a>
    </div>
</div>

<?php include 'footer.php'; ?>