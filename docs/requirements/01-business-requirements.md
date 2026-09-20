# Business Requirements — Online Voting System (Lok Sabha Portal)

> **Layer:** Business / stakeholder · **Feeds:** ISO 29110 SI.2
> **Derived from:** `README.md`, `demo.md`, `kiosk-plan.md`, `schema.sql`, `init_db.php`, source

---

## 1. Business context

The organisation is running a **constituency-scoped parliamentary (Lok Sabha)
election portal** as an educational demonstration of election-grade integrity
patterns: in-person biometric enrollment, server-enforced one-person-one-vote,
booth-scoped terminals, and a complete audit trail.

The critical distinction that shapes every requirement below: **this is not a
remote internet-voting system.** Enrollment and voting are **in person at a polling
booth**, mirroring how the integrity of a physical election is anchored to a
physical location. The online portal exists to *view* the certified ballot, verify
identity, and manage the citizen's profile — not to cast a self-service vote.

> **Demo-grade declaration.** The system is explicitly **not certified for real
> elections** and is not a product of the Election Commission of India. Business
> requirement BR-12 makes this explicit and binding.

---

## 2. Stakeholders

| Stakeholder | Role | Primary concern |
| --- | --- | --- |
| **Election Commission (super_admin)** | Chief Election Officer | Ballot integrity, turnout, full control |
| **Verification Officer (admin)** | Assists operations | Approve voters, manage candidates, booths |
| **Polling Booth Officer** | Runs the kiosk terminal | Enroll/verify citizens quickly, issue ballots |
| **Voter / Citizen** | Casts one ballot | A working, private, trustworthy vote |
| **Electoral Auditor** | Reviews the process | Traceability of every action |
| **Instructor / Assessor** | Grades the demo | `demo.md` runs end-to-end |
| **Maintainer / Developer** | Evolves the system | A process they can actually follow |

---

## 3. Business goals (requirements)

### BR-01 — Conduct a constituency-scoped election
A citizen selects their State/UT and Lok Sabha constituency on entry; the portal,
candidates, and ballots are all scoped to that constituency. All 543 PC labels are
supported.
**Success:** a citizen sees only candidates standing in their own constituency.

### BR-02 — Guarantee one person, one vote
The system must make it impossible for a citizen to cast more than one ballot,
including under concurrent/double submission.
**Success:** a second submit cannot increment any candidate's count.

### BR-03 — Establish identity by in-person biometric verification
A citizen's identity is verified against an enrolled credential (fingerprint /
WebAuthn passkey), optionally corroborated by a live face match, at a booth.
**Success:** a ballot can only follow a recent, successful verification.

### BR-04 — Operate in person, at a location (no remote self-service ballot)
Enrollment and ballot casting happen at a physical booth. The online portal is
view-only regarding the ballot.
**Success:** there is no path to cast a ballot without a booth kiosk.

### BR-05 — Maintain a complete, auditable record
Every enrollment, verification, and ballot action is attributed and logged.
**Success:** an auditor can reconstruct who did what, where, and when.

### BR-06 — Give officials a control console
Election officials can manage the electorate, candidates, booths, and see live
results/turnout, under role-based access.
**Success:** a `super_admin` can onboard a walk-in citizen; an `admin` cannot
perform super-admin-only actions.

### BR-07 — Protect personal and biometric data
Biometric data must not be stored as raw material; personal data must be minimal
and not exposed.
**Success:** the system stores only a public credential plus an audit outcome — no
fingerprint image, no face image beyond the audit snapshot policy.

### BR-08 — Support multiple booths with scoped terminals
Each booth runs a locked-down kiosk session with no administrative reach; actions
are attributed to the booth.
**Success:** a kiosk session cannot access the admin console or any other voter's
ballot, and every enrollment/verification records its `booth_id`.

### BR-09 — Provide modern login assurance
Voter login is passkey-first (fingerprint), with an email + password fallback and
a second factor (one-time code).
**Success:** both paths work; the second factor expires and cannot be reused.

### BR-10 — Keep the demonstration reproducible
A new operator can initialise, seed, and run the system and complete the demo flow
from documentation alone.
**Success:** the `demo.md` flow completes in ~8 minutes on a clean checkout.

### BR-11 — Degrade honestly (no silent failure)
Where a capability is demo-grade (e.g. SMS delivery), the system must not present a
false success.
**Success:** the SMS path is a declared, visible limitation, not a claimed feature.

### BR-12 — Never misrepresent certification
The system must never present itself as certified for real elections.
**Success:** the repository and UI carry the educational/demo declaration.

---

## 4. Business rules

| ID | Rule | Enforced in |
| --- | --- | --- |
| **BRULE-01** | A booth issues a ballot **only** when the citizen's constituency equals the booth's constituency | `kiosk/_kiosk.php` `kiosk_ballot_eligibility()`, `kiosk/vote.php` |
| **BRULE-02** | A citizen must be **approved** and must **not** have voted | `kiosk/vote.php` |
| **BRULE-03** | A ballot authorization **expires 120 s** after successful fingerprint verification and is **consumed on submit** | `kiosk/_kiosk.php` `KIOSK_BALLOT_WINDOW`, `kiosk/vote.php` |
| **BRULE-04** | A candidate may receive a vote **only if they stand in that constituency** | `kiosk/vote.php` conditional `UPDATE candidates … AND constituency = ?` |
| **BRULE-05** | The vote is recorded by an **atomic conditional update** — `has_voted` 0 → 1 | `kiosk/vote.php` |
| **BRULE-06** | The kiosk **auto-locks after 300 s** of inactivity | `kiosk/_kiosk.php` `KIOSK_IDLE_SECONDS` |
| **BRULE-07** | Kiosk unlock is rate-limited: **5 failures / 900 s** per IP | `kiosk/_kiosk.php`, `kiosk_auth_attempts` |
| **BRULE-08** | Two-factor codes **expire after 5 minutes** | `login.php`, `otp_service.php` |
| **BRULE-09** | Online self-registration is **closed**; enrollment is in person at a booth | `register.php` (disabled), `kiosk/index.php` enroll |
| **BRULE-10** | Face match is decided **server-side** against a distance threshold | `voters/face_verify_api.php`, flow in `README.md` (0.55) |
| **BRULE-11** | A kiosk session carries **no admin identity** | `kiosk/_kiosk.php` (session scoping) |

---

## 5. Constraints

| ID | Constraint |
| --- | --- |
| **CON-01** | PHP ≥ 8.0 with `pdo_sqlite`, `openssl`, `curl`, `mbstring` |
| **CON-02** | SQLite single-file database (`voting_system.db`) |
| **CON-03** | No web framework; Bootstrap 5 + vanilla JS front end |
| **CON-04** | WebAuthn requires a **secure context** (HTTPS or localhost) |
| **CON-05** | The WebAuthn **RP ID must be stable and pinned** — a rotating tunnel hostname orphans every enrolled passkey |
| **CON-06** | A booth kiosk requires **live connectivity** to the server (server-issued challenge) |
| **CON-07** | Demo deployment only; no production certification |

---

## 6. Scope

### In scope
Constituency selection · passkey and password login with 2FA · declaration gate ·
identity verification (face + fingerprint) · view-only candidate display · booth
kiosk unlock/enroll/verify/ballot · booth-scoped audit · admin console
(voters, candidates, booths, results, turnout) · user documentation.

### Out of scope (deliberate)
| Excluded | Reason |
| --- | --- |
| Remote self-service voting | Removed by design — integrity (BR-04); `voters/vote.php` deleted |
| Online self-registration | Security — enrollment is in person (BRULE-09) |
| Raw fingerprint capture / external scanners | Phone sensors never expose an image (`kiosk-plan.md` decision) |
| Working SMS OTP | Demo-grade only; key is a placeholder (BR-11) |
| Real-election certification | BR-12 |
| Production secret management | Tracked as gap G-01 |

---

## 7. Success criteria (demo acceptance)

1. A citizen can select a constituency and reach the portal.
2. A citizen can log in (passkey or password + 2FA) and complete the declaration.
3. Identity verification runs server-side with a visible pass/fail.
4. An official can onboard a walk-in citizen.
5. A booth kiosk can unlock, enroll, verify, and issue a ballot to an eligible citizen.
6. A second ballot attempt is refused.
7. Live results/turnout reflect the cast ballot.
8. The whole flow completes from `demo.md` without a walkthrough.

---

## Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial business baseline |
