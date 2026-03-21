<?php
session_start();

// If admin is already logged in, redirect to index
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === 1) {
    header('Location: index.php');
    exit();
}
 
require_once('../database/db_connect.php'); // update the path if needed

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_email = isset($_POST['admin_email']) ? trim($_POST['admin_email']) : '';
    $admin_password = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';

    if ($admin_email && $admin_password) {
        // Fetch admin from users table where user_type='admin'
        $stmt = $conn->prepare("SELECT user_id, user_email, user_name, user_password, user_type FROM users WHERE user_email = ? AND user_type = 'admin' LIMIT 1");
        $stmt->bind_param('s', $admin_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Assuming user_password is stored hashed, for plain text use ($row['user_password'] === $admin_password)
            if (password_verify($admin_password, $row['user_password'])) {
                // Set session for logged in admin
                $_SESSION['is_admin'] = 1;
                $_SESSION['admin_username'] = $row['user_email'];
                $_SESSION['admin_user_name'] = $row['user_name'];
                $_SESSION['user_name'] = $row['user_name'];
                $_SESSION['user_email'] = $row['user_email'];
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['user_type'] = $row['user_type'];
                header('Location: index.php');
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or not an admin user.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <!-- <link rel="stylesheet" href="css/login.css"> -->
    <style>
        * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    height: 100vh;
    background: #f7fafe;
    display: flex;
    justify-content: center;
    align-items: center;
}


.admin-box {
    background: #ffffff;
    padding: 32px 28px;
    width: 340px;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(44,62,80,0.12);
    border: 2px solid #e5e8ec;
}


.admin-box h2 {
    margin-bottom: 22px;
    color: #253858;
    font-size: 1.3rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-align: center;
}

.admin-box input {
    width: 100%;
    padding: 10px 12px;
    margin-bottom: 16px;
    border-radius: 4px;
    border: 1px solid #bbc4d0;
    font-size: 1rem;
    color: #222e3a;
    background: #f2f4f8;
}

.admin-box input:focus {
    border-color: #2c3e50;
    outline: none;
    background: #fff;
}


.admin-box button {
    width: 100%;
    padding: 10px 0;
    border: none;
    background: #253858;
    color: #fff;
    font-size: 16px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
}

.admin-box button:hover {
    background: #16223a;
}


    </style>
</head>
<body>

<div>
    <form class="admin-box" method="post" action="">
        <h2>Admin Login</h2>

        <?php if ($error): ?>
            <div style="color:#b2181e;background:#ffdede;padding:9px 15px;border-radius:8px;margin-bottom:13px;text-align:center"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <input type="email" name="admin_email" placeholder="Admin Email">
        <input type="password" name="admin_password" placeholder="Password">

        <button type="submit">Login</button>
    </form>
</div>

</body>
</html>
