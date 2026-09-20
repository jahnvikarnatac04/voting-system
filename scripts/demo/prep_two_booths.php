<?php
/**
 * scripts/demo/prep_two_booths.php
 * ===================================================================
 * Prepare the seeded database for the TWO-BOOTH presentation demo.
 *
 * It makes the demo real by setting up exactly the configuration the
 * booth/region rules need:
 *
 *   BOOTH-001  →  Varanasi (PC-77)     [Uttar Pradesh]
 *   BOOTH-002  →  Hyderabad (PC-09)    [Telangana]
 *
 *   Citizen V  (Arjun Singh)   → Varanasi   votes at BOOTH-001
 *   Citizen X  (Suresh Joshi)  → Varanasi   is REFUSED at BOOTH-002
 *   Citizen H  (Rohit Verma)   → Hyderabad  votes at BOOTH-002
 *   Citizen N  (monika)        → (no constituency)  always refused
 *
 * Idempotent — safe to run repeatedly. It NEVER deletes candidates or
 * changes another citizen's record.
 *
 * Usage:
 *   php scripts/demo/prep_two_booths.php            # prepare (idempotent)
 *   php scripts/demo/prep_two_booths.php --status   # report current state only
 *   php scripts/demo/prep_two_booths.php --unvote   # also clear the demo citizens' voted flag
 *   php scripts/demo/prep_two_booths.php --help
 *
 * NOTE: --unvote clears has_voted for the four demo citizens only. Ballot
 * counts on candidates are NOT rewound (ballots are anonymous, so there is
 * nothing to decrement). For a truly clean slate, restore the backup taken
 * before the demo:
 *     cp voting_system.db.demo-backup voting_system.db
 * ===================================================================
 */

$ROOT = dirname(__DIR__, 2);
require $ROOT . '/db.php'; // honours VOTING_DB_PATH

const DEMO_CONST_A = 'Varanasi (PC-77)';
const DEMO_CONST_B = 'Hyderabad (PC-09)';

$mode = $argv[1] ?? 'prepare';

if (in_array($mode, ['-h', '--help', 'help'], true)) {
    fwrite(STDOUT, <<<TXT
Usage: php scripts/demo/prep_two_booths.php [--status|--unvote]

  (no flag)   Prepare the two booths and demo citizens (idempotent)
  --status    Show what is currently configured; change nothing
  --unvote    Prepare AND clear has_voted on the four demo citizens
TXT . "\n");
    exit(0);
}

/** The demo cast: email → constituency ('' = deliberately has none). */
$citizens = [
    'you@example.com'            => [ 'Citizen V', DEMO_CONST_A ], // Arjun Singh
    'suresh.joshi7@gmail.com'    => [ 'Citizen X', DEMO_CONST_A ], // mismatch case
    'rohit.verma4@gmail.com'     => [ 'Citizen H', DEMO_CONST_B ], // Hyderabad
    'monikakarnatac@gmail.com'   => [ 'Citizen N', ''            ], // no constituency
];

$booths = [
    [
        'code' => 'BOOTH-001', 'name' => 'Varanasi Polling Station — Booth 001',
        'state' => 'Uttar Pradesh', 'const' => DEMO_CONST_A, 'pin' => '123456',
    ],
    [
        'code' => 'BOOTH-002', 'name' => 'Hyderabad Polling Station — Booth 002',
        'state' => 'Telangana', 'const' => DEMO_CONST_B, 'pin' => '654321',
    ],
];

function line(string $s = ''): void { fwrite(STDOUT, $s . PHP_EOL); }

line('==========================================================');
line(' Two-booth demo preparation');
line('==========================================================');

/* ---------------------------------------------------------------- */
/* Status mode — read only                                          */
/* ---------------------------------------------------------------- */
if ($mode === '--status') {
    line('BOOTHS');
    $rows = $pdo->query("SELECT code, name, state, constituency, active FROM booths ORDER BY code")
                ->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $b) {
        printf("  %-10s %-38s %-16s %s\n",
            $b['code'], $b['name'], $b['state'], $b['constituency'] === '' ? '(none)' : $b['constituency']);
    }

    line();
    line('DEMO CITIZENS');
    foreach ($citizens as $email => [$label, $const]) {
        $s = $pdo->prepare("SELECT id, fullname, status, has_voted, COALESCE(constituency,'') c FROM voters WHERE LOWER(email)=? LIMIT 1");
        $s->execute([strtolower($email)]);
        $v = $s->fetch(PDO::FETCH_ASSOC);
        if (!$v) {
            printf("  %-10s %-30s MISSING\n", $label, $email);
        } else {
            printf("  %-10s id=%-3s %-22s status=%-8s voted=%s  region=%s\n",
                $label, $v['id'], $v['fullname'], $v['status'], $v['has_voted'],
                $v['c'] === '' ? '(none)' : $v['c']);
        }
    }

    line();
    line('CANDIDATES');
    $c = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE constituency = ?");
    foreach ([DEMO_CONST_A, DEMO_CONST_B] as $const) {
        $c->execute([$const]);
        printf("  %-22s %d candidates\n", $const, (int)$c->fetchColumn());
    }
    exit(0);
}

/* ---------------------------------------------------------------- */
/* Prepare                                                          */
/* ---------------------------------------------------------------- */
$pdo->beginTransaction();
$changes = [];

try {
    // --- Booths ---
    foreach ($booths as $b) {
        $find = $pdo->prepare("SELECT id FROM booths WHERE code = ? LIMIT 1");
        $find->execute([$b['code']]);
        $id = $find->fetchColumn();

        if ($id) {
            $upd = $pdo->prepare("UPDATE booths SET name=?, state=?, constituency=?, pin_hash=?, active=1 WHERE id=?");
            $upd->execute([$b['name'], $b['state'], $b['const'], password_hash($b['pin'], PASSWORD_DEFAULT), $id]);
            $changes[] = "booth {$b['code']} updated → {$b['const']} (PIN {$b['pin']})";
        } else {
            $ins = $pdo->prepare("INSERT INTO booths (code, name, state, constituency, pin_hash, active) VALUES (?,?,?,?,?,1)");
            $ins->execute([$b['code'], $b['name'], $b['state'], $b['const'], password_hash($b['pin'], PASSWORD_DEFAULT)]);
            $changes[] = "booth {$b['code']} created → {$b['const']} (PIN {$b['pin']})";
        }
    }

    // --- Demo citizens ---
    foreach ($citizens as $email => [$label, $const]) {
        $find = $pdo->prepare("SELECT id, status, has_voted FROM voters WHERE LOWER(email)=? LIMIT 1");
        $find->execute([strtolower($email)]);
        $v = $find->fetch(PDO::FETCH_ASSOC);
        if (!$v) {
            $changes[] = "!! $label ($email) NOT FOUND — skipped";
            continue;
        }
        $upd = $pdo->prepare("UPDATE voters SET constituency=?, status='approved' WHERE id=?");
        $upd->execute([$const, $v['id']]);
        $changes[] = sprintf("%s (%s) → %s  [approved]", $label, $email, $const === '' ? '(no constituency)' : $const);

        if ($mode === '--unvote') {
            $pdo->prepare("UPDATE voters SET has_voted=0, voting='no' WHERE id=?")->execute([$v['id']]);
            $changes[] = "   ↳ $label voted flag cleared";
        } elseif ((int)$v['has_voted'] === 1) {
            $changes[] = "   ↳ NOTE: $label has ALREADY voted — re-run with --unvote for a clean rehearsal";
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Preparation failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

foreach ($changes as $c) {
    line('  • ' . $c);
}

/* ---------------------------------------------------------------- */
/* Verification                                                     */
/* ---------------------------------------------------------------- */
line();
line('VERIFY');
$ok = true;

$b = $pdo->query("SELECT code, constituency FROM booths WHERE active=1 ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);
$byConst = array_column($b, 'constituency', 'code');
$okBooths = isset($byConst['BOOTH-001'], $byConst['BOOTH-002'])
         && $byConst['BOOTH-001'] === DEMO_CONST_A
         && $byConst['BOOTH-002'] === DEMO_CONST_B;
line(($okBooths ? '  [ok] ' : '  [!!] ') . 'two active booths with different constituencies');
$ok = $ok && $okBooths;

foreach ([DEMO_CONST_A, DEMO_CONST_B] as $const) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE constituency=?");
    $c->execute([$const]);
    $n = (int)$c->fetchColumn();
    line(sprintf('  %s %d candidates exist for %s', $n > 0 ? '[ok]' : '[!!]', $n, $const));
    $ok = $ok && $n > 0;
}

line();
line($ok ? 'READY — run: php -S localhost:8000' : 'INCOMPLETE — see the [!!] lines above');
exit($ok ? 0 : 1);
