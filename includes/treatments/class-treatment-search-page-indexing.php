<?php
/**
 * Treatment: Exclude internal search result pages from indexing
 *
 * Updates the active SEO plugin setting when Yoast SEO or Rank Math is
 * present. If no supported SEO plugin is active, Shadow by Christopher Ross enables a native
 * runtime noindex rule for search result pages.
 *
 * Undo restores the previous SEO-plugin option payloads and the previous
 * Shadow by Christopher Ross native runtime toggle.
 *
 * @package ThisIsMyURL\Shadow
 * @since   0.7056
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Treatments;

use ThisIsMyURL\Shadow\Core\Treatment_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Treatment_Search_Page_Indexing extends Treatment_Base {

	/** @var string */
	protected static $slug = 'search-page-indexing';

	private const BACKUP_OPTION = 'thisismyurl_shadow_search_page_indexing_backup';
	private const NATIVE_OPTION = 'thisismyurl_shadow_search_page_noindex_enabled';

	public static function get_risk_level(): string {
		return 'safe';
	}

	public static function apply(): array {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$has_yoast      = in_array( 'wordpress-seo/wp-seo.php', $active_plugins, true )
			|| in_array( 'wordpress-seo-premium/wp-seo-premium.php', $active_plugins, true );
		$has_rankmath   = in_array( 'seo-by-rank-math/rank-math.php', $active_plugins, true )
			|| in_array( 'seo-by-rank-math-pro/rank-math-pro.php', $active_plugins, true );

		$backup = array(
			'yoast'      => self::capture_option( 'wpseo_titles' ),
			'rank_math'  => self::capture_option( 'rank_math_settings_general' ),
			'native'     => self::capture_option( self::NATIVE_OPTION ),
			'has_yoast'  => $has_yoast,
			'has_rankmath' => $has_rankmath,
		);

		$changes = array();

		if ( $has_yoast ) {
			$titles = get_option( 'wpseo_titles', array() );
			if ( ! is_array( $titles ) ) {
				$titles = array();
			}

			if ( empty( $titles['noindex-search-wpseo'] ) ) {
				$titles['noindex-search-wpseo'] = true;
				update_option( 'wpseo_titles', $titles );
				$changes[] = __( 'Yoast SEO search pages set to noindex', 'thisismyurl-shadow' );
			}
		}

		if ( $has_rankmath ) {
			$general = get_option( 'rank_math_settings_general', array() );
			if ( ! is_array( $general ) ) {
				$general = array();
			}

			if ( empty( $general['noindex_search'] ) ) {
				$general['noindex_search'] = true;
				update_option( 'rank_math_settings_general', $general );
				$changes[] = __( 'Rank Math search pages set to noindex', 'thisismyurl-shadow' );
			}
		}

		if ( ! $has_yoast && ! $has_rankmath && ! (bool) get_option( self::NATIVE_OPTION, false ) ) {
			update_option( self::NATIVE_OPTION, true, false );
			$changes[] = __( 'Shadow by Christopher Ross native search-page noindex enabled', 'thisismyurl-shadow' );
		}

		if ( empty( $changes ) ) {
			delete_option( self::BACKUP_OPTION );
			return array(
				'success' => true,
				'message' => __( 'Search result pages were already configured to stay out of search indexes. No changes made.', 'thisismyurl-shadow' ),
			);
		}

		static::save_backup_value( self::BACKUP_OPTION, $backup );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %s: comma-separated list of changes applied */
				__( 'Search-page indexing protections applied: %s.', 'thisismyurl-shadow' ),
				implode( ', ', $changes )
			),
		);
	}

	public static function undo(): array {
		$loaded = static::load_backup_array(
			self::BACKUP_OPTION,
			array( 'yoast', 'rank_math', 'native', 'has_yoast', 'has_rankmath' ),
			true
		);

		if ( ! $loaded['found'] ) {
			return array(
				'success' => false,
				'message' => __( 'No previous search-page indexing settings were stored.', 'thisismyurl-shadow' ),
			);
		}

		$backup = $loaded['value'];

		self::restore_captured_option( 'wpseo_titles', $backup['yoast'] );
		self::restore_captured_option( 'rank_math_settings_general', $backup['rank_math'] );
		self::restore_captured_option( self::NATIVE_OPTION, $backup['native'] );

		return array(
			'success' => true,
			'message' => __( 'Search-page indexing settings restored to the previous state.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Capture whether an option existed and what value it held.
	 *
	 * @param string $option_name Option name.
	 * @return array{exists:bool,value:mixed}
	 */
	private static function capture_option( string $option_name ): array {
		$sentinel = '__thisismyurl_shadow_option_missing__';
		$value    = get_option( $option_name, $sentinel );

		return array(
			'exists' => $sentinel !== $value,
			'value'  => $sentinel !== $value ? $value : null,
		);
	}

	/**
	 * Restore or delete an option using a captured state payload.
	 *
	 * @param string                $option_name Option name.
	 * @param array<string,mixed>   $captured    Captured state.
	 * @return void
	 */
	private static function restore_captured_option( string $option_name, array $captured ): void {
		if ( ! empty( $captured['exists'] ) ) {
			update_option( $option_name, $captured['value'] );
			return;
		}

		delete_option( $option_name );
	}
}