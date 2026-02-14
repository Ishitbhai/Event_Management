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
    <link rel="stylesheet" href="css/login.css">
    <style>
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