<?php
include 'header.php';
require_once __DIR__ . '/database/db_connect.php';

date_default_timezone_set('Asia/Kolkata');
mysqli_query($conn, "SET time_zone = '+05:30'");

if (!isset($_SESSION['user_id'])) { ?>
    <script>window.location.href="login.php";</script>
<?php exit; }

$user_role = isset($_SESSION['user_type']) ? strtolower($_SESSION['user_type']) : '';
if ($user_role !== 'owner' && $user_role !== 'admin') { ?>
    <script>window.location.href="events.php";</script>
<?php exit; }

if (empty($_SESSION['pending_event_checkout'])
    || (int)($_SESSION['pending_event_checkout']['owner_id'] ?? 0) !== (int)$_SESSION['user_id']) { ?>
    <script>window.location.href="create_event.php";</script>
<?php exit; }

$pending     = $_SESSION['pending_event_checkout'];
$category_id = (int)($pending['category_id'] ?? 0);

$category_name = '';
if ($category_id > 0) {
    $cs = mysqli_prepare($conn, 'SELECT category_name FROM category WHERE category_id = ? LIMIT 1');
    mysqli_stmt_bind_param($cs, 'i', $category_id);
    mysqli_stmt_execute($cs);
    mysqli_stmt_bind_result($cs, $category_name);
    mysqli_stmt_fetch($cs);
    mysqli_stmt_close($cs);
}

$user_email = $user_name = '';
$uid = (int)$_SESSION['user_id'];
$us = mysqli_prepare($conn, 'SELECT user_email, user_name FROM users WHERE user_id = ? LIMIT 1');
mysqli_stmt_bind_param($us, 'i', $uid);
mysqli_stmt_execute($us);
mysqli_stmt_bind_result($us, $user_email, $user_name);
mysqli_stmt_fetch($us);
mysqli_stmt_close($us);

$title       = $pending['title'] ?? '';
$start_label = ($t = strtotime($pending['event_start_time'] ?? '')) ? date('d M Y, h:i A', $t) : '';
$end_label   = ($t = strtotime($pending['event_end_time']   ?? '')) ? date('d M Y, h:i A', $t) : '';
$price_float = (float)($pending['event_price_float'] ?? 0);
$price_fmt   = number_format($price_float, 2, '.', '');
$event_seats = (int)($pending['event_seats'] ?? 0);

$cfg    = require __DIR__ . '/config/razorpay_config.php';
$key_id = trim((string)($cfg['key_id'] ?? ''));
$secret = trim((string)($cfg['key_secret'] ?? ''));
$has_key = $key_id !== '';
?>
<link rel="stylesheet" href="bootstrap/css/all.min.css"/>
<style>
.pay-page-wrap{max-width:1100px;margin:24px auto 48px;padding:0 16px}
.payment-wrapper{display:grid;grid-template-columns:1fr 1fr;gap:28px;background:#fff;border-radius:20px;padding:36px;box-shadow:0 20px 60px rgba(0,0,0,.12)}
.event-section{border-right:1px solid #e5e7eb;padding-right:24px}
.payment-section{padding-left:8px}
.section-title{font-size:22px;font-weight:700;color:#1f2937;margin-bottom:22px;display:flex;align-items:center;gap:10px}
.section-title i{color:#667eea}
.event-card{background:linear-gradient(135deg,#667eea,#764ba2);border-radius:16px;padding:26px;color:#fff;box-shadow:0 10px 40px rgba(102,126,234,.25)}
.event-title{font-size:20px;font-weight:700;margin-bottom:16px}
.event-details{display:flex;flex-direction:column;gap:12px;font-size:15px}
.event-detail-item{display:flex;align-items:flex-start;gap:10px}
.event-detail-item i{width:20px;text-align:center;margin-top:2px}
.price-section{background:linear-gradient(135deg,#f8fafc,#e2e8f0);border-radius:14px;padding:22px;margin-bottom:22px;text-align:center}
.price-label{font-size:13px;color:#64748b;text-transform:uppercase;letter-spacing:.06em}
.price-amount{font-size:32px;font-weight:800;color:#1e293b;margin-top:6px}
.original-price{font-size:15px;color:#94a3b8;text-decoration:line-through;margin-top:2px}
.discount-badge{font-size:13px;color:#10b981;margin-top:4px;display:none}
.pay-btn{width:100%;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none;border-radius:14px;padding:16px 20px;font-size:17px;font-weight:700;cursor:pointer;box-shadow:0 8px 24px rgba(102,126,234,.35);transition:transform .15s,box-shadow .15s}
.pay-btn:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 12px 28px rgba(102,126,234,.45)}
.pay-btn:disabled{opacity:.65;cursor:not-allowed}
.pay-hint{font-size:13px;color:#64748b;margin-top:14px;line-height:1.5}
.err-box{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:12px 14px;border-radius:10px;font-size:14px;margin-bottom:16px;display:none}
.back-link{display:inline-flex;align-items:center;gap:8px;color:#667eea;text-decoration:none;font-weight:600;margin-top:18px}
.back-link:hover{color:#764ba2}
.security-badge{display:flex;align-items:center;gap:10px;margin-top:20px;color:#64748b;font-size:13px;padding:12px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px}
.security-badge i{color:#10b981}
.coupon-wrap{margin-bottom:18px}
.coupon-wrap label{font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px}
.coupon-row{display:flex;gap:8px}
.coupon-row input{flex:1;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:14px;text-transform:uppercase;outline:none}
.coupon-row input:focus{border-color:#667eea}
.coupon-row button{padding:10px 16px;background:#667eea;color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;font-size:14px}
.coupon-row button:disabled{opacity:.6;cursor:not-allowed}
.coupon-msg{font-size:13px;margin-top:6px;min-height:18px}
@media(max-width:768px){.payment-wrapper{grid-template-columns:1fr;padding:22px}.event-section{border:none;padding-right:0}}
</style>

<div class="pay-page-wrap">
  <div class="payment-wrapper">

    <!-- LEFT: event details -->
    <div class="event-section">
      <h2 class="section-title"><i class="fas fa-calendar-check"></i> Event Details</h2>
      <div class="event-card">
        <h3 class="event-title"><?php echo htmlspecialchars($title); ?></h3>
        <div class="event-details">
          <div class="event-detail-item"><i class="fas fa-tag"></i><span><?php echo $category_name !== '' ? htmlspecialchars($category_name) : '—'; ?></span></div>
          <div class="event-detail-item"><i class="fas fa-play-circle"></i><span>Starts: <?php echo htmlspecialchars($start_label); ?></span></div>
          <div class="event-detail-item"><i class="fas fa-stop-circle"></i><span>Ends: <?php echo htmlspecialchars($end_label); ?></span></div>
          <div class="event-detail-item"><i class="fas fa-users"></i><span><?php echo $event_seats; ?> seats</span></div>
        </div>
      </div>
      <div class="security-badge">
        <i class="fas fa-shield-alt"></i>
        <span><?php echo $secret !== '' ? 'Razorpay Test Checkout — no real money charged.' : 'Razorpay Checkout (test mode) — use test card/UPI details.'; ?></span>
      </div>
    </div>

    <!-- RIGHT: payment -->
    <div class="payment-section">
      <h2 class="section-title"><i class="fas fa-credit-card"></i> Pay Listing Fee</h2>

      <div id="pay-err" class="err-box"></div>

      <?php if (!$has_key): ?>
        <div class="err-box" style="display:block">
          Razorpay Key ID is not configured. Add it in <code>config/razorpay_config.php</code>.
        </div>
      <?php else: ?>

        <!-- Coupon -->
        <div class="coupon-wrap">
          <label>Have a coupon code?</label>
          <div class="coupon-row">
            <input type="text" id="coupon-input" placeholder="Enter coupon code">
            <button type="button" id="apply-coupon-btn">Apply</button>
          </div>
          <div class="coupon-msg" id="coupon-msg"></div>
        </div>

        <!-- Price -->
        <div class="price-section">
          <div class="price-label">Total</div>
          <div class="price-amount" id="display-price">₹<?php echo htmlspecialchars($price_fmt); ?></div>
          <div class="original-price" id="original-price" style="display:none">₹<?php echo htmlspecialchars($price_fmt); ?></div>
          <div class="discount-badge" id="discount-badge"></div>
        </div>

        <p class="pay-hint">The Razorpay Checkout window will open. Use test card/UPI details — no real money is charged. Your event is saved only after successful payment.</p>

        <button type="button" class="pay-btn" id="rzp-button">
          <i class="fas fa-lock"></i> Pay with Razorpay
        </button>

      <?php endif; ?>

      <a href="create_event.php" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to edit event details
      </a>
    </div>

  </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
(function () {
    var btn    = document.getElementById('rzp-button');
    var errBox = document.getElementById('pay-err');
    if (!btn) return;

    var userEmail = <?php echo json_encode($user_email); ?>;
    var userName  = <?php echo json_encode($user_name); ?>;

    function showErr(msg) { errBox.textContent = msg; errBox.style.display = 'block'; }
    function hideErr()    { errBox.style.display = 'none'; }

    function doVerify(payload) {
        btn.disabled = true;
        fetch('payment_verify.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (vr) {
            if (!vr.ok || vr.j.error) { showErr(vr.j.error || 'Verification failed.'); btn.disabled = false; return; }
            window.location.href = vr.j.redirect || 'events.php';
        })
        .catch(function () { showErr('Network error during verification.'); btn.disabled = false; });
    }

    btn.addEventListener('click', function () {
        hideErr();
        btn.disabled = true;
        fetch('razorpay_create_order.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
            if (!res.ok || res.j.error) { showErr(res.j.error || 'Could not start payment.'); btn.disabled = false; return; }
            var d = res.j;

            var options = {
                key:         d.key_id,
                amount:      d.amount,
                currency:    d.currency || 'INR',
                name:        'Aone Hub',
                description: 'Event listing fee',
                prefill:     { email: userEmail, name: userName },
                theme:       { color: '#667eea' },
                modal: {
                    ondismiss: function () {
                        btn.disabled = false;
                        window.location.href = 'create_event.php?payment=cancelled';
                    }
                }
            };

            if (d.keyonly) {
                // Key-only: no order_id, Razorpay returns only payment_id
                options.handler = function (response) {
                    doVerify({ razorpay_payment_id: response.razorpay_payment_id });
                };
            } else {
                // Both keys: order_id + signature verification
                options.order_id = d.order_id;
                options.handler  = function (response) {
                    doVerify({
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id:   response.razorpay_order_id,
                        razorpay_signature:  response.razorpay_signature
                    });
                };
            }

            var rzp = new Razorpay(options);
            rzp.on('payment.failed', function () {
                showErr('Payment failed. You can try again.');
                btn.disabled = false;
            });
            rzp.open();
            btn.disabled = false;
        })
        .catch(function () { showErr('Network error. Try again.'); btn.disabled = false; });
    });

    // Coupon
    var couponBtn = document.getElementById('apply-coupon-btn');
    if (couponBtn) {
        couponBtn.addEventListener('click', function () {
            var code   = document.getElementById('coupon-input').value.trim();
            var msgEl  = document.getElementById('coupon-msg');
            if (!code) { msgEl.style.color = '#b91c1c'; msgEl.textContent = 'Enter a coupon code.'; return; }
            couponBtn.disabled = true;
            fetch('apply_coupon.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ coupon_code: code })
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.error) {
                    msgEl.style.color = '#b91c1c';
                    msgEl.textContent = d.error;
                    couponBtn.disabled = false;
                    return;
                }
                msgEl.style.color   = '#10b981';
                msgEl.textContent   = 'Coupon applied! ' + d.discount + '% off';
                document.getElementById('display-price').textContent  = '₹' + d.new_price;
                var orig = document.getElementById('original-price');
                orig.style.display = 'block';
                var badge = document.getElementById('discount-badge');
                badge.textContent   = d.discount + '% discount applied';
                badge.style.display = 'block';
                document.getElementById('coupon-input').disabled = true;
                couponBtn.disabled = true;
            })
            .catch(function () { msgEl.style.color = '#b91c1c'; msgEl.textContent = 'Network error.'; couponBtn.disabled = false; });
        });
    }
})();
</script>

<?php include 'footer.php'; ?>
