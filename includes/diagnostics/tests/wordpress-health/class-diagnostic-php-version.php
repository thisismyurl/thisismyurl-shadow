<?php
/**
 * PHP Version Diagnostic
 *
 * Checks whether the server is running a supported, actively maintained PHP
 * version with current security patches. Flags PHP 8.1 (EOL Dec 2025) as
 * high severity, PHP 8.0 (EOL Nov 2023) as high, and PHP 7.x or older as
 * critically high.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Server_Environment_Helper as Server_Env;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Php_Version Class
 *
 * Uses the Server_Env helper to read PHP_VERSION and compare it against known
 * EOL thresholds. Returns null for PHP 8.2+, and escalating high-severity
 * findings for 8.1, 8.0, and anything older.
 *
 * @since 0.6095
 */
class Diagnostic_Php_Version extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'php-version';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'PHP Version';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the server is running a supported, actively maintained PHP version with current security patches.';

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
	 * Reads the PHP version via the Server_Env helper. Returns null for PHP 8.2+.
	 * Returns a high-severity finding for PHP 8.1 (EOL Dec 2025), PHP 8.0 (EOL
	 * Nov 2023), and a critically high finding for PHP 7.x or older, each with
	 * the actual version, minimum acceptable version, and recommended version.
	 *
	 * @since  0.6095
	 * @return array|null Finding array for unsupported PHP, null when healthy.
	 */
	public static function check() {
		$version = Server_Env::get_php_version();

		// PHP 8.2+ is actively maintained with security patches.
		if ( Server_Env::is_php_at_least( '8.2.0' ) ) {
			return null;
		}

		// PHP 8.1 reached end-of-life December 2025 — no further security patches.
		if ( Server_Env::is_php_at_least( '8.1.0' ) ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: %s: PHP version string */
					__( 'Your server is running PHP %s, which reached end-of-life in December 2025 and no longer receives security updates. Upgrade to PHP 8.2 or higher to keep your server patched and benefit from significant performance improvements.', 'thisismyurl-shadow' ),
					esc_html( $version )
				),
				'severity'     => 'high',
				'threat_level' => 65,
				'details'      => array(
					'current'     => $version,
					'minimum_ok'  => '8.2',
					'recommended' => '8.3',
					'eol'         => 'December 2025',
				),
			);
		}

		// PHP 8.0 reached end-of-life November 2023.
		if ( Server_Env::is_php_at_least( '8.0.0' ) ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: %s: PHP version string */
					__( 'Your server is running PHP %s, which reached end-of-life in November 2023. This version has known, unpatched vulnerabilities. Upgrade to PHP 8.2 or higher immediately.', 'thisismyurl-shadow' ),
					esc_html( $version )
				),
				'severity'     => 'high',
				'threat_level' => 80,
				'details'      => array(
					'current'     => $version,
					'minimum_ok'  => '8.2',
					'recommended' => '8.3',
					'eol'         => 'November 2023',
				),
			);
		}

		// PHP 7.x or older — critically EOL.
		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: PHP version string */
				__( 'Your server is running PHP %s, which is critically out of date and has not received security patches for years. This poses a severe risk to your site and server. Upgrade to PHP 8.2 or higher as a matter of urgency.', 'thisismyurl-shadow' ),
				esc_html( $version )
			),
			'severity'     => 'high',
			'threat_level' => 95,
			'details'      => array(
				'current'     => $version,
				'minimum_ok'  => '8.2',
				'recommended' => '8.3',
			),
		);
	}
}
