<?php
/**
 * File Write Review Page â€” View Template
 *
 * Displays pending file-write treatments with before/after diff preview,
 * backup/restore controls, and a gated Apply flow that requires the admin to
 * read and acknowledge SFTP recovery instructions before any file is touched.
 *
 * Variables available in scope (set by File_Write_Review_Page::render()):
 *   $pending  array[]  List of treatment info arrays from File_Write_Registry.
 *
 * @package ThisIsMyURL\Shadow
 * @since 0.6095
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

use ThisIsMyURL\Shadow\Admin\File_Write_Trust;

// Bail if somehow rendered outside the review page context.
if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

if ( ! function_exists( 'get_filesystem_method' ) || ! function_exists( 'wp_is_writable' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}

$guardian_url = admin_url( 'admin.php?page=thisismyurl-shadow-guardian' );
$review_url   = admin_url( 'admin.php?page=thisismyurl-shadow-file-review' );

$preview_manual_enabled = isset( $_GET['thisismyurl_shadow_preview_manual'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['thisismyurl_shadow_preview_manual'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passive preview toggle only.

if ( $preview_manual_enabled ) {
	$pending[] = array(
		'class'                  => '',
		'finding_id'             => 'preview-manual-file-write',
		'target_file'            => THISISMYURL_SHADOW_PATH . 'preview/manual-example-do-not-create.php',
		'file_label'             => __( 'preview/manual-example-do-not-create.php', 'thisismyurl-shadow' ),
		'change_summary'         => __( 'Preview example: manually add a protective config rule', 'thisismyurl-shadow' ),
		'snippet'                => "define( 'DISALLOW_FILE_EDIT', true );",
		'sftp_instructions'      => '',
		'risk_level'             => 'high',
		'is_preview'             => true,
		'manual_reason_override' => __( 'This is a safe preview card added by Shadow by Christopher Ross so you can see how a manual-only file fix will look. It does not point to a real fix and it cannot change anything on your site.', 'thisismyurl-shadow' ),
		'manual_steps_override'  => array(
			__( 'Look at the explanation card and confirm the layout is easy to follow.', 'thisismyurl-shadow' ),
			__( 'Review the sample file path and the code block to make sure the instructions feel clear.', 'thisismyurl-shadow' ),
			__( 'When you are done testing, leave preview mode to return to your real pending fixes.', 'thisismyurl-shadow' ),
		),
	);
}

$preview_manual_url = add_query_arg( 'thisismyurl_shadow_preview_manual', '1', $review_url );
$preview_exit_url   = remove_query_arg( 'thisismyurl_shadow_preview_manual', $review_url );

$build_manual_reason = static function ( bool $file_exists, bool $file_readable, bool $file_writable, string $filesystem_method ): string {
	if ( ! $file_exists ) {
		return __( 'Shadow by Christopher Ross cannot make this change because the target file could not be found on the server. Until WordPress can see the file, it cannot safely edit or verify it for you.', 'thisismyurl-shadow' );
	}

	if ( ! $file_readable ) {
		return __( 'Shadow by Christopher Ross can see that the file exists, but WordPress is not allowed to read it. That means Shadow cannot safely inspect the current contents before making a change.', 'thisismyurl-shadow' );
	}

	if ( ! $file_writable ) {
		return __( 'WordPress can read this file, but the server is blocking write access. This usually means the file permissions or file ownership are locked down, so Shadow by Christopher Ross cannot save the change for you.', 'thisismyurl-shadow' );
	}

	if ( 'direct' !== $filesystem_method ) {
		return __( 'WordPress can inspect this file, but this site does not expose direct filesystem access to the WordPress process. Shadow by Christopher Ross only applies file changes automatically in beta when direct access is available, so this fix needs to be completed with your host file manager or SFTP client.', 'thisismyurl-shadow' );
	}

	return '';
};

$build_manual_steps = static function ( string $file_path, string $file_label, string $filesystem_method ): array {
	$steps = array();

	if ( '' !== $filesystem_method && 'direct' !== $filesystem_method ) {
		$steps[] = __( 'Use your host file manager, SFTP client, or deployment workflow for this change. Shadow by Christopher Ross is intentionally not attempting credential-based filesystem writes during beta.', 'thisismyurl-shadow' );
	}

	return array_merge(
		$steps,
		array(
		sprintf(
			/* translators: %s: file label */
			__( 'Open your hosting File Manager or SFTP client and locate %s.', 'thisismyurl-shadow' ),
			$file_label
		),
		sprintf(
			/* translators: %s: absolute file path */
			__( 'Go to this path: %s', 'thisismyurl-shadow' ),
			$file_path
		),
		__( 'Make a copy of the current file before changing anything so you can roll back if needed.', 'thisismyurl-shadow' ),
		__( 'Update the file so it contains the exact code block shown below for this fix.', 'thisismyurl-shadow' ),
		__( 'Save the file, then reload your site and run Guardian again to confirm the warning is gone.', 'thisismyurl-shadow' ),
		)
	);
};

$actionable = array();
$manual     = array();

foreach ( $pending as $treatment ) {
	$file_path     = (string) $treatment['target_file'];
	$file_exists   = file_exists( $file_path );
	$file_readable = $file_exists && is_readable( $file_path );
	$file_writable = $file_exists && wp_is_writable( $file_path );
	$filesystem_method = $file_exists ? (string) get_filesystem_method( array(), $file_path ) : '';
	$can_auto_apply = $file_exists && $file_readable && $file_writable && 'direct' === $filesystem_method;
	$backup_key    = 'thisismyurl_shadow_file_backup_' . md5( $file_path );
	$backup_data   = get_option( $backup_key, null );
	$has_backup    = is_array( $backup_data ) && ! empty( $backup_data['content'] );
	$backup_at     = $has_backup ? (int) $backup_data['created_at'] : 0;
	$needs_warning = File_Write_Trust::needs_warning( $file_path );

	$prepared = array_merge(
		$treatment,
		array(
			'file_exists'   => $file_exists,
			'file_readable' => $file_readable,
			'file_writable' => $file_writable,
			'filesystem_method' => $filesystem_method,
			'can_auto_apply' => $can_auto_apply,
			'has_backup'    => $has_backup,
			'backup_at'     => $backup_at,
			'needs_warning' => $needs_warning,
			'manual_reason' => isset( $treatment['manual_reason_override'] )
				? (string) $treatment['manual_reason_override']
				: $build_manual_reason( $file_exists, $file_readable, $file_writable, $filesystem_method ),
			'manual_steps'  => isset( $treatment['manual_steps_override'] ) && is_array( $treatment['manual_steps_override'] )
				? $treatment['manual_steps_override']
				: $build_manual_steps( $file_path, (string) $treatment['file_label'], $filesystem_method ),
		)
	);

	if ( $can_auto_apply ) {
		$actionable[] = $prepared;
	} else {
		$manual[] = $prepared;
	}
}

$render_actionable_card = static function ( array $treatment ): void {
	$finding_id        = (string) $treatment['finding_id'];
	$file_path         = (string) $treatment['target_file'];
	$file_label        = (string) $treatment['file_label'];
	$change_summary    = (string) $treatment['change_summary'];
	$snippet           = (string) $treatment['snippet'];
	$sftp_instructions = (string) $treatment['sftp_instructions'];
	$has_backup        = ! empty( $treatment['has_backup'] );
	$backup_at         = (int) ( $treatment['backup_at'] ?? 0 );
	$needs_warning     = ! empty( $treatment['needs_warning'] );
	$restore_classes   = 'button thisismyurl-shadow-btn-restore wps-file-review-restore' . ( $has_backup ? '' : ' wps-file-review-restore--hidden' );
	?>
	<div class="thisismyurl-shadow-file-review-card wps-file-review-card wps-file-review-card--actionable"
		id="thisismyurl-shadow-review-card-<?php echo esc_attr( $finding_id ); ?>"
		data-finding-id="<?php echo esc_attr( $finding_id ); ?>"
		data-file-path="<?php echo esc_attr( $file_path ); ?>">

		<div class="wps-file-review-card-header">
			<div>
				<h2 class="wps-file-review-card-title"><?php echo esc_html( $change_summary ); ?></h2>
				<p class="wps-file-review-path">
					<strong><?php esc_html_e( 'Target file:', 'thisismyurl-shadow' ); ?></strong>
					<code><?php echo esc_html( $file_path ); ?></code>
				</p>
			</div>
			<div class="wps-file-review-pill-group">
				<span class="wps-file-review-pill wps-file-review-pill--success"><?php esc_html_e( 'Shadow by Christopher Ross beta can apply this', 'thisismyurl-shadow' ); ?></span>
				<span class="thisismyurl-shadow-risk-badge wps-file-review-risk">âš  <?php esc_html_e( 'File Write Required', 'thisismyurl-shadow' ); ?></span>
			</div>
		</div>

		<div class="wps-file-review-status-row">
			<span class="wps-file-review-status wps-file-review-status--success">âœ“ <?php esc_html_e( 'Direct filesystem access confirmed', 'thisismyurl-shadow' ); ?></span>
			<?php if ( $has_backup ) : ?>
				<span class="thisismyurl-shadow-backup-status wps-file-review-status wps-file-review-status--success">
					<span aria-hidden="true">âœ“</span>
					<?php esc_html_e( 'Backup created', 'thisismyurl-shadow' ); ?>
					<?php echo ' ' . esc_html( human_time_diff( $backup_at, time() ) . ' ' . __( 'ago', 'thisismyurl-shadow' ) ); ?>
				</span>
			<?php else : ?>
				<span class="thisismyurl-shadow-backup-status wps-file-review-status wps-file-review-status--warning">âš  <?php esc_html_e( 'No backup yet', 'thisismyurl-shadow' ); ?></span>
			<?php endif; ?>
		</div>

		<div class="wps-file-review-section">
			<h3 class="wps-file-review-section-title"><?php esc_html_e( 'Exact Change Shadow Will Make', 'thisismyurl-shadow' ); ?></h3>
			<pre class="wps-file-review-snippet"><?php echo esc_html( $snippet ); ?></pre>
			<p class="wps-file-review-helptext"><?php esc_html_e( 'This is the exact content Shadow by Christopher Ross will write. In beta, this auto-apply path is only available when WordPress has direct filesystem access, so review it first, then preview, back up, and apply when you are ready.', 'thisismyurl-shadow' ); ?></p>
		</div>

		<div class="thisismyurl-shadow-diff-area wps-file-review-diff-area" id="thisismyurl-shadow-diff-<?php echo esc_attr( $finding_id ); ?>">
			<h3 class="wps-file-review-section-title"><?php esc_html_e( 'Dry-Run Preview', 'thisismyurl-shadow' ); ?></h3>
			<div class="thisismyurl-shadow-diff-inner wps-file-review-diff-inner"></div>
		</div>

		<div class="wps-file-review-actions">
			<button type="button" class="button thisismyurl-shadow-btn-dry-run" data-finding-id="<?php echo esc_attr( $finding_id ); ?>">
				<?php esc_html_e( 'Preview Changes', 'thisismyurl-shadow' ); ?>
			</button>
			<button type="button" class="button thisismyurl-shadow-btn-backup" data-finding-id="<?php echo esc_attr( $finding_id ); ?>" data-file-path="<?php echo esc_attr( $file_path ); ?>">
				<?php $has_backup ? esc_html_e( 'Refresh Backup', 'thisismyurl-shadow' ) : esc_html_e( 'Create Backup', 'thisismyurl-shadow' ); ?>
			</button>
			<button type="button" class="<?php echo esc_attr( $restore_classes ); ?>" data-finding-id="<?php echo esc_attr( $finding_id ); ?>" data-file-path="<?php echo esc_attr( $file_path ); ?>">
				<?php esc_html_e( 'Restore from Backup', 'thisismyurl-shadow' ); ?>
			</button>
			<div class="wps-file-review-spacer"></div>
			<button type="button"
				class="button button-primary thisismyurl-shadow-btn-apply"
				data-finding-id="<?php echo esc_attr( $finding_id ); ?>"
				data-file-path="<?php echo esc_attr( $file_path ); ?>"
				data-needs-warning="<?php echo esc_attr( $needs_warning ? '1' : '0' ); ?>"
				data-sftp-instructions="<?php echo esc_attr( $sftp_instructions ); ?>"
				data-file-label="<?php echo esc_attr( $file_label ); ?>">
				<?php esc_html_e( 'Apply Fix', 'thisismyurl-shadow' ); ?>
			</button>
		</div>

		<div class="thisismyurl-shadow-card-status wps-file-review-status-box" id="thisismyurl-shadow-status-<?php echo esc_attr( $finding_id ); ?>" aria-live="polite" aria-atomic="true"></div>
	</div>
	<?php
};

$render_manual_card = static function ( array $treatment ): void {
	$finding_id     = (string) $treatment['finding_id'];
	$file_path      = (string) $treatment['target_file'];
	$change_summary = (string) $treatment['change_summary'];
	$snippet        = (string) $treatment['snippet'];
	$manual_reason  = (string) ( $treatment['manual_reason'] ?? '' );
	$manual_steps   = isset( $treatment['manual_steps'] ) && is_array( $treatment['manual_steps'] ) ? $treatment['manual_steps'] : array();
	$is_preview     = ! empty( $treatment['is_preview'] );
	?>
	<div class="thisismyurl-shadow-file-review-card wps-file-review-card wps-file-review-card--manual" id="thisismyurl-shadow-review-card-<?php echo esc_attr( $finding_id ); ?>">
		<div class="wps-file-review-card-header">
			<div>
				<h2 class="wps-file-review-card-title"><?php echo esc_html( $change_summary ); ?></h2>
				<p class="wps-file-review-path">
					<strong><?php esc_html_e( 'Target file:', 'thisismyurl-shadow' ); ?></strong>
					<code><?php echo esc_html( $file_path ); ?></code>
				</p>
			</div>
			<div class="wps-file-review-pill-group">
				<?php if ( $is_preview ) : ?>
					<span class="wps-file-review-pill wps-file-review-pill--preview"><?php esc_html_e( 'Preview mode', 'thisismyurl-shadow' ); ?></span>
				<?php endif; ?>
				<span class="wps-file-review-pill wps-file-review-pill--manual"><?php esc_html_e( 'Manual update needed', 'thisismyurl-shadow' ); ?></span>
			</div>
		</div>

		<div class="wps-alert wps-alert--warning wps-file-review-manual-alert">
			<div class="wps-alert-icon">!</div>
			<div class="wps-alert-content">
				<strong><?php esc_html_e( 'Why Shadow by Christopher Ross cannot write this file', 'thisismyurl-shadow' ); ?></strong>
				<p class="wps-alert-copy"><?php echo esc_html( $manual_reason ); ?></p>
			</div>
		</div>

		<div class="wps-file-review-two-column">
			<div class="wps-file-review-section">
				<h3 class="wps-file-review-section-title"><?php esc_html_e( 'How To Make This Update Yourself', 'thisismyurl-shadow' ); ?></h3>
				<ol class="wps-file-review-manual-steps">
					<?php foreach ( $manual_steps as $step ) : ?>
						<li><?php echo esc_html( $step ); ?></li>
					<?php endforeach; ?>
				</ol>
				<p class="wps-file-review-helptext"><?php esc_html_e( 'If your host offers cPanel, Plesk, or a file manager, you can use that instead of SFTP. The important part is making the exact file change below.', 'thisismyurl-shadow' ); ?></p>
			</div>

			<div class="wps-file-review-section">
				<h3 class="wps-file-review-section-title"><?php esc_html_e( 'Exact Code To Add Or Update', 'thisismyurl-shadow' ); ?></h3>
				<pre class="wps-file-review-snippet"><?php echo esc_html( $snippet ); ?></pre>
				<p class="wps-file-review-helptext"><?php esc_html_e( 'Copy this block exactly. After you save the file, come back to Guardian and run the check again.', 'thisismyurl-shadow' ); ?></p>
			</div>
		</div>
	</div>
	<?php
};
?>
<style>
	.thisismyurl-shadow-file-review-wrap {
		max-width: 100%;
		margin: 0 auto;
		padding-bottom: 32px;
	}

	.wps-file-review-shell {
		display: grid;
		gap: 24px;
	}

	.wps-file-review-hero {
		background: linear-gradient(135deg, #f7fafc 0%, #eef4ff 45%, #fffdf7 100%);
		border: 1px solid #dbe4f0;
		border-radius: 18px;
		padding: 28px;
		box-shadow: 0 16px 40px rgba(15, 23, 42, 0.06);
	}

	.wps-file-review-hero-actions {
		display: flex;
		gap: 10px;
		flex-wrap: wrap;
		align-items: center;
	}

	.wps-file-review-secondary-link {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		padding: 10px 14px;
		border-radius: 999px;
		border: 1px solid #d4dfec;
		background: rgba(255, 255, 255, 0.88);
		color: #204566;
		font-size: 16px;
		font-weight: 700;
		text-decoration: none;
	}

	.wps-file-review-secondary-link:hover,
	.wps-file-review-secondary-link:focus {
		color: #17324c;
		border-color: #a9bfd8;
	}

	.wps-file-review-hero-top {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 16px;
		flex-wrap: wrap;
		margin-bottom: 18px;
	}

	.wps-file-review-kicker {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 6px 12px;
		border-radius: 999px;
		background: #ffffff;
		border: 1px solid #d8e3f2;
		font-size: 16px;
		font-weight: 700;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		color: #2f5d8a;
	}

	.wps-file-review-title {
		margin: 10px 0 8px;
		font-size: 34px;
		line-height: 1.1;
		color: #132238;
	}

	.wps-file-review-description {
		max-width: 100%;
		margin: 0;
		font-size: 16px;
		line-height: 1.65;
		color: #4e647c;
	}

	.wps-file-review-back-link {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		text-decoration: none;
		color: #204566;
		font-weight: 600;
	}

	.wps-file-review-stats {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
		gap: 14px;
	}

	.wps-file-review-flow {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
		gap: 14px;
		margin-top: 16px;
	}

	.wps-file-review-flow-step {
		background: rgba(255,255,255,0.74);
		border: 1px solid #dde7f1;
		border-radius: 14px;
		padding: 14px 16px;
	}

	.wps-file-review-flow-label {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 100%;
		height: 28px;
		border-radius: 999px;
		background: #17324c;
		color: #fff;
		font-size: 16px;
		font-weight: 700;
		margin-bottom: 10px;
	}

	.wps-file-review-flow-step strong {
		display: block;
		margin-bottom: 4px;
		font-size: 16px;
		color: #132238;
	}

	.wps-file-review-flow-step p {
		margin: 0;
		font-size: 16px;
		line-height: 1.6;
		color: #5d738a;
	}

	.wps-file-review-preview-banner {
		margin-top: 16px;
		padding: 14px 16px;
		border-radius: 14px;
		border: 1px solid #ffe2a8;
		background: linear-gradient(180deg, #fff8e8 0%, #fffdf7 100%);
		color: #7c4a03;
	}

	.wps-file-review-preview-banner strong {
		display: block;
		margin-bottom: 4px;
	}

	.wps-file-review-stat {
		background: rgba(255,255,255,0.82);
		border: 1px solid #dfe8f3;
		border-radius: 14px;
		padding: 16px 18px;
	}

	.wps-file-review-stat-value {
		display: block;
		font-size: 28px;
		font-weight: 700;
		color: #10263d;
	}

	.wps-file-review-stat-label {
		display: block;
		margin-top: 4px;
		font-size: 16px;
		color: #5d738a;
	}

	.wps-file-review-section-block {
		background: #fff;
		border: 1px solid #dfe6ee;
		border-radius: 18px;
		padding: 24px;
		box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
	}

	.wps-file-review-section-block--actionable {
		border-top: 5px solid #1f8f5f;
	}

	.wps-file-review-section-block--manual {
		border-top: 5px solid #d97706;
	}

	.wps-file-review-section-heading {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 12px;
		flex-wrap: wrap;
		margin-bottom: 18px;
	}

	.wps-file-review-section-heading h2 {
		margin: 0 0 6px;
		font-size: 24px;
		color: #132238;
	}

	.wps-file-review-section-heading p {
		margin: 0;
		max-width: 100%;
		color: #546b82;
		line-height: 1.6;
	}

	.wps-file-review-count-badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 100%;
		height: 42px;
		padding: 0 14px;
		border-radius: 999px;
		background: #eef4fb;
		color: #204566;
		font-weight: 700;
	}

	.wps-file-review-count-badge--actionable {
		background: #e9f8ef;
		color: #1f7a4f;
	}

	.wps-file-review-count-badge--manual {
		background: #fff2df;
		color: #a55a00;
	}

	.wps-file-review-list {
		display: grid;
		gap: 20px;
	}

	.wps-file-review-card {
		border: 1px solid #dfe6ee;
		border-radius: 16px;
		background: #fff;
		box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
		overflow: hidden;
		scroll-margin-top: 24px;
	}

	.wps-file-review-card:target {
		box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.14), 0 14px 34px rgba(37, 99, 235, 0.18);
		border-color: #93c5fd;
	}

	.wps-file-review-card--manual {
		background: linear-gradient(180deg, #fffdf8 0%, #ffffff 100%);
	}

	.wps-file-review-card-header {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 16px;
		padding: 22px 24px 18px;
		background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
		border-bottom: 1px solid #edf2f7;
	}

	.wps-file-review-card-title {
		margin: 0 0 8px;
		font-size: 20px;
		line-height: 1.3;
		color: #132238;
	}

	.wps-file-review-path {
		margin: 0;
		font-size: 16px;
		line-height: 1.6;
		color: #5b6f84;
	}

	.wps-file-review-path code,
	.wps-file-review-modal-file-label code {
		display: inline-block;
		padding: 3px 8px;
		border-radius: 8px;
		background: #f4f7fb;
		color: #17324c;
		font-size: 16px;
	}

	.wps-file-review-pill-group {
		display: flex;
		gap: 8px;
		flex-wrap: wrap;
		justify-content: flex-end;
	}

	.wps-file-review-pill {
		display: inline-flex;
		align-items: center;
		padding: 7px 12px;
		border-radius: 999px;
		font-size: 16px;
		font-weight: 700;
	}

	.wps-file-review-pill--success {
		background: #e9f8ef;
		color: #1f7a4f;
	}

	.wps-file-review-pill--manual {
		background: #fff2df;
		color: #a55a00;
	}

	.wps-file-review-pill--preview {
		background: #eef4fb;
		color: #204566;
	}

	.wps-file-review-empty-state {
		padding: 18px;
		border-radius: 16px;
		border: 1px dashed #d6e0eb;
		background: linear-gradient(180deg, #fbfdff 0%, #ffffff 100%);
	}

	.wps-file-review-status-row,
	.wps-file-review-actions,
	.wps-file-review-section,
	.wps-file-review-status-box,
	.wps-file-review-manual-alert,
	.wps-file-review-two-column {
		padding-left: 24px;
		padding-right: 24px;
	}

	.wps-file-review-status-row {
		display: flex;
		gap: 10px;
		flex-wrap: wrap;
		padding-top: 16px;
	}

	.wps-file-review-status {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 8px 12px;
		border-radius: 999px;
		font-size: 16px;
		font-weight: 600;
	}

	.wps-file-review-status--success {
		background: #edf9f1;
		color: #1f7a4f;
	}

	.wps-file-review-status--warning {
		background: #fff4e5;
		color: #9a5a00;
	}

	.wps-file-review-status--error {
		background: #fff0f0;
		color: #b42318;
	}

	.wps-file-review-section {
		padding-top: 18px;
	}

	.wps-file-review-section-title {
		margin: 0 0 10px;
		font-size: 16px;
		font-weight: 700;
		color: #17324c;
	}

	.wps-file-review-snippet {
		margin: 0;
		padding: 18px;
		border-radius: 14px;
		background: #0f172a;
		color: #dbeafe;
		font-size: 16px;
		line-height: 1.65;
		white-space: pre-wrap;
		word-break: break-word;
		overflow: auto;
	}

	.wps-file-review-helptext {
		margin: 10px 0 0;
		font-size: 16px;
		line-height: 1.6;
		color: #61768d;
	}

	.wps-file-review-diff-area {
		display: none;
		padding: 18px 24px 0;
	}

	.wps-file-review-diff-inner {
		border: 1px solid #dce5f0;
		border-radius: 14px;
		overflow: hidden;
		background: #fbfdff;
	}

	.wps-file-review-diff-table {
		width: 100%;
		border-collapse: collapse;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
		font-size: 16px;
	}

	.wps-file-review-diff-prefix {
		width: 100%;
		padding: 10px 12px;
		text-align: center;
		color: #64748b;
		border-right: 1px solid #e5edf6;
	}

	.wps-file-review-diff-content {
		padding: 10px 12px;
		white-space: pre-wrap;
		word-break: break-word;
	}

	.wps-file-review-diff-row--add {
		background: #effcf3;
	}

	.wps-file-review-diff-row--remove {
		background: #fff1f2;
	}

	.wps-file-review-actions {
		display: flex;
		align-items: center;
		gap: 10px;
		flex-wrap: wrap;
		padding-top: 20px;
		padding-bottom: 20px;
	}

	.wps-file-review-spacer {
		flex: 1 1 auto;
	}

	.wps-file-review-status-box {
		display: none;
		padding-top: 0;
		padding-bottom: 22px;
		font-weight: 600;
	}

	.wps-file-review-status-box.is-visible {
		display: block;
	}

	.wps-file-review-status-box--success {
		color: #1f7a4f;
	}

	.wps-file-review-status-box--error {
		color: #b42318;
	}

	.wps-file-review-status-box--info {
		color: #205493;
	}

	.wps-file-review-manual-alert {
		margin: 18px 24px 0;
	}

	.wps-file-review-two-column {
		display: grid;
		grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
		gap: 20px;
		padding-bottom: 22px;
	}

	.wps-file-review-manual-steps {
		margin: 0;
		padding-left: 20px;
		color: #34495e;
		line-height: 1.7;
	}

	.wps-file-review-manual-steps li + li {
		margin-top: 8px;
	}

	.wps-file-review-preferences {
		background: #ffffff;
		border: 1px solid #dde7f1;
		border-radius: 16px;
		padding: 22px 24px;
	}

	.wps-file-review-pref-label {
		display: flex;
		align-items: flex-start;
		gap: 10px;
		font-weight: 600;
		color: #17324c;
	}

	.wps-file-review-pref-note {
		margin: 10px 0 0 28px;
		font-size: 16px;
		line-height: 1.6;
		color: #61768d;
	}

	@media (max-width: 100%) {
		.wps-file-review-two-column {
			grid-template-columns: 1fr;
		}

		.wps-file-review-card-header,
		.wps-file-review-section-heading,
		.wps-file-review-hero-top {
			flex-direction: column;
		}

		.wps-file-review-pill-group {
			justify-content: flex-start;
		}

		.wps-file-review-spacer {
			display: none;
		}
	}
</style>

<div class="wrap thisismyurl-shadow-file-review-wrap">
	<div class="wps-file-review-shell">
		<div class="wps-file-review-hero">
			<div class="wps-file-review-hero-top">
				<div>
					<span class="wps-file-review-kicker"><?php esc_html_e( 'File Change Review', 'thisismyurl-shadow' ); ?></span>
					<h1 class="wps-file-review-title"><?php esc_html_e( 'Review Proposed File Changes', 'thisismyurl-shadow' ); ?></h1>
					<p class="wps-file-review-description"><?php esc_html_e( 'Shadow by Christopher Ross has split these changes into two groups. The first group contains files WordPress can update directly in this beta because the site exposes direct filesystem access. The second group contains files that still need attention, but must be updated manually because WordPress cannot safely write them from inside the site.', 'thisismyurl-shadow' ); ?></p>
				</div>
				<div class="wps-file-review-hero-actions">
					<?php if ( empty( $manual ) && ! $preview_manual_enabled ) : ?>
						<a href="<?php echo esc_url( $preview_manual_url ); ?>" class="wps-file-review-secondary-link"><?php esc_html_e( 'Preview a manual-only example', 'thisismyurl-shadow' ); ?></a>
					<?php elseif ( $preview_manual_enabled ) : ?>
						<a href="<?php echo esc_url( $preview_exit_url ); ?>" class="wps-file-review-secondary-link"><?php esc_html_e( 'Exit preview mode', 'thisismyurl-shadow' ); ?></a>
					<?php endif; ?>
					<a href="<?php echo esc_url( $guardian_url ); ?>" class="wps-file-review-back-link">&larr; <?php esc_html_e( 'Back to Guardian', 'thisismyurl-shadow' ); ?></a>
				</div>
			</div>

			<div class="wps-file-review-stats">
				<div class="wps-file-review-stat">
					<span class="wps-file-review-stat-value"><?php echo (int) count( $actionable ); ?></span>
					<span class="wps-file-review-stat-label"><?php esc_html_e( 'Changes Shadow by Christopher Ross can apply', 'thisismyurl-shadow' ); ?></span>
				</div>
				<div class="wps-file-review-stat">
					<span class="wps-file-review-stat-value"><?php echo (int) count( $manual ); ?></span>
					<span class="wps-file-review-stat-label"><?php esc_html_e( 'Files you need to update yourself', 'thisismyurl-shadow' ); ?></span>
				</div>
				<div class="wps-file-review-stat">
					<span class="wps-file-review-stat-value"><?php echo (int) count( $pending ); ?></span>
					<span class="wps-file-review-stat-label"><?php esc_html_e( 'Total file-based fixes waiting for review', 'thisismyurl-shadow' ); ?></span>
				</div>
			</div>

			<div class="wps-file-review-flow">
				<div class="wps-file-review-flow-step">
					<span class="wps-file-review-flow-label">1</span>
					<strong><?php esc_html_e( 'Review the exact code', 'thisismyurl-shadow' ); ?></strong>
					<p><?php esc_html_e( 'Every card shows the file path and the exact snippet Shadow by Christopher Ross wants to add or change.', 'thisismyurl-shadow' ); ?></p>
				</div>
				<div class="wps-file-review-flow-step">
					<span class="wps-file-review-flow-label">2</span>
					<strong><?php esc_html_e( 'Apply or update manually', 'thisismyurl-shadow' ); ?></strong>
					<p><?php esc_html_e( 'Writable files can be backed up and applied here. Locked files include plain-English instructions for doing it yourself.', 'thisismyurl-shadow' ); ?></p>
				</div>
				<div class="wps-file-review-flow-step">
					<span class="wps-file-review-flow-label">3</span>
					<strong><?php esc_html_e( 'Run Guardian again', 'thisismyurl-shadow' ); ?></strong>
					<p><?php esc_html_e( 'After the file change is made, rerun the check so Shadow by Christopher Ross can confirm the issue is resolved.', 'thisismyurl-shadow' ); ?></p>
				</div>
			</div>

			<?php if ( $preview_manual_enabled ) : ?>
				<div class="wps-file-review-preview-banner">
					<strong><?php esc_html_e( 'Preview mode is on', 'thisismyurl-shadow' ); ?></strong>
					<p><?php esc_html_e( 'Shadow by Christopher Ross added one fake manual card below so you can review the manual-only layout safely. No site files are changed in this mode.', 'thisismyurl-shadow' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="wps-file-review-preview-banner">
				<strong><?php esc_html_e( 'Beta safeguard', 'thisismyurl-shadow' ); ?></strong>
				<p><?php esc_html_e( 'Automatic file changes are intentionally limited to direct filesystem access. If your host requires FTP, SSH, or another credentialed transport, Shadow by Christopher Ross will keep the fix in the manual-review section and show the exact code to apply yourself.', 'thisismyurl-shadow' ); ?></p>
			</div>
		</div>

		<?php if ( empty( $pending ) ) : ?>
			<div class="wps-file-review-section-block">
				<div class="wps-alert wps-alert--success">
					<div class="wps-alert-icon">âœ“</div>
					<div class="wps-alert-content">
						<strong><?php esc_html_e( 'All clear!', 'thisismyurl-shadow' ); ?></strong>
						<p class="wps-alert-copy"><?php esc_html_e( 'There are no pending file-write changes to review at this time.', 'thisismyurl-shadow' ); ?></p>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<section class="wps-file-review-section-block wps-file-review-section-block--actionable">
			<div class="wps-file-review-section-heading">
				<div>
					<h2><?php esc_html_e( 'Things Shadow Can Apply In Beta', 'thisismyurl-shadow' ); ?></h2>
					<p><?php esc_html_e( 'These files are readable, writable, and available through WordPress direct filesystem access. You can preview each change, create a backup, and apply the fix directly from this page.', 'thisismyurl-shadow' ); ?></p>
				</div>
				<span class="wps-file-review-count-badge wps-file-review-count-badge--actionable"><?php echo (int) count( $actionable ); ?></span>
			</div>

			<div class="wps-file-review-list">
				<?php if ( ! empty( $actionable ) ) : ?>
					<?php foreach ( $actionable as $treatment ) : ?>
						<?php $render_actionable_card( $treatment ); ?>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="wps-alert wps-alert--info wps-file-review-empty-state">
						<div class="wps-alert-icon">i</div>
						<div class="wps-alert-content">
							<strong><?php esc_html_e( 'Nothing in this section right now', 'thisismyurl-shadow' ); ?></strong>
							<p class="wps-alert-copy"><?php esc_html_e( 'Shadow by Christopher Ross does not currently have any writable file changes it can apply automatically.', 'thisismyurl-shadow' ); ?></p>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<section class="wps-file-review-section-block wps-file-review-section-block--manual">
			<div class="wps-file-review-section-heading">
				<div>
					<h2><?php esc_html_e( 'Files That Need Manual Updates', 'thisismyurl-shadow' ); ?></h2>
					<p><?php esc_html_e( 'These changes still matter, but WordPress does not have enough direct filesystem access to save them safely in beta. For each one below, Shadow by Christopher Ross explains the reason in plain English and shows the exact code you need to add yourself.', 'thisismyurl-shadow' ); ?></p>
				</div>
				<span class="wps-file-review-count-badge wps-file-review-count-badge--manual"><?php echo (int) count( $manual ); ?></span>
			</div>

			<div class="wps-file-review-list">
				<?php if ( ! empty( $manual ) ) : ?>
					<?php foreach ( $manual as $treatment ) : ?>
						<?php $render_manual_card( $treatment ); ?>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="wps-alert wps-alert--success wps-file-review-empty-state">
						<div class="wps-alert-icon">âœ“</div>
						<div class="wps-alert-content">
							<strong><?php esc_html_e( 'No blocked files right now', 'thisismyurl-shadow' ); ?></strong>
							<p class="wps-alert-copy"><?php esc_html_e( 'Every file-based fix currently in this review can be written by Shadow by Christopher Ross directly. If a future file is locked by permissions, it will appear here with manual steps.', 'thisismyurl-shadow' ); ?></p>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<?php if ( ! empty( $actionable ) ) : ?>
			<div class="wps-file-review-preferences">
				<h3 class="wps-file-review-section-title"><?php esc_html_e( 'Warning Preferences', 'thisismyurl-shadow' ); ?></h3>
				<p class="wps-file-review-path"><?php esc_html_e( 'Once you are comfortable with the file-write process, you can skip the SFTP acknowledgment step for future fixes.', 'thisismyurl-shadow' ); ?></p>
				<label class="wps-file-review-pref-label">
					<input type="checkbox" id="thisismyurl-shadow-trust-all" <?php checked( File_Write_Trust::is_all_trusted() ); ?>>
					<?php esc_html_e( 'Skip SFTP acknowledgment for all future file-write fixes (global)', 'thisismyurl-shadow' ); ?>
				</label>
				<p class="wps-file-review-pref-note"><?php esc_html_e( 'Per-file trust is also available in the confirmation dialog when you apply a specific fix.', 'thisismyurl-shadow' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div><!-- /.wrap -->

<!-- =========================================================
	SFTP Acknowledgment Static Modal
	Opened by JS when Apply is clicked and needs_warning=1.
	The JS populates #thisismyurl-shadow-sftp-modal-instructions before opening.
	========================================================= -->
<div id="thisismyurl-shadow-sftp-modal"
	class="thisismyurl-shadow-static-modal wps-file-review-modal"
	role="dialog"
	aria-modal="true"
	aria-labelledby="thisismyurl-shadow-sftp-modal-title">

	<!-- Overlay -->
	<div class="thisismyurl-shadow-modal-overlay wps-file-review-modal-overlay"></div>

	<!-- Dialog -->
	<div class="wps-file-review-modal-dialog">

		<!-- Header -->
		<div class="wps-file-review-modal-header">
			<span class="wps-file-review-modal-icon">âš </span>
			<div>
				<h2 id="thisismyurl-shadow-sftp-modal-title" class="wps-file-review-modal-title">
					<?php esc_html_e( 'Before You Proceed: Recovery Instructions', 'thisismyurl-shadow' ); ?>
				</h2>
				<p class="wps-file-review-modal-subtitle">
					<?php esc_html_e( 'Please read and store the following SFTP recovery steps in case anything goes wrong.', 'thisismyurl-shadow' ); ?>
				</p>
			</div>
		</div>

		<!-- Body -->
		<div class="wps-file-review-modal-body">

			<div class="wps-file-review-modal-warning">
				<strong><?php esc_html_e( 'Why is this important?', 'thisismyurl-shadow' ); ?></strong>
				<?php esc_html_e( 'If the change causes an issue (e.g. a white screen or redirect loop), you may not be able to access WordPress to undo it. The SFTP method below lets you revert the file even without WordPress running.', 'thisismyurl-shadow' ); ?>
			</div>

			<h3 class="wps-file-review-section-title">
				<?php esc_html_e( 'SFTP Recovery Instructions', 'thisismyurl-shadow' ); ?>
			</h3>

			<div id="thisismyurl-shadow-sftp-modal-file-label" class="wps-file-review-modal-file-label">
				<!-- Populated by JS -->
			</div>

			<ol id="thisismyurl-shadow-sftp-modal-instructions" class="wps-file-review-modal-instructions">
				<!-- Populated by JS -->
			</ol>

			<div class="wps-file-review-modal-fallback">
				<strong><?php esc_html_e( 'If you use cPanel File Manager:', 'thisismyurl-shadow' ); ?></strong><br>
				<?php esc_html_e( 'Log in to your hosting â†’ cPanel â†’ File Manager â†’ navigate to the file â†’ right-click â†’ Edit â†’ paste the original content â†’ Save.', 'thisismyurl-shadow' ); ?>
			</div>

			<!-- Acknowledgment checkboxes -->
			<div class="wps-file-review-modal-acks">
				<label class="wps-file-review-modal-ack">
					<input type="checkbox" id="thisismyurl-shadow-ack-read" class="wps-file-review-modal-ack-input">
					<span><?php esc_html_e( 'I have read these recovery instructions and stored them somewhere safe (e.g. a password manager, printed copy, or a text file outside this site).', 'thisismyurl-shadow' ); ?></span>
				</label>

				<label class="wps-file-review-modal-ack">
					<input type="checkbox" id="thisismyurl-shadow-ack-file-trust" class="wps-file-review-modal-ack-input">
					<span id="thisismyurl-shadow-ack-file-trust-label">
						<?php esc_html_e( 'Skip this warning for this file in future (per-file trust)', 'thisismyurl-shadow' ); ?>
					</span>
				</label>

				<label class="wps-file-review-modal-ack">
					<input type="checkbox" id="thisismyurl-shadow-ack-all-trust" class="wps-file-review-modal-ack-input">
					<span><?php esc_html_e( 'Skip SFTP acknowledgment for all future file-write fixes (global trust)', 'thisismyurl-shadow' ); ?></span>
				</label>
			</div>
		</div>

		<!-- Footer -->
		<div class="wps-file-review-modal-footer">
			<button type="button"
					id="thisismyurl-shadow-sftp-modal-cancel"
					class="button">
				<?php esc_html_e( 'Cancel', 'thisismyurl-shadow' ); ?>
			</button>
			<button type="button"
					id="thisismyurl-shadow-sftp-modal-confirm"
					class="button button-primary"
					disabled>
				<?php esc_html_e( 'I Understand â€” Apply Fix', 'thisismyurl-shadow' ); ?>
			</button>
		</div>

	</div>
</div><!-- /#thisismyurl-shadow-sftp-modal -->

