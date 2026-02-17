<?php
session_start();
require_once('sidebar.php');
require_once('../database/db_connect.php');

// --- Initialize error messages ---
$error = '';
$success = '';
$field_errors = [
    'user_name' => '',
    'user_email' => '',
    'user_phone_number' => '',
    'user_address' => '',
    'user_password' => '',
    'confirm_user_password' => '',
    'registered_at' => '',
    'last_login' => '',
];

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Improved phone validation: allow any 10 digits except all 0/1, repeating >6, incrementing runs, etc
function is_invalid_phone($phone) {
    $phone = preg_replace('/\D/', '', $phone);

    // Must be exactly 10 digits numeric
    if (strlen($phone) !== 10) return true;

    // Must not be all zeros or ones, or start with 0 or 1 (commonly for mobile, adjust as per locale)
    if (preg_match('/^(0{10}|1{10})$/', $phone)) return true;

    // Must not have one digit repeated 6 or more times (e.g. 1111110000)
    if (preg_match('/(\d)\1{5,}/', $phone)) return true;

    // Must not contain common incrementing patterns (to block fake/test numbers)
    if (preg_match('/012345|123456|234567|345678|456789/', $phone)) return true;

    return false;
}

// --- Password strength validator for server-side (one uppercase, one lowercase, one digit, one special char, min 8 chars) ---
function is_invalid_password($password) {
    if (strlen($password) < 8)
        return true;
    if (!preg_match('/[A-Z]/', $password))
        return true;
    if (!preg_match('/[a-z]/', $password))
        return true;
    if (!preg_match('/[0-9]/', $password))
        return true;
    if (!preg_match('/[\W_]/', $password)) // special char (including underscore)
        return true;
    return false;
}

// Validate timestamp, must be in "YYYY-MM-DDTHH:MM" (HTML5-local-datetime format)
function is_invalid_timestamp($dtstr) {
    return !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dtstr);
}

function datetime_local_to_mysql($dtstr) {
    if (empty($dtstr)) return null;
    // Convert from 'YYYY-MM-DDTHH:MM' to 'YYYY-MM-DD HH:MM:SS'
    return date('Y-m-d H:i:s', strtotime($dtstr));
}

// Fetch user_id from GET
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
if ($user_id <= 0) {
    $error = "Invalid User ID.";
} else {
    // Fetch user data from DB
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();
    } else {
        $user = null;
        $error = "User not found.";
    }
    $stmt->close();
}

$orig_values = [
    'user_name' => $user['user_name'] ?? '',
    'user_email' => $user['user_email'] ?? '',
    'user_phone_number' => $user['user_phone_number'] ?? '',
    'user_address' => $user['user_address'] ?? '',
    'user_status' => $user['user_status'] ?? 'active',
    'user_type' => $user['user_type'] ?? 'user',
    'registered_at' => !empty($user['registered_at']) ? substr($user['registered_at'],0,16) : '',
    'last_login' => !empty($user['last_login']) ? substr($user['last_login'],0,16) : '',
];

// On submit, validate fields and update user in DB
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id > 0 && $user) {
    $user_name = trim($_POST['user_name'] ?? '');
    $user_email = trim($_POST['user_email'] ?? '');
    $user_phone_number = trim($_POST['user_phone_number'] ?? '');
    $user_address = trim($_POST['user_address'] ?? '');
    $user_status = $_POST['user_status'] ?? 'active';
    $user_type = $_POST['user_type'] ?? 'user';
    $user_password = $_POST['user_password'] ?? '';
    $confirm_user_password = $_POST['confirm_user_password'] ?? '';
    $registered_at = trim($_POST['registered_at'] ?? '');
    $last_login = trim($_POST['last_login'] ?? '');

    // Retain/edit values
    $orig_values = [
        'user_name' => $user_name,
        'user_email' => $user_email,
        'user_phone_number' => $user_phone_number,
        'user_address' => $user_address,
        'user_status' => $user_status,
        'user_type' => $user_type,
        'registered_at' => $registered_at,
        'last_login' => $last_login,
    ];

    // Field validations
    if (empty($user_name)) {
        $field_errors['user_name'] = 'Full Name is required.';
    } elseif (mb_strlen($user_name) < 2) {
        $field_errors['user_name'] = 'Full Name must be at least 2 characters.';
    }
    if (empty($user_email)) {
        $field_errors['user_email'] = 'Email Address is required.';
    } elseif (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $field_errors['user_email'] = 'Please enter a valid Email Address.';
    } else {
        // Check if email already exists for other users
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_email=? AND user_id<>?");
        $stmt->bind_param("si", $user_email, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $field_errors['user_email'] = 'Email already exists.';
        }
        $stmt->close();
    }
    if (empty($user_phone_number)) {
        $field_errors['user_phone_number'] = 'Phone number is required.';
    } else {
        // Accept dashes/spaces but must be 10 digits after stripping
        $digits_only = preg_replace('/\D/', '', $user_phone_number);
        if (is_invalid_phone($digits_only)) {
            $field_errors['user_phone_number'] = 'Please enter a valid 10-digit phone number.';
        }
    }

    // Address optional but sanitize
    $user_address = trim($user_address);

    // --- Validate registered_at & last_login ---
    $now = date('Y-m-d\TH:i'); // Current in local form
    if (empty($registered_at)) {
        $field_errors['registered_at'] = 'Registered At is required.';
    } elseif (is_invalid_timestamp($registered_at)) {
        $field_errors['registered_at'] = 'Registered At value is invalid.';
    } elseif ($registered_at > $now) {
        $field_errors['registered_at'] = 'Registered At cannot be in the future.';
    }
    if (empty($last_login)) {
        $field_errors['last_login'] = 'Last Login is required.';
    } elseif (is_invalid_timestamp($last_login)) {
        $field_errors['last_login'] = 'Last Login value is invalid.';
    }
    // Only check this validation if both are not already invalid
    if (empty($field_errors['registered_at']) && empty($field_errors['last_login'])) {
        if ($last_login < $registered_at) {
            $field_errors['last_login'] = 'Last Login cannot be before Registered At.';
        }
    }

    // Password logic
    $update_password = false;
    if (!empty($user_password) || !empty($confirm_user_password)) {
        if (empty($user_password)) {
            $field_errors['user_password'] = "Password is required if changing.";
        } elseif (is_invalid_password($user_password)) {
            $field_errors['user_password'] = "Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a digit, and a special character.";
        }
        if (empty($confirm_user_password)) {
            $field_errors['confirm_user_password'] = "Please confirm password.";
        }
        if ($user_password !== $confirm_user_password) {
            $field_errors['confirm_user_password'] = "Passwords do not match.";
        }
        if (empty($field_errors['user_password']) && empty($field_errors['confirm_user_password']))
            $update_password = true;
    }

    // Status/type (not validated further, enums)
    if (!in_array($user_status, ['active','inactive'])) {
        $user_status = 'active';
    }
    if (!in_array($user_type, ['admin','user'])) {
        $user_type = 'user';
    }

    // Check for errors
    $has_error = false;
    foreach($field_errors as $fe)
        if (!empty($fe)) { $has_error = true; break; }

    if (!$has_error) {
        // Update query
        $upd_registered_at = datetime_local_to_mysql($registered_at);
        $upd_last_login = datetime_local_to_mysql($last_login);
        if ($update_password) {
            $password_hash = password_hash($user_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET user_name=?, user_email=?, user_phone_number=?, user_address=?, user_status=?, user_type=?, user_password=?, registered_at=?, last_login=? WHERE user_id=?");
            $stmt->bind_param("sssssssssi", $user_name, $user_email, $user_phone_number, $user_address, $user_status, $user_type, $password_hash, $upd_registered_at, $upd_last_login, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET user_name=?, user_email=?, user_phone_number=?, user_address=?, user_status=?, user_type=?, registered_at=?, last_login=? WHERE user_id=?");
            $stmt->bind_param("ssssssssi", $user_name, $user_email, $user_phone_number, $user_address, $user_status, $user_type, $upd_registered_at, $upd_last_login, $user_id);
        }
        if ($stmt->execute()) {
            $success = "User updated successfully.";
            // Refetch updated user data
            $stmt->close();
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $user = $res->fetch_assoc();
            $orig_values = [
                'user_name' => $user['user_name'] ?? '',
                'user_email' => $user['user_email'] ?? '',
                'user_phone_number' => $user['user_phone_number'] ?? '',
                'user_address' => $user['user_address'] ?? '',
                'user_status' => $user['user_status'] ?? 'active',
                'user_type' => $user['user_type'] ?? 'user',
                'registered_at' => !empty($user['registered_at']) ? substr($user['registered_at'],0,16) : '',
                'last_login' => !empty($user['last_login']) ? substr($user['last_login'],0,16) : '',
            ];
            $field_errors = [
                'user_name' => '',
                'user_email' => '',
                'user_phone_number' => '',
                'user_address' => '',
                'user_password' => '',
                'confirm_user_password' => '',
                'registered_at' => '',
                'last_login' => '',
            ];
        } else {
            $error = "Failed to update user. Try again.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/users.css">
    <link rel="stylesheet" href="css/user_edit.css">
    <script src="js/jquery-4.0.0.min.js"></script>

</head>
<body>
<div class="dashboard-main" style="max-width:510px; margin:40px auto 0 auto;">
    <?php if ($error): ?>
        <div class="userform-fail"><?php echo esc($error); ?></div>
    <?php elseif ($success): ?>
        <div class="userform-success"><?php echo esc($success); ?></div>
    <?php endif; ?>
    <?php if (!$user): ?>
        <a href="users.php" class="create-event-btn" style="text-decoration:none;">&larr; Back to Users</a>
    <?php else: ?>
    <form method="POST" autocomplete="off" class="userform-form" id="edituserform" novalidate>
        <div class="userform-heading">Edit User</div>
        <div style="margin-bottom:17px;">
            <label for="user_name" class="userform-label">Full Name</label>
            <input type="text" name="user_name" id="user_name" class="userform-input" value="<?php echo esc($orig_values['user_name']); ?>" required>
            <div class="userform-error" id="user_name_err"><?php echo esc($field_errors['user_name']); ?></div>
        </div>
        <div style="margin-bottom:17px;">
            <label for="user_email" class="userform-label">Email Address</label>
            <input type="text" name="user_email" id="user_email" class="userform-input" value="<?php echo esc($orig_values['user_email']); ?>" required>
            <div class="userform-error" id="user_email_err"><?php echo esc($field_errors['user_email']); ?></div>
        </div>
        <div style="margin-bottom:17px;">
            <label for="user_phone_number" class="userform-label">Phone Number</label>
            <input type="text" name="user_phone_number" id="user_phone_number" class="userform-input" maxlength="10" value="<?php echo esc($orig_values['user_phone_number']); ?>" required>
            <div class="userform-error" id="user_phone_number_err"><?php echo esc($field_errors['user_phone_number']); ?></div>
        </div>
        <div style="margin-bottom:17px;">
            <label for="user_address" class="userform-label">Address</label>
            <input type="text" name="user_address" id="user_address" class="userform-input" value="<?php echo esc($orig_values['user_address']); ?>">
            <div class="userform-error" id="user_address_err"><?php echo esc($field_errors['user_address']); ?></div>
        </div>
        <div style="margin-bottom:17px;">
            <label for="user_status" class="userform-label">User Status</label>
            <select name="user_status" id="user_status" class="userform-select">
                <option value="active" <?php if($orig_values['user_status']=='active') echo 'selected'; ?>>Active</option>
                <option value="inactive" <?php if($orig_values['user_status']=='inactive') echo 'selected'; ?>>Inactive</option>
            </select>
        </div>
        <div style="margin-bottom:17px;">
            <label for="user_type" class="userform-label">User Type</label>
            <select name="user_type" id="user_type" class="userform-select">
                <option value="user" <?php if($orig_values['user_type']=='user') echo 'selected'; ?>>User</option>
                <option value="admin" <?php if($orig_values['user_type']=='admin') echo 'selected'; ?>>Admin</option>
            </select>
        </div>
        <div style="margin-bottom:17px;">
            <label for="registered_at" class="userform-label">Registered At</label>
            <input type="datetime-local" name="registered_at" id="registered_at" class="userform-input" value="<?php echo esc($orig_values['registered_at']); ?>" max="<?php echo date('Y-m-d\TH:i'); ?>">
            <div class="userform-error" id="registered_at_err"><?php echo esc($field_errors['registered_at']); ?></div>
        </div>
        <div style="margin-bottom:17px;">
            <label for="last_login" class="userform-label">Last Login</label>
            <input type="datetime-local" name="last_login" id="last_login" class="userform-input" value="<?php echo esc($orig_values['last_login']); ?>">
            <div class="userform-error" id="last_login_err"><?php echo esc($field_errors['last_login']); ?></div>
        </div>
        <div style="margin-bottom:17px;">
            <label for="user_password" class="userform-label">New Password <span style="font-weight:400;color:#b3b3b3;">(Leave blank to keep current)</span></label>
            <input type="password" name="user_password" id="user_password" class="userform-input" value="">
            <div class="userform-error" id="user_password_err"><?php echo esc($field_errors['user_password']); ?></div>
        </div>
        <div style="margin-bottom:24px;">
            <label for="confirm_user_password" class="userform-label">Confirm New Password</label>
            <input type="password" name="confirm_user_password" id="confirm_user_password" class="userform-input" value="">
            <div class="userform-error" id="confirm_user_password_err"><?php echo esc($field_errors['confirm_user_password']); ?></div>
        </div>
        <button type="submit" class="userform-btn" id="submitBtn">Update User</button>
    </form>
    <div style="margin-top:23px; text-align:center;">
        <a href="users.php" class="edit-btn" style="text-decoration:none;padding:9px 26px;">&larr; Back to Users</a>
    </div>
    <?php endif; ?>
</div>

<script>
// Client-side phone validation matches server
function is_invalid_email(email) {
    return !/^([a-zA-Z0-9_\.-]+)@([a-zA-Z0-9-]+\.)+([a-zA-Z0-9]{2,})$/.test(email);
}
function is_invalid_phone(phone) {
    var cleaned = phone.replace(/\D/g, '');
    if (cleaned.length !== 10) return true;
    if (/^(0{10}|1{10})$/.test(cleaned)) return true;
    if (/(\d)\1{5,}/.test(cleaned)) return true;
    if (/012345|123456|234567|345678|456789/.test(cleaned)) return true;
    return false;
}

// Password validation: 1 uppercase, 1 lowercase, 1 digit, 1 special char, min 8 chars
function is_invalid_password(password) {
    if (password.length < 8) return true;
    if (!/[A-Z]/.test(password)) return true;
    if (!/[a-z]/.test(password)) return true;
    if (!/[0-9]/.test(password)) return true;
    if (!/[\W_]/.test(password)) return true;
    return false;
}

// Timestamp validation in JS, matches server
function is_invalid_datetime_local(val) {
    if (!val) return true;
    // Accept YYYY-MM-DDTHH:MM
    return !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(val);
}

function validate_field(field) {
    let v = field.val();
    let errId = "#" + field.attr('id') + "_err";
    let id = field.attr('id');
    let error = '';

    if (id === "user_name") {
        if (!v.trim()) error = "Full Name is required.";
        else if (v.length < 2) error = "Full Name must be at least 2 characters.";
    }
    else if (id === "user_email") {
        if (!v.trim()) error = "Email Address is required.";
        else if (is_invalid_email(v.trim())) error = "Please enter a valid Email Address.";
    }
    else if (id === "user_phone_number") {
        if (!v.trim()) error = "Phone number is required.";
        else if (is_invalid_phone(v)) error = "Please enter a valid 10-digit phone number.";
    }
    else if (id === "registered_at") {
        if (!v) error = "Registered At is required.";
        else if (is_invalid_datetime_local(v)) error = "Registered At value is invalid.";
        else if (v > new Date().toISOString().substring(0,16)) error = "Registered At cannot be in the future.";
    }
    else if (id === "last_login") {
        if (!v) error = "Last Login is required.";
        else if (is_invalid_datetime_local(v)) error = "Last Login value is invalid.";
        else {
            let reg = $('#registered_at').val();
            if (reg && !is_invalid_datetime_local(reg) && v < reg)
                error = "Last Login cannot be before Registered At.";
        }
    }
    else if (id === "user_password") {
        let other = $('#confirm_user_password').val();
        if (v || other) {
            if (!v) error = "Password is required if changing.";
            else if (is_invalid_password(v))
                error = "Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a digit, and a special character.";
        }
    }
    else if (id === "confirm_user_password") {
        let pass = $('#user_password').val();
        if (pass || v) {
            if (!v) error = "Please confirm password.";
            else if (pass !== v) error = "Passwords do not match.";
        }
    }

    $(errId).text(error);
    return error === '';
}

function validate_all() {
    let ok = true;
    $('#user_name, #user_email, #user_phone_number, #registered_at, #last_login, #user_password, #confirm_user_password').each(function() {
        if (!validate_field($(this))) ok = false;
    });
    return ok;
}

$(function(){
    $('#user_name, #user_email, #user_phone_number, #registered_at, #last_login, #user_password, #confirm_user_password').on('change input blur', function() {
        validate_field($(this));
    });

    $('#edituserform').on('submit', function(e) {
        $('.userform-error').text('');
        let valid = true;

        valid = validate_all();

        if (!valid) {
            e.preventDefault();
            let $firstError = $('.userform-error').filter(function(){ return $(this).text() !== ""; }).first();
            if ($firstError.length) {
                $('html,body').animate({scrollTop: $firstError.offset().top-70}, 200);
            }
            return false;
        }
    });
});
</script>
</body>
</html>
