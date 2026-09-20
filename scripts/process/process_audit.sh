#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# process_audit.sh — Process Asset Library integrity check
# Enforces: PROCESS.md and docs/process/05-process-asset-library.md
# Runs in:   .github/workflows/ci.yml  (also runnable locally)
#
# 1. Every required process asset exists.
# 2. Every relative markdown link in the PAL resolves to a real file.
# Exits 1 on any failure.
# ---------------------------------------------------------------------------
set -u

ROOT="$(git rev-parse --show-toplevel 2>/dev/null || echo .)"
cd "$ROOT" || exit 2

say() { printf '%s\n' "$*"; }

# ---------------------------------------------------------------------------
# Required assets
# ---------------------------------------------------------------------------
REQUIRED="
PROCESS.md
CONTRIBUTING.md
docs/process/00-framework-overview.md
docs/process/01-process-definition.md
docs/process/02-process-assessment.md
docs/process/03-process-measurement.md
docs/process/04-process-improvement.md
docs/process/05-process-asset-library.md
docs/process/policies/configuration-management.md
docs/process/policies/quality-assurance.md
docs/process/policies/security-and-secrets.md
docs/process/policies/code-review.md
docs/process/procedures/change-management.md
docs/process/procedures/defect-management.md
docs/process/procedures/testing.md
docs/process/procedures/release.md
docs/process/templates/change-request.md
docs/process/templates/project-plan.md
docs/process/templates/software-requirements-specification.md
docs/process/templates/software-design-description.md
docs/process/templates/test-plan.md
docs/process/templates/measurement-report.md
docs/process/records/README.md
docs/requirements/README.md
docs/requirements/01-business-requirements.md
docs/requirements/02-software-requirements-specification.md
docs/requirements/03-production-requirements-plan.md
.github/workflows/ci.yml
.github/pull_request_template.md
.github/CODEOWNERS
scripts/process/check_secrets.sh
scripts/process/process_audit.sh
"

say "== Required process assets =="
missing=0
for f in $REQUIRED; do
  if [ ! -f "$f" ]; then
    printf '  [x] missing required asset — %s\n' "$f"
    missing=$((missing + 1))
  fi
done
[ "$missing" -eq 0 ] && say "  all required assets present."

# ---------------------------------------------------------------------------
# Markdown link integrity
# Defined as a function so `case` is parsed at top level, not inside $(...),
# which macOS bash 3.2 mis-parses.
# ---------------------------------------------------------------------------
report_broken_links() {
  git ls-files '*.md' | grep -v '^vendor/' | while IFS= read -r md; do
    [ -f "$md" ] || continue
    dir="$(dirname "$md")"
    grep -oE '\]\([^)]+\)' "$md" 2>/dev/null \
      | sed -E 's/^\]\(//; s/\)$//' \
      | while IFS= read -r target; do
          # skip external links and pure anchors
          case "$target" in
            http://*|https://*|mailto:*) continue ;;
          esac
          case "$target" in
            '#'*) continue ;;
          esac
          clean="${target%%#*}"
          [ -n "$clean" ] || continue
          case "$clean" in
            /*) abs="$clean" ;;
            *)  abs="$dir/$clean" ;;
          esac
          if [ ! -e "$abs" ]; then
            printf '  [x] broken link — %s -> %s\n' "$md" "$target"
          fi
        done
  done
}

say "== Markdown link integrity =="
LINK_REPORT="$(mktemp 2>/dev/null || echo /tmp/pal_links.$$)"
report_broken_links > "$LINK_REPORT"
broken=$(grep -c . "$LINK_REPORT" 2>/dev/null | tr -d ' ')
broken=${broken:-0}
[ "$broken" -eq 0 ] && say "  all relative links resolve."
cat "$LINK_REPORT"
rm -f "$LINK_REPORT"

# ---------------------------------------------------------------------------
# Verdict
# ---------------------------------------------------------------------------
say ""
total=$((missing + broken))
if [ "$total" -eq 0 ]; then
  say "PASS: process_audit clean (PAL intact, all links resolve)."
  exit 0
fi
say "FAIL: process_audit found $total issue(s) ($missing missing asset(s), $broken broken link(s))."
exit 1
