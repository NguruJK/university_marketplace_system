<?php
// ============================================================
// UMS PLATFORM FEE CONFIGURATION
// ============================================================

define('PLATFORM_FEE_PERCENT', 5);      // 5% platform fee
define('PLATFORM_FEE_LABEL',   'UMS Platform Fee (5%)');
define('PLATFORM_FEE_PURPOSE', 'Platform maintenance, moderation, and student welfare funding');
define('MIN_FEE_AMOUNT',       10);     // Minimum fee in KSh

/**
 * Calculate platform fee and seller amount
 * @param float $price — Item listing price
 * @return array — [fee, seller_amount, total]
 */
function calculateFee($price) {
    $fee           = max(MIN_FEE_AMOUNT, round($price * PLATFORM_FEE_PERCENT / 100, 2));
    $seller_amount = round($price - $fee, 2);

    return [
        'price'         => $price,
        'fee'           => $fee,
        'fee_percent'   => PLATFORM_FEE_PERCENT,
        'seller_amount' => $seller_amount,
    ];
}
?>