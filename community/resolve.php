<?php
require '../includes/auth_check.php';
require '../includes/db.php';
header('Content-Type: application/json');

$enquiry_id = intval($_POST['enquiry_id'] ?? 0);
$reply_id   = intval($_POST['reply_id']   ?? 0);

// Only the question owner or admin can resolve
$stmt = $pdo->prepare("SELECT * FROM general_enquiries WHERE enquiry_id = ?");
$stmt->execute([$enquiry_id]);
$enquiry = $stmt->fetch();

if (!$enquiry) {
    echo json_encode(['success' => false, 'message' => 'Not found.']);
    exit;
}

if ($enquiry['student_id'] !== $_SESSION['user_id'] && $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Not authorized.']);
    exit;
}

// Mark as resolved
$pdo->prepare("UPDATE general_enquiries SET status = 'resolved' WHERE enquiry_id = ?")
    ->execute([$enquiry_id]);

// Mark best answer if reply provided
if ($reply_id) {
    $pdo->prepare("UPDATE enquiry_replies SET is_best_answer = 1 WHERE reply_id = ? AND enquiry_id = ?")
        ->execute([$reply_id, $enquiry_id]);
}

echo json_encode(['success' => true]);
?>