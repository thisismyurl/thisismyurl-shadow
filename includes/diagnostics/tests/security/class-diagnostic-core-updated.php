<?php
/**
 * WordPress Core Updated Diagnostic
 *
 * Checks whether a WordPress core update is available and flags sites running
 * an outdated version that may contain known security vulnerabilities.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_WP_Settings_Helper as WP_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Core_Updated Class
 *
 * @since 0.6095
 */
class Diagnostic_Core_Updated extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'core-updated';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'WordPress Core Updated';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a WordPress core update is available, as running an outdated version may expose the site to known security vulnerabilities.';

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
	 * Calls WP_Settings::get_available_core_update() to check for a pending
	 * WordPress core update and returns a finding when one is available.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when an update is available, null when healthy.
	 */
	public static function check() {
		$update = WP_Settings::get_available_core_update();
		if ( null === $update ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: 1: current WP version, 2: available version */
				__( 'WordPress core is out of date. Your site is running version %1$s but version %2$s is available. Outdated core files are one of the leading causes of site compromises.', 'thisismyurl-shadow' ),
				esc_html( $update['current'] ),
				esc_html( $update['available'] )
			),
			'severity'     => 'high',
			'threat_level' => 80,
			'details'      => array(
				'current'   => $update['current'],
				'available' => $update['available'],
			),
		);
	}
}
