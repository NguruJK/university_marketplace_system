<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/db.php';

class MpesaDaraja {

    // ---- Step 1: Get OAuth Access Token ----
    public function getAccessToken() {
        $url         = MPESA_BASE_URL . '/oauth/v1/generate?grant_type=client_credentials';
        $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['access_token'] ?? null;
    }

    // ---- Step 2: Initiate STK Push ----
    public function stkPush($phone, $amount, $order_id) {
        $token     = $this->getAccessToken();
        if (!$token) return ['error' => 'Could not get access token'];

        $timestamp = date('YmdHis');
        $password  = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

        // Format phone: remove leading 0 or + and add 254
        $phone = preg_replace('/^(\+?254|0)/', '254', $phone);

        $payload = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => (int) ceil($amount), // M-Pesa requires whole number
            'PartyA'            => $phone,
            'PartyB'            => MPESA_SHORTCODE,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => MPESA_CALLBACK_URL,
            'AccountReference'  => 'UMS-ORDER-' . $order_id,
            'TransactionDesc'   => 'UMS Marketplace Payment'
        ];

        $url = MPESA_BASE_URL . '/mpesa/stkpush/v1/processrequest';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST,           true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    // ---- Step 3: Check Transaction Status ----
    public function queryStatus($checkout_request_id) {
        $token = $this->getAccessToken();
        if (!$token) return ['error' => 'Could not get access token'];

        $timestamp = date('YmdHis');
        $password  = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

        $payload = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkout_request_id
        ];

        $url = MPESA_BASE_URL . '/mpesa/stkpushquery/v1/query';

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST,           true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,     json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
?>