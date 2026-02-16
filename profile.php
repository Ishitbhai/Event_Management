<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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
}

$field_errors = [];
$update_msg = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['profile_update'])) {
    $updates = [];
    $params = [];
    $param_types = '';
    $has_error = false;
    foreach ($db_fields as $field) {
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
                // Use cleaned value for DB
                $val = $only_digits;
            }
        }
        $updates[] = "`$field`=?";
        $params[] = $val;
        $param_types .= 's';
    }
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

<link rel="stylesheet" href="css/profile.css">

<div class="profile-container">
    <div class="profile-header">
        <h2><?php echo h($user['user_name']) ?: 'Your'; ?> Profile</h2>
    </div>
    <?php if ($update_msg) echo $update_msg; ?>
    <form method="post" autocomplete="off">
        <?php foreach ($db_fields as $field): ?>
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
