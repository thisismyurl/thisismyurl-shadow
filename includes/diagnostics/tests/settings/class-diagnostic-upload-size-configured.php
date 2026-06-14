<?php
/**
 * Upload Size Configured Diagnostic
 *
 * The PHP upload_max_filesize and post_max_size ini directives control the
 * maximum file size WordPress can receive. Default PHP values are often as
 * low as 2 MB — far too small for modern media including hero images, PDFs,
 * short videos, or plugin zip files. There is also a compound constraint:
 * post_max_size governs the entire HTTP request body, so it must be strictly
 * larger than upload_max_filesize or large uploads silently fail mid-transfer
 * before WordPress even receives the data, showing only a generic error.
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
 * Diagnostic_Upload_Size_Configured Class
 *
 * Uses wp_max_upload_size() for the WordPress-effective upload limit and
 * ini_get() to detect mismatched post_max_size constraints. Two independent
 * issues are detected: a limit below the 16 MB minimum, and a post_max_size
 * that is not strictly larger than upload_max_filesize.
 *
 * @since 0.6095
 */
class Diagnostic_Upload_Size_Configured extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'upload-size-configured';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Upload Size Configured';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks that the maximum upload size is at least 16 MB and that post_max_size is strictly larger than upload_max_filesize, preventing silent upload failures for images, PDFs, and media files.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'settings';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

	/**
	 * Minimum recommended upload size in bytes (16 MB).
	 *
	 * @var int
	 */
	private const MIN_UPLOAD_BYTES = 16 * 1024 * 1024;

	/**
	 * Parse a PHP ini shorthand size string into bytes.
	 *
	 * @param string $value PHP ini shorthand (e.g. '8M', '512K', '2G').
	 * @return int Size in bytes.
	 */
	private static function parse_ini_size( string $value ): int {
		$value = trim( $value );
		$last  = strtolower( substr( $value, -1 ) );
		$num   = (int) $value;

		switch ( $last ) {
			case 'g':
				$num *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$num *= 1024 * 1024;
				break;
			case 'k':
				$num *= 1024;
				break;
		}

		return $num;
	}

	/**
	 * Run the diagnostic check.
	 *
	 * Checks (1) that the effective WordPress upload limit is at least
	 * MIN_UPLOAD_BYTES, and (2) that post_max_size > upload_max_filesize.
	 * Both conditions are evaluated independently and reported together
	 * when multiple issues are present.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when upload limits are misconfigured, null when healthy.
	 */
	public static function check() {
		$upload_max_bytes = (int) wp_max_upload_size();
		$upload_max_raw   = (string) ini_get( 'upload_max_filesize' );
		$post_max_raw     = (string) ini_get( 'post_max_size' );
		$upload_bytes     = self::parse_ini_size( $upload_max_raw );
		$post_bytes       = self::parse_ini_size( $post_max_raw );

		$issues = array();

		// Check 1: Effective upload limit is below the recommended minimum.
		if ( $upload_max_bytes < self::MIN_UPLOAD_BYTES ) {
			$issues[] = sprintf(
				/* translators: %s: current upload limit formatted as file size */
				__( 'The maximum upload size is %s — too small for high-resolution images, PDFs, or short video clips. Any file larger than this limit will silently fail to upload.', 'thisismyurl-shadow' ),
				size_format( $upload_max_bytes )
			);
		}

		// Check 2: post_max_size is not strictly larger than upload_max_filesize.
		if ( $post_bytes > 0 && $post_bytes <= $upload_bytes ) {
			$issues[] = sprintf(
				/* translators: 1: post_max_size value, 2: upload_max_filesize value */
				__( 'post_max_size (%1$s) is not larger than upload_max_filesize (%2$s). When a file approaches or exceeds post_max_size, PHP silently truncates the entire request before WordPress receives any data — the user sees a generic error with no actionable message.', 'thisismyurl-shadow' ),
				$post_max_raw,
				$upload_max_raw
			);
		}

		if ( empty( $issues ) ) {
			return null;
		}

		$severity     = count( $issues ) > 1 ? 'high' : 'medium';
		$threat_level = count( $issues ) > 1 ? 55 : 35;

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => implode( ' ', $issues ),
			'severity'     => $severity,
			'threat_level' => $threat_level,
			'details'      => array(
				'upload_max_filesize'   => $upload_max_raw,
				'post_max_size'         => $post_max_raw,
				'effective_upload_size' => size_format( $upload_max_bytes ),
				'fix'                   => __( 'In php.ini set: upload_max_filesize = 64M and post_max_size = 128M. On managed hosting, use the control panel\'s PHP settings editor or PHP Selector. On Apache, these values can also be set in .htaccess with: php_value upload_max_filesize 64M and php_value post_max_size 128M. post_max_size must always be larger than upload_max_filesize.', 'thisismyurl-shadow' ),
			),
		);
	}
}
