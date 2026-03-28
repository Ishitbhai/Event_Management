<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Remove all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session.
session_destroy();

?>
<script>
    window.location.href="login.php";
</script>
<?php
exit;
?>

