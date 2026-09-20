<?php
/**
 * scripts/assign_constituency.php
 * ---------------------------------------------------------------
 * Assign a Parliamentary Constituency to voters. A booth only issues
 * a ballot to a citizen whose constituency matches the booth's, so
 * every voter who should vote at a booth needs one.
 *
 * Usage:
 *   php scripts/assign_constituency.php list
 *   php scripts/assign_constituency.php assign "Varanasi (PC-77)" --all
 *   php scripts/assign_constituency.php assign "Varanasi (PC-77)" --blank
 *   php scripts/assign_constituency.php assign "Varanasi (PC-77)" --email=you@example.com
 *   php scripts/assign_constituency.php assign "Varanasi (PC-77)" --epic=ABC1234567
 *   php scripts/assign_constituency.php assign "Varanasi (PC-77)" --id=7
 *
 *   --all    every voter
 *   --blank  only voters with no constituency yet (safe for demo seeding)
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/../db.php';

function out(string $s = ''): void { fwrite(STDOUT, $s . PHP_EOL); }

$mode = $argv[1] ?? '';

if ($mode === 'list' || $mode === '') {
    out('Constituencies with candidates (choose one of these):');
    $rows = $pdo->query(
        "SELECT constituency, COUNT(*) AS n FROM candidates
         WHERE constituency <> '' GROUP BY constituency ORDER BY constituency"
    )->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        out(sprintf('  %-32s %d candidates', $r['constituency'], (int)$r['n']));
    }
    $noConst = (int)$pdo->query("SELECT COUNT(*) FROM voters WHERE COALESCE(constituency,'') = ''")->fetchColumn();
    $total   = (int)$pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();
    out();
    out("Voters without a constituency: {$noConst} of {$total}");
    out();
    out('Example: php scripts/assign_constituency.php assign "Varanasi (PC-77)" --blank');
    exit(0);
}

if ($mode !== 'assign') {
    fwrite(STDERR, "Unknown command: {$mode}\nUse 'list' or 'assign'. See the header of this file.\n");
    exit(1);
}

$constituency = trim($argv[2] ?? '');
if ($constituency === '') {
    fwrite(STDERR, "✗ Missing constituency. Run 'list' to see valid values.\n");
    exit(1);
}

$flags = array_slice($argv, 3);
$all      = in_array('--all', $flags, true);
$blank    = in_array('--blank', $flags, true);
$email    = null;
$epic     = null;
$id       = null;
$other    = [];

foreach ($flags as $f) {
    if (strncmp($f, '--email=', 8) === 0)      $email = strtolower(trim(substr($f, 8)));
    elseif (strncmp($f, '--epic=', 7) === 0)   $epic  = strtoupper(trim(substr($f, 7)));
    elseif (strncmp($f, '--id=', 5) === 0)     $id    = (int)substr($f, 5);
    elseif ($f !== '--all' && $f !== '--blank') $other[] = $f;
}

// Warn (but don't block) if no candidates exist for this constituency.
$cand = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE constituency = ?");
$cand->execute([$constituency]);
$candCount = (int)$cand->fetchColumn();
if ($candCount === 0) {
    out("⚠  No candidates found for \"{$constituency}\" — the ballot would be empty.");
    out('   Pick a constituency from: php scripts/assign_constituency.php list');
    out();
}

if ($id !== null) {
    $stmt = $pdo->prepare("UPDATE voters SET constituency = ? WHERE id = ?");
    $stmt->execute([$constituency, $id]);
    out("✓ Voter #{$id} → {$constituency} ({$stmt->rowCount()} row updated)");
} elseif ($email !== null) {
    $stmt = $pdo->prepare("UPDATE voters SET constituency = ? WHERE LOWER(email) = ?");
    $stmt->execute([$constituency, $email]);
    out("✓ Voter {$email} → {$constituency} ({$stmt->rowCount()} row updated)");
} elseif ($epic !== null) {
    $stmt = $pdo->prepare("UPDATE voters SET constituency = ? WHERE UPPER(voter_id_number) = ?");
    $stmt->execute([$constituency, $epic]);
    out("✓ Voter EPIC {$epic} → {$constituency} ({$stmt->rowCount()} row updated)");
} elseif ($all || $blank) {
    if ($blank) {
        $stmt = $pdo->prepare("UPDATE voters SET constituency = ? WHERE COALESCE(constituency,'') = ''");
        $stmt->execute([$constituency]);
        out("✓ Assigned {$stmt->rowCount()} voter(s) that had no constituency → {$constituency}");
    } else {
        $stmt = $pdo->prepare("UPDATE voters SET constituency = ?");
        $stmt->execute([$constituency]);
        out("✓ Assigned all {$stmt->rowCount()} voter(s) → {$constituency}");
    }
} else {
    fwrite(STDERR, "✗ Choose a target: --all, --blank, --email=, --epic=, or --id=\n");
    exit(1);
}

out();
out('Next: point a booth at the same constituency so it can issue a ballot.');
out('  Admin console → 📍 Booths & Kiosks → Edit → set the constituency.');
out('  It must match this string EXACTLY (e.g. "Varanasi (PC-77)").');
