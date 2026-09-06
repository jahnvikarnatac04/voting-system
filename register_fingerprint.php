<?php
session_start();
require_once __DIR__ . '/db.php';

// Reached right after signup (temp_fp_voter_id) or from a logged-in session
if (!isset($_SESSION['temp_fp_voter_id']) && !isset($_SESSION['vid'])) {
    header("Location: login.php");
    exit();
}
$target_voter_id = $_SESSION['temp_fp_voter_id'] ?? $_SESSION['vid'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometric Fingerprint Enrollment - Online Voting System</title>
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <style>
        :root { --primary-color: blueviolet; --primary-hover: #701eb8; }
        body { background-color: #f8f9fa; font-family: Arial, sans-serif; }
        .fp-card {
            background: #fff;
            border-radius: 12px;
            padding: 35px;
            max-width: 480px;
            margin: 60px auto;
            border: 1px solid #e0e0e0;
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            text-align: center;
        }
        .fp-icon { font-size: 55px; color: var(--primary-color); margin-bottom: 15px; }
        .btn-custom {
            background-color: var(--primary-color);
            color: #fff;
            font-weight: bold;
            padding: 10px 24px;
            border-radius: 6px;
            border: none;
            width: 100%;
        }
        .btn-custom:hover { background-color: var(--primary-hover); color: #fff; }
        .btn-custom:disabled { background-color: #c9b8dc; cursor: not-allowed; }
    </style>
</head>
<body>

<div class="container">
    <div class="fp-card">
        <div class="fp-icon">🖐️</div>
        <h4 class="font-weight-bold mb-2">Fingerprint / Passkey Enrollment</h4>
        <p class="text-muted small mb-4">
            Scan your fingerprint with your device's biometric sensor (Touch ID, Windows Hello, Android
            fingerprint, phone face unlock…). On a computer without a sensor, the system prompt can create
            the passkey on <strong>a nearby phone or a security key</strong> instead — just pick that option.
        </p>

        <div id="statusBox" class="alert alert-info py-2 d-none"></div>

        <button id="enrollBtn" class="btn btn-custom mb-3">Scan &amp; Register Fingerprint</button>
        <a href="login.php" class="text-muted small d-block">Skip for now &rarr; Go to Login</a>
    </div>
</div>

<script>
// ---------- WebAuthn helpers ----------
const b64url = {
    encode(buf) {
        const bytes = new Uint8Array(buf);
        let s = '';
        for (const b of bytes) s += String.fromCharCode(b);
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    },
    decode(str) {
        str = str.replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4) str += '=';
        const bin = atob(str);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return bytes;
    }
};

async function api(action, extra = {}) {
    const res = await fetch('webauthn_options.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...extra })
    });
    let data;
    try { data = await res.json(); } catch (e) { throw new Error('Server error (' + res.status + ').'); }
    if (!res.ok || data.success === false) throw new Error(data.message || ('Server error ' + res.status));
    return data;
}

function toServerCredential(cred) {
    return {
        id: cred.id,
        rawId: b64url.encode(cred.rawId),
        type: cred.type,
        response: {
            clientDataJSON: b64url.encode(cred.response.clientDataJSON),
            attestationObject: b64url.encode(cred.response.attestationObject)
        }
    };
}

function deviceLabel() {
    const ua = navigator.userAgent;
    let os = 'device';
    if (/Windows/.test(ua)) os = 'Windows';
    else if (/Mac OS X/.test(ua)) os = 'macOS';
    else if (/Android/.test(ua)) os = 'Android';
    else if (/iPhone|iPad/.test(ua)) os = 'iOS';
    else if (/Linux/.test(ua)) os = 'Linux';
    let br = 'browser';
    if (/Edg\//.test(ua)) br = 'Edge';
    else if (/Chrome\//.test(ua)) br = 'Chrome';
    else if (/Firefox\//.test(ua)) br = 'Firefox';
    else if (/Safari\//.test(ua)) br = 'Safari';
    return os + ' · ' + br;
}

function show(msg, kind) {
    const box = document.getElementById('statusBox');
    box.className = 'alert alert-' + (kind || 'info') + ' py-2 d-block';
    box.innerText = msg;
}

// ---------- Enrollment flow ----------
document.getElementById('enrollBtn').addEventListener('click', async () => {
    const btn = document.getElementById('enrollBtn');
    btn.disabled = true;

    if (!window.isSecureContext || !window.PublicKeyCredential) {
        show('WebAuthn / biometrics is not available here. Passkeys require localhost or an HTTPS connection. You can skip this step and enroll later from your dashboard.', 'danger');
        btn.disabled = false;
        return;
    }

    try {
        show('Preparing your enrollment...', 'info');
        const { options } = await api('register_begin');

        const publicKey = {
            challenge: b64url.decode(options.challenge),
            rp: options.rp,
            user: { ...options.user, id: b64url.decode(options.user.id) },
            pubKeyCredParams: options.pubKeyCredParams,
            timeout: options.timeout,
            attestation: options.attestation,
            authenticatorSelection: options.authenticatorSelection
        };
        if (options.excludeCredentials.length) {
            publicKey.excludeCredentials = options.excludeCredentials.map(c => ({ type: c.type, id: b64url.decode(c.id) }));
        }

        show('Please authenticate now — scan your fingerprint, or choose "Use another device" to enroll a phone or security key.', 'primary');
        const credential = await navigator.credentials.create({ publicKey });

        show('Verifying with the server...', 'info');
        await api('register_finish', { credential: toServerCredential(credential), label: deviceLabel() });

        show('✅ Fingerprint enrolled successfully!', 'success');
        setTimeout(() => { window.location.href = 'login.php'; }, 1400);
    } catch (err) {
        show('Enrollment cancelled or failed: ' + err.message, 'danger');
        btn.disabled = false;
    }
});
</script>
</body>
</html>
