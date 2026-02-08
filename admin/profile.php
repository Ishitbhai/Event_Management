<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1 || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require('../database/db_connect.php');

$admin_id = (int)$_SESSION['user_id'];
$success_msg = $error_msg = "";

// Fetch admin user data from database
$query = $conn->prepare("SELECT user_name, user_email, user_phone_number FROM users WHERE user_id=? LIMIT 1");
$query->bind_param("i", $admin_id);
$query->execute();
$query->bind_result($user_name, $user_email, $user_phone_number);
$query->fetch();
$query->close();

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    // Edit fields: name/phone or password (email not updatable)
    if (isset($_POST['save_profile'])) {
        $new_name = trim($_POST['user_name']);
        $new_phone = trim($_POST['user_phone_number']);
        // Server-side: Ensure name is only text (no number)
        if (!$new_name) {
            $error_msg = "Name cannot be empty.";
        } elseif (!preg_match("/^[a-zA-Z\s]+$/", $new_name)) {
            $error_msg = "Name must only contain letters and spaces.";
        } elseif (!preg_match("/^[0-9]{10}$/", $new_phone)) {
            $error_msg = "Phone number must be exactly 10 digits.";
        } else {
            $stmt = $conn->prepare("UPDATE users SET user_name=?, user_phone_number=? WHERE user_id=?");
            $stmt->bind_param("ssi", $new_name, $new_phone, $admin_id);
            if ($stmt->execute()) {
                $success_msg = "Profile updated successfully.";
                $user_name = $new_name;
                $user_phone_number = $new_phone;
            } else {
                $error_msg = "Failed to update profile.";
            }
            $stmt->close();
        }
    } else if (isset($_POST['change_password'])) {
        // Handle password change
        $current_pw = $_POST['current_password'];
        $new_pw = $_POST['new_password'];
        $confirm_pw = $_POST['confirm_password'];
        if (!$current_pw || !$new_pw || !$confirm_pw) {
            $error_msg = "All password fields are required.";
        } else if ($new_pw !== $confirm_pw) {
            $error_msg = "New password and confirmation do not match.";
        } elseif (
            !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $new_pw)
        ) {
            $error_msg = "Password must be minimum 8 characters, include upper and lower case letters, a digit and a special character.";
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT user_password FROM users WHERE user_id=?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $stmt->bind_result($hashed_pw);
            $stmt->fetch();
            $stmt->close();

            if (!password_verify($current_pw, $hashed_pw)) {
                $error_msg = "Current password is incorrect.";
            } else {
                // Update new password
                $hashed_new_pw = password_hash($new_pw, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET user_password=? WHERE user_id=?");
                $stmt->bind_param("si", $hashed_new_pw, $admin_id);
                if ($stmt->execute()) {
                    $success_msg = "Password changed successfully.";
                } else {
                    $error_msg = "Failed to change password.";
                }
                $stmt->close();
            }
        }
    }
}

$page_heading = "My Profile";
require('sidebar.php');
?>

<link rel="stylesheet" href="css/profile.css">

<!-- jQuery CDN for validation -->
<script src="js/jquery-4.0.0.min.js"></script>
<script src="js/profile.js"></script>

<div class="profile-main">
    <div class="profile-avatar">
        &#128100;
    </div>
    <h2 class="profile-title">
        Edit Profile
    </h2>
    <?php if ($error_msg): ?>
        <div class="err-msg"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php elseif ($success_msg): ?>
        <div class="success-msg"><?php echo htmlspecialchars($success_msg); ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
        <table class="profile-info-table">
            <tr>
                <td class="profile-label">Name:</td>
                <td class="profile-value">
                    <input type="text" name="user_name" required value="<?php echo htmlspecialchars($user_name); ?>">
                </td>
            </tr>
            <tr>
                <td class="profile-label">Email:</td>
                <td class="profile-value">
                    <input type="email" name="user_email" required value="<?php echo htmlspecialchars($user_email); ?>" readonly>
                </td>
            </tr>
            <tr>
                <td class="profile-label">Phone:</td>
                <td class="profile-value">
                    <input type="text" name="user_phone_number" value="<?php echo htmlspecialchars($user_phone_number); ?>">
                </td>
            </tr>
        </table>
        <div class="profile-btn-row">
            <button type="submit" name="save_profile" class="button-uniform"><span>&#128190;</span>Save Profile</button>
            <button type="button" class="button-uniform secondary" onclick="openPwModal()"><span>&#128273;</span>Change Password</button>
        </div>
    </form>
</div>

<!-- Password Change Modal -->
<div id="passwordModal" role="dialog" aria-modal="true">
    <div class="modal-content">
        <button class="modal-close-btn" type="button" onclick="closePwModal()" title="Close">&times;</button>
        <!-- New Change Password Title -->
        <div class="pw-modal-title">Change Password</div>
        <form method="post" autocomplete="off" style="margin-bottom:0;" id="pw-change-form">
            <fieldset class="pw-change">
                <legend class="pw-legend">Change Password</legend>
                <div class="form-group">
                    <label class="pw-label" for="current_password">Current Password:</label>
                    <input type="password" id="current_password" name="current_password" class="pw-input" required>
                </div>
                <div class="form-group">
                    <label class="pw-label" for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" class="pw-input" required>
                </div>
                <div class="form-group">
                    <label class="pw-label" for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="pw-input" required>
                </div>
            </fieldset>
            <!-- Modal buttons outside border for Change/Cancel -->
            <div class="profile-btn-row profile-btn-row-modal">
                <button type="submit" name="change_password" class="button-uniform"><span>&#128274;</span>Change Password</button>
                <button type="button" class="button-uniform secondary" onclick="closePwModal()"><span>&#10006;</span>Cancel</button>
            </div>
        </form>
    </div>
</div>

</div>
</div>
</body>
</html>
