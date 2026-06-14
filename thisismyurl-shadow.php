<?php
/**
 * Plugin Name: Shadow by Christopher Ross
 * Plugin URI: https://thisismyurl.com/shadow/
 * Description: Local-first WordPress diagnostics and safer fixes — file review and plain-English guidance before risky changes.
 * Version: 1.6147
 * Author: thisismyurl
 * Author URI: https://profiles.wordpress.org/thisismyurl/
 * Text Domain: thisismyurl-shadow
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * License:           GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ThisIsMyURL\Shadow
 * @since 0.6123
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define the plugin-wide constants used during bootstrap.
 *
 * These are intentionally declared in the main plugin file because they are
 * needed before the autoloader and service bootstrap can resolve any classes.
 * In practice they form the contract between WordPress' plugin loader and the
 * rest of the Shadow by Christopher Ross codebase.
 */
define( 'THISISMYURL_SHADOW_VERSION', '1.6147' );
define( 'THISISMYURL_SHADOW_FILE', __FILE__ );
define( 'THISISMYURL_SHADOW_BASENAME', plugin_basename( __FILE__ ) );
define( 'THISISMYURL_SHADOW_PATH', plugin_dir_path( __FILE__ ) );
define( 'THISISMYURL_SHADOW_URL', plugin_dir_url( __FILE__ ) );

/**
 * Composer Autoloader (PSR-4)
 *
 * Enables automatic class loading for PSR-4 compliant files.
 */
if ( file_exists( THISISMYURL_SHADOW_PATH . 'vendor/autoload.php' ) ) {
	require_once THISISMYURL_SHADOW_PATH . 'vendor/autoload.php';
}

/**
 * Bootstrap Autoloader
 *
 * Automatically loads all Shadow by Christopher Ross classes in dependency order.
 * Replaces 130+ manual require_once calls.
 *
 * Phase 4: Bootstrap Consolidation - eliminates manual loading.
 * Phase 6: Final Polish - clean implementation.
 */
require_once THISISMYURL_SHADOW_PATH . 'includes/systems/core/class-bootstrap-autoloader.php';
require_once THISISMYURL_SHADOW_PATH . 'includes/systems/core/class-hooks-initializer.php';
require_once THISISMYURL_SHADOW_PATH . 'includes/systems/core/class-rename-migration-2026.php';

/**
 * Run the rename migration on every admin_init as a defensive backstop. The
 * class is gated by a one-shot flag option and short-circuits after the first
 * successful run, so the cost on subsequent loads is one get_option() call.
 */
add_action(
	'admin_init',
	static function (): void {
		\ThisIsMyURL\Shadow\Core\Rename_Migration_2026::run();
	},
	1
);

if ( ! function_exists( 'thisismyurl_shadow_activate_plugin' ) ) {
	/**
	 * Run plugin activation tasks.
	 *
	 * Activation is intentionally kept thin here. The main file only ensures the
	 * autoloader exists, then delegates real setup work to Hooks_Initializer so
	 * lifecycle behavior stays centralized in one place.
	 *
	 * @return void
	 */
	function thisismyurl_shadow_activate_plugin() {
		\ThisIsMyURL\Shadow\Core\Bootstrap_Autoloader::init();
		\ThisIsMyURL\Shadow\Core\Hooks_Initializer::on_activate();

		// One-shot legacy data migration from the wpshadow-prefixed identifiers
		// the plugin shipped under prior to the WordPress.org rename.
		if ( class_exists( '\\ThisIsMyURL\\Shadow\\Core\\Rename_Migration_2026' ) ) {
			\ThisIsMyURL\Shadow\Core\Rename_Migration_2026::run();
		}
	}
}

if ( ! function_exists( 'thisismyurl_shadow_deactivate_plugin' ) ) {
	/**
	 * Run plugin deactivation tasks.
	 *
	 * Deactivation mirrors activation: this file boots the minimum runtime and
	 * then hands off to the centralized lifecycle coordinator.
	 *
	 * @return void
	 */
	function thisismyurl_shadow_deactivate_plugin() {
		\ThisIsMyURL\Shadow\Core\Bootstrap_Autoloader::init();
		\ThisIsMyURL\Shadow\Core\Hooks_Initializer::on_deactivate();
	}
}

register_activation_hook( __FILE__, 'thisismyurl_shadow_activate_plugin' );
register_deactivation_hook( __FILE__, 'thisismyurl_shadow_deactivate_plugin' );

/**
 * Load UI callback functions used by admin menus.
 *
 * These files define global callbacks and helper functions referenced by
 * add_menu_page()/add_submenu_page().
 */
$thisismyurl_shadow_ui_view_files = array(
	'includes/ui/views/functions-page-layout.php',
	'includes/ui/views/menu-stubs.php',
	'includes/ui/views/dashboard-shared.php',
	'includes/ui/views/dashboard-page-v2.php',
	'includes/systems/core/functions-runtime.php',
	'includes/systems/core/functions-treatment.php',
);

foreach ( $thisismyurl_shadow_ui_view_files as $thisismyurl_shadow_ui_view_file ) {
	$thisismyurl_shadow_ui_view_path = THISISMYURL_SHADOW_PATH . $thisismyurl_shadow_ui_view_file;
	if ( file_exists( $thisismyurl_shadow_ui_view_path ) ) {
		require_once $thisismyurl_shadow_ui_view_path;
	}
}

/**
 * Prime the autoloader as early as possible once plugins are loaded.
 *
 * This first bootstrap step does not initialize subsystems yet. Its only job
 * is to make classes resolvable before later init hooks begin wiring menus,
 * settings, diagnostics, and treatments.
 */
add_action(
	'plugins_loaded',
	function () {
		\ThisIsMyURL\Shadow\Core\Bootstrap_Autoloader::init();
	},
	1
);

/**
 * Load translations at a WordPress-safe point in the lifecycle.
 *
 * WordPress now expects text domains to be loaded on init or later, so this
 * hook runs before any user-facing systems start building labels or messages.
 */
add_action(
	'init',
	function () {
		// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
		load_plugin_textdomain( 'thisismyurl-shadow', false, dirname( THISISMYURL_SHADOW_BASENAME ) . '/languages' );
	},
	1
);

/**
 * Register plugin settings after translations are available.
 *
 * This ordering keeps translated labels available to the Settings API while
 * still running early enough that downstream systems can rely on defaults.
 */
add_action(
	'init',
	function () {
		\ThisIsMyURL\Shadow\Core\Settings_Registry::register();
	},
	5
);

/**
 * Hand off to the service bootstrap once WordPress init is underway.
 *
 * Plugin_Bootstrap owns the dependency order for the rest of the plugin, so
 * the main file stops making decisions here and delegates orchestration.
 */
add_action(
	'init',
	function () {
		\ThisIsMyURL\Shadow\Core\Plugin_Bootstrap::init();
	},
	20
);

/**
 * Cache-suppression helper for stateful dashboard render callbacks.
 *
 * WordPress.org review forbids defining cache-busting constants on a global
 * hook. Render callbacks for highly stateful screens (e.g. the live diagnostic
 * dashboard) call this helper inside their own page-render path so the
 * constants are only defined when that specific admin page is actually being
 * rendered. Most pages will not call it.
 *
 * @since 0.6096
 * @return void
 */
if ( ! function_exists( 'thisismyurl_shadow_suppress_page_cache' ) ) {
	function thisismyurl_shadow_suppress_page_cache(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}

		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}

		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		}

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
	}
}
