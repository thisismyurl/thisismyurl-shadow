<?php
/**
 * Settings Page
 *
 * Full settings UI for Christopher Ross Shadow: general options, scan schedule,
 * and per-diagnostic toggles and frequency.
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

// ── Tab detection ─────────────────────────────────────────────────────────
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive tab selection only.
$valid_tabs = array( 'general', 'scanning', 'backups', 'accessibility' );
if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
	$active_tab = 'general';
}

// ── Retrieve option values ─────────────────────────────────────────────────
$scan_config = class_exists( '\ThisIsMyURL\Shadow\Admin\Pages\Scan_Frequency_Manager' )
	? \ThisIsMyURL\Shadow\Admin\Pages\Scan_Frequency_Manager::get_scan_config()
	: get_option( 'thisismyurl_shadow_scan_frequency_settings', array() );

if ( ! is_array( $scan_config ) ) {
	$scan_config = array();
}

$scan_config = wp_parse_args(
	$scan_config,
	array(
		'frequency'             => 'daily',
		'scan_time'             => '02:00',
		'run_diagnostics'       => true,
		'run_treatments'        => true,
		'scan_on_plugin_update' => true,
		'scan_on_theme_update'  => true,
	)
);



$backup_status = class_exists( '\ThisIsMyURL\Shadow\Guardian\Backup_Manager' )
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

$next_backup_display = class_exists( '\ThisIsMyURL\Shadow\Guardian\Backup_Scheduler' )
	? \ThisIsMyURL\Shadow\Guardian\Backup_Scheduler::get_next_scheduled_display()
	: __( 'Scheduler unavailable', 'thisismyurl-shadow' );

/**
 * Helper: read a Christopher Ross Shadow option using the shared settings registry when available.
 *
 * @param  string $option   Option name.
 * @param  mixed  $fallback Default value.
 * @return mixed
 */
function thisismyurl_shadow_settings_value( string $option, $fallback = '' ) {
	if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Settings_Registry' ) ) {
		return \ThisIsMyURL\Shadow\Core\Settings_Registry::get( $option, $fallback );
	}

	$key = 0 === strpos( $option, 'thisismyurl_shadow_' ) ? $option : 'thisismyurl_shadow_' . $option;
	return get_option( $key, $fallback );
}

/**
 * Helper: checked/selected state for a boolean option.
 *
 * @param  string $option   Option name (with or without thisismyurl_shadow_ prefix).
 * @param  bool   $fallback Default value.
 * @return bool
 */
function thisismyurl_shadow_settings_bool( string $option, bool $fallback = true ): bool {
	return (bool) thisismyurl_shadow_settings_value( $option, $fallback );
}

/**
 * Helper: string option value.
 *
 * @param  string $option   Option name.
 * @param  string $fallback Default value.
 * @return string
 */
function thisismyurl_shadow_settings_str( string $option, string $fallback = '' ): string {
	return (string) thisismyurl_shadow_settings_value( $option, $fallback );
}

/**
 * Helper: integer option value.
 *
 * @param  string $option   Option name.
 * @param  int    $fallback Default value.
 * @return int
 */
function thisismyurl_shadow_settings_int( string $option, int $fallback = 0 ): int {
	return (int) thisismyurl_shadow_settings_value( $option, $fallback );
}

// Base settings page URL.
$settings_url = admin_url( 'admin.php?page=thisismyurl-shadow-settings' );

?>
<div class="wrap wps-settings-page">

<?php
thisismyurl_shadow_render_page_header(
	__( 'Settings', 'thisismyurl-shadow' ),
	__( 'Configure Christopher Ross Shadow to match your workflow and site requirements.', 'thisismyurl-shadow' ),
	'dashicons-admin-settings'
);
?>

	<!-- ── Global save indicator ──────────────────────────────────────────── -->
	<div id="wps-settings-notice" class="wps-settings-notice" aria-live="polite"></div>

	<!-- ── Tab navigation ─────────────────────────────────────────────────── -->
	<nav class="wps-settings-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'thisismyurl-shadow' ); ?>">
		<?php
		$settings_tabs = array(
			'general'       => array(
				'label' => __( 'General', 'thisismyurl-shadow' ),
				'icon'  => 'dashicons-admin-generic',
			),
			'scanning'      => array(
				'label' => __( 'Scanning', 'thisismyurl-shadow' ),
				'icon'  => 'dashicons-search',
			),
			'backups'       => array(
				'label' => __( 'Backups', 'thisismyurl-shadow' ),
				'icon'  => 'dashicons-backup',
			),
			'accessibility' => array(
				'label' => __( 'Accessibility', 'thisismyurl-shadow' ),
				'icon'  => 'dashicons-universal-access-alt',
			),
		);
		foreach ( $settings_tabs as $tab_key => $settings_tab ) :
			$href   = add_query_arg( 'tab', $tab_key, $settings_url );
			$active = $active_tab === $tab_key ? ' wps-settings-tab--active' : '';
			?>
			<a
				href="<?php echo esc_url( $href ); ?>"
				class="wps-settings-tab<?php echo esc_attr( $active ); ?>"
				aria-current="<?php echo esc_attr( $active_tab === $tab_key ? 'page' : 'false' ); ?>"
			>
				<span class="dashicons <?php echo esc_attr( $settings_tab['icon'] ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $settings_tab['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<!-- ═══════════════════════════════════════════════════════════════════════
		TAB: GENERAL
		═══════════════════════════════════════════════════════════════════════ -->
	<?php if ( 'general' === $active_tab ) : ?>
	<div class="wps-settings-body">

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'Caching', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'Control how long diagnostic results are kept to avoid re-running expensive checks every visit.', 'thisismyurl-shadow' ); ?></p>

			<div class="wps-settings-rows">

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-cache-enabled"><?php esc_html_e( 'Enable Result Caching', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Cache diagnostic results to speed up page loads. Disable only when actively debugging.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-cache-enabled"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_cache_enabled"
								data-type="bool"
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_cache_enabled', true ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-cache-duration"><?php esc_html_e( 'Cache Duration', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'How long to keep cached diagnostic results before re-running checks.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<select
							id="wps-cache-duration"
							class="wps-auto-save"
							data-option="thisismyurl_shadow_cache_duration"
							data-type="integer"
						>
							<?php
							$durations   = array(
								3600   => __( '1 hour', 'thisismyurl-shadow' ),
								21600  => __( '6 hours', 'thisismyurl-shadow' ),
								43200  => __( '12 hours', 'thisismyurl-shadow' ),
								86400  => __( '24 hours (recommended)', 'thisismyurl-shadow' ),
								172800 => __( '48 hours', 'thisismyurl-shadow' ),
							);
							$current_dur = thisismyurl_shadow_settings_int( 'thisismyurl_shadow_cache_duration', 86400 );
							foreach ( $durations as $secs => $label ) :
								echo '<option value="' . esc_attr( $secs ) . '"' . selected( $current_dur, $secs, false ) . '>' . esc_html( $label ) . '</option>';
							endforeach;
							?>
						</select>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

			</div><!-- .wps-settings-rows -->
		</div><!-- .wps-settings-section -->

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'WordPress File Editors', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'Control access to the built-in WordPress theme and plugin file editors.', 'thisismyurl-shadow' ); ?></p>

			<?php $wp_file_edit_locked = ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) || ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ); ?>
			<div class="wps-settings-rows">

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-theme-editor"><?php esc_html_e( 'Enable Theme File Editor', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint">
							<?php if ( $wp_file_edit_locked ) : ?>
								<?php esc_html_e( 'Disabled by WordPress. To re-enable, remove DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS from wp-config.php.', 'thisismyurl-shadow' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Allow admins to edit theme files directly from the WordPress admin. Disable to reduce attack surface.', 'thisismyurl-shadow' ); ?>
							<?php endif; ?>
						</p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-theme-editor"
								<?php if ( $wp_file_edit_locked ) : ?>
								disabled
								<?php else : ?>
								class="wps-auto-save"
								data-option="thisismyurl_shadow_enable_theme_file_editor"
								data-type="bool"
									<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_enable_theme_file_editor', true ) ); ?>
								<?php endif; ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-plugin-editor"><?php esc_html_e( 'Enable Plugin File Editor', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint">
							<?php if ( $wp_file_edit_locked ) : ?>
								<?php esc_html_e( 'Disabled by WordPress. To re-enable, remove DISALLOW_FILE_EDIT and DISALLOW_FILE_MODS from wp-config.php.', 'thisismyurl-shadow' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'Allow admins to edit plugin files directly from the WordPress admin. Disable to reduce attack surface.', 'thisismyurl-shadow' ); ?>
							<?php endif; ?>
						</p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-plugin-editor"
								<?php if ( $wp_file_edit_locked ) : ?>
								disabled
								<?php else : ?>
								class="wps-auto-save"
								data-option="thisismyurl_shadow_enable_plugin_file_editor"
								data-type="bool"
									<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_enable_plugin_file_editor', true ) ); ?>
								<?php endif; ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

			</div><!-- .wps-settings-rows -->
		</div><!-- .wps-settings-section -->

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'Debug', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'Verbose logging for diagnosing plugin issues. Keep disabled in production.', 'thisismyurl-shadow' ); ?></p>

			<div class="wps-settings-rows">

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-debug-mode"><?php esc_html_e( 'Debug Mode', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Enable verbose logging of all Christopher Ross Shadow operations. Useful when reporting issues.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-debug-mode"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_debug_mode"
								data-type="bool"
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_debug_mode', false ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

			</div><!-- .wps-settings-rows -->
		</div><!-- .wps-settings-section -->

	</div><!-- .wps-settings-body (general) -->
	<?php endif; ?>

	<!-- ═══════════════════════════════════════════════════════════════════════
		TAB: SCANNING
		═══════════════════════════════════════════════════════════════════════ -->
	<?php if ( 'scanning' === $active_tab ) : ?>
	<div class="wps-settings-body">

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'Automatic Scan Schedule', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'Control how often Christopher Ross Shadow runs diagnostic scans in the background.', 'thisismyurl-shadow' ); ?></p>

			<div class="wps-settings-rows">

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-scan-frequency"><?php esc_html_e( 'Scan Frequency', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'How often to run the automatic background scan.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<select
							id="wps-scan-frequency"
							class="wps-save-scan-config"
							data-key="frequency"
						>
							<option value="manual"  <?php selected( $scan_config['frequency'], 'manual' ); ?>><?php esc_html_e( 'Manual only', 'thisismyurl-shadow' ); ?></option>
							<option value="hourly"  <?php selected( $scan_config['frequency'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'thisismyurl-shadow' ); ?></option>
							<option value="daily"   <?php selected( $scan_config['frequency'], 'daily' ); ?>><?php esc_html_e( 'Daily (recommended)', 'thisismyurl-shadow' ); ?></option>
							<option value="weekly"  <?php selected( $scan_config['frequency'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'thisismyurl-shadow' ); ?></option>
						</select>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row" id="wps-scan-time-row">
					<div class="wps-settings-row-label">
						<label for="wps-scan-time"><?php esc_html_e( 'Preferred Scan Time', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'The time of day (24-hour) when automatic scans should run. Choose a low-traffic period.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<input
							type="time"
							id="wps-scan-time"
							class="wps-save-scan-config"
							data-key="scan_time"
							value="<?php echo esc_attr( $scan_config['scan_time'] ); ?>"
						/>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

			</div><!-- .wps-settings-rows -->
		</div><!-- .wps-settings-section -->

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'Scan Behaviour', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'Choose what happens when a scan runs.', 'thisismyurl-shadow' ); ?></p>

			<div class="wps-settings-rows">

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-run-diagnostics"><?php esc_html_e( 'Run Diagnostics', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Execute enabled diagnostic checks during each scan. Strongly recommended.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-run-diagnostics"
								class="wps-save-scan-config"
								data-key="run_diagnostics"
								data-type="bool"
								<?php checked( ! empty( $scan_config['run_diagnostics'] ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-run-treatments"><?php esc_html_e( 'Auto-Apply Safe Treatments', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Automatically apply treatments that are marked safe and have admin approval. Requires backups enabled.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-run-treatments"
								class="wps-save-scan-config"
								data-key="run_treatments"
								data-type="bool"
								<?php checked( ! empty( $scan_config['run_treatments'] ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-scan-plugin-update"><?php esc_html_e( 'Scan After Plugin Update', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Trigger a scan automatically when a plugin is updated, activated, or deactivated.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-scan-plugin-update"
								class="wps-save-scan-config"
								data-key="scan_on_plugin_update"
								data-type="bool"
								<?php checked( ! empty( $scan_config['scan_on_plugin_update'] ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-scan-theme-update"><?php esc_html_e( 'Scan After Theme Update', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Trigger a scan automatically when a theme is updated, activated, or switched.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-scan-theme-update"
								class="wps-save-scan-config"
								data-key="scan_on_theme_update"
								data-type="bool"
								<?php checked( ! empty( $scan_config['scan_on_theme_update'] ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

			</div><!-- .wps-settings-rows -->
		</div><!-- .wps-settings-section -->

	</div><!-- .wps-settings-body (scanning) -->
	<?php endif; ?>

	<!-- ═══════════════════════════════════════════════════════════════════════
		TAB: BACKUPS
		═══════════════════════════════════════════════════════════════════════ -->
	<?php if ( 'backups' === $active_tab ) : ?>
	<div class="wps-settings-body">

		<?php if ( isset( $_GET['thisismyurl_shadow_backup_run'] ) && 'error' === sanitize_key( wp_unslash( $_GET['thisismyurl_shadow_backup_run'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Local backup could not be created.', 'thisismyurl-shadow' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'Local Backup Status', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'Vault Lite stores local-only restore points on this server. No cloud tools are used in this lite version.', 'thisismyurl-shadow' ); ?></p>

			<div class="wps-settings-rows">
				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label><?php esc_html_e( 'Stored Backups', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of retained local backups. */
									_n( '%d local backup currently stored.', '%d local backups currently stored.', (int) $backup_status['count'], 'thisismyurl-shadow' ),
									(int) $backup_status['count']
								)
							);
							?>
						</p>
					</div>
					<div class="wps-settings-row-control">
						<strong><?php echo esc_html( (string) $backup_status['count'] ); ?></strong>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label><?php esc_html_e( 'Disk Usage', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Combined size of all local backup archives currently retained.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<strong><?php echo esc_html( (string) $backup_status['total_size_human'] ); ?></strong>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label><?php esc_html_e( 'Last Backup', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php echo esc_html( (string) $backup_status['last_backup_label'] ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<?php if ( ! empty( $backup_status['last_backup_file'] ) ) : ?>
							<code><?php echo esc_html( (string) $backup_status['last_backup_file'] ); ?></code>
						<?php else : ?>
							<span class="description"><?php esc_html_e( 'No backup file yet', 'thisismyurl-shadow' ); ?></span>
						<?php endif; ?>
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
						<label><?php esc_html_e( 'Backup Location', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'These archives remain on the local server only, inside a secret randomized Vault Lite directory.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<strong><?php echo esc_html( (string) ( $backup_status['directory_public_label'] ?? __( 'Private Vault Lite storage (hidden randomized path)', 'thisismyurl-shadow' ) ) ); ?></strong>
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
			</div><!-- .wps-settings-rows -->
		</div><!-- .wps-settings-section -->

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'Vault Lite Backups', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'Lightweight backups created automatically before each treatment is applied.', 'thisismyurl-shadow' ); ?></p>

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
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_backup_enabled', true ) ); ?>
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
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_backup_include_database', true ) ); ?>
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
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_backup_restore_database_allowed', false ) ); ?>
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
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_backup_compress', true ) ); ?>
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
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_backup_include_uploads', true ) ); ?>
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
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_backup_verify', true ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
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
							$current_retention = thisismyurl_shadow_settings_int( 'thisismyurl_shadow_backup_retention_days', 7 );
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
								value="<?php echo esc_attr( thisismyurl_shadow_settings_int( 'thisismyurl_shadow_backup_max_size_mb', 500 ) ); ?>"
							/>
							<span class="wps-input-unit">MB</span>
						</div>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

			</div><!-- .wps-settings-rows -->
		</div><!-- .wps-settings-section -->

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
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_backup_schedule_enabled', false ) ); ?>
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
							$current_bkfreq = thisismyurl_shadow_settings_str( 'thisismyurl_shadow_backup_schedule_frequency', 'daily' );
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
							value="<?php echo esc_attr( thisismyurl_shadow_settings_str( 'thisismyurl_shadow_backup_schedule_time', '02:00' ) ); ?>"
						/>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

			</div><!-- .wps-settings-rows -->
		</div><!-- .wps-settings-section -->

	</div><!-- .wps-settings-body (backups) -->
	<?php endif; ?>

	<!-- ═══════════════════════════════════════════════════════════════════════
		TAB: ACCESSIBILITY
		═══════════════════════════════════════════════════════════════════════ -->
	<?php if ( 'accessibility' === $active_tab ) : ?>
	<div class="wps-settings-body">

		<?php
		$current_font_choice = thisismyurl_shadow_settings_str( 'thisismyurl_shadow_admin_font_family', 'default' );
		$current_font_scale  = sprintf( '%.2F', (float) get_option( 'thisismyurl_shadow_font_size_multiplier', 1.0 ) );
		$current_focus_style = thisismyurl_shadow_settings_str( 'thisismyurl_shadow_focus_indicators', 'standard' );
		?>

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'Reading Comfort & Focus', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'These options can be applied across WordPress admin screens, not just Christopher Ross Shadow. They are optional comfort aids — what helps one person read or focus more easily may not help another.', 'thisismyurl-shadow' ); ?></p>

			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'The focus-friendly font option uses a local readable font stack inspired by styles some ADHD or dyslexic users report as easier to track. No external font files are loaded.', 'thisismyurl-shadow' ); ?></p>
			</div>

			<div class="wps-settings-rows">

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-admin-font-family"><?php esc_html_e( 'Admin Reading Font', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Choose a more readable font style for the WordPress admin. The focus-friendly option uses a Lexend / Atkinson-style stack with roomier letter spacing.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<select
							id="wps-admin-font-family"
							class="wps-auto-save"
							data-option="thisismyurl_shadow_admin_font_family"
							data-type="string"
						>
							<option value="default" <?php selected( $current_font_choice, 'default' ); ?>><?php esc_html_e( 'WordPress default', 'thisismyurl-shadow' ); ?></option>
							<option value="readable" <?php selected( $current_font_choice, 'readable' ); ?>><?php esc_html_e( 'Readable sans-serif', 'thisismyurl-shadow' ); ?></option>
							<option value="lexend" <?php selected( $current_font_choice, 'lexend' ); ?>><?php esc_html_e( 'Focus-friendly reading stack', 'thisismyurl-shadow' ); ?></option>
						</select>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-font-size-multiplier"><?php esc_html_e( 'Text Size', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Increase the text size used across the WordPress admin to reduce strain and improve readability.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<select
							id="wps-font-size-multiplier"
							class="wps-auto-save"
							data-option="thisismyurl_shadow_font_size_multiplier"
							data-type="string"
						>
							<option value="1.00" <?php selected( $current_font_scale, '1.00' ); ?>><?php esc_html_e( '100% (default)', 'thisismyurl-shadow' ); ?></option>
							<option value="1.10" <?php selected( $current_font_scale, '1.10' ); ?>><?php esc_html_e( '110%', 'thisismyurl-shadow' ); ?></option>
							<option value="1.25" <?php selected( $current_font_scale, '1.25' ); ?>><?php esc_html_e( '125%', 'thisismyurl-shadow' ); ?></option>
							<option value="1.40" <?php selected( $current_font_scale, '1.40' ); ?>><?php esc_html_e( '140%', 'thisismyurl-shadow' ); ?></option>
						</select>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-focus-indicators"><?php esc_html_e( 'Focus Indicator Strength', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Make keyboard focus outlines easier to see when tabbing through buttons, links, and form fields.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<select
							id="wps-focus-indicators"
							class="wps-auto-save"
							data-option="thisismyurl_shadow_focus_indicators"
							data-type="string"
						>
							<option value="standard" <?php selected( $current_focus_style, 'standard' ); ?>><?php esc_html_e( 'Standard', 'thisismyurl-shadow' ); ?></option>
							<option value="enhanced" <?php selected( $current_focus_style, 'enhanced' ); ?>><?php esc_html_e( 'Enhanced', 'thisismyurl-shadow' ); ?></option>
							<option value="maximum" <?php selected( $current_focus_style, 'maximum' ); ?>><?php esc_html_e( 'Maximum', 'thisismyurl-shadow' ); ?></option>
						</select>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-high-contrast-mode"><?php esc_html_e( 'High Contrast Mode', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Increase contrast in admin text, controls, and notices to improve visibility.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-high-contrast-mode"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_high_contrast_mode"
								data-type="bool"
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_high_contrast_mode', false ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-reduce-motion"><?php esc_html_e( 'Reduce Motion', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Reduce animations and transitions in the WordPress admin for people who are motion-sensitive or who focus better with calmer interfaces.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-reduce-motion"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_reduce_motion"
								data-type="bool"
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_reduce_motion', false ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

			</div><!-- .wps-settings-rows -->
		</div><!-- .wps-settings-section -->

		<div class="wps-settings-section">
			<h2 class="wps-settings-section-title"><?php esc_html_e( 'Clarity & Inclusion', 'thisismyurl-shadow' ); ?></h2>
			<p class="wps-settings-section-desc"><?php esc_html_e( 'Accessibility is broader than one setting or one diagnosis. These options aim to reduce friction, improve clarity, and give you a calmer, more usable admin experience.', 'thisismyurl-shadow' ); ?></p>

			<div class="wps-settings-rows">
				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-screen-reader-optimization"><?php esc_html_e( 'Screen Reader Friendly Enhancements', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Enhance skip-focus visibility and a few admin feedback patterns to work more smoothly with assistive technology.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-screen-reader-optimization"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_screen_reader_optimization"
								data-type="bool"
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_screen_reader_optimization', false ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>

				<div class="wps-settings-row">
					<div class="wps-settings-row-label">
						<label for="wps-simplified-ui"><?php esc_html_e( 'Simplified Admin Feel', 'thisismyurl-shadow' ); ?></label>
						<p class="wps-settings-row-hint"><?php esc_html_e( 'Reduce some visual clutter and use calmer spacing to make admin screens easier to scan.', 'thisismyurl-shadow' ); ?></p>
					</div>
					<div class="wps-settings-row-control">
						<label class="wps-toggle-switch">
							<input
								type="checkbox"
								id="wps-simplified-ui"
								class="wps-auto-save"
								data-option="thisismyurl_shadow_simplified_ui"
								data-type="bool"
								<?php checked( thisismyurl_shadow_settings_bool( 'thisismyurl_shadow_simplified_ui', false ) ); ?>
							/>
							<span class="wps-toggle-slider" aria-hidden="true"></span>
						</label>
						<span class="wps-save-status" aria-live="polite"></span>
					</div>
				</div>
			</div>
		</div>

	</div><!-- .wps-settings-body (accessibility) -->
	<?php endif; ?>

	<!-- Governance Report Section -->
	<div id="wps-governance-report" class="wps-settings-body wps-governance-report">
		<h2 class="wps-governance-title">
			<?php esc_html_e( 'Governance & Compliance Report', 'thisismyurl-shadow' ); ?>
		</h2>
		<p class="wps-governance-intro">
			<?php esc_html_e( 'Export a comprehensive readiness inventory of all diagnostics and treatments for compliance and audit purposes.', 'thisismyurl-shadow' ); ?>
		</p>

		<div id="wps-readiness-summary-report" class="wps-governance-panel">
			<div class="wps-governance-summary-grid">
				<div class="wps-governance-stat-card wps-governance-stat-card--success">
					<div class="wps-governance-stat-label"><?php esc_html_e( 'Production Diagnostics', 'thisismyurl-shadow' ); ?></div>
					<div class="wps-governance-stat-value" data-count-prod-diag>0</div>
				</div>
				<div class="wps-governance-stat-card wps-governance-stat-card--warning">
					<div class="wps-governance-stat-label"><?php esc_html_e( 'Beta Diagnostics', 'thisismyurl-shadow' ); ?></div>
					<div class="wps-governance-stat-value" data-count-beta-diag>0</div>
				</div>
				<div class="wps-governance-stat-card wps-governance-stat-card--danger">
					<div class="wps-governance-stat-label"><?php esc_html_e( 'Planned Diagnostics', 'thisismyurl-shadow' ); ?></div>
					<div class="wps-governance-stat-value" data-count-planned-diag>0</div>
				</div>
				<div class="wps-governance-stat-card wps-governance-stat-card--success">
					<div class="wps-governance-stat-label"><?php esc_html_e( 'Production Treatments', 'thisismyurl-shadow' ); ?></div>
					<div class="wps-governance-stat-value" data-count-prod-treat>0</div>
				</div>
				<div class="wps-governance-stat-card wps-governance-stat-card--warning">
					<div class="wps-governance-stat-label"><?php esc_html_e( 'Beta Treatments', 'thisismyurl-shadow' ); ?></div>
					<div class="wps-governance-stat-value" data-count-beta-treat>0</div>
				</div>
				<div class="wps-governance-stat-card wps-governance-stat-card--danger">
					<div class="wps-governance-stat-label"><?php esc_html_e( 'Planned Treatments', 'thisismyurl-shadow' ); ?></div>
					<div class="wps-governance-stat-value" data-count-planned-treat>0</div>
				</div>
			</div>
		</div>

		<div id="wps-treatment-maturity-summary" class="wps-governance-panel">
			<div class="wps-governance-meta-label"><?php esc_html_e( 'Treatment Maturity Coverage', 'thisismyurl-shadow' ); ?></div>
			<div class="wps-governance-treatment-grid">
				<div class="wps-governance-stat-card wps-governance-stat-card--success">
					<div class="wps-governance-stat-label"><?php esc_html_e( 'Automated', 'thisismyurl-shadow' ); ?></div>
					<div class="wps-governance-stat-value" data-count-treat-shipped>—</div>
					<div class="wps-governance-note"><?php esc_html_e( 'apply + undo implemented', 'thisismyurl-shadow' ); ?></div>
				</div>
				<div class="wps-governance-stat-card wps-governance-stat-card--warning">
					<div class="wps-governance-stat-label"><?php esc_html_e( 'Guidance-Only', 'thisismyurl-shadow' ); ?></div>
					<div class="wps-governance-stat-value" data-count-treat-guidance>—</div>
					<div class="wps-governance-note"><?php esc_html_e( 'returns manual steps only', 'thisismyurl-shadow' ); ?></div>
				</div>
				<div class="wps-governance-stat-card wps-governance-stat-card--info">
					<div class="wps-governance-stat-label"><?php esc_html_e( 'Reversible', 'thisismyurl-shadow' ); ?></div>
					<div class="wps-governance-stat-value" data-count-treat-reversible>—</div>
					<div class="wps-governance-note"><?php esc_html_e( 'undo() fully restores state', 'thisismyurl-shadow' ); ?></div>
				</div>
			</div>
			<div class="wps-governance-meta-label"><?php esc_html_e( 'Risk Distribution', 'thisismyurl-shadow' ); ?></div>
			<div class="wps-governance-pill-row">
				<span class="wps-governance-pill wps-governance-pill--safe"><?php esc_html_e( 'Safe:', 'thisismyurl-shadow' ); ?> <span data-count-treat-safe>—</span></span>
				<span class="wps-governance-pill wps-governance-pill--moderate"><?php esc_html_e( 'Moderate:', 'thisismyurl-shadow' ); ?> <span data-count-treat-moderate>—</span></span>
				<span class="wps-governance-pill wps-governance-pill--high"><?php esc_html_e( 'High:', 'thisismyurl-shadow' ); ?> <span data-count-treat-high>—</span></span>
				<span class="wps-governance-pill wps-governance-pill--guidance"><?php esc_html_e( 'Guidance:', 'thisismyurl-shadow' ); ?> <span data-count-treat-guidance-risk>—</span></span>
			</div>
		</div>

		<div class="wps-governance-actions">
			<button type="button" class="button button-primary" id="wps-export-inventory-json">
				<?php esc_html_e( 'Export as JSON', 'thisismyurl-shadow' ); ?>
			</button>
			<button type="button" class="button" id="wps-export-inventory-csv">
				<?php esc_html_e( 'Export as CSV', 'thisismyurl-shadow' ); ?>
			</button>
			<button type="button" class="button" id="wps-refresh-readiness-summary">
				<?php esc_html_e( 'Refresh Summary', 'thisismyurl-shadow' ); ?>
			</button>
		</div>
		<p id="wps-export-status" class="wps-governance-status"></p>

		<div class="wps-readiness-sections">
			<?php
			$states       = array( 'production', 'beta', 'planned' );
			$state_labels = array(
				'production' => __( 'Production', 'thisismyurl-shadow' ),
				'beta'       => __( 'Beta', 'thisismyurl-shadow' ),
				'planned'    => __( 'Planned', 'thisismyurl-shadow' ),
			);
			?>
			<?php foreach ( $states as $state ) : ?>
				<div class="wps-readiness-section wps-readiness-section--<?php echo esc_attr( $state ); ?>">
					<button
						type="button"
						class="wps-readiness-section-toggle"
						data-state="<?php echo esc_attr( $state ); ?>"
						aria-expanded="false"
					>
						<span class="wps-readiness-section-title">
							<span class="wps-readiness-state-dot wps-readiness-state-dot--<?php echo esc_attr( $state ); ?>"></span>
							<?php echo esc_html( $state_labels[ $state ] ); ?>
						</span>
						<span class="wps-toggle-arrow">▼</span>
					</button>
					<div class="wps-readiness-section-content" data-state="<?php echo esc_attr( $state ); ?>">
						<div class="wps-inventory-list">
							<?php esc_html_e( 'Loading...', 'thisismyurl-shadow' ); ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>


</div><!-- .wps-settings-page -->
