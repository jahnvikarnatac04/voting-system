# 01 — Process Definition

> **SWEBOK KA10 sub-area 10.2 — Software Engineering Process Definition**

This document defines the processes actually performed in this repository. They
are instantiated from the **ISO/IEC 29110 Basic profile**: the *Software
Implementation* (SI) and *Project Management* (PM) processes.

It follows SWEBOK's rule that a defined process must specify its **activities,
inputs, outputs (work products), entry/exit criteria, and responsible roles.** A
process that omits any of these is a habit, not a definition.

---

## 1. Lifecycle model

**Iterative/incremental, trunk-based.** Work proceeds in small vertical slices
merged to `main`, each slice carrying its requirements, design note, code, and
verification evidence together. Long-lived branches are avoided — the single
existing exception, `feature/verification-clean`, is retired under the CM policy.

```
        ┌── SI.1 Initiation ─────────────────────────────────────┐
        │                                                        │
   ┌────▼────┐   ┌────────────┐   ┌────────────┐   ┌────────────┐ │
   │ SI.2    │──▶│ SI.3       │──▶│ SI.4       │──▶│ SI.5       │ │
   │ Require-│   │ Architect/ │   │ Construct  │   │ Integrate/ │ │
   │ ments   │   │ Design     │   │ (+ review) │   │ Test       │ │
   └─────────┘   └────────────┘   └────────────┘   └─────┬──────┘ │
                                                          │        │
                                              ┌───────────▼──────┐ │
                                              │ SI.6 Delivery    │ │
                                              └──────────────────┘ │
        └────────────────────────────────────────────────────────┘
        PM.1…PM.4 run continuously across the slice (see §3)
```

---

## 2. Software Implementation (SI) process

### SI.1 — Software Implementation Initiation

| | |
| --- | --- |
| **Inputs** | A Change Request (`templates/change-request.md`), a defect report, or a `kiosk-plan.md`-style change proposal |
| **Activities** | Confirm the request is in scope; identify the affected modules; select the process subset via tailoring rules (`00-framework-overview.md` §5) |
| **Outputs** | An accepted Change Request with an impact note |
| **Entry criteria** | Request exists and is non-duplicative |
| **Exit criteria** | Scope agreed; affected files identified |
| **Roles** | Requester → Technical Lead |

### SI.2 — Software Requirements Analysis

| | |
| --- | --- |
| **Inputs** | Accepted Change Request |
| **Activities** | Elicit/derive functional and non-functional requirements; define acceptance criteria; identify the quality attribute affected (for this product: correctness, auditability, security) |
| **Outputs** | `templates/software-requirements-specification.md` (or a short requirements section inside the Change Request for small slices) |
| **Entry criteria** | SI.1 exit met |
| **Exit criteria** | Requirements are testable; each has an acceptance criterion; traceability to the Change Request ID established |
| **Roles** | Technical Lead (acct.), Stakeholder |

### SI.3 — Architectural & Detailed Design

| | |
| --- | --- |
| **Inputs** | Requirements baseline |
| **Activities** | Decide where change lands; respect existing patterns (`db.php` idempotent-migration pattern, server-side enforcement in `includes/webauthn.php`); record any new interface or schema change |
| **Outputs** | `templates/software-design-description.md`, or a design note in the Change Request |
| **Entry criteria** | SI.2 exit met |
| **Exit criteria** | Data/schema impact assessed; security impact assessed; no design contradicts an existing policy |
| **Roles** | Technical Lead |

### SI.4 — Software Construction

| | |
| --- | --- |
| **Inputs** | Design description |
| **Activities** | Implement; keep changes minimally scoped; write/extend automated tests alongside |
| **Outputs** | Source changes on a short-lived branch, plus tests |
| **Entry criteria** | SI.3 exit met |
| **Exit criteria** | Code compiles (`php -l` clean); tests written; review requested |
| **Roles** | Developer |

### SI.5 — Software Integration & Tests

| | |
| --- | --- |
| **Inputs** | Source changes, test plan |
| **Activities** | Integrate; run the CI pipeline; execute the test plan; peer review per the Code Review Policy |
| **Outputs** | `templates/test-plan.md` (results recorded), review approval |
| **Entry criteria** | SI.4 exit met |
| **Exit criteria** | CI green; review approved; acceptance criteria from SI.2 demonstrably met |
| **Roles** | Developer, Reviewer |

### SI.6 — Product Delivery

| | |
| --- | --- |
| **Inputs** | Verified increment on `main` |
| **Activities** | Merge; update user-facing docs (`README.md`, `demo.md`, `kiosk-plan.md` as applicable); tag the version if it is a milestone |
| **Outputs** | Merged, documented, tagged increment |
| **Entry criteria** | SI.5 exit met |
| **Exit criteria** | Docs match behaviour; work product set archived; PM.4 (closure) triggered if this ends a release |
| **Roles** | Technical Lead |

---

## 3. Project Management (PM) process

In a two-person VSE the PM process is deliberately thin, but it is still
*defined* — otherwise planning, control, and closure happen implicitly and become
unauditable.

| Sub-process | What is done | Evidence |
| --- | --- | --- |
| **PM.1 Planning** | Each increment gets a plan: goal, scope, acceptance criteria, estimate, owner. For small slices this is the Change Request itself. | `templates/project-plan.md` |
| **PM.2 Executing** | Work proceeds against the plan; progress visible on the issue/PR. | GitHub issues + PRs |
| **PM.3 Assessment & Control** | Metrics collected per `03-process-measurement.md`; deviations raise a corrective action. | `templates/measurement-report.md` |
| **PM.4 Closure** | Increment reviewed against acceptance criteria; lessons recorded; gap register updated. | Closure note on the PR/issue |

---

## 4. Roles and RACI

Roles, **not people** — in a VSE, one person holds several. Assign a person to a
role per increment in the plan.

| Role | Responsibility | Typical holder |
| --- | --- | --- |
| **Stakeholder** | States needs; accepts output | Product owner / instructor |
| **Project Manager** | Planning, control, closure (PM.1–PM.4) | Either contributor |
| **Technical Lead** | Requirements, design, delivery quality (SI.2, SI.3, SI.6) | Senior contributor |
| **Developer** | Construction and unit verification (SI.4) | Either contributor |
| **Reviewer** | Independent review (SI.5) | The contributor who did *not* author the change |
| **Process Owner** | Maintains this PAL; runs IDEAL cycles | Maintainer |

### RACI — key activities

`R` = Responsible, `A` = Accountable, `C` = Consulted, `I` = Informed.

| Activity | Stakeholder | PM | Tech Lead | Developer | Reviewer | Process Owner |
| --- | --- | --- | --- | --- | --- | --- |
| SI.1 Initiation | C | A | R | I | | I |
| SI.2 Requirements | C | I | A/R | C | C | |
| SI.3 Design | I | C | A/R | C | C | |
| SI.4 Construction | | I | C | A/R | | |
| SI.5 Integration & Test | I | C | A | R | A/R | |
| SI.6 Delivery | I | A | R | I | C | I |
| PM.1 Planning | C | A/R | C | C | | I |
| PM.3 Control | I | A/R | C | I | | C |
| PM.4 Closure | I | A/R | C | I | C | C |
| Process improvement | C | C | C | C | C | A/R |

**Segregation rule:** the author of a change may not be its sole approver. With
two contributors this is achievable — the other contributor reviews.

---

## 5. Work products

| Work product | Template | Retention |
| --- | --- | --- |
| Change Request | `templates/change-request.md` | GitHub issue (permanent) |
| Project Plan | `templates/project-plan.md` | Repo / issue |
| Requirements Specification | `templates/software-requirements-specification.md` | Repo `docs/` or issue |
| Design Description | `templates/software-design-description.md` | Repo `docs/` or PR |
| Test Plan | `templates/test-plan.md` | Repo `docs/` or PR |
| Measurement Report | `templates/measurement-report.md` | `docs/process/records/` |
| Gap Report | `02-process-assessment.md` §4 | `docs/process/` |

Work products are versioned in Git — that is the Configuration Management Policy's
requirement, and Git is the CM system of record.

---

## 6. Entry/exit criteria summary

| Gate | Must be true to proceed |
| --- | --- |
| Initiation → Requirements | Request scoped and accepted |
| Requirements → Design | Every requirement testable with an acceptance criterion |
| Design → Construction | Schema + security impact assessed |
| Construction → Integration | `php -l` clean; tests written |
| Integration → Delivery | CI green; review approved; acceptance criteria met |
| Delivery → Closure | Docs updated; gap register reviewed |

---

## 7. Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial definition |
