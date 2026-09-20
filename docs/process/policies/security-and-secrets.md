# Policy — Security & Secrets

> **SWEBOK KA10.1 (process infrastructure) · supports KA10.2 (Security as a quality attribute)**
> **Status:** Binding · **Owner:** Technical Lead · **Review:** each cycle
> **This policy may not be tailored away** (see `../00-framework-overview.md` §5).

---

## 1. Purpose

To protect credentials, voter data, and biometric material. This policy exists
because the repository currently **contains live SMTP credentials in source**
(gap G-01) and has a **tracked live database** (G-03). This is the highest-severity
finding in the assessment and is improvement **IMP-01**.

---

## 2. Rules — non-negotiable

### 2.1 Secrets

1. **No secret in a tracked file.** Ever. Not credentials, not API keys, not app
   passwords, not private keys.
2. Secrets live in **`includes/config.php`** (untracked, gitignored) or environment
   variables. Only `includes/config.example.php` with placeholders is tracked.
3. **Scanner gate:** `scripts/process/check_secrets.sh` runs in CI and fails the
   build on a detected secret. This is enforcement, not advice.
4. **Rotation on exposure:** any secret committed to history is treated as
   compromised and **must be rotated**, not merely removed in a later commit —
   history retains it. Rotate the credential at the provider.
5. **Never echo a secret** in an issue, PR, log, or chat.

### 2.2 Voter & biometric data

1. The live database (`voting_system.db`) is **not versioned** (see CM Policy §4).
2. Biometric material stays minimal by design — a public credential plus an audit
   row; **no raw fingerprint image is stored** (`kiosk-plan.md` decision record).
3. Personal data in seeds and tests must be **synthetic or anonymized**. Real
   personal data is never committed (DPDP Act 2023 awareness).
4. Audit logs (`biometric_logs`) record *that* a check happened and its outcome —
   not the biometric itself.

### 2.3 WebAuthn / kiosk configuration

1. `WEBAUTHN_RP_ID` is **pinned** via config, never derived from the `Host` header
   in a deployment.
2. A **stable** RP ID hostname is required for any real kiosk. A rotating tunnel
   hostname changes the RP ID and **orphans every enrolled passkey** — the exact
   failure documented in `kiosk-plan.md`. `scripts/set_kiosk_host.php` exists to
   set a stable host; treat its output as an untracked secret-adjacent artifact.
3. Origins are validated against an **allowlist** (`WEBAUTHN_ALLOWED_ORIGINS`).

### 2.4 Server-side trust boundary

1. Authorization and verification decisions are made **server-side**. There is no
   client-only "mark verified" or "mark voted" path.
2. Vote casting keeps the **atomic conditional UPDATE** so a double submit cannot
   record twice.
3. A kiosk session carries **no admin identity**; existing admin guards must keep
   excluding it.

---

## 3. Secure change review checklist

Every PR answers:

- [ ] Does this touch a credential, config, or secret? If so, is it untracked?
- [ ] Does this touch auth/verification/ballot logic? If so, is enforcement server-side?
- [ ] Does this add a dependency? If so, is it pinned in `composer.lock` and needed?
- [ ] Does this log or expose personal/biometric data?
- [ ] Does this change the RP ID / origin handling?

---

## 4. Incident handling

If a secret or personal-data exposure is found:

1. **Contain** — stop further exposure, open a Change Request immediately.
2. **Assess** — what was exposed, for how long, who could read it.
3. **Rotate** — invalidate the credential at the provider.
4. **Remediate** — remove from tracking, add to `.gitignore`, add a scanner rule.
5. **Record** — a closure note (PM.4) so it feeds IDEAL *Leveraging*.

---

## 5. Revision history

| Version | Date | Change |
| --- | --- | --- |
| 0.1 | 2026-09-20 | Initial policy; mandates rotation of exposed SMTP credentials |
