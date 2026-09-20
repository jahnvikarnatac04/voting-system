# Procedure — Testing

> **SWEBOK KA10.2 · implements SI.5**
> **Owner:** Developer

The repository currently has **no automated tests** (gap G-02) — the highest
priority quality gap. This procedure defines how tests are introduced and used.

---

## 1. Test levels

| Level | What | Tooling |
| --- | --- | --- |
| **Static** | Syntax and dependency validity | `php -l`, `composer validate` (already in CI) |
| **Unit** | Pure logic — WebAuthn option construction, vote eligibility rules, migrations | PHPUnit (to be added) |
| **Integration** | DB behaviour against a throwaway SQLite file | PHPUnit + temp DB |
| **End-to-end** | The `demo.md` booth → ballot → results flow | Manual (documented), automated later |

Start at unit + integration; the single-file SQLite DB makes an isolated test
database cheap.

---

## 2. What must be tested (priority order)

1. **Vote integrity** — one vote per voter; the atomic conditional UPDATE refuses a
   second vote; authorization expires and is consumed on submit.
2. **Eligibility** — a ballot is only opened when the voter's constituency matches
   the booth's.
3. **WebAuthn option/verification logic** — challenge, origin, signature and
   sign-counter handling; RP ID from config, not `Host`.
4. **Schema migrations** — `db.php` idempotent upgrades are safe to re-run.
5. **Audit logging** — biometric/ballot actions write `biometric_logs`.

---

## 3. Rules

- A change to auth/verification/ballot logic **requires** a test, or a stated QA
  exception recorded in the PR (QA Policy §3).
- Tests must be **deterministic** and independent — no shared mutable state, no
  reliance on a pre-existing `voting_system.db`.
- Tests use **synthetic data only** (Security Policy §2.2).
- A **bug fix adds a regression test** (Defect Management §3).

---

## 4. Running

```bash
# static check on changed file
php -l path/to/file.php

# full local gate (as CI runs it)
bash scripts/process/check_secrets.sh
bash scripts/process/process_audit.sh

# test suite (once the harness lands — IMP-04)
composer test
```

---

## 5. Advancing out of the gap

IMP-04 in `../04-process-improvement.md` establishes the harness. Exit criterion:
**test count > 0**, a coverage target set, and the suite wired into CI so a red test
blocks merge. Until then, every increment should add at least one test rather than
waiting for the harness.

---

## Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial procedure; establishes path out of G-02 |
