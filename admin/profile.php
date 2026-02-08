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

// Fetch admin user data including address, user_type, registered_at, last_login
$query = $conn->prepare("SELECT user_name, user_email, user_phone_number, user_address, user_type, registered_at, last_login FROM users WHERE user_id=? LIMIT 1");
$query->bind_param("i", $admin_id);
$query->execute();
$query->bind_result($user_name, $user_email, $user_phone_number, $user_address, $user_type, $registered_at, $last_login);
$query->fetch();
$query->close();

// -- INITIALIZE to allow for show on first visit or failed post
$field_errors = [
    'user_name' => '',
    'user_phone_number' => '',
    'user_address' => ''
];
$pw_field_errors = [
    'current_password' => '',
    'new_password' => '',
    'confirm_password' => ''
];

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    // Edit fields: name/phone/address
    if (isset($_POST['save_profile'])) {
        $new_name = trim($_POST['user_name']);
        $new_phone = trim($_POST['user_phone_number']);
        $new_address = trim($_POST['user_address']);

        // Init error fields
        $field_errors = [
            'user_name' => '',
            'user_phone_number' => '',
            'user_address' => ''
        ];

        $has_error = false;
        if (!$new_name) {
            $field_errors['user_name'] = "Name cannot be empty.";
            $has_error = true;
        } elseif (!preg_match("/^[a-zA-Z\s]+$/", $new_name)) {
            $field_errors['user_name'] = "Name must only contain letters and spaces.";
            $has_error = true;
        }
        if (!$new_phone) {
            $field_errors['user_phone_number'] = "Phone number cannot be empty.";
            $has_error = true;
        } elseif (!preg_match("/^[0-9]{10}$/", $new_phone)) {
            $field_errors['user_phone_number'] = "Phone number must be exactly 10 digits.";
            $has_error = true;
        }
        if (!$new_address) {
            $field_errors['user_address'] = "Address cannot be empty.";
            $has_error = true;
        } elseif (mb_strlen($new_address) < 5) {
            $field_errors['user_address'] = "Address must be at least 5 characters long.";
            $has_error = true;
        }

        if (!$has_error) {
            $stmt = $conn->prepare("UPDATE users SET user_name=?, user_phone_number=?, user_address=? WHERE user_id=?");
            $stmt->bind_param("sssi", $new_name, $new_phone, $new_address, $admin_id);
            if ($stmt->execute()) {
                $success_msg = "Profile updated successfully.";
                $user_name = $new_name;
                $user_phone_number = $new_phone;
                $user_address = $new_address;
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

        // be sure to clear for each post
        $pw_field_errors = [
            'current_password' => '',
            'new_password' => '',
            'confirm_password' => ''
        ];
        $pw_has_error = false;

        if (!$current_pw) {
            $pw_field_errors['current_password'] = "Current password is required.";
            $pw_has_error = true;
        }
        if (!$new_pw) {
            $pw_field_errors['new_password'] = "New password is required.";
            $pw_has_error = true;
        }
        if (!$confirm_pw) {
            $pw_field_errors['confirm_password'] = "Password confirmation is required.";
            $pw_has_error = true;
        }
        if (!$pw_has_error) {
            if ($new_pw !== $confirm_pw) {
                $pw_field_errors['confirm_password'] = "New password and confirmation do not match.";
                $pw_has_error = true;
            } elseif (
                !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $new_pw)
            ) {
                $pw_field_errors['new_password'] = "Password must be minimum 8 characters, include upper and lower case letters, a digit and a special character.";
                $pw_has_error = true;
            }
        }

        if (!$pw_has_error) {
            // Verify current password
            $stmt = $conn->prepare("SELECT user_password FROM users WHERE user_id=?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $stmt->bind_result($hashed_pw);
            $stmt->fetch();
            $stmt->close();

            if (!password_verify($current_pw, $hashed_pw)) {
                $pw_field_errors['current_password'] = "Current password is incorrect.";
                $pw_has_error = true;
            } else {
                // Update new password
                $hashed_new_pw = password_hash($new_pw, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET user_password=? WHERE user_id=?");
                $stmt->bind_param("si", $hashed_new_pw, $admin_id);
                if ($stmt->execute()) {
                    $success_msg = "Password changed successfully.";
                } else {
                    $pw_field_errors['new_password'] = "Failed to change password.";
                    $pw_has_error = true;
                }
                $stmt->close();
            }
        }

        // If any error, ensure modal stays open so errors show
        if ($pw_has_error) {
            // This JS opens modal after form POST with errors
            echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(function(){
                    if (typeof openPwModal === 'function') openPwModal();
                    else $('#passwordModal').fadeIn(150);
                }, 120);
            });
            </script>";
        }
    }
}

$page_heading = "My Profile";
require('sidebar.php');
?>

<link rel="stylesheet" href="css/profile.css">

<!-- jQuery CDN for validation -->
<script src="js/jquery-4.0.0.min.js"></script>
<script>
$(document).ready(function(){
    // Profile form validation
    $('form#profile-edit-form').on('submit', function(e){
        var name = $.trim($('input[name="user_name"]').val());
        var phone = $.trim($('input[name="user_phone_number"]').val());
        var address = $.trim($('input[name="user_address"]').val());
        var valid = true;

        // Clear previous errors
        $('.field-err').text('');

        // Name
        if (name.length == 0) {
            $('#err_user_name').text("Name cannot be empty.");
            valid = false;
        } else if (!/^[a-zA-Z\s]+$/.test(name)) {
            $('#err_user_name').text("Name must only contain letters and spaces.");
            valid = false;
        }

        // Phone
        if (phone.length == 0) {
            $('#err_user_phone_number').text("Phone number cannot be empty.");
            valid = false;
        } else if (!/^[0-9]{10}$/.test(phone)) {
            $('#err_user_phone_number').text("Phone number must be exactly 10 digits.");
            valid = false;
        }

        // Address
        if (address.length == 0) {
            $('#err_user_address').text("Address cannot be empty.");
            valid = false;
        } else if (address.length < 5) {
            $('#err_user_address').text("Address must be at least 5 characters long.");
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
            return false;
        }
    });

    // Password modal validation
    $('form#pw-change-form').on('submit', function(e){
        var current = $.trim($('#current_password').val());
        var pw1 = $.trim($('#new_password').val());
        var pw2 = $.trim($('#confirm_password').val());
        var valid = true;

        // Clear previous errors
        $('.field-err-pw').text('');

        // Current Password
        if (!current) {
            $('#err_current_password').text("Current password is required.");
            valid = false;
        }

        // New Password
        if (!pw1) {
            $('#err_new_password').text("New password is required.");
            valid = false;
        } else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/.test(pw1)) {
            $('#err_new_password').text("Password must be minimum 8 characters, include upper and lower case letters, a digit and a special character.");
            valid = false;
        }

        // Confirm Password
        if (!pw2) {
            $('#err_confirm_password').text("Password confirmation is required.");
            valid = false;
        } else if (pw1 !== pw2) {
            $('#err_confirm_password').text("New password and confirmation do not match.");
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
            return false;
        }
    });

    // Move modal open/close functionality here and fix:
    window.openPwModal = function(){
        $("#passwordModal").fadeIn(150);
    }
    window.closePwModal = function(){
        $("#passwordModal").fadeOut(150);
    }

    // Also close modal if user clicks background outside modal-content:
    $('#passwordModal').on('click', function(e){
        if ($(e.target).is('#passwordModal')) {
            closePwModal();
        }
    });

    // Open modal if PHP triggers it (if any error in password form)
    // (This must match the PHP echo'd script if error occurred)
    if (window.location.hash === "#pwerror") {
        setTimeout(function(){
            if (typeof openPwModal === 'function') openPwModal();
            else $('#passwordModal').fadeIn(150);
        }, 100);
    }
});
</script>
<script src="js/profile.js"></script>

<div class="profile-main">
    <div class="profile-avatar">
        &#128100;
    </div>
    <h2 class="profile-title">
        Edit Profile
    </h2>
    <?php if (isset($success_msg) && $success_msg): ?>
        <div class="success-msg"><?php echo htmlspecialchars($success_msg); ?></div>
    <?php elseif (isset($error_msg) && $error_msg): ?>
        <div class="err-msg"><?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off" id="profile-edit-form">
        <table class="profile-info-table">
            <tr>
                <td class="profile-label">Name:</td>
                <td class="profile-value">
                    <input type="text" name="user_name" required value="<?php echo htmlspecialchars($user_name); ?>">
                    <br><span class="field-err" id="err_user_name"><?php if(isset($field_errors) && $field_errors['user_name']) echo htmlspecialchars($field_errors['user_name']); ?></span>
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
                    <br><span class="field-err" id="err_user_phone_number"><?php if(isset($field_errors) && $field_errors['user_phone_number']) echo htmlspecialchars($field_errors['user_phone_number']); ?></span>
                </td>
            </tr>
            <tr>
                <td class="profile-label">Address:</td>
                <td class="profile-value">
                    <input type="text" name="user_address" value="<?php echo htmlspecialchars($user_address); ?>" required>
                    <br><span class="field-err" id="err_user_address"><?php if(isset($field_errors) && $field_errors['user_address']) echo htmlspecialchars($field_errors['user_address']); ?></span>
                </td>
            </tr>
            <tr>
                <td class="profile-label">User Type:</td>
                <td class="profile-value">
                    <input type="text" name="user_type" value="<?php echo htmlspecialchars($user_type); ?>" readonly>
                </td>
            </tr>
            <tr>
                <td class="profile-label">Registered At:</td>
                <td class="profile-value">
                    <input type="text" name="registered_at" value="<?php echo htmlspecialchars($registered_at); ?>" readonly>
                </td>
            </tr>
            <tr>
                <td class="profile-label">Last Login:</td>
                <td class="profile-value">
                    <input type="text" name="last_login" value="<?php echo htmlspecialchars($last_login); ?>" readonly>
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
<div id="passwordModal" role="dialog" aria-modal="true" style="display:none;">
    <div class="modal-content">
        <button class="modal-close-btn" type="button" onclick="closePwModal()" title="Close">&times;</button>
        <!-- New Change Password Title -->
        <div class="pw-modal-title">Change Password</div>
        <form method="post" autocomplete="off" style="margin-bottom:0;" id="pw-change-form">
            <fieldset class="pw-change">
                <legend class="pw-legend">Change Password</legend>
                <div class="form-group">
                    <label class="pw-label" for="current_password">Current Password:</label>
                    <input type="password" id="current_password" name="current_password" class="pw-input" required value="<?php echo isset($_POST['current_password']) ? htmlspecialchars($_POST['current_password']) : ''; ?>">
                    <br><span class="field-err-pw" id="err_current_password"><?php if(isset($pw_field_errors) && $pw_field_errors['current_password']) echo htmlspecialchars($pw_field_errors['current_password']); ?></span>
                </div>
                <div class="form-group">
                    <label class="pw-label" for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" class="pw-input" required value="">
                    <br><span class="field-err-pw" id="err_new_password"><?php if(isset($pw_field_errors) && $pw_field_errors['new_password']) echo htmlspecialchars($pw_field_errors['new_password']); ?></span>
                </div>
                <div class="form-group">
                    <label class="pw-label" for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="pw-input" required value="">
                    <br><span class="field-err-pw" id="err_confirm_password"><?php if(isset($pw_field_errors) && $pw_field_errors['confirm_password']) echo htmlspecialchars($pw_field_errors['confirm_password']); ?></span>
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
