<?php

define('MPESA_ENV',             'sandbox');
define('MPESA_CONSUMER_KEY',    '0CkWZ1BAdh06tW5BOfGLpBsmYufiK4r32fU3ezk8HlzImvJM');      // ← from Daraja portal
define('MPESA_CONSUMER_SECRET', 'l8hO0lkJ62kIY4K6vSvpDBNP1a0oPdnIJejR4EztZwdb7yr7GJnCMS7A2WQAGrZD');   // ← from Daraja portal
define('MPESA_SHORTCODE',       '174379');                  // ← sandbox shortcode
define('MPESA_PASSKEY',         'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');
define('MPESA_CALLBACK_URL',    'https://unplastic-marlon-unoverdrawn.ngrok-free.dev/ums/mpesa/callback.php'); // ← your ngrok URL

// API Base URLs
define('MPESA_BASE_URL', MPESA_ENV === 'sandbox'
    ? 'https://sandbox.safaricom.co.ke'
    : 'https://api.safaricom.co.ke'
);
?>