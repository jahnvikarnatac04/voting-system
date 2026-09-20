/**
 * blink-liveness.js
 * ===============================================================
 * "Blink twice" liveness detection for a live camera stream, built on
 * the 68-point landmarks that face-api.js already produces.
 *
 * Why it is robust:
 *   - It does not use fixed EAR cut-offs. A fixed threshold fails for
 *     people with narrow or hooded eyes and drifts with lighting.
 *     Instead it calibrates each person's OPEN-eye baseline over a
 *     short warm-up, then counts a blink as an eye-aspect-ratio drop
 *     to <= 70% of that baseline followed by a recovery to >= 86%.
 *   - Hysteresis (a closed band separate from the open band) stops
 *     noise around the threshold from double-counting.
 *   - A per-blink cooldown rejects flicker.
 *   - The baseline adapts slowly upward, so a wider-open frame later
 *     in the session does not silently disable detection.
 *
 * Usage:
 *   const det = BlinkLiveness.createDetector({ blinksNeeded: 2 });
 *   // each frame, after face-api detection:
 *   const s = det.feed(BlinkLiveness.eyeAspectRatio(landmarks.positions));
 *   if (s.done) { ...liveness passed... }
 * ===============================================================
 */
(function (global) {
    'use strict';

    // face-api.js 68-point eye outlines
    var LEFT_EYE  = [36, 37, 38, 39, 40, 41];
    var RIGHT_EYE = [42, 43, 44, 45, 46, 47];

    /** Vertical/horizontal ratio of one eye. Low value = eye closed. */
    function eyeRatio(pts, idx) {
        var d = function (a, b) {
            return Math.hypot(pts[a].x - pts[b].x, pts[a].y - pts[b].y);
        };
        // Standard EAR: two vertical spans over one horizontal span.
        var vertical   = d(idx[1], idx[5]) + d(idx[2], idx[4]);
        var horizontal = 2 * d(idx[0], idx[3]);
        return vertical / (horizontal + 1e-6);
    }

    /** Eye aspect ratio averaged across both eyes. */
    function eyeAspectRatio(positions) {
        if (!positions || positions.length < 48) {
            return NaN;
        }
        return (eyeRatio(positions, LEFT_EYE) + eyeRatio(positions, RIGHT_EYE)) / 2;
    }

    /**
     * Stateful blink counter.
     *
     * feed(ear) -> {
     *   blinks, needed, done, state, ear, baseline, calibrated, warmup
     * }
     */
    function createDetector(opts) {
        opts = opts || {};

        var blinksNeeded  = opts.blinksNeeded  || 2;
        var warmupFrames   = opts.warmupFrames  || 10;
        var closeRatio    = opts.closeRatio    || 0.70;   // closed <= 70% of baseline
        var openRatio     = opts.openRatio     || 0.86;   // open   >= 86% of baseline
        var minClosed     = opts.minClosed     || 0.10;   // absolute EAR floor
        var minBaseline   = opts.minBaseline   || 0.18;   // below this, calibration is unreliable
        var cooldownMs    = opts.cooldownMs    || 250;

        var baseline       = 0;
        var warmupSeen     = 0;
        var state          = 'warmup';   // warmup | open | closed
        var blinks         = 0;
        var lastBlinkAt    = 0;
        var lastEar        = NaN;

        function reset() {
            baseline = 0; warmupSeen = 0; state = 'warmup';
            blinks = 0; lastBlinkAt = 0; lastEar = NaN;
        }

        function status() {
            return {
                blinks:     blinks,
                needed:     blinksNeeded,
                done:       blinks >= blinksNeeded,
                state:      state,
                ear:        lastEar,
                baseline:   baseline,
                calibrated: baseline >= minBaseline,
                warmup:     state === 'warmup'
            };
        }

        function feed(ear, nowMs) {
            nowMs = (typeof nowMs === 'number') ? nowMs : Date.now();
            if (!isFinite(ear) || ear <= 0) {
                return status();
            }
            lastEar = ear;

            // ---- warm-up: learn the open-eye baseline (widest span seen) ----
            if (state === 'warmup') {
                baseline = Math.max(baseline, ear);
                warmupSeen++;
                if (warmupSeen >= warmupFrames) {
                    state = 'open';
                }
                return status();
            }

            // Adapt slowly upward if a more-open frame appears later.
            if (ear > baseline) {
                baseline += 0.20 * (ear - baseline);
            }

            var closedAt = Math.max(minClosed, baseline * closeRatio);
            var openAt   = Math.max(closedAt + 0.02, baseline * openRatio);

            if (state !== 'closed' && ear <= closedAt) {
                state = 'closed';
            } else if (state === 'closed' && ear >= openAt) {
                state = 'open';
                if (nowMs - lastBlinkAt >= cooldownMs) {
                    blinks++;
                    lastBlinkAt = nowMs;
                }
            } else if (state !== 'closed') {
                state = 'open';
            }

            return status();
        }

        return { feed: feed, reset: reset, status: status };
    }

    global.BlinkLiveness = {
        eyeAspectRatio: eyeAspectRatio,
        createDetector: createDetector
    };
})(window);
