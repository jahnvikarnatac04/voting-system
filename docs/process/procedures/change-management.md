# Procedure — Change Management

> **SWEBOK KA10.2 · implements SI.1 → SI.6 end-to-end**
> **Owner:** Project Manager

Turns a request into controlled change on `main`. This is the spine of the SI
process defined in `../01-process-definition.md`.

---

## Flow

```
Request ──▶ CR ──▶ Plan/Req ──▶ Design ──▶ Implement ──▶ Review ──▶ CI ──▶ Merge ──▶ Close
 (SI.1)     (SI.1)  (SI.2)       (SI.3)     (SI.4)        (SI.5)   (SI.5) (SI.6)   (PM.4)
```

---

## Steps

### 1. Raise a Change Request (SI.1)
Open an issue using the **Change Request** template (`.github/ISSUE_TEMPLATE/`).
Assign a CR ID (the issue number). State the goal and the affected area.

### 2. Triage and tailor (SI.1)
Confirm scope and pick the process subset per the tailoring rules
(`../00-framework-overview.md` §5). Record any tailoring in the CR.

### 3. Requirements (SI.2)
For anything non-trivial, fill the SRS template (`../templates/software-requirements-specification.md`)
— or a requirements section inside the CR for a small slice. Every requirement
gets a **testable acceptance criterion**.

### 4. Design (SI.3)
Record the design (`../templates/software-design-description.md`) or a design note
in the CR. Assess **schema impact** and **security impact** explicitly.

### 5. Implement (SI.4)
Branch `<type>/<short-description>` from `main`. Implement + tests. Keep the branch
under ~5 days (CM Policy §5).

### 6. Verify and review (SI.5)
Push; CI runs (lint, audit, secret scan). Request review from a **non-author**.
Apply the Code Review Policy.

### 7. Merge and close (SI.6 / PM.4)
Squash/merge to `main` referencing the CR (`Closes #<n>`). Update docs. Close the CR
with a one-line outcome. For a milestone, follow `release.md`.

---

## Traceability

Every merged change must be traceable:

```
Issue #<n> (CR)  ──▶  PR #<m> (Refs #<n>)  ──▶  commit(s)  ──▶  tag/release
```

If a line is missing, the change is not controlled.

---

## Emergency changes

For a security or integrity fix: implement immediately, merge, then **open the CR
retroactively within 24h** and record it in the release/closure record. Do not
skip review permanently — retro-review it.

---

## Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial procedure |
