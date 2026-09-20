# Policy — Configuration Management

> **SWEBOK KA10.1 (process infrastructure) · supports KA10.2 Definition**
> **Status:** Binding · **Owner:** Technical Lead · **Review:** each IDEAL cycle

---

## 1. Purpose

To ensure that every artifact that defines, builds, or delivers the product is
**identified, versioned, and controlled** — and that artifacts which must *not* be
versioned are rigorously excluded.

This policy exists because the repository has real CM defects: no `.gitignore`,
a tracked live database (`voting_system.db`), and credentials in source. See gaps
G-01/G-03/G-05 in `../02-process-assessment.md`.

---

## 2. System of record

**Git** is the configuration management system. The single source of truth for the
product is the `main` branch. GitHub Issues and Pull Requests are the records for
change requests, defects, reviews, and closure.

---

## 3. Configuration items — under version control

| Category | Examples | Control |
| --- | --- | --- |
| Source | `*.php`, `js/`, `css/` | Every change via PR |
| Schema | `schema.sql`, migrations in `db.php`, `init_db.php` | Reviewed for backward compatibility |
| Process assets | `PROCESS.md`, `docs/process/**`, `.github/**` | Per PAL §4 |
| Dependencies | `composer.json`, `composer.lock` | Lock file committed; never edit by hand |
| User docs | `README.md`, `demo.md`, `kiosk-plan.md` | Updated in the same PR as behaviour |
| Config **examples** | `includes/config.example.php` | Safe placeholders only |

---

## 4. Non-configuration items — must NOT be versioned

| Category | Examples | Rationale |
| --- | --- | --- |
| Secrets / credentials | `includes/config.php`, `.env`, SMTP app passwords, API keys | Security Policy |
| Runtime data | `voting_system.db`, `*.db`, `*.sqlite` | Live voter data; not a build artifact |
| Dependencies (resolved) | `vendor/` | Reproducible via Composer |
| OS / editor cruft | `.DS_Store`, `Thumbs.db`, `.idea/`, `.vscode/` | Noise |
| Build/test output | coverage reports, logs | Regenerable |

These are enforced by `.gitignore` and by `scripts/process/check_secrets.sh`.

> **Ratified decision:** `voting_system.db` is **removed from tracking**. The
> database is created by `php init_db.php` (+ `seed_real_data.php`). A tracked
> database is both a data-protection defect and a merge-conflict generator. The
> database file remains on disk; it is simply untracked.

---

## 5. Identification and baselining

- **Identification:** every change has a **Change Request ID** (GitHub issue). The
  PR references it (`Refs #<n>`), giving full traceability request → code → test.
- **Baselines:** a milestone release is a baseline. It is marked with an annotated
  Git tag (e.g. `v0.2.0`) and a release note produced per
  `../procedures/release.md`.
- **Branching:** trunk-based. Short-lived branches named
  `<type>/<short-description>` (e.g. `fix/secret-hygiene`); `feature/verification-clean`
  is retired under this policy. Branch lifetime target: **< 5 days**.

---

## 6. Change control

| Change type | Required |
| --- | --- |
| Source / schema | CR + PR + review + CI green |
| Process asset | CR + Process Owner review |
| Dependency / `composer.lock` | CR + note on why, and security consideration |
| Emergency fix | CR opened retroactively within 24h; noted in the release record |

No change reaches `main` without passing CI and the Code Review Policy.

---

## 7. Auditing

| Check | Frequency | Tool |
| --- | --- | --- |
| No tracked secrets/data artifacts | Every push | `check_secrets.sh` (CI) |
| `.gitignore` covers non-CIs | Per cycle | manual + audit script |
| `.gitignore` matches practice | Per cycle | `process_audit.sh` |
| Branch lifetime | Per cycle | git |

---

## 8. Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial policy; ratifies untracking `voting_system.db` |
