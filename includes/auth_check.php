<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /ums/auth/login.php");
    exit;
}

if (!isset($pdo)) {
    require_once __DIR__ . '/db.php';
}

$stmt = $pdo->prepare("SELECT is_active, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

if (!$current_user || !$current_user['is_active']) {
    session_destroy();
    header("Location: /ums/auth/login.php?suspended=1");
    exit;
}
?>