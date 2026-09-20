<?php
/**
 * admin/onboard_voter.php
 * ---------------------------------------------------------------
 * Admin-only onboarding for a walk-in citizen (ADMIN ONLY).
 *
 * This creates the voter record ONLY. Fingerprint enrollment is done
 * at a booth kiosk (kiosk/index.php) — the kiosk binds the credential
 * to the citizen and records which booth it happened at.
 *
 * Replaces the old admin/enroll_voter.php, whose admin-side scanning
 * path is now redundant with the booth kiosk.
 * ---------------------------------------------------------------
 */
session_start();
require_once __DIR__ . '/../db.php';

// Admin gate: election staff only
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$error   = '';
$created = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['onboard'])) {
    $fullname     = trim($_POST['fullname'] ?? '');
    $email        = strtolower(trim($_POST['email'] ?? ''));
    $epic         = strtoupper(trim($_POST['voter_id_number'] ?? ''));
    $mobile       = trim($_POST['mobile'] ?? '');
    $constituency = trim($_POST['constituency'] ?? '');
    $password     = (string)($_POST['password'] ?? '');
    $status       = ($_POST['status'] ?? 'approved') === 'pending' ? 'pending' : 'approved';

    if ($fullname === '' || $email === '' || $epic === '') {
        $error = 'Full name, Email and Voter ID (EPIC) are all required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Temporary password must be at least 6 characters.';
    } else {
        try {
            $dup = $pdo->prepare(
                "SELECT id, email, voter_id_number FROM voters
                 WHERE LOWER(email) = ? OR UPPER(voter_id_number) = ? LIMIT 1"
            );
            $dup->execute([$email, $epic]);
            $existing = $dup->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if (strcasecmp($existing['email'], $email) === 0) {
                    $error = 'A voter with this email already exists (#' . (int)$existing['id'] . ').';
                } else {
                    $error = 'A voter with this Voter ID (EPIC) already exists (#' . (int)$existing['id'] . ').';
                }
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO voters (fullname, email, voter_id_number, mobile, constituency, address, password, photo, document_proof, status, has_voted)
                     VALUES (?, ?, ?, ?, ?, '', ?, 'default.png', '', ?, 0)"
                );
                $ins->execute([
                    $fullname,
                    $email,
                    $epic,
                    $mobile !== '' ? $mobile : null,
                    $constituency,
                    password_hash($password, PASSWORD_DEFAULT),
                    $status,
                ]);
                $created = [
                    'id'           => (int)$pdo->lastInsertId(),
                    'fullname'     => $fullname,
                    'email'        => $email,
                    'password'     => $password,
                    'constituency' => $constituency,
                    'status'       => $status,
                ];
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

$old = [
    'fullname'     => htmlspecialchars($_POST['fullname'] ?? ''),
    'email'        => htmlspecialchars($_POST['email'] ?? ''),
    'epic'         => htmlspecialchars($_POST['voter_id_number'] ?? ''),
    'mobile'       => htmlspecialchars($_POST['mobile'] ?? ''),
    'constituency' => htmlspecialchars($_POST['constituency'] ?? ''),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboard Citizen - Admin - Online Voting System</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/app.css">
    <style>
        body { background-color: #f8f9fc; font-family: Arial, sans-serif; }
        .header {
            background-color: var(--primary-color); color: #fff; height: 9vh;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .panel {
            background: #fff; border-radius: 12px; padding: 28px; max-width: 720px;
            margin: 36px auto; border: 1px solid #e0e0e0; border-top: 5px solid var(--primary-color);
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
        }
        .btn-custom { background-color: var(--primary-color); color: #fff; font-weight: bold; border: none; }
        code.cred { background: #f3e8ff; color: var(--primary-color); padding: 2px 8px; border-radius: 6px; font-weight: bold; }
    </style>
    <link rel="stylesheet" href="../css/ui.css">
</head>
<body>

<div class="container-fluid header">
    <h4 class="m-0 font-weight-bold">➕ Onboard a Citizen — Election Staff Only</h4>
    <a href="dashboard.php" class="btn btn-light btn-sm font-weight-bold">← Admin Dashboard</a>
</div>

<div class="container">
    <div class="panel">

        <?php if ($created): ?>
            <div class="text-center mb-3"><div style="font-size:44px;">✅</div></div>
            <h4 class="font-weight-bold text-center mb-2">Citizen onboarded</h4>
            <p class="text-muted text-center small">
                Record <strong>#<?= (int)$created['id']; ?></strong> created
                (<?= htmlspecialchars(ucfirst($created['status'])); ?>).
                Hand over these login credentials:
            </p>
            <div class="text-center mb-3">
                <div><code class="cred"><?= htmlspecialchars($created['email']); ?></code>
                     / <code class="cred"><?= htmlspecialchars($created['password']); ?></code></div>
            </div>

            <div class="alert alert-info small">
                <strong>Next: enroll their fingerprint at a booth kiosk.</strong><br>
                On the booth phone, open <code>kiosk/login.php</code>, unlock with the booth code + PIN,
                search this citizen, and press <strong>Enroll</strong>.
                <?php if ($created['constituency'] === ''): ?>
                    <br><span class="text-danger">⚠ No constituency was set — the citizen cannot cast a ballot until
                    their constituency matches the booth's.</span>
                <?php else: ?>
                    <br>Constituency: <strong><?= htmlspecialchars($created['constituency']); ?></strong>
                    (the booth must be set to the same one to issue a ballot).
                <?php endif; ?>
            </div>

            <a href="onboard_voter.php" class="btn btn-custom w-100 mb-2">Onboard another citizen</a>
            <a href="booths.php" class="btn btn-outline-secondary w-100">📍 Booths &amp; Kiosks</a>

        <?php else: ?>

            <div class="text-center mb-4">
                <div style="font-size:44px;">🧾</div>
                <h4 class="font-weight-bold mb-1">Onboard a Walk-in Citizen</h4>
                <p class="text-muted small mb-0">
                    Creates the voter record after you verify their ID in person.
                    Fingerprint enrollment is done afterwards at a booth kiosk.
                </p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 text-center small"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="onboardForm" autocomplete="off">
                <input type="hidden" name="onboard" value="1">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold" for="fullname">Full name (as on ID) *</label>
                        <input type="text" name="fullname" id="fullname" class="form-control form-control-sm" value="<?= $old['fullname']; ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold" for="email">Email (login ID) *</label>
                        <input type="email" name="email" id="email" class="form-control form-control-sm" value="<?= $old['email']; ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold" for="voter_id_number">Voter ID (EPIC) *</label>
                        <input type="text" name="voter_id_number" id="voter_id_number" class="form-control form-control-sm" value="<?= $old['epic']; ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold" for="mobile">Mobile (optional)</label>
                        <input type="text" name="mobile" id="mobile" class="form-control form-control-sm" value="<?= $old['mobile']; ?>">
                    </div>
                    <div class="form-group col-md-8">
                        <label class="small font-weight-bold" for="constituency">Constituency</label>
                        <input type="text" name="constituency" id="constituency" class="form-control form-control-sm"
                               placeholder="e.g. Varanasi (PC-77)" value="<?= $old['constituency']; ?>">
                        <small class="text-muted">Decides which ballot this citizen gets at a booth.</small>
                    </div>
                    <div class="form-group col-md-4">
                        <label class="small font-weight-bold" for="status">Status</label>
                        <select name="status" id="status" class="form-control form-control-sm">
                            <option value="approved" selected>Approved now</option>
                            <option value="pending">Keep pending</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label class="small font-weight-bold" for="password">Temporary password *</label>
                        <input type="text" name="password" id="password" class="form-control form-control-sm" minlength="6" placeholder="min 6 characters" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-custom btn-block font-weight-bold">Create Citizen Record</button>
                <small class="text-muted d-block mt-2 text-center">
                    ID is verified in person by you. Fingerprint enrollment happens at the booth kiosk.
                </small>
            </form>

            <hr>
            <p class="small text-muted mb-0 text-center">
                Bulk-assign constituencies:
                <code>php scripts/assign_constituency.php assign "Varanasi (PC-77)" --blank</code>
            </p>
        <?php endif; ?>

    </div>
</div>

<script>
// Suggest a temp password if the admin leaves it blank.
(function () {
    const pw = document.querySelector('input[name="password"]');
    const form = document.getElementById('onboardForm');
    if (pw && form) {
        form.addEventListener('submit', function () {
            if (!pw.value) pw.value = 'Vote@' + Math.floor(1000 + Math.random() * 9000);
        });
    }
})();
</script>
</body>
</html>
