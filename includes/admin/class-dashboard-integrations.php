<?php
/**
 * Dashboard Integrations
 *
 * Adds Christopher Ross Shadow integrations to the WordPress dashboard, admin bar, and Site Health.
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
 * Dashboard_Integrations Class
 *
 * Provides Christopher Ross Shadow entry points inside standard WordPress admin surfaces.
 *
 * @since 0.6095
 */
class Dashboard_Integrations extends Hook_Subscriber_Base {

	/**
	 * Get hook subscriptions.
	 *
	 * @since 0.6095
	 * @return array Hook subscriptions.
	 */
	protected static function get_hooks(): array {
		return array(
			'actions' => array(
				array( 'wp_dashboard_setup', 'register_dashboard_widget', 10, 0 ),
				array( 'admin_bar_menu', 'add_admin_bar_item', 90, 1 ),
			),
			'filters' => array(
				array( 'site_health_tests', 'filter_site_health_tests', 10, 1 ),
			),
		);
	}

	/**
	 * Register dashboard widget.
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function register_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'thisismyurl_shadow_overview_widget',
			__( 'Christopher Ross Shadow Overview', 'thisismyurl-shadow' ),
			array( __CLASS__, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render dashboard widget.
	 *
	 * @since 0.6095
	 * @return void
	 */
	public static function render_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$count     = self::get_open_findings_count();
		$last_scan = self::get_last_scan_timestamp();
		$label     = sprintf(
			/* translators: %s: number of open findings */
			_n( '%s open item', '%s open items', $count, 'thisismyurl-shadow' ),
			number_format_i18n( $count )
		);
		$last_scan_text = $last_scan > 0
			? sprintf(
				/* translators: %s: human-readable time difference */
				__( 'Last scan %s ago.', 'thisismyurl-shadow' ),
				human_time_diff( $last_scan, time() )
			)
			: __( 'No scans yet. Run your first scan to set a baseline.', 'thisismyurl-shadow' );

		$dashboard_url = admin_url( 'admin.php?page=thisismyurl-shadow' );
		?>
		<div class="thisismyurl-shadow-dashboard-widget">
			<p>
				<?php esc_html_e( 'Christopher Ross Shadow runs locally inside your WordPress site and checks for issues you can fix here.', 'thisismyurl-shadow' ); ?>
			</p>
			<p>
				<strong><?php echo esc_html( $label ); ?></strong>
			</p>
			<p><?php echo esc_html( $last_scan_text ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $dashboard_url ); ?>">
					<?php esc_html_e( 'Open Christopher Ross Shadow Dashboard', 'thisismyurl-shadow' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Add Christopher Ross Shadow entry to the admin bar.
	 *
	 * @since 0.6095
	 * @param  \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public static function add_admin_bar_item( $wp_admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
			return;
		}

		$count         = self::get_open_findings_count();
		$dashboard_url = admin_url( 'admin.php?page=thisismyurl-shadow' );
		$title         = $count > 0
			? sprintf(
				/* translators: %s: number of open findings */
				__( 'Christopher Ross Shadow (%s)', 'thisismyurl-shadow' ),
				number_format_i18n( $count )
			)
			: __( 'Christopher Ross Shadow', 'thisismyurl-shadow' );

		$wp_admin_bar->add_node(
			array(
				'id'    => 'thisismyurl-shadow-overview',
				'title' => esc_html( $title ),
				'href'  => esc_url( $dashboard_url ),
				'meta'  => array(
					'title' => esc_attr__( 'Open Christopher Ross Shadow Dashboard', 'thisismyurl-shadow' ),
				),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'parent' => 'thisismyurl-shadow-overview',
				'id'     => 'thisismyurl-shadow-overview-dashboard',
				'title'  => esc_html__( 'View Dashboard', 'thisismyurl-shadow' ),
				'href'   => esc_url( $dashboard_url ),
			)
		);
	}

	/**
	 * Register Site Health tests for Christopher Ross Shadow.
	 *
	 * @since 0.6095
	 * @param  array $tests Site Health tests.
	 * @return array Updated Site Health tests.
	 */
	public static function filter_site_health_tests( array $tests ): array {
		$tests['direct']['thisismyurl_shadow_overview'] = array(
			'label' => __( 'Christopher Ross Shadow overview', 'thisismyurl-shadow' ),
			'test'  => array( __CLASS__, 'get_site_health_test' ),
		);

		return $tests;
	}

	/**
	 * Provide the Site Health test result.
	 *
	 * @since 0.6095
	 * @return array Site Health test result.
	 */
	public static function get_site_health_test(): array {
		$count         = self::get_open_findings_count();
		$last_scan     = self::get_last_scan_timestamp();
		$dashboard_url = admin_url( 'admin.php?page=thisismyurl-shadow' );
		$badge         = array(
			'label' => 'Christopher Ross Shadow',
			'color' => 'blue',
		);

		if ( 0 === $last_scan ) {
			return array(
				'label'       => __( 'Christopher Ross Shadow has not run a scan yet', 'thisismyurl-shadow' ),
				'status'      => 'recommended',
				'badge'       => $badge,
				'description' => sprintf(
					'<p>%s</p>',
					esc_html__( 'Christopher Ross Shadow runs locally inside your WordPress site. Running a first scan gives you a clear starting point.', 'thisismyurl-shadow' )
				),
				'actions'     => sprintf(
					'<p><a class="button" href="%s">%s</a></p>',
					esc_url( $dashboard_url ),
					esc_html__( 'Open Christopher Ross Shadow', 'thisismyurl-shadow' )
				),
				'test'        => 'thisismyurl_shadow_overview',
			);
		}

		if ( $count > 0 ) {
			return array(
				'label'       => __( 'Christopher Ross Shadow found items to review', 'thisismyurl-shadow' ),
				'status'      => 'recommended',
				'badge'       => $badge,
				'description' => sprintf(
					'<p>%s</p>',
					esc_html__( 'These checks run locally inside your WordPress site. Reviewing them can improve security and performance.', 'thisismyurl-shadow' )
				),
				'actions'     => sprintf(
					'<p><a class="button" href="%s">%s</a></p>',
					esc_url( $dashboard_url ),
					esc_html__( 'Review in Christopher Ross Shadow', 'thisismyurl-shadow' )
				),
				'test'        => 'thisismyurl_shadow_overview',
			);
		}

		return array(
			'label'       => __( 'Christopher Ross Shadow checks look good', 'thisismyurl-shadow' ),
			'status'      => 'good',
			'badge'       => $badge,
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__( 'Your last scan did not find anything that needs attention right now.', 'thisismyurl-shadow' )
			),
			'test'        => 'thisismyurl_shadow_overview',
		);
	}

	/**
	 * Get the count of active findings.
	 *
	 * @since 0.6095
	 * @return int Active findings count.
	 */
	private static function get_open_findings_count(): int {
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

	/**
	 * Get the last diagnostic scan timestamp.
	 *
	 * @since 0.6095
	 * @return int Unix timestamp.
	 */
	private static function get_last_scan_timestamp(): int {
		$last_scan = get_option( 'thisismyurl_shadow_last_quick_checks', 0 );

		return is_numeric( $last_scan ) ? (int) $last_scan : 0;
	}
}
