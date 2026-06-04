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

$content  = strip_tags(trim($_POST['content'] ?? ''));
$category = $_POST['category'] ?? 'General';
$allowed  = ['Lecturer', 'Lost & Found', 'Room Change', 'Events', 'General'];

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Question cannot be empty.']);
    exit;
}
if (strlen($content) < 10) {
    echo json_encode(['success' => false, 'message' => 'Question too short — at least 10 characters.']);
    exit;
}
if (!in_array($category, $allowed)) {
    $category = 'General';
}

$stmt = $pdo->prepare("
    INSERT INTO general_enquiries (student_id, content, category)
    VALUES (?, ?, ?)
");
$stmt->execute([$_SESSION['user_id'], $content, $category]);
$enquiry_id = $pdo->lastInsertId();

$stmt = $pdo->prepare("
    SELECT e.*, u.full_name AS student_name, u.avatar,
           0 AS reply_count
    FROM general_enquiries e
    JOIN users u ON e.student_id = u.id
    WHERE e.enquiry_id = ?
");
$stmt->execute([$enquiry_id]);
$enquiry = $stmt->fetch();

echo json_encode(['success' => true, 'enquiry' => $enquiry]);
?>