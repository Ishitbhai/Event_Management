<?php
// Start session and include DB connection
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'database/db_connect.php';
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Handle messages from registration process
$success = '';
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Pre-fill login email if available
$prefill_email = '';
if (isset($_SESSION['login_email'])) {
    $prefill_email = $_SESSION['login_email'];
    unset($_SESSION['login_email']);
}

// Initialize error variable
$error = "";
$login_success = false;

// AJAX login support
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax_login'])) {
    header("Content-Type: application/json");
    $user_email = isset($_POST['user_email']) ? trim($_POST['user_email']) : '';
    $user_password = isset($_POST['user_password']) ? $_POST['user_password'] : '';

    if (empty($user_email) || empty($user_password)) {
        echo json_encode(['success' => false, 'error' => "Please enter both email and password."]);
        exit;
    } else {
        $stmt = $conn->prepare("SELECT user_id, user_name,user_type, user_email, user_password, user_status FROM users WHERE user_email = ? LIMIT 1");
        $stmt->bind_param("s", $user_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // Check if user's status is 'active'
            if (isset($user['user_status']) && strtolower($user['user_status']) === 'active') {
                // Verify password (hashed in DB)
                if (password_verify($user_password, $user['user_password'])) {
                    // Set last login to current timestamp
                    $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $update_stmt->bind_param("i", $user['user_id']);
                    $update_stmt->execute();
                    $update_stmt->close();

                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_name'] = $user['user_name'];
                    $_SESSION['user_email'] = $user['user_email'];
                    $_SESSION['user_type'] = $user['user_type'];

                    echo json_encode(['success' => true, 'redirect' => 'index.php']);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'error' => "Incorrect password."]);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'error' => "Account not verified. Please check your email for the activation link."]);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'error' => "No user found with that email address."]);
            exit;
        }
        $stmt->close();
    }
}

// For normal (non-AJAX) fallback, keep server rendered error, but don't redirect if there is an error
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['ajax_login'])) {
    $user_email = isset($_POST['user_email']) ? trim($_POST['user_email']) : '';
    $user_password = isset($_POST['user_password']) ? $_POST['user_password'] : '';

    if (empty($user_email) || empty($user_password)) {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, user_name,user_type, user_email, user_password, user_status FROM users WHERE user_email = ? LIMIT 1");
        $stmt->bind_param("s", $user_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // Check if user's status is 'active'
            if (isset($user['user_status']) && strtolower($user['user_status']) === 'active') {
                // Verify password (hashed in DB)
                if (password_verify($user_password, $user['user_password'])) {
                    // Set last login to current timestamp
                    $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $update_stmt->bind_param("i", $user['user_id']);
                    $update_stmt->execute();
                    $update_stmt->close();

                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_name'] = $user['user_name'];
                    $_SESSION['user_email'] = $user['user_email'];
                    $_SESSION['user_type'] = $user['user_type'];

                    // Redirect to main page or dashboard
                    header("Location: index.php");
                    exit;
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "Account not verified. Please check your email for the activation link.";
            }
        } else {
            $error = "No user found with that email address.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AOne Hub</title>
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- <link rel="stylesheet" href="css/login.css"> -->
    <style>
        body {
    min-height: 100vh;
    background: linear-gradient(135deg, #9796f0 0%, #fbc7d4 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
    /* Smooth bg animation */
    animation: bgMove 12s ease-in-out infinite alternate;
}

@keyframes bgMove {
    0% {
        background-position: 0% 50%;
    }
    100% {
        background-position: 100% 50%;
    }
}

.auth-container {
    width: 100vw;
    min-height: 100vh;
    padding: 0;
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    /* Fade-in animation */
    opacity: 0;
    animation: fadeIn 1s ease 0.2s forwards;
    box-sizing: border-box;
}

@keyframes fadeIn {
    to {
        opacity: 1;
    }
}

.auth-box {
    background: #fff;
    padding: 2.5rem 0; /* Remove horizontal padding (symmetric 0 left/right) */
    border-radius: 1rem;
    max-width: 410px;
    width: 100%;
    box-shadow: 0 6px 36px rgba(38, 38, 94, 0.14);
    border: 0;
    /* Slide-in animation */
    transform: translateY(60px) scale(0.97);
    opacity: 0;
    animation: boxAppear 0.9s cubic-bezier(.6, .11, .42, .98) 0.3s forwards;
    margin: 0 auto;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
}

@keyframes boxAppear {
    to {
        transform: none;
        opacity: 1;
    }
}

.auth-box form,
.auth-box > form {
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
@keyframes fadeUp {
    to {
        opacity: 1;
        transform: none;
    }
}

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
.input-row .input-half input {
    width: 100%;
    box-sizing: border-box;
    margin-left: 0;
    margin-right: 0;
    padding-left: 0;
    padding-right: 0;
}

/* Remove textarea styles: Address is now type="text" input, uses input styles */

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

/* Link styling */
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
.fp-success {
    color: #25c685;
    font-size: 1rem;
    margin-top: 0.2em;
}
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
.fp-btn:hover {
    background: #b06ab3;
}

.fp-mt20 { margin-top: 1.4rem; }

/* Bootstrap-style responsiveness */
@media (max-width: 992px) {
    .auth-box {
        max-width: 500px;
        padding: 2rem 0; /* Remove horizontal padding for symmetry */
    }
    .auth-box form,
    .auth-box > form {
        width: 98%;
    }
}
@media (max-width: 768px) {
    .auth-box {
        max-width: 98vw;
        padding: 1.5rem 0; /* Remove horizontal padding */
        border-radius: 0.7rem;
    }
    .auth-box form,
    .auth-box > form {
        width: 99%;
    }
    .input-row {
        flex-direction: column;
        gap: 0.25rem;
        margin-bottom: 1rem;
    }
    .auth-box h2 {
        font-size: 1.3rem;
    }
    .auth-box p {
        font-size: 0.97rem;
        margin-bottom: 1.2rem;
    }
}
@media (max-width: 480px) {
    .auth-box {
        max-width: 99vw;
        min-width: 0;
        padding: 1rem 0; /* Zero side padding */
        border-radius: 0.5rem;
    }
    .auth-box form,
    .auth-box > form {
        width: 99vw;
    }
    .auth-box h2 {
        font-size: 1rem;
    }
    .auth-box p,
    .links {
        font-size: 0.88rem;
    }
    .fp-btn,
    .auth-box button,
    .auth-box input[type="submit"] {
        font-size: 0.98rem;
        padding: 0.65rem;
        border-radius: 0.95rem;
        margin-left: 0;
        margin-right: 0;
    }
    .fp-input,
    .fp-input-reset,
    .fp-input-reset-confirm {
        font-size: 0.94rem;
        padding: 0.5rem 0.8rem;
        width: 100%;
        margin-left: 0;
        margin-right: 0;
    }
}
@media (max-height: 480px) {
    body {
        flex-direction: column;
        padding: 5vw 0;
    }
}

.auth-box {
    padding-left: 1.2rem !important;
    padding-right: 1.2rem !important;
}
@media (max-width: 768px) {
    .auth-box {
        padding-left: 0.7rem !important;
        padding-right: 0.7rem !important;
    }
}
.input-row.row {
    margin-left: 0;
    margin-right: 0;
}
.auth-box .row {
    --bs-gutter-x: 0 !important;
}
        /* Reduce margin bottom for input fields */
        .input-row .mb-3 {
            margin-bottom: 0.7rem !important;
        }
        .input-row .form-control {
            padding-top: .5rem !important;
            padding-bottom: .5rem !important;
        }
        /* Reduce margin for login button */
        .input-row .mt-3 {
            margin-top: 0.85rem !important;
        }
        /* Remove excessive margin on the form title and subtitle if needed */
        .auth-box .mb-2,
        .auth-box .mb-4 {
            margin-bottom: 0.85rem !important;
        }
        /* Make links in one line, style separated by | for aesthetics */
        .login-links-inline {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.7rem;
            font-size: 0.96rem;
            margin-top: 1.2rem;
        }
        .login-links-inline a {
            margin-bottom: 0 !important;
        }
        @media (max-width: 430px) {
            .auth-box {
                padding-left: .5rem !important;
                padding-right: .5rem !important;
                max-width: 95vw;
            }
            .login-links-inline {
                flex-direction: column;
                gap: 0.15rem;
            }
        }
    </style>
</head>
<body>

<div class="auth-container">
    <form class="auth-box" id="loginForm" action="" method="post" novalidate autocomplete="off">
        <h2 class="mb-2">Welcome Back</h2>
        <p class="mb-4">Login to continue</p>

        <!-- Show registration success message -->
        <?php if (!empty($success)) { ?>
            <div class="alert alert-success py-2" id="success-message"><?php echo $success; ?></div>
        <?php } ?>

        <!-- Show login error if any -->
        <?php if (!empty($error)) { ?>
            <div class="alert alert-danger py-2" id="error-message"><?php echo $error; ?></div>
        <?php } ?>

        <div class="alert alert-danger py-2 d-none" id="ajax-error-message"></div>
        <div class="input-row row">
            <div class="input-half col-12 px-0 mb-3">
                <input type="email" class="form-control" placeholder="Email Address" name="user_email" id="user_email"
                       required value="<?php echo htmlspecialchars($prefill_email); ?>">
            </div>
        </div>
        <div class="input-row row">
            <div class="input-half col-12 px-0 mb-3">
                <input type="password" class="form-control" placeholder="Password" name="user_password" id="user_password"
                       required>
            </div>
        </div>

        <div class="input-row row">
            <div class="input-half col-12 px-0">
                <button type="submit" class="btn btn-primary w-100 mt-3" id="loginBtn">Login</button>
            </div>
        </div>

        <div class="login-links-inline mt-4 text-center">
            <a href="forgot_password.php">Forgot Password?</a>
            <span>|</span>
            <span>New user? <a href="register.php">Register</a></span>
        </div>
    </form>
</div>

<!-- Add Bootstrap JS and dependencies at the end -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var loginForm = document.getElementById("loginForm");
    if (loginForm) {
        loginForm.addEventListener('submit', function(e){
            // Fallback for browsers with no JS: allow normal submit
            if (window.FormData) {
                e.preventDefault();

                // Hide any previous errors
                var ajaxError = document.getElementById('ajax-error-message');
                if (ajaxError) {
                    ajaxError.classList.add('d-none');
                    ajaxError.innerText = "";
                }

                var btn = document.getElementById('loginBtn');
                if(btn) btn.disabled = true;

                var formData = new FormData(loginForm);
                formData.append('ajax_login', '1');
                fetch(window.location.href, {
                    method: "POST",
                    body: formData,
                })
                .then(response => response.json())
                .then(data => {
                    if (btn) btn.disabled = false;
                    if (data.success) {
                        // Redirect if successful
                        window.location = data.redirect;
                    } else if (data.error) {
                        // Show error without refreshing
                        if (ajaxError) {
                            ajaxError.classList.remove('d-none');
                            ajaxError.innerText = data.error;
                        }
                    }
                })
                .catch(function(){
                    if (btn) btn.disabled = false;
                    if (ajaxError) {
                        ajaxError.classList.remove('d-none');
                        ajaxError.innerText = "An error occurred. Please try again.";
                    }
                });
            }
        });
    }
});
</script>
</body>
</html>