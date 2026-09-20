<?php
/**
 * scripts/start-dev.php
 * ---------------------------------------------------------------
 * Start the dev stack in one command:
 *
 *   php scripts/start-dev.php
 *
 *   1. PHP built-in server on 127.0.0.1:8000 (web root = project root)
 *   2. ngrok tunnel  ->  the kiosk host pinned in includes/config.php
 *
 * WHY bind 127.0.0.1 (not localhost): `php -S localhost:8000` listens on
 * IPv6 only on macOS, so anything dialing 127.0.0.1 (ngrok among it) gets
 * connection refused. Binding the IPv4 address explicitly avoids that.
 *
 * WHY the pinned domain: passkeys are permanently bound to the RP ID in
 * includes/config.php (WEBAUTHN_RP_ID). Starting the tunnel with a random
 * URL would orphan every enrolled passkey, so this script always starts
 * ngrok with that exact domain. Change it with scripts/set_kiosk_host.php.
 *
 * Both processes run in the background (logs: /tmp/voting-dev-*.log) and
 * survive this script exiting. Re-running is safe: it kills stale
 * instances of both first, so you always end up with exactly one of each.
 *
 *   php scripts/start-dev.php --stop    stop both processes
 *   php scripts/start-dev.php --help    usage
 * ---------------------------------------------------------------
 */

const PORT      = 8000;
const BIND_HOST = '127.0.0.1';
const LOG_DIR   = '/tmp';
const LOG_PHP   = LOG_DIR . '/voting-dev-php.log';
const LOG_NGROK = LOG_DIR . '/voting-dev-ngrok.log';

const ROOT = __DIR__ . '/..';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$arg = $argv[1] ?? '';

if ($arg === '-h' || $arg === '--help') {
    usage();
    exit(0);
}

if ($arg === '--stop' || $arg === '-s') {
    stopMatching('php -S ' . BIND_HOST . ':' . PORT);
    stopMatching('ngrok http');
    out('✓ Stopped dev server and ngrok tunnel (if they were running).');
    exit(0);
}

if ($arg !== '') {
    fwrite(STDERR, "✗ Unknown option: {$arg}\n\n");
    usage(STDERR);
    exit(1);
}

/* ------------------------------------------------------------------ */
/* Start                                                              */
/* ------------------------------------------------------------------ */

$host = kioskHost();

out('Stopping any stale dev processes…');
stopMatching('php -S ' . BIND_HOST . ':' . PORT);
stopMatching('ngrok http');

out('Starting:');
startPhpServer();
$tunnel = startNgrok($host);

out('Waiting for the server to respond…');
if (!waitFor(fn() => verify('http://' . BIND_HOST . ':' . PORT . '/', 'Online Voting System'), 40)) {
    fwrite(STDERR, "✗ The PHP server did not come up — see " . LOG_PHP . "\n");
    exit(1);
}

if ($tunnel) {
    // ngrok takes a moment to publish; worth confirming, but not fatal.
    if (waitFor(fn() => verify(rtrim($host, '/') . '/', 'Online Voting System'), 25)) {
        out('✓ Tunnel is live: ' . rtrim($host, '/') . '/kiosk/login.php');
    } else {
        fwrite(STDERR, "⚠ The tunnel did not answer yet — see " . LOG_NGROK . "\n");
    }
}

out();
out('✓ Dev stack is up.');
out('  Portal : http://' . BIND_HOST . ':' . PORT);
out('  Kiosk  : ' . rtrim($host, '/') . '/kiosk/login.php   (passkeys only work on this host)');
out('  Stop   : php scripts/start-dev.php --stop');

// -----------------------------------------------------------------------

function usage($stream = STDOUT): void
{
    fwrite($stream, <<<TXT
Usage: php scripts/start-dev.php [--stop]

Starts in the background:
  1. PHP built-in server  http://127.0.0.1:8000     (log: /tmp/voting-dev-php.log)
  2. ngrok tunnel         ->  <WEBAUTHN_RP_ID from includes/config.php>
                                             (log: /tmp/voting-dev-ngrok.log)

Re-running is safe: stale instances are killed first.
Use the pinned https://<RP ID>/kiosk/login.php URL for the booth kiosk —
passkeys only work on that hostname. Use http://localhost:8000 locally.

TXT);
}

function out(string $s = ''): void
{
    fwrite(STDOUT, $s . PHP_EOL);
}

/**
 * Read the pinned kiosk host from includes/config.php.
 */
function kioskHost(): string
{
    $path = ROOT . '/includes/config.php';
    if (!is_file($path)) {
        fwrite(STDERR, "✗ {$path} not found. Copy includes/config.example.php and pin a host:\n");
        fwrite(STDERR, "  php scripts/set_kiosk_host.php https://your-name.ngrok-free.app\n");
        exit(1);
    }
    $config = require $path;
    $host = is_array($config) ? ($config['WEBAUTHN_RP_ID'] ?? '') : '';
    if (!is_string($host) || $host === '') {
        fwrite(STDERR, "✗ WEBAUTHN_RP_ID missing from includes/config.php. Pin it:\n");
        fwrite(STDERR, "  php scripts/set_kiosk_host.php https://your-name.ngrok-free.app\n");
        exit(1);
    }
    return $host;
}

/** Launch the PHP built-in server in the background; returns its pid. */
function startPhpServer(): int
{
    $cmd = sprintf(
        'nohup %s -S %s:%d -t %s > %s 2>&1 & echo $!',
        escapeshellarg(PHP_BINARY),
        BIND_HOST,
        PORT,
        escapeshellarg(realpath(ROOT) ?: ROOT),
        escapeshellarg(LOG_PHP)
    );
    $pid = (int) trim((string) shell_exec($cmd));
    out(sprintf('  PHP server   http://%s:%d   (pid %d, log %s)', BIND_HOST, PORT, $pid, LOG_PHP));
    return $pid;
}

/** Launch ngrok for the pinned domain in the background; false if ngrok is absent. */
function startNgrok(string $host): bool
{
    $found = trim((string) shell_exec('command -v ngrok 2>/dev/null'));
    if ($found === '') {
        fwrite(STDERR, "  ⚠ ngrok is not on PATH — started the PHP server only.\n");
        fwrite(STDERR, "    Install ngrok to expose the kiosk at " . rtrim($host, '/') . "\n");
        return false;
    }

    $domain = preg_replace('#^https?://#', '', trim($host));
    $cmd = sprintf(
        'nohup ngrok http --domain=%s %d > %s 2>&1 & echo $!',
        escapeshellarg($domain),
        PORT,
        escapeshellarg(LOG_NGROK)
    );
    $pid = (int) trim((string) shell_exec($cmd));
    out(sprintf('  ngrok        https://%s   (pid %d, log %s)', $domain, $pid, LOG_NGROK));
    return true;
}

/** Poll $probe until it returns true, or the attempt budget runs out. */
function waitFor(callable $probe, int $attempts): bool
{
    for ($i = 0; $i < $attempts; $i++) {
        if ($probe()) {
            return true;
        }
        usleep(200_000);
    }
    return false;
}

function stopMatching(string $needle): void
{
    $out = shell_exec('ps -ww -o pid=,command= -p $(pgrep -f ' . escapeshellarg($needle) . ') 2>/dev/null');
    if (!is_string($out) || trim($out) === '') {
        return;
    }
    $selfPid = (string) getmypid();
    foreach (explode("\n", trim($out)) as $line) {
        $line = trim($line);
        if ($line === '' || !preg_match('/^(\d+)\s+(.*)$/', $line, $m)) {
            continue;
        }
        [$pid, $cmd] = [(int) $m[1], $m[2]];
        if ($cmd === 'php' || (string) $pid === $selfPid) {
            continue; // don't kill ourselves or a bare "php"
        }
        posix_kill($pid, SIGTERM);
        usleep(300_000);
        if (posix_kill($pid, 0)) {
            posix_kill($pid, SIGKILL);
        }
        fwrite(STDOUT, "  killed stale pid {$pid}: {$cmd}\n");
    }
}

function verify(string $url, string $needle): bool
{
    $ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => "ngrok-skip-browser-warning: true\r\n"]]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) && strpos($body, $needle) !== false;
}
