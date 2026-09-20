# 03 — Process Measurement

> **SWEBOK KA10 sub-area 10.4 — Software Engineering Process Measurement**

You cannot improve a process you do not measure. This document derives our metrics
using **Goal-Question-Metric (GQM)** — every metric exists to answer a question,
and every question exists to serve a stated goal. No metric is collected "because
it's easy."

The goals below are chosen to address the gap register in
`02-process-assessment.md` §4.

---

## 1. GQM derivation

### Goal 1 — Establish verifiable change

> *Improve the verifiability of changes so that voting-integrity regressions are caught before delivery.*

| Question | Metric | Definition | Source | Target |
| --- | --- | --- | --- | --- |
| Q1.1 Are changes covered by automated tests? | **Test coverage of changed lines** | % of added/changed executable lines exercised by tests in the same PR | CI report | ≥ 60% and rising |
| Q1.2 Do changes pass automated gates? | **CI pass rate on `main` pushes** | green builds ÷ total builds per month | CI history | 100% |
| Q1.3 Do tests exist at all? | **Test count** | number of automated test cases | repo | > 0 this cycle |

*Traces:* G-02, G-04

### Goal 2 — Protect sensitive assets

> *Eliminate exposure of secrets and uncontrolled data, to preserve voter-data integrity and passkey continuity.*

| Question | Metric | Definition | Source | Target |
| --- | --- | --- | --- | --- |
| Q2.1 Are secrets committed? | **Tracked-secret count** | matches from the secret scanner in tracked files | `scripts/process/check_secrets.sh` | 0 |
| Q2.2 Is uncontrolled data committed? | **Tracked data-artifact count** | committed DB files / images / archives | `.gitignore` audit | 0 |
| Q2.3 Is the RP ID stable? | **RP-ID churn events per release** | times `WEBAUTHN_RP_ID` changed | git log | 0 between releases |

*Traces:* G-01, G-03, G-05

### Goal 3 — Make work visible and controlled

> *Make the state of work explicit, so planning and control are possible.*

| Question | Metric | Definition | Source | Target |
| --- | --- | --- | --- | --- |
| Q3.1 Is intake disciplined? | **Changes with a linked issue** | PRs referencing a Change Request ID ÷ total PRs | PRs | ≥ 90% |
| Q3.2 Is work planned? | **Increments with a recorded plan** | changes with a Plan/CR ÷ total increments | issues | ≥ 90% |
| Q3.3 Are reviews happening? | **Review coverage** | changes with ≥1 non-author approval ÷ total changes | PRs | 100% |
| Q3.4 Are lessons captured? | **Closure notes per release** | closures with recorded lessons ÷ releases | issues | 100% |

*Traces:* G-06, G-08, G-09

### Goal 4 — Keep the process itself healthy

> *Ensure the framework is used and improving, not decaying.*

| Question | Metric | Definition | Source | Target |
| --- | --- | --- | --- | --- |
| Q4.1 Is the PAL intact? | **Process audit result** | pass/fail of required-asset check | `scripts/process/process_audit.sh` | pass |
| Q4.2 Is the baseline improving? | **Mean process rating** | average capability level across appraised processes | `02-process-assessment.md` §2 | rising each cycle |
| Q4.3 Are gaps closing? | **Open Critical/High gaps** | count in the gap register | gap register | → 0 |

*Traces:* G-07, PA.3.1

---

## 2. Metric definitions register

To keep measurement stable, each metric has one canonical definition. Do not
redefine a metric mid-cycle; change it via a Change Request.

| Metric | Type | Unit | Collection | Frequency | Owner |
| --- | --- | --- | --- | --- | --- |
| Test coverage of changed lines | Leading | % | CI | per PR | Developer |
| CI pass rate | Leading | % | CI | monthly | PM |
| Test count | Lagging | count | repo | per release | Developer |
| Tracked-secret count | Lagging | count | scanner | per PR | Reviewer |
| Tracked data-artifact count | Lagging | count | audit | per cycle | Process Owner |
| RP-ID churn events | Lagging | count | git | per release | Tech Lead |
| Changes with linked issue | Leading | % | PRs | per cycle | PM |
| Review coverage | Leading | % | PRs | per cycle | Reviewer |
| Closure notes per release | Leading | % | issues | per release | PM |
| Process audit result | Lagging | pass/fail | script | per cycle | Process Owner |
| Mean process rating | Lagging | level | appraisal | per cycle | Process Owner |

**Leading vs lagging:** leading indicators predict; lagging confirm. A healthy
program watches leading indicators (coverage, review coverage) to change *future*
outcomes, and uses lagging indicators (secret count, mean rating) to confirm past
improvement.

---

## 3. Collection mechanism

| Layer | How |
| --- | --- |
| Automated | CI workflow emits lint + audit + secret-scan results; coverage reported once tests exist |
| Semi-automated | `scripts/process/process_audit.sh` and `check_secrets.sh` — run locally and in CI |
| Manual | PR/issue linkage, closure notes — recorded in GitHub, which is the system of record |

**Anti-goal:** do not introduce a dashboard or a metrics database. At this scale,
GitHub + the two scripts are the measurement infrastructure. A heavier tool would
itself become an unmaintained artifact and a new gap.

---

## 4. Reporting

A **Measurement Report** (`templates/measurement-report.md`) is produced at the end
of each increment/release and stored in `docs/process/records/`. It reports each
metric against its target, states the trend, and raises corrective actions for any
miss. It feeds directly into PM.3 (Assessment & Control) and the IDEAL *Leveraging*
phase.

---

## 5. Interpreting results honestly

- **Zero is a result, not a failure.** If the secret count is 0, the goal is met.
- **A metric miss is a process signal, not a person's fault.** Investigate the
  process before the individual.
- **Do not collect a metric with no consumer.** If no decision changes based on a
  metric, retire it (via Change Request) — it is waste.

---

## 6. Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial metric set derived from gap register |
