<?php
/**
 * Dashboard At a Glance Problems Item
 *
 * Adds a Christopher Ross Shadow problem count to the WordPress "At a Glance" widget.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Admin
 * @since 0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Admin;

use ThisIsMyURL\Shadow\Core\Hook_Subscriber_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard_Glance_Problems Class
 *
 * Adds the Christopher Ross Shadow problem count to the default dashboard glance widget.
 *
 * @since 0.6095
 */
class Dashboard_Glance_Problems extends Hook_Subscriber_Base {

	/**
	 * Get hook subscriptions.
	 *
	 * @since 0.6095
	 * @return array Hook subscriptions.
	 */
	protected static function get_hooks(): array {
		return array(
			'filters' => array(
				array( 'dashboard_glance_items', 'filter_glance_items', 10, 1 ),
			),
		);
	}

	/**
	 * Register the hook subscriptions.
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function init(): void {
		self::subscribe();
	}

	/**
	 * Add Christopher Ross Shadow problems count to At a Glance.
	 *
	 * @since 0.6095
	 * @param  array $items Glance items.
	 * @return array Updated glance items.
	 */
	public static function filter_glance_items( array $items ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $items;
		}

		$count = self::get_problem_count();
		$label = sprintf(
			/* translators: %s: number of problems */
			_n( '%s problem', '%s problems', $count, 'thisismyurl-shadow' ),
			number_format_i18n( $count )
		);

		$items[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=thisismyurl-shadow' ) ),
			esc_html( $label )
		);

		return $items;
	}

	/**
	 * Get the count of active Christopher Ross Shadow problems.
	 *
	 * @since 0.6095
	 * @return int Problem count.
	 */
	private static function get_problem_count(): int {
		$findings = function_exists( 'thisismyurl_shadow_get_site_findings' )
			? thisismyurl_shadow_get_site_findings()
			: get_option( 'thisismyurl_shadow_site_findings', array() );

		if ( ! is_array( $findings ) ) {
			return 0;
		}

		$dismissed = get_option( 'thisismyurl_shadow_dismissed_findings', array() );
		$excluded  = get_option( 'thisismyurl_shadow_excluded_findings', array() );

		$count = 0;
		foreach ( $findings as $key => $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}

			$finding_id = $finding['id'] ?? $key;
			if ( empty( $finding_id ) ) {
				continue;
			}

			if ( isset( $dismissed[ $finding_id ] ) || isset( $excluded[ $finding_id ] ) ) {
				continue;
			}

			++$count;
		}

		return $count;
	}
}
