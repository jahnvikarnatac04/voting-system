# Requirements Baseline

> **SWEBOK KA1 — Software Requirements** (the *input* to KA10's SI.2 process step)

---

## Why this exists outside `docs/process/`

SWEBOK separates **requirements engineering (KA1)** from **software engineering
process (KA10)**. The process framework in [`../../PROCESS.md`](../../PROCESS.md)
defines *how* requirements are elicited, specified, verified, and controlled — it
does not contain the requirements themselves.

But ISO/IEC 29110 **SI.2 requires an actual SRS work product** as the input to the
whole Software Implementation chain. Until that artifact existed, the process had
no material flowing through it and SI.2 was correctly appraised at **capability
Level 0** (gap G-06 in `../process/02-process-assessment.md`).

These documents close that gap. They are **KA1 artifacts consumed by KA10**.

---

## Contents

| Document | Layer | Answers |
| --- | --- | --- |
| [`01-business-requirements.md`](01-business-requirements.md) | Business / stakeholder | **Why** the system exists, for whom, under what rules and constraints |
| [`02-software-requirements-specification.md`](02-software-requirements-specification.md) | Software (as-built) | **What** the system must do, testably, with implementation status |
| [`03-production-requirements-plan.md`](03-production-requirements-plan.md) | Software (forward) | **What a production election system would require**, and the phased plan to get there |

The reusable shell for future changes is
[`../process/templates/software-requirements-specification.md`](../process/templates/software-requirements-specification.md).

---

## Traceability chain

```
BR-n  (business requirement)
  └─▶ FR-n / NFR-n  (software requirement)
        └─▶ CR-n  (change that implemented it)
              └─▶ TC-n  (test that verifies it)
```

Every software requirement in `02-` carries its business source, implementation
status, and verifying evidence. A requirement with no implementation and no plan is
either **out of scope** or a **gap** — it is never silently ignored.

---

## Status vocabulary

| Status | Meaning |
| --- | --- |
| **Implemented** | Present in code, with the cited file as evidence |
| **Partial** | Present but incomplete or demo-grade |
| **Planned** | Not implemented; tracked in the improvement backlog |
| **Out of scope** | Deliberately excluded (see §Scope in `01-`) |

---

## Change control

This baseline is a configuration item. Changes follow
[`../process/procedures/change-management.md`](../process/procedures/change-management.md):
raise a Change Request, update the affected requirement(s), and record the
rationale. Requirements are **never edited silently** — a change to a requirement is
a change to what the product must do.

---

## Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial baseline derived from `README.md`, `demo.md`, `kiosk-plan.md`, schema, and source |
