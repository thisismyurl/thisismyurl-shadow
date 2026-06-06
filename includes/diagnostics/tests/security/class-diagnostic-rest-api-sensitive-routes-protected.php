<?php
/**
 * REST API Sensitive Routes Protected Diagnostic
 *
 * Tests whether the WordPress REST API users endpoint exposes user account
 * data without authentication, enabling username enumeration attacks.
 *
 * @package ThisIsMyURL\Shadow
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
 * Diagnostic_Rest_Api_Sensitive_Routes_Protected Class
 *
 * @since 0.6095
 */
class Diagnostic_Rest_Api_Sensitive_Routes_Protected extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'rest-api-sensitive-routes-protected';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'REST API Sensitive Routes Protected';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Tests whether the /wp-json/wp/v2/users endpoint is publicly accessible without authentication, which would allow attackers to enumerate valid usernames on the site.';

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
	 * Makes an unauthenticated GET request to the /wp/v2/users REST endpoint.
	 * A HTTP 200 response containing an array of user objects indicates the
	 * endpoint is open and username enumeration is possible.
	 *
	 * @since  0.6095
	 * @return array|null Finding array if issue exists, null if healthy.
	 */
	public static function check() {
		// Check if the users endpoint is accessible without authentication.
		// WordPress 5.7+ requires authentication for the /wp/v2/users index,
		// but some configurations or plugins may loosen this.
		$rest_url = rest_url( 'wp/v2/users' );
		$result = Diagnostic_Request_Helper::get_result( $rest_url, array(
			'timeout'    => 5,
			'user-agent' => 'Christopher Ross Shadow-Diagnostic/1.0',
		) );

		if ( empty( $result['success'] ) || empty( $result['response'] ) || ! is_array( $result['response'] ) ) {
			return null; // Cannot test; skip to avoid false positives.
		}

		$response = $result['response'];
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// A 200 response with an array of users means enumeration is possible.
		if ( 200 === (int) $code && is_array( $data ) && ! empty( $data ) ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'The WordPress REST API users endpoint (/wp-json/wp/v2/users) is publicly accessible and returned user account data. This allows attackers to enumerate valid usernames, which aids brute-force attacks. Restrict this endpoint using a security plugin such as iThemes Security or by filtering rest_endpoints with a permission callback that requires authentication.', 'thisismyurl-shadow' ),
				'severity'     => 'medium',
				'threat_level' => 50,
				'details'      => array(
					'endpoint'      => '/wp/v2/users',
					'http_code'     => $code,
					'user_count'    => count( $data ),
				),
			);
		}

		return null;
	}
}
