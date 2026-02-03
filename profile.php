<?php
// Start session for login detection
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'database/db_connect.php';

// Function to sanitize output
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Get all user details except sensitive ones (password, token, etc.)
$user_id = $_SESSION['user_id'];

// Remove user_id, user_status from editable/displayed fields list (but we WANT to display user_type!)
$excluded_fields = ['user_id', 'user_status', 'type']; // allow user_type

// Add fields that should NOT be editable, even if shown
$readonly_fields = ['user_email', 'registered_at', 'last_login', 'user_type'];

// Build dynamic SQL for fetching all columns except password/token and excluded fields
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

// Handle form submission for updating profile
$update_msg = '';
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['profile_update'])) {
    $updates = [];
    $params = [];
    $param_types = '';
    foreach ($db_fields as $field) {
        // Don't allow update for read-only fields: registered_at, user_email, last_login, user_type
        if (in_array($field, $readonly_fields)) continue;
        $val = trim($_POST[$field] ?? '');

        // Very basic validation for name
        if ($field === 'user_name' && $val === '') {
            $update_msg = '<div style="color:#c00;">Name cannot be empty.</div>';
            break;
        }
        $updates[] = "`$field`=?";
        $params[] = $val;
        $param_types .= 's';
    }
    if ($update_msg === '') {
        if (!empty($updates)) {
            $sql_update = "UPDATE users SET " . implode(", ", $updates) . " WHERE user_id=?";
            $params[] = $user_id;
            $param_types .= 'i';
            $stmt = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt, $param_types, ...$params);
            if (mysqli_stmt_execute($stmt)) {
                $update_msg = '<div style="color:#218838;">Profile updated successfully.</div>';
                // Refresh
                // Re-fetch fresh user data
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
    }
}
?>

<?php include 'header.php'; ?>

<link rel="stylesheet" href="css/profile.css">

<style>
/* Ensure textarea doesn't go outside profile-container */

</style>

<div class="profile-container">
    <div class="profile-header">
        <h2><?php echo h($user['user_name']) ?: 'Your'; ?> Profile</h2>
        <!-- <div class="user-type-label" style="font-size:1em;color:#444;margin-top:6px;">
            User Type: <span style="font-weight:bold;"><?php echo isset($user['user_type']) ? h(ucfirst($user['user_type'])) : "N/A"; ?></span>
        </div> -->
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
                <?php elseif (strpos($field, 'address') !== false): ?>
                    <textarea name="<?php echo h($field); ?>"><?php echo h($user[$field]); ?></textarea>
                <?php else: ?>
                    <input type="text" name="<?php echo h($field); ?>" value="<?php echo h($user[$field]); ?>" />
                <?php endif; ?>
                </span>
            </div>
        <?php endforeach; ?>
        <div class="profile-actions">
            <button type="submit" name="profile_update">Update Profile</button>
        </div>
    </form>
    <div class="profile-links" style="text-align:center;margin-top:12px;">
        <a href="index.php">Home</a> |
        <a href="change_password.php">Change Password</a>
    </div>
</div>

<?php include 'footer.php'; ?>
