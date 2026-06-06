<?php
/**
 * Theme and Plugin Editor Disabled Diagnostic
 *
 * Checks whether the built-in WordPress theme and plugin file editors have
 * been disabled via DISALLOW_FILE_EDIT to prevent code injection attacks.
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
 * Theme and Plugin Editor Disabled Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_File_Editor_Disabled extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'file-editor-disabled';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Theme and Plugin Editor Disabled';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress built-in theme and plugin file editor is disabled to prevent code modification directly from the admin panel.';

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
	 * Uses Server_Env::is_file_edit_disabled() to verify that the DISALLOW_FILE_EDIT
	 * constant is defined and set to true.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when the file editor is not disabled, null when healthy.
	 */
	public static function check() {
		if ( Server_Env::is_file_edit_disabled() ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'The built-in theme and plugin file editor is enabled in the WordPress admin. If an attacker compromises an admin account, they can execute arbitrary code directly through this editor without needing server access. Add DISALLOW_FILE_EDIT in wp-config.php to disable it.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 55,
			'details'      => array(
				'disallow_file_edit' => false,
				'fix'                => __( 'Add define( \'DISALLOW_FILE_EDIT\', true ); to wp-config.php.', 'thisismyurl-shadow' ),
			),
		);
	}
}
