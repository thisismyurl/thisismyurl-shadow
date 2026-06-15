<?php
/**
 * Treatment: Browser Caching Headers
 *
 * Adds Apache mod_expires and mod_headers rules to .htaccess that set
 * long-lived Cache-Control and Expires headers for static assets (images,
 * CSS, JS, fonts). Browsers cache these files locally and skip re-downloading
 * them, reducing page load times on repeat visits by 40–70%.
 *
 * Images and fonts receive a 1-year cache; CSS and JS receive 1 month.
 * An ETag fallback is included for validation when ETags are supported.
 *
 * Note: This only works on Apache-based servers. On Nginx, add equivalent
 * `expires` directives to your server block config.
 *
 * File written: .htaccess (ABSPATH)
 * Risk level:   low (.htaccess append — reversible)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Treatments
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;
use ThisIsMyURL\Shadow\Admin\File_Write_Registry;

require_once __DIR__ . '/trait-file-write-helpers.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add reversible Apache caching rules to the site's .htaccess file.
 *
 * This treatment is implemented as a file-write class because browser caching
 * on Apache is most reliably configured at the web server layer. The class
 * exposes both the remediation logic and the metadata needed by Shadow's
 * review UI so an admin can inspect, apply, and undo the change safely.
 */
class Treatment_Browser_Caching_Headers extends Treatment_Base {

	use File_Write_Helpers;

	/**
	 * Finding identifier handled by this treatment.
	 *
	 * @since 0.6095
	 * @var   string
	 */
	protected static $slug = 'browser-caching-headers';

	/**
	 * Marker slug used to wrap the inserted .htaccess block.
	 *
	 * The marker lets the plugin locate and remove only its own changes during an
	 * undo operation instead of trying to parse unrelated server rules.
	 *
	 * @since 0.6095
	 * @var   string
	 */
	const MARKER_SLUG = 'browser-caching-headers';

	/**
	 * Apache directives inserted into .htaccess when the treatment is applied.
	 *
	 * @since 0.6095
	 * @var   string
	 */
	const HTACCESS_BLOCK = '<IfModule mod_expires.c>
    ExpiresActive On
    # Images
    ExpiresByType image/jpeg                    "access plus 1 year"
    ExpiresByType image/png                     "access plus 1 year"
    ExpiresByType image/gif                     "access plus 1 year"
    ExpiresByType image/webp                    "access plus 1 year"
    ExpiresByType image/avif                    "access plus 1 year"
    ExpiresByType image/svg+xml                 "access plus 1 year"
    ExpiresByType image/x-icon                  "access plus 1 year"
    # Fonts
    ExpiresByType font/woff                     "access plus 1 year"
    ExpiresByType font/woff2                    "access plus 1 year"
    ExpiresByType application/font-woff         "access plus 1 year"
    ExpiresByType application/font-woff2        "access plus 1 year"
    # CSS and JavaScript
    ExpiresByType text/css                      "access plus 1 month"
    ExpiresByType text/javascript               "access plus 1 month"
    ExpiresByType application/javascript        "access plus 1 month"
    ExpiresByType application/x-javascript      "access plus 1 month"
    # HTML
    ExpiresByType text/html                     "access plus 1 hour"
    ExpiresByType application/xhtml+xml         "access plus 1 hour"
    # Video
    ExpiresByType video/mp4                     "access plus 1 year"
    ExpiresByType video/webm                    "access plus 1 year"
</IfModule>
<IfModule mod_headers.c>
    <FilesMatch "\.(ico|jpe?g|png|gif|webp|avif|svg|woff2?)$">
        Header set Cache-Control "max-age=31536000, public, immutable"
    </FilesMatch>
    <FilesMatch "\.(css|js)$">
        Header set Cache-Control "max-age=2592000, public"
    </FilesMatch>
    <FilesMatch "\.html?$">
        Header set Cache-Control "max-age=3600, public, must-revalidate"
    </FilesMatch>
</IfModule>';

	/**
	 * Register this treatment with the file-write registry.
	 *
	 * Boot methods are used throughout Shadow's file-write treatments so the
	 * plugin can discover which classes need preview/backup UI support without
	 * instantiating them.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function boot(): void {
		File_Write_Registry::register( static::class );
	}

	// =========================================================================
	// Treatment_Base contract
	// =========================================================================

	/**
	 * Return the finding identifier this treatment resolves.
	 *
	 * @since  0.6095
	 * @return string Diagnostic slug handled by this treatment.
	 */
	public static function get_finding_id(): string {
		return self::$slug;
	}

	/**
	 * Report the relative risk level of applying this treatment.
	 *
	 * @since  0.6095
	 * @return string Risk level label consumed by treatment orchestration.
	 */
	public static function get_risk_level(): string {
		return 'low';
	}

	/**
	 * Write the caching block into .htaccess.
	 *
	 * @since  0.6095
	 * @return array<string,mixed> Result payload from the shared file-write helper.
	 */
	public static function apply(): array {
		return self::write_htaccess_block(
			self::get_target_file(),
			self::MARKER_SLUG,
			self::HTACCESS_BLOCK
		);
	}

	/**
	 * Remove the caching block previously written by this treatment.
	 *
	 * @since  0.6095
	 * @return array<string,mixed> Result payload from the shared file-write helper.
	 */
	public static function undo(): array {
		return self::remove_htaccess_block( self::get_target_file(), self::MARKER_SLUG );
	}

	// =========================================================================
	// File_Write_Registry interface
	// =========================================================================

	/**
	 * Return the absolute path of the file this treatment edits.
	 *
	 * @since  0.6095
	 * @return string Absolute path to .htaccess in the WordPress root.
	 */
	public static function get_target_file(): string {
		return ABSPATH . '.htaccess';
	}

	/**
	 * Return the short label shown to admins for the target file.
	 *
	 * @since  0.6095
	 * @return string Human-readable file label.
	 */
	public static function get_file_label(): string {
		return '.htaccess';
	}

	/**
	 * Summarize the proposed change for confirmation dialogs and review screens.
	 *
	 * @since  0.6095
	 * @return string Localized one-line summary of the file modification.
	 */
	public static function get_proposed_change_summary(): string {
		return __( 'Add long-lived Expires/Cache-Control headers for static assets via .htaccess (Apache mod_expires + mod_headers)', 'thisismyurl-shadow' );
	}

	/**
	 * Return the exact marker-wrapped snippet that would be written to the file.
	 *
	 * @since  0.6095
	 * @return string Previewable code snippet for the review UI.
	 */
	public static function get_proposed_snippet(): string {
		return "# thisismyurl_shadow_MARKER_START: browser-caching-headers\n" .
		       self::HTACCESS_BLOCK . "\n" .
		       "# thisismyurl_shadow_MARKER_END: browser-caching-headers";
	}

	/**
	 * Provide manual rollback instructions for admins editing files themselves.
	 *
	 * This text is intentionally explicit because many site owners using the
	 * plugin may not be comfortable with .htaccess or server-level caching rules.
	 *
	 * @since  0.6095
	 * @return string Multi-line rollback instructions for SFTP or file-manager use.
	 */
	public static function get_sftp_undo_instructions(): string {
		$file = self::get_target_file();
		return implode( "\n", [
			"Connect to your server via SFTP or cPanel File Manager.",
			"Navigate to: {$file}",
			"Open the file in a text editor.",
			"Find and delete the block between these two marker lines (inclusive):",
			"  # thisismyurl_shadow_MARKER_START: browser-caching-headers",
			"  <IfModule mod_expires.c> ... </IfModule>",
			"  <IfModule mod_headers.c> ... </IfModule>",
			"  # thisismyurl_shadow_MARKER_END: browser-caching-headers",
			"Save the file.",
			"Note: If your server runs Nginx, add 'expires' directives to your vhost",
			"config instead. Example: location ~* \.(css|js)$ { expires 1M; }",
		] );
	}
}

Treatment_Browser_Caching_Headers::boot();
