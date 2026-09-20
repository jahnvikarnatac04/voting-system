<?php
/**
 * kiosk/vote.php
 * ---------------------------------------------------------------
 * Casts a booth-kiosk ballot. Every guard is re-checked here — never
 * trust the ballot page:
 *   - kiosk must be unlocked (scoped booth session)
 *   - CSRF token must match
 *   - a fingerprint verification must still be inside the ballot window
 *   - citizen approved, not yet voted, constituency matches the booth
 *   - candidate must stand in that constituency
 * The one-vote mark is an atomic conditional UPDATE, so a double submit
 * or a race cannot record two ballots.
 * ---------------------------------------------------------------
 */
require_once __DIR__ . '/_kiosk.php';
require_once __DIR__ . '/../includes/i18n.php';
kiosk_require_unlocked();

$booth = kiosk_booth();

/** Render a terminal error page for the kiosk operator. */
function kiosk_vote_error(string $message): void
{
    ?>
    <!DOCTYPE html>
    <html lang="<?= current_lang() ?>"<?= i18n_is_rtl() ? ' dir="rtl"' : '' ?>>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex">
        <title><?= te('kiosk.vote_not_recorded') ?></title>
        <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" href="../css/app.css">
        <style>
            body { background: #f8f9fc; font-family: Arial, sans-serif; }
            .card-x { background:#fff; border:1px solid #e0e0e0; border-radius:12px; padding:28px;
                      max-width:520px; margin:60px auto; box-shadow:0 6px 20px rgba(0,0,0,.06);
                      border-top:5px solid #dc3545; text-align:center; }
            .btn-custom { background-color: var(--primary-color); color:#fff; font-weight:bold; border:none; }
        </style>
        <link rel="stylesheet" href="../css/ui.css">
    </head>
    <body>
<?php render_lang_switcher(); ?>
        <div class="card-x">
            <div style="font-size:42px;">⚠️</div>
            <h5 class="font-weight-bold mb-2">Vote not recorded</h5>
            <p class="text-muted small"><?= htmlspecialchars($message); ?></p>
            <a href="index.php?mode=clear" class="btn btn-custom w-100"><?= te('kiosk.back_to_search') ?></a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

if (!kiosk_csrf_check($_POST['_csrf'] ?? '')) {
    kiosk_vote_error('Your session token was missing or stale. Please verify the citizen again and retry.');
}

$auth = kiosk_verified_voter();
if ($auth === null) {
    header('Location: index.php?expired=1');
    exit();
}

$voter_id     = (int)$auth['voter_id'];
$candidate_id = (int)($_POST['candidate_id'] ?? 0);

if ($candidate_id <= 0) {
    kiosk_vote_error('No candidate was selected.');
}

// Eligibility at THIS booth.
$el = kiosk_ballot_eligibility($pdo, $voter_id, $booth['id']);
if (!$el['ok']) {
    kiosk_vote_error($el['reason']);
}

try {
    $pdo->beginTransaction();

    // Re-check the citizen inside the transaction (guards double submits/races).
    $chk = $pdo->prepare("SELECT status, has_voted, voting, constituency FROM voters WHERE id = ? LIMIT 1");
    $chk->execute([$voter_id]);
    $v = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$v || strtolower(trim((string)$v['status'])) !== 'approved') {
        throw new Exception('This citizen is not approved to vote.');
    }
    if (((int)$v['has_voted'] === 1) || strtolower(trim((string)$v['voting'])) === 'yes') {
        throw new Exception('This citizen has already voted.');
    }
    if (trim((string)$v['constituency']) === '' || strcasecmp(trim((string)$v['constituency']), $el['constituency']) !== 0) {
        throw new Exception('This citizen is not eligible in this constituency.');
    }

    // Candidate must stand in the citizen's constituency.
    $updC = $pdo->prepare("UPDATE candidates SET votes_count = votes_count + 1 WHERE id = ? AND constituency = ?");
    $updC->execute([$candidate_id, $el['constituency']]);
    if ($updC->rowCount() !== 1) {
        throw new Exception('That candidate is not on this citizen\'s ballot.');
    }

    // Atomic one-vote mark: only succeeds while still unvoted.
    $updV = $pdo->prepare(
        "UPDATE voters SET has_voted = 1, voting = 'yes'
         WHERE id = ? AND has_voted = 0 AND (voting IS NULL OR LOWER(voting) <> 'yes')"
    );
    $updV->execute([$voter_id]);
    if ($updV->rowCount() !== 1) {
        throw new Exception('This citizen has already voted.');
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    kiosk_vote_error($e->getMessage());
}

// Consume the authorization, rotate the CSRF token, and hand the operator a
// receipt via a redirect (POST -> redirect -> GET, so a refresh can't re-vote).
kiosk_clear_ballot_auth();
unset($_SESSION['kiosk_csrf']);

$_SESSION['booth_vote_receipt'] = [
    'name'         => $el['voter']['fullname'] ?? ('Voter #' . $voter_id),
    'constituency' => $el['constituency'],
    'at'           => time(),
];

header('Location: index.php');
exit();
