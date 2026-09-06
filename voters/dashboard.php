<?php
session_start();
require_once __DIR__ . '/../db.php';

// Redirect to login if voter is not authenticated
if (!isset($_SESSION['vid'])) {
    header("Location: ../login.php");
    exit();
}

$vid = (int)$_SESSION['vid'];

// Fetch latest voter record from SQLite database
try {
    $stmt = $pdo->prepare("SELECT * FROM voters WHERE id = ? LIMIT 1");
    $stmt->execute([$vid]);
    $voters = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voters) {
        header("Location: ../logout.php");
        exit();
    }

    // Ensure only approved voters can access the dashboard
    $status = strtolower(trim($voters['status'] ?? 'pending'));
    if ($status !== 'approved') {
        session_destroy();
        header("Location: ../login.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Check face verification session flag
$is_face_verified = !empty($_SESSION['face_verified']) && $_SESSION['face_verified'] === true;

// Server-verified fingerprint / passkey credentials for this voter
$fp_count = 0;
try {
    $fp_stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM passkeys WHERE voter_id = ?");
    $fp_stmt->execute([$vid]);
    $fp_count = (int)$fp_stmt->fetch(PDO::FETCH_ASSOC)['c'];
} catch (PDOException $e) {
    $fp_count = 0;
}
$has_fp = $fp_count > 0;
$has_voted = ((int)($voters['has_voted'] ?? 0) === 1) || (strtolower(trim($voters['voting'] ?? '')) === 'yes');

// Fetch candidate / party list — filtered to the voter's Parliamentary
// Constituency (seeded real ballots), plus any unassigned legacy rows.
$region = trim($_SESSION['selected_constituency'] ?? '');
$candidates_list = [];
try {
    if ($region !== '') {
        $candidates_stmt = $pdo->prepare("SELECT * FROM candidates WHERE constituency = ? OR constituency = '' ORDER BY id ASC");
        $candidates_stmt->execute([$region]);
        $candidates_list = $candidates_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $candidates_stmt = $pdo->query("SELECT * FROM candidates ORDER BY id ASC");
        $candidates_list = $candidates_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    try {
        $groups_stmt = $pdo->query("SELECT id, name, name AS party, image AS photo FROM groups ORDER BY id ASC");
        $candidates_list = $groups_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $ex) {
        $candidates_list = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Dashboard - Online Voting System</title>

    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    
    <style>
        :root {
            --primary-color: blueviolet;
            --primary-hover: #701eb8;
        }

        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }

        .header {
            background-color: var(--primary-color); 
            color: #ffffff;
            height: 9vh;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        #left-side {
            background: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-top: 20px;
        }

        #right-side {
            background: #ffffff;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-top: 20px;
        }

        .parties-heading {
            margin-bottom: 25px;
            font-weight: bold;
            text-align: center;
        }

        .voter-img {
            width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--primary-color);
            padding: 3px;
            margin: 0 auto 20px auto;
            display: block;
            background-color: #f8f9fa;
        }

        .party-img {
            width: 80px;
            height: 60px;
            object-fit: contain !important;
            background-color: #ffffff;
            border-radius: 4px;
            border: 1px solid #ddd;
            padding: 2px;
            display: block;
            margin: 0 auto;
        }

        .table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .table-custom td {
            padding: 10px 0;
            border-bottom: 1px solid #eeeeee;
            font-size: 15px;
        }

        .table-custom td.label-col {
            width: 42%;
            color: #333333;
            font-weight: 600;
        }

        .btn-group-custom {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>

    <div class="container-fluid header">
        <h3 class="m-0 font-weight-bold">Online Voting System</h3>
    </div>

    <!-- Dashboard Main Grid -->
    <div class="container my-4">
        <div class="row">
            
            <!-- Left Side: Voter Details -->
            <div class="col-md-4">
                <div id="left-side">
                    <h4 class="text-center font-weight-bold mb-4">VOTER DETAILS</h4>

                    <?php 
                        $imageName = trim($voters['photo'] ?? $voters['image'] ?? ''); 
                        $photoPath = "../images/default.png";

                        if (!empty($imageName) && file_exists(__DIR__ . "/../images/" . $imageName)) {
                            $photoPath = "../images/" . $imageName;
                        }
                    ?>
                    <img src="<?= htmlspecialchars($photoPath); ?>" 
                         alt="Voter Photo" 
                         class="voter-img"
                         onerror="this.onerror=null; this.src='../images/default.png';">

                    <!-- Voter Data Table -->
                    <table class="table-custom">
                        <tr>
                            <td class="label-col">Name:</td>
                            <td><?= htmlspecialchars($voters['fullname'] ?? $voters['name'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td class="label-col">Voter ID:</td>
                            <td><span class="badge badge-info px-2 py-1"><?= htmlspecialchars($voters['voter_id_number'] ?? $voters['id_number'] ?? 'N/A'); ?></span></td>
                        </tr>
                        <tr>
                            <td class="label-col">Email:</td>
                            <td><?= htmlspecialchars($voters['email'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <td class="label-col">Status:</td>
                            <td><span class="badge badge-success px-2 py-1">Approved Citizen</span></td>
                        </tr>
                        <tr>
                            <td class="label-col">Biometrics:</td>
                            <td>
                                <?php if ($is_face_verified): ?>
                                    <span class="badge badge-success px-2 py-1">Face Verified</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary px-2 py-1">Face Not Verified</span>
                                <?php endif; ?>
                                <?php if ($has_fp): ?>
                                    <span class="badge badge-success px-2 py-1">Fingerprint Enrolled (<?= $fp_count; ?>)</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary px-2 py-1">Fingerprint Not Set</span>
                                <?php endif; ?>
                                <br>
                                <a href="add_passkey.php" class="small" style="color: blueviolet;">
                                    <?= $has_fp ? 'Manage Fingerprints / Passkeys' : 'Enroll Fingerprint Now'; ?>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td class="label-col">Ballot Status:</td>
                            <td>
                                <?php if (!$has_voted): ?>
                                    <span class="badge badge-warning px-2 py-1">Not Voted</span>
                                <?php else: ?>
                                    <span class="badge badge-success px-2 py-1">Already Voted</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <!-- Action Buttons -->
                    <div class="btn-group-custom">
                        <a href="edit_profile.php" class="btn btn-outline-primary btn-block m-0">Edit Profile</a>
                        <a href="../logout.php" class="btn btn-outline-danger btn-block m-0">Logout</a>
                    </div>
                </div>
            </div>

            <!-- Right Side: Available Parties Table -->
            <div class="col-md-8">
                <div id="right-side">
                    <h4 class="parties-heading">
                        AVAILABLE CANDIDATES<?= $region !== '' ? ' — ' . htmlspecialchars($region) : ' FOR VOTING'; ?>
                    </h4>

                    <?php if (!$has_voted && !$is_face_verified): ?>
                        <div class="alert alert-warning text-center py-2 mb-3">
                            <small>Biometric verification is required before you can cast your vote. Choose <strong>Face Verification</strong> or <strong>Fingerprint Verification</strong> below.</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered text-center align-middle">
                            <thead class="thead-dark">
                                <tr>
                                    <th style="width: 10%;">S.No.</th>
                                    <th style="width: 25%;">Symbol</th>
                                    <th style="width: 45%;">Candidate & Party</th>
                                    <th style="width: 20%;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($candidates_list)): ?>
                                    <?php $sno = 1; foreach ($candidates_list as $candidate): ?>
                                        <?php
                                        $groupImg = trim($candidate['photo'] ?? $candidate['image'] ?? '');
                                        $symbolPath = "../images/default.png";

                                        if (!empty($groupImg) && file_exists(__DIR__ . "/../images/" . $groupImg)) {
                                            $symbolPath = "../images/" . $groupImg;
                                        }
                                        $candidate_id = (int)($candidate['id'] ?? $candidate['gid'] ?? 0);
                                        $candidate_name = htmlspecialchars($candidate['name'] ?? '');
                                        $party_name = htmlspecialchars($candidate['party'] ?? '');
                                        ?>
                                        <tr>
                                            <td class="align-middle"><?= $sno++; ?></td>
                                            <td class="align-middle">
                                                <img src="<?= htmlspecialchars($symbolPath); ?>" 
                                                     alt="Party Symbol" 
                                                     class="party-img"
                                                     onerror="this.onerror=null; this.src='../images/default.png';">
                                            </td>
                                            <td class="align-middle text-left pl-3">
                                                <strong><?= $candidate_name; ?></strong>
                                                <?php if (!empty($party_name) && $party_name !== $candidate_name): ?>
                                                    <br><small class="text-muted"><?= $party_name; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="align-middle">
                                                <?php if (!$has_voted): ?>
                                                    <?php if ($is_face_verified): ?>
                                                        <form action="vote.php" method="POST" onsubmit="return confirm('Are you sure you want to cast your vote for <?= htmlspecialchars(addslashes($candidate['name'] ?? 'this candidate')); ?>? This action cannot be undone.');">
                                                            <input type="hidden" name="candidate_id" value="<?= $candidate_id; ?>">
                                                            <button type="submit" name="vote_btn" class="btn btn-success btn-sm font-weight-bold px-3">Vote</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <a href="face_verify.php" class="btn btn-warning btn-sm font-weight-bold px-2 mb-1">Verify Face</a><br>
                                                        <a href="verify_biometrics.php" class="btn btn-info btn-sm font-weight-bold px-2">Verify Fingerprint</a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-sm" disabled>Voted</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            No candidates or parties registered yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

</body>
</html>