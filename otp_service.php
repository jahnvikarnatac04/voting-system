<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/app_config.php';

/**
 * 1. Send OTP to Email (via standard mail / SMTP format)
 */
function send_email_otp($to_email, $recipient_name, $otp) {
    $subject = "Your Election Verification OTP";
    $message = "Hello {$recipient_name},\n\nYour 6-digit One-Time Password (OTP) is: {$otp}\n\nThis code is valid for 5 minutes.";
    $headers = "From: Online Voting System <no-reply@votingportal.local>\r\n" .
               "Reply-To: no-reply@votingportal.local\r\n" .
               "X-Mailer: PHP/" . phpversion();

    @mail($to_email, $subject, $message, $headers);
    return true;
}

/**
 * 2. Send OTP to Phone (via CallMeBot Free WhatsApp Gateway)
 */
function send_phone_otp($mobile, $otp) {
    $apiKey = (string) app_config('CALLMEBOT_API_KEY', '');
    
    // Format 10-digit number with country code 91
    $clean_mobile = preg_replace('/[^0-9]/', '', $mobile);
    if (strlen($clean_mobile) === 10) {
        $clean_mobile = "91" . $clean_mobile;
    }

    $text = urlencode("🔐 Your Election Portal OTP is: {$otp}. Valid for 5 minutes.");
    $url  = "https://api.callmebot.com/whatsapp.php?phone={$clean_mobile}&text={$text}&apikey={$apiKey}";

    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    @file_get_contents($url, false, $ctx);
    return true;
}

/**
 * 3. Generate & Dispatch OTP across both channels
 */
function dispatch_dual_otp($email, $mobile, $name, $context = 'registration') {
    $otp = (string)random_int(100000, 999999);
    $_SESSION[$context . '_otp'] = $otp;
    $_SESSION[$context . '_otp_expiry'] = time() + 300; // 5 mins

    // Send to both destinations
    send_email_otp($email, $name, $otp);
    send_phone_otp($mobile, $otp);

    // Keep active in session for testing & offline viva demonstration
    $_SESSION[$context . '_demo_otp'] = $otp;
    return $otp;
}

/**
 * 4. Verify Submitted Code
 */
function verify_dual_otp($entered_otp, $context = 'registration') {
    if (!isset($_SESSION[$context . '_otp']) || !isset($_SESSION[$context . '_otp_expiry'])) {
        return ['status' => false, 'message' => 'OTP request expired. Please request a new code.'];
    }

    if (time() > $_SESSION[$context . '_otp_expiry']) {
        unset($_SESSION[$context . '_otp'], $_SESSION[$context . '_otp_expiry'], $_SESSION[$context . '_demo_otp']);
        return ['status' => false, 'message' => 'OTP has expired.'];
    }

    if (trim($entered_otp) === (string)$_SESSION[$context . '_otp']) {
        unset($_SESSION[$context . '_otp'], $_SESSION[$context . '_otp_expiry'], $_SESSION[$context . '_demo_otp']);
        return ['status' => true, 'message' => 'Verification successful.'];
    }

    return ['status' => false, 'message' => 'Invalid OTP code.'];
}