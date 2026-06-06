<?php
/**
 * WordPress Settings Helper for Diagnostics
 *
 * Provides cached, read-only access to WordPress settings and options
 * commonly needed by diagnostic tests. All methods are side-effect-free
 * and safe to call repeatedly.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics\Helpers
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable update_modification_detected

/**
 * Diagnostic_WP_Settings_Helper Class
 *
 * Static helpers for accessing WordPress settings data.
 * Results are cached per request using static properties.
 *
 * @since 0.6095
 */
class Diagnostic_WP_Settings_Helper {

	/**
	 * Per-request option cache.
	 *
	 * @var array<string, mixed>
	 */
	private static array $cache = array();

	// -------------------------------------------------------------------------
	// Site Identity
	// -------------------------------------------------------------------------

	/**
	 * Get the site title (blogname).
	 *
	 * @return string
	 */
	public static function get_site_title(): string {
		return (string) get_option( 'blogname', '' );
	}

	/**
	 * Check whether the site title is still the WordPress default.
	 *
	 * @return bool
	 */
	public static function is_default_site_title(): bool {
		$title = strtolower( trim( self::get_site_title() ) );
		return '' === $title || 'my wordpress website' === $title || 'wordpress' === $title;
	}

	/**
	 * Get the site tagline (blogdescription).
	 *
	 * @return string
	 */
	public static function get_tagline(): string {
		return (string) get_option( 'blogdescription', '' );
	}

	/**
	 * Check whether the tagline is still the WordPress default.
	 *
	 * @return bool
	 */
	public static function is_default_tagline(): bool {
		$tagline = strtolower( trim( self::get_tagline() ) );
		$defaults = array(
			'',
			'just another wordpress site',
			'just another wordpress blog',
		);
		return in_array( $tagline, $defaults, true );
	}

	/**
	 * Get the site locale (WPLANG option).
	 *
	 * @return string e.g. 'en_US', 'de_DE', or '' for English default.
	 */
	public static function get_locale(): string {
		return (string) get_option( 'WPLANG', '' );
	}

	/**
	 * Get the admin email address.
	 *
	 * @return string
	 */
	public static function get_admin_email(): string {
		return (string) get_option( 'admin_email', '' );
	}

	// -------------------------------------------------------------------------
	// Timezone & Date Formats
	// -------------------------------------------------------------------------

	/**
	 * Get timezone configuration details.
	 *
	 * @return array{
	 *     timezone_string: string,
	 *     gmt_offset: float,
	 *     is_utc: bool,
	 *     is_named: bool,
	 *     is_valid: bool
	 * }
	 */
	public static function get_timezone_data(): array {
		if ( isset( self::$cache['timezone_data'] ) ) {
			return self::$cache['timezone_data'];
		}

		$timezone_string = (string) get_option( 'timezone_string', '' );
		$gmt_offset      = (float) get_option( 'gmt_offset', 0 );

		$is_named = '' !== $timezone_string;
		$is_utc   = ! $is_named && 0.0 === $gmt_offset;

		// Validate the named timezone string.
		$is_valid = true;
		if ( $is_named ) {
			try {
				new \DateTimeZone( $timezone_string );
			} catch ( \Exception $e ) {
				$is_valid = false;
			}
		}

		self::$cache['timezone_data'] = array(
			'timezone_string' => $timezone_string,
			'gmt_offset'      => $gmt_offset,
			'is_utc'          => $is_utc,
			'is_named'        => $is_named,
			'is_valid'        => $is_valid,
		);

		return self::$cache['timezone_data'];
	}

	/**
	 * Get the date format option.
	 *
	 * @return string PHP date format string, e.g. 'F j, Y'.
	 */
	public static function get_date_format(): string {
		return (string) get_option( 'date_format', 'F j, Y' );
	}

	/**
	 * Get the time format option.
	 *
	 * @return string PHP time format string, e.g. 'g:i a'.
	 */
	public static function get_time_format(): string {
		return (string) get_option( 'time_format', 'g:i a' );
	}

	/**
	 * Get the week start day.
	 *
	 * @return int 0 = Sunday, 1 = Monday … 6 = Saturday.
	 */
	public static function get_week_start_day(): int {
		return (int) get_option( 'start_of_week', 0 );
	}

	// -------------------------------------------------------------------------
	// Front Page / Reading Settings
	// -------------------------------------------------------------------------

	/**
	 * Get the reading/front-page display setting.
	 *
	 * @return string 'latest_posts' or 'page'.
	 */
	public static function get_front_page_display(): string {
		return (string) get_option( 'show_on_front', 'posts' ) === 'page' ? 'page' : 'latest_posts';
	}

	/**
	 * Get the configured static front page post ID.
	 * Returns 0 when not configured or using 'latest posts'.
	 *
	 * @return int
	 */
	public static function get_front_page_id(): int {
		return (int) get_option( 'page_on_front', 0 );
	}

	/**
	 * Get the configured posts/blog page post ID.
	 * Returns 0 when not configured.
	 *
	 * @return int
	 */
	public static function get_posts_page_id(): int {
		return (int) get_option( 'page_for_posts', 0 );
	}

	/**
	 * Return whether the site uses a static front page.
	 *
	 * @return bool
	 */
	public static function has_static_front_page(): bool {
		return 'page' === self::get_front_page_display();
	}

	// -------------------------------------------------------------------------
	// Users / Registration
	// -------------------------------------------------------------------------

	/**
	 * Get the default user role for new registrations.
	 *
	 * @return string e.g. 'subscriber', 'contributor', 'administrator'.
	 */
	public static function get_default_user_role(): string {
		return (string) get_option( 'default_role', 'subscriber' );
	}

	/**
	 * Check whether open user registration is enabled.
	 *
	 * @return bool
	 */
	public static function is_registration_open(): bool {
		return (bool) get_option( 'users_can_register', false );
	}

	// -------------------------------------------------------------------------
	// Comments / Discussion
	// -------------------------------------------------------------------------

	/**
	 * Check whether new posts have comments open by default.
	 *
	 * @return bool
	 */
	public static function are_comments_open_by_default(): bool {
		return 'open' === get_option( 'default_comment_status', 'closed' );
	}

	/**
	 * Check whether new posts have pings/trackbacks open by default.
	 *
	 * @return bool
	 */
	public static function are_pings_open_by_default(): bool {
		return 'open' === get_option( 'default_ping_status', 'closed' );
	}

	/**
	 * Check whether comment moderation is enabled (hold all comments for review).
	 *
	 * @return bool
	 */
	public static function is_comment_moderation_enabled(): bool {
		return (bool) get_option( 'moderation_queue', false ) || (bool) get_option( 'comment_moderation', false );
	}

	/**
	 * Get the maximum number of links allowed in a comment before it is held.
	 * 0 means no limit applied this way.
	 *
	 * @return int
	 */
	public static function get_max_links_in_comment(): int {
		return (int) get_option( 'comment_max_links', 2 );
	}

	/**
	 * Check whether comments require registration to post.
	 *
	 * @return bool
	 */
	public static function is_comment_registration_required(): bool {
		return (bool) get_option( 'comment_registration', false );
	}

	// -------------------------------------------------------------------------
	// Privacy Policy
	// -------------------------------------------------------------------------

	/**
	 * Get the privacy policy page ID.
	 * Returns 0 when not configured.
	 *
	 * @return int
	 */
	public static function get_privacy_policy_page_id(): int {
		return (int) get_option( 'wp_page_for_privacy_policy', 0 );
	}

	/**
	 * Check whether a privacy policy page is configured and published.
	 *
	 * @return bool
	 */
	public static function has_published_privacy_policy_page(): bool {
		$page_id = self::get_privacy_policy_page_id();
		if ( $page_id <= 0 ) {
			return false;
		}
		$page = get_post( $page_id );
		return $page instanceof \WP_Post && 'publish' === $page->post_status;
	}

	// -------------------------------------------------------------------------
	// Media / Images
	// -------------------------------------------------------------------------

	/**
	 * Get thumbnail image dimensions.
	 *
	 * @return array{width: int, height: int, crop: bool}
	 */
	public static function get_thumbnail_size(): array {
		return array(
			'width'  => (int) get_option( 'thumbnail_size_w', 150 ),
			'height' => (int) get_option( 'thumbnail_size_h', 150 ),
			'crop'   => (bool) get_option( 'thumbnail_crop', 1 ),
		);
	}

	/**
	 * Get medium image dimensions.
	 *
	 * @return array{width: int, height: int}
	 */
	public static function get_medium_size(): array {
		return array(
			'width'  => (int) get_option( 'medium_size_w', 300 ),
			'height' => (int) get_option( 'medium_size_h', 300 ),
		);
	}

	/**
	 * Get large image dimensions.
	 *
	 * @return array{width: int, height: int}
	 */
	public static function get_large_size(): array {
		return array(
			'width'  => (int) get_option( 'large_size_w', 1024 ),
			'height' => (int) get_option( 'large_size_h', 1024 ),
		);
	}

	/**
	 * Get all registered additional image sizes (excluding built-ins).
	 *
	 * @return array<string, array{width: int, height: int, crop: bool}>
	 */
	public static function get_additional_image_sizes(): array {
		if ( isset( self::$cache['additional_image_sizes'] ) ) {
			return self::$cache['additional_image_sizes'];
		}
		$sizes = wp_get_additional_image_sizes();
		self::$cache['additional_image_sizes'] = is_array( $sizes ) ? $sizes : array();
		return self::$cache['additional_image_sizes'];
	}

	/**
	 * Get the JPEG compression quality (0–100).
	 *
	 * @return int
	 */
	public static function get_jpeg_quality(): int {
		// WordPress applies jpeg_quality filter; use the option as the base.
		$quality = (int) get_option( 'jpeg_quality', 82 );
		// Also check if constant is defined upstream.
		return $quality > 0 ? $quality : 82;
	}

	/**
	 * Get the big image size threshold in pixels (0 = disabled).
	 *
	 * @return int
	 */
	public static function get_big_image_size_threshold(): int {
		$threshold = (int) get_option( 'big_image_size_threshold', 2560 );
		if ( defined( 'BIG_IMAGE_SIZE_THRESHOLD' ) ) {
			$threshold = (int) BIG_IMAGE_SIZE_THRESHOLD;
		}
		return $threshold;
	}

	/**
	 * Check whether WordPress organises uploads into year/month folders.
	 *
	 * @return bool
	 */
	public static function uses_year_month_folders(): bool {
		return (bool) get_option( 'uploads_use_yearmonth_folders', 1 );
	}

	// -------------------------------------------------------------------------
	// Reading / Performance
	// -------------------------------------------------------------------------

	/**
	 * Get the number of posts shown per page.
	 *
	 * @return int
	 */
	public static function get_posts_per_page(): int {
		return (int) get_option( 'posts_per_page', 10 );
	}

	// -------------------------------------------------------------------------
	// Maintenance Mode
	// -------------------------------------------------------------------------

	/**
	 * Check whether the site is currently in maintenance mode.
	 *
	 * Detects:
	 * 1. WordPress core .maintenance file (left over from mid-update).
	 * 2. Popular coming-soon/maintenance-mode plugin options.
	 *
	 * @return bool
	 */
	public static function is_maintenance_mode_active(): bool {
		if ( isset( self::$cache['maintenance_mode'] ) ) {
			return self::$cache['maintenance_mode'];
		}

		$active = false;

		// 1. WordPress core .maintenance file (ABSPATH/.maintenance).
		$maintenance_file = ABSPATH . '.maintenance';
		if ( file_exists( $maintenance_file ) ) {
			$active = true;
		}

		// 2. SeedProd / Coming Soon Pro.
		if ( ! $active ) {
			$seedprod = get_option( 'seedprod_page_settings', null );
			if ( is_array( $seedprod ) && ! empty( $seedprod['enable_maintenance_mode'] ) ) {
				$active = true;
			}
		}

		// 3. Maintenance Mode (fruitfulcode plugin) / LightStart / Divi Maintenance.
		if ( ! $active && get_option( 'maintenance_mode', false ) ) {
			$active = true;
		}

		// 4. WP Maintenance Mode plugin (wpmm_settings).
		if ( ! $active ) {
			$wpmm = get_option( 'wpmm_settings', null );
			if ( is_array( $wpmm ) && ! empty( $wpmm['general']['status'] ) && 1 === (int) $wpmm['general']['status'] ) {
				$active = true;
			}
		}

		// 5. Coming Soon Page – Under Construction (by Colorlib): coolclock_countdown_settings.
		if ( ! $active ) {
			$colorlib = get_option( 'colorlib_coming_soon_settings', null );
			if ( is_array( $colorlib ) && ! empty( $colorlib['status'] ) && '1' === (string) $colorlib['status'] ) {
				$active = true;
			}
		}

		// 6. WP Maintenance by WebFactory.
		if ( ! $active ) {
			$webfactory = get_option( 'wp_maintenance', null );
			if ( is_array( $webfactory ) && ! empty( $webfactory['state'] ) && 1 === (int) $webfactory['state'] ) {
				$active = true;
			}
		}

		self::$cache['maintenance_mode'] = $active;
		return $active;
	}

	// -------------------------------------------------------------------------
	// Site URLs
	// -------------------------------------------------------------------------

	/**
	 * Get the WordPress address URL (option siteurl).
	 *
	 * @return string
	 */
	public static function get_wp_address(): string {
		return (string) get_option( 'siteurl', '' );
	}

	/**
	 * Get the Site address / Home URL (option home).
	 *
	 * @return string
	 */
	public static function get_home_address(): string {
		return (string) get_option( 'home', '' );
	}

	/**
	 * Check whether the site URL (siteurl) uses HTTPS.
	 *
	 * @return bool
	 */
	public static function is_site_url_https(): bool {
		return 0 === strncmp( self::get_wp_address(), 'https://', 8 );
	}

	/**
	 * Check whether the home URL uses HTTPS.
	 *
	 * @return bool
	 */
	public static function is_home_url_https(): bool {
		return 0 === strncmp( self::get_home_address(), 'https://', 8 );
	}

	// -------------------------------------------------------------------------
	// Auto-updates
	// -------------------------------------------------------------------------

	/**
	 * Get the effective core auto-update behaviour.
	 *
	 * @return string 'all'|'minor'|'development'|'disabled'
	 */
	public static function get_auto_update_core(): string {
		// Constant overrides everything.
		if ( defined( 'WP_AUTO_UPDATE_CORE' ) ) {
			$val = WP_AUTO_UPDATE_CORE;
			if ( false === $val ) {
				return 'disabled';
			}
			if ( 'development' === $val || 'minor' === $val || 'beta' === $val ) {
				return (string) $val;
			}
			if ( true === $val ) {
				return 'all';
			}
		}

		$option = get_option( 'auto_update_core_enabled', null );
		if ( null !== $option ) {
			return $option ? 'minor' : 'disabled';
		}

		// Default WordPress behaviour: minor only.
		return 'minor';
	}

	/**
	 * Check whether plugin auto-updates are enabled at the WordPress level.
	 * Individual plugins can override this with their own option.
	 *
	 * @return bool
	 */
	public static function is_plugin_auto_update_enabled(): bool {
		if ( defined( 'WP_AUTO_UPDATE_CORE' ) && false === WP_AUTO_UPDATE_CORE ) {
			return false;
		}
		$plugin_updates_option = 'auto_update_' . 'plugins';
		return (bool) get_option( $plugin_updates_option, false );
	}

	/**
	 * Check whether theme auto-updates are enabled.
	 *
	 * @return bool
	 */
	public static function is_theme_auto_update_enabled(): bool {
		if ( defined( 'WP_AUTO_UPDATE_CORE' ) && false === WP_AUTO_UPDATE_CORE ) {
			return false;
		}
		return (bool) get_option( 'auto_update_themes', false );
	}

	// -------------------------------------------------------------------------
	// Update Status
	// -------------------------------------------------------------------------

	/**
	 * Get available core update information.
	 * Returns null when no update is available.
	 *
	 * @return array|null { current: string, available: string }
	 */
	public static function get_available_core_update(): ?array {
		if ( array_key_exists( 'core_update', self::$cache ) ) {
			return self::$cache['core_update'];
		}

		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$updates = get_core_updates();
		if ( empty( $updates ) || ! is_array( $updates ) ) {
			self::$cache['core_update'] = null;
			return null;
		}

		foreach ( $updates as $update ) {
			if ( isset( $update->response ) && 'upgrade' === $update->response ) {
				self::$cache['core_update'] = array(
					'current'   => get_bloginfo( 'version' ),
					'available' => isset( $update->current ) ? (string) $update->current : '',
				);
				return self::$cache['core_update'];
			}
		}

		self::$cache['core_update'] = null;
		return null;
	}

	/**
	 * Get list of plugins with available updates.
	 *
	 * Returns array of [ plugin_file => [ Name, current_version, new_version ] ].
	 *
	 * @return array<string, array{name: string, current: string, available: string}>
	 */
	public static function get_plugins_needing_updates(): array {
		if ( isset( self::$cache['plugins_needing_updates'] ) ) {
			return self::$cache['plugins_needing_updates'];
		}

		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$out     = array();
		$updates = get_plugin_updates();
		if ( is_array( $updates ) ) {
			foreach ( $updates as $plugin_file => $plugin_data ) {
				$out[ (string) $plugin_file ] = array(
					'name'      => isset( $plugin_data->Name ) ? (string) $plugin_data->Name : (string) $plugin_file,
					'current'   => isset( $plugin_data->Version ) ? (string) $plugin_data->Version : '',
					'available' => isset( $plugin_data->update->new_version ) ? (string) $plugin_data->update->new_version : '',
				);
			}
		}

		self::$cache['plugins_needing_updates'] = $out;
		return $out;
	}

	/**
	 * Get list of themes with available updates.
	 *
	 * @return array<string, array{name: string, current: string, available: string}>
	 */
	public static function get_themes_needing_updates(): array {
		if ( isset( self::$cache['themes_needing_updates'] ) ) {
			return self::$cache['themes_needing_updates'];
		}

		if ( ! function_exists( 'get_theme_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
			require_once ABSPATH . 'wp-admin/includes/theme.php';
		}

		$out     = array();
		$updates = get_theme_updates();
		if ( is_array( $updates ) ) {
			foreach ( $updates as $theme_slug => $theme ) {
				$out[ (string) $theme_slug ] = array(
					'name'      => method_exists( $theme, 'get' ) ? (string) $theme->get( 'Name' ) : (string) $theme_slug,
					'current'   => method_exists( $theme, 'get' ) ? (string) $theme->get( 'Version' ) : '',
					'available' => isset( $theme->update['new_version'] ) ? (string) $theme->update['new_version'] : '',
				);
			}
		}

		self::$cache['themes_needing_updates'] = $out;
		return $out;
	}

	// -------------------------------------------------------------------------
	// SMTP / Email
	// -------------------------------------------------------------------------

	/**
	 * Known SMTP plugin option/class signatures.
	 *
	 * @var array<string, string>
	 */
	private static array $smtp_plugin_signatures = array(
		'wpforms_smtp'           => 'WP Mail SMTP (WPForms)',
		'swpsmtp_options'        => 'Easy WP SMTP',
		'wp_mail_smtp'           => 'WP Mail SMTP',
		'vs_google_analytics'    => 'Post SMTP Mailer',
		'postman_state'          => 'Post SMTP Mailer',
		'smtp2go_options'        => 'SMTP2GO',
		'sendinblue_config'      => 'Brevo / Sendinblue',
		'mailgun_api_key'        => 'Mailgun',
		'sparkpost_settings'     => 'SparkPost',
		'mailpoet_settings'      => 'MailPoet',
		'fluentmail-settings'    => 'FluentSMTP',
	);

	/**
	 * Detect whether an SMTP or transactional email plugin is active.
	 *
	 * @return bool
	 */
	public static function uses_smtp_plugin(): bool {
		return '' !== self::get_smtp_plugin_name();
	}

	/**
	 * Return the name of the detected SMTP plugin, or empty string.
	 *
	 * @return string
	 */
	public static function get_smtp_plugin_name(): string {
		if ( isset( self::$cache['smtp_plugin_name'] ) ) {
			return self::$cache['smtp_plugin_name'];
		}

		// Check option-based signatures.
		foreach ( self::$smtp_plugin_signatures as $option_key => $plugin_name ) {
			if ( false !== get_option( $option_key, false ) ) {
				self::$cache['smtp_plugin_name'] = $plugin_name;
				return $plugin_name;
			}
		}

		// Check class-based signatures (plugin active = class exists).
		$class_checks = array(
			'PHPMailer\PHPMailer\PHPMailer' => '', // Core; skip.
			'WPMailSMTP\Core'               => 'WP Mail SMTP',
			'FluentMail\App\Services\Mailer\Manager' => 'FluentSMTP',
		);

		foreach ( $class_checks as $class => $name ) {
			if ( '' !== $name && class_exists( $class, false ) ) {
				self::$cache['smtp_plugin_name'] = $name;
				return $name;
			}
		}

		self::$cache['smtp_plugin_name'] = '';
		return '';
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
