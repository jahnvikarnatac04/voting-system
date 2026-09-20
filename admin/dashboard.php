<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/party_symbols.php';

// Redirect to admin login if not authenticated
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// 1. Handle Citizen Verification Actions (Approve / Reject)
if (isset($_GET['action']) && isset($_GET['vid'])) {
    $action = strtolower(trim($_GET['action']));
    $vid = (int)$_GET['vid'];

    if (in_array($action, ['approve', 'reject'])) {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $update_stmt = $pdo->prepare("UPDATE voters SET status = ? WHERE id = ?");
        $update_stmt->execute([$status, $vid]);
    }
    header("Location: dashboard.php");
    exit();
}

// 2. Summary Statistics via PDO
$total_voters = (int)$pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();
$total_approved = (int)$pdo->query("SELECT COUNT(*) FROM voters WHERE status = 'approved'")->fetchColumn();
$total_pending_verifications = (int)$pdo->query("SELECT COUNT(*) FROM voters WHERE status = 'pending'")->fetchColumn();
$total_votes_cast = (int)$pdo->query("SELECT COUNT(*) FROM voters WHERE has_voted = 1 AND status = 'approved'")->fetchColumn();

// Fetch Candidates / Parties
try {
    $candidates_stmt = $pdo->query("SELECT * FROM candidates ORDER BY votes_count DESC");
    $candidates_list = $candidates_stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_parties = count($candidates_list);
} catch (PDOException $e) {
    // Fallback if table is named groups
    try {
        $groups_stmt = $pdo->query("SELECT id, name, name AS party, total_vote AS votes_count, image AS photo FROM groups ORDER BY total_vote DESC");
        $candidates_list = $groups_stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_parties = count($candidates_list);
    } catch (PDOException $ex) {
        $candidates_list = [];
        $total_parties = 0;
    }
}

// Turnout Calculations based on Approved Voters
$pending_votes = max(0, $total_approved - $total_votes_cast);
$turnout_percentage = ($total_approved > 0) ? round(($total_votes_cast / $total_approved) * 100, 1) : 0;
$pending_percentage = ($total_approved > 0) ? round((100 - $turnout_percentage), 1) : 0;

// Biometric enrollment status per voter (for the voters table + booth console count)
$enrolled_map = [];
try {
    $pk_stmt = $pdo->query("SELECT DISTINCT voter_id FROM passkeys");
    foreach ($pk_stmt->fetchAll(PDO::FETCH_ASSOC) as $pk_row) {
        $enrolled_map[(int)$pk_row['voter_id']] = true;
    }
} catch (PDOException $e) {
    $enrolled_map = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Online Voting System</title>

    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/app.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>

        body {
            background-color: #f8f9fc;
            font-family: Arial, sans-serif;
        }

        .header {
            background-color: var(--primary-color);
            color: #ffffff;
            height: 9vh;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border-left: 5px solid var(--primary-color);
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            user-select: none;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-card.active-card {
            background-color: #f3e8ff;
            outline: 2px solid var(--primary-color);
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 5px 0;
        }

        .stat-card p {
            color: #666;
            margin: 0;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .section-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-top: 25px;
        }

        .party-img {
            width: 70px;
            height: 55px;
            object-fit: contain;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 2px;
        }

        .voter-img-thumb {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 50%;
            border: 1px solid #ddd;
            background-color: #f0f0f0;
        }
    </style>
    <link rel="stylesheet" href="../css/ui.css">
</head>
<body>

    <!-- Header Navigation -->
    <div class="container-fluid header">
        <h4 class="m-0 font-weight-bold">Online Voting System — Admin Panel</h4>
        <div>
            <a href="onboard_voter.php" class="btn btn-warning btn-sm font-weight-bold mr-2" title="Admin-only: register a walk-in citizen; their fingerprint is enrolled at a booth kiosk">
                ➕ Onboard Citizen
            </a>
            <a href="booths.php" class="btn btn-info btn-sm font-weight-bold mr-2" title="Manage polling-station kiosks and their booth codes">
                📍 Booths &amp; Kiosks
            </a>
            <a href="verify_pair.php" class="btn btn-info btn-sm font-weight-bold mr-2" title="Generate a one-time code to pair the booth phone">
                📱 Pair Booth Phone
            </a>
            <span class="mr-3 text-white">Welcome, <strong><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></strong></span>
            <a href="logout.php" class="btn btn-light btn-sm font-weight-bold">Logout</a>
        </div>
    </div>

    <div class="container my-4">
        
        <!-- Metric Summary Cards -->
        <div class="row">
            
            <!-- Card 1: Citizen Verifications -->
            <div class="col-md-3 mb-3">
                <div class="stat-card" id="card-verify" style="border-left-color: #fd7e14;" onclick="showSection('verification-section', '')">
                    <p>Pending Verifications</p>
                    <h3 style="color: #fd7e14;"><?= $total_pending_verifications; ?></h3>
                    <small class="text-muted">Click to review ID proofs</small>
                </div>
            </div>

            <!-- Card 2: Total Votes Cast -->
            <div class="col-md-3 mb-3">
                <div class="stat-card" id="card-voted-status" style="border-left-color: #28a745;" onclick="showSection('votes-cast-section', '')">
                    <p>Total Votes Cast</p>
                    <h3 style="color: #28a745;"><?= $total_votes_cast; ?></h3>
                    <small class="text-muted">Click to view ratio gauge</small>
                </div>
            </div>

            <!-- Card 3: Registered Parties -->
            <div class="col-md-3 mb-3">
                <div class="stat-card active-card" id="card-parties" style="border-left-color: #17a2b8;" onclick="showSection('parties-section', '')">
                    <p>Registered Candidates</p>
                    <h3 style="color: #17a2b8;"><?= $total_parties; ?></h3>
                </div>
            </div>

            <!-- Card 4: Voter Turnout -->
            <div class="col-md-3 mb-3">
                <div class="stat-card" id="card-turnout" style="border-left-color: #ffc107;" onclick="showSection('turnout-section', '')">
                    <p>Voter Turnout</p>
                    <h3 style="color: #e0a800;"><?= $turnout_percentage; ?>%</h3>
                    <small class="text-muted">Click to view analytics</small>
                </div>
            </div>

        </div>

        <!-- SECTION 1: Registered Parties & Live Standings -->
        <div class="section-card" id="parties-section">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="font-weight-bold m-0 text-dark">Registered Parties & Live Standings</h5>
                <div>
                    <button class="btn btn-outline-secondary btn-sm mr-2" onclick="showSection('voters-section', 'all')">View Registered Voters</button>
                    <a href="register_group.php" class="btn btn-custom btn-sm px-3">+ Add New Party</a>
                </div>
            </div>

            <div class="table-responsive">
            <table class="table table-bordered table-striped text-center align-middle m-0">
                <thead class="thead-dark">
                    <tr>
                        <th style="width: 8%;">S.No.</th>
                        <th style="width: 22%;">Party Symbol</th>
                        <th style="width: 45%;">Party / Candidate Name</th>
                        <th style="width: 25%;">Total Votes Tallied</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($candidates_list)): ?>
                        <?php $sno_group = 1; foreach ($candidates_list as $candidate): ?>
                            <?php
                            // 'default.png' means "no custom symbol uploaded" -> use the
                            // real party logo from images/parties/ (generic badge if unknown)
                            $photo = $candidate['photo'] ?? '';
                            if (!empty($photo) && $photo !== 'default.png' && file_exists(__DIR__ . "/../images/" . $photo)) {
                                $symbol = "../images/" . $photo;
                            } else {
                                $symbol = "../" . party_symbol($candidate['party'] ?? ($candidate['name'] ?? ''));
                            }
                            ?>
                            <tr>
                                <td class="align-middle"><?= $sno_group++; ?></td>
                                <td class="align-middle">
                                    <img src="<?= htmlspecialchars($symbol); ?>" 
                                         alt="Party Symbol" 
                                         class="party-img"
                                         onerror="this.onerror=null; this.src='../images/default.png';">
                                </td>
                                <td class="align-middle font-weight-bold"><?= htmlspecialchars($candidate['name'] ?? $candidate['party']); ?></td>
                                <td class="align-middle">
                                    <span class="badge badge-success px-3 py-2" style="font-size: 0.95rem;">
                                        <?= (int)($candidate['votes_count'] ?? 0); ?> votes
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-3">No parties registered yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- SECTION 2: Citizen ID Verification Panel -->
        <div class="section-card" id="verification-section" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="font-weight-bold m-0 text-dark">Indian Citizen Verification & Approval Panel</h5>
                <span class="badge badge-warning px-3 py-2">Pending Review: <?= $total_pending_verifications; ?></span>
            </div>

            <div class="table-responsive">
            <table class="table table-bordered table-hover text-center align-middle m-0">
                <thead class="thead-dark">
                    <tr>
                        <th style="width: 5%;">S.No.</th>
                        <th style="width: 15%;">Name</th>
                        <th style="width: 15%;">Voter ID (EPIC)</th>
                        <th style="width: 18%;">Email</th>
                        <th style="width: 18%;">ID Proof Document</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 17%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $verify_stmt = $pdo->query("SELECT * FROM voters ORDER BY CASE WHEN status = 'pending' THEN 1 ELSE 2 END, id DESC");
                    $verify_list = $verify_stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($verify_list)):
                        $sno_v = 1;
                        foreach ($verify_list as $row):
                            $doc_path = (!empty($row['document_proof']) && file_exists(__DIR__ . "/../images/" . $row['document_proof'])) 
                                        ? "../images/" . $row['document_proof'] 
                                        : "";
                            $curr_status = strtolower(trim($row['status'] ?? 'pending'));
                    ?>
                    <tr>
                        <td class="align-middle"><?= $sno_v++; ?></td>
                        <td class="align-middle font-weight-bold text-left"><?= htmlspecialchars($row['fullname']); ?></td>
                        <td class="align-middle"><span class="badge badge-info px-2 py-1"><?= htmlspecialchars($row['voter_id_number'] ?? 'N/A'); ?></span></td>
                        <td class="align-middle text-left">
                            <small class="d-block font-weight-bold"><?= htmlspecialchars($row['email']); ?></small>
                        </td>
                        <td class="align-middle">
                            <?php if (!empty($doc_path)): ?>
                                <a href="<?= htmlspecialchars($doc_path); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                    View Proof ↗
                                </a>
                            <?php else: ?>
                                <span class="text-muted small">No doc attached</span>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle">
                            <?php if ($curr_status === 'approved'): ?>
                                <span class="badge badge-success px-2 py-1">Approved</span>
                            <?php elseif ($curr_status === 'rejected'): ?>
                                <span class="badge badge-danger px-2 py-1">Rejected</span>
                            <?php else: ?>
                                <span class="badge badge-warning px-2 py-1">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle">
                            <?php if ($curr_status === 'pending'): ?>
                                <a href="dashboard.php?action=approve&vid=<?= (int)$row['id']; ?>" class="btn btn-success btn-sm font-weight-bold">Approve</a>
                                <a href="dashboard.php?action=reject&vid=<?= (int)$row['id']; ?>" class="btn btn-danger btn-sm font-weight-bold" onclick="return confirm('Reject this citizen application?');">Reject</a>
                            <?php else: ?>
                                <span class="text-muted small font-italic">Processed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-3">No registration records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- SECTION 3: Split Ratio Gauge Bar -->
        <div class="section-card" id="votes-cast-section" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="font-weight-bold m-0 text-dark">Ballot Distribution & Cast Status</h5>
                <span class="badge badge-primary px-3 py-2" style="font-size: 0.9rem;">
                    Total Verified Citizens: <?= $total_approved; ?>
                </span>
            </div>

            <div class="progress mb-3" style="height: 34px; border-radius: 17px; overflow: hidden; background-color: #e9ecef;">
                <div class="progress-bar bg-success font-weight-bold" 
                     role="progressbar" 
                     style="width: <?= $turnout_percentage; ?>%; font-size: 0.95rem;">
                    <?php if ($total_votes_cast > 0): ?>
                        <?= $total_votes_cast; ?> Cast (<?= $turnout_percentage; ?>%)
                    <?php endif; ?>
                </div>
                <div class="progress-bar bg-danger font-weight-bold" 
                     role="progressbar" 
                     style="width: <?= $pending_percentage; ?>%; font-size: 0.95rem;">
                    <?php if ($pending_votes > 0): ?>
                        <?= $pending_votes; ?> Pending (<?= $pending_percentage; ?>%)
                    <?php endif; ?>
                </div>
            </div>

            <div class="row text-center mt-4">
                <div class="col-md-6 mb-2">
                    <div class="p-3 border rounded bg-light">
                        <span class="text-success font-weight-bold" style="font-size: 1.1rem;">
                            ● Verified Ballots Cast: <?= $total_votes_cast; ?> (<?= $turnout_percentage; ?>%)
                        </span>
                    </div>
                </div>
                <div class="col-md-6 mb-2">
                    <div class="p-3 border rounded bg-light">
                        <span class="text-danger font-weight-bold" style="font-size: 1.1rem;">
                            ● Verified Pending Turnout: <?= $pending_votes; ?> (<?= $pending_percentage; ?>%)
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 4: Voter Turnout Analytics -->
        <div class="section-card" id="turnout-section" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="font-weight-bold m-0 text-dark">Election Participation & Voter Turnout Analytics</h5>
                <span class="badge badge-primary px-3 py-2" style="font-size: 0.9rem;">
                    Verified Citizens: <?= $total_approved; ?>
                </span>
            </div>
            
            <div class="row align-items-center">
                <div class="col-md-6 mb-4">
                    <h6 class="font-weight-bold text-secondary mb-2">Participation Progress (Approved Voters)</h6>
                    <div class="progress mb-4" style="height: 28px; border-radius: 14px; overflow: hidden; background-color: #e9ecef;">
                        <div class="progress-bar bg-success font-weight-bold" 
                             role="progressbar" 
                             style="width: <?= $turnout_percentage; ?>%; font-size: 0.95rem;">
                            <?= $turnout_percentage; ?>% Turnout
                        </div>
                    </div>

                    <ul class="list-group shadow-sm">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="text-primary font-weight-bold">●</i> Approved Eligible Voters:</span>
                            <strong><?= $total_approved; ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="text-success font-weight-bold">●</i> Ballots Cast:</span>
                            <strong class="text-success"><?= $total_votes_cast; ?> (<?= $turnout_percentage; ?>%)</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="text-danger font-weight-bold">●</i> Awaiting Turnout:</span>
                            <strong class="text-danger"><?= $pending_votes; ?> (<?= $pending_percentage; ?>%)</strong>
                        </li>
                    </ul>
                </div>

                <div class="col-md-6 text-center">
                    <div style="max-width: 260px; margin: 0 auto;">
                        <canvas id="turnoutChart"></canvas>
                    </div>
                    <small class="text-muted d-block mt-2 font-weight-bold">Turnout Distribution</small>
                </div>
            </div>
        </div>

        <!-- SECTION 5: Registered Voters Details Table -->
        <div class="section-card" id="voters-section" style="display: none;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="font-weight-bold m-0 text-dark">Registered Voters List</h5>
                <div>
                    <button class="btn btn-outline-secondary btn-sm" onclick="filterVoterTable('all')">Show All</button>
                    <button class="btn btn-outline-success btn-sm" onclick="filterVoterTable('voted')">Voted Only</button>
                    <button class="btn btn-outline-danger btn-sm" onclick="filterVoterTable('not_voted')">Not Voted</button>
                </div>
            </div>

            <div class="table-responsive">
            <table class="table table-bordered table-hover text-center align-middle m-0" id="voters-table">
                <thead class="thead-dark">
                    <tr>
                        <th style="width: 5%;">S.No.</th>
                        <th style="width: 10%;">Photo</th>
                        <th style="width: 20%;">Name</th>
                        <th style="width: 20%;">Email</th>
                        <th style="width: 15%;">Voter ID (EPIC)</th>
                        <th style="width: 10%;">Approval</th>
                        <th style="width: 10%;">Voting Status</th>
                        <th style="width: 10%;">Biometrics</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $voters_list_stmt = $pdo->query("SELECT * FROM voters ORDER BY id DESC");
                    $voters_list = $voters_list_stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($voters_list)):
                        $sno_voter = 1;
                        foreach ($voters_list as $voter):
                            $voter_photo = (!empty($voter['photo']) && file_exists(__DIR__ . "/../images/" . $voter['photo']))
                                            ? "../images/" . $voter['photo']
                                            : "../images/default.png";
                            $has_voted = ((int)$voter['has_voted'] === 1);
                            $appr_status = strtolower(trim($voter['status'] ?? 'pending'));
                    ?>
                    <tr class="voter-row" data-status="<?= $has_voted ? 'voted' : 'not_voted'; ?>">
                        <td class="align-middle"><?= $sno_voter++; ?></td>
                        <td class="align-middle">
                            <img src="<?= htmlspecialchars($voter_photo); ?>" 
                                 alt="Voter" 
                                 class="voter-img-thumb"
                                 onerror="this.onerror=null; this.src='../images/default.png';">
                        </td>
                        <td class="align-middle font-weight-bold text-left"><?= htmlspecialchars($voter['fullname']); ?></td>
                        <td class="align-middle text-left"><?= htmlspecialchars($voter['email']); ?></td>
                        <td class="align-middle"><span class="badge badge-info px-2 py-1"><?= htmlspecialchars($voter['voter_id_number'] ?? 'N/A'); ?></span></td>
                        <td class="align-middle">
                            <?php if ($appr_status === 'approved'): ?>
                                <span class="badge badge-success px-2 py-1">Approved</span>
                            <?php elseif ($appr_status === 'rejected'): ?>
                                <span class="badge badge-danger px-2 py-1">Rejected</span>
                            <?php else: ?>
                                <span class="badge badge-warning px-2 py-1">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle">
                            <?php if ($has_voted): ?>
                                <span class="badge badge-success px-2 py-1">Voted</span>
                            <?php else: ?>
                                <span class="badge badge-secondary px-2 py-1">Not Voted</span>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle">
                            <?php if (isset($enrolled_map[(int)$voter['id']])): ?>
                                <span class="badge badge-success px-2 py-1" title="Fingerprint enrolled">✔ Enrolled</span>
                            <?php else: ?>
                                <span class="badge badge-secondary px-2 py-1" title="Fingerprint is enrolled at a booth kiosk">Not enrolled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center py-3">No registered voters found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

    </div>

    <script>
    let turnoutChart = null;

    function renderTurnoutChart() {
        if (turnoutChart !== null) return;

        const ctx = document.getElementById('turnoutChart').getContext('2d');
        turnoutChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Voted', 'Not Voted'],
                datasets: [{
                    data: [<?= $total_votes_cast; ?>, <?= $pending_votes; ?>],
                    backgroundColor: ['#28a745', '#dc3545'],
                    hoverBackgroundColor: ['#218838', '#c82333'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 14, font: { size: 12 } }
                    }
                }
            }
        });
    }

    function showSection(sectionId, filterType) {
        const partiesSec = document.getElementById('parties-section');
        const verifySec = document.getElementById('verification-section');
        const votesCastSec = document.getElementById('votes-cast-section');
        const turnoutSec = document.getElementById('turnout-section');
        const votersSec = document.getElementById('voters-section');

        document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active-card'));

        if (partiesSec) partiesSec.style.display = 'none';
        if (verifySec) verifySec.style.display = 'none';
        if (votesCastSec) votesCastSec.style.display = 'none';
        if (turnoutSec) turnoutSec.style.display = 'none';
        if (votersSec) votersSec.style.display = 'none';

        if (sectionId === 'parties-section') {
            partiesSec.style.display = 'block';
            document.getElementById('card-parties').classList.add('active-card');
        } else if (sectionId === 'verification-section') {
            verifySec.style.display = 'block';
            document.getElementById('card-verify').classList.add('active-card');
        } else if (sectionId === 'votes-cast-section') {
            votesCastSec.style.display = 'block';
            document.getElementById('card-voted-status').classList.add('active-card');
        } else if (sectionId === 'turnout-section') {
            turnoutSec.style.display = 'block';
            document.getElementById('card-turnout').classList.add('active-card');
            renderTurnoutChart();
        } else if (sectionId === 'voters-section') {
            votersSec.style.display = 'block';
            filterVoterTable(filterType || 'all');
        }
    }

    function filterVoterTable(status) {
        const rows = document.querySelectorAll('.voter-row');
        rows.forEach(row => {
            if (status === 'all') {
                row.style.display = '';
            } else if (row.getAttribute('data-status') === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    </script>

</body>
</html>