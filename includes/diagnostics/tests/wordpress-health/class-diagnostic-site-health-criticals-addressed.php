<?php
/**
 * Site Health Criticals Addressed Diagnostic
 *
 * Checks the WordPress Site Health screen results for unresolved critical
 * issues. Reads the cached site-health result option and flags when WordPress
 * core has categorised one or more items as critical.
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
 * Diagnostic_Site_Health_Criticals_Addressed Class
 *
 * Reads the health-check-site-status-result option populated by the WordPress
 * Site Health screen. Returns null when the cache is empty or when no critical
 * issues are present. Returns a high-severity finding with critical and
 * recommended counts when criticals exist.
 *
 * @since 0.6095
 */
class Diagnostic_Site_Health_Criticals_Addressed extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'site-health-criticals-addressed';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Site Health Criticals Addressed';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks the WordPress Site Health screen results for unresolved critical issues that require immediate attention.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'wordpress-health';

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
	 * Reads the health-check-site-status-result option (populated by the Site
	 * Health screen). Returns null when the cache is absent or empty, and when
	 * the critical count is zero. Returns a high-severity finding with the
	 * critical and recommended counts and a link to Tools > Site Health when
	 * criticals exist.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when Site Health criticals exist, null when healthy.
	 */
	public static function check() {
		// WordPress caches Site Health results in this option each time the
		// Site Health screen is visited. If the cache is empty the user hasn't
		// run Site Health yet — skip rather than guess.
		$cached = get_option( 'health-check-site-status-result', null );
		if ( empty( $cached ) ) {
			return null;
		}

		$data = is_string( $cached ) ? json_decode( $cached, true ) : (array) $cached;
		if ( empty( $data ) || ! is_array( $data ) ) {
			return null;
		}

		$critical_count    = isset( $data['critical'] ) ? (int) $data['critical'] : 0;
		$recommended_count = isset( $data['recommended'] ) ? (int) $data['recommended'] : 0;

		if ( $critical_count === 0 ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: 1: number of critical issues, 2: number of recommended improvements */
				_n(
					'WordPress Site Health reports %1$d critical issue (%2$d improvement recommended). Critical items represent problems that WordPress core has identified as actively affecting the site\'s security, reliability, or performance.',
					'WordPress Site Health reports %1$d critical issues (%2$d improvements recommended). Critical items represent problems that WordPress core has identified as actively affecting the site\'s security, reliability, or performance.',
					$critical_count,
					'thisismyurl-shadow'
				),
				$critical_count,
				$recommended_count
			),
			'severity'     => 'high',
			'threat_level' => min( 90, 40 + ( $critical_count * 10 ) ),
			'details'      => array(
				'critical_count'    => $critical_count,
				'recommended_count' => $recommended_count,
				'fix'               => __( 'Visit Tools > Site Health to review and resolve each critical item.', 'thisismyurl-shadow' ),
			),
		);
	}
}
