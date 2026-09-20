<!--
PR template — enforces the Code Review Policy and Definition of Done.
See docs/process/policies/code-review.md and quality-assurance.md
-->

## What & why

<!-- One paragraph. Link the Change Request. -->
Refs #

## Change type

- [ ] Feature
- [ ] Defect fix (S1/S2 require a regression test)
- [ ] Refactor
- [ ] Process / docs
- [ ] Security

## Process checklist

- [ ] A Change Request exists and is linked above (`procedures/change-management.md`)
- [ ] Acceptance criteria are met and evidenced
- [ ] CI is green (lint, process gates)
- [ ] Tests added/updated — **or** a QA exception is stated below
- [ ] Reviewer is **not** the author (segregation of duties)
- [ ] Docs updated if behaviour changed

## Security checklist

Required if this touches auth, verification, ballot, config, or RP ID
(`policies/security-and-secrets.md` §3):

- [ ] No secret in a tracked file
- [ ] Enforcement remains server-side (no client-only trust path)
- [ ] Vote casting still uses the atomic conditional UPDATE
- [ ] RP ID / origin handling unaffected, or explicitly assessed
- [ ] No personal/biometric data added to logs, seeds, or tests

## Verification evidence

<!-- Commands run, screenshots, demo.md step that was exercised -->

```
bash scripts/process/check_secrets.sh
bash scripts/process/process_audit.sh
```

## QA exception (if any)

<!-- If no tests were added, state why and what was substituted. -->

## Reviewer notes

<!-- Blocking vs non-blocking. Explain the "why". -->
