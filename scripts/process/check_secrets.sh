#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# check_secrets.sh — Secret & uncontrolled-data scanner
# Enforces: docs/process/policies/security-and-secrets.md
# Runs in:   .github/workflows/ci.yml  (also runnable locally)
#
# Scans TRACKED files (git ls-files). Exits 1 on any finding.
# Portable (bash 3.2-safe): `case` never appears inside $(...).
# ---------------------------------------------------------------------------
set -u

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || echo .)"
cd "$ROOT" || exit 2

say() { printf '%s\n' "$*"; }

# Candidate tracked files: skip our own pattern file, the process docs, and CI
# (they legitimately contain the words being searched for). Vendor is excluded
# here and reported separately as a single finding.
list_files() {
  git ls-files \
    | grep -v -e '^scripts/process/check_secrets\.sh$' \
    | grep -v -e '^vendor/' \
    | grep -v -e '^docs/process/' \
    | grep -v -e '^\.github/'
}

# --- High-confidence secret patterns: "label|regex" ------------------------
PATTERNS='private key block|-----BEGIN ([A-Z]+ )?PRIVATE KEY-----
AWS access key id|AKIA[0-9A-Z]{16}
Google API key|AIza[0-9A-Za-z_-]{35}
Slack token|xox[baprs]-[0-9A-Za-z-]{10,}
GitHub token|gh[pousr]_[0-9A-Za-z]{36,}
Mailer/credential assignment|->[Pp]assword[[:space:]]*=[[:space:]]*["'"'"'][^"'"'"']{6,}["'"'"']
Credential variable assignment|(smtp_pass|smtp_password|mail_password|db_pass|api_key|api_secret|auth_token|password)[[:space:]]*=[[:space:]]*["'"'"'][^"'"'"']{6,}["'"'"']'

# --- Findings to stdout; one line each -------------------------------------
report_secrets() {
  list_files | while IFS= read -r f; do
    [ -f "$f" ] || continue
    printf '%s\n' "$PATTERNS" | while IFS= read -r entry; do
      [ -n "$entry" ] || continue
      label="${entry%%|*}"
      rx="${entry#*|}"
      matches="$(grep -nEI -e "$rx" "$f" 2>/dev/null)"
      [ -n "$matches" ] || continue
      printf '%s\n' "$matches" | while IFS= read -r hit; do
        value="${hit#*:}"
        # Ignore obvious placeholders.
        case "$value" in
          *YOUR_*|*your_*|*CHANGEME*|*CHANGE_ME*|*PLACEHOLDER*|*placeholder*) continue ;;
          *example*|*Example*|*xxxx*|*XXXX*|*'<...>'*) continue ;;
        esac
        printf '  [x] [secret] %s — %s:%s\n' "$label" "$f" "${hit%%:*}"
      done
    done
  done
}

report_data() {
  list_files | while IFS= read -r f; do
    case "$f" in
      *.db|*.sqlite|*.sqlite3|*.db-journal)
        printf '  [x] [data] runtime database tracked — %s\n' "$f" ;;
      *.DS_Store|.DS_Store|Thumbs.db)
        printf '  [x] [data] OS cruft tracked — %s\n' "$f" ;;
      .env|.env.*)
        printf '  [x] [secret] env file tracked — %s\n' "$f" ;;
    esac
  done
  # Vendored dependencies tracked = one aggregate finding, not hundreds.
  if [ -n "$(git ls-files vendor/ | head -1)" ]; then
    printf '  [x] [data] vendored dependencies tracked — vendor/ (%s files)\n' \
      "$(git ls-files vendor/ | wc -l | tr -d ' ')"
  fi
}

# --- Run -------------------------------------------------------------------
say "== Secret scan (tracked files) =="
SECRET_REPORT="$(mktemp 2>/dev/null || echo /tmp/secrets.$$)"
report_secrets > "$SECRET_REPORT"
[ -s "$SECRET_REPORT" ] || say "  no secret patterns found."
cat "$SECRET_REPORT"

say "== Uncontrolled data artifacts =="
DATA_REPORT="$(mktemp 2>/dev/null || echo /tmp/data.$$)"
report_data > "$DATA_REPORT"
[ -s "$DATA_REPORT" ] || say "  no uncontrolled data artifacts found."
cat "$DATA_REPORT"

secrets=$(grep -c . "$SECRET_REPORT" 2>/dev/null | tr -d ' '); secrets=${secrets:-0}
data=$(grep -c . "$DATA_REPORT" 2>/dev/null | tr -d ' '); data=${data:-0}
rm -f "$SECRET_REPORT" "$DATA_REPORT"

total=$((secrets + data))
say ""
if [ "$total" -eq 0 ]; then
  say "PASS: check_secrets clean (0 findings)."
  exit 0
fi
say "FAIL: check_secrets found $total finding(s) ($secrets secret, $data data)."
say "      Remediate per docs/process/policies/security-and-secrets.md §4 (rotate, remove, ignore)."
exit 1
