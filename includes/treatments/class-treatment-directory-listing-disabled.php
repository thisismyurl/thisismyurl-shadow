<?php
/**
 * Treatment: Disable Directory Listing via .htaccess
 *
 * Appends an "Options -Indexes" directive to the root .htaccess file using
 * WordPress's insert_with_markers() helper so the block is clearly labelled
 * and removable. Prevents web servers from returning a file listing when
 * a directory is requested with no index file.
 *
 * Risk level: high — modifies the root .htaccess file. Backed up implicitly
 * by insert_with_markers which keeps the existing content intact outside
 * the managed block. Undo removes the block cleanly.
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
 * Adds "Options -Indexes" to the root .htaccess to disable directory listing.
 */
class Treatment_Directory_Listing_Disabled extends Treatment_Base {

	/**
	 * @var string
	 */
	protected static $slug = 'directory-listing-disabled';

	/**
	 * Marker label used by insert_with_markers().
	 */
	private const MARKER = 'This Is My URL Shadow Directory Listing';

	/** @return string */
	public static function get_risk_level(): string {
		return 'high';
	}

	/**
	 * Append directory-listing denial rules to the root .htaccess.
	 *
	 * @return array
	 */
	public static function apply() {
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$htaccess = get_home_path() . '.htaccess';
		$rules    = array(
			'# Disable directory browsing - managed by Christopher Ross Shadow',
			'Options -Indexes',
		);

		$result = insert_with_markers( $htaccess, self::MARKER, $rules );

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not write to .htaccess. Check that the file is writable.', 'thisismyurl-shadow' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Directory listing disabled via .htaccess. Visitors will no longer see a file index when browsing directories that have no index file.', 'thisismyurl-shadow' ),
			'details' => array( 'htaccess_path' => $htaccess ),
		);
	}

	/**
	 * Remove the Christopher Ross Shadow directory-listing block from .htaccess.
	 *
	 * @return array
	 */
	public static function undo() {
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		$htaccess = get_home_path() . '.htaccess';
		$result   = insert_with_markers( $htaccess, self::MARKER, array() );

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Could not update .htaccess. Check file permissions.', 'thisismyurl-shadow' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Directory listing protection removed from .htaccess.', 'thisismyurl-shadow' ),
		);
	}
}
