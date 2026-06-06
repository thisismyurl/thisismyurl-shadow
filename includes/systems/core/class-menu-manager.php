<?php
/**
 * Christopher Ross Shadow Menu Manager
 *
 * Centralized registration of WordPress admin menus for Christopher Ross Shadow.
 * Extracted from thisismyurl-shadow.php as part of Phase 4.5 refactoring.
 *
 * Philosophy: Commandment #7 (Ridiculously Good - obvious structure)
 *
 * @package ThisIsMyURL\Shadow
 * @subpackage Core
 */

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ThisIsMyURL\Shadow\Admin\Post_Types_Page;
use ThisIsMyURL\Shadow\Core\Form_Param_Helper;
/**
 * Manages Christopher Ross Shadow admin menu registration and setup
 */
class Menu_Manager {


	/**
	 * Initialize menus (call on admin_menu hook)
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_legacy_redirects' ) );

		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Post_Types_Page' ) && method_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Post_Types_Page', 'enqueue_assets' ) ) {
			add_action( 'admin_enqueue_scripts', array( 'ThisIsMyURL\\Shadow\\Admin\\Post_Types_Page', 'enqueue_assets' ) );
		}
	}

	/**
	 * Register all Christopher Ross Shadow admin menus
	 *
	 * @return void
	 */
	public static function register_menus() {
		$admin_capability    = self::get_admin_capability();
		$analyst_capability  = self::get_analyst_capability();
		$core_pages_released = self::are_core_pages_released();

		// Top-level menu.
		add_menu_page(
			'Christopher Ross Shadow',
			'Christopher Ross Shadow',
			$admin_capability,
			'thisismyurl-shadow',
			'thisismyurl_shadow_render_dashboard_v2',
			'dashicons-shield-alt',
			999
		);

		// Dashboard submenu.
		add_submenu_page(
			'thisismyurl-shadow',
			__( 'Dashboard', 'thisismyurl-shadow' ),
			__( 'Dashboard', 'thisismyurl-shadow' ),
			$admin_capability,
			'thisismyurl-shadow',
			'thisismyurl_shadow_render_dashboard_v2'
		);

		// Guardian (Diagnostics & Treatments System).
		// The diagnostic detail view is rendered by thisismyurl_shadow_render_guardian_page()
		// when ?diagnostic= is present, so no separate hidden page registration is needed.
                add_submenu_page(
                        'thisismyurl-shadow',
                        __( 'Guardian', 'thisismyurl-shadow' ),
                        __( 'Guardian', 'thisismyurl-shadow' ),
                        $analyst_capability,
                        'thisismyurl-shadow-guardian',
                        'thisismyurl_shadow_render_guardian_page'
                );

                if ( $core_pages_released ) {
                        // Findings (Kanban Board).
                        add_submenu_page(
                                'thisismyurl-shadow',
                                __( 'Findings', 'thisismyurl-shadow' ),
                                __( 'Findings', 'thisismyurl-shadow' ),
                                $analyst_capability,
                                'thisismyurl-shadow-findings',
                                'thisismyurl_shadow_render_findings'
			);

			// Automations (Workflow Automation).
			add_submenu_page(
				'thisismyurl-shadow',
				__( 'Automations', 'thisismyurl-shadow' ),
				__( 'Automations', 'thisismyurl-shadow' ),
				$analyst_capability,
				'thisismyurl-shadow-automations',
				'thisismyurl_shadow_render_workflow_builder'
			);
		}

		// Vault Lite local backup page.
		add_submenu_page(
			'thisismyurl-shadow',
			__( 'Vault Lite', 'thisismyurl-shadow' ),
			__( 'Vault Lite', 'thisismyurl-shadow' ),
			$admin_capability,
			'thisismyurl-shadow-vault-lite',
			'thisismyurl_shadow_render_vault_lite'
		);

		// Settings.
		add_submenu_page(
			'thisismyurl-shadow',
			__( 'Settings', 'thisismyurl-shadow' ),
			__( 'Settings', 'thisismyurl-shadow' ),
			$admin_capability,
			'thisismyurl-shadow-settings',
			'thisismyurl_shadow_render_settings'
		);

		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Post_Types_Page' ) && method_exists( '\\ThisIsMyURL\\Shadow\\Admin\\Post_Types_Page', 'subscribe' ) ) {
			Post_Types_Page::subscribe();
		}

		// Scan Settings is now a tab on Settings page, not a separate menu.
		// Legacy redirect handled in handle_legacy_redirects().

		

		// Achievements (with Leaderboard & Rewards as submenus).
		if ( class_exists( '\ThisIsMyURL\Shadow\Gamification\Gamification_Release_Gate' ) && \ThisIsMyURL\Shadow\Gamification\Gamification_Release_Gate::is_released() ) {
			add_submenu_page(
				'thisismyurl-shadow',
				__( 'Achievements', 'thisismyurl-shadow' ),
				__( 'Achievements', 'thisismyurl-shadow' ),
				$analyst_capability,
				'thisismyurl-shadow-achievements',
				array( 'ThisIsMyURL\Shadow\Gamification\Gamification_UI', 'render_achievements_page' )
			);
		}

		// Vault Lite now has its own dedicated submenu page.
	}

	/**
	 * Handle legacy URL redirects for bookmarks/external links
	 *
	 * @return void
	 */
	public static function handle_legacy_redirects() {
		if ( ! Form_Param_Helper::has_get( 'page' ) ) {
			return;
		}

		$page                = Form_Param_Helper::get( 'page', 'text', '' );
		$tab                 = Form_Param_Helper::get( 'tab', 'text', '' );
		$core_pages_released = self::are_core_pages_released();

		if ( in_array( $page, array( 'thisismyurl-shadow-findings', 'thisismyurl-shadow-automations' ), true ) && ! $core_pages_released ) {
			if ( current_user_can( self::get_analyst_capability() ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=thisismyurl-shadow' ) );
				exit;
			}
		}

		// TODO(rename-v2): `wpshadow-*` admin page slugs below are preserved as
		// inbound legacy slugs that bookmarks, plugin-update notices, and old
		// admin URLs still hit. They are read here only as the redirect SOURCE
		// (the destinations have all been renamed to `thisismyurl-shadow-*`).
		// A future major version can drop these once usage telemetry shows the
		// old slugs no longer receive traffic.
		if ( 'wpshadow-academy' === $page && class_exists( '\\ThisIsMyURL\\Shadow\\Academy\\Academy_Release_Gate' ) && ! \ThisIsMyURL\Shadow\Academy\Academy_Release_Gate::is_available() ) {
			if ( current_user_can( self::get_admin_capability() ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=thisismyurl-shadow' ) );
				exit;
			}
		}

		if ( in_array( $page, array( 'thisismyurl-shadow-achievements', 'wpshadow-leaderboard', 'wpshadow-rewards' ), true ) && class_exists( '\ThisIsMyURL\Shadow\Gamification\Gamification_Release_Gate' ) && ! \ThisIsMyURL\Shadow\Gamification\Gamification_Release_Gate::is_released() ) {
			if ( current_user_can( self::get_analyst_capability() ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=thisismyurl-shadow' ) );
				exit;
			}
		}

		if ( 'thisismyurl-shadow-settings' === $page && 'backups' === $tab ) {
			if ( current_user_can( self::get_admin_capability() ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=thisismyurl-shadow-vault-lite' ) );
				exit;
			}
		}

		$redirects = array(
			'thisismyurl-shadow-guardian-reports'       => 'thisismyurl-shadow',
			'thisismyurl-shadow-guardian-notifications' => 'thisismyurl-shadow-settings&tab=notifications',
			'wpshadow-scan-settings'          => 'thisismyurl-shadow-settings&tab=scan-settings',
			'wpshadow-tools'                  => 'thisismyurl-shadow',
			'wpshadow-defensive'              => 'thisismyurl-shadow-settings&tab=defensive',
			'wpshadow-kpi'                    => 'thisismyurl-shadow-settings&tab=kpi',
			'wpshadow-learning'               => 'thisismyurl-shadow-settings&tab=learning',
			'wpshadow-cultural'               => 'thisismyurl-shadow-settings&tab=cultural',
		);

		if ( isset( $redirects[ $page ] ) ) {
			if ( 'thisismyurl-shadow-guardian-reports' === $page || 'thisismyurl-shadow-guardian-notifications' === $page ) {
				$capability = self::get_admin_capability();
			} else {
				$capability = self::get_analyst_capability();
			}

			if ( current_user_can( $capability ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . $redirects[ $page ] ) );
				exit;
			}
		}
	}

	/**
	 * Register Christopher Ross Shadow settings link on plugins page
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified action links.
	 */
	public static function add_settings_link( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=thisismyurl-shadow' ) ) . '">Settings</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Check whether Findings, Guardian, and Automations pages are released.
	 *
	 * @since 0.6095
	 * @return bool True when pages should be visible.
	 */
	private static function are_core_pages_released() {
		$release_datetime = (string) apply_filters( 'thisismyurl_shadow_core_pages_release_datetime', '2026-04-30 23:59:59' );

		try {
			$timezone = wp_timezone();
			$release  = new \DateTimeImmutable( $release_datetime, $timezone );
			$now      = new \DateTimeImmutable( 'now', $timezone );

			return $now >= $release;
		} catch ( \Exception $exception ) {
			return false;
		}
	}

	/**
	 * Get capability required for admin-level Christopher Ross Shadow pages.
	 *
	 * @return string Capability name.
	 */
	private static function get_admin_capability() {
		if ( is_multisite() && is_network_admin() ) {
			return 'manage_network_options';
		}

		return 'manage_options';
	}

	/**
	 * Get capability required for analyst/read-only Christopher Ross Shadow pages.
	 *
	 * @return string Capability name.
	 */
	private static function get_analyst_capability() {
		return self::get_admin_capability();
	}
}
