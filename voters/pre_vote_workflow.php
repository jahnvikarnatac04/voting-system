<?php
session_start();
require_once __DIR__ . '/../db.php';

// Must be logged in
if (!isset($_SESSION['vid'])) {
    header("Location: ../login.php");
    exit();
}

// Fetch voter info for display
$vid = (int)$_SESSION['vid'];
try {
    $stmt = $pdo->prepare("SELECT fullname, status FROM voters WHERE id = ? LIMIT 1");
    $stmt->execute([$vid]);
    $voter = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$voter || strtolower(trim($voter['status'])) !== 'approved') {
        header("Location: ../login.php");
        exit();
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle workflow completion POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_workflow'])) {
    $_SESSION['pre_vote_workflow_completed'] = true;
    header('Location: dashboard.php');
    exit();
}

// Handle step transitions
$step = isset($_GET['step']) ? max(1, min(3, (int)$_GET['step'])) : 1;

// If they already completed the workflow this session, skip ahead
if (!empty($_SESSION['pre_vote_workflow_completed'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voter Declaration — Online Voting System</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/app.css">
    <style>

        body {
            background-color: var(--bg-light);
            font-family: Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
        }

        .workflow-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 40px 35px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
            margin: 30px auto;
            max-width: 700px;
            width: 100%;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
        }

        .step-dot {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            color: #fff;
            background: #dee2e6;
            transition: all 0.3s ease;
        }

        .step-dot.active {
            background: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(138, 43, 226, 0.25);
        }

        .step-dot.done {
            background: #28a745;
        }

        .step-line {
            width: 60px;
            height: 3px;
            background: #dee2e6;
            margin: 0 8px;
            transition: background 0.3s ease;
        }

        .step-line.done {
            background: #28a745;
        }

        .legal-text {
            text-align: left;
            font-size: 14.5px;
            line-height: 1.75;
            color: #333;
            max-height: 420px;
            overflow-y: auto;
            padding: 20px 24px;
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 8px;
        }

        .legal-text h5 {
            color: var(--primary-color);
            font-weight: bold;
            margin-top: 18px;
            margin-bottom: 8px;
        }

        .legal-text h5:first-child {
            margin-top: 0;
        }

        .legal-text ul {
            margin-bottom: 10px;
        }

        .legal-text li {
            margin-bottom: 4px;
        }

        .declaration-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 18px 22px;
            margin-top: 20px;
            text-align: left;
        }

        .declaration-box p {
            margin: 0;
            font-weight: 600;
            color: #856404;
            font-size: 14px;
        }

        .agree-check {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 18px;
            text-align: left;
        }

        .agree-check input[type="checkbox"] {
            margin-top: 4px;
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
        }

        .agree-check label {
            font-size: 14px;
            color: #555;
            cursor: pointer;
        }

        .btn-custom {
            background-color: var(--primary-color);
            color: #ffffff;
            font-weight: bold;
            padding: 12px 30px;
            border-radius: 6px;
            border: none;
            font-size: 15px;
        }

        .btn-proceed {
            background-color: #28a745;
            color: #ffffff;
            font-weight: bold;
            padding: 12px 30px;
            border-radius: 6px;
            border: none;
            font-size: 15px;
        }

        .btn-proceed:hover {
            background-color: #218838;
            color: #ffffff;
        }

        .btn-back {
            background-color: transparent;
            color: #666;
            border: 2px solid #ddd;
            font-weight: bold;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 15px;
        }

        .btn-back:hover {
            background-color: #f0f0f0;
            color: #333;
        }

        .voter-greeting {
            text-align: center;
            margin-bottom: 8px;
        }

        .voter-greeting h4 {
            font-weight: bold;
            color: #333;
        }

        .voter-greeting .badge {
            font-size: 12px;
        }

        .step-title {
            text-align: center;
            font-weight: bold;
            color: #444;
            margin-bottom: 5px;
        }

        .step-subtitle {
            text-align: center;
            color: #888;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .notice-icon {
            font-size: 48px;
            text-align: center;
            margin-bottom: 10px;
        }

        footer {
            text-align: center;
            padding: 20px 0;
            color: #777;
            font-size: 13px;
            border-top: 1px solid #e9ecef;
            background-color: #fff;
            margin-top: auto;
        }
    </style>
    <link rel="stylesheet" href="../css/ui.css">
</head>
<body>

    <div class="container-fluid header">
        <h3>Online Voting System — Voter Declaration</h3>
    </div>

    <main class="container mb-5">
        <div class="workflow-card">

            <!-- Greeting -->
            <div class="voter-greeting">
                <h4>Welcome, <?= htmlspecialchars($voter['fullname'] ?? 'Voter'); ?></h4>
                <span class="badge badge-success">Approved Citizen</span>
            </div>

            <!-- Step Indicator -->
            <div class="step-indicator mt-3">
                <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : ''; ?>">
                    <?= $step > 1 ? '✓' : '1'; ?>
                </div>
                <div class="step-line <?= $step > 1 ? 'done' : ''; ?>"></div>
                <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : ''; ?>">
                    <?= $step > 2 ? '✓' : '2'; ?>
                </div>
                <div class="step-line <?= $step > 2 ? 'done' : ''; ?>"></div>
                <div class="step-dot <?= $step >= 3 ? 'active' : ''; ?>">3</div>
            </div>

            <!-- ===================== STEP 1: Legal Disclaimer ===================== -->
            <?php if ($step === 1): ?>
                <div class="notice-icon">⚖️</div>
                <h5 class="step-title">Step 1 — Legal Disclaimer &amp; Voter Obligations</h5>
                <p class="step-subtitle">Please read the following legal terms carefully before proceeding.</p>

                <div class="legal-text">
                    <h5>Constitutional Basis</h5>
                    <p>
                        This Online Voting System is operated under the authority of the Election Commission of India
                        and is governed by the provisions of the <strong>Representation of the People Act, 1951</strong>
                        and the <strong>Indian Penal Code, Sections 171A–171F</strong> relating to electoral offences.
                    </p>

                    <h5>Voter Eligibility</h5>
                    <p>By proceeding, you declare and affirm that:</p>
                    <ul>
                        <li>You are a citizen of India as defined under <strong>Article 5–8 of the Constitution of India</strong>.</li>
                        <li>You are at least <strong>18 years of age</strong> on the date of this election.</li>
                        <li>You are enrolled as a voter in the Parliamentary Constituency you have selected.</li>
                        <li>You are not disqualified from voting under any provision of law, including conviction for corrupt practices or election offences.</li>
                    </ul>

                    <h5>Prohibited Conduct</h5>
                    <p>Under Indian law, the following acts are electoral offences and are punishable by law:</p>
                    <ul>
                        <li><strong>Section 171A IPC</strong> — Bribery in connection with elections.</li>
                        <li><strong>Section 171B IPC</strong> — Undue influence at elections.</li>
                        <li><strong>Section 171C IPC</strong> — Interference with the free exercise of electoral right.</li>
                        <li><strong>Section 171G IPC</strong> — False statement in connection with an election.</li>
                        <li><strong>Section 171H IPC</strong> — Illegal payments in connection with an election.</li>
                        <li><strong>Section 171I IPC</strong> — Failure to keep election accounts.</li>
                    </ul>

                    <h5>One Person, One Vote</h5>
                    <p>
                        Each approved voter is entitled to cast <strong>exactly one (1) ballot</strong> per election.
                        Attempting to vote more than once, voting on behalf of another person, or impersonating
                        a voter is a criminal offence under the <strong>Representation of the People Act, 1951</strong>
                        and may result in disqualification and prosecution.
                    </p>

                    <h5>System Integrity</h5>
                    <p>
                        Any attempt to tamper with, manipulate, or gain unauthorised access to this voting system
                        is a punishable offence under the <strong>Information Technology Act, 2000</strong>
                        (Sections 43, 65, 66, and 72). All sessions are logged and auditable.
                    </p>
                </div>

                <div class="declaration-box">
                    <p>
                        📜 I solemnly affirm that I am a citizen of India, that I am eligible to vote in the
                        selected Parliamentary Constituency, and that I understand voting more than once or
                        impersonating a voter is a punishable offence under Indian law.
                    </p>
                </div>

                <div class="agree-check">
                    <input type="checkbox" id="legalAgree" onchange="document.getElementById('step1Next').disabled = !this.checked;">
                    <label for="legalAgree">
                        I have read, understood, and agree to the legal disclaimer and voter obligations stated above.
                    </label>
                </div>

                <div class="text-center mt-4">
                    <a href="dashboard.php" class="btn btn-back mr-2">← Back to Login</a>
                    <button id="step1Next" class="btn btn-custom" disabled onclick="window.location.href='pre_vote_workflow.php?step=2'">
                        I Agree — Continue →
                    </button>
                </div>

            <!-- ===================== STEP 2: Biometric Privacy Notice ===================== -->
            <?php elseif ($step === 2): ?>
                <div class="notice-icon">🔒</div>
                <h5 class="step-title">Step 2 — Biometric Data &amp; Privacy Notice</h5>
                <p class="step-subtitle">How your biometric information is collected, used, and protected.</p>

                <div class="legal-text">
                    <h5>Biometric Verification Purpose</h5>
                    <p>
                        This system uses <strong>facial recognition</strong> and/or <strong>fingerprint (WebAuthn/passkey)</strong>
                        verification to confirm your identity before casting your ballot. This is done to prevent
                        voter impersonation and ensure the integrity of the electoral process.
                    </p>

                    <h5>Data Collected</h5>
                    <p>During the biometric verification process, the following data may be collected:</p>
                    <ul>
                        <li><strong>Facial Descriptor:</strong> A 128-dimensional mathematical vector derived from your live camera capture and your registered profile photo. This vector cannot be reverse-engineered to reconstruct your face.</li>
                        <li><strong>Live Capture Snapshot:</strong> A small (160px) JPEG image of your live face capture, stored for audit purposes only.</li>
                        <li><strong>Fingerprint Credential:</strong> A WebAuthn public key and credential ID associated with your device's biometric sensor. Your actual fingerprint never leaves your device.</li>
                        <li><strong>Verification Logs:</strong> Timestamps, match scores, IP addresses, and pass/fail outcomes of each verification attempt.</li>
                    </ul>

                    <h5>Data Usage</h5>
                    <p>Your biometric data is used <strong>exclusively</strong> for:</p>
                    <ul>
                        <li>Verifying your identity before ballot submission.</li>
                        <li>Maintaining an auditable trail of verification attempts for election integrity.</li>
                        <li>Detecting and preventing fraudulent or multiple voting attempts.</li>
                    </ul>

                    <h5>Data Protection</h5>
                    <p>Your biometric data is protected under the <strong>Information Technology Act, 2000</strong> and the
                    <strong>Digital Personal Data Protection Act, 2023</strong>. Specifically:</p>
                    <ul>
                        <li>Data is stored on secure, access-controlled servers within India.</li>
                        <li>Data is <strong>not shared</strong> with any third party, political party, candidate, or government agency beyond the Election Commission.</li>
                        <li>Data is retained only for the duration of the election cycle and audit period, after which it is securely deleted.</li>
                        <li>You have the right to request deletion of your biometric data after the election concludes.</li>
                    </ul>

                    <h5>Consent</h5>
                    <p>
                        By proceeding, you provide informed consent for the collection and processing of your
                        biometric data as described above. You may choose <strong>fingerprint verification</strong>
                        instead of face verification if you prefer. Both methods are equally secure.
                    </p>
                </div>

                <div class="declaration-box">
                    <p>
                        🔐 I consent to the collection and processing of my biometric data (face descriptor and/or
                        fingerprint credential) solely for identity verification and election integrity purposes,
                        as described in this privacy notice.
                    </p>
                </div>

                <div class="agree-check">
                    <input type="checkbox" id="privacyAgree" onchange="document.getElementById('step2Next').disabled = !this.checked;">
                    <label for="privacyAgree">
                        I have read and understood the Biometric Data &amp; Privacy Notice, and I consent to the processing of my biometric data.
                    </label>
                </div>

                <div class="text-center mt-4">
                    <a href="pre_vote_workflow.php?step=1" class="btn btn-back mr-2">← Back</a>
                    <button id="step2Next" class="btn btn-custom" disabled onclick="window.location.href='pre_vote_workflow.php?step=3'">
                        I Consent — Continue →
                    </button>
                </div>

            <!-- ===================== STEP 3: Voting Instructions ===================== -->
            <?php elseif ($step === 3): ?>
                <div class="notice-icon">🗳️</div>
                <h5 class="step-title">Step 3 — Voting Instructions</h5>
                <p class="step-subtitle">How to cast your ballot securely and correctly.</p>

                <div class="legal-text">
                    <h5>How to Vote</h5>
                    <p>Follow these steps to cast your ballot:</p>
                    <ol>
                        <li><strong>Verify Your Identity</strong> — Before you can vote, you must complete biometric verification. You can choose either:
                            <ul>
                                <li><strong>Face Verification:</strong> Allow camera access and blink when prompted. Your live face is matched against your registered profile photo.</li>
                                <li><strong>Fingerprint Verification:</strong> Touch your fingerprint sensor (or use a paired phone/tablet) to verify via your registered WebAuthn passkey.</li>
                            </ul>
                        </li>
                        <li><strong>Review Candidates</strong> — On the Voter Dashboard, you will see all candidates contesting in your selected Parliamentary Constituency. Review their names, party affiliations, and symbols carefully.</li>
                        <li><strong>Cast Your Vote</strong> — Click the <strong>"Vote"</strong> button next to your chosen candidate. A confirmation prompt will appear — confirm your choice to submit your ballot.</li>
                        <li><strong>Vote Recorded</strong> — Once submitted, your vote is recorded and your ballot status changes to <strong>"Already Voted"</strong>. You cannot change or undo your vote.</li>
                    </ol>

                    <h5>Important Rules</h5>
                    <ul>
                        <li><strong>One Vote Only:</strong> You may cast exactly one ballot. Once voted, the Vote button is disabled and no further voting is possible.</li>
                        <li><strong>Biometric Required:</strong> You must complete biometric verification (face or fingerprint) each time you log in before you can vote. This is for your security.</li>
                        <li><strong>Secret Ballot:</strong> Your vote is anonymous. No one — not administrators, candidates, or election officials — can see who you voted for. The audit trail records only that you voted, not your choice.</li>
                        <li><strong>Session Timeout:</strong> For security, your session will expire after a period of inactivity. You will need to log in again.</li>
                        <li><strong>No Coercion:</strong> If you are being forced or pressured to vote a certain way, please contact the Election Commission helpline immediately.</li>
                    </ul>

                    <h5>After Voting</h5>
                    <p>
                        After casting your ballot, you will be returned to the Voter Dashboard where your ballot
                        status will show <strong>"Already Voted"</strong>. You may log out safely. Your participation
                        in this election has been securely recorded.
                    </p>

                    <h5>Need Help?</h5>
                    <p>
                        If you encounter any issues during the voting process — camera not working, fingerprint not
                        recognized, or any other technical difficulty — please contact the polling station help desk
                        or the Election Commission technical support helpline.
                    </p>
                </div>

                <div class="declaration-box">
                    <p>
                        ✅ I have read and understood the voting instructions. I am ready to proceed to the
                        Voter Dashboard and cast my ballot.
                    </p>
                </div>

                <div class="agree-check">
                    <input type="checkbox" id="instructionsAgree" onchange="document.getElementById('step3Proceed').disabled = !this.checked;">
                    <label for="instructionsAgree">
                        I understand the voting process and am ready to proceed.
                    </label>
                </div>

                <div class="text-center mt-4">
                    <a href="pre_vote_workflow.php?step=2" class="btn btn-back mr-2">← Back</a>
                    <button id="step3Proceed" class="btn btn-proceed" disabled onclick="completeWorkflow()">
                        ✅ I Understand — Proceed to Dashboard
                    </button>
                </div>

            <?php endif; ?>

        </div>
    </main>

    <footer>
        <p class="m-0">&copy; <?= date('Y'); ?> Online Voting System. All Rights Reserved.</p>
    </footer>

    <script>
    function completeWorkflow() {
        // Mark workflow as completed in session via a lightweight POST
        fetch('pre_vote_workflow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'complete_workflow=1'
        }).then(() => {
            window.location.href = 'dashboard.php';
        }).catch(() => {
            // Fallback: navigate anyway
            window.location.href = 'dashboard.php';
        });
    }
    </script>
</body>
</html>
