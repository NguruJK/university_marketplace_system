<?php
require '../includes/auth_check.php';
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $listing_id = intval($_POST['listing_id']);
    $question   = trim($_POST['question']);

    if ($listing_id && $question) {
        $stmt = $pdo->prepare(
            "INSERT INTO enquiries (listing_id, buyer_id, question) VALUES (?, ?, ?)"
        );
        $stmt->execute([$listing_id, $_SESSION['user_id'], $question]);
    }
    header("Location: /ums/listing.php?id=$listing_id");
    exit;
}
?>