<?php

/**
 * Shadow Plugin Bootstrap
 *
 * Service registry and bootstrap orchestrator.
 * Initializes all core systems in the correct order.
 *
 * Philosophy: Commandment #7 (Ridiculously Good - obvious initialization order)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Core
 */

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinate the plugin's runtime startup sequence.
 *
 * This class answers the architectural question "what turns on, and in what
 * order?" It does not implement diagnostics, treatments, menus, or reporting
 * itself. Instead, it assembles those systems in a deliberate order so later
 * services can rely on earlier ones already being available.
 */
class Plugin_Bootstrap {


	/**
	 * Initialize the plugin's major runtime systems.
	 *
	 * The ordering here is intentional:
	 * - foundational classes are loaded first,
	 * - global hooks are registered next,
	 * - user-facing subsystems are then activated in dependency order.
	 *
	 * Keeping this sequence explicit makes the plugin easier to debug and easier
	 * to study because readers can trace startup without chasing scattered hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// 1. Load core base classes (required before everything else)
		self::load_core_classes();

		// 1.5. Remove stale references after diagnostics/treatments change.
		self::cleanup_stale_registry_references();

		$core_path = THISISMYURL_SHADOW_PATH . 'includes/systems/core/';

		// 2. Register hooks (should run early, before other systems)
		if ( ! class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Hooks_Initializer' ) && file_exists( $core_path . 'class-hooks-initializer.php' ) ) {
			require_once $core_path . 'class-hooks-initializer.php';
		}
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Hooks_Initializer' ) ) {
			Hooks_Initializer::init();
		}

		// Register Shadow by Christopher Ross-managed event post type and migration hooks.
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Training_Event_CPT' ) ) {
			Training_Event_CPT::init();
		}

		// 3. Initialize menu system
		if ( ! class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Menu_Manager' ) && file_exists( $core_path . 'class-menu-manager.php' ) ) {
			require_once $core_path . 'class-menu-manager.php';
		}
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Menu_Manager' ) ) {
			Menu_Manager::init();
		}

		// 4. Load dashboard page
		self::load_dashboard_page();

		// 5. (Removed) Automations module

		// 6. Engage module removed.

		// 7. Load performance optimizer
		self::load_performance_optimizer();

		// 9. Load reporting and intelligence features
		self::load_reporting_intelligence();

		// 10. Load AJAX handlers for utilities
		self::load_ajax_handlers();

		// 13. (Removed) Usage analytics

		// 14. (Removed) Automations recipes

		// 13. Load dashboard integrations (WP admin bar, At a Glance widget)
		self::load_dashboard_integrations();

		// 14. Load pro addon integration
		self::load_pro_integration();

		// 15. Load WP-CLI commands
		self::load_cli_commands();

		// 16. Fire initialization complete hook
		do_action( 'thisismyurl_shadow_core_initialized' );
	}

	/**
	 * Remove cached references to diagnostics or treatments that no longer exist.
	 *
	 * This runs before registry initialization so discovery starts from a clean
	 * baseline after refactors, file renames, or readiness-state changes.
	 *
	 * @return void
	 */
	private static function cleanup_stale_registry_references() {
		if ( function_exists( 'thisismyurl_shadow_maybe_cleanup_removed_diagnostic_treatment_references' ) ) {
			thisismyurl_shadow_maybe_cleanup_removed_diagnostic_treatment_references();
		}
	}

	/**
	 * Load low-level support classes that other systems depend on.
	 *
	 * These includes are intentionally conservative. The plugin avoids loading
	 * every possible file up front and instead loads only the shared classes that
	 * are required before subsystem-specific initialization can safely begin.
	 *
	 * @return void
	 */
	private static function load_core_classes() {
		$core_path = THISISMYURL_SHADOW_PATH . 'includes/systems/core/';

		// Already loaded by thisismyurl-shadow.php:
		// - class-treatment-base.php
		// - class-ajax-handler-base.php
		// - class-diagnostic-base.php
		// - class-color-utils.php
		// - class-theme-data-provider.php
		// - class-activity-logger.php
		// - class-error-handler.php

		// PHASE 1 OPTIMIZATION: Load database and cache management classes
		if ( file_exists( $core_path . 'class-database-indexes.php' ) ) {
			require_once $core_path . 'class-database-indexes.php';
		}

		if ( file_exists( $core_path . 'class-cache-manager.php' ) ) {
			require_once $core_path . 'class-cache-manager.php';
		}

		// PHASE 3 OPTIMIZATION: Load query optimization classes
		if ( file_exists( $core_path . 'class-query-batch-optimizer.php' ) ) {
			require_once $core_path . 'class-query-batch-optimizer.php';
		}

		// PHASE 3 OPTIMIZATION: Load dashboard page-level cache
		if ( file_exists( $core_path . 'class-dashboard-cache.php' ) ) {
			require_once $core_path . 'class-dashboard-cache.php';
		}

		// Load additional core classes
		if ( file_exists( $core_path . 'class-kpi-tracker.php' ) ) {
			require_once $core_path . 'class-kpi-tracker.php';
		}

		if ( file_exists( $core_path . 'class-finding-status-manager.php' ) ) {
			require_once $core_path . 'class-finding-status-manager.php';
		}

		if ( file_exists( $core_path . 'class-dashboard-widgets.php' ) ) {
			require_once $core_path . 'class-dashboard-widgets.php';
		}

		if ( file_exists( $core_path . 'class-site-health-explanations.php' ) ) {
			require_once $core_path . 'class-site-health-explanations.php';
		}

		$treatment_hooks_path = THISISMYURL_SHADOW_PATH . 'includes/utils/class-treatment-hooks.php';
		if ( file_exists( $treatment_hooks_path ) ) {
			require_once $treatment_hooks_path;
		}
		if ( file_exists( $core_path . 'class-trend-chart.php' ) ) {
			require_once $core_path . 'class-trend-chart.php';
		}

		if ( file_exists( $core_path . 'class-abstract-registry.php' ) ) {
			require_once $core_path . 'class-abstract-registry.php';
		}

		if ( file_exists( $core_path . 'class-category-metadata.php' ) ) {
			require_once $core_path . 'class-category-metadata.php';
		}

		// Load Diagnostic Scheduler (manages diagnostic frequency and scheduling)
		$utils_path = THISISMYURL_SHADOW_PATH . 'includes/utils/';
		if ( file_exists( $utils_path . 'class-diagnostic-scheduler.php' ) ) {
			require_once $utils_path . 'class-diagnostic-scheduler.php';
			if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Diagnostic_Scheduler' ) ) {
				\ThisIsMyURL\Shadow\Core\Diagnostic_Scheduler::init();
			}
		}
	}

	/**
	 * Load dashboard page
	 *
	 * NOTE: dashboard shared helpers and v2 callbacks are loaded early in
	 * thisismyurl-shadow.php so dashboard callbacks exist before admin_menu fires.
	 *
	 * @return void
	 */
	private static function load_dashboard_page() {
		// Dashboard callbacks already loaded in thisismyurl-shadow.php
	}

	/**
	 * Automations module removed.
	 */

	/**
	 * Load performance optimizer
	 *
	 * @return void
	 */
	private static function load_performance_optimizer() {
		$optimizer_path = THISISMYURL_SHADOW_PATH . 'includes/optimizer/';

		if ( file_exists( $optimizer_path . 'class-performance-optimizer.php' ) ) {
			require_once $optimizer_path . 'class-performance-optimizer.php';

			if ( class_exists( '\\ThisIsMyURL\\Shadow\\Optimizer\\Performance_Optimizer' ) ) {
				\ThisIsMyURL\Shadow\Optimizer\Performance_Optimizer::init();
			}
		}

		// PHASE 3 OPTIMIZATION: Initialize query batch optimizer
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Query_Batch_Optimizer' ) ) {
			\ThisIsMyURL\Shadow\Core\Query_Batch_Optimizer::init();
		}

		// PHASE 3 OPTIMIZATION: Initialize dashboard cache system
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Dashboard_Cache' ) ) {
			\ThisIsMyURL\Shadow\Core\Dashboard_Cache::init();
		}
	}

	/**
	 * Load reporting and intelligence features
	 *
	 * Loads advanced reporting capabilities including:
	 * - Predictive Analytics & Forecasting
	 * - Competitive Benchmarking
	 * - Real-Time Monitoring & Alerting
	 * - Visual Health Journey
	 * - Executive ROI Dashboard
	 * - Team Collaboration
	 *
	 * @since 0.6095
	 * @return void
	 */
	private static function load_reporting_intelligence() {
		$reporting_path = THISISMYURL_SHADOW_PATH . 'includes/reporting/';

		// Load reporting intelligence classes
		$reporting_classes = array(
			'class-predictive-analytics.php',
			'class-competitive-benchmarking.php',
			'class-realtime-monitoring.php',
			'class-visual-health-journey.php',
		);

		foreach ( $reporting_classes as $file ) {
			if ( file_exists( $reporting_path . $file ) ) {
				require_once $reporting_path . $file;
			}
		}

		// Load Phase 4 Infrastructure (Export, Integrations, Analytics)
		$phase4_classes = array(
			'class-report-alert-manager.php',
			'class-report-integration-manager.php',
			'class-report-annotation-manager.php',
			'class-report-analytics-engine.php',
			'class-phase4-initializer.php',
		);

		foreach ( $phase4_classes as $file ) {
			if ( file_exists( $reporting_path . $file ) ) {
				require_once $reporting_path . $file;
			}
		}

		// Load Phase 5: Academy & Training Integration
		self::load_academy_training();

		// Initialize real-time monitoring if enabled
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Reporting\\Realtime_Monitoring' ) ) {
			\ThisIsMyURL\Shadow\Reporting\Realtime_Monitoring::init();
		}
	}

	/**
	 * Load pro addon integration
	 *
	 * @return void
	 */
	private static function load_pro_integration() {
		// Load pro addon if available (separate plugin)
		do_action( 'thisismyurl_shadow_load_pro_features' );
	}

	/**
	 * Load WP-CLI commands
	 *
	 * @return void
	 */
	private static function load_cli_commands() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		$cli_path = THISISMYURL_SHADOW_PATH . 'includes/utils/cli/';

		if ( file_exists( $cli_path . 'class-thisismyurl-shadow-cli.php' ) ) {
			require_once $cli_path . 'class-thisismyurl-shadow-cli.php';

			// CLI will auto-register
		}
	}

	/**
	 * Load AJAX handlers for utilities
	 *
	 * @since 0.6095
	 * @return void
	 */
	private static function load_ajax_handlers() {
		if ( ! is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$ajax_path = THISISMYURL_SHADOW_PATH . 'includes/admin/ajax/';

		// (Removed) Utility AJAX handlers (clone, conflict detection)
	}

	/**
	 * Automations recipes removed.
	 */

	/**
	 * Load Academy & Training Integration (Phase 5)
	 *
	 * Loads educational content systems including:
	 * - KB Article Management
	 * - Training Widget
	 * - Weekly Tips Widget
	 * - Post-Fix Education
	 * - Contextual Learning Tips
	 *
	 * @since 0.6095
	 * @return void
	 */
	private static function load_academy_training() {
		$content_path = THISISMYURL_SHADOW_PATH . 'includes/content/';

		// Load KB Article Manager
		if ( file_exists( $content_path . 'class-kb-article-manager.php' ) ) {
			require_once $content_path . 'class-kb-article-manager.php';

			if ( class_exists( '\\ThisIsMyURL\\Shadow\\Content\\KB_Article_Manager' ) ) {
				\ThisIsMyURL\Shadow\Content\KB_Article_Manager::init();
			}
		}

		// Load Training Widget
		if ( file_exists( $content_path . 'class-training-widget.php' ) ) {
			require_once $content_path . 'class-training-widget.php';

			if ( class_exists( '\\ThisIsMyURL\\Shadow\\Content\\Training_Widget' ) ) {
				\ThisIsMyURL\Shadow\Content\Training_Widget::init();
			}
		}

		// Load Weekly Tips Widget
		if ( file_exists( $content_path . 'class-weekly-tips-widget.php' ) ) {
			require_once $content_path . 'class-weekly-tips-widget.php';

			if ( class_exists( '\\ThisIsMyURL\\Shadow\\Content\\Weekly_Tips_Widget' ) ) {
				\ThisIsMyURL\Shadow\Content\Weekly_Tips_Widget::init();
			}
		}

		// Load Post-Fix Education
		if ( file_exists( $content_path . 'class-post-fix-education.php' ) ) {
			require_once $content_path . 'class-post-fix-education.php';

			if ( class_exists( '\\ThisIsMyURL\\Shadow\\Content\\Post_Fix_Education' ) ) {
				\ThisIsMyURL\Shadow\Content\Post_Fix_Education::init();
			}
		}

	}

	/**
	 * Load dashboard integrations
	 *
	 * Registers the Shadow by Christopher Ross overview widget on the WP dashboard,
	 * adds an admin bar shortcut, and adds the At-a-Glance problem count.
	 *
	 * @return void
	 */
	private static function load_dashboard_integrations() {
		if ( ! is_admin() ) {
			return;
		}

		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Dashboard_Integrations' ) ) {
			\ThisIsMyURL\Shadow\Admin\Dashboard_Integrations::subscribe();
		}

		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Dashboard_Glance_Problems' ) ) {
			\ThisIsMyURL\Shadow\Admin\Dashboard_Glance_Problems::init();
		}
	}

	/**
	 * Get current plugin initialization status.
	 *
	 * @since 0.6095
	 * @return array {
	 *     Status information.
	 *
	 *     @type bool  $ready  Whether plugin is ready
	 *     @type array $errors Any initialization errors
	 * }
	 */
	public static function get_status() {
		return array(
			'ready'  => true,
			'errors' => array(),
		);
	}
}
