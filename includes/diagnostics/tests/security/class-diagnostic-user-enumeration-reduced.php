<?php
/**
 * User Enumeration Reduced Diagnostic
 *
 * Checks whether WordPress user enumeration via the author query parameter
 * or REST API is restricted to prevent username harvesting by attackers.
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
 * Diagnostic_User_Enumeration_Reduced Class
 *
 * Detects known security plugins via option keys and loaded classes that
 * are expected to restrict user enumeration, flagging sites where none are found.
 *
 * @since 0.6095
 */
class Diagnostic_User_Enumeration_Reduced extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'user-enumeration-reduced';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'User Enumeration Reduced';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress user enumeration via the author query parameter or REST API is restricted to prevent attackers from harvesting valid usernames.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'security';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Checks well-known option keys and loaded classes from popular security
	 * plugins that are expected to restrict user enumeration, returning a
	 * medium-severity finding when none are detected.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when enumeration is unprotected, null when healthy.
	 */
	public static function check() {
		// Check for security plugins known to restrict user enumeration.
		$protection_options = array(
			'aio_wp_security_settings', // All In One WP Security
			'wordfence_entries',         // Wordfence
			'cerber-main',               // WP Cerber Security
			'itsec_core',                // iThemes / Solid Security
			'sucuriscan_plugin_version', // Sucuri Security
		);

		foreach ( $protection_options as $option ) {
			if ( false !== get_option( $option, false ) ) {
				return null;
			}
		}

		// Check for active security classes.
		$security_classes = array(
			'ITSEC_Core',
			'wfConfig',
			'SucuriScanWPHardening',
			'Cerber_Main',
		);

		foreach ( $security_classes as $class ) {
			if ( class_exists( $class, false ) ) {
				return null;
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No security plugin was detected that restricts user enumeration. By default, WordPress exposes usernames through author archive URLs (/?author=1), REST API endpoints (/wp-json/wp/v2/users), and login error messages. Attackers use these to harvest usernames for brute-force attacks.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 45,
			'details'      => array(
				'note' => __( 'Install a security plugin such as Wordfence, WP Cerber, or iThemes Security to block username enumeration via author archives and the REST API.', 'thisismyurl-shadow' ),
			),
		);
	}
}
