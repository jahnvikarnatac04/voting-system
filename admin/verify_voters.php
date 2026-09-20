<?php
session_start();
require_once __DIR__ . '/../db.php';

// Check if admin is logged in (adjust session key to match your admin login setup)
if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$message = "";
$error = "";

// Handle Approve / Reject Actions
if (isset($_GET['action'], $_GET['id'])) {
    $action = strtolower(trim($_GET['action']));
    $voter_id = (int)$_GET['id'];

    if (in_array($action, ['approve', 'reject'])) {
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';

        try {
            $stmt = $pdo->prepare("UPDATE voters SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $voter_id]);
            $_SESSION['flash_msg'] = "Voter #{$voter_id} has been successfully " . ucfirst($new_status) . ".";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Database error: " . $e->getMessage();
        }
    }
    header("Location: verify_voters.php");
    exit();
}

// Flash messages
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Fetch all pending voters
try {
    $stmt = $pdo->query("SELECT * FROM voters WHERE status = 'pending' ORDER BY created_at ASC");
    $pending_voters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_voters = [];
    $error = "Failed to fetch voters: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Voters - Admin Panel</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/app.css">
    <style>
        body {
            background-color: var(--bg-light);
            font-family: Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
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
        .header a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
        }
        .content-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            margin: 30px 0;
        }
        .voter-img {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ddd;
        }
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        footer {
            text-align: center;
            padding: 15px 0;
            color: #777;
            font-size: 14px;
            background-color: #fff;
            border-top: 1px solid #e9ecef;
        }
    </style>
    <link rel="stylesheet" href="../css/ui.css">
</head>
<body>

    <header class="header">
        <h4 class="m-0 font-weight-bold">Online Voting System — Admin Panel</h4>
        <div>
            <a href="dashboard.php" class="mr-3">Dashboard</a>
            <a href="logout.php" class="text-warning font-weight-bold">Logout</a>
        </div>
    </header>

    <main class="container-fluid px-4">
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="font-weight-bold m-0">Pending Voter Applications</h4>
                <span class="badge badge-secondary"><?= count($pending_voters); ?> Pending</span>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>Photo</th>
                            <th>Full Name</th>
                            <th>Voter ID (EPIC)</th>
                            <th>Email</th>
                            <th>Proof Document</th>
                            <th>Applied Date</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_voters)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No pending registrations found. All voters are verified!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_voters as $voter): ?>
                                <tr>
                                    <td>
                                        <?php 
                                            $photo_src = (!empty($voter['photo']) && file_exists(__DIR__ . '/../images/' . $voter['photo']))
                                                ? '../images/' . htmlspecialchars($voter['photo']) 
                                                : '../images/default.png';
                                        ?>
                                        <img src="<?= $photo_src; ?>" alt="Photo" class="voter-img">
                                    </td>
                                    <td class="font-weight-bold"><?= htmlspecialchars($voter['fullname']); ?></td>
                                    <td><code><?= htmlspecialchars($voter['voter_id_number']); ?></code></td>
                                    <td><?= htmlspecialchars($voter['email']); ?></td>
                                    <td>
                                        <?php if (!empty($voter['document_proof']) && file_exists(__DIR__ . '/../images/' . $voter['document_proof'])): ?>
                                            <a href="../images/<?= htmlspecialchars($voter['document_proof']); ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                View Document
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">No file attached</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?= htmlspecialchars($voter['created_at'] ?? 'N/A'); ?></small></td>
                                    <td><span class="badge-pending">Pending</span></td>
                                    <td class="text-center">
                                        <a href="verify_voters.php?action=approve&id=<?= (int)$voter['id']; ?>" 
                                           class="btn btn-sm btn-success mr-1"
                                           onclick="return confirm('Approve voter: <?= addslashes($voter['fullname']); ?>?');">
                                           Approve
                                        </a>
                                        <a href="verify_voters.php?action=reject&id=<?= (int)$voter['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Reject voter: <?= addslashes($voter['fullname']); ?>?');">
                                           Reject
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <p class="m-0">&copy; <?= date('Y'); ?> Online Voting System. All Rights Reserved.</p>
    </footer>

</body>
</html>