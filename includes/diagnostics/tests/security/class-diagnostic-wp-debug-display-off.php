<?php
/**
 * WP Debug Display Off Diagnostic
 *
 * Checks whether WP_DEBUG_DISPLAY is enabled along with WP_DEBUG, which
 * would cause PHP errors and sensitive information to leak to site visitors.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Server_Environment_Helper as Server_Env;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Wp_Debug_Display_Off Class
 *
 * Reads the WP_DEBUG and WP_DEBUG_DISPLAY constants via the Server_Env
 * helper and flags configurations where both are enabled in production.
 *
 * @since 0.6095
 */
class Diagnostic_Wp_Debug_Display_Off extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'wp-debug-display-off';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'WP Debug Display Off';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WP_DEBUG_DISPLAY is enabled along with WP_DEBUG, which would cause PHP errors and potentially sensitive information to leak to site visitors.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'security';

	/**
	 * Whether this diagnostic is part of the core trusted set.
	 *
	 * @var bool
	 */
	protected static $is_core = true;

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'high';

	/**
	 * Run the diagnostic check.
	 *
	 * Returns early when WP_DEBUG is off (display cannot leak), then checks
	 * WP_DEBUG_DISPLAY via the Server_Env helper and returns a high-severity
	 * finding when both constants are enabled.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when debug output is visible, null when healthy.
	 */
	public static function check() {
		// If WP_DEBUG itself is off, display can't leak anything.
		if ( ! Server_Env::is_wp_debug() ) {
			return null;
		}

		if ( ! Server_Env::is_wp_debug_display() ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WP_DEBUG is enabled and WP_DEBUG_DISPLAY is set to true (or not explicitly disabled). PHP errors and warnings are being printed directly to the page, visible to all visitors. This leaks server paths, plugin names, and code structure to potential attackers.', 'thisismyurl-shadow' ),
			'severity'     => 'high',
			'threat_level' => 70,
			'details'      => array(
				'wp_debug'         => true,
				'wp_debug_display' => true,
				'fix'              => __( 'Add define( \'WP_DEBUG_DISPLAY\', false ); to wp-config.php while keeping WP_DEBUG if needed.', 'thisismyurl-shadow' ),
			),
		);
	}
}
