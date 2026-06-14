<?php

/**
 * Register Shadow by Christopher Ross findings as native WordPress Site Health tests.
 *
 * This file adapts Shadow's stored diagnostic findings into the structure
 * expected by WordPress Site Health. That integration matters because many
 * site owners and developers already use Tools > Site Health as their first
 * troubleshooting stop. Surfacing findings there makes the plugin feel like a
 * good WordPress citizen instead of a separate reporting silo.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Admin
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/../systems/diagnostics/class-diagnostic-registry.php';

/**
 * Numeric severity threshold used to map a finding to Site Health "critical".
 *
 * @since 0.6095
 */
if ( ! defined( 'thisismyurl_shadow_SEVERITY_CRITICAL_THRESHOLD' ) ) {
	define( 'thisismyurl_shadow_SEVERITY_CRITICAL_THRESHOLD', 75 );
}

/**
 * Numeric severity threshold used to map a finding to Site Health "recommended".
 *
 * @since 0.6095
 */
if ( ! defined( 'thisismyurl_shadow_SEVERITY_RECOMMENDED_THRESHOLD' ) ) {
	define( 'thisismyurl_shadow_SEVERITY_RECOMMENDED_THRESHOLD', 50 );
}

// Register tests after core Site Health infrastructure is available in admin.
add_action( 'admin_init', 'thisismyurl_shadow_register_diagnostic_site_health_tests', 20 );

// Removed thisismyurl_shadow_site_health_test_overall function per Issue #558
// Individual findings are now shown as separate recommendations instead of being lumped together

// Removed unused functions that were never properly implemented:
// - thisismyurl_shadow_init_individual_diagnostic_tests
// - thisismyurl_shadow_generate_site_health_test
// - thisismyurl_shadow_filter_site_health_tests
// All functionality is now in thisismyurl_shadow_register_diagnostic_site_health_tests

/**
 * Register stored Shadow by Christopher Ross findings as direct Site Health tests.
 *
 * Each finding becomes its own test entry so WordPress can display a separate
 * row, status, and "View in Shadow by Christopher Ross" action. This is intentionally done with
 * a filter callback rather than by writing to Site Health internals directly,
 * because the filter API is the stable extension point WordPress provides.
 *
 * For inexperienced WordPress developers: "direct" tests are evaluated in the
 * same request that builds the Site Health page, which is appropriate here
 * because the plugin is reading already-saved findings rather than performing
 * expensive live scans.
 *
 * @since  0.6095
 * @return void
 */
function thisismyurl_shadow_register_diagnostic_site_health_tests() {
	// Bug fix: Changed from 'thisismyurl_shadow_last_findings' (which was never set) to
	// 'thisismyurl_shadow_site_findings' (the actual option used throughout the codebase).
	// See: class-finding-utils.php line 126 for where this option is updated.
	$findings = get_option( 'thisismyurl_shadow_site_findings', array() );

	if ( empty( $findings ) || ! is_array( $findings ) ) {
		return;
	}

	$badge = array(
		'label' => 'Shadow by Christopher Ross',
		'color' => 'blue',
	);

	// Register all findings as individual tests in a single filter callback
	add_filter(
		'site_health_tests',
		function ( $tests ) use ( $findings, $badge ) {
			// Register each finding as an individual test
			foreach ( $findings as $diagnostic_id => $finding_data ) {
				$test_id = 'thisismyurl_shadow_diagnostic_' . sanitize_key( $diagnostic_id );

				// Add to Site Health tests if not already present
				if ( ! isset( $tests['direct'][ $test_id ] ) && ! isset( $tests['async'][ $test_id ] ) ) {
					// Create inline test callback
					$tests['direct'][ $test_id ] = array(
						'label' => isset( $finding_data['title'] ) ? $finding_data['title'] : $diagnostic_id,
						'test'  => function () use ( $diagnostic_id, $finding_data, $badge, $test_id ) {
							return thisismyurl_shadow_generate_diagnostic_site_health_result( $diagnostic_id, $finding_data, $badge, $test_id );
						},
					);
				}
			}

			return $tests;
		}
	);
}

/**
 * Build the Site Health result array for one Shadow by Christopher Ross finding.
 *
 * Site Health expects a specific array contract containing the label, status,
 * badge metadata, description, actions, and an internal test identifier. This
 * helper translates Shadow's more flexible finding payload into that schema
 * and normalizes severity values that may be stored as either strings such as
 * "high" or numbers such as a 0-100 threat score.
 *
 * @since  0.6095
 * @param  string               $diagnostic_id Shadow by Christopher Ross finding identifier.
 * @param  array<string,mixed>  $finding_data  Stored finding payload.
 * @param  array<string,string> $badge         Site Health badge definition.
 * @param  string               $test_id       Unique Site Health test ID.
 * @return array<string,mixed> Site Health-compatible result array.
 */
function thisismyurl_shadow_generate_diagnostic_site_health_result( $diagnostic_id, $finding_data, $badge, $test_id ) {
	$action_url  = admin_url( 'admin.php?page=thisismyurl-shadow' );
	$title       = isset( $finding_data['title'] ) ? $finding_data['title'] : $diagnostic_id;
	$description = isset( $finding_data['description'] ) ? $finding_data['description'] : __( 'Shadow by Christopher Ross has detected an issue.', 'thisismyurl-shadow' );

	// Get severity - can be string (high/medium/low) or numeric (0-100)
	$severity = isset( $finding_data['severity'] ) ? $finding_data['severity'] : 'medium';

	// Map severity to Site Health status
	// String format: 'high', 'medium', 'low'
	// Numeric format: 0-100 (from threat_level)
	$site_health_status = 'recommended';

	if ( is_numeric( $severity ) ) {
		// Numeric severity (0-100)
		$severity_num = (int) $severity;
		if ( $severity_num >= thisismyurl_shadow_SEVERITY_CRITICAL_THRESHOLD ) {
			$site_health_status = 'critical';
		} elseif ( $severity_num >= thisismyurl_shadow_SEVERITY_RECOMMENDED_THRESHOLD ) {
			$site_health_status = 'recommended';
		} else {
			$site_health_status = 'good';
		}
	} else {
		// String severity
		$severity_str = strtolower( (string) $severity );
		if ( 'critical' === $severity_str ) {
			$site_health_status = 'critical';
		} elseif ( 'high' === $severity_str ) {
			$site_health_status = 'critical';
		} elseif ( 'medium' === $severity_str ) {
			$site_health_status = 'recommended';
		} else {
			// 'low' or anything else
			$site_health_status = 'good';
		}
	}

	return array(
		'label'       => $title,
		'status'      => $site_health_status,
		'badge'       => $badge,
		'description' => $description,
		'actions'     => array(
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $action_url . '#' . sanitize_title_with_dashes( $diagnostic_id ) ),
				esc_html__( 'View in Shadow by Christopher Ross', 'thisismyurl-shadow' )
			),
		),
		'test'        => $test_id,
	);
}
