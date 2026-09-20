<?php
/**
 * kiosk/face_api.php
 * ---------------------------------------------------------------
 * Server-side FACE check for the booth kiosk.
 *
 * The kiosk window captures a live face (blink-twice liveness runs in
 * the browser), computes a 128-d descriptor for it and for the
 * citizen's reference photo, and posts both here. THIS server
 * recomputes the distance against a fixed threshold, writes the audit
 * row against the booth, and — only on a genuine match — sets the
 * session marker that (together with a live fingerprint verification)
 * authorizes a ballot.
 *
 * Trust note: descriptors are computed client-side (face-api.js has no
 * server build). What this endpoint adds is a real server decision, a
 * single-use nonce, a booth-scoped audit trail, and no client-only
 * "mark me verified" path.
 *
 * It also accepts `enroll_photo`: an operator-captured reference photo
 * for a citizen who has none, stored as `voters.face_photo` — so a
 * citizen onboarded with the generic placeholder can still be checked
 * by face at the booth.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/_kiosk.php';

header('Content-Type: application/json; charset=utf-8');

function fj($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function fjerr($message, $status = 400, $code = '')
{
    fj(['success' => false, 'message' => $message, 'code' => $code], $status);
}

// ---------- booth-scoped auth ----------
if (!kiosk_is_unlocked()) {
    fjerr('This kiosk is locked. Unlock the booth first.', 401, 'AUTH');
}
$booth    = kiosk_booth();
$booth_id = (int)$booth['id'];

$raw  = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    fjerr('Invalid JSON request.');
}
$action = trim($body['action'] ?? '');

/** The citizen this face check is for (armed verify target / fingerprint-verified). */
$target_id = kiosk_face_target_voter_id();

/** Load the citizen, or null. */
function kiosk_face_load_voter(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, fullname, status, photo, face_photo FROM voters WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Save a data-URL image into images/ and return the stored filename, or ''. */
function kiosk_save_data_url(string $data_url, string $prefix, int $voter_id): string
{
    if (strlen($data_url) === 0 || strlen($data_url) > 500000) {
        return '';
    }
    $parts = explode(',', $data_url, 2);
    if (count($parts) !== 2) {
        return '';
    }
    $bin = base64_decode($parts[1], true);
    if ($bin === false || strlen($bin) === 0) {
        return '';
    }
    $dir = __DIR__ . '/../images/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ext  = (stripos($parts[0], 'png') !== false) ? 'png' : 'jpg';
    $name = $prefix . '_' . $voter_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (@file_put_contents($dir . $name, $bin) === false) {
        return '';
    }
    return $name;
}

/** Audit a face event against the booth (never throws). */
function kiosk_log_face(PDO $pdo, int $voter_id, string $method, int $matched, ?float $distance, string $image_file, int $booth_id): void
{
    try {
        $log = $pdo->prepare(
            "INSERT INTO biometric_logs (voter_id, method, matched, distance, image_file, ip_address, booth_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $log->execute([
            $voter_id,
            $method,
            $matched,
            $distance === null ? null : round($distance, 4),
            $image_file,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $booth_id,
        ]);
    } catch (PDOException $e) {
        // logging must never break the response
    }
}

switch ($action) {

    /* --------------------------------------------------------------- */
    case 'begin':
        $voter = kiosk_face_load_voter($pdo, $target_id);
        if (!$voter) {
            fjerr('No citizen is armed for a face check. Select one at the booth first.', 409, 'NO_TARGET');
        }

        $_SESSION['kiosk_face_nonce']        = bin2hex(random_bytes(24));
        $_SESSION['kiosk_face_nonce_vid']    = (int)$voter['id'];
        $_SESSION['kiosk_face_nonce_expiry'] = time() + 300;

        $photo = kiosk_face_reference_photo($pdo, (int)$voter['id']);

        fj([
            'success'       => true,
            'nonce'         => $_SESSION['kiosk_face_nonce'],
            'threshold'     => KIOSK_FACE_THRESHOLD,
            'voter_id'      => (int)$voter['id'],
            'voter_name'    => (string)$voter['fullname'],
            'photo_url'     => $photo,
            'has_reference' => $photo !== '',
            'booth_id'      => $booth_id,
        ]);
        // no break

    /* --------------------------------------------------------------- */
    case 'match':
        $nonce = trim($body['nonce'] ?? '');
        if ($nonce === '' || empty($_SESSION['kiosk_face_nonce']) || !hash_equals($_SESSION['kiosk_face_nonce'], $nonce)) {
            fjerr('Face check session expired or invalid. Start again.', 400, 'NONCE');
        }
        if ((int)($_SESSION['kiosk_face_nonce_expiry'] ?? 0) < time()) {
            unset($_SESSION['kiosk_face_nonce'], $_SESSION['kiosk_face_nonce_vid'], $_SESSION['kiosk_face_nonce_expiry']);
            fjerr('Face check request expired. Start again.', 400, 'NONCE_EXPIRED');
        }
        // Single use.
        $nonce_vid = (int)($_SESSION['kiosk_face_nonce_vid'] ?? 0);
        unset($_SESSION['kiosk_face_nonce'], $_SESSION['kiosk_face_nonce_vid'], $_SESSION['kiosk_face_nonce_expiry']);

        $voter = kiosk_face_load_voter($pdo, $nonce_vid);
        if (!$voter) {
            fjerr('The armed citizen is no longer available. Start again.', 409, 'NO_TARGET');
        }

        $live = $body['live_descriptor'] ?? null;
        $ref  = $body['ref_descriptor'] ?? null;
        $snapshot = trim((string)($body['snapshot'] ?? ''));

        if (!is_array($live) || !is_array($ref) || count($live) !== 128 || count($ref) !== 128) {
            fjerr('Face descriptors missing or malformed.', 400, 'DESCRIPTOR');
        }
        foreach ([$live, $ref] as $desc) {
            foreach ($desc as $v) {
                if (!is_numeric($v) || !is_finite((float)$v)) {
                    fjerr('Face descriptor contains invalid values.', 400, 'DESCRIPTOR');
                }
            }
        }

        // ---- recompute the Euclidean distance server-side ----
        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) {
            $d = (float)$live[$i] - (float)$ref[$i];
            $sum += $d * $d;
        }
        $distance = sqrt($sum);
        $matched  = $distance <= KIOSK_FACE_THRESHOLD;

        // ---- audit snapshot of the live capture ----
        $saved_image = '';
        if (strlen($snapshot) > 0) {
            $dir = __DIR__ . '/../images/face_audit/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $parts = explode(',', $snapshot, 2);
            $bin = isset($parts[1]) ? base64_decode($parts[1], true) : false;
            if ($bin !== false && strlen($bin) > 0 && strlen($bin) < 400000) {
                $ext  = (stripos($parts[0], 'png') !== false) ? 'png' : 'jpg';
                $name = 'face_' . (int)$voter['id'] . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                if (@file_put_contents($dir . $name, $bin) !== false) {
                    $saved_image = 'face_audit/' . $name;
                }
            }
        }

        kiosk_log_face($pdo, (int)$voter['id'], 'face', $matched ? 1 : 0, $distance, $saved_image, $booth_id);

        if ($matched) {
            $_SESSION['booth_face_vid']  = (int)$voter['id'];
            $_SESSION['booth_face_name'] = (string)$voter['fullname'];
            $_SESSION['booth_face_at']   = time();
        }

        $fp_auth = kiosk_fingerprint_verified_voter();
        fj([
            'success'         => true,
            'matched'         => $matched,
            'distance'        => round($distance, 4),
            'threshold'       => KIOSK_FACE_THRESHOLD,
            'voter_id'        => (int)$voter['id'],
            'voter_name'      => (string)$voter['fullname'],
            'fingerprint_ok'  => $fp_auth !== null && $fp_auth['voter_id'] === (int)$voter['id'],
            // True only when BOTH biometrics are now satisfied for this citizen.
            'ballot_ready'    => kiosk_verified_voter() !== null,
            'message'         => $matched
                ? 'Face matched.'
                : 'Face did not match the reference photo closely enough.',
        ]);
        // no break

    /* --------------------------------------------------------------- */
    case 'enroll_photo':
        $voter = kiosk_face_load_voter($pdo, $target_id);
        if (!$voter) {
            fjerr('No citizen is armed. Select one at the booth first.', 409, 'NO_TARGET');
        }
        $snapshot = trim((string)($body['snapshot'] ?? ''));
        $name = kiosk_save_data_url($snapshot, 'reference', (int)$voter['id']);
        if ($name === '') {
            fjerr('Could not store the captured photo. Please retake it.', 400, 'PHOTO');
        }

        try {
            $upd = $pdo->prepare("UPDATE voters SET face_photo = ? WHERE id = ?");
            $upd->execute([$name, (int)$voter['id']]);
        } catch (PDOException $e) {
            fjerr('Database error while storing the reference photo.', 500, 'DB');
        }

        kiosk_log_face($pdo, (int)$voter['id'], 'face_enroll', 1, null, $name, $booth_id);

        fj([
            'success'   => true,
            'photo_url' => '../images/' . $name,
            'voter_id'  => (int)$voter['id'],
            'voter_name'=> (string)$voter['fullname'],
        ]);
        // no break

    default:
        fjerr('Unknown action.', 400, 'ACTION');
}
