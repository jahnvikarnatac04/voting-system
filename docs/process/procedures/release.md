# Procedure — Release & Delivery

> **SWEBOK KA10.2 · implements SI.6 and PM.4**
> **Owner:** Technical Lead

Defines how a verified increment becomes a baselined release.

---

## 1. Trigger

A milestone is reached (e.g. the kiosk voting flow ships, or a Critical/High gap
closes). A release is **not** every merge — merges are continuous delivery; a
release is a **baseline**.

---

## 2. Readiness gate

All must be true:

- [ ] CI green on `main`
- [ ] All intended CRs merged and closed
- [ ] **QA:** the `demo.md` end-to-end smoke flow passes
- [ ] **Security:** `check_secrets.sh` clean (tracked-secret count = 0)
- [ ] **CM:** `check_secrets.sh` clean for data artifacts; no stray branch
- [ ] Docs updated (`README.md`, `demo.md`, `kiosk-plan.md` as affected)
- [ ] Test count / coverage recorded (once the harness exists)

---

## 3. Steps

1. **Freeze** — no new merges except fixes for this release.
2. **Verify** — run the readiness gate and the end-to-end smoke flow.
3. **Version** — create an annotated tag `vMAJOR.MINOR.PATCH`
   (`git tag -a v0.2.0 -m "..."`). SemVer: breaking schema/auth = MAJOR, feature =
   MINOR, fix = PATCH.
4. **Notes** — write a release note: what shipped, what was verified, known
   limitations, and the metrics snapshot.
5. **Deliver** — the tag is the version of record; deploy per the project README.
6. **Close (PM.4)** — record a closure note: outcome vs. acceptance criteria, and
   **lessons learned** for IDEAL *Leveraging*.

---

## 4. Rollback

Because delivery is a tagged commit and the database is generated (not versioned),
rollback = redeploy the previous tag and re-seed if the schema changed
backward-incompatibly. Schema changes must therefore note their rollback path.

---

## 5. First baseline

The first release under this framework should be the increment that closes
**IMP-01…IMP-03** (secret hygiene, `.gitignore`, CI). Tag it `v0.2.0` and use its
release note as the template for later ones.

---

## Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial procedure |
