# 05 — Process Asset Library (PAL) Catalog

> **SWEBOK KA10 — Process Infrastructure under 10.1 Fundamentals**

The PAL is the complete inventory of process assets. SWEBOK distinguishes four
kinds of infrastructure asset; this catalog files every artifact under one of them
and states its purpose, trigger, owner, and status.

---

## 1. Assets by kind

### Policies — *binding rules*

| ID | Asset | Purpose | Trigger | Owner |
| --- | --- | --- | --- | --- |
| POL-CM | [`policies/configuration-management.md`](policies/configuration-management.md) | What is versioned, how changes are identified/baselined | Every change | Tech Lead |
| POL-QA | [`policies/quality-assurance.md`](policies/quality-assurance.md) | How quality is verified independently of the author | Every change | Reviewer |
| POL-SEC | [`policies/security-and-secrets.md`](policies/security-and-secrets.md) | Handling of credentials, voter data, biometric data | Every commit | Tech Lead |
| POL-CR | [`policies/code-review.md`](policies/code-review.md) | Review entry/exit criteria; segregation of duties | Every PR | Reviewer |

### Procedures — *step-by-step how*

| ID | Asset | Purpose | Trigger |
| --- | --- | --- | --- |
| PRC-CHG | [`procedures/change-management.md`](procedures/change-management.md) | How a change moves from request to `main` | New change |
| PRC-DEF | [`procedures/defect-management.md`](procedures/defect-management.md) | How defects are reported, triaged, fixed, verified | Defect found |
| PRC-TST | [`procedures/testing.md`](procedures/testing.md) | How tests are written and run | Every change |
| PRC-REL | [`procedures/release.md`](procedures/release.md) | How a release is prepared and delivered | Milestone |

### Templates — *work product shells*

| ID | Asset | Work product |
| --- | --- | --- |
| TPL-PLAN | [`templates/project-plan.md`](templates/project-plan.md) | Project/Increment Plan (PM.1) |
| TPL-SRS | [`templates/software-requirements-specification.md`](templates/software-requirements-specification.md) | Requirements (SI.2) |
| TPL-SDD | [`templates/software-design-description.md`](templates/software-design-description.md) | Design (SI.3) |
| TPL-TP | [`templates/test-plan.md`](templates/test-plan.md) | Test Plan (SI.5) |
| TPL-CR | [`templates/change-request.md`](templates/change-request.md) | Change Request (SI.1) |
| TPL-MR | [`templates/measurement-report.md`](templates/measurement-report.md) | Measurement Report (PM.3) |

### Tools — *executable assets*

| ID | Asset | Purpose | Runs |
| --- | --- | --- | --- |
| TOOL-CI | `.github/workflows/ci.yml` | Lint, Composer validation, secret scan, process audit | Every push/PR |
| TOOL-SEC | `scripts/process/check_secrets.sh` | Detect committed secrets/data artifacts | CI + local |
| TOOL-AUD | `scripts/process/process_audit.sh` | Verify PAL integrity | CI + local |

---

### Requirements baseline — KA1 input to KA10

Not process assets themselves, but the **input** the SI.2 step consumes. Filed
here so the library's scope is complete.

| ID | Asset | Serves |
| --- | --- | --- |
| REQ-BRD | [`../requirements/01-business-requirements.md`](../requirements/01-business-requirements.md) | Business layer: stakeholders, goals, rules, scope |
| REQ-SRS | [`../requirements/02-software-requirements-specification.md`](../requirements/02-software-requirements-specification.md) | Software layer: testable FR/NFR, status, traceability |

---

## 2. Document-level assets (the KA10 core)

| ID | Asset | SWEBOK sub-area |
| --- | --- | --- |
| DOC-FND | [`00-framework-overview.md`](00-framework-overview.md) | 10.1 Fundamentals |
| DOC-DEF | [`01-process-definition.md`](01-process-definition.md) | 10.2 Definition |
| DOC-ASM | [`02-process-assessment.md`](02-process-assessment.md) | 10.3 Assessment |
| DOC-MEA | [`03-process-measurement.md`](03-process-measurement.md) | 10.4 Measurement |
| DOC-IMP | [`04-process-improvement.md`](04-process-improvement.md) | 10.5 Improvement |

Records produced by running the process (measurement reports, closure notes)
are archived under `docs/process/records/`.

---

## 3. Routing table — "I need to…"

| I need to… | Go to |
| --- | --- |
| Know what the system must do | [`../requirements/02-software-requirements-specification.md`](../requirements/02-software-requirements-specification.md) |
| Know why a feature exists | [`../requirements/01-business-requirements.md`](../requirements/01-business-requirements.md) |
| Propose a change | `procedures/change-management.md` → `templates/change-request.md` |
| Report or fix a defect | `procedures/defect-management.md` |
| Write or run tests | `procedures/testing.md` |
| Cut a release | `procedures/release.md` |
| Review a PR | `policies/code-review.md` |
| Handle a credential or voter/biometric data | `policies/security-and-secrets.md` |
| Know what is under version control | `policies/configuration-management.md` |
| Understand my role | `01-process-definition.md` §4 |
| See what we are fixing | `04-process-improvement.md` §3 |
| See how we are doing | `03-process-measurement.md` §2 |
| Know the rules for editing *this* framework | §4 below |

---

## 4. Control of the PAL itself

The process assets are configuration items — they change under the same discipline
as code.

1. **Changes** to any asset go through a Change Request (`templates/change-request.md`).
2. **Reviews** require the Process Owner as reviewer for policy/metric changes.
3. **History** is Git; every change carries a rationale in its commit message.
4. **Versioning** — each asset carries a *Revision history* table; bump on change.
5. **Integrity** is asserted by `scripts/process/process_audit.sh`, which fails if a
   required asset is missing or if a referenced asset does not exist.

---

## 5. Asset metadata convention

Every asset begins with, or contains:

- a **SWEBOK KA10 reference** (which sub-area it serves), and
- a **Revision history** table.

This keeps the library auditable: any file can be traced to a KA10 sub-area, and
any rating in `02-process-assessment.md` can be traced to an asset.

---

## 6. Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial catalog |
