<?php
// phpcs:ignoreFile -- File has explicit ABSPATH guards; plugin-check false-positives this namespaced structure.
/**
 * Treatment Hooks
 *
 * Runtime hook registration for Shadow by Christopher Ross treatments that require active
 * WordPress filters/actions rather than passive configuration changes.
 *
 * Called once from Hooks_Initializer::on_plugins_loaded_late(). Each section
 * reads a specific WP option that the corresponding treatment class writes via
 * apply() / deletes via undo(), and adds hooks only when that option is set.
 *
 * Treatments handled here:
 *  - login-throttling-active      → thisismyurl_shadow_login_throttling_enabled
 *  - form-rate-limiting-active    → thisismyurl_shadow_form_rate_limiting_enabled
 *  - login-url-hardening          → thisismyurl_shadow_login_url_token
 *
 * Head tag cleanup (wp_head):
 *  - rss-head-links               → thisismyurl_shadow_remove_rss_head_links
 *  - pingback-head-link           → thisismyurl_shadow_remove_pingback_head_link
 *  - wlwmanifest-link             → thisismyurl_shadow_remove_wlwmanifest_link
 *  - rsd-link                     → thisismyurl_shadow_remove_rsd_link
 *  - shortlink-head-tag           → thisismyurl_shadow_remove_shortlink_head_tag
 *  - oembed-discovery-links       → thisismyurl_shadow_remove_oembed_discovery_links
 *  - wp-generator-tag             → thisismyurl_shadow_remove_wp_generator_tag
 *  - rest-api-head-link           → thisismyurl_shadow_remove_rest_api_head_link
 *  - adjacent-posts-links         → thisismyurl_shadow_remove_adjacent_posts_links
 *  - rss-version-leak             → thisismyurl_shadow_remove_rss_version_leak
 *  - emoji-assets                 → thisismyurl_shadow_remove_emoji_assets
 *  - block-library-css            → thisismyurl_shadow_dequeue_block_library_css
 *
 * Additional runtime toggles:
 *  - embed-assets                 → thisismyurl_shadow_remove_embed_assets
 *  - dashicons-frontend           → thisismyurl_shadow_dequeue_dashicons_frontend
 *  - noncritical-js-deferred      → thisismyurl_shadow_defer_noncritical_js
 *  - heartbeat-usage              → thisismyurl_shadow_optimize_heartbeat
 *  - emoji-in-admin               → thisismyurl_shadow_remove_emoji_admin
 *  - dashboard-rss-widget-active  → thisismyurl_shadow_remove_dashboard_rss_widgets
 *  - security-headers-present     → thisismyurl_shadow_send_security_headers
 *  - admin-session-expiration-hardened → thisismyurl_shadow_harden_admin_session_expiry
 *  - large-image-threshold        → thisismyurl_shadow_big_image_threshold
 *  - jpeg-quality                 → thisismyurl_shadow_jpeg_quality
 *  - image-lazy-loading           → thisismyurl_shadow_reenable_lazy_loading
 *  - search-page-indexing         → thisismyurl_shadow_search_page_noindex_enabled
 *  - media-attachment-pages (< 6.4) → thisismyurl_shadow_redirect_attachment_pages
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Core
 * @since 0.6095
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers runtime WordPress hooks for active native treatments.
 */
class Treatment_Hooks {

	// =========================================================================
	// Boot
	// =========================================================================

	/**
	 * Register runtime hooks for all active native treatments.
	 */
	public static function init(): void {
		self::maybe_init_login_throttling();
		self::maybe_init_form_rate_limiting();
		self::maybe_init_login_url_hardening();
		self::maybe_init_head_cleanup();
		self::maybe_init_embed_assets();
		self::maybe_init_dashicons_frontend();
		self::maybe_init_noncritical_js_deferred();
		self::maybe_init_heartbeat_usage();
		self::maybe_init_emoji_in_admin();
		self::maybe_init_dashboard_rss_widgets();
		self::maybe_init_security_headers();
		self::maybe_init_admin_session_expiration();
		self::maybe_init_large_image_threshold();
		self::maybe_init_jpeg_quality();
		self::maybe_init_image_lazy_loading();
		self::maybe_init_search_page_indexing();
		self::maybe_init_media_attachment_pages_redirect();
	}

	// =========================================================================
	// Login throttling
	// =========================================================================

	/**
	 * If the login-throttling treatment is active, add brute-force protection
	 * hooks.
	 *
	 * On each failed login, the IP is tracked in a transient. After 5 failures
	 * within a 15-minute window, the IP is locked out for 60 minutes. All
	 * thresholds are filterable.
	 */
	private static function maybe_init_login_throttling(): void {
		if ( ! get_option( 'thisismyurl_shadow_login_throttling_enabled', false ) ) {
			return;
		}

		// Track failed attempts.
		add_action( 'wp_login_failed', [ __CLASS__, 'on_login_failed' ] );

		// Gate the authenticate filter — pre-empts the credential check.
		add_filter( 'authenticate', [ __CLASS__, 'filter_authenticate' ], 1, 3 );
	}

	/**
	 * Record a failed login attempt for the current visitor IP.
	 *
	 * @since 0.6095
	 * @param string $username The username that failed to authenticate.
	 */
	public static function on_login_failed( string $username ): void {
		$ip  = self::get_visitor_ip();
		$key = self::throttle_key( $ip );

		/** @var list<int> $attempts */
		$attempts  = (array) get_transient( $key );
		$window    = (int) apply_filters( 'thisismyurl_shadow_login_throttle_window', 15 * MINUTE_IN_SECONDS );
		$now       = time();

		// Purge attempts outside the sliding window.
		$attempts = array_values( array_filter(
			$attempts,
			static function ( $ts ) use ( $now, $window ): bool {
				return is_numeric( $ts ) && ( $now - (int) $ts ) < $window;
			}
		) );

		$attempts[] = $now;
		set_transient( $key, $attempts, $window );

		$limit = (int) apply_filters( 'thisismyurl_shadow_login_throttle_limit', 5 );
		if ( count( $attempts ) >= $limit ) {
			$lockout_duration = (int) apply_filters( 'thisismyurl_shadow_login_lockout_duration', HOUR_IN_SECONDS );
			set_transient( self::lockout_key( $ip ), $now, $lockout_duration );
		}
	}

	/**
	 * Return a WP_Error early if the visiting IP is currently locked out.
	 *
	 * @since 0.6095
	 * @param \WP_User|\WP_Error|null $user     User object, error, or null.
	 * @param string                  $username Username.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function filter_authenticate( $user, string $username, string $password ) {
		if ( empty( $username ) ) {
			return $user;
		}

		// Admins acting on behalf of other users (e.g. REST internal calls)
		// should never be locked out.
		if ( is_a( $user, 'WP_User' ) ) {
			return $user;
		}

		$ip = self::get_visitor_ip();

		if ( get_transient( self::lockout_key( $ip ) ) ) {
			$lockout_duration = (int) apply_filters( 'thisismyurl_shadow_login_lockout_duration', HOUR_IN_SECONDS );
			$minutes          = (int) ceil( $lockout_duration / MINUTE_IN_SECONDS );

			return new \WP_Error(
				'thisismyurl_shadow_login_lockout',
				sprintf(
					/* translators: %d: number of minutes */
					__( '<strong>Too many failed attempts.</strong> Your IP has been temporarily locked out. Please try again in %d minutes, or contact the site administrator.', 'thisismyurl-shadow' ),
					$minutes
				)
			);
		}

		return $user;
	}

	// =========================================================================
	// Form rate limiting (comment submissions)
	// =========================================================================

	/**
	 * If the form-rate-limiting treatment is active, add comment-flood hooks.
	 */
	private static function maybe_init_form_rate_limiting(): void {
		if ( ! get_option( 'thisismyurl_shadow_form_rate_limiting_enabled', false ) ) {
			return;
		}

		add_filter( 'preprocess_comment', [ __CLASS__, 'filter_preprocess_comment' ] );
	}

	/**
	 * Block comment submissions that exceed the rate limit from a single IP.
	 *
	 * Default limit: 3 comments per 5-minute sliding window.
	 *
	 * @since 0.6095
	 * @param array $commentdata Raw comment data.
	 * @return array Comment data, or wp_die() on violation.
	 */
	public static function filter_preprocess_comment( array $commentdata ): array {
		// Always allow logged-in users (admins, editors) to comment without rate limits.
		if ( is_user_logged_in() ) {
			return $commentdata;
		}

		$ip     = self::get_visitor_ip();
		$key    = 'thisismyurl_shadow_comment_rate_' . md5( $ip );
		$limit  = (int) apply_filters( 'thisismyurl_shadow_comment_rate_limit', 3 );
		$window = (int) apply_filters( 'thisismyurl_shadow_comment_rate_window', 5 * MINUTE_IN_SECONDS );
		$now    = time();

		/** @var list<int> $submissions */
		$submissions = (array) get_transient( $key );
		$submissions = array_values( array_filter(
			$submissions,
			static function ( $ts ) use ( $now, $window ): bool {
				return is_numeric( $ts ) && ( $now - (int) $ts ) < $window;
			}
		) );

		if ( count( $submissions ) >= $limit ) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: %d: minutes to wait */
						__( 'You are submitting comments too quickly. Please wait %d minutes before submitting another comment.', 'thisismyurl-shadow' ),
						(int) ceil( $window / MINUTE_IN_SECONDS )
					)
				),
				esc_html__( 'Comment Rate Limit Exceeded', 'thisismyurl-shadow' ),
				[ 'response' => 429, 'back_link' => true ]
			);
		}

		$submissions[] = $now;
		set_transient( $key, $submissions, $window );

		return $commentdata;
	}

	// =========================================================================
	// Login URL hardening
	// =========================================================================

	/**
	 * If the login-url-hardening treatment is active, require a secret query
	 * token on every wp-login.php request.
	 */
	private static function maybe_init_login_url_hardening(): void {
		$token = (string) get_option( 'thisismyurl_shadow_login_url_token', '' );
		if ( '' === $token ) {
			return;
		}

		// Rewrite the login URL to include our secret token.
		add_filter( 'login_url', [ __CLASS__, 'filter_login_url' ], 99, 3 );

		// Intercept direct access to wp-login.php; reject if token is absent.
		add_action( 'login_init', [ __CLASS__, 'on_login_init' ] );
	}

	/**
	 * Append the secret token to the login URL.
	 *
	 * @since 0.6095
	 * @param string      $login_url    The login URL.
	 * @param string      $redirect     Redirect URL after login.
	 * @param bool        $force_reauth Force authentication.
	 * @return string
	 */
	public static function filter_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
		$token = (string) get_option( 'thisismyurl_shadow_login_url_token', '' );
		if ( '' === $token ) {
			return $login_url;
		}
		return add_query_arg( 'wpstoken', $token, $login_url );
	}

	/**
	 * At the start of every wp-login.php request: if the token is missing or
	 * wrong, redirect to the site homepage with a 302.
	 *
	 * Internal WordPress processes that use `wp_login_url()` already receive
	 * the token via the filter above. Browser-direct access (bot scanning) will
	 * lack the token and be redirected.
	 *
	 * Safety: if the stored token is empty for any reason, the gate is bypassed
	 * so the admin can never be locked out.
	 *
	 * @since 0.6095
	 */
	public static function on_login_init(): void {
		// Never apply the gate during WP-Cron or REST internal requests.
		if ( wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$token = (string) get_option( 'thisismyurl_shadow_login_url_token', '' );
		if ( '' === $token ) {
			return; // Safety: no token configured → no gate.
		}

		$supplied = isset( $_GET['wpstoken'] ) ? sanitize_key( wp_unslash( $_GET['wpstoken'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only login-URL token gate compared with hash_equals().

		if ( ! hash_equals( $token, $supplied ) ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
	}

	// =========================================================================
	// Head tag cleanup (wp_head)
	// =========================================================================

	/**
	 * Register all active head-tag removal actions in a single pass to avoid
	 * multiple small get_option() calls scattered across separate init hooks.
	 *
	 * Each branch is guarded by the corresponding treatment option so the hooks
	 * fire only when the treatment has been applied. All remove_action() calls
	 * are idempotent and safe to run on every request.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_head_cleanup(): void {
		self::maybe_init_rss_head_links();
		self::maybe_init_pingback_head_link();
		self::maybe_init_wlwmanifest_link();
		self::maybe_init_rsd_link();
		self::maybe_init_shortlink_head_tag();
		self::maybe_init_oembed_discovery_links();
		self::maybe_init_wp_generator_tag();
		self::maybe_init_rest_api_head_link();
		self::maybe_init_adjacent_posts_links();
		self::maybe_init_rss_version_leak();
		self::maybe_init_emoji_assets();
		self::maybe_init_block_library_css();
	}

	/**
	 * Remove RSS feed autodiscovery <link> tags from <head>.
	 *
	 * Removes feed_links (main post + comments feed) at priority 2 and
	 * feed_links_extra (category, author, search feeds) at priority 3.
	 * The feed URLs themselves remain accessible; only the head advertisements
	 * are suppressed.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_rss_head_links(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_rss_head_links', false ) ) {
			return;
		}
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}

	/**
	 * Remove the pingback <link rel="pingback"> head tag and X-Pingback HTTP header.
	 *
	 * The head tag always outputs the xmlrpc.php URL regardless of whether
	 * pingbacks are disabled. The X-Pingback header is suppressed by removing
	 * its filter from wp_headers.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_pingback_head_link(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_pingback_head_link', false ) ) {
			return;
		}
		remove_action( 'wp_head',    'pingback_url' );
		remove_filter( 'wp_headers', 'wp_headers_pingback' );
	}

	/**
	 * Remove the legacy Windows Live Writer manifest link from <head>.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_wlwmanifest_link(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_wlwmanifest_link', false ) ) {
			return;
		}
		remove_action( 'wp_head', 'wlwmanifest_link' );
	}

	/**
	 * Remove the Really Simple Discovery (RSD) link from <head>.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_rsd_link(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_rsd_link', false ) ) {
			return;
		}
		remove_action( 'wp_head', 'rsd_link' );
	}

	/**
	 * Remove the shortlink <link> tag from <head> and the X-Shortlink HTTP header.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_shortlink_head_tag(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_shortlink_head_tag', false ) ) {
			return;
		}
		remove_action( 'wp_head',           'wp_shortlink_wp_head', 10, 0 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	}

	/**
	 * Remove oEmbed autodiscovery <link> tags from <head>.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_oembed_discovery_links(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_oembed_discovery_links', false ) ) {
			return;
		}
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	}

	/**
	 * Remove the WordPress version <meta name="generator"> tag from <head>.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_wp_generator_tag(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_wp_generator_tag', false ) ) {
			return;
		}
		remove_action( 'wp_head', 'wp_generator' );
	}

	/**
	 * Remove REST API discovery links from <head>.
	 *
	 * This does not disable the REST API itself; it only stops publishing
	 * the endpoint discovery tag in front-end HTML.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_rest_api_head_link(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_rest_api_head_link', false ) ) {
			return;
		}
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	}

	/**
	 * Remove adjacent post <link rel="prev/next"> tags from <head>.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_adjacent_posts_links(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_adjacent_posts_links', false ) ) {
			return;
		}
		remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );
	}

	/**
	 * Suppress the WordPress version string from RSS feed <generator> tags.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_rss_version_leak(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_rss_version_leak', false ) ) {
			return;
		}
		add_filter( 'the_generator', '__return_empty_string' );
	}

	/**
	 * Remove WordPress emoji detection scripts, styles, and feed filters.
	 *
	 * Emoji characters continue to render via the OS/browser natively.
	 * Only the WordPress-injected script, SVG stylesheet, DNS prefetch tag,
	 * and feed staticise filters are removed.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_emoji_assets(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_emoji_assets', false ) ) {
			return;
		}
		remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles',     'print_emoji_styles' );
		remove_action( 'admin_print_styles',  'print_emoji_styles' );
		remove_filter( 'the_content_feed',    'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss',    'wp_staticize_emoji' );
		remove_filter( 'wp_mail',             'wp_staticize_emoji_for_email' );
	}

	/**
	 * Dequeue block-library styles on classic themes.
	 *
	 * This treatment is intended for classic themes and should not run on
	 * block/FSE themes where these styles are required.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_block_library_css(): void {
		if ( ! get_option( 'thisismyurl_shadow_dequeue_block_library_css', false ) ) {
			return;
		}

		add_action(
			'wp_enqueue_scripts',
			static function (): void {
				if ( ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) || current_theme_supports( 'block-templates' ) ) {
					return;
				}

				wp_dequeue_style( 'wp-block-library' );
				wp_dequeue_style( 'wp-block-library-theme' );
				wp_dequeue_style( 'global-styles' );

				wp_deregister_style( 'wp-block-library' );
				wp_deregister_style( 'wp-block-library-theme' );
				wp_deregister_style( 'global-styles' );
			},
			100
		);
	}

	// =========================================================================
	// Additional runtime option-backed treatments
	// =========================================================================

	/**
	 * Disable WordPress embed host assets and related endpoints.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_embed_assets(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_embed_assets', false ) ) {
			return;
		}

		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	}

	/**
	 * Dequeue Dashicons on the frontend for logged-out visitors.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_dashicons_frontend(): void {
		if ( ! get_option( 'thisismyurl_shadow_dequeue_dashicons_frontend', false ) ) {
			return;
		}

		add_action(
			'wp_enqueue_scripts',
			static function (): void {
				if ( is_user_logged_in() ) {
					return;
				}

				wp_dequeue_style( 'dashicons' );
				wp_deregister_style( 'dashicons' );
			},
			100
		);
	}

	/**
	 * Defer non-critical frontend scripts by injecting the defer attribute.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_noncritical_js_deferred(): void {
		if ( ! get_option( 'thisismyurl_shadow_defer_noncritical_js', false ) ) {
			return;
		}

		add_filter(
			'script_loader_tag',
			static function ( string $tag, string $handle, string $src ): string {
				if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
					return $tag;
				}

				if ( '' === $src || false === strpos( $tag, '<script' ) ) {
					return $tag;
				}

				$excluded = array( 'jquery', 'jquery-core', 'jquery-migrate', 'wp-polyfill' );
				if ( in_array( $handle, $excluded, true ) ) {
					return $tag;
				}

				if ( false !== strpos( $tag, ' defer' ) || false !== strpos( $tag, ' async' ) || false !== strpos( $tag, 'data-no-defer' ) ) {
					return $tag;
				}

				return preg_replace( '/<script\s+/i', '<script defer ', $tag, 1 ) ?: $tag;
			},
			20,
			3
		);
	}

	/**
	 * Reduce Heartbeat polling interval on admin screens.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_heartbeat_usage(): void {
		if ( ! get_option( 'thisismyurl_shadow_optimize_heartbeat', false ) ) {
			return;
		}

		add_filter(
			'heartbeat_settings',
			static function ( array $settings ): array {
				$settings['interval'] = 60;
				return $settings;
			}
		);
	}

	/**
	 * Remove emoji assets from WordPress admin pages.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_emoji_in_admin(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_emoji_admin', false ) ) {
			return;
		}

		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
	}

	/**
	 * Remove default WordPress dashboard RSS widgets.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_dashboard_rss_widgets(): void {
		if ( ! get_option( 'thisismyurl_shadow_remove_dashboard_rss_widgets', false ) ) {
			return;
		}

		add_action(
			'wp_dashboard_setup',
			static function (): void {
				remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
				remove_meta_box( 'dashboard_secondary', 'dashboard', 'side' );
			},
			20
		);
	}

	/**
	 * Emit baseline security headers via send_headers.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_security_headers(): void {
		if ( ! get_option( 'thisismyurl_shadow_send_security_headers', false ) ) {
			return;
		}

		add_action(
			'send_headers',
			static function (): void {
				if ( headers_sent() ) {
					return;
				}

				header( 'X-Content-Type-Options: nosniff', true );
				header( 'X-Frame-Options: SAMEORIGIN', true );
				header( 'Referrer-Policy: strict-origin-when-cross-origin', true );

				if ( is_ssl() ) {
					header( 'Strict-Transport-Security: max-age=31536000', true );
				}
			},
			20
		);
	}

	/**
	 * Reduce admin auth cookie lifetime for administrator users.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_admin_session_expiration(): void {
		if ( ! get_option( 'thisismyurl_shadow_harden_admin_session_expiry', false ) ) {
			return;
		}

		add_filter(
			'auth_cookie_expiration',
			static function ( int $length, int $user_id, bool $remember ): int {
				unset( $remember );
				if ( $user_id > 0 && user_can( $user_id, 'manage_options' ) ) {
					return DAY_IN_SECONDS;
				}
				return $length;
			},
			10,
			3
		);
	}

	/**
	 * Restore/override big image scaling threshold.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_large_image_threshold(): void {
		$threshold = (int) get_option( 'thisismyurl_shadow_big_image_threshold', 0 );
		if ( $threshold <= 0 ) {
			return;
		}

		add_filter(
			'big_image_size_threshold',
			static function ( $value ) use ( $threshold ) {
				unset( $value );
				return $threshold;
			},
			999
		);
	}

	/**
	 * Override JPEG quality for generated image sizes.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_jpeg_quality(): void {
		$quality = (int) get_option( 'thisismyurl_shadow_jpeg_quality', 0 );
		if ( $quality < 1 || $quality > 100 ) {
			return;
		}

		add_filter(
			'jpeg_quality',
			static function ( int $value, string $context ) use ( $quality ): int {
				unset( $value, $context );
				return $quality;
			},
			10,
			2
		);
	}

	/**
	 * Re-enable native lazy loading when disabled by theme/plugin filters.
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_image_lazy_loading(): void {
		if ( ! get_option( 'thisismyurl_shadow_reenable_lazy_loading', false ) ) {
			return;
		}

		add_filter(
			'wp_lazy_loading_enabled',
			static function ( bool $default, string $tag_name, string $context ): bool {
				unset( $default, $tag_name, $context );
				return true;
			},
			999,
			3
		);
	}

	/**
	 * Exclude internal search result pages from search indexes.
	 *
	 * @since 0.7056
	 */
	private static function maybe_init_search_page_indexing(): void {
		if ( ! get_option( 'thisismyurl_shadow_search_page_noindex_enabled', false ) ) {
			return;
		}

		add_filter(
			'wp_robots',
			static function ( array $robots ): array {
				if ( is_admin() || ! is_search() ) {
					return $robots;
				}

				$robots['noindex'] = true;
				$robots['follow']  = true;

				return $robots;
			},
			999
		);

		add_action(
			'send_headers',
			static function (): void {
				if ( headers_sent() || is_admin() || ! is_search() ) {
					return;
				}

				header( 'X-Robots-Tag: noindex, follow', true );
			},
			20
		);
	}

	/**
	 * Redirect attachment pages for legacy WordPress versions (< 6.4).
	 *
	 * @since 0.6095
	 */
	private static function maybe_init_media_attachment_pages_redirect(): void {
		if ( ! get_option( 'thisismyurl_shadow_redirect_attachment_pages', false ) ) {
			return;
		}

		add_action(
			'template_redirect',
			static function (): void {
				if ( is_admin() || ! is_attachment() ) {
					return;
				}

				$target = home_url( '/' );
				$post   = get_post();
				if ( $post instanceof \WP_Post && $post->post_parent > 0 ) {
					$permalink = get_permalink( $post->post_parent );
					if ( is_string( $permalink ) && '' !== $permalink ) {
						$target = $permalink;
					}
				}

				wp_safe_redirect( $target, 301 );
				exit;
			},
			9
		);
	}

	// =========================================================================
	// Shared helpers
	// =========================================================================

	/**
	 * Retrieve the best-available visitor IP.
	 *
	 * @return string IP address (sanitised).
	 */
	private static function get_visitor_ip(): string {
		$keys = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) );
				// X-Forwarded-For may be a comma-separated list; take the first.
				$ip = trim( explode( ',', $ip )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}

	/**
	 * Build the transient key used to track failed login attempts for an IP.
	 *
	 * @param string $ip Visitor IP.
	 * @return string
	 */
	private static function throttle_key( string $ip ): string {
		return 'thisismyurl_shadow_throttle_' . md5( $ip );
	}

	/**
	 * Build the transient key for an IP's active lockout.
	 *
	 * @param string $ip Visitor IP.
	 * @return string
	 */
	private static function lockout_key( string $ip ): string {
		return 'thisismyurl_shadow_lockout_' . md5( $ip );
	}
}

if ( ! class_exists( 'ThisIsMyURL\\Shadow\\Core\\Treatment_Hooks', false ) ) {
	class_alias( 'Treatment_Hooks', 'ThisIsMyURL\\Shadow\\Core\\Treatment_Hooks' );
}
