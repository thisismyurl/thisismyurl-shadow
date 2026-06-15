<?php
/**
 * Active Plugin Count Reasonable Diagnostic
 *
 * Checks that the number of active plugins is not excessive. Too many plugins
 * slow the site, create maintenance burden, and increase the attack surface.
 *
 * @package    Shadow by Christopher Ross
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
 * Diagnostic_Active_Plugin_Count_Reasonable Class
 *
 * @since 0.6095
 */
class Diagnostic_Active_Plugin_Count_Reasonable extends Diagnostic_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'active-plugin-count-reasonable';

	/**
	 * @var string
	 */
	protected static $title = 'Active Plugin Count Reasonable';

	/**
	 * @var string
	 */
	protected static $description = 'Checks that the number of active plugins is not excessive. Too many plugins slow the site, create maintenance burden, and increase the security attack surface.';

	/**
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Counts active plugins and flags when the number exceeds recommended limits.
	 * More than 20 active plugins is a low-severity warning; more than 30 is medium.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when plugin count is excessive, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$count          = count( $active_plugins );

		// Fewer than 20 active plugins is considered healthy.
		if ( $count <= 20 ) {
			return null;
		}

		$severity     = $count > 30 ? 'medium' : 'low';
		$threat_level = $count > 30 ? 35 : 20;

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of active plugins */
				_n(
					'You have %d active plugin. Review your plugins and deactivate any that are unused or redundant.',
					'You have %d active plugins. Review your plugins and deactivate any that are unused or redundant.',
					$count,
					'thisismyurl-shadow'
				),
				$count
			),
			'severity'     => $severity,
			'threat_level' => $threat_level,
			'details'      => array(
				'active_plugin_count' => $count,
				'fix'                 => __( 'Go to Plugins → Installed Plugins and deactivate or delete plugins that are not actively used on this site.', 'thisismyurl-shadow' ),
			),
		);
	}
}
