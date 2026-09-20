<?php
session_start();
require_once __DIR__ . '/../db.php';

// Authentication Check: ensure voter is logged in
if (!isset($_SESSION['vid'])) {
    header("Location: ../login.php");
    exit();
}

$vid = (int)$_SESSION['vid'];

// Fetch voter registration details
try {
    $stmt = $pdo->prepare("SELECT fullname, photo, status, has_voted FROM voters WHERE id = ? LIMIT 1");
    $stmt->execute([$vid]);
    $voter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voter) {
        header("Location: ../logout.php");
        exit();
    }

    $status = strtolower(trim($voter['status'] ?? 'pending'));
    if ($status !== 'approved') {
        header("Location: ../login.php");
        exit();
    }

    // If voter already voted, redirect back to dashboard
    $has_voted = ((int)($voter['has_voted'] ?? 0) === 1);
    if ($has_voted) {
        header("Location: dashboard.php");
        exit();
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Locate voter profile image
$imageName = trim($voter['photo'] ?? '');
$photoPath = "../images/default.png";
if (!empty($imageName) && file_exists(__DIR__ . "/../images/" . $imageName)) {
    $photoPath = "../images/" . $imageName;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Verification - Online Voting System</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/app.css">
    <!-- face-api.js is vendored locally so no internet is needed at runtime -->
    <script src="../js/face-api.min.js"></script>
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
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .verify-card {
            background: #ffffff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin: 30px auto;
        }

        .ref-photo {
            width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--primary-color);
            padding: 2px;
            display: block;
            margin: 0 auto 10px auto;
            background: #fff;
        }

        .video-wrap { position: relative; display: inline-block; margin: 15px auto; }
        #webcam {
            width: 100%;
            max-width: 420px;
            height: auto;
            border-radius: 8px;
            border: 2px solid #333;
            transform: scaleX(-1);
            background: #000;
        }
                                                 
        .liveness-tag {
            position: absolute;
            left: 50%;
            bottom: 12px;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.65);
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
            white-space: nowrap;
            pointer-events: none;
        }

        .btn-custom {
            background-color: var(--primary-color);
            color: #fff;
            font-weight: bold;
            padding: 10px 24px;
            border-radius: 6px;
            border: none;
        }

        .step-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: #dee2e6; margin: 0 3px; }
        .step-dot.active { background: var(--primary-color); }
        .step-dot.done { background: #28a745; }

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
        <h4 class="m-0 font-weight-bold">Online Voting System — Face Verification</h4>
    </header>

    <main class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <div class="verify-card text-center">
                    <h4 class="font-weight-bold mb-1">Voter Face Verification</h4>
                    <p class="text-muted small mb-2">Prove you are a real person: a short anti-spoof check happens before your face is matched to your registered photo.</p>

                    <div class="mb-3">
                        <img id="refImg" src="<?= htmlspecialchars($photoPath); ?>" alt="Registered Voter Photo" class="ref-photo">
                        <p class="small text-muted mb-0"><strong><?= htmlspecialchars($voter['fullname'] ?? 'Voter'); ?></strong> (Registered Photo)</p>
                    </div>

                    <div class="video-wrap d-none" id="videoWrap">
                        <video id="webcam" autoplay muted playsinline></video>
                        <div class="liveness-tag" id="livenessTag">Preparing…</div>
                    </div>

                    <div class="my-3">
                        <div id="statusAlert" class="alert alert-info py-2 d-inline-block px-4 mb-1">
                            Initializing AI facial recognition models…
                        </div>
                    </div>

                    <div class="mt-2">
                        <button id="verifyBtn" class="btn btn-success font-weight-bold px-4 py-2" disabled>Scan &amp; Verify Identity</button>
                        <a href="dashboard.php" class="btn btn-outline-secondary px-3 py-2 ml-2">Cancel</a>
                    </div>
                    <div class="mt-3 small">
                        <span class="step-dot" id="s1"></span> Models ready
                        <span class="step-dot" id="s2"></span> Liveness check (blink)
                        <span class="step-dot" id="s3"></span> Server match
                    </div>
                    <div class="mt-2">
                        <a href="verify_biometrics.php" class="text-muted small">No camera? Verify with fingerprint instead →</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <p class="m-0">&copy; <?= date('Y'); ?> Online Voting System. All Rights Reserved.</p>
    </footer>

<script>
(function () {
    'use strict';

    const video = document.getElementById('webcam');
    const videoWrap = document.getElementById('videoWrap');
    const refImg = document.getElementById('refImg');
    const statusAlert = document.getElementById('statusAlert');
    const verifyBtn = document.getElementById('verifyBtn');
    const livenessTag = document.getElementById('livenessTag');
    const steps = { s1: document.getElementById('s1'), s2: document.getElementById('s2'), s3: document.getElementById('s3') };

    let stream = null;
    let referenceDescriptor = null;
    let livenessToken = 0;             // invalidates the blink loop on stop
    let openBaseline = 0;              // calibrated "eyes open" EAR for this person

    const MIN_FACE_AREA = 150 * 150;   // face must be large enough in frame
    const BLINKS_NEEDED = 2;

    // ---- Adaptive blink detection -------------------------------------------------
    // EAR varies widely between people (≈0.20 narrow … ≈0.38 wide), with glasses,
    // lighting and camera angle, so FIXED cut-offs miss blinks (the old
    // closed<0.19 / open>0.24 pair never registered "open" for narrow eyes).
    // Instead: calibrate the person's own open-eye level, then use RELATIVE
    // hysteresis thresholds + closed-phase duration gating (50–700 ms).
    const CALIB_SAMPLES = 6;      // open-eye samples needed before counting starts
    const DROP_ABS      = 0.042;  // closed: EAR < baseline − abs
    const DROP_RATIO    = 0.75;   // closed: EAR < baseline × ratio
    const OPEN_ABS      = 0.022;  // open:   EAR > baseline − abs
    const OPEN_RATIO    = 0.87;   // open:   EAR > baseline × ratio
    const FALLBACK_OPEN = 0.19;   // pre-calibration fallback ("eyes open" cut)
    const BLINK_MIN_MS  = 50;     // shorter closure = sensor noise
    const BLINK_MAX_MS  = 700;    // longer closure = looking down / rubbing
    const RECAL_MS      = 9000;   // no blink progress for this long → recalibrate
    const RECAL_LIMIT   = 2;      // max auto-recalibrations per attempt

    function setStatus(msg, kind) {
        statusAlert.className = 'alert alert-' + (kind || 'info') + ' py-2 d-inline-block px-4 mb-1';
        statusAlert.innerText = msg;
    }
    function setStep(id, state) { steps[id].className = 'step-dot ' + (state || ''); }

    // Eye aspect ratio from the 68 facial landmarks.
    // Returns the better of the two eyes: with a tilted head one eye is
    // foreshortened, and the clearly visible one tracks blinks far better.
    function ear(pts) {
        const d = (a, b) => Math.hypot(pts[a].x - pts[b].x, pts[a].y - pts[b].y);
        const span = (a, b) => Math.max(d(a, b), 1e-6);
        const left  = (d(37, 41) + d(38, 40)) / (2 * span(36, 39));
        const right = (d(43, 47) + d(44, 46)) / (2 * span(42, 45));
        return Math.max(left, right);
    }

    function median(values) {
        if (!values.length) return 0;
        const s = [...values].sort((a, b) => a - b);
        const mid = s.length >> 1;
        return s.length % 2 ? s[mid] : (s[mid - 1] + s[mid]) / 2;
    }

    // Hysteresis classifier against the person's calibrated baseline.
    // 'hold' = dead zone: keep the previous state (rejects jitter).
    function classifyEye(v) {
        if (v < openBaseline - DROP_ABS && v < openBaseline * DROP_RATIO) return 'closed';
        if (v > openBaseline - OPEN_ABS || v > openBaseline * OPEN_RATIO) return 'open';
        return 'hold';
    }

    // Baseline-aware "eyes open" check (used when picking the capture frame).
    function isEyeOpenV(v) {
        if (openBaseline > 0) {
            return v > openBaseline - OPEN_ABS || v > openBaseline * OPEN_RATIO;
        }
        return v > FALLBACK_OPEN;
    }

    async function stopCamera() {
        if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        livenessToken++;   // invalidate any running blink-detection loop
        videoWrap.classList.add('d-none');
    }

    // ---------- 1. load models + extract reference descriptor ----------
    async function init() {
        try {
            setStatus('Loading facial recognition models…');
            const MODEL_URL = '../models';
            await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
            setStep('s1', 'done');

            setStatus('Analyzing registered photo…');
            if (!refImg.complete || refImg.naturalWidth === 0) {
                await new Promise((resolve) => { refImg.onload = resolve; refImg.onerror = resolve; });
            }

            const refDetection = await faceapi.detectSingleFace(refImg).withFaceLandmarks().withFaceDescriptor();
            if (!refDetection) {
                setStatus('No face found in your registered profile photo. Please contact support or use fingerprint verification.', 'warning');
                return;
            }
            referenceDescriptor = Array.from(refDetection.descriptor);
            setStatus('Ready. Press “Scan & Verify” and follow the on-screen steps.', 'success');
            verifyBtn.disabled = false;
        } catch (err) {
            setStatus('Error initializing face verification: ' + err.message, 'danger');
        }
    }

    // ---------- 2. live liveness loop (adaptive blink detection) ----------
    function startLivenessLoop() {
        const token = ++livenessToken;
        let eyeState = 'unknown';        // 'open' | 'closed' | 'unknown'
        let calib = [];
        let closedAt = 0;
        let blinkCount = 0;
        let lastBlinkAt = 0;
        let lastFaceAt = 0;
        let watchingSince = 0;
        let recalibrations = 0;
        let flashUntil = 0;
        let tipsUntil = 0;
        let done = false;
        const watchStart = performance.now();

        openBaseline = 0;                // fresh calibration for every attempt
        livenessTag.innerText = 'Calibrating — open your eyes and look straight at the camera…';
        setStep('s2', 'active');

        async function tick() {
            if (done || !referenceDescriptor || token !== livenessToken) return;
            const t0 = performance.now();
            try {
                // Smaller inference size ⇒ much faster loop ⇒ blinks can't slip between samples.
                const det = await faceapi
                    .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ inputSize: 288, scoreThreshold: 0.35 }))
                    .withFaceLandmarks();
                if (done || token !== livenessToken) return;
                const now = performance.now();

                if (!det) {
                    if (now - (lastFaceAt || watchStart) > 1200) {
                        livenessTag.innerText = 'No face detected — center yourself in the frame';
                    }
                } else if (det.detection.box.width * det.detection.box.height < MIN_FACE_AREA) {
                    livenessTag.innerText = 'Move a little closer to the camera';
                } else {
                    lastFaceAt = now;
                    const v = ear(det.landmarks.positions);

                    if (openBaseline <= 0) {
                        // --- calibrate this person's open-eye level ---
                        calib.push(v);
                        const m = median(calib);
                        if (calib.length >= CALIB_SAMPLES && m > 0.13) {
                            openBaseline = m;
                            watchingSince = now;
                        } else if (calib.length >= 30) {
                            openBaseline = median(calib.slice(-12)) || v; // unusual eyes: use what we have
                            watchingSince = now;
                        }
                        if (openBaseline <= 0) {
                            livenessTag.innerText = 'Calibrating — open your eyes, face the camera…';
                        }
                    } else {
                        // --- blink state machine: hysteresis + duration gate ---
                        let st = classifyEye(v);
                        if (st === 'hold') st = eyeState;
                        if (st !== eyeState) {
                            if (st === 'closed') {
                                closedAt = now;
                            } else if (st === 'open' && eyeState === 'closed') {
                                const dur = now - closedAt;
                                if (dur >= BLINK_MIN_MS && dur <= BLINK_MAX_MS && blinkCount < BLINKS_NEEDED) {
                                    blinkCount++;
                                    lastBlinkAt = now;
                                    flashUntil = now + 900;
                                }
                            }
                            eyeState = st;
                        }

                        // baseline gently follows the true open-eye level (drift/lighting)
                        if (v > openBaseline - 0.015) openBaseline = openBaseline * 0.97 + v * 0.03;

                        // stuck? auto-recalibrate (bad calibration, glasses glare, lighting shift)
                        const progressAgo = now - (lastBlinkAt || watchingSince);
                        if (blinkCount < BLINKS_NEEDED && progressAgo > RECAL_MS) {
                            if (recalibrations < RECAL_LIMIT) {
                                recalibrations++;
                                openBaseline = 0; calib = []; eyeState = 'unknown';
                                livenessTag.innerText = 'Re-calibrating — look straight at the camera…';
                            } else if (now > tipsUntil) {
                                tipsUntil = now + 4000;
                                livenessTag.innerText = 'Trouble detecting blinks — try better lighting, remove glasses, or move closer';
                            }
                        }

                        if (blinkCount >= BLINKS_NEEDED) {
                            done = true;
                            livenessTag.innerText = '✓ Liveness confirmed — capturing…';
                            captureAndVerify();
                            return;
                        }

                        if (now > tipsUntil) {
                            const dots = '●'.repeat(blinkCount) + '○'.repeat(BLINKS_NEEDED - blinkCount);
                            livenessTag.innerText = (now < flashUntil)
                                ? '👍 Blink ' + blinkCount + ' registered! ' + dots
                                : 'Blink naturally — ' + (BLINKS_NEEDED - blinkCount) + ' more  ' + dots;
                        }
                    }
                }
            } catch (e) {
                // transient detection errors are ignored
            }
            const dt = performance.now() - t0;
            setTimeout(tick, Math.max(15, 70 - dt)); // self-paced: ~70ms sampling, faster when inference allows
        }

        tick();
    }

    // ---------- 3. capture a clean single-face frame + verify on server ----------
    async function captureAndVerify() {
        setStatus('Capturing your live photo…', 'primary');
        try {
            // Draw several frames and keep the first with exactly one face, eyes open
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth; canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');

            let chosen = null;
            for (let attempt = 0; attempt < 12; attempt++) {
                ctx.drawImage(video, 0, 0);
                const results = await faceapi.detectAllFaces(canvas).withFaceLandmarks().withFaceDescriptors();

                if (results.length === 0) {
                    livenessTag.innerText = 'Keep your face in view…';
                } else if (results.length > 1) {
                    livenessTag.innerText = 'Only one person may be in frame';
                } else {
                    const r = results[0];
                    const area = r.detection.box.width * r.detection.box.height;
                    if (area < MIN_FACE_AREA || !isEyeOpenV(ear(r.landmarks.positions))) {
                        livenessTag.innerText = 'Keep eyes open and face centered…';
                    } else {
                        chosen = r;
                        break;
                    }
                }
                await new Promise((res) => setTimeout(res, 150));
            }

            if (!chosen) {
                throw new Error('Could not capture a clear single face. Please retry in good lighting.');
            }

            const liveDescriptor = Array.from(chosen.descriptor);

            // small audit snapshot (160px jpeg)
            const snapCanvas = document.createElement('canvas');
            const scale = 160 / canvas.width;
            snapCanvas.width = 160; snapCanvas.height = Math.round(canvas.height * scale);
            snapCanvas.getContext('2d').drawImage(canvas, 0, 0, snapCanvas.width, snapCanvas.height);
            const snapshot = snapCanvas.toDataURL('image/jpeg', 0.65);

            setStatus('Submitting to server for verification…', 'info');
            setStep('s3', 'active');

            const begin = await fetch('face_verify_api.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'begin' })
            }).then(r => r.json());
            if (!begin.success) throw new Error(begin.message || 'Could not start verification.');

            const res = await fetch('face_verify_api.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'match',
                    nonce: begin.nonce,
                    live_descriptor: liveDescriptor,
                    ref_descriptor: referenceDescriptor,
                    snapshot: snapshot
                })
            }).then(r => r.json());
            if (!res.success) throw new Error(res.message || 'Server rejected the attempt.');

            if (res.matched) {
                setStep('s3', 'done');
                setStatus('✅ Identity verified! Match score ' + res.distance.toFixed(3) + '. Redirecting…', 'success');
                await stopCamera();
                setTimeout(() => { window.location.href = res.redirect || 'dashboard.php'; }, 1200);
            } else {
                setStatus('❌ Face did not match closely enough (score ' + res.distance.toFixed(3) + ' / threshold ' + res.threshold + '). Please retry or use fingerprint verification.', 'warning');
                setStep('s3', '');
                setStep('s2', '');
                verifyBtn.disabled = false;
                stopCamera();
            }
        } catch (err) {
            setStatus('Verification failed: ' + err.message, 'danger');
            setStep('s2', '');
            setStep('s3', '');
            verifyBtn.disabled = false;
            stopCamera();
        }
    }

    // ---------- start flow ----------
    verifyBtn.addEventListener('click', async () => {
        if (!referenceDescriptor) return;
        verifyBtn.disabled = true;
        try {
            if (!navigator.mediaDevices || !window.isSecureContext) {
                throw new Error('Camera access needs localhost or HTTPS.');
            }
            setStatus('Accessing webcam…', 'info');
            stream = await navigator.mediaDevices.getUserMedia({
                video: { width: 640, height: 480 }
            });
            video.srcObject = stream;
            videoWrap.classList.remove('d-none');
            await new Promise((res) => { video.onloadedmetadata = res; });
            await video.play();
            setStatus('Liveness check: blink ' + BLINKS_NEEDED + ' times when prompted.', 'primary');
            startLivenessLoop();
        } catch (err) {
            setStatus('Could not start the camera: ' + err.message, 'danger');
            verifyBtn.disabled = false;
        }
    });

    window.addEventListener('beforeunload', stopCamera);
    window.addEventListener('load', init);
})();
</script>
</body>
</html>
