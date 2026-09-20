# Production Requirements Plan — Forward Requirements

> **Layer:** Forward requirements (intent-derived, not reverse-engineered)
> **Complements:** [`02-software-requirements-specification.md`](02-software-requirements-specification.md) (the *as-built* baseline)
> **Status:** Plan · **Owner:** Technical Lead + Process Owner · **Date:** 2026-09-20

---

## 0. How to read this document

`02-software-requirements-specification.md` describes **what exists**. This document
describes **what a production election system would require**, derived forward from
legal obligations, election-integrity principles, and published standards — not from
the current code.

Three warnings before the tables:

1. **The first requirement is not technical.** Whether the project may run an
   electronic voting channel at all is a legal question (PR-LGL-1). Every other
   requirement is contingent on it. A technical plan that starts before this gate is
   closed is wasted effort.
2. **The current architecture cannot be "hardened" into production.** It is a
   single-file SQLite database behind one unscoped PHP application with secrets in
   source. Production requires a different trust architecture, not better
   configuration of this one. See PR-MIG-1.
3. **The current system does not achieve ballot secrecy** (PR-INT-3). A ballot is
   cast on the same device, moments after that citizen's identity is verified, in
   front of booth staff. Identity and vote are linked at the point of casting. This
   is a genuine, non-obvious flaw — not a hardening item.

**Priority** = Must (blocks the phase) / Should / Could.
**Phase** = P0–P6 (see §6). **Verification** = how the requirement is proven.

---

## 1. Production context and trust model

| Dimension | Demo | Production |
| --- | --- | --- |
| Electors | synthetic seed set | ~968 million registered; ~1.05 million polling stations |
| Per-constituency scale | handful | 1.5–2.5 million electors |
| Trust anchor | *the software is correct* | **software independence** — a bug cannot alter the outcome undetectably |
| Ballot secrecy | not achieved | constitutionally required |
| Data store | one SQLite file | distributed, audited, independently controlled |
| Operators | 2 developers | election officials, auditors, party agents, observers |
| Legal basis | educational demo | statutory (see P0) |

**Threat model actors** (all in scope for production; only some for the demo):
remote attackers · organised cyber-crime · **insiders with privileged access** ·
polling-booth staff · political parties and their agents · the software vendor ·
supply-chain compromise · coercion of the voter in the booth.

**Assets:** the elector roll · the ballot box (ballots) · tally keys · the tally ·
biometric/identity data · audit logs · the source and build pipeline.

---

## 2. PR-LGL — Legal, regulatory and lawful-basis requirements

| ID | Requirement | Rationale | Priority | Phase | Verification |
| --- | --- | --- | --- | --- | --- |
| **PR-LGL-1** | The voting **channel** shall be established by statute before any build. Options: (a) booth DRE + paper trail, (b) ETPBS-style postal, (c) internet i-voting. | In India, internet/remote voting is **not currently lawful**; remote voting (ECI's Multi-Constituency RVM prototype, 2022) requires **Parliament to amend the RP Act**. ETPBS covers only service voters. | Must | P0 | Written legal opinion + statutory authority |
| **PR-LGL-2** | The system shall comply with the **DPDP Act 2023 and DPDP Rules 2025** (notified 13 Nov 2025): lawful basis, consent, purpose limitation, data-principal rights. | Personal data of electors is processed at national scale. | Must | P1 | DPIA + DPO sign-off |
| **PR-LGL-3** | A **Data Protection Impact Assessment** shall precede processing, and a Data Protection Officer shall be appointed. | DPDP obligations for large-scale processing. | Must | P1 | DPIA document |
| **PR-LGL-4** | Breach notification shall meet **DPDP** (to the Data Protection Board) **and CERT-In (6 hours)** timelines, with **180-day** log retention. | Two overlapping regimes; the shorter clock governs operationally. | Must | P1 | Drill evidence |
| **PR-LGL-5** | Identity verification shall conform to **Aadhaar Act / UIDAI regulations** if Aadhaar is used; Aadhaar numbers shall not be stored in the application. | UIDAI restricts storage; reference via Vault only. | Must | P1 | Architecture review |
| **PR-LGL-6** | The system shall comply with the **RP Act** and ECI directions for the chosen channel, including prescribed forms and records. | Statutory election conduct. | Must | P0 | Legal review |
| **PR-LGL-7** | Retention and destruction schedules shall be defined per data class, with secure erasure. | Purpose limitation; legal retention duties. | Should | P1 | Retention register |

---

## 3. PR-GOV — Governance, standards and certification

| ID | Requirement | Rationale | Priority | Phase | Verification |
| --- | --- | --- | --- | --- | --- |
| **PR-GOV-1** | The system shall be assessed against **VVSG 2.0** (EAC, 2021) principles — principally **software independence**. | Internationally recognised baseline for voting-system assurance. | Must | P1 | Gap assessment |
| **PR-GOV-2** | The system shall conform to **CoE CM/Rec(2017)5** (adopted 14 Jun 2017) on e-voting — notably **ballot secrecy** and **verifiability**. | The main international legal standard for e-voting. | Must | P1 | Conformance review |
| **PR-GOV-3** | Interchange of ballot data shall follow **IEEE 1622** formats where blank-ballot distribution applies. | Standard EDI for ballot distribution. | Could | P3 | Format test |
| **PR-GOV-4** | A **separation of duties** regime shall separate: system builder, system operator, and outcome certifier. | No party shall be able to both operate and certify the same election. | Must | P2 | Org design |
| **PR-GOV-5** | Certification shall be performed by an **accredited independent testing laboratory**. | Vendor self-attestation is not assurance. | Must | P4 | Lab report |
| **PR-GOV-6** | The source code shall be available for **independent security review**, with escrow for continuity. | Public trust requires inspectability; continuity against vendor failure. | Must | P4 | Escrow agreement |
| **PR-GOV-7** | A published **transparency policy** shall define what is disclosed publicly and when. | Legitimacy depends on scrutineers being able to verify. | Should | P4 | Published policy |

---

## 4. PR-INT — Ballot integrity, secrecy and verifiability

This is the category that most distinguishes production from the demo.

| ID | Requirement | Rationale | Priority | Phase | Verification |
| --- | --- | --- | --- | --- | --- |
| **PR-INT-1** | The system shall exhibit **software independence**: a flaw in the software cannot change the outcome without detection. | VVSG 2.0's central property. The demo fails this completely — correctness rests entirely on trusting one application and one database. | Must | P1 | Design review + audit results |
| **PR-INT-2** | Every ballot shall have a **voter-verifiable record** — a paper audit trail (VVPAT) or, for paperless designs, an **end-to-end verifiable (E2E-V)** cryptographic proof (individual + universal verifiability). | Satisfies PR-INT-1. Paper trail and E2E-V are alternative mechanisms; one is required. | Must | P1 | Audit / proof verification |
| **PR-INT-3** | **Ballot secrecy** shall be achieved: no party, including booth staff or the system, may link an elector's identity to their vote. | A fundamental democratic principle (CoE CM/Rec(2017)5). **The demo links identity to the cast vote at the same device.** | Must | P1 | Threat-model review + protocol analysis |
| **PR-INT-4** | The system shall be **receipt-free / coercion-resistant** to the extent required by the channel, or coercion mitigations shall be documented where it is not achievable. | In-person coercion is a real threat at booths. | Should | P2 | Design + policy |
| **PR-INT-5** | Cast-as-intended verification shall allow the voter to confirm the recorded vote matches their intent. | Catches malicious client/organiser substitution. | Must | P2 | Protocol test |
| **PR-INT-6** | Recorded-as-cast and counted-as-recorded verification shall be independently performable. | Completes E2E-V where used. | Should | P2 | Verification report |
| **PR-INT-7** | The tally shall be **cryptographically verifiable** (e.g. homomorphic tally or verifiable mixnet) where not paper-based. | Removes trust in the tally program. | Should | P2 | Independent verification |
| **PR-INT-8** | **Risk-limiting audits (RLA)** shall be conducted on the voter-verifiable record where one exists. | Statistically sound confirmation of the outcome. | Must | P5 | Audit report |
| **PR-INT-9** | One-person-one-vote shall be enforced by **cryptographic or ledger mechanism**, not only a mutable flag. | The demo's `has_voted` flag is a single point of failure under insider attack. | Must | P2 | Design + test |

---

## 5. PR-IDN / PR-SEC / PR-PRV — Identity, security, privacy

### Identity and eligibility

| ID | Requirement | Rationale | Priority | Phase |
| --- | --- | --- | --- | --- |
| **PR-IDN-1** | Enrollment shall occur in person with documentary proof, recorded in an append-only elector ledger. | Integrity of the roll. | Must | P2 |
| **PR-IDN-2** | De-duplication shall be performed against the national roll (EPIC/Aadhaar-reference). | Prevents duplicate electors. | Must | P2 |
| **PR-IDN-3** | Biometric templates shall never leave a certified device; only a **verification outcome** shall be transmitted. | UIDAI norms; the demo already does this correctly — preserve it. | Must | P2 |
| **PR-IDN-4** | Identity verification shall be **liveness-resistant** to presentation/spoof attacks. | The demo's face match (0.55 Euclidean threshold) is spoofable by a photograph. | Must | P3 |

### Security and key management

| ID | Requirement | Rationale | Priority | Phase |
| --- | --- | --- | --- | --- |
| **PR-SEC-1** | All secrets shall live in a **managed secrets store**; none in source or images. | Closes gap G-01; production baseline. | Must | P2 |
| **PR-SEC-2** | Tally/decryption keys shall be held in **HSMs** under **threshold cryptography** and **dual control** with split knowledge. | No single insider or compromise can decrypt the vote. | Must | P2 |
| **PR-SEC-3** | A documented **key ceremony** (witnessed, recorded, reproducible) shall govern key generation and use. | Key compromise is unrecoverable. | Must | P2 |
| **PR-SEC-4** | Access shall be **RBAC with least privilege**, MFA, and just-in-time elevation, fully logged. | Insider threat. | Must | P2 |
| **PR-SEC-5** | All state-changing operations shall be **idempotent and auditable**, with replay protection. | The kiosk CSRF/nonce design is the right pattern — generalise it. | Must | P2 |
| **PR-SEC-6** | A formal **vulnerability management** programme shall exist (disclosure policy, SLAs, pen tests). | Ongoing assurance. | Must | P3 |
| **PR-SEC-7** | The build pipeline shall produce a **signed, reproducible build** with an **SBOM**. | Supply-chain integrity. | Must | P3 |
| **PR-SEC-8** | Dependency risk shall be managed (pinned, vetted, updated). | `composer.lock` exists; production needs policy + scanning. | Must | P2 |

### Privacy and data protection

| ID | Requirement | Rationale | Priority | Phase |
| --- | --- | --- | --- | --- |
| **PR-PRV-1** | Data minimisation shall apply: collect only what the channel requires. | DPDP purpose limitation. | Must | P1 |
| **PR-PRV-2** | The **vote record shall be unlinkable** to the elector's identity in storage. | Technical enforcement of ballot secrecy. | Must | P1 |
| **PR-PRV-3** | Personal data shall be encrypted at rest and in transit with managed keys. | Baseline protection. | Must | P2 |
| **PR-PRV-4** | Data-principal rights (access, correction, erasure where applicable) shall be operable, with election-law exceptions documented. | DPDP rights vs. statutory retention. | Should | P2 |
| **PR-PRV-5** | No raw biometric data shall be stored anywhere in the system. | Already true in the demo; keep it invariant. | Must | P2 |

---

## 6. PR-AUD / PR-AVL / PR-OPS / PR-ACC / PR-QLT / PR-INF

### Auditability and transparency

| ID | Requirement | Rationale | Priority | Phase |
| --- | --- | --- | --- | --- |
| **PR-AUD-1** | Audit logs shall be **tamper-evident** (append-only, hash-chained or externally anchored). | The demo's DB may be edited by anyone with file access. | Must | P2 |
| **PR-AUD-2** | Every privileged action shall record actor, time, purpose and outcome. | Forensics and scrutiny. | Must | P2 |
| **PR-AUD-3** | Candidate agents and observers shall have real-time visibility of prescribed events. | Statutory scrutineering. | Should | P4 |
| **PR-AUD-4** | Logs shall be retained ≥ 180 days (CERT-In) and per election-law retention. | Regulatory. | Must | P2 |

### Availability, scale and resilience

| ID | Requirement | Rationale | Priority | Phase |
| --- | --- | --- | --- | --- |
| **PR-AVL-1** | The system shall serve election-day peak load across ~1.05M booths / 968M electors. | SQLite-in-a-file cannot. Requires horizontal architecture. | Must | P3 |
| **PR-AVL-2** | A **degraded mode / paper fallback** shall exist for connectivity loss. | The demo requires live connectivity (documented limitation). | Must | P3 |
| **PR-AVL-3** | DR shall meet defined RTO/RPO with tested failover. | Election continuity. | Must | P3 |
| **PR-AVL-4** | The system shall resist DDoS and targeted disruption. | Adversarial environment. | Must | P3 |

### Operability and incident response

| ID | Requirement | Rationale | Priority | Phase |
| --- | --- | --- | --- | --- |
| **PR-OPS-1** | A 24×7 election-period operations centre shall support booths with defined runbooks. | Field operations at national scale. | Must | P4 |
| **PR-OPS-2** | Incident response shall be rehearsed against both CERT-In (6h) and DPDP clocks. | Two regimes; drills are the only proof. | Must | P4 |
| **PR-OPS-3** | Anomaly detection shall flag turnout/integrity anomalies in real time. | Early warning. | Should | P4 |
| **PR-OPS-4** | Post-election review shall feed the process improvement cycle. | Ties to IDEAL *Leveraging*. | Should | P6 |

### Accessibility and usability

| ID | Requirement | Rationale | Priority | Phase |
| --- | --- | --- | --- | --- |
| **PR-ACC-1** | The interface shall meet **WCAG 2.2 AA** and voting-accessibility requirements (assisted voting, screen-reader support). | Enfranchisement is a legal duty. | Must | P4 |
| **PR-ACC-2** | The interface shall support the constitutionally recognised languages of the constituency. | The demo is English-only. | Must | P4 |
| **PR-ACC-3** | Usability shall be validated with real electors, including first-time and low-literacy voters. | Ballot error is disenfranchisement. | Must | P4 |

### Quality and assurance

| ID | Requirement | Rationale | Priority | Phase |
| --- | --- | --- | --- | --- |
| **PR-QLT-1** | Independent verification & validation shall be performed by a party separate from the developers. | Closes gap G-02 at production rigour. | Must | P3 |
| **PR-QLT-2** | Integrity-critical paths shall be covered by **formal methods or exhaustive testing**. | Software independence depends on it. | Should | P3 |
| **PR-QLT-3** | The SDLC shall follow the KA10 framework at a **higher capability profile** (target ≥ Managed, PA.2.x). | Processes must scale with consequence. | Must | P2 |
| **PR-QLT-4** | Defect escape rate and test coverage shall be measured and gated in CI. | The demo has zero automated tests. | Must | P2 |

### Infrastructure and supply chain

| ID | Requirement | Rationale | Priority | Phase |
| --- | --- | --- | --- | --- |
| **PR-INF-1** | Production infrastructure shall be **segmented** (enrollment / ballot / tally / audit zones). | Containment and separation of duties. | Must | P3 |
| **PR-INF-2** | Configuration shall be declarative, reviewed, and drift-detected. | Reproducibility. | Should | P3 |
| **PR-INF-3** | The database shall be a replicated, transactionally sound store with verifiable integrity — **SQLite is not used in production**. | Scale and tamper-evidence. | Must | P3 |

---

## 7. PR-MIG — Transition from the current demo

| ID | Requirement | Rationale | Priority | Phase |
| --- | --- | --- | --- | --- |
| **PR-MIG-1** | The production system shall be **re-architected, not incrementally hardened**, from this demo. Reuse is limited to: the WebAuthn server-side verification pattern, the idempotent-migration discipline, and the fairness of the kiosk's server-side guards. | The current trust model (one app + one SQLite file + secrets in source) is structurally non-production. | Must | P1 |
| **PR-MIG-2** | The demo codebase shall be **clearly labelled non-production** and prevented from being deployed into any real election. | BR-12; avoids accidental misuse. | Must | P0 |
| **PR-MIG-3** | Retained components shall be re-verified against production requirements before reuse. | A pattern working in a demo is not evidence for production. | Must | P2 |

---

## 8. Phased roadmap

```
P0  Lawful basis & feasibility ──▶ GATE: statute permits the channel
P1  Trust architecture & compliance design
P2  Foundational security, identity, audit
P3  Scale, resilience, assurance, supply chain
P4  Certification, accessibility, operations
P5  Monitored pilot + risk-limiting audit
P6  Operate, audit, improve (IDEAL cycle)
```

| Phase | Objective | Key requirements | Exit criteria |
| --- | --- | --- | --- |
| **P0** | Establish whether and how the election may run electronically | PR-LGL-1, PR-LGL-6, PR-MIG-2 | Written statutory authority for the chosen channel; demo labelled non-production |
| **P1** | Design the trust architecture and compliance posture | PR-INT-1…3, PR-LGL-2/3/5, PR-GOV-1/2, PR-PRV-1/2, PR-MIG-1 | Software-independence strategy chosen; DPIA complete; conformance gap report |
| **P2** | Build the security, identity and audit foundations | PR-SEC-1…5, PR-IDN-1…3, PR-PRV-3/5, PR-AUD-1/2/4, PR-INT-9, PR-GOV-4, PR-QLT-3/4 | Secrets managed; keys in HSM under threshold; tamper-evident logs; RBAC; CI gates |
| **P3** | Achieve scale, resilience and independent assurance | PR-AVL-1…4, PR-INF-1/3, PR-QLT-1, PR-SEC-6/7, PR-IDN-4 | Load test at target scale; DR tested; pen test passed; SBOM + signed builds |
| **P4** | Certify, make accessible, operationalise | PR-GOV-5/6/7, PR-ACC-1…3, PR-OPS-1/2/3 | Accredited lab certification; WCAG 2.2 AA validated; ops centre staffed and drilled |
| **P5** | Pilot under scrutiny | PR-INT-8, PR-GOV-3 | Limited monitored election + risk-limiting audit report |
| **P6** | Operate and improve | PR-OPS-4, all | Post-election review; next IDEAL cycle opened |

**Critical path:** P0 → P1 (PR-INT-1 and PR-INT-3) → P4. If software independence or
ballot secrecy is unsolved, no amount of P2/P3 hardening produces a legitimate
system.

---

## 9. Traceability: current gaps → production requirements

| Current gap / weakness | Production requirement(s) |
| --- | --- |
| G-01 secrets in source | PR-SEC-1, PR-LGL-4 |
| G-02 no automated tests | PR-QLT-1, PR-QLT-4 |
| G-03 tracked DB / no CM hygiene | PR-INF-3, PR-AUD-1, PR-MIG-1 |
| Trust rests entirely on the software | PR-INT-1, PR-INT-2, PR-INT-8 |
| Identity linked to vote at the kiosk | PR-INT-3, PR-PRV-2 |
| `has_voted` flag is the only one-vote control | PR-INT-9 |
| Single-file SQLite store | PR-AVL-1, PR-INF-3 |
| Face match 0.55 threshold, spoofable | PR-IDN-4 |
| English-only UI | PR-ACC-2 |
| Demo requires live connectivity | PR-AVL-2 |
| Incidents unhandled | PR-OPS-2, PR-LGL-4 |
| Zero capability maturity (assessment cycle 0) | PR-QLT-3 |

---

## 10. Decisions required (to be raised as Change Requests)

| ADR | Decision |
| --- | --- |
| ADR-P1 | Channel: booth DRE + paper trail vs ETPBS vs internet i-voting (or a hybrid) |
| ADR-P2 | Software-independence mechanism: VVPAT vs E2E-V cryptography vs both |
| ADR-P3 | Tally design: paper count, homomorphic tally, or verifiable mixnet |
| ADR-P4 | Identity: Aadhaar-reference vs EPIC-only vs certified biometric device |
| ADR-P5 | Build vs buy the certified platform |
| ADR-P6 | Data residency and key custody model |

Each ADR follows the repository's decision-record convention
(see the model in [`../../kiosk-plan.md`](../../kiosk-plan.md)) and is raised via
[`../process/procedures/change-management.md`](../process/procedures/change-management.md).

---

## 11. Non-goals and anti-requirements

| Anti-requirement | Why it is refused |
| --- | --- |
| "Harden the demo into production" | Structurally impossible (PR-MIG-1); would produce false assurance |
| Internet voting without end-to-end verifiability | Cannot satisfy software independence (PR-INT-1) |
| Paperless voting without a verifiable record | Eliminates the only independent check on the software |
| Storing raw biometrics | Violates UIDAI norms and PR-PRV-5 |
| A single administrator able to modify results | Violates PR-GOV-4 and PR-INT-1 |
| "Trust us, the code is closed" | Refuses PR-GOV-6; legitimacy requires inspectability |
| Claiming certification not held | BR-12 |

---

## 12. Principal risks

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Legal gate (P0) never clears | Whole programme is void | Resolve before any build; treat as kill-gate |
| Software independence judged unachievable for the channel | Outcome unverifiable | Choose paper-trail channel instead of i-voting |
| Insider compromise of keys | Outcome manipulation | Threshold crypto + HSM + dual control (PR-SEC-2/3) |
| Scale underestimated | Election-day failure | Load testing as a P3 exit criterion |
| Certification discovered late | Rebuild | Engage accredited lab at P1, not P4 |
| Scope creep across 6 phases | Nothing ships | Phase gates are hard; Requirements change via CR only |

---

## 13. Effort shape (indicative)

This is a multi-year, multi-team programme, not a feature. Realistically:
legal/policy workstream (P0–P1) in parallel with an architecture workstream (P1),
a build workstream (P2–P3), an assurance workstream (P3–P5), and an operations
workstream (P4–P6). Independent V&V and accredited-lab engagement should begin at
P1 even though their deliverables land at P4 — engaging them late is the most
commonly cited cause of e-voting programme failure.

---

## 14. References

- Election Commission of India — Multi-Constituency Remote Electronic Voting
  Machine (RVM) prototype, 2022; Electronically Transmitted Postal Ballot System
  (ETPBS).
- US Election Assistance Commission — **Voluntary Voting System Guidelines
  (VVSG) 2.0**, adopted 10 Feb 2021 (software independence, verifiability).
- Council of Europe — **Recommendation CM/Rec(2017)5** on standards for e-voting,
  adopted 14 Jun 2017 (ballot secrecy, verifiability).
- NIST — Voting programme / VVSG introduction.
- IEEE — **IEEE 1622-2011**, electronic distribution of blank ballots (data
  interchange).
- India — **Digital Personal Data Protection Act 2023** and **DPDP Rules 2025**
  (notified 13 Nov 2025); **CERT-In Directions 2022** (6-hour incident reporting,
  180-day log retention); **Aadhaar Act / UIDAI** regulations; **Representation of
  the People Act**.
- Rivest & Wack — *On the notion of "software independence" in voting systems*
  (NIST, 2006).

---

## Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial forward requirements plan |
