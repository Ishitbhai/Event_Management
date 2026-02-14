<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aone Hub</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-light navbar-aone">
  <div class="container position-relative">

    <a class="navbar-brand" href="index.php">
      <span>Aone</span>Hub
    </a>

    <button class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#aoneNavbar"
            aria-controls="aoneNavbar"
            aria-expanded="false"
            aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="aoneNavbar">

      <ul class="navbar-nav me-auto mb-2 mb-lg-0">

        <li class="nav-item">
            <a class="nav-link<?= basename($_SERVER['PHP_SELF'])=='index.php' ? ' active' : '' ?>" href="index.php">Home</a>
        </li>

        <li class="nav-item">
            <a class="nav-link<?= basename($_SERVER['PHP_SELF'])=='events.php' ? ' active' : '' ?>" href="events.php">Events</a>
        </li>

        <li class="nav-item">
            <a class="nav-link<?= basename($_SERVER['PHP_SELF'])=='services.php' ? ' active' : '' ?>" href="services.php">Services</a>
        </li>

        <li class="nav-item">
            <a class="nav-link<?= basename($_SERVER['PHP_SELF'])=='bookings.php' ? ' active' : '' ?>" href="bookings.php">Bookings</a>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle"
             href="#"
             id="navbarMoreDropdown"
             role="button"
             data-bs-toggle="dropdown"
             aria-expanded="false">
              More
          </a>
          <ul class="dropdown-menu" aria-labelledby="navbarMoreDropdown">
            <li><a class="dropdown-item" href="#">About Us</a></li>
            <li><a class="dropdown-item" href="#">Contact</a></li>
          </ul>
        </li>

      </ul>


      <div class="auth-btns">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="btn btn-outline-light me-2" style="border:none;">
                <img src="images/user.png" class="profile-img"/>
                <span>Profile</span>
            </a>
            <a href="logout.php" class="btn btn-logout">
                <img src="images/logout.png" style="width:1.65rem;height:1.65rem;margin-right:8px;">
                <span>Logout</span>
            </a>
        <?php else: ?>
            <a href="login.php" class="btn btn-light">Login</a>
            <a href="register.php" class="btn btn-primary">Register</a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</nav>

<!-- Bootstrap Bundle JS (VERY IMPORTANT) -->
<script src="bootstrap/js/bootstrap.bundle.min.js"></script>

</body>
</html>
            