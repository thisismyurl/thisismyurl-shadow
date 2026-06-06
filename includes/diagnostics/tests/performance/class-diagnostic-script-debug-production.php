<?php
/**
 * SCRIPT_DEBUG Disabled in Production Diagnostic
 *
 * When SCRIPT_DEBUG is set to true, WordPress loads full unminified
 * development builds of all core JavaScript and CSS instead of the
 * production-minified versions. This includes a substantially heavier
 * jQuery build and uncompressed admin assets. It is a developer-only
 * constant that has no legitimate purpose on a production site and can
 * double or triple the JavaScript payload served to every visitor.
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
 * Diagnostic_Script_Debug_Production Class
 *
 * Checks that the SCRIPT_DEBUG constant is not set to true. Returns null
 * when the constant is absent or false (the production-safe state).
 *
 * @since 0.6095
 */
class Diagnostic_Script_Debug_Production extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'script-debug-production';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'SCRIPT_DEBUG Disabled in Production';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks that SCRIPT_DEBUG is not set to true. When active on a production site it forces WordPress to serve unminified development builds of all core JavaScript and CSS, inflating page payload for every visitor.';

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
	 * Run the diagnostic check.
	 *
	 * Returns null when SCRIPT_DEBUG is absent or false. Returns a high-
	 * severity performance finding when the constant is explicitly true,
	 * as it forces WordPress to serve development-build assets to all visitors
	 * on every page load.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when SCRIPT_DEBUG is active, null when disabled.
	 */
	public static function check() {
		if ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'SCRIPT_DEBUG is set to true in wp-config.php. This forces WordPress to load full unminified development builds of all core JavaScript and CSS files instead of the production-minified versions. On a live site this inflates page payload and increases load time for every visitor. SCRIPT_DEBUG is a developer tool exclusively for local development — it should never be true on a production site.', 'thisismyurl-shadow' ),
			'severity'     => 'high',
			'threat_level' => 60,
			'details'      => array(
				'script_debug' => true,
				'fix'          => __( 'In wp-config.php, remove the line or change it to: define( \'SCRIPT_DEBUG\', false ); — This constant must only ever be true in a local development environment, never on a live site.', 'thisismyurl-shadow' ),
			),
		);
	}
}
