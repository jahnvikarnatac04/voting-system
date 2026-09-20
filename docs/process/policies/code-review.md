# Policy — Code Review

> **SWEBOK KA10.1 (process infrastructure) · supports SI.5 in KA10.2**
> **Status:** Binding · **Owner:** Reviewer · **Review:** each IDEAL cycle

---

## 1. Purpose

To catch defects, enforce consistency, and spread knowledge before a change reaches
`main`. Review is a **verification activity** (see QA Policy) and the primary
quality gate for a team too small to have dedicated testers.

---

## 2. When review is required

| Change | Review required |
| --- | --- |
| Any change touching auth, verification, ballot, or schema | **Yes** — 1 non-author approval |
| Any change to a process asset or policy | **Yes** — Process Owner for policy/metrics |
| Any change to dependencies | **Yes** — plus a security note |
| Docs-only, typo, formatting | **Yes**, lightweight (may be same-day) |

The author may never approve their own change (segregation of duties).

---

## 3. Review sizing

- Prefer small PRs: **target < 400 changed lines**. Large diffs get shallow
  reviews — split them.
- One logical change per PR. A refactor and a behaviour change are two PRs.
- The PR description states the CR ID, what changed, and how it was verified.

---

## 4. Reviewer criteria

A reviewer checks, in order:

1. **Correctness** — does it do what the CR/SRS says, including edge cases
   (double vote, expired authorization, wrong constituency)?
2. **Security** — server-side enforcement? secrets? the Secure Change Review
   Checklist in `security-and-secrets.md` §3?
3. **Auditability** — are important actions still logged?
4. **Compatibility** — does it break the existing schema pattern or migration path?
5. **Clarity** — is it understandable without the author present?
6. **Tests** — are they present and meaningful, or is a QA exception stated?
7. **Policy** — does it comply with the CM, QA, and Security policies?

---

## 5. Review dimensions and how to comment

| Verdict | Use |
| --- | --- |
| **Approve** | Meets all criteria; ready to merge |
| **Request changes** | A blocking defect, security issue, or missing criterion |
| **Comment** | Non-blocking suggestion; must not be silently ignored |

Comments explain the *why*, not just the *what*. Distinguish blocking from optional
explicitly (`nit:`, `blocking:`).

---

## 6. Reviewer responsibilities

- Review against the **acceptance criteria**, not personal style.
- Do not rubber-stamp; a review that found nothing should still confirm the criteria.
- If the change is too large or unclear to review, request a split — do not approve
  blind.

---

## 7. Author responsibilities before requesting review

- [ ] CI green
- [ ] PR description links a CR ID
- [ ] Tests added/updated, or a QA exception stated
- [ ] Docs updated if behaviour changed
- [ ] Self-reviewed the diff first

---

## 8. Records

Approvals are recorded on the PR (permanent). Review findings that reveal a process
gap are added to the gap register (`../02-process-assessment.md` §4).

---

## 9. Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial policy |
