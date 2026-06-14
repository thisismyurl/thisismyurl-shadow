<?php
/**
 * Treatment: Normalize posts per page to a sensible default
 *
 * Sites with very high posts-per-page values generate heavy archive pages,
 * while very low values bury content and reduce crawl depth. This treatment
 * sets the WordPress posts_per_page option to 10, which sits in the middle of
 * Shadow's recommended range for most sites.
 *
 * Undo: restores the previous posts_per_page value.
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

/**
 * Sets posts_per_page to 10.
 */
class Treatment_Posts_Per_Page_Optimized extends Treatment_Base {

	/** @var string */
	protected static $slug = 'posts-per-page-optimized';

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Set posts_per_page to 10.
	 *
	 * @return array
	 */
	public static function apply(): array {
		$current = (int) get_option( 'posts_per_page', 10 );

		if ( 10 === $current ) {
			return array(
				'success' => true,
				'message' => __( 'Posts per page is already set to 10. No changes made.', 'thisismyurl-shadow' ),
			);
		}

		static::save_backup_value( 'thisismyurl_shadow_posts_per_page_prev', $current );
		update_option( 'posts_per_page', 10 );

		return array(
			'success' => true,
			'message' => sprintf(
				/* translators: %d: previous posts per page value */
				__( 'Posts per page changed from %d to 10 to keep archive pages within a healthier performance and crawlability range.', 'thisismyurl-shadow' ),
				$current
			),
		);
	}

	/**
	 * Restore the previous posts_per_page value.
	 *
	 * @return array
	 */
	public static function undo(): array {
		return static::restore_option_from_backup(
			'posts_per_page',
			'thisismyurl_shadow_posts_per_page_prev',
			__( 'No previous posts-per-page value was stored.', 'thisismyurl-shadow' ),
			static function ( $previous ): string {
				return sprintf(
					/* translators: %d: restored posts per page value */
					__( 'Posts per page restored to %d.', 'thisismyurl-shadow' ),
					(int) $previous
				);
			}
		);
	}
}