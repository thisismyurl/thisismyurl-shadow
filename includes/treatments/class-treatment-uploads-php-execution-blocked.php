<?php
/**
 * Treatment: Block PHP Execution in Uploads Directory
 *
 * Writes a .htaccess file into wp-content/uploads/ containing rules that
 * deny execution of PHP files. This prevents a class of attack where an
 * attacker uploads a PHP file disguised as an image and then requests it
 * directly to execute arbitrary code.
 *
 * Risk level: high — writes a file to the uploads directory. The file is
 * removed by undo(). Does not affect image or media file delivery.
 *
 * Undo: removes the Christopher Ross Shadow block from the uploads .htaccess file using
 * WordPress's insert_with_markers() with an empty rules array.
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes PHP-denial rules to the uploads directory .htaccess file.
 */
class Treatment_Uploads_Php_Execution_Blocked extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'uploads-php-execution-blocked';

	/**
	 * Marker label used by insert_with_markers().
	 */
	private const MARKER = 'This Is My URL Shadow PHP Execution Block';

	/** @return string */
	public static function get_risk_level(): string {
		return 'high';
	}

	/**
	 * Write PHP-denial rules to the uploads .htaccess.
	 *
	 * @return array
	 */
	public static function apply() {
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$upload_dir = wp_upload_dir();
		$htaccess   = trailingslashit( $upload_dir['basedir'] ) . '.htaccess';

		$rules = array(
			'# Block PHP execution in uploads - managed by Christopher Ross Shadow',
			'<FilesMatch "\.php$">',
			'  Order Deny,Allow',
			'  Deny from all',
			'</FilesMatch>',
			'# For Apache 2.4+',
			'<FilesMatch "\.php$">',
			'  Require all denied',
			'</FilesMatch>',
		);

		$result = insert_with_markers( $htaccess, self::MARKER, $rules );

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not write to the uploads .htaccess file. Check that the uploads directory is writable.', 'thisismyurl-shadow' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'PHP execution blocked in the uploads directory via .htaccess. Image and media file delivery is unaffected.', 'thisismyurl-shadow' ),
			'details' => array( 'htaccess_path' => $htaccess ),
		);
	}

	/**
	 * Remove the Christopher Ross Shadow PHP-denial block from the uploads .htaccess.
	 *
	 * @return array
	 */
	public static function undo() {
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$upload_dir = wp_upload_dir();
		$htaccess   = trailingslashit( $upload_dir['basedir'] ) . '.htaccess';

		$result = insert_with_markers( $htaccess, self::MARKER, array() );

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not update the uploads .htaccess file. Check file permissions.', 'thisismyurl-shadow' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'PHP execution block removed from the uploads directory .htaccess.', 'thisismyurl-shadow' ),
		);
	}
}
