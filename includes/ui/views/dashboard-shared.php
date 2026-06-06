<?php
/**
 * Shared Dashboard/Guardian helper functions.
 *
 * This file contains non-rendering helpers used by the v2 dashboard and
 * guardian views.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Views
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load category metadata functions (global aliases).
require_once THISISMYURL_SHADOW_PATH . 'includes/systems/core/functions-category-metadata.php';

/**
 * Build scheduler run key from fully-qualified diagnostic class name.
 *
 * @since  0.6095
 * @param  string $class_name Diagnostic class name.
 * @return string
 */
function thisismyurl_shadow_get_diagnostic_run_key_from_class( string $class_name ): string {
	$short_name = str_replace( 'ThisIsMyURL\\Shadow\\Diagnostics\\', '', $class_name );
	$short_name = strtolower( str_replace( '_', '-', $short_name ) );

	return sanitize_key( $short_name );
}

/**
 * Format a timestamp in human-friendly relative text with a precise title.
 *
 * @since  0.6095
 * @param  int    $timestamp Unix timestamp.
 * @return string
 */
function thisismyurl_shadow_format_human_time( int $timestamp ): string {
	if ( $timestamp <= 0 ) {
		return esc_html__( 'Never', 'thisismyurl-shadow' );
	}

	$now      = time();
	$relative = $timestamp > $now
		? sprintf(
			/* translators: %s: human time difference */
			esc_html__( 'in %s', 'thisismyurl-shadow' ),
			human_time_diff( $now, $timestamp )
		)
		: sprintf(
			/* translators: %s: human time difference */
			esc_html__( '%s ago', 'thisismyurl-shadow' ),
			human_time_diff( $timestamp, $now )
		);

	$precise = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );

	return sprintf(
		'<span title="%s">%s</span>',
		esc_attr( $precise ),
		esc_html( $relative )
	);
}

/**
 * Build fallback explanation sections when a diagnostic does not provide them.
 *
 * @since  0.6094
 * @param  string $run_key        Diagnostic run key.
 * @param  string $name           Diagnostic display name.
 * @param  string $description    Diagnostic description.
 * @param  string $family         Diagnostic family slug.
 * @param  string $status_raw     Diagnostic status.
 * @param  string $failure_reason Failure reason text.
 * @param  string $confidence     Confidence level.
 * @param  bool   $is_core        Whether this is a core diagnostic.
 * @return array<string, string>
 */
function thisismyurl_shadow_build_explanation_sections_fallback(
	string $run_key,
	string $name,
	string $description,
	string $family,
	string $status_raw,
	string $failure_reason,
	string $confidence,
	bool $is_core
): array {
	$family_label = '' !== trim( $family )
		? strtolower( str_replace( '-', ' ', trim( $family ) ) )
		: strtolower( __( 'site health', 'thisismyurl-shadow' ) );

	if ( 'failed' === $status_raw ) {
		$status_phrase = __( 'currently reporting an issue', 'thisismyurl-shadow' );
	} elseif ( 'passed' === $status_raw ) {
		$status_phrase = __( 'currently passing', 'thisismyurl-shadow' );
	} elseif ( 'disabled' === $status_raw ) {
		$status_phrase = __( 'currently disabled', 'thisismyurl-shadow' );
	} else {
		$status_phrase = __( 'still collecting results', 'thisismyurl-shadow' );
	}

	$core_phrase = $is_core
		? __( 'It is part of Christopher Ross Shadow core coverage, so changes here usually deserve priority.', 'thisismyurl-shadow' )
		: __( 'It is part of the extended coverage set, useful for deeper hardening and operational quality.', 'thisismyurl-shadow' );
	$automation_constraint = thisismyurl_shadow_get_automation_constraint_reason(
		$run_key,
		$name,
		$description,
		$family,
		$failure_reason
	);

	$summary = '' !== trim( $description )
		? sprintf(
			/* translators: 1: diagnostic name, 2: diagnostic description, 3: status phrase */
			__( '%1$s checks the following: %2$s Right now this check is %3$s.', 'thisismyurl-shadow' ),
			$name,
			$description,
			$status_phrase
		)
		: sprintf(
			/* translators: 1: diagnostic name, 2: status phrase */
			__( '%1$s is %2$s. This check helps confirm that the related system behavior stays intentional and safe over time.', 'thisismyurl-shadow' ),
			$name,
			$status_phrase
		);

	$how_tested = sprintf(
		/* translators: 1: diagnostic name, 2: confidence level */
		__( 'Christopher Ross Shadow evaluates %1$s using deterministic rules inside the diagnostic class and records the latest result for dashboard reporting. Confidence for this check is marked as %2$s, which indicates how much direct evidence the test can gather versus inferred signals.', 'thisismyurl-shadow' ),
		$name,
		$confidence
	);

	$why_matters = sprintf(
		/* translators: 1: diagnostic family label, 2: core or extended coverage phrase */
		__( 'This matters because gaps in %1$s controls often compound quietly before they become visible outages or security events. %2$s Consistent monitoring gives you earlier warning and safer remediation windows.', 'thisismyurl-shadow' ),
		$family_label,
		$core_phrase
	);

	$signal_text = trim( $failure_reason );
	if ( '' === $signal_text ) {
		$signal_text = trim( $description );
	}
	if ( '' === $signal_text ) {
		$signal_text = __( 'Christopher Ross Shadow detected a configuration issue in this area.', 'thisismyurl-shadow' );
	}

	$guidance_haystack = strtolower(
		$run_key . ' ' . $name . ' ' . $description . ' ' . $failure_reason
	);
	$issue_specific_fix = '';

	if ( false !== strpos( $guidance_haystack, 'cache' ) ) {
		$issue_specific_fix = __( 'Open your caching plugin settings (or your host performance panel) and enable full-page caching for public pages. Exclude checkout, cart, account, and other logged-in/dynamic pages from page cache. Save changes, clear cache, then visit your homepage in an incognito window to confirm pages load normally.', 'thisismyurl-shadow' );
	} elseif (
		false !== strpos( $guidance_haystack, 'cron' )
		|| false !== strpos( $guidance_haystack, 'scheduled' )
	) {
		$issue_specific_fix = __( 'Set up a real server cron job to run wp-cron.php every 5 minutes, then add DISABLE_WP_CRON=true in wp-config.php so WordPress does not rely on visitor traffic. If your host has a cron UI, use that panel; if not, ask hosting support to add it for you. This stabilizes background tasks like emails, cleanups, and renewals.', 'thisismyurl-shadow' );
	} elseif (
		false !== strpos( $guidance_haystack, 'admin-account' )
		|| false !== strpos( $guidance_haystack, 'administrator' )
	) {
		$issue_specific_fix = __( 'Go to Users > All Users and review every Administrator account. Keep only owners or trusted technical maintainers as Administrators, and downgrade other users to Editor/Author/Shop Manager where possible. Enable two-factor authentication for remaining admin accounts and remove any accounts you do not recognize.', 'thisismyurl-shadow' );
	} elseif (
		false !== strpos( $guidance_haystack, 'autoload' )
		|| false !== strpos( $guidance_haystack, 'options' )
	) {
		$issue_specific_fix = __( 'Create a fresh backup, then identify large autoloaded options (usually from old or heavy plugins). Remove plugin settings you no longer need, and avoid manual database edits unless you are sure a setting is unused. After cleanup, confirm your site still works and then re-run this check to verify autoload size dropped.', 'thisismyurl-shadow' );
	} elseif ( false !== strpos( $guidance_haystack, 'transient' ) ) {
		$issue_specific_fix = __( 'Run a safe transient cleanup using a maintenance plugin like WP-Optimize, then monitor whether expired transients return quickly. If they do, a plugin is likely creating too many temporary entries and may need configuration changes. This cleanup is usually safe, but always keep a backup before bulk database actions.', 'thisismyurl-shadow' );
	} elseif (
		false !== strpos( $guidance_haystack, 'orphaned' )
		|| false !== strpos( $guidance_haystack, 'user meta' )
	) {
		$issue_specific_fix = __( 'Back up the site first, then remove orphaned metadata with a trusted database cleanup tool or by asking your developer/host to run a targeted cleanup query. Also review any custom user-deletion workflow so metadata is cleaned when users are removed in the future.', 'thisismyurl-shadow' );
	} elseif (
		false !== strpos( $guidance_haystack, 'ssl' )
		|| false !== strpos( $guidance_haystack, 'https' )
		|| false !== strpos( $guidance_haystack, 'certificate' )
	) {
		$issue_specific_fix = __( 'Enable HTTPS in your hosting control panel and make sure your SSL certificate is valid and auto-renewing. In WordPress Settings > General, confirm both WordPress Address and Site Address start with https://. Then clear caches and confirm there are no mixed-content warnings in your browser.', 'thisismyurl-shadow' );
	} elseif (
		false !== strpos( $guidance_haystack, 'seo' )
		|| false !== strpos( $guidance_haystack, 'meta' )
		|| false !== strpos( $guidance_haystack, 'sitemap' )
	) {
		$issue_specific_fix = __( 'Open your SEO plugin and complete its setup wizard (titles, site type, indexing rules, and sitemap). Ensure important pages are indexable and not blocked by noindex settings. Then request a recrawl in Google Search Console after changes are published.', 'thisismyurl-shadow' );
	} elseif (
		false !== strpos( $guidance_haystack, 'accessibility' )
		|| false !== strpos( $guidance_haystack, 'contrast' )
		|| false !== strpos( $guidance_haystack, 'alt text' )
	) {
		$issue_specific_fix = __( 'Update the relevant page/template so content is readable and navigable: improve color contrast, add descriptive alt text to informative images, and ensure buttons/links have clear labels. Test with keyboard-only navigation and then run the diagnostic again to confirm the issue is resolved.', 'thisismyurl-shadow' );
	}

	if ( '' === $issue_specific_fix ) {
		$family_fix_map = array(
			'security'         => __( 'Open WordPress admin and review users, plugins, and security settings first. Remove anything you do not use, tighten permissions, and enable stronger login protection (such as two-factor authentication) where available.', 'thisismyurl-shadow' ),
			'performance'      => __( 'Apply one performance change at a time (caching, image optimization, script cleanup), test the site after each change, and keep the change only if pages remain correct and load faster.', 'thisismyurl-shadow' ),
			'database'         => __( 'Create a backup, then run a trusted database optimization/cleanup workflow for the specific table or record type involved. Avoid mass deletion without a rollback point.', 'thisismyurl-shadow' ),
			'workflows'        => __( 'Check scheduled tasks and automation settings first, then confirm background jobs are running on time. Workflow issues are often resolved by fixing cron reliability and cleaning old queue entries.', 'thisismyurl-shadow' ),
			'seo'              => __( 'Review SEO plugin settings and page-level metadata, then verify indexability and sitemap health in Search Console. Focus on one change set at a time so you can confirm what fixed the issue.', 'thisismyurl-shadow' ),
			'accessibility'    => __( 'Update content and theme output so visitors can read and operate the page without barriers, then test with keyboard navigation and an accessibility scanner before marking complete.', 'thisismyurl-shadow' ),
			'design'           => __( 'Adjust the template or component in a staging environment first, preview on desktop and mobile, and publish only after confirming layout and readability remain strong.', 'thisismyurl-shadow' ),
			'settings'         => __( 'Review the related WordPress or plugin setting carefully, compare with recommended defaults, and save only the minimal change needed to resolve the warning.', 'thisismyurl-shadow' ),
			'monitoring'       => __( 'Confirm logging and health checks are enabled and reachable, then verify alerts are being recorded so future regressions are caught quickly.', 'thisismyurl-shadow' ),
			'code-quality'     => __( 'Update the related plugin/theme code path or setting that triggered this warning, then retest affected pages and run diagnostics again to validate the fix.', 'thisismyurl-shadow' ),
			'wordpress-health' => __( 'Use Tools > Site Health as your starting point, apply the recommended change there, and then re-check this diagnostic to confirm WordPress reports healthy status.', 'thisismyurl-shadow' ),
		);

		$family_key = sanitize_key( $family );
		$issue_specific_fix = $family_fix_map[ $family_key ] ?? __(
			'Review the issue message carefully, make the smallest safe adjustment in settings or plugins, and confirm site behavior before and after the change.',
			'thisismyurl-shadow'
		);
	}

	$how_to_fix = 'failed' === $status_raw
		? sprintf(
			/* translators: 1: issue signal text, 2: issue-specific beginner guidance, 3: automation constraint explanation */
			__( 'What Christopher Ross Shadow found: %1$s. Recommended next step: %2$s Why Christopher Ross Shadow is not auto-fixing this: %3$s After you make this change, wait for the next scheduled run to confirm the result changes to Passed.', 'thisismyurl-shadow' ),
			$signal_text,
			$issue_specific_fix,
			$automation_constraint
		)
		: __( 'No immediate fix is required right now. Keep this check enabled and run it again after major site updates so you can catch regressions early, while they are still easy to reverse.', 'thisismyurl-shadow' );

	return array(
		'summary'              => $summary,
		'how_wp_shadow_tested' => $how_tested,
		'why_it_matters'       => $why_matters,
		'how_to_fix_it'        => $how_to_fix,
	);
}

/**
 * Explain why Christopher Ross Shadow is not applying a diagnostic fix automatically.
 *
 * @since 0.7056
 * @param string $run_key        Diagnostic run key.
 * @param string $name           Diagnostic display name.
 * @param string $description    Diagnostic description.
 * @param string $family         Diagnostic family slug.
 * @param string $failure_reason Failure reason text.
 * @return string
 */
function thisismyurl_shadow_get_automation_constraint_reason(
	string $run_key,
	string $name,
	string $description,
	string $family,
	string $failure_reason
): string {
	$metadata_note = '';
	if ( class_exists( '\ThisIsMyURL\Shadow\Core\Diagnostic_Metadata' ) ) {
		$meta = \ThisIsMyURL\Shadow\Core\Diagnostic_Metadata::get( $run_key );
		if ( is_array( $meta ) && ! empty( $meta['notes'] ) ) {
			$metadata_note = trim( (string) $meta['notes'] );
		}
	}

	$has_input_path = false;
	if ( class_exists( '\ThisIsMyURL\Shadow\Core\Treatment_Input_Requirements' ) ) {
		$requirements   = \ThisIsMyURL\Shadow\Core\Treatment_Input_Requirements::get_for_finding( $run_key );
		$has_input_path = ! empty( $requirements['fields'] ) && is_array( $requirements['fields'] );
	}

	if ( $has_input_path ) {
		$reason = __( 'the correct value depends on your site intent, naming, or environment details, so Christopher Ross Shadow needs your input before it can safely write the setting', 'thisismyurl-shadow' );
		if ( '' !== $metadata_note ) {
			$reason .= ' ' . sprintf(
				/* translators: %s: diagnostic metadata note */
				__( 'Diagnostic note: %s.', 'thisismyurl-shadow' ),
				$metadata_note
			);
		}

		return $reason;
	}

	$tx_maturity = '';
	if ( class_exists( '\ThisIsMyURL\Shadow\Treatments\Treatment_Registry' ) ) {
		$tx_class = \ThisIsMyURL\Shadow\Treatments\Treatment_Registry::get_treatment( $run_key );
		if ( null !== $tx_class && class_exists( '\ThisIsMyURL\Shadow\Core\Treatment_Metadata' ) ) {
			$tx_meta = \ThisIsMyURL\Shadow\Core\Treatment_Metadata::get( $run_key );
			if ( is_array( $tx_meta ) && ! empty( $tx_meta['maturity'] ) ) {
				$tx_maturity = (string) $tx_meta['maturity'];
			}
		}
	}

	if ( 'guidance' === $tx_maturity ) {
		$reason = __( 'this change depends on hosting, filesystem access, external services, or operator review, so Christopher Ross Shadow only provides guidance steps instead of making the change blindly', 'thisismyurl-shadow' );
		if ( '' !== $metadata_note ) {
			$reason .= ' ' . sprintf(
				/* translators: %s: diagnostic metadata note */
				__( 'Diagnostic note: %s.', 'thisismyurl-shadow' ),
				$metadata_note
			);
		}

		return $reason;
	}

	$haystack = strtolower(
		$run_key
		. ' ' . $name
		. ' ' . $description
		. ' ' . $family
		. ' ' . $failure_reason
		. ' ' . $metadata_note
	);

	if (
		false !== strpos( $haystack, 'cron' )
		|| false !== strpos( $haystack, 'scheduled' )
		|| false !== strpos( $haystack, 'server' )
		|| false !== strpos( $haystack, 'hosting' )
		|| false !== strpos( $haystack, 'opcache' )
		|| false !== strpos( $haystack, 'php version' )
		|| false !== strpos( $haystack, 'http/2' )
		|| false !== strpos( $haystack, 'http/3' )
	) {
		return __( 'the fix lives at the server or hosting layer, outside normal WordPress settings, so applying it automatically would require assumptions about your infrastructure that Christopher Ross Shadow cannot safely make', 'thisismyurl-shadow' );
	}

	if (
		false !== strpos( $haystack, 'backup' )
		|| false !== strpos( $haystack, 'autoload' )
		|| false !== strpos( $haystack, 'orphaned' )
		|| false !== strpos( $haystack, 'duplicate-post-meta' )
		|| false !== strpos( $haystack, 'user-meta' )
		|| false !== strpos( $haystack, 'woocommerce-session' )
		|| false !== strpos( $haystack, 'row-count' )
		|| false !== strpos( $haystack, 'table-large' )
	) {
		return __( 'the fix would involve deleting, relocating, or bulk-editing data, and Christopher Ross Shadow avoids destructive cleanup unless the change can be reversed safely and validated confidently', 'thisismyurl-shadow' );
	}

	if (
		false !== strpos( $haystack, 'viewport' )
		|| false !== strpos( $haystack, 'lang attribute' )
		|| false !== strpos( $haystack, 'responsive image' )
		|| false !== strpos( $haystack, 'accessible' )
		|| false !== strpos( $haystack, 'theme' )
		|| false !== strpos( $haystack, 'template' )
		|| false !== strpos( $haystack, 'markup' )
		|| false !== strpos( $haystack, 'srcset' )
	) {
		return __( 'the problem is in theme or plugin output code rather than a single WordPress option, so auto-fixing it would mean editing code paths that may be custom to your site', 'thisismyurl-shadow' );
	}

	if (
		false !== strpos( $haystack, 'contact' )
		|| false !== strpos( $haystack, 'about' )
		|| false !== strpos( $haystack, 'schema' )
		|| false !== strpos( $haystack, 'meta' )
		|| false !== strpos( $haystack, 'analytics' )
	) {
		return __( 'the right fix depends on your content, compliance choices, or plugin stack, so Christopher Ross Shadow avoids inventing pages, copy, or plugin behavior unless you explicitly provide the inputs', 'thisismyurl-shadow' );
	}

	if (
		false !== strpos( $haystack, 'admin-account' )
		|| false !== strpos( $haystack, 'administrator' )
		|| false !== strpos( $haystack, 'user-enumeration' )
		|| false !== strpos( $haystack, 'role' )
	) {
		return __( 'the change affects user accounts or access policy, so Christopher Ross Shadow leaves the final decision to the site owner instead of risking an unintended lockout or privilege change', 'thisismyurl-shadow' );
	}

	$reason = __( 'this issue does not map cleanly to one reversible WordPress write, and solving it safely requires context about your site setup, theme, content, plugins, or hosting environment', 'thisismyurl-shadow' );
	if ( '' !== $metadata_note ) {
		$reason .= ' ' . sprintf(
			/* translators: %s: diagnostic metadata note */
			__( 'Diagnostic note: %s.', 'thisismyurl-shadow' ),
			$metadata_note
		);
	}

	return $reason;
}

/**
 * Build diagnostics activity rows for dashboard display.
 *
 * @since  0.6095
 * @return array<int, array<string, mixed>>
 */
function thisismyurl_shadow_get_diagnostics_activity_rows(): array {
	if ( ! class_exists( '\\ThisIsMyURL\\Shadow\\Diagnostics\\Diagnostic_Registry' ) ) {
		return array();
	}

	$test_states = function_exists( 'thisismyurl_shadow_get_diagnostic_test_states' )
		? thisismyurl_shadow_get_diagnostic_test_states()
		: get_option( 'thisismyurl_shadow_diagnostic_test_states', array() );
	if ( ! is_array( $test_states ) ) {
		$test_states = array();
	}

	$diagnostics = \ThisIsMyURL\Shadow\Diagnostics\Diagnostic_Registry::get_diagnostic_definitions();
	if ( empty( $diagnostics ) ) {
		return array();
	}

	$rows = array();
	$now  = time();
	$category_meta = thisismyurl_shadow_get_category_metadata();
	$cached_findings = function_exists( 'thisismyurl_shadow_get_cached_findings' )
		? thisismyurl_shadow_get_cached_findings()
		: array();
	if ( empty( $cached_findings ) ) {
		$cached_findings = get_option( 'thisismyurl_shadow_site_findings', array() );
	}
	if ( ! is_array( $cached_findings ) ) {
		$cached_findings = array();
	}
	if ( function_exists( 'thisismyurl_shadow_index_findings_by_id' ) ) {
		$cached_findings = thisismyurl_shadow_index_findings_by_id( $cached_findings );
	}

	$family_to_gauge = array(
		'security'         => 'security',
		'performance'      => 'performance',
		'seo'              => 'seo',
		'accessibility'    => 'accessibility',
		'design'           => 'design',
		'settings'         => 'settings',
		'monitoring'       => 'monitoring',
		'workflows'        => 'workflows',
		'code-quality'     => 'code-quality',
		'database'         => 'performance',
		'wordpress-health' => 'wordpress-health',
	);

	foreach ( $diagnostics as $diagnostic ) {
		if ( ! is_array( $diagnostic ) ) {
			continue;
		}

		$class_name  = isset( $diagnostic['class'] ) ? (string) $diagnostic['class'] : '';
		$short_class = isset( $diagnostic['short_class'] ) ? (string) $diagnostic['short_class'] : '';
		if ( '' === $class_name ) {
			continue;
		}

		$friendly_name = isset( $diagnostic['title'] ) ? (string) $diagnostic['title'] : '';
		if ( '' === trim( $friendly_name ) ) {
			$friendly_name = str_replace( '_', ' ', str_replace( 'Diagnostic_', '', $short_class ) );
			$friendly_name = ucwords( strtolower( $friendly_name ) );
		}

		$description = isset( $diagnostic['description'] ) ? (string) $diagnostic['description'] : '';
		$severity    = isset( $diagnostic['severity'] ) ? (string) $diagnostic['severity'] : '';

		$time_to_fix = 0;
		if ( class_exists( $class_name ) && method_exists( $class_name, 'get_time_to_fix_minutes' ) ) {
			$time_to_fix = (int) call_user_func( array( $class_name, 'get_time_to_fix_minutes' ) );
		}

		$impact = '';
		if ( class_exists( $class_name ) && method_exists( $class_name, 'get_impact' ) ) {
			$impact = (string) call_user_func( array( $class_name, 'get_impact' ) );
		}

		$run_key = '';
		if ( class_exists( $class_name ) && method_exists( $class_name, 'get_run_key' ) ) {
			$run_key = sanitize_key( (string) call_user_func( array( $class_name, 'get_run_key' ) ) );
		}
		if ( '' === $run_key ) {
			$run_key = thisismyurl_shadow_get_diagnostic_run_key_from_class( $class_name );
		}

		$family = '';
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Diagnostic_Metadata' ) ) {
			$meta = \ThisIsMyURL\Shadow\Core\Diagnostic_Metadata::get( $run_key );
			if ( is_array( $meta ) && ! empty( $meta['category'] ) ) {
				$family = sanitize_key( (string) $meta['category'] );
			}
		}
		if ( '' === $family && ! empty( $diagnostic['family'] ) ) {
			$family = sanitize_key( (string) $diagnostic['family'] );
		}

		$gauge_key = isset( $family_to_gauge[ $family ] ) ? $family_to_gauge[ $family ] : 'settings';
		$gauge_label = isset( $category_meta[ $family ]['label'] )
			? (string) $category_meta[ $family ]['label']
			: ucwords( str_replace( '-', ' ', $family ) );

		$status_label = esc_html__( 'Not yet run', 'thisismyurl-shadow' );
		$status_raw   = 'pending';
		$failure_reason = '';
		$failure_issues = array();
		$explanation_sections = array();
		$last_run_raw  = 0;
		$next_run_due_at = 0;
		$next_run_label = esc_html__( 'Not scheduled', 'thisismyurl-shadow' );
		$is_overdue = false;

		if ( isset( $test_states[ $class_name ] ) && is_array( $test_states[ $class_name ] ) ) {
			$state = $test_states[ $class_name ];

			if ( ! empty( $state['last_run'] ) ) {
				$last_run_raw = (int) $state['last_run'];
			}

			if ( ! empty( $state['next_run'] ) ) {
				$next_run_due_at = (int) $state['next_run'];
				if ( $next_run_due_at > 0 ) {
					if ( $next_run_due_at <= $now ) {
						$is_overdue     = true;
						$next_run_label = esc_html__( 'Overdue', 'thisismyurl-shadow' );
					} else {
						$next_run_label = thisismyurl_shadow_format_human_time( $next_run_due_at );
					}
				}
			}

			if ( ! empty( $state['last_status'] ) ) {
				$last_status = strtolower( (string) $state['last_status'] );
				if ( 'passed' === $last_status ) {
					$status_label = esc_html__( 'Passed', 'thisismyurl-shadow' );
					$status_raw   = 'passed';
				} elseif ( 'failed' === $last_status ) {
					$status_label = esc_html__( 'Failed', 'thisismyurl-shadow' );
					$status_raw   = 'failed';
				}
			}
		}

		if ( isset( $cached_findings[ $run_key ] ) && is_array( $cached_findings[ $run_key ] ) ) {
			$finding = $cached_findings[ $run_key ];

			if ( ! empty( $finding['status'] ) ) {
				$status_from_cache = strtolower( (string) $finding['status'] );
				if ( 'passed' === $status_from_cache ) {
					$status_label = esc_html__( 'Passed', 'thisismyurl-shadow' );
					$status_raw   = 'passed';
				} elseif ( 'failed' === $status_from_cache ) {
					$status_label = esc_html__( 'Failed', 'thisismyurl-shadow' );
					$status_raw   = 'failed';
				}
			}

			if ( ! empty( $finding['failure_reason'] ) ) {
				$failure_reason = (string) $finding['failure_reason'];
			}

			if ( ! empty( $finding['failure_issues'] ) && is_array( $finding['failure_issues'] ) ) {
				$failure_issues = $finding['failure_issues'];
			}

			if ( ! empty( $finding['explanation_sections'] ) && is_array( $finding['explanation_sections'] ) ) {
				$explanation_sections = $finding['explanation_sections'];
			}

			if ( ! empty( $finding['next_run_due_at'] ) ) {
				$next_run_due_at = (int) $finding['next_run_due_at'];
				if ( $next_run_due_at > 0 ) {
					if ( $next_run_due_at <= $now ) {
						$is_overdue     = true;
						$next_run_label = esc_html__( 'Overdue', 'thisismyurl-shadow' );
					} else {
						$next_run_label = thisismyurl_shadow_format_human_time( $next_run_due_at );
					}
				}
			}

			if ( ! empty( $finding['last_run'] ) ) {
				$last_run_raw = (int) $finding['last_run'];
			}
		}

		$frequency = 'daily';
		if ( class_exists( $class_name ) && method_exists( $class_name, 'get_scan_frequency' ) ) {
			$frequency = (string) call_user_func( array( $class_name, 'get_scan_frequency' ) );
		}

		$current_frequency = $frequency;
		$is_enabled        = ! empty( $diagnostic['enabled'] );

		if ( ! $is_enabled ) {
			$status_label = esc_html__( 'Disabled', 'thisismyurl-shadow' );
			$status_raw   = 'disabled';
		}

		$is_core = false;
		$confidence = 'standard';
		if ( '' !== $class_name && class_exists( $class_name ) ) {
			if ( method_exists( $class_name, 'is_core' ) ) {
				$is_core = (bool) call_user_func( array( $class_name, 'is_core' ) );
			}
			if ( method_exists( $class_name, 'get_confidence' ) ) {
				$confidence = (string) call_user_func( array( $class_name, 'get_confidence' ) );
			}
		}

		if ( ! is_array( $explanation_sections ) ) {
			$explanation_sections = array();
		}

		if (
			'' === trim( (string) ( $explanation_sections['summary'] ?? '' ) )
			|| '' === trim( (string) ( $explanation_sections['how_wp_shadow_tested'] ?? '' ) )
			|| '' === trim( (string) ( $explanation_sections['why_it_matters'] ?? '' ) )
			|| '' === trim( (string) ( $explanation_sections['how_to_fix_it'] ?? '' ) )
		) {
			$explanation_sections = thisismyurl_shadow_build_explanation_sections_fallback(
				$run_key,
				$friendly_name,
				$description,
				$family,
				$status_raw,
				$failure_reason,
				$confidence,
				$is_core
			);
		}

		$rows[] = array(
			'run_key'   => $run_key,
			'name'      => $friendly_name,
			'class'     => $class_name,
			'enabled'   => $is_enabled,
			'family'    => $family,
			'gauge_key' => $gauge_key,
			'gauge_label' => $gauge_label,
			'description' => $description,
			'severity'  => $severity,
			'time_to_fix' => $time_to_fix,
			'impact'    => $impact,
			'frequency' => $current_frequency,
			'failure_reason' => $failure_reason,
			'failure_issues' => $failure_issues,
			'explanation_sections' => $explanation_sections,
			'last_run_ts' => $last_run_raw,
			'next_run_ts' => $next_run_due_at,
			'next_run_sort' => $is_overdue ? -1 : $next_run_due_at,
			'last_run'  => $last_run_raw > 0 ? thisismyurl_shadow_format_human_time( $last_run_raw ) : esc_html__( 'Not yet run', 'thisismyurl-shadow' ),
			'next_run'  => $next_run_label,
			'status'    => $status_label,
			'status_raw' => $status_raw,
			'detail_url' => thisismyurl_shadow_get_diagnostic_detail_admin_url( $run_key ),
			'is_core'   => $is_core,
			'confidence' => $confidence,
		);
	}

	return $rows;
}

/**
 * Build the dedicated admin URL for a diagnostic detail page.
 *
 * @since  0.6095
 * @param  string $run_key Diagnostic run key.
 * @return string
 */
function thisismyurl_shadow_get_diagnostic_detail_admin_url( string $run_key ): string {
	return add_query_arg(
		array(
			'page'       => 'thisismyurl-shadow-guardian',
			'diagnostic' => sanitize_key( $run_key ),
		),
		admin_url( 'admin.php' )
	);
}
