<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['vid'])) {
    header("Location: ../login.php");
    exit();
}

$vid = (int)$_SESSION['vid'];
$stmt = $pdo->prepare("SELECT status, fullname FROM voters WHERE id = ? LIMIT 1");
$stmt->execute([$vid]);
$voter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voter) {
    header("Location: ../logout.php");
    exit();
}
$status = strtolower(trim($voter['status'] ?? 'pending'));
if ($status !== 'approved') {
    header("Location: ../login.php");
    exit();
}

// Does this voter have at least one enrolled passkey?
$fp_stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM passkeys WHERE voter_id = ?");
$fp_stmt->execute([$vid]);
$has_fp = (int)$fp_stmt->fetch(PDO::FETCH_ASSOC)['c'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fingerprint Verification - Voting</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <style>
        :root { --primary-color: blueviolet; --primary-hover: #701eb8; }
        body { background-color: #f8f9fa; font-family: Arial, sans-serif; }
        .verify-card {
            background: #fff;
            border-radius: 12px;
            padding: 35px;
            max-width: 480px;
            margin: 60px auto;
            border: 1px solid #e0e0e0;
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            text-align: center;
        }
        .btn-custom {
            background-color: #28a745;
            color: #fff;
            font-weight: bold;
            padding: 10px 24px;
            border-radius: 6px;
            border: none;
            width: 100%;
        }
        .btn-custom:hover { background-color: #218838; color: #fff; }
        .btn-custom:disabled { background-color: #a9d5b5; cursor: not-allowed; }
    </style>
</head>
<body>

<div class="container">
    <div class="verify-card">
        <h4 class="font-weight-bold mb-2">🖐️ Fingerprint Verification</h4>
        <p class="text-muted small mb-4">
            Authorize your ballot by scanning the fingerprint you registered.
            On a computer without a sensor, pick <strong>"A phone or tablet"</strong>
            in the system prompt to approve from a device that has your passkey.
        </p>

        <div id="verifyStatus" class="alert alert-info py-2 d-none"></div>

        <?php if ($has_fp): ?>
            <button id="verifyFpBtn" class="btn btn-custom mb-3">Touch Fingerprint Sensor</button>
        <?php else: ?>
            <div class="alert alert-warning py-2 small mb-3">
                No fingerprint / passkey registered for this account yet.
                Add one before you can verify this way.
            </div>
            <a href="add_passkey.php" class="btn btn-primary w-100 font-weight-bold mb-2">Register Fingerprint / Passkey</a>
        <?php endif; ?>

        <a href="dashboard.php" class="text-muted small d-block">← Back to Dashboard</a>
    </div>
</div>

<?php if ($has_fp): ?>
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
    const box = document.getElementById('verifyStatus');
    box.className = 'alert alert-' + (kind || 'info') + ' py-2 d-block';
    box.innerText = msg;
}

document.getElementById('verifyFpBtn').addEventListener('click', async () => {
    const btn = document.getElementById('verifyFpBtn');
    btn.disabled = true;

    if (!window.isSecureContext || !window.PublicKeyCredential) {
        show('Fingerprint verification needs localhost or HTTPS. Use Face Verification instead.', 'danger');
        btn.disabled = false;
        return;
    }

    try {
        show('Preparing secure verification…', 'info');
        const { options } = await api('vote_begin');

        show('Touch your fingerprint — or choose "A phone or tablet" to approve from another device.', 'primary');
        const assertion = await navigator.credentials.get({
            publicKey: {
                challenge: b64url.decode(options.challenge),
                rpId: options.rpId,
                timeout: options.timeout,
                userVerification: options.userVerification
            }
        });

        const credential = {
            id: assertion.id,
            rawId: b64url.encode(assertion.rawId),
            type: assertion.type,
            response: {
                clientDataJSON: b64url.encode(assertion.response.clientDataJSON),
                authenticatorData: b64url.encode(assertion.response.authenticatorData),
                signature: b64url.encode(assertion.response.signature),
                userHandle: b64url.encode(assertion.response.userHandle)
            }
        };

        show('Verifying signature…', 'info');
        const res = await api('vote_finish', { credential });

        show('✅ Fingerprint verified! Authorizing ballot…', 'success');
        setTimeout(() => { window.location.href = res.redirect || 'dashboard.php'; }, 1000);
    } catch (err) {
        show('Verification failed: ' + err.message, 'danger');
        btn.disabled = false;
    }
});
</script>
<?php endif; ?>
</body>
</html>
