<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$enquiry_id = intval($_POST['enquiry_id'] ?? 0);
$reply_text = strip_tags(trim($_POST['reply_text'] ?? ''));

if (!$enquiry_id || empty($reply_text)) {
    echo json_encode(['success' => false, 'message' => 'Reply cannot be empty.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM general_enquiries WHERE enquiry_id = ?");
$stmt->execute([$enquiry_id]);
$enquiry = $stmt->fetch();

if (!$enquiry) {
    echo json_encode(['success' => false, 'message' => 'Question not found.']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO enquiry_replies (enquiry_id, student_id, reply_text)
    VALUES (?, ?, ?)
");
$stmt->execute([$enquiry_id, $_SESSION['user_id'], $reply_text]);
$reply_id = $pdo->lastInsertId();

$stmt = $pdo->prepare("
    SELECT r.*, u.full_name AS student_name, u.avatar
    FROM enquiry_replies r
    JOIN users u ON r.student_id = u.id
    WHERE r.reply_id = ?
");
$stmt->execute([$reply_id]);
$reply = $stmt->fetch();

echo json_encode(['success' => true, 'reply' => $reply]);
?>