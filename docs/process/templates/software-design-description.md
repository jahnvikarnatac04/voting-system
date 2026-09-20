# Software Design Description — <name>

> Template (SI.3). Record design so intent survives. For this project, the
> *design decision record* style used in `kiosk-plan.md` is the model to follow.

## 1. Overview

What is being designed/changed, and in one paragraph how.

## 2. Requirements addressed

| Requirement | How the design satisfies it |
| --- | --- |
| FR-1 | |

## 3. Design

### 3.1 Components touched

| File / module | Change |
| --- | --- |
| | |

### 3.2 Data & schema impact

- New/changed tables or columns:
- Migration approach (must follow the idempotent pattern in `db.php`):
- Backward-compatible? Rollback path:

### 3.3 Interfaces

| Interface | Input | Output | Notes |
| --- | --- | --- | --- |
| | | | |

## 4. Security analysis

Mandatory for any change touching auth, verification, ballot, config, or RP ID.

- **Trust boundary:** where does server-side enforcement live?
- **Secret handling:** does this introduce/read a secret? Where does it live?
- **RP ID / origin:** affected? stable?
- **Failure modes:** what happens on replay, expiry, double submit?

## 5. Design decisions (ADR)

| Decision | Alternatives considered | Rationale |
| --- | --- | --- |
| | | |

## 6. Testability

What becomes testable, and the tests to add (`procedures/testing.md`).

## 7. Traceability

| Design element | Requirement | Test |
| --- | --- | --- |
| | | |
