<?php
/**
 * File Write Trust Manager
 *
 * Remembers the admin's trust preferences for file-write warnings.
 *
 * Admins can choose to skip the SFTP-acknowledgment step for a specific file
 * or for all file-write treatments globally. Those preferences are stored in
 * the WordPress options table and this class manages CRUD on them.
 *
 * Option schema:
 *   thisismyurl_shadow_file_write_trust (array)
 *     - 'all'              => bool   (true = skip warnings for all files)
 *     - 'files'            => string[] (absolute paths that are individually trusted)
 *
 * Philosophy: Commandment #5 (Stay Out of the Way) — respect the admin's choice
 * to streamline once they have read and stored recovery instructions.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Admin
 * @since 0.6095
 */

namespace ThisIsMyURL\Shadow\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persist the admin's decision to skip future file-write warnings.
 *
 * Shadow by Christopher Ross shows extra caution before editing files because WordPress sites
 * often live on shared hosting or custom deployment setups. This class stores
 * the admin's explicit trust decisions so repeated safe fixes can become less
 * disruptive once the person operating the plugin understands the workflow.
 */
class File_Write_Trust {

	/**
	 * WordPress option key used to store trust preferences.
	 *
	 * @since 0.6095
	 * @var   string
	 */
	const OPTION_KEY = 'thisismyurl_shadow_file_write_trust';

	/**
	 * Sentinel array key meaning "trust all files globally".
	 *
	 * @since 0.6095
	 * @var   string
	 */
	const TRUST_ALL = 'all';

	// -------------------------------------------------------------------------
	// Read helpers
	// -------------------------------------------------------------------------

	/**
	 * Check whether an SFTP-acknowledgment warning should be shown for a file.
	 *
	 * Returns false (= no warning needed) when the admin has previously chosen
	 * to trust either this specific file or all files globally.
	 *
	 * @param string $file_path Absolute path to the file.
	 * @return bool True if the warning should be shown; false if trusted.
	 */
	public static function needs_warning( string $file_path ): bool {
		if ( self::is_all_trusted() ) {
			return false;
		}

		return ! self::is_file_trusted( $file_path );
	}

	/**
	 * Check whether global trust is enabled (skip warnings for all files).
	 *
	 * @return bool
	 */
	public static function is_all_trusted(): bool {
		$prefs = self::get_prefs();
		return ! empty( $prefs[ self::TRUST_ALL ] );
	}

	/**
	 * Check whether a specific file has been individually trusted.
	 *
	 * @param string $file_path Absolute path.
	 * @return bool
	 */
	public static function is_file_trusted( string $file_path ): bool {
		$prefs = self::get_prefs();
		$trusted_files = $prefs['files'] ?? [];
		return in_array( $file_path, (array) $trusted_files, true );
	}

	// -------------------------------------------------------------------------
	// Write helpers
	// -------------------------------------------------------------------------

	/**
	 * Trust a specific file (skip warning for this file only in future).
	 *
	 * @param string $file_path Absolute path.
	 * @return void
	 */
	public static function trust_file( string $file_path ): void {
		$prefs = self::get_prefs();
		$trusted_files = (array) ( $prefs['files'] ?? [] );

		if ( ! in_array( $file_path, $trusted_files, true ) ) {
			$trusted_files[] = $file_path;
		}

		$prefs['files'] = $trusted_files;
		self::save_prefs( $prefs );
	}

	/**
	 * Enable global trust — skip warnings for all files in future.
	 *
	 * @return void
	 */
	public static function trust_all(): void {
		$prefs = self::get_prefs();
		$prefs[ self::TRUST_ALL ] = true;
		self::save_prefs( $prefs );
	}

	/**
	 * Completely reset all trust preferences.
	 *
	 * @return void
	 */
	public static function reset(): void {
		delete_option( self::OPTION_KEY );
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Load current preferences from the option.
	 *
	 * @return array
	 */
	private static function get_prefs(): array {
		$raw = get_option( self::OPTION_KEY, [] );
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}
		return $raw;
	}

	/**
	 * Persist preferences to the option.
	 *
	 * @param array $prefs
	 * @return void
	 */
	private static function save_prefs( array $prefs ): void {
		update_option( self::OPTION_KEY, $prefs, false );
	}
}
