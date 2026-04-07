<?php
require_once("../database/db_connect.php");
session_start();
require('sidebar.php');

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    echo '<script>window.location.href="login.php";</script>'; exit();
}

function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

function eventOptions($conn, $sel = null) {
    $o = '<option value="">-- Select Event --</option>';
    $r = $conn->query("SELECT event_id, event_title FROM events ORDER BY event_title ASC");
    while ($row = $r->fetch_assoc())
        $o .= '<option value="'.(int)$row['event_id'].'"'.($sel==$row['event_id']?' selected':'').'>'.esc($row['event_title']).' (ID:'.(int)$row['event_id'].')</option>';
    return $o;
}
function userOptions($conn, $sel = null) {
    $o = '<option value="">-- Select User --</option>';
    $r = $conn->query("SELECT user_id, user_name, user_email FROM users ORDER BY user_name ASC");
    while ($row = $r->fetch_assoc())
        $o .= '<option value="'.(int)$row['user_id'].'"'.($sel==$row['user_id']?' selected':'').'>'.esc($row['user_name']).' ('.esc($row['user_email']).')</option>';
    return $o;
}

$errors = [];
$success = false;
$coupon_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$coupon_id) { echo '<script>window.location.href="coupons.php";</script>'; exit(); }

// Load existing
$stmt = $conn->prepare("SELECT * FROM coupons WHERE coupon_id = ? LIMIT 1");
$stmt->bind_param('i', $coupon_id); $stmt->execute();
$coupon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$coupon) { echo '<script>window.location.href="coupons.php";</script>'; exit(); }

// Build form data — DB values as default, POST overrides on re-render
$f = [
    'coupon_code'                => $coupon['coupon_code'],
    'coupon_from_event_id'       => $coupon['coupon_from_event_id'],
    'coupon_applied_to_event_id' => $coupon['coupon_applied_to_event_id'] ?? '',
    'coupon_user_id'             => $coupon['coupon_user_id'],
    'coupon_discount'            => $coupon['coupon_discount'],
    'coupon_valid_till'          => $coupon['coupon_valid_till'],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $k => $_) $f[$k] = trim($_POST[$k] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($f['coupon_code'] === '')                         $errors[] = 'Coupon code is required.';
    elseif (strlen($f['coupon_code']) > 64)               $errors[] = 'Coupon code max 64 characters.';
    else {
        $chk = $conn->prepare("SELECT coupon_id FROM coupons WHERE coupon_code = ? AND coupon_id != ? LIMIT 1");
        $chk->bind_param('si', $f['coupon_code'], $coupon_id); $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) $errors[] = 'Coupon code already used by another coupon.';
        $chk->close();
    }

    if ($f['coupon_from_event_id'] === '')                $errors[] = 'From Event is required.';
    if ($f['coupon_user_id'] === '')                      $errors[] = 'User is required.';

    if ($f['coupon_discount'] === '' || !ctype_digit($f['coupon_discount']) || (int)$f['coupon_discount'] < 1 || (int)$f['coupon_discount'] > 100)
        $errors[] = 'Discount must be between 1 and 100.';

    if ($f['coupon_valid_till'] === '')                   $errors[] = 'Valid till is required.';
    elseif (strtotime($f['coupon_valid_till']) <= time())  $errors[] = 'Valid till must be in the future.';

    // Cross-validation: applied_to set → is_used = 1, else is_used = 0
    $applied = ($f['coupon_applied_to_event_id'] !== '' && $f['coupon_applied_to_event_id'] !== '0')
               ? (int)$f['coupon_applied_to_event_id'] : null;
    $is_used = ($applied !== null) ? '1' : '0';

    if (empty($errors)) {
        $from  = (int)$f['coupon_from_event_id'];
        $uid   = (int)$f['coupon_user_id'];
        $disc  = (int)$f['coupon_discount'];
        $vtill = date('Y-m-d H:i:s', strtotime($f['coupon_valid_till']));

        $stmt = $conn->prepare("UPDATE coupons SET coupon_code=?, coupon_from_event_id=?, coupon_applied_to_event_id=?, coupon_user_id=?, coupon_discount=?, coupon_valid_till=?, coupon_is_used=? WHERE coupon_id=?");
        $stmt->bind_param('siiiissi', $f['coupon_code'], $from, $applied, $uid, $disc, $vtill, $is_used, $coupon_id);
        try {
            if ($stmt->execute()) {
                $success = true;
            } else {
                if ($conn->errno == 1062) {
                    if (strpos($conn->error, 'coupon_from_event_id') !== false)
                        $errors[] = 'A coupon already exists for the selected From Event.';
                    elseif (strpos($conn->error, 'coupon_applied_to_event_id') !== false)
                        $errors[] = 'A coupon is already applied to the selected Applied To Event.';
                    elseif (strpos($conn->error, 'coupon_code') !== false)
                        $errors[] = 'This coupon code already exists.';
                    else
                        $errors[] = 'A duplicate entry was detected. Please check your inputs.';
                } else {
                    $errors[] = 'Failed to update coupon. Please try again.';
                }
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'coupon_from_event_id') !== false)
                $errors[] = 'A coupon already exists for the selected From Event.';
            elseif (strpos($e->getMessage(), 'coupon_applied_to_event_id') !== false)
                $errors[] = 'A coupon is already applied to the selected Applied To Event.';
            elseif (strpos($e->getMessage(), 'Duplicate') !== false)
                $errors[] = 'A duplicate entry was detected. Please check your inputs.';
            else
                $errors[] = 'Failed to update coupon. Please try again.';
        }
        $stmt->close();
    }
}

$vtill_input = '';
if ($f['coupon_valid_till']) {
    $t = strtotime($f['coupon_valid_till']);
    if ($t) $vtill_input = date('Y-m-d\TH:i', $t);
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><title>Edit Coupon</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="js/jquery-4.0.0.min.js"></script>
<style>
body{margin:0;background:#f4f6fb}
.dashboard-main{padding:40px}
.internal-header{margin:0 0 18px;color:#322053}
.coupon-form-wrap{max-width:520px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 1px 10px rgba(44,62,80,.10);padding:30px 32px 24px}
.form-group{margin-bottom:20px}
.form-group label{display:block;font-weight:600;margin-bottom:5px;color:#594285}
.form-group input,.form-group select{width:100%;border-radius:6px;border:1px solid #ddd;padding:8px 10px;font-size:15px;box-sizing:border-box;background:#fafbff}
.form-group input:focus,.form-group select:focus{outline:none;border-color:#7090f5;background:#fff}
.form-actions{margin-top:24px;display:flex;gap:14px;justify-content:flex-end}
.btn{background:linear-gradient(90deg,#2d397a,#594285 90%);color:#fff;padding:8px 24px;border:none;border-radius:7px;font-size:15px;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}
.btn:hover{background:linear-gradient(90deg,#594285,#2d397a)}
.btn-cancel{background:#aaa;color:#222}
.btn-cancel:hover{background:#888;color:#fff}
.error-msg{background:#fde8e4;color:#a5092c;border-radius:4px;padding:9px 14px;margin-bottom:14px}
.success-msg{background:#dbfadd;color:#18793a;border-radius:4px;padding:9px 14px;margin-bottom:14px}
.form-error{color:#a5092c;font-size:13px;margin-top:3px;display:block}
.info-note{font-size:12px;color:#e07b00;margin-top:4px}
.is-used-badge{display:inline-block;padding:3px 12px;border-radius:12px;font-size:13px;font-weight:700}
.used{background:#fde8e4;color:#a5092c}
.unused{background:#dbfadd;color:#18793a}
</style>
</head><body>
<div class="dashboard-main">
<h2 class="internal-header">Edit Coupon #<?= $coupon_id ?></h2>
<div class="coupon-form-wrap">

<?php if ($success): ?>
    <div class="success-msg">Coupon updated successfully. <a href="coupons.php">Back to list</a></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="error-msg"><?= implode('<br>', array_map('esc', $errors)) ?></div>
<?php endif; ?>

<form method="post" id="couponForm" novalidate autocomplete="off">

    <div class="form-group">
        <label>Coupon Code <span style="color:red">*</span></label>
        <input type="text" name="coupon_code" id="coupon_code" value="<?= esc($f['coupon_code']) ?>" maxlength="64">
        <span class="form-error" id="err_code"></span>
    </div>

    <div class="form-group">
        <label>From Event <span style="color:red">*</span></label>
        <select name="coupon_from_event_id" id="coupon_from_event_id">
            <?= eventOptions($conn, $f['coupon_from_event_id'] ?: null) ?>
        </select>
        <span class="form-error" id="err_from"></span>
    </div>

    <div class="form-group">
        <label>Applied To Event <small style="font-weight:400">(optional — if set, coupon is marked Used)</small></label>
        <select name="coupon_applied_to_event_id" id="coupon_applied_to_event_id">
            <option value="">-- None --</option>
            <?= eventOptions($conn, $f['coupon_applied_to_event_id'] ?: null) ?>
        </select>
        <span class="info-note" id="used_note" style="display:none">⚠ Coupon will be marked as <strong>Used</strong>.</span>
    </div>

    <div class="form-group">
        <label>Is Used (auto-managed)</label>
        <div id="is_used_display">
            <?php $cur_used = ($f['coupon_applied_to_event_id'] && $f['coupon_applied_to_event_id'] !== '0') ? 1 : 0; ?>
            <span class="is-used-badge <?= $cur_used ? 'used' : 'unused' ?>" id="is_used_badge"><?= $cur_used ? 'Used' : 'Not Used' ?></span>
            <small style="color:#888;margin-left:8px">Set automatically based on Applied To Event</small>
        </div>
    </div>

    <div class="form-group">
        <label>User <span style="color:red">*</span></label>
        <select name="coupon_user_id" id="coupon_user_id">
            <?= userOptions($conn, $f['coupon_user_id'] ?: null) ?>
        </select>
        <span class="form-error" id="err_user"></span>
    </div>

    <div class="form-group">
        <label>Discount (%) <span style="color:red">*</span></label>
        <input type="number" name="coupon_discount" id="coupon_discount" value="<?= esc($f['coupon_discount']) ?>" min="1" max="100">
        <span class="form-error" id="err_discount"></span>
    </div>

    <div class="form-group">
        <label>Valid Till <span style="color:red">*</span></label>
        <input type="datetime-local" name="coupon_valid_till" id="coupon_valid_till" value="<?= esc($vtill_input) ?>">
        <span class="form-error" id="err_valid"></span>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn">Update Coupon</button>
        <a href="coupons.php" class="btn btn-cancel">Cancel</a>
    </div>
</form>
</div></div>

<script>
$(function(){
    function v(id,msg){ $('#'+id).text(msg); return msg==='';}
    function chkCode(){ var val=$('#coupon_code').val().trim(); if(!val) return v('err_code','Coupon code is required.'); if(val.length>64) return v('err_code','Max 64 characters.'); return v('err_code',''); }
    function chkFrom(){ var val=$('#coupon_from_event_id').val(); if(!val) return v('err_from','From Event is required.'); return v('err_from',''); }
    function chkUser(){ var val=$('#coupon_user_id').val(); if(!val) return v('err_user','User is required.'); return v('err_user',''); }
    function chkDiscount(){ var val=parseInt($('#coupon_discount').val()); if(isNaN(val)||val<1||val>100) return v('err_discount','Discount must be 1–100.'); return v('err_discount',''); }
    function chkValid(){ var val=$('#coupon_valid_till').val(); if(!val) return v('err_valid','Valid till is required.'); if(new Date(val)<=new Date()) return v('err_valid','Must be in the future.'); return v('err_valid',''); }

    $('#coupon_applied_to_event_id').on('change', function(){
        var set = $(this).val() !== '';
        $('#used_note').toggle(set);
        $('#is_used_badge').text(set ? 'Used' : 'Not Used').removeClass('used unused').addClass(set ? 'used' : 'unused');
    }).trigger('change');

    $('#coupon_code').on('blur input', chkCode);
    $('#coupon_from_event_id').on('change', chkFrom);
    $('#coupon_user_id').on('change', chkUser);
    $('#coupon_discount').on('blur input', chkDiscount);
    $('#coupon_valid_till').on('change blur', chkValid);

    $('#couponForm').on('submit', function(e){
        var ok = chkCode() & chkFrom() & chkUser() & chkDiscount() & chkValid();
        if(!ok){ e.preventDefault(); $('html,body').animate({scrollTop:0},200); }
    });
});
</script>
</body></html>
