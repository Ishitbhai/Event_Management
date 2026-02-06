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

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_email = isset($_POST['user_email']) ? trim($_POST['user_email']) : '';
    $user_password = isset($_POST['user_password']) ? $_POST['user_password'] : '';

    if (empty($user_email) || empty($user_password)) {
        $error = "Please enter both email and password.";
    } else {
        // Use prepared statement to avoid SQL injection
        // Also select 'user_status' field to check if account is active
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
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
<div class="auth-container">
    <form class="auth-box" action="" method="post">
        <h2>Welcome Back</h2>
        <p>Login to continue</p>

        <!-- Show registration success message -->
        <?php if (!empty($success)) { ?>
            <p style="color:green; margin-bottom:10px;"><?php echo $success; ?></p>
        <?php } ?>

        <!-- Show login error if any -->
        <?php if (!empty($error)) { ?>
            <p style="color:red; margin-bottom:10px;"><?php echo $error; ?></p>
        <?php } ?>

        <input type="email" placeholder="Email Address" name="user_email" value="<?php echo htmlspecialchars($prefill_email); ?>" required>
        <input type="password" placeholder="Password" name="user_password" required>

        <button type="submit">Login</button>

        <div class="links">
            <a href="forgot_password.php">Forgot Password?</a>
            <span>New user? <a href="register.php">Register</a></span>
        </div>
    </form>
</div>
</body>
</html>