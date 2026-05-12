<?php
// Set your credentials directly here since this file is standalone
$host = 'localhost';
$user = 'root';
$pass = ''; 
$db = 'ums';
$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    // For M-Pesa callbacks, it's better to log errors than to 'die' 
    // because Safaricom won't see the error message anyway.
    error_log("M-Pesa DB Connection failed: " . mysqli_connect_error());
    exit();
}

if (!function_exists('clean')) {
    function clean($data) {
        global $conn;
        return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($data))));
    }
}
?>