<?php
/**
 * Server & WordPress Environment Helper for Diagnostics
 *
 * Provides cached, read-only access to PHP runtime settings, database
 * configuration, WordPress constants, and other server-level data that
 * diagnostic tests need. All methods are side-effect-free and safe to
 * call repeatedly within a single request.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics\Helpers
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

/**
 * Diagnostic_Server_Environment_Helper Class
 *
 * @since 0.6095
 */
class Diagnostic_Server_Environment_Helper {

	/**
	 * Per-request result cache.
	 *
	 * @var array<string, mixed>
	 */
	private static array $cache = array();

	// -------------------------------------------------------------------------
	// PHP Version
	// -------------------------------------------------------------------------

	/**
	 * Get the current PHP version string.
	 *
	 * @return string e.g. '8.2.18'
	 */
	public static function get_php_version(): string {
		return PHP_VERSION;
	}

	/**
	 * Check whether the running PHP version is at least $required.
	 *
	 * @param string $required Version string, e.g. '8.2.0'.
	 * @return bool
	 */
	public static function is_php_at_least( string $required ): bool {
		return version_compare( PHP_VERSION, $required, '>=' );
	}

	// -------------------------------------------------------------------------
	// PHP Memory & Execution
	// -------------------------------------------------------------------------

	/**
	 * Convert a PHP ini memory string (e.g. '256M', '1G', '-1') to bytes.
	 *
	 * @param string $value Raw ini value.
	 * @return int Bytes, or -1 for unlimited.
	 */
	public static function parse_memory_string( string $value ): int {
		$value = trim( $value );
		if ( '-1' === $value ) {
			return -1;
		}
		$unit  = strtolower( substr( $value, -1 ) );
		$bytes = (int) $value;
		switch ( $unit ) {
			case 'g':
				$bytes *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$bytes *= 1024 * 1024;
				break;
			case 'k':
				$bytes *= 1024;
				break;
		}
		return $bytes;
	}

	/**
	 * Get the PHP memory_limit in bytes.
	 *
	 * @return int Bytes (-1 = unlimited).
	 */
	public static function get_php_memory_limit_bytes(): int {
		return self::parse_memory_string( ini_get( 'memory_limit' ) );
	}

	/**
	 * Get the PHP memory_limit in megabytes.
	 *
	 * @return float MB (-1 = unlimited).
	 */
	public static function get_php_memory_limit_mb(): float {
		$bytes = self::get_php_memory_limit_bytes();
		if ( -1 === $bytes ) {
			return -1.0;
		}
		return round( $bytes / ( 1024 * 1024 ), 1 );
	}

	/**
	 * Get the WP_MEMORY_LIMIT constant (WordPress-requested limit) in bytes.
	 *
	 * @return int Bytes (-1 = unlimited).
	 */
	public static function get_wp_memory_limit_bytes(): int {
		$raw = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '40M';
		return self::parse_memory_string( (string) $raw );
	}

	/**
	 * Get max_execution_time in seconds. 0 = unlimited.
	 *
	 * @return int
	 */
	public static function get_max_execution_time(): int {
		return (int) ini_get( 'max_execution_time' );
	}

	// -------------------------------------------------------------------------
	// OPcache
	// -------------------------------------------------------------------------

	/**
	 * Check whether the OPcache extension is installed (loaded).
	 *
	 * @return bool
	 */
	public static function is_opcache_installed(): bool {
		return extension_loaded( 'Zend OPcache' );
	}

	/**
	 * Check whether OPcache is actually enabled.
	 *
	 * @return bool
	 */
	public static function is_opcache_enabled(): bool {
		if ( ! self::is_opcache_installed() ) {
			return false;
		}
		return (bool) ini_get( 'opcache.enable' );
	}

	/**
	 * Get the OPcache memory size from ini (MB).
	 * Returns 0 when OPcache is not available.
	 *
	 * @return int MB
	 */
	public static function get_opcache_memory_mb(): int {
		if ( ! self::is_opcache_installed() ) {
			return 0;
		}
		return (int) ini_get( 'opcache.memory_consumption' );
	}

	/**
	 * Get OPcache status details (calls opcache_get_status safely).
	 * Returns null when unavailable or restricted (e.g. CLI-run context).
	 *
	 * @return array|null
	 */
	public static function get_opcache_status(): ?array {
		if ( isset( self::$cache['opcache_status'] ) ) {
			return self::$cache['opcache_status'];
		}
		if ( ! self::is_opcache_enabled() || ! function_exists( 'opcache_get_status' ) ) {
			self::$cache['opcache_status'] = null;
			return null;
		}
		$status = @opcache_get_status( false ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		self::$cache['opcache_status'] = is_array( $status ) ? $status : null;
		return self::$cache['opcache_status'];
	}

	// -------------------------------------------------------------------------
	// Object Cache
	// -------------------------------------------------------------------------

	/**
	 * Check whether a persistent external object cache is active.
	 *
	 * @return bool
	 */
	public static function is_object_cache_enabled(): bool {
		return (bool) wp_using_ext_object_cache();
	}

	// -------------------------------------------------------------------------
	// Database
	// -------------------------------------------------------------------------

	/**
	 * Get the database server version string.
	 *
	 * @return string e.g. '8.0.32' or '10.6.12-MariaDB'
	 */
	public static function get_db_version(): string {
		if ( isset( self::$cache['db_version'] ) ) {
			return self::$cache['db_version'];
		}
		global $wpdb;
		/*
		 * SELECT VERSION() is intentionally queried via $wpdb because WordPress does not provide a
		 * higher-level API for the raw server version string. $wpdb->db_version() normalizes engine
		 * compatibility information, but this helper also needs the vendor-specific text so diagnostics
		 * can distinguish MariaDB and MySQL variants accurately.
		 */
		$version = $wpdb->get_var( 'SELECT VERSION()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::$cache['db_version'] = is_string( $version ) ? $version : '';
		return self::$cache['db_version'];
	}

	/**
	 * Detect whether the database server is MariaDB.
	 *
	 * @return bool
	 */
	public static function is_mariadb(): bool {
		return false !== stripos( self::get_db_version(), 'mariadb' );
	}

	/**
	 * Detect whether WordPress is running on SQLite.
	 *
	 * @return bool
	 */
	public static function is_sqlite(): bool {
		if ( isset( self::$cache['is_sqlite'] ) ) {
			return (bool) self::$cache['is_sqlite'];
		}

		$is_sqlite = false;

		if ( defined( 'DB_ENGINE' ) && 'sqlite' === strtolower( (string) DB_ENGINE ) ) {
			$is_sqlite = true;
		} elseif ( defined( 'DATABASE_TYPE' ) && 'sqlite' === strtolower( (string) DATABASE_TYPE ) ) {
			$is_sqlite = true;
		}

		self::$cache['is_sqlite'] = $is_sqlite;

		return $is_sqlite;
	}

	/**
	 * Get the storage engine for the wp_posts table (representative of site tables).
	 * Returns empty string if the query fails.
	 *
	 * @return string e.g. 'InnoDB', 'MyISAM', ''
	 */
	public static function get_db_engine(): string {
		if ( isset( self::$cache['db_engine'] ) ) {
			return self::$cache['db_engine'];
		}

		if ( self::is_sqlite() ) {
			self::$cache['db_engine'] = 'SQLite';
			return self::$cache['db_engine'];
		}

		global $wpdb;
		/*
		 * Engine detection is read from information_schema on purpose.
		 * WordPress has no core helper that exposes the storage engine for a table, and using content
		 * APIs would tell us nothing about whether the underlying table is InnoDB, MyISAM, or SQLite.
		 */
		$engine = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				DB_NAME,
				$wpdb->posts
			)
		);
		self::$cache['db_engine'] = is_string( $engine ) ? $engine : '';
		return self::$cache['db_engine'];
	}

	/**
	 * Check whether the wp_posts table uses InnoDB.
	 *
	 * @return bool
	 */
	public static function is_innodb(): bool {
		return 'InnoDB' === self::get_db_engine();
	}

	/**
	 * Get the database table prefix.
	 *
	 * @return string e.g. 'wp_'
	 */
	public static function get_db_prefix(): string {
		global $wpdb;
		/*
		 * Reading $wpdb->prefix directly is preferable here because the prefix is a database adapter
		 * property, not a persisted option. There is no alternative WordPress function that gives a
		 * more canonical answer than the initialized wpdb instance itself.
		 */
		return (string) $wpdb->prefix;
	}

	/**
	 * Check whether the database prefix is the default 'wp_'.
	 *
	 * @return bool
	 */
	public static function is_default_db_prefix(): bool {
		return 'wp_' === self::get_db_prefix();
	}

	/**
	 * Get the database character set (DB_CHARSET constant).
	 *
	 * @return string e.g. 'utf8mb4'
	 */
	public static function get_db_charset(): string {
		return defined( 'DB_CHARSET' ) ? (string) DB_CHARSET : '';
	}

	/**
	 * Get the database collation (DB_COLLATE constant).
	 *
	 * @return string e.g. 'utf8mb4_unicode_ci' or '' for server default.
	 */
	public static function get_db_collation(): string {
		return defined( 'DB_COLLATE' ) ? (string) DB_COLLATE : '';
	}

	// -------------------------------------------------------------------------
	// WordPress Debug Constants
	// -------------------------------------------------------------------------

	/**
	 * Check WP_DEBUG.
	 *
	 * @return bool
	 */
	public static function is_wp_debug(): bool {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * Check WP_DEBUG_DISPLAY.
	 * Returns true when WP_DEBUG_DISPLAY is not set to false (WordPress default = true).
	 *
	 * @return bool
	 */
	public static function is_wp_debug_display(): bool {
		if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
			return true; // WordPress default is on.
		}
		return (bool) WP_DEBUG_DISPLAY;
	}

	/**
	 * Check WP_DEBUG_LOG.
	 * Can be a bool or a path string.
	 *
	 * @return bool|string True for default path, string for custom path, false if disabled.
	 */
	public static function get_wp_debug_log(): bool|string {
		if ( ! defined( 'WP_DEBUG_LOG' ) ) {
			return false;
		}
		$val = WP_DEBUG_LOG;
		if ( is_string( $val ) && '' !== $val && ! in_array( strtolower( $val ), array( '0', 'false' ), true ) ) {
			return $val;
		}
		return (bool) $val;
	}

	/**
	 * Check whether WP_DEBUG_LOG is enabled (either bool true or a custom path).
	 *
	 * @return bool
	 */
	public static function is_wp_debug_log_enabled(): bool {
		$val = self::get_wp_debug_log();
		return false !== $val && '' !== $val;
	}

	/**
	 * Determine whether the debug log is stored inside the web root (accessible publicly).
	 * Returns true when the log path is under ABSPATH with no .htaccess protection detected.
	 *
	 * @return bool
	 */
	public static function is_debug_log_publicly_accessible(): bool {
		$log = self::get_wp_debug_log();
		if ( false === $log || ! is_string( $log ) ) {
			// Bool true means default path: wp-content/debug.log — inside web root.
			if ( true === $log ) {
				return ! file_exists( WP_CONTENT_DIR . '/.htaccess' );
			}
			return false;
		}
		// Custom path — check whether it falls below ABSPATH.
		$real_log  = realpath( dirname( $log ) );
		$real_root = realpath( ABSPATH );
		if ( false === $real_log || false === $real_root ) {
			return false; // Can't determine.
		}
		return 0 === strncmp( $real_log, $real_root, strlen( $real_root ) );
	}

	/**
	 * Check SCRIPT_DEBUG.
	 *
	 * @return bool
	 */
	public static function is_script_debug(): bool {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	}

	/**
	 * Check SAVEQUERIES.
	 *
	 * @return bool
	 */
	public static function is_savequeries(): bool {
		return defined( 'SAVEQUERIES' ) && SAVEQUERIES;
	}

	// -------------------------------------------------------------------------
	// WordPress Security Constants
	// -------------------------------------------------------------------------

	/**
	 * Check DISALLOW_FILE_EDIT (theme/plugin editor disabled).
	 *
	 * @return bool
	 */
	public static function is_file_edit_disabled(): bool {
		return defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
	}

	/**
	 * Check DISALLOW_FILE_MODS (prevents plugin/theme installation + updates from WP Admin).
	 *
	 * @return bool
	 */
	public static function is_file_mods_disabled(): bool {
		return defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS;
	}

	/**
	 * Check FORCE_SSL_ADMIN.
	 *
	 * @return bool
	 */
	public static function is_force_ssl_admin(): bool {
		return defined( 'FORCE_SSL_ADMIN' ) && FORCE_SSL_ADMIN;
	}

	/**
	 * Check DISABLE_WP_CRON.
	 *
	 * @return bool
	 */
	public static function is_wp_cron_disabled(): bool {
		return defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	}

	// -------------------------------------------------------------------------
	// Auth Keys & Salts
	// -------------------------------------------------------------------------

	/**
	 * WordPress secret key constant names.
	 *
	 * @var string[]
	 */
	private static array $auth_key_constants = array(
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
	);

	/**
	 * The placeholder value WordPress ships in wp-config-sample.php.
	 */
	private const AUTH_KEY_PLACEHOLDER = 'put your unique phrase here';

	/**
	 * Check whether all auth keys and salts are defined and non-placeholder.
	 *
	 * @return bool
	 */
	public static function are_auth_keys_configured(): bool {
		return empty( self::get_auth_key_issues() );
	}

	/**
	 * Get a list of auth key/salt constants that are missing or using the placeholder value.
	 *
	 * @return string[] List of problematic constant names.
	 */
	public static function get_auth_key_issues(): array {
		if ( isset( self::$cache['auth_key_issues'] ) ) {
			return self::$cache['auth_key_issues'];
		}

		$issues = array();
		foreach ( self::$auth_key_constants as $constant ) {
			if ( ! defined( $constant ) ) {
				$issues[] = $constant . ' (not defined)';
				continue;
			}
			$value = constant( $constant );
			if ( '' === $value || self::AUTH_KEY_PLACEHOLDER === $value ) {
				$issues[] = $constant . ' (placeholder)';
			}
		}

		self::$cache['auth_key_issues'] = $issues;
		return $issues;
	}

	// -------------------------------------------------------------------------
	// wp-config.php Permissions
	// -------------------------------------------------------------------------

	/**
	 * Attempt to locate wp-config.php.
	 * WordPress allows it one directory above ABSPATH to hide it from the web root.
	 *
	 * @return string Absolute path or empty string if not found.
	 */
	public static function get_wp_config_path(): string {
		if ( isset( self::$cache['wp_config_path'] ) ) {
			return self::$cache['wp_config_path'];
		}

		$candidates = array(
			ABSPATH . 'wp-config.php',
			dirname( ABSPATH ) . '/wp-config.php',
		);

		foreach ( $candidates as $path ) {
			if ( @file_exists( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				self::$cache['wp_config_path'] = $path;
				return $path;
			}
		}

		self::$cache['wp_config_path'] = '';
		return '';
	}

	/**
	 * Get the octal file permissions of wp-config.php.
	 * Returns null when the file cannot be found or stat'd.
	 *
	 * @return string|null e.g. '0600', '0644'.
	 */
	public static function get_wp_config_permissions_octal(): ?string {
		$path = self::get_wp_config_path();
		if ( '' === $path ) {
			return null;
		}
		$perms = @fileperms( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $perms ) {
			return null;
		}
		return substr( sprintf( '%o', $perms ), -4 );
	}

	/**
	 * Check whether wp-config.php permissions are hardened (0600 or 0400).
	 * Returns null when permissions cannot be determined.
	 *
	 * @return bool|null
	 */
	public static function is_wp_config_hardened(): ?bool {
		$octal = self::get_wp_config_permissions_octal();
		if ( null === $octal ) {
			return null;
		}
		return in_array( $octal, array( '0600', '0400', '0640' ), true );
	}

	// -------------------------------------------------------------------------
	// Autoloaded Options
	// -------------------------------------------------------------------------

	/**
	 * Get the total size of autoloaded options in kilobytes.
	 *
	 * Uses a single SUM() aggregation query — far cheaper than loading all
	 * autoloaded options into memory.
	 *
	 * @return float KB
	 */
	public static function get_autoloaded_options_size_kb(): float {
		if ( isset( self::$cache['autoloaded_options_kb'] ) ) {
			return self::$cache['autoloaded_options_kb'];
		}

		global $wpdb;
		$autoload_values = array( 'yes', 'on', '1', 'auto' );
		$placeholders    = implode( ',', array_fill( 0, count( $autoload_values ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$bytes = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ($placeholders)",
				$autoload_values
			)
		);

		$kb                                      = $bytes ? round( (float) $bytes / 1024, 2 ) : 0.0;
		self::$cache['autoloaded_options_kb'] = $kb;
		return $kb;
	}

	// -------------------------------------------------------------------------
	// Transients
	// -------------------------------------------------------------------------

	/**
	 * Count the number of expired transients still stored in the options table.
	 * Only counts transients stored in wp_options (i.e. without a persistent
	 * object cache backend, which handles expiry natively).
	 *
	 * @return int
	 */
	public static function get_expired_transient_count(): int {
		if ( isset( self::$cache['expired_transient_count'] ) ) {
			return self::$cache['expired_transient_count'];
		}

		// With a persistent object cache, transients are not stored in wp_options.
		if ( wp_using_ext_object_cache() ) {
			self::$cache['expired_transient_count'] = 0;
			return 0;
		}

		global $wpdb;
		// Timeout keys hold the expiry timestamp. Expired = timeout < now.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				 AND CAST(option_value AS UNSIGNED) < %d
				 AND CAST(option_value AS UNSIGNED) > 0",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			)
		);

		self::$cache['expired_transient_count'] = $count;
		return $count;
	}

	// -------------------------------------------------------------------------
	// Heartbeat / Autosave
	// -------------------------------------------------------------------------

	/**
	 * Get the Heartbeat API interval in seconds.
	 * WordPress default is 60 seconds (15 on the post editor).
	 *
	 * @return int
	 */
	public static function get_heartbeat_interval(): int {
		// The heartbeat_settings filter adjusts this; read the stored option if available.
		$interval = (int) get_option( 'heartbeat_interval', 60 );
		return $interval > 0 ? $interval : 60;
	}

	/**
	 * Get the autosave interval in seconds.
	 * Defined by the AUTOSAVE_INTERVAL constant (default 60).
	 *
	 * @return int
	 */
	public static function get_autosave_interval(): int {
		return defined( 'AUTOSAVE_INTERVAL' ) ? (int) AUTOSAVE_INTERVAL : 60;
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Clear the per-request cache (useful in testing).
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$cache = array();
	}
}
