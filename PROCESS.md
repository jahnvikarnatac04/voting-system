# Software Engineering Process Asset Library (PAL)

**Entry point for the process framework of the Online Voting System.**

This repository operates a lightweight, standards-based software engineering
process framework. This file is the **index** — the single place to start when you
want to know *how work is done here*, *where the rules live*, and *how the process
itself is improved*.

| | |
| --- | --- |
| **Framework basis** | SWEBOK v4 Knowledge Area 10 — *Software Engineering Process* |
| **Process profile** | ISO/IEC 29110 — *Basic profile* (Very Small Entity) |
| **Improvement cycle** | IDEAL (Initiating → Diagnosing → Establishing → Acting → Leveraging) |
| **Assessment model** | ISO/IEC 33001/33020-style process capability appraisal |
| **Owner** | Repository maintainers (see `CODEOWNERS`) |
| **Review cadence** | Each IDEAL cycle; see `docs/process/04-process-improvement.md` |

---

## 1. How this library is organized

SWEBOK KA10 defines five sub-areas. Every process asset in this repository is
filed under exactly one of them so the framework stays traceable and auditable.

| SWEBOK KA10 sub-area | Where it lives | Question it answers |
| --- | --- | --- |
| **10.1 Fundamentals** | [`docs/process/00-framework-overview.md`](docs/process/00-framework-overview.md) | What is our process, and why does it look like this? |
| **10.2 Process Definition** | [`docs/process/01-process-definition.md`](docs/process/01-process-definition.md) | What are the defined processes, roles, and work products? |
| **10.3 Process Assessment** | [`docs/process/02-process-assessment.md`](docs/process/02-process-assessment.md) | How capable are we today, and where are the gaps? |
| **10.4 Process Measurement** | [`docs/process/03-process-measurement.md`](docs/process/03-process-measurement.md) | What do we measure, and how do we collect it? |
| **10.5 Process Improvement** | [`docs/process/04-process-improvement.md`](docs/process/04-process-improvement.md) | How do we close the gaps, and how do we know it worked? |

The catalog of **policies, procedures, templates, and tools** — the "process
infrastructure" SWEBOK calls out under *Fundamentals* — is in
[`docs/process/05-process-asset-library.md`](docs/process/05-process-asset-library.md).

> **The requirements baseline is a KA1 artifact, not a KA10 one.** The actual
> business and software requirements — the *input* consumed by the SI.2 process step
> — live in [`docs/requirements/`](docs/requirements/README.md). The process
> framework defines how requirements are handled; those documents contain them. A
> process with no requirements flowing through it is hollow, which is why the
> baseline is part of this asset library's scope even though it is filed under KA1.

---

## 2. Documentation map

```
PROCESS.md                                  ← you are here (index)
├── docs/requirements/                       KA1 baseline consumed by SI.2
│   ├── 01-business-requirements.md         Stakeholders, goals, business rules
│   └── 02-software-requirements-specification.md   Testable FR/NFR + status
├── docs/process/
│   ├── 00-framework-overview.md            KA10.1 Fundamentals + scope + mapping
│   ├── 01-process-definition.md            KA10.2 Definition (SI.1–SI.6, PM.1–PM.4, RACI)
│   ├── 02-process-assessment.md            KA10.3 Assessment (capability model, baseline, gaps)
│   ├── 03-process-measurement.md           KA10.4 Measurement (GQM goals → metrics)
│   ├── 04-process-improvement.md           KA10.5 Improvement (IDEAL roadmap, PDCA)
│   ├── 05-process-asset-library.md         PAL catalog & routing
│   ├── policies/                           Binding rules (CM, QA, security, review)
│   └── templates/                          Work-product templates (plans, specs, CRs)
├── CONTRIBUTING.md                         Contributor-facing "how to work here"
└── .github/                                Executable enforcement (CI, PR/issue templates, CODEOWNERS)
```

---

## 3. Executable enforcement

Process that is not enforced decays. These artifacts make the framework
*mechanically* checkable rather than aspirational.

| Control | Artifact | Enforces |
| --- | --- | --- |
| Continuous integration | `.github/workflows/ci.yml` | Lint, Composer validation, secret scan, process audit |
| Secret detection | `scripts/process/check_secrets.sh` | Security & Secrets Policy |
| Self-audit | `scripts/process/process_audit.sh` | PAL integrity — required process assets exist and are referenced |
| Change intake | `.github/ISSUE_TEMPLATE/` | Change Request / Defect procedures |
| Review gate | `.github/pull_request_template.md` + `CODEOWNERS` | Code Review Policy |

Run the two local gates at any time:

```bash
bash scripts/process/check_secrets.sh     # exit 1 if a secret is staged/tracked
bash scripts/process/process_audit.sh     # exit 1 if the PAL is incomplete
```

---

## 4. Reading paths

- **New contributor** → [`CONTRIBUTING.md`](CONTRIBUTING.md) → then `01-process-definition.md` §3 (roles) and §5 (work products).
- **Maintainer / auditor** → `02-process-assessment.md` (baseline + gap register) → `04-process-improvement.md` (what we are fixing).
- **Reviewing a change** → `policies/code-review-policy.md` → `policies/configuration-management-policy.md`.
- **Handling a defect or an incident** → `procedures` referenced in `05-process-asset-library.md`.
- **Just want to run the app** → [`README.md`](README.md) and [`demo.md`](demo.md). This framework is *about the process*, not the product.

---

*This framework is tailored to a very small entity (see `00-framework-overview.md`
§2). It deliberately omits heavy ceremony that would not survive contact with a
two-person team. Scope changes go through the change-management procedure.*
