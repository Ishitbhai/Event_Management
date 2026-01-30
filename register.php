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
    
    <form class="auth-box" id="registerForm" action="login.php" method="post" novalidate>
        <h2>Create Account</h2>
        <p>Join EventHub today</p>

        <div class="input-row">
            <div class="input-half">
                <input type="text" placeholder="Full Name" name="fullname" id="fullname">
            </div>
            <div class="input-half">
                <input type="text" placeholder="Username" name="username" id="username">
            </div>
        </div>

        <div class="input-row">
            <div class="input-half">
                <input type="email" placeholder="Email Address" name="email" id="email">
            </div>
            <div class="input-half">
                <input type="tel" placeholder="Phone Number" name="phone" id="phone" pattern="[0-9]{10,15}" title="Please enter a valid phone number.">
            </div>
        </div>

        <textarea name="address" placeholder="Address" id="address" rows="2" style="width:100%; margin-bottom:16px; border-radius:8px; border:1.3px solid #bfc9dc; padding:12px 14px; background:#f7faff; font-size:15px; font-family: 'Segoe UI', sans-serif;"></textarea>

        <input type="date" placeholder="Date of Birth" name="dob" id="dob">

        <div class="input-row">
            <div class="input-half">
                <input type="password" placeholder="Password" name="password" id="password">
            </div>
            <div class="input-half">
                <input type="password" placeholder="Confirm Password" name="confirm_password" id="confirm_password">
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
