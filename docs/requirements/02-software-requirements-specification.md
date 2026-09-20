# Software Requirements Specification — Online Voting System

> **Layer:** Software · **Process step:** ISO/IEC 29110 SI.2
> **Sources:** `01-business-requirements.md` (BR-n) · **Status:** as of 2026-09-20

Each requirement is **testable**, traced to a business requirement, and carries an
**implementation status** with file evidence. Status vocabulary is defined in
[`README.md`](README.md).

---

## A. Entry & constituency scoping

| ID | Requirement | Acceptance criterion | BR | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| **FR-1.1** | The portal shall require selection of a State/UT and Lok Sabha constituency before entry. | Given no constituency selected, when a user opens the portal, then the picker is shown. | BR-01 | Implemented | `index.php` |
| **FR-1.2** | The system shall support all 543 PC labels. | Given the constituency list, when it is loaded, then 543 entries are available. | BR-01 | Implemented | `index.php`, seed data |
| **FR-1.3** | Candidate display shall be scoped to the selected constituency. | Given constituency X, when the ballot/list is shown, then only X's candidates appear. | BR-01 | Implemented | `kiosk/_kiosk.php` `kiosk_candidates()`, `admin/dashboard.php` |

## B. Authentication

| ID | Requirement | Acceptance criterion | BR | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| **FR-2.1** | Login shall be passkey-first (WebAuthn / fingerprint). | Given a registered passkey, when the user signs in, then no password is required. | BR-09 | Implemented | `login.php`, `webauthn_options.php` |
| **FR-2.2** | A password fallback shall be available. | Given the passkey flow, when the user chooses "email & password", then a credential form appears. | BR-09 | Implemented | `login.php` |
| **FR-2.3** | Authentication shall require a second factor (one-time code). | Given valid credentials, when submitted, then an OTP step is shown before access. | BR-09 | Implemented | `login.php`, `otp_service.php` |
| **FR-2.4** | One-time codes shall expire after 5 minutes and be single-use. | Given a code issued >5 min ago, when used, then it is rejected. | BR-09 / BRULE-08 | Implemented | `otp_service.php` |
| **FR-2.5** | Cross-device passkey approval shall be supported. | Given a passkey on another device, when "use a phone" is chosen, then approval completes. | BR-09 | Partial (manual) | `webauthn_options.php` `login_begin` |
| **FR-2.6** | Legacy plain-text password comparison shall be removed. | Given a stored hash, when login is attempted, then only `password_verify` is accepted. | BR-07 | Planned | `login.php` (fallback present — gap) |

## C. Pre-vote declaration

| ID | Requirement | Acceptance criterion | BR | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| **FR-3.1** | A 3-step declaration gate shall precede dashboard access. | Given a logged-in voter who has not declared, when they reach the dashboard, then the gate blocks it. | BR-10 | Implemented | `voters/pre_vote_workflow.php` |
| **FR-3.2** | The dashboard shall remain locked until the declaration completes. | Given an incomplete declaration, when the dashboard URL is opened directly, then access is denied. | BR-10 | Implemented | `voters/dashboard.php` |

## D. Identity verification

| ID | Requirement | Acceptance criterion | BR | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| **FR-4.1** | A live face capture shall be compared to the registered photo. | Given a matching face, when captured, then the result is "match"; otherwise "no match". | BR-03 | Implemented | `voters/face_verify.php`, `face_verify_api.php` |
| **FR-4.2** | The face-match decision and threshold shall be enforced server-side. | Given a client claiming a match, when the server recomputes, then a client-only "verified" flag is ignored. | BR-02/BR-03 | Implemented | `voters/face_verify_api.php` |
| **FR-4.3** | Fingerprint/passkey verification shall be available as the authoritative check. | Given an enrolled credential, when the citizen verifies, then the result is recorded. | BR-03 | Implemented | `webauthn_options.php` `verify_*`, `voters/verify_biometrics.php` |
| **FR-4.4** | Every biometric attempt shall be logged with method, result, and context. | Given any attempt, when it completes, then a `biometric_logs` row exists. | BR-05 | Implemented | `biometric_logs` schema, `db.php` |
| **FR-4.5** | A one-time nonce shall bind a verification to its attempt. | Given a completed verification, when the nonce is replayed, then it is rejected. | BR-02 | Implemented | `webauthn.php`, `face_verify_api.php`, `kiosk/face_api.php` |
| **FR-4.6** | The booth face check shall require a blink-twice liveness challenge before matching. | Given a live capture, when the citizen blinks twice, then liveness passes and the match proceeds; otherwise the capture is not submitted. | BR-03 | Implemented | `kiosk/face_verify.php`, `js/blink-liveness.js` |

## E. Voter portal

| ID | Requirement | Acceptance criterion | BR | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| **FR-5.1** | The candidate list shall be view-only (no self-service ballot). | Given the voter dashboard, when viewed, then no vote-casting control exists. | BR-04 | Implemented | `voters/dashboard.php`; `voters/vote.php` deleted |
| **FR-5.2** | Voters shall be able to edit their profile. | Given a logged-in voter, when they edit and save, then the change persists. | BR-10 | Implemented | `voters/edit_profile.php` |
| **FR-5.3** | Voters shall manage (list/rename/remove) their own passkeys. | Given enrolled passkeys, when the voter removes one, then it no longer authenticates. | BR-09 | Implemented | `voters/add_passkey.php`, `voters/dashboard.php` |
| **FR-5.4** | Passkey enrollment shall be a booth activity, not self-service web enrollment. | Given the voter portal, when a user tries to enroll a new identity credential, then it is directed to a booth. | BR-04 / BRULE-09 | Partial | `kiosk/index.php`; admin-side enroll removed |

## F. Booth kiosk

| ID | Requirement | Acceptance criterion | BR | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| **FR-6.1** | A kiosk shall unlock with a booth code + PIN. | Given valid code/PIN, when submitted, then the kiosk session starts. | BR-08 | Implemented | `kiosk/login.php` |
| **FR-6.2** | The kiosk session shall have no administrative powers. | Given an unlocked kiosk, when an `admin/*` URL is opened, then access is denied. | BR-08 / BRULE-11 | Implemented | `kiosk/_kiosk.php` |
| **FR-6.3** | The kiosk shall auto-lock after 5 minutes idle. | Given 300 s of inactivity, when the next action occurs, then re-unlock is required. | BR-08 / BRULE-06 | Implemented | `kiosk/_kiosk.php` `KIOSK_IDLE_SECONDS` |
| **FR-6.4** | Unlock attempts shall be rate-limited. | Given 5 failed unlocks in 900 s from one IP, when retried, then it is locked out. | BR-08 / BRULE-07 | Implemented | `kiosk_auth_attempts`, `kiosk/login.php` |
| **FR-6.5** | Staff shall search citizens by name/email/EPIC. | Given a query, when submitted, then matching citizens are listed. | BR-03 | Implemented | `kiosk/index.php` |
| **FR-6.6** | Staff shall enroll a citizen's fingerprint credential at the booth. | Given a citizen and a successful scan, then a `passkeys` row is created with the booth's `booth_id`. | BR-03/BR-08 | Implemented | `webauthn_options.php`, `kiosk/index.php` |
| **FR-6.7** | Staff shall verify an enrolled citizen (identity check-in). | Given an enrolled citizen, when verified, then the outcome is recorded against the booth. | BR-03/BR-05 | Implemented | `webauthn_options.php` `verify_*` |
| **FR-6.8** | A booth verification shall require a face check AND a fingerprint for the same citizen. | Given only one of the two, when the ballot is requested, then it is refused. | BR-03/BR-08 | Implemented | `kiosk/_kiosk.php` `kiosk_verified_voter()`, `kiosk/face_api.php` |
| **FR-6.9** | Staff shall capture a reference face photo at the booth when none is usable. | Given a citizen whose photo is the placeholder, when a photo is captured, then `voters.face_photo` is set and used for later checks. | BR-03/BR-08 | Implemented | `kiosk/face_api.php` `enroll_photo` |

## G. Ballot casting & integrity

| ID | Requirement | Acceptance criterion | BR | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| **FR-7.1** | A ballot shall open only after a recent successful fingerprint verification AND a recent face check. | Given a missing or stale check (fingerprint > 120 s, face > 300 s), when the ballot is requested, then it is refused. | BR-03 / BRULE-03 | Implemented | `kiosk/_kiosk.php` `kiosk_verified_voter()`, `kiosk/ballot.php` |
| **FR-7.2** | A booth shall issue a ballot only if the citizen's constituency matches the booth's. | Given a mismatched constituency, when the ballot is requested, then it is refused with a reason. | BR-01 / BRULE-01 | Implemented | `kiosk_ballot_eligibility()` |
| **FR-7.3** | Only approved, not-yet-voted citizens shall be eligible. | Given `status != approved` or `has_voted = 1`, when submitting, then it is refused. | BR-02 / BRULE-02 | Implemented | `kiosk/vote.php` |
| **FR-7.4** | A candidate shall receive a vote only if they stand in that constituency. | Given a candidate from another constituency, when submitted, then the update affects no row. | BR-01 / BRULE-04 | Implemented | `kiosk/vote.php` |
| **FR-7.5** | Vote recording shall be atomic; a double submit cannot record twice. | Given two simultaneous submits, when both complete, then exactly one vote is counted. | BR-02 / BRULE-05 | Implemented | `kiosk/vote.php` conditional `UPDATE` |
| **FR-7.6** | The ballot authorization shall be consumed on submit. | Given a cast ballot, when submit is repeated, then authorization is gone. | BR-02 / BRULE-03 | Implemented | `kiosk_clear_ballot_auth()` |
| **FR-7.7** | The ballot page shall show remaining validity and expire visibly. | Given an open ballot, when the window lapses, then the UI redirects. | BR-10 | Implemented | `kiosk/ballot.php` timer |
| **FR-7.8** | State-changing kiosk actions shall be CSRF-protected. | Given a request without a valid CSRF token, when submitted, then it is rejected. | BR-07 | Implemented | `kiosk/_kiosk.php` `kiosk_csrf_*` |
| **FR-7.9** | A receipt shall be shown after a successful ballot. | Given a cast ballot, when the page reloads, then a receipt appears. | BR-10 | Implemented | `kiosk/index.php` receipt |

## H. Admin console

| ID | Requirement | Acceptance criterion | BR | Status | Evidence |
| --- | --- | --- | --- | --- | --- |
| **FR-8.1** | Admin access shall be role-based (`super_admin` / `admin`). | Given an `admin`, when a super-admin-only action is attempted, then it is denied. | BR-06 | Implemented | `admin/login.php`, `admins.role` |
| **FR-8.2** | Officials shall approve or reject voter registrations. | Given a pending voter, when approved, then they become eligible. | BR-06 | Implemented | `admin/verify_voters.php` |
| **FR-8.3** | Officials shall onboard a walk-in citizen. | Given name/email/EPIC/constituency, when submitted, then the record is created and shown as approved. | BR-06 | Implemented | `admin/onboard_voter.php` |
| **FR-8.4** | Duplicate email/EPIC entries shall be rejected with a pointer to the existing record. | Given an existing email, when onboarded again, then it is refused with a link. | BR-06/BR-07 | Implemented | `admin/onboard_voter.php` |
| **FR-8.5** | Officials shall manage candidates per constituency. | Given a constituency, when a candidate is added, then they appear on that ballot. | BR-06 | Implemented | `admin/dashboard.php` |
| **FR-8.6** | Officials shall manage booths and their constituency. | Given a booth, when its constituency is set, then booth/citizen eligibility follows. | BR-08 | Implemented | `admin/booths.php` |
| **FR-8.7** | Live results and turnout shall be visible. | Given cast ballots, when results are viewed, then counts and turnout reflect them. | BR-05/BR-06 | Implemented | `admin/dashboard.php` |
| **FR-8.8** | Enrollment/verification shall be attributable to a booth. | Given an event, when queried, then `booth_id` is present. | BR-05/BR-08 | Implemented | `biometric_logs`, `passkeys.booth_id` |

---

## I. Non-functional requirements

These four attributes are **mandatory consideration for every change** (see
`../process/policies/quality-assurance.md` §4).

| ID | Attribute | Requirement | Acceptance criterion | Status |
| --- | --- | --- | --- | --- |
| **NFR-1** | Correctness | One person, one vote, enforced atomically | Concurrent double submit records exactly one vote | Implemented |
| **NFR-2** | Auditability | Every biometric/ballot action is traceable | `biometric_logs` + `votes` allow reconstruction | Implemented |
| **NFR-3** | Security | No secret or personal data exposure; server-side trust boundary | Secret scan clean; no client-only verification path | **Partial** — secrets in source (G-01) |
| **NFR-4** | Privacy | No raw biometric retained; synthetic seed data only | No fingerprint image stored; seeds are synthetic | Implemented |

### Supporting quality requirements

| ID | Category | Requirement | Acceptance criterion | Status |
| --- | --- | --- | --- | --- |
| **NFR-5** | Usability | The demo flow completes unaided in ~8 min | `demo.md` run end-to-end | Implemented |
| **NFR-6** | Portability | Runs on PHP ≥ 8.0 + SQLite with no framework | `composer install`; `php -S localhost:8000` | Implemented |
| **NFR-7** | Deployability | WebAuthn works under HTTPS or localhost; RP ID pinnable | Passkey login works under `php -S`; RP ID from config | Implemented |
| **NFR-8** | Reliability | A kiosk requires live server connectivity | Failure is explicit, not a false success | Implemented (documented limitation, `kiosk-plan.md` §Phase 6) |
| **NFR-9** | Maintainability | Schema upgrades are idempotent and safe to re-run | Re-running `init_db.php`/`db.php` introduces no error | Implemented |
| **NFR-10** | Testability | Automated tests exist for integrity-critical logic | Test suite present and green in CI | **Not implemented** — gap G-02 |
| **NFR-11** | Operability | Empty/errored states are explained to the user | No blank screens; reasons shown | Implemented |

---

## J. Traceability matrix (summary)

| Business req | Software reqs | Status |
| --- | --- | --- |
| BR-01 Constituency scope | FR-1.1–1.3, FR-7.2 | Implemented |
| BR-02 One person one vote | FR-7.3–7.6, NFR-1 | Implemented |
| BR-03 Biometric identity | FR-4.1–4.5, FR-6.5–6.7, FR-7.1 | Implemented |
| BR-04 In-person only | FR-5.1, FR-5.4 | Implemented |
| BR-05 Auditability | FR-4.4, FR-8.7, FR-8.8, NFR-2 | Implemented |
| BR-06 Admin console | FR-8.1–8.7 | Implemented |
| BR-07 Data protection | NFR-3, NFR-4, FR-2.6 | **Partial** (G-01) |
| BR-08 Booth scoping | FR-6.1–6.4, FR-8.6, FR-8.8 | Implemented |
| BR-09 Login assurance | FR-2.1–2.5 | Implemented (FR-2.5 manual) |
| BR-10 Reproducible demo | FR-3.1, FR-3.2, FR-7.7, FR-7.9, NFR-5, NFR-11 | Implemented |
| BR-11 Honest degradation | — | Implemented |
| BR-12 No false certification | `README.md` declaration | Implemented |

---

## K. Verification status

| Verification | Method | Status |
| --- | --- | --- |
| Static (lint) | `php -l`, Composer validate | In CI |
| Unit / integration tests | PHPUnit | **None** — NFR-10 / G-02 |
| Acceptance | `demo.md` flow | Manual, documented |
| Secret/data hygiene | `scripts/process/check_secrets.sh` | **Failing** — G-01/G-03 |
| Process integrity | `scripts/process/process_audit.sh` | Passing |

> **Honest summary:** every functional requirement except two is implemented. The
> two open items are **security (NFR-3)** and **testability (NFR-10)** — which are
> exactly the Critical gaps G-01 and G-02 in the assessment. The requirements
> baseline agrees with the process appraisal. That agreement is the point of having
> both.

---

## Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial SRS instantiated from the repository (closes G-06 as a work product) |
