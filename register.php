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
    ?>
    <script>
        window.location.href="index.php";
    </script>
    <?php
    exit();
}

// Load PHPMailer (make sure composer installed it)
require 'vendor/autoload.php';

function is_invalid_phone($phone) {
    $clean = preg_replace('/\D/', '', $phone);
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
    if (preg_match('/^(\d)\1{9,}$/', $clean)) return true;
    if ($clean === implode('', range(1, 9)) . '0') return true;
    if ($clean === '9876543210') return true;
    if (preg_match('/^(\d)(\d)\1\2+$/', $clean)) return true;
    return false;
}

$password_error_message = '';
$phone_error_message = '';
$profile_picture_error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get form data and sanitize
    $user_name = mysqli_real_escape_string($conn, $_POST['user_name']);
    $user_email = mysqli_real_escape_string($conn, $_POST['user_email']);
    $user_phone_number = mysqli_real_escape_string($conn, $_POST['user_phone_number']);
    $user_address = mysqli_real_escape_string($conn, $_POST['user_address']);
    $user_password = $_POST['user_password'];
    $confirm_user_password = $_POST['confirm_user_password'];
    $user_token = bin2hex(random_bytes(16)); // secure token

    // Profile picture logic (optional)
    $profile_picture_uploaded = false;
    $profile_picture_filename_in_db = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $allowed_exts = ['jpg', 'jpeg', 'png'];
        $max_size = 2 * 1024 * 1024;
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));

        if (!in_array($file_type, $allowed_types) || !in_array($file_ext, $allowed_exts)) {
            $profile_picture_error_message = "Profile picture must be a JPG, JPEG, or PNG file.";
        } elseif ($file_size > $max_size) {
            $profile_picture_error_message = "Profile picture must not exceed 2MB.";
        }
    }

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
    } elseif (!empty($profile_picture_error_message)) {
        $error = $profile_picture_error_message;
    } else {
        // Handle optional profile picture upload (store only filename in DB if uploaded)
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE && empty($profile_picture_error_message)) {
            $uploads_dir = 'images';
            if (!file_exists($uploads_dir)) {
                mkdir($uploads_dir, 0755, true);
            }
            $unique_name = uniqid('profile_', true) . '.' . $file_ext;
            $profile_picture_full_path = $uploads_dir . '/' . $unique_name;
            if (move_uploaded_file($file_tmp, $profile_picture_full_path)) {
                $profile_picture_uploaded = true;
                // Store only the filename in the database (no path)
                $profile_picture_filename_in_db = mysqli_real_escape_string($conn, $unique_name);
            } else {
                $error = "Failed to upload profile picture.";
            }
        }

        // Hash the password
        $hashed_password = password_hash($user_password, PASSWORD_DEFAULT);

        // Insert into database: if image uploaded, include the filename, else leave NULL/empty
        if ($profile_picture_filename_in_db !== null) {
            $sql = "INSERT INTO users (user_name, user_email, user_phone_number, user_address, user_password, user_token, profile_picture)
                    VALUES ('$user_name', '$user_email', '$user_phone_number', '$user_address', '$hashed_password', '$user_token', '$profile_picture_filename_in_db')";
        } else {
            $sql = "INSERT INTO users (user_name, user_email, user_phone_number, user_address, user_password, user_token)
                    VALUES ('$user_name', '$user_email', '$user_phone_number', '$user_address', '$hashed_password', '$user_token')";
        }

        if (empty($error) && mysqli_query($conn, $sql)) {
            // Send verification email using PHPMailer
            $mail = new PHPMailer(true);
            try {
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

        } elseif (empty($error)) {
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
    <script src="js/jquery-4.0.0.min.js"></script>
    <script src="js/jquery.validate.min.js"></script>
    <script>
        $(document).ready(function(){
    $.validator.addMethod("strongPassword", function(value, element) {
        return this.optional(element)
            || /[a-z]/.test(value)    
            && /[A-Z]/.test(value)    
            && /[0-9]/.test(value) 
            && /[^A-Za-z0-9]/.test(value); 
    }, "Password must contain at least one lowercase letter, one uppercase letter, one digit, and one special character.");

    $('#registerForm').validate({
        rules: {
            fullname: {
                required: true,
                minlength: 3
            },
            username: {
                required: true,
                minlength: 3,
                maxlength: 20
            },
            email: {
                required: true,
                email: true
            },
            phone: {
                required: true,
                digits: true,
                minlength: 10,
                maxlength: 15
            },
            address: {
                required: true,
                minlength: 5
            },
            dob: {
                required: true,
                date: true
            },
            password: {
                required: true,
                minlength: 6,
                strongPassword: true
            },
            confirm_password: {
                required: true,
                equalTo: "#password"
            }
            // profile_picture optional
        },
        messages: {
            fullname: {
                required: "Please enter your full name",
                minlength: "Full name must be at least 3 characters"
            },
            username: {
                required: "Please enter a username",
                minlength: "Username must be at least 3 characters",
                maxlength: "Username must be less than 20 characters"
            },
            email: {
                required: "Please enter your email address",
                email: "Please enter a valid email address"
            },
            phone: {
                required: "Please provide your phone number",
                digits: "Phone number must be digits only",
                minlength: "Phone must be at least 10 digits",
                maxlength: "Phone must be no more than 15 digits"
            },
            address: {
                required: "Please provide your address",
                minlength: "Address must be at least 5 characters"
            },
            dob: {
                required: "Please provide your date of birth",
                date: "Please enter a valid date"
            },
            password: {
                required: "Please provide a password",
                minlength: "Password must be at least 6 characters",
                strongPassword: "Password must contain at least one lowercase letter, one uppercase letter, one digit, and one special character."
            },
            confirm_password: {
                required: "Please confirm your password",
                equalTo: "Passwords do not match"
            }
        },
        errorElement: 'span',
        errorClass: 'error',
        highlight: function(element) {
            $(element).addClass('error');
        },
        unhighlight: function(element) {
            $(element).removeClass('error');
        }
    });
});
    </script>
    <style>
    body {
    min-height: 100vh;
    background: linear-gradient(135deg, #9796f0 0%, #fbc7d4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
    animation: bgMove 12s ease-in-out infinite alternate;
}
@keyframes bgMove { 0% { background-position: 0% 50%; } 100% { background-position: 100% 50%; } }
.auth-container {
    width: 100vw;
    min-height: 100vh;
    padding: 0;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    animation: fadeIn 1s ease 0.2s forwards;
    box-sizing: border-box;
}
@keyframes fadeIn { to { opacity: 1; } }
.auth-box {
    background: #fff;
    padding: 2.5rem 0;
    border-radius: 1rem;
    max-width: 410px;
    width: 100%;
    box-shadow: 0 6px 36px rgba(38, 38, 94, 0.14);
    border: 0;
    transform: translateY(60px) scale(0.97);
    opacity: 0;
    animation: boxAppear 0.9s cubic-bezier(.6, .11, .42, .98) 0.3s forwards;
    margin: 0 auto;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
}
@keyframes boxAppear { to { transform: none; opacity: 1; } }
.auth-box form, .auth-box > form {
    width: 90%;
    margin-left: auto;
    margin-right: auto;
    box-sizing: border-box;
}
.auth-box h2 {
    font-size: 2.1rem;
    font-weight: 700;
    color: #333b4a;
    margin-bottom: 0.3em;
    text-align: center;
    font-family: 'Roboto Slab', serif;
    letter-spacing: 0.5px;
    transform: translateY(20px);
    animation: fadeUp 0.76s 0.6s forwards;
}
.auth-box p {
    color: #596376;
    font-size: 1rem;
    margin-bottom: 2rem;
    text-align: center;
    opacity: 0;
    transform: translateY(20px);
    animation: fadeUp 0.75s 0.75s forwards;
}
@keyframes fadeUp { to { opacity: 1; transform: none; } }
.input-row {
    display: flex;
    gap: 1rem;
    width: 100%;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
}
.input-row .input-half {
    flex: 1 1 0;
    min-width: 0;
}
.input-row .input-half input, .input-row .input-half label {
    width: 100%;
    box-sizing: border-box;
    margin-left: 0;
    margin-right: 0;
    padding-left: 0;
    padding-right: 0;
}
/* Animations for all input fields */
.auth-box input[type="text"],
.auth-box input[type="email"],
.auth-box input[type="password"],
.auth-box input[type="tel"] {
    border-radius: 0.5rem;
    border: 1px solid #ced4da;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    transition: border-color 0.18s, box-shadow 0.18s;
    background: #f5f8fc;
    color: #282828;
    box-sizing: border-box;
    margin-left: 0;
    margin-right: 0;
    width: 100%;
}
.auth-box input[type="text"]:focus,
.auth-box input[type="email"]:focus,
.auth-box input[type="password"]:focus,
.auth-box input[type="tel"]:focus {
    border-color: #7349e2;
    background: #eef2fa;
    outline: none;
    box-shadow: 0 2px 12px rgba(94, 92, 230, 0.12);
}
.auth-box button,
.auth-box input[type="submit"] {
    width: 100%;
    padding: 0.85rem;
    border: none;
    border-radius: 2rem;
    font-size: 1.05rem;
    background: linear-gradient(90deg, #5e5ce6, #fa709a 99%);
    color: #fff;
    font-weight: 600;
    transition: background 0.22s, transform 0.15s, box-shadow 0.2s;
    margin-top: 0.25rem;
    box-shadow: 0 3px 16px rgba(254, 98, 131, 0.09);
    box-sizing: border-box;
    margin-left: 0;
    margin-right: 0;
    display: block;
}
.auth-box button:hover,
.auth-box input[type="submit"]:hover {
    background: linear-gradient(90deg, #34307a 0%, #d53369 100%);
    transform: translateY(-2px) scale(1.03);
    opacity: 0.97;
    box-shadow: 0 5px 18px rgba(117, 82, 235, 0.14);
}
.links {
    margin-top: 1.2rem;
    font-size: 0.96rem;
    text-align: center;
    color: #868e96;
    opacity: 0;
    animation: fadeIn 1s 1.2s forwards;
}
.links a {
    color: #5e5ce6;
    text-decoration: underline;
    font-weight: 600;
    transition: color 0.18s;
    word-break: break-word;
    margin-left: 4px;
}
.links a:hover {
    color: #ae47f3;
    text-decoration: underline;
    letter-spacing: 0.7px;
}
/* Validation and error messages */
.error,
.fp-error {
    color: #e53935;
    font-size: 1rem;
    margin: 0.2em 0 0.6em 0;
    display: block;
    transition: color 0.22s;
}
input.error,
textarea.error {
    border-color: #e53935 !important;
    background: #fff5f6;
}
.fp-success { color: #25c685; font-size: 1rem; margin-top: 0.2em; }
.fp-input,
.fp-input-reset,
.fp-input-reset-confirm {
    border-radius: 0.45rem;
    padding: 0.73rem 1rem;
    border: 1px solid #bdc7df;
    font-size: 1rem;
    background: #f7f9fa;
    width: 100%;
    margin-bottom: 1.2rem;
    margin-top: 0.7em;
    margin-left: 0;
    margin-right: 0;
    box-sizing: border-box;
}
.fp-btn {
    width: 100%;
    padding: 0.7rem;
    border-radius: 1.3rem;
    font-size: 1rem;
    background: #6a82fb;
    color: #fff;
    border: none;
    font-weight: 600;
    margin-top: 0.6em;
    box-shadow: 0 2px 6px rgba(100,100,120,0.12);
    transition: background 0.16s;
    margin-left: 0;
    margin-right: 0;
    box-sizing: border-box;
}
.fp-btn:hover { background: #b06ab3; }
.fp-mt20 { margin-top: 1.4rem; }
@media (max-width: 992px) {
    .auth-box { max-width: 500px; padding: 2rem 0; }
    .auth-box form, .auth-box > form { width: 98%; }
}
@media (max-width: 768px) {
    .auth-box { max-width: 98vw; padding: 1.5rem 0; border-radius: 0.7rem; }
    .auth-box form, .auth-box > form { width: 99%; }
    .input-row { flex-direction: column; gap: 0.25rem; margin-bottom: 1rem; }
    .auth-box h2 { font-size: 1.3rem; }
    .auth-box p { font-size: 0.97rem; margin-bottom: 1.2rem; }
}
@media (max-width: 480px) {
    .auth-box { max-width: 99vw; min-width: 0; padding: 1rem 0; border-radius: 0.5rem; }
    .auth-box form, .auth-box > form { width: 99vw; }
    .auth-box h2 { font-size: 1rem; }
    .auth-box p, .links { font-size: 0.88rem; }
    .fp-btn, .auth-box button, .auth-box input[type="submit"] { font-size: 0.98rem; padding: 0.65rem; border-radius: 0.95rem; margin-left: 0; margin-right: 0; }
    .fp-input, .fp-input-reset, .fp-input-reset-confirm { font-size: 0.94rem; padding: 0.5rem 0.8rem; width: 100%; margin-left: 0; margin-right: 0; }
}
@media (max-height: 480px) {
    body { flex-direction: column; padding: 5vw 0; }
}
.auth-box { padding-left: 1.2rem !important; padding-right: 1.2rem !important; }
@media (max-width: 768px) {
    .auth-box { padding-left: 0.7rem !important; padding-right: 0.7rem !important; }
}
.input-row.row { margin-left: 0; margin-right: 0; }
.auth-box .row { --bs-gutter-x: 0 !important; }
    </style>
</head>
<body>

<div class="auth-container">

    <!-- Note: multipart/form-data is needed for image upload -->
    <form class="auth-box" id="registerForm" action="" method="post" enctype="multipart/form-data" novalidate>
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

        <!-- Profile Picture input, optional -->
        <div class="input-row row">
            <div class="input-half col-12 px-0">
                <label for="profile_picture" style="font-size: 1rem;">Profile Picture</label>
                <input type="file" class="form-control" name="profile_picture" id="profile_picture" accept=".jpg,.jpeg,.png" style="background:#fff;">
                <?php if(!empty($profile_picture_error_message)): ?>
                    <div class="text-danger mt-1" style="font-size:13px;"><?php echo $profile_picture_error_message; ?></div>
                <?php endif; ?>
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
