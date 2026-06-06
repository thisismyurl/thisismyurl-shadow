<?php
/**
 * WordPress Generator Meta Tag Diagnostic
 *
 * Checks whether WordPress is still outputting a <meta name="generator"> tag
 * in every page's <head>, which advertises the exact WordPress version to
 * anyone scanning for vulnerable installations.
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
 * Diagnostic_Wp_Generator_Tag Class
 *
 * @since 0.6095
 */
class Diagnostic_Wp_Generator_Tag extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'wp-generator-tag';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'WordPress Generator Meta Tag';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is outputting a <meta name="generator"> tag that publicly advertises the exact WordPress version number to anyone viewing the page source.';

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
	 * Checks whether Perfmatters, WP Rocket, or any other mechanism has
	 * already removed the wp_generator hook from wp_head. If the hook is
	 * still registered at its default priority, the tag is being output.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when generator tag is still output, null when healthy.
	 */
	public static function check() {
		// Perfmatters — remove_version also strips the generator meta tag.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['extras']['remove_version'] ) ) {
			return null;
		}

		// WP Rocket removes the generator tag when version fingerprinting is disabled.
		$rocket = get_option( 'wp_rocket_settings', array() );
		if ( is_array( $rocket ) && ! empty( $rocket['remove_version'] ) ) {
			return null;
		}

		// Generic check: if the wp_generator action has been deregistered from
		// wp_head by any means, the tag is not being output.
		if ( ! has_action( 'wp_head', 'wp_generator' ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress is outputting a <meta name="generator" content="WordPress X.X.X"> tag in every page\'s <head>. This publicly advertises the exact version of WordPress you are running. Automated vulnerability scanners use this information to target sites running versions with known security flaws. Remove it with remove_action(\'wp_head\', \'wp_generator\') or via a performance plugin.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'fix' => __( 'Add to functions.php: remove_action(\'wp_head\', \'wp_generator\'); — or use Perfmatters / WP Rocket\'s "Remove Version Numbers" option.', 'thisismyurl-shadow' ),
			),
		);
	}
}
