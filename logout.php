<?php
// logout.php
session_start();
session_unset();
session_destroy();
header('Location: alumni_login.php');
exit;
?>
