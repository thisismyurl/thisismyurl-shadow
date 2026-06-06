<?php
/**
 * Required PHP Extensions Loaded Diagnostic
 *
 * WordPress and its plugin ecosystem depend on several PHP extensions that
 * are not guaranteed on all hosting environments. Missing extensions produce
 * silent, difficult-to-diagnose failures: image uploads silently fail when
 * both GD and Imagick are absent, international content is corrupted when
 * mbstring is missing, and dozens of plugins fail to make outbound API calls
 * without curl. Surfacing missing extensions early prevents hours of
 * debugging after a site goes live.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Php_Extensions_Required Class
 *
 * Checks for PHP extensions required by WordPress core and its ecosystem.
 * Groups missing extensions by severity: critical (core cannot function)
 * and important (common plugin functionality breaks).
 *
 * @since 0.6095
 */
class Diagnostic_Php_Extensions_Required extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'php-extensions-required';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Required PHP Extensions Loaded';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks that PHP extensions required by WordPress and common plugins are loaded, including an image library (GD or Imagick), mbstring, curl, openssl, and json.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'monitoring';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

	/**
	 * Extensions whose absence causes core WordPress failures.
	 * Checked individually — each missing one is flagged separately.
	 *
	 * @var string[]
	 */
	private const CRITICAL = array( 'json', 'mysqli' );

	/**
	 * Extensions whose absence breaks common plugin functionality.
	 *
	 * @var string[]
	 */
	private const IMPORTANT = array( 'mbstring', 'curl', 'openssl', 'xml' );

	/**
	 * Run the diagnostic check.
	 *
	 * Checks that at least one image processing library (GD or Imagick) is
	 * present and that all individually required extensions are loaded.
	 * Absence of a CRITICAL extension yields high severity; absence of an
	 * IMPORTANT extension yields medium severity.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when extensions are missing, null when all present.
	 */
	public static function check() {
		$missing_critical  = array();
		$missing_important = array();

		// An image library is required for media uploads and thumbnail generation.
		if ( ! extension_loaded( 'gd' ) && ! extension_loaded( 'imagick' ) ) {
			$missing_critical[] = 'gd or imagick (image processing)';
		}

		foreach ( self::CRITICAL as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$missing_critical[] = $ext;
			}
		}

		foreach ( self::IMPORTANT as $ext ) {
			if ( ! extension_loaded( $ext ) ) {
				$missing_important[] = $ext;
			}
		}

		if ( empty( $missing_critical ) && empty( $missing_important ) ) {
			return null;
		}

		$all_missing  = array_merge( $missing_critical, $missing_important );
		$severity     = ! empty( $missing_critical ) ? 'high' : 'medium';
		$threat_level = ! empty( $missing_critical ) ? 70 : 45;

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: 1: missing extension count, 2: comma-separated list */
				_n(
					'%1$d required PHP extension is not loaded: %2$s. Missing extensions cause silent failures — image uploads may fail, outbound API calls may break, and international content may be corrupted.',
					'%1$d required PHP extensions are not loaded: %2$s. Missing extensions cause silent failures — image uploads may fail, outbound API calls may break, and international content may be corrupted.',
					count( $all_missing ),
					'thisismyurl-shadow'
				),
				count( $all_missing ),
				implode( ', ', $all_missing )
			),
			'severity'     => $severity,
			'threat_level' => $threat_level,
			'details'      => array(
				'missing_critical'  => $missing_critical,
				'missing_important' => $missing_important,
				'fix'               => __( 'Contact your hosting provider and ask them to enable the missing PHP extensions. Most managed WordPress hosts include all required extensions. On a self-managed server, install via your package manager — for example on Debian/Ubuntu: sudo apt install php-gd php-mbstring php-curl php-xml php-json. A PHP version upgrade may also be required.', 'thisismyurl-shadow' ),
			),
		);
	}
}
