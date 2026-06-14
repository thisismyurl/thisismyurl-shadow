<?php
/**
 * Treatment: PHP Version
 *
 * Provides guidance for upgrading the PHP version. PHP upgrades must be
 * performed at the hosting or server level; WordPress plugins cannot
 * change the PHP interpreter version.
 *
 * Risk level: n/a (guidance only)
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
 * Returns hosting-level guidance for upgrading the PHP version.
 */
class Treatment_Php_Version extends Treatment_Base {

	/** @var string */
	protected static $slug = 'php-version';

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
	 * Return PHP version upgrade guidance.
	 *
	 * @return array{success:bool, message:string}
	 */
	public static function apply(): array {
		$current_version   = PHP_VERSION;
		$recommended       = '8.2'; // Update as WP requirements evolve.

		return [
			'success' => false,
			'message' => sprintf(
				/* translators: 1: current PHP version, 2: recommended version */
				__(
					"Your current PHP version is %1\$s. WordPress recommends PHP %2\$s or higher.

PHP upgrades must be performed by your hosting provider or server admin.

BEFORE UPGRADING — Test your site:
  1. Create a staging/test site with the new PHP version.
  2. Check that your theme and all plugins are compatible.
  3. Run the site's main functionality to catch deprecation errors.

OPTION 1 — cPanel / Shared Hosting:
  1. Log in to cPanel and find 'MultiPHP Manager' or 'PHP Version'.
  2. Select your domain and choose PHP %2\$s (or the latest available).
  3. Click Apply. The change takes effect immediately.
  4. Check your site for any PHP errors (enable WP_DEBUG temporarily).

OPTION 2 — VPS/Dedicated (Debian/Ubuntu):
  sudo add-apt-repository ppa:ondrej/php
  sudo apt-get update
  sudo apt-get install php%2\$s php%2\$s-fpm php%2\$s-mysql php%2\$s-xml php%2\$s-mbstring
  sudo update-alternatives --set php /usr/bin/php%2\$s
  sudo service php%2\$s-fpm restart

OPTION 3 — Contact your hosting provider:
  Open a support ticket asking: 'Please upgrade my site to PHP %2\$s.'
  Most quality hosts can do this in minutes.

Re-run the Shadow by Christopher Ross scan after upgrading.",
					'thisismyurl-shadow'
				),
				$current_version,
				$recommended
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
			'message' => __( 'This is a guidance-only treatment — no changes were made by Shadow by Christopher Ross.', 'thisismyurl-shadow' ),
		];
	}
}
