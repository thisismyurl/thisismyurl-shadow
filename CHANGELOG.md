# Changelog

All notable changes to This Is My URL Shadow are documented here.

This project aims to follow the spirit of **Keep a Changelog**, while keeping entries readable for both technical and non-technical users.

---

## [1.6143] — 2026-05-23

### Changed
- Promoted to a full release (class 1). The `0.6xxx` line was pre-release on the `x.Yddd` scheme.
- Standardized the donation link to GitHub Sponsors (`https://github.com/sponsors/thisismyurl`).

## [0.6125] - 2026-05-03

### Fixed
- Guarded the GitHub-update bootstrapper with `file_exists()` so installs from WordPress.org do not fatal when the self-hosted updater file is intentionally excluded from the distribution zip

### Changed
- Added `Plugin URI` and `Author URI` to the plugin header
- Aligned the plugin header description with the WordPress.org short description
- Updated `Tested up to` to the current stable WordPress release
- Brand cleanup across CHANGELOG, SECURITY, PRIVACY, README, and the release-collateral script
- Tag list updated for stronger discovery intent on WordPress.org search

---

## [0.6124] - 2026-05-03

### Changed
- Cleanup pass for the WPShadow → This Is My URL Shadow rename: CSS classes, DOM IDs, dashboard JS globals, asset filenames, admin notice classes, GitHub workflow paths, and the GitHub release-updater repo slug now all use the `thisismyurl-shadow` brand
- Supporting documentation (README, CONTRIBUTING, CODE_OF_CONDUCT, SUPPORT, PRIVACY, SECURITY, DONATE, docs/) updated to the new brand
- Legacy on-disk backup directory and filename prefixes preserved with `TODO(rename-v2)` markers so existing user backups remain restorable across upgrade

---

## [0.6123] - 2026-05-03

### Changed
- Renamed plugin from "WPShadow" to "This Is My URL Shadow" for the WordPress.org submission. Slug is now `thisismyurl-shadow`
- Renamed namespace `WPShadow\*` to `ThisIsMyURL\Shadow\*`, constants `THISISMYURL_SHADOW_*`, text domain `thisismyurl-shadow`, and AJAX / cron / option / transient identifiers to the `thisismyurl_shadow_*` prefix
- Replaced `WP_CONTENT_DIR . '/uploads'` (and similar) with `wp_upload_dir()`, `WP_PLUGIN_DIR`, `WPMU_PLUGIN_DIR`, and `get_theme_root()`

### Removed
- Removed `error_reporting()` / `ini_set()` overrides from global init; PHP error reporting is now left to WordPress and the host
- Removed global `define( 'DONOTCACHEPAGE', true )`. Cache-suppression now lives in a helper that only runs inside specific stateful render callbacks

### Added
- One-shot, idempotent migration that copies legacy options, transients, and cron schedules to their renamed keys on first admin load after upgrade
- Tightened `.distignore` so the WordPress.org zip excludes `.git/`, `.gitattributes`, `.github/`, `tests/`, `docs/`, `vendor/`, `composer.*`, and the GitHub release updater
- New public-facing documentation for contributing, support, security, privacy, sponsorship, and next-step planning
- A formal documentation index at `docs/INDEX.md`
- GitHub issue and pull request templates

---

## [0.6095] - 2026-04-05

### Changed
- Aligned release metadata across plugin headers, stable tags, and distributable documentation
- Normalized future-dated `@since` annotations to the current release version
- Improved dashboard detail routing and linked the WordPress gauge to native Site Health
- Hardened bootstrap and admin menu loading to reduce recent startup regressions
- Kept release packaging and validation safeguards in place so shipped metadata stays synchronized

---

## [0.6035] - 2026-02-04

### Added
- Accessibility and inclusivity improvements aligned with the CANON pillars
- Documentation cleanup and reorganization into a more curated structure
- Expanded diagnostic coverage and production-release readiness work

### Changed
- Continued release hardening and community-readiness improvements

---

## [0.6030] - 2026-01-30

### Added
- Initial development release
- Early diagnostic and dashboard foundation

---

## Notes

For WordPress.org-specific release notes, see:
- [`readme.txt`](readme.txt)

For current project direction, see:
- [`docs/MILESTONES.md`](docs/MILESTONES.md)
- [`docs/NEXT_STEPS.md`](docs/NEXT_STEPS.md)
