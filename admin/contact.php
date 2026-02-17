<?php
session_start();
require_once('sidebar.php');

// Only admins allowed
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    header("Location: login.php");
    exit();
}

require_once('../database/db_connect.php');

// --- Edit contact info functionality ---
$contact_errors = [];
$contact_success = false;
$message_success = false;
$message_error = '';

// --- Handle status update for contact message, like events.php ---
$status_update_success = false;
$status_update_error = '';
if (isset($_POST['update_message_status'])) {
    $msg_id = (int)($_POST['message_id'] ?? 0);
    $valid_values = ['1', '0', 1, 0, 'true', 'false', true, false];
    $new_status = in_array($_POST['new_status'], ['1', 1, 'true', true], true) ? '1' : '0';
    $stmt = $conn->prepare("UPDATE contact_messages SET is_read=? WHERE contact_message_id=?");
    $stmt->bind_param("si", $new_status, $msg_id);
    if ($stmt->execute()) {
        $status_update_success = true;
    } else {
        $status_update_error = "Failed to update status.";
    }
    $stmt->close();
}

// Handle usual POSTs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['update_message_status'])) {

    // Handle edit contact info
    if (isset($_POST['edit_contact_info'])) {
        // Sanitize
        $contact_address = trim($_POST['contact_address'] ?? '');
        $contact_phone   = trim($_POST['contact_phone'] ?? '');
        $contact_email   = trim($_POST['contact_email'] ?? '');

        // Allow multi-line working hours; remove surrounding whitespace only, do not collapse lines
        $working_hours   = isset($_POST['working_hours']) ? rtrim(str_replace("\r", "", $_POST['working_hours'])) : '';

        // Validation
        if ($contact_address === '') $contact_errors['contact_address'] = "Address required";
        if ($contact_phone === '') {
            $contact_errors['contact_phone'] = "Phone required";
        } elseif (!preg_match('/^\d{10}$/', $contact_phone)) {
            $contact_errors['contact_phone'] = "Phone must be exactly 10 digits";
        }
        if ($contact_email === '' || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) 
            $contact_errors['contact_email'] = "Valid email required";
        if (trim($working_hours) === '') $contact_errors['working_hours'] = "Working hours required";

        if (!$contact_errors) {
            $stmt = $conn->prepare("UPDATE contact SET contact_address=?, contact_phone=?, contact_email=?, working_hours=? WHERE contact_id=1");
            $stmt->bind_param("ssss", $contact_address, $contact_phone, $contact_email, $working_hours);
            if ($stmt->execute()) {
                $contact_success = true;
            }
            $stmt->close();
        }
    }

    // Handle message sending
    if (isset($_POST['send_new_message'])) {
        $smtp_username = 'ishitvadhavana@gmail.com';
        $smtp_password = 'pwxo zzsn bafo emhf';
        $smtp_host = 'smtp.gmail.com';
        $smtp_port = 587;
        $smtp_secure = 'tls';
        $from_name = 'AOne Hub Admin';

        $to = trim($_POST['msg_to_email'] ?? '');
        $subject = trim($_POST['msg_subject'] ?? '');
        $body = trim($_POST['msg_body'] ?? '');

        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '' || $body === '') {
            $message_error = 'All fields required with valid To email.';
        } else {
            require_once __DIR__ . '/../vendor/autoload.php';
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = $smtp_host;
                $mail->SMTPAuth = true;
                $mail->Username = $smtp_username;
                $mail->Password = $smtp_password;
                $mail->SMTPSecure = $smtp_secure;
                $mail->Port = $smtp_port;
                $mail->setFrom($smtp_username, $from_name);
                $mail->addAddress($to);
                $mail->isHTML(false);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->send();
                $message_success = true;
            } catch (\Exception $e) {
                $message_error = 'Message could not be sent. Error: ' . $mail->ErrorInfo;
            }
        }
    }
}

// Fetch current contact info
$contact = [
    'contact_address' => '',
    'contact_phone' => '',
    'contact_email' => '',
    'working_hours' => ''
];
$res = $conn->query("SELECT contact_address, contact_phone, contact_email, working_hours FROM contact WHERE contact_id=1 LIMIT 1");
if ($res && $res->num_rows > 0) {
    $contact = $res->fetch_assoc();
}

// --- Fetch contact messages ---
$contact_messages = [];
$res_msg = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
if ($res_msg && $res_msg->num_rows > 0) {
    while($row = $res_msg->fetch_assoc()) {
        $contact_messages[] = $row;
    }
}

function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// --- Helper for preserving line breaks markup ---
function markup_newlines($text) {
    // esc first, then nl2br
    return nl2br(esc($text));
}

// --- Pagination using improved logic and classic-pagination style ---
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) ? (int)$_GET['page'] : 1;
$per_page =10;
$total_msgs = count($contact_messages);
$total_pages = ceil($total_msgs / $per_page);
$start_index = ($page - 1) * $per_page;
$paged_msgs = array_slice($contact_messages, $start_index, $per_page);
$serial_start = $start_index + 1;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Messages</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <script src="../bootstrap/js/jquery-3.7.1.min.js"></script>

    <link rel="stylesheet" href="css/contact.css">
    <link rel="stylesheet" href="css/events.css">

    
</head>
<body>
<div class="dashboard-main">
    <div class="dashboard-header" style="display: flex; justify-content: space-between; align-items: end;">
        <div>
            <h2>Contact Messages</h2>
        </div>
        <div style="display:flex; gap:9px;">
            <button class="btn" type="button" id="editContactBtn">Edit Contact Info</button>
            <button class="msg-btn" type="button" id="newMessageBtn">
                <span style="font-size:18px;vertical-align:middle;">&#9993;</span> Message
            </button>
        </div>
    </div>
    <div class="dashboard-card">
        <h3 style="margin-top: 0; color:#594285; font-size:20px; margin-bottom:0;">Contact Information</h3>
        <div class="contact-info-list">
            <div class="contact-info-item"><b>Address:</b> <br><?= esc($contact['contact_address']) ?></div>
            <div class="contact-info-item"><b>Phone:</b> <br><?= esc($contact['contact_phone']) ?></div>
            <div class="contact-info-item"><b>Email:</b> <br><?= esc($contact['contact_email']) ?></div>
            <div class="contact-info-item"><b>Working Hours:</b> <br>
                <span style="white-space:pre-line;"><?= markup_newlines($contact['working_hours']) ?></span>
            </div>
        </div>
        <?php if ($contact_success): ?>
            <div class="success-msg" style="margin-bottom:0;">Contact information updated.</div>
        <?php endif; ?>
    </div>
    <div class="dashboard-card" style="overflow-x:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;font-size:19px;color:#594285;">Messages</h3>
        </div>
        <?php if ($status_update_success): ?>
            <div class="success-msg" style="margin-bottom:10px;">Status updated!</div>
        <?php elseif ($status_update_error): ?>
            <div class="error-msg" style="margin-bottom:10px;"><?= esc($status_update_error) ?></div>
        <?php endif; ?>
        <table class="contact-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Subject</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Received At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($paged_msgs) === 0): ?>
                    <tr><td colspan="8" style="text-align:center; color:#888;">No messages found.</td></tr>
                <?php else: ?>
                    <?php foreach($paged_msgs as $i => $msg): ?>
                        <tr>
                            <td><?= $serial_start + $i ?></td>
                            <td><?= esc($msg['contact_message_full_name']) ?></td>
                            <td><?= esc($msg['contact_message_email']) ?></td>
                            <td><?= esc($msg['contact_message_subject']) ?></td>
                            <td style="max-width:340px;white-space:pre-line;"><?= nl2br(esc($msg['contact_message'])) ?></td>
                            <td>
                                <!-- Dropdown START -->
                                <form class="message-status-form" method="post" action="" onchange="this.submit();">
                                    <input type="hidden" name="update_message_status" value="1" />
                                    <input type="hidden" name="message_id" value="<?= (int)$msg['contact_message_id'] ?>" />
                                    <select name="new_status" class="message-status-select <?= $msg['is_read'] == '1' ? 'read' : 'unread' ?>">
                                        <option value="1" <?= $msg['is_read'] == '1' ? 'selected' : '' ?>>Read</option>
                                        <option value="0" <?= $msg['is_read'] == '0' ? 'selected' : '' ?>>Unread</option>
                                    </select>
                                </form>
                                <!-- Dropdown END -->
                            </td>
                            <td><?= esc(date('Y-m-d H:i',strtotime($msg['created_at']))) ?></td>
                            <td>
                                <div class="action-btn-table">
                                    <button class="msg-btn" title="Message" onclick="messageUser('<?= esc($msg['contact_message_email']) ?>','<?= esc($msg['contact_message_full_name']) ?>');event.stopPropagation();">
                                        &#9993; Message
                                    </button>
                                    <button class="btn" style="background:#a5092c;padding:4px 11px;font-size:14px;" title="Delete" onclick="deleteMsg(<?= (int)$msg['contact_message_id'] ?>);event.stopPropagation();">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Improved Classic Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="classic-pagination">
                <ul>
                <?php
                    // Previous Button
                    if ($page > 1) {
                        echo '<li><a href="?page=' . ($page-1) . '">&laquo; Prev</a></li>';
                    } else {
                        echo '<li><span class="disabled">&laquo; Prev</span></li>';
                    }

                    // Show all page numbers for <=15, else window & first/last/ellipsis (classic style)
                    if ($total_pages <= 15) {
                        for ($p = 1; $p <= $total_pages; $p++) {
                            if ($page == $p) {
                                echo '<li><span class="active">' . $p . '</span></li>';
                            } else {
                                echo '<li><a href="?page=' . $p . '">' . $p . '</a></li>';
                            }
                        }
                    } else {
                        if ($page < 6) {
                            // 1 2 3 4 5 6 ... n
                            for ($p = 1; $p <= 6; $p++) {
                                if ($page == $p) {
                                    echo '<li><span class="active">' . $p . '</span></li>';
                                } else {
                                    echo '<li><a href="?page=' . $p . '">' . $p . '</a></li>';
                                }
                            }
                            echo '<li><span>...</span></li>';
                            echo '<li><a href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        } elseif ($page > $total_pages - 5) {
                            // 1 ... n-5 n-4 n-3 n-2 n-1 n
                            echo '<li><a href="?page=1">1</a></li>';
                            echo '<li><span>...</span></li>';
                            for ($p = $total_pages-5; $p <= $total_pages; $p++) {
                                if ($page == $p) {
                                    echo '<li><span class="active">' . $p . '</span></li>';
                                } else {
                                    echo '<li><a href="?page=' . $p . '">' . $p . '</a></li>';
                                }
                            }
                        } else {
                            // 1 ... page-2 page-1 page page+1 page+2 ... n
                            echo '<li><a href="?page=1">1</a></li>';
                            echo '<li><span>...</span></li>';
                            for ($p = $page-2; $p <= $page+2; $p++) {
                                if ($page == $p) {
                                    echo '<li><span class="active">' . $p . '</span></li>';
                                } else {
                                    echo '<li><a href="?page=' . $p . '">' . $p . '</a></li>';
                                }
                            }
                            echo '<li><span>...</span></li>';
                            echo '<li><a href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                    }

                    // Next Button
                    if ($page < $total_pages) {
                        echo '<li><a href="?page=' . ($page+1) . '">Next &raquo;</a></li>';
                    } else {
                        echo '<li><span class="disabled">Next &raquo;</span></li>';
                    }
                ?>
                </ul>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Edit Contact info Popup Form -->
<div class="edit-contact-form-popup-bg" id="editContactPopup">
    <form class="edit-contact-form-popup" method="post" action="" autocomplete="off" id="editContactForm" onsubmit="return validateContactForm();">
        <input type="hidden" name="edit_contact_info" value="1" />
        <h3 style="margin-top:0; color:#594285;">Edit Contact Information</h3>
        <div class="form-group">
            <label for="contact_address">Address</label>
            <input type="text" id="contact_address" name="contact_address" value="<?= esc($contact['contact_address']) ?>">
            <div class="form-error" id="contact_address_error">
                <?php if (isset($contact_errors['contact_address'])): ?>
                    <?= esc($contact_errors['contact_address']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group">
            <label for="contact_phone">Phone</label>
            <input type="text" id="contact_phone" name="contact_phone" value="<?= esc($contact['contact_phone']) ?>">
            <div class="form-error" id="contact_phone_error">
                <?php if (isset($contact_errors['contact_phone'])): ?>
                    <?= esc($contact_errors['contact_phone']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group">
            <label for="contact_email">Email</label>
            <input type="email" id="contact_email" name="contact_email" value="<?= esc($contact['contact_email']) ?>">
            <div class="form-error" id="contact_email_error">
                <?php if (isset($contact_errors['contact_email'])): ?>
                    <?= esc($contact_errors['contact_email']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-group">
            <label for="working_hours">Working Hours</label>
            <textarea id="working_hours" name="working_hours" rows="4"><?= esc($contact['working_hours']) ?></textarea>
            <div class="form-error" id="working_hours_error">
                <?php if (isset($contact_errors['working_hours'])): ?>
                    <?= esc($contact_errors['working_hours']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div style="text-align:right;display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" class="btn" style="background:#aaa;color:#fff;" onclick="closeEditContactPopup()">Cancel</button>
            <button type="submit" class="btn">Save</button>
        </div>
    </form>
</div>

<!-- SEND NEW MESSAGE Modal -->
<div class="edit-contact-form-popup-bg" id="newMessagePopup" style="display:none;">
    <div class="edit-contact-form-popup" style="max-width:555px;">
        <h3 style="margin-top:0; color:#594285;">Send New Message</h3>
        <?php if ($message_success): ?>
            <div class="success-msg">Message sent successfully!</div>
        <?php elseif ($message_error): ?>
            <div class="error-msg"><?= esc($message_error) ?></div>
        <?php endif; ?>
        <form id="newMsgForm" autocomplete="off" method="post" action="">
            <input type="hidden" name="send_new_message" value="1" />
            <div class="form-group">
                <label for="msg_to_email">To Email</label>
                <input type="email" id="msg_to_email" name="msg_to_email" required value="<?= esc($_POST['msg_to_email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="msg_subject">Subject</label>
                <input type="text" id="msg_subject" name="msg_subject" required value="<?= esc($_POST['msg_subject'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="msg_body">Message</label>
                <textarea id="msg_body" name="msg_body" required rows="4" style="width:100%;border-radius:5px;border:1px solid #ddd;font-size:15px;padding:8px;"><?= esc($_POST['msg_body'] ?? '') ?></textarea>
            </div>
            <div style="text-align:right;display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn" style="background:#aaa;color:#fff;" onclick="closeNewMessagePopup()">Cancel</button>
                <button type="submit" class="btn">Send</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Open contact info edit popup
    document.getElementById('editContactBtn').onclick = function() {
        document.getElementById('editContactPopup').style.display='flex';
    };
    function closeEditContactPopup() {
        document.getElementById('editContactPopup').style.display='none';
    }

    // Open new message popup
    document.getElementById('newMessageBtn').onclick = function() {
        document.getElementById('msg_to_email').value = '';
        document.getElementById('msg_subject').value = '';
        document.getElementById('msg_body').value = '';
        document.getElementById('newMessagePopup').style.display='flex';
    };
    function closeNewMessagePopup() {
        document.getElementById('newMessagePopup').style.display='none';
    }

    // Message button in each row
    function messageUser(email, name) {
        document.getElementById('msg_to_email').value = email;
        document.getElementById('msg_subject').value = '';
        document.getElementById('msg_body').value = '';
        document.getElementById('newMessagePopup').style.display='flex';
        setTimeout(function() { document.getElementById('msg_subject').focus(); }, 100);
    }

    // Delete message (placeholder)
    function deleteMsg(msgId) {
        if (confirm('Are you sure you want to delete this message?')) {
            alert('Deleted message ID: ' + msgId + '\n(Update backend to implement this action.)');
        }
    }

    // --- POPUP CLOSE BEHAVIOR CHANGES ---
    // Don't close pop-up if form has errors

    // Utility: checks visible .form-error elements for the main popup
    function formHasVisibleErrors() {
        // Only for edit contact popup
        var hasErr = false;
        $('#editContactForm .form-error').each(function() {
            if ($(this).text().trim().length > 0) hasErr = true;
        });
        return hasErr;
    }

    // Overriding close on ESC for edit contact, only if no errors
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            // Only close if no visible errors on main form
            if ($('#editContactPopup').css('display') !== 'none') {
                if (!formHasVisibleErrors()) closeEditContactPopup();
            } else {
                closeNewMessagePopup();
            }
        }
    });

    // Only close on clicking backdrop if no errors shown
    document.getElementById('editContactPopup').onclick = function(e){
        if (e.target === this) {
            if (!formHasVisibleErrors()) closeEditContactPopup();
        }
    };
    document.getElementById('newMessagePopup').onclick = function(e){
        if (e.target === this) closeNewMessagePopup();
    };

    // ------- jQuery VALIDATION FOR CONTACT FORM -------
    function showError(id, msg) {
        $('#' + id).text(msg);
    }
    function clearError(id) {
        $('#' + id).text('');
    }

    function validateContactForm() {
        var valid = true;

        // Address
        var addr = $.trim($('#contact_address').val());
        if (addr.length === 0) {
            showError('contact_address_error', 'Address required');
            valid = false;
        } else {
            clearError('contact_address_error');
        }

        // Phone
        var phone = $.trim($('#contact_phone').val());
        if (phone.length === 0) {
            showError('contact_phone_error', 'Phone required');
            valid = false;
        } else if (!/^\d{10}$/.test(phone)) {
            showError('contact_phone_error', 'Phone must be exactly 10 digits');
            valid = false;
        } else {
            clearError('contact_phone_error');
        }

        // Email
        var email = $.trim($('#contact_email').val());
        var email_valid = /^[\w\.\-]+@([\w\-]+\.)+[a-zA-Z]{2,7}$/;
        if (email.length === 0) {
            showError('contact_email_error', 'Email required');
            valid = false;
        } else if (!email_valid.test(email)) {
            showError('contact_email_error', 'Valid email required');
            valid = false;
        } else {
            clearError('contact_email_error');
        }

        // Working hours (allow multiline textarea)
        var wh = $('#working_hours').val();
        if ($.trim(wh).length === 0) {
            showError('working_hours_error', 'Working hours required');
            valid = false;
        } else {
            clearError('working_hours_error');
        }

        return valid;
    }

    $(function(){
        // Live validation for contact form fields (now with 'input' event for up-to-date validation)
        $('#contact_address').on('input change keyup blur', function() {
            if ($.trim(this.value) === "") {
                showError('contact_address_error', 'Address required');
            } else {
                clearError('contact_address_error');
            }
        });

        $('#contact_phone').on('input change keyup blur', function() {
            var val = $.trim(this.value);
            if (val === "") {
                showError('contact_phone_error', 'Phone required');
            } else if (!/^\d{10}$/.test(val)) {
                showError('contact_phone_error', 'Phone must be exactly 10 digits');
            } else {
                clearError('contact_phone_error');
            }
        });

        $('#contact_email').on('input change keyup blur', function() {
            var val = $.trim(this.value);
            var email_valid = /^[\w\.\-]+@([\w\-]+\.)+[a-zA-Z]{2,7}$/;
            if (val === "") {
                showError('contact_email_error', 'Email required');
            } else if (!email_valid.test(val)) {
                showError('contact_email_error', 'Valid email required');
            } else {
                clearError('contact_email_error');
            }
        });

        $('#working_hours').on('input change keyup blur', function() {
            var val = $(this).val();
            if ($.trim(val) === "") {
                showError('working_hours_error', 'Working hours required');
            } else {
                clearError('working_hours_error');
            }
        });

        // Prevent edit popup from being closed by ESC/backdrop if there are visible errors
        $('#editContactForm input, #editContactForm textarea').on('input change', function() {
            // This callback only exists to recalculate errors, so closing is correctly blocked if relevant.
            // No-op: popup close logic already checks for errors.
        });
    });

</script>
</body>
</html>
