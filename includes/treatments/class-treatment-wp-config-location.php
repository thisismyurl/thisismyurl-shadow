<?php
/**
 * Treatment: wp-config Location
 *
 * Provides guidance for moving wp-config.php one directory above the
 * WordPress root (ABSPATH). WordPress has built-in support for this layout:
 * if wp-config.php is not found in ABSPATH, WordPress looks one level up.
 *
 * Moving wp-config.php above the web root prevents direct browser access to
 * the file even if your server's PHP engine fails (e.g. during an outage),
 * adding an extra layer of credential protection.
 *
 * Christopher Ross Shadow does not move the file automatically because the new directory
 * must already exist and be writable, and the move must be verified
 * immediately before the old file is deleted.
 *
 * Risk level: n/a (guidance only — move manually)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Treatments
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns guidance for moving wp-config.php above the web root.
 */
class Treatment_Wp_Config_Location extends Treatment_Base {

	/** @var string */
	protected static $slug = 'wp-config-location';

	// =========================================================================
	// Treatment_Base contract
	// =========================================================================

	public static function get_finding_id(): string {
		return self::$slug;
	}

	public static function get_risk_level(): string {
		return 'none';
	}

	/**
	 * Return guidance for relocating wp-config.php above the web root.
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function apply(): array {
		$wp_root    = rtrim( ABSPATH, '/\\' );
		$parent_dir = dirname( $wp_root );
		$wp_config  = $wp_root . DIRECTORY_SEPARATOR . 'wp-config.php';
		$new_path   = $parent_dir . DIRECTORY_SEPARATOR . 'wp-config.php';

		return [
			'success' => false,
			'message' => sprintf(
				/* translators: 1: current path, 2: new path, 3: parent directory */
				__(
					"Moving wp-config.php one level above the WordPress root hides it from direct browser access, even if PHP stops working.

Current location: %1\$s
Target location:  %2\$s

PREREQUISITES:
  • WordPress must be installed directly in the web root (not a subdirectory install).
  • %3\$s must be ABOVE the publicly accessible web root (e.g. public_html, htdocs).
  • If WordPress IS the web root, moving wp-config.php here exposes it to browsers — skip this step.

STEP 1 — Connect to your server via SFTP (FileZilla, Cyberduck, etc.) or cPanel File Manager.

STEP 2 — Copy (do NOT move yet) wp-config.php from:
    %1\$s
  To:
    %2\$s

STEP 3 — Test your site immediately. Visit your WordPress homepage AND wp-admin.
  If the site loads correctly, WordPress found the new location.

STEP 4 — Only after confirming the site works, delete the original file:
    %1\$s

STEP 5 — Re-run the Christopher Ross Shadow scan to confirm this diagnostic is resolved.

TROUBLESHOOTING:
  If the site breaks after moving, copy wp-config.php back to %1\$s.
  WordPress may not support this layout if it is installed in a subdirectory or
  if another wp-config.php file already exists at %2\$s.

NOTE: This only works if %3\$s is not web-accessible.
  Verify by visiting http://yoursite.com/../wp-config.php — it must return 404, not file contents.",
					'thisismyurl-shadow'
				),
				$wp_config,
				$new_path,
				$parent_dir
			),
		];
	}

	/**
	 * No state to undo (guidance only).
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function undo(): array {
		return [
			'success' => true,
			'message' => __( 'This is a guidance-only treatment — no changes were made by Christopher Ross Shadow.', 'thisismyurl-shadow' ),
		];
	}
}
