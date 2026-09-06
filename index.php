<?php
require_once __DIR__ . '/db.php';
session_start();

// Handle setting the Lok Sabha Constituency
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_constituency'])) {
    $selected_state = trim($_POST['state'] ?? '');
    $selected_const = trim($_POST['constituency'] ?? '');

    if (!empty($selected_state) && !empty($selected_const)) {
        $_SESSION['selected_state'] = $selected_state;
        $_SESSION['selected_constituency'] = $selected_const;
    }
}

// Changing constituency
if (isset($_GET['action']) && $_GET['action'] === 'change_constituency') {
    unset($_SESSION['selected_state']);
    unset($_SESSION['selected_constituency']);
    header("Location: index.php");
    exit();
}

$has_constituency = !empty($_SESSION['selected_constituency']);
$selected_state = $_SESSION['selected_state'] ?? '';
$selected_constituency = $_SESSION['selected_constituency'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Voting System - Lok Sabha Portal</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css">
    
    <style>
        :root {
            --primary-color: blueviolet;
            --primary-hover: #701eb8;
            --bg-light: #f8f9fa;
        }

        body {
            background-color: var(--bg-light);
            font-family: Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            margin: 0;
            scroll-behavior: smooth;
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

        .header h3 {
            margin: 0;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .hero-section {
            text-align: center;
            margin-top: 35px;
            margin-bottom: 25px;
        }

        .hero-section h2 {
            font-weight: bold;
            color: #333333;
            margin-bottom: 10px;
        }

        .hero-section p {
            color: #666666;
            font-size: 1.05rem;
        }

        .portal-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 35px 30px;
            text-align: center;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .portal-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(138, 43, 226, 0.12);
        }

        .portal-card h4 {
            font-weight: bold;
            color: #333333;
            margin-bottom: 15px;
        }

        .portal-card p {
            color: #666666;
            font-size: 14.5px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .constituency-badge {
            background-color: #ede7f6;
            color: var(--primary-color);
            padding: 7px 16px;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
            border: 1px solid #d1c4e9;
        }

        .btn-custom {
            background-color: var(--primary-color);
            color: #ffffff;
            font-weight: bold;
            padding: 11px;
            border-radius: 6px;
            border: none;
            text-decoration: none;
            display: block;
            width: 100%;
        }

        .btn-custom:hover {
            background-color: var(--primary-hover);
            color: #ffffff;
            text-decoration: none;
        }

        .btn-outline-custom {
            background-color: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            font-weight: bold;
            padding: 10px;
            border-radius: 6px;
            text-decoration: none;
            display: block;
            width: 100%;
        }

        .btn-outline-custom:hover {
            background-color: var(--primary-color);
            color: #ffffff;
            text-decoration: none;
        }

        /* Discreet Admin Section */
        .discreet-admin-section {
            margin-top: 100px;
            padding: 40px 20px 20px 20px;
            border-top: 1px dashed #d6d8db;
            text-align: center;
        }

        .admin-box {
            background-color: #f1f3f5;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            max-width: 420px;
            margin: 0 auto;
        }

        .btn-admin {
            background-color: #495057;
            color: #ffffff;
            font-weight: bold;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-admin:hover {
            background-color: #212529;
            color: #ffffff;
            text-decoration: none;
        }

        footer {
            text-align: center;
            padding: 20px 0;
            color: #777777;
            font-size: 13px;
            border-top: 1px solid #e9ecef;
            background-color: #ffffff;
        }

        .admin-link {
            color: #adb5bd;
            text-decoration: none;
            font-size: 12px;
        }

        .admin-link:hover {
            color: #6c757d;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <div class="container-fluid header">
        <h3>Online Voting System — National Portal</h3>
    </div>

    <main class="container mb-5">
        
        <?php if (!$has_constituency): ?>
            <!-- Step 1: Lok Sabha Constituency Selection Screen -->
            <div class="hero-section">
                <h2>Select Electoral Constituency</h2>
                <p>Choose your State / Union Territory and Parliamentary (Lok Sabha) Division</p>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="portal-card">
                        <form action="index.php" method="POST">
                            
                            <!-- 1. State / UT Selection -->
                            <div class="form-group text-left mb-3">
                                <label for="stateSelect" class="text-dark font-weight-bold">State / Union Territory:</label>
                                <select class="form-control" name="state" id="stateSelect" required onchange="populateConstituencies()">
                                    <option value="" disabled selected>-- Select State / UT --</option>
                                    
                                    <optgroup label="States (28)">
                                        <option value="Andhra Pradesh">Andhra Pradesh</option>
                                        <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                                        <option value="Assam">Assam</option>
                                        <option value="Bihar">Bihar</option>
                                        <option value="Chhattisgarh">Chhattisgarh</option>
                                        <option value="Goa">Goa</option>
                                        <option value="Gujarat">Gujarat</option>
                                        <option value="Haryana">Haryana</option>
                                        <option value="Himachal Pradesh">Himachal Pradesh</option>
                                        <option value="Jharkhand">Jharkhand</option>
                                        <option value="Karnataka">Karnataka</option>
                                        <option value="Kerala">Kerala</option>
                                        <option value="Madhya Pradesh">Madhya Pradesh</option>
                                        <option value="Maharashtra">Maharashtra</option>
                                        <option value="Manipur">Manipur</option>
                                        <option value="Meghalaya">Meghalaya</option>
                                        <option value="Mizoram">Mizoram</option>
                                        <option value="Nagaland">Nagaland</option>
                                        <option value="Odisha">Odisha</option>
                                        <option value="Punjab">Punjab</option>
                                        <option value="Rajasthan">Rajasthan</option>
                                        <option value="Sikkim">Sikkim</option>
                                        <option value="Tamil Nadu">Tamil Nadu</option>
                                        <option value="Telangana">Telangana</option>
                                        <option value="Tripura">Tripura</option>
                                        <option value="Uttar Pradesh">Uttar Pradesh</option>
                                        <option value="Uttarakhand">Uttarakhand</option>
                                        <option value="West Bengal">West Bengal</option>
                                    </optgroup>

                                    <optgroup label="Union Territories (8)">
                                        <option value="Andaman and Nicobar Islands">Andaman and Nicobar Islands</option>
                                        <option value="Chandigarh">Chandigarh</option>
                                        <option value="Dadra and Nagar Haveli and Daman and Diu">Dadra and Nagar Haveli and Daman and Diu</option>
                                        <option value="Delhi (NCT)">Delhi (NCT)</option>
                                        <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                                        <option value="Ladakh">Ladakh</option>
                                        <option value="Lakshadweep">Lakshadweep</option>
                                        <option value="Puducherry">Puducherry</option>
                                    </optgroup>
                                </select>
                            </div>

                            <!-- 2. Parliamentary Constituency Selection -->
                            <div class="form-group text-left mb-4">
                                <label for="constituencySelect" class="text-dark font-weight-bold">Parliamentary Constituency (Lok Sabha):</label>
                                <select class="form-control" name="constituency" id="constituencySelect" required disabled>
                                    <option value="" disabled selected>-- Select State / UT First --</option>
                                </select>
                            </div>

                            <button type="submit" name="set_constituency" class="btn btn-custom">
                                Proceed to Portal &rarr;
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Step 2: Centered Voter Portal -->
            <div class="hero-section">
                <h2>Welcome to the Online Voting Portal</h2>
                <p class="mb-2">Cast your vote securely using biometric face verification</p>
                <div class="mb-2">
                    <span class="constituency-badge">
                        <?= htmlspecialchars($selected_constituency); ?>, <?= htmlspecialchars($selected_state); ?>
                    </span>
                    <a href="index.php?action=change_constituency" class="btn btn-link btn-sm text-muted">(Change Region)</a>
                </div>
            </div>

            <!-- Single Focused Voter Card -->
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="portal-card">
                        <div>
                            <h4>Citizen Voter Portal</h4>
                            <p>Log in with your voter credentials and 2FA OTP to view certified Lok Sabha candidates and cast your ballot.</p>
                        </div>
                        <div class="mt-3">
                            <a href="login.php" class="btn btn-custom mb-3">Voter Login &rarr;</a>
                            <a href="register.php" class="btn btn-outline-custom">New Citizen Registration</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Discreet Admin Section (Visible by scrolling down) -->
            <div class="discreet-admin-section" id="admin-section">
                <div class="admin-box">
                    <p class="text-muted font-weight-bold mb-3">Only Admin Login</p>
                    <a href="admin/login.php" class="btn-admin">🔒 Administrator Login</a>
                </div>
            </div>

        <?php endif; ?>

    </main>

    <footer>
        <div class="container">
            <p class="m-0">&copy; <?= date('Y'); ?> Online Voting System. All Rights Reserved.</p>
            <?php if ($has_constituency): ?>
                <a href="#admin-section" class="admin-link mt-1 d-inline-block">Officer Access</a>
            <?php endif; ?>
        </div>
    </footer>

    <script>
    const lokSabhaData = {
        "Andhra Pradesh": ["Visakhapatnam (PC-04)", "Vijayawada (PC-12)", "Guntur (PC-13)", "Tirupati (PC-23)", "Kurnool (PC-19)"],
        "Arunachal Pradesh": ["Arunachal West (PC-01)", "Arunachal East (PC-02)"],
        "Assam": ["Guwahati (PC-07)", "Dibrugarh (PC-13)", "Silchar (PC-02)", "Kaziranga (PC-10)", "Barpeta (PC-03)"],
        "Bihar": ["Patna Sahib (PC-30)", "Pataliputra (PC-31)", "Gaya (PC-38)", "Muzaffarpur (PC-15)", "Bhagalpur (PC-26)", "Darbhanga (PC-14)"],
        "Chhattisgarh": ["Raipur (PC-08)", "Bilaspur (PC-05)", "Durg (PC-07)", "Bastar (PC-10)"],
        "Goa": ["North Goa (PC-01)", "South Goa (PC-02)"],
        "Gujarat": ["Gandhinagar (PC-06)", "Ahmedabad East (PC-07)", "Ahmedabad West (PC-08)", "Surat (PC-24)", "Vadodara (PC-20)", "Rajkot (PC-10)"],
        "Haryana": ["Gurgaon (PC-09)", "Faridabad (PC-10)", "Ambala (PC-01)", "Rohtak (PC-07)", "Karnal (PC-05)"],
        "Himachal Pradesh": ["Shimla (PC-04)", "Mandi (PC-02)", "Kangra (PC-01)", "Hamirpur (PC-03)"],
        "Jharkhand": ["Ranchi (PC-08)", "Jamshedpur (PC-09)", "Dhanbad (PC-07)", "Hazaribagh (PC-04)"],
        "Karnataka": ["Bengaluru South (PC-26)", "Bengaluru North (PC-24)", "Bengaluru Central (PC-25)", "Mysuru (PC-20)", "Dakshina Kannada (PC-17)", "Hubli-Dharwad (PC-10)"],
        "Kerala": ["Thiruvananthapuram (PC-20)", "Ernakulam (PC-12)", "Wayanad (PC-04)", "Kozhikode (PC-05)", "Thrissur (PC-10)"],
        "Madhya Pradesh": ["Bhopal (PC-19)", "Indore (PC-26)", "Gwalior (PC-03)", "Jabalpur (PC-13)", "Ujjain (PC-22)"],
        "Maharashtra": ["Mumbai South (PC-31)", "Mumbai North (PC-24)", "Pune (PC-34)", "Nagpur (PC-10)", "Thane (PC-25)", "Nashik (PC-20)", "Aurangabad (PC-19)"],
        "Manipur": ["Inner Manipur (PC-01)", "Outer Manipur (PC-02)"],
        "Meghalaya": ["Shillong (PC-01)", "Tura (PC-02)"],
        "Mizoram": ["Mizoram (PC-01)"],
        "Nagaland": ["Nagaland (PC-01)"],
        "Odisha": ["Bhubaneswar (PC-17)", "Puri (PC-18)", "Cuttack (PC-14)", "Sambalpur (PC-03)", "Berhampur (PC-20)"],
        "Punjab": ["Amritsar (PC-02)", "Ludhiana (PC-07)", "Jalandhar (PC-04)", "Patiala (PC-13)", "Bathinda (PC-11)", "Gurdaspur (PC-01)", "Khadoor Sahib (PC-03)", "Hoshiarpur (PC-05)", "Anandpur Sahib (PC-06)", "Fatehgarh Sahib (PC-08)", "Faridkot (PC-09)", "Firozpur (PC-10)", "Sangrur (PC-12)"],
        "Rajasthan": ["Jaipur (PC-07)", "Jodhpur (PC-13)", "Udaipur (PC-19)", "Kota (PC-24)", "Bikaner (PC-02)", "Ajmer (PC-12)"],
        "Sikkim": ["Sikkim (PC-01)"],
        "Tamil Nadu": ["Chennai Central (PC-04)", "Chennai South (PC-03)", "Chennai North (PC-02)", "Coimbatore (PC-20)", "Madurai (PC-32)", "Sriperumbudur (PC-05)"],
        "Telangana": ["Hyderabad (PC-09)", "Secunderabad (PC-08)", "Chevella (PC-10)", "Malkajgiri (PC-07)", "Warangal (PC-15)"],
        "Tripura": ["Tripura West (PC-01)", "Tripura East (PC-02)"],
        "Uttar Pradesh": ["Gautam Buddha Nagar (PC-13)", "Varanasi (PC-77)", "Rae Bareli (PC-36)", "Lucknow (PC-35)", "Amethi (PC-37)", "Gorakhpur (PC-64)", "Agra (PC-18)", "Kanpur (PC-43)", "Prayagraj (PC-52)"],
        "Uttarakhand": ["Haridwar (PC-05)", "Tehri Garhwal (PC-01)", "Garhwal (PC-02)", "Almora (PC-03)", "Nainital-Udhamsingh Nagar (PC-04)"],
        "West Bengal": ["Kolkata Dakshin (PC-23)", "Kolkata Uttar (PC-24)", "Darjeeling (PC-04)", "Howrah (PC-25)", "Asansol (PC-40)", "Diamond Harbour (PC-21)"],

        "Andaman and Nicobar Islands": ["Andaman and Nicobar Islands (PC-01)"],
        "Chandigarh": ["Chandigarh (PC-01)"],
        "Dadra and Nagar Haveli and Daman and Diu": ["Dadra and Nagar Haveli (PC-01)", "Daman and Diu (PC-02)"],
        "Delhi (NCT)": ["New Delhi (PC-04)", "Chandni Chowk (PC-01)", "East Delhi (PC-03)", "South Delhi (PC-07)", "North East Delhi (PC-02)", "North West Delhi (PC-05)", "West Delhi (PC-06)"],
        "Jammu and Kashmir": ["Srinagar (PC-02)", "Jammu (PC-05)", "Anantnag-Rajouri (PC-03)", "Baramulla (PC-01)", "Udhampur (PC-04)"],
        "Ladakh": ["Ladakh (PC-01)"],
        "Lakshadweep": ["Lakshadweep (PC-01)"],
        "Puducherry": ["Puducherry (PC-01)"]
    };

    function populateConstituencies() {
        const stateSelect = document.getElementById("stateSelect");
        const constSelect = document.getElementById("constituencySelect");
        const selectedState = stateSelect.value;

        constSelect.innerHTML = '<option value="" disabled selected>-- Select Constituency --</option>';

        if (selectedState && lokSabhaData[selectedState]) {
            lokSabhaData[selectedState].forEach(function(item) {
                const opt = document.createElement("option");
                opt.value = item;
                opt.textContent = item;
                constSelect.appendChild(opt);
            });
            constSelect.disabled = false;
        } else {
            constSelect.disabled = true;
        }
    }
    </script>
</body>
</html>