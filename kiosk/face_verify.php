<?php
/**
 * kiosk/face_verify.php
 * ---------------------------------------------------------------
 * The FACE half of booth verification.
 *
 * Reached from the kiosk's armed "Verify" step. It:
 *   1. loads the citizen's reference photo (an in-person booth capture
 *      when one exists, otherwise the registration photo),
 *   2. runs a blink-twice liveness challenge on a live camera stream
 *      (see js/blink-liveness.js — adaptive EAR, not a fixed cutoff),
 *   3. captures a clean single-face frame and posts both descriptors to
 *      kiosk/face_api.php, where the match is decided server-side.
 *
 * Passing the face check is only HALF of the ballot gate: the kiosk also
 * requires a fingerprint verification (see kiosk/_kiosk.php,
 * `kiosk_verified_voter()`). If the citizen has no usable reference photo,
 * the operator can capture one here — an in-person booth enrollment.
 * ---------------------------------------------------------------
 */

require_once __DIR__ . '/_kiosk.php';
require_once __DIR__ . '/../includes/i18n.php';
kiosk_require_unlocked();

$booth = kiosk_booth();

$target_id = kiosk_face_target_voter_id();
if ($target_id <= 0) {
    header('Location: index.php?mode=clear');
    exit();
}

$stmt = $pdo->prepare("SELECT id, fullname, status FROM voters WHERE id = ? LIMIT 1");
$stmt->execute([$target_id]);
$voter = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$voter) {
    header('Location: index.php?mode=clear');
    exit();
}

$reference_photo = kiosk_face_reference_photo($pdo, (int)$voter['id']);
$has_reference   = $reference_photo !== '';
$name            = $voter['fullname'] ?? ('Voter #' . $target_id);
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>"<?= i18n_is_rtl() ? ' dir="rtl"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= te('kiosk.face_title') ?><?= htmlspecialchars($booth['name']); ?></title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/app.css">
    <!-- face-api.js vendored locally; no internet needed at runtime -->
    <script src="../js/face-api.min.js"></script>
    <script src="../js/blink-liveness.js"></script>
    <style>
        body { background-color: #f8f9fc; font-family: Arial, sans-serif; padding-bottom: 40px; }
        .header {
            background-color: var(--primary-color); color: #fff;
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 18px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            position: sticky; top: 0; z-index: 20;
        }
        .face-card {
            background: #fff; border: 1px solid #e0e0e0; border-radius: 12px;
            padding: 22px; margin-top: 22px; box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            border-top: 5px solid #0d6efd;
        }
        .ref-photo {
            width: 110px; height: 110px; object-fit: cover; border-radius: 50%;
            border: 3px solid var(--primary-color); padding: 2px; background: #fff;
        }
        .video-wrap { position: relative; display: inline-block; margin: 12px auto; }
        #webcam {
            width: 100%; max-width: 420px; height: auto; border-radius: 10px;
            border: 2px solid #333; transform: scaleX(-1); background: #000;
        }
        .liveness-tag {
            position: absolute; left: 50%; bottom: 12px; transform: translateX(-50%);
            background: rgba(0,0,0,0.7); color: #fff; font-size: 13px; font-weight: 600;
            padding: 6px 14px; border-radius: 20px; white-space: nowrap; pointer-events: none;
        }
        .btn-custom { background-color: var(--primary-color); color: #fff; font-weight: bold; border: none; }
        .step-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: #dee2e6; margin: 0 3px; }
        .step-dot.active { background: var(--primary-color); }
        .step-dot.done { background: #28a745; }
    </style>
    <link rel="stylesheet" href="../css/ui.css">
</head>
<body>
<?php render_lang_switcher(); ?>

<div class="header">
    <div>
        <div class="font-weight-bold"><?= te('kiosk.face_brand') ?></div>
        <span class="booth-chip">📍 <?= htmlspecialchars($booth['name']); ?> · <?= htmlspecialchars($booth['code']); ?></span>
    </div>
    <a href="index.php" class="btn btn-light btn-sm font-weight-bold"><?= te('kiosk.cancel') ?></a>
</div>

<div class="container" style="max-width: 640px;">
    <div class="face-card text-center">
        <div class="voter-chip mb-3"><?= htmlspecialchars($name); ?></div>

        <div class="mb-2">
            <?php if ($has_reference): ?>
                <img id="refImg" src="<?= htmlspecialchars($reference_photo); ?>" alt="<?= te('kiosk.ref_alt') ?>" class="ref-photo">
                <p class="small text-muted mb-0 mt-1"><?= te('kiosk.ref_on_file') ?></p>
            <?php else: ?>
                <img id="refImg" src="" alt="" class="ref-photo d-none">
                <p class="small text-muted mb-0"><?= te('kiosk.ref_missing') ?></p>
            <?php endif; ?>
        </div>

        <div class="video-wrap d-none" id="videoWrap">
            <video id="webcam" autoplay muted playsinline></video>
            <div class="liveness-tag" id="livenessTag"><?= te('kiosk.preparing') ?></div>
        </div>

        <div class="my-3">
            <div id="statusAlert" class="alert alert-info py-2 d-inline-block px-4 mb-1">
                <?= te('kiosk.loading_models') ?></div>
        </div>

        <div class="mt-2">
            <button id="startBtn" class="btn btn-custom font-weight-bold px-4 py-2" disabled><?= te('kiosk.start_face') ?></button>
            <button id="captureRefBtn" class="btn btn-outline-primary font-weight-bold px-3 py-2 ml-2 d-none"><?= te('kiosk.capture_ref') ?></button>
        </div>

        <div class="mt-3 small">
            <span class="step-dot" id="s1"></span> <?= te('kiosk.ref_ready') ?><span class="step-dot" id="s2"></span> <?= te('kiosk.blink_twice') ?><span class="step-dot" id="s3"></span> <?= te('kiosk.server_match') ?></div>
        <div class="mt-2 small text-muted">
            <?= te('kiosk.fp_still_required') ?></div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var video        = document.getElementById('webcam');
    var videoWrap    = document.getElementById('videoWrap');
    var refImg       = document.getElementById('refImg');
    var statusAlert  = document.getElementById('statusAlert');
    var startBtn     = document.getElementById('startBtn');
    var captureBtn   = document.getElementById('captureRefBtn');
    var livenessTag  = document.getElementById('livenessTag');
    var steps = { s1: document.getElementById('s1'), s2: document.getElementById('s2'), s3: document.getElementById('s3') };

    var MODEL_URL = '../models';
    var MIN_FACE_AREA = 160 * 160;
    var BLINKS_NEEDED = 2;
    var EYES_OPEN_EAR = 0.18;   // only used when picking a clean capture frame

    var stream = null;
    var referenceDescriptor = null;
    var loopTimer = null;

    function setStatus(msg, kind) {
        statusAlert.className = 'alert alert-' + (kind || 'info') + ' py-2 d-inline-block px-4 mb-1';
        statusAlert.innerText = msg;
    }
    function setStep(id, state) { steps[id].className = 'step-dot ' + (state || ''); }

    function stopCamera() {
        if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
        if (loopTimer) { clearInterval(loopTimer); loopTimer = null; }
        videoWrap.classList.add('d-none');
    }

    async function ensureCamera() {
        if (stream) return;
        if (!navigator.mediaDevices || !window.isSecureContext) {
            throw new Error('Camera access needs HTTPS (or localhost).');
        }
        stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 } });
        video.srcObject = stream;
        videoWrap.classList.remove('d-none');
        await new Promise(function (res) { video.onloadedmetadata = res; });
        await video.play();
    }

    function faceArea(det) { return det.detection.box.width * det.detection.box.height; }

    /* ---------- 1. models + reference descriptor ---------- */
    async function init() {
        try {
            setStatus('Loading facial recognition models…');
            await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
            await computeReferenceDescriptor();
        } catch (err) {
            setStatus('Error initializing face check: ' + err.message, 'danger');
        }
    }

    async function computeReferenceDescriptor() {
        if (!refImg || !refImg.src || refImg.classList.contains('d-none')) {
            offerCaptureReference('No usable reference photo on file.');
            return;
        }
        setStatus('Analyzing the reference photo…', 'info');
        if (!refImg.complete || refImg.naturalWidth === 0) {
            await new Promise(function (res) { refImg.onload = res; refImg.onerror = res; });
        }
        var det = await faceapi.detectSingleFace(refImg).withFaceLandmarks().withFaceDescriptor();
        if (!det) {
            referenceDescriptor = null;
            offerCaptureReference('No face found in the reference photo.');
            return;
        }
        referenceDescriptor = Array.from(det.descriptor);
        setStep('s1', 'done');
        captureBtn.classList.add('d-none');
        startBtn.disabled = false;
        setStatus('Ready. Press “Start Face Check” — you will be asked to blink twice.', 'success');
    }

    function offerCaptureReference(reason) {
        referenceDescriptor = null;
        startBtn.disabled = true;
        captureBtn.classList.remove('d-none');
        setStep('s1', '');
        setStatus(reason + ' Capture a reference photo in person, or use fingerprint only.', 'warning');
    }

    /* ---------- 2. capture an in-person reference photo ---------- */
    captureBtn.addEventListener('click', async function () {
        captureBtn.disabled = true;
        try {
            await ensureCamera();
            setStatus('Look at the camera with your eyes open and hold still…', 'primary');
            var frame = await waitForCleanFrame(40);
            if (!frame) throw new Error('Could not capture a clear single face. Try again in good lighting.');

            var snapshot = frame.canvas.toDataURL('image/jpeg', 0.85);
            var res = await api('enroll_photo', { snapshot: snapshot });
            if (!res.success) throw new Error(res.message || 'Could not store the reference photo.');

            referenceDescriptor = Array.from(frame.detection.descriptor);
            if (res.photo_url) {
                refImg.src = res.photo_url;
                refImg.classList.remove('d-none');
            }
            setStep('s1', 'done');
            captureBtn.classList.add('d-none');
            startBtn.disabled = false;
            setStatus('Reference photo captured. Press “Start Face Check”.', 'success');
        } catch (err) {
            setStatus('Capture failed: ' + err.message, 'danger');
        } finally {
            captureBtn.disabled = false;
            stopCamera();
        }
    });

    /* ---------- 3. blink-twice liveness, then capture + verify ---------- */
    startBtn.addEventListener('click', async function () {
        if (!referenceDescriptor) return;
        startBtn.disabled = true;
        try {
            await ensureCamera();
            setStatus('Liveness check: blink twice when prompted.', 'primary');
            setStep('s2', 'active');
            startLivenessLoop();
        } catch (err) {
            setStatus('Could not start the camera: ' + err.message, 'danger');
            startBtn.disabled = false;
        }
    });

    function startLivenessLoop() {
        var detector = BlinkLiveness.createDetector({ blinksNeeded: BLINKS_NEEDED });
        var busy = false;
        var startedAt = Date.now();

        livenessTag.innerText = 'Look at the camera, eyes open…';

        loopTimer = setInterval(async function () {
            if (busy) return;
            busy = true;
            try {
                var det = await faceapi.detectSingleFace(video).withFaceLandmarks();
                if (!det) {
                    livenessTag.innerText = 'No face detected — center yourself in the frame';
                } else if (faceArea(det) < MIN_FACE_AREA) {
                    livenessTag.innerText = 'Move closer to the camera';
                } else {
                    var ear = BlinkLiveness.eyeAspectRatio(det.landmarks.positions);
                    var s = detector.feed(ear);

                    if (s.done) {
                        clearInterval(loopTimer);
                        loopTimer = null;
                        livenessTag.innerText = '✓ Liveness confirmed — capturing…';
                        await captureAndVerify();
                        return;
                    }
                    if (s.warmup) {
                        livenessTag.innerText = 'Hold still with your eyes open…';
                    } else if (!s.calibrated) {
                        livenessTag.innerText = 'Move into better light and keep your eyes open';
                    } else {
                        livenessTag.innerText = 'Blink again (' + (s.needed - s.blinks) + ' more)';
                    }
                }

                if (Date.now() - startedAt > 30000) {
                    clearInterval(loopTimer);
                    loopTimer = null;
                    livenessTag.innerText = 'Timed out';
                    setStatus('Liveness timed out. Please press “Start Face Check” and try again.', 'warning');
                    setStep('s2', '');
                    startBtn.disabled = false;
                    stopCamera();
                }
            } catch (e) {
                // transient detection errors are ignored
            } finally {
                busy = false;
            }
        }, 120);
    }

    /* ---------- 4. clean single-face capture, then server match ---------- */
    async function captureAndVerify() {
        setStatus('Capturing your live photo…', 'primary');
        try {
            var frame = await waitForCleanFrame(12);
            if (!frame) throw new Error('Could not capture a clear single face. Please retry in good lighting.');

            var liveDescriptor = Array.from(frame.detection.descriptor);
            var snapshot = frame.canvas.toDataURL('image/jpeg', 0.65);

            setStatus('Submitting to the server for verification…', 'info');
            setStep('s3', 'active');

            var begin = await api('begin', {});
            if (!begin.success) throw new Error(begin.message || 'Could not start the face check.');

            var res = await api('match', {
                nonce: begin.nonce,
                live_descriptor: liveDescriptor,
                ref_descriptor: referenceDescriptor,
                snapshot: snapshot
            });
            if (!res.success) throw new Error(res.message || 'The server rejected the attempt.');

            if (res.matched) {
                setStep('s3', 'done');
                setStatus('✅ Face matched (score ' + Number(res.distance).toFixed(3) + '). Redirecting…', 'success');
                stopCamera();
                setTimeout(function () { window.location.href = 'index.php'; }, 1200);
            } else {
                setStatus('❌ Face did not match closely enough (score ' + Number(res.distance).toFixed(3) +
                          ' / threshold ' + res.threshold + '). Try again or use fingerprint.', 'warning');
                setStep('s2', '');
                setStep('s3', '');
                startBtn.disabled = false;
                stopCamera();
            }
        } catch (err) {
            setStatus('Verification failed: ' + err.message, 'danger');
            setStep('s2', '');
            setStep('s3', '');
            startBtn.disabled = false;
            stopCamera();
        }
    }

    /** Draw frames until one has exactly one clear face with eyes open. */
    async function waitForCleanFrame(attempts) {
        var canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        var ctx = canvas.getContext('2d');

        for (var i = 0; i < attempts; i++) {
            ctx.drawImage(video, 0, 0);
            var results = await faceapi.detectAllFaces(canvas).withFaceLandmarks().withFaceDescriptors();
            if (results.length === 1) {
                var r = results[0];
                var ear = BlinkLiveness.eyeAspectRatio(r.landmarks.positions);
                if (faceArea(r) >= MIN_FACE_AREA && ear > EYES_OPEN_EAR) {
                    return { canvas: canvas, detection: r };
                }
            }
            await new Promise(function (res) { setTimeout(res, 150); });
        }
        return null;
    }

    async function api(action, extra) {
        var res = await fetch('face_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ action: action }, extra || {}))
        });
        var raw = await res.text();
        var data;
        try {
            data = JSON.parse(raw);
        } catch (e) {
            var snippet = raw.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 180);
            throw new Error('Server returned non-JSON (' + res.status + '): ' + (snippet || '(empty response)'));
        }
        if (!res.ok || data.success === false) throw new Error(data.message || ('Server error ' + res.status));
        return data;
    }

    window.addEventListener('beforeunload', stopCamera);
    window.addEventListener('load', init);
})();
</script>
</body>
</html>
