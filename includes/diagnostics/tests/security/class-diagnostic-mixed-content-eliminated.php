<?php
/**
 * Mixed Content Eliminated Diagnostic
 *
 * Checks whether an HTTPS site is serving any mixed content (HTTP assets),
 * which triggers browser security warnings and degrades trust.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Request_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mixed Content Eliminated Diagnostic Class
 *
 * Detects HTTP asset references on HTTPS sites by checking core URL options
 * and scanning homepage HTML for same-domain http:// src/href attributes.
 *
 * @since 0.6095
 */
class Diagnostic_Mixed_Content_Eliminated extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'mixed-content-eliminated';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Mixed Content Eliminated';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether an HTTPS site is serving any mixed content (HTTP assets), which triggers browser security warnings and degrades trust.';

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
	 * Compares siteurl against home_url for an http:// mismatch, then fetches
	 * the homepage and scans the HTML for same-domain HTTP asset references.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when mixed content is detected, null when healthy.
	 */
	public static function check() {
		// Mixed content only matters on HTTPS sites.
		$home_url = home_url();
		if ( 0 !== strpos( $home_url, 'https://' ) ) {
			return null;
		}

		// Check core WordPress URL settings themselves.
		$siteurl = get_option( 'siteurl', '' );
		if ( 0 === strpos( $siteurl, 'http://' ) ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'The WordPress Site URL (siteurl option) is set to http:// while the home URL uses https://. This mismatch can cause mixed-content errors and redirect loops. Update the siteurl option to use https:// under Settings → General, or directly in the database.', 'thisismyurl-shadow' ),
				'severity'     => 'high',
				'threat_level' => 70,
				'details'      => array( 'siteurl' => $siteurl, 'homeurl' => $home_url ),
			);
		}

		// Scan homepage HTML for asset src/href with http:// pointing to this domain.
		$result = Diagnostic_Request_Helper::get_result( $home_url, array(
			'timeout'    => 7,
			'user-agent' => 'Christopher Ross Shadow-Diagnostic/1.0',
		) );

		if ( empty( $result['success'] ) || empty( $result['response'] ) || ! is_array( $result['response'] ) ) {
			return null;
		}

		$response = $result['response'];
		$body   = wp_remote_retrieve_body( $response );
		$domain = wp_parse_url( $home_url, PHP_URL_HOST );

		// Match src="http://domain or href="http://domain asset references (same-domain http).
		$pattern = '/(?:src|href)\s*=\s*["\']http:\/\/' . preg_quote( $domain, '/' ) . '/i';
		$mixed   = preg_match( $pattern, $body );

		if ( $mixed ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'Mixed content was detected on the homepage: HTTP asset references (src or href) were found on an HTTPS page. Browsers block or warn about mixed content, degrading user trust and security. Run the Better Search Replace plugin to update http:// to https:// in stored content, or force HTTPS site-wide via WordPress settings or a plugin.', 'thisismyurl-shadow' ),
				'severity'     => 'high',
				'threat_level' => 65,
				'details'      => array( 'mixed_content_detected' => true, 'checked_url' => $home_url ),
			);
		}

		return null;
	}
}
