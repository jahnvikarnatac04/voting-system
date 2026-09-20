# Two-Booth Demo — Presentation Script & Screenshot Checklist

A storyboarded demo of the geofenced voting rules on the **real seeded
database**: two polling booths in two parliamentary constituencies, a citizen
refused at the wrong booth, one-person-one-vote, and the face biometric gate.

| | |
| --- | --- |
| **Runtime** | ~14–16 min live (~10 min if you skip Acts 4 and 8) |
| **Setup** | ~10 min, done **before** the audience arrives |
| **Devices** | one laptop (two browser profiles) + one camera |
| **Companion** | [`demo.md`](demo.md) is the short general demo; this is the two-booth version |

---

## 0. What the audience should conclude

By the end, three claims must be *visibly* true:

1. **Booths are real, scoped terminals** — two booths, each unlocked for its own
   location, with no path into the admin console.
2. **The region rule is enforced server-side** — a booth only issues a ballot to a
   citizen of *its own* constituency, and never twice to the same citizen.
3. **Identity is verified server-side** — fingerprint and face (portal *and*
   booth) are decided on the server, with a single-use nonce and an audit trail.

---

## 1. Cast and accounts

| Role | Who | Credential |
| --- | --- | --- |
| Election officer | `superadmin` | `SuperSecret123!` (or `admin` / `admin123`) |
| **Booth 001** — Varanasi (PC-77) | code `BOOTH-001` | PIN `123456` |
| **Booth 002** — Hyderabad (PC-09) | code `BOOTH-002` | PIN `654321` |
| **Citizen V** — Arjun Singh (Varanasi) | `you@example.com` | `Voter@123` |
| **Citizen X** — Suresh Joshi (Varanasi) | `suresh.joshi7@gmail.com` | `Voter@123` |
| **Citizen H** — Rohit Verma (Hyderabad) | `rohit.verma4@gmail.com` | `Voter@123` |
| **Citizen N** — monika (no constituency) | `monikakarnatac@gmail.com` | `Voter@123` |

Seeded candidates you will see on the ballots (real 2024 candidates):

- **Varanasi (PC-77)** — Narendra Modi (BJP), Ajay Rai (INC), Ather Jamal Lari (BSP), +4
- **Hyderabad (PC-09)** — Asaduddin Owaisi (AIMIM), Madhavi Latha Kompella (BJP), Mohammed Waliullah Sameer (INC), +5

> The two candidate lists look completely different. That contrast is the point —
> it is the visible proof the booth is issuing the *right region's* ballot.

---

## 2. Stage layout (do this before the audience arrives)

WebAuthn passkeys are bound to the **hostname**, and a kiosk session is bound to
**one booth per browser profile**. Set up two profiles so both booths can be on
screen at once:

| Window | Profile | URL |
| --- | --- | --- |
| **Booth 001** (left) | Chrome *Profile 1* (or a normal window) | `http://localhost:8000/kiosk/login.php` |
| **Booth 002** (right) | Chrome *Profile 2* (or an incognito window) | `http://localhost:8000/kiosk/login.php` |
| **Admin + voter portal** (background tab) | either | `http://localhost:8000/admin/login.php` |

Three rules that prevent 90% of live-demo failures:

- **Use `localhost`, never `127.0.0.1`.** They are different RP IDs; a passkey
  enrolled under one will not work under the other.
- **Never change the hostname mid-demo.** Switching to a tunnel URL orphans every
  enrolpasskey already captured.
- **Keep both kiosk windows loaded** before you start; reloading loses nothing but
  costs time on stage.

---

## 3. Pre-flight (≈10 min, before the audience)

### 3.1 Back up — so you can reset after a rehearsal

```bash
cp voting_system.db voting_system.db.demo-backup
```

### 3.2 Configure the two booths and the democrats

```bash
php scripts/demo/prep_two_booths.php          # idempotent; safe to re-run
php scripts/demo/prep_two_booths.php --status # confirm the result
```

Expected:
```
  • booth BOOTH-001 updated → Varanasi (PC-77) (PIN 123456)
  • booth BOOTH-002 created → Hyderabad (PC-09) (PIN 654321)
  • Citizen V (you@example.com) → Varanasi (PC-77)  [approved]
  ...
READY — run: php -S localhost:8000
```

> If a rehearsal already used these citizens, re-run with `--unvote` to clear
> their voted flag. Ballot counts on candidates cannot be rewound (ballots are
> anonymous) — restore the backup in §3.1 for a mathematically clean slate.

### 3.3 Make the OTP land somewhere you can read

```bash
sqlite3 voting_system.db "UPDATE voters SET email='YOUR-REAL-INBOX@example.com' WHERE email='you@example.com';"
```

Shortcut: log in as **`jahnvikarnatac04@gmail.com`** (Citizen H's neighbour,
id 5) — it is the SMTP sending account, so the OTP arrives in its own inbox.

### 3.4 Make the face actually match

Seeded photos are the generic `default.png` — face-api cannot match against it.
The **portal** face screen (Act 7) reads the profile photo, so upload a real selfie
once for the citizen you will demo there. (The **booth** face check can instead
capture a reference photo on the spot, so other citizens need no pre-upload.)

1. `localhost:8000` → pick **Uttar Pradesh → Varanasi (PC-77)**
2. Voter login → `you@example.com` / `Voter@123` → OTP
3. **Edit Profile** → upload a clear, well-lit selfie → save

### 3.5 Camera and browser

- Pre-approve camera access, and keep the demo tab **frontmost**.
- Zoom the browser to ~110% so the ballot and refusal text read from the back row.

### 3.6 Start the app

```bash
php -S localhost:8000
```

---

## 4. The script

Each act has **SAY** (narration), **DO** (actions), **EXPECT** (what must appear),
and **📸** (the shot to capture).

---

### Act 0 — Two booths exist *(~60 s)*

**SAY** — "The election runs across many constituencies. Each physical polling
station is a *booth*, and a booth belongs to exactly one constituency."

**DO** — Admin console → `admin/login.php` → `superadmin` / `SuperSecret123!` →
**📍 Booths & Kiosks**.

**EXPECT** — A list containing **BOOTH-001 (Varanasi — Uttar Pradesh)** and
**BOOTH-002 (Hyderabad — Telangana)**, with different states and constituencies.

**📸 Shot 01** — the booths list showing both booths and their constituencies.

---

### Act 1 — Booth 001 unlocks, scoped to Varanasi *(~45 s)*

**SAY** — "A kiosk is unlocked with a *booth code and PIN*, not an admin login.
Whatever it does is attributed to this location — and it has no admin powers at all."

**DO** — Left window → `kiosk/login.php` → `BOOTH-001` / `123456` → **Unlock Kiosk**.

**EXPECT** — The kiosk opens showing **Booth 001 — Varanasi**. Header says
"Booth Biometric Kiosk"; the booth's name/constituency is displayed.

**📸 Shot 02** — the unlocked kiosk naming Booth 001 / Varanasi.

---

### Act 2 — Enroll two citizens' fingerprints at Booth 001 *(~90 s)*

**SAY** — "Citizens can't self-register online for security reasons. Their
fingerprint credential is bound to their account **in person**, at the booth."

> If a citizen still has the generic placeholder photo, the first face check will
> offer **Capture Reference Photo** — take it once here; it is stored against the
> citizen for later checks. (Arjun already has a real photo from §3.4.)

**DO** — search **"Arjun"** → **Enroll** → scan / passkey prompt.
Then search **"Suresh"** → **Enroll** → scan.

**EXPECT** — A success state for each; the kiosk auto-disarms. The enrolled
credential is a *public* key — no fingerprint image is ever stored.

> Enrolling **Suresh** now is deliberate: he is a *Varanasi* citizen, and in Act 5
> we will take him to the *Hyderabad* booth to be refused.

**📸 Shot 03** — the enrollment success screen.

---

### Act 3 — Verify and cast the Varanasi ballot *(~2 min)* ← core

**SAY** — "Now the citizen proves identity. Two checks are required — a face
check and a fingerprint — and only then does the ballot open, for two minutes."

**DO**
1. search **"Arjun"** → **Verify**
2. → **Step 1 — Start Face Check** → he blinks twice → the match is decided server-side
3. back at the kiosk → **Step 2 — Verify Fingerprint** → scan / passkey
4. → **🗳️ Open Ballot for Arjun Singh**
5. point at the candidate list — read out two names — then select one → confirm

**EXPECT**
- Index shows **"Open Ballot for Arjun Singh"** with **Constituency: Varanasi (PC-77)** and a countdown (~120 s).
- The ballot lists **Varanasi** candidates: **Narendra Modi**, **Ajay Rai**, **Ather Jamal Lari**, …
- After confirming, a **receipt** appears; the kiosk is ready for the next citizen.

**📸 Shot 04** — verification succeeded + "Open Ballot".
**📸 Shot 05** — the ballot showing **Varanasi** candidates. *(This is the shot that pairs with Shot 08.)*
**📸 Shot 06** — the receipt.

---

### Act 4 — One person, one vote *(~30 s)*

**SAY** — "Watch what happens if he tries again."

**DO** — search **"Arjun"** → **Verify** → **Open Ballot** → attempt to vote again.

**EXPECT** — A refusal: **"This citizen has already voted."** No second ballot is
issued — the one-vote mark is an atomic database condition, so even a double
submit or a race cannot record twice.

> Optional 20-second bonus: search **"monika"** (Citizen N) → Verify → the kiosk
> says **"No constituency is assigned to this citizen."**

**📸 Shot 07** — the "already voted" refusal.

---

### Act 5 — The region rule: Varanasi citizen at the Hyderabad booth *(~90 s)* ← money shot

**SAY** — "Same citizen. Same device. Different polling station. This is the rule
that makes the ballot trustworthy."

**DO** — Right window → `kiosk/login.php` → `BOOTH-002` / `654321` → **Unlock Kiosk**
→ search **"Suresh"** (a *Varanasi* citizen) → **Verify** → face check → fingerprint.

**EXPECT** — Identity verification **succeeds** (he is who he says he is), and then:

> **"This citizen's constituency (Varanasi (PC-77)) does not match this booth (Hyderabad (PC-09))."**

No ballot is offered. The check is **server-side** — it is not a hidden button or a
CSS trick; `kiosk/vote.php` re-checks it inside the transaction.

**📸 Shot 08** — the mismatch refusal naming both constituencies. *(The strongest single image of the demo.)*

---

### Act 6 — Booth 002 issues its own region's ballot *(~90 s)*

**SAY** — "Now a citizen who *does* belong here."

**DO** — still in the Booth 002 window → search **"Rohit"** → **Enroll** → scan
→ search **"Rohit"** → **Verify** (face check → fingerprint) → **Open Ballot** → select → confirm.

**EXPECT** — The ballot lists **Hyderabad** candidates: **Asaduddin Owaisi**,
**Madhavi Latha Kompella**, … — **completely different names and parties** from
Act 3.

**📸 Shot 09** — the Hyderabad ballot. *(Put this beside Shot 05: same system, different region, different ballot.)*
**📸 Shot 10** — the receipt.

---

### Act 7 — Face biometric, decided server-side *(~2 min)*

**SAY** — "The portal adds a second identity check. The browser computes a face
descriptor, but the **match decision is made on the server** — the client cannot
mark itself verified."

**DO**
1. New tab → `localhost:8000` → **Uttar Pradesh → Varanasi (PC-77)**
2. **Voter Login** → `you@example.com` / `Voter@123` → OTP from the inbox
3. complete the declaration gate → dashboard → **Identity verification**
4. let the camera match → then deliberately look away / cover the camera

**EXPECT**
- A live capture compared to the registered photo, at a **0.55** distance threshold.
- A **match** result, then a **no-match** result, each with the computed distance.
- Points to make aloud: the one-time nonce is single-use, and **every attempt is
  written to `biometric_logs`** with the outcome and IP.

**📸 Shot 11** — the live face-capture overlay.
**📸 Shot 12** — a successful match (with distance).
**📸 Shot 13** — a deliberate mismatch.

> **Say if asked:** the booth also runs a face check — that is the one used in
> Acts 3/5/6. This portal screen is the *same* check wired to a voter session
> rather than a kiosk, and it authorizes nothing by itself. All three paths
> (portal face, kiosk face, kiosk fingerprint) share the server-side decision and
> the `biometric_logs` audit trail; only the kiosk pair feeds
> `kiosk_verified_voter()`.

---

### Act 8 — Results reflect the ballots *(~45 s)*

**SAY** — "And the count reflects exactly what was cast — one ballot per citizen,
in each constituency."

**DO** — Admin console → **Results / Turnout** → show Varanasi and Hyderabad.

**EXPECT** — Both constituencies show turnout; the candidates voted for are
incremented by one. Two booths, two regions, one coherent result.

**📸 Shot 14** — live results for both constituencies.

---

## 5. Screenshot checklist

Capture these as you go; the pairing in brackets is what makes the story land.

| # | Filename | When | Must be visible | Why it matters |
| --- | --- | --- | --- | --- |
| 01 | `01-two-booths.png` | Act 0 | Both booth codes + different constituencies | Proves two distinct booths |
| 02 | `02-kiosk-unlocked.png` | Act 1 | Booth 001 / Varanasi | Proves per-booth scoped unlock |
| 03 | `03-enrolled.png` | Act 2 | Enrollment success | Proves in-person enrollment |
| 04 | `04-verified-open-ballot.png` | Act 3 | "Open Ballot for Arjun Singh" + countdown | Proves verify → ballot authorization |
| 05 | `05-ballot-varanasi.png` | Act 3 | Varanasi candidate names | **Region A ballot** |
| 06 | `06-receipt.png` | Act 3 | Vote recorded receipt | Proves the cast |
| 07 | `07-already-voted.png` | Act 4 | "already voted" refusal | Proves one person, one vote |
| 08 | `08-region-mismatch.png` | Act 5 | Both constituencies in the refusal | **The regional rule** |
| 09 | `09-ballot-hyderabad.png` | Act 6 | Hyderabad candidate names | **Region B ballot** |
| 10 | `10-receipt-booth2.png` | Act 6 | Receipt at Booth 002 | Proves cross-booth works when eligible |
| 11 | `11-face-capture.png` | Act 7 | Live camera capture UI | Proves the biometric gate runs |
| 12 | `12-face-match.png` | Act 7 | Match + distance value | Proves the server decision |
| 13 | `13-face-mismatch.png` | Act 7 | No-match result | Proves it is a real check |
| 14 | `14-results.png` | Act 8 | Turnout/results for both PCs | Proves the count |

**Hero image:** Shot 08, with Shots 05 and 09 side by side.

---

## 6. Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| No passkey prompt appears | Not a secure context, or RP ID ≠ URL host | Use `http://localhost:8000` exactly — not `127.0.0.1`, not another hostname |
| Ballot says "no candidates are configured" | Booth/citizen constituency string mismatch | Re-run `prep_two_booths.php`; strings must match exactly (incl. the `(PC-nn)` suffix) |
| Kiosk refuses to unlock | Rate limited: 5 failures / 15 min per IP | Wait, or clear `kiosk_auth_attempts` |
| OTP never arrives | Seeded address is synthetic | Re-point the email (§3.3) or use the SMTP account's own inbox |
| Face never matches | Profile photo is `default.png`, or bad lighting | Upload a real selfie (§3.4); face the light |
| Ballot window closed | >120 s elapsed after verification | Re-verify; it is only a two-minute window |
| "already voted" on a fresh run | A rehearsal consumed the vote | `prep_two_booths.php --unvote`, or restore the backup |

---

## 7. Reset and teardown

```bash
# restore the pre-demo database exactly
cp voting_system.db.demo-backup voting_system.db

# or, without restoring: clear only the demo citizens' voted flag
php scripts/demo/prep_two_booths.php --unvote

# check state at any time
php scripts/demo/prep_two_booths.php --status

rm voting_system.db.demo-backup        # once you no longer need it
```

---

## 8. Timing budget

| Segment | Time |
| --- | --- |
| Acts 0–2 (booths, unlock, enroll) | 3.5 min |
| Acts 3–6 (ballot, one-vote, mismatch, second booth) | 6.5 min |
| Act 7 (face) | 2 min |
| Act 8 (results) | 45 s |
| **Core demo (skip 4 & 8)** | **~10 min** |
| **Full run** | **~14–16 min** |

---

## 9. Closing narrative

> "Two polling stations in two constituencies. Each terminal unlocks for its own
> location only. Enrolment happens in person. A citizen is verified by fingerprint
> and, in the portal, by face — both decided on the server. And a booth will only
> ever hand a ballot to a citizen of *its own* constituency, exactly once. Same
> software, different regions, and the count is the count."

---

*Preparation script: [`scripts/demo/prep_two_booths.php`](scripts/demo/prep_two_booths.php) ·
Rule definitions: [`docs/requirements/02-software-requirements-specification.md`](docs/requirements/02-software-requirements-specification.md) (FR-7.x) ·
Automated proof of these rules: [`scripts/tests/e2e_booth_voting.php`](scripts/tests/e2e_booth_voting.php)*
