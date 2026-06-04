<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$checkout_id = trim($_GET['checkout_id'] ?? '');

if (!$checkout_id) {
    echo json_encode(['status' => 'error']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT t.status, t.mpesa_receipt_number, t.result_desc
    FROM transactions t
    WHERE t.checkout_request_id = ?
");
$stmt->execute([$checkout_id]);
$transaction = $stmt->fetch();

echo json_encode($transaction ?: ['status' => 'pending']);
?>