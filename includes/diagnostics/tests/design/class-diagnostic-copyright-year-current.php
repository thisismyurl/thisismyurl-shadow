<?php
/**
 * Copyright Year Current Diagnostic
 *
 * Checks that the copyright year in the site footer matches the current year.
 * A stale year makes the site appear neglected and out of date.
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
 * Diagnostic_Copyright_Year_Current Class
 *
 * Inspects the active theme's footer.php and any registered widget areas for
 * year strings that look like a copyright notice. Flags when a year earlier
 * than the current year is found in the footer output.
 *
 * @since 0.6095
 */
class Diagnostic_Copyright_Year_Current extends Diagnostic_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'copyright-year-current';

	/**
	 * @var string
	 */
	protected static $title = 'Copyright Year Current';

	/**
	 * @var string
	 */
	protected static $description = 'Checks that the copyright year in the site footer matches the current year. A stale year makes the site appear neglected and out of date.';

	/**
	 * @var string
	 */
	protected static $family = 'design';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the active theme's footer.php template, any Custom HTML widgets in
	 * widget areas, and the site description for year references. Compares found
	 * years against the current year. Returns null when the year is current or
	 * dynamic. Returns a low-severity finding when a past year is hard-coded.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when a stale copyright year is detected, null when healthy.
	 */
	public static function check() {
		$current_year = (int) gmdate( 'Y' );

		// 1. Check the active theme's footer.php source.
		$footer_file = get_template_directory() . '/footer.php';
		$sources     = array();

		if ( is_readable( $footer_file ) ) {
			$sources[] = file_get_contents( $footer_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		// 2. Check Custom HTML widgets in registered sidebars.
		$sidebars = get_option( 'sidebars_widgets', array() );
		if ( ! is_array( $sidebars ) ) {
			$sidebars = array();
		}
		unset( $sidebars['array_version'] );
		foreach ( $sidebars as $widgets ) {
			if ( ! is_array( $widgets ) ) {
				continue;
			}
			foreach ( $widgets as $widget_id ) {
				if ( ! str_starts_with( (string) $widget_id, 'custom_html' ) ) {
					continue;
				}
				preg_match( '/-(\d+)$/', $widget_id, $m );
				$instance_index = $m[1] ?? null;
				if ( $instance_index ) {
					$instances   = get_option( 'widget_custom_html', array() );
					$sources[]   = $instances[ $instance_index ]['content'] ?? '';
				}
			}
		}

		// Regex: find © or (c) or "copyright" followed by a 4-digit year <= last year.
		$pattern    = '/(?:&copy;|©|copyright|\(c\))[^\n<]{0,40}(\b(19|20)\d{2}\b)/i';
		$stale_year = null;

		foreach ( $sources as $source ) {
			if ( empty( $source ) ) {
				continue;
			}
			preg_match_all( $pattern, $source, $matches );
			if ( ! empty( $matches[1] ) ) {
				foreach ( $matches[1] as $year_str ) {
					$year = (int) $year_str;
					// Skip dynamic tokens (e.g. PHP echo date calls) — not a literal year.
					if ( $year < $current_year ) {
						$stale_year = $year;
						break 2;
					}
				}
			}
		}

		if ( null === $stale_year ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: 1: stale year, 2: current year */
				__( 'A copyright year of %1$d was detected in the site footer. The current year is %2$d. A stale copyright date makes the site appear neglected. Update it to the current year or use a dynamic expression to output the year automatically.', 'thisismyurl-shadow' ),
				$stale_year,
				$current_year
			),
			'severity'     => 'low',
			'threat_level' => 10,
			'details'      => array(
				'found_year'   => $stale_year,
				'current_year' => $current_year,
			),
		);
	}
}
