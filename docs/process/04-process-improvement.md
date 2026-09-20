# 04 — Process Improvement

> **SWEBOK KA10 sub-area 10.5 — Software Engineering Process Improvement**

This document is the **engine** of the framework: how we move from the cycle-0
baseline in `02-process-assessment.md` to a measurably more capable process. It
uses the **IDEAL model** for sequencing and **PDCA** for the inner control loop.

---

## 1. Why IDEAL

IDEAL fits this entity for three reasons: it starts by *diagnosing before fixing*
(we already have the diagnosis in the gap register), it is explicitly cyclic (so it
survives a two-person team), and it separates *establishing* from *acting* — which
prevents the classic VSE failure of adopting a process on paper and never piloting
it.

---

## 2. IDEAL phases mapped to this repository

### I — Initiating *(complete)*

| Action | Outcome |
| --- | --- |
| Secure sponsorship | Maintainers own the framework (`CODEOWNERS`) |
| Define scope | Whole repository, whole process, framework-basis SWEBOK KA10 |
| Establish the infrastructure | This PAL; `PROCESS.md` index |
| Set the improvement objective | "Move processes from *performed* to *managed*, closing Critical/High gaps." |

### D — Diagnosing *(complete)*

| Action | Outcome |
| --- | --- |
| Appraise current capability | `02-process-assessment.md` §2 baseline |
| Identify strengths to preserve | `02-process-assessment.md` §3 |
| Build the gap register | `02-process-assessment.md` §4 (G-01…G-10) |
| Select candidate improvements | The backlog in §3 below |

### E — Establishing *(this cycle)*

| Action | Outcome |
| --- | --- |
| Define processes | `01-process-definition.md` (SI + PM) |
| Define metrics | `03-process-measurement.md` (GQM) |
| Define priorities | Backlog §3, ordered risk × leverage |
| Assign ownership | Each backlog item has an owner role |
| Define the plan | The improvement plan §4 |

### A — Acting *(next)*

| Action | Outcome |
| --- | --- |
| **Pilot** the highest-risk improvement first (G-01 secrets) | Prove the mechanism on one item before scaling |
| Implement against the plan | Code + process changes |
| Institutionalize | Wire into CI so it cannot silently lapse |
| Track via PDCA | §5 |

### L — Leveraging *(continuous)*

| Action | Outcome |
| --- | --- |
| Re-appraise capability | New `02-process-assessment.md` ratings |
| Capture lessons | `docs/process/records/` closure notes |
| Standardize what worked | Promote to policies/templates |
| Feed the next cycle | New gap register → *Diagnosing* again |

---

## 3. Improvement backlog

Ordered by **risk × leverage** (severity from the gap register). "Executable"
means the item is enforced by tooling, not merely documented.

| Rank | ID | Improvement | Gap | Executable? | Owner role | Exit criterion |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | **IMP-01** | Secret hygiene: scanner + rotate/remove committed SMTP creds | G-01 | ✓ `check_secrets.sh`, CI | Tech Lead | Tracked-secret count = 0; creds rotated |
| 2 | **IMP-02** | Add a `.gitignore`; stop tracking `voting_system.db`, `vendor/`, `.DS_Store`, `config.php` | G-03, G-05 | ✓ file | PM | Tracked data-artifact count = 0 |
| 3 | **IMP-03** | Restore CI with lint + audit + secret scan | G-04 | ✓ `ci.yml` | Developer | CI pass rate = 100% |
| 4 | **IMP-04** | Introduce a test harness and first tests for voting integrity | G-02 | ✓ CI gate | Developer | Test count > 0; coverage target set |
| 5 | **IMP-05** | Change intake: PR + issue templates, CR IDs | G-08 | ✓ `.github/` | PM | Linked-issue % ≥ 90% |
| 6 | **IMP-06** | Requirements/design/test templates adopted for increments | G-06 | ✗ template | Tech Lead | Plan recorded for ≥ 90% of increments |
| 7 | **IMP-07** | Start measurement: first Measurement Report | G-07 | ◐ script+template | PM | First report filed in `records/` |
| 8 | **IMP-08** | Closure/retrospective discipline | G-09 | ✗ template | PM | Closure note per release |
| 9 | **IMP-09** | Versioning & release notes | G-10 | ✗ policy | Tech Lead | Tag + notes per milestone |
| 10 | **IMP-10** | Credential management (`config.php` from env, not source) | G-01, G-05 | ✓ policy | Tech Lead | No secret in any tracked file |

Ranks 1–5 are **executable** and therefore the highest-value first moves: they make
the framework self-enforcing. Ranks 6–10 are behavioural and follow once the gates
exist.

---

## 4. Improvement plan (this cycle)

```
Cycle 1 — "Make it self-enforcing"
├── Phase A (Acting) — pilots, in backback order 1→5
│   ├── IMP-01 secrets hygiene        (owner: Tech Lead)      ── pilot first
│   ├── IMP-02 .gitignore             (owner: PM)
│   ├── IMP-03 CI restored            (owner: Developer)
│   ├── IMP-04 first tests            (owner: Developer)
│   └── IMP-05 intake templates       (owner: PM)
│
├── Phase L (Leveraging)
│   ├── Re-appraise (02 §2 update on evidence)
│   ├── File Measurement Report (03 §4)
│   └── Open cycle 2 gap register
│
└── Cycle 2 — "Make it managed"    (target: ranks 6–10, PA.2.x ≥ 2)
```

Each phase closes with a recorded exit review; unclosed items roll to the next
cycle **with a stated reason**, never silently.

---

## 5. PDCA inner loop

Within *Acting*, each improvement runs a PDCA wheel. This is how a VSE avoids
sinking effort into an improvement that does not work.

| Phase | For an improvement item |
| --- | --- |
| **Plan** | State the target metric and threshold from `03-process-measurement.md` |
| **Do** | Pilot on a small scope (one PR, one check) |
| **Check** | Compare the metric against target; record the result |
| **Act** | If it worked → standardize (promote to policy/CI). If not → adjust or abandon, and record why. |

**Example (IMP-01):** Plan → tracked-secret count 0. Do → run the scanner on the
current tree, rotate the SMTP credentials. Check → scanner exits 0. Act →
standardize by adding the scan to CI so drift is impossible.

---

## 6. Governance of improvement

| Concern | Rule |
| --- | --- |
| Who may propose | Anyone (Change Request) |
| Who prioritizes | Process Owner + PM, using the gap register |
| Who approves a new policy/metric | Process Owner (recorded decision) |
| Cadence | One cycle per milestone; re-appraisal at cycle close |
| Evidence rule | A rating moves only on evidence; a gap closes only on a passing criterion |

---

## 7. Anti-patterns explicitly rejected

- **Process theatre** — adopting policies nobody follows. Rejected by keeping the
  framework small and executable.
- **Big-bang adoption** — implementing all 10 improvements at once. Rejected by the
  pilot-first sequencing in §4.
- **Metric vanity** — measuring coverage to look good rather than to decide.
  Rejected by the "no consumer → retire it" rule in `03-process-measurement.md` §5.

---

## 8. Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial roadmap (IDEAL Initiating/Diagnosing complete; Establishing underway) |
