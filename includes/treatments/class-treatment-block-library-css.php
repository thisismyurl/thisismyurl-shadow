<?php
/**
 * Treatment: Dequeue block library CSS on classic themes
 *
 * WordPress loads the block library stylesheet (wp-block-library, plus its
 * theme companion and global-styles sheets) on every front-end page. On
 * classic PHP-template themes that do not use the block editor for front-end
 * rendering, this CSS is almost entirely unused and adds unnecessary page
 * weight for every visitor.
 *
 * This treatment stores a flag that tells the Shadow by Christopher Ross bootstrap to dequeue
 * those three stylesheets on the frontend when the active theme is a classic
 * (non-block/non-FSE) theme. The flag is only applied at page load, so it
 * takes effect immediately from the next request.
 *
 * The treatment is not applied on block/FSE themes — those themes require the
 * block library CSS for correct rendering.
 *
 * Undo: deletes the flag; bootstrap stops dequeuing the stylesheets.
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
 * Dequeues wp-block-library CSS on classic PHP themes.
 */
class Treatment_Block_Library_Css extends Treatment_Base {

	/** @var string */
	protected static $slug = 'block-library-css';

	/** @return string */
	public static function get_risk_level(): string {
		return 'safe';
	}

	/**
	 * Store the flag so the bootstrap dequeues block library CSS on classic themes.
	 *
	 * @return array
	 */
	public static function apply(): array {
		// Guard: do not apply on block/FSE themes.
		if ( ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) ||
			current_theme_supports( 'block-templates' ) ) {
			return array(
				'success' => false,
				'message' => __( 'The active theme is a block/FSE theme that requires the block library CSS. This treatment only applies to classic PHP themes.', 'thisismyurl-shadow' ),
			);
		}

		update_option( 'thisismyurl_shadow_dequeue_block_library_css', true );

		return array(
			'success' => true,
			'message' => __( 'Block library CSS (wp-block-library, wp-block-library-theme, global-styles) will be dequeued on the frontend from the next page load. This has no visual impact on classic themes that do not use block rendering.', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Remove the flag; bootstrap stops dequeuing block library CSS.
	 *
	 * @return array
	 */
	public static function undo(): array {
		delete_option( 'thisismyurl_shadow_dequeue_block_library_css' );

		return array(
			'success' => true,
			'message' => __( 'Block library CSS dequeue removed. WordPress will load wp-block-library CSS on the frontend again from the next page load.', 'thisismyurl-shadow' ),
		);
	}
}
