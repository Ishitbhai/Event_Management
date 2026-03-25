<?php
session_start();
// Unset all admin session variables
$_SESSION = array();
session_destroy();
?>
<script>
    window.location.href = 'login.php';
</script>
<?php
exit();
?>
