<?php
// -----------------------------------------------------------------
// Voter registration is DISABLED: the electorate is pre-enrolled by
// the Election Office (see the seeded approved voters). Anyone landing
// here is redirected to the login portal.
// -----------------------------------------------------------------
header('Location: login.php');
exit();

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/app_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error_msg = "";
$success_msg = "";

// Check whether the user is on the initial registration form or the OTP verification screen
$current_step = isset($_SESSION['temp_registration']) ? 'otp_step' : 'form_step';

// Handle canceling / resetting registration state
if (isset($_GET['action']) && $_GET['action'] === 'cancel_otp') {
    unset($_SESSION['temp_registration'], $_SESSION['reg_otp'], $_SESSION['reg_otp_expiry']);
    header("Location: register.php");
    exit();
}

/**
 * Dispatch Live Email OTP via Gmail SMTP
 */
function send_live_email($to_email, $to_name, $otp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = app_config('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = app_config('SMTP_USERNAME');
        $mail->Password   = app_config('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) app_config('SMTP_PORT', 587);

        $mail->setFrom(
            app_config('SMTP_FROM', app_config('SMTP_USERNAME')),
            app_config('SMTP_FROM_NAME', 'Online Voting System')
        );
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = 'Your Citizen Voter Registration OTP';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px; max-width: 500px;'>
                <h3 style='color: blueviolet; margin-top: 0;'>Online Voting Verification</h3>
                <p>Hello <strong>" . htmlspecialchars($to_name) . "</strong>,</p>
                <p>Your One-Time Password (OTP) for completing citizen registration is:</p>
                <div style='font-size: 26px; font-weight: bold; letter-spacing: 6px; background: #f3f3f3; display: inline-block; padding: 10px 20px; border-radius: 6px; color: #222; margin: 10px 0;'>{$otp}</div>
                <p style='color: #777; font-size: 13px; margin-top: 15px;'>This OTP code is valid for 5 minutes. Do not share this code with anyone.</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// -------------------------------------------------------------------
// STEP 1: Process Form Input, Stage Uploads & Send Email OTP
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_btn'])) {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $id_number = strtoupper(trim($_POST['id_number'] ?? ''));
    $mobile    = trim($_POST['mobile'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $password  = $_POST['password'] ?? '';

    // Field Validations
    if (!preg_match("/^[0-9]{10}$/", $mobile)) {
        $error_msg = "Please enter a valid 10-digit mobile number.";
    } elseif (!preg_match("/^[A-Z]{3}[0-9]{7}$/", $id_number)) {
        $error_msg = "Invalid Voter ID (EPIC) format! Must be 3 uppercase letters followed by 7 digits (e.g., ABC1234567).";
    } elseif (strlen($password) < 6) {
        $error_msg = "Password must be at least 6 characters long.";
    } else {
        try {
            // Check for duplicate Email or Voter ID in SQLite
            $check_stmt = $pdo->prepare("SELECT id FROM voters WHERE email = ? OR voter_id_number = ? LIMIT 1");
            $check_stmt->execute([$email, $id_number]);

            if ($check_stmt->fetch()) {
                $error_msg = "A voter with this Email or Voter ID / EPIC is already registered.";
            } else {
                $target_dir = __DIR__ . "/images/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }

                // 1. Process Voter Photograph
                $unique_image = "default.png";
                if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $img_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed_img = ['jpg', 'jpeg', 'png', 'webp'];

                    if (!in_array($img_ext, $allowed_img)) {
                        throw new Exception("Invalid voter photo format. Only JPG, PNG, and WEBP are allowed.");
                    }
                    $unique_image = time() . '_photo_' . uniqid() . '.' . $img_ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $unique_image);
                }

                // 2. Process Citizenship Proof Document
                $unique_doc = "";
                if (!empty($_FILES['id_document']['name']) && $_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
                    $doc_ext = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));
                    $allowed_docs = ['jpg', 'jpeg', 'png', 'pdf'];

                    if (!in_array($doc_ext, $allowed_docs)) {
                        throw new Exception("Invalid document format. Only JPG, PNG, and PDF are allowed.");
                    }
                    $unique_doc = time() . '_doc_' . uniqid() . '.' . $doc_ext;
                    move_uploaded_file($_FILES['id_document']['tmp_name'], $target_dir . $unique_doc);
                }

                // 3. Generate 6-Digit Secure OTP
                $generated_otp = (string)random_int(100000, 999999);
                $_SESSION['reg_otp'] = $generated_otp;
                $_SESSION['reg_otp_expiry'] = time() + (5 * 60);

                // 4. Dispatch Live Email OTP
                send_live_email($email, $name, $generated_otp);

                // 5. Store pending citizen details in session
                $_SESSION['temp_registration'] = [
                    'name'            => $name,
                    'email'           => $email,
                    'id_number'       => $id_number,
                    'mobile'          => $mobile,
                    'address'         => $address,
                    'hashed_password' => password_hash($password, PASSWORD_DEFAULT),
                    'photo'           => $unique_image,
                    'document'        => $unique_doc
                ];

                header("Location: register.php");
                exit();
            }
        } catch (Exception $e) {
            $error_msg = "Error: " . $e->getMessage();
        }
    }
}

// -------------------------------------------------------------------
// STEP 2: Validate Email OTP, Insert Record & Proceed to Biometrics
// -------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp_btn'])) {
    $entered_otp = trim($_POST['otp'] ?? '');

    if (empty($entered_otp)) {
        $error_msg = "Please enter the 6-digit OTP code.";
    } elseif (!isset($_SESSION['reg_otp']) || !isset($_SESSION['reg_otp_expiry'])) {
        $error_msg = "OTP session expired. Please submit the form again.";
    } elseif (time() > $_SESSION['reg_otp_expiry']) {
        unset($_SESSION['temp_registration'], $_SESSION['reg_otp'], $_SESSION['reg_otp_expiry']);
        $error_msg = "OTP has expired. Please re-enter your details.";
    } elseif ($entered_otp !== (string)$_SESSION['reg_otp']) {
        $error_msg = "Invalid OTP code entered. Please check your email inbox and try again.";
    } else {
        try {
            $data = $_SESSION['temp_registration'];

            $insert_stmt = $pdo->prepare("
                INSERT INTO voters (fullname, email, voter_id_number, mobile, address, password, photo, document_proof, status, has_voted)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 0)
            ");
            
            $insert_stmt->execute([
                $data['name'],
                $data['email'],
                $data['id_number'],
                $data['mobile'],
                $data['address'],
                $data['hashed_password'],
                $data['photo'],
                $data['document']
            ]);

            // Retrieve the newly created voter ID for fingerprint binding
            $new_voter_id = (int)$pdo->lastInsertId();
            $_SESSION['temp_fp_voter_id'] = $new_voter_id;

            // Clear registration and OTP staging
            unset($_SESSION['temp_registration'], $_SESSION['reg_otp'], $_SESSION['reg_otp_expiry']);

            // Proceed directly to biometric fingerprint registration
            header("Location: register_fingerprint.php");
            exit();

        } catch (Exception $e) {
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
    <title>Voter Registration - Online Voting System</title>
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/app.css">
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
        .register-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 35px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            margin: 30px 0;
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
        .otp-input {
            letter-spacing: 8px;
            font-size: 24px;
            text-align: center;
            font-weight: bold;
        }
        footer {
            text-align: center;
            padding: 20px 0;
            color: #777;
            font-size: 14px;
            border-top: 1px solid #e9ecef;
            background-color: #fff;
        }
    </style>
    <link rel="stylesheet" href="css/ui.css">
</head>
<body>

    <div class="container-fluid header">
        <h3 class="m-0 font-weight-bold">Online Voting System</h3>
    </div>

    <main class="container">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-6">
                <div class="register-card">

                    <?php if ($current_step === 'form_step'): ?>
                        <!-- Step 1: Form Entry -->
                        <h4 class="text-center font-weight-bold mb-3">Citizen Voter Registration</h4>
                        <p class="text-center text-muted small mb-4">Indian Citizen Verification & Email OTP Validation</p>

                        <?php if (!empty($error_msg)): ?>
                            <div class="alert alert-danger py-2 text-center" role="alert">
                                <small><?= htmlspecialchars($error_msg); ?></small>
                            </div>
                        <?php endif; ?>

                        <form action="" method="POST" enctype="multipart/form-data">
                            <div class="form-group mb-3">
                                <label for="name"><strong>Full Name (As per Voter ID):</strong></label>
                                <input type="text" class="form-control" name="name" id="name" value="<?= htmlspecialchars($_POST['name'] ?? ''); ?>" placeholder="Enter full name" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group mb-3">
                                    <label for="email"><strong>Email Address:</strong></label>
                                    <input type="email" class="form-control" name="email" id="email" value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="name@example.com" required>
                                </div>
                                <div class="col-md-6 form-group mb-3">
                                    <label for="id_number"><strong>Voter ID / EPIC No:</strong></label>
                                    <input type="text" class="form-control" name="id_number" id="id_number" value="<?= htmlspecialchars($_POST['id_number'] ?? ''); ?>" placeholder="e.g. ABC1234567" maxlength="10" style="text-transform:uppercase" required>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="mobile"><strong>Mobile Number:</strong></label>
                                <input type="text" class="form-control" name="mobile" id="mobile" value="<?= htmlspecialchars($_POST['mobile'] ?? ''); ?>" placeholder="10-digit mobile number" maxlength="10" required>
                            </div>

                            <div class="form-group mb-3">
                                <label for="address"><strong>Permanent Residential Address:</strong></label>
                                <textarea class="form-control" name="address" id="address" rows="2" placeholder="Enter complete address" required><?= htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group mb-3">
                                <label for="password"><strong>Password:</strong></label>
                                <input type="password" class="form-control" name="password" id="password" placeholder="Create a password" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 form-group mb-4">
                                    <label for="image"><strong>Voter Photograph:</strong></label>
                                    <input type="file" class="form-control" name="image" id="image" accept="image/*" required>
                                </div>
                                <div class="col-md-6 form-group mb-4">
                                    <label for="id_document"><strong>Citizenship Proof (ID Copy):</strong></label>
                                    <input type="file" class="form-control" name="id_document" id="id_document" accept="image/*,.pdf" required>
                                </div>
                            </div>

                            <button type="submit" name="register_btn" class="btn btn-custom">Send Email OTP &rarr;</button>
                        </form>

                        <div class="text-center mt-3">
                            <p class="mb-1 text-muted small">Already registered? <a href="login.php" style="color: blueviolet; font-weight: bold;">Login here</a></p>
                            <a href="index.php" class="text-muted small">← Back to Portal</a>
                        </div>

                    <?php else: ?>
                        <!-- Step 2: Live Email OTP Screen -->
                        <h4 class="text-center font-weight-bold mb-2">Verify Security OTP</h4>
                        <p class="text-center text-muted small mb-3">
                            A verification code has been dispatched to your inbox <strong><?= htmlspecialchars($_SESSION['temp_registration']['email'] ?? ''); ?></strong>.
                        </p>

                        <?php if (!empty($error_msg)): ?>
                            <div class="alert alert-danger py-2 text-center" role="alert">
                                <small><?= htmlspecialchars($error_msg); ?></small>
                            </div>
                        <?php endif; ?>

                        <form action="" method="POST">
                            <div class="form-group mb-4">
                                <label for="otp" class="text-center d-block"><strong>Enter 6-Digit OTP:</strong></label>
                                <input type="text" class="form-control otp-input" name="otp" id="otp" maxlength="6" placeholder="000000" autofocus required>
                            </div>

                            <button type="submit" name="verify_otp_btn" class="btn btn-custom mb-2">Verify & Register Biometrics &rarr;</button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="register.php?action=cancel_otp" class="text-muted small">&larr; Re-enter Details / Resend</a>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p class="m-0">&copy; <?= date('Y'); ?> Online Voting System. All Rights Reserved.</p>
        </div>
    </footer>

</body>
</html>