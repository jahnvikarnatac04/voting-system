# Contributing

Welcome. This repository runs a lightweight, standards-based process framework.
This file is the **practical** entry point for a contributor. The authoritative
process assets live in [`PROCESS.md`](PROCESS.md).

---

## TL;DR — the loop

1. **Raise a Change Request** (GitHub issue → *Change Request* template).
2. **Branch** from `main`: `<type>/<short-description>` (e.g. `fix/secret-hygiene`).
3. **Implement + test**, following the Code Review and Security policies.
4. **Open a PR**, link the CR (`Refs #<n>`), complete the checklist.
5. **Get a non-author review**, ensure CI is green.
6. **Merge**, close the CR with a one-line outcome.

Full detail: [`docs/process/procedures/change-management.md`](docs/process/procedures/change-management.md).

---

## Before your first change

- Read [`PROCESS.md`](PROCESS.md) (the map) and
  [`docs/process/01-process-definition.md`](docs/process/01-process-definition.md) §4 (roles).
- Read the **Security & Secrets Policy** —
  [`docs/process/policies/security-and-secrets.md`](docs/process/policies/security-and-secrets.md).
  It is binding and may not be tailored away.
- Get the app running: see [`README.md`](README.md) and [`demo.md`](demo.md).

---

## Local gates (run before you push)

```bash
bash scripts/process/check_secrets.sh    # no secrets / uncontrolled data
bash scripts/process/process_audit.sh    # process assets intact, links resolve
php -l path/to/changed.php               # syntax
```

CI runs exactly these, plus Composer validation, so a local pass means a green CI.

---

## Rules that are enforced, not requested

| Rule | Enforced by |
| --- | --- |
| No secret in a tracked file | `check_secrets.sh` in CI |
| No runtime DB / `vendor/` tracked | `.gitignore` + `check_secrets.sh` |
| A non-author reviews every change | PR template + `CODEOWNERS` |
| Process assets stay consistent | `process_audit.sh` in CI |
| Behaviour changes update docs | Definition of Done (`quality-assurance.md` §7) |

---

## What "done" means

See [`docs/process/policies/quality-assurance.md`](docs/process/policies/quality-assurance.md) §7.
In short: acceptance criteria met, CI green, non-author review, tests (or a stated
exception), docs updated.

---

## Improving the process itself

The framework is improved by design, not by accident. Found a process gap? Raise a
Change Request and add it to the **gap register** in
[`docs/process/02-process-assessment.md`](docs/process/02-process-assessment.md) §4.
The improvement backlog is in
[`docs/process/04-process-improvement.md`](docs/process/04-process-improvement.md) §3.

---

## Tailoring

Small changes may use a lighter process. The rules for what may be tailored, and
what never may be, are in
[`docs/process/00-framework-overview.md`](docs/process/00-framework-overview.md) §5.
Record any tailoring in the Change Request.
