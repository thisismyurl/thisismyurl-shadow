<?php
/**
 * Treatment: Ensure the assigned posts page is published
 *
 * When a Posts Page is assigned in Reading Settings, that page must exist and
 * be published. This treatment publishes the assigned page when possible, or
 * creates a simple placeholder Blog page if the configured page is missing.
 *
 * Undo: restores the previous page_for_posts value, removes any page created
 * by Christopher Ross Shadow, and restores the prior post status when it was changed.
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
 * Publishes or creates the page used as the Posts Page.
 */
class Treatment_Posts_Page_Published extends Treatment_Base {

	/** @var string */
	protected static $slug = 'posts-page-published';

	/** @return string */
	public static function get_risk_level(): string {
		return 'moderate';
	}

	/**
	 * Publish or create the page used as the Posts Page.
	 *
	 * @return array
	 */
	public static function apply(): array {
		$previous_page_id = (int) get_option( 'page_for_posts', 0 );
		static::save_backup_value( 'thisismyurl_shadow_posts_page_prev_id', $previous_page_id );

		$page_id    = $previous_page_id;
		$page       = $page_id > 0 ? get_post( $page_id ) : null;
		$created_id = 0;
		$old_status = '';
		$message    = '';

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
					__( 'The assigned Posts Page "%s" was published.', 'thisismyurl-shadow' ),
					esc_html( $page->post_title )
				);
			} else {
				$message = __( 'The assigned Posts Page is already published. No content change was required.', 'thisismyurl-shadow' );
			}
		} else {
			$page_id = wp_insert_post(
				array(
					'post_title'     => __( 'Blog', 'thisismyurl-shadow' ),
					'post_content'   => '',
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
						__( 'Could not create a Posts Page: %s', 'thisismyurl-shadow' ),
						$page_id->get_error_message()
					),
				);
			}

			$created_id = (int) $page_id;
			$message    = __( 'A published placeholder Blog page was created and assigned as the Posts Page.', 'thisismyurl-shadow' );
		}

		static::save_backup_value( 'thisismyurl_shadow_posts_page_created', $created_id );
		static::save_backup_value( 'thisismyurl_shadow_posts_page_prev_status', $old_status );

		update_option( 'page_for_posts', (int) $page_id );

		return array(
			'success' => true,
			'message' => $message,
			'details' => array(
				'page_id' => (int) $page_id,
			),
		);
	}

	/**
	 * Restore the previous Posts Page configuration.
	 *
	 * @return array
	 */
	public static function undo(): array {
		$prev_loaded    = static::load_backup_value( 'thisismyurl_shadow_posts_page_prev_id', true );
		$created_loaded = static::load_backup_value( 'thisismyurl_shadow_posts_page_created', true );
		$status_loaded  = static::load_backup_value( 'thisismyurl_shadow_posts_page_prev_status', true );

		if ( ! $prev_loaded['found'] ) {
			return array(
				'success' => false,
				'message' => __( 'No previous Posts Page configuration was stored.', 'thisismyurl-shadow' ),
			);
		}

		$previous = (int) $prev_loaded['value'];
		$created  = $created_loaded['found'] ? (int) $created_loaded['value'] : 0;
		$status   = $status_loaded['found'] ? (string) $status_loaded['value'] : '';

		if ( $created > 0 ) {
			wp_delete_post( $created, true );
		}

		if ( '' !== $status && $previous > 0 ) {
			wp_update_post(
				array(
					'ID'          => $previous,
					'post_status' => $status,
				)
			);
		}

		update_option( 'page_for_posts', $previous );

		return array(
			'success' => true,
			'message' => __( 'Posts Page configuration restored to its previous state.', 'thisismyurl-shadow' ),
		);
	}
}