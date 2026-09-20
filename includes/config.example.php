<?php
/**
 * Example configuration.
 * ---------------------------------------------------------------
 * Copy this file to includes/config.php and edit for your deployment:
 *
 *     cp includes/config.example.php includes/config.php
 *
 * `includes/config.php` is listed in .gitignore and must NEVER be
 * committed — it holds real credentials. This example file is the
 * tracked template and contains placeholders only.
 *
 * Values may also be supplied as environment variables of the same name;
 * this file takes precedence when present.
 *
 * WebAuthn / kiosk note:
 *   If WEBAUTHN_RP_ID is not pinned, the RP ID is derived from the
 *   request Host header. A rotating tunnel hostname then changes the
 *   RP ID, which ORPHANS every passkey enrolled earlier — they simply
 *   stop working. Pin a stable hostname for any real kiosk deployment.
 *   `php scripts/set_kiosk_host.php <host>` writes these two keys for you
 *   and preserves the credential keys below.
 * ---------------------------------------------------------------
 */

return [
    // ---- WebAuthn / booth kiosk -------------------------------------
    // The registrable RP ID the passkeys are bound to (no scheme, no port).
    'WEBAUTHN_RP_ID' => 'vote.example.org',

    // Comma-separated origins allowed in clientDataJSON. If omitted,
    // only the request-derived origin is accepted.
    'WEBAUTHN_ALLOWED_ORIGINS' => 'https://vote.example.org,https://booth.example.org',

    // ---- Email OTP (Gmail SMTP via PHPMailer) -----------------------
    'SMTP_HOST'      => 'smtp.gmail.com',
    'SMTP_PORT'      => 587,
    'SMTP_USERNAME'  => 'your-account@gmail.com',
    // A 16-character Google App Password (no spaces). NOT your account
    // password. Revoke and reissue if it is ever exposed.
    'SMTP_PASSWORD'  => 'your-16-character-app-password',
    'SMTP_FROM'      => 'your-account@gmail.com',
    'SMTP_FROM_NAME' => 'Online Voting System',

    // ---- Mobile OTP (Fast2SMS) --------------------------------------
    'FAST2SMS_API_KEY' => 'your-fast2sms-api-key',

    // ---- WhatsApp OTP (CallMeBot, legacy path) ----------------------
    'CALLMEBOT_API_KEY' => 'your-callmebot-api-key',
];
