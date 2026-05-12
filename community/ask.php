<?php
require '../includes/auth_check.php';
require '../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$content  = strip_tags(trim($_POST['content'] ?? ''));
$category = $_POST['category'] ?? 'General';
$allowed  = ['Lecturer','Lost & Found','Room Change','Events','General'];

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

// Fetch the newly created enquiry with student name
$stmt = $pdo->prepare("
    SELECT e.*, u.full_name AS student_name, u.avatar,
           COUNT(r.reply_id) AS reply_count
    FROM general_enquiries e
    JOIN users u ON e.student_id = u.id
    LEFT JOIN enquiry_replies r ON e.enquiry_id = r.enquiry_id
    WHERE e.enquiry_id = ?
    GROUP BY e.enquiry_id
");
$stmt->execute([$enquiry_id]);
$enquiry = $stmt->fetch();

echo json_encode(['success' => true, 'enquiry' => $enquiry]);
?>