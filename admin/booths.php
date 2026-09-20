<?php
/**
 * admin/booths.php
 * ---------------------------------------------------------------
 * Manage polling-station kiosks (ADMIN ONLY).
 *
 * A booth is a physical location with a short code + PIN used to
 * unlock the phone kiosk at that station. Enrollments and booth
 * verifications are recorded against the booth they happened at.
 * ---------------------------------------------------------------
 */
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

$error = '';
$notice = '';

/* ---------------- Enable / disable a booth ---------------- */
if (isset($_GET['toggle'])) {
    $tid = (int)$_GET['toggle'];
    try {
        $pdo->prepare("UPDATE booths SET active = CASE active WHEN 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$tid]);
        $notice = 'Booth status updated.';
    } catch (PDOException $e) {
        $error = 'Could not update booth: ' . $e->getMessage();
    }
}

/* ---------------- Update a booth (name / state / constituency / PIN) ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booth'])) {
    $uid          = (int)($_POST['id'] ?? 0);
    $name         = trim($_POST['name'] ?? '');
    $state        = trim($_POST['state'] ?? '');
    $constituency = trim($_POST['constituency'] ?? '');
    $pin          = (string)($_POST['pin'] ?? '');

    if ($uid <= 0 || $name === '') {
        $error = 'Booth name is required.';
    } elseif ($pin !== '' && strlen($pin) < 6) {
        $error = 'Booth PIN must be at least 6 characters (or leave it blank to keep the current one).';
    } else {
        try {
            if ($pin !== '') {
                $upd = $pdo->prepare("UPDATE booths SET name = ?, state = ?, constituency = ?, pin_hash = ? WHERE id = ?");
                $upd->execute([$name, $state, $constituency, password_hash($pin, PASSWORD_DEFAULT), $uid]);
            } else {
                $upd = $pdo->prepare("UPDATE booths SET name = ?, state = ?, constituency = ? WHERE id = ?");
                $upd->execute([$name, $state, $constituency, $uid]);
            }
            header('Location: booths.php');
            exit();
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Load the booth being edited (if any).
$edit_id    = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$edit_booth = null;
if ($edit_id > 0) {
    try {
        $st = $pdo->prepare("SELECT * FROM booths WHERE id = ? LIMIT 1");
        $st->execute([$edit_id]);
        $edit_booth = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        $edit_booth = null;
    }
}

/* ---------------- Create a booth ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_booth'])) {
    $code         = strtoupper(trim($_POST['code'] ?? ''));
    $name         = trim($_POST['name'] ?? '');
    $state        = trim($_POST['state'] ?? '');
    $constituency = trim($_POST['constituency'] ?? '');
    $pin          = (string)($_POST['pin'] ?? '');

    if ($code === '' || $name === '' || $pin === '') {
        $error = 'Booth code, name, and PIN are required.';
    } elseif (!preg_match('/^[A-Z0-9\-]{3,32}$/', $code)) {
        $error = 'Booth code must be 3–32 chars: letters, digits, or hyphens.';
    } elseif (strlen($pin) < 6) {
        $error = 'Booth PIN must be at least 6 characters.';
    } else {
        try {
            $dup = $pdo->prepare("SELECT id FROM booths WHERE UPPER(code) = ? LIMIT 1");
            $dup->execute([$code]);
            if ($dup->fetch()) {
                $error = 'A booth with this code already exists.';
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO booths (code, name, state, constituency, pin_hash, active) VALUES (?, ?, ?, ?, ?, 1)"
                );
                $ins->execute([$code, $name, $state, $constituency, password_hash($pin, PASSWORD_DEFAULT)]);
                $notice = 'Booth ' . $code . ' created. Unlock the phone kiosk with this code + PIN.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

/* ---------------- Stats ---------------- */
$booths = [];
try {
    $booths = $pdo->query(
        "SELECT b.*,
                (SELECT COUNT(*) FROM passkeys p WHERE p.booth_id = b.id)             AS enroll_count,
                (SELECT COUNT(*) FROM biometric_logs l WHERE l.booth_id = b.id)       AS event_count
         FROM booths b
         ORDER BY b.active DESC, b.name ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $booths = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booths &amp; Kiosks - Admin - Online Voting System</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/app.css">
    <style>
        body { background-color: #f8f9fc; font-family: Arial, sans-serif; }
        .header {
            background-color: var(--primary-color); color: #fff; height: 9vh;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .btn-custom { background-color: var(--primary-color); color: #fff; font-weight: bold; border: none; }
        .panel { background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.05); }
        .panel-header { border-top: 5px solid var(--primary-color); border-radius: 12px 12px 0 0; }
        code.booth-code { background: #f3e8ff; color: var(--primary-color); padding: 2px 8px; border-radius: 6px; font-weight: bold; }
    </style>
    <link rel="stylesheet" href="../css/ui.css">
</head>
<body>

<div class="container-fluid header">
    <h4 class="m-0 font-weight-bold">📍 Booths &amp; Kiosks — Election Staff Only</h4>
    <a href="dashboard.php" class="btn btn-light btn-sm font-weight-bold">← Admin Dashboard</a>
</div>

<div class="container my-4">
    <div class="row">

        <!-- Create booth -->
        <div class="col-lg-4 mb-4">
            <div class="panel p-3 panel-header">
                <?php if ($edit_booth): ?>
                    <h5 class="font-weight-bold mb-1">Edit booth <?= htmlspecialchars($edit_booth['code']); ?></h5>
                    <p class="text-muted small">
                        Point this booth at its Parliamentary Constituency — a citizen only gets a
                        ballot here if their constituency matches.
                    </p>
                <?php else: ?>
                    <h5 class="font-weight-bold mb-1">Create a booth</h5>
                    <p class="text-muted small">
                        Each physical polling station gets one booth code + PIN. Hand these to the booth
                        operator to unlock the phone kiosk.
                    </p>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($notice)): ?>
                    <div class="alert alert-success py-2 small"><?= htmlspecialchars($notice); ?></div>
                <?php endif; ?>

                <form method="POST" action="" autocomplete="off">
                    <?php if ($edit_booth): ?>
                        <input type="hidden" name="update_booth" value="1">
                        <input type="hidden" name="id" value="<?= (int)$edit_booth['id']; ?>">
                    <?php else: ?>
                        <input type="hidden" name="create_booth" value="1">
                    <?php endif; ?>
                    <div class="form-group mb-2">
                        <input type="text" name="code" class="form-control form-control-sm"
                               aria-label="Booth code"
                               placeholder="Booth code (e.g. BOOTH-007) *"
                               value="<?= htmlspecialchars($edit_booth['code'] ?? ($_POST['code'] ?? '')); ?>"
                               <?= $edit_booth ? 'readonly' : 'required'; ?>>
                    </div>
                    <div class="form-group mb-2">
                        <input type="text" name="name" class="form-control form-control-sm"
                               aria-label="Station name"
                               placeholder="Station name *" required
                               value="<?= htmlspecialchars($edit_booth['name'] ?? ($_POST['name'] ?? '')); ?>">
                    </div>
                    <div class="form-group mb-2">
                        <input type="text" name="state" class="form-control form-control-sm"
                               aria-label="State or union territory"
                               placeholder="State / UT"
                               value="<?= htmlspecialchars($edit_booth['state'] ?? ($_POST['state'] ?? '')); ?>">
                    </div>
                    <div class="form-group mb-2">
                        <input type="text" name="constituency" class="form-control form-control-sm"
                               aria-label="Constituency"
                               placeholder="Constituency (e.g. Varanasi (PC-77))"
                               value="<?= htmlspecialchars($edit_booth['constituency'] ?? ($_POST['constituency'] ?? '')); ?>">
                        <small class="text-muted">Must match the citizen's constituency exactly to issue a ballot.</small>
                    </div>
                    <div class="form-group mb-2">
                        <input type="text" name="pin" class="form-control form-control-sm"
                               aria-label="Booth PIN"
                               placeholder="<?= $edit_booth ? 'New PIN (leave blank to keep current)' : 'Booth PIN (min 6) *'; ?>"
                               <?= $edit_booth ? '' : 'minlength="6" required'; ?>>
                    </div>
                    <button type="submit" class="btn btn-custom btn-block btn-sm font-weight-bold">
                        <?= $edit_booth ? '💾 Save Changes' : '➕ Create Booth'; ?>
                    </button>
                    <?php if ($edit_booth): ?>
                        <a href="booths.php" class="btn btn-outline-secondary btn-block btn-sm mt-2">Cancel</a>
                    <?php endif; ?>
                </form>

                <hr>
                <p class="small text-muted mb-0">
                    <strong>Kiosk URL:</strong> open <code>kiosk/login.php</code> on the booth phone and enter the
                    booth code + PIN. WebAuthn requires HTTPS or localhost — pin a stable
                    <code>WEBAUTHN_RP_ID</code> in production (see <code>includes/config.example.php</code>).
                </p>
            </div>
        </div>

        <!-- Booth list -->
        <div class="col-lg-8">
            <div class="panel p-3">
                <h5 class="font-weight-bold mb-3">Registered booths (<?= count($booths); ?>)</h5>

                <?php if (empty($booths)): ?>
                    <div class="alert alert-secondary text-center small mb-0">
                        No booths yet — create one on the left to enable the phone kiosk.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>Booth</th>
                                    <th>Location</th>
                                    <th class="text-center">Enrollments</th>
                                    <th class="text-center">Events</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($booths as $b): ?>
                                <tr>
                                    <td>
                                        <code class="booth-code"><?= htmlspecialchars($b['code']); ?></code>
                                        <div class="small font-weight-bold"><?= htmlspecialchars($b['name']); ?></div>
                                    </td>
                                    <td class="small text-muted">
                                        <?= htmlspecialchars(trim(($b['constituency'] ?? '') . (($b['constituency'] ?? '') && ($b['state'] ?? '') ? ', ' : '') . ($b['state'] ?? '')) ?: '—'); ?>
                                    </td>
                                    <td class="text-center"><?= (int)$b['enroll_count']; ?></td>
                                    <td class="text-center"><?= (int)$b['event_count']; ?></td>
                                    <td class="text-center">
                                        <?= ((int)$b['active'] === 1)
                                            ? '<span class="badge badge-success">Active</span>'
                                            : '<span class="badge badge-secondary">Disabled</span>'; ?>
                                    </td>
                                    <td class="text-right">
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="booths.php?edit=<?= (int)$b['id']; ?>">Edit</a>
                                        <a class="btn btn-sm btn-outline-<?= ((int)$b['active'] === 1) ? 'danger' : 'success'; ?>"
                                           href="booths.php?toggle=<?= (int)$b['id']; ?>">
                                            <?= ((int)$b['active'] === 1) ? 'Disable' : 'Enable'; ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

</body>
</html>
