<?php
require '../includes/auth_check.php';
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enquiry_id = intval($_POST['enquiry_id']);
    $listing_id = intval($_POST['listing_id']);
    $answer     = trim($_POST['answer']);

    if ($enquiry_id && $answer) {
        $stmt = $pdo->prepare(
            "UPDATE enquiries SET answer = ?, is_resolved = 1 WHERE id = ?"
        );
        $stmt->execute([$answer, $enquiry_id]);
    }
    header("Location: /ums/listing.php?id=$listing_id");
    exit;
}
?>