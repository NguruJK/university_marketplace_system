<?php
session_start();
session_destroy();
header("Location: /ums/auth/login.php");
exit;
?>