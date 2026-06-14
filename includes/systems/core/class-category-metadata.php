<?php
/**
 * Category Metadata and Configuration
 *
 * Defines all health gauge categories with colors, labels, and display properties.
 * Used throughout dashboard for gauge rendering and category organization.
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Core
 */

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get category metadata for all health gauges.
 *
 * Returns configuration for all 11 health categories:
 * - 9 standard Shadow by Christopher Ross categories (including Accessibility)
 * - 1 WordPress native Site Health
 * - 1 Overall site health (calculated from all)
 *
 * @return array<string, array{label: string, icon: string, color: string, background: string, description: string}>
 */
function thisismyurl_shadow_get_category_metadata(): array {
	return array(
		'security'         => array(
			'label'       => __( 'Security', 'thisismyurl-shadow' ),
			'icon'        => 'dashicons-shield',
			'color'       => '#dc2626',
			'background'  => 'rgba(220, 38, 38, 0.1)',
			'description' => __( 'Site security, vulnerabilities, and protection measures', 'thisismyurl-shadow' ),
		),
		'performance'      => array(
			'label'       => __( 'Performance', 'thisismyurl-shadow' ),
			'icon'        => 'dashicons-performance',
			'color'       => '#0891b2',
			'background'  => 'rgba(8, 145, 178, 0.1)',
			'description' => __( 'Site speed, caching, and optimization', 'thisismyurl-shadow' ),
		),
		'code-quality'     => array(
			'label'       => __( 'Code Quality', 'thisismyurl-shadow' ),
			'icon'        => 'dashicons-editor-code',
			'color'       => '#7c3aed',
			'background'  => 'rgba(124, 58, 237, 0.1)',
			'description' => __( 'Code standards, best practices, and technical debt', 'thisismyurl-shadow' ),
		),
		'seo'              => array(
			'label'       => __( 'SEO', 'thisismyurl-shadow' ),
			'icon'        => 'dashicons-search',
			'color'       => '#2563eb',
			'background'  => 'rgba(37, 99, 235, 0.1)',
			'description' => __( 'Search engine optimization and discoverability', 'thisismyurl-shadow' ),
		),
		'design'           => array(
			'label'       => __( 'Design', 'thisismyurl-shadow' ),
			'icon'        => 'dashicons-admin-appearance',
			'color'       => '#8e44ad',
			'background'  => 'rgba(142, 68, 173, 0.1)',
			'description' => __( 'Visual design and user experience', 'thisismyurl-shadow' ),
		),
		'accessibility'    => array(
			'label'       => __( 'Accessibility', 'thisismyurl-shadow' ),
			'icon'        => 'dashicons-universal-access',
			'color'       => '#16a34a',
			'background'  => 'rgba(22, 163, 74, 0.1)',
			'description' => __( 'WCAG compliance, keyboard navigation, screen reader support, and inclusive design', 'thisismyurl-shadow' ),
		),
		'settings'         => array(
			'label'       => __( 'Settings', 'thisismyurl-shadow' ),
			'icon'        => 'dashicons-admin-settings',
			'color'       => '#4b5563',
			'background'  => 'rgba(75, 85, 99, 0.1)',
			'description' => __( 'WordPress configuration and settings', 'thisismyurl-shadow' ),
		),
		'monitoring'       => array(
			'label'       => __( 'Monitoring', 'thisismyurl-shadow' ),
			'icon'        => 'dashicons-visibility',
			'color'       => '#059669',
			'background'  => 'rgba(5, 150, 105, 0.1)',
			'description' => __( 'Site monitoring, uptime, and alerts', 'thisismyurl-shadow' ),
		),
		'workflows'        => array(
			'label'       => __( 'Workflows', 'thisismyurl-shadow' ),
			'icon'        => 'dashicons-update',
			'color'       => '#ea580c',
			'background'  => 'rgba(234, 88, 12, 0.1)',
			'description' => __( 'Automation, scheduled tasks, and workflows', 'thisismyurl-shadow' ),
		),
		'wordpress-health' => array(
			'label'       => __( 'WordPress Health', 'thisismyurl-shadow' ),
			'icon'        => 'dashicons-wordpress',
			'color'       => '#21759b',
			'background'  => 'rgba(33, 117, 155, 0.1)',
			'description' => __( 'WordPress native site health checks', 'thisismyurl-shadow' ),
		),
		'overall'          => array(
			'label'       => __( 'Overall Health', 'thisismyurl-shadow' ),
			'icon'        => 'dashicons-heart',
			'color'       => '#1976d2',
			'background'  => 'rgba(25, 118, 210, 0.1)',
			'description' => __( 'Combined health score across all categories', 'thisismyurl-shadow' ),
		),
	);
}

/**
 * Calculate overall health from all category gauges.
 *
 * @param array $findings_by_category Findings grouped by category.
 * @param array $category_meta Category metadata array.
 * @return array Health status with score, status, color, message.
 */
function thisismyurl_shadow_calculate_overall_health( array $findings_by_category, array $category_meta ): array {
	$total_score = 0;
	$gauge_count = 0;

	foreach ( $category_meta as $cat_key => $meta ) {
		if ( in_array( $cat_key, array( 'overall', 'wordpress-health' ), true ) ) {
			continue;
		}

		$cat_findings = $findings_by_category[ $cat_key ] ?? array();
		$total        = count( $cat_findings );
		$threat_total = 0;

		foreach ( $cat_findings as $finding ) {
			$threat_total += isset( $finding['threat_level'] ) ? (int) $finding['threat_level'] : 50;
		}

		$gauge_percent = $total > 0 ? min( 100, $threat_total / $total ) : 0;
		$gauge_percent = 100 - $gauge_percent;

		$total_score += $gauge_percent;
		++$gauge_count;
	}

	$combined_score = $gauge_count > 0 ? (int) round( $total_score / $gauge_count ) : 100;
	$combined_score = max( 0, min( 100, $combined_score ) );

	if ( $combined_score >= 80 ) {
		return array(
			'score'   => $combined_score,
			'status'  => __( 'Good', 'thisismyurl-shadow' ),
			'color'   => '#2e7d32',
			'message' => __( 'Your site is healthy with only minor issues to address.', 'thisismyurl-shadow' ),
		);
	}

	if ( $combined_score >= 60 ) {
		return array(
			'score'   => $combined_score,
			'status'  => __( 'Fair', 'thisismyurl-shadow' ),
			'color'   => '#f57c00',
			'message' => __( 'Your site needs some attention. Review the issues below.', 'thisismyurl-shadow' ),
		);
	}

	return array(
		'score'   => $combined_score,
		'status'  => __( 'Poor', 'thisismyurl-shadow' ),
		'color'   => '#c62828',
		'message' => __( 'Your site needs immediate attention. Address critical issues now.', 'thisismyurl-shadow' ),
	);
}

