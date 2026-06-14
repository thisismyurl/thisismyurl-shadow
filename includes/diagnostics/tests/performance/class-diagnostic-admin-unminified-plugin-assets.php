<?php
/**
 * Admin Unminified Plugin Assets Diagnostic
 *
 * Scans the captured admin page HTML for stylesheet and script URLs containing
 * explicit "minify=false" query parameters. Several plugins (notably Jetpack)
 * opt out of minification via this parameter, forcing all users to download
 * larger, uncompressed asset payloads on every admin page load.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Admin_Page_HTML_Helper as Admin_HTML;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Admin_Unminified_Plugin_Assets Class
 *
 * @since 0.6095
 */
class Diagnostic_Admin_Unminified_Plugin_Assets extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'admin-unminified-plugin-assets';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Unminified Plugin Assets in Admin';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Scans admin page HTML for stylesheet and script URLs that explicitly include minify=false, forcing all admin users to download larger uncompressed asset files on every page load.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

	/**
	 * Pattern that identifies explicitly unminified asset URLs.
	 *
	 * @var string
	 */
	private const UNMINIFIED_PATTERN = '/[?&]minify=false/i';

	/**
	 * Run the diagnostic check.
	 *
	 * Retrieves the admin page HTML captured by Diagnostic_Admin_Page_HTML_Helper
	 * and searches all <link href> and <script src> attributes for URLs containing
	 * the minify=false query parameter. Returns null if HTML has not been captured
	 * yet — the data will be available on the next diagnostic run.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when unminified assets are detected, null when healthy or HTML not available.
	 */
	public static function check(): ?array {
		$html = Admin_HTML::get_html();

		if ( null === $html ) {
			return null; // HTML not yet captured; skip gracefully.
		}

		// Collect all <link href="..."> and <script src="..."> URLs.
		$unminified = array();

		// Stylesheets.
		if ( preg_match_all( '/<link\b[^>]+\bhref\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $link_matches ) ) {
			foreach ( $link_matches[1] as $url ) {
				if ( preg_match( self::UNMINIFIED_PATTERN, $url ) ) {
					$unminified[] = self::shorten_url( $url );
				}
			}
		}

		// Scripts.
		if ( preg_match_all( '/<script\b[^>]+\bsrc\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $script_matches ) ) {
			foreach ( $script_matches[1] as $url ) {
				if ( preg_match( self::UNMINIFIED_PATTERN, $url ) ) {
					$unminified[] = self::shorten_url( $url );
				}
			}
		}

		$unminified = array_unique( $unminified );
		$count      = count( $unminified );

		if ( 0 === $count ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of unminified assets */
				_n(
					'%d admin asset URL explicitly requests an unminified version using minify=false. This forces every admin user to download a larger, uncompressed file on every page load. Unminified JS and CSS files are typically 20–60%% larger than their minified equivalents.',
					'%d admin asset URLs explicitly request unminified versions using minify=false. This forces every admin user to download larger, uncompressed files on every page load. Unminified JS and CSS files are typically 20–60%% larger than their minified equivalents.',
					$count,
					'thisismyurl-shadow'
				),
				$count
			),
			'severity'     => $count >= 5 ? 'medium' : 'low',
			'threat_level' => min( 45, 15 + ( $count * 5 ) ),
			'details'      => array(
				'unminified_asset_count' => $count,
				'urls'                   => array_slice( $unminified, 0, 10 ),
				'note'                   => __(
					'These plugins explicitly pass minify=false to their own asset URLs. Contact the plugin authors or check their settings to see if minified builds can be enabled. Jetpack, for example, passes minify=false to all its front-end build assets.',
					'thisismyurl-shadow'
				),
			),
		);
	}

	/**
	 * Trim a URL to a path-only form for readable output.
	 *
	 * @since  0.6095
	 * @param  string $url Full URL.
	 * @return string Shortened path.
	 */
	private static function shorten_url( string $url ): string {
		$parsed = wp_parse_url( $url );
		$path   = $parsed['path'] ?? $url;

		// Append just the relevant query params.
		if ( ! empty( $parsed['query'] ) ) {
			$path .= '?' . $parsed['query'];
		}

		return $path;
	}
}
