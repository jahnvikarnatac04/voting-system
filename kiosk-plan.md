# Kiosk Plan — Phone as a Booth Biometric Terminal

Plan for turning the existing admin booth-enrollment flow into a dedicated
**kiosk app on a phone** that enrolls and verifies fingerprints for a specific
location (booth).

> Status: **implemented** — see “Implementation status” below for what shipped.

---

## Implementation status

All six phases are in place:

| Phase | Delivered |
| --- | --- |
| 0 — Deployment shape | Documented; `includes/config.example.php` shows how to pin `WEBAUTHN_RP_ID` / origins |
| 1 — Location model | `booths`, `kiosk_auth_attempts` tables; `booth_id` on `passkeys` + `biometric_logs`; migrations in `db.php`; demo booth seeded by `init_db.php` |
| 2 — Kiosk mode | `kiosk/_kiosk.php` (scoped session, idle timeout), `kiosk/login.php`, `kiosk/logout.php`, `kiosk/index.php` (locked-down UI) |
| 3 — Enrollment | `webauthn_options.php` enrolls against the armed kiosk target, stamps `booth_id`, audits it (the admin-side arming path was removed in phase 8) |
| 4 — Booth verification | New `verify_begin` / `verify_finish` actions + kiosk Verify flow |
| 5 — Hardening | Configured RP ID + origin allowlist + loopback helper in `includes/webauthn.php`; kiosk unlock rate-limiting/lockout |
| 6 — Connectivity | Caveat documented below |
| 7 — Booth voting | Ballot cast on the kiosk device (`kiosk/ballot.php`, `kiosk/vote.php`); authorization bound to the just-verified citizen, ~2-min window, consumed on submit; `voters.constituency` + booth constituency matching; setup via booth **Edit** and `scripts/assign_constituency.php` |
| 8 — Consolidation | Self-service voting removed: `voters/vote.php` deleted and the voter dashboard is now view-only (identity verification, face + fingerprint, kept). Admin-side enroll console removed: `admin/enroll_voter.php` → `admin/onboard_voter.php`, which only creates the citizen record — fingerprint enrollment happens at the kiosk. `wa_booth_enrollment()` is kiosk-only. |
| 9 — Booth face check | Face is a required **second** biometric at the booth. `kiosk/face_verify.php` runs a camera capture with a blink-twice liveness challenge (`js/blink-liveness.js`, adaptive EAR); `kiosk/face_api.php` decides the match server-side, with a single-use nonce and `biometric_logs` rows carrying `booth_id`. `voters.face_photo` stores an in-person reference capture when no usable photo exists. `kiosk_verified_voter()` now needs **both** a face check and a fingerprint — flip `KIOSK_REQUIRE_FACE` to relax that. |

### Using the kiosk

```bash
php init_db.php            # creates the booths table + demo booth
php -S localhost:8000      # WebAuthn needs HTTPS or localhost
```

1. Admin console → **📍 Booths & Kiosks** → create a booth (or use the seeded
   `BOOTH-001` / PIN `123456`).
2. On the booth phone, open `kiosk/login.php` and unlock with the booth code + PIN.
3. Search by name / email / EPIC → **Enroll** (bind a fingerprint) or **Verify**.
   Verify runs two required steps: **face check** (blink twice; capture a reference
   photo first if the citizen has none) and then **fingerprint**. Both must pass for
   the same citizen before the ballot opens.
4. Every enrollment/verification is logged against that `booth_id` in
   `biometric_logs`; enrolled passkeys carry the `booth_id` they came from.
5. For a ballot, the booth and the citizen must share a constituency: set the
   booth's via **Booths & Kiosks → Edit**, and the citizen's via walk-in
   onboarding or `php scripts/assign_constituency.php assign "…" --blank`.
   A successful **Verify** then offers **Open Ballot**, the citizen chooses on the
   kiosk, and the vote is recorded with the existing one-vote transaction.

The kiosk session is deliberately scoped: it has no `admin_id`, so the existing
`admin/*` guards keep it out of the election console entirely.

---

## Why not the Go / raw-fingerprint rewrite

A Go rewrite of a raw-fingerprint minutiae engine (Otsu → Zhang–Suen →
crossing-number) was proposed. It is **not applicable to this goal**:

- A phone's built-in fingerprint sensor **never exposes a fingerprint image**.
  iOS (`LocalAuthentication` / Touch ID / Face ID) and Android
  (`BiometricPrompt`) expose biometrics only as an authenticated yes/no — there
  is no native or web API that returns the raw scan. WebAuthn rides exactly that
  surface.
- Therefore a Go engine that needs raw 400×400 images **cannot run on a phone
  using its own sensor**, in Go or any other language.
- Raw-image capture would require an **external USB/BT scanner** (Mantra MFS100,
  SecuGen, DigitalPersona, ZKTeco…) and its vendor SDK, which forces CGO/JNI
  bindings regardless — collapsing the proposal's "pure Go" premise — plus a new
  enrollment path and a template-storage privacy/legal surface (DPDP Act 2023,
  UIDAI norms).

Decision (confirmed): the kiosk uses the **phone's built-in sensor via
WebAuthn**, and language is flexible. So the work is *harden and extend the
existing WebAuthn booth flow*, not a rewrite.

---

## What already exists

| Piece | Where |
| --- | --- |
| Booth enrollment: a booth kiosk arms a target voter → citizen scans → credential bound to *that voter* → kiosk auto-disarms | `kiosk/index.php`, `webauthn_options.php` (`kiosk_enroll_vid`, `register_finish`) |
| Server-side WebAuthn verification (challenge, origin, signature, sign-counter; ES256/RS256; `attestation: none`) | `includes/webauthn.php` |
| Passkey-first login incl. cross-device "use a phone" (residentKey required, no allowCredentials) | `webauthn_options.php` → `login_begin` |
| Tables `passkeys`, `biometric_logs` | `schema.sql`, `db.php` |

---

## What is missing for "a kiosk at a specific location"

1. **No location/booth concept.** Nothing records *where* an enrollment or
   verification happened. There is no `booths` table and `passkeys` /
   `biometric_logs` have no `booth_id`. This is the core requirement and it is
   entirely absent.
2. **No kiosk mode.** The booth phone currently signs into the **full admin
   console** with full privileges and navigation. A kiosk should be a scoped,
   locked, single-purpose screen with no path to the rest of the admin.
3. **`wa_rp_id()` derives the RP ID from the `Host` header**
   (`includes/webauthn.php:83`). Two problems:
   - a rotating quick-tunnel hostname (`*.trycloudflare.com`) changes the RP ID,
     which **orphans every previously enrolled passkey** on restart;
   - trusting the `Host` header for RP ID / origin is spoofable.
4. **A remote phone over plain HTTP cannot use WebAuthn at all.**
   `wa_secure_context_ok()` accepts only HTTPS or `localhost` / `127.0.0.1` /
   `::1`, so `http://192.168.x.x:8000` from a booth phone silently fails.
5. **No booth-side *verification*** separate from enrollment and from the
   pre-vote check. "Enroll **and verify**" — verify is not built.
6. **Enrollment audits are thin.** Passkey enrollments write only to `passkeys`,
   never to `biometric_logs` (only face verification logs there).
7. **One voter at a time.** The booth must re-arm per citizen; there is no EPIC
   lookup / queue for throughput.

---

## Phased plan

### Phase 0 — Deployment shape (prerequisite, blocks everything)
Pin a **stable HTTPS hostname** for the app (a named Cloudflare tunnel on your
own domain, or a TLS reverse proxy). A kiosk on a rotating tunnel URL cannot
work: the RP ID would change and passkeys would break. Until this is settled,
nothing else can be relied on.

### Phase 1 — Location model (data)
- Add a `booths` table: `id`, `code`, `name`, `state`, `constituency`, `active`,
  `created_at`.
- Add `booth_id` to `passkeys` (where the credential was enrolled) and to
  `biometric_logs` (where the attempt happened).
- Migrate using the existing idempotent `ALTER TABLE` upgrade pattern in
  `db.php`.

### Phase 2 — Kiosk mode (access + UI) — done
- A scoped booth login that creates `kiosk_booth_id` in the session **without**
  admin powers (booth code + kiosk PIN) — `kiosk/login.php`, `kiosk/_kiosk.php`.
- A `kiosk/` area: locked-down single-purpose UI, no dashboard/nav, idle
  timeout, auto-disarm after a successful scan — `kiosk/index.php`.
- Guards so that kiosk sessions cannot reach `admin/*` or voter actions: the
  kiosk session carries no `admin_id`, so existing admin guards already exclude it.

### Phase 3 — Enrollment at the booth (extend existing)
- Generalize `admin_enroll_vid` into a kiosk-aware armed target
  (`kiosk_booth_id` + armed voter), reusing `register_begin` / `register_finish`.
- Stamp `booth_id` on the inserted `passkeys` row.
- Log the enrollment to `biometric_logs` (`method='passkey_enroll'`, `booth_id`).
- Improve throughput: EPIC search + a small pending queue.

### Phase 4 — Booth verification (new)
- Add `verify_begin` / `verify_finish`, mirroring the existing
  `vote_begin` / `vote_finish` pair: the kiosk confirms a person matches their
  enrolled credential for booth check-in (not voting). Server-side only; log
  `booth_id` and the result.

### Phase 5 — Hardening
- Replace the Host-derived RP ID with a **configured constant** and validate
  origins against an allowlist (closes Host-header spoofing and tunnel-rotation
  breakage).
- Kiosk attempt rate-limiting / lockout.
- Optional device binding / attestation if booth devices are known.
- Audit admin/kiosk actions.

### Phase 6 — Connectivity (be explicit)
WebAuthn ceremonies need a **server-issued challenge**, so the kiosk requires a
live link to the app server. Full offline verification on the phone is **not
possible** with this design. If offline booths are a hard requirement, that
needs a separate architecture (e.g. a local server on a laptop/tablet at the
booth) and should be decided before Phase 2.

---

## Suggested first move

Phases 1 + 2 are the highest-leverage, lowest-risk steps: they add the `booths`
table and `booth_id` columns plus the scoped kiosk session, with **no change to
the existing WebAuthn crypto path** in `includes/webauthn.php`.
