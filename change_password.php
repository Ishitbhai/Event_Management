<?php
// Start session for login detection
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    ?>
    <script>
        window.location.href="login.php";
    </script>
    <?php
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

<!-- <link rel="stylesheet" href="css/change_password.css"> -->
<style>
    body {
    background: #f5f7fa;
}
.profile-bg-main {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f0f6fc 0%, #e7eaf6 100%);
}
.cpw-card-cool {
    width: 100%;
    max-width: 410px;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(51,60,89,0.09), 0 1.5px 3px rgba(40,42,66,0.049);
    padding: 38px 34px 24px 34px;
    margin: 36px auto;
    position: relative;
    transition: box-shadow 0.15s;
}
.cpw-card-cool:hover,
.cpw-card-cool:focus-within {
    box-shadow: 0 6px 24px 3px rgba(51, 60, 89, 0.13);
}
.cpw-title {
    font-size: 1.62em;
    font-weight: 700;
    color: #25316d;
    margin-bottom: 27px;
    letter-spacing: 0.03em;
    text-align: center;
}
.profile-error-msg, .profile-success-msg {
    font-size: 1.03em;
    border-radius: 4px;
    margin-bottom: 20px;
    padding: 10px 15px;
    text-align: center;
    letter-spacing: 0.01em;
}
.profile-error-msg {
    background: #fcf2f2;
    color: #bc3131;
    border: 1px solid #edc7c7;
}
.profile-success-msg {
    background: #e8f8f5;
    color: #22755f;
    border: 1px solid #bdeedc;
}
.form-label {
    color: #164065;
    letter-spacing: .01em;
    font-weight: 600;
    font-size: 1.08em;
    margin-bottom: 7px;
}
.cpw-cool-form .form-control {
    border-radius: 6px;
    background: #f9fafd;
    border: 1.2px solid #d1d7e0;
    color: #28365d;
    font-size: 1.07em;
    transition: border-color 0.16s;
    margin-bottom: 2px;
    box-shadow: none;
}
.cpw-cool-form .form-control:focus {
    border-color: #4a92f3;
    background: #f1f8ff;
    outline: none;
    box-shadow: 0 0 2px #4a92f3;
}
.cpw-btn-save {
    width: 100%;
    background: linear-gradient(92deg, #313866, #6272a4);
    color: #fff;
    padding: 10px 10px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    font-size: 1.1em;
    letter-spacing: 0.02em;
    transition: background 0.17s, box-shadow 0.18s;
    box-shadow: 0 3px 16px rgba(49,49,81,0.07);
}
.cpw-btn-save:hover, .cpw-btn-save:focus {
    background: linear-gradient(95deg,#28345c,#546093);
    color: #fff;
    box-shadow: 0 8px 18px 0 rgba(49,49,81,0.10);
}
.cpw-form-footer-link {
    display: block;
    text-align: center;
    margin-top: 25px;
    font-size: 1.04em;
    text-decoration: none;
    color: #6486b6;
    letter-spacing: .013em;
    font-weight: 500;
}
.cpw-form-footer-link:hover,
.cpw-form-footer-link:focus {
    text-decoration: underline;
    color: #415d87;
}
@media (max-width:540px) {
    .cpw-card-cool {
        padding: 18vw 6vw 8vw 6vw;
        max-width: 98vw;
    }
    .cpw-title { font-size: 1.27em; }
    .cpw-form-footer-link { font-size: 1em; }
}
</style>
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