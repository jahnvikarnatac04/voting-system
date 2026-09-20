<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/app_config.php';

/**
 * 1. Send Real Email via Gmail SMTP
 */
function send_real_email_otp($recipient_email, $recipient_name, $otp) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = app_config('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = app_config('SMTP_USERNAME');
        $mail->Password   = app_config('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) app_config('SMTP_PORT', 587);

        $mail->setFrom(
            app_config('SMTP_FROM', app_config('SMTP_USERNAME')),
            app_config('SMTP_FROM_NAME', 'Online Voting System')
        );
        $mail->addAddress($recipient_email, $recipient_name);

        $mail->isHTML(true);
        $mail->Subject = 'Your Election Verification OTP';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 500px;'>
                <h3 style='color: blueviolet;'>Online Voting System Verification</h3>
                <p>Hello <strong>" . htmlspecialchars($recipient_name) . "</strong>,</p>
                <p>Your One-Time Password (OTP) for registration is:</p>
                <h1 style='background: #f1f1f1; display: inline-block; padding: 8px 18px; letter-spacing: 5px; color: #333;'>{$otp}</h1>
                <p style='color: #777; font-size: 13px; margin-top: 15px;'>This code is valid for 5 minutes. Do not share this OTP with anyone.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * 2. Send Real OTP to Phone via Fast2SMS (or Free WhatsApp)
 */
function send_real_phone_otp($mobile, $otp) {
    // Fast2SMS API integration for Indian numbers
    $apiKey = (string) app_config('FAST2SMS_API_KEY', '');
    
    $clean_mobile = preg_replace('/[^0-9]/', '', $mobile);
    if (strlen($clean_mobile) === 12 && substr($clean_mobile, 0, 2) === '91') {
        $clean_mobile = substr($clean_mobile, 2);
    }

    $fields = [
        "variables_values" => $otp,
        "route"            => "otp",
        "numbers"          => $clean_mobile,
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => "https://www.fast2sms.com/dev/bulkV2",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($fields),
        CURLOPT_HTTPHEADER     => [
            "authorization: " . $apiKey,
            "content-type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    curl_close($curl);
    return true;
}