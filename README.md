# Online Voting System — Lok Sabha Portal

A PHP + SQLite web application that simulates a secure national (Lok Sabha) election portal with biometric voter verification, two-factor authentication, and an election-commission admin console.

> **Educational / demo project.** It demonstrates authentication and ballot-integrity patterns and is **not** certified for real elections.

## Features

### Voter portal
- **Constituency selection** — pick a State/UT and Lok Sabha constituency (all 543 PC labels bundled) before entering the portal
- **Passkey-first login** — sign in with a fingerprint / WebAuthn passkey (cross-device "a phone or tablet" approval supported), with email + password fallback
- **Two-factor authentication** — 6-digit OTP sent over Gmail SMTP (email) and Fast2SMS (mobile), valid for 5 minutes
- **Pre-vote workflow** — a 3-step declaration screen before the dashboard unlocks
- **Identity verification** — live webcam capture matched against the registered photo using face-api.js 128-d descriptors, plus fingerprint/passkey verification; the match decision, threshold, one-time nonce, snapshot, and IP are handled/audited server-side. This face check (`voters/face_verify.php` + `voters/face_verify_api.php`) is **portal-only**: it confirms identity and sets a `face_verified` session flag shown on the dashboard. It does **not** authorize a ballot — a booth-kiosk ballot is unlocked only by a fresh *fingerprint* verification (`kiosk_verified_voter()`).
- **View-only portal** — view certified candidates and manage your profile. **Ballots are cast in person at a booth kiosk** (see below); the portal no longer casts votes.
- **Ballot integrity** — one vote per voter, enforced at the booth kiosk with a live re-check + atomic conditional UPDATE
- **Party symbols & logos** — real Indian national/state party logos with ECI-style symbol rendering### Admin console (`/admin`)

- Role-based accounts (`super_admin` / `admin`)
- Approve or reject voter registrations
- Manage candidates per constituency, view live results and turnout

### Booth kiosk (`/kiosk`)

A scoped, locked-down terminal for a phone acting as the hardware at one
physical polling station.

- **Booth-scoped unlock** — a booth code + PIN (managed under **Booths & Kiosks**
in the admin console) unlocks the kiosk for *that location only*. The kiosk
session has no admin powers.
- **Enroll** — search a citizen by name / email / EPIC and bind their
fingerprint/passkey to their account, recorded against the booth.
- **Verify (two required steps)** — confirm a citizen's identity with **both** a
face check and a fingerprint/passkey scan, each recorded against the booth:
  1. **Face check** — a live camera capture is matched to the citizen's reference
     photo after a **blink-twice liveness** challenge (adaptive eye-aspect-ratio,
     not a fixed cutoff). The match decision is server-side (`kiosk/face_api.php`).
     If the citizen has no usable photo, the operator captures a reference photo
     in person (stored as `voters.face_photo`).
  2. **Fingerprint** — the WebAuthn scan, as before.

  A ballot needs **both**; set `KIOSK_REQUIRE_FACE = false` in `kiosk/_kiosk.php`
  to make the face check optional.
- **Cast ballot on the kiosk** — right after both checks pass the kiosk opens the
citizen's ballot, they choose and confirm on the device, and the vote is recorded
with the existing one-vote transaction (atomic; a double submit cannot record
twice). Only available when the citizen's constituency matches the booth's; the
authorization expires ~2 minutes after the fingerprint scan (the face check stays
valid 5 minutes) and is consumed on submit.
- **Auto-lock** — the kiosk re-locks after 5 minutes of inactivity; unlock
attempts are rate-limited per device.
- Biometric data never leaves the device; the booth stores only a public
credential. Enrollments/verifications are logged in `biometric_logs` with the
`booth_id` they happened at.

#### Setting up booth voting

A booth only issues a ballot when the citizen's constituency matches the booth's,
so both need a constituency:

0. **Get the citizen into the database first.** There is no self-service sign-up
   (`register.php` is disabled). Use **Admin console → ➕ Onboard Citizen**
   (`admin/onboard_voter.php`): full name, email, EPIC, mobile, **constituency**,
   status (defaults to *Approved now*), and a temporary password. Creating the
   citizen and assigning their constituency happen in the *same* form, so a
   walk-in who is not yet in the DB gets both in one step.
1. **Admin console → 📍 Booths & Kiosks → Edit** → set the booth's State /
   Constituency (must match a seeded constituency exactly, e.g. `Varanasi (PC-77)`).
2. Give existing citizens a constituency — per record via **Onboard Citizen**, or
   in bulk:
   ```bash
   php scripts/assign_constituency.php list
   php scripts/assign_constituency.php assign "Varanasi (PC-77)" --blank
   ```
   `--blank` fills only citizens that have none; `--all`, `--email=`, `--epic=`, and
   `--id=` are also supported (see the script header).
3. At the kiosk: search → **Enroll** (first time) → **Verify** (face check, then
   fingerprint) → **Open Ballot** → citizen chooses → confirm.

> The citizen's face check needs a usable photo. If the registration photo is the
> generic placeholder, the face page offers **Capture Reference Photo** — an
> in-person capture at the booth, stored as `voters.face_photo`.

The constituency string must match the booth's **exactly** (including the `(PC-nn)`
suffix), and at least one candidate must exist for that exact string, or the booth
opens an empty ballot. A citizen with no constituency is refused with
*"No constituency is assigned to this citizen."*

> The demo DB seeds booth **`BOOTH-001` / PIN `123456`** (no constituency until you
> set one). WebAuthn requires HTTPS or localhost — for a real kiosk, pin a stable
> RP ID via `includes/config.example.php` (a rotating tunnel hostname would orphan
> every enrolled passkey).

## Tech stack

| Layer      | Choice                                                        |
| ---------- | ------------------------------------------------------------- |
| Backend    | PHP 8 (PDO), no framework                                     |
| Database   | SQLite (`voting_system.db`)                                   |
| UI         | Bootstrap 5 (bundled locally), vanilla JS                     |
| Face match | face-api.js (client) + server-side distance check             |
| Passkeys   | Dependency-free server-side WebAuthn (`includes/webauthn.php`), ES256 + RS256 |
| Email/SMS  | PHPMailer (Gmail SMTP), Fast2SMS REST API                     |

## Requirements

- PHP ≥ 8.0 with extensions: `pdo_sqlite`, `openssl`, `curl`, `mbstring`
- Composer
- A browser with WebAuthn + camera access (passkey login and face verification require **localhost or HTTPS**)

## Setup

```bash
# 1. Install dependencies
composer install

# 2. Initialise the database (creates tables + default admins)
php init_db.php

# 3. Seed realistic demo data (optional but recommended)
#    Real Lok Sabha 2024 candidates per constituency + synthetic demo voters
php seed_real_data.php

# 4. Run locally
php -S localhost:8000
```

Then open <http://localhost:8000>.

### Default admin accounts (created by `init_db.php`)

| Role        | Username   | Password      |
| ----------- | ---------- | ------------- |
| super_admin | `admin`    | `admin123`    |
| admin       | `officer1` | `officer123`  |

### Demo voters

`seed_real_data.php` pre-enrolls an approved electorate (registration is closed by design — see `register.php`). Every seeded voter's password is **`Voter@123`**.

## Configuration

Credentials and service settings live in an **untracked** config file. Nothing
sensitive is committed.

```bash
cp includes/config.example.php includes/config.php
# then edit includes/config.php
```

| Key | What |
| --- | --- |
| `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_FROM`, `SMTP_FROM_NAME` | Gmail SMTP (email OTP) |
| `FAST2SMS_API_KEY` | Fast2SMS (mobile OTP) |
| `CALLMEBOT_API_KEY` | CallMeBot (WhatsApp OTP, legacy path) |
| `WEBAUTHN_RP_ID`, `WEBAUTHN_ALLOWED_ORIGINS` | WebAuthn / booth kiosk |

`includes/config.php` is listed in `.gitignore` and must never be committed.
Values may also be supplied as environment variables of the same name.
`includes/config.example.php` documents every key with placeholders.

```bash
php scripts/set_kiosk_host.php <host>   # pins the RP ID; preserves the keys above
```

A tracked secret scan runs in CI (`scripts/process/check_secrets.sh`) and fails the
build if a credential is committed.

⚠️ **If a credential was ever committed, rotate it at the provider.** Removing it
in a later commit does not remove it from history. See
[`docs/process/policies/security-and-secrets.md`](docs/process/policies/security-and-secrets.md).

## Project structure

```
├── index.php                  # Constituency picker → portal landing
├── login.php                  # Passkey / credentials + 2FA OTP login
├── register.php               # Disabled (redirects to login); voters pre-enrolled
├── register_fingerprint.php   # Passkey enrollment
├── webauthn_options.php       # WebAuthn begin/finish endpoints
├── db.php                     # PDO SQLite connection + idempotent schema upgrades
├── init_db.php                # Schema bootstrap + default admins
├── seed_real_data.php         # Lok Sabha 2024 candidates + demo voters
├── schema.sql                 # Reference schema
├── admin/                     # Admin login, dashboard, voter approval, results, booths
├── kiosk/                     # Phone booth terminal: unlock, enroll, verify, ballot, vote
├── voters/                    # Voter dashboard (view-only), pre-vote workflow, identity verification (face/fingerprint)
├── includes/                  # webauthn.php, party_symbols.php, i18n.php, connection.php
├── lang/                      # Translation catalogues (en.php, hi.php)
├── images/                    # Voter photos, candidate photos, party logos
├── demo.md                    # short end-to-end demo guide
├── demo-two-booths.md         # two-booth / regional-rules presentation script
├── scripts/                   # fetch_party_logos.php, demo/, tests/ and dev/ helpers
├── css/                       # app.css (shared tokens/components), ui.css (responsive/a11y)
├── bootstrap/, js/            # Vendored Bootstrap 4 and face-api/models
└── .github/workflows/ci.yml   # CI: process gates, composer validate, PHP lint, e2e tests
```

## Front-end stylesheets

Pages no longer carry a private copy of the same CSS. Two shared sheets are
linked by every page:

| File | Loaded | Purpose |
|---|---|---|
| `css/app.css` | after Bootstrap, **before** the page's own `<style>` | Design tokens (`--primary-color`, `--bg-light`, …) and the components that were duplicated everywhere (`.header`, `.btn-custom`, `footer`, the booth/voter chips) |
| `css/ui.css` | **after** the page's own `<style>` | Progressive enhancement only: responsive header, small-screen overflow guards, keyboard focus rings, coarse-pointer tap targets, reduced motion, print rules, the language strip |

The load order is deliberate. `app.css` comes first so any page can still
override a shared rule; `ui.css` comes last so its media queries can win over a
page-level `height: 9vh` of the same specificity.

## Languages

English and Hindi ship by default. The switcher appears as a strip above the
header on the **booth kiosk** pages; the choice is remembered in the session and
in a `voting_lang` cookie, and `<html lang>` follows it.

Adding a language is one file:

```bash
cp lang/en.php lang/ta.php     # then translate the values
```

The switcher discovers `lang/*.php` automatically, so a new catalogue shows up
with no code change. Keys missing from a catalogue fall back to English and then
to the key itself, so a partly translated file degrades to English rather than
rendering blank — translations can be added a key at a time.

**Coverage:** the booth kiosk (`kiosk/login.php`, `index.php`, `face_verify.php`,
`ballot.php`, `vote.php`) is fully translated. `index.php`, `login.php`, the
`voters/*` portal pages and the admin console are still English-only — to
localise them, wrap their strings in `te('your.key')` and add the key to
`lang/*.php`.

Two strings on the kiosk are deliberately left in English: they are sentence
fragments split by an interpolated value (`kiosk.no_candidates_hint`,
`kiosk.fp_on_file`).

### Checking a UI change visually

`scripts/dev/` has two development-only helpers that screenshot every page
(including authenticated ones, via crafted sessions) and pixel-diff two runs:

```bash
php scripts/dev/ui_screenshots.php before   # 36 screenshots at desktop + phone widths
#  ...make your CSS change...
php scripts/dev/ui_screenshots.php after
php scripts/dev/ui_diff.php before after    # per-page changed-pixel percentage
```

Use them to prove a stylesheet refactor is rendering-neutral, or to see exactly
which region a deliberate change moved. They require Chrome and the GD
extension, write only to the system temp directory, and are not part of the
application.

## Security notes

- Passwords hashed with `password_hash()`; OTPs expire after 5 minutes
- Face match threshold (0.55 Euclidean distance) and verification state enforced **server-side**; no client-only "mark verified" path exists
- Every biometric attempt is logged (`biometric_logs`) with method, distance, snapshot, and IP
- Vote casting re-checks voter status inside a transaction to prevent double-voting races
- Known demo-grade gaps: `login.php` accepts legacy plain-text passwords as a fallback (tracked as FR-2.6), there are no automated tests (NFR-10), and SQLite is a single-file DB — all fine for a classroom demo, not for production. Credentials now live in the untracked `includes/config.php`; see [Security & Secrets Policy](docs/process/policies/security-and-secrets.md).
