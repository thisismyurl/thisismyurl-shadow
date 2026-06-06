<?php
/**
 * Error Handler - Enhances WordPress fatal error pages
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Core
 */

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enhances WordPress fatal error display
 */
class Error_Handler {

	/**
	 * Initialize error handling
	 *
	 * Why do programmers enjoy clean logs? Easier bugs, fewer shrugs.
	 */
	public static function init(): void {
		// PHP error reporting is owned by WordPress, the host, and the site
		// operator. The plugin must not call error_reporting() or ini_set()
		// to override those decisions on a global hook (WordPress.org review
		// requirement). Diagnostics that need to inspect runtime errors do
		// so via WordPress' own hooks below.

		if ( self::should_skip_enhanced_error_output() ) {
			return;
		}

		// Hook into WordPress PHP error handler
		add_filter( 'wp_php_error_message', array( __CLASS__, 'enhance_error_message' ), 10, 2 );
		add_filter( 'wp_php_error_args', array( __CLASS__, 'enhance_error_args' ), 10, 2 );
		// Only add error modal script when there's an actual error
		// (removed from wp_footer/admin_footer as it was showing on ALL pages)
	}

	/**
	 * Store internal runtime errors without writing directly to PHP output.
	 *
	 * @since 0.6098
	 * @param string                 $message Human-readable error summary.
	 * @param array|\Throwable|mixed $context Additional context or exception.
	 * @return void
	 */
	public static function log_error( string $message, $context = array() ): void {
		$metadata = array();

		if ( $context instanceof \Throwable ) {
			$metadata = array(
				'error_class'   => get_class( $context ),
				'error_message' => $context->getMessage(),
				'error_file'    => basename( $context->getFile() ),
				'error_line'    => $context->getLine(),
				'error_code'    => $context->getCode(),
			);
		} elseif ( is_array( $context ) ) {
			$metadata = $context;
		} elseif ( null !== $context && '' !== (string) $context ) {
			$metadata = array(
				'context' => (string) $context,
			);
		}

		if ( class_exists( 'ThisIsMyURL\\Shadow\\Core\\Activity_Logger' ) ) {
			Activity_Logger::log(
				'internal_error',
				$message,
				'system',
				$metadata
			);
		}

		do_action( 'thisismyurl_shadow_internal_error_logged', $message, $metadata );
	}

	/**
	 * Skip enhanced fatal-error UI for non-HTML request types.
	 *
	 * @since 0.6098
	 * @return bool
	 */
	private static function should_skip_enhanced_error_output(): bool {
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? strtolower( trim( (string) wp_unslash( $_SERVER['HTTP_ACCEPT'] ) ) ) : '';
		return '' !== $accept && false === strpos( $accept, 'text/html' );
	}

	/**
	 * Reduce raw fatal messages to a safe summary for any optional UI payload.
	 *
	 * @since 0.6098
	 * @param string $message Raw fatal error message.
	 * @return string
	 */
	private static function summarize_error_message( string $message ): string {
		$message = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $message ) ) );

		if ( '' === $message ) {
			return __( 'A critical error interrupted this request.', 'thisismyurl-shadow' );
		}

		$patterns = array(
			'/ in \/[^ ]+/i',
			'/Stack trace\:.*/i',
			'/#\d+\s+.+/i',
			'/\s+thrown$/i',
		);

		$summary = preg_replace( $patterns, '', $message );
		$summary = trim( preg_replace( '/\s+/', ' ', (string) $summary ) );

		if ( '' === $summary ) {
			return __( 'A critical error interrupted this request.', 'thisismyurl-shadow' );
		}

		return $summary;
	}

	/**
	 * Add the modal script and HTML early so functions are available
	 */
	public static function add_error_modal_script(): void {
		?>
		<!-- Christopher Ross Shadow Error Handler Modal -->
		<div id="thisismyurl-shadow-modal-overlay" class="thisismyurl-shadow-modal-overlay wps-none" role="dialog" aria-modal="true" aria-labelledby="thisismyurl-shadow-error-modal-title" aria-hidden="true" data-thisismyurl-shadow-modal="static" data-overlay-close="true" data-esc-close="true">
			<div class="thisismyurl-shadow-modal wps-p-30-rounded-8" role="document">
				<h2 id="thisismyurl-shadow-error-modal-title" style="margin-top: 0; color: #0073aa;">
					<?php esc_html_e( 'How can Christopher Ross Shadow help?', 'thisismyurl-shadow' ); ?>
				</h2>

				<p style="line-height:1.0; color: #333;">
					<?php esc_html_e( 'We have two options to help you resolve this error:', 'thisismyurl-shadow' ); ?>
				</p>

				<!-- Option 1: Send Anonymous Report -->
				<div class="wps-m-20-p-15-rounded-4">
					<h3 style="margin-top: 0; font-size: 16px; color: #0073aa;">
						<?php esc_html_e( '📊 Send Anonymous Report (Recommended)', 'thisismyurl-shadow' ); ?>
					</h3>
					<p class="wps-m-10">
						<?php esc_html_e( 'Send error details to Christopher Ross Shadow for personalized suggestions. We collect:', 'thisismyurl-shadow' ); ?>
					</p>
					<ul class="wps-m-10">
						<li><?php esc_html_e( 'Error message and location', 'thisismyurl-shadow' ); ?></li>
						<li><?php esc_html_e( 'PHP version and WordPress version', 'thisismyurl-shadow' ); ?></li>
						<li><?php esc_html_e( 'Active plugins list (names only)', 'thisismyurl-shadow' ); ?></li>
					</ul>
					<p class="wps-m-10">
						<?php esc_html_e( '✓ No personal data • No site URL • No content • Fully anonymous', 'thisismyurl-shadow' ); ?>
					</p>
					<button
						id="thisismyurl-shadow-send-report-btn"
						class="wps-p-12-rounded-4"
						onclick="thisismyurlShadowSendReport()"
					>
						<?php esc_html_e( 'Send Report & Get Help', 'thisismyurl-shadow' ); ?>
					</button>
				</div>

				<!-- Close button -->
				<button
					onclick="thisismyurlShadowCloseModal()"
					class="wps-p-10-rounded-4"
				>
					<?php esc_html_e( 'Close', 'thisismyurl-shadow' ); ?>
				</button>
			</div>
		</div>

		<script>
		// Store error data globally so functions can access it
		window.thisismyurlShadowErrorData = {
			lastError: null
		};

		// Show modal
		function thisismyurlShadowShowHelpModal(errorData) {
			// Store error data if provided
			if (errorData) {
				window.thisismyurlShadowErrorData.lastError = errorData;
			}
			if (window.thisismyurlShadowModal && typeof window.thisismyurlShadowModal.openStatic === 'function') {
				window.thisismyurlShadowModal.openStatic('thisismyurl-shadow-modal-overlay', { returnFocus: document.activeElement });
				return;
			}
			const modal = document.getElementById("thisismyurl-shadow-modal-overlay");
			if (modal) {
				modal.classList.remove('wps-none');
				modal.style.display = "flex";
				modal.setAttribute('aria-hidden', 'false');
			}
		}

		// Close modal
		function thisismyurlShadowCloseModal() {
			if (window.thisismyurlShadowModal && typeof window.thisismyurlShadowModal.closeStatic === 'function') {
				window.thisismyurlShadowModal.closeStatic('thisismyurl-shadow-modal-overlay');
				return;
			}
			const modal = document.getElementById("thisismyurl-shadow-modal-overlay");
			if (modal) {
				modal.style.display = "none";
				modal.setAttribute('aria-hidden', 'true');
				modal.classList.add('wps-none');
			}
		}

		// Send anonymous report
		function thisismyurlShadowSendReport() {
			const btn = document.getElementById("thisismyurl-shadow-send-report-btn");
			btn.disabled = true;
			btn.textContent = "<?php esc_attr_e( 'Sending...', 'thisismyurl-shadow' ); ?>";

			const errorData = window.thisismyurlShadowErrorData.lastError || {};
			const formData = new FormData();
			formData.append("action", "thisismyurl_shadow_send_error_report");
			formData.append("nonce", "<?php echo esc_attr( wp_create_nonce( 'thisismyurl_shadow_error_report' ) ); ?>");
			formData.append("error_data", JSON.stringify(errorData));

			fetch("<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>", {
				method: "POST",
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					btn.textContent = "<?php esc_attr_e( '✓ Report Sent', 'thisismyurl-shadow' ); ?>";
					btn.style.background = "#46b450";

					setTimeout(() => {
						thisismyurlShadowCloseModal();
					}, 1500);
				} else {
					btn.textContent = "<?php esc_attr_e( '✗ Error Sending', 'thisismyurl-shadow' ); ?>";
					btn.style.background = "#dc3232";
					setTimeout(() => {
						btn.disabled = false;
						btn.textContent = "<?php esc_attr_e( 'Send Report & Get Help', 'thisismyurl-shadow' ); ?>";
						btn.style.background = "#0073aa";
					}, 3000);
				}
			})
			.catch(() => {
				btn.textContent = "<?php esc_attr_e( '✗ Error Sending', 'thisismyurl-shadow' ); ?>";
				btn.style.background = "#dc3232";
				setTimeout(() => {
					btn.disabled = false;
					btn.textContent = "<?php esc_attr_e( 'Send Report & Get Help', 'thisismyurl-shadow' ); ?>";
					btn.style.background = "#0073aa";
				}, 3000);
			});
		}

		// Close modal on outside click
		(function() {
			const modal = document.getElementById("thisismyurl-shadow-modal-overlay");
			if (modal) {
				modal.addEventListener("click", function(e) {
					if (e.target === this) {
						thisismyurlShadowCloseModal();
					}
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Enhance the error message with Christopher Ross Shadow help options
	 *
	 * @param string $message Error message
	 * @param array $error Error details
	 * @return string Enhanced message
	 */
	public static function enhance_error_message( string $message, array $error ): string {
		// Prepare error data for modal
		$error_data = array(
			'message'        => self::summarize_error_message( (string) ( $error['message'] ?? '' ) ),
			'file'           => '',
			'line'           => '',
			'type'           => $error['type'] ?? '',
			'php_version'    => phpversion(),
			'wp_version'     => get_bloginfo( 'version' ),
			'active_plugins' => array_values( array_map( 'plugin_basename', get_option( 'active_plugins', array() ) ) ),
		);

		// Add Christopher Ross Shadow help button and modal (only shown on actual errors)
		$help_section = '<div class="wps-p-15-rounded-4">' .
			'<p class="wps-m-0">' .
				esc_html__( 'For help resolving this issue, Christopher Ross Shadow can assist:', 'thisismyurl-shadow' ) .
			'</p>' .
			'<button
				id="thisismyurl-shadow-help-btn"
				class="wps-p-10-rounded-3"
				onclick="thisismyurlShadowShowHelpModal(' . wp_json_encode( $error_data ) . ')"
			>' .
				esc_html__( 'Get Help with This Error', 'thisismyurl-shadow' ) .
			'</button>' .
			'</div>';

		$message .= $help_section;

		// Add modal HTML and scripts (only when error occurs)
		ob_start();
		self::add_error_modal_script();
		$message .= ob_get_clean();

		return $message;
	}

	/**
	 * Enhance error args to add Christopher Ross Shadow AI report button
	 *
	 * @param array $args wp_die arguments
	 * @param array $error Error details
	 * @return array Enhanced args
	 */
	public static function enhance_error_args( array $args, array $error ): array {
		// Modal is now always available via add_error_modal_script hook
		return $args;
	}
}
