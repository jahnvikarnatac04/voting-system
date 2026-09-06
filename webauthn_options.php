<?php
/**
 * webauthn_options.php
 * ---------------------------------------------------------------
 * JSON API driving every WebAuthn (passkey / fingerprint) ceremony.
 *
 * Actions:
 *   register_begin / register_finish  -> enroll a NEW passkey for a voter
 *        (after signup, or later from the dashboard)
 *   login_begin    / login_finish     -> sign in with an existing passkey
 *   vote_begin     / vote_finish      -> fingerprint check before casting a ballot
 *   delete_credential                 -> remove one of your passkeys
 *
 * Everything is verified server-side (challenge, origin, signature,
 * counter). Only "none" attestation + ES256/RS256 are accepted.
 * ---------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/webauthn.php';

header('Content-Type: application/json; charset=utf-8');

function wa_json($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data);
    exit();
}

function wa_json_error($message, $status = 400, $code = '')
{
    wa_json(['success' => false, 'message' => $message, 'code' => $code], $status);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw ?: '', true);
if (!is_array($body)) {
    wa_json_error('Invalid JSON request.');
}
$action = trim($body['action'] ?? '');

/** Current voter: logged-in session, or a voter mid signup (temp_fp_voter_id). */
function wa_target_voter($pdo)
{
    $id = $_SESSION['vid'] ?? $_SESSION['temp_fp_voter_id'] ?? null;
    if ($id === null) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM voters WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$id]);
    $voter = $stmt->fetch(PDO::FETCH_ASSOC);
    return $voter ?: null;
}

function wa_voter_passkey_ids($pdo, $voter_id)
{
    $stmt = $pdo->prepare("SELECT credential_id FROM passkeys WHERE voter_id = ?");
    $stmt->execute([(int)$voter_id]);
    return array_map(fn($r) => $r['credential_id'], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function wa_begin_guard()
{
    if (!wa_secure_context_ok()) {
        wa_json_error(
            'WebAuthn (fingerprint / passkey) only works on localhost or over HTTPS. ' .
            'Open the site as http://localhost (or a deployed https:// address) to use it.',
            403, 'INSECURE_CONTEXT'
        );
    }
}

switch ($action) {

    /* ---------------- Enrollment ---------------- */

    case 'register_begin':
        wa_begin_guard();
        $voter = wa_target_voter($pdo);
        if (!$voter) {
            wa_json_error('You must be logged in (or completing registration) to add a passkey.', 401);
        }
        $challenge = wa_store_challenge('register', (int)$voter['id']);
        wa_json([
            'success' => true,
            'options' => [
                'challenge' => $challenge,
                'rp'        => ['name' => 'Online Voting System', 'id' => wa_rp_id()],
                'user'      => [
                    'id'          => wa_b64url_encode(wa_user_handle((int)$voter['id'])),
                    'name'        => $voter['email'],
                    'displayName' => $voter['fullname'],
                ],
                'pubKeyCredParams' => [
                    ['type' => 'public-key', 'alg' => -7],
                    ['type' => 'public-key', 'alg' => -257],
                ],
                'timeout'     => 120000,
                'attestation' => 'none',
                'authenticatorSelection' => [
                    'residentKey'      => 'required',
                    'userVerification' => 'required',
                ],
                'excludeCredentials' => array_map(
                    fn($id) => ['type' => 'public-key', 'id' => $id],
                    wa_voter_passkey_ids($pdo, (int)$voter['id'])
                ),
            ],
        ]);
        // no break

    case 'register_finish':
        $voter = wa_target_voter($pdo);
        if (!$voter) {
            wa_json_error('Your registration session expired. Please log in again.', 401);
        }
        try {
            $cred = wa_verify_registration($body['credential'] ?? [], (int)$voter['id']);

            $dup = $pdo->prepare("SELECT id FROM passkeys WHERE credential_id = ? LIMIT 1");
            $dup->execute([$cred['credential_id']]);
            if ($dup->fetch()) {
                wa_json_error('This passkey is already registered.');
            }

            $label = trim($body['label'] ?? '');
            $insert = $pdo->prepare(
                "INSERT INTO passkeys (voter_id, credential_id, public_key, alg, sign_count, device_label)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $insert->execute([
                (int)$voter['id'],
                $cred['credential_id'],
                $cred['pem'],
                $cred['alg'],
                $cred['sign_count'],
                $label,
            ]);

            // Mid-signup enrollment finished -> clear the temporary flag
            if (isset($_SESSION['temp_fp_voter_id'])) {
                unset($_SESSION['temp_fp_voter_id']);
            }
            wa_json(['success' => true, 'credential_id' => $cred['credential_id']]);
        } catch (Exception $e) {
            wa_json_error($e->getMessage(), 400, 'VERIFY_FAILED');
        }
        // no break

    /* ---------------- Login (passkey-first) ---------------- */

    case 'login_begin':
        wa_begin_guard();
        $challenge = wa_store_challenge('login');
        wa_json([
            'success' => true,
            'options' => [
                'challenge'      => $challenge,
                'rpId'           => wa_rp_id(),
                'timeout'        => 120000,
                'userVerification' => 'required',
                // No allowCredentials list + resident keys => the browser offers
                // this device's fingerprints AND the Google-style "use a phone
                // or tablet / security key" cross-device flow automatically.
            ],
        ]);
        // no break

    case 'login_finish':
        try {
            $cred_id = trim($body['credential']['id'] ?? '');
            $stmt = $pdo->prepare(
                "SELECT p.*, v.status AS voter_status, v.fullname, v.email, v.voter_id_number,
                        v.mobile, v.photo, v.has_voted, v.voting
                 FROM passkeys p JOIN voters v ON v.id = p.voter_id
                 WHERE p.credential_id = ? LIMIT 1"
            );
            $stmt->execute([$cred_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                wa_json_error('Unknown passkey. Please re-register this device.', 400, 'UNKNOWN_CREDENTIAL');
            }

            $result = wa_verify_assertion(
                $body['credential'] ?? [],
                ['pem' => $row['public_key'], 'alg' => (int)$row['alg'], 'sign_count' => (int)$row['sign_count']],
                null
            );
            $voter_id = wa_resolve_assertion_voter($result, (int)$row['voter_id']);

            // Update anti-clone counter
            $upd = $pdo->prepare("UPDATE passkeys SET sign_count = ? WHERE id = ?");
            $upd->execute([$result['sign_count'], (int)$row['id']]);

            $status = strtolower(trim($row['voter_status'] ?? 'pending'));
            if ($status === 'pending') {
                wa_json(['success' => false, 'message' => 'Your citizenship verification is pending admin review. Please wait for approval.', 'code' => 'PENDING']);
            }
            if ($status === 'rejected') {
                wa_json(['success' => false, 'message' => 'Your registration was rejected during verification. Please contact support.', 'code' => 'REJECTED']);
            }

            // Authenticate session
            $_SESSION['vid']       = $voter_id;
            $_SESSION['name']      = $row['fullname'];
            $_SESSION['email']     = $row['email'];
            $_SESSION['id_number'] = $row['voter_id_number'];
            $_SESSION['image']     = $row['photo'] ?: 'default.png';
            $_SESSION['voting']    = ((int)$row['has_voted'] === 1) ? 'yes' : 'no';
            $_SESSION['status']    = 'approved';

            wa_json(['success' => true, 'redirect' => 'voters/dashboard.php']);
        } catch (Exception $e) {
            wa_json_error($e->getMessage(), 400, 'VERIFY_FAILED');
        }
        // no break

    /* ---------------- Fingerprint check before voting ---------------- */

    case 'vote_begin':
        wa_begin_guard();
        if (empty($_SESSION['vid'])) {
            wa_json_error('Not logged in.', 401);
        }
        $stmt = $pdo->prepare("SELECT status FROM voters WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$_SESSION['vid']]);
        $voter = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$voter || strtolower(trim($voter['status'])) !== 'approved') {
            wa_json_error('Your account must be approved before voting.', 403);
        }
        wa_store_challenge('login');
        wa_json([
            'success' => true,
            'options' => [
                'challenge'      => $_SESSION['wa_challenge']['challenge'],
                'rpId'           => wa_rp_id(),
                'timeout'        => 120000,
                'userVerification' => 'required',
            ],
        ]);
        // no break

    case 'vote_finish':
        if (empty($_SESSION['vid'])) {
            wa_json_error('Not logged in.', 401);
        }
        try {
            $cred_id = trim($body['credential']['id'] ?? '');
            $stmt = $pdo->prepare("SELECT * FROM passkeys WHERE voter_id = ? AND credential_id = ? LIMIT 1");
            $stmt->execute([(int)$_SESSION['vid'], $cred_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                wa_json_error('No registered fingerprint found for this account. Enroll one first.', 400, 'NO_CREDENTIAL');
            }

            $result = wa_verify_assertion(
                $body['credential'] ?? [],
                ['pem' => $row['public_key'], 'alg' => (int)$row['alg'], 'sign_count' => (int)$row['sign_count']],
                (int)$_SESSION['vid']
            );

            $upd = $pdo->prepare("UPDATE passkeys SET sign_count = ? WHERE id = ?");
            $upd->execute([$result['sign_count'], (int)$row['id']]);

            $_SESSION['face_verified'] = true; // biometric gate for ballot access
            wa_json(['success' => true, 'redirect' => 'dashboard.php']);
        } catch (Exception $e) {
            wa_json_error($e->getMessage(), 400, 'VERIFY_FAILED');
        }
        // no break

    /* ---------------- Passkey management ---------------- */

    case 'delete_credential':
        if (empty($_SESSION['vid'])) {
            wa_json_error('Not logged in.', 401);
        }
        $cred_id = trim($body['credential_id'] ?? '');
        if ($cred_id === '') {
            wa_json_error('Missing credential id.');
        }
        $del = $pdo->prepare("DELETE FROM passkeys WHERE voter_id = ? AND credential_id = ?");
        $del->execute([(int)$_SESSION['vid'], $cred_id]);
        wa_json(['success' => true]);
        // no break

    default:
        wa_json_error('Unknown action: ' . htmlspecialchars($action));
}
