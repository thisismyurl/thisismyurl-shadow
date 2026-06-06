<?php
/**
 * Emoji in Admin Diagnostic
 *
 * Checks whether WordPress emoji detection scripts and styles are still
 * injected into wp-admin pages. Every modern browser renders emoji natively,
 * making these assets unnecessary overhead for all authors and administrators.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Emoji_In_Admin Class
 *
 * @since 0.6095
 */
class Diagnostic_Emoji_In_Admin extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'emoji-in-admin';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Emoji Scripts in Admin';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress emoji detection scripts and inline styles are injected into wp-admin pages. All modern browsers render emoji natively, so these assets add unnecessary overhead to every backend page load for everyone who logs in.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'performance';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

	/**
	 * Run the diagnostic check.
	 *
	 * WordPress registers print_emoji_detection_script and print_emoji_styles on
	 * admin_print_scripts / admin_print_styles respectively. Performance plugins
	 * remove these actions during their own init. By the time check() runs (after
	 * plugins are loaded), has_action() reliably reflects whether the injections
	 * will fire on admin pages.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when admin emoji scripts are active, null when already removed.
	 */
	public static function check(): ?array {
		// Check if known performance plugins have already removed admin emoji.
		$perf_options = array(
			'perfmatters_options' => array( 'extras', 'disable_emoji' ),
			'wp_rocket_settings'  => array( 'emoji' ),
		);

		foreach ( $perf_options as $option_name => $path ) {
			$opt = get_option( $option_name, array() );
			if ( ! is_array( $opt ) ) {
				continue;
			}

			// Walk the key path.
			$value = $opt;
			foreach ( $path as $key ) {
				if ( ! isset( $value[ $key ] ) ) {
					$value = null;
					break;
				}
				$value = $value[ $key ];
			}

			if ( ! empty( $value ) ) {
				return null; // Already handled by a performance plugin.
			}
		}

		// WP Asset CleanUp presence implies it may handle this.
		if ( false !== get_option( 'wpacu_settings', false ) ) {
			return null;
		}

		// Autoptimize extras.
		$ao = get_option( 'autoptimize_extra_settings', array() );
		if ( is_array( $ao ) && ! empty( $ao['autoptimize_extra_remove_emojis'] ) ) {
			return null;
		}

		// Primary test: is the admin emoji hook still registered?
		// WordPress hooks these in wp-includes/default-filters.php.
		// Performance plugins remove them in their own init hooks.
		// By the time check() runs, removal has either happened or not.
		$script_hooked = has_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		$style_hooked  = has_action( 'admin_print_styles', 'print_emoji_styles' );

		if ( ! $script_hooked && ! $style_hooked ) {
			return null;
		}

		$active = array();
		if ( $script_hooked ) {
			$active[] = 'admin_print_scripts → print_emoji_detection_script';
		}
		if ( $style_hooked ) {
			$active[] = 'admin_print_styles → print_emoji_styles';
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __(
				'WordPress emoji detection scripts and styles are being injected on wp-admin pages. Every modern browser renders emoji natively without these assets. Removing them eliminates a small JavaScript payload and an inline style block from every single admin page load for every logged-in user — particularly noticeable on high-traffic editorial sites.',
				'thisismyurl-shadow'
			),
			'severity'     => 'low',
			'threat_level' => 12,
			'details'      => array(
				'active_hooks' => $active,
				'note'         => __(
					'Use Perfmatters or WP Rocket to disable emoji scripts in the admin, or add remove_action() calls for admin_print_scripts and admin_print_styles in a functionality plugin.',
					'thisismyurl-shadow'
				),
			),
		);
	}
}
