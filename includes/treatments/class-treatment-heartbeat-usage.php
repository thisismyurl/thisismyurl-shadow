<?php
/**
 * Treatment: Reduce WordPress Heartbeat API polling interval
 *
 * The WordPress Heartbeat API polls the server every 15 seconds by default,
 * keeping authenticated admin sessions alive and enabling real-time features
 * like post locking. On most sites this frequency is unnecessary and adds
 * background server load. This treatment stores a flag that tells the
 * Shadow by Christopher Ross bootstrap to filter wp_heartbeat_settings and raise the interval
 * to 60 seconds on admin screens.
 *
 * The fix is skipped if a dedicated heartbeat management plugin is already
 * active (Perfmatters, WP Rocket, Heartbeat Control).
 *
 * Undo: removes the flag; bootstrap stops applying the filter on the next load.
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
 * Slows WordPress Heartbeat API polling to 60 seconds.
 */
class Treatment_Heartbeat_Usage extends Treatment_Base {

	/** @var string */
	protected static $slug = 'heartbeat-usage';

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Store the optimization flag so the bootstrap can apply the filter.
	 *
	 * @return array
	 */
	public static function apply(): array {
		update_option( 'thisismyurl_shadow_optimize_heartbeat', true );

		return array(
			'success' => true,
			'message' => __( 'Heartbeat interval will be reduced to 60 seconds on admin screens. This takes effect on the next page load.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the flag; the bootstrap will no longer apply the heartbeat filter.
	 *
	 * @return array
	 */
	public static function undo(): array {
		delete_option( 'thisismyurl_shadow_optimize_heartbeat' );

		return array(
			'success' => true,
			'message' => __( 'Heartbeat optimization removed. WordPress Heartbeat will use its default interval again on the next page load.', 'thisismyurl-shadow' ),
		);
	}
}
