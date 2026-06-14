<?php
/**
 * Admin Account Count Minimized Diagnostic
 *
 * Checks whether the number of administrator accounts exceeds a safe threshold,
 * reducing the attack surface for privilege abuse or compromised credentials.
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
 * Admin Account Count Minimized Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Admin_Account_Count_Minimized extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'admin-account-count-minimized';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Admin Account Count Minimized';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the number of administrator accounts exceeds a safe threshold, which increases the attack surface for privilege abuse or compromised credentials.';

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
	 * Uses count_users() to count administrator-role accounts and flags when
	 * the total exceeds the recommended maximum.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when admin count is excessive, null when healthy.
	 */
	public static function check() {
		$counts      = count_users();
		$admin_count = isset( $counts['avail_roles']['administrator'] ) ? (int) $counts['avail_roles']['administrator'] : 0;

		// 1–2 admin accounts is a reasonable working number.
		if ( $admin_count <= 2 ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of admin accounts */
				__( 'Your site has %d administrator accounts. Each admin account is a potential entry point for an attacker. Review them and reduce to only the accounts that genuinely require administrator-level access.', 'thisismyurl-shadow' ),
				$admin_count
			),
			'severity'     => $admin_count > 5 ? 'high' : 'medium',
			'threat_level' => min( 30 + ( ( $admin_count - 2 ) * 10 ), 80 ),
			'details'      => array(
				'admin_count' => $admin_count,
				'explanation_sections' => array(
					'summary' => sprintf(
						/* translators: %d: administrator account count */
						__( 'Shadow by Christopher Ross found %d administrator accounts on this site. Every additional admin account increases your privilege exposure, because a single compromised password, reused credential, or vulnerable endpoint tied to any admin can lead to full-site control.', 'thisismyurl-shadow' ),
						$admin_count
					),
					'how_wp_shadow_tested' => __( 'Shadow by Christopher Ross used WordPress role counts to measure how many accounts currently hold the administrator role. This is a direct permissions audit, not a heuristic. It flags once the count exceeds a practical operating baseline of one to two admin users for most production sites.', 'thisismyurl-shadow' ),
					'why_it_matters' => __( 'Admin role sprawl expands your attack surface and complicates incident response. With more high-privilege accounts, it becomes harder to maintain MFA, monitor unusual behavior, and enforce strong credential standards consistently. Reducing privileged accounts is one of the most effective low-cost risk controls.', 'thisismyurl-shadow' ),
					'how_to_fix_it' => __( 'Review each administrator account and verify a current business need. Downgrade accounts that only require editorial or shop-management access, remove stale users, and enforce MFA for remaining admins. Keep at least one emergency owner account documented securely, then run this check again to confirm the admin count is minimized.', 'thisismyurl-shadow' ),
				),
			),
		);
	}
}
