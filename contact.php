<?php
include_once 'database/db_connect.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/* ==========================
   HANDLE AJAX FIRST
========================== */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['form_ajax'] ?? '') == '1') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    header('Content-Type: application/json');

    if (!$name || !$email || !$subject || !$message) {
        echo json_encode(['success'=>false, 'msg'=>"All fields are required."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO contact_messages 
        (contact_message_full_name, contact_message_email, contact_message_subject, contact_message) 
        VALUES (?, ?, ?, ?)");

    if ($stmt) {
        $stmt->bind_param("ssss", $name, $email, $subject, $message);

        if ($stmt->execute()) {

            // $_SESSION['contact_success_msg'] = "Message sent successfully.";

            echo json_encode(['success'=>true]);
        } else {
            echo json_encode(['success'=>false, 'msg'=>"Database error."]);
        }

        $stmt->close();
    } else {
        echo json_encode(['success'=>false, 'msg'=>"Prepare failed."]);
    }

    exit;
}

/* ==========================
   AFTER POST HANDLING
========================== */

include 'header.php';


// Load contact info from the "contact" table, fetch id=1
$contact_info = [];
$result = $conn->query("SELECT * FROM contact WHERE contact_id=1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    // Map all possible fields you want to show
    $contact_info = [
        "address" => $row['contact_address'] ?? '',
        "phone"   => $row['contact_phone'] ?? '',
        "email"   => $row['contact_email'] ?? '',
        // Add newlines for each new line character in hours
        "hours"   => nl2br($row['working_hours'] ?? ''),
        // Add any other fields as needed
    ];
} else {
    // No record found
    $contact_info = [
        "address" => "",
        "phone" => "",
        "email" => "",
        "hours" => ""
    ];
}

// Handle AJAX form submit to contact_message table
// Also handle session success message for full page refresh
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['form_ajax'] ?? '') == '1') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    // Always return JSON
    header('Content-Type: application/json');
    if (!$name || !$email || !$subject || !$message) {
        echo json_encode(['success'=>false, 'msg'=>"All fields are required."]);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO contact_messages (contact_message_full_name, contact_message_email, contact_message_subject, contact_message) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssss", $name, $email, $subject, $message);
        $res = $stmt->execute();
        // Defensive: check the query worked, then set success flag
        if ($res) {
            $_SESSION['contact_success_msg'] = "Message sent successfully.";
            // 'msg' is set so JS doesn't see "Unexpected response."
            echo json_encode(['success'=>true, 'msg'=>"Message sent successfully.", 'reload'=>1]);
        } else {
            echo json_encode(['success'=>false, 'msg'=>"Something went wrong. Please try again."]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success'=>false, 'msg'=>"Something went wrong. Please try again."]);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | AoneHub</title>
    <link rel="stylesheet" href="bootstrap/css/bootstrap-icons.css">

    <link rel="stylesheet" href="css/contact.css">
</head>
<body>
    <!-- Hero -->
    <section class="contact-hero">
        <h1 class="contact-hero-title">Contact Us</h1>
        <p>We're here to assist you! Reach out via the form or directly below.</p>
    </section>
    <?php if (!empty($contact_success_msg)): ?>
    <div class="top-contact-success animate__animated animate__fadeInDown" id="top-contact-success"><?php echo htmlspecialchars($contact_success_msg); ?></div>
    <?php endif; ?>
    <section class="contact-section animate__animated animate__fadeInUp">
        <div class="contact-info-panel">
            <h2><i class="bi bi-info-circle"></i> Get in Touch</h2>
            <div class="contact-info-item">
                <h3><i class="bi bi-geo-alt"></i> Address</h3>
                <p><?php echo nl2br(htmlspecialchars($contact_info['address'] ?? '')); ?></p>
            </div>
            <div class="contact-info-item">
                <h3><i class="bi bi-telephone"></i> Phone</h3>
                <p>
                    <?php if (!empty($contact_info['phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($contact_info['phone']); ?>"><?php echo htmlspecialchars($contact_info['phone']); ?></a>
                    <?php endif; ?>
                </p>
            </div>
            <div class="contact-info-item">
                <h3><i class="bi bi-envelope"></i> Email</h3>
                <p>
                    <?php if (!empty($contact_info['email'])): ?>
                        <a href="mailto:<?php echo htmlspecialchars($contact_info['email']); ?>"><?php echo htmlspecialchars($contact_info['email']); ?></a>
                    <?php endif; ?>
                </p>
            </div>
            <div class="contact-info-item">
                <h3><i class="bi bi-clock"></i> Hours</h3>
                <p><?php echo $contact_info['hours'] ?? ''; ?></p>
            </div>
        </div>
        <div class="contact-form-panel">
            <h2>Send us a Message</h2>
            <div class="contact-success" id="contact-success"></div>
            <div class="contact-error" id="contact-error"></div>
            <form id="contactForm" autocomplete="off">
                <input type="hidden" name="form_ajax" value="1" />
                <div class="contact-form-row">
                    <label for="name">Full Name <span style="color:#bf2424">*</span></label>
                    <input type="text" id="name" name="name" minlength="2" maxlength="80">
                </div>
                <div class="contact-form-row">
                    <label for="email">Email <span style="color:#bf2424">*</span></label>
                    <input type="email" id="email" name="email" maxlength="120">
                </div>
                <div class="contact-form-row">
                    <label for="subject">Subject <span style="color:#bf2424">*</span></label>
                    <input type="text" id="subject" name="subject" maxlength="128">
                </div>
                <div class="contact-form-row">
                    <label for="message">Message <span style="color:#bf2424">*</span></label>
                    <textarea id="message" name="message" minlength="5" maxlength="1500"></textarea>
                </div>
                <button type="submit" class="contact-form-btn animate__animated" id="submitBtn" aria-label="Send Contact Message">Send Message</button>
            </form>
        </div>
    </section>
    <!-- Include dynamic footer -->
    <?php include 'footer.php'; ?>
    <script>
    // Animate button on submit and handle AJAX
    const form = document.getElementById('contactForm');
    const contactSuccess = document.getElementById('contact-success');
    const contactError = document.getElementById('contact-error');
    const submitBtn = document.getElementById('submitBtn');
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        contactSuccess.style.display = "none";
        contactError.style.display = "none";
        submitBtn.classList.remove('animate__headShake', 'animate__tada');
        submitBtn.disabled = true;
        submitBtn.textContent = "Sending...";
        const formData = new FormData(form);

        var xhr = new XMLHttpRequest();
        xhr.open("POST", window.location.pathname, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                var data = {};
                try { data = JSON.parse(xhr.responseText); } catch(e){ data = {success: false, msg: 'Unexpected error.'}; }
                if (xhr.status === 200 && data && data.success) {
                    // Refresh page to show success message at the top
                    contactSuccess.textContent = "Message sent successfully.";
                    contactSuccess.style.display = "block";
                    contactError.style.display = "none";
                    form.reset();
                    submitBtn.disabled = false;
                    submitBtn.textContent = "Send Message";
                    submitBtn.classList.add("animate__tada");
                                    
                    setTimeout(function(){
                        contactSuccess.style.display = "none";
                    }, 5000);

                } else {
                    contactError.textContent = (data && data.msg) ? data.msg : "Error submitting. Try again.";
                    contactError.style.display = "block";
                    contactSuccess.style.display = "none";
                    submitBtn.classList.add("animate__headShake");
                    submitBtn.disabled = false;
                    submitBtn.textContent = "Send Message";
                    setTimeout(function(){
                        contactError.style.display="none";
                    }, 5200);
                }
            }
        };
        xhr.send(formData);
    });
    </script>
</body>
</html>
<?php if(isset($conn) && $conn) $conn->close(); ?>
