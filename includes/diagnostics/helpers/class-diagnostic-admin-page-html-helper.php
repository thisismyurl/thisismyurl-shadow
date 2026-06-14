<?php
/**
 * Diagnostic Admin Page HTML Helper
 *
 * Captures the full HTML output of a WordPress admin page so that HTML-based
 * diagnostics can inspect injected scripts, styles, and attributes without
 * making a separate HTTP request.
 *
 * How it works
 * ─────────────
 * 1. `register()` is called from the plugin bootstrap once per admin page load.
 * 2. `maybe_start_capture()` fires on `admin_init` (priority 1) — before any
 *    HTML is written — and wraps the entire output buffer with `ob_start()`.
 * 3. `maybe_finish_capture()` fires on `shutdown` (priority PHP_INT_MAX) and
 *    retrieves the buffered HTML, stores it in a transient, then re-echoes it
 *    so the page renders normally.
 * 4. HTML-based diagnostics call `get_html()`, which returns the stored HTML.
 *    If no HTML has been captured yet the method returns null and the diagnostic
 *    skips gracefully — the data will be available on the next diagnostic run.
 * 5. After treatments are applied, `invalidate()` clears the stored HTML so the
 *    next page load refreshes the capture.
 *
 * Scope guards
 * ─────────────
 * - Only fires for admins (`manage_options` capability).
 * - Skips AJAX, REST, cron, and CLI requests.
 * - Limited to a single buffer per request via `$is_buffering`.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Admin_Page_HTML_Helper Class
 *
 * @since 0.6095
 */
class Diagnostic_Admin_Page_HTML_Helper {

	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'thisismyurl_shadow_admin_html_u';

	/**
	 * Transient TTL in seconds (10 minutes).
	 *
	 * @var int
	 */
	const CAPTURE_TTL = 600;

	/**
	 * Whether a buffer is currently open for this request.
	 *
	 * @var bool
	 */
	private static bool $is_buffering = false;

	/**
	 * In-memory copy of the captured HTML for same-request access.
	 *
	 * @var string|null
	 */
	private static ?string $memory_html = null;

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	/**
	 * Register the capture hooks.
	 *
	 * Called from the Shadow by Christopher Ross plugin bootstrap. Safe to call on every request;
	 * the inner guards ensure capturing only happens on qualifying admin pages.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function register(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', array( static::class, 'maybe_start_capture' ), 1 );
		add_action( 'shutdown', array( static::class, 'maybe_finish_capture' ), PHP_INT_MAX );
	}

	// -------------------------------------------------------------------------
	// Capture lifecycle
	// -------------------------------------------------------------------------

	/**
	 * Start the output buffer if conditions are met.
	 *
	 * Fires on `admin_init` at priority 1, before WordPress begins emitting HTML.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function maybe_start_capture(): void {
		// Only capture for users who can review diagnostics.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Skip non-page contexts.
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		if ( self::$is_buffering ) {
			return;
		}

		self::$is_buffering = true;
		ob_start();
	}

	/**
	 * Finish the capture, persist to transient, and re-emit the page.
	 *
	 * Fires on `shutdown` at the highest available priority to ensure the entire
	 * page including footer scripts has been rendered into the buffer.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function maybe_finish_capture(): void {
		if ( ! self::$is_buffering ) {
			return;
		}

		self::$is_buffering = false;

		$html = ob_get_clean();

		// Only persist if we received a meaningful amount of HTML.
		if ( ! is_string( $html ) || strlen( $html ) < 1000 ) {
			if ( is_string( $html ) ) {
				// Re-emit even if we won't store it.
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			return;
		}

		// Persist for the diagnostic runner to read.
		$key                = self::transient_key();
		self::$memory_html  = $html;
		set_transient( $key, $html, self::CAPTURE_TTL );

		// Re-emit so the page renders normally for the user.
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	// -------------------------------------------------------------------------
	// Public API for diagnostics
	// -------------------------------------------------------------------------

	/**
	 * Return the last captured admin page HTML for the current user.
	 *
	 * Returns null when no HTML has been captured yet (first run after install,
	 * or after `invalidate()` was called). Diagnostics should return null when
	 * this method returns null — the data will be available on the next run.
	 *
	 * @since  0.6095
	 * @return string|null
	 */
	public static function get_html(): ?string {
		// Same-request: memory is faster than a transient lookup.
		if ( null !== self::$memory_html ) {
			return self::$memory_html;
		}

		$cached = get_transient( self::transient_key() );

		if ( is_string( $cached ) && strlen( $cached ) > 0 ) {
			self::$memory_html = $cached;
			return $cached;
		}

		return null;
	}

	/**
	 * Invalidate the stored HTML for the current user.
	 *
	 * Call this after any admin-side treatment (e.g. adding a defer attribute,
	 * removing an inline script) so the next admin page load re-captures fresh
	 * HTML and HTML-based diagnostics can verify the treatment took effect.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function invalidate(): void {
		delete_transient( self::transient_key() );
		self::$memory_html = null;
	}

	/**
	 * Force-store HTML directly.
	 *
	 * Intended for the test runner so it can inject synthetic HTML without
	 * needing a live page render (unit-tests and fixtures).
	 *
	 * @since  0.6095
	 * @param  string $html Full page HTML string.
	 * @return void
	 */
	public static function set_html( string $html ): void {
		self::$memory_html = $html;
		set_transient( self::transient_key(), $html, self::CAPTURE_TTL );
	}

	// -------------------------------------------------------------------------
	// HTML parsing utilities used by multiple HTML diagnostics
	// -------------------------------------------------------------------------

	/**
	 * Count `<script src="…">` tags in the given HTML string.
	 *
	 * @since  0.6095
	 * @param  string $html        Raw HTML.
	 * @param  string $attr_filter Optional regex that the tag must match too.
	 * @return int
	 */
	public static function count_script_tags( string $html, string $attr_filter = '' ): int {
		$count = preg_match_all( '/<script\b[^>]+\bsrc\s*=[^>]+>/i', $html, $matches );

		if ( false === $count || 0 === $count ) {
			return 0;
		}

		if ( '' === $attr_filter ) {
			return $count;
		}

		$filtered = 0;
		foreach ( $matches[0] as $tag ) {
			if ( preg_match( $attr_filter, $tag ) ) {
				$filtered++;
			}
		}

		return $filtered;
	}

	/**
	 * Count inline `<script>` blocks (no `src` attribute) in the given HTML.
	 *
	 * @since  0.6095
	 * @param  string $html Raw HTML.
	 * @return int
	 */
	public static function count_inline_scripts( string $html ): int {
		$count = preg_match_all( '/<script(?:\s[^>]*)?>(?!.*?\bsrc\s*=)/si', $html, $matches );

		if ( false === $count ) {
			return 0;
		}

		// Exclude script tags that DO have a src attribute — they are external refs.
		$inline = 0;
		foreach ( $matches[0] as $opening_tag ) {
			if ( ! preg_match( '/\bsrc\s*=/i', $opening_tag ) ) {
				$inline++;
			}
		}

		return $inline;
	}

	/**
	 * Count inline `<style>` blocks in the given HTML.
	 *
	 * @since  0.6095
	 * @param  string $html Raw HTML.
	 * @return int
	 */
	public static function count_inline_styles( string $html ): int {
		$count = preg_match_all( '/<style[\s>]/i', $html, $matches );
		return false === $count ? 0 : $count;
	}

	/**
	 * Count `<script src="…">` tags that lack both `defer` and `async`.
	 *
	 * @since  0.6095
	 * @param  string $html Raw HTML.
	 * @return int
	 */
	public static function count_blocking_scripts( string $html ): int {
		$total = preg_match_all( '/<script\b[^>]+\bsrc\s*=[^>]+>/i', $html, $matches );

		if ( false === $total || 0 === $total ) {
			return 0;
		}

		$blocking = 0;
		foreach ( $matches[0] as $tag ) {
			if ( ! preg_match( '/\b(?:defer|async)\b/i', $tag ) ) {
				$blocking++;
			}
		}

		return $blocking;
	}

	// -------------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------------

	/**
	 * Build a transient key unique to the current logged-in user.
	 *
	 * @since  0.6095
	 * @return string
	 */
	private static function transient_key(): string {
		$user_id = get_current_user_id();
		return self::TRANSIENT_PREFIX . $user_id;
	}
}
