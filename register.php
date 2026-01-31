<?php
// Include database connection
require_once 'database/db_connect.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Redirect to index page if user already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Load PHPMailer (make sure composer installed it)
require 'vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get form data and sanitize
    $user_name = mysqli_real_escape_string($conn, $_POST['user_name']);
    $user_email = mysqli_real_escape_string($conn, $_POST['user_email']);
    $user_phone_number = mysqli_real_escape_string($conn, $_POST['user_phone_number']);
    $user_address = mysqli_real_escape_string($conn, $_POST['user_address']);
    $user_password = $_POST['user_password'];
    $confirm_user_password = $_POST['confirm_user_password'];
    $user_token = bin2hex(random_bytes(16)); // secure token

    // Check if email already exists 
    $check_email_sql = "SELECT user_id FROM users WHERE user_email = '$user_email' LIMIT 1";
    $check_email_result = mysqli_query($conn, $check_email_sql);

    if (mysqli_num_rows($check_email_result) > 0) {
        $error = "Email already exists!";
    } elseif ($user_password !== $confirm_user_password) {
        $error = "Passwords do not match!";
    } else {
        // Hash the password
        $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);

        // Insert into database
        $sql = "INSERT INTO users (user_name, user_email, user_phone_number, user_address, user_password, user_token)
                VALUES ('$user_name', '$user_email', '$user_phone_number', '$user_address', '$hashed_password', '$user_token')";

        if (mysqli_query($conn, $sql)) {
            // Send verification email using PHPMailer
            $mail = new PHPMailer(true);
            try {
                // SMTP configuration
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // your SMTP host
                $mail->SMTPAuth = true;
                $mail->Username = 'ishitvadhavana@gmail.com'; // your email
                $mail->Password = 'pwxo zzsn bafo emhf'; // Gmail App Password
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('ishitvadhavana@gmail.com', 'AOne Hub');
                $mail->addAddress($user_email, $user_name);

                $mail->isHTML(true);
                $mail->Subject = 'Verify Your AOne Hub Account';
                $mail->Body    = "Hi $user_name,<br><br>
                                  Please click the link below to verify your account:<br>
                                  <a href='http://localhost/event_management/verify.php?email=$user_email&token=$user_token'>Verify Email</a><br><br>
                                  Thank you!";

                $mail->send();
                $success = "Registration successful! Please check your email to verify your account.";
            } catch (Exception $e) {
                $error = "Registration successful, but email could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }

        } else {
            $error = "Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AOne Hub</title>
    <link rel="stylesheet" href="css/login.css">
    <script src="js/jquery-4.0.0.min.js"></script>
    <script src="js/jquery.validate.min.js"></script>
    <script src="js/register.js"></script>
</head>
<body>

<div class="auth-container">
    
    <form class="auth-box" id="registerForm" action="" method="post" novalidate>
        <h2>Create Account</h2>
        <p>Join EventHub today</p>

        <!-- Display messages -->
        <?php if (!empty($error)) { ?>
            <p style="color:red;"><?php echo $error; ?></p>
        <?php } elseif (!empty($success)) { ?>
            <p style="color:green;"><?php echo $success; ?></p>
        <?php } ?>

        <div class="input-row">
            <div class="input-half">
                <input type="text" placeholder="Full Name" name="user_name" id="user_name" required>
            </div>
        </div>

        <div class="input-row">
            <div class="input-half">
                <input type="email" placeholder="Email Address" name="user_email" id="user_email" required>
            </div>
            <div class="input-half">
                <input type="tel" placeholder="Phone Number" name="user_phone_number" id="user_phone_number" pattern="[0-9]{10,15}" title="Please enter a valid phone number." required>
            </div>
        </div>

        <textarea
            name="user_address"
            placeholder="Address"
            id="user_address"
            rows="2"
            style="width:100%; margin-bottom:16px; border-radius:8px; border:1.3px solid #bfc9dc; padding:12px 14px; background:#f7faff; font-size:15px; font-family: 'Segoe UI', sans-serif; resize: vertical; min-height: 52px; max-height: 160px;"
            required
        ></textarea>

        <div class="input-row">
            <div class="input-half">
                <input type="password" placeholder="Password" name="user_password" id="user_password" required>
            </div>
            <div class="input-half">
                <input type="password" placeholder="Confirm Password" name="confirm_user_password" id="confirm_user_password" required>
            </div>
        </div>

        <button type="submit">Register</button>

        <div class="links">
            <span>Already have an account? <a href="login.php">Login</a></span>
        </div>
    </form>
</div>

</body>
</html>
