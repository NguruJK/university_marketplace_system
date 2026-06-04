<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'enquiries' => [], 'total' => 0, 'has_more' => false]);
    exit;
}

$mode     = $_GET['mode']     ?? 'recent';
$category = $_GET['category'] ?? '';
$search   = strip_tags(trim($_GET['search'] ?? ''));
$page     = max(1, intval($_GET['page'] ?? 1));
$limit    = 10;
$offset   = ($page - 1) * $limit;

$sql    = "SELECT e.*, u.full_name AS student_name, u.avatar,
           COUNT(r.reply_id) AS reply_count
           FROM general_enquiries e
           JOIN users u ON e.student_id = u.id
           LEFT JOIN enquiry_replies r ON e.enquiry_id = r.enquiry_id
           WHERE 1";
$params = [];

if ($category) {
    $sql .= " AND e.category = ?";
    $params[] = $category;
}
if ($search) {
    $sql .= " AND e.content LIKE ?";
    $params[] = "%$search%";
}

$sql .= " GROUP BY e.enquiry_id";
$sql .= $mode === 'hot'
    ? " ORDER BY reply_count DESC, e.views DESC"
    : " ORDER BY e.created_at DESC";
$sql .= " LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$enquiries = $stmt->fetchAll();

$count_sql    = "SELECT COUNT(*) FROM general_enquiries WHERE 1";
$count_params = [];
if ($category) { $count_sql .= " AND category = ?"; $count_params[] = $category; }
if ($search)   { $count_sql .= " AND content LIKE ?"; $count_params[] = "%$search%"; }
$total = $pdo->prepare($count_sql);
$total->execute($count_params);
$total_count = $total->fetchColumn();

echo json_encode([
    'success'   => true,
    'enquiries' => $enquiries,
    'total'     => $total_count,
    'page'      => $page,
    'has_more'  => ($offset + $limit) < $total_count
]);
?>