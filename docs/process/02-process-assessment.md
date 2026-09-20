# 02 — Process Assessment

> **SWEBOK KA10 sub-area 10.3 — Software Engineering Process Assessment**

This document records **how capable our processes are today** and **where the gaps
are**. It is the *Diagnosing* phase of the IDEAL cycle. The appraisal uses an
ISO/IEC 33001/33020-style capability scale, rating each process attribute from 0
to 3.

Assessment is **evidence-based**. Every rating cites an observable artifact — a
commit, a file, an absence that is itself evidence. An appraisal without evidence
is an opinion.

---

## 1. Capability scale

We use the ISO/IEC 33020 four-level scale, applied to each process attribute:

| Level | Name | Meaning |
| --- | --- | --- |
| **0** | Incomplete | The process is not performed, or its outcomes are not achieved. |
| **1** | Performed | The process is performed and produces its intended outcomes. |
| **2** | Managed | Performance is planned, monitored, and controlled; work products are managed. |
| **3** | Established | A defined process is used and tailored from a standard. |

Process Attributes (PA) appraised:

- **PA.1.1** Process performance — are outcomes achieved?
- **PA.2.1** Performance management — is it planned/controlled?
- **PA.2.2** Work product management — are outputs controlled?
- **PA.3.1** Process definition — is it a defined standard, tailored?

---

## 2. Appraisal baseline (cycle 0)

**Appraisal date:** 2026-09-20 · **Scope:** whole repository · **Method:** document
review + git history analysis + tooling inspection.

| Process | PA.1.1 | PA.2.1 | PA.2.2 | PA.3.1 | Rating | Evidence |
| --- | --- | --- | --- | --- | --- | --- |
| **SI.1 Initiation** | 1 | 0 | 1 | 0 | **1** | Work is initiated via commits/PRs; no scored intake. PR #1 merged (`b68d872`). |
| **SI.2 Requirements** | 1 | 0 | 1 | 0 | **1** | Baseline instantiated in `docs/requirements/` (BR + SRS + traceability matrix). Not yet planned per increment, nor a defined/tailored standard. |
| **SI.3 Design** | 1 | 0 | 1 | 0 | **1** | `kiosk-plan.md` is a genuine design document with phases and a decision record. Not applied uniformly. |
| **SI.4 Construction** | 2 | 1 | 1 | 0 | **1** | 11 commits on `main`; consistent craftsmanship (`db.php` idempotent migrations) but no branch policy or definition of done. |
| **SI.5 Integration & Test** | 0 | 0 | 0 | 0 | **0** | **Zero automated tests.** CI workflow removed at `69fafed`. No test plan. |
| **SI.6 Delivery** | 1 | 0 | 1 | 0 | **1** | Increments land on `main`; `README.md`/`demo.md` kept current. No versioning/tags. |
| **PM.1 Planning** | 1 | 0 | 0 | 0 | **0** | `kiosk-plan.md` tables act as phase plans; no per-increment plans, estimates, or ownership. |
| **PM.2 Executing** | 1 | 0 | 1 | 0 | **1** | Work tracked loosely via commits; 1 merged PR. |
| **PM.3 Control** | 0 | 0 | 0 | 0 | **0** | No metrics collected at all (see `03-process-measurement.md`). |
| **PM.4 Closure** | 0 | 0 | 0 | 0 | **0** | No closure/retrospective records. |
| **CM (supporting)** | 1 | 0 | 1 | 0 | **1** | Git is used, but no `.gitignore`; `voting_system.db` is a tracked binary artifact. |
| **QA (supporting)** | 0 | 0 | 0 | 0 | **0** | No QA policy; no verification activity beyond manual demo. |
| **Security (supporting)** | 1 | 0 | 0 | 0 | **1** | Server-side verification is strong (`includes/webauthn.php`); **live SMTP credentials committed in source.** |

### Aggregate

- **Processes at Level 0:** 5 of 13 *(SI.5, PM.1, PM.3, PM.4, QA)*
- **Processes at Level 1:** 8 of 13
- **Processes at Level ≥ 2:** 0
- **Maturity statement:** the entity performs work and produces a working product,
  but **the process is carried in the heads of two people and in prose docs.** It
  is *performed* at best and *managed* nowhere. This is the expected starting point
  for IDEAL *Diagnosing* and is the baseline against which improvement is measured.

---

## 3. Strengths (preserve these)

A good appraisal records what already works, so improvement does not damage it.

1. **Strong security engineering in the crypto path.** `includes/webauthn.php`
   verifies challenge, origin, signature, and sign-counter server-side. This is
   genuinely above the maturity of everything around it.
2. **Honest documentation.** `kiosk-plan.md` records *decisions and rejected
   alternatives* (the "Why not the Go rewrite" section) — a rare and valuable
   practice, effectively an Architecture Decision Record.
3. **Idempotent schema upgrades** in `db.php` — a real CM discipline at the code
   level that the rest of the repository lacks at the process level.
4. **README already self-identifies risks** — the "Known demo-grade gaps" section
   is the seed of this gap register.

---

## 4. Gap register

Prioritized by **risk × leverage**. Each gap becomes an entry in the improvement
backlog (`04-process-improvement.md`).

| ID | Gap | Process | Severity | Traces to |
| --- | --- | --- | --- | --- |
| **G-01** | Live SMTP credentials committed in `login.php` / `register.php`; no secret hygiene | Security | **Critical** | PA.2.2 |
| **G-02** | No automated tests; no way to verify a change didn't break voting integrity | SI.5 / QA | **Critical** | PA.1.1 |
| **G-03** | No `.gitignore`; `voting_system.db` (a live DB with voter data) is tracked; `vendor/`, `.DS_Store` unmanaged | CM | **High** | PA.2.2 |
| **G-04** | CI pipeline removed (`69fafed`) — no automated gate of any kind | SI.5 | **High** | PA.2.1 |
| **G-05** | `includes/config.php` (rotating ngrok RP ID) is untracked but only by luck; the passkey-orphaning risk from `kiosk-plan.md` §"What is missing" #3 is live | CM / Security | **High** | PA.2.2 |
| **G-06** | ~~No requirements work product; acceptance criteria live only in prose~~ — **closed**: instantiated in `../requirements/` | SI.2 | ~~Medium~~ **Closed** | PA.1.1 |
| **G-07** | No metrics collected; no way to know if process change helps | PM.3 | **Medium** | PA.2.1 |
| **G-08** | No change-request/defect intake discipline; no PR or issue templates | SI.1 | **Medium** | PA.2.1 |
| **G-09** | No closure/retrospective record; lessons are not captured | PM.4 | **Low** | PA.3.1 |
| **G-10** | No versioning/tags or release notes | SI.6 | **Low** | PA.2.2 |

---

## 5. Conformance to the chosen profile

Assessment against **ISO/IEC 29110 Basic** means asking: are the profile's required
work products and outcomes present?

| 29110 Basic requirement | Status before framework | Status after this cycle |
| --- | --- | --- |
| Work products identified and controlled | ✗ | ✓ templates + CM policy |
| Requirements elicitation & analysis | ✗ | ✓ SI.2 + template + instantiated baseline (`../requirements/`) |
| Architecture/design defined | ~ (in `kiosk-plan.md`) | ✓ SI.3 + template |
| Construction & unit verification | ~ | ✓ SI.4 + test process |
| Integration & testing performed | ✗ | ◐ CI restored; tests pending (G-02) |
| Product delivery controlled | ~ | ✓ SI.6 |
| Project planning performed | ✗ | ✓ PM.1 + template |
| Progress controlled & measured | ✗ | ◐ metrics defined; collection pending |
| Traceability & configuration management | ✗ | ✓ CM policy |
| Quality assurance performed | ✗ | ✓ QA policy |

Key: ✓ satisfied · ◐ partially satisfied · ✗ not satisfied

**Result: the framework closes the *definition* gap in one cycle. The remaining
◐ items are behavioural, not documentary — they depend on discipline, which is why
they are tracked as metrics, not as completed checkboxes.**

---

## 6. Next appraisal

Re-appraise at the end of the next IDEAL *Leveraging* phase (or when 3 of the
Critical/High gaps are closed). Re-run:

```bash
bash scripts/process/process_audit.sh
```

and update §2 with evidence. Ratings must move only on evidence.

---

## 7. Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial appraisal (cycle 0) |
