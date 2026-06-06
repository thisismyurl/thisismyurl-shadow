<?php
/**
 * Treatment: Defer non-critical JavaScript
 *
 * By default, WordPress enqueues scripts with a plain `<script src="...">` tag
 * that blocks HTML parsing until the file is downloaded and executed. Scripts
 * that are not required for initial rendering (analytics, widgets, etc.) should
 * carry the `defer` attribute so the browser only executes them after the page
 * has parsed, improving First Contentful Paint.
 *
 * This treatment stores a flag that tells the Christopher Ross Shadow bootstrap to add a
 * `script_loader_tag` filter applying `defer` to all frontend script enqueues
 * except those in a known-safe exclusion list:
 *   - jquery, jquery-core, jquery-migrate (must execute synchronously)
 *   - Scripts already carrying defer or async attributes
 *   - Inline scripts (no src attribute)
 *
 * The `defer` attribute is safe for the vast majority of plugins and themes.
 * If a specific script breaks, explicitly exclude it from deferral by adding
 * `data-no-defer` to its registered data attributes.
 *
 * Undo: deletes the flag; bootstrap stops adding the script_loader_tag filter.
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds defer attribute to non-critical frontend scripts via script_loader_tag.
 */
class Treatment_Noncritical_Js_Deferred extends Treatment_Base {

	/** @var string */
	protected static $slug = 'noncritical-js-deferred';

	/**
	 * Script handles that must never receive defer — they are synchronous
	 * dependencies that other code relies on being immediately available.
	 */
	private const EXCLUDED_HANDLES = array(
		'jquery',
		'jquery-core',
		'jquery-migrate',
		'wp-polyfill',
	);

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Store the flag so the bootstrap defers non-critical scripts.
	 *
	 * @return array
	 */
	public static function apply(): array {
		update_option( 'thisismyurl_shadow_defer_noncritical_js', true );

		return array(
			'success' => true,
			'message' => __( 'Non-critical JavaScript deferral enabled. The defer attribute will be added to script tags on the frontend (excluding jQuery and critical polyfills) from the next page load. If a specific script breaks, add data-no-defer to its registration or contact support.', 'thisismyurl-shadow' ),
			'details' => array(
				'excluded_handles' => self::EXCLUDED_HANDLES,
			),
		);
	}

	/**
	 * Remove the flag; bootstrap stops applying the defer filter.
	 *
	 * @return array
	 */
	public static function undo(): array {
		delete_option( 'thisismyurl_shadow_defer_noncritical_js' );

		return array(
			'success' => true,
			'message' => __( 'JavaScript deferral removed. All scripts will load with default WordPress behaviour (synchronously) from the next page load.', 'thisismyurl-shadow' ),
		);
	}
}
