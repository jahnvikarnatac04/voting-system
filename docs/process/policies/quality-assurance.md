# Policy — Quality Assurance

> **SWEBOK KA10.1 (process infrastructure) · supports SI.5 in KA10.2**
> **Status:** Binding · **Owner:** Reviewer · **Review:** each IDEAL cycle

---

## 1. Purpose

To ensure the product conforms to its requirements and process — through
**planned, independent verification**, not through hope. QA answers: *how do we
know it works, and who besides the author checked?*

---

## 2. Verification vs. validation

Both are required and are distinct:

| | Verification | Validation |
| --- | --- | --- |
| Question | "Did we build it right?" | "Did we build the right thing?" |
| Acts on | Design, code, schema vs. requirements | Requirements vs. user need |
| In this repo | `php -l`, tests, CI, code review | Acceptance criteria met; demo flow (`demo.md`) succeeds |

A change is **done** only when both hold.

---

## 3. Independence

The author of a change **may not be its sole verifier.** With a two-person team
this means the *other* contributor reviews. Where that is impossible (solo work),
the change carries an explicit QA note stating the limitation and what was
substituted (e.g. a documented end-to-end run of `demo.md`).

---

## 4. Quality attributes of record

Because this is a voting application, these attributes take precedence when
trade-offs arise. They are the acceptance lens for every change:

| Attribute | Definition of "good" here |
| --- | --- |
| **Correctness** | One person, one vote is enforced atomically; results reflect ballots |
| **Auditability** | Every biometric/ballot action is traceable (`biometric_logs`, commit history) |
| **Security** | No credential or voter/biometric data exposure; server-side enforcement |
| **Usability** | The `demo.md` end-to-end flow completes without a walkthrough |

---

## 5. Verification activities

| Activity | Scope | When | Evidence |
| --- | --- | --- | --- |
| Static check | `php -l` on changed files; Composer validation | Every change (CI) | CI log |
| Automated tests | Voting-integrity logic, WebAuthn option construction, schema migrations | Every change (CI) | Test results |
| Secret/data scan | No secret or data artifact committed | Every change (CI) | Scanner output |
| Peer review | Diff read against policy | Every change | PR approval |
| Acceptance | Change Request acceptance criteria | Before merge | Checked in PR |
| End-to-end | `demo.md` smoke flow | Before a release | Release note |

---

## 6. Quality records

| Record | Where | Retention |
| --- | --- | --- |
| Test results | CI logs | GitHub default |
| Review approvals | PR | Permanent |
| Acceptance evidence | PR description | Permanent |
| Release verification | Release note | Permanent |
| QA exceptions | CR QA note | Permanent |

---

## 7. Definition of Done

A change is Done when **all** of:

1. Acceptance criteria (from the CR/SRS) are met and evidenced.
2. CI is green (lint, audit, secret scan).
3. Review approved by a non-author.
4. Relevant tests added/updated (or a stated QA exception).
5. User-facing docs updated if behaviour changed.

---

## 8. Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial policy |
