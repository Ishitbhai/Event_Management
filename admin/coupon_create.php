
<?php
require_once("../database/db_connect.php");
session_start();
require('sidebar.php');

// Only admins allowed
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}

// --- Helpers ---
function getEventOptions($conn, $selected = null) {
    $out = "";
    $result = $conn->query("SELECT event_id, event_title FROM events ORDER BY event_title ASC");
    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {
            $sel = ($selected !== null && $selected == $r['event_id']) ? "selected" : "";
            $label = htmlspecialchars($r['event_title']) . " (ID: " . (int)$r['event_id'] . ")";
            $out .= '<option value="'.(int)$r['event_id'].'" '.$sel.'>'.$label.'</option>';
        }
    }
    return $out ?: "<option disabled>No completed events found</option>";
}

function getUserOptions($conn, $selected = null) {
    $out = "";
    $result = $conn->query("SELECT user_id, user_name, user_email FROM users ORDER BY user_name ASC");
    if ($result && $result->num_rows > 0) {
        while ($r = $result->fetch_assoc()) {
            $sel = ($selected !== null && $selected == $r['user_id']) ? "selected" : "";
            $label = htmlspecialchars($r['user_name']) . " (" . htmlspecialchars($r['user_email']) . ")";
            $out .= '<option value="'.(int)$r['user_id'].'" '.$sel.'>'.$label.'</option>';
        }
    }
    return $out ?: "<option disabled>No users found</option>";
}

// Value repopulation for form
function old($key) {
    return htmlspecialchars($_POST[$key] ?? '', ENT_QUOTES, 'UTF-8');
}
function oldSelected($key, $val) {
    return (isset($_POST[$key]) && $_POST[$key] == $val) ? "selected" : "";
}

$errors = [];
$success = false;

// Only check for duplicate coupon code and save coupon, move all other validation to JS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['coupon_code'] ?? '');
    $from_event = trim($_POST['coupon_from_event_id'] ?? '');
    $applied_to_event = trim($_POST['coupon_applied_to_event_id'] ?? '');
    $user = trim($_POST['coupon_user_id'] ?? '');
    $discount = trim($_POST['coupon_discount'] ?? '');
    $valid_till_input = trim($_POST['coupon_valid_till'] ?? '');

    // Only check required PHP-side: Is coupon code unique
    if ($code !== '') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM coupons WHERE coupon_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $stmt->bind_result($cnt);
        $stmt->fetch();
        $stmt->close();
        if ($cnt > 0) $errors[] = "Coupon code already exists.";
    }

    // Server-side validation for coupon_valid_till: required and must be in future
    if ($valid_till_input === '') {
        $errors[] = "Valid till is required.";
    } else {
        // try to parse
        $t = strtotime($valid_till_input);
        if ($t === false) {
            $errors[] = "Invalid valid till date format.";
        } else {
            if ($t <= time()) {
                $errors[] = "Valid till date/time must be in the future.";
            }
        }
    }

    if (empty($errors)) {
        $from_event = ($from_event === '' || $from_event === 'none') ? null : (int)$from_event;
        $applied_to_event = ($applied_to_event === '' || $applied_to_event === 'none') ? null : (int)$applied_to_event;
        $user = ($user === '' || $user === 'none') ? null : (int)$user;
        $discount = (int)$discount;

        // Valid till is timestamp (already validated)
        $valid_till = strtotime($valid_till_input);

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO coupons
                (coupon_code, coupon_from_event_id, coupon_applied_to_event_id, coupon_user_id, coupon_discount, coupon_valid_till, coupon_is_used, coupon_created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
            $stmt->bind_param(
                "siiisi",
                $code,
                $from_event,
                $applied_to_event,
                $user,
                $discount,
                $valid_till
            );
            // NULL bind workaround (for nullable columns)
            if ($from_event === null) $stmt->bind_param("siiisi", $code, $null = null, $applied_to_event, $user, $discount, $valid_till);
            if ($applied_to_event === null) $stmt->bind_param("siiisi", $code, $from_event, $null = null, $user, $discount, $valid_till);
            if ($user === null) $stmt->bind_param("siiisi", $code, $from_event, $applied_to_event, $null = null, $discount, $valid_till);

            // $stmt->execute();
            // if ($stmt->affected_rows === 1) {
                // $success = true;
            // } else {
                // $errors[] = "Failed to create coupon. Please try again.";
            // }
            // $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Coupon</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="js/jquery-4.0.0.min.js"></script>
<!-- <link rel="stylesheet" href="css/coupen_create.css"> -->
<style>
    body {margin:0; background: #f4f6fb; overflow-x:hidden;}
.dashboard-main {padding: 40px;}
.internal-header {margin: 0 0 18px 0; color: #322053;}
.coupon-form-wrap {
    max-width: 500px;
    margin: 40px auto;
    background: #fff; 
    border-radius: 12px;
    box-shadow: 0 1px 10px rgba(44,62,80,0.10);
    padding: 30px 32px 22px 32px;
}
.form-group {margin-bottom: 22px;}
.form-group label {display:block; font-weight:600; margin-bottom:5px; color:#594285;}
.form-group input[type=text],
.form-group input[type=number],
.form-group input[type=date],
.form-group input[type=datetime-local],
.form-group select {
    width:100%; border-radius:6px; border:1px solid #ddd; padding: 8px 10px; font-size:15px;
    box-sizing: border-box; background: #fafbff;
}
.form-group input[type=text]:focus,
.form-group input[type=number]:focus,
.form-group input[type=date]:focus,
.form-group input[type=datetime-local]:focus,
.form-group select:focus {
    outline: none;
    border-color: #7090f5;
    background: #fff;
}
.form-actions {
    margin-top: 24px;
    display: flex;
    gap: 18px;
    justify-content: flex-end;
}
.btn {
    background: linear-gradient(90deg, #2d397a, #594285 90%);
    color: #fff;
    padding: 8px 24px;
    border: none;
    border-radius: 7px; font-size: 15px; font-weight: 700;
    cursor: pointer;
    transition: background .16s;
}
.btn:hover {background: linear-gradient(90deg, #594285, #2d397a 100%);}
.error-msg {background: #fde8e4; color: #a5092c; border-radius: 4px; padding:9px 14px;margin-bottom:14px;}
.success-msg {background: #dbfadd;color: #18793a; border-radius: 4px; padding:9px 14px;margin-bottom:14px;}
.form-error {
    color: #a5092c;
    font-size: 14px;
    padding: 6px 0 0 2px;
    margin: 0;
    min-height: 0;
    min-height: 0;
    display: block;
    height: auto;
}
.form-error:empty {
    padding: 0;
    min-height: 0;
    height: 0;
    margin: 0;
}

</style>
</head>
<body>
<div class="dashboard-main">
    <h2 class="internal-header">Create Coupon</h2>
    <div class="coupon-form-wrap">

        <?php if (!empty($errors)): ?>
            <div class="error-msg">
                <?= implode('<br>', $errors) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-msg">
                Coupon created successfully.<br>
                <a href="coupons.php" style="color:#225085;text-decoration:underline;">Go back to coupons list</a>
            </div>
        <?php else: ?>
        <form method="post" id="couponForm" autocomplete="off" novalidate>
            <div class="form-group">
                <label for="coupon_code">Coupon Code <span style="color:red">*</span></label>
                <input type="text" name="coupon_code" id="coupon_code" value="<?= old('coupon_code') ?>" maxlength="64" />
                <span class="form-error" id="err_coupon_code"></span>
            </div>

            <div class="form-group">
                <label for="coupon_from_event_id">From Event <span style="color:red">*</span></label>
                <select name="coupon_from_event_id" id="coupon_from_event_id">
                    <option value="">-- Select Event --</option>
                    <?= getEventOptions($conn, $_POST['coupon_from_event_id'] ?? null); ?>
                </select>
                <span class="form-error" id="err_coupon_from_event_id"></span>
            </div>

            <div class="form-group">
                <label for="coupon_applied_to_event_id">Applied To Event (Optional)</label>
                <select name="coupon_applied_to_event_id" id="coupon_applied_to_event_id">
                    <option value="none">-- None --</option>
                    <?= getEventOptions($conn, $_POST['coupon_applied_to_event_id'] ?? null); ?>
                </select>
                <span class="form-error" id="err_coupon_applied_to_event_id"></span>
            </div>

            <div class="form-group">
                <label for="coupon_user_id">User <span style="color:red">*</span></label>
                <select name="coupon_user_id" id="coupon_user_id">
                    <option value="">-- Select User --</option>
                    <?= getUserOptions($conn, $_POST['coupon_user_id'] ?? null); ?>
                </select>
                <span class="form-error" id="err_coupon_user_id"></span>
            </div>

            <div class="form-group">
                <label for="coupon_discount">Discount (%) <span style="color:red">*</span></label>
                <input type="number" name="coupon_discount" id="coupon_discount" value="<?= old('coupon_discount') ?>" min="1" max="100" />
                <span class="form-error" id="err_coupon_discount"></span>
            </div>
            <div class="form-group">
                <label for="coupon_valid_till">Valid Till <span style="color:red">*</span></label>
                <?php
                $validTillVal = '';
                if (isset($_POST['coupon_valid_till']) && $_POST['coupon_valid_till'] !== '') {
                    $try_val = $_POST['coupon_valid_till'];
                    if (is_numeric($try_val)) {
                        $validTillVal = date('Y-m-d\TH:i', $try_val);
                    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $try_val)) {
                        $t = strtotime($try_val);
                        if ($t !== false) $validTillVal = date('Y-m-d\TH:i', $t);
                    } else {
                        $validTillVal = htmlspecialchars($try_val, ENT_QUOTES, 'UTF-8');
                    }
                }
                ?>
                <input type="datetime-local" name="coupon_valid_till" id="coupon_valid_till" value="<?= $validTillVal ?>" required />
                <span class="form-error" id="err_coupon_valid_till"></span>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Create Coupon</button>
                <a class="btn" href="coupons.php" style="background: #aaa; color:#222;">Cancel</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<script>
$(document).ready(function() {
    function validateCode() {
        var val = $("#coupon_code").val().trim();
        if (val === "") {
            $("#err_coupon_code").text("Coupon code is required.");
            return false;
        }
        if (val.length > 64) {
            $("#err_coupon_code").text("Coupon code must be at most 64 characters.");
            return false;
        }
        $("#err_coupon_code").text('');
        return true;
    }
    function validateFromEvent() {
        var val = $("#coupon_from_event_id").val();
        if (val === "" || val === null) {
            $("#err_coupon_from_event_id").text("From Event is required.");
            return false;
        }
        $("#err_coupon_from_event_id").text('');
        return true;
    }
    function validateUser() {
        var val = $("#coupon_user_id").val();
        if (val === "" || val === null) {
            $("#err_coupon_user_id").text("User is required.");
            return false;
        }
        $("#err_coupon_user_id").text('');
        return true;
    }
    function validateDiscount() {
        var val = $("#coupon_discount").val().trim();
        if (val === "") {
            $("#err_coupon_discount").text("Discount is required.");
            return false;
        }
        if (!/^\d+$/.test(val) || parseInt(val) < 1 || parseInt(val) > 100) {
            $("#err_coupon_discount").text("Discount must be between 1 and 100 (%).");
            return false;
        }
        $("#err_coupon_discount").text('');
        return true;
    }

    // Validate coupon_valid_till required and must be in the future
    function validateValidTill() {
        var val = $("#coupon_valid_till").val();
        if (val === "" || val === null) {
            $("#err_coupon_valid_till").text("Valid till is required.");
            return false;
        }
        var pickedDate = new Date(val);
        var now = new Date();
        // make the input behave like PHP time() -- must be > now
        if (isNaN(pickedDate.getTime())) {
            $("#err_coupon_valid_till").text("Invalid date/time format.");
            return false;
        }
        if (pickedDate <= now) {
            $("#err_coupon_valid_till").text("Valid till date/time must be in the future.");
            return false;
        }
        $("#err_coupon_valid_till").text('');
        return true;
    }

    // Optional field: no validation for applied_to_event. But always clear error on change.
    function clearAppliedToEventError() {
        $("#err_coupon_applied_to_event_id").text('');
        return true;
    }

    $("#coupon_code").on("input change blur", validateCode);
    $("#coupon_from_event_id").on("change blur", validateFromEvent);
    $("#coupon_user_id").on("change blur", validateUser);
    $("#coupon_discount").on("input change blur", validateDiscount);
    $("#coupon_applied_to_event_id").on("change blur", clearAppliedToEventError);
    $("#coupon_valid_till").on("input change blur", validateValidTill);

    $("#couponForm").on("submit", function(e) {
        var valid = true;
        if (!validateCode()) valid = false;
        if (!validateFromEvent()) valid = false;
        if (!validateUser()) valid = false;
        if (!validateDiscount()) valid = false;
        if (!validateValidTill()) valid = false;
        if (!valid) {
            e.preventDefault();
            $("html,body").animate({scrollTop: $(".coupon-form-wrap").offset().top-30},200);
        }
    });

    // On page load, clear errors
    $(".form-error").text('');
});
</script>
</body>
</html>
