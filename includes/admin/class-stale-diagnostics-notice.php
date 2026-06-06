<?php
/**
 * Stale Diagnostics Notice
 *
 * Renders a dismissible admin notice when the Christopher Ross Shadow diagnostic suite has
 * not been run in more than 24 hours. Prompts the administrator to review
 * schedule settings and status in Guardian.
 *
 * The notice is dismissed per-user with a 24-hour cooldown stored in user meta.
 *
 * Philosophy: Commandment #1 (Helpful Neighbor) — surface actionable issues
 * without overwhelming; Commandment #8 (Inspire Confidence) — always explain
 * the site's current health state.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Admin
 * @since 0.6096
 */

namespace ThisIsMyURL\Shadow\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the "diagnostics are stale" admin notice.
 */
class Stale_Diagnostics_Notice {

	/**
	 * User meta key for the dismiss-until timestamp.
	 */
	const DISMISSED_META_KEY = 'thisismyurl_shadow_stale_diagnostics_dismissed_until';

	/**
	 * How long (in seconds) a dismissal lasts.
	 */
	const DISMISS_DURATION = DAY_IN_SECONDS;

	/**
	 * How long (in seconds) before diagnostics are considered stale.
	 */
	const STALE_THRESHOLD = DAY_IN_SECONDS;

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'wp_ajax_thisismyurl_shadow_dismiss_stale_diagnostics_notice', array( __CLASS__, 'handle_dismiss' ) );
		add_action( 'admin_post_thisismyurl_shadow_run_guardian', array( __CLASS__, 'handle_run_guardian' ) );
	}

	/**
	 * Render the notice in wp-admin when Guardian is overdue.
	 *
	 * Only shown to administrators with manage_options capability who have not
	 * recently dismissed the notice, and only when diagnostics are stale.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! is_admin() ) {
			return;
		}

		// Check dismissal cooldown.
		$user_id         = get_current_user_id();
		$dismissed_until = (int) get_user_meta( $user_id, self::DISMISSED_META_KEY, true );
		if ( $dismissed_until > time() ) {
			return;
		}

		if ( ! self::is_guardian_overdue() ) {
			return;
		}

		$last_run     = (int) get_option( 'thisismyurl_shadow_last_quick_checks', 0 );
		$guardian_url = admin_url( 'admin.php?page=thisismyurl-shadow-guardian' );
		$notice_nonce = wp_create_nonce( 'thisismyurl_shadow_stale_diagnostics_nonce' );
		$redirect_url = self::sanitize_redirect_target( (string) filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_UNSAFE_RAW ) );
		$run_guardian = self::get_run_guardian_url( $redirect_url );

		if ( 0 === $last_run ) {
			$message = __( 'Christopher Ross Shadow Guardian has not completed yet. Run Guardian to execute diagnostics, apply automatic treatments, and refresh reports.', 'thisismyurl-shadow' );
		} else {
			$time_ago = human_time_diff( $last_run, time() );
			/* translators: %s: human-readable time since last run, e.g. "2 hours" */
			$message = sprintf(
				/* translators: %s: human-readable time since the last Guardian run. */
				__( 'Christopher Ross Shadow Guardian has not run in %s. Run Guardian to bring diagnostics, treatments, and report cards up to date.', 'thisismyurl-shadow' ),
				$time_ago
			);
		}
		?>
		<div class="notice notice-warning is-dismissible thisismyurl-shadow-stale-diagnostics-notice"
			data-nonce="<?php echo esc_attr( $notice_nonce ); ?>">
			<p>
				<strong><?php esc_html_e( 'Christopher Ross Shadow — Guardian Overdue', 'thisismyurl-shadow' ); ?></strong>
			</p>
			<p><?php echo esc_html( $message ); ?></p>
			<p>
				<a href="<?php echo esc_url( $run_guardian ); ?>" class="button button-primary">
					<?php esc_html_e( 'Run Guardian', 'thisismyurl-shadow' ); ?>
				</a>
				&nbsp;
				<a href="<?php echo esc_url( $guardian_url ); ?>" class="button">
					<?php esc_html_e( 'Open Guardian', 'thisismyurl-shadow' ); ?>
				</a>
				&nbsp;
				<a href="#"
					class="thisismyurl-shadow-dismiss-stale-diagnostics-notice thisismyurl-shadow-notice-muted-link"
					data-nonce="<?php echo esc_attr( $notice_nonce ); ?>">
					<?php esc_html_e( 'Remind me tomorrow', 'thisismyurl-shadow' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * AJAX: dismiss the notice for DISMISS_DURATION seconds.
	 *
	 * @return void
	 */
	public static function handle_dismiss(): void {
		check_ajax_referer( 'thisismyurl_shadow_stale_diagnostics_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'thisismyurl-shadow' ) ) );
		}

		$user_id = get_current_user_id();
		update_user_meta( $user_id, self::DISMISSED_META_KEY, time() + self::DISMISS_DURATION );

		wp_send_json_success( array( 'message' => __( 'Notice dismissed.', 'thisismyurl-shadow' ) ) );
	}

	/**
	 * Run Guardian immediately via admin-post action.
	 *
	 * @return void
	 */
	public static function handle_run_guardian(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'thisismyurl-shadow' ) );
		}

		check_admin_referer( 'thisismyurl_shadow_run_guardian' );

		$run_error = '';
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Pages\\Scan_Frequency_Manager' ) ) {
			try {
				\ThisIsMyURL\Shadow\Admin\Pages\Scan_Frequency_Manager::run_diagnostic_scan( true );
			} catch ( \Throwable $exception ) {
				$run_error = sanitize_key( get_class( $exception ) );
				\ThisIsMyURL\Shadow\Core\Error_Handler::log_error( 'Christopher Ross Shadow Guardian run failed', $exception );
			}
		} else {
			$run_error = 'scan_manager_missing';
		}

		$redirect = self::sanitize_redirect_target( (string) filter_input( INPUT_GET, 'redirect', FILTER_UNSAFE_RAW ) );
		if ( '' === $redirect ) {
			$redirect = admin_url( 'admin.php?page=thisismyurl-shadow-guardian' );
		}

		$args = array(
			'thisismyurl_shadow_guardian_run' => '1',
		);
		if ( '' !== $run_error ) {
			$args['thisismyurl_shadow_guardian_error'] = $run_error;
		}

		$redirect = add_query_arg( $args, $redirect );

		if ( wp_safe_redirect( $redirect ) ) {
			exit;
		}

		$status_text = '' !== $run_error
			? __( 'Guardian run encountered an issue.', 'thisismyurl-shadow' )
			: __( 'Guardian run completed.', 'thisismyurl-shadow' );

		wp_die(
			sprintf(
				'<p>%1$s</p><p><a href="%2$s">%3$s</a></p>',
				esc_html( $status_text ),
				esc_url( $redirect ),
				esc_html__( 'Continue', 'thisismyurl-shadow' )
			),
			esc_html__( 'Christopher Ross Shadow Guardian', 'thisismyurl-shadow' ),
			array( 'response' => 200 )
		);
	}

	/**
	 * Determine if Guardian has missed its configured schedule window.
	 *
	 * @return bool
	 */
	private static function is_guardian_overdue(): bool {
		$config = class_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Pages\\Scan_Frequency_Manager' )
			? \ThisIsMyURL\Shadow\Admin\Pages\Scan_Frequency_Manager::get_scan_config()
			: array();

		$frequency = isset( $config['frequency'] ) ? (string) $config['frequency'] : 'daily';
		if ( 'manual' === $frequency ) {
			return false;
		}

		$last_run = (int) get_option( 'thisismyurl_shadow_last_quick_checks', 0 );
		if ( $last_run <= 0 ) {
			return true;
		}

		$threshold_map = array(
			'hourly' => HOUR_IN_SECONDS,
			'daily'  => DAY_IN_SECONDS,
			'weekly' => WEEK_IN_SECONDS,
		);
		$window        = $threshold_map[ $frequency ] ?? self::STALE_THRESHOLD;

		return ( time() - $last_run ) > ( $window + 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Build a nonce-protected URL for triggering Guardian run.
	 *
	 * @param string $redirect Redirect target after execution.
	 * @return string
	 */
	public static function get_run_guardian_url( string $redirect = '' ): string {
		$args = array(
			'action' => 'thisismyurl_shadow_run_guardian',
		);
		if ( '' !== $redirect ) {
			$args['redirect'] = $redirect;
		}

		$url = add_query_arg( $args, admin_url( 'admin-post.php' ) );

		return wp_nonce_url( $url, 'thisismyurl_shadow_run_guardian' );
	}

	/**
	 * Normalize a redirect target to a safe internal URL.
	 *
	 * @param string $redirect Candidate redirect target.
	 * @return string
	 */
	private static function sanitize_redirect_target( string $redirect ): string {
		$redirect = trim( $redirect );

		if ( '' === $redirect ) {
			return '';
		}

		if ( 0 === strpos( $redirect, '/' ) ) {
			$redirect = home_url( $redirect );
		}

		return wp_validate_redirect( $redirect, '' );
	}
}
