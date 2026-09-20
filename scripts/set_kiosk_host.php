<?php
/**
 * scripts/set_kiosk_host.php
 * ---------------------------------------------------------------
 * Pin the WebAuthn RP ID + allowed origin for the booth kiosk.
 *
 *   php scripts/set_kiosk_host.php https://your-name.ngrok-free.app
 *
 * Writes includes/config.php:
 *   WEBAUTHN_RP_ID            = your-name.ngrok-free.app
 *   WEBAUTHN_ALLOWED_ORIGINS  = https://your-name.ngrok-free.app
 *
 * Only those two keys are managed here; any other keys already present
 * (SMTP credentials, SMS keys) are preserved. includes/config.php is
 * untracked — see includes/config.example.php for the full key list.
 *
 * WHY: passkeys are permanently bound to the RP ID (the hostname).
 * If the hostname changes, every enrolled passkey stops working.
 * Pinning makes the app verify against a known hostname instead of
 * trusting whatever the request's Host header says.
 *
 * NOTE: passkeys are scoped to ONE hostname. Once pinned to the
 * tunnel host, "localhost" passkeys will no longer be offered there
 * (and vice-versa) — enroll through the kiosk at the pinned URL.
 * ---------------------------------------------------------------
 */

$input = $argv[1] ?? '';

if ($input === '' || in_array($input, ['-h', '--help'], true)) {
    fwrite(STDOUT, <<<TXT
Usage: php scripts/set_kiosk_host.php <host-or-url>

Examples:
  php scripts/set_kiosk_host.php https://your-name.ngrok-free.app
  php scripts/set_kiosk_host.php your-name.ngrok-free.app

Find your stable ngrok dev domain in the ngrok dashboard, or from the
line ngrok prints when it starts:  Forwarding  https://<name>.ngrok-free.app -> ...

TXT);
    exit($input === '' ? 1 : 0);
}

// Accept with or without a scheme; default to https (tunnels are HTTPS).
if (!preg_match('#^https?://#i', $input)) {
    $input = 'https://' . $input;
}
$parts  = parse_url($input);
$scheme = strtolower($parts['scheme'] ?? 'https');
$host   = strtolower($parts['host'] ?? '');
$port   = isset($parts['port']) ? (int)$parts['port'] : 0;

if ($host === '' || !preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $host) || strpos($host, '.') === false) {
    fwrite(STDERR, "✗ Could not parse a valid public hostname from: {$argv[1]}\n");
    fwrite(STDERR, "  Expected something like: https://your-name.ngrok-free.app\n");
    exit(1);
}

// An RP ID must not be an IP address or bare "localhost" for a phone kiosk.
if (filter_var($host, FILTER_VALIDATE_IP) || $host === 'localhost') {
    fwrite(STDERR, "✗ '{$host}' is not usable as a kiosk RP ID (a phone needs a public HTTPS host).\n");
    exit(1);
}

$origin = $scheme . '://' . $host . ($port > 0 ? ':' . $port : '');
$path   = __DIR__ . '/../includes/config.php';

// PRESERVE any existing keys (SMTP credentials, SMS keys, ...). Only the
// two WEBAUTHN_* keys are managed by this script; everything else in
// includes/config.php must survive a re-run untouched.
$existing = [];
if (is_file($path)) {
    $loaded = require $path;
    if (is_array($loaded)) {
        $existing = $loaded;
    }
}
$existing['WEBAUTHN_RP_ID']           = $host;
$existing['WEBAUTHN_ALLOWED_ORIGINS'] = $origin;

$php = "<?php\n"
     . "/**\n"
     . " * LOCAL CONFIGURATION — UNTRACKED (see .gitignore).\n"
     . " * WEBAUTHN_* keys are managed by scripts/set_kiosk_host.php;\n"
     . " * all other keys are preserved as-is. Contains credentials — do not commit.\n"
     . " */\n"
     . "return [\n";
foreach ($existing as $k => $v) {
    $php .= "    " . var_export($k, true) . " => " . var_export($v, true) . ",\n";
}
$php .= "];\n";

if (file_put_contents($path, $php) === false) {
    fwrite(STDERR, "✗ Could not write {$path}\n");
    exit(1);
}

fwrite(STDOUT, "✓ Pinned kiosk host\n");
fwrite(STDOUT, "    RP ID          : {$host}\n");
fwrite(STDOUT, "    Allowed origin : {$origin}\n");
fwrite(STDOUT, "    Written to     : includes/config.php\n\n");
fwrite(STDOUT, "Next:\n");
fwrite(STDOUT, "  1. Restart the tunnel so it serves this hostname.\n");
fwrite(STDOUT, "  2. Open {$origin}/kiosk/login.php on the phone — use THIS URL for the\n");
fwrite(STDOUT, "     whole kiosk flow (admin + kiosk), not localhost.\n");
fwrite(STDOUT, "  3. Re-enroll fingerprints through the kiosk: existing localhost\n");
fwrite(STDOUT, "     passkeys belong to a different RP ID and will not transfer.\n");
