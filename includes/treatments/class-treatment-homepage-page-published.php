<?php
/**
 * Treatment: Ensure the assigned homepage page is published
 *
 * When WordPress is configured to use a static homepage, the selected page
 * must exist and be published. This treatment publishes the assigned page when
 * it exists but is unpublished, or creates a simple placeholder Home page when
 * no usable page is selected.
 *
 * Undo: restores the previous reading settings, removes any page created by
 * Christopher Ross Shadow, and restores the prior post status when it was changed.
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
 * Publishes or creates the page used as the static homepage.
 */
class Treatment_Homepage_Page_Published extends Treatment_Base {

	/** @var string */
	protected static $slug = 'homepage-page-published';

	/** @return string */
	public static function get_risk_level(): string {
		return 'moderate';
	}

	/**
	 * Publish or create the page used as the homepage.
	 *
	 * @return array
	 */
	public static function apply(): array {
		$previous = array(
			'show_on_front' => (string) get_option( 'show_on_front', 'posts' ),
			'page_on_front' => (int) get_option( 'page_on_front', 0 ),
		);
		static::save_backup_value( 'thisismyurl_shadow_homepage_page_prev', $previous );

		$page_id     = $previous['page_on_front'];
		$page        = $page_id > 0 ? get_post( $page_id ) : null;
		$created_id  = 0;
		$old_status  = '';
		$message     = '';

		if ( $page instanceof \WP_Post ) {
			if ( 'publish' !== $page->post_status ) {
				$old_status = (string) $page->post_status;
				wp_update_post(
					array(
						'ID'          => $page->ID,
						'post_status' => 'publish',
					)
				);
				$message = sprintf(
					/* translators: %s: page title */
					__( 'The assigned homepage page "%s" was published.', 'thisismyurl-shadow' ),
					esc_html( $page->post_title )
				);
			} else {
				$message = __( 'The assigned homepage page is already published. Reading settings were reaffirmed.', 'thisismyurl-shadow' );
			}
		} else {
			$page_id = wp_insert_post(
				array(
					'post_title'     => __( 'Home', 'thisismyurl-shadow' ),
					'post_content'   => __( 'This homepage placeholder was created by Christopher Ross Shadow. Replace this content with your real homepage layout and copy.', 'thisismyurl-shadow' ),
					'post_status'    => 'publish',
					'post_type'      => 'page',
					'post_author'    => get_current_user_id() ?: 1,
					'comment_status' => 'closed',
				),
				true
			);

			if ( is_wp_error( $page_id ) ) {
				return array(
					'success' => false,
					'message' => sprintf(
						/* translators: %s: error message */
						__( 'Could not create a homepage page: %s', 'thisismyurl-shadow' ),
						$page_id->get_error_message()
					),
				);
			}

			$created_id = (int) $page_id;
			$message    = __( 'A published placeholder Home page was created and assigned as the static homepage.', 'thisismyurl-shadow' );
		}

		static::save_backup_value( 'thisismyurl_shadow_homepage_page_created', $created_id );
		static::save_backup_value( 'thisismyurl_shadow_homepage_page_prev_status', $old_status );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $page_id );

		return array(
			'success' => true,
			'message' => $message,
			'details' => array(
				'page_id' => (int) $page_id,
			),
		);
	}

	/**
	 * Restore the previous homepage configuration.
	 *
	 * @return array
	 */
	public static function undo(): array {
		$prev_loaded    = static::load_backup_array( 'thisismyurl_shadow_homepage_page_prev', array( 'show_on_front', 'page_on_front' ), true );
		$created_loaded = static::load_backup_value( 'thisismyurl_shadow_homepage_page_created', true );
		$status_loaded  = static::load_backup_value( 'thisismyurl_shadow_homepage_page_prev_status', true );

		if ( ! $prev_loaded['found'] || ! is_array( $prev_loaded['value'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No previous homepage configuration was stored.', 'thisismyurl-shadow' ),
			);
		}

		$previous = $prev_loaded['value'];
		$created  = $created_loaded['found'] ? (int) $created_loaded['value'] : 0;
		$status   = $status_loaded['found'] ? (string) $status_loaded['value'] : '';

		if ( $created > 0 ) {
			wp_delete_post( $created, true );
		}

		if ( '' !== $status ) {
			$current_page_id = (int) $previous['page_on_front'];
			if ( $current_page_id > 0 ) {
				wp_update_post(
					array(
						'ID'          => $current_page_id,
						'post_status' => $status,
					)
				);
			}
		}

		update_option( 'show_on_front', (string) $previous['show_on_front'] );
		update_option( 'page_on_front', (int) $previous['page_on_front'] );

		return array(
			'success' => true,
			'message' => __( 'Homepage page configuration restored to its previous state.', 'thisismyurl-shadow' ),
		);
	}
}