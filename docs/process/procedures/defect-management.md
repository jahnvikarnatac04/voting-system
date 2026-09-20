# Procedure — Defect Management

> **SWEBOK KA10.2 · supports SI.5 and PM.3**
> **Owner:** Project Manager

Defects in a voting system can change an election outcome; they are treated as
first-class work, never as interruptions.

---

## 1. Report

Open an issue using the **Defect** template (`.github/ISSUE_TEMPLATE/`). Include:

- **What happened** vs. **what was expected**
- **Reproduction steps** (or the `demo.md` step that failed)
- **Impact** — does it affect vote integrity, privacy, availability, or UX?
- **Environment** — PHP version, browser, kiosk vs voter portal

Never paste a secret or personal data into a defect report.

---

## 2. Triage and severity

| Severity | Definition | Response |
| --- | --- | --- |
| **S1 — Integrity** | Double vote possible, wrong result, auth bypass, **exposed secret** | Immediate; emergency change |
| **S2 — Security/Privacy** | Data exposure, missing server-side check, passkey orphan risk | Same cycle |
| **S3 — Functional** | A feature is broken but integrity holds | Next cycle |
| **S4 — Cosmetic** | UI/typo | Backlog |

---

## 3. Fix

Follow `change-management.md`. The PR must reference the defect issue. **Every S1/S2
fix adds a regression test** so the defect cannot silently return.

---

## 4. Verify

The **reporter (or another non-author)** re-runs the reproduction steps and confirms
the fix. Verification is recorded on the issue before it is closed.

---

## 5. Analyse and improve

For S1 and S2 defects, ask **"which process let this through?"** — not only "who
wrote the bug?" The answer routes to:

- a **gap register** entry (`../02-process-assessment.md` §4),
- an **improvement backlog** item (`../04-process-improvement.md` §3), or
- a **new test/CI check**.

A defect that recurs is evidence the process fix failed — revisit it under PDCA.

---

## 6. Metrics

Defect counts by severity and the **escape rate** (defects found after merge) feed
Goal 1 in `../03-process-measurement.md`.

---

## Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial procedure |
