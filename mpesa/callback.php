<?php
// This file receives payment results from Safaricom
require_once __DIR__ . '/../includes/mpesa_db.php';

// Get the raw JSON callback from Safaricom
$raw      = file_get_contents('php://input');
$response = json_decode($raw, true);

// Log for debugging (optional — delete in production)
file_put_contents(__DIR__ . '/callback_log.txt',
    date('Y-m-d H:i:s') . "\n" . $raw . "\n\n",
    FILE_APPEND
);

// Extract data
$body        = $response['Body']['stkCallback'] ?? [];
$result_code = $body['ResultCode'] ?? -1;
$result_desc = $body['ResultDesc'] ?? '';
$merchant_id = $body['MerchantRequestID'] ?? '';
$checkout_id = $body['CheckoutRequestID'] ?? '';

// Find the transaction
$stmt = $pdo->prepare("
    SELECT t.*, o.listing_id 
    FROM transactions t
    JOIN orders o ON t.order_id = o.id
    WHERE t.checkout_request_id = ?
");
$stmt->execute([$checkout_id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    http_response_code(200); // Always return 200 to Safaricom
    exit;
}

if ($result_code == 0) {
    // ✅ PAYMENT SUCCESS
    $items      = $body['CallbackMetadata']['Item'] ?? [];
    $receipt    = '';
    $amount     = 0;
    $phone      = '';

    foreach ($items as $item) {
        match($item['Name']) {
            'MpesaReceiptNumber' => $receipt = $item['Value'],
            'Amount'             => $amount  = $item['Value'],
            'PhoneNumber'        => $phone   = $item['Value'],
            default              => null
        };
    }

    // Update transaction
    $pdo->prepare("
        UPDATE transactions
        SET status = 'success',
            mpesa_receipt_number = ?,
            result_code = ?,
            result_desc = ?
        WHERE checkout_request_id = ?
    ")->execute([$receipt, $result_code, $result_desc, $checkout_id]);

    // Update order
    $pdo->prepare("
        UPDATE orders SET status = 'completed'
        WHERE id = ?
    ")->execute([$transaction['order_id']]);

    // Mark listing as sold
    $pdo->prepare("
        UPDATE listings SET status = 'sold'
        WHERE id = ?
    ")->execute([$transaction['listing_id']]);

} else {
    // ❌ PAYMENT FAILED
    $pdo->prepare("
        UPDATE transactions
        SET status = 'failed',
            result_code = ?,
            result_desc = ?
        WHERE checkout_request_id = ?
    ")->execute([$result_code, $result_desc, $checkout_id]);

    $pdo->prepare("
        UPDATE orders SET status = 'failed' WHERE id = ?
    ")->execute([$transaction['order_id']]);
}

http_response_code(200);
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
?>