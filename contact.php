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

    <!-- <link rel="stylesheet" href="css/contact.css"> -->
    <style>
:root {
    --main: #3498db;
    --dark: #2c3e50;
    --light-bg: #f6f8fa;
}
html, body {
    height: 100%;
}
body {
    min-height: 100vh;
    font-family: 'Inter', Arial, sans-serif;
    background: var(--light-bg);
    color: #222;
}
.contact-hero {
    background: linear-gradient(108deg, #e7f3ff 60%, #dceafe 100%);
    padding: 40px 0 28px 0;
    text-align: center;
    position: relative;
    /* keep as is */
}
/* -- Custom animation for Contact Us heading -- */
.contact-hero .contact-hero-title {
    display: inline-block;
    animation: contactHeroSlideDown 1.1s cubic-bezier(.53,.07,.41,1.02);
}
@keyframes contactHeroSlideDown {
    0% {
        opacity: 0;
        transform: translateY(-70px) scale(0.93);
    }
    60% {
        opacity: 1;
        transform: translateY(8px) scale(1.04);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
.contact-hero p {
    font-size: 1.14rem;
    color: #3272ae;
}
.contact-section {
    max-width: 1024px;
    margin: 40px auto;
    display: flex;
    flex-wrap: wrap;
    gap: 38px;
    border-radius: 12px;
    padding: 35px 2vw 32px 2vw;
    background: #fff;
    box-shadow: 0 8px 30px rgba(39, 74, 120, 0.05);
    align-items: stretch;
    animation: fadeInUp 0.8s;
    position: relative;
}
@keyframes fadeInUp {
    0% { opacity: 0; transform: translateY(30px);}
    100% { opacity:1; transform: none;}
}
.contact-info-panel {
    flex: 1 1 280px;
    min-width: 230px;
    max-width: 360px;
    background: linear-gradient(120deg, #e2f3ff 70%, #e7f4fc 100%);
    border-radius: 8px;
    padding: 33px 22px 33px 22px;
    box-shadow: 0 5px 18px rgba(52,152,219,0.08);
    display: flex;
    flex-direction: column;
    gap: 20px;
    justify-content: center;
}
.contact-info-panel h2 {
    color: #3849b4;
    font-size: 1.22rem;
    font-weight: 700;
    margin-bottom: 22px;
    letter-spacing: 0.01em;
    border-left: 3px solid #ffe145;
    padding-left: 17px;
}
.contact-info-item {
    margin-bottom: 16px;
}
.contact-info-item h3 {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 1rem;
    color: #2c387e;
    margin-bottom: 3px;
}
.contact-info-item p, .contact-info-item a {
    color: #34495e;
    font-size: 0.97rem;
    margin: 0;
    text-decoration: none;
    transition: color .15s;
}
.contact-info-item a:hover {
    color: var(--main);
    text-decoration: underline;
}

.contact-form-panel {
    flex: 1.5 1 370px;
    background: #f7fafc;
    border-radius: 8px;
    padding: 32px 20px 21px 20px;
    box-shadow: 0 5px 18px rgba(52,152,219,0.05);
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.contact-form-panel h2 {
    color: #384989;
    font-size: 1.09rem;
    font-weight: 600;
    margin-bottom: 21px;
    letter-spacing: 0.01em;
}
.contact-form-row {
    margin-bottom: 17px;
    position: relative;
}
.contact-form-row label {
    font-size: 0.96rem;
    color: #49516C;
    font-weight: 500;
    display: block;
    margin-bottom: 7px;
}
.contact-form-row input,
.contact-form-row textarea {
    width: 100%;
    padding: 10px 13px;
    font-size: 1rem;
    border: 2px solid #dbe3f3;
    border-radius: 5px;
    outline: none;
    background: #f9fafb;
    color: #232;
    transition: border-color 0.19s, background 0.18s;
    box-sizing: border-box;
}
.contact-form-row input:focus,
.contact-form-row textarea:focus {
    border-color: #ffe14f;
    background: #fefde6;
}
.contact-form-row textarea {
    resize: vertical;
    min-height: 110px;
    max-height: 240px;
}
.contact-form-btn {
    background: linear-gradient(90deg, #40d2fa 30%, #ffe145 100%);
    color: #263159;
    font-weight: 700;
    padding: 13px 40px;
    border: none;
    border-radius: 26px;
    font-size: 1.15rem;
    box-shadow: 0 3px 14px rgba(52,152,219,0.09);
    cursor: pointer;
    outline: none;
    margin-top: 2px;
    transition: background .18s, box-shadow .16s, transform .11s;
}
.contact-form-btn:hover,
.contact-form-btn:focus {
    background: linear-gradient(95deg, #24b4e3 10%, #ffe07f 98%);
    transform: translateY(-2px) scale(1.03);
    color: #32305d;
    box-shadow: 0 6px 18px rgba(68,60,135,0.13);
}
.contact-success,
.contact-error {
    display: none;
    margin-bottom: 15px;
    padding: 12px 10px;
    border-radius: 6px;
    font-size: 1rem;
    font-weight: 600;
    text-align: center;
    animation: fadeIn .7s both;
}
/* Green color for contact success message */
.contact-success {
    background: linear-gradient(90deg, #38ef7d 0%, #11998e 100%);
    color: #fff;
    border: 1.5px solid #14a44d;
    box-shadow: 0 3px 16px rgba(52, 199, 89, 0.16), 0 1.5px 7px rgba(13,160,124,0.12);
    font-size: 1.07rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    padding: 13px 24px;
    text-shadow: 0 1px 7px rgba(17,153,142,0.10);
    margin-bottom: 15px;
    border-radius: 8px;
}
/* Responsive */
@media (max-width: 1024px) {
    .contact-section { flex-direction: column; gap:22px; padding: 26px 3vw 20px 3vw;}
    .contact-info-panel, .contact-form-panel { min-width: unset;max-width:100%;}
}
@media (max-width: 600px) {
    .contact-section { padding: 9px 0 13px 0; border-radius: 0; box-shadow: none; }
    .contact-form-panel, .contact-info-panel { box-shadow:none; border-radius:0; padding: 17px 7vw 18px 7vw;}
    .contact-hero { padding: 31px 8px 13px 8px;}
    .contact-info-panel h2 { padding-left:10px;}
}
    </style>
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
