=== Shadow by Christopher Ross ===
Contributors: thisismyurl
Donate link: https://github.com/sponsors/thisismyurl
Tags: diagnostics, site-health, security, performance, site-audit
Requires at least: 6.4
Requires PHP: 8.1
Tested up to: 7.0
Stable tag: 1.6147
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Local-first WordPress diagnostics and safer fixes, with file review before risky changes.

== Description ==

Most WordPress site owners do not know what is broken until something fails in production. Shadow by Christopher Ross surfaces the problems early — health, security, performance, and accessibility — and gives you a calm path to fix them.

What makes Shadow different:

* **Local-first.** Everything runs on your own server. No cloud account, no registration; your site data never leaves your site.
* **Review before it writes.** Risky changes are shown to you first — you see the exact change before it is made.
* **Reversible.** Treatments apply with undo support, so a fix you do not like can be rolled back.
* **Plain-English, accessibility-first.** Findings are explained in language you can act on.

This first public release includes:

* 230 display-ready diagnostics across 11 categories
* 101 treatment classes in the remediation layer (93 automated, 8 guidance-only)
* dashboard views for findings, trends, and status
* file-write review for risky changes
* WordPress Site Health integration

Shadow by Christopher Ross runs locally and does not require registration or a cloud account.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/thisismyurl-shadow/` directory, or install the plugin through WordPress.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open the Shadow by Christopher Ross dashboard from the WordPress admin menu.
4. Review findings and apply safe fixes where appropriate.

== Frequently Asked Questions ==

= Is it safe to use on a production site? =

Yes. Diagnostics only read your site; they change nothing. Any change a treatment makes is shown to you first and can be undone. This is the first public release and is actively developed — the review-and-undo model is designed to keep you in control on a live site.

= Does Shadow by Christopher Ross require an account or cloud service? =

No. Shadow by Christopher Ross runs locally and does not require registration, a paid plan, or a cloud connection.

= What kinds of issues does it check? =

Shadow by Christopher Ross includes diagnostics across accessibility, code quality, database health, design, monitoring, performance, security, SEO, settings, WordPress health, and workflows.

= Does it make changes automatically? =

Some fixes can be applied through the treatment system. Lower-risk changes can be automated with apply and undo support. Higher-risk changes are designed to be reviewed more carefully, and some actions are guidance-only by design.

= Does it support multisite? =

Shadow by Christopher Ross includes multisite-aware admin behavior and capability handling. Multisite administrators should test changes on a staging site before a wide rollout.

= Is accessibility taken seriously? =

Yes. Shadow by Christopher Ross is built around clearer language, keyboard-friendly workflows, screen-reader-aware structure, and lower-stress recovery paths. Accessibility issues should be treated as product bugs, not polish.

= Does it send my data to third parties? =

Not by default. The plugin is local-first. Optional future services, if introduced, must remain opt-in and clearly explained.

== External services ==

Shadow by Christopher Ross is local-first and does not send your site data to any third party. Two behaviours involve outbound HTTP requests, and both are disclosed here for transparency.

1. Self-directed diagnostics (loopback requests to your own site). Several performance and security diagnostics request your own site's URLs — derived from `home_url()` and the REST API URL — to inspect how your server actually responds. These checks cover things such as caching and compression headers, HTTP/2 support, mixed-content references, and directory-listing exposure. The requests go only to your own domain; no data is sent to any external service.

2. WordPress.org secret-key (salt) API, only when you run the matching treatment. The "Set authentication keys and salts" treatment makes a one-time request to the official WordPress.org salt API (https://api.wordpress.org/secret-key/1.1/salt/) to generate a fresh set of secret keys for your `wp-config.php`. This request runs only when you explicitly apply that treatment. No site data is transmitted in the request. WordPress.org terms: https://wordpress.org/about/privacy/ — privacy policy: https://wordpress.org/about/privacy/

== Screenshots ==

1. Shadow by Christopher Ross dashboard overview
2. Diagnostics inventory and findings views
3. Treatment and file review workflows

== Changelog ==

= 1.6147 =
* Removed the local backup and restore feature; backup and restore are not part of this plugin and are handled by a separate, dedicated plugin.
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
* Cleanup pass for the WPShadow → Shadow by Christopher Ross rename: CSS classes, DOM IDs, dashboard JS globals, asset filenames, admin notice classes, GitHub workflow paths, repo slug for the GitHub release updater, and supporting documentation now all use the `thisismyurl-shadow` brand. Legacy on-disk backup directory and filename prefixes are preserved with `TODO(rename-v2)` markers so existing user backups remain restorable across upgrade.

= 0.6123 =
* Renamed plugin from "WPShadow" to "Shadow by Christopher Ross" for the WordPress.org submission. Slug is now `thisismyurl-shadow`.
* Removed `error_reporting()` / `ini_set()` overrides from global init; PHP error reporting is now left to WordPress and the host.
* Removed global `define( 'DONOTCACHEPAGE', true )`. Cache-suppression now lives in a helper that only runs inside specific stateful render callbacks.
* Replaced `WP_CONTENT_DIR . '/uploads'` (and similar) with `wp_upload_dir()`, `WP_PLUGIN_DIR`, `WPMU_PLUGIN_DIR`, and `get_theme_root()`.
* Renamed namespace `WPShadow\*` to `ThisIsMyURL\Shadow\*`, constants `THISISMYURL_SHADOW_*`, text domain `thisismyurl-shadow`, and AJAX / cron / option / transient identifiers to the `thisismyurl_shadow_*` prefix.
* Added a one-shot, idempotent migration that copies legacy options, transients, and cron schedules to their renamed keys on first admin load after upgrade.
* Tightened `.distignore` so the WordPress.org zip excludes `.git/`, `.gitattributes`, `.github/`, `tests/`, `docs/`, `vendor/`, `composer.*`, and the GitHub release updater.

= 0.6095 =
* First public release of Shadow by Christopher Ross.
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
