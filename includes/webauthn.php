<?php
/**
 * includes/webauthn.php
 * ---------------------------------------------------------------
 * Dependency-free server-side WebAuthn (passkey / fingerprint)
 * support for the Online Voting System.
 *
 * Supported authenticators:
 *   - ES256 (alg -7,  ECDSA P-256)  -> every modern passkey provider
 *   - RS256 (alg -257, RSA)         -> older FIDO security keys
 *   - attestation format: "none"    (attestation is never inspected;
 *     the credential public key is taken from authenticatorData and
 *     later signatures are verified against it)
 *
 * The caller (webauthn_options.php) owns sessions and DB storage.
 * All functions throw \Exception on any verification failure.
 * ---------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------------- base64url ---------------- */

function wa_b64url_encode($bin)
{
    // PHP 8.1 deprecates passing null to string parameters; normalize here so a
    // stray null can never emit a notice into a JSON response body.
    if (!is_string($bin)) {
        $bin = $bin === null ? '' : (string)$bin;
    }
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function wa_b64url_decode($s)
{
    if (!is_string($s) || $s === '') {
        throw new Exception('Missing base64url value.');
    }
    $s = strtr($s, '-_', '+/');
    $pad = strlen($s) % 4;
    if ($pad > 0) {
        $s .= str_repeat('=', 4 - $pad);
    }
    $bin = base64_decode($s, true);
    if ($bin === false) {
        throw new Exception('Invalid base64url value.');
    }
    return $bin;
}

/* ---------------- configuration ---------------- */

/**
 * Read a WebAuthn setting. Precedence:
 *   1. includes/config.php  (returns an associative array)
 *   2. environment variable of the same name
 *   3. $default
 *
 * Useful keys:
 *   WEBAUTHN_RP_ID            e.g. "vote.example.org"
 *   WEBAUTHN_ALLOWED_ORIGINS  comma-separated, e.g.
 *                             "https://vote.example.org,https://booth.example.org"
 *
 * When neither is configured the RP ID / expected origin are derived
 * from the request Host header (previous behaviour, fine for localhost
 * and single-host deployments).
 */
function wa_config($key, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = [];
        $file = __DIR__ . '/config.php';
        if (is_file($file)) {
            $loaded = include $file;
            if (is_array($loaded)) {
                $cfg = $loaded;
            }
        }
    }
    if (array_key_exists($key, $cfg)) {
        return $cfg[$key];
    }
    $env = getenv($key);
    if ($env !== false && $env !== '') {
        return $env;
    }
    return $default;
}

/** Configured RP ID, or '' when unset. */
function wa_configured_rp_id()
{
    $v = wa_config('WEBAUTHN_RP_ID', '');
    return is_string($v) ? trim(strtolower($v)) : '';
}

/**
 * The configured origin allowlist, or null to fall back to the
 * request-derived origin. Entries are normalised: lower-cased, no
 * trailing slash.
 */
function wa_allowed_origins()
{
    $v = wa_config('WEBAUTHN_ALLOWED_ORIGINS', '');
    if (is_array($v)) {
        $list = $v;
    } else {
        $list = explode(',', (string)$v);
    }
    $list = array_values(array_filter(array_map(static function ($o) {
        return rtrim(strtolower(trim((string)$o)), '/');
    }, $list), static fn($o) => $o !== ''));

    return empty($list) ? null : $list;
}

/** Origins accepted in clientDataJSON (allowlist, else derived origin). */
function wa_expected_origins()
{
    $allowed = wa_allowed_origins();
    if ($allowed !== null) {
        return $allowed;
    }
    return [strtolower(wa_origin())];
}

/* ---------------- RP / origin helpers ---------------- */

function wa_host_header()
{
    return $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
}

function wa_is_https()
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    // Behind a reverse proxy (Cloudflare Tunnel, ngrok, nginx, ...) the edge
    // terminates TLS and forwards plain HTTP, so trust the forwarded headers.
    // X-Forwarded-Proto may be a chain ("https,http") — take the first hop.
    $xfp = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
    if ($xfp === 'https') {
        return true;
    }
    // Cloudflare-specific header (e.g. {"scheme":"https"}).
    $cf = json_decode($_SERVER['HTTP_CF_VISITOR'] ?? '', true);
    if (is_array($cf) && strtolower((string)($cf['scheme'] ?? '')) === 'https') {
        return true;
    }
    if (strtolower($_SERVER['HTTP_FRONT_END_HTTPS'] ?? '') === 'on') {
        return true;
    }
    return ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

/**
 * RP ID derived from the request Host header: hostname without port,
 * lowercased, trailing dot stripped (browsers may send a FQDN Host
 * such as "trycloudflare.com.").
 */
function wa_derive_rp_id()
{
    $host = wa_host_header();
    if (strncmp($host, '[', 1) === 0) { // IPv6 literal, e.g. [::1]:8000
        $end = strpos($host, ']');
        return strtolower($end ? substr($host, 1, $end - 1) : $host);
    }
    $host = strtolower(explode(':', $host)[0]);
    return rtrim($host, '.');
}

/**
 * Effective RP ID.
 *
 * A kiosk MUST have a stable RP ID: if this is derived from the Host
 * header, any hostname change (e.g. a rotating quick-tunnel URL)
 * invalidates every previously enrolled passkey. Set WEBAUTHN_RP_ID
 * (or includes/config.php) to pin it in production.
 */
function wa_rp_id()
{
    $configured = wa_configured_rp_id();
    if ($configured !== '') {
        return rtrim($configured, '.');
    }
    return wa_derive_rp_id();
}

/** True when the request Host is a loopback name (dev-only secure context). */
function wa_host_is_loopback()
{
    return in_array(wa_derive_rp_id(), ['localhost', '127.0.0.1', '::1'], true);
}

/** Full origin (scheme://host[:port]) as the browser sees it. */
function wa_origin()
{
    return (wa_is_https() ? 'https' : 'http') . '://' . wa_host_header();
}

/**
 * WebAuthn only runs in a secure context: real HTTPS, or plain HTTP
 * on localhost / loopback (dev machines).
 */
function wa_secure_context_ok()
{
    if (wa_is_https()) {
        return true;
    }
    return wa_host_is_loopback();
}

/* ---------------- DER encoding (for COSE -> PEM) ---------------- */

function wa_der_len($len)
{
    if ($len < 0x80) {
        return chr($len);
    }
    $bytes = '';
    while ($len > 0) {
        $bytes = chr($len & 0xff) . $bytes;
        $len >>= 8;
    }
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function wa_der_seq($content)
{
    return "\x30" . wa_der_len(strlen($content)) . $content;
}

function wa_der_oid(array $parts)
{
    $first = array_shift($parts);
    $second = array_shift($parts);
    $values = [$first * 40 + $second];
    foreach ($parts as $p) {
        $values[] = $p;
    }
    $body = '';
    foreach ($values as $v) {
        $groups = [];
        do {
            $groups[] = $v & 0x7f;
            $v >>= 7;
        } while ($v > 0);
        $n = count($groups);
        for ($i = $n - 1; $i >= 0; $i--) {
            $b = $groups[$i];
            if ($i > 0) {
                $b |= 0x80; // continuation bit on every group except the last
            }
            $body .= chr($b);
        }
    }
    return "\x06" . wa_der_len(strlen($body)) . $body;
}

function wa_der_int($bytes)
{
    if ($bytes === '' || (ord($bytes[0]) & 0x80)) {
        $bytes = "\x00" . $bytes; // keep positive
    }
    return "\x02" . wa_der_len(strlen($bytes)) . $bytes;
}

/* ---------------- Minimal CBOR decoder ---------------- */

function wa_cbor_decode($bin)
{
    $off = 0;
    $val = wa_cbor_decode_at($bin, $off);
    if ($off < strlen($bin)) {
        throw new Exception('Trailing bytes after CBOR item.');
    }
    return $val;
}

function wa_cbor_decode_at($bin, &$off)
{
    if ($off >= strlen($bin)) {
        throw new Exception('Truncated CBOR data.');
    }
    $ib = ord($bin[$off++]);
    $mt = $ib >> 5;
    $ai = $ib & 0x1f;

    if ($ai < 24) {
        $val = $ai;
    } elseif ($ai === 24) {
        $val = ord($bin[$off++]);
    } elseif ($ai === 25) {
        $val = unpack('n', substr($bin, $off, 2))[1];
        $off += 2;
    } elseif ($ai === 26) {
        $val = unpack('N', substr($bin, $off, 4))[1];
        $off += 4;
    } elseif ($ai === 27) {
        $hi = unpack('N', substr($bin, $off, 4))[1];
        $lo = unpack('N', substr($bin, $off + 4, 4))[1];
        $off += 8;
        $val = ($hi * 4294967296) + $lo;
    } else {
        throw new Exception('Unsupported CBOR length encoding.');
    }

    switch ($mt) {
        case 0: return $val;                          // unsigned int
        case 1: return -1 - $val;                     // negative int
        case 2:                                       // byte string
        case 3:                                       // text string
            $s = substr($bin, $off, $val);
            if (strlen($s) !== $val) {
                throw new Exception('Truncated CBOR string.');
            }
            $off += $val;
            return $s;
        case 4:                                       // array
            $a = [];
            for ($i = 0; $i < $val; $i++) {
                $a[] = wa_cbor_decode_at($bin, $off);
            }
            return $a;
        case 5:                                       // map
            $m = [];
            for ($i = 0; $i < $val; $i++) {
                $k = wa_cbor_decode_at($bin, $off);
                $m[$k] = wa_cbor_decode_at($bin, $off);
            }
            return $m;
        case 6:                                       // tag -> unwrap
            return wa_cbor_decode_at($bin, $off);
        case 7:                                       // simple / float
            if ($ai === 20) return false;
            if ($ai === 21) return true;
            if ($ai === 22 || $ai === 23) return null;
            throw new Exception('Unsupported CBOR simple/float value.');
    }
    throw new Exception('Invalid CBOR major type.');
}

/* ---------------- authenticatorData parsing ---------------- */

/**
 * Parses authenticatorData.
 * Flags: 0x01 UP (user present), 0x04 UV (user verified), 0x40 AT (attested credential data).
 */
function wa_parse_auth_data($ad)
{
    if (!is_string($ad) || strlen($ad) < 37) {
        throw new Exception('authenticatorData too short.');
    }
    $out = [
        'rp_id_hash'  => substr($ad, 0, 32),
        'flags'       => ord($ad[32]),
        'counter'     => unpack('N', substr($ad, 33, 4))[1],
        'credential_id' => null,
        'public_key'    => null,
    ];
    if ($out['flags'] & 0x40) { // attested credential data present
        $off = 37;
        if (strlen($ad) < $off + 16) {
            throw new Exception('Truncated AAGUID.');
        }
        $off += 16;
        $cred_len = unpack('n', substr($ad, $off, 2))[1];
        $off += 2;
        if (strlen($ad) < $off + $cred_len) {
            throw new Exception('Truncated credential id.');
        }
        $out['credential_id'] = substr($ad, $off, $cred_len);
        $off += $cred_len;
        $out['public_key'] = wa_cbor_decode_at($ad, $off); // COSE key map
    }
    return $out;
}

/* ---------------- COSE public key -> PEM ---------------- */

function wa_cose_to_pem(array $cose)
{
    $alg = $cose[3] ?? null;

    if ($alg === -7) { // ES256: EC2, P-256
        $crv = $cose[-1] ?? null;
        $x = $cose[-2] ?? null;
        $y = $cose[-3] ?? null;
        if ($crv !== 1) {
            throw new Exception('Unsupported EC curve for ES256 (expected P-256).');
        }
        if (!is_string($x) || strlen($x) !== 32 || !is_string($y) || strlen($y) !== 32) {
            throw new Exception('Malformed ES256 public key.');
        }
        // SubjectPublicKeyInfo: SEQ { SEQ { ecPublicKey, prime256v1 }, BIT STRING 0x04||x||y }
        $algo = wa_der_seq(wa_der_oid([1, 2, 840, 10045, 2, 1]) . wa_der_oid([1, 2, 840, 10045, 3, 1, 7]));
        $point = "\x00" . "\x04" . $x . $y;
        $spki = wa_der_seq($algo . "\x03" . wa_der_len(strlen($point)) . $point);
        return ['pem' => wa_pem($spki), 'alg' => -7];
    }

    if ($alg === -257) { // RS256
        $n = $cose[-1] ?? null;
        $e = $cose[-2] ?? null;
        if (!is_string($n) || $n === '' || !is_string($e) || $e === '') {
            throw new Exception('Malformed RS256 public key.');
        }
        $rsa = wa_der_seq(wa_der_int($n) . wa_der_int($e));
        $algo = wa_der_seq(wa_der_oid([1, 2, 840, 113549, 1, 1, 1]) . "\x05\x00"); // rsaEncryption + NULL
        $spki = wa_der_seq($algo . "\x03" . wa_der_len(strlen($rsa)) . $rsa);
        return ['pem' => wa_pem($spki), 'alg' => -257];
    }

    throw new Exception('Unsupported credential algorithm: ' . ($alg ?? '(none)') . '. Only ES256 and RS256 are supported.');
}

function wa_pem($der)
{
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/* ---------------- clientDataJSON checks ---------------- */

/**
 * Validates clientDataJSON. $expected_origin may be a single origin or
 * an allowlist array (see wa_expected_origins()).
 */
function wa_verify_client_data($raw_json, $expected_type, $expected_challenge, $expected_origin)
{
    $data = json_decode($raw_json, true);
    if (!is_array($data)) {
        throw new Exception('clientDataJSON is not valid JSON.');
    }
    if (($data['type'] ?? '') !== $expected_type) {
        throw new Exception('Unexpected clientData type.');
    }
    if (($data['challenge'] ?? '') !== $expected_challenge) {
        throw new Exception('Challenge mismatch — possible replay.');
    }
    $origin = strtolower((string)($data['origin'] ?? ''));
    $allowed = is_array($expected_origin)
        ? array_map('strtolower', $expected_origin)
        : [strtolower((string)$expected_origin)];
    if (!in_array($origin, $allowed, true)) {
        throw new Exception('Origin mismatch.');
    }
    return $data;
}

/* ---------------- challenge / session plumbing ---------------- */

function wa_new_challenge()
{
    return random_bytes(32);
}

function wa_store_challenge($action, $voter_id = null, $ttl = 180)
{
    $challenge = wa_b64url_encode(wa_new_challenge());
    $_SESSION['wa_challenge'] = [
        'challenge' => $challenge,
        'action'    => $action,
        'voter_id'  => $voter_id,
        'expires'   => time() + $ttl,
    ];
    return $challenge;
}

/** One-time retrieval of a pending challenge (deletes it). */
function wa_take_challenge($action, $expected_voter = null)
{
    if (empty($_SESSION['wa_challenge'])) {
        throw new Exception('No pending WebAuthn request. Please start again.');
    }
    $c = $_SESSION['wa_challenge'];
    unset($_SESSION['wa_challenge']);
    if (($c['action'] ?? '') !== $action) {
        throw new Exception('WebAuthn request type mismatch.');
    }
    if (($c['expires'] ?? 0) < time()) {
        throw new Exception('WebAuthn request expired. Please try again.');
    }
    if ($expected_voter !== null && (int)($c['voter_id'] ?? 0) !== (int)$expected_voter) {
        throw new Exception('Voter session mismatch.');
    }
    return $c['challenge'];
}

/* ---------------- userHandle mapping ---------------- */

function wa_user_handle($voter_id)
{
    return 'voter_' . (int)$voter_id; // stable ASCII bytes <= 64
}

function wa_voter_id_from_handle($handle)
{
    if (!is_string($handle)) {
        return null;
    }
    if (preg_match('/^voter_(\d+)$/', $handle, $m)) {
        return (int)$m[1];
    }
    return null;
}

/* ---------------- Registration (create) verification ---------------- */

/**
 * Verifies a full registration payload and returns the new credential:
 *   ['credential_id' => b64url, 'pem' => PEM string, 'alg' => int, 'sign_count' => int]
 */
function wa_verify_registration($payload, $expected_voter = null)
{
    $challenge = wa_take_challenge('register', $expected_voter);

    $client_raw = wa_b64url_decode($payload['response']['clientDataJSON'] ?? null);
    $origins = wa_expected_origins();
    $cd = wa_verify_client_data($client_raw, 'webauthn.create', $challenge, $origins);

    $att_obj_raw = wa_b64url_decode($payload['response']['attestationObject'] ?? null);
    $att = wa_cbor_decode($att_obj_raw);
    if (!is_array($att)) {
        throw new Exception('Malformed attestationObject.');
    }
    if (($att['fmt'] ?? '') !== 'none') {
        throw new Exception('Only "none" attestation is accepted by this system.');
    }
    if (empty($att['authData']) || !is_string($att['authData'])) {
        throw new Exception('Missing authenticatorData.');
    }

    $auth = wa_parse_auth_data($att['authData']);

    // authenticatorData rpIdHash must equal SHA-256 of our RP ID
    if (!hash_equals(hash('sha256', wa_rp_id(), true), $auth['rp_id_hash'])) {
        throw new Exception('RP ID hash mismatch.');
    }
    if (!($auth['flags'] & 0x01)) { // user present
        throw new Exception('User presence flag not set.');
    }
    if (!($auth['flags'] & 0x40)) { // attested credential data required at creation
        throw new Exception('Missing attested credential data.');
    }
    if (empty($auth['credential_id'])) {
        throw new Exception('Missing credential id.');
    }

    $key = wa_cose_to_pem($auth['public_key']);

    return [
        'credential_id' => wa_b64url_encode($auth['credential_id']),
        'pem'           => $key['pem'],
        'alg'           => $key['alg'],
        'sign_count'    => $auth['counter'],
    ];
}

/* ---------------- Assertion (get / login) verification ---------------- */

/**
 * Verifies an assertion payload against the stored public key.
 *   $stored: ['pem' => string, 'alg' => int, 'sign_count' => int]
 * Returns ['sign_count' => int] with the new counter already validated.
 */
function wa_verify_assertion($payload, array $stored, $expected_voter = null)
{
    $challenge = wa_take_challenge('login', null); // login/vote challenges are account-agnostic

    // userHandle is returned for resident (discoverable) passkeys. When a
    // credential is selected by id, some authenticators omit it — the caller
    // binds the credential row to its voter, so a missing handle is fine.
    $voter_id = null;
    if (!empty($payload['response']['userHandle'])) {
        $voter_id = wa_voter_id_from_handle(wa_b64url_decode($payload['response']['userHandle']));
    }
    if ($expected_voter !== null && $voter_id !== null && $voter_id !== (int)$expected_voter) {
        throw new Exception('This passkey belongs to a different voter account.');
    }

    $client_raw = wa_b64url_decode($payload['response']['clientDataJSON'] ?? null);
    $origins = wa_expected_origins();
    wa_verify_client_data($client_raw, 'webauthn.get', $challenge, $origins);

    $ad = wa_b64url_decode($payload['response']['authenticatorData'] ?? null);
    $auth = wa_parse_auth_data($ad);
    if (!hash_equals(hash('sha256', wa_rp_id(), true), $auth['rp_id_hash'])) {
        throw new Exception('RP ID hash mismatch.');
    }
    if (!($auth['flags'] & 0x01)) {
        throw new Exception('User presence flag not set.');
    }
    if (!($auth['flags'] & 0x04)) { // we always request userVerification: required
        throw new Exception('User verification (fingerprint/PIN) was not performed.');
    }

    $signature = wa_b64url_decode($payload['response']['signature'] ?? null);

    // Signed data = authenticatorData || SHA-256(original clientDataJSON bytes)
    $client_hash = hash('sha256', $client_raw, true);
    $signed = $ad . $client_hash;

    $pkey = openssl_pkey_get_public($stored['pem']);
    if ($pkey === false) {
        throw new Exception('Stored public key could not be loaded.');
    }
    $ok = openssl_verify($signed, $signature, $pkey, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        throw new Exception('Invalid passkey signature.');
    }

    // Counter check (detects cloned authenticators)
    $new_counter = $auth['counter'];
    if ($new_counter > 0) {
        if ($new_counter <= (int)$stored['sign_count']) {
            throw new Exception('Authenticator counter did not advance — possible clone.');
        }
    }

    // credential_id is only present in attested credential data (creation),
    // never in a get/assertion — encode it only when it exists.
    return [
        'voter_id'      => $voter_id,
        'sign_count'    => $new_counter,
        'credential_id' => $auth['credential_id'] !== null ? wa_b64url_encode($auth['credential_id']) : null,
    ];
}

/** Derive the authoritative voter id from an assertion result + stored row. */
function wa_resolve_assertion_voter(array $result, $row_voter_id)
{
    $row_voter_id = (int)$row_voter_id;
    if ($result['voter_id'] !== null && $result['voter_id'] !== $row_voter_id) {
        throw new Exception('Passkey identity mismatch.');
    }
    return $row_voter_id;
}
