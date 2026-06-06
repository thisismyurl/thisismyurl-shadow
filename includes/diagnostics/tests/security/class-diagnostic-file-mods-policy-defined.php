<?php
/**
 * File Modifications Policy Defined Diagnostic
 *
 * Checks whether a file modifications policy is explicitly defined via
 * DISALLOW_FILE_MODS or DISALLOW_FILE_EDIT constants in wp-config.php.
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
 * File Modifications Policy Defined Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_File_Mods_Policy_Defined extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'file-mods-policy-defined';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'File Modifications Policy Defined';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a file modifications policy (DISALLOW_FILE_MODS or DISALLOW_FILE_EDIT) is explicitly defined in wp-config.php to lock down the admin panel.';

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
	 * Checks DISALLOW_FILE_MODS and DISALLOW_FILE_EDIT constants along with
	 * environment-mode indicators to determine whether a policy is in place.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no policy is defined, null when healthy.
	 */
	public static function check() {
		$file_mods_disabled = ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS );
		$file_edit_disabled = ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT );

		// If file mods are fully disabled, file editing is also implicitly disabled.
		if ( $file_mods_disabled || $file_edit_disabled ) {
			return null;
		}

		// Neither constant is set: the theme and plugin code editor is accessible in wp-admin.
		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'The WordPress file editor (Appearance → Theme File Editor and Plugins → Plugin File Editor) is accessible from the admin panel. If an administrator account is compromised, an attacker can use the editor to inject malicious PHP code directly into theme or plugin files. Define DISALLOW_FILE_EDIT or DISALLOW_FILE_MODS in wp-config.php to disable this access vector.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 55,
			'details'      => array(
				'DISALLOW_FILE_EDIT' => defined( 'DISALLOW_FILE_EDIT' ) ? DISALLOW_FILE_EDIT : 'not defined',
				'DISALLOW_FILE_MODS' => defined( 'DISALLOW_FILE_MODS' ) ? DISALLOW_FILE_MODS : 'not defined',
			),
		);
	}
}
