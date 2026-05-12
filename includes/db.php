<?php
require_once __DIR__ . '/security.php';

$host = 'localhost';
$db   = 'ums';
$user = 'root';
$pass = ''; // XAMPP default is empty

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 3. Prevent "Cannot redeclare clean()" error
if (!function_exists('clean')) {
    function clean($data) {
        global $conn;
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return mysqli_real_escape_string($conn, $data);
    }
}

// Global redirect helper
function redirect($url) {
    header("Location: $url");
    exit;
}

// Global auth check helper
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}
// Auto cleanup — delete unverified accounts older than 24 hours
$pdo->query("
    DELETE FROM users
    WHERE is_verified = 0
    AND token_expires_at IS NOT NULL
    AND token_expires_at < NOW()
");
?>