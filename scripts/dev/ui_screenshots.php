<?php
/**
 * UI screenshot harness (development tool - not part of the application).
 *
 * Boots the app on a scratch port with a private session directory, crafts
 * authenticated sessions for the gated pages, then captures headless-Chrome
 * screenshots of every page at desktop and mobile widths.
 *
 *   php scripts/dev/ui_screenshots.php <label>
 *
 * Output goes to /tmp/ui-shots/<label>/<page>-<width>.png. Run it before and
 * after a UI change and compare the two directories.
 *
 * Requires Google Chrome. Uses no application code beyond db.php (read-only).
 */

$ROOT  = dirname(__DIR__, 2);
$label = $argv[1] ?? 'snapshot';
$PORT  = 8123;
$OUT   = sys_get_temp_dir() . '/ui-shots/' . $label;
$SESS  = sys_get_temp_dir() . '/ui-shots-sessions';

$CHROME_CANDIDATES = [
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
];

$chrome = null;
foreach ($CHROME_CANDIDATES as $c) {
    if (is_executable($c)) {
        $chrome = $c;
        break;
    }
}
if ($chrome === null) {
    fwrite(STDERR, "No Chrome/Chromium binary found; cannot take screenshots.\n");
    exit(2);
}

@mkdir($OUT, 0777, true);
@mkdir($SESS, 0777, true);

/* ---------------------------------------------------------------- helpers */

function put(string $path, string $content): void
{
    file_put_contents($path, $content);
    echo '  wrote ' . $path . "\n";
}

/** Session files are plain "key|serialize(value)" pairs. */
function write_session(string $dir, string $id, array $data, int $offsetSeconds = 0): string
{
    $out = '';
    foreach ($data as $k => $v) {
        $out .= $k . '|' . serialize($v);
    }
    file_put_contents($dir . '/sess_' . $id, $out);
    if ($offsetSeconds) {
        @touch($dir . '/sess_' . $id, time() + $offsetSeconds);
    }
    return $id;
}

function db_ids(string $root): array
{
    $pdo  = null;
    $prev = getcwd();
    chdir($root);
    require $root . '/db.php';          // defines $pdo
    chdir($prev);

    $one = function (string $sql) use ($pdo) {
        try {
            $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            return $row ?: [];
        } catch (Throwable $e) {
            return [];
        }
    };

    // Prefer an approved citizen who has NOT voted and has a constituency: the
    // face screen bounces already-voted citizens, and the ballot page needs an
    // eligible voter at the booth.
    $voter = $one("SELECT id FROM voters WHERE status = 'approved' AND has_voted = 0 AND constituency <> '' ORDER BY id LIMIT 1")
          ?: $one("SELECT id FROM voters WHERE status = 'approved' ORDER BY id LIMIT 1");

    return [
        'voter' => (int)($voter['id'] ?? 0),
        'admin' => (int)($one("SELECT id FROM admins ORDER BY id LIMIT 1")['id'] ?? 0),
        'booth' => $one("SELECT id, code, name FROM booths ORDER BY id LIMIT 1"),
    ];
}

/* ------------------------------------------------------------- boot server */

$serverCmd = sprintf(
    'php -d session.save_path=%s -d session.use_only_cookies=0 -d session.use_trans_sid=1 -S 127.0.0.1:%d -t %s',
    escapeshellarg($SESS),
    $PORT,
    escapeshellarg($ROOT)
);
$server = proc_open($serverCmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $ROOT);

if (!is_resource($server)) {
    fwrite(STDERR, "Could not start the PHP development server.\n");
    exit(2);
}

register_shutdown_function(function () use ($server) {
    proc_terminate($server, 9);
    proc_close($server);
});

// Wait for it to answer.
$up = false;
for ($i = 0; $i < 50; $i++) {
    usleep(200000);
    $sock = @fsockopen('127.0.0.1', $PORT, $errno, $errstr, 0.5);
    if ($sock) {
        fclose($sock);
        $up = true;
        break;
    }
}
if (!$up) {
    fwrite(STDERR, "The server never came up on port $PORT.\n");
    exit(2);
}

/* ---------------------------------------------------------- craft sessions */

$ids = db_ids($ROOT);
echo "fixtures: voter={$ids['voter']} admin={$ids['admin']} booth=" . json_encode($ids['booth']) . "\n";

$booth = $ids['booth'];
// A voter who is past the pre-vote wizard (so the dashboard renders), and one
// who has not completed it yet (so the wizard itself renders).
$vSess = write_session($SESS, 'shotvoter' . bin2hex(random_bytes(4)), [
    'vid'                         => $ids['voter'],
    'pre_vote_workflow_completed' => true,
]);
$vFresh = write_session($SESS, 'shotfresh' . bin2hex(random_bytes(4)), [
    'vid' => $ids['voter'],
]);
$aSess = write_session($SESS, 'shotadmin' . bin2hex(random_bytes(4)), [
    'admin_id'         => $ids['admin'],
    'admin_logged_in'  => true,
    'admin_name'       => 'Screenshot Admin',
]);
$kioskBase = [
    'kiosk_booth_id'   => (int)($booth['id'] ?? 1),
    'kiosk_booth_code' => $booth['code'] ?? 'BOOTH-001',
    'kiosk_booth_name' => $booth['name'] ?? 'Booth',
    'kiosk_started'    => time(),
    'kiosk_last_seen'  => time(),
    'kiosk_csrf'       => 'shotcsrftoken',
];
$kSess = write_session($SESS, 'shotkiosk' . bin2hex(random_bytes(4)), $kioskBase);

// An unlocked kiosk where the citizen has just passed BOTH biometric checks,
// which is the only state in which the ballot renders.
$bSess = write_session($SESS, 'shotballot' . bin2hex(random_bytes(4)), $kioskBase + [
    'booth_verified_vid'  => $ids['voter'],
    'booth_verified_name' => 'Screenshot Voter',
    'booth_verified_at'   => time(),
    'booth_face_vid'      => $ids['voter'],
    'booth_face_name'     => 'Screenshot Voter',
    'booth_face_at'       => time(),
]);
$cSess = write_session($SESS, 'shotconst' . bin2hex(random_bytes(4)), [
    'selected_state'        => 'Uttar Pradesh',
    'selected_constituency' => 'Varanasi (PC-77)',
]);

/* ------------------------------------------------------------ page matrix */

$pages = [
    'entry'            => ['index.php',                    null],
    'entry-selected'   => ['index.php',                    $cSess],
    'voter-login'      => ['login.php',                    null],
    'admin-login'      => ['admin/login.php',              null],
    'kiosk-login'      => ['kiosk/login.php',              null],
    'pre-vote'         => ['voters/pre_vote_workflow.php', $vFresh],
    'voter-dashboard'  => ['voters/dashboard.php',         $vSess],
    'voter-edit'       => ['voters/edit_profile.php',      $vSess],
    'voter-face'       => ['voters/face_verify.php',       $vSess],
    'voter-passkey'    => ['voters/add_passkey.php',       $vSess],
    'voter-biometrics' => ['voters/verify_biometrics.php', $vSess],
    'admin-dashboard'  => ['admin/dashboard.php',          $aSess],
    'admin-boards'     => ['admin/booths.php',             $aSess],
    'admin-onboard'    => ['admin/onboard_voter.php',      $aSess],
    'admin-verify'     => ['admin/verify_voters.php',      $aSess],
    'kiosk-home'       => ['kiosk/index.php',              $kSess],
    'kiosk-ballot'     => ['kiosk/ballot.php',             $bSess],
    'kiosk-face'       => ['kiosk/face_verify.php',        $bSess],
];

$widths = [
    'desktop' => '1280,900',
    'mobile'  => '390,844',
];

$taken = 0;
$bad   = 0;

foreach ($pages as $name => [$path, $sess]) {
    foreach ($widths as $wName => $size) {
        $url = "http://127.0.0.1:$PORT/$path";
        if ($sess !== null) {
            $url .= '?PHPSESSID=' . $sess;   // accepted because use_only_cookies=0
        }
        $png = "$OUT/$name-$wName.png";

        $cmd = escapeshellarg($chrome)
            . ' --headless --disable-gpu --hide-scrollbars --no-sandbox'
            . ' --virtual-time-budget=2500'
            . ' --window-size=' . escapeshellarg($size)
            . ' --screenshot=' . escapeshellarg($png)
            . ' ' . escapeshellarg($url);

        exec($cmd . ' 2>/dev/null', $o, $rc);

        // A page that redirected to a login screen is a harness problem, not a
        // page problem - flag it so the snapshot is not trusted.
        $size0 = is_file($png) ? filesize($png) : 0;
        $status = ($rc === 0 && $size0 > 2000) ? 'ok' : 'CHECK';
        if ($status !== 'ok') {
            $bad++;
        }
        $taken++;
        printf("  %-4s %-18s %-8s %s\n", $status, $name, $wName, $size0 ? $size0 . 'B' : 'no file');
    }
}

echo "\n$taken screenshots in $OUT" . ($bad ? " ($bad need checking)" : '') . "\n";
