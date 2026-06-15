<?php
/**
 * Browser Caching Headers Diagnostic
 *
 * Detects missing Cache-Control headers causing browsers to re-download assets unnecessarily.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Request_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Browser Caching Headers Diagnostic Class
 *
 * Validates Cache-Control and Expires headers for browser caching optimization.
 *
 * @since 0.6095
 */
class Diagnostic_Browser_Caching_Headers extends Diagnostic_Base {

	/**
	 * The diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'browser-caching-headers';

	/**
	 * The diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Browser Caching Headers';

	/**
	 * The diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether static assets (CSS and JS files) are served with long-lived Cache-Control or Expires headers. Without these headers, return visitors re-download unchanged files on every page view, increasing load times and server bandwidth costs. A max-age of at least seven days is expected for versioned assets.';

	/**
	 * The family this diagnostic belongs to.
	 *
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
	 * Tests static asset and HTML caching headers.
	 *
	 * @since 0.6095
	 * @return array|null Finding array if issue found, null otherwise.
	 */
	public static function check() {
		$issues         = array();
		$cache_results  = array();
		$test_resources = array();

		global $wp_styles;
		if ( $wp_styles && isset( $wp_styles->queue ) && ! empty( $wp_styles->queue ) ) {
			$first_style = $wp_styles->registered[ $wp_styles->queue[0] ] ?? null;
			if ( $first_style && isset( $first_style->src ) ) {
				$test_resources['css'] = $first_style->src;
			}
		}

		global $wp_scripts;
		if ( $wp_scripts && isset( $wp_scripts->queue ) && ! empty( $wp_scripts->queue ) ) {
			$first_script = $wp_scripts->registered[ $wp_scripts->queue[0] ] ?? null;
			if ( $first_script && isset( $first_script->src ) ) {
				$test_resources['js'] = $first_script->src;
			}
		}

		foreach ( $test_resources as $type => $url ) {
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$response_result = Diagnostic_Request_Helper::head_result( $url, array( 'timeout' => 5 ) );
			if ( empty( $response_result['success'] ) || empty( $response_result['response'] ) || ! is_array( $response_result['response'] ) ) {
				continue;
			}

			$response = $response_result['response'];

			$cache_control = (string) wp_remote_retrieve_header( $response, 'cache-control' );
			$expires       = (string) wp_remote_retrieve_header( $response, 'expires' );

			$max_age = 0;
			if ( preg_match( '/max-age=(\d+)/', $cache_control, $matches ) ) {
				$max_age = (int) $matches[1];
			}

			$has_good_cache = ( $max_age >= 604800 || '' !== $expires );
			$cache_results[ $type ] = array(
				'url'           => $url,
				'cache_control' => $cache_control,
				'expires'       => $expires,
				'max_age'       => $max_age,
				'has_cache'     => $has_good_cache,
			);

			if ( ! $has_good_cache ) {
				$issues[] = sprintf(
					/* translators: %s: resource type */
					__( '%s files lack long cache headers.', 'thisismyurl-shadow' ),
					strtoupper( $type )
				);
			}
		}

		$html_response = Diagnostic_Request_Helper::head_result( home_url( '/' ), array( 'timeout' => 5 ) );
		if ( null !== $html_response ) {
			$html_cache_control = (string) wp_remote_retrieve_header( $html_response, 'cache-control' );
			$cache_results['html'] = array(
				'url'           => home_url( '/' ),
				'cache_control' => $html_cache_control,
			);
		}

		if ( empty( $issues ) ) {
			return null;
		}

		$severity = count( $issues ) > 1 ? 'medium' : 'low';

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: list of issues */
				__( 'Browser caching improvements are available: %s', 'thisismyurl-shadow' ),
				implode( ' ', $issues )
			),
			'severity'     => $severity,
			'threat_level' => 40,
			'meta'         => array(
				'cache_results' => $cache_results,
				'issues_found'  => count( $issues ),
			),
		);
	}
}
