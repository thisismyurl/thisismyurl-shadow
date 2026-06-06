<?php

/**
 * Christopher Ross Shadow Health Gauges Dashboard Module
 *
 * Handles health gauge rendering for:
 * - Overall site health score
 * - Category-specific health gauges
 * - Asset enqueuing for gauge styles
 * - Health calculation and display
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Dashboard
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue gauges and dashboard assets
 */
function thisismyurl_shadow_enqueue_gauges_assets( string $hook ): void {
	if ( 'toplevel_page_thisismyurl-shadow' !== $hook ) {
		return;
	}

	// Enqueue gauges CSS for health dashboard (#563)
	if ( file_exists( THISISMYURL_SHADOW_PATH . 'assets/css/gauges.css' ) ) {
		wp_enqueue_style(
			'thisismyurl-shadow-gauges',
			THISISMYURL_SHADOW_URL . 'assets/css/gauges.css',
			array(),
			(string) filemtime( THISISMYURL_SHADOW_PATH . 'assets/css/gauges.css' )
		);
	}

	// Ensure WordPress heartbeat API is active on dashboard pages.
	wp_enqueue_script( 'heartbeat' );
}
add_action( 'admin_enqueue_scripts', 'thisismyurl_shadow_enqueue_gauges_assets' );

/**
 * Get per-category test coverage counts for dashboard gauges.
 *
 * Counts associated diagnostics by category and reports how many actually ran
 * during the current dashboard freshness window.
 *
 * @param array $category_meta Dashboard category metadata.
 * @param bool  $never_run     Whether diagnostics have never been run.
 * @return array<string, array{run: int, total: int}> Category counts.
 */
function thisismyurl_shadow_get_gauge_test_counts( array $category_meta, bool $never_run ): array {
	$counts = array();

	foreach ( $category_meta as $cat_key => $meta ) {
		$counts[ $cat_key ] = array(
			'run'     => 0,
			'passed'  => 0,
			'failed'  => 0,
			'unknown' => 0,
			'total'   => 0,
		);
	}

	$diagnostic_file_map = class_exists( '\\ThisIsMyURL\\Shadow\\Diagnostics\\Diagnostic_Registry' )
		? \ThisIsMyURL\Shadow\Diagnostics\Diagnostic_Registry::get_diagnostic_file_map()
		: array();

	$disabled = get_option( 'thisismyurl_shadow_disabled_diagnostic_classes', array() );
	if ( ! is_array( $disabled ) ) {
		$disabled = array();
	}

	// Load all persisted states once for the raw-state fallback (mirrors Diagnostic Status table logic).
	$all_raw_states = function_exists( 'thisismyurl_shadow_get_diagnostic_test_states' )
		? thisismyurl_shadow_get_diagnostic_test_states()
		: array();

	$now = time();
	foreach ( $diagnostic_file_map as $class_name => $diagnostic_data ) {
		if ( ! is_string( $class_name ) || '' === $class_name ) {
			continue;
		}

		$qualified_class = 0 === strpos( $class_name, 'ThisIsMyURL\\Shadow\\Diagnostics\\' )
			? $class_name
			: 'ThisIsMyURL\\Shadow\\Diagnostics\\' . $class_name;

		$is_disabled = in_array( $qualified_class, $disabled, true ) || in_array( $class_name, $disabled, true );
		if ( $is_disabled ) {
			continue;
		}

		$category = sanitize_key( (string) ( $diagnostic_data['family'] ?? '' ) );
		if ( ! isset( $counts[ $category ] ) ) {
			continue;
		}

		++$counts[ $category ]['total'];

		$state = function_exists( 'thisismyurl_shadow_get_valid_diagnostic_test_state' )
			? thisismyurl_shadow_get_valid_diagnostic_test_state( $qualified_class, $now )
			: null;

		// Fallback: use persisted raw state when the valid-state check returns null
		// (e.g. transient expired). This matches the same fallback used in the
		// Diagnostic Status table so both surfaces show consistent counts.
		if ( ! is_array( $state ) && isset( $all_raw_states[ $qualified_class ] ) && is_array( $all_raw_states[ $qualified_class ] ) ) {
			$raw_status = (string) ( $all_raw_states[ $qualified_class ]['status'] ?? '' );
			if ( 'passed' === $raw_status || 'failed' === $raw_status ) {
				$state = $all_raw_states[ $qualified_class ];
			}
		}

		if ( ! is_array( $state ) ) {
			++$counts[ $category ]['unknown'];
			continue;
		}

		$status = (string) ( $state['status'] ?? 'unknown' );
		if ( 'passed' === $status ) {
			++$counts[ $category ]['passed'];
			++$counts[ $category ]['run'];
		} elseif ( 'failed' === $status ) {
			++$counts[ $category ]['failed'];
			++$counts[ $category ]['run'];
		} else {
			++$counts[ $category ]['unknown'];
		}
	}

	$wp_health_total = 0;
	if ( class_exists( '\\WP_Site_Health' ) || file_exists( ABSPATH . 'wp-admin/includes/class-wp-site-health.php' ) ) {
		if ( ! class_exists( '\\WP_Site_Health' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}

		$wp_tests = \WP_Site_Health::get_tests();
		foreach ( array( 'direct', 'async' ) as $test_type ) {
			if ( empty( $wp_tests[ $test_type ] ) || ! is_array( $wp_tests[ $test_type ] ) ) {
				continue;
			}

			foreach ( $wp_tests[ $test_type ] as $test ) {
				$test_slug = (string) ( $test['test'] ?? '' );
				if ( '' !== $test_slug && false !== strpos( $test_slug, 'thisismyurl-shadow' ) ) {
					continue;
				}
				++$wp_health_total;
			}
		}
	}

	$wp_health_run = 0;
	$wp_health_raw = get_transient( 'health-check-site-status-result' );
	if ( is_string( $wp_health_raw ) ) {
		$decoded = json_decode( $wp_health_raw, true );
		if ( is_array( $decoded ) ) {
			$wp_health_raw = $decoded;
		}
	}

	if ( is_array( $wp_health_raw ) ) {
		$wp_health_run = (int) ( $wp_health_raw['good'] ?? 0 ) + (int) ( $wp_health_raw['recommended'] ?? 0 ) + (int) ( $wp_health_raw['critical'] ?? 0 );
	}

	if ( isset( $counts['wordpress-health'] ) ) {
		$wp_known = 0;
		$wp_good  = 0;

		if ( is_array( $wp_health_raw ) ) {
			$wp_good        = (int) ( $wp_health_raw['good'] ?? 0 );
			$wp_recommended = (int) ( $wp_health_raw['recommended'] ?? 0 );
			$wp_critical    = (int) ( $wp_health_raw['critical'] ?? 0 );
			$wp_known       = $wp_good + $wp_recommended + $wp_critical;
		}

		$counts['wordpress-health']['total']   = $wp_health_total;
		$counts['wordpress-health']['passed']  = min( $wp_good, $wp_health_total );
		$counts['wordpress-health']['failed']  = min( max( 0, $wp_known - $wp_good ), $wp_health_total );
		$counts['wordpress-health']['run']     = min( $wp_known, $wp_health_total );
		$counts['wordpress-health']['unknown'] = max( 0, $wp_health_total - $counts['wordpress-health']['run'] );
	}

	// Calculate overall test counts from category aggregates.
	$overall_run     = 0;
	$overall_passed  = 0;
	$overall_failed  = 0;
	$overall_unknown = 0;
	$overall_total   = 0;
	foreach ( $counts as $cat_key => $cat_counts ) {
		if ( 'overall' === $cat_key ) {
			continue;
		}

		$overall_run     += (int) ( $cat_counts['run'] ?? 0 );
		$overall_passed  += (int) ( $cat_counts['passed'] ?? 0 );
		$overall_failed  += (int) ( $cat_counts['failed'] ?? 0 );
		$overall_unknown += (int) ( $cat_counts['unknown'] ?? 0 );
		$overall_total   += (int) ( $cat_counts['total'] ?? 0 );
	}

	$counts['overall'] = array(
		'run'     => $overall_run,
		'passed'  => $overall_passed,
		'failed'  => $overall_failed,
		'unknown' => $overall_unknown,
		'total'   => $overall_total,
	);

	return $counts;
}

/**
 * Render health gauges section on dashboard
 *
 * Called via thisismyurl_shadow_dashboard_gauges hook
 * Renders overall health gauge and category-specific gauges
 * Always shows gauges, even when no findings exist (Issue #1672)
 *
 * @param string $category_filter Optional category to filter by (Issue #564)
 */
function thisismyurl_shadow_render_health_gauges( string $category_filter = '' ): void {
	$is_drilldown = ! empty( $category_filter );

	// Get findings (empty array if none)
	$findings = \thisismyurl_shadow_get_site_findings();

	// Check if diagnostics have ever been run
	$last_scan = get_option( 'thisismyurl_shadow_last_quick_checks', 0 );
	$never_run = empty( $last_scan );

	// Group findings by category
	$category_meta = \thisismyurl_shadow_get_category_metadata();

		// Filter categories if drill-down active
	if ( $is_drilldown ) {
		// Show only the selected category gauge prominently
		if ( isset( $category_meta[ $category_filter ] ) ) {
			$category_meta = array( $category_filter => $category_meta[ $category_filter ] );
		} else {
			return; // Invalid category
		}
	}

	$test_counts = thisismyurl_shadow_get_gauge_test_counts( $category_meta, $never_run );

	$findings_by_category = array();
	foreach ( $category_meta as $cat_key => $meta ) {
		$findings_by_category[ $cat_key ] = array_filter(
			$findings,
			function ( $f ) use ( $cat_key ) {
				return isset( $f['category'] ) && $f['category'] === $cat_key;
			}
		);
	}

	// Calculate test pass rates from persisted per-test states.
	$overall_total_tests   = (int) ( $test_counts['overall']['total'] ?? 0 );
	$overall_tests_passed  = (int) ( $test_counts['overall']['passed'] ?? 0 );
	$overall_tests_unknown = (int) ( $test_counts['overall']['unknown'] ?? 0 );
	$overall_pass_rate     = $overall_total_tests > 0 ? (int) ( ( $overall_tests_passed / $overall_total_tests ) * 100 ) : 0;

	// Get pass rate color utility function
	$get_pass_rate_color = function ( $pass_rate ) {
		if ( $pass_rate >= 80 ) {
			return '#2e7d32'; // Green - Good pass rate
		}
		if ( $pass_rate >= 60 ) {
			return '#f57c00'; // Orange - Fair pass rate
		}
		return '#f44336'; // Red - Poor pass rate
	};

	$overall_color             = $get_pass_rate_color( $overall_pass_rate );
	?>
	<div class="wps-dashboard-gauges wps-gap-6 wps-mb-8">
		<!-- Left: Large Overall Health Gauge + Scan Buttons -->
		<div class="wps-health-gauge-main">
			<div class="wps-health-gauge-card" style="display:block; color:inherit; text-decoration:none; border-color: <?php echo esc_attr( $overall_color ); ?>;" role="group" aria-label="<?php esc_attr_e( 'Overall Christopher Ross Shadow dashboard health gauge', 'thisismyurl-shadow' ); ?>">
				<h3 class="wps-health-gauge-title"><?php esc_html_e( 'Overall Site Health', 'thisismyurl-shadow' ); ?></h3>

				<svg id="thisismyurl-shadow-overall-gauge" width="200" height="200" viewBox="0 0 200 200" class="wps-health-gauge-svg" aria-labelledby="overall-health-title" role="img">
					<title id="overall-health-title">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: tests passed, 2: total tests, 3: pass rate percentage */
								__( 'Overall tests passed: %1$d/%2$d (%3$d%%)', 'thisismyurl-shadow' ),
								$overall_tests_passed,
								$overall_total_tests,
								$overall_pass_rate
							)
						);
						?>
					</title>
					<!-- Outer decorative circle -->
					<circle cx="100" cy="100" r="95" fill="none" stroke="<?php echo esc_attr( $overall_color ); ?>" stroke-width="2" opacity="0.2" />
					<!-- Gauge background -->
					<circle cx="100" cy="100" r="85" fill="none" stroke="#e0e0e0" stroke-width="16" />
					<!-- Gauge progress -->
					<circle cx="100" cy="100" r="85" fill="none" stroke="<?php echo esc_attr( $overall_color ); ?>" stroke-width="16"
						stroke-dasharray="<?php echo (int) ( ( $overall_pass_rate / 100 ) * 534 ); ?> 534"
						stroke-linecap="round" transform="rotate(-90 100 100)"
						class="wps-gauge-progress" />
					<!-- Center text -->
					<text x="100" y="97" text-anchor="middle" font-size="40" font-weight="bold" fill="<?php echo esc_attr( $overall_color ); ?>"><?php echo (int) $overall_tests_passed; ?></text>
					<text x="100" y="118" text-anchor="middle" font-size="16" fill="#999">/<?php echo (int) $overall_total_tests; ?></text>
										<text x="100" y="138" text-anchor="middle" font-size="14" fill="#666"><?php echo esc_html( sprintf(
											/* translators: %d: pass percentage. */
											__( '%d%% Pass', 'thisismyurl-shadow' ),
											(int) $overall_pass_rate
										) ); ?></text>
				</svg>

				<p class="wps-health-gauge-message">
					<?php
					if ( $overall_tests_unknown > 0 ) {
						echo esc_html(
							sprintf(
								/* translators: 1: unknown test count */
								__( '%1$d tests are still unknown until they run.', 'thisismyurl-shadow' ),
								$overall_tests_unknown
							)
						);
					} else {
						echo esc_html(
							sprintf(
								/* translators: 1: pass rate percentage */
								__( '%1$d%% of tests passed', 'thisismyurl-shadow' ),
								$overall_pass_rate
							)
						);
					}
					?>
				</p>

				<div class="wps-health-gauge-test-count" data-test-category="overall">
					<?php
					if ( $overall_tests_unknown > 0 ) {
						echo esc_html(
							sprintf(
								/* translators: 1: tests passed, 2: total tests, 3: unknown tests */
								__( 'Passed: %1$d/%2$d (Unknown: %3$d)', 'thisismyurl-shadow' ),
								$overall_tests_passed,
								$overall_total_tests,
								$overall_tests_unknown
							)
						);
					} else {
						echo esc_html(
							sprintf(
								/* translators: 1: tests passed, 2: total tests */
								__( 'Passed: %1$d/%2$d', 'thisismyurl-shadow' ),
								$overall_tests_passed,
								$overall_total_tests
							)
						);
					}
					?>
				</div>
			</div>

			<!-- Fullscreen Button -->
			<div class="wps-health-gauge-actions">
				<button id="thisismyurl-shadow-fullscreen-toggle" class="button wps-btn-scan" title="<?php esc_attr_e( 'View dashboard in fullscreen mode (great for office displays)', 'thisismyurl-shadow' ); ?>" aria-label="<?php esc_attr_e( 'Toggle fullscreen mode', 'thisismyurl-shadow' ); ?>">
					<span class="dashicons dashicons-fullscreen-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'Full Screen', 'thisismyurl-shadow' ); ?>
				</button>
				<div id="thisismyurl-shadow-readiness-summary" class="wps-readiness-summary" aria-live="polite"></div>
			</div>
		</div>

		<!-- Right: Category Gauges in a responsive grid -->
		<div class="wps-health-gauge-categories">
			<div class="wps-health-gauge-grid">
				<?php
				foreach ( $category_meta as $cat_key => $meta ) :               // Skip the overall category (shown as large gauge on left)
					if ( 'overall' === $cat_key ) {
						continue;
					}
									// Special handling for WordPress Health category
					if ( 'wordpress-health' === $cat_key ) {
						// Calculate WordPress Health pass rate from test_counts (same source as label).
						$wp_test_total  = (int) ( $test_counts['wordpress-health']['total'] ?? 0 );
						$wp_test_passed = (int) ( $test_counts['wordpress-health']['passed'] ?? 0 );
						$gauge_percent  = $wp_test_total > 0 ? (int) ( ( $wp_test_passed / $wp_test_total ) * 100 ) : 0;

						// Derive status from the same pass-rate metric shown on the gauge.
						if ( $gauge_percent >= 80 ) {
							$status_text = __( 'Excellent', 'thisismyurl-shadow' );
							$status_icon = '✓';
						} elseif ( $gauge_percent >= 60 ) {
							$status_text = __( 'Good', 'thisismyurl-shadow' );
							$status_icon = '✓';
						} elseif ( $gauge_percent >= 40 ) {
							$status_text = __( 'Fair', 'thisismyurl-shadow' );
							$status_icon = '◐';
						} else {
							$status_text = __( 'Needs Work', 'thisismyurl-shadow' );
							$status_icon = '✕';
						}

						// Determine status color based on pass rate
						if ( $gauge_percent >= 80 ) {
							$status_color = '#10b981'; // Green
							$gauge_color  = '#2e7d32';
						} elseif ( $gauge_percent >= 60 ) {
							$status_color = '#f59e0b'; // Orange
							$gauge_color  = '#f57c00';
						} else {
							$status_color = '#ef4444'; // Red
							$gauge_color  = '#c62828';
						}
						?>
						<div class="wps-category-gauge" data-category="wordpress-health" role="group" aria-label="<?php esc_attr_e( 'WordPress health gauge', 'thisismyurl-shadow' ); ?>">
							<div class="wps-category-gauge-icon">
								<svg width="70" height="70" viewBox="0 0 100 100" aria-hidden="true">
									<!-- Gauge background -->
									<circle cx="50" cy="50" r="40" fill="none" stroke="#e0e0e0" stroke-width="8" />
									<!-- Gauge progress -->
									<circle cx="50" cy="50" r="40" fill="none" stroke="<?php echo esc_attr( $gauge_color ); ?>" stroke-width="8"
										class="wps-gauge-progress"
										stroke-dasharray="<?php echo (int) ( $gauge_percent / 100 * 251 ); ?> 251"
										stroke-linecap="round" transform="rotate(-90 50 50)" />
									<!-- Percentage text -->
									<text x="50" y="55" text-anchor="middle" font-size="18" font-weight="bold" fill="#333"><?php echo (int) $gauge_percent; ?>%</text>
								</svg>
							</div>

							<div class="wps-category-gauge-content">
								<!-- Title -->
								<h4 class="wps-category-gauge-title"><?php echo esc_html( isset( $meta['label'] ) ? $meta['label'] : ucfirst( $cat_key ) ); ?></h4>

								<!-- Status -->
								<div class="wps-category-gauge-status">
									<span class="wps-category-gauge-status-text">
										<span aria-hidden="true"><?php echo esc_html( $status_icon ); ?></span>
						<?php echo esc_html( $status_text ); ?>
									</span>
									<div class="wps-category-gauge-count" data-test-category="<?php echo esc_attr( $cat_key ); ?>">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: passed tests, 2: total tests */
								__( 'Passed: %1$d/%2$d', 'thisismyurl-shadow' ),
								$wp_test_passed,
								$wp_test_total
							)
						);
						?>
									</div>
								</div>
							</div>
						</div>
						<?php
						continue;
					}

					// Calculate category test pass rate from persisted states.
					$cat_test_total   = (int) ( $test_counts[ $cat_key ]['total'] ?? 0 );
					$cat_test_passed  = (int) ( $test_counts[ $cat_key ]['passed'] ?? 0 );
					$cat_test_unknown = (int) ( $test_counts[ $cat_key ]['unknown'] ?? 0 );
					$cat_pass_rate    = $cat_test_total > 0 ? (int) ( ( $cat_test_passed / $cat_test_total ) * 100 ) : 0;

					// Show 0% until diagnostics have been run
					if ( $never_run ) {
						$cat_pass_rate   = 0;
						$cat_test_passed = 0;
					}

					// Set status and colors based on pass rate
					if ( $cat_pass_rate >= 80 ) {
						$status_text  = __( 'Excellent', 'thisismyurl-shadow' );
						$status_icon  = '✓';
						$status_color = '#10b981';
						$gauge_color  = '#2e7d32';
					} elseif ( $cat_pass_rate >= 60 ) {
						$status_text  = __( 'Good', 'thisismyurl-shadow' );
						$status_icon  = '✓';
						$status_color = '#10b981';
						$gauge_color  = '#2e7d32';
					} elseif ( $cat_pass_rate >= 40 ) {
						$status_text  = __( 'Fair', 'thisismyurl-shadow' );
						$status_icon  = '◐';
						$status_color = '#f59e0b';
						$gauge_color  = '#f57c00';
					} else {
						$status_text  = __( 'Needs Work', 'thisismyurl-shadow' );
						$status_icon  = '✕';
						$status_color = '#ef4444';
						$gauge_color  = '#c62828';
					}

					$gauge_percent  = $cat_pass_rate;
					$cat_test_run   = (int) ( $test_counts[ $cat_key ]['run'] ?? 0 );
					$cat_test_total = (int) ( $test_counts[ $cat_key ]['total'] ?? 0 );
					?>
					<div
						class="wps-category-gauge"
						data-category="<?php echo esc_attr( $cat_key ); ?>"
						role="group"
						aria-label="
						<?php
						echo esc_attr(
							sprintf(
								/* translators: 1: category name, 2: pass rate percentage, 3: tests passed, 4: total tests */
								__( '%1$s: %2$d%% pass rate (%3$d/%4$d tests).', 'thisismyurl-shadow' ),
								isset( $meta['label'] ) ? $meta['label'] : ucfirst( $cat_key ),
								$cat_pass_rate,
								$cat_test_passed,
								$cat_test_total
							)
						);
						?>
						">
						<div class="wps-category-gauge-icon">
							<svg width="70" height="70" viewBox="0 0 100 100" aria-hidden="true">
								<!-- Gauge background -->
								<circle cx="50" cy="50" r="40" fill="none" stroke="#e0e0e0" stroke-width="8" />
								<!-- Gauge progress -->
								<circle cx="50" cy="50" r="40" fill="none" stroke="<?php echo esc_attr( $gauge_color ); ?>" stroke-width="8"
									class="wps-gauge-progress"
									stroke-dasharray="<?php echo (int) ( $gauge_percent / 100 * 251 ); ?> 251"
									stroke-linecap="round" transform="rotate(-90 50 50)" />
								<!-- Percentage text -->
							<text x="50" y="55" text-anchor="middle" font-size="18" font-weight="bold" fill="#333"><?php echo (int) $gauge_percent; ?>%</text>
							</svg>
						</div>

						<div class="wps-category-gauge-content">
							<!-- Title -->
						<h4 class="wps-category-gauge-title"><?php echo esc_html( isset( $meta['label'] ) ? $meta['label'] : ucfirst( $cat_key ) ); ?></h4>

							<!-- Status -->
							<div class="wps-category-gauge-status">
							<span class="wps-category-gauge-status-text" data-status="<?php echo esc_attr( $status_text ); ?>">
									<span aria-hidden="true"><?php echo esc_html( $status_icon ); ?></span>
									<?php echo esc_html( $status_text ); ?>
								</span>
								<div class="wps-category-gauge-count" data-test-category="<?php echo esc_attr( $cat_key ); ?>">
									<?php
									if ( $cat_test_unknown > 0 ) {
										echo esc_html(
											sprintf(
												/* translators: 1: passed tests, 2: total associated tests, 3: unknown tests */
												__( 'Passed: %1$d/%2$d (Unknown: %3$d)', 'thisismyurl-shadow' ),
												$cat_test_passed,
												$cat_test_total,
												$cat_test_unknown
											)
										);
									} else {
										echo esc_html(
											sprintf(
												/* translators: 1: passed tests, 2: total associated tests */
												__( 'Passed: %1$d/%2$d', 'thisismyurl-shadow' ),
												$cat_test_passed,
												$cat_test_total
											)
										);
									}
									?>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<?php
}

// Hook gauges rendering into dashboard
add_action( 'thisismyurl_shadow_dashboard_gauges', 'thisismyurl_shadow_render_health_gauges' );
