<?php
session_start();
require_once __DIR__ . '/../db.php';

// Redirect to login if voter is not authenticated
if (!isset($_SESSION['vid'])) {
    header("Location: ../login.php");
    exit();
}

$vid = (int)$_SESSION['vid'];
$error_msg = "";
$success_msg = "";

// Fetch current voter details using SQLite PDO
try {
    $stmt = $pdo->prepare("SELECT * FROM voters WHERE id = ? LIMIT 1");
    $stmt->execute([$vid]);
    $voter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voter) {
        header("Location: ../logout.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle profile update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $mobile  = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($name) || empty($email)) {
        $error_msg = "Name and email fields cannot be empty.";
    } elseif (!empty($mobile) && (!is_numeric($mobile) || strlen($mobile) !== 10)) {
        $error_msg = "Please enter a valid 10-digit mobile number.";
    } else {
        try {
            $photo_name = $voter['photo'] ?? $voter['image'] ?? 'default.png';

            // Check if a new image was uploaded
            if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp'];

                if (!in_array($ext, $allowed)) {
                    throw new Exception("Invalid image format. Only JPG, PNG, and WEBP are allowed.");
                }

                $photo_name = time() . '_voter_' . uniqid() . '.' . $ext;
                $target_dir = __DIR__ . "/../images/";

                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0755, true);
                }

                move_uploaded_file($_FILES['image']['tmp_name'], $target_dir . $photo_name);

                // Require re-verification with the new photo
                unset($_SESSION['face_verified']);
            }

            // Update voter record via parameterized PDO query
            $update_stmt = $pdo->prepare("
                UPDATE voters 
                SET fullname = ?, email = ?, photo = ? 
                WHERE id = ?
            ");
            $update_stmt->execute([$name, $email, $photo_name, $vid]);

            // Optional update for auxiliary columns if they exist
            try {
                $aux_stmt = $pdo->prepare("UPDATE voters SET mobile = ?, address = ? WHERE id = ?");
                $aux_stmt->execute([$mobile, $address, $vid]);
            } catch (Exception $ignored) {}

            echo '<script>
                alert("Profile updated successfully!");
                window.location.href = "dashboard.php";
            </script>';
            exit();

        } catch (Exception $e) {
            $error_msg = "Failed to update profile: " . $e->getMessage();
        }
    }
}

// Locate current profile photo
$current_image = $voter['photo'] ?? $voter['image'] ?? '';
$photoPath = (!empty($current_image) && file_exists(__DIR__ . "/../images/" . $current_image))
             ? "../images/" . htmlspecialchars($current_image)
             : "../images/default.png";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Voter Dashboard</title>

    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/app.css">
    
    <style>

        .header {
            background-color: blueviolet; 
            color: #ffffff;
            height: 9vh;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .card-custom {
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            padding: 30px;
            margin-top: 30px;
            margin-bottom: 40px;
        }

        .current-img-preview {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid blueviolet;
            padding: 2px;
            display: block;
            margin-bottom: 10px;
        }

        .btn-custom {
            background-color: blueviolet;
            color: white;
            font-weight: bold;
            border: none;
        }
    </style>
    <link rel="stylesheet" href="../css/ui.css">
</head>
<body>

    <div class="container-fluid header">
        <h3 class="m-0 font-weight-bold">Online Voting System</h3>
    </div>

    <!-- Main Form Container -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card-custom">
                    <h4 class="text-center mb-4 font-weight-bold">Edit Profile</h4>

                    <?php if (!empty($error_msg)): ?>
                        <div class="alert alert-danger py-2 text-center" role="alert">
                            <small><?= htmlspecialchars($error_msg); ?></small>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                        
                        <div class="form-group mb-3">
                            <label for="name"><strong>Full Name:</strong></label>
                            <input type="text" class="form-control" name="name" id="name" 
                                   value="<?= htmlspecialchars($voter['fullname'] ?? $voter['name'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="email"><strong>Email Address:</strong></label>
                            <input type="email" class="form-control" name="email" id="email" 
                                   value="<?= htmlspecialchars($voter['email'] ?? ''); ?>" required>
                        </div>

                        <div class="form-group mb-3">
                            <label for="voter_id"><strong>Voter ID (EPIC):</strong></label>
                            <input type="text" class="form-control" id="voter_id" value="<?= htmlspecialchars($voter['voter_id_number'] ?? $voter['id_number'] ?? 'N/A'); ?>" disabled>
                            <small class="text-muted">Voter ID numbers cannot be modified.</small>
                        </div>

                        <div class="form-group mb-3">
                            <label for="mobile"><strong>Mobile Number:</strong></label>
                            <input type="text" class="form-control" name="mobile" id="mobile" 
                                   value="<?= htmlspecialchars($voter['mobile'] ?? ''); ?>" maxlength="10">
                            <small id="mobileError" class="text-danger"></small>
                        </div>

                        <div class="form-group mb-3">
                            <label for="address"><strong>Address:</strong></label>
                            <textarea name="address" id="address" class="form-control" rows="2"><?= htmlspecialchars($voter['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group mb-4">
                            <label><strong>Current Photo:</strong></label>
                            <img src="<?= htmlspecialchars($photoPath); ?>" alt="Current Voter Photo" class="current-img-preview" onerror="this.src='../images/default.png';">
                            
                            <label for="image" class="mt-2 text-muted small">Upload New Photo (optional):</label>
                            <input type="file" class="form-control" name="image" id="image" accept="image/*">
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" name="update_profile" class="btn btn-custom px-4">Update Profile</button>
                            <a href="dashboard.php" class="btn btn-secondary px-4">Cancel</a>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-Side Form Validation -->
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