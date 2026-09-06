<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['vid'])) {
    header("Location: ../login.php");
    exit();
}

$vid = (int)$_SESSION['vid'];

// Only approved voters manage passkeys (pending accounts enroll at signup only)
$stmt = $pdo->prepare("SELECT status FROM voters WHERE id = ? LIMIT 1");
$stmt->execute([$vid]);
$voter = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$voter || strtolower(trim($voter['status'])) !== 'approved') {
    header("Location: dashboard.php");
    exit();
}

// List existing passkeys
$list = [];
try {
    $stmt = $pdo->prepare("SELECT id, credential_id, device_label, created_at FROM passkeys WHERE voter_id = ? ORDER BY id DESC");
    $stmt->execute([$vid]);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $list = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Fingerprints / Passkeys - Online Voting System</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <style>
        :root { --primary-color: blueviolet; --primary-hover: #701eb8; }
        body { background-color: #f8f9fa; font-family: Arial, sans-serif; }
        .fp-card {
            background: #fff;
            border-radius: 12px;
            padding: 35px;
            max-width: 560px;
            margin: 50px auto;
            border: 1px solid #e0e0e0;
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
        }
        .btn-custom {
            background-color: var(--primary-color);
            color: #fff;
            font-weight: bold;
            border: none;
            width: 100%;
        }
        .btn-custom:hover { background-color: var(--primary-hover); color: #fff; }
        .btn-custom:disabled { background-color: #c9b8dc; cursor: not-allowed; }
        .passkey-row { display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>

<div class="container">
    <div class="fp-card">
        <div class="text-center">
            <div style="font-size: 44px;">🖐️</div>
            <h4 class="font-weight-bold mb-1">Fingerprints / Passkeys</h4>
            <p class="text-muted small mb-4">Devices you can use to sign in with your fingerprint.</p>
        </div>

        <div id="statusBox" class="alert alert-info py-2 d-none"></div>

        <!-- Existing passkeys -->
        <div id="passkeyList" class="mb-3">
            <?php if (empty($list)): ?>
                <div class="alert alert-warning py-2 text-center small mb-0" id="noPasskeys">
                    No fingerprints registered yet. Add one below — it can live on this device or on a phone you carry.
                </div>
            <?php else: ?>
                <?php foreach ($list as $pk): ?>
                    <div class="border rounded p-2 px-3 mb-2 passkey-row">
                        <div>
                            <strong class="small">🔑 <?= htmlspecialchars($pk['device_label'] ?: 'Fingerprint device'); ?></strong>
                            <br><small class="text-muted">Added <?= htmlspecialchars(date('j M Y', strtotime($pk['created_at']))); ?></small>
                        </div>
                        <button class="btn btn-outline-danger btn-sm del-btn" data-id="<?= htmlspecialchars($pk['credential_id']); ?>">Remove</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <button id="addBtn" class="btn btn-custom mb-2">➕ Add Fingerprint / Passkey</button>
        <a href="dashboard.php" class="btn btn-outline-secondary w-100">← Back to Dashboard</a>
    </div>
</div>

<script>
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
    const res = await fetch('../webauthn_options.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...extra })
    });
    let data;
    try { data = await res.json(); } catch (e) { throw new Error('Server error (' + res.status + ').'); }
    if (!res.ok || data.success === false) throw new Error(data.message || ('Server error ' + res.status));
    return data;
}

function show(msg, kind) {
    const box = document.getElementById('statusBox');
    box.className = 'alert alert-' + (kind || 'info') + ' py-2 d-block';
    box.innerText = msg;
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

document.getElementById('addBtn').addEventListener('click', async () => {
    const btn = document.getElementById('addBtn');
    btn.disabled = true;

    if (!window.isSecureContext || !window.PublicKeyCredential) {
        show('Passkeys require localhost or an HTTPS connection — this device cannot enroll right now.', 'danger');
        btn.disabled = false;
        return;
    }

    try {
        show('Preparing enrollment…', 'info');
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

        show('Authenticate now — scan your fingerprint, or choose "Use another device" to add your phone.', 'primary');
        const credential = await navigator.credentials.create({ publicKey });

        show('Verifying with the server…', 'info');
        const res = await api('register_finish', {
            credential: {
                id: credential.id,
                rawId: b64url.encode(credential.rawId),
                type: credential.type,
                response: {
                    clientDataJSON: b64url.encode(credential.response.clientDataJSON),
                    attestationObject: b64url.encode(credential.response.attestationObject)
                }
            },
            label: deviceLabel()
        });

        show('✅ Added successfully!', 'success');
        setTimeout(() => { window.location.reload(); }, 1000);
    } catch (err) {
        show('Failed to add: ' + err.message, 'danger');
        btn.disabled = false;
    }
});

// Remove a passkey
document.querySelectorAll('.del-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Remove this fingerprint/passkey? You will no longer be able to sign in with it.')) return;
        btn.disabled = true;
        try {
            await api('delete_credential', { credential_id: btn.dataset.id });
            window.location.reload();
        } catch (err) {
            alert('Could not remove passkey: ' + err.message);
            btn.disabled = false;
        }
    });
});
</script>
</body>
</html>
