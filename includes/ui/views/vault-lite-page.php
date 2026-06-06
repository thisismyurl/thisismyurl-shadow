<?php
/**
 * Vault Lite Page
 *
 * Local-only backup dashboard and settings for Christopher Ross Shadow.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Views
 * @since      0.6095
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'thisismyurl-shadow' ) );
}

require_once THISISMYURL_SHADOW_PATH . 'includes/ui/views/functions-page-layout.php';

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'vault'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive tab selection only.
$valid_tabs = array( 'vault', 'settings' );
if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
	$active_tab = 'vault';
}

$get_option_value = static function ( string $option, $fallback = '' ) {
	if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Settings_Registry' ) ) {
		return \ThisIsMyURL\Shadow\Core\Settings_Registry::get( $option, $fallback );
	}

	$key = 0 === strpos( $option, 'thisismyurl_shadow_' ) ? $option : 'thisismyurl_shadow_' . $option;
	return get_option( $key, $fallback );
};

$get_bool = static function ( string $option, bool $fallback = true ) use ( $get_option_value ): bool {
	return (bool) $get_option_value( $option, $fallback );
};

$get_str = static function ( string $option, string $fallback = '' ) use ( $get_option_value ): string {
	return (string) $get_option_value( $option, $fallback );
};

$get_int = static function ( string $option, int $fallback = 0 ) use ( $get_option_value ): int {
	return (int) $get_option_value( $option, $fallback );
};

$backup_status = class_exists( '\\ThisIsMyURL\\Shadow\\Guardian\\Backup_Manager' )
	? \ThisIsMyURL\Shadow\Guardian\Backup_Manager::get_status_summary()
	: array(
		'directory'              => '',
		'directory_public_label' => __( 'Private Vault Lite storage (hidden randomized path)', 'thisismyurl-shadow' ),
		'count'                  => 0,
		'total_size_human'       => size_format( 0 ),
		'last_backup_label'      => __( 'No local backups yet', 'thisismyurl-shadow' ),
		'last_backup_file'       => '',
		'last_backup_status'     => 'warning',
	);

$next_backup_display = class_exists( '\\ThisIsMyURL\\Shadow\\Guardian\\Backup_Scheduler' )
	? \ThisIsMyURL\Shadow\Guardian\Backup_Scheduler::get_next_scheduled_display()
	: __( 'Scheduler unavailable', 'thisismyurl-shadow' );


$backup_entries = class_exists( '\\ThisIsMyURL\\Shadow\\Guardian\\Backup_Manager' )
	? \ThisIsMyURL\Shadow\Guardian\Backup_Manager::get_backups()
	: array();

$backup_run_status      = isset( $_GET['thisismyurl_shadow_backup_run'] ) ? sanitize_key( wp_unslash( $_GET['thisismyurl_shadow_backup_run'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$backup_restored_status = isset( $_GET['thisismyurl_shadow_backup_restored'] ) ? sanitize_key( wp_unslash( $_GET['thisismyurl_shadow_backup_restored'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$restore_message        = isset( $_GET['thisismyurl_shadow_restore_message'] ) ? sanitize_text_field( wp_unslash( $_GET['thisismyurl_shadow_restore_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$backup_deleted_status  = isset( $_GET['thisismyurl_shadow_backup_deleted'] ) ? sanitize_key( wp_unslash( $_GET['thisismyurl_shadow_backup_deleted'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$delete_message         = isset( $_GET['thisismyurl_shadow_delete_message'] ) ? sanitize_text_field( wp_unslash( $_GET['thisismyurl_shadow_delete_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$retention_days         = $get_int( 'thisismyurl_shadow_backup_retention_days', 7 );
$max_size_human         = size_format( $get_int( 'thisismyurl_shadow_backup_max_size_mb', 500 ) * 1024 * 1024 );
$vault_url              = admin_url( 'admin.php?page=thisismyurl-shadow-vault-lite' );
?>
<div class="wrap wps-settings-page">

<?php
thisismyurl_shadow_render_page_header(
	__( 'Vault Lite', 'thisismyurl-shadow' ),
	__( 'Manage local-only restore points and backup settings for this site.', 'thisismyurl-shadow' ),
	'dashicons-backup'
);
?>

	<div id="wps-settings-notice" class="wps-settings-notice" aria-live="polite"></div>

	<nav class="wps-settings-tabs" aria-label="<?php esc_attr_e( 'Vault Lite sections', 'thisismyurl-shadow' ); ?>">
		<?php
		$vault_tabs = array(
			'vault'    => array(
				'label' => __( 'Vault Lite', 'thisismyurl-shadow' ),
				'icon'  => 'dashicons-backup',
			),
			'settings' => array(
				'label' => __( 'Settings', 'thisismyurl-shadow' ),
				'icon'  => 'dashicons-admin-generic',
			),
		);
		foreach ( $vault_tabs as $tab_key => $vault_tab ) :
			$href   = add_query_arg( 'tab', $tab_key, $vault_url );
			$active = $active_tab === $tab_key ? ' wps-settings-tab--active' : '';
			?>
			<a
				href="<?php echo esc_url( $href ); ?>"
				class="wps-settings-tab<?php echo esc_attr( $active ); ?>"
				aria-current="<?php echo esc_attr( $active_tab === $tab_key ? 'page' : 'false' ); ?>"
			>
				<span class="dashicons <?php echo esc_attr( $vault_tab['icon'] ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $vault_tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'error' === $backup_run_status ) : ?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Local backup could not be created.', 'thisismyurl-shadow' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $backup_restored_status ) : ?>
		<div class="notice <?php echo 'success' === $backup_restored_status ? 'notice-success' : 'notice-error'; ?>">
			<p><?php echo esc_html( '' !== $restore_message ? $restore_message : __( 'Restore request finished.', 'thisismyurl-shadow' ) ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $backup_deleted_status ) : ?>
		<div class="notice <?php echo 'success' === $backup_deleted_status ? 'notice-success' : 'notice-error'; ?>">
			<p><?php echo esc_html( '' !== $delete_message ? $delete_message : __( 'Backup deletion finished.', 'thisismyurl-shadow' ) ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( 'vault' === $active_tab ) : ?>
	<div class="wps-settings-body">

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'Local Backup Status', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'Vault Lite stores local-only restore points on this server. No cloud tools are included in this version.', 'thisismyurl-shadow' ); ?></p>

			<div class="wps-settings-rows">
				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label><?php esc_html_e( 'Disk Usage', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Combined size of all retained local backup archives.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<strong><?php echo esc_html( (string) $backup_status['total_size_human'] ); ?></strong>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label><?php esc_html_e( 'Next Scheduled Backup', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Shown when scheduled local backups are enabled.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<strong><?php echo esc_html( $next_backup_display ); ?></strong>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label><?php esc_html_e( 'Run Backup Now', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Create a new local restore point immediately.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="thisismyurl_shadow_run_local_backup" />
							<?php wp_nonce_field( 'thisismyurl_shadow_run_local_backup' ); ?>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Local Backup', 'thisismyurl-shadow' ); ?></button>
						</form>
					</div>
				</div>
			</div>
		</div>

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'Available Backups', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'Download, restore, or delete any retained local backup from this list. Christopher Ross Shadow creates a fresh safety backup before restoring.', 'thisismyurl-shadow' ); ?></p>

			<?php if ( empty( $backup_entries ) ) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'No retained local backups are available yet.', 'thisismyurl-shadow' ); ?></p>
				</div>
			<?php else : ?>
				<div class="wps-settings-table-wrap">
					<table class="widefat striped">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Backup', 'thisismyurl-shadow' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Created', 'thisismyurl-shadow' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Size', 'thisismyurl-shadow' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'thisismyurl-shadow' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Actions', 'thisismyurl-shadow' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $backup_entries as $entry ) : ?>
								<?php
								$file_name    = isset( $entry['file'] ) ? (string) $entry['file'] : '';
								$description  = class_exists( '\\ThisIsMyURL\\Shadow\\Guardian\\Backup_Manager' ) ? \ThisIsMyURL\Shadow\Guardian\Backup_Manager::describe_backup( $entry ) : '';
								$created_at   = isset( $entry['created_at'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $entry['created_at'] ) : __( 'Unknown', 'thisismyurl-shadow' );
								$size_label   = isset( $entry['size'] ) ? size_format( (int) $entry['size'] ) : __( 'Unknown', 'thisismyurl-shadow' );
								$is_verified  = ! isset( $entry['verified'] ) || ! empty( $entry['verified'] );
								$status_label = $is_verified ? __( 'Verified', 'thisismyurl-shadow' ) : __( 'Needs Review', 'thisismyurl-shadow' );
								$download_url = wp_nonce_url(
									add_query_arg(
										array(
											'action'      => 'thisismyurl_shadow_download_local_backup',
											'backup_file' => rawurlencode( $file_name ),
										),
										admin_url( 'admin-post.php' )
									),
									'thisismyurl_shadow_download_local_backup'
								);
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( $file_name ); ?></strong>
										<?php if ( '' !== $description ) : ?>
											<div class="description"><?php echo esc_html( $description ); ?></div>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $created_at ); ?></td>
									<td><?php echo esc_html( $size_label ); ?></td>
									<td><?php echo esc_html( $status_label ); ?></td>
									<td style="white-space:nowrap;">
										<a
											href="<?php echo esc_url( $download_url ); ?>"
											class="button button-secondary"
											title="<?php esc_attr_e( 'Download this backup archive', 'thisismyurl-shadow' ); ?>"
										>
											<span class="dashicons dashicons-download" aria-hidden="true" style="position:relative; top:5px;"></span>
											<?php esc_html_e( 'Download', 'thisismyurl-shadow' ); ?>
										</a>
										<a
											href="#wps-vault-restore-dialog"
											class="button button-secondary wps-vault-restore-trigger"
											data-backup-file="<?php echo esc_attr( $file_name ); ?>"
											data-backup-description="<?php echo esc_attr( $description ); ?>"
										><?php esc_html_e( 'Restore', 'thisismyurl-shadow' ); ?></a>
										<button
											type="button"
											class="button button-link-delete wps-vault-delete-trigger"
											data-backup-file="<?php echo esc_attr( $file_name ); ?>"
											data-backup-description="<?php echo esc_attr( $description ); ?>"
											title="<?php esc_attr_e( 'Delete this backup', 'thisismyurl-shadow' ); ?>"
										>
											<span class="dashicons dashicons-trash" aria-hidden="true" style="position:relative; top:5px;"></span>
											<?php esc_html_e( 'Delete', 'thisismyurl-shadow' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>

	</div>

	<dialog id="wps-vault-restore-dialog" class="wps-settings-dialog" aria-labelledby="wps-vault-restore-title">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="thisismyurl_shadow_restore_local_backup" />
			<input type="hidden" id="wps-vault-restore-file" name="backup_file" value="" />
			<?php wp_nonce_field( 'thisismyurl_shadow_restore_local_backup' ); ?>

			<div class="wps-settings-dialog__header">
				<h2 id="wps-vault-restore-title"><?php esc_html_e( 'Restore Local Backup', 'thisismyurl-shadow' ); ?></h2>
			</div>

			<div class="wps-settings-dialog__body">
				<p><?php esc_html_e( 'You are about to restore this local backup:', 'thisismyurl-shadow' ); ?></p>
				<p id="wps-vault-restore-description"><strong></strong></p>
				<p class="description"><?php esc_html_e( 'Christopher Ross Shadow will create a fresh safety backup first when possible. Restoring may overwrite files and database content.', 'thisismyurl-shadow' ); ?></p>
			</div>

			<div class="wps-settings-dialog__footer" style="display:flex; gap:12px; justify-content:flex-end;">
				<button type="button" class="button" data-wps-vault-close><?php esc_html_e( 'Cancel', 'thisismyurl-shadow' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Restore Backup', 'thisismyurl-shadow' ); ?></button>
			</div>
		</form>
	</dialog>

	<dialog id="wps-vault-delete-dialog" class="wps-settings-dialog" aria-labelledby="wps-vault-delete-title">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="thisismyurl_shadow_delete_local_backup" />
			<input type="hidden" id="wps-vault-delete-file" name="backup_file" value="" />
			<?php wp_nonce_field( 'thisismyurl_shadow_delete_local_backup' ); ?>

			<div class="wps-settings-dialog__header">
				<h2 id="wps-vault-delete-title"><?php esc_html_e( 'Delete Backup', 'thisismyurl-shadow' ); ?></h2>
			</div>

			<div class="wps-settings-dialog__body">
				<p><?php esc_html_e( 'You are about to permanently delete this backup:', 'thisismyurl-shadow' ); ?></p>
				<p id="wps-vault-delete-description"><strong></strong></p>
				<p class="description"><?php esc_html_e( 'This action cannot be undone.', 'thisismyurl-shadow' ); ?></p>
			</div>

			<div class="wps-settings-dialog__footer" style="display:flex; gap:12px; justify-content:flex-end;">
				<button type="button" class="button" data-wps-vault-close><?php esc_html_e( 'Cancel', 'thisismyurl-shadow' ); ?></button>
				<button type="submit" class="button button-primary" style="background:#b32d2e;border-color:#b32d2e;"><?php esc_html_e( 'Delete Backup', 'thisismyurl-shadow' ); ?></button>
			</div>
		</form>
	</dialog>
	<?php endif; ?>

	<?php if ( 'settings' === $active_tab ) : ?>
	<div class="wps-settings-body">

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'Vault Lite Backups', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'Lightweight local backups created before treatments or on demand.', 'thisismyurl-shadow' ); ?></p>

			<div class="wps-settings-rows">
				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-backup-enabled"><?php esc_html_e( 'Enable Backups', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Create a backup before applying any treatment. Strongly recommended.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-backup-enabled"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_backup_enabled"
								data-type="bool"
								<?php checked( $get_bool( 'thisismyurl_shadow_backup_enabled', true ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-backup-db"><?php esc_html_e( 'Include Database', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Include a database dump in each Vault Lite backup.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-backup-db"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_backup_include_database"
								data-type="bool"
								<?php checked( $get_bool( 'thisismyurl_shadow_backup_include_database', true ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-backup-restore-db"><?php esc_html_e( 'Allow SQL Import During Restore', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Off by default. When disabled, Vault Lite restores site files only and leaves any included database dump untouched unless site policy explicitly allows SQL import.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-backup-restore-db"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_backup_restore_database_allowed"
								data-type="bool"
								<?php checked( $get_bool( 'thisismyurl_shadow_backup_restore_database_allowed', false ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-backup-compress"><?php esc_html_e( 'Compress Backups', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Compress backup archives to save disk space.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-backup-compress"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_backup_compress"
								data-type="bool"
								<?php checked( $get_bool( 'thisismyurl_shadow_backup_compress', true ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-backup-uploads"><?php esc_html_e( 'Include Uploads Folder', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Back up the /uploads directory along with the rest of your site files.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-backup-uploads"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_backup_include_uploads"
								data-type="bool"
								<?php checked( $get_bool( 'thisismyurl_shadow_backup_include_uploads', true ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-backup-verify"><?php esc_html_e( 'Verify Backups', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Verify the integrity of each backup after creation.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-backup-verify"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_backup_verify"
								data-type="bool"
								<?php checked( $get_bool( 'thisismyurl_shadow_backup_verify', true ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-treatment-backup-exclude-uploads"><?php esc_html_e( 'Exclude Uploads from Treatment Backups', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'When a backup is triggered automatically before a treatment, skip the /uploads folder. Treatments never modify uploaded media, so including it only adds unnecessary size.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-treatment-backup-exclude-uploads"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_treatment_backup_exclude_uploads"
								data-type="bool"
								<?php checked( $get_bool( 'thisismyurl_shadow_treatment_backup_exclude_uploads', true ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-treatment-backup-window"><?php esc_html_e( 'Treatment Backup Deduplication Window', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'If a backup already exists within this many minutes, skip creating another one before the next treatment. Prevents N treatments in a session from generating N identical archives.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<select
							id="wps-treatment-backup-window"
							class="wps-auto-save"
							data-option="thisismyurl_shadow_treatment_backup_window"
							data-type="integer"
						>
							<?php
							$window_options = array(
								15  => '15 minutes',
								30  => '30 minutes',
								60  => '1 hour (recommended)',
								120 => '2 hours',
								240 => '4 hours',
								480 => '8 hours',
							);
							$current_window = $get_int( 'thisismyurl_shadow_treatment_backup_window', 60 );
							foreach ( $window_options as $mins => $label ) :
								echo '<option value="' . esc_attr( $mins ) . '"' . selected( $current_window, $mins, false ) . '>' . esc_html( $label ) . '</option>';
							endforeach;
							?>
						</select>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-backup-retention"><?php esc_html_e( 'Retention Period', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Number of days to keep backup files before they are automatically deleted.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<select
							id="wps-backup-retention"
							class="wps-auto-save"
							data-option="thisismyurl_shadow_backup_retention_days"
							data-type="integer"
						>
							<?php
							$retention_options = array(
								1  => '1 day',
								3  => '3 days',
								7  => '7 days (recommended)',
								14 => '14 days',
								30 => '30 days',
								60 => '60 days',
								90 => '90 days',
							);
							$current_retention = $get_int( 'thisismyurl_shadow_backup_retention_days', 7 );
							foreach ( $retention_options as $days => $label ) :
								echo '<option value="' . esc_attr( $days ) . '"' . selected( $current_retention, $days, false ) . '>' . esc_html( $label ) . '</option>';
							endforeach;
							?>
						</select>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-backup-max-size"><?php esc_html_e( 'Maximum Total Size', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Maximum total disk space (MB) that all Vault Lite backups may occupy. Oldest backups are pruned when exceeded.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<div class="wps-input-with-unit">
							<input
								type="number"
								id="wps-backup-max-size"
								class="wps-auto-save small-text"
								data-option="thisismyurl_shadow_backup_max_size_mb"
								data-type="integer"
								min="50"
								max="10000"
								step="50"
								value="<?php echo esc_attr( $get_int( 'thisismyurl_shadow_backup_max_size_mb', 500 ) ); ?>"
							/>
							<span class="wps-input-unit">MB</span>
						</div>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>
			</div>
		</div>

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'Scheduled Backups', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'Run regular automatic backups on a schedule, independent of treatment activity.', 'thisismyurl-shadow' ); ?></p>

			<div class="wps-settings-rows">
				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-backup-schedule"><?php esc_html_e( 'Enable Scheduled Backups', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Run automatic backups on a regular schedule.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-backup-schedule"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_backup_schedule_enabled"
								data-type="bool"
								<?php checked( $get_bool( 'thisismyurl_shadow_backup_schedule_enabled', false ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-backup-freq"><?php esc_html_e( 'Backup Frequency', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'How often to create a scheduled backup.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<select
							id="wps-backup-freq"
							class="wps-auto-save"
							data-option="thisismyurl_shadow_backup_schedule_frequency"
							data-type="string"
						>
							<?php
							$backup_freqs   = array(
								'daily'   => __( 'Daily (recommended)', 'thisismyurl-shadow' ),
								'weekly'  => __( 'Weekly', 'thisismyurl-shadow' ),
								'monthly' => __( 'Monthly', 'thisismyurl-shadow' ),
							);
							$current_bkfreq = $get_str( 'thisismyurl_shadow_backup_schedule_frequency', 'daily' );
							foreach ( $backup_freqs as $bfk => $bfl ) :
								echo '<option value="' . esc_attr( $bfk ) . '"' . selected( $current_bkfreq, $bfk, false ) . '>' . esc_html( $bfl ) . '</option>';
							endforeach;
							?>
						</select>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-backup-time"><?php esc_html_e( 'Backup Time', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'The time of day (24-hour) when scheduled backups run. Choose a low-traffic period.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<input
							type="time"
							id="wps-backup-time"
							class="wps-auto-save"
							data-option="thisismyurl_shadow_backup_schedule_time"
							data-type="string"
							value="<?php echo esc_attr( $get_str( 'thisismyurl_shadow_backup_schedule_time', '02:00' ) ); ?>"
						/>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>
			</div>
		</div>

	</div>
	<?php endif; ?>
</div>
