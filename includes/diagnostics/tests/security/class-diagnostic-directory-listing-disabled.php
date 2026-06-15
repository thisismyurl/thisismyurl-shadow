<?php
/**
 * Directory Listing Disabled Diagnostic
 *
 * Verifies that web server directory listing is disabled for the uploads
 * folder to prevent attackers from enumerating uploaded files.
 *
 * @package    Shadow by Christopher Ross
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
 * Directory Listing Disabled Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Directory_Listing_Disabled extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'directory-listing-disabled';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Directory Listing Disabled';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether web server directory listing is disabled to prevent attackers from browsing the site\'s file structure when no index file is present.';

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
	 * Makes an HTTP request to the uploads directory URL and checks whether
	 * the response shows a file/directory listing or a forbidden/redirect.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when directory listing is exposed, null when healthy.
	 */
	public static function check() {
		// Test by requesting a known directory that should not show a file listing.
		$uploads      = wp_upload_dir();
		$test_url     = trailingslashit( $uploads['baseurl'] );

		$result = Diagnostic_Request_Helper::get_result(
			$test_url,
			array(
				'timeout'             => 7,
				'redirection'         => 2,
				'user-agent'          => 'Shadow by Christopher Ross-Diagnostic/1.0',
				'reject_unsafe_urls'  => false,
			)
		);

		if ( empty( $result['success'] ) || empty( $result['response'] ) || ! is_array( $result['response'] ) ) {
			return null; // Cannot determine — skip.
		}

		$response = $result['response'];
		$code = (int) wp_remote_retrieve_response_code( $response );

		// 403 Forbidden or 404 Not Found — directory listing is blocked.
		if ( 403 === $code || 404 === $code ) {
			return null;
		}

		// 200 — check if the response body looks like a directory index.
		if ( 200 !== $code ) {
			return null; // Unexpected status; cannot draw a conclusion safely.
		}

		$body = wp_remote_retrieve_body( $response );
		$is_listing = (
			false !== stripos( $body, 'Index of' ) ||
			false !== stripos( $body, '<title>Index of' ) ||
			false !== stripos( $body, 'Directory listing for' )
		);

		if ( ! $is_listing ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Your web server is returning a directory listing for the uploads folder. Visitors (and attackers) can browse all uploaded files and learn about your site structure. Disable directory indexes in your .htaccess or server configuration.', 'thisismyurl-shadow' ),
			'severity'     => 'high',
			'threat_level' => 65,
			'details'      => array(
				'tested_url'  => $test_url,
				'http_status' => $code,
			),
		);
	}
}
