<?php
require_once("../database/db_connect.php");
session_start();
require('sidebar.php');

// Only admins allowed
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    ?>
    <script>
        window.location.href = 'login.php';
    </script>
    <?php
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
function old($key, $arr = null) {
    if ($arr !== null) {
        return htmlspecialchars($arr[$key] ?? '', ENT_QUOTES, 'UTF-8');
    } else {
        return htmlspecialchars($_POST[$key] ?? '', ENT_QUOTES, 'UTF-8');
    }
}
function oldSelected($key, $val, $arr = null) {
    $data = $arr ?? $_POST;
    return (isset($data[$key]) && $data[$key] == $val) ? "selected" : "";
}

$errors = [];
$success = false;
$coupon = null;

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $errors[] = "Invalid coupon ID.";
} else {
    $coupon_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE coupon_id = ?");
    $stmt->bind_param("i", $coupon_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $coupon = $result->fetch_assoc();
    } else {
        $errors[] = "Coupon not found.";
    }
    $stmt->close();
}

// Pre-fill form fields with DB data unless there is POST
$formData = [];
if ($coupon) {
    $formData = [
        'coupon_code' => $coupon['coupon_code'],
        'coupon_from_event_id' => $coupon['coupon_from_event_id'],
        'coupon_applied_to_event_id' => $coupon['coupon_applied_to_event_id'] === null ? 'none' : $coupon['coupon_applied_to_event_id'],
        'coupon_user_id' => $coupon['coupon_user_id'],
        'coupon_discount' => $coupon['coupon_discount'],
        'coupon_valid_till' => $coupon['coupon_valid_till'],
    ];
    // When POSTed, override with POST for sticky values
    foreach ($formData as $k=>$v) {
        if (isset($_POST[$k])) $formData[$k] = $_POST[$k];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Coupon</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="js/jquery-4.0.0.min.js"></script>
<!-- <link rel="stylesheet" href="css/coupon_edit.css"> -->
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
    <h2 class="internal-header">Edit Coupon</h2>
    <div class="coupon-form-wrap">

        <?php if (!empty($errors)): ?>
            <div class="error-msg">
                <?= implode('<br>', $errors) ?>
            </div>
        <?php elseif (!$coupon): ?>
            <div class="error-msg">
                Coupon data unavailable.
            </div>
        <?php else: ?>
        <form method="post" id="couponForm" autocomplete="off" novalidate>
            <div class="form-group">
                <label for="coupon_code">Coupon Code <span style="color:red">*</span></label>
                <input type="text" name="coupon_code" id="coupon_code" value="<?= old('coupon_code', $formData) ?>" maxlength="64" />
                <span class="form-error" id="err_coupon_code"></span>
            </div>

            <div class="form-group">
                <label for="coupon_from_event_id">From Event <span style="color:red">*</span></label>
                <select name="coupon_from_event_id" id="coupon_from_event_id">
                    <option value="">-- Select Event --</option>
                    <?= getEventOptions($conn, $formData['coupon_from_event_id'] ?? null); ?>
                </select>
                <span class="form-error" id="err_coupon_from_event_id"></span>
            </div>

            <div class="form-group">
                <label for="coupon_applied_to_event_id">Applied To Event (Optional)</label>
                <select name="coupon_applied_to_event_id" id="coupon_applied_to_event_id">
                    <option value="none" <?= oldSelected('coupon_applied_to_event_id', 'none', $formData) ?>>-- None --</option>
                    <?= getEventOptions($conn, ($formData['coupon_applied_to_event_id'] !== 'none' ? $formData['coupon_applied_to_event_id'] : null)); ?>
                </select>
                <span class="form-error" id="err_coupon_applied_to_event_id"></span>
            </div>

            <div class="form-group">
                <label for="coupon_user_id">User <span style="color:red">*</span></label>
                <select name="coupon_user_id" id="coupon_user_id">
                    <option value="">-- Select User --</option>
                    <?= getUserOptions($conn, $formData['coupon_user_id'] ?? null); ?>
                </select>
                <span class="form-error" id="err_coupon_user_id"></span>
            </div>

            <div class="form-group">
                <label for="coupon_discount">Discount (%) <span style="color:red">*</span></label>
                <input type="number" name="coupon_discount" id="coupon_discount" value="<?= old('coupon_discount', $formData) ?>" min="1" max="100" />
                <span class="form-error" id="err_coupon_discount"></span>
            </div>
            <div class="form-group">
                <label for="coupon_valid_till">Valid Till <span style="color:red">*</span></label>
                <?php
                // $formData['coupon_valid_till'] can be unix timestamp or Y-m-d[ H:i[:s]]
                $validTillVal = '';
                $validFrom = $formData['coupon_valid_till'] ?? '';
                if (is_numeric($validFrom) && $validFrom != 0) {
                    $validTillVal = date('Y-m-d\TH:i', (int)$validFrom);
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $validFrom)) {
                    $t = strtotime($validFrom);
                    if ($t !== false) $validTillVal = date('Y-m-d\TH:i', $t);
                } else {
                    $validTillVal = htmlspecialchars($validFrom, ENT_QUOTES, 'UTF-8');
                }
                ?>
                <input type="datetime-local" name="coupon_valid_till" id="coupon_valid_till" value="<?= $validTillVal ?>" required />
                <span class="form-error" id="err_coupon_valid_till"></span>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit" style="opacity:0.99;">Update</button>
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

    // Allow form submission, but do not do anything
    $("#couponForm").on("submit", function(e) {
        // Just allow it to post for now.
    });

    // On page load, clear errors
    $(".form-error").text('');
});
</script>
</body>
</html>
