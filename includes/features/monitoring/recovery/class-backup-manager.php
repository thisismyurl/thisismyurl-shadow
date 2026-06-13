<?php
/**
 * Local Backup Manager.
 *
 * Provides the "Vault Lite" local-only backup engine for Christopher Ross Shadow.
 * It creates protected ZIP archives on the same server before treatments
 * run and when scheduled/manual backups are triggered.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Guardian
 * @since   0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Guardian;

use ThisIsMyURL\Shadow\Core\Activity_Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

/**
 * Implement Christopher Ross Shadow's local-only backup engine.
 *
 * This class exists to make treatments and scheduled maintenance safer. It is
 * responsible for creating protected archives, tracking those archives in an
 * option-backed index, enforcing restore policy, and cleaning up old backups.
 *
 * For readers learning the plugin, this class is the main reference for how
 * Christopher Ross Shadow balances convenience with containment: backups live on the same
 * server, are stored in hidden randomized paths, and are treated as operational
 * safety tooling rather than a full remote backup product.
 */
class Backup_Manager {

	/**
	 * Option controlling whether SQL dumps may be applied during restore.
	 *
	 * @var string
	 */
	private const OPTION_ALLOW_DATABASE_RESTORE = 'thisismyurl_shadow_backup_restore_database_allowed';

	/**
	 * Filter allowing code-level override of the SQL restore policy.
	 *
	 * @var string
	 */
	private const FILTER_ALLOW_DATABASE_RESTORE = 'thisismyurl_shadow_allow_backup_database_restore';

	/**
	 * Maximum number of archive entries allowed during restore validation.
	 *
	 * @var int
	 */
	private const RESTORE_MAX_ARCHIVE_ENTRIES = 200000;

	/**
	 * Maximum total uncompressed bytes allowed during restore validation.
	 *
	 * @var int
	 */
	private const RESTORE_MAX_UNCOMPRESSED_BYTES = 21474836480;

	/**
	 * Option storing indexed local backup metadata.
	 *
	 * @var string
	 */
	private const OPTION_INDEX = 'thisismyurl_shadow_local_backup_index';

	/**
	 * Option storing the most recent backup result.
	 *
	 * @var string
	 */
	private const OPTION_LAST_RESULT = 'thisismyurl_shadow_last_backup_result';

	/**
	 * Legacy backup directory name used before secret storage was introduced.
	 *
	 * @var string
	 */
	// TODO(rename-v2): preserved as `wpshadow-backups` so existing on-disk backup
	// directories from the WPShadow era stay discoverable. Renaming this would
	// orphan user backups; a future major version should ship a backup-file
	// migrator and only then rename to `thisismyurl-shadow-backups`.
	private const LEGACY_BACKUP_DIR_NAME = 'wpshadow-backups';

	/**
	 * Private root directory for Vault Lite backups under uploads.
	 *
	 * @var string
	 */
	private const PRIVATE_ROOT_DIR_NAME = '.thisismyurl-shadow-vault-lite';

	/**
	 * Option that stores the randomized secret backup directory token.
	 *
	 * @var string
	 */
	private const OPTION_DIRECTORY_TOKEN = 'thisismyurl_shadow_backup_dir_token';

	/**
	 * Maximum number of indexed backup records to keep in the option.
	 *
	 * @var int
	 */
	private const INDEX_LIMIT = 50;

	/**
	 * Bootstrap the local backup manager.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function init(): void {
		add_action( 'thisismyurl_shadow_before_treatment_apply', array( __CLASS__, 'maybe_backup_before_treatment' ), 10, 3 );
	}

	/**
	 * Create a local backup before non-dry-run treatment execution.
	 *
	 * Skips creating a new archive when a recent backup already exists within
	 * the configured deduplication window (thisismyurl_shadow_treatment_backup_window, default 60 min).
	 * This prevents N treatments in one session from producing N identical 100+ MB archives.
	 *
	 * Uploads are excluded from treatment backups by default because treatments
	 * only touch WP options, wp-config.php constants, and .htaccess rules — never
	 * uploaded media. The thisismyurl_shadow_treatment_backup_exclude_uploads option allows
	 * disabling this override when a full snapshot is preferred.
	 *
	 * @since  0.6095
	 * @param  string $class      Treatment class name.
	 * @param  string $finding_id Finding identifier.
	 * @param  bool   $dry_run    Whether the treatment is a dry run.
	 * @return void
	 */
	public static function maybe_backup_before_treatment( string $class, string $finding_id, bool $dry_run = false ): void {
		if ( $dry_run || ! (bool) get_option( 'thisismyurl_shadow_backup_enabled', true ) ) {
			return;
		}

		// ── Deduplication ────────────────────────────────────────────────────
		// If a backup was already created within the configured window, reuse
		// it instead of generating another identical archive.
		$window_minutes = max( 1, (int) get_option( 'thisismyurl_shadow_treatment_backup_window', 60 ) );
		$cutoff         = current_time( 'timestamp' ) - ( $window_minutes * MINUTE_IN_SECONDS );
		$latest         = self::get_latest_backup();

		if ( is_array( $latest ) && isset( $latest['created_at'] ) && (int) $latest['created_at'] >= $cutoff ) {
			if ( class_exists( Activity_Logger::class ) ) {
				Activity_Logger::log(
					'local_backup_reused',
					sprintf(
						/* translators: 1: treatment class, 2: backup filename */
						__( 'Recent backup reused before %1$s: %2$s', 'thisismyurl-shadow' ),
						$class,
						(string) ( $latest['file'] ?? '' )
					),
					'backups',
					array(
						'finding_id'   => $finding_id,
						'class'        => $class,
						'reused_file'  => $latest['file'] ?? '',
					)
				);
			}

			return;
		}

		// ── Scope: exclude uploads unless overridden ──────────────────────────
		// Treatments never modify /uploads; including it only adds unnecessary
		// size (often 50-100 MB+) to every pre-treatment snapshot.
		$exclude_uploads = (bool) get_option( 'thisismyurl_shadow_treatment_backup_exclude_uploads', true );

		$result = self::create_backup(
			array(
				'trigger'          => 'treatment',
				'context'          => $finding_id,
				'label'            => $class,
				'exclude_uploads'  => $exclude_uploads,
			)
		);

		if ( empty( $result['success'] ) && class_exists( Activity_Logger::class ) ) {
			Activity_Logger::log(
				'local_backup_failed',
				sprintf(
					/* translators: 1: treatment class, 2: error message */
					__( 'Local backup failed before %1$s: %2$s', 'thisismyurl-shadow' ),
					$class,
					(string) ( $result['message'] ?? __( 'Unknown error', 'thisismyurl-shadow' ) )
				),
				'backups',
				array(
					'finding_id' => $finding_id,
					'class'      => $class,
				)
			);
		}
	}

	/**
	 * Create a protected local backup archive.
	 *
	 * @since  0.6095
	 * @param  array<string,mixed> $args Optional backup context arguments.
	 * @return array<string,mixed> Backup result payload.
	 */
	public static function create_backup( array $args = array() ): array {
		$trigger = isset( $args['trigger'] ) ? sanitize_key( (string) $args['trigger'] ) : 'manual';

		if ( 'treatment' === $trigger && ! (bool) get_option( 'thisismyurl_shadow_backup_enabled', true ) ) {
			return self::build_failure_result( __( 'Local backups are disabled for treatments.', 'thisismyurl-shadow' ), $trigger );
		}

		if ( 'scheduled' === $trigger && ! (bool) get_option( 'thisismyurl_shadow_backup_schedule_enabled', false ) ) {
			return self::build_failure_result( __( 'Scheduled local backups are currently disabled.', 'thisismyurl-shadow' ), $trigger );
		}

		if ( ! class_exists( '\\ZipArchive' ) ) {
			return self::build_failure_result( __( 'ZIP support is not available on this server, so local backups cannot be created yet.', 'thisismyurl-shadow' ), $trigger );
		}

		self::ensure_backup_directory();
		$backup_dir = self::get_backup_directory();

		if ( ! is_dir( $backup_dir ) || ! wp_is_writable( $backup_dir ) ) {
			return self::build_failure_result( __( 'The local backup directory is not writable.', 'thisismyurl-shadow' ), $trigger );
		}

		$timestamp = current_time( 'timestamp' );
		// TODO(rename-v2): backup filename prefix kept as `wpshadow-backup-` so
		// in-flight backups remain restorable post-upgrade. A future major
		// version should ship a one-shot rename for archived `.zip` files and
		// only then switch to a `thisismyurl-shadow-backup-` prefix.
		$filename  = wp_unique_filename(
			$backup_dir,
			'wpshadow-backup-' . gmdate( 'Ymd-His', (int) $timestamp ) . '-' . $trigger . '.zip'
		);
		$path      = trailingslashit( $backup_dir ) . $filename;

		$zip         = new \ZipArchive();
		$open_result = $zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		if ( true !== $open_result ) {
			return self::build_failure_result( __( 'The backup archive could not be created.', 'thisismyurl-shadow' ), $trigger );
		}

		$manifest = self::build_manifest( $args, $timestamp );
		$zip->addFromString(
			'manifest.json',
			(string) wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )
		);

		if ( (bool) get_option( 'thisismyurl_shadow_backup_include_database', true ) ) {
			$database_dump = self::export_database_sql();
			if ( '' !== $database_dump ) {
				$zip->addFromString( 'database.sql', $database_dump );
			}
		}

		foreach ( self::get_backup_sources( $args ) as $source ) {
			self::add_path_to_zip( $zip, $source['path'], $source['archive_root'] );
		}

		$zip->close();
		clearstatcache( true, $path );

		if ( ! file_exists( $path ) ) {
			return self::build_failure_result( __( 'The backup archive did not finish writing to disk.', 'thisismyurl-shadow' ), $trigger );
		}

		$size     = (int) filesize( $path );
		$hash     = (string) hash_file( 'sha256', $path );
		$verified = ! (bool) get_option( 'thisismyurl_shadow_backup_verify', true ) || self::verify_backup( $path );

		$entry = array(
			'file'             => $filename,
			'path'             => $path,
			'trigger'          => $trigger,
			'context'          => isset( $args['context'] ) ? sanitize_text_field( (string) $args['context'] ) : '',
			'label'            => isset( $args['label'] ) ? sanitize_text_field( (string) $args['label'] ) : '',
			'created_at'       => $timestamp,
			'size'             => $size,
			'sha256'           => $hash,
			'verified'         => $verified,
			'include_database' => (bool) get_option( 'thisismyurl_shadow_backup_include_database', true ),
			'include_uploads'  => (bool) get_option( 'thisismyurl_shadow_backup_include_uploads', true ),
		);

		$index = self::get_backup_index();
		array_unshift( $index, $entry );
		if ( count( $index ) > self::INDEX_LIMIT ) {
			$index = array_slice( $index, 0, self::INDEX_LIMIT );
		}
		update_option( self::OPTION_INDEX, $index, false );

		$result = array(
			'success'  => $verified,
			'message'  => $verified
				? __( 'Local backup created successfully.', 'thisismyurl-shadow' )
				: __( 'Local backup was created, but verification did not complete cleanly.', 'thisismyurl-shadow' ),
			'file'     => $filename,
			'path'     => $path,
			'size'     => $size,
			'formatted_size' => size_format( $size ),
			'verified' => $verified,
			'trigger'  => $trigger,
		);

		update_option( self::OPTION_LAST_RESULT, $result, false );
		self::prune_backups();

		if ( class_exists( Activity_Logger::class ) ) {
			Activity_Logger::log(
				'local_backup_created',
				sprintf(
					/* translators: 1: backup filename, 2: backup size */
					__( 'Local backup created: %1$s (%2$s)', 'thisismyurl-shadow' ),
					$filename,
					size_format( $size )
				),
				'backups',
				array(
					'file'     => $filename,
					'trigger'  => $trigger,
					'verified' => $verified,
				)
			);
		}

		return $result;
	}

	/**
	 * Get a summary of local backup status for the settings UI.
	 *
	 * @since  0.6095
	 * @return array<string,mixed> Backup status summary.
	 */
	public static function get_status_summary(): array {
		$index      = self::get_backup_index();
		$total_size = 0;
		foreach ( $index as $entry ) {
			$total_size += isset( $entry['size'] ) ? (int) $entry['size'] : 0;
		}

		$last = ! empty( $index ) ? $index[0] : null;

		return array(
			'directory'              => self::get_backup_directory(),
			'directory_public_label' => self::get_public_location_label(),
			'count'                  => count( $index ),
			'total_size'             => $total_size,
			'total_size_human'       => size_format( $total_size ),
			'last_backup'            => $last,
			'last_backup_label'      => isset( $last['created_at'] ) ? self::format_timestamp( (int) $last['created_at'] ) : __( 'No local backups yet', 'thisismyurl-shadow' ),
			'last_backup_file'       => isset( $last['file'] ) ? (string) $last['file'] : '',
			'last_backup_status'     => isset( $last['verified'] ) && false === $last['verified'] ? 'warning' : 'ok',
		);
	}

	/**
	 * Get the local backup directory path.
	 *
	 * @since  0.6095
	 * @return string Absolute local backup directory path.
	 */
	public static function get_backup_directory(): string {
		return trailingslashit( self::get_backup_root_directory() ) . self::get_directory_token();
	}

	/**
	 * Get a security-safe public label for the backup location.
	 *
	 * @since  0.6095
	 * @return string Human-friendly location label.
	 */
	public static function get_public_location_label(): string {
		return __( 'Private Vault Lite storage (hidden randomized path)', 'thisismyurl-shadow' );
	}

	/**
	 * Resolve the uploads basedir, falling back only when wp_get_upload_dir()
	 * is unavailable (e.g. during very early lifecycle code).
	 *
	 * Centralized here so call sites do not repeat the wp_get_upload_dir()
	 * unwrapping pattern, which previously left WP_CONTENT_DIR concatenations
	 * scattered through this file.
	 *
	 * @since 0.6096
	 * @return string Absolute uploads basedir path.
	 */
	private static function get_uploads_basedir(): string {
		if ( function_exists( 'wp_get_upload_dir' ) ) {
			$uploads = wp_get_upload_dir();
			if ( ! empty( $uploads['basedir'] ) ) {
				return (string) $uploads['basedir'];
			}
		}

		return WP_CONTENT_DIR . '/uploads';
	}

	/**
	 * Get the private backup root directory under uploads.
	 *
	 * @since  0.6095
	 * @return string Absolute root directory path.
	 */
	private static function get_backup_root_directory(): string {
		return trailingslashit( self::get_uploads_basedir() ) . self::PRIVATE_ROOT_DIR_NAME;
	}

	/**
	 * Get the legacy predictable backup directory path.
	 *
	 * @since  0.6095
	 * @return string Absolute legacy directory path.
	 */
	private static function get_legacy_backup_directory(): string {
		return trailingslashit( self::get_uploads_basedir() ) . self::LEGACY_BACKUP_DIR_NAME;
	}

	/**
	 * Get or create the randomized token used for the secret backup directory.
	 *
	 * @since  0.6095
	 * @return string Sanitized directory token.
	 */
	private static function get_directory_token(): string {
		$token = self::normalize_directory_token( get_option( self::OPTION_DIRECTORY_TOKEN, '' ) );

		if ( '' === $token ) {
			$token = self::generate_directory_token();

			if ( false === get_option( self::OPTION_DIRECTORY_TOKEN, false ) ) {
				add_option( self::OPTION_DIRECTORY_TOKEN, $token, '', false );
			} else {
				update_option( self::OPTION_DIRECTORY_TOKEN, $token, false );
			}
		}

		return $token;
	}

	/**
	 * Generate a new randomized directory token.
	 *
	 * @since  0.6095
	 * @return string Randomized token.
	 */
	private static function generate_directory_token(): string {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $e ) {
			$generated = wp_generate_password( 32, false, false );
			$token     = self::normalize_directory_token( $generated );

			return '' !== $token ? $token : md5( $generated . wp_rand() );
		}
	}

	/**
	 * Normalize a candidate directory token.
	 *
	 * @since  0.6095
	 * @param  mixed $token Raw token value.
	 * @return string Sanitized token or empty string when invalid.
	 */
	private static function normalize_directory_token( $token ): string {
		if ( ! is_string( $token ) ) {
			return '';
		}

		$token = strtolower( preg_replace( '/[^a-z0-9]/', '', $token ) ?? '' );

		return strlen( $token ) >= 24 ? $token : '';
	}

	/**
	 * Ensure the local backup directory exists and is protected from direct browsing.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function ensure_backup_directory(): void {
		$root_dir = self::get_backup_root_directory();
		if ( ! is_dir( $root_dir ) ) {
			wp_mkdir_p( $root_dir );
		}
		if ( is_dir( $root_dir ) ) {
			self::write_protection_files( $root_dir );
		}

		$dir = self::get_backup_directory();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		if ( ! is_dir( $dir ) ) {
			return;
		}

		self::write_protection_files( $dir );
		self::migrate_legacy_backups( $dir );
	}

	/**
	 * Write server-side protection files into a backup directory.
	 *
	 * @since  0.6095
	 * @param  string $dir Absolute directory path.
	 * @return void
	 */
	private static function write_protection_files( string $dir ): void {
		$index_file = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Options -Indexes\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$web_config = trailingslashit( $dir ) . 'web.config';
		if ( ! file_exists( $web_config ) ) {
			file_put_contents( $web_config, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <authorization>\n      <remove users=\"*\" roles=\"\" verbs=\"\" />\n      <add accessType=\"Deny\" users=\"*\" />\n    </authorization>\n  </system.webServer>\n</configuration>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Move backups out of the old predictable directory into the secret one.
	 *
	 * @since  0.6095
	 * @param  string $target_dir Secret target directory.
	 * @return void
	 */
	private static function migrate_legacy_backups( string $target_dir ): void {
		$legacy_dir = self::get_legacy_backup_directory();

		if ( ! is_dir( $legacy_dir ) || wp_normalize_path( $legacy_dir ) === wp_normalize_path( $target_dir ) ) {
			return;
		}

		$items = glob( trailingslashit( $legacy_dir ) . '*' );
		if ( ! is_array( $items ) || empty( $items ) ) {
			self::write_protection_files( $legacy_dir );
			return;
		}

		$moved_paths = array();
		foreach ( $items as $item_path ) {
			$item_name = basename( (string) $item_path );
			if ( in_array( $item_name, array( 'index.php', 'web.config' ), true ) ) {
				continue;
			}

			$destination = trailingslashit( $target_dir ) . wp_unique_filename( $target_dir, $item_name );
			if ( self::move_file_safely( (string) $item_path, $destination ) ) {
				$moved_paths[ wp_normalize_path( (string) $item_path ) ] = $destination;
			}
		}

		if ( ! empty( $moved_paths ) ) {
			$index = self::get_backup_index();
			foreach ( $index as &$entry ) {
				$entry_path = isset( $entry['path'] ) ? wp_normalize_path( (string) $entry['path'] ) : '';
				if ( isset( $moved_paths[ $entry_path ] ) ) {
					$entry['path'] = $moved_paths[ $entry_path ];
					$entry['file'] = basename( $moved_paths[ $entry_path ] );
				}
			}
			unset( $entry );
			update_option( self::OPTION_INDEX, $index, false );
		}

		self::write_protection_files( $legacy_dir );
	}

	/**
	 * Move a file into the secret backup directory, falling back to copy/delete.
	 *
	 * @since  0.6095
	 * @param  string $source      Source path.
	 * @param  string $destination Destination path.
	 * @return bool True when the move succeeded.
	 */
	private static function move_file_safely( string $source, string $destination ): bool {
		if ( @copy( $source, $destination ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			wp_delete_file( $source );
			return file_exists( $destination );
		}

		return false;
	}

	/**
	 * Verify that a local backup archive is readable and contains expected files.
	 *
	 * @since  0.6095
	 * @param  string $path Absolute path to the archive.
	 * @return bool True when the archive passes basic verification.
	 */
	public static function verify_backup( string $path ): bool {
		if ( ! self::is_managed_backup_path( $path ) || ! file_exists( $path ) || 0 >= (int) filesize( $path ) ) {
			return false;
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path, \ZipArchive::CHECKCONS ) ) {
			return false;
		}

		$validation = self::validate_restore_archive( $zip );
		$valid      = ! empty( $validation['success'] );
		$zip->close();

		return $valid;
	}

	/**
	 * Remove expired backups and enforce the total size cap.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function prune_backups(): void {
		$index          = self::get_backup_index();
		$retention_days = max( 1, (int) get_option( 'thisismyurl_shadow_backup_retention_days', 7 ) );
		$max_size_mb    = max( 50, (int) get_option( 'thisismyurl_shadow_backup_max_size_mb', 500 ) );
		$cutoff         = current_time( 'timestamp' ) - ( $retention_days * DAY_IN_SECONDS );
		$max_bytes      = $max_size_mb * 1024 * 1024;

		$kept = array();
		foreach ( $index as $entry ) {
			$path       = isset( $entry['path'] ) ? (string) $entry['path'] : '';
			$created_at = isset( $entry['created_at'] ) ? (int) $entry['created_at'] : 0;

			if ( '' === $path || ! file_exists( $path ) ) {
				continue;
			}

			if ( $created_at > 0 && $created_at < $cutoff ) {
				wp_delete_file( $path );
				continue;
			}

			$entry['size'] = (int) filesize( $path );
			$kept[]        = $entry;
		}

		$total = 0;
		foreach ( $kept as $entry ) {
			$total += isset( $entry['size'] ) ? (int) $entry['size'] : 0;
		}

		if ( $total > $max_bytes ) {
			usort(
				$kept,
				static function ( array $left, array $right ): int {
					return (int) ( $left['created_at'] ?? 0 ) <=> (int) ( $right['created_at'] ?? 0 );
				}
			);

			while ( $total > $max_bytes && ! empty( $kept ) ) {
				$oldest = array_shift( $kept );
				$path   = isset( $oldest['path'] ) ? (string) $oldest['path'] : '';
				$size   = isset( $oldest['size'] ) ? (int) $oldest['size'] : 0;

				if ( '' !== $path && file_exists( $path ) ) {
					wp_delete_file( $path );
				}

				$total -= $size;
			}
		}

		usort(
			$kept,
			static function ( array $left, array $right ): int {
				return (int) ( $right['created_at'] ?? 0 ) <=> (int) ( $left['created_at'] ?? 0 );
			}
		);

		update_option( self::OPTION_INDEX, array_values( $kept ), false );
	}

	/**
	 * Get all indexed local backups.
	 *
	 * @since  0.6095
	 * @return array<int,array<string,mixed>> Indexed backup entries.
	 */
	public static function get_backups(): array {
		return self::get_backup_index();
	}

	/**
	 * Get the most recently created indexed backup entry.
	 *
	 * @since  0.6095
	 * @return array<string,mixed>|null Most recent backup entry or null.
	 */
	public static function get_latest_backup(): ?array {
		$index = self::get_backup_index();
		return ! empty( $index ) ? $index[0] : null;
	}

	/**
	 * Get a single indexed backup entry by filename.
	 *
	 * @since  0.6095
	 * @param  string $filename Backup filename.
	 * @return array<string,mixed>|null Matching backup entry or null.
	 */
	public static function get_backup_entry( string $filename ): ?array {
		$target = sanitize_file_name( $filename );

		if ( '' === $target ) {
			return null;
		}

		foreach ( self::get_backup_index() as $entry ) {
			if ( $target === (string) ( $entry['file'] ?? '' ) ) {
				return self::is_valid_backup_entry( $entry ) ? $entry : null;
			}
		}

		return null;
	}

	/**
	 * Delete a single indexed backup and remove it from disk.
	 *
	 * @since  0.6095
	 * @param  string $filename Backup filename.
	 * @return array<string,mixed> Result payload.
	 */
	public static function delete_backup( string $filename ): array {
		$target = sanitize_file_name( $filename );

		if ( '' === $target ) {
			return array(
				'success' => false,
				'message' => __( 'The selected backup could not be found.', 'thisismyurl-shadow' ),
			);
		}

		$index     = self::get_backup_index();
		$deleted   = null;
		$remaining = array();

		foreach ( $index as $entry ) {
			if ( $target === (string) ( $entry['file'] ?? '' ) && null === $deleted ) {
				$deleted = $entry;
				continue;
			}

			$remaining[] = $entry;
		}

		if ( ! is_array( $deleted ) ) {
			return array(
				'success' => false,
				'message' => __( 'The selected backup could not be found.', 'thisismyurl-shadow' ),
			);
		}

		$path             = isset( $deleted['path'] ) ? (string) $deleted['path'] : '';
		$file_was_removed = true;

		if ( '' !== $path && file_exists( $path ) ) {
			wp_delete_file( $path );
			$file_was_removed = ! file_exists( $path );
		}

		if ( ! $file_was_removed ) {
			return array(
				'success' => false,
				'message' => __( 'The backup file could not be deleted from disk.', 'thisismyurl-shadow' ),
				'file'    => $target,
			);
		}

		update_option( self::OPTION_INDEX, array_values( $remaining ), false );

		if ( class_exists( Activity_Logger::class ) ) {
			Activity_Logger::log(
				'local_backup_deleted',
				sprintf(
					/* translators: %s: deleted backup filename */
					__( 'Local backup deleted: %s', 'thisismyurl-shadow' ),
					$target
				),
				'backups',
				array(
					'file' => $target,
				)
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Backup deleted successfully.', 'thisismyurl-shadow' ),
			'file'    => $target,
		);
	}

	/**
	 * Build a human-readable description for a backup entry.
	 *
	 * @since  0.6095
	 * @param  array<string,mixed> $entry Backup index entry.
	 * @return string Human-readable description.
	 */
	public static function describe_backup( array $entry ): string {
		$trigger_label = self::get_trigger_label( (string) ( $entry['trigger'] ?? 'manual' ) );
		$created_at    = isset( $entry['created_at'] ) ? self::format_timestamp( (int) $entry['created_at'] ) : __( 'unknown time', 'thisismyurl-shadow' );
		$size_label    = ! empty( $entry['size'] ) ? size_format( (int) $entry['size'] ) : __( 'size unknown', 'thisismyurl-shadow' );
		$verification  = ! empty( $entry['verified'] ) ? __( 'verified', 'thisismyurl-shadow' ) : __( 'not verified', 'thisismyurl-shadow' );

		return sprintf(
			/* translators: 1: trigger label, 2: formatted date/time, 3: formatted size, 4: verification state */
			__( '%1$s created %2$s • %3$s • %4$s', 'thisismyurl-shadow' ),
			$trigger_label,
			$created_at,
			$size_label,
			$verification
		);
	}

	/**
	 * Restore a local backup archive.
	 *
	 * @since  0.6095
	 * @param  string $filename Backup filename to restore.
	 * @return array<string,mixed> Restore result payload.
	 */
	public static function restore_backup( string $filename ): array {
		$entry = self::get_backup_entry( $filename );
		if ( ! is_array( $entry ) || empty( $entry['path'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'The selected backup could not be found.', 'thisismyurl-shadow' ),
			);
		}

		$path = (string) $entry['path'];
		if ( ! self::is_managed_backup_path( $path ) || ! file_exists( $path ) ) {
			return array(
				'success' => false,
				'message' => __( 'The selected backup file is no longer available on disk.', 'thisismyurl-shadow' ),
			);
		}

		if ( ! class_exists( '\\ZipArchive' ) ) {
			return array(
				'success' => false,
				'message' => __( 'ZIP support is not available on this server, so the backup cannot be restored automatically.', 'thisismyurl-shadow' ),
			);
		}

		@set_time_limit( 0 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		@ignore_user_abort( true ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged

		$safety_backup = self::create_backup(
			array(
				'trigger' => 'pre-restore',
				'context' => (string) ( $entry['file'] ?? $filename ),
			)
		);

		if ( empty( $safety_backup['success'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Restore stopped because Christopher Ross Shadow could not create the required safety backup first.', 'thisismyurl-shadow' ),
				'file'    => (string) ( $entry['file'] ?? $filename ),
			);
		}

		$temp_dir = trailingslashit( self::get_backup_directory() ) . 'restore-temp-' . wp_generate_password( 12, false, false );
		wp_mkdir_p( $temp_dir );

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path, \ZipArchive::CHECKCONS ) ) {
			self::remove_directory_tree( $temp_dir );
			return array(
				'success' => false,
				'message' => __( 'The backup archive could not be opened for restore.', 'thisismyurl-shadow' ),
			);
		}

		$archive_validation = self::validate_restore_archive( $zip );
		if ( empty( $archive_validation['success'] ) ) {
			$zip->close();
			self::remove_directory_tree( $temp_dir );
			return array(
				'success' => false,
				'message' => isset( $archive_validation['message'] ) ? (string) $archive_validation['message'] : __( 'The backup archive failed validation and cannot be restored automatically.', 'thisismyurl-shadow' ),
			);
		}

		$extracted = $zip->extractTo( $temp_dir );
		$zip->close();

		if ( ! $extracted ) {
			self::remove_directory_tree( $temp_dir );
			return array(
				'success' => false,
				'message' => __( 'The backup archive could not be extracted for restore.', 'thisismyurl-shadow' ),
			);
		}

		$uploads_basedir = self::get_uploads_basedir();
		$themes_root     = function_exists( 'get_theme_root' ) ? get_theme_root() : WP_CONTENT_DIR . '/themes';
		$mu_plugins_dir  = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
		$plugins_dir     = WP_PLUGIN_DIR;

		$directory_targets = array(
			$temp_dir . '/site-files/wp-content/plugins'    => $plugins_dir,
			$temp_dir . '/site-files/wp-content/themes'     => $themes_root,
			$temp_dir . '/site-files/wp-content/mu-plugins' => $mu_plugins_dir,
			$temp_dir . '/site-files/wp-content/uploads'    => $uploads_basedir,
		);

		foreach ( $directory_targets as $source => $destination ) {
			if ( is_dir( $source ) ) {
				self::copy_directory_tree( $source, $destination );
			}
		}

		$config_dir = $temp_dir . '/site-files/config';
		if ( is_dir( $config_dir ) ) {
			$config_files = glob( trailingslashit( $config_dir ) . '*' );
			if ( is_array( $config_files ) ) {
				foreach ( $config_files as $config_file ) {
					$target = self::get_config_restore_target( basename( (string) $config_file ) );
					if ( '' !== $target && is_file( $config_file ) && ! is_link( $config_file ) ) {
						wp_mkdir_p( dirname( $target ) );
						copy( $config_file, $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
					}
				}
			}
		}

		$database_restored       = false;
		$database_restore_skipped = false;
		$database_dump           = $temp_dir . '/database.sql';
		if ( is_file( $database_dump ) ) {
			if ( self::is_database_restore_allowed( (string) ( $entry['file'] ?? $filename ) ) ) {
				$database_restored = self::import_database_dump( $database_dump );
			} else {
				$database_restore_skipped = true;
			}
		}

		self::remove_directory_tree( $temp_dir );

		if ( class_exists( Activity_Logger::class ) ) {
			Activity_Logger::log(
				'local_backup_restored',
				sprintf(
					/* translators: %s: restored backup filename */
					__( 'Local backup restored: %s', 'thisismyurl-shadow' ),
					(string) ( $entry['file'] ?? $filename )
				),
				'backups',
				array(
					'file'              => (string) ( $entry['file'] ?? $filename ),
					'database_restored' => $database_restored,
					'database_restore_skipped' => $database_restore_skipped,
				)
			);
		}

		$message = __( 'Backup files were restored successfully. Christopher Ross Shadow created a fresh safety backup first, but the database dump was not applied automatically.', 'thisismyurl-shadow' );
		if ( $database_restored ) {
			$message = __( 'Backup restored successfully. Christopher Ross Shadow created a fresh safety backup first.', 'thisismyurl-shadow' );
		} elseif ( $database_restore_skipped ) {
			$message = self::get_database_restore_denied_message();
		}

		return array(
			'success'            => true,
			'message'            => $message,
			'file'               => (string) ( $entry['file'] ?? $filename ),
			'safety_backup_file' => isset( $safety_backup['file'] ) ? sanitize_file_name( (string) $safety_backup['file'] ) : '',
			'database_restored'  => $database_restored,
			'database_restore_skipped' => $database_restore_skipped,
		);
	}

	/**
	 * Load the indexed backup list.
	 *
	 * @since  0.6095
	 * @return array<int,array<string,mixed>> Indexed backup entries.
	 */
	private static function get_backup_index(): array {
		$index = get_option( self::OPTION_INDEX, array() );
		return is_array( $index ) ? $index : array();
	}

	/**
	 * Build the backup manifest payload.
	 *
	 * @since  0.6095
	 * @param  array<string,mixed> $args      Backup arguments.
	 * @param  int                 $timestamp Creation timestamp.
	 * @return array<string,mixed> Manifest payload.
	 */
	private static function build_manifest( array $args, int $timestamp ): array {
		return array(
			'plugin'            => 'thisismyurl-shadow',
			'version'           => defined( 'THISISMYURL_SHADOW_VERSION' ) ? THISISMYURL_SHADOW_VERSION : 'unknown',
			'created_at'        => $timestamp,
			'created_at_human'  => self::format_timestamp( $timestamp ),
			'trigger'           => isset( $args['trigger'] ) ? sanitize_key( (string) $args['trigger'] ) : 'manual',
			'context'           => isset( $args['context'] ) ? sanitize_text_field( (string) $args['context'] ) : '',
			'include_database'       => (bool) get_option( 'thisismyurl_shadow_backup_include_database', true ),
			'include_uploads'        => (bool) get_option( 'thisismyurl_shadow_backup_include_uploads', true ),
			'uploads_excluded_by_caller' => ! empty( $args['exclude_uploads'] ),
			'compressed'        => true,
			'site_url'          => home_url( '/' ),
			'wp_version'        => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
		);
	}

	/**
	 * Get the file and directory sources that should be archived.
	 *
	 * @since  0.6095
	 * @param  array<string,mixed> $args Backup arguments forwarded from create_backup().
	 * @return array<int,array{path:string,archive_root:string}>
	 */
	private static function get_backup_sources( array $args = array() ): array {
		$sources    = array();
		$wp_content = WP_CONTENT_DIR;

		$directory_map = array(
			$wp_content . '/plugins'    => 'site-files/wp-content/plugins',
			$wp_content . '/themes'     => 'site-files/wp-content/themes',
			$wp_content . '/mu-plugins' => 'site-files/wp-content/mu-plugins',
		);

		// Uploads are excluded when the caller passes 'exclude_uploads' => true
		// (e.g., treatment backups). Otherwise the global include_uploads setting applies.
		$caller_excludes_uploads = ! empty( $args['exclude_uploads'] );
		$global_include_uploads  = (bool) get_option( 'thisismyurl_shadow_backup_include_uploads', true );

		if ( ! $caller_excludes_uploads && $global_include_uploads ) {
			$directory_map[ $wp_content . '/uploads' ] = 'site-files/wp-content/uploads';
		}

		foreach ( $directory_map as $path => $archive_root ) {
			if ( is_dir( $path ) ) {
				$sources[] = array(
					'path'         => $path,
					'archive_root' => $archive_root,
				);
			}
		}

		$config_candidates = array(
			ABSPATH . 'wp-config.php',
			dirname( ABSPATH ) . '/wp-config.php',
			ABSPATH . '.htaccess',
		);

		foreach ( $config_candidates as $file ) {
			if ( is_file( $file ) ) {
				$sources[] = array(
					'path'         => $file,
					'archive_root' => 'site-files/config/' . basename( $file ),
				);
			}
		}

		return $sources;
	}

	/**
	 * Add a file or directory tree to the archive.
	 *
	 * @since  0.6095
	 * @param  \ZipArchive $zip          Active ZIP archive.
	 * @param  string      $source_path  Absolute filesystem path.
	 * @param  string      $archive_root Archive path prefix.
	 * @return void
	 */
	private static function add_path_to_zip( \ZipArchive $zip, string $source_path, string $archive_root ): void {
		$normalized_source = wp_normalize_path( $source_path );
		$excluded_paths    = self::get_excluded_paths();

		if ( self::is_excluded_path( $normalized_source, $excluded_paths ) || ! file_exists( $source_path ) ) {
			return;
		}

		if ( is_file( $source_path ) ) {
			$zip->addFile( $source_path, $archive_root );
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source_path, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isLink() ) {
				continue;
			}

			$item_path = wp_normalize_path( $item->getPathname() );
			if ( self::is_excluded_path( $item_path, $excluded_paths ) ) {
				continue;
			}

			$relative     = ltrim( str_replace( $normalized_source, '', $item_path ), '/' );
			$archive_path = trim( $archive_root . '/' . $relative, '/' );

			if ( $item->isDir() ) {
				$zip->addEmptyDir( $archive_path );
			} elseif ( $item->isFile() ) {
				$zip->addFile( $item->getPathname(), $archive_path );
			}
		}
	}

	/**
	 * Export the current WordPress database to SQL text.
	 *
	 * @since  0.6095
	 * @return string SQL dump content.
	 */
	private static function export_database_sql(): string {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return '';
		}

		/*
		 * This backup export stays on $wpdb intentionally.
		 *
		 * WordPress exposes content APIs for posts, terms, users, and options, but it does not
		 * provide a core function that can enumerate every table, fetch each table's CREATE
		 * statement, and emit a full SQL dump that preserves custom plugin tables alongside core
		 * data. Rebuilding a site backup through object APIs would silently miss non-core tables
		 * and would not preserve indexes, column types, or table definitions.
		 *
		 * Because this method is explicitly a database export routine rather than normal runtime
		 * content access, SHOW TABLES, SHOW CREATE TABLE, and raw row reads through $wpdb are the
		 * correct primitives.
		 */

		$tables = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $tables ) || empty( $tables ) ) {
			return '';
		}

		$sql  = "-- Christopher Ross Shadow local backup database export\n";
		$sql .= '-- Generated: ' . gmdate( 'c' ) . "\n\n";

		foreach ( $tables as $table ) {
			$table_name = (string) $table;
			if ( '' === $table_name ) {
				continue;
			}

			$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table_name}`", ARRAY_N ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
			if ( is_array( $create ) && isset( $create[1] ) ) {
				$sql .= 'DROP TABLE IF EXISTS `' . str_replace( '`', '``', $table_name ) . "`;\n";
				$sql .= (string) $create[1] . ";\n\n";
			}

			$rows = $wpdb->get_results( "SELECT * FROM `{$table_name}`", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
			if ( ! is_array( $rows ) || empty( $rows ) ) {
				$sql .= "\n";
				continue;
			}

			foreach ( $rows as $row ) {
				$columns = array_map(
					static function ( string $column ): string {
						return '`' . str_replace( '`', '``', $column ) . '`';
					},
					array_keys( $row )
				);

				$values = array_map( array( __CLASS__, 'sql_export_value' ), array_values( $row ) );
				$sql   .= 'INSERT INTO `' . str_replace( '`', '``', $table_name ) . '` (' . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $values ) . ");\n";
			}

			$sql .= "\n";
		}

		return $sql;
	}

	/**
	 * Convert a PHP value to a SQL-safe export literal.
	 *
	 * @since  0.6095
	 * @param  mixed $value Raw value.
	 * @return string SQL literal.
	 */
	private static function sql_export_value( $value ): string {
		if ( null === $value ) {
			return 'NULL';
		}

		$escaped = str_replace(
			array( '\\', "\0", "\n", "\r", "\x1a", "'" ),
			array( '\\\\', '\\0', '\\n', '\\r', '\\Z', "\\'" ),
			(string) $value
		);

		return "'{$escaped}'";
	}

	/**
	 * Copy a restored directory tree into its live destination.
	 *
	 * @since  0.6095
	 * @param  string $source      Extracted source directory.
	 * @param  string $destination Live destination directory.
	 * @return void
	 */
	private static function copy_directory_tree( string $source, string $destination ): void {
		if ( ! is_dir( $source ) ) {
			return;
		}

		wp_mkdir_p( $destination );

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$target_path = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

			if ( $item->isLink() ) {
				continue;
			}

			if ( $item->isDir() ) {
				wp_mkdir_p( $target_path );
			} elseif ( $item->isFile() ) {
				wp_mkdir_p( dirname( $target_path ) );
				copy( $item->getPathname(), $target_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			}
		}
	}

	/**
	 * Apply a SQL dump created by this backup manager.
	 *
	 * @since  0.6095
	 * @param  string $sql_path Absolute path to the exported SQL file.
	 * @return bool True when the SQL file was imported cleanly.
	 */
	private static function import_database_dump( string $sql_path ): bool {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! is_readable( $sql_path ) ) {
			return false;
		}

		/*
		 * This restore path also needs $wpdb by design.
		 *
		 * Core WordPress functions can update individual entities, but they cannot replay an
		 * arbitrary SQL dump that may include schema statements, bulk inserts, foreign-key state,
		 * or plugin-defined tables outside the standard content APIs. Converting a dump into calls
		 * like wp_insert_post(), update_option(), or wp_insert_user() would be incomplete, would
		 * lose plugin schema fidelity, and would be materially slower on large restores.
		 *
		 * Since this method is intentionally acting as a database import routine, direct execution
		 * through $wpdb is the faithful implementation. The surrounding archive validation and the
		 * explicit SQL-import policy gate are what make this low-level path acceptable.
		 */

		$handle = fopen( $sql_path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return false;
		}

		$statement = '';
		$success   = true;

		$wpdb->query( 'SET foreign_key_checks = 0' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared

		while ( false !== ( $line = fgets( $handle ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets
			$trimmed = trim( $line );

			if ( '' === $trimmed || 0 === strpos( $trimmed, '--' ) ) {
				continue;
			}

			$statement .= $line;

			if ( preg_match( '/;\s*$/', $trimmed ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared -- Executes the plugin's own self-generated SQL dump during an admin-gated, nonce-verified, capability-checked restore. The full statement is read from the dump file; there are no bindable values to parameterize.
				$wpdb->query( $statement );
				if ( ! empty( $wpdb->last_error ) ) {
					$success = false;
					break;
				}

				$statement = '';
			}
		}

		$wpdb->query( 'SET foreign_key_checks = 1' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $success;
	}

	/**
	 * Determine whether SQL import is allowed for restore operations.
	 *
	 * @since  0.6095
	 * @param  string $backup_file Backup filename being restored.
	 * @return bool
	 */
	private static function is_database_restore_allowed( string $backup_file ): bool {
		$allowed = (bool) get_option( self::OPTION_ALLOW_DATABASE_RESTORE, false );

		return (bool) apply_filters(
			self::FILTER_ALLOW_DATABASE_RESTORE,
			$allowed,
			sanitize_file_name( $backup_file )
		);
	}

	/**
	 * Build the user-facing message for a policy-denied database import.
	 *
	 * @since  0.6095
	 * @return string
	 */
	private static function get_database_restore_denied_message(): string {
		return __( 'Backup files were restored successfully and Christopher Ross Shadow created a fresh safety backup first, but the SQL import was skipped because database restore is disabled by site policy.', 'thisismyurl-shadow' );
	}

	/**
	 * Resolve the live destination path for a restored config file.
	 *
	 * @since  0.6095
	 * @param  string $filename Extracted config filename.
	 * @return string Absolute live destination path.
	 */
	private static function get_config_restore_target( string $filename ): string {
		switch ( $filename ) {
			case 'wp-config.php':
				return file_exists( ABSPATH . 'wp-config.php' ) ? ABSPATH . 'wp-config.php' : dirname( ABSPATH ) . '/wp-config.php';
			case '.htaccess':
				return ABSPATH . '.htaccess';
			default:
				return '';
		}
	}

	/**
	 * Remove a temporary directory tree.
	 *
	 * @since  0.6095
	 * @param  string $directory Directory to remove.
	 * @return void
	 */
	private static function remove_directory_tree( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			} else {
				wp_delete_file( $item->getPathname() );
			}
		}

		rmdir( $directory ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	/**
	 * Get a user-facing label for the backup trigger type.
	 *
	 * @since  0.6095
	 * @param  string $trigger Trigger slug.
	 * @return string Human-readable label.
	 */
	private static function get_trigger_label( string $trigger ): string {
		switch ( $trigger ) {
			case 'scheduled':
				return __( 'Scheduled backup', 'thisismyurl-shadow' );
			case 'treatment':
				return __( 'Pre-treatment backup', 'thisismyurl-shadow' );
			case 'pre-restore':
				return __( 'Safety backup', 'thisismyurl-shadow' );
			case 'manual':
			default:
				return __( 'Manual backup', 'thisismyurl-shadow' );
		}
	}

	/**
	 * Get paths that should never be included inside a backup archive.
	 *
	 * @since  0.6095
	 * @return array<int,string> Normalized path prefixes to exclude.
	 */
	private static function get_excluded_paths(): array {
		return array(
			wp_normalize_path( self::get_backup_root_directory() ),
			wp_normalize_path( self::get_legacy_backup_directory() ),
			wp_normalize_path( WP_CONTENT_DIR . '/cache' ),
			wp_normalize_path( WP_CONTENT_DIR . '/upgrade' ),
			wp_normalize_path( ABSPATH . '.git' ),
		);
	}

	/**
	 * Determine whether a path should be excluded from backup archives.
	 *
	 * @since  0.6095
	 * @param  string            $path           Candidate normalized path.
	 * @param  array<int,string> $excluded_paths Excluded path prefixes.
	 * @return bool True when the path should be skipped.
	 */
	private static function is_excluded_path( string $path, array $excluded_paths ): bool {
		foreach ( $excluded_paths as $excluded_path ) {
			if ( '' !== $excluded_path && 0 === strpos( $path, $excluded_path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate that an indexed backup entry still points to a managed archive path.
	 *
	 * @since  0.6095
	 * @param  array<string,mixed> $entry Backup entry.
	 * @return bool
	 */
	private static function is_valid_backup_entry( array $entry ): bool {
		$path = isset( $entry['path'] ) ? (string) $entry['path'] : '';
		$file = isset( $entry['file'] ) ? sanitize_file_name( (string) $entry['file'] ) : '';

		if ( '' === $path || '' === $file ) {
			return false;
		}

		return self::is_managed_backup_path( $path ) && $file === sanitize_file_name( wp_basename( $path ) );
	}

	/**
	 * Determine whether a path is inside a managed backup directory.
	 *
	 * @since  0.6095
	 * @param  string $path Candidate path.
	 * @return bool
	 */
	private static function is_managed_backup_path( string $path ): bool {
		$normalized_path = wp_normalize_path( $path );
		$real_path       = file_exists( $path ) ? wp_normalize_path( (string) realpath( $path ) ) : $normalized_path;

		if ( '' === $real_path || 'zip' !== strtolower( (string) pathinfo( $normalized_path, PATHINFO_EXTENSION ) ) ) {
			return false;
		}

		$allowed_roots = array(
			trailingslashit( wp_normalize_path( self::get_backup_root_directory() ) ),
			trailingslashit( wp_normalize_path( self::get_legacy_backup_directory() ) ),
		);

		foreach ( $allowed_roots as $allowed_root ) {
			if ( 0 === strpos( $real_path, $allowed_root ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate archive structure before it is used for restore operations.
	 *
	 * @since  0.6095
	 * @param  \ZipArchive $zip Open archive instance.
	 * @return array{success:bool,message?:string}
	 */
	private static function validate_restore_archive( \ZipArchive $zip ): array {
		if ( $zip->numFiles <= 0 || $zip->numFiles > self::RESTORE_MAX_ARCHIVE_ENTRIES ) {
			return array(
				'success' => false,
				'message' => __( 'The backup archive contains an invalid number of entries.', 'thisismyurl-shadow' ),
			);
		}

		if ( false === $zip->locateName( 'manifest.json' ) ) {
			return array(
				'success' => false,
				'message' => __( 'The backup archive is missing its manifest file.', 'thisismyurl-shadow' ),
			);
		}

		$total_uncompressed = 0;

		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$stat = $zip->statIndex( $index );
			if ( ! is_array( $stat ) || empty( $stat['name'] ) ) {
				return array(
					'success' => false,
					'message' => __( 'The backup archive contains unreadable entries.', 'thisismyurl-shadow' ),
				);
			}

			$entry_name = str_replace( '\\', '/', (string) $stat['name'] );
			if ( ! self::is_allowed_restore_archive_entry( $entry_name ) ) {
				return array(
					'success' => false,
					'message' => __( 'The backup archive contains unexpected paths and cannot be restored automatically.', 'thisismyurl-shadow' ),
				);
			}

			$total_uncompressed += max( 0, (int) ( $stat['size'] ?? 0 ) );
			if ( $total_uncompressed > self::RESTORE_MAX_UNCOMPRESSED_BYTES ) {
				return array(
					'success' => false,
					'message' => __( 'The backup archive is too large to restore automatically.', 'thisismyurl-shadow' ),
				);
			}
		}

		return array( 'success' => true );
	}

	/**
	 * Determine whether an archive entry path is allowed for restore.
	 *
	 * @since  0.6095
	 * @param  string $entry_name Archive entry name.
	 * @return bool
	 */
	private static function is_allowed_restore_archive_entry( string $entry_name ): bool {
		$entry_name = trim( $entry_name );

		if ( '' === $entry_name || strlen( $entry_name ) > 1024 ) {
			return false;
		}

		if ( '/' === $entry_name[0] || preg_match( '/^[A-Za-z]:\//', $entry_name ) ) {
			return false;
		}

		if ( false !== strpos( $entry_name, '../' ) || false !== strpos( $entry_name, '..\\' ) || false !== strpos( $entry_name, '/..' ) ) {
			return false;
		}

		if ( 'manifest.json' === $entry_name || 'database.sql' === $entry_name ) {
			return true;
		}

		return 0 === strpos( $entry_name, 'site-files/' );
	}

	/**
	 * Build a standardized failure result payload.
	 *
	 * @since  0.6095
	 * @param  string $message User-facing failure message.
	 * @param  string $trigger Backup trigger slug.
	 * @return array<string,mixed> Failure result payload.
	 */
	private static function build_failure_result( string $message, string $trigger ): array {
		$result = array(
			'success' => false,
			'message' => $message,
			'trigger' => $trigger,
		);

		update_option( self::OPTION_LAST_RESULT, $result, false );

		return $result;
	}

	/**
	 * Format a timestamp using the site's date and time settings.
	 *
	 * @since  0.6095
	 * @param  int $timestamp Unix timestamp.
	 * @return string Human-readable date/time.
	 */
	private static function format_timestamp( int $timestamp ): string {
		return wp_date(
			get_option( 'date_format', 'Y-m-d' ) . ' ' . get_option( 'time_format', 'H:i' ),
			$timestamp
		);
	}
}
