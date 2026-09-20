# Demo Guide — Online Voting System

How to prep for and run an end-to-end demo (~8 minutes).

> For the **two-booth / regional-rules presentation** (two booths in different
> constituencies, a citizen refused at the wrong booth, and the face gate), see
> [`demo-two-booths.md`](demo-two-booths.md). This file remains the short general demo.

---

## ⚠️ Prep before demoing (5 min)

### 0. Baseline setup

```bash
composer install          # install PHPMailer etc.
php init_db.php           # create schema + default admins
php seed_real_data.php    # real Lok Sabha 2024 candidates + demo voters (safe to re-run)
php -S localhost:8000     # start the app
```

Open <http://localhost:8000>. Everything else (face models, Bootstrap, face-api.js, party logos) is vendored locally — the only internet dependency is email delivery.

### 1. Make the OTP land in an inbox you control

OTPs go out by **email only** — the Fast2SMS key is a placeholder, so SMS silently fails. Seeded voters have synthetic addresses (`arjun.singh1@gmail.com`…) whose inboxes you can't open. Point one voter at an inbox you can open:

```bash
sqlite3 voting_system.db "UPDATE voters SET email='you@example.com' WHERE email='arjun.singh1@gmail.com';"
```

(Alternative: log in as `jahnvikarnatac04@gmail.com` — it is the sending SMTP account, so the OTP lands in its own inbox.)

Seeded voter password for everyone: **`Voter@123`**

### 2. Make face verification actually match

All seeded voters have the generic `default.png` as their photo — face-api can't match against it. Log in as your demo voter and upload a real selfie via **Edit Profile** first. After that, live face verification works.

### 3. Camera permission

Keep the demo tab frontmost and pre-approve camera access so the face-verify screen doesn't stall on the browser prompt.

---

## 🎬 The demo flow (~8 min)

> **Language switching.** The kiosk screens carry a language strip above the
> header — one tap toggles English ⇄ हिन्दी and the choice sticks in a cookie.
> It is the quickest way to show the booth working for a citizen who does not
> read English. The voter portal and admin console are still English-only.


### Act 1 — Voter journey (the core)

1. Open `localhost:8000` → pick State + Lok Sabha constituency → note the portal is constituency-scoped
2. **Voter Login** → pause on the passkey-first screen ("Sign in with your fingerprint") → click **"Use Email & Password instead"** → log in with the seeded voter + `Voter@123`
3. Show the **2FA screen** → fetch the OTP from the inbox → enter it → logged in
4. **Pre-vote workflow**: the 3-step declaration gate before the dashboard unlocks
5. **Identity verification**: webcam opens, live capture vs registered photo — the wow moment. Deliberately point the camera away once → "did not match" → then match. This verifies identity; it no longer unlocks a self-service ballot.

   > This is the portal's copy of the face check. The **booth kiosk has its own face check too** (Act 2), where it is one of **two required steps** before a ballot opens. The portal copy is only an account identity check and unlocks nothing by itself.

6. Note the candidate list is **view-only** — the ballot is cast at the booth kiosk (Act 2).

### Act 2 — Admin console

1. Scroll to the discreet officer section → `admin/login.php` → `superadmin` / `SuperSecret123!` (also works: `admin` / `admin123`)
2. Approve a `pending` voter → show that voter can now log in
3. 🖐️ **Booth kiosk enrollment** — the security talking point:

   > "Voters can't self-register online for security reasons — the election admin pre-enrolls them at the booth kiosk."

   Admin-side, onboard any walk-in not yet in the list with **➕ Onboard Citizen** in the dashboard header: name / email / EPIC / constituency / temp password → the record is created (default **Approved now**, since the ID was verified in person). Hand them a login slip.

   > This is the only way a citizen gets into the database — self-registration is disabled. The **constituency** you type here must match the target booth's constituency **exactly** (e.g. `Varanasi (PC-77)`), and candidates must already exist for that string, or the booth refuses/opens an empty ballot. To fix an existing citizen who has no constituency:
   > ```bash
   > php scripts/assign_constituency.php list
   > php scripts/assign_constituency.php assign "Varanasi (PC-77)" --blank
   > ```

   Then on the booth phone open `kiosk/login.php` → unlock with the booth code + PIN → search the citizen → **Enroll** → they scan → the passkey is bound to *their* account and the booth auto-disarms.

   Duplicate email/EPIC entries are rejected with a pointer to the existing record.

4. 🗳️ **Cast a ballot at the kiosk — two biometric steps** — search the citizen → **Verify** → **Step 1: Start Face Check** (they blink twice; the match is decided server-side) → back at the kiosk, **Step 2: Verify Fingerprint** → the kiosk then offers **Open Ballot** (only if their constituency matches the booth's). They choose a candidate and confirm. Trying to vote again is refused.

   > A kiosk ballot needs **both** the face check and the fingerprint. If a citizen still has the placeholder photo, the face page offers **Capture Reference Photo** — do that once, in person, and the check proceeds.

5. Show **live results / turnout** for the constituency

### Act 3 — Passkey sign-in (optional closer)

1. Logout → back to the voter login screen
2. **Continue with Fingerprint / Passkey** → the passkey enrolled at the booth kiosk just works (localhost is a secure context, so WebAuthn runs fine under `php -S`) — including "a phone or tablet" cross-device approval if you want to flex
3. If asked: voters can manage/rename devices later from **Manage Fingerprints / Passkeys** on their dashboard —   but *enrollment* stays a booth-kiosk activity

---

## One-liner narrative

> "In-person enrollment, in-person voting — the booth kiosk pre-enrolls each voter's fingerprint, and every ballot sits behind that fingerprint, with a server-enforced one-person-one-vote and an audit trail."

---

## Quick end-to-end smoke test (2 min)

The shortest path to prove the whole pipeline works:

```bash
# 1. One-time prep
composer install
php init_db.php
php seed_real_data.php
sqlite3 voting_system.db "UPDATE voters SET email='you@example.com' WHERE email='arjun.singh1@gmail.com';"
php -S localhost:8000
```

Then in the browser:

1. Pick a constituency → **Voter Login** → email + `Voter@123`
2. Enter OTP from your inbox
3. Upload a selfie in **Edit Profile** (one-time, enables face match)
4. Complete the declaration → **face scan** (identity verification)
5. `superadmin` / `SuperSecret123!` at `admin/login.php` → check the results page
6. (Optional) `kiosk/login.php` → unlock with `BOOTH-001` / `123456` → search the citizen → **Enroll** → **Verify** (face check → fingerprint) → **Open Ballot** → cast a vote (the booth and the citizen must share a constituency)

If all steps pass, the app is demo-ready.
