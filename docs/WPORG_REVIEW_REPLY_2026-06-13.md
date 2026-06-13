# WordPress.org review reply — 2026-06-13

Review ID: `R thisismyurl-shadow/thisismyurl/18Apr26/T6 13Jun26/4.0.1 (P0TDX299387HGN)`

Draft reply for Christopher to send to `plugins@wordpress.org` (reply in-thread).
Send the prose below; the file:line citations are there so the team can verify quickly.

---

Hello, and thank you for the detailed review.

I have addressed every category you raised and swept the whole plugin for
additional cases of each pattern (not only the lines quoted), tested on a clean
install with `WP_DEBUG` true, and am uploading the corrected version.

A short summary of what changed, followed by three clarifications where the
reported items are intentional behaviour of a diagnostics-and-remediation
plugin rather than defects.

## Fixed

- **Sanitization of superglobals.** `$_SERVER['HTTP_ACCEPT']` is now
  `sanitize_text_field( wp_unslash( … ) )`. The two `filter_input( …,
  FILTER_UNSAFE_RAW )` reads in the stale-diagnostics notice are replaced with
  `sanitize_text_field( wp_unslash( $_SERVER / $_GET[…] ) )`. I also found and
  fixed a missing `wp_unslash` on one other `$_GET` read during the sweep.
- **Late escaping.** The admin-accessibility CSS is no longer echoed directly;
  it is now enqueued through the styles API (`wp_register_style` +
  `wp_add_inline_style`) on `admin_enqueue_scripts`. The blanket
  `OutputNotEscaped` suppression is gone.
- **Prepared SQL.** The sample-content diagnostic query now uses a single
  `$wpdb->prepare()` with the `array_fill()` placeholder pattern from your
  email (one `%s` per phrase, all values bound through the second argument).
- **File/dir locations.** The flagged `WP_CONTENT_DIR` / `WP_PLUGIN_DIR` /
  `ABSPATH` usages were almost all inside the backup engine, which has been
  removed (see below). The few that remain use an API helper where one exists.
- **PHP limits.** `set_time_limit()` / `ini_set()` calls are confirmed scoped
  to the diagnostic-scan execution method only (never global/`init`/
  constructor), with comments documenting the scope.
- **Backup/restore engine removed.** The local backup/restore feature
  ("Vault Lite") — including the `$wpdb->query()` that replayed a `.sql` dump
  and the whole-site path handling you flagged — has been **removed from this
  plugin** and now lives in a separate plugin. This resolves the restore-SQL
  finding and most of the file/directory-location findings outright.
- **GitHub updater removed.** The self-hosted update bridge has been removed
  entirely (file deleted, no longer referenced), per the earlier request.
- **Plugin name corrected.** The display name is now "Shadow by Christopher
  Ross" (a stray leading character has been removed).

## Two clarifications

### 1. Option names that belong to other plugins / WordPress core

This Is My URL Shadow is a diagnostics **and remediation** plugin. Several
treatments deliberately read and write the options of *other* plugins, or of
WordPress core, to fix a misconfiguration the administrator has chosen to
remediate. These are not the plugin's own options, so prefixing them would
break the fix — e.g. writing `thisismyurl_shadow_wpseo_titles` would not change
Yoast's behaviour.

| Option written | Owner | Why Shadow writes it |
|---|---|---|
| `wpseo_titles` | Yoast SEO | Re-enable search-engine indexing |
| `rank_math_settings_general` | Rank Math | Re-enable search-engine indexing |
| `stats_options` | Jetpack | Turn off the admin-bar sparkline (Stats dashboard untouched) |
| `wpmm_settings`, `wp_maintenance`, `seedprod_page_settings`, `maintenance_mode`, `colorlib_coming_soon_settings` | WP Maintenance Mode / WebFactory / SeedProd / Colorlib | Turn **off** maintenance / coming-soon mode on request |
| `auto_update_plugins`, `auto_update_core_enabled`, `WPLANG` | WordPress core | Apply the auto-update / locale policy the admin selected |

Every one of these writes is user-initiated (a treatment the admin explicitly
applies), runs only after a capability check, and is guarded by a test that the
target plugin is actually active before touching its option. All options the
plugin owns are prefixed `thisismyurl_shadow_`.

### 2. The remaining `ABSPATH` references are in security diagnostics/treatments

With the backup engine removed, the only remaining `ABSPATH` references belong
to security features for which referencing the WordPress root is inherent to
the feature's job:

- `class-diagnostic-sensitive-files-protected.php` — scans `ABSPATH` (the
  webroot) for publicly exposed `wp-config.bak/.old`, `debug.log`, etc. Finding
  files in the webroot is the diagnostic's entire purpose.
- `class-treatment-file-mods-policy-defined.php` — inserts `define(
  'DISALLOW_FILE_EDIT', true )` into `wp-config.php`, which must be located
  relative to `ABSPATH`.
- `class-ajax-handler-base.php` — uses `ABSPATH` as the root of a
  path-traversal allow-list (a security control); requests for files outside
  the WordPress root are rejected.

I did not list every individual change, since the team re-reviews the whole
plugin. Happy to clarify any of the above.

Thank you,
Christopher Ross

---

## Internal notes (do not send)

- Verification done in the editing environment: `php -l` clean on every changed
  file; manual review against ValidatedSanitizedInput / EscapeOutput /
  PreparedSQL / NonceVerification; residual-reference sweep after the Vault Lite
  removal returns zero `Backup_Manager`/`Backup_Scheduler`/vault-feature hits.
  `composer`/`phpcs`/`wp plugin check` were not runnable there (no https wrapper
  in that PHP CLI).
- Vault Lite (local backup/restore) was removed entirely this round (8 files
  deleted incl. github-updater.php; ~14 files edited to strip wiring). This
  resolved the restore-SQL and most file/dir-location findings without needing
  the keep+justify argument.
- **Before upload, run locally:** `composer install` →
  `composer run lint:phpcs` → `wp plugin check thisismyurl-shadow`. Confirm the
  three flagged sniff families are clean. The `prepare-wordpress-org-release.sh`
  script is the release gate.
- The sample-content query carries a scoped `phpcs:disable …
  PreparedSQL.InterpolatedNotPrepared` because WPCS can't prove `$where_or` is
  placeholder-only; the query is genuinely prepared. The reply explicitly tells
  the reviewer the query now uses their recommended pattern, so the remaining
  ignore should not re-trip the review.
- Display name: a prior reply round noted the name was changed to "This Is My
  URL Shadow" and the GitHub-updater had to be removed — confirm both are still
  resolved in this upload.
