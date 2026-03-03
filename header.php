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
     <style>
      .navbar-aone {
    background: #fff;
    box-shadow: 0 3px 16px -8px #25336544;
    padding: 0.45rem 0 0.45rem 0;
    font-family: 'Segoe UI', 'Montserrat', Arial, sans-serif;
    letter-spacing: 0.018em;
}

/* IMPORTANT FIX */
.navbar-light .navbar-toggler {
    border: none;
}

.navbar-light .navbar-toggler-icon {
    background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3e%3cpath stroke='rgba%2842,46,91,0.9%29' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
}

.navbar-brand span {
    font-weight: 800;
    color: #2A2E5B;
    font-size: 1.45rem;
    letter-spacing: -0.01em;
    text-shadow: 0 1px 5px #ffe36f1c;
}

.navbar-brand {
    font-size: 1.45rem;
    color: #4268ba !important;
    letter-spacing: 0.02em;
    display: flex;
    align-items: center;
}

.navbar-brand::after {
    content: '';
    display: inline-block;
    margin-left: 9px;
    width: 8px;
    height: 8px;
    background: #ffe36f;
    border-radius: 50%;
    box-shadow: 0 1px 6px #ffe36f60;
}

.navbar-nav .nav-link {
    color: #2c3875 !important;
    font-weight: 500;
    margin-right: 10px;
    padding: 6px 22px !important;
    border-radius: 8px;
    transition: background 0.13s, color 0.16s;
    letter-spacing: 0.006em;
}

.navbar-nav .nav-link.active,
.navbar-nav .nav-link:focus,
.navbar-nav .nav-link:hover {
    background: linear-gradient(94deg, #2A2E5B17 35%, #ffe36f26 100%);
    color: #406be0 !important;
}

.dropdown-menu {
    background: #f6f8fa;
    border-radius: 0.58rem;
    min-width: 168px;
    border: 1px solid #d1d9ef;
    box-shadow: 0 12px 40px -14px #2e4a8a29;
    padding: 0.35rem 0.15rem;
}

.dropdown-item {
    color: #314474;
    font-weight: 500;
    border-radius: 0.53rem;
    padding: 0.475rem 1.15rem;
    transition: background 0.13s, color 0.15s;
}

.dropdown-item:hover,
.dropdown-item:focus {
    background: #ffe36f54;
    color: #1d2f55;
}


.navbar-collapse {
    position: relative;
}

.auth-btns {
    position: absolute;
    right: 0;
    top: 56%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.auth-btns .btn {
    font-weight: 600;
    border-radius: 22px;
    padding: 5px 20px;
    display: flex;
    align-items: center;
    background: #f2f4fe;
    color: #223468;
    border: 1px solid #dae2fb;
    box-shadow: 0 1px 8px -4px #3356ac1a;
    margin-right: 2px;
    transition: background 0.13s, color 0.12s, border 0.13s;
}

.auth-btns .btn:hover,
.auth-btns .btn:focus {
    background: #ffe36fa6;
    color: #223166;
    border: 1px solid #ffe36f;
}

.profile-img {
    width: 2.15rem;
    height: 2.15rem;
    object-fit: cover;
    background: #dde1f6;
    border-radius: 50%;
    margin-right: 8px;
    border: 2px solid #ffe36f55;
}

.btn-logout {
    background: transparent !important;
    color: #f1674b !important;
    border: 1px solid #f1674b;
}

.btn-logout:hover,
.btn-logout:focus {
    background: #ffe7dfb0 !important;
    color: #a02d0a !important;
    border: 1px solid #f1674b;
}

@media (max-width: 991.98px) {
    .navbar-aone {
        padding: 0.67rem 0.28rem;
    }

    .auth-btns {
        position: static;
        transform: none;
        justify-content: flex-start;
        margin-top: 0.7rem;
        margin-bottom: 0.5rem;
    }

}

@media (max-width: 767.98px) {
    .navbar-brand span,
    .navbar-brand {
        font-size: 1.1rem;
    }

    .profile-img {
        width: 1.5rem;
        height: 1.5rem;
        margin-right: 5px;
    }

    .auth-btns .btn {
        padding: 4px 11px;
        font-size: 0.98em;
    }

    .navbar-nav .nav-link {
        padding: 6px 11px !important;
        font-size: 0.97em;
        margin-right: 2px;
    }
}

@media (max-width: 575.98px) {
    .navbar-brand span,
    .navbar-brand {
        font-size: 0.93rem;
    }

    .container.position-relative, .container {
        padding-left: 5px;
        padding-right: 5px;
        max-width: 100vw;
    }
}
.footer-classic {
    background: #23263b;
    color: #eaeaea;
    padding: 46px 0 10px 0;
    font-family: 'Segoe UI', 'Montserrat', Arial, sans-serif;
    border-top: 1px solid #323348;
    font-size: 1.04rem;
}
.footer-classic .container {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: flex-start;
    max-width: 1150px;
    margin: 0 auto;
    gap: 28px 12px;
}
.footer-classic-col {
    flex: 1 1 180px;
    min-width: 160px;
    margin-bottom: 28px;
}
.footer-classic-logo {
    font-size: 1.75rem;
    font-weight: 700;
    color: #fafafa;
    letter-spacing: 0.07em;
    margin-bottom: 10px;
    text-shadow: 0 2px 12px #1d1d2223;
}
.footer-classic-desc {
    color: #cfcfde;
    font-size: 1.03rem;
    margin-bottom: 1rem;
    line-height: 1.48;
    max-width: 350px;
}
.footer-classic-links, .footer-classic-social {
    list-style: none;
    padding: 0;
    margin: 0;
}
.footer-classic-links li {
    margin-bottom: 0.66em;
}
.footer-classic-links a {
    color: #bfc2cc;
    text-decoration: none;
    font-weight: 500;
    font-size: 1.06rem;
    transition: color 0.15s;
    letter-spacing: 0.01em;
}
.footer-classic-links a:hover {
    color: #fff;
    text-decoration: underline;
}
.footer-classic-col h5 {
    font-size: 1.13rem;
    color: #f3efc7;
    font-weight: 600;
    margin-bottom: 0.68em;
    letter-spacing: 0.03em;
    text-shadow: none;
}
.footer-classic-social {
    display: flex;
    gap: 0.68em;
    margin-top: 5px;
}
.footer-classic-social a {
    color: #c2c2d2;
    font-size: 1.17rem;
    background: #23263b;
    border: 1px solid #33354a;
    padding: 7px 10px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    transition: color 0.15s, border 0.14s, background 0.13s;
}
.footer-classic-social a:hover {
    color: #fff;
    border: 1px solid #cfcfde;
    background: #2d3053;
}
.footer-copyright-classic {
    text-align: center;
    margin-top: 18px;
    font-size: 0.99rem;
    color: #9ca2b6;
    letter-spacing: 0.03em;
    padding-bottom: 12px;
}
.footer-contact-list span {
    color: #bcbcbf;
    font-size: 1.01em;
    margin-right: 6px;
    vertical-align: middle;
}
/* Responsiveness */
@media (max-width: 980px) {
    .footer-classic .container {
    flex-wrap: wrap;
    gap: 22px 0;
    }
    .footer-classic-col {
    margin-bottom: 10px;
    min-width: 50%;
    }
}
@media (max-width: 700px) {
    .footer-classic .container {
    flex-direction: column;
    align-items: stretch;
    gap: 0;
    }
    .footer-classic-col {
    min-width: 100%;
    margin-bottom: 19px;
    }
    .footer-classic-logo,
    .footer-classic-desc {
    max-width: 100%;
    }
}
@media (max-width: 520px) {
    .footer-classic {
    font-size: 0.96rem;
    padding: 28px 0 6px 0;
    }
    .footer-classic-col {
    margin-bottom: 14px;
    }
    .footer-copyright-classic {
    padding-bottom: 6px;
    }
}
     </style>
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
            <li><a class="dropdown-item" href="about_us.php">About Us</a></li>
            <li><a class="dropdown-item" href="contact.php">Contact</a></li>
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
            