#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DISTIGNORE_FILE="${ROOT_DIR}/.distignore"
PLUGIN_MAIN_FILE="${ROOT_DIR}/thisismyurl-shadow.php"
README_FILE="${ROOT_DIR}/readme.txt"
CHANGELOG_FILE="${ROOT_DIR}/CHANGELOG.md"
FEATURES_FILE="${ROOT_DIR}/docs/FEATURES.md"
DEFAULT_TARGET="$(git -C "${ROOT_DIR}" rev-parse HEAD)"

PUBLISH_GITHUB_RELEASE=0
RUN_RELEASE_PROOF=0
RELEASE_PROOF_CONTAINER="thisismyurl-shadow-wordpress"
VERSION=""
REPO_SLUG=""
TAG_NAME=""
OUTPUT_DIR=""
TARGET_REF="${DEFAULT_TARGET}"

usage() {
	cat <<'EOF'
Usage:
  bash scripts/prepare-wordpress-org-release.sh [options]

Options:
  --version <version>              Override the version read from thisismyurl-shadow.php.
  --repo <owner/repo>              Override the GitHub repository slug.
  --tag <tag>                      Override the Git tag name. Defaults to v<version>.
  --target <git-ref>               Commitish used when creating a GitHub release.
  --output-dir <dir>               Output directory. Defaults to artifacts/releases/<version>.
  --publish-github-release         Create or update the GitHub release and upload assets.
  --run-release-proof              Run scripts/release-proof.sh before packaging.
  --release-proof-container <name> Container name passed to the proof script.
  --help                           Show this help text.

Examples:
  bash scripts/prepare-wordpress-org-release.sh
  bash scripts/prepare-wordpress-org-release.sh --publish-github-release
  bash scripts/prepare-wordpress-org-release.sh --run-release-proof --publish-github-release
EOF
}

log() {
	printf '%s\n' "$1"
}

fail() {
	printf 'Error: %s\n' "$1" >&2
	exit 1
}

require_command() {
	local command_name="$1"
	command -v "${command_name}" >/dev/null 2>&1 || fail "Required command not found: ${command_name}"
}

extract_plugin_version() {
	php -r '$source = file_get_contents($argv[1]); preg_match("/^ \* Version: (.+)$/m", $source, $matches); echo trim($matches[1] ?? "");' "$PLUGIN_MAIN_FILE"
}

extract_stable_tag() {
	php -r '$source = file_get_contents($argv[1]); preg_match("/^Stable tag: (.+)$/mi", $source, $matches); echo trim($matches[1] ?? "");' "$README_FILE"
}

extract_repo_slug() {
	if git -C "${ROOT_DIR}" remote get-url origin >/dev/null 2>&1; then
		local remote_url
		remote_url="$(git -C "${ROOT_DIR}" remote get-url origin)"
		remote_url="${remote_url%.git}"
		remote_url="${remote_url#git@github.com:}"
		remote_url="${remote_url#https://github.com/}"
		remote_url="${remote_url#http://github.com/}"
		printf '%s' "${remote_url}"
		return
	fi

	php -r '$data = json_decode(file_get_contents($argv[1]), true); echo trim((string) ($data["name"] ?? ""));' "${ROOT_DIR}/composer.json"
}

extract_beta_counts_json() {
	php -r '
	$features = file_get_contents($argv[1]);
	$patterns = [
		"diagnostics" => "/-\\s+([0-9]+)\\s+shipped diagnostics/i",
		"automated_treatments" => "/-\\s+([0-9]+)\\s+automated treatments/i",
		"guidance_treatments" => "/-\\s+([0-9]+)\\s+guidance-only treatments/i",
	];
	$result = [];
	foreach ($patterns as $key => $pattern) {
		preg_match($pattern, $features, $matches);
		$result[$key] = isset($matches[1]) ? (int) $matches[1] : 0;
	}
	echo json_encode($result, JSON_UNESCAPED_SLASHES);
	' "${FEATURES_FILE}"
}

extract_release_bullets_json() {
	php -r '
	$version = $argv[1];
	$readme = file_get_contents($argv[2]);
	$pattern = sprintf("/=\\s+%s\\s+=\\R(.*?)(?=\\R=\\s+[^\\r\\n]+\\s+=|\\z)/s", preg_quote($version, "/"));
	$section = "";
	if (preg_match($pattern, $readme, $matches)) {
		$section = trim($matches[1]);
	}
	$lines = preg_split("/\\R/", $section ?: "");
	$bullets = [];
	foreach ($lines as $line) {
		$line = trim($line);
		if (0 === strpos($line, "* ")) {
			$bullets[] = substr($line, 2);
		}
	}
	if (empty($bullets)) {
		$changelog = file_get_contents($argv[3]);
		$headerPattern = sprintf("/^## \\\[\\Q%s\\E\\]\\s*-?.*$/m", $version);
		if (preg_match($headerPattern, $changelog, $match, PREG_OFFSET_CAPTURE)) {
			$start = $match[0][1] + strlen($match[0][0]);
			$tail = substr($changelog, $start);
			if (preg_match("/^## \\\[.*$/m", $tail, $next, PREG_OFFSET_CAPTURE)) {
				$tail = substr($tail, 0, $next[0][1]);
			}
			$lines = preg_split("/\\R/", $tail ?: "");
			foreach ($lines as $line) {
				$line = trim($line);
				if (0 === strpos($line, "- ")) {
					$bullets[] = substr($line, 2);
				}
			}
		}
	}
	echo json_encode(array_values(array_unique($bullets)), JSON_UNESCAPED_SLASHES);
	' "$VERSION" "${README_FILE}" "${CHANGELOG_FILE}"
}

extract_short_description() {
	php -r '
	$lines = file($argv[1], FILE_IGNORE_NEW_LINES);
	$after_header_block = false;
	foreach ($lines as $line) {
		$trimmed = trim($line);
		if (!$after_header_block) {
			if ("" === $trimmed) {
				$after_header_block = true;
			}
			continue;
		}
		if ("" !== $trimmed) {
			echo $trimmed;
			break;
		}
	}
	' "$README_FILE"
}

markdown_bullets_from_json() {
	php -r '
	$data = json_decode($argv[1], true);
	if (!is_array($data) || empty($data)) {
		echo "- Release notes were not found in readme.txt or CHANGELOG.md.\n";
		exit(0);
	}
	foreach ($data as $item) {
		echo "- " . $item . PHP_EOL;
	}
	' "$1"
}

json_get() {
	php -r '$data = json_decode($argv[1], true); $key = $argv[2]; echo is_array($data) && array_key_exists($key, $data) ? (string) $data[$key] : "";' "$1" "$2"
}

ensure_version_alignment() {
	local plugin_version stable_tag
	plugin_version="$(extract_plugin_version)"
	stable_tag="$(extract_stable_tag)"

	[[ -n "${VERSION}" ]] || VERSION="${plugin_version}"
	[[ -n "${VERSION}" ]] || fail 'Could not determine plugin version from thisismyurl-shadow.php.'
	[[ "${VERSION}" == "${plugin_version}" ]] || fail "Requested version ${VERSION} does not match plugin header version ${plugin_version}."
	[[ -n "${stable_tag}" ]] || fail 'Could not determine Stable tag from readme.txt.'
	[[ "${stable_tag}" == "${VERSION}" ]] || fail "readme.txt Stable tag ${stable_tag} does not match version ${VERSION}."
}

prepare_directories() {
	[[ -n "${OUTPUT_DIR}" ]] || OUTPUT_DIR="${ROOT_DIR}/artifacts/releases/${VERSION}"
	ASSETS_DIR="${OUTPUT_DIR}/assets"
	CONTENT_DIR="${OUTPUT_DIR}/content"
	TEMP_BUILD_DIR="${OUTPUT_DIR}/build"
	DIST_BUILD_ROOT="${TEMP_BUILD_DIR}/thisismyurl-shadow"
	ZIP_PATH="${ASSETS_DIR}/thisismyurl-shadow-${VERSION}.zip"
	SHA_PATH="${ASSETS_DIR}/thisismyurl-shadow-${VERSION}.zip.sha256"
	MANIFEST_PATH="${OUTPUT_DIR}/release-manifest.json"
	mkdir -p "${ASSETS_DIR}" "${CONTENT_DIR}" "${DIST_BUILD_ROOT}"
	rm -rf "${DIST_BUILD_ROOT}"
	mkdir -p "${DIST_BUILD_ROOT}"
}

run_release_proof_if_requested() {
	if [[ ${RUN_RELEASE_PROOF} -eq 0 ]]; then
		return
	fi

	[[ -x "${ROOT_DIR}/scripts/release-proof.sh" ]] || fail 'scripts/release-proof.sh is not executable.'
	log "Running release proof script against container ${RELEASE_PROOF_CONTAINER}"
	"${ROOT_DIR}/scripts/release-proof.sh" "${RELEASE_PROOF_CONTAINER}"
}

build_zip() {
	log "Building distributable plugin zip"
	# Ship the developer hook reference even though .distignore excludes docs/
	# and *.md. rsync is first-match-wins, so these includes — placed BEFORE the
	# --exclude-from — keep the docs/ directory walkable and let docs/HOOKS.md
	# through. Every other docs/*.md still falls to the .distignore excludes.
	rsync -a --delete \
		--exclude '.git/' \
		--include='docs/' \
		--include='docs/HOOKS.md' \
		--exclude-from="${DISTIGNORE_FILE}" \
		"${ROOT_DIR}/" "${DIST_BUILD_ROOT}/"

	(
		cd "${TEMP_BUILD_DIR}"
		zip -rq "${ZIP_PATH}" thisismyurl-shadow
	)

	sha256sum "${ZIP_PATH}" > "${SHA_PATH}"
}

generate_content_files() {
	local counts_json release_bullets_json release_bullets_md short_description generated_at
	counts_json="$(extract_beta_counts_json)"
	release_bullets_json="$(extract_release_bullets_json)"
	release_bullets_md="$(markdown_bullets_from_json "${release_bullets_json}")"
	short_description="$(extract_short_description)"
	generated_at="$(date -u '+%Y-%m-%d %H:%M:%S UTC')"

	local diagnostics_count automated_count guidance_count
	diagnostics_count="$(json_get "${counts_json}" diagnostics)"
	automated_count="$(json_get "${counts_json}" automated_treatments)"
	guidance_count="$(json_get "${counts_json}" guidance_treatments)"

	cat > "${CONTENT_DIR}/github-release-notes.md" <<EOF
# Christopher Ross Shadow ${VERSION}

## Summary

${short_description}

This beta release currently highlights:

- ${diagnostics_count} shipped diagnostics across 11 live categories
- ${automated_count} automated treatments with apply and undo support
- ${guidance_count} guidance-only treatments for changes that should remain manual

## What Changed

${release_bullets_md}

## Release Assets

- thisismyurl-shadow-${VERSION}.zip
- thisismyurl-shadow-${VERSION}.zip.sha256

## Validation

- Packaged with the repository's .distignore rules
EOF

	cat > "${CONTENT_DIR}/github-discussion-announcement.md" <<EOF
# Christopher Ross Shadow ${VERSION} beta is available

Christopher Ross Shadow ${VERSION} is ready for testing.

## Release highlights

- ${diagnostics_count} shipped diagnostics across 11 categories
- ${automated_count} automated treatments with apply and undo support
- ${guidance_count} guidance-only treatments for higher-risk or manual-only workflows
- local-first diagnostics, file review, backup, and recovery flows

## What changed in this release

${release_bullets_md}

## What feedback is most useful

- install and upgrade experience from the generated zip
- clarity of findings, treatment copy, and recovery guidance
- anything confusing in the dashboard, file review, or backup flows
- WordPress.org submission feedback, including readme and screenshots

## Download

Use the attached GitHub release assets or the WordPress.org submission package generated for this release.
EOF

	cat > "${CONTENT_DIR}/github-wiki-release-page.md" <<EOF
# Release ${VERSION}

- Generated: ${generated_at}
- Tag: ${TAG_NAME}
- Repository: ${REPO_SLUG}

## Release summary

- ${diagnostics_count} shipped diagnostics
- ${automated_count} automated treatments
- ${guidance_count} guidance-only treatments

## Changelog summary

${release_bullets_md}

## Assets

- ${ZIP_PATH}
- ${SHA_PATH}

## Recommended follow-up

- upload screenshots or refresh existing WordPress.org assets if needed
- verify the zip in a clean WordPress install
- post the announcement content to GitHub Discussions or another public beta channel
EOF

	cat > "${CONTENT_DIR}/wordpress-org-submission-summary.md" <<EOF
# WordPress.org Submission Summary for Christopher Ross Shadow ${VERSION}

## Plugin summary

Christopher Ross Shadow is a local-first WordPress diagnostics and remediation plugin focused on safer changes, plain-English guidance, file review, and recovery workflows.

## Current beta scope

- ${diagnostics_count} shipped diagnostics across 11 categories
- ${automated_count} automated treatments with apply and undo support
- ${guidance_count} guidance-only treatments for changes that should remain manual
- dashboard, Site Health integration, backup, and recovery workflows

## Recommended reviewer notes

- The plugin is intentionally local-first and does not require registration.
- Higher-risk file changes are routed through review workflows instead of being silently applied.
- Public counts should be read from docs/FEATURES.md and the live diagnostics inventory.

## Package details

- Zip: thisismyurl-shadow-${VERSION}.zip
- SHA-256: see thisismyurl-shadow-${VERSION}.zip.sha256

## Release notes summary

${release_bullets_md}
EOF

	cat > "${CONTENT_DIR}/wordpress-org-support-announcement.md" <<EOF
# Christopher Ross Shadow ${VERSION} beta release

Christopher Ross Shadow ${VERSION} is now available for testing.

Highlights in this beta:

- ${diagnostics_count} shipped diagnostics across 11 categories
- ${automated_count} automated treatments with apply and undo support
- ${guidance_count} guidance-only treatments for changes that should stay manual

What changed in this release:

${release_bullets_md}

If you test this build, the most helpful feedback is:

- install or upgrade issues
- confusing diagnostics or remediation copy
- anything unclear in backup, restore, or file review flows
- compatibility issues with common hosting or plugin stacks
EOF

	cat > "${MANIFEST_PATH}" <<EOF
{
  "version": "${VERSION}",
  "tag": "${TAG_NAME}",
  "repo": "${REPO_SLUG}",
  "target": "${TARGET_REF}",
  "generated_at": "${generated_at}",
  "assets": {
    "zip": "${ZIP_PATH}",
    "sha256": "${SHA_PATH}"
  },
  "content": {
    "github_release_notes": "${CONTENT_DIR}/github-release-notes.md",
    "github_discussion": "${CONTENT_DIR}/github-discussion-announcement.md",
    "github_wiki": "${CONTENT_DIR}/github-wiki-release-page.md",
    "wordpress_org_submission_summary": "${CONTENT_DIR}/wordpress-org-submission-summary.md",
    "wordpress_org_support_announcement": "${CONTENT_DIR}/wordpress-org-support-announcement.md"
  }
}
EOF
}

publish_github_release_if_requested() {
	if [[ ${PUBLISH_GITHUB_RELEASE} -eq 0 ]]; then
		return
	fi

	require_command gh
	gh auth status >/dev/null 2>&1 || fail 'GitHub CLI is not authenticated. Run gh auth login first.'

	log "Publishing GitHub release ${TAG_NAME} to ${REPO_SLUG}"

	if gh release view "${TAG_NAME}" --repo "${REPO_SLUG}" >/dev/null 2>&1; then
		gh release edit "${TAG_NAME}" \
			--repo "${REPO_SLUG}" \
			--title "Christopher Ross Shadow ${VERSION}" \
			--notes-file "${CONTENT_DIR}/github-release-notes.md"
		gh release upload "${TAG_NAME}" \
			"${ZIP_PATH}" \
			"${SHA_PATH}" \
			--repo "${REPO_SLUG}" \
			--clobber
	else
		gh release create "${TAG_NAME}" \
			"${ZIP_PATH}" \
			"${SHA_PATH}" \
			--repo "${REPO_SLUG}" \
			--target "${TARGET_REF}" \
			--title "Christopher Ross Shadow ${VERSION}" \
			--notes-file "${CONTENT_DIR}/github-release-notes.md"
	fi
}

parse_args() {
	while [[ $# -gt 0 ]]; do
		case "$1" in
			--version)
				[[ $# -ge 2 ]] || fail '--version requires a value.'
				VERSION="$2"
				shift 2
				;;
			--repo)
				[[ $# -ge 2 ]] || fail '--repo requires a value.'
				REPO_SLUG="$2"
				shift 2
				;;
			--tag)
				[[ $# -ge 2 ]] || fail '--tag requires a value.'
				TAG_NAME="$2"
				shift 2
				;;
			--target)
				[[ $# -ge 2 ]] || fail '--target requires a value.'
				TARGET_REF="$2"
				shift 2
				;;
			--output-dir)
				[[ $# -ge 2 ]] || fail '--output-dir requires a value.'
				OUTPUT_DIR="$2"
				shift 2
				;;
			--publish-github-release)
				PUBLISH_GITHUB_RELEASE=1
				shift
				;;
			--run-release-proof)
				RUN_RELEASE_PROOF=1
				shift
				;;
			--release-proof-container)
				[[ $# -ge 2 ]] || fail '--release-proof-container requires a value.'
				RELEASE_PROOF_CONTAINER="$2"
				shift 2
				;;
			--help|-h)
				usage
				exit 0
				;;
			*)
				fail "Unknown argument: $1"
				;;
		esac
	done
}

main() {
	require_command php
	require_command rsync
	require_command zip
	require_command sha256sum

	parse_args "$@"
	ensure_version_alignment

	[[ -n "${REPO_SLUG}" ]] || REPO_SLUG="$(extract_repo_slug)"
	[[ -n "${REPO_SLUG}" ]] || fail 'Could not determine a GitHub repository slug. Use --repo owner/repo.'
	[[ -n "${TAG_NAME}" ]] || TAG_NAME="v${VERSION}"

	prepare_directories
	run_release_proof_if_requested
	build_zip
	generate_content_files
	publish_github_release_if_requested

	log "Release bundle created in ${OUTPUT_DIR}"
	log "Zip artifact: ${ZIP_PATH}"
	log "Checksum: ${SHA_PATH}"
	log "Generated content directory: ${CONTENT_DIR}"
	if [[ ${PUBLISH_GITHUB_RELEASE} -eq 1 ]]; then
		log "GitHub release ${TAG_NAME} has been published or updated on ${REPO_SLUG}"
	else
		log 'GitHub release publish step was skipped. Re-run with --publish-github-release to push the assets.'
	fi
}

main "$@"