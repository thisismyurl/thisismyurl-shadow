<?php
/**
 * File Write Review Page
 *
 * Registers a hidden Shadow by Christopher Ross admin page (accessible via notice link or direct
 * URL) that presents each pending file-write treatment with:
 *   - The exact text that would be inserted/modified in the target file
 *   - A side-by-side diff preview (via AJAX dry-run)
 *   - A one-click backup button (stores current file content in DB)
 *   - A restore button (restores from the stored backup)
 *   - An "Apply Fix" button that opens the SFTP acknowledgment modal first
 *
 * The page is deliberately hidden from the sidebar menu (remove_submenu_page)
 * to avoid confusion. It is always accessible via the notice link.
 *
 * Philosophy: Commandment #8 (Inspire Confidence) — full transparency before
 * any file is touched; Commandment #5 (Stay Out of the Way) — minimal friction
 * once the admin has read the instructions.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Admin\Pages
 * @since 0.6095
 */

namespace ThisIsMyURL\Shadow\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render and manage the hidden admin page for reviewing file-write treatments.
 *
 * This page exists to slow down potentially risky automatic fixes just enough
 * for an admin to understand what will change. Rather than applying file edits
 * immediately, Shadow by Christopher Ross routes people here so they can inspect the proposed
 * diff, create a backup, and read rollback guidance first.
 */
class File_Write_Review_Page {

	/**
	 * Slug used when WordPress routes requests to this review screen.
	 *
	 * @since 0.6095
	 * @var   string
	 */
	const PAGE_SLUG = 'thisismyurl-shadow-file-review';

	/**
	 * Register the hooks needed to expose and decorate the review page.
	 *
	 * This method wires the page into the admin menu system, asset pipeline, and
	 * page-header lifecycle. Keeping hook registration in one place makes the
	 * class easier to bootstrap from a central service loader.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_page' ], 99 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_head', [ __CLASS__, 'hide_menu_entry' ] );
	}

	/**
	 * Register the review page with WordPress as a submenu entry.
	 *
	 * Even though the page is hidden visually, WordPress still needs a real menu
	 * registration so capability checks, page titles, and routing all work the
	 * same way they do for any other admin screen.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function register_page(): void {
		add_submenu_page(
			'thisismyurl-shadow',
			__( 'Review File Changes', 'thisismyurl-shadow' ),
			__( 'Review File Changes', 'thisismyurl-shadow' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render' ]
		);
	}

	/**
	 * Hide the submenu link while preserving WordPress page registration.
	 *
	 * WordPress derives the admin page title from the submenu entry, so removing
	 * the submenu item causes admin-header.php to receive a null title on direct
	 * page visits. CSS keeps the page hidden while preserving core routing/title
	 * behavior.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function hide_menu_entry(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<style>
			#toplevel_page_thisismyurl-shadow .wp-submenu a[href="admin.php?page=<?php echo esc_attr( self::PAGE_SLUG ); ?>"] {
				display: none !important;
			}
		</style>
		<?php
	}

	/**
	 * Enqueue JavaScript and shared modal assets for the review page.
	 *
	 * The page relies on AJAX-powered preview, backup, apply, and restore flows.
	 * This method ensures those front-end behaviors are only loaded on the review
	 * screen and passes the nonces and translated strings that the script needs.
	 *
	 * @since  0.6095
	 * @param  string $hook Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( string $hook ): void {
		// Only load on this specific page.
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		// Modal styling is shared when available.
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Admin_Asset_Registry' ) ) {
			\ThisIsMyURL\Shadow\Core\Admin_Asset_Registry::enqueue_modal_assets();
		}

		// Page JS.
		wp_enqueue_script(
			'thisismyurl-shadow-file-write-review',
			THISISMYURL_SHADOW_URL . 'assets/js/file-write-review.js',
			[ 'jquery' ],
			file_exists( THISISMYURL_SHADOW_PATH . 'assets/js/file-write-review.js' )
				? (string) filemtime( THISISMYURL_SHADOW_PATH . 'assets/js/file-write-review.js' )
				: THISISMYURL_SHADOW_VERSION,
			true
		);

		wp_localize_script(
			'thisismyurl-shadow-file-write-review',
			'thisismyurlShadowFileReview',
			[
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonces'     => [
					'backup'  => wp_create_nonce( 'thisismyurl_shadow_file_write_backup' ),
					'dryRun'  => wp_create_nonce( 'thisismyurl_shadow_file_write_dry_run' ),
					'apply'   => wp_create_nonce( 'thisismyurl_shadow_file_write_apply' ),
					'restore' => wp_create_nonce( 'thisismyurl_shadow_file_write_restore' ),
				],
				'i18n'       => [
					'backupSuccess'   => __( 'Backup created successfully.', 'thisismyurl-shadow' ),
					'backupFailed'    => __( 'Backup failed. Please try again.', 'thisismyurl-shadow' ),
					'dryRunPending'   => __( 'Running preview…', 'thisismyurl-shadow' ),
					'applySuccess'    => __( 'Fix applied successfully.', 'thisismyurl-shadow' ),
					'applyFailed'     => __( 'Fix could not be applied. Please check file permissions.', 'thisismyurl-shadow' ),
					'restoreSuccess'  => __( 'File restored from backup.', 'thisismyurl-shadow' ),
					'restoreFailed'   => __( 'Restore failed.', 'thisismyurl-shadow' ),
					'confirmRestore'  => __( 'Restore the file to its state when the backup was created? The current file will be overwritten.', 'thisismyurl-shadow' ),
					'ackRequired'     => __( 'Please read and acknowledge the recovery instructions before applying.', 'thisismyurl-shadow' ),
				],
			]
		);
	}

	/**
	 * Render the review page template after permission and file checks.
	 *
	 * The method gathers the current set of pending file-write treatments and then
	 * includes the dedicated view file that prints the interface. Separating the
	 * controller logic from the view keeps the class easier to read and lets the
	 * template focus on presentation.
	 *
	 * @since  0.6095
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'thisismyurl-shadow' ) );
		}

		$view_file = THISISMYURL_SHADOW_PATH . 'includes/ui/views/file-write-review-page.php';

		if ( ! file_exists( $view_file ) ) {
			wp_die( esc_html__( 'View template not found.', 'thisismyurl-shadow' ) );
		}

		// Collect pending file-write treatments for the view.
		$pending = \ThisIsMyURL\Shadow\Admin\File_Write_Registry::get_pending();

		include $view_file;
	}
}
