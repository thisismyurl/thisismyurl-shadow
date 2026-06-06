<?php
/**
 * Fatal Error Handler Enabled Diagnostic
 *
 * Since WordPress 5.2, the built-in fatal error handler catches unhandled
 * PHP exceptions and fatal errors before they produce a blank white screen.
 * It emails a recovery link to the admin, activates a safe recovery mode,
 * and prevents the site from being fully inaccessible. Setting
 * WP_DISABLE_FATAL_ERROR_HANDLER to true removes this safety net entirely,
 * reverting to the pre-5.2 behaviour: a blank screen with no notification
 * and no recovery path short of FTP or server access.
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
 * Diagnostic_Fatal_Error_Handler_Enabled Class
 *
 * Flags when WP_DISABLE_FATAL_ERROR_HANDLER is explicitly set to true,
 * disabling the WordPress recovery mode introduced in WordPress 5.2.
 *
 * @since 0.6095
 */
class Diagnostic_Fatal_Error_Handler_Enabled extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'fatal-error-handler-enabled';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Fatal Error Handler Enabled';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks that WP_DISABLE_FATAL_ERROR_HANDLER is not set to true. When disabled, fatal PHP errors produce a blank white screen with no admin email alert and no recovery mode — making the site unrecoverable without server access.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'monitoring';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

	/**
	 * Run the diagnostic check.
	 *
	 * Returns null (healthy) when the constant is absent or false, meaning
	 * WordPress recovery mode is active. Returns a high-severity finding when
	 * the constant is true, as fatal plugin or theme errors will produce a
	 * silent white screen with no notification path to the site owner.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when error handler is disabled, null when active.
	 */
	public static function check() {
		if ( ! defined( 'WP_DISABLE_FATAL_ERROR_HANDLER' ) || ! WP_DISABLE_FATAL_ERROR_HANDLER ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WP_DISABLE_FATAL_ERROR_HANDLER is set to true in wp-config.php. This disables the WordPress recovery mode introduced in 5.2. When a plugin or theme causes a fatal PHP error the site will show a blank white screen — no admin email, no recovery link, and no safe mode. Restoring the site requires FTP or server shell access. Remove this constant to restore the safety net.', 'thisismyurl-shadow' ),
			'severity'     => 'high',
			'threat_level' => 65,
			'details'      => array(
				'wp_disable_fatal_error_handler' => true,
				'fix'                            => __( 'Remove the line define( \'WP_DISABLE_FATAL_ERROR_HANDLER\', true ) from wp-config.php. WordPress recovery mode is safe for production use — it only activates when a fatal error occurs and has no effect on normal site operation.', 'thisismyurl-shadow' ),
			),
		);
	}
}
