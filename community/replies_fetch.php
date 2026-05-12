<?php
require '../includes/auth_check.php';
require '../includes/db.php';
header('Content-Type: application/json');

$enquiry_id = intval($_GET['enquiry_id'] ?? 0);

// Increment view count
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