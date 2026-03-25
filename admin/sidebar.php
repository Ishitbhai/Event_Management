<?php
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== 1) {
    ?>
    <script>
        window.location.href = 'login.php';
    </script>
    <?php
}
require_once('../database/db_connect.php');

// --- Fetch profile picture for logged-in admin from database ---
$profile_picture = null;
if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);

    if ($conn && !$conn->connect_error) {
        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($profile_picture);
        $stmt->fetch();
        $stmt->close();
        
    }
    // If not found, $profile_picture will stay null
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <!-- Load all Bootstrap CSS files from ../bootstrap/css/ -->
    <?php
    $bootstrap_css_dir = realpath(__DIR__ . '/../bootstrap/css/');
    if ($bootstrap_css_dir && is_dir($bootstrap_css_dir)) {
        $css_files = glob($bootstrap_css_dir . '/*.css');
        foreach ($css_files as $css_file) {
            $relative_path = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', $css_file);
            // Adapt path to relative-for-inclusion: output as "../bootstrap/css/..."
            $href = "../bootstrap/css/" . basename($css_file);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($href) . '">' . "\n";
        }
    }
    ?>
    <style>
        body {
    margin: 0;
    padding: 0;
}
.page-header {
    z-index: 3;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.profile-btn {
    border: none;
    background: #f3f3f3;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 20px;
    margin-left: 12px;
    position: relative;
    overflow: hidden;
}
.profile-icon {
    font-size: 20px;
    color: #333;
    display: flex;
    align-items: center;
    justify-content: center;
}
/* corrected: don't use flex/alignment on the img, just size/crop */
.profile-picture-img {
    width: 38px;
    height: 38px;
    object-fit: cover;
    border-radius: 50%;
    border: 1px solid #d6d6d6;
    background: #ccd2e0;
    display: block;
    /* Remove flex and font-size props that only apply to .profile-icon */
    max-width: none;
    max-height: none;
    padding: 0;
    margin: 0;
}
html, body {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    min-height: 100vh;
    width: 100%;
}

/* Sidebar styles */
.sidebar-container {
    display: flex;
    min-height: 100vh;
    width: 100vw;
    margin: 0;
    padding: 0;
    position: relative;
}

.sidebar {
    width: 240px;
    background: #1d2327;
    color: #fff;
    position: fixed;
    left: 0;
    top: 0;
    min-height: 100vh;
    height: 100vh;
    padding-top: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
    z-index: 200;
    transition: width 0.22s, left 0.22s, box-shadow 0.22s;
    box-shadow: 2px 0 16px rgba(30,32,35,0.04);
}

.sidebar .sidebar-logo {
    font-size: 1.6rem;
    font-weight: 700;
    padding: 20px 28px 22px 28px;
    letter-spacing: 1.4px;
    color: #fbfbfb;
    margin: 0;
    background: none;
}

.sidebar nav {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin: 0;
    padding: 0;
}

.sidebar nav a {
    padding: 15px 32px;
    color: #c6d3ee;
    text-decoration: none;
    font-size: 1.07rem;
    font-weight: 500;
    border-bottom: 4px solid transparent;
    transition: background 0.16s, color 0.16s, border-left 0.16s;
    letter-spacing: 0.1px;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar nav a:hover,
.sidebar nav a.active {
    background: #2c3338;
    color: #fff;
    border-bottom: 4px solid #e2e2e2;
}

.sidebar .sidebar-footer {
    padding: 18px 28px;
    font-size: 0.92rem;
    color: #adb6cc;
    border-top: 1px solid #314066;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Main content shifts based on sidebar width */
.main-content {
    flex: 1;
    background: #f4f6f9;
    min-height: 100vh;
    padding: 0;
    display: flex;
    flex-direction: column;
    margin-left: 240px;
    width: calc(100vw - 240px);
    transition: margin-left 0.22s, width 0.22s;
}

.page-header {
    background: #fff;
    border-bottom: 1px solid #e3e8f0;
    padding: 28px 38px 18px 38px;
    font-size: 1.35rem;
    font-weight: 600;
    color: #203156;
    letter-spacing: 1px;
    position: sticky;
    top: 0;
    z-index: 90;
    box-shadow: 0 4px 20px rgba(44, 50, 70, 0.05);
    margin: 0;
    width: 100%;
    box-sizing: border-box;
}

/* Hamburger menu for small screens */
.sidebar-toggle-btn {
    display: none;
    position: fixed;
    left: 16px;
    top: 16px;
    width: 38px;
    height: 38px;
    background: #1d2327;
    color: #e3e7ee;
    border: none;
    z-index: 300;
    cursor: pointer;
    border-radius: 7px;
    justify-content: center;
    align-items: center;
    box-shadow: 0 2px 12px rgba(40,42,55,0.09);
}

.sidebar-toggle-btn span, .sidebar-toggle-btn svg {
    pointer-events: none;
    font-size: 2rem;
    display: inline-block;
}

/* TABLET: Collapse sidebar to smaller widths */
@media (max-width: 1020px) {
    .sidebar {
        width: 72px;
        min-width: 72px;
    }
    .sidebar .sidebar-logo, .sidebar .sidebar-footer {
        display: none;
    }
    .main-content {
        margin-left: 72px;
        width: calc(100vw - 72px);
    }
    .sidebar nav a {
        padding: 16px 0;
        text-align: center;
        font-size: 1.05rem;
    }
    .page-header {
        padding-left: 18px;
        padding-right: 18px;
    }
}

/* Small TABLET and large phone */
@media (max-width: 700px) {
    .sidebar {
        width: 56px;
        min-width: 56px;
        /* Still visible as narrow sidebar */
    }
    .main-content {
        margin-left: 56px;
        width: calc(100vw - 56px);
    }
    .sidebar nav a {
        padding: 14px 0;
        font-size: 0.92rem;
        text-align: center;
    }
    .page-header {
        padding-left: 11px;
        padding-right: 11px;
        font-size: 1.07rem;
    }
}

/* PHONE: sidebar becomes overlay (hidden by default, shown by toggle) */
@media (max-width: 520px) {
    .sidebar {
        position: fixed;
        left: -240px;
        width: 220px;
        min-width: 180px;
        max-width: 92vw;
        top: 0;
        height: 100vh;
        transition: left 0.28s, box-shadow 0.28s;
        box-shadow: 2px 0 50px rgba(0,0,0,0.17);
        background: #1d2327;
        display: flex;
    }
    .sidebar.open {
        left: 0;
        box-shadow: 2px 0 70px rgba(0,0,0,0.25);
    }
    .sidebar .sidebar-logo,
    .sidebar .sidebar-footer {
        display: block;
    }
    .sidebar nav a {
        padding: 15px 28px;
        font-size: 1.02rem;
        text-align: left;
    }
    .sidebar-container {
        min-height: 100vh;
    }
    .main-content {
        margin-left: 0 !important;
        width: 100vw !important;
    }
    .sidebar-toggle-btn {
        display: flex;
    }
    .sidebar ~ .sidebar-backdrop {
        display: none;
        position: fixed;
        z-index: 199;
        left: 0;
        right: 0;
        top: 0;
        bottom: 0;
        background: rgba(0,0,0,0.24);
        transition: opacity 0.22s;
    }
    .sidebar.open ~ .sidebar-backdrop {
        display: block;
        opacity: 1;
    }
}

/* Allow scrolling if screen is too short */
@media (max-height: 540px) {
    .sidebar {
        overflow-y: auto;
    }
}

/* Utility: body.no-scroll disables scrolling behind overlay sidebar */
body.no-scroll {
    overflow: hidden;
}


/* Custom responsive fixes */
@media (max-width: 1020px) {
    .sidebar {
        width: 72px;
        min-width: 0;
        max-width: 100vw;
    }
    .sidebar .sidebar-logo, .sidebar .sidebar-footer {
        display: none;
    }
    .main-content {
        margin-left: 72px;
        width: calc(100vw - 72px);
    }
    .sidebar nav a {
        padding: 16px 0;
        text-align: center;
        font-size: 1.05rem;
    }
    .page-header {
        padding-left: 18px;
        padding-right: 18px;
    }
}
@media (max-width: 700px) {
    .sidebar {
        width: 56px;
        min-width: 0;
        max-width: 100vw;
    }
    .main-content {
        margin-left: 56px;
        width: calc(100vw - 56px);
    }
    .sidebar nav a {
        padding: 14px 0;
        font-size: 0.92rem;
        text-align: center;
    }
    .page-header {
        padding-left: 11px;
        padding-right: 11px;
        font-size: 1.07rem;
    }
}
@media (max-width: 520px) {
    .sidebar {
        position: fixed;
        left: -240px;
        width: 220px;
        min-width: 150px;
        max-width: 92vw;
        top: 0;
        height: 100vh;
        transition: left 0.28s, box-shadow 0.28s;
        box-shadow: 2px 0 50px rgba(0,0,0,0.17);
        background: #1d2327;
        display: flex;
        z-index: 201;
    }
    .sidebar.open {
        left: 0;
        box-shadow: 2px 0 70px rgba(0,0,0,0.25);
    }
    .sidebar .sidebar-logo,
    .sidebar .sidebar-footer {
        display: block;
    }
    .sidebar nav a {
        padding: 15px 28px;
        font-size: 1.02rem;
        text-align: left;
    }
    .main-content {
        margin-left: 0 !important;
        width: 100vw !important;
        z-index: 1;
        position: static;
    }
    .sidebar-toggle-btn {
        display: flex;
    }
    .sidebar ~ .sidebar-backdrop {
        display: none;
        position: fixed;
        z-index: 199;
        left: 0;
        right: 0;
        top: 0;
        bottom: 0;
        background: rgba(0,0,0,0.24);
        transition: opacity 0.22s;
        opacity: 0;
    }
    .sidebar.open ~ .sidebar-backdrop {
        display: block;
        opacity: 1;
    }
    .page-header {
        /* Use margin-top to push header below sidebar when overlay visible */
        position: relative;
        z-index: 3;
        margin-top: 70px !important;
    }
}
@media (max-width: 520px) {
    .sidebar-toggle-btn {
        display: flex;
    }
}
@media (min-width: 521px) {
    .sidebar-toggle-btn {
        display: none !important;
    }
}
@media (max-height: 540px) {
    .sidebar {
        overflow-y: auto;
    }
}
body.no-scroll {
    overflow: hidden;
}
/* Always provide top margin for .page-header when sidebar is mobile overlay. 
   We'll reinforce with JS, but this covers static situations. */
@media (max-width: 520px) {
    .main-content .page-header {
        margin-top: 70px !important;
    }
}

    </style>
    <!-- <link rel="stylesheet" href="css/sidebar.css"> -->
<script>
// Sidebar hamburger/overlay logic for small screens
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.querySelector('.sidebar');
    const backdropClass = 'sidebar-backdrop';
    let backdrop = document.querySelector('.' + backdropClass);

    // Ensure toggle button
    function ensureToggleBtn() {
        let btn = document.querySelector('.sidebar-toggle-btn');
        if (window.innerWidth <= 520) {
            if (!btn) {
                btn = document.createElement('button');
                btn.className = 'sidebar-toggle-btn';
                btn.type = 'button';
                btn.title = 'Open or close sidebar';
                btn.setAttribute('aria-label', 'Toggle sidebar');
                btn.innerHTML = '<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true"><rect x="4" y="6" width="16" height="2" fill="currentColor"/><rect x="4" y="11" width="16" height="2" fill="currentColor"/><rect x="4" y="16" width="16" height="2" fill="currentColor"/></svg>';
                document.body.appendChild(btn);

                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    // Toggle open/closed state on click
                    if (sidebar.classList.contains('open')) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });
            }
        } else {
            if (btn) {
                btn.remove();
            }
        }
    }

    // Create backdrop if not present
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = backdropClass;
        document.body.appendChild(backdrop);
    }

    function openSidebar() {
        sidebar.classList.add('open');
        if (backdrop) {
            backdrop.style.display = 'block';
            // For fade-in
            setTimeout(function() {
                backdrop.style.opacity = '1';
            }, 10);
        }
        document.body.classList.add('no-scroll');
        // When sidebar opens as overlay, push page-header down
        setPageHeaderMargin();
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        if (backdrop) {
            backdrop.style.opacity = '0';
            setTimeout(function () {
                backdrop.style.display = 'none';
            }, 220);
        }
        document.body.classList.remove('no-scroll');
        // When closed, reset page-header margin
        setPageHeaderMargin();
    }

    // Adjust margin of .page-header for mobile overlay
    function setPageHeaderMargin() {
        var pageHeader = document.querySelector('.page-header');
        if (!pageHeader) return;
        if (window.innerWidth <= 520 && sidebar.classList.contains('open')) {
            pageHeader.style.marginTop = '48px';
        } else {
            pageHeader.style.marginTop = '';
        }
    }

    // Backdrop closes the sidebar
    backdrop.addEventListener('click', closeSidebar);

    // Responsive: Ensure toggle button exists/disappears on resize
    ensureToggleBtn();
    window.addEventListener('resize', function () {
        // Always close overlay on resize to desktop
        if (window.innerWidth > 520) {
            closeSidebar();
        }
        ensureToggleBtn();
        setPageHeaderMargin();
    });

    // Keyboard accessibility: ESC closes
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            // Only close if open and in mobile mode
            if (window.innerWidth <= 520 && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        }
    });

    // On click outside (on main content), close the sidebar on mobile
    document.addEventListener('click', function(e){
        const btn = document.querySelector('.sidebar-toggle-btn');
        // Only if sidebar is open and we're on mobile
        if (
            window.innerWidth <= 520 &&
            sidebar.classList.contains('open') &&
            !sidebar.contains(e.target) &&
            (!btn || !btn.contains(e.target)) &&
            !backdrop.contains(e.target)
        ) {
            closeSidebar();
        }
    });

    // Initialize pageHeader margin after DOM
    setPageHeaderMargin();
});

</script>
    <!-- <script src="js/sidebar.js"></script> -->
</head>
<body>
    <?php
    // Determine the current page in a robust way
    $current_page = basename($_SERVER['PHP_SELF']);
    ?>
    <div class="sidebar-container">
        <aside class="sidebar">
            <div class="sidebar-logo">
                Aone hub
            </div>
            <nav>
                <a href="index.php" <?php if ($current_page == 'index.php') echo 'class="active"'; ?>>Dashboard</a>
                <a href="events.php" <?php if ($current_page == 'events.php') echo 'class="active"'; ?>>Events</a>
                <a href="bookings.php" <?php if ($current_page == 'bookings.php' || $current_page == 'bookings.php') echo 'class="active"'; ?>>Bookings</a>
                <a href="categories.php" <?php if ($current_page == 'categories.php' || $current_page == 'categories.php') echo 'class="active"'; ?>>Categories</a>
                <a href="users.php" <?php if ($current_page == 'users.php' || $current_page == 'users.php') echo 'class="active"'; ?>>Users</a>
                <a href="services.php" <?php if ($current_page == 'services.php' || $current_page == 'services.php') echo 'class="active"'; ?>>Services</a>
                <a href="reviews.php" <?php if ($current_page == 'reviews.php' || $current_page == 'reviews.php') echo 'class="active"'; ?>>Reviews</a>
                <a href="coupons.php" <?php if ($current_page == 'coupons.php') echo 'class="active"'; ?>>Coupons</a>
                <a href="about_us.php" <?php if ($current_page == 'about_us.php') echo 'class="active"'; ?>>About Us</a>
                <a href="contact.php" <?php if ($current_page == 'contact.php') echo 'class="active"'; ?>>Contact</a>
                <a href="logout.php">Logout</a>
            </nav>
            <div class="sidebar-footer">
                &copy; <?php echo date("Y"); ?> Event Management
            </div>
        </aside>
        <div class="main-content">
            <div class="page-header">
                <?php
                // Provide a default heading, or echo a $page_heading variable if set
                if (isset($page_heading) && $page_heading) {
                    echo htmlspecialchars($page_heading);
                } else {
                    echo "Admin Panel";
                }
                ?>
                <button class="profile-btn" title="Profile" onclick="window.location.href='profile.php'">
                    <?php if (!empty($profile_picture)): ?>
                        <img src="../images/<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile" class="profile-picture-img">
                    <?php else: ?>
                        <span class="profile-icon">&#128100;</span>
                    <?php endif; ?>
                </button>
            </div>