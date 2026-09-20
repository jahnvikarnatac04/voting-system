<?php
/**
 * kiosk/index.php
 * ---------------------------------------------------------------
 * Phone kiosk terminal for ONE booth. Two jobs:
 *   1. ENROLL   — bind a citizen's fingerprint/passkey to their account.
 *   2. VERIFY   — confirm a citizen matches their enrolled credential.
 *
 * Both ceremonies are verified server-side (webauthn_options.php) and
 * logged with this booth's id. The kiosk has no admin powers and no
 * link out to the wider app.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/_kiosk.php';
require_once __DIR__ . '/../includes/i18n.php';
kiosk_require_unlocked();

$booth = kiosk_booth();

/* ---------------- Arm / clear a target ---------------- */

$mode = $_GET['mode'] ?? '';
$vid  = isset($_GET['vid']) ? (int)$_GET['vid'] : 0;

$clear_verified = ['booth_verified_vid', 'booth_verified_name', 'booth_verified_at'];

if ($mode === 'enroll') {
    foreach (array_merge($clear_verified, ['kiosk_verify_vid', 'kiosk_verify_name']) as $k) {
        unset($_SESSION[$k]);
    }
    if ($vid > 0) {
        $stmt = $pdo->prepare("SELECT id, fullname FROM voters WHERE id = ? LIMIT 1");
        $stmt->execute([$vid]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($v) {
            $_SESSION['kiosk_enroll_vid']  = (int)$v['id'];
            $_SESSION['kiosk_enroll_name'] = (string)$v['fullname'];
        }
    } else {
        unset($_SESSION['kiosk_enroll_vid'], $_SESSION['kiosk_enroll_name']);
    }
} elseif ($mode === 'verify') {
    foreach (array_merge($clear_verified, ['kiosk_enroll_vid', 'kiosk_enroll_name']) as $k) {
        unset($_SESSION[$k]);
    }
    if ($vid > 0) {
        $stmt = $pdo->prepare("SELECT id, fullname FROM voters WHERE id = ? LIMIT 1");
        $stmt->execute([$vid]);
        $v = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($v) {
            $_SESSION['kiosk_verify_vid']  = (int)$v['id'];
            $_SESSION['kiosk_verify_name'] = (string)$v['fullname'];
        }
    } else {
        unset($_SESSION['kiosk_verify_vid'], $_SESSION['kiosk_verify_name']);
    }
} elseif ($mode === 'clear') {
    foreach (array_merge($clear_verified, ['kiosk_enroll_vid', 'kiosk_enroll_name', 'kiosk_verify_vid', 'kiosk_verify_name']) as $k) {
        unset($_SESSION[$k]);
    }
}
kiosk_touch();

$enroll_vid = !empty($_SESSION['kiosk_enroll_vid']) ? (int)$_SESSION['kiosk_enroll_vid'] : 0;
$verify_vid = !empty($_SESSION['kiosk_verify_vid']) ? (int)$_SESSION['kiosk_verify_vid'] : 0;

/** Full details for an armed citizen, incl. how many fingerprints they have. */
function kiosk_voter_card(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT id, fullname, email, voter_id_number, status FROM voters WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $v = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$v) {
        return null;
    }
    $fp = $pdo->prepare("SELECT COUNT(*) FROM passkeys WHERE voter_id = ?");
    $fp->execute([$id]);
    $v['fp_count'] = (int)$fp->fetchColumn();
    return $v;
}

$armed = null;
$armed_action = '';
if ($enroll_vid > 0) {
    $armed = kiosk_voter_card($pdo, $enroll_vid);
    $armed_action = 'enroll';
} elseif ($verify_vid > 0) {
    $armed = kiosk_voter_card($pdo, $verify_vid);
    $armed_action = 'verify';
}

// Armed target vanished (deleted voter) → clear it.
if (($enroll_vid > 0 || $verify_vid > 0) && $armed === null) {
    unset($_SESSION['kiosk_enroll_vid'], $_SESSION['kiosk_verify_vid']);
    $armed = null;
    $armed_action = '';
}

/* ---------------- Citizen search ---------------- */

$q = trim($_GET['q'] ?? '');
$results = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare(
        "SELECT v.id, v.fullname, v.email, v.voter_id_number, v.status,
                (SELECT COUNT(*) FROM passkeys p WHERE p.voter_id = v.id) AS fp_count
         FROM voters v
         WHERE v.fullname LIKE ? OR v.email LIKE ? OR v.voter_id_number LIKE ?
         ORDER BY v.fullname ASC
         LIMIT 25"
    );
    $stmt->execute([$like, $like, $like]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Success banner from the previous verification.
$verified_vid  = !empty($_SESSION['booth_verified_vid']) ? (int)$_SESSION['booth_verified_vid'] : 0;
$verified_name = (string)($_SESSION['booth_verified_name'] ?? '');
if ($verified_vid > 0 && $armed === null && $verified_name === '') {
    $row = kiosk_voter_card($pdo, $verified_vid);
    $verified_name = $row['fullname'] ?? ('Voter #' . $verified_vid);
}

// Receipt from a ballot just cast on this device (POST -> redirect -> GET).
$receipt = $_SESSION['booth_vote_receipt'] ?? null;
unset($_SESSION['booth_vote_receipt']);

// Biometric progress for the citizen currently at the kiosk. A ballot needs
// BOTH a fingerprint verification and (while KIOSK_REQUIRE_FACE is on) a face
// check for the same person.
$fp_auth   = kiosk_fingerprint_verified_voter();
$face_auth = kiosk_face_verified_voter();
$both_done = $fp_auth !== null && $face_auth !== null && $fp_auth['voter_id'] === $face_auth['voter_id'];

// Is there a LIVE ballot authorization (both checks done moments ago)?
$ballot_auth = ($armed === null && $both_done) ? kiosk_verified_voter() : null;
$ballot_el   = null;
if ($ballot_auth !== null) {
    $ballot_el = kiosk_ballot_eligibility($pdo, (int)$ballot_auth['voter_id'], $booth['id']);
    $verified_name = $verified_name !== ''
        ? $verified_name
        : ($ballot_el['voter']['fullname'] ?? ('Voter #' . (int)$ballot_auth['voter_id']));
}

// Fingerprint done but the face check still outstanding → offer the face step.
$need_face = ($armed === null && $fp_auth !== null && !$both_done);
$face_pending_name = $need_face
    ? ($verified_name !== '' ? $verified_name : ('Voter #' . (int)$fp_auth['voter_id']))
    : '';

// Has the face check already passed for the citizen currently armed to verify?
$armed_face_done = ($armed !== null && $armed_action === 'verify'
    && $face_auth !== null && $face_auth['voter_id'] === (int)$armed['id']);

$expired = isset($_GET['expired']);

function status_pill(string $status): string
{
    $s = strtolower(trim($status));
    if ($s === 'approved') return '<span class="badge badge-success">Approved</span>';
    if ($s === 'rejected') return '<span class="badge badge-danger">Rejected</span>';
    return '<span class="badge badge-warning">Pending</span>';
}
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>"<?= i18n_is_rtl() ? ' dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= te('kiosk.booth_kiosk_prefix') ?><?= htmlspecialchars($booth['name']); ?></title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/app.css">
    <style>
        body { background-color: #f8f9fc; font-family: Arial, sans-serif; padding-bottom: 40px; }
        .header {
            background-color: var(--primary-color); color: #fff;
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 18px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            position: sticky; top: 0; z-index: 20;
        }
        .kiosk-card {
            background: #fff; border: 1px solid #e0e0e0; border-radius: 12px;
            padding: 22px; margin-top: 22px; box-shadow: 0 6px 20px rgba(0,0,0,0.06);
        }
        .kiosk-card.enroll { border-top: 5px solid #1f7a3f; }
        .kiosk-card.verify { border-top: 5px solid #0d6efd; }
        .btn-custom { background-color: var(--primary-color); color: #fff; font-weight: bold; border: none; }
        .scan-btn { font-size: 1.05rem; padding: 16px; }
        .result-row { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .big-icon { font-size: 42px; }
        .search-hit { border: 1px solid #e6e6ef; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
    </style>
    <link rel="stylesheet" href="../css/ui.css">
</head>
<body>
<?php render_lang_switcher(); ?>

<div class="header">
    <div>
        <div class="font-weight-bold"><?= te('kiosk.brand') ?></div>
        <span class="booth-chip">📍 <?= htmlspecialchars($booth['name']); ?> · <?= htmlspecialchars($booth['code']); ?></span>
    </div>
    <a href="logout.php" class="btn btn-light btn-sm font-weight-bold"><?= te('kiosk.lock') ?></a>
</div>

<div class="container" style="max-width: 760px;">

    <?php if ($receipt): ?>
        <div class="alert alert-success mt-3 mb-0 text-center">
            🗳️ <strong><?= htmlspecialchars($receipt['name'] ?? 'Citizen'); ?></strong> <?= te('kiosk.vote_recorded') ?><span class="d-block small"><?= htmlspecialchars($receipt['constituency'] ?? ''); ?> <?= te('kiosk.hand_next') ?></span>
        </div>
    <?php elseif ($ballot_auth !== null && $ballot_el !== null): ?>
        <!-- Fingerprint verified moments ago — offer the ballot (if eligible) -->
        <div class="kiosk-card verify">
            <div class="text-center mb-3">
                <div class="big-icon">🔎</div>
                <h5 class="font-weight-bold mb-1"><?= te('kiosk.fp_verified') ?></h5>
                <div class="voter-chip mb-2"><?= htmlspecialchars($verified_name); ?></div>
                <div class="small text-muted">
                    <?= te('kiosk.constituency') ?><strong><?= htmlspecialchars($ballot_el['constituency'] !== '' ? $ballot_el['constituency'] : '—'); ?></strong>
                    · valid for <?= (int)$ballot_auth['expires_in']; ?>s
                </div>
            </div>

            <?php if ($ballot_el['ok']): ?>
                <a href="ballot.php" class="btn btn-success w-100 scan-btn font-weight-bold">
                    <?= te('kiosk.open_ballot') ?><?= htmlspecialchars($verified_name); ?>
                </a>
                <p class="text-center text-muted small mt-2 mb-0">
                    <?= te('kiosk.hand_device') ?></p>
            <?php else: ?>
                <div class="alert alert-warning text-center small mb-2"><?= htmlspecialchars($ballot_el['reason']); ?></div>
                <p class="text-center text-muted small mb-2"><?= te('kiosk.no_ballot_here') ?></p>
            <?php endif; ?>

            <a href="index.php?mode=clear" class="btn btn-outline-secondary w-100 mt-2"><?= te('kiosk.next_citizen') ?></a>
        </div>
    <?php elseif ($need_face): ?>
        <!-- Fingerprint verified; the face check is still outstanding -->
        <div class="kiosk-card verify">
            <div class="text-center mb-3">
                <div class="big-icon">🖐️</div>
                <h5 class="font-weight-bold mb-1">Fingerprint verified</h5>
                <div class="voter-chip mb-2"><?= htmlspecialchars($face_pending_name); ?></div>
                <div class="small text-muted"><?= te('kiosk.face_required') ?></div>
            </div>
            <a href="face_verify.php" class="btn btn-primary w-100 scan-btn font-weight-bold">🙂 Start Face Check</a>
            <a href="index.php?mode=clear" class="btn btn-outline-secondary w-100 mt-2">Next citizen</a>
        </div>
    <?php elseif ($expired || $verified_vid > 0): ?>
        <div class="alert alert-warning mt-3 mb-0 text-center small">
            ⏱️ Verification expired or was cleared. Verify the citizen's fingerprint and face again to open their ballot.
        </div>
    <?php endif; ?>

    <?php if ($armed !== null): ?>
        <!-- ---------------- Scan step ---------------- -->
        <?php $is_enroll = ($armed_action === 'enroll'); ?>
        <div class="kiosk-card <?= $is_enroll ? 'enroll' : 'verify'; ?>">
            <div class="text-center mb-3">
                <div class="big-icon"><?= $is_enroll ? '🖐️' : '🔎'; ?></div>
                <h4 class="font-weight-bold mb-1">
                    <?= $is_enroll ? 'Enroll Fingerprint' : 'Verify Citizen'; ?>
                </h4>
                <div class="voter-chip mb-2">
                    <?= htmlspecialchars($armed['fullname']); ?> · <?= htmlspecialchars($armed['email']); ?>
                </div>
                <div class="small text-muted">
                    <?= te('kiosk.epic') ?><span class="badge badge-info"><?= htmlspecialchars($armed['voter_id_number'] ?? 'N/A'); ?></span>
                    <?= status_pill($armed['status']); ?>
                    · <?= (int)$armed['fp_count']; ?> fingerprint(s) on file
                </div>
            </div>

            <?php if (!$is_enroll && KIOSK_REQUIRE_FACE): ?>
                <div class="alert <?= $armed_face_done ? 'alert-success' : 'alert-info'; ?> text-center small">
                    <?php if ($armed_face_done): ?>
                        <?= te('kiosk.face_passed') ?><strong><?= te('kiosk.step2_label') ?></strong> <?= te('kiosk.step2_scan') ?><?php else: ?>
                        <strong><?= te('kiosk.step1_label') ?></strong> <?= te('kiosk.step1_face') ?><strong>Step 2:</strong> <?= te('kiosk.step2_fp') ?><?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!$is_enroll && (int)$armed['fp_count'] === 0): ?>
                <div class="alert alert-warning text-center small">
                    <?= te('kiosk.no_fp_use') ?><strong><?= te('kiosk.enroll') ?></strong> first.
                </div>
            <?php endif; ?>

            <?php if (!$is_enroll && KIOSK_REQUIRE_FACE && !$armed_face_done): ?>
                <a href="face_verify.php" class="btn btn-primary w-100 scan-btn font-weight-bold mb-2">
                    <?= te('kiosk.step1_start_face') ?></a>
            <?php endif; ?>

            <div id="scanStatus" class="alert alert-info py-2 text-center d-none"></div>

            <button id="scanBtn" class="btn btn-custom w-100 scan-btn"
                    data-action="<?= $is_enroll ? 'enroll' : 'verify'; ?>">
                <span id="scanBtnLabel"><?= $is_enroll ? 'Start Scan' : ($armed_face_done ? 'Step 2 — Verify Fingerprint' : 'Verify Fingerprint'); ?></span>
            </button>

            <a href="index.php?mode=clear" class="btn btn-outline-secondary w-100 mt-2"><?= te('kiosk.cancel_other') ?></a>
        </div>
    <?php else: ?>
        <!-- ---------------- Pick a citizen ---------------- -->
        <div class="kiosk-card">
            <h5 class="font-weight-bold mb-2"><?= te('kiosk.find_citizen') ?></h5>
            <p class="text-muted small mb-3">
                Search by name, email, or EPIC number. Then choose <strong>Enroll</strong> (first time) or
                <strong><?= te('kiosk.verify') ?></strong> (identity check-in).
            </p>
            <form method="GET" action="index.php" class="form-row">
                <div class="col-9 mb-2">
                    <input type="text" name="q" class="form-control" aria-label="<?= te('kiosk.search_aria') ?>" placeholder="<?= te('kiosk.search_ph') ?>"
                           value="<?= htmlspecialchars($q); ?>" autocomplete="off" autofocus>
                </div>
                <div class="col-3 mb-2">
                    <button type="submit" class="btn btn-custom w-100"><?= te('kiosk.search') ?></button>
                </div>
            </form>

            <?php if ($q !== ''): ?>
                <hr>
                <?php if (empty($results)): ?>
                    <div class="alert alert-secondary text-center small mb-0">
                        No citizen matched “<?= htmlspecialchars($q); ?>”.
                        <br><?= te('kiosk.walkin_note') ?></div>
                <?php else: ?>
                    <?php foreach ($results as $r): ?>
                        <div class="search-hit result-row">
                            <div>
                                <strong><?= htmlspecialchars($r['fullname']); ?></strong><br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($r['voter_id_number'] ?? 'No EPIC'); ?> ·
                                    <?= htmlspecialchars($r['email']); ?>
                                </small><br>
                                <?= status_pill($r['status']); ?>
                                <span class="badge badge-secondary"><?= (int)$r['fp_count']; ?> fp</span>
                            </div>
                            <div class="text-right" style="min-width: 150px;">
                                <a class="btn btn-sm btn-success btn-block mb-1"
                                   href="index.php?mode=enroll&vid=<?= (int)$r['id']; ?>&q=<?= urlencode($q); ?>">Enroll</a>
                                <?php if ((int)$r['fp_count'] === 0): ?>
                                    <span class="btn btn-sm btn-primary btn-block disabled"
                                          title="<?= te('kiosk.no_fp_title') ?>">Verify</span>
                                <?php else: ?>
                                    <a class="btn btn-sm btn-primary btn-block"
                                       href="index.php?mode=verify&vid=<?= (int)$r['id']; ?>&q=<?= urlencode($q); ?>">Verify</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <p class="text-center text-muted small mt-3 mb-0">
        Biometric data stays on the device; the booth only stores a public credential.
        Enrollment/verification is logged against <strong><?= htmlspecialchars($booth['code']); ?></strong>.
    </p>
</div>

<?php if ($armed !== null): ?>
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
        headers: {
            'Content-Type': 'application/json',
            // Suppresses the ngrok free-tier browser-warning interstitial,
            // which otherwise answers XHR/fetch with an HTML page (HTTP 200)
            // instead of our JSON. Harmless when not behind ngrok.
            'ngrok-skip-browser-warning': 'true'
        },
        body: JSON.stringify({ action, ...extra })
    });

    const raw = await res.text();
    let data;
    try {
        data = JSON.parse(raw);
    } catch (e) {
        // Surface WHAT came back instead of an opaque "Server error (200)".
        const snippet = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180);
        throw new Error('Server returned non-JSON (' + res.status + '): ' + (snippet || '(empty response)'));
    }
    if (!res.ok || data.success === false) throw new Error(data.message || ('Server error ' + res.status));
    return data;
}

function showScan(msg, kind) {
    const box = document.getElementById('scanStatus');
    box.className = 'alert alert-' + (kind || 'info') + ' py-2 text-center d-block';
    box.innerText = msg;
}

function deviceLabel() {
    const ua = navigator.userAgent;
    let os = 'device';
    if (/Android/.test(ua)) os = 'Android';
    else if (/iPhone|iPad/.test(ua)) os = 'iOS';
    else if (/Windows/.test(ua)) os = 'Windows';
    else if (/Mac OS X/.test(ua)) os = 'macOS';
    else if (/Linux/.test(ua)) os = 'Linux';
    return 'Booth kiosk: ' + os;
}

document.getElementById('scanBtn').addEventListener('click', async function () {
    const btn = this;
    const mode = btn.dataset.action;
    btn.disabled = true;

    if (!window.isSecureContext || !window.PublicKeyCredential) {
        showScan('Biometrics need a secure context — open the kiosk over HTTPS (or http://localhost).', 'danger');
        btn.disabled = false;
        return;
    }

    try {
        if (mode === 'enroll') {
            showScan('Preparing enrollment…', 'info');
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

            showScan('Ask the citizen to scan now.', 'primary');
            const credential = await navigator.credentials.create({ publicKey });

            showScan('Verifying with the server…', 'info');
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
            showScan('✅ Enrolled' + (res.enrolled_name ? ' for ' + res.enrolled_name : '') + '. Ready for the next citizen.', 'success');
            setTimeout(() => { window.location.href = 'index.php?mode=clear'; }, 1600);
        } else {
            showScan('Preparing verification…', 'info');
            const { options } = await api('verify_begin');
            const publicKey = {
                challenge: b64url.decode(options.challenge),
                rpId: options.rpId,
                timeout: options.timeout,
                userVerification: options.userVerification
            };
            if (options.allowCredentials && options.allowCredentials.length) {
                publicKey.allowCredentials = options.allowCredentials.map(c => ({ type: c.type, id: b64url.decode(c.id) }));
            }

            showScan('Ask the citizen to scan to confirm their identity.', 'primary');
            const assertion = await navigator.credentials.get({ publicKey });

            showScan('Verifying signature…', 'info');
            await api('verify_finish', {
                credential: {
                    id: assertion.id,
                    rawId: b64url.encode(assertion.rawId),
                    type: assertion.type,
                    response: {
                        clientDataJSON: b64url.encode(assertion.response.clientDataJSON),
                        authenticatorData: b64url.encode(assertion.response.authenticatorData),
                        signature: b64url.encode(assertion.response.signature),
                        userHandle: assertion.response.userHandle
                            ? b64url.encode(assertion.response.userHandle) : null
                    }
                }
            });
            showScan('✅ Verified!', 'success');
            // Plain index.php (NOT mode=clear) so the "verified" banner is
            // preserved for the operator; the target is already disarmed
            // server-side.
            setTimeout(() => { window.location.href = 'index.php'; }, 1600);
        }
    } catch (err) {
        showScan('Failed: ' + err.message, 'danger');
        btn.disabled = false;
    }
});
</script>
<?php endif; ?>

<script>
// Auto-lock the kiosk after inactivity.
(function () {
    const idleMs = <?= (int)KIOSK_IDLE_SECONDS; ?> * 1000;
    let timer;
    function reset() {
        clearTimeout(timer);
        timer = setTimeout(() => { window.location.href = 'login.php?timeout=1'; }, idleMs);
    }
    ['mousemove', 'keydown', 'touchstart', 'click', 'scroll'].forEach(function (e) {
        document.addEventListener(e, reset, { passive: true });
    });
    reset();
})();
</script>
</body>
</html>
