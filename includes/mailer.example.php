<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

function sendVerificationEmail($to_email, $to_name, $token) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'joelnguru@students.uonbi.ac.ke'; 
        $mail->Password   = 'gocs bxur kmkq hvhm';
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        // ... rest of function
    } catch (Exception $e) {
        return false;
    }
}
?>