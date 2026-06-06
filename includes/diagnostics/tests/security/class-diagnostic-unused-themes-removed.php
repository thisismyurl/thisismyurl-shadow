<?php
/**
 * Unused Themes Removed Diagnostic
 *
 * Checks whether unused themes remain installed on the site, reducing the
 * attack surface from outdated or unmaintained theme files.
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
 * Unused Themes Removed Diagnostic Class
 *
 * Compares the installed theme list against the active stylesheet and template
 * options, flagging sites with more than one unused theme on disk.
 *
 * @since 0.6095
 */
class Diagnostic_Unused_Themes_Removed extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'unused-themes-removed';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Unused Themes Removed';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether unused themes remain installed on the site, reducing the attack surface from outdated or unmaintained theme files.';

	/**
	 * Gauge family/category for dashboard placement.
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
	 * Reads the stylesheet and template options to determine the active theme
	 * and parent theme, then counts remaining installed themes as unused,
	 * allowing one extra fallback theme before flagging.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when unused themes are present, null when healthy.
	 */
	public static function check() {
		$all_themes        = wp_get_themes();
		$active_stylesheet = (string) get_option( 'stylesheet', '' );
		$active_template   = (string) get_option( 'template', '' );

		// Theme slugs in active use (active child + its parent, if any).
		$in_use     = array_filter( array_unique( array( $active_stylesheet, $active_template ) ) );
		$all_slugs  = array_keys( $all_themes );
		$inactive   = array_diff( $all_slugs, $in_use );
		$count      = count( $inactive );

		// Allow one additional theme (WordPress recommends keeping a default theme as fallback).
		if ( $count <= 1 ) {
			return null;
		}

		$names = array();
		foreach ( $inactive as $slug ) {
			$names[] = isset( $all_themes[ $slug ] ) ? $all_themes[ $slug ]->get( 'Name' ) : $slug;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of inactive themes */
				_n(
					'%d unused theme is installed. Inactive themes can contain exploitable vulnerabilities even when not active. Remove all themes you are not using.',
					'%d unused themes are installed. Inactive themes can contain exploitable vulnerabilities even when not active. Remove all themes you are not using.',
					$count,
					'thisismyurl-shadow'
				),
				$count
			),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array(
				'inactive_count'  => $count,
				'inactive_themes' => $names,
				'active_theme'    => $active_stylesheet,
			),
		);
	}
}
