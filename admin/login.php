<?php
session_start();
require_once __DIR__ . '/../db.php';

// If already logged in, redirect directly to admin dashboard
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_logged_in'])) {
    header("Location: dashboard.php");
    exit();
}

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_btn'])) {
    $identifier = trim($_POST['identifier'] ?? $_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error_msg = "Please fill in all fields.";
    } else {
        try {
            // Match against either username or email
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$identifier, $identifier]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin) {
                // Verify password against standard password_hash or fallback plaintext
                $password_matches = password_verify($password, $admin['password']) || ($password === $admin['password']);

                if ($password_matches) {
                    // Prevent session fixation
                    session_regenerate_id(true);

                    // Set multi-admin session parameters
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id']        = (int)($admin['id'] ?? $admin['aid'] ?? 1);
                    $_SESSION['admin_name']      = $admin['name'] ?? $admin['username'] ?? 'Administrator';
                    $_SESSION['admin_username']  = $admin['username'] ?? '';
                    $_SESSION['admin_email']     = $admin['email'] ?? $identifier;
                    $_SESSION['admin_role']      = $admin['role'] ?? 'admin'; // 'super_admin' or 'admin'

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error_msg = "Invalid username/email or password.";
                }
            } else {
                $error_msg = "Invalid username/email or password.";
            }
        } catch (PDOException $e) {
            $error_msg = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Online Voting System</title>

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
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .login-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 35px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            margin-top: 50px;
            margin-bottom: 40px;
        }

        .btn-custom {
            background-color: var(--primary-color);
            color: #ffffff;
            font-weight: bold;
            padding: 10px;
            border-radius: 6px;
            border: none;
            width: 100%;
        }

        footer {
            text-align: center;
            padding: 20px 0;
            color: #777777;
            font-size: 14px;
            border-top: 1px solid #e9ecef;
            background-color: #ffffff;
        }
    </style>
    <link rel="stylesheet" href="../css/ui.css">
</head>
<body>

    <!-- Header -->
    <div class="container-fluid header">
        <h3 class="m-0 font-weight-bold">Online Voting System — Admin Portal</h3>
    </div>

    <!-- Login Form -->
    <main class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="login-card">
                    <h4 class="text-center font-weight-bold mb-4">Admin Login</h4>

                    <?php if (!empty($error_msg)): ?>
                        <div class="alert alert-danger py-2 text-center" role="alert">
                            <small><?= htmlspecialchars($error_msg); ?></small>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="form-group mb-3">
                            <label for="identifier"><strong>Username / Email:</strong></label>
                            <input type="text" class="form-control" name="identifier" id="identifier" 
                                   value="<?= htmlspecialchars($_POST['identifier'] ?? $_POST['email'] ?? ''); ?>" 
                                   placeholder="Enter username or email" required autocomplete="username">
                        </div>

                        <div class="form-group mb-4">
                            <label for="password"><strong>Password:</strong></label>
                            <input type="password" class="form-control" name="password" id="password" 
                                   placeholder="Enter password" required autocomplete="current-password">
                        </div>

                        <button type="submit" name="login_btn" class="btn btn-custom">Login to Dashboard</button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="../index.php" class="text-muted small">← Back to Voter Portal</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p class="m-0">&copy; <?= date('Y'); ?> Online Voting System. All Rights Reserved.</p>
        </div>
    </footer>

</body>
</html>