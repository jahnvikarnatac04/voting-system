<?php
/**
 * voters/face_verify_api.php
 * ---------------------------------------------------------------
 * Server-side face verification for the pre-ballot biometric gate.
 *
 * The browser sends the live-capture descriptor + the reference
 * (registered photo) descriptor along with a one-time nonce. THIS
 * server computes the match, records an audit entry and — only on a
 * genuine match — sets the session flag that unlocks the ballot.
 *
 * Trust note: the descriptors are computed client-side (face-api.js
 * has no server build). What this endpoint adds is a real server
 * decision: the distance is recomputed here against a fixed
 * threshold, every attempt (with snapshot + IP) is logged, the
 * nonce is single-use, and there is no client-only "mark me
 * verified" endpoint left to call directly.
 * ---------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

const FACE_MATCH_THRESHOLD = 0.55; // Euclidean distance on 128-d descriptors

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

// ---------- auth ----------
if (empty($_SESSION['vid'])) {
    fjerr('Not logged in.', 401, 'AUTH');
}
$vid = (int)$_SESSION['vid'];

$stmt = $pdo->prepare("SELECT status FROM voters WHERE id = ? LIMIT 1");
$stmt->execute([$vid]);
$voter = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$voter || strtolower(trim($voter['status'])) !== 'approved') {
    fjerr('Your account must be approved before voting.', 403, 'NOT_APPROVED');
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    fjerr('Invalid JSON request.');
}
$action = trim($body['action'] ?? '');

switch ($action) {

    case 'begin':
        // One-time, expiring nonce bound to this session
        $_SESSION['face_nonce'] = bin2hex(random_bytes(24));
        $_SESSION['face_nonce_expiry'] = time() + 300;
        fj([
            'success'   => true,
            'nonce'     => $_SESSION['face_nonce'],
            'threshold' => FACE_MATCH_THRESHOLD,
        ]);
        // no break

    case 'match':
        $nonce = trim($body['nonce'] ?? '');
        if ($nonce === '' || empty($_SESSION['face_nonce']) || !hash_equals($_SESSION['face_nonce'], $nonce)) {
            fjerr('Verification session expired or invalid. Start again.', 400, 'NONCE');
        }
        if ((int)($_SESSION['face_nonce_expiry'] ?? 0) < time()) {
            unset($_SESSION['face_nonce'], $_SESSION['face_nonce_expiry']);
            fjerr('Verification request expired. Start again.', 400, 'NONCE_EXPIRED');
        }
        // Single use
        unset($_SESSION['face_nonce'], $_SESSION['face_nonce_expiry']);

        $live = $body['live_descriptor'] ?? null;
        $ref  = $body['ref_descriptor'] ?? null;
        $snapshot = trim($body['snapshot'] ?? ''); // optional small jpeg data-url for audit

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

        // ---- compute Euclidean distance server-side ----
        $sum = 0.0;
        for ($i = 0; $i < 128; $i++) {
            $d = (float)$live[$i] - (float)$ref[$i];
            $sum += $d * $d;
        }
        $distance = sqrt($sum);
        $matched  = $distance <= FACE_MATCH_THRESHOLD;

        // ---- save an audit snapshot of the live capture ----
        $saved_image = '';
        if (strlen($snapshot) > 0 && strlen($snapshot) < 400000) {
            $parts = explode(',', $snapshot, 2);
            $bin = isset($parts[1]) ? base64_decode($parts[1], true) : false;
            if ($bin !== false && strlen($bin) > 0) {
                $dir = __DIR__ . '/../images/face_audit/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $ext = (strpos($parts[0], 'png') !== false) ? 'png' : 'jpg';
                $name = 'face_' . $vid . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                file_put_contents($dir . $name, $bin);
                $saved_image = 'face_audit/' . $name;
            }
        }

        // ---- audit log ----
        try {
            $log = $pdo->prepare(
                "INSERT INTO biometric_logs (voter_id, method, matched, distance, image_file, ip_address)
                 VALUES (?, 'face', ?, ?, ?, ?)"
            );
            $log->execute([$vid, $matched ? 1 : 0, round($distance, 4), $saved_image, $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (PDOException $e) {
            // logging must never break the verification response
        }

        if ($matched) {
            $_SESSION['face_verified'] = true; // unlock the ballot
            fj([
                'success'  => true,
                'matched'  => true,
                'distance' => round($distance, 4),
                'redirect' => 'dashboard.php',
            ]);
        }

        fj([
            'success'  => true,
            'matched'  => false,
            'distance' => round($distance, 4),
            'threshold' => FACE_MATCH_THRESHOLD,
            'message'  => 'Face did not match the registered photo closely enough.',
        ]);
        // no break

    default:
        fjerr('Unknown action.', 400, 'ACTION');
}
