<?php
include 'header.php';
require_once 'database/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href="login.php";</script>'; exit;
}

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$coupon   = isset($_SESSION['new_coupon_generated']) ? $_SESSION['new_coupon_generated'] : null;
unset($_SESSION['new_coupon_generated']);

// Fetch event title
$event_title = '';
if ($event_id) {
    $s = mysqli_prepare($conn, 'SELECT event_title FROM events WHERE event_id = ? LIMIT 1');
    mysqli_stmt_bind_param($s, 'i', $event_id);
    mysqli_stmt_execute($s);
    mysqli_stmt_bind_result($s, $event_title);
    mysqli_stmt_fetch($s);
    mysqli_stmt_close($s);
}
?>
<style>
.success-wrap{max-width:600px;margin:48px auto;padding:0 16px}
.success-card{background:#fff;border-radius:18px;box-shadow:0 8px 40px rgba(0,0,0,.10);padding:40px 36px;text-align:center}
.success-icon{font-size:56px;color:#10b981;margin-bottom:16px}
.success-title{font-size:26px;font-weight:800;color:#1e293b;margin-bottom:8px}
.success-sub{color:#64748b;font-size:15px;margin-bottom:28px}
.coupon-box{background:linear-gradient(135deg,#667eea,#764ba2);border-radius:14px;padding:24px 28px;color:#fff;margin:24px 0;text-align:center}
.coupon-label{font-size:13px;text-transform:uppercase;letter-spacing:.08em;opacity:.85;margin-bottom:8px}
.coupon-code{font-size:32px;font-weight:900;letter-spacing:.18em;font-family:monospace;background:rgba(255,255,255,.18);border-radius:8px;padding:8px 20px;display:inline-block;margin-bottom:10px;border:2px dashed rgba(255,255,255,.5)}
.coupon-detail{font-size:14px;opacity:.9}
.copy-btn{background:rgba(255,255,255,.22);border:1.5px solid rgba(255,255,255,.5);color:#fff;border-radius:8px;padding:6px 18px;font-size:13px;font-weight:600;cursor:pointer;margin-top:10px;transition:background .15s}
.copy-btn:hover{background:rgba(255,255,255,.35)}
.copy-btn.copied{background:rgba(16,185,129,.5)}
.action-btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:28px}
.btn-primary-custom{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:10px;padding:12px 28px;font-size:15px;font-weight:700;text-decoration:none;cursor:pointer}
.btn-outline-custom{background:#fff;color:#667eea;border:2px solid #667eea;border-radius:10px;padding:12px 28px;font-size:15px;font-weight:700;text-decoration:none}
</style>

<div class="success-wrap">
    <div class="success-card">
        <div class="success-icon">✓</div>
        <div class="success-title">Payment Successful!</div>
        <div class="success-sub">
            Your event <strong><?php echo htmlspecialchars($event_title); ?></strong> has been created and is pending approval.
        </div>

        <?php if ($coupon): ?>
        <div class="coupon-box">
            <div class="coupon-label">🎉 You earned a reward coupon!</div>
            <div class="coupon-code" id="coupon-code-text"><?php echo htmlspecialchars($coupon['code']); ?></div>
            <div class="coupon-detail">
                <strong><?php echo (int)$coupon['discount']; ?>% off</strong> on your next event booking
                &nbsp;·&nbsp; Valid till <?php echo date('d M Y', strtotime($coupon['valid_till'])); ?>
            </div>
            <button class="copy-btn" id="copy-btn" onclick="copyCoupon()">Copy Code</button>
        </div>
        <p style="font-size:13px;color:#64748b;margin-top:-12px">This coupon has been saved to your account. Use it when booking your next event.</p>
        <?php endif; ?>

        <div class="action-btns">
            <a href="events.php" class="btn-primary-custom">Go to My Events</a>
            <?php if ($event_id): ?>
            <a href="event_requests.php?event_id=<?php echo $event_id; ?>" class="btn-outline-custom">View Booking Requests</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyCoupon() {
    var code = document.getElementById('coupon-code-text').textContent.trim();
    navigator.clipboard.writeText(code).then(function() {
        var btn = document.getElementById('copy-btn');
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(function(){ btn.textContent = 'Copy Code'; btn.classList.remove('copied'); }, 2000);
    });
}
</script>

<?php include 'footer.php'; ?>
