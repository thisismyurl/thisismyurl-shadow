#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONTAINER_NAME="${1:-thisismyurl-shadow-wordpress}"
OUT_DIR="${ROOT_DIR}/artifacts/release-proof"
TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
OUT_FILE="${OUT_DIR}/release-proof-${TIMESTAMP}.txt"

mkdir -p "${OUT_DIR}"

FAILURES=0

log() {
  printf "%s\n" "$1" | tee -a "${OUT_FILE}"
}

check_cmd() {
  local description="$1"
  shift

  log ""
  log "[CHECK] ${description}"

  if "$@" >>"${OUT_FILE}" 2>&1; then
    log "[PASS] ${description}"
  else
    log "[FAIL] ${description}"
    FAILURES=$((FAILURES + 1))
  fi
}

extract_first_number() {
  local file_path="$1"
  local pattern="$2"

  grep -Ei "${pattern}" "${file_path}" | head -n 1 | grep -Eo '[0-9]+' | head -n 1 || true
}

extract_last_number() {
  local file_path="$1"
  local pattern="$2"

  grep -Ei "${pattern}" "${file_path}" | head -n 1 | grep -Eo '[0-9]+' | tail -n 1 || true
}

log "Christopher Ross Shadow Release Proof"
log "Generated: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
log "Container: ${CONTAINER_NAME}"
log "Workspace: ${ROOT_DIR}"
log ""

LINT_FILES=(
  "includes/ui/views/settings-page.php"
  "includes/systems/core/class-settings-registry.php"
  "includes/systems/core/class-hooks-initializer.php"
  "includes/ui/views/dashboard-page-v2.php"
)

for rel_file in "${LINT_FILES[@]}"; do
  check_cmd "PHP lint ${rel_file}" \
    docker exec "${CONTAINER_NAME}" php -l "/var/www/html/wp-content/plugins/thisismyurl-shadow/${rel_file}"
done

log ""
log "[CHECK] Public docs count alignment"
README_FILE="${ROOT_DIR}/README.md"
FEATURES_FILE="${ROOT_DIR}/docs/FEATURES.md"

README_DIAG="$(extract_first_number "${README_FILE}" 'shipped diagnostics')"
FEATURES_DIAG="$(extract_first_number "${FEATURES_FILE}" 'shipped diagnostics')"
README_AUTO="$(extract_first_number "${README_FILE}" 'automated treatments')"
FEATURES_AUTO="$(extract_first_number "${FEATURES_FILE}" 'automated treatments')"
README_GUIDE="$(extract_last_number "${README_FILE}" 'guidance-only treatments')"
FEATURES_GUIDE="$(extract_last_number "${FEATURES_FILE}" 'guidance-only treatments')"

log "README diagnostics=${README_DIAG:-missing}"
log "FEATURES diagnostics=${FEATURES_DIAG:-missing}"
log "README automated=${README_AUTO:-missing}"
log "FEATURES automated=${FEATURES_AUTO:-missing}"
log "README guidance=${README_GUIDE:-missing}"
log "FEATURES guidance=${FEATURES_GUIDE:-missing}"

if [[ -n "${README_DIAG}" && "${README_DIAG}" == "${FEATURES_DIAG}" ]]; then
  log "[PASS] Diagnostics count aligned"
else
  log "[FAIL] Diagnostics count mismatch"
  FAILURES=$((FAILURES + 1))
fi

if [[ -n "${README_AUTO}" && "${README_AUTO}" == "${FEATURES_AUTO}" ]]; then
  log "[PASS] Automated treatment count aligned"
else
  log "[FAIL] Automated treatment count mismatch"
  FAILURES=$((FAILURES + 1))
fi

if [[ -n "${README_GUIDE}" && "${README_GUIDE}" == "${FEATURES_GUIDE}" ]]; then
  log "[PASS] Guidance-only treatment count aligned"
else
  log "[FAIL] Guidance-only treatment count mismatch"
  FAILURES=$((FAILURES + 1))
fi

log ""
if [[ ${FAILURES} -eq 0 ]]; then
  log "Release proof completed with 0 failures."
  log "Report: ${OUT_FILE}"
  exit 0
fi

log "Release proof completed with ${FAILURES} failure(s)."
log "Report: ${OUT_FILE}"
exit 1
