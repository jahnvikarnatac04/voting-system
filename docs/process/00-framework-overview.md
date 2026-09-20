# 00 — Process Framework Overview

> **SWEBOK KA10 sub-area 10.1 — Software Engineering Process Fundamentals**

This document establishes *what* the process framework is, *why* it is shaped the
way it is, and *how* the three reference models used here fit together. It is the
conceptual foundation for every other process asset.

---

## 1. Process concepts applied here

SWEBOK defines a software engineering process as a set of interrelated activities,
performed to develop, maintain, and evolve a software product. The four concepts
this repository borrows directly:

| Concept | Meaning | Concrete expression here |
| --- | --- | --- |
| **Process** | The what/why of a coherent set of activities | The SI and PM processes in `01-process-definition.md` |
| **Procedure** | *How* an activity is performed step-by-step | `docs/process/procedures/` |
| **Method** | A technique/tool applied within an activity | Secret scan, PHP lint, GQM, DB migration pattern |
| **Policy** | A binding rule constraining behaviour | `docs/process/policies/` |
| **Lifecycle model** | The arrangement of processes across the product's life | Iterative/incremental, IDEAL-supervised (below) |

The key SWEBOK insight we are acting on: **process is a product too.** It has
requirements (the gap register), a design (this framework), a build (templates and
CI), and a maintenance cycle (IDEAL). It is managed with the same rigour as code —
but with far less ceremony, because the entity is tiny.

---

## 2. Organizational context (why the rigor is calibrated this way)

The framework was scoped against the *actual* repository state, not an ideal one:

| Characteristic | Observed | Process consequence |
| --- | --- | --- |
| Team size | 2 active contributors (3 git identities, one aliased) | No dedicated roles; people wear hats. RACI uses role names, not people. |
| Commits to date | 11, single `main` line | Trunk-based development; short-lived feature branches only |
| Language | PHP 8 + SQLite, no framework | Lint = `php -l`; packaging = Composer |
| Automated tests | **0** | Highest-priority gap; test process must be *introduced*, not documented |
| CI | **Removed** at commit `69fafed` | Restored in `.github/workflows/ci.yml` |
| Docs | Rich narrative (`README`, `demo.md`, `kiosk-plan.md`) | Reused as inputs; not duplicated |
| Domain | Online/Lok Sabha voting | Correctness + auditability are the primary quality attributes |
| Sensitive assets | Live SMTP credentials in source; a tracked `voting_system.db` | Security & CM policies are non-negotiable, not optional |

**Conclusion:** the entity qualifies as a **Very Small Entity (VSE)**. The
appropriate profile is **ISO/IEC 29110 Basic**, not CMMI. CMMI Level 2+ would
impose process areas (e.g. formal causal analysis, organizational training) that a
two-person demo project cannot sustain, and a process that is not followed is
worse than no process — it erodes trust in the whole framework.

---

## 3. Reference models and how they interlock

Three models are used, each for a different purpose. They are **complementary, not
competing**.

```
                ┌──────────────────────────────────────────────┐
                │  SWEBOK KA10 — the WHAT (audit taxonomy)      │
                │  Fundamentals · Definition · Assessment ·     │
                │  Measurement · Improvement                    │
                └───────────────────────┬──────────────────────┘
                                        │ organizes the asset library
                ┌───────────────────────▼──────────────────────┐
                │  ISO/IEC 29110 Basic — the WHICH processes    │
                │  Software Implementation (SI) + Project       │
                │  Management (PM)                              │
                └───────────────────────┬──────────────────────┘
                                        │ sequenced by
                ┌───────────────────────▼──────────────────────┐
                │  IDEAL — the WHEN/HOW we improve              │
                │  Initiating · Diagnosing · Establishing ·     │
                │  Acting · Leveraging                          │
                └──────────────────────────────────────────────┘
```

### 3.1 SWEBOK KA10 → our assets

| SWEBOK sub-area | Our asset |
| --- | --- |
| 10.1 Fundamentals | this document |
| 10.2 Process Definition | `01-process-definition.md` |
| 10.3 Process Assessment | `02-process-assessment.md` |
| 10.4 Process Measurement | `03-process-measurement.md` |
| 10.5 Process Improvement | `04-process-improvement.md` |

### 3.2 ISO/IEC 29110 Basic profile → our processes

The Basic profile defines two processes. Both are instantiated in
`01-process-definition.md`:

- **Software Implementation (SI)** — SI.1 Initiation → SI.6 Delivery.
- **Project Management (PM)** — PM.1 Planning → PM.4 Closure.

### 3.3 IDEAL → our improvement sequencing

IDEAL drives the *temporal* dimension. Its phases map onto KA10 assets as follows;
the full roadmap is in `04-process-improvement.md`.

| IDEAL phase | Activity | Primary KA10 anchor |
| --- | --- | --- |
| **I**nitiating | Establish sponsorship, scope the improvement program | 10.1 Fundamentals |
| **D**iagnosing | Baseline current capability, build the gap register | 10.3 Assessment |
| **E**stablishing | Define processes, metrics, plans, priorities | 10.2 Definition + 10.4 Measurement |
| **A**cting | Run pilots, implement, institutionalize | 10.2 + 10.5 |
| **L**everaging | Analyse lessons, standardize, feed the next cycle | 10.5 Improvement |

---

## 4. Process infrastructure

SWEBOK groups the supporting assets under *process infrastructure*. Ours:

| Infrastructure element | Provided by |
| --- | --- |
| **Policies** | `docs/process/policies/` |
| **Procedures** | `docs/process/procedures/` |
| **Templates** | `docs/process/templates/` |
| **Tools** | `scripts/process/`, `.github/workflows/ci.yml` |
| **Roles** | `01-process-definition.md` §3 |
| **Records** | Git history, GitHub issues/PRs, `voting_system.db` audit tables |

The catalog and routing table live in `05-process-asset-library.md`.

---

## 5. Tailoring rules

This framework is a **tailorable standard**, not a fixed mandate.

- **Allowed without approval:** skipping a template for a change under ~20 lines
  with no new interface; using a lightweight review (single reviewer) for
  documentation-only changes.
- **Requires a recorded decision:** omitting a defined process step, adding a new
  policy, or changing a metric definition.
- **Never tailored away:** the Security & Secrets Policy and Configuration
  Management Policy. These are treated as invariant because the domain and the
  current repository state make them safety-critical.

Any deviation must be recorded in the Change Request (see
`templates/change-request.md`) with a rationale, so tailoring remains auditable.

---

## 6. Revision history

| Version | Date | Change | Author |
| --- | --- | --- | --- |
| 0.1 | 2026-09-20 | Initial framework establishment (IDEAL *Initiating*) | Process owner |
