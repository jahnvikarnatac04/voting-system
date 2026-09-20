<?php
/**
 * scripts/tests/e2e_booth_voting.php
 * ===================================================================
 * END-TO-END VERIFICATION — two booths, regional voting rules, face biometrics.
 *
 * What this proves, against the REAL application code over HTTP:
 *   1. Two polling booths exist in different constituencies.
 *   2. A kiosk unlocks only for its own booth (code + PIN), scoped session.
 *   3. A booth refuses a citizen from another constituency.
 *   4. A citizen with no constituency, or not approved, cannot vote.
 *   5. One person, one vote is enforced (double submit refused).
 *   6. A candidate from another constituency cannot be voted for.
 *   7. Cross-booth: the same citizen is refused at the wrong booth.
 *   8. The ballot authorization expires after the 120s window.
 *   9. CSRF protects the vote endpoint.
 *  10. The face-match decision is computed and thresholded SERVER-side,
 *      the nonce is single-use, and every attempt is audited.
 *  11. A booth can capture a citizen's reference face photo in person, which
 *      is recorded, audited against the booth, and then used for checks.
 *
 * It runs against an ISOLATED database (VOTING_DB_PATH) and a throwaway
 * PHP built-in server. Your real voting_system.db is never touched.
 *
 * Run:  php scripts/tests/e2e_booth_voting.php
 * Exit: 0 = all passed, 1 = failures.
 * ===================================================================
 */

$ROOT = dirname(__DIR__, 2);

/* ------------------------------------------------------------------ */
/* Matrix sub-mode                                                     */
/*                                                                     */
/* Runs the eligibility checks in a CLEAN child process (no prior      */
/* output, so session_start() inside _kiosk.php is safe) and prints    */
/* JSON. The main harness invokes this and asserts on the result.      */
/* ------------------------------------------------------------------ */
if (($argv[1] ?? '') === '--matrix') {
    require $ROOT . '/db.php';
    require $ROOT . '/kiosk/_kiosk.php';

    $bStmt = $pdo->prepare("SELECT id FROM booths WHERE code = ? LIMIT 1");
    $vStmt = $pdo->prepare("SELECT id FROM voters WHERE email = ? LIMIT 1");

    $cases = [
        'V1@A' => ['alice@e2e.test', 'BOOTH-A'],
        'V1@B' => ['alice@e2e.test', 'BOOTH-B'],
        'V3@A' => ['carol@e2e.test', 'BOOTH-A'],
        'V4@A' => ['dave@e2e.test',  'BOOTH-A'],
        'V5@A' => ['erin@e2e.test',  'BOOTH-A'],
    ];

    $out = [];
    foreach ($cases as $key => [$email, $code]) {
        $vStmt->execute([$email]);
        $vid = (int)$vStmt->fetchColumn();
        $bStmt->execute([$code]);
        $bid = (int)$bStmt->fetchColumn();
        $r = kiosk_ballot_eligibility($pdo, $vid, $bid);
        $out[$key] = ['ok' => $r['ok'], 'reason' => $r['reason']];
    }
    echo json_encode($out);
    exit(0);
}

/* ------------------------------------------------------------------ */
/* Harness                                                             */
/* ------------------------------------------------------------------ */

$GLOBALS['results'] = [];
$GLOBALS['failed']  = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    $GLOBALS['results'][] = [$ok, $name, $detail];
    if (!$ok) {
        $GLOBALS['failed']++;
    }
    printf("  %s %s%s\n", $ok ? '[PASS]' : '[FAIL]', $name, $detail !== '' ? "  ($detail)" : '');
}

function section(string $title): void
{
    echo "\n" . $title . "\n" . str_repeat('-', strlen($title)) . "\n";
}

/* ------------------------------------------------------------------ */
/* HTTP client (curl, falling back to streams)                         */
/* ------------------------------------------------------------------ */

function http(string $url, ?string $body = null, ?string $cookie = null, array $extraHeaders = []): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $headers = $extraHeaders;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($cookie !== null) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw  = curl_exec($ch);
        $info = curl_getinfo($ch);
        $hs = (int)($info['header_size'] ?? 0);
        return [
            'status'  => (int)($info['http_code'] ?? 0),
            'headers' => substr((string)$raw, 0, $hs),
            'body'    => substr((string)$raw, $hs),
        ];
    }

    $opts = ['http' => ['method' => $body !== null ? 'POST' : 'GET', 'ignore_errors' => true, 'timeout' => 15]];
    if ($body !== null) {
        $opts['http']['content'] = $body;
        $opts['http']['header']  = "Content-Type: application/x-www-form-urlencoded\r\n";
    }
    if ($cookie !== null) {
        $opts['http']['header'] = ($opts['http']['header'] ?? '') . "Cookie: $cookie\r\n";
    }
    $ctx  = stream_context_create($opts);
    $bodyOut = @file_get_contents($url, false, $ctx);
    $status  = 0;
    $hdr     = '';
    // Avoid the (PHP 8.5-deprecated) locally scoped $http_response_header.
    if (function_exists('http_get_last_response_headers')) {
        $h = http_get_last_response_headers();
        if (is_array($h)) {
            $hdr = implode("\r\n", $h);
            if (isset($h[0]) && preg_match('#\s(\d{3})\s#', $h[0], $m)) {
                $status = (int)$m[1];
            }
        }
    }
    return ['status' => $status, 'headers' => $hdr, 'body' => (string)$bodyOut];
}

function location_of(array $resp): string
{
    if (preg_match('/^Location:\s*(.+)$/im', $resp['headers'], $m)) {
        return trim($m[1]);
    }
    return '';
}

function set_cookie(array $resp, string $name): string
{
    if (preg_match('/^Set-Cookie:\s*' . preg_quote($name, '/') . '=([^;]+)/im', $resp['headers'], $m)) {
        return $m[1];
    }
    return '';
}

/* ------------------------------------------------------------------ */
/* Session crafting                                                    */
/* ------------------------------------------------------------------ */

/**
 * Write a PHP session file directly, so we can test pages that require a
 * verified-voter / unlocked-kiosk session without performing a WebAuthn
 * ceremony (which needs a real authenticator).
 */
function write_session(string $dir, string $id, array $data): void
{
    $out = '';
    foreach ($data as $k => $v) {
        $out .= $k . '|' . serialize($v);
    }
    file_put_contents($dir . '/sess_' . $id, $out);
}

function kiosk_session(string $dir, int $boothId, string $boothCode, string $boothName, int $verifiedVoterId, string $csrf, ?int $verifiedAt = null): string
{
    $id = 'e2e' . bin2hex(random_bytes(8));
    write_session($dir, $id, [
        'kiosk_booth_id'      => $boothId,
        'kiosk_booth_code'    => $boothCode,
        'kiosk_booth_name'    => $boothName,
        'kiosk_started'       => time(),
        'kiosk_last_seen'     => time(),
        'kiosk_csrf'          => $csrf,
        // A fully verified citizen: BOTH the fingerprint and the face check.
        'booth_verified_vid'  => $verifiedVoterId,
        'booth_verified_name' => 'E2E Voter',
        'booth_verified_at'   => $verifiedAt ?? time(),
        'booth_face_vid'      => $verifiedVoterId,
        'booth_face_name'     => 'E2E Voter',
        'booth_face_at'       => $verifiedAt ?? time(),
    ]);
    return $id;
}

/** A session with ONLY the fingerprint check — the face check is missing. */
function kiosk_session_fp_only(string $dir, int $boothId, string $boothCode, string $boothName, int $voterId, string $csrf): string
{
    $id = 'e2e' . bin2hex(random_bytes(8));
    write_session($dir, $id, [
        'kiosk_booth_id'      => $boothId,
        'kiosk_booth_code'    => $boothCode,
        'kiosk_booth_name'    => $boothName,
        'kiosk_started'       => time(),
        'kiosk_last_seen'     => time(),
        'kiosk_csrf'          => $csrf,
        'booth_verified_vid'  => $voterId,
        'booth_verified_name' => 'E2E Voter',
        'booth_verified_at'   => time(),
    ]);
    return $id;
}

/* ------------------------------------------------------------------ */
/* Fixture setup                                                       */
/* ------------------------------------------------------------------ */

$TMP  = sys_get_temp_dir() . '/voting-e2e-' . bin2hex(random_bytes(4));
$DB   = $TMP . '/test.db';
$SESS = $TMP . '/sessions';
mkdir($TMP, 0700, true);
mkdir($SESS, 0700, true);

$PORT = 8199;
$BASE = "http://127.0.0.1:$PORT";

echo "=====================================================\n";
echo " E2E: booth + regional voting rules + face biometrics\n";
echo "=====================================================\n";
echo "isolated db : $DB\n";
echo "server      : $BASE\n";

putenv("VOTING_DB_PATH=$DB");

// Tables the app expects (mirrors init_db.php), then db.php adds booths,
// kiosk_auth_attempts, passkeys, biometric_logs and the constituency column.
$bootstrap = new PDO('sqlite:' . $DB);
$bootstrap->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$bootstrap->exec("
CREATE TABLE IF NOT EXISTS voters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fullname TEXT NOT NULL, email TEXT NOT NULL UNIQUE,
    voter_id_number TEXT NOT NULL UNIQUE, password TEXT NOT NULL,
    mobile TEXT, address TEXT, photo TEXT DEFAULT 'default.png',
    document_proof TEXT, status TEXT NOT NULL DEFAULT 'pending',
    has_voted INTEGER NOT NULL DEFAULT 0, voting TEXT DEFAULT 'no',
    fingerprint_credential TEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS candidates (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, party TEXT NOT NULL,
    photo TEXT DEFAULT 'default.png', votes_count INTEGER NOT NULL DEFAULT 0,
    constituency TEXT DEFAULT '', created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");
require $ROOT . '/db.php'; // defines $pdo + booths/kiosk tables/columns

const CONST_A = 'Varanasi (PC-77)';
const CONST_B = 'Mumbai South (PC-30)';

$booths = [
    'A' => ['code' => 'BOOTH-A', 'name' => 'Booth A — Varanasi', 'pin' => '111111', 'const' => CONST_A],
    'B' => ['code' => 'BOOTH-B', 'name' => 'Booth B — Mumbai South', 'pin' => '222222', 'const' => CONST_B],
];

$boothIds = [];
$insB = $pdo->prepare("INSERT INTO booths (code, name, state, constituency, pin_hash, active) VALUES (?,?,?,?,?,1)");
foreach ($booths as $k => $b) {
    $insB->execute([$b['code'], $b['name'], '', $b['const'], password_hash($b['pin'], PASSWORD_DEFAULT)]);
    $boothIds[$k] = (int)$pdo->lastInsertId();
}

$insC = $pdo->prepare("INSERT INTO candidates (name, party, constituency, votes_count) VALUES (?,?,?,0)");
$cand = [];
foreach ([[ 'Arjun Rao','Party A', CONST_A ], [ 'Bina Shah','Party B', CONST_A ],
          [ 'Chetan Iyer','Party C', CONST_B ], [ 'Divya Nair','Party D', CONST_B ]] as $c) {
    $insC->execute($c);
    $cand[] = (int)$pdo->lastInsertId();
}
[$candA1, $candA2, $candB1, $candB2] = $cand;

// voters: [key, name, constituency, status, alreadyVoted]
$votersSpec = [
    'V1' => ['Alice',   CONST_A, 'approved', 0],
    'V2' => ['Bob',     CONST_A, 'approved', 0],
    'V3' => ['Carol',   CONST_A, 'approved', 1],
    'V4' => ['Dave',    '',      'approved', 0],
    'V5' => ['Erin',    CONST_A, 'pending',  0],
    'V6' => ['Frank',   CONST_B, 'approved', 0],
    'V7' => ['Grace',   CONST_A, 'approved', 0],
    'V8' => ['Heidi',   CONST_A, 'approved', 0],
    'V9' => ['Ivan',    CONST_A, 'approved', 0],
];
$voterIds = [];
$insV = $pdo->prepare(
    "INSERT INTO voters (fullname, email, voter_id_number, password, status, has_voted, voting, constituency)
     VALUES (?,?,?,?,?,?,?,?)"
);
$n = 0;
foreach ($votersSpec as $key => [$name, $const, $status, $voted]) {
    $n++;
    $insV->execute([
        $name, strtolower($name) . "@e2e.test", 'E2E' . str_pad((string)$n, 7, '0', STR_PAD_LEFT),
        password_hash('x', PASSWORD_DEFAULT), $status, $voted, $voted ? 'yes' : 'no', $const,
    ]);
    $voterIds[$key] = (int)$pdo->lastInsertId();
}

function vote_count(PDO $pdo, int $candidateId): int
{
    $s = $pdo->prepare("SELECT votes_count FROM candidates WHERE id = ?");
    $s->execute([$candidateId]);
    return (int)$s->fetchColumn();
}

function has_voted(PDO $pdo, int $voterId): bool
{
    $s = $pdo->prepare("SELECT has_voted FROM voters WHERE id = ?");
    $s->execute([$voterId]);
    return (int)$s->fetchColumn() === 1;
}

/* ------------------------------------------------------------------ */
/* Start server                                                        */
/* ------------------------------------------------------------------ */

$env = getenv();
$env['VOTING_DB_PATH'] = $DB;

$descriptors = [0 => ['pipe', 'r'], 1 => ['file', $TMP . '/server.log', 'a'], 2 => ['file', $TMP . '/server.log', 'a']];
$proc = proc_open(
    [PHP_BINARY, '-d', 'session.save_path=' . $SESS, '-d', 'session.gc_probability=0',
     '-d', 'session.use_strict_mode=0', '-S', "127.0.0.1:$PORT", '-t', $ROOT],
    $descriptors, $pipes, $ROOT, $env
);

if (!is_resource($proc)) {
    fwrite(STDERR, "Could not start the PHP built-in server.\n");
    exit(2);
}

// wait for readiness
$ready = false;
for ($i = 0; $i < 50; $i++) {
    $fp = @fsockopen('127.0.0.1', $PORT, $errno, $errstr, 0.2);
    if ($fp) { fclose($fp); $ready = true; break; }
    usleep(100000);
}
if (!$ready) {
    fwrite(STDERR, "Server did not become ready.\n");
    proc_terminate($proc);
    exit(2);
}

register_shutdown_function(function () use ($proc, $TMP) {
    if (is_resource($proc)) {
        proc_terminate($proc);
        proc_close($proc);
    }
    // best-effort cleanup
    if (is_dir($TMP)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($TMP, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($TMP);
    }
});

/* ================================================================== */
/* 1. Two booths, different regions                                    */
/* ================================================================== */
section('1. Booths exist and cover different constituencies');

$rows = $pdo->query("SELECT id, code, name, constituency FROM booths ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
check('two booths are registered', count($rows) === 2, 'found ' . count($rows));
$consts = array_column($rows, 'constituency');
check('the two booths are in DIFFERENT constituencies', $consts[0] !== $consts[1], implode(' vs ', $consts));
check('booth A constituency is correct', $consts[0] === CONST_A, $consts[0]);
check('booth B constituency is correct', $consts[1] === CONST_B, $consts[1]);

/* ================================================================== */
/* 2. Kiosk unlock is per-booth                                        */
/* ================================================================== */
section('2. Kiosk unlocks only for its own booth (code + PIN)');

$unlockA = http("$BASE/kiosk/login.php", 'booth_code=BOOTH-A&booth_pin=111111&unlock_btn=1');
$cookieA = set_cookie($unlockA, 'PHPSESSID');
check('booth A unlock redirects (accepted)', $unlockA['status'] === 302 && $cookieA !== '', 'status ' . $unlockA['status']);
$idxA = http("$BASE/kiosk/index.php", null, "PHPSESSID=$cookieA");
check('unlocked kiosk A session renders booth A', strpos($idxA['body'], 'Varanasi') !== false);
check('kiosk A session is NOT booth B', strpos($idxA['body'], 'Mumbai South') === false);

$unlockB = http("$BASE/kiosk/login.php", 'booth_code=BOOTH-B&booth_pin=222222&unlock_btn=1');
$cookieB = set_cookie($unlockB, 'PHPSESSID');
check('booth B unlock redirects (accepted)', $unlockB['status'] === 302 && $cookieB !== '', 'status ' . $unlockB['status']);
$idxB = http("$BASE/kiosk/index.php", null, "PHPSESSID=$cookieB");
check('unlocked kiosk B session renders booth B', strpos($idxB['body'], 'Mumbai South') !== false);

$bad = http("$BASE/kiosk/login.php", 'booth_code=BOOTH-A&booth_pin=000000&unlock_btn=1');
check('wrong PIN is refused', $bad['status'] === 200 && stripos($bad['body'], 'Invalid booth code or PIN') !== false);

/* ================================================================== */
/* 3. Kiosk session has no admin powers                                */
/* ================================================================== */
section('3. Kiosk session cannot reach the admin console');

$admin = http("$BASE/admin/dashboard.php", null, "PHPSESSID=$cookieA");
$isBlocked = $admin['status'] !== 200 || stripos($admin['body'], 'Dashboard') === false;
check('kiosk session cannot open admin dashboard', $isBlocked, 'status ' . $admin['status']);

/* ================================================================== */
/* 4. Eligibility matrix (real kiosk_ballot_eligibility)               */
/* ================================================================== */
section('4. Regional eligibility rules');

// Exercised in a clean child process against the same isolated DB, so the
// real kiosk_ballot_eligibility() decides each case.
$matrixRaw = shell_exec(
    'VOTING_DB_PATH=' . escapeshellarg($DB) . ' ' . escapeshellarg(PHP_BINARY)
    . ' ' . escapeshellarg(__FILE__) . ' --matrix 2>/dev/null'
);
$matrix = json_decode((string)$matrixRaw, true);
check('eligibility matrix ran', is_array($matrix) && count($matrix) === 5, substr((string)$matrixRaw, 0, 100));

if (is_array($matrix)) {
    check('V1 (Varanasi) IS eligible at booth A', !empty($matrix['V1@A']['ok']), (string)($matrix['V1@A']['reason'] ?? ''));
    check('V1 (Varanasi) is REFUSED at booth B', empty($matrix['V1@B']['ok']) && stripos((string)$matrix['V1@B']['reason'], 'does not match') !== false, (string)($matrix['V1@B']['reason'] ?? ''));
    check('already-voted citizen is REFUSED', empty($matrix['V3@A']['ok']) && stripos((string)$matrix['V3@A']['reason'], 'already voted') !== false, (string)($matrix['V3@A']['reason'] ?? ''));
    check('citizen with no constituency is REFUSED', empty($matrix['V4@A']['ok']) && stripos((string)$matrix['V4@A']['reason'], 'No constituency') !== false, (string)($matrix['V4@A']['reason'] ?? ''));
    check('unapproved citizen is REFUSED', empty($matrix['V5@A']['ok']) && stripos((string)$matrix['V5@A']['reason'], 'not approved') !== false, (string)($matrix['V5@A']['reason'] ?? ''));
}

/* ================================================================== */
/* 5. Casting a real ballot over HTTP                                  */
/* ================================================================== */
section('5. Ballot casting and one-person-one-vote');

$before = vote_count($pdo, $candA1);
$s = kiosk_session($SESS, $boothIds['A'], 'BOOTH-A', 'Booth A', $voterIds['V1'], 'csrfV1');
$r = http("$BASE/kiosk/vote.php", '_csrf=csrfV1&candidate_id=' . $candA1, "PHPSESSID=$s");
check('V1 casts a ballot at booth A', $r['status'] === 302 && $r['body'] === '', 'status ' . $r['status']);
check('V1 is now marked as voted', has_voted($pdo, $voterIds['V1']));
check('V1 candidate count incremented by exactly 1', vote_count($pdo, $candA1) === $before + 1);

// double submit
$s2 = kiosk_session($SESS, $boothIds['A'], 'BOOTH-A', 'Booth A', $voterIds['V2'], 'csrfV2');
$first  = http("$BASE/kiosk/vote.php", '_csrf=csrfV2&candidate_id=' . $candA1, "PHPSESSID=$s2");
$countAfterFirst = vote_count($pdo, $candA1);
$s3 = kiosk_session($SESS, $boothIds['A'], 'BOOTH-A', 'Booth A', $voterIds['V2'], 'csrfV2b');
$second = http("$BASE/kiosk/vote.php", '_csrf=csrfV2b&candidate_id=' . $candA1, "PHPSESSID=$s3");
check('V2 first ballot succeeds', $first['status'] === 302, 'status ' . $first['status']);
check('V2 second ballot is REFUSED', $second['status'] === 200 && stripos($second['body'], 'already voted') !== false);
check('double submit did not add a second vote', vote_count($pdo, $candA1) === $countAfterFirst, "count {$countAfterFirst}");

// candidate from another constituency
$s4 = kiosk_session($SESS, $boothIds['A'], 'BOOTH-A', 'Booth A', $voterIds['V7'], 'csrfV7');
$countBeforeBad = vote_count($pdo, $candB1);
$r = http("$BASE/kiosk/vote.php", '_csrf=csrfV7&candidate_id=' . $candB1, "PHPSESSID=$s4");
check('candidate from another constituency is REJECTED', $r['status'] === 200 && stripos($r['body'], 'not on this citizen') !== false);
check('rejected ballot changed nothing', vote_count($pdo, $candB1) === $countBeforeBad && !has_voted($pdo, $voterIds['V7']));

// The booth demands BOTH biometrics: a fingerprint alone must not open a ballot.
$fpOnly = kiosk_session_fp_only($SESS, $boothIds['A'], 'BOOTH-A', 'Booth A', $voterIds['V7'], 'csrfV7fp');
$r = http("$BASE/kiosk/vote.php", '_csrf=csrfV7fp&candidate_id=' . $candA1, "PHPSESSID=$fpOnly");
check('fingerprint alone cannot vote (face check also required)', strpos(location_of($r), 'expired=1') !== false, 'status ' . $r['status'] . ' loc ' . location_of($r));
check('fingerprint-only attempt recorded no vote', !has_voted($pdo, $voterIds['V7']));

/* ================================================================== */
/* 6. The same citizen across two booths                               */
/* ================================================================== */
section('6. Cross-booth: same citizen, wrong booth');

$sA = kiosk_session($SESS, $boothIds['A'], 'BOOTH-A', 'Booth A', $voterIds['V6'], 'csrfV6a');
$r = http("$BASE/kiosk/vote.php", '_csrf=csrfV6a&candidate_id=' . $candA1, "PHPSESSID=$sA");
check('V6 (Mumbai South) is REFUSED at booth A (Varanasi)', $r['status'] === 200 && stripos($r['body'], 'does not match this booth') !== false);
check('V6 still has not voted after refusal', !has_voted($pdo, $voterIds['V6']));

$beforeB = vote_count($pdo, $candB1);
$sB = kiosk_session($SESS, $boothIds['B'], 'BOOTH-B', 'Booth B', $voterIds['V6'], 'csrfV6b');
$r = http("$BASE/kiosk/vote.php", '_csrf=csrfV6b&candidate_id=' . $candB1, "PHPSESSID=$sB");
check('V6 IS accepted at booth B (Mumbai South)', $r['status'] === 302, 'status ' . $r['status']);
check('V6 vote counted in booth B constituency', vote_count($pdo, $candB1) === $beforeB + 1);

/* ================================================================== */
/* 7. Ballot window expiry                                             */
/* ================================================================== */
section('7. Ballot authorization expires after the window');

$stale = kiosk_session($SESS, $boothIds['A'], 'BOOTH-A', 'Booth A', $voterIds['V8'], 'csrfV8', time() - 200);
$r = http("$BASE/kiosk/vote.php", '_csrf=csrfV8&candidate_id=' . $candA1, "PHPSESSID=$stale");
check('expired verification cannot vote', strpos(location_of($r), 'expired=1') !== false, 'status ' . $r['status'] . ' loc ' . location_of($r));
check('expired attempt did not record a vote', !has_voted($pdo, $voterIds['V8']));

/* ================================================================== */
/* 8. CSRF                                                             */
/* ================================================================== */
section('8. CSRF protection on the vote endpoint');

$sCsrf = kiosk_session($SESS, $boothIds['A'], 'BOOTH-A', 'Booth A', $voterIds['V9'], 'righttoken');
$r = http("$BASE/kiosk/vote.php", '_csrf=wrongtoken&candidate_id=' . $candA1, "PHPSESSID=$sCsrf");
check('wrong CSRF token is refused', $r['status'] === 200 && stripos($r['body'], 'session token') !== false);
check('CSRF failure recorded no vote', !has_voted($pdo, $voterIds['V9']));

/* ================================================================== */
/* 9. Face biometric decision is server-side                           */
/* ================================================================== */
section('9. Face biometric verification (server-side decision)');

$fid = 'face' . bin2hex(random_bytes(8));
write_session($SESS, $fid, ['vid' => $voterIds['V1']]);
$fcookie = "PHPSESSID=$fid";

$json = function (array $payload) use ($BASE, $fcookie) {
    return http("$BASE/voters/face_verify_api.php", json_encode($payload), $fcookie, ['Content-Type: application/json']);
};

$begin = $json(['action' => 'begin']);
$bb = json_decode($begin['body'], true);
check('face begin issues a nonce', is_array($bb) && !empty($bb['nonce']), 'status ' . $begin['status']);
check('server advertises the 0.55 threshold', isset($bb['threshold']) && (float)$bb['threshold'] === 0.55, (string)($bb['threshold'] ?? '?'));

$zeros = array_fill(0, 128, 0.0);
$ones  = array_fill(0, 128, 1.0);

$match = json_decode($json([
    'action' => 'match', 'nonce' => $bb['nonce'],
    'live_descriptor' => $zeros, 'ref_descriptor' => $zeros,
])['body'], true);
check('identical descriptors MATCH', !empty($match['matched']) && (float)$match['distance'] === 0.0, 'distance ' . ($match['distance'] ?? '?'));

$replay = json_decode($json([
    'action' => 'match', 'nonce' => $bb['nonce'],
    'live_descriptor' => $zeros, 'ref_descriptor' => $zeros,
])['body'], true);
check('nonce is single-use (replay refused)', ($replay['code'] ?? '') === 'NONCE', 'code ' . ($replay['code'] ?? '-'));

$bb2 = json_decode($json(['action' => 'begin'])['body'], true);
$far = json_decode($json([
    'action' => 'match', 'nonce' => $bb2['nonce'],
    'live_descriptor' => $ones, 'ref_descriptor' => $zeros,
])['body'], true);
check('distant descriptors DO NOT match', isset($far['matched']) && $far['matched'] === false, 'distance ' . ($far['distance'] ?? '?'));
check('distance is computed server-side (>0.55)', (float)$far['distance'] > 0.55, (string)$far['distance']);

$bb3 = json_decode($json(['action' => 'begin'])['body'], true);
$bad = json_decode($json([
    'action' => 'match', 'nonce' => $bb3['nonce'],
    'live_descriptor' => array_fill(0, 127, 0.0), 'ref_descriptor' => $zeros,
])['body'], true);
check('malformed descriptor is rejected', ($bad['code'] ?? '') === 'DESCRIPTOR', 'code ' . ($bad['code'] ?? '-'));

$logged = (int)$pdo->query("SELECT COUNT(*) FROM biometric_logs WHERE method = 'face'")->fetchColumn();
check('every face attempt is audited', $logged >= 2, "biometric_logs rows: $logged");

/* ================================================================== */
/* 10. Kiosk face check (server-side decision, booth-scoped)           */
/* ================================================================== */
section('10. Kiosk face check is server-side and booth-scoped');

$kfid = 'kface' . bin2hex(random_bytes(8));
write_session($SESS, $kfid, [
    'kiosk_booth_id'    => $boothIds['A'],
    'kiosk_booth_code'  => 'BOOTH-A',
    'kiosk_booth_name'  => 'Booth A',
    'kiosk_started'     => time(),
    'kiosk_last_seen'   => time(),
    'kiosk_csrf'        => 'kx',
    'kiosk_verify_vid'  => $voterIds['V9'],
    'kiosk_verify_name' => 'E2E Voter',
]);
$kcookie = "PHPSESSID=$kfid";
$kjson = function (array $payload) use ($BASE, $kcookie) {
    return http("$BASE/kiosk/face_api.php", json_encode($payload), $kcookie, ['Content-Type: application/json']);
};

$kbeginResp = $kjson(['action' => 'begin']);
$kbegin = json_decode($kbeginResp['body'], true);
check('kiosk face begin issues a nonce', is_array($kbegin) && !empty($kbegin['nonce']), 'status ' . $kbeginResp['status']);
check('kiosk face begin reports the armed citizen', (int)($kbegin['voter_id'] ?? 0) === $voterIds['V9'], 'voter ' . ($kbegin['voter_id'] ?? '?'));

$kmatch = json_decode($kjson([
    'action' => 'match', 'nonce' => $kbegin['nonce'],
    'live_descriptor' => $zeros, 'ref_descriptor' => $zeros,
])['body'], true);
check('kiosk face identical descriptors MATCH', !empty($kmatch['matched']) && (float)$kmatch['distance'] === 0.0, 'distance ' . ($kmatch['distance'] ?? '?'));
check('kiosk face did not yet authorize a ballot (fingerprint missing)', empty($kmatch['ballot_ready']));

$klogged = (int)$pdo->query(
    "SELECT COUNT(*) FROM biometric_logs WHERE method = 'face' AND booth_id = " . (int)$boothIds['A']
)->fetchColumn();
check('kiosk face attempt is audited against the booth', $klogged >= 1, "rows: $klogged");

/* ================================================================== */
/* 11. Kiosk face UI wiring                                            */
/* ================================================================== */
section('11. Kiosk UI guides the two-step verification');

$armId = 'arm' . bin2hex(random_bytes(8));
write_session($SESS, $armId, [
    'kiosk_booth_id'    => $boothIds['A'],
    'kiosk_booth_code'  => 'BOOTH-A',
    'kiosk_booth_name'  => 'Booth A',
    'kiosk_started'     => time(),
    'kiosk_last_seen'   => time(),
    'kiosk_csrf'        => 'armcsrf',
    'kiosk_verify_vid'  => $voterIds['V9'],
    'kiosk_verify_name' => 'E2E Voter',
]);
$armCookie = "PHPSESSID=$armId";

$armIdx = http("$BASE/kiosk/index.php", null, $armCookie);
check('armed verify offers the face step first', stripos($armIdx['body'], 'Start Face Check') !== false);

$facePage = http("$BASE/kiosk/face_verify.php", null, $armCookie);
check('face page renders for the armed citizen', $facePage['status'] === 200 && stripos($facePage['body'], 'blink twice') !== false, 'status ' . $facePage['status']);
$noArmedFace = http("$BASE/kiosk/face_verify.php", null, "PHPSESSID=$cookieA");
check('face page without an armed citizen redirects away', $noArmedFace['status'] === 302, 'status ' . $noArmedFace['status']);

// Fingerprint done, face outstanding → the kiosk asks for the face check.
$fpPending = kiosk_session_fp_only($SESS, $boothIds['A'], 'BOOTH-A', 'Booth A', $voterIds['V7'], 'csrfV7face');
$pendIdx = http("$BASE/kiosk/index.php", null, "PHPSESSID=$fpPending");
check('fingerprint-only kiosk asks for the face check', stripos($pendIdx['body'], 'Start Face Check') !== false);

/* ================================================================== */
/* 12. Booth reference-photo capture (in-person face enrollment)       */
/* ================================================================== */
section('12. Booth reference-photo capture (in-person face enrollment)');

// The app stores captures under images/ (a real, shared directory), so track
// what we create and remove it on the way out — even if a check fails.
$captureCleanup = [];
register_shutdown_function(function () use (&$captureCleanup) {
    foreach ($captureCleanup as $f) {
        if (is_string($f) && $f !== '' && is_file($f)) {
            @unlink($f);
        }
    }
});

$refVid  = $voterIds['V9'];            // fixture voter: photo is the placeholder
$capId   = 'cap' . bin2hex(random_bytes(8));
write_session($SESS, $capId, [
    'kiosk_booth_id'    => $boothIds['A'],
    'kiosk_booth_code'  => 'BOOTH-A',
    'kiosk_booth_name'  => 'Booth A',
    'kiosk_started'     => time(),
    'kiosk_last_seen'   => time(),
    'kiosk_csrf'        => 'capcsrf',
    'kiosk_verify_vid'  => $refVid,
    'kiosk_verify_name' => 'E2E Voter',
]);
$capCookie = "PHPSESSID=$capId";

// 1x1 PNG — a structurally valid image payload for the capture.
$tinyPng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

$capJson = function (array $payload) use ($BASE, $capCookie) {
    return http("$BASE/kiosk/face_api.php", json_encode($payload), $capCookie, ['Content-Type: application/json']);
};

// Before capture: the placeholder photo is not a usable reference.
$before = json_decode($capJson(['action' => 'begin'])['body'], true);
check('begin reports NO usable reference for a placeholder photo',
    is_array($before) && ($before['has_reference'] ?? null) === false && ($before['photo_url'] ?? '') === '',
    'has_reference=' . var_export($before['has_reference'] ?? null, true));

// Capture a reference photo in person.
$cap = json_decode($capJson(['action' => 'enroll_photo', 'snapshot' => $tinyPng])['body'], true);
check('reference photo capture succeeds', !empty($cap['success']) && !empty($cap['photo_url']), 'message ' . ($cap['message'] ?? '-'));
check('capture is attributed to the armed citizen', (int)($cap['voter_id'] ?? 0) === $refVid, 'voter ' . ($cap['voter_id'] ?? '?'));

$name = basename((string)($cap['photo_url'] ?? ''));
$filePath = $ROOT . '/images/' . $name;
$captureCleanup[] = $filePath;
check('captured reference file exists on disk', $name !== '' && is_file($filePath), $filePath);

$storedStmt = $pdo->prepare("SELECT face_photo FROM voters WHERE id = ? LIMIT 1");
$storedStmt->execute([$refVid]);
$storedFace = trim((string)$storedStmt->fetchColumn());
check('voters.face_photo now points at the capture', $storedFace === $name, 'face_photo=' . ($storedFace === '' ? '(empty)' : $storedFace));

$enrollRows = (int)$pdo->query(
    "SELECT COUNT(*) FROM biometric_logs WHERE method = 'face_enroll' AND booth_id = " . (int)$boothIds['A']
)->fetchColumn();
check('reference capture is audited against the booth', $enrollRows >= 1, "face_enroll rows: $enrollRows");

// After capture: the reference is usable and resolved from the stored file.
$after = json_decode($capJson(['action' => 'begin'])['body'], true);
check('begin now reports a usable reference photo', !empty($after['has_reference']) && !empty($after['photo_url']), 'photo_url ' . ($after['photo_url'] ?? '-'));
check('begin reference URL resolves to the stored capture', basename((string)($after['photo_url'] ?? '')) === $name);

$capPage = http("$BASE/kiosk/face_verify.php", null, $capCookie);
check('face page now shows the reference photo on file',
    $capPage['status'] === 200 && stripos($capPage['body'], 'Reference photo on file') !== false, 'status ' . $capPage['status']);

// Capture requires an armed citizen and a real image payload.
$noTarget = json_decode(
    http("$BASE/kiosk/face_api.php", json_encode(['action' => 'enroll_photo', 'snapshot' => $tinyPng]), "PHPSESSID=$cookieA", ['Content-Type: application/json'])['body'],
    true
);
check('capture without an armed citizen is refused', ($noTarget['code'] ?? '') === 'NO_TARGET', 'code ' . ($noTarget['code'] ?? '-'));

$badSnap = json_decode($capJson(['action' => 'enroll_photo', 'snapshot' => 'data:image/jpeg;base64,@@not-base64@@'])['body'], true);
check('malformed snapshot is refused', ($badSnap['code'] ?? '') === 'PHOTO', 'code ' . ($badSnap['code'] ?? '-'));

$emptySnap = json_decode($capJson(['action' => 'enroll_photo', 'snapshot' => ''])['body'], true);
check('empty snapshot is refused', ($emptySnap['code'] ?? '') === 'PHOTO', 'code ' . ($emptySnap['code'] ?? '-'));

$stillStored = $pdo->prepare("SELECT face_photo FROM voters WHERE id = ? LIMIT 1");
$stillStored->execute([$refVid]);
check('a refused capture does not overwrite the stored photo', trim((string)$stillStored->fetchColumn()) === $name);

/* ================================================================== */
/* Summary                                                             */
/* ================================================================== */
$total = count($GLOBALS['results']);
$pass  = $total - $GLOBALS['failed'];
echo "\n=====================================================\n";
printf(" RESULT: %d/%d checks passed\n", $pass, $total);
if ($GLOBALS['failed'] > 0) {
    echo " Failed checks:\n";
    foreach ($GLOBALS['results'] as [$ok, $name]) {
        if (!$ok) {
            echo "   - $name\n";
        }
    }
}
echo "=====================================================\n";

exit($GLOBALS['failed'] === 0 ? 0 : 1);
