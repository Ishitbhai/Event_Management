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

<!-- <link rel="stylesheet" href="css/profile.css"> -->
<style>
    .button-uniform {
    display: inline-flex;
    align-items: center;
    gap: 0.5em;
    background: #1d2327 !important;
    color: #fff !important;
    border: none !important;
    border-radius: 8px;
    padding: 10px 22px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 2px 8px #1d232721;
    transition: background 0.16s, color 0.14s, box-shadow 0.14s, transform 0.13s;
    outline: none;
    min-width: 130px;
    max-width: 100%;
    overflow: hidden;
    white-space: nowrap;
    text-align: center;
}
.button-uniform:active, .button-uniform.secondary:active {
    box-shadow: 0 1px 4px #1d23274b;
    transform: scale(0.97);
}
.button-uniform.secondary {
    background: #1d2327 !important;
    color: #fff !important;
    /* no border */
}
.button-uniform:hover, .button-uniform:focus,
.button-uniform.secondary:hover, .button-uniform.secondary:focus {
    background: #23272b !important;
    color: #f4f4f4 !important;
    box-shadow: 0 4px 18px #1d232725;
}

.profile-btn-row {
    margin-top:32px;
    display: flex;
    flex-direction: row;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
}
.profile-btn-row.profile-btn-row-modal {
    margin-top: 0 !important;
}

.profile-main {
    background: #fff;
    border-radius: 18px;
    max-width: 530px;
    margin: 45px auto 0 auto;
    padding: 36px 20px 22px 20px;
    box-shadow: 0 5px 23px -7px #18849030;
}

.profile-avatar {
    width: 78px; height: 78px;
    border-radius: 50%;
    object-fit: cover;
    margin: 0 auto 18px auto;
    border: 3px solid #e8f0fa;
    background: #eee;
    font-size:2.8em;
    display:flex;
    align-items:center;
    justify-content:center;
    color:#197655;
    box-shadow:0 2px 12px #28b3e223;
}
.profile-info-table {
    width: 100%; font-size: 1.09em;
    margin-top:7px;
    table-layout: fixed;
}
.profile-info-table td {
    padding: 7px 10px 7px 0;
    border: none;
    vertical-align: middle;
}
.profile-label {
    color: #218a5a; font-weight: bold;
    width: 110px;
    min-width: 100px;
    max-width: 130px;
    word-break: break-word;
}
.profile-value input[readonly] {
    background: #ececec;
    color: #999;
    cursor: not-allowed;
}
.profile-value input {
    width: 100%; padding:7px 9px; border-radius:7px;
    border:1px solid #d5e0e7; background:#fafbfc;
    font-size:1em;
    box-sizing: border-box;
    min-width: 0;
}
#passwordModal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0; top: 0; width: 100vw; height: 100vh;
    background: rgba(28,42,49,0.22);
    align-items: center; justify-content: center;
}
#passwordModal.active {
    display: flex;
}

.modal-content {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 3px 30px #028bcd29;
    padding: 23px 13px 15px 13px;
    min-width: 280px;
    min-height: 100px;
    position: relative;
    animation: popfade .18s cubic-bezier(.68,-0.2,.57,1.4);
    box-sizing: border-box;
    max-width: 95vw;
    overflow-x: hidden;
}
@media (max-width: 550px) {
    .profile-main {
        max-width: 98vw;
        padding: 12vw 2vw 4vw 2vw;
    }
    .modal-content {
        min-width: unset;
        width: 99vw;
        border-radius: 0;
        padding: 18px 2vw 12px 2vw;
    }
    .profile-info-table td {
        padding: 7px 1vw 7px 0;
    }
}
@keyframes popfade {
    from { transform: scale(0.8); opacity:0; }
    to   { transform: scale(1); opacity:1; }
}
.modal-close-btn {
    position: absolute;
    top: 11px;
    right: 15px;
    border: none;
    background: transparent;
    font-size: 2em;
    color: #1d2327;
    cursor: pointer;
    outline: none;
    z-index: 9;
    padding: 2px 7px;
    border-radius: 9px;
    transition: background .14s;
}
.modal-close-btn:hover, .modal-close-btn:focus {
    background: #e6f8fb;
}

fieldset.pw-change {
    border-radius:13px; border:1.2px solid #e6edf0;
    padding: 18px 3px 11px 3px; background: #f7fcff;
    margin:0;
    min-width: 0;
    box-sizing: border-box;
    position: relative;
}
legend.pw-legend {
    font-size:1.15em;
    color:#1b6e70;
    font-weight:700;
    margin-bottom: 10px;
    letter-spacing: 0.2px;
    /* Hide original legend visually but retain accessibility */
    position: absolute;
    left: -9999px;
}
.pw-modal-title {
    text-align: center;
    font-size: 1.24em;
    color: #1b6e70;
    font-weight: 700;
    margin-bottom: 11px;
    margin-top: -13px;
    letter-spacing: 0.2px;
}
.pw-label { width:120px; display:inline-block; font-weight:600;color: #176760;}
.err-msg, .success-msg {
    background: #ffdede; color: #b2181e; padding: 12px 18px; border-radius:10px; margin-bottom:24px; text-align:center; font-size:1.01em;
}
.success-msg { background: #e7fadf; color: #227a39;}
.profile-title {
    text-align: center;
    margin-bottom: 13px;
    font-size: 1.8em;
    letter-spacing: 0.5px;
}
.form-group {
    margin-bottom: 17px;
}
.pw-input {
    width:100%;
    padding: 7px 9px;
    border-radius:7px;
    border:1px solid #d5e0e7;
    background:#fafbfc;
    font-size:1em;
    min-width:0;
    box-sizing: border-box;
}
body.modal-open {
    overflow: hidden;
}

/* Modal bottom button row outside border, aligned with modal, not inside fieldset */
.profile-btn-row.profile-btn-row-modal {
    margin-top: 12px !important;
    padding-bottom: 4px;
    justify-content: flex-end;
}

.field-err, .field-err-pw {
    color: #cc0000;
    font-size: 0.95em;
    font-weight: 500;
}
/* Modal Styles */
#passwordModal {
    position: fixed;
    z-index: 10000;
    left: 0; top: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.3);
    display: none;
    align-items: center;
    justify-content: center;
}
#passwordModal .modal-content {
    background: #fff;
    margin: 40px auto;
    border-radius: 7px;
    padding: 20px 35px 10px 35px;
    max-width: 400px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.15);
    position: relative;
}
#passwordModal .modal-close-btn {
    position: absolute; top: 15px; right: 15px; border: none; background: none; font-size: 1.6em; cursor: pointer;
}

</style>

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
                    <input type="text" name="user_name"  value="<?php echo htmlspecialchars($user_name); ?>">
                    <br><span class="field-err" id="err_user_name"><?php if(isset($field_errors) && $field_errors['user_name']) echo htmlspecialchars($field_errors['user_name']); ?></span>
                </td>
            </tr>
            <tr>
                <td class="profile-label">Email:</td>
                <td class="profile-value">
                    <input type="email" name="user_email"  value="<?php echo htmlspecialchars($user_email); ?>" readonly>
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
                    <input type="text" name="user_address" value="<?php echo htmlspecialchars($user_address); ?>" >
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
                    <input type="password" id="current_password" name="current_password" class="pw-input"  value="<?php echo isset($_POST['current_password']) ? htmlspecialchars($_POST['current_password']) : ''; ?>">
                    <br><span class="field-err-pw" id="err_current_password"><?php if(isset($pw_field_errors) && $pw_field_errors['current_password']) echo htmlspecialchars($pw_field_errors['current_password']); ?></span>
                </div>
                <div class="form-group">
                    <label class="pw-label" for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" class="pw-input"  value="">
                    <br><span class="field-err-pw" id="err_new_password"><?php if(isset($pw_field_errors) && $pw_field_errors['new_password']) echo htmlspecialchars($pw_field_errors['new_password']); ?></span>
                </div>
                <div class="form-group">
                    <label class="pw-label" for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="pw-input"  value="">
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
