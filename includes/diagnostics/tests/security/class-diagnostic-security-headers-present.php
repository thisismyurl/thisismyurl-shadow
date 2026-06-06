<?php
/**
 * Security Headers Present Diagnostic
 *
 * Checks whether the site sends essential HTTP security headers such as
 * X-Content-Type-Options, X-Frame-Options, and Referrer-Policy.
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
 * Security Headers Present Diagnostic Class
 *
 * Issues a HEAD request to the homepage and audits the response for the
 * presence of recommended HTTP security headers, reporting any that are absent.
 *
 * @since 0.6095
 */
class Diagnostic_Security_Headers_Present extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'security-headers-present';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Security Headers Present';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the site sends essential HTTP security headers such as X-Content-Type-Options, X-Frame-Options, and Content-Security-Policy.';

	/**
	 * Gauge family/category for dashboard placement.
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
	 * Issues a HEAD request to the homepage and inspects the returned headers
	 * for X-Content-Type-Options, X-Frame-Options, Referrer-Policy, and HSTS,
	 * returning a medium finding that lists any headers that are absent.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when required headers are missing, null when healthy.
	 */
	public static function check() {
		$home_url = home_url( '/' );
		$response = wp_remote_head( $home_url, array(
			'timeout'    => 5,
			'user-agent' => 'Christopher Ross Shadow-Diagnostic/1.0',
			'sslverify'  => false,
		) );

		if ( is_wp_error( $response ) ) {
			return null; // Cannot test; skip to avoid false positives.
		}

		$headers      = wp_remote_retrieve_headers( $response );
		$is_https     = ( 'https' === wp_parse_url( $home_url, PHP_URL_SCHEME ) );
		$missing      = array();

		if ( empty( $headers->offsetGet( 'x-content-type-options' ) ) ) {
			$missing[] = 'X-Content-Type-Options';
		}

		if ( empty( $headers->offsetGet( 'x-frame-options' ) ) ) {
			// CSP with frame-ancestors is an acceptable substitute.
			$csp = $headers->offsetGet( 'content-security-policy' );
			if ( ! $csp || false === stripos( $csp, 'frame-ancestors' ) ) {
				$missing[] = 'X-Frame-Options';
			}
		}

		if ( empty( $headers->offsetGet( 'referrer-policy' ) ) ) {
			$missing[] = 'Referrer-Policy';
		}

		if ( $is_https && empty( $headers->offsetGet( 'strict-transport-security' ) ) ) {
			$missing[] = 'Strict-Transport-Security (HSTS)';
		}

		if ( empty( $missing ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: list of missing headers */
				__( 'The following recommended HTTP security headers are missing from the site\'s responses: %s. These headers protect visitors against clickjacking, MIME-type sniffing, and cross-site information leakage. Add them via your server configuration, a CDN, or a security/header plugin.', 'thisismyurl-shadow' ),
				implode( ', ', $missing )
			),
			'severity'     => 'medium',
			'threat_level' => 45,
			'details'      => array(
				'missing_headers' => $missing,
				'checked_url'     => $home_url,
			),
		);
	}
}
