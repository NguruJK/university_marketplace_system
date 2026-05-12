<?php
// Security is loaded via db.php which loads security.php
// Just check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /ums/auth/login.php");
    exit;
}

// Check if user is still active in the database
require_once __DIR__ . '/db.php';

$stmt = $pdo->prepare("SELECT is_active, role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$current_user = $stmt->fetch();

// Suspended or deleted — kick out
if (!$current_user || !$current_user['is_active']) {
    session_destroy();
    header("Location: /ums/auth/login.php?suspended=1");
    exit;
}
?>