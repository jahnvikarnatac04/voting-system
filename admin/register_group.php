<?php
session_start();
require_once __DIR__ . '/../db.php';

// 1. Strict Authentication Check
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$error_msg = "";
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_group'])) {
    $name     = trim($_POST['name'] ?? '');
    $party    = trim($_POST['party'] ?? $name);
    $email    = trim($_POST['email'] ?? '');
    $mobile   = trim($_POST['mobile'] ?? '');
    $address  = trim($_POST['address'] ?? '');

    if (empty($name) || empty($party)) {
        $error_msg = "Both candidate name and party affiliation are required.";
    } elseif (!empty($mobile) && (!is_numeric($mobile) || strlen($mobile) !== 10)) {
        $error_msg = "Please enter a valid 10-digit contact mobile number.";
    } else {
        try {
            // 2. Ensure candidates table schema is ready
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS candidates (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    party TEXT NOT NULL,
                    photo TEXT DEFAULT 'default.png',
                    votes_count INTEGER NOT NULL DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ");

            // 3. Process Symbol / Image Upload
            $unique_image = "default.png";
            $target_dir = __DIR__ . "/../images/";

            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['image']['tmp_name'];
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

                if (!in_array($ext, $allowed_exts)) {
                    throw new Exception("Invalid image format. Allowed: JPG, PNG, WEBP, SVG.");
                }

                $unique_image = time() . '_party_' . uniqid() . '.' . $ext;
                move_uploaded_file($file_tmp, $target_dir . $unique_image);
            }

            // 4. Insert candidate record
            $stmt = $pdo->prepare("
                INSERT INTO candidates (name, party, photo, votes_count) 
                VALUES (?, ?, ?, 0)
            ");
            $stmt->execute([$name, $party, $unique_image]);

            // Synchronize with groups table fallback if used
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS groups (
                        gid INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        email TEXT,
                        mobile TEXT,
                        address TEXT,
                        image TEXT DEFAULT 'default.png',
                        total_vote INTEGER DEFAULT 0
                    );
                ");
                $grp_stmt = $pdo->prepare("
                    INSERT INTO groups (name, email, mobile, address, image, total_vote) 
                    VALUES (?, ?, ?, ?, ?, 0)
                ");
                $grp_stmt->execute([$name, $email, $mobile, $address, $unique_image]);
            } catch (Exception $ignored) {}

            echo '<script>
                alert("Candidate / Party registered successfully!");
                window.location.href = "dashboard.php";
            </script>';
            exit();

        } catch (Exception $e) {
            $error_msg = "Failed to register: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Party / Candidate - Admin Panel</title>

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

        .card-custom {
            background: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            margin: 35px auto;
        }

        .btn-custom {
            background-color: var(--primary-color);
            color: #ffffff;
            font-weight: bold;
            padding: 10px;
            border-radius: 6px;
            width: 100%;
            border: none;
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

    <main class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card-custom">
                    <h4 class="text-center font-weight-bold mb-3">Register New Candidate</h4>
                    <p class="text-center text-muted small mb-4">Add a new contestant to the official electronic ballot</p>

                    <?php if (!empty($error_msg)): ?>
                        <div class="alert alert-danger py-2 text-center" role="alert">
                            <small><?= htmlspecialchars($error_msg); ?></small>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                        
                        <div class="form-group mb-3">
                            <label for="name"><strong>Candidate Name:</strong></label>
                            <input type="text" class="form-control" name="name" id="name" 
                                   value="<?= htmlspecialchars($_POST['name'] ?? ''); ?>" 
                                   placeholder="e.g. Narendra Modi" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="party"><strong>Party Name / Affiliation:</strong></label>
                            <input type="text" class="form-control" name="party" id="party" 
                                   value="<?= htmlspecialchars($_POST['party'] ?? ''); ?>" 
                                   placeholder="e.g. Bharatiya Janata Party (BJP)" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="email"><strong>Official Contact Email:</strong></label>
                            <input type="email" class="form-control" name="email" id="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                   placeholder="party@example.com">
                        </div>

                        <div class="form-group mb-3">
                            <label for="mobile"><strong>Contact Mobile Number:</strong></label>
                            <input type="text" class="form-control" name="mobile" id="mobile" 
                                   value="<?= htmlspecialchars($_POST['mobile'] ?? ''); ?>" 
                                   placeholder="10-digit mobile number" maxlength="10">
                            <small id="mobileError" class="form-text text-danger"></small>
                        </div>

                        <div class="form-group mb-3">
                            <label for="address"><strong>Headquarters / Address:</strong></label>
                            <textarea name="address" id="address" class="form-control" rows="2" placeholder="Party office address"><?= htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group mb-4">
                            <label for="image"><strong>Party Symbol / Candidate Logo:</strong></label>
                            <input type="file" class="form-control" name="image" id="image" accept="image/*" required>
                            <small class="text-muted">Accepted formats: JPG, PNG, WEBP, SVG</small>
                        </div>

                        <button type="submit" class="btn btn-custom" name="register_group">Register Candidate</button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="dashboard.php" class="text-muted small">&larr; Back to Admin Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <p class="m-0">&copy; <?= date('Y'); ?> Online Voting System. All Rights Reserved.</p>
    </footer>

    <script>
    function validateForm() {
        let mobile = document.getElementById("mobile").value.trim();
        let mobileError = document.getElementById("mobileError");

        if (mobile !== "" && (mobile.length !== 10 || isNaN(mobile))) {
            mobileError.innerText = "Please enter a valid 10-digit mobile number.";
            return false;
        } else {
            mobileError.innerText = "";
        }
        return true;
    }
    </script>

</body>
</html>