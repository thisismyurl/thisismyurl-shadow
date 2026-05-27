=== This Is My URL - Shadow ===
Contributors: thisismyurl
Donate link: https://github.com/sponsors/thisismyurl
Tags: diagnostics, site-health, security, performance, site-audit
Requires at least: 6.4
Requires PHP: 8.1
Tested up to: 7.0
Stable tag: 1.6147
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local-first WordPress diagnostics and safer fixes, with file review and one-click recovery before risky changes.

== Description ==

Most WordPress site owners do not know what is broken until something fails in production. This Is My URL Shadow surfaces the problems early — health, security, performance, and accessibility — and gives you a calm path to fix them without sending your site data to a cloud service.

This first public release is a beta focused on the core plugin experience:

* 230 display-ready diagnostics across 11 categories
* 101 executable treatment classes in the remediation layer
* 93 automated treatment entries and 8 guidance-only treatment entries
* dashboard views for findings, trends, and status
* file-write review for risky changes
* local backup and recovery workflows
* WordPress Site Health integration
* accessibility-first, plain-English guidance

This Is My URL Shadow runs locally and does not require registration or a cloud account.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/thisismyurl-shadow/` directory, or install the plugin through WordPress.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open the This Is My URL Shadow dashboard from the WordPress admin menu.
4. Review findings and apply safe fixes where appropriate.

== Frequently Asked Questions ==

= Is this a beta release? =

Yes. This is the first public beta release of This Is My URL Shadow. The beta is intended for real-world use and feedback while the team continues to polish workflows, copy, and recovery paths.

= Does This Is My URL Shadow require an account or cloud service? =

No. This Is My URL Shadow runs locally. The current public beta does not require registration, a paid plan, or a cloud connection.

= What kinds of issues does it check? =

This Is My URL Shadow includes diagnostics across accessibility, code quality, database health, design, monitoring, performance, security, SEO, settings, WordPress health, and workflows.

= Does it make changes automatically? =

Some fixes can be applied through the treatment system. Lower-risk changes can be automated with apply and undo support. Higher-risk changes are designed to be reviewed more carefully, and some actions are guidance-only by design.

= Does it support multisite? =

This Is My URL Shadow includes multisite-aware admin behavior and capability handling. As with any beta, multisite administrators should test changes carefully before wide rollout.

= Is accessibility taken seriously? =

Yes. This Is My URL Shadow is built around clearer language, keyboard-friendly workflows, screen-reader-aware structure, and lower-stress recovery paths. Accessibility issues should be treated as product bugs, not polish.

= Does it send my data to third parties? =

Not by default. The plugin is local-first. Optional future services, if introduced, must remain opt-in and clearly explained.

== Screenshots ==

1. This Is My URL Shadow dashboard overview
2. Diagnostics inventory and findings views
3. Treatment and file review workflows
4. Backup and recovery interface

== Changelog ==

= 1.6147 =
* Unified plugin versioning to the x.Yddd calendar-version scheme.
* Confirmed compatibility with WordPress 7.0.


= 1.6143 =
* First full release (class 1). The 0.6xxx line was pre-release on the `x.Yddd` scheme.
* Standardized the donation link to GitHub Sponsors.

= 0.6125 =
* Guarded the GitHub-update bootstrapper with `file_exists()` so installs from WordPress.org do not fatal when the self-hosted updater file is intentionally excluded from the distribution zip.
* Added `Plugin URI` and `Author URI` to the plugin header.
* Aligned the plugin header description with the WordPress.org short description.
* Updated `Tested up to` to the current stable WordPress release.
* Brand cleanup across CHANGELOG, SECURITY, PRIVACY, README, and the release-collateral script.
* Tag list updated for stronger discovery intent on WordPress.org search.

= 0.6124 =
* Cleanup pass for the WPShadow → This Is My URL Shadow rename: CSS classes, DOM IDs, dashboard JS globals, asset filenames, admin notice classes, GitHub workflow paths, repo slug for the GitHub release updater, and supporting documentation now all use the `thisismyurl-shadow` brand. Legacy on-disk backup directory and filename prefixes are preserved with `TODO(rename-v2)` markers so existing user backups remain restorable across upgrade.

= 0.6123 =
* Renamed plugin from "WPShadow" to "This Is My URL Shadow" for the WordPress.org submission. Slug is now `thisismyurl-shadow`.
* Removed `error_reporting()` / `ini_set()` overrides from global init; PHP error reporting is now left to WordPress and the host.
* Removed global `define( 'DONOTCACHEPAGE', true )`. Cache-suppression now lives in a helper that only runs inside specific stateful render callbacks.
* Replaced `WP_CONTENT_DIR . '/uploads'` (and similar) with `wp_upload_dir()`, `WP_PLUGIN_DIR`, `WPMU_PLUGIN_DIR`, and `get_theme_root()`.
* Renamed namespace `WPShadow\*` to `ThisIsMyURL\Shadow\*`, constants `THISISMYURL_SHADOW_*`, text domain `thisismyurl-shadow`, and AJAX / cron / option / transient identifiers to the `thisismyurl_shadow_*` prefix.
* Added a one-shot, idempotent migration that copies legacy options, transients, and cron schedules to their renamed keys on first admin load after upgrade.
* Tightened `.distignore` so the WordPress.org zip excludes `.git/`, `.gitattributes`, `.github/`, `tests/`, `docs/`, `vendor/`, `composer.*`, and the GitHub release updater.

= 0.6095 =
* First public beta release.
* Aligned public documentation with the current plugin scope and philosophy.
* Refined diagnostics, treatment, file-review, and recovery messaging for public release.
* Continued hardening of core safety boundaries and admin workflows.

= 0.6035 =
* Expanded core diagnostics and release-readiness work.

= 0.6030 =
* Initial development release.

== Support ==

Open an issue at https://github.com/thisismyurl/thisismyurl-shadow/issues for bug reports and reproducible accessibility problems. See `SUPPORT.md` in the repository for the full support policy.

== License ==

This plugin is licensed under GPL v2 or later.
