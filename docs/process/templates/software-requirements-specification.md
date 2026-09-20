# Software Requirements Specification — <name>

> Template (SI.2). Requirements must be **testable** and traceable to a Change
> Request. Skip sections that do not apply, but say so rather than deleting silently.

## 1. Purpose & scope

## 2. Source

| Field | Value |
| --- | --- |
| CR ID | |
| Stakeholders | |
| Date | |

## 3. Functional requirements

| ID | Requirement | Priority | Acceptance criterion (testable) |
| --- | --- | --- | --- |
| FR-1 | | Must | Given … when … then … |
| FR-2 | | Should | |

## 4. Non-functional requirements

Because this is a voting system, the four attributes below are **mandatory
consideration** even when unchanged.

| ID | Attribute | Requirement | Acceptance criterion |
| --- | --- | --- | --- |
| NFR-1 | Correctness | One person, one vote enforced atomically | |
| NFR-2 | Auditability | Every ballot/biometric action is logged | |
| NFR-3 | Security | No credential/data exposure; server-side enforcement | |
| NFR-4 | Usability | `demo.md` flow completes unaided | |

## 5. Constraints & assumptions

- PHP ≥ 8.0, `pdo_sqlite`, WebAuthn needs HTTPS or localhost.
- Single-file SQLite DB.

## 6. Traceability

| Requirement | CR | Design | Test |
| --- | --- | --- | --- |
| FR-1 | | | |

## 7. Out of scope

## 8. Approval

| Role | Name | Date |
| --- | --- | --- |
| Technical Lead | | |
| Stakeholder | | |
