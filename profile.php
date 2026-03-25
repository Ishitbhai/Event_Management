<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    ?>
    <script>
        window.location.href="login.php";
    </script>
    <?php
    exit();
}

require_once 'database/db_connect.php';

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$user_id = $_SESSION['user_id'];

$excluded_fields = ['user_id', 'user_status', 'type'];
$readonly_fields = ['user_email', 'registered_at', 'last_login', 'user_type'];

$all_fields_sql = "SHOW COLUMNS FROM users";
$columns_res = mysqli_query($conn, $all_fields_sql);
$db_fields = [];
while ($col = mysqli_fetch_assoc($columns_res)) {
    $col_name = $col['Field'];
    if (
        !in_array($col_name, ['user_password', 'user_token']) &&
        !in_array($col_name, $excluded_fields)
    ) {
        $db_fields[] = $col_name;
    }
}
$fields_sql = implode(", ", array_map(function($f){return "`$f`";}, $db_fields));
$sql = "SELECT $fields_sql FROM users WHERE user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) === 1) {
    $user = mysqli_fetch_assoc($result);
} else {
    $user = array_fill_keys($db_fields, '');
    if (in_array('profile_picture', $db_fields)) {
        $user['profile_picture'] = 'user.png';
    }
}

$field_errors = [];
$update_msg = '';

// Handle file upload and POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['profile_update'])) {
    $updates = [];
    $params = [];
    $param_types = '';
    $has_error = false;

    // Handle other fields
    foreach ($db_fields as $field) {
        if ($field === 'profile_picture') continue; // Handled separately
        if (in_array($field, $readonly_fields)) continue;
        $val = trim($_POST[$field] ?? '');

        // Name validation: not empty, must be only letters and whitespace
        if ($field === 'user_name') {
            if ($val === '') {
                $field_errors[$field] = 'Name cannot be empty.';
                $has_error = true;
            } elseif (!preg_match('/^[a-zA-Z\s]+$/', $val)) {
                $field_errors[$field] = 'Name must contain only letters and spaces.';
                $has_error = true;
            }
        }

        // Address validation: not empty
        if (strpos($field, 'address') !== false) {
            if ($val === '') {
                $field_errors[$field] = 'Address cannot be empty.';
                $has_error = true;
            }
        }

        // Phone number validation: must be exactly 10 digits, only digits allowed
        if ($field === 'user_phone_number') {
            $only_digits = preg_replace('/\D/', '', $val);
            if (strlen($only_digits) !== 10) {
                $field_errors[$field] = 'Phone number must be exactly 10 digits.';
                $has_error = true;
            } else {
                $val = $only_digits;
            }
        }
        
        $updates[] = "`$field`=?";
        $params[] = $val;
        $param_types .= 's';
    }

    // Profile picture logic: only update if user selected a new image
    $target_dir = "images/";

    if (
        in_array('profile_picture', $db_fields) &&
        isset($_FILES['profile_picture']) &&
        is_uploaded_file($_FILES['profile_picture']['tmp_name']) &&
        $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK
    ) {
        // Get original extension, sanitize name for security (but keep unique)
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $acceptable_exts = ['jpg','jpeg','png','gif','webp','bmp'];

        if (in_array($ext, $acceptable_exts)) {
            $safe_filename = 'user_' . intval($user_id) . '_' . time() . '.' . $ext;
            $target_file = $target_dir . $safe_filename;
            // Save uploaded file
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $updates[] = "`profile_picture`=?";
                $params[] = $safe_filename;
                $param_types .= 's';
            } else {
                $field_errors['profile_picture'] = 'Failed to upload image. Please try again.';
                $has_error = true;
            }
        } else {
            $field_errors['profile_picture'] = 'Only image files (jpg, png, gif, webp, bmp) are allowed.';
            $has_error = true;
        }
    }
    // If no image is selected, do NOT update profile_picture field; leave as is.

    if (!$has_error) {
        if (!empty($updates)) {
            $sql_update = "UPDATE users SET " . implode(", ", $updates) . " WHERE user_id=?";
            $params[] = $user_id;
            $param_types .= 'i';
            $stmt = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt, $param_types, ...$params);
            if (mysqli_stmt_execute($stmt)) {
                $update_msg = '<div style="color:#218838;">Profile updated successfully.</div>';
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $user_id);
                mysqli_stmt_execute($stmt);
                $ref_result = mysqli_stmt_get_result($stmt);
                if ($ref_result && mysqli_num_rows($ref_result) === 1) {
                    $user = mysqli_fetch_assoc($ref_result);
                }
            } else {
                $update_msg = '<div style="color:#c00;">Failed to update profile. Please try again.</div>';
            }
        }
    } else {
        $update_msg = '<div style="color:#c00;">Please correct the highlighted errors below.</div>';
    }
}

?>

<?php include 'header.php'; ?>

<!-- <link rel="stylesheet" href="css/profile.css"> -->
<style>
    .profile-container, .profile-header, .profile-row, .profile-label, .profile-value, .profile-actions, .profile-links {
    box-sizing: border-box;
}

body {
    background: #eff2f7;
}

.profile-container {
    max-width: 620px;
    width: 99%;
    margin: 40px auto;
    background: linear-gradient(120deg, #f8fafc 0%, #f4f6fb 100%);
    border-radius: 18px;
    box-shadow: 0 8px 30px 0 rgba(68, 101, 145, 0.09), 0 1.5px 6px rgba(180,210,255,0.13);
    padding: 40px 38px 32px 38px;
    border: 1.5px solid #ddf1ff;
    transition: box-shadow 0.2s;
}
.profile-container:hover {
    box-shadow: 0 16px 48px 0 rgba(68, 101, 145, 0.13);
}
.profile-header {
    text-align: center;
    margin-bottom: 29px;
    position: relative;
}
.profile-header h2 {
    margin-bottom: 13px;
    color: #2262a7;
    font-weight: 700;
    font-family: 'Segoe UI', Arial, sans-serif;
    letter-spacing: 0.02em;
    font-size: 2.1em;
}

.profile-picture-circle {
    display: block;
    margin: 0 auto 18px auto;
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 4px solid #e6f0fa;
    background: #f7faff;
    object-fit: cover;
    box-shadow: 0 0 12px 0 rgba(82,154,188,0.13);
}

@media (max-width: 600px) {
    .profile-row {
        flex-direction: column;
        align-items: stretch;
    }
    .profile-label {
        width: 100% !important;
        margin-bottom: 0.3em;
        font-size: 1em;
    }
    .profile-header h2 {
        font-size: 1.45em;
    }
    .profile-picture-circle {
        width: 80px;
        height: 80px;
    }
    .profile-container {
        padding: 22px 4vw 20px 4vw;
        max-width: 100vw;
    }
}

.profile-label {
    font-weight: 600;
    color: #336293;
    width: 180px;
    flex-shrink: 0;
    font-size: 1.10em;
    text-shadow: 0 1px 0 #f3f8fe;
    letter-spacing: 0.01em;
    margin-top: 4px;
}

.profile-value {
    flex: 2;
    min-width: 0;
}

.profile-value input,
.profile-value textarea {
    width: 100%;
    min-width: 180px;
    max-width: 700px;
    padding: 10px 18px 10px 16px;
    font-size: 1.15em;
    margin: 0;
    border: 1.2px solid #d5e3f3;
    border-radius: 6px;
    background: #fafcff;
    color: #205283;
    box-shadow: 0 2px 8px 0 rgba(190, 207, 239, 0.04);
    transition: border 0.18s, box-shadow 0.2s;
}
.profile-value input:read-only,
.profile-value textarea:read-only {
    background: #f0f6fc;
    color: #97a6b8;
    border: 1.2px dashed #e0ebf5;
}
.profile-value input:focus,
.profile-value textarea:focus {
    border: 1.5px solid #51aaff;
    box-shadow: 0 0 0 2px #d8eafa;
    outline: none;
}

.profile-container .profile-row textarea {
    resize: vertical;
    font-family: inherit;
    font-size: 1.1em;
    border-radius: 6px;
    border: 1.2px solid #d5e3f3;
    background: #fafcff;
    padding: 10px 18px;
    min-height: 70px;
    min-width: 180px;
    max-width: 100%;
    margin-top: 0;
}
@media (max-width: 400px) {
    .profile-container .profile-row textarea {
        font-size: 1em;
        padding: 8px 10px;
    }
}

.profile-actions {
    margin-top: 23px;
    text-align: center;
}
.profile-actions button[type=submit] {
    background: linear-gradient(93deg, #25b771 6%, #4ad4d4 93%);
    color: #f7fafb !important;
    box-shadow: 0 2px 10px 0 rgba(67,187,130,0.10);
    padding: 8px 27px;
    border-radius: 7px;
    text-decoration: none;
    margin-left: 7px;
    margin-right: 7px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    font-size: 1.07em;
    letter-spacing: 0.02em;
    outline: none;
    transition: background 0.18s, box-shadow 0.16s;
}
.profile-actions button[type=submit]:hover,
.profile-actions button[type=submit]:active, 
.profile-actions button[type=submit]:focus {
    background: linear-gradient(93deg, #21985f 6%, #1dbdbd 93%);
    color: #fff;
    box-shadow: 0 3px 16px 0 rgba(42,179,175,0.13);
}

.profile-links {
    text-align:center;
    margin-top:20px;
    font-size: 1.06em;
    font-family: 'Segoe UI', Arial, sans-serif;
}
.profile-links a {
    color: #299bff;
    text-decoration: underline;
    margin: 0 13px;
    font-weight: 500;
    transition: color 0.15s;
    border-bottom: 1.5px solid transparent;
}
.profile-links a:hover, .profile-links a:focus {
    color: #176acb;
    border-bottom: 1.5px solid #299bff;
    text-decoration: none;
}

.profile-status {
    display: inline-block;
    padding: 2.5px 13px;
    border-radius: 15px;
    font-size: 1em;
    background: #eeeeef;
    color: #789;
    margin-left: 3px;
    letter-spacing: 0.02em;
}
.profile-status.active {
    background: #d3ffea;
    color: #219c46;
}
.profile-status.pending {
    background: #fffdc8;
    color: #947400;
}

.profile-container .profile-message,
.profile-container div[style*="color:#c00"],
.profile-container div[style*="color:#218838"] {
    margin-bottom: 14px;
    padding: 9px 16px;
    border-radius: 6px;
    font-size: 1.03em;
    font-weight: 500;
    background: #fff9f8;
    color: #b03636;
    border: 1px solid #ffe7e7;
    text-align: center;
}
.profile-container div[style*="color:#218838"] {
    background: #f2fff5;
    color: #168241;
    border: 1px solid #c9eccb;
}

@media (min-width: 901px) {
    .profile-container {
        max-width: 740px;
        padding: 44px 68px 36px 68px;
    }
    .profile-header h2 {
        font-size: 2.4em;
    }
    .profile-row {
        margin-bottom: 26px;
    }
    .profile-links {
        font-size: 1.15em;
    }
}

.profile-value input, .profile-value textarea {
    transition: border 0.15s, box-shadow 0.18s;
}

::-webkit-input-placeholder { color: #b7cadb; }
::-moz-placeholder { color: #b7cadb; }
:-ms-input-placeholder { color: #b7cadb; }
::placeholder { color: #b7cadb; }

</style>
<div class="profile-container">
    <div class="profile-header">
        <?php
        // Display profile picture (circular)
        $img_val = "user.png";
        if (isset($user['profile_picture']) && trim($user['profile_picture'])) {
            if (file_exists("images/" . $user['profile_picture'])) {
                $img_val = $user['profile_picture'];
            }
        }
        ?>
        <img class="profile-picture-circle" src="images/<?php echo h($img_val); ?>" alt="Profile Picture"
            onerror="this.onerror=null;this.src='images/user.png';">
        <h2><?php echo h($user['user_name']) ?: 'Your'; ?> Profile</h2>
    </div>
    <?php if ($update_msg) echo $update_msg; ?>
    <form method="post" autocomplete="off" enctype="multipart/form-data">
        <?php foreach ($db_fields as $field): ?>
            <?php if ($field === "profile_picture") continue; // Show separately below ?>
            <div class="profile-row">
                <?php
                    $label = ucwords(str_replace(['user_', '_'], ['', ' '], $field));
                    if ($field == 'registered_at') $label = "Account Created";
                    if ($field == 'last_login') $label = "Last Login";
                    if ($field == 'user_type') $label = "User Type";
                    $has_field_error = isset($field_errors[$field]);
                ?>
                <span class="profile-label"><?php echo $label; ?>:</span>
                <span class="profile-value">
                <?php if ($field == 'registered_at'): ?>
                    <input type="text" name="<?php echo h($field); ?>" value="<?php echo (!empty($user[$field]) ? date('M d, Y', strtotime($user[$field])) : '') ?>" readonly />
                <?php elseif ($field == 'user_email'): ?>
                    <input type="text" name="<?php echo h($field); ?>" value="<?php echo h($user[$field]); ?>" readonly />
                <?php elseif ($field == 'last_login'): ?>
                    <input type="text" name="<?php echo h($field); ?>" value="<?php echo (!empty($user[$field]) ? date('M d, Y H:i', strtotime($user[$field])) : '') ?>" readonly />
                <?php elseif ($field == 'user_type'): ?>
                    <input type="text" name="<?php echo h($field); ?>" value="<?php echo h(ucfirst($user[$field])); ?>" readonly />
                <?php elseif ($field === 'user_phone_number'): ?>
                    <input type="tel" name="<?php echo h($field); ?>" value="<?php echo h($user[$field]); ?>" />
                <?php elseif (strpos($field, 'address') !== false): ?>
                    <textarea name="<?php echo h($field); ?>"><?php echo h($user[$field]); ?></textarea>
                <?php elseif ($field === 'user_name'): ?>
                    <input type="text" name="<?php echo h($field); ?>" value="<?php echo h($user[$field]); ?>" />
                <?php else: ?>
                    <input type="text" name="<?php echo h($field); ?>" value="<?php echo h($user[$field]); ?>" />
                <?php endif; ?>
                <?php if ($has_field_error): ?>
                    <div style="color:#c00; font-size:13px;"><?php echo h($field_errors[$field]); ?></div>
                <?php endif; ?>
                </span>
            </div>
        <?php endforeach; ?>
        <?php if (in_array('profile_picture', $db_fields)): ?>
            <div class="profile-row">
                <span class="profile-label">Profile Picture:</span>
                <span class="profile-value">
                    <input style="padding:7px 10px;font-size:1em;" type="file" name="profile_picture" accept="image/*">
                    <br>
                    <small>(Select only if you want to change your profile picture.)
                    </small>
                    <?php if (isset($field_errors['profile_picture'])): ?>
                        <div style="color:#c00; font-size:13px;"><?php echo h($field_errors['profile_picture']); ?></div>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
        <div class="profile-actions">
            <button type="submit" name="profile_update">Update Profile</button>
        </div>
    </form>
    <div class="profile-links">
        <a href="index.php">Home</a> |
        <a href="change_password.php">Change Password</a>
    </div>
</div>

<?php include 'footer.php'; ?>
