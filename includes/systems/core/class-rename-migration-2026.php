<?php
/**
 * One-shot legacy data migration from the wpshadow / thisismyurl_ identifiers
 * the plugin shipped under prior to the WordPress.org rename to
 * "Christopher Ross Shadow" (slug: thisismyurl-shadow).
 *
 * What this class migrates, all gated by a single one-shot flag option so the
 * migration only ever runs once per site:
 *
 *   1. Options whose key began with `wpshadow_` or with the brand-prefix-only
 *      `thisismyurl_` (i.e. not `thisismyurl_shadow_`). Each old option's
 *      value is copied to its new `thisismyurl_shadow_*` key only if the new
 *      key does not already exist, then the old key is deleted. autoload is
 *      preserved (defaults to "no" for migrated values to avoid bloat).
 *
 *   2. Transients with the same legacy prefixes. Best-effort: transients can
 *      expire between writes and reads. Both timeout rows and value rows are
 *      cleaned up. We rely on $wpdb directly because there is no public WP
 *      API to enumerate transient keys by prefix.
 *
 *   3. Cron schedules registered under the legacy hook names. Existing
 *      schedules are unscheduled and re-scheduled under the renamed
 *      `thisismyurl_shadow_*` hook with the same recurrence and next-run.
 *
 * What this class intentionally does NOT migrate:
 *
 *   - post_meta keys on the Training Event CPT (`_thisismyurl_event_*`,
 *     `_thisismyurl_training_*`, `_thisismyurl_legacy_event_id`,
 *     `_thisismyurl_migrated_event_id`). These are stable, brand-prefixed,
 *     and not flagged by the .org review. Migrating them would require
 *     sweeping potentially many rows for no compliance benefit.
 *
 *   - The marker comments embedded in user-edited wp-config.php files
 *     (`// thisismyurl_MARKER_START: <slug>`). The undo regex still matches
 *     the legacy markers so prior file-write treatments can be reverted.
 *
 *   - User backup directory names (`.wpshadow-vault-lite/`, `wpshadow-backups/`)
 *     and existing on-disk backup zip filenames (`wpshadow-backup-*.zip`).
 *     Renaming these would orphan all existing backups; the new names are
 *     used only for new artifacts created after upgrade.
 *
 * The class is intentionally self-contained, has no external dependencies on
 * other plugin classes (so it can run during a plugin activation that has not
 * yet booted the autoloader), and is safe to delete in a future major version
 * once the migration window closes.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Core
 * @since 0.6123
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Idempotent renamer for the WordPress.org compliance rebrand.
 */
class Rename_Migration_2026 {

	/**
	 * Option name that records whether the migration has already executed.
	 */
	private const FLAG_OPTION = 'thisismyurl_shadow_migrated_from_wpshadow_v1';

	/**
	 * Run the migration. Safe to call repeatedly: short-circuits after first run.
	 *
	 * @return void
	 */
	public static function run(): void {
		if ( '1' === (string) get_option( self::FLAG_OPTION, '' ) ) {
			return;
		}

		self::migrate_options();
		self::migrate_transients();
		self::migrate_cron_hooks();

		update_option( self::FLAG_OPTION, '1', false );
	}

	/**
	 * Discover and rename options carrying a legacy prefix.
	 *
	 * Two prefixes are migrated:
	 *  - `wpshadow_` (original name)
	 *  - `thisismyurl_` where the next segment is NOT `shadow_` (the half-way
	 *     state the codebase shipped between rebrands)
	 *
	 * @return void
	 */
	private static function migrate_options(): void {
		global $wpdb;

		// Collect candidate option names from wp_options. Direct query is the
		// only practical way to enumerate option names by prefix; there is no
		// public WP API that returns keys without their values.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			"SELECT option_name FROM {$wpdb->options} "
			. "WHERE option_name LIKE 'wpshadow\\_%' "
			. "OR ( option_name LIKE 'thisismyurl\\_%' AND option_name NOT LIKE 'thisismyurl\\_shadow\\_%' AND option_name NOT LIKE 'thisismyurl\\_2026\\_%' )"
		);

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$old_name = isset( $row->option_name ) ? (string) $row->option_name : '';
			if ( '' === $old_name ) {
				continue;
			}

			$new_name = self::rename_key( $old_name );
			if ( $new_name === $old_name ) {
				continue;
			}

			// Read the legacy value with raw get_option to avoid filters that
			// might reference the new name and short-circuit the read.
			$value = get_option( $old_name, null );
			if ( null === $value ) {
				delete_option( $old_name );
				continue;
			}

			// Never overwrite a new-prefix option that already has a value.
			if ( null === get_option( $new_name, null ) || false === get_option( $new_name, false ) ) {
				$existing = get_option( $new_name, '__thisismyurl_shadow_migration_absent__' );
				if ( '__thisismyurl_shadow_migration_absent__' === $existing ) {
					add_option( $new_name, $value, '', 'no' );
				}
			}

			delete_option( $old_name );
		}
	}

	/**
	 * Discover and rename transients carrying a legacy prefix. Both `_transient_`
	 * value rows and `_transient_timeout_` rows are handled.
	 *
	 * Best-effort: a transient that has expired between this scan and the row
	 * read will simply not be migrated, which matches expected transient
	 * semantics (callers must handle absence already).
	 *
	 * @return void
	 */
	private static function migrate_transients(): void {
		global $wpdb;

		$prefixes = array( '_transient_wpshadow_', '_transient_thisismyurl_' );

		foreach ( $prefixes as $prefix ) {
			$like_value   = $wpdb->esc_like( $prefix ) . '%';
			$like_timeout = $wpdb->esc_like( '_transient_timeout_' . substr( $prefix, strlen( '_transient_' ) ) ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					$like_value,
					$like_timeout
				)
			);

			if ( empty( $rows ) || ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				$option_name = isset( $row->option_name ) ? (string) $row->option_name : '';
				if ( '' === $option_name ) {
					continue;
				}

				// Resolve the bare transient key (strip the WP storage prefix).
				if ( 0 === strpos( $option_name, '_transient_timeout_' ) ) {
					$bare = substr( $option_name, strlen( '_transient_timeout_' ) );
				} elseif ( 0 === strpos( $option_name, '_transient_' ) ) {
					$bare = substr( $option_name, strlen( '_transient_' ) );
				} else {
					continue;
				}

				$new_bare = self::rename_key( $bare );
				if ( $new_bare === $bare ) {
					// Already-migrated key; safe to delete the legacy row.
					delete_option( $option_name );
					continue;
				}

				// Skip thisismyurl_shadow_* and thisismyurl_2026_* — those are not legacy.
				if ( 0 === strpos( $bare, 'thisismyurl_shadow_' ) || 0 === strpos( $bare, 'thisismyurl_2026_' ) ) {
					continue;
				}

				// Migrate value-row only; the timeout will be re-set by set_transient().
				if ( 0 === strpos( $option_name, '_transient_' ) && 0 !== strpos( $option_name, '_transient_timeout_' ) ) {
					$value = get_transient( $bare );
					if ( false !== $value && false === get_transient( $new_bare ) ) {
						$timeout_row    = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", '_transient_timeout_' . $bare ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
						$remaining_secs = is_numeric( $timeout_row ) ? max( 0, (int) $timeout_row - time() ) : 0;
						set_transient( $new_bare, $value, $remaining_secs );
					}
				}

				delete_option( $option_name );
			}
		}
	}

	/**
	 * Re-register cron events scheduled under legacy hook names.
	 *
	 * @return void
	 */
	private static function migrate_cron_hooks(): void {
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			return;
		}

		foreach ( $crons as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}

			foreach ( $hooks as $hook_name => $events ) {
				$hook_name = (string) $hook_name;

				if ( 0 !== strpos( $hook_name, 'wpshadow_' )
					&& ! ( 0 === strpos( $hook_name, 'thisismyurl_' )
						&& 0 !== strpos( $hook_name, 'thisismyurl_shadow_' )
						&& 0 !== strpos( $hook_name, 'thisismyurl_2026_' ) )
				) {
					continue;
				}

				$new_hook = self::rename_key( $hook_name );
				if ( $new_hook === $hook_name || ! is_array( $events ) ) {
					continue;
				}

				foreach ( $events as $event ) {
					$args     = isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array();
					$schedule = isset( $event['schedule'] ) ? (string) $event['schedule'] : '';

					if ( '' !== $schedule ) {
						wp_schedule_event( (int) $timestamp, $schedule, $new_hook, $args );
					} else {
						wp_schedule_single_event( (int) $timestamp, $new_hook, $args );
					}
				}

				wp_clear_scheduled_hook( $hook_name );
			}
		}
	}

	/**
	 * Rename a single legacy identifier, returning the input unchanged if it
	 * does not match a legacy prefix.
	 *
	 * @param string $key Legacy option / transient / hook name.
	 * @return string Renamed key (or the original on no-op).
	 */
	private static function rename_key( string $key ): string {
		if ( 0 === strpos( $key, 'wpshadow_' ) ) {
			return 'thisismyurl_shadow_' . substr( $key, strlen( 'wpshadow_' ) );
		}

		if ( 0 === strpos( $key, 'thisismyurl_' )
			&& 0 !== strpos( $key, 'thisismyurl_shadow_' )
			&& 0 !== strpos( $key, 'thisismyurl_2026_' )
		) {
			return 'thisismyurl_shadow_' . substr( $key, strlen( 'thisismyurl_' ) );
		}

		return $key;
	}
}
