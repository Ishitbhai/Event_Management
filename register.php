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

function is_invalid_phone($phone) {
    // Remove non-numeric characters
    $clean = preg_replace('/\D/', '', $phone);

    // Block by "obvious" patterns
    $blocked = [
        '1234567890',
        '0987654321',
        '1111111111',
        '2222222222',
        '3333333333',
        '4444444444',
        '5555555555',
        '6666666666',
        '7777777777',
        '8888888888',
        '9999999999',
        '0000000000',
    ];

    if (in_array($clean, $blocked)) return true;

    // Check for all same digit
    if (preg_match('/^(\d)\1{9,}$/', $clean)) return true; // e.g. 1111111111, 2222222222

    // Ascending sequence e.g., 1234567890
    if ($clean === implode('', range(1, 9)) . '0') return true;

    // Descending sequence e.g., 9876543210
    if ($clean === '9876543210') return true;

    // Also block two alternating digits repeated
    if (preg_match('/^(\d)(\d)\1\2+$/', $clean)) return true;

    return false;
}

$password_error_message = '';
$phone_error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get form data and sanitize
    $user_name = mysqli_real_escape_string($conn, $_POST['user_name']);
    $user_email = mysqli_real_escape_string($conn, $_POST['user_email']);
    $user_phone_number = mysqli_real_escape_string($conn, $_POST['user_phone_number']);
    $user_address = mysqli_real_escape_string($conn, $_POST['user_address']);
    $user_password = $_POST['user_password'];
    $confirm_user_password = $_POST['confirm_user_password'];
    $user_token = bin2hex(random_bytes(16)); // secure token

    // Password validation: min 8, 1 upper, 1 lower, 1 digit, 1 special character
    $password_valid = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $user_password);

    // Phone validation (block patterns)
    $phone_invalid = is_invalid_phone($user_phone_number);

    // Check if email already exists 
    $check_email_sql = "SELECT user_id FROM users WHERE user_email = '$user_email' LIMIT 1";
    $check_email_result = mysqli_query($conn, $check_email_sql);

    if (mysqli_num_rows($check_email_result) > 0) {
        $error = "Email already exists!";
    } elseif ($user_password !== $confirm_user_password) {
        $password_error_message = "Passwords do not match!";
    } elseif (!$password_valid) {
        $password_error_message = "Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one digit, and one special character.";
    } elseif ($phone_invalid) {
        $phone_error_message = "Please enter a valid phone number. Sequential, repeated or obviously fake phone numbers are not allowed.";
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
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
    <script src="js/jquery-4.0.0.min.js"></script>
    <script src="js/jquery.validate.min.js"></script>
    <script src="js/register.js"></script>
</head>
<body>

<div class="auth-container">

    <form class="auth-box" id="registerForm" action="" method="post" novalidate>
        <h2 class="mb-2">Create Account</h2>
        <p class="mb-4">Join Aone Hub today</p>

        <!-- Display main messages -->
        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger py-2"><?php echo $error; ?></div>
        <?php } elseif (!empty($success)) { ?>
            <div class="alert alert-success py-2"><?php echo $success; ?></div>
        <?php } ?>

        <div class="input-row row">
            <div class="input-half col-12 px-0">
                <input type="text" class="form-control" placeholder="Full Name" name="user_name" id="user_name" required value="<?php echo isset($_POST['user_name']) ? htmlspecialchars($_POST['user_name']) : ''; ?>">
            </div>
        </div>

        <div class="input-row row">
            <div class="input-half col-12 col-md-6 mb-3 mb-md-0 px-0">
                <input type="email" class="form-control" placeholder="Email Address" name="user_email" id="user_email" required value="<?php echo isset($_POST['user_email']) ? htmlspecialchars($_POST['user_email']) : ''; ?>">
            </div>
            <div class="input-half col-12 col-md-6 px-0">
                <input type="tel" class="form-control" placeholder="Phone Number" name="user_phone_number" id="user_phone_number" pattern="[0-9]{10,15}" title="Please enter a valid phone number." required value="<?php echo isset($_POST['user_phone_number']) ? htmlspecialchars($_POST['user_phone_number']) : ''; ?>">
                <?php if(!empty($phone_error_message)): ?>
                    <div class="text-danger mt-1" style="font-size:13px;"><?php echo $phone_error_message; ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Make address field stretch full width like two column input-row -->
        <div class="input-row row">
            <div class="input-half col-12 px-0">
                <input
                    type="text"
                    name="user_address"
                    placeholder="Address"
                    id="user_address"
                    class="form-control"
                    required
                    value="<?php echo isset($_POST['user_address']) ? htmlspecialchars($_POST['user_address']) : ''; ?>"
                >
            </div>
        </div>

        <div class="input-row row">
            <div class="input-half col-12 col-md-6 mb-3 mb-md-0 px-0">
                <input type="password" class="form-control" placeholder="Password" name="user_password" id="user_password" required pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$" title="Min 8 chars, one uppercase, one lowercase, one digit, one special character.">
                <?php if (!empty($password_error_message)): ?>
                    <div class="text-danger mt-1" style="font-size:13px;"><?php echo $password_error_message; ?></div>
                <?php endif; ?>
            </div>
            <div class="input-half col-12 col-md-6 px-0">
                <input type="password" class="form-control" placeholder="Confirm Password" name="confirm_user_password" id="confirm_user_password" required>
            </div>
        </div>

        <div class="input-row row">
            <div class="input-half col-12 px-0">
                <button type="submit" class="btn btn-primary w-100 mt-3">Register</button>
            </div>
        </div>

        <div class="links text-center mt-9">
            <span>Already have an account? <a href="login.php">Login</a></span>
        </div>
    </form>
</div>

<!-- Add Bootstrap JS and dependencies at the end -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
