<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'replies' => []]);
    exit;
}

$enquiry_id = intval($_GET['enquiry_id'] ?? 0);

if (!$enquiry_id) {
    echo json_encode(['success' => false, 'replies' => []]);
    exit;
}

$pdo->prepare("UPDATE general_enquiries SET views = views + 1 WHERE enquiry_id = ?")
    ->execute([$enquiry_id]);

$stmt = $pdo->prepare("
    SELECT r.*, u.full_name AS student_name, u.avatar
    FROM enquiry_replies r
    JOIN users u ON r.student_id = u.id
    WHERE r.enquiry_id = ?
    ORDER BY r.is_best_answer DESC, r.created_at ASC
");
$stmt->execute([$enquiry_id]);
$replies = $stmt->fetchAll();

echo json_encode(['success' => true, 'replies' => $replies]);
?>