<?php
/**
 * Training Event CPT manager.
 *
 * @package ThisIsMyURL\Shadow
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register and manage event content in Shadow by Christopher Ross.
 */
class Training_Event_CPT {

	/**
	 * Post type slug.
	 */
	private const POST_TYPE = 'thisismyurl_shadow_event';

	/**
	 * Current rewrite/data version.
	 */
	private const VERSION = '3';

	/**
	 * Legacy-to-canonical post type mappings.
	 *
	 * @var array<string, string>
	 */
	private const LEGACY_POST_TYPE_MAP = array(
		// Short/canonical aliases â†’ current slugs.
		'training_event'   => 'thisismyurl_shadow_event',
		'training_program' => 'thisismyurl_shadow_training',
		'service'          => 'thisismyurl_shadow_service',
		'portfolio_item'   => 'thisismyurl_shadow_portfolio',
		'case_study'       => 'thisismyurl_shadow_case_study',
		'testimonial'      => 'thisismyurl_shadow_feedback',
		'tool'             => 'thisismyurl_shadow_tool',
		// Old thisismyurl_shadow_ prefixed slugs â†’ current thisismyurl_shadow_ slugs (post-rename compat).
		'thisismyurl_shadow_event'      => 'thisismyurl_shadow_event',
		'thisismyurl_shadow_training'   => 'thisismyurl_shadow_training',
		'thisismyurl_shadow_service'    => 'thisismyurl_shadow_service',
		'thisismyurl_shadow_portfolio'  => 'thisismyurl_shadow_portfolio',
		'thisismyurl_shadow_case_study' => 'thisismyurl_shadow_case_study',
		'thisismyurl_shadow_feedback' => 'thisismyurl_shadow_feedback',
		'thisismyurl_shadow_tool'       => 'thisismyurl_shadow_tool',
	);

	/**
	 * Legacy location taxonomy slug.
	 */
	private const LEGACY_LOCATION_TAXONOMY = 'location';

	/**
	 * Canonical location taxonomy slug.
	 */
	private const LOCATION_TAXONOMY = 'thisismyurl_shadow_location';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'maybe_migrate_legacy_content_types_and_taxonomies' ), 11 );
		add_action( 'init', array( __CLASS__, 'register_meta' ), 12 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_event_details' ) );
		add_action( 'init', array( __CLASS__, 'maybe_migrate_legacy_training_events' ), 22 );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrites' ), 99 );
		add_action( 'pre_get_posts', array( __CLASS__, 'map_legacy_post_type_queries' ), 1 );

		// Plugin_Bootstrap currently runs at init:20. If callbacks are registered here,
		// lower-priority init hooks (8/9/12) have already passed on this request.
		if ( did_action( 'init' ) ) {
			self::maybe_migrate_legacy_content_types_and_taxonomies();
			self::register_meta();
			self::maybe_migrate_legacy_training_events();
			self::maybe_flush_rewrites();
		}
	}

	/**
	 * Register required legacy site CPTs and taxonomy when missing.
	 *
	 * @return void
	 */
	public static function register_legacy_content_post_types(): void {
		$post_types = array(
			'thisismyurl_shadow_service' => array(
				'label'        => __( 'Services', 'thisismyurl-shadow' ),
				'singular'     => __( 'Service', 'thisismyurl-shadow' ),
				'archive_slug' => 'services',
				'rewrite_slug' => 'services',
				'hierarchical' => true,
			),
			'thisismyurl_shadow_training' => array(
				'label'        => __( 'Training Programs', 'thisismyurl-shadow' ),
				'singular'     => __( 'Training Program', 'thisismyurl-shadow' ),
				'archive_slug' => 'training',
				'rewrite_slug' => 'training',
				'hierarchical' => true,
			),
			'thisismyurl_shadow_portfolio' => array(
				'label'        => __( 'Portfolio', 'thisismyurl-shadow' ),
				'singular'     => __( 'Portfolio Item', 'thisismyurl-shadow' ),
				'archive_slug' => 'portfolio',
				'rewrite_slug' => 'portfolio',
				'hierarchical' => false,
			),
			'thisismyurl_shadow_case_study' => array(
				'label'        => __( 'Case Studies', 'thisismyurl-shadow' ),
				'singular'     => __( 'Case Study', 'thisismyurl-shadow' ),
				'archive_slug' => 'case-studies',
				'rewrite_slug' => 'case-studies',
				'hierarchical' => false,
			),
			'thisismyurl_shadow_feedback' => array(
				'label'        => __( 'Testimonials', 'thisismyurl-shadow' ),
				'singular'     => __( 'Testimonial', 'thisismyurl-shadow' ),
				'archive_slug' => 'testimonials',
				'rewrite_slug' => 'testimonials',
				'hierarchical' => false,
			),
			'thisismyurl_shadow_tool' => array(
				'label'        => __( 'Tools', 'thisismyurl-shadow' ),
				'singular'     => __( 'Tool', 'thisismyurl-shadow' ),
				'archive_slug' => 'tools',
				'rewrite_slug' => 'tools',
				'hierarchical' => false,
			),
		);

		foreach ( $post_types as $post_type => $config ) {
			if ( post_type_exists( $post_type ) ) {
				continue;
			}

			register_post_type(
				$post_type,
				array(
					'labels' => array(
						'name'          => $config['label'],
						'singular_name' => $config['singular'],
					),
					'public'              => true,
					'show_ui'             => true,
					'show_in_menu'        => true,
					'show_in_rest'        => true,
					'has_archive'         => $config['archive_slug'],
					'rewrite'             => array(
						'slug'       => $config['rewrite_slug'],
						'with_front' => false,
					),
					'publicly_queryable'  => true,
					'exclude_from_search' => false,
					'query_var'           => true,
					'hierarchical'        => (bool) $config['hierarchical'],
					'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author', 'page-attributes' ),
				)
			);
		}

		if ( ! taxonomy_exists( self::LOCATION_TAXONOMY ) ) {
			register_taxonomy(
				self::LOCATION_TAXONOMY,
				array( 'post', 'thisismyurl_shadow_service', 'thisismyurl_shadow_training', 'thisismyurl_shadow_portfolio', 'thisismyurl_shadow_case_study', 'thisismyurl_shadow_feedback', 'thisismyurl_shadow_tool', self::POST_TYPE ),
				array(
					'label'             => __( 'Locations', 'thisismyurl-shadow' ),
					'public'            => true,
					'hierarchical'      => true,
					'show_ui'           => true,
					'show_in_rest'      => true,
					'show_admin_column' => true,
					'rewrite'           => array(
						'slug'       => 'location',
						'with_front' => false,
					),
				)
			);
		}

		$location_types = array( 'thisismyurl_shadow_service', 'thisismyurl_shadow_training', 'thisismyurl_shadow_portfolio', 'thisismyurl_shadow_case_study', 'thisismyurl_shadow_feedback', 'thisismyurl_shadow_tool', self::POST_TYPE, 'post' );
		foreach ( $location_types as $location_type ) {
			if ( post_type_exists( $location_type ) ) {
				register_taxonomy_for_object_type( self::LOCATION_TAXONOMY, $location_type );
			}
		}

		// Keep the legacy taxonomy wired as an alias for theme/back-compat logic.
		if ( taxonomy_exists( self::LEGACY_LOCATION_TAXONOMY ) ) {
			foreach ( $location_types as $location_type ) {
				if ( post_type_exists( $location_type ) ) {
					register_taxonomy_for_object_type( self::LEGACY_LOCATION_TAXONOMY, $location_type );
				}
			}
		}
	}

	/**
	 * Migrate legacy post types and location taxonomy data to canonical names.
	 *
	 * @return void
	 */
	public static function maybe_migrate_legacy_content_types_and_taxonomies(): void {
		global $wpdb;

		$option_name = 'thisismyurl_shadow_content_type_migration_v2';
		if ( 'done' === (string) get_option( $option_name ) ) {
			return;
		}

		foreach ( self::LEGACY_POST_TYPE_MAP as $legacy_post_type => $canonical_post_type ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->posts} SET post_type = %s WHERE post_type = %s",
					$canonical_post_type,
					$legacy_post_type
				)
			);
		}

		$legacy_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_taxonomy_id, term_id, description, parent FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s",
				self::LEGACY_LOCATION_TAXONOMY
			)
		);

		if ( ! empty( $legacy_rows ) ) {
			foreach ( $legacy_rows as $legacy_row ) {
				$existing_new_tt_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s AND term_id = %d LIMIT 1",
						self::LOCATION_TAXONOMY,
						(int) $legacy_row->term_id
					)
				);

				if ( $existing_new_tt_id <= 0 ) {
					$wpdb->insert(
						$wpdb->term_taxonomy,
						array(
							'term_id'     => (int) $legacy_row->term_id,
							'taxonomy'    => self::LOCATION_TAXONOMY,
							'description' => (string) $legacy_row->description,
							'parent'      => (int) $legacy_row->parent,
							'count'       => 0,
						),
						array( '%d', '%s', '%s', '%d', '%d' )
					);
					$existing_new_tt_id = (int) $wpdb->insert_id;
				}

				if ( $existing_new_tt_id > 0 ) {
					$wpdb->query(
						$wpdb->prepare(
							"INSERT INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order)
							 SELECT tr.object_id, %d, tr.term_order
							 FROM {$wpdb->term_relationships} tr
							 WHERE tr.term_taxonomy_id = %d
							 AND NOT EXISTS (
								SELECT 1 FROM {$wpdb->term_relationships} tr2
								WHERE tr2.object_id = tr.object_id AND tr2.term_taxonomy_id = %d
							 )",
							$existing_new_tt_id,
							(int) $legacy_row->term_taxonomy_id,
							$existing_new_tt_id
						)
					);

					$count = (int) $wpdb->get_var(
						$wpdb->prepare(
							"SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d",
							$existing_new_tt_id
						)
					);
					$wpdb->update(
						$wpdb->term_taxonomy,
						array( 'count' => $count ),
						array( 'term_taxonomy_id' => $existing_new_tt_id ),
						array( '%d' ),
						array( '%d' )
					);
				}
			}
		}

		wp_cache_flush();
		update_option( $option_name, 'done', false );
	}

	/**
	 * Map legacy post type queries to canonical post types.
	 *
	 * @param \WP_Query $query Query object.
	 * @return void
	 */
	public static function map_legacy_post_type_queries( \WP_Query $query ): void {
		$post_type = $query->get( 'post_type' );

		if ( empty( $post_type ) ) {
			return;
		}

		if ( is_string( $post_type ) ) {
			if ( isset( self::LEGACY_POST_TYPE_MAP[ $post_type ] ) ) {
				$query->set( 'post_type', self::LEGACY_POST_TYPE_MAP[ $post_type ] );
			}
			return;
		}

		if ( is_array( $post_type ) ) {
			$mapped = array();
			foreach ( $post_type as $candidate_type ) {
				$candidate_type = (string) $candidate_type;
				if ( isset( self::LEGACY_POST_TYPE_MAP[ $candidate_type ] ) ) {
					$mapped[] = self::LEGACY_POST_TYPE_MAP[ $candidate_type ];
				} else {
					$mapped[] = $candidate_type;
				}
			}
			$query->set( 'post_type', array_values( array_unique( $mapped ) ) );
		}
	}

	/**
	 * Register event CPT.
	 *
	 * @return void
	 */
	public static function register_post_type(): void {
		$labels = array(
			'name'                  => __( 'Events', 'thisismyurl-shadow' ),
			'singular_name'         => __( 'Event', 'thisismyurl-shadow' ),
			'add_new'               => __( 'Add Event', 'thisismyurl-shadow' ),
			'add_new_item'          => __( 'Add New Event', 'thisismyurl-shadow' ),
			'edit_item'             => __( 'Edit Event', 'thisismyurl-shadow' ),
			'new_item'              => __( 'New Event', 'thisismyurl-shadow' ),
			'view_item'             => __( 'View Event', 'thisismyurl-shadow' ),
			'view_items'            => __( 'View Events', 'thisismyurl-shadow' ),
			'search_items'          => __( 'Search Events', 'thisismyurl-shadow' ),
			'not_found'             => __( 'No events found.', 'thisismyurl-shadow' ),
			'not_found_in_trash'    => __( 'No events found in Trash.', 'thisismyurl-shadow' ),
			'all_items'             => __( 'All Events', 'thisismyurl-shadow' ),
			'archives'              => __( 'Events', 'thisismyurl-shadow' ),
			'attributes'            => __( 'Event Attributes', 'thisismyurl-shadow' ),
			'insert_into_item'      => __( 'Insert into event', 'thisismyurl-shadow' ),
			'uploaded_to_this_item' => __( 'Uploaded to this event', 'thisismyurl-shadow' ),
			'menu_name'             => __( 'Events', 'thisismyurl-shadow' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => $labels,
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => true,
				'menu_position'       => 24,
				'menu_icon'           => 'dashicons-calendar-alt',
				'has_archive'         => 'events',
				'rewrite'             => array(
					'slug'       => 'events',
					'with_front' => false,
				),
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author' ),
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'query_var'           => true,
			)
		);
	}

	/**
	 * Register event meta in REST.
	 *
	 * @return void
	 */
	public static function register_meta(): void {
		$meta_fields = array(
			'_thisismyurl_event_start_datetime'      => 'string',
			'_thisismyurl_event_end_datetime'        => 'string',
			'_thisismyurl_event_type'                => 'string',
			'_thisismyurl_event_location'            => 'string',
			'_thisismyurl_event_delivery_mode'       => 'string',
			'_thisismyurl_event_capacity'            => 'integer',
			'_thisismyurl_event_status'              => 'string',
			'_thisismyurl_event_registration_mode'   => 'string',
			'_thisismyurl_event_registration_url'    => 'string',
			'_thisismyurl_event_price'               => 'string',
			'_thisismyurl_event_price_currency'      => 'string',
			'_thisismyurl_event_pricing_model'       => 'string',
			'_thisismyurl_event_price_min'           => 'number',
			'_thisismyurl_event_price_max'           => 'number',
			'_thisismyurl_event_timezone'            => 'string',
			'_thisismyurl_event_venue'               => 'string',
			'_thisismyurl_event_contact_email'       => 'string',
			'_thisismyurl_event_host_name'           => 'string',
			'_thisismyurl_event_host_url'            => 'string',
			'_thisismyurl_event_talk_format'         => 'string',
			'_thisismyurl_event_duration_minutes'    => 'integer',
			'_thisismyurl_event_recording_url'       => 'string',
			'_thisismyurl_event_slides_url'          => 'string',
			'_thisismyurl_training_program_id'       => 'integer',
			'_thisismyurl_legacy_event_id'           => 'integer',
		);

		foreach ( $meta_fields as $meta_key => $meta_type ) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				array(
					'single'            => true,
					'show_in_rest'      => true,
					'type'              => $meta_type,
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Add event details meta box.
	 *
	 * @return void
	 */
	public static function add_meta_boxes(): void {
		add_meta_box(
			'thisismyurl_shadow_event_details',
			__( 'Event Details', 'thisismyurl-shadow' ),
			array( __CLASS__, 'render_event_details_metabox' ),
			self::POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render admin metabox for event details.
	 *
	 * @param \WP_Post $post Event post.
	 * @return void
	 */
	public static function render_event_details_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'thisismyurl_shadow_event_details', 'thisismyurl_shadow_event_details_nonce' );

		$values = self::get_event_values( (int) $post->ID );
		$training_programs = get_posts(
			array(
				'post_type'      => 'training_program',
				'post_status'    => 'publish',
				'posts_per_page' => 300,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<p>
			<label for="thisismyurl_shadow_event_type"><strong><?php esc_html_e( 'Event Type', 'thisismyurl-shadow' ); ?></strong></label><br />
			<select id="thisismyurl_shadow_event_type" name="thisismyurl_shadow_event_type" style="width:100%;max-width:100%;">
				<?php foreach ( self::event_types() as $event_type => $label ) : ?>
					<option value="<?php echo esc_attr( $event_type ); ?>" <?php selected( $values['event_type'], $event_type ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="thisismyurl_shadow_event_start_datetime"><strong><?php esc_html_e( 'Start Date/Time', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="datetime-local" id="thisismyurl_shadow_event_start_datetime" name="thisismyurl_shadow_event_start_datetime" value="<?php echo esc_attr( $values['start_datetime_local'] ); ?>" style="width:100%;max-width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_end_datetime"><strong><?php esc_html_e( 'End Date/Time', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="datetime-local" id="thisismyurl_shadow_event_end_datetime" name="thisismyurl_shadow_event_end_datetime" value="<?php echo esc_attr( $values['end_datetime_local'] ); ?>" style="width:100%;max-width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_timezone"><strong><?php esc_html_e( 'Timezone', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="text" id="thisismyurl_shadow_event_timezone" name="thisismyurl_shadow_event_timezone" value="<?php echo esc_attr( $values['timezone'] ); ?>" placeholder="America/Toronto" style="width:100%;max-width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_venue"><strong><?php esc_html_e( 'Venue', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="text" id="thisismyurl_shadow_event_venue" name="thisismyurl_shadow_event_venue" value="<?php echo esc_attr( $values['venue'] ); ?>" style="width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_location"><strong><?php esc_html_e( 'Location Label', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="text" id="thisismyurl_shadow_event_location" name="thisismyurl_shadow_event_location" value="<?php echo esc_attr( $values['location'] ); ?>" placeholder="Niagara Falls, Ontario" style="width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_delivery_mode"><strong><?php esc_html_e( 'Delivery Mode', 'thisismyurl-shadow' ); ?></strong></label><br />
			<select id="thisismyurl_shadow_event_delivery_mode" name="thisismyurl_shadow_event_delivery_mode" style="width:100%;max-width:100%;">
				<?php foreach ( self::delivery_modes() as $mode => $label ) : ?>
					<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $values['delivery_mode'], $mode ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="thisismyurl_shadow_event_status"><strong><?php esc_html_e( 'Event Status', 'thisismyurl-shadow' ); ?></strong></label><br />
			<select id="thisismyurl_shadow_event_status" name="thisismyurl_shadow_event_status" style="width:100%;max-width:100%;">
				<?php foreach ( self::event_statuses() as $status => $label ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $values['event_status'], $status ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="thisismyurl_shadow_training_program_id"><strong><?php esc_html_e( 'Related Training Program', 'thisismyurl-shadow' ); ?></strong></label><br />
			<select id="thisismyurl_shadow_training_program_id" name="thisismyurl_shadow_training_program_id" style="width:100%;max-width:100%;">
				<option value="0"><?php esc_html_e( 'None', 'thisismyurl-shadow' ); ?></option>
				<?php foreach ( $training_programs as $program ) : ?>
					<option value="<?php echo esc_attr( (string) $program->ID ); ?>" <?php selected( $values['training_program_id'], (int) $program->ID ); ?>><?php echo esc_html( get_the_title( (int) $program->ID ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="thisismyurl_shadow_event_capacity"><strong><?php esc_html_e( 'Capacity', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="number" min="0" step="1" id="thisismyurl_shadow_event_capacity" name="thisismyurl_shadow_event_capacity" value="<?php echo esc_attr( (string) $values['capacity'] ); ?>" style="width:100%;max-width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_registration_mode"><strong><?php esc_html_e( 'Registration Mode', 'thisismyurl-shadow' ); ?></strong></label><br />
			<select id="thisismyurl_shadow_event_registration_mode" name="thisismyurl_shadow_event_registration_mode" style="width:100%;max-width:100%;">
				<?php foreach ( self::registration_modes() as $registration_mode => $label ) : ?>
					<option value="<?php echo esc_attr( $registration_mode ); ?>" <?php selected( $values['registration_mode'], $registration_mode ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="thisismyurl_shadow_event_price"><strong><?php esc_html_e( 'Price (CAD)', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="text" id="thisismyurl_shadow_event_price" name="thisismyurl_shadow_event_price" value="<?php echo esc_attr( $values['price'] ); ?>" placeholder="249" style="width:100%;max-width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_price_currency"><strong><?php esc_html_e( 'Price Currency', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="text" id="thisismyurl_shadow_event_price_currency" name="thisismyurl_shadow_event_price_currency" value="<?php echo esc_attr( $values['price_currency'] ); ?>" placeholder="CAD" maxlength="3" style="width:100%;max-width:100%;text-transform:uppercase;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_pricing_model"><strong><?php esc_html_e( 'Pricing Model', 'thisismyurl-shadow' ); ?></strong></label><br />
			<select id="thisismyurl_shadow_event_pricing_model" name="thisismyurl_shadow_event_pricing_model" style="width:100%;max-width:100%;">
				<?php foreach ( self::pricing_models() as $pricing_model => $label ) : ?>
					<option value="<?php echo esc_attr( $pricing_model ); ?>" <?php selected( $values['pricing_model'], $pricing_model ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="thisismyurl_shadow_event_price_min"><strong><?php esc_html_e( 'Price Minimum (optional)', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="number" min="0" step="0.01" id="thisismyurl_shadow_event_price_min" name="thisismyurl_shadow_event_price_min" value="<?php echo esc_attr( (string) $values['price_min'] ); ?>" style="width:100%;max-width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_price_max"><strong><?php esc_html_e( 'Price Maximum (optional)', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="number" min="0" step="0.01" id="thisismyurl_shadow_event_price_max" name="thisismyurl_shadow_event_price_max" value="<?php echo esc_attr( (string) $values['price_max'] ); ?>" style="width:100%;max-width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_registration_url"><strong><?php esc_html_e( 'Registration URL (Optional)', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="url" id="thisismyurl_shadow_event_registration_url" name="thisismyurl_shadow_event_registration_url" value="<?php echo esc_attr( $values['registration_url'] ); ?>" style="width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_contact_email"><strong><?php esc_html_e( 'Contact Email (Optional)', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="email" id="thisismyurl_shadow_event_contact_email" name="thisismyurl_shadow_event_contact_email" value="<?php echo esc_attr( $values['contact_email'] ); ?>" style="width:100%;max-width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_host_name"><strong><?php esc_html_e( 'Host / Organizer Name', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="text" id="thisismyurl_shadow_event_host_name" name="thisismyurl_shadow_event_host_name" value="<?php echo esc_attr( $values['host_name'] ); ?>" style="width:100%;max-width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_host_url"><strong><?php esc_html_e( 'Host / Organizer URL', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="url" id="thisismyurl_shadow_event_host_url" name="thisismyurl_shadow_event_host_url" value="<?php echo esc_attr( $values['host_url'] ); ?>" style="width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_talk_format"><strong><?php esc_html_e( 'Talk / Session Format', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="text" id="thisismyurl_shadow_event_talk_format" name="thisismyurl_shadow_event_talk_format" value="<?php echo esc_attr( $values['talk_format'] ); ?>" placeholder="Keynote, Workshop, Panel" style="width:100%;max-width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_duration_minutes"><strong><?php esc_html_e( 'Session Duration (minutes)', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="number" min="0" step="1" id="thisismyurl_shadow_event_duration_minutes" name="thisismyurl_shadow_event_duration_minutes" value="<?php echo esc_attr( (string) $values['duration_minutes'] ); ?>" style="width:100%;max-width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_recording_url"><strong><?php esc_html_e( 'Recording URL', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="url" id="thisismyurl_shadow_event_recording_url" name="thisismyurl_shadow_event_recording_url" value="<?php echo esc_attr( $values['recording_url'] ); ?>" style="width:100%;" />
		</p>
		<p>
			<label for="thisismyurl_shadow_event_slides_url"><strong><?php esc_html_e( 'Slides URL', 'thisismyurl-shadow' ); ?></strong></label><br />
			<input type="url" id="thisismyurl_shadow_event_slides_url" name="thisismyurl_shadow_event_slides_url" value="<?php echo esc_attr( $values['slides_url'] ); ?>" style="width:100%;" />
		</p>
		<?php
	}

	/**
	 * Save event details from metabox.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save_event_details( int $post_id ): void {
		if ( ! isset( $_POST['thisismyurl_shadow_event_details_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['thisismyurl_shadow_event_details_nonce'] ) ), 'thisismyurl_shadow_event_details' ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$start_datetime = self::sanitize_datetime_input( isset( $_POST['thisismyurl_shadow_event_start_datetime'] ) ? sanitize_text_field( wp_unslash( $_POST['thisismyurl_shadow_event_start_datetime'] ) ) : '' );
		$end_datetime   = self::sanitize_datetime_input( isset( $_POST['thisismyurl_shadow_event_end_datetime'] ) ? sanitize_text_field( wp_unslash( $_POST['thisismyurl_shadow_event_end_datetime'] ) ) : '' );
		$event_type     = isset( $_POST['thisismyurl_shadow_event_type'] ) ? sanitize_key( wp_unslash( $_POST['thisismyurl_shadow_event_type'] ) ) : 'training';
		$timezone       = isset( $_POST['thisismyurl_shadow_event_timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['thisismyurl_shadow_event_timezone'] ) ) : 'America/Toronto';
		$venue          = isset( $_POST['thisismyurl_shadow_event_venue'] ) ? sanitize_text_field( wp_unslash( $_POST['thisismyurl_shadow_event_venue'] ) ) : '';
		$location       = isset( $_POST['thisismyurl_shadow_event_location'] ) ? sanitize_text_field( wp_unslash( $_POST['thisismyurl_shadow_event_location'] ) ) : '';
		$delivery_mode  = isset( $_POST['thisismyurl_shadow_event_delivery_mode'] ) ? sanitize_key( wp_unslash( $_POST['thisismyurl_shadow_event_delivery_mode'] ) ) : 'onsite';
		$event_status   = isset( $_POST['thisismyurl_shadow_event_status'] ) ? sanitize_key( wp_unslash( $_POST['thisismyurl_shadow_event_status'] ) ) : 'scheduled';
		$registration_mode = isset( $_POST['thisismyurl_shadow_event_registration_mode'] ) ? sanitize_key( wp_unslash( $_POST['thisismyurl_shadow_event_registration_mode'] ) ) : 'internal_form';
		$program_id     = isset( $_POST['thisismyurl_shadow_training_program_id'] ) ? absint( wp_unslash( $_POST['thisismyurl_shadow_training_program_id'] ) ) : 0;
		$capacity       = isset( $_POST['thisismyurl_shadow_event_capacity'] ) ? absint( wp_unslash( $_POST['thisismyurl_shadow_event_capacity'] ) ) : 0;
		$price          = isset( $_POST['thisismyurl_shadow_event_price'] ) ? sanitize_text_field( wp_unslash( $_POST['thisismyurl_shadow_event_price'] ) ) : '';
		$price_currency = isset( $_POST['thisismyurl_shadow_event_price_currency'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['thisismyurl_shadow_event_price_currency'] ) ) ) : 'CAD';
		$pricing_model  = isset( $_POST['thisismyurl_shadow_event_pricing_model'] ) ? sanitize_key( wp_unslash( $_POST['thisismyurl_shadow_event_pricing_model'] ) ) : 'tiered';
		$price_min      = isset( $_POST['thisismyurl_shadow_event_price_min'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['thisismyurl_shadow_event_price_min'] ) ) : 0.0;
		$price_max      = isset( $_POST['thisismyurl_shadow_event_price_max'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['thisismyurl_shadow_event_price_max'] ) ) : 0.0;
		$registration   = isset( $_POST['thisismyurl_shadow_event_registration_url'] ) ? esc_url_raw( wp_unslash( $_POST['thisismyurl_shadow_event_registration_url'] ) ) : '';
		$contact_email  = isset( $_POST['thisismyurl_shadow_event_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['thisismyurl_shadow_event_contact_email'] ) ) : '';
		$host_name      = isset( $_POST['thisismyurl_shadow_event_host_name'] ) ? sanitize_text_field( wp_unslash( $_POST['thisismyurl_shadow_event_host_name'] ) ) : '';
		$host_url       = isset( $_POST['thisismyurl_shadow_event_host_url'] ) ? esc_url_raw( wp_unslash( $_POST['thisismyurl_shadow_event_host_url'] ) ) : '';
		$talk_format    = isset( $_POST['thisismyurl_shadow_event_talk_format'] ) ? sanitize_text_field( wp_unslash( $_POST['thisismyurl_shadow_event_talk_format'] ) ) : '';
		$duration_minutes = isset( $_POST['thisismyurl_shadow_event_duration_minutes'] ) ? absint( wp_unslash( $_POST['thisismyurl_shadow_event_duration_minutes'] ) ) : 0;
		$recording_url  = isset( $_POST['thisismyurl_shadow_event_recording_url'] ) ? esc_url_raw( wp_unslash( $_POST['thisismyurl_shadow_event_recording_url'] ) ) : '';
		$slides_url     = isset( $_POST['thisismyurl_shadow_event_slides_url'] ) ? esc_url_raw( wp_unslash( $_POST['thisismyurl_shadow_event_slides_url'] ) ) : '';

		if ( ! isset( self::delivery_modes()[ $delivery_mode ] ) ) {
			$delivery_mode = 'onsite';
		}

		if ( ! isset( self::event_statuses()[ $event_status ] ) ) {
			$event_status = 'scheduled';
		}

		if ( ! isset( self::event_types()[ $event_type ] ) ) {
			$event_type = 'training';
		}

		if ( ! isset( self::registration_modes()[ $registration_mode ] ) ) {
			$registration_mode = 'internal_form';
		}

		if ( ! isset( self::pricing_models()[ $pricing_model ] ) ) {
			$pricing_model = 'tiered';
		}

		if ( '' === $price_currency ) {
			$price_currency = 'CAD';
		}
		$price_currency = substr( $price_currency, 0, 3 );

		if ( $program_id > 0 && 'training_program' !== get_post_type( $program_id ) ) {
			$program_id = 0;
		}

		update_post_meta( $post_id, '_thisismyurl_event_start_datetime', $start_datetime );
		update_post_meta( $post_id, '_thisismyurl_event_end_datetime', $end_datetime );
		update_post_meta( $post_id, '_thisismyurl_event_type', $event_type );
		update_post_meta( $post_id, '_thisismyurl_event_timezone', $timezone );
		update_post_meta( $post_id, '_thisismyurl_event_venue', $venue );
		update_post_meta( $post_id, '_thisismyurl_event_location', $location );
		update_post_meta( $post_id, '_thisismyurl_event_delivery_mode', $delivery_mode );
		update_post_meta( $post_id, '_thisismyurl_event_status', $event_status );
		update_post_meta( $post_id, '_thisismyurl_event_registration_mode', $registration_mode );
		update_post_meta( $post_id, '_thisismyurl_training_program_id', $program_id );
		update_post_meta( $post_id, '_thisismyurl_event_capacity', $capacity );
		update_post_meta( $post_id, '_thisismyurl_event_price', $price );
		update_post_meta( $post_id, '_thisismyurl_event_price_currency', $price_currency );
		update_post_meta( $post_id, '_thisismyurl_event_pricing_model', $pricing_model );
		update_post_meta( $post_id, '_thisismyurl_event_price_min', $price_min );
		update_post_meta( $post_id, '_thisismyurl_event_price_max', $price_max );
		update_post_meta( $post_id, '_thisismyurl_event_registration_url', $registration );
		update_post_meta( $post_id, '_thisismyurl_event_contact_email', $contact_email );
		update_post_meta( $post_id, '_thisismyurl_event_host_name', $host_name );
		update_post_meta( $post_id, '_thisismyurl_event_host_url', $host_url );
		update_post_meta( $post_id, '_thisismyurl_event_talk_format', $talk_format );
		update_post_meta( $post_id, '_thisismyurl_event_duration_minutes', $duration_minutes );
		update_post_meta( $post_id, '_thisismyurl_event_recording_url', $recording_url );
		update_post_meta( $post_id, '_thisismyurl_event_slides_url', $slides_url );

		if ( 'speaking' === $event_type ) {
			update_post_meta( $post_id, '_event_kind', 'past_speaking' );
		} elseif ( 'past_speaking' === (string) get_post_meta( $post_id, '_event_kind', true ) ) {
			delete_post_meta( $post_id, '_event_kind' );
		}

		// Keep legacy keys in sync while theme transitions complete.
		update_post_meta( $post_id, '_training_event_start_datetime', $start_datetime );
		update_post_meta( $post_id, '_training_event_end_datetime', $end_datetime );
		update_post_meta( $post_id, '_training_event_location', $location );
		update_post_meta( $post_id, '_training_event_type', $event_status );
		update_post_meta( $post_id, '_training_event_training_program_id', $program_id );
		update_post_meta( $post_id, '_training_event_program_id', $program_id );
		update_post_meta( $post_id, '_training_event_course_id', $program_id );
	}

	/**
	 * Run one-time migration from legacy training_event posts.
	 *
	 * @return void
	 */
	public static function maybe_migrate_legacy_training_events(): void {
		$option_name = 'thisismyurl_shadow_training_event_migration_v1';
		if ( 'done' === (string) get_option( $option_name ) ) {
			return;
		}

		$legacy_posts = get_posts(
			array(
				'post_type'      => 'training_event',
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		if ( empty( $legacy_posts ) ) {
			update_option( $option_name, 'done', false );
			return;
		}

		foreach ( $legacy_posts as $legacy_post ) {
			if ( ! $legacy_post instanceof \WP_Post ) {
				continue;
			}

			$legacy_id   = (int) $legacy_post->ID;
			$legacy_slug = (string) $legacy_post->post_name;

			$existing_event_id = self::find_existing_event_for_legacy_id( $legacy_id, $legacy_slug );
			if ( $existing_event_id > 0 ) {
				update_post_meta( $existing_event_id, '_thisismyurl_legacy_event_id', $legacy_id );
				continue;
			}

			$new_event_id = wp_insert_post(
				array(
					'post_type'      => self::POST_TYPE,
					'post_status'    => $legacy_post->post_status,
					'post_title'     => $legacy_post->post_title,
					'post_name'      => $legacy_slug,
					'post_content'   => $legacy_post->post_content,
					'post_excerpt'   => $legacy_post->post_excerpt,
					'post_author'    => (int) $legacy_post->post_author,
					'post_date'      => $legacy_post->post_date,
					'post_date_gmt'  => $legacy_post->post_date_gmt,
					'post_modified'  => $legacy_post->post_modified,
					'post_modified_gmt' => $legacy_post->post_modified_gmt,
				),
				true
			);

			if ( is_wp_error( $new_event_id ) || $new_event_id <= 0 ) {
				continue;
			}

			self::copy_all_meta( $legacy_id, (int) $new_event_id );
			self::map_legacy_event_meta( $legacy_id, (int) $new_event_id );
			set_post_thumbnail( (int) $new_event_id, (int) get_post_thumbnail_id( $legacy_id ) );

			$location_terms = wp_get_post_terms( $legacy_id, 'location', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $location_terms ) && ! empty( $location_terms ) ) {
				wp_set_post_terms( (int) $new_event_id, array_map( 'intval', $location_terms ), 'location', false );
			}

			update_post_meta( (int) $new_event_id, '_thisismyurl_legacy_event_id', $legacy_id );
			update_post_meta( $legacy_id, '_thisismyurl_migrated_event_id', (int) $new_event_id );
		}

		update_option( $option_name, 'done', false );
	}

	/**
	 * Flush rewrites once for this CPT version.
	 *
	 * @return void
	 */
	public static function maybe_flush_rewrites(): void {
		$option_name = 'thisismyurl_shadow_event_rewrite_version';
		if ( self::VERSION === (string) get_option( $option_name ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( $option_name, self::VERSION, false );
	}

	/**
	 * Find existing new event by legacy ID or slug.
	 *
	 * @param int    $legacy_id Legacy post ID.
	 * @param string $legacy_slug Legacy slug.
	 * @return int
	 */
	private static function find_existing_event_for_legacy_id( int $legacy_id, string $legacy_slug ): int {
		$existing_by_meta = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_thisismyurl_legacy_event_id',
				'meta_value'     => $legacy_id,
			)
		);
		if ( ! empty( $existing_by_meta ) ) {
			return (int) $existing_by_meta[0];
		}

		$existing_by_slug = get_page_by_path( $legacy_slug, OBJECT, self::POST_TYPE );
		if ( $existing_by_slug instanceof \WP_Post ) {
			return (int) $existing_by_slug->ID;
		}

		return 0;
	}

	/**
	 * Copy all meta from old post to new post.
	 *
	 * @param int $source_id Source post ID.
	 * @param int $target_id Target post ID.
	 * @return void
	 */
	private static function copy_all_meta( int $source_id, int $target_id ): void {
		$all_meta = get_post_meta( $source_id );
		if ( empty( $all_meta ) ) {
			return;
		}

		foreach ( $all_meta as $key => $values ) {
			if ( ! is_array( $values ) ) {
				continue;
			}

			delete_post_meta( $target_id, (string) $key );
			foreach ( $values as $value ) {
				add_post_meta( $target_id, (string) $key, maybe_unserialize( $value ) );
			}
		}
	}

	/**
	 * Map legacy training event keys to new normalized keys.
	 *
	 * @param int $source_id Legacy event ID.
	 * @param int $target_id New event ID.
	 * @return void
	 */
	private static function map_legacy_event_meta( int $source_id, int $target_id ): void {
		$start = (string) get_post_meta( $source_id, '_training_event_start_datetime', true );
		$end   = (string) get_post_meta( $source_id, '_training_event_end_datetime', true );
		$loc   = (string) get_post_meta( $source_id, '_training_event_location', true );
		$type  = (string) get_post_meta( $source_id, '_training_event_type', true );

		$program_id = 0;
		$program_keys = array(
			'_training_event_training_program_id',
			'_training_event_program_id',
			'_training_event_course_id',
			'_training_program_id',
			'training_program_id',
			'program_id',
		);

		foreach ( $program_keys as $program_key ) {
			$candidate_id = (int) get_post_meta( $source_id, $program_key, true );
			if ( $candidate_id > 0 && in_array( get_post_type( $candidate_id ), array( 'thisismyurl_shadow_training', 'training_program' ), true ) ) {
				$program_id = $candidate_id;
				break;
			}
		}

		if ( '' !== $start ) {
			update_post_meta( $target_id, '_thisismyurl_event_start_datetime', $start );
		}
		if ( '' !== $end ) {
			update_post_meta( $target_id, '_thisismyurl_event_end_datetime', $end );
		}
		if ( '' !== $loc ) {
			update_post_meta( $target_id, '_thisismyurl_event_location', $loc );
		}

		$legacy_kind = (string) get_post_meta( $source_id, '_event_kind', true );
		if ( 'past_speaking' === $legacy_kind ) {
			update_post_meta( $target_id, '_thisismyurl_event_type', 'speaking' );
			update_post_meta( $target_id, '_thisismyurl_event_registration_mode', 'no_registration' );
		} else {
			update_post_meta( $target_id, '_thisismyurl_event_type', 'training' );
		}

		update_post_meta( $target_id, '_thisismyurl_event_status', '' !== $type ? $type : 'scheduled' );
		if ( $program_id > 0 ) {
			update_post_meta( $target_id, '_thisismyurl_training_program_id', $program_id );
			update_post_meta( $target_id, '_training_event_training_program_id', $program_id );
			update_post_meta( $target_id, '_training_event_program_id', $program_id );
			update_post_meta( $target_id, '_training_event_course_id', $program_id );
		}
	}

	/**
	 * Normalize datetime-local input to mysql datetime.
	 *
	 * @param string $value Raw datetime value.
	 * @return string
	 */
	private static function sanitize_datetime_input( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}

	/**
	 * Get event values for metabox rendering.
	 *
	 * @param int $post_id Event post ID.
	 * @return array<string, mixed>
	 */
	private static function get_event_values( int $post_id ): array {
		$start = (string) get_post_meta( $post_id, '_thisismyurl_event_start_datetime', true );
		if ( '' === $start ) {
			$start = (string) get_post_meta( $post_id, '_training_event_start_datetime', true );
		}

		$end = (string) get_post_meta( $post_id, '_thisismyurl_event_end_datetime', true );
		if ( '' === $end ) {
			$end = (string) get_post_meta( $post_id, '_training_event_end_datetime', true );
		}

		$program_id = (int) get_post_meta( $post_id, '_thisismyurl_training_program_id', true );
		if ( $program_id <= 0 ) {
			$program_id = (int) get_post_meta( $post_id, '_training_event_training_program_id', true );
		}

		$location = (string) get_post_meta( $post_id, '_thisismyurl_event_location', true );
		if ( '' === $location ) {
			$location = (string) get_post_meta( $post_id, '_training_event_location', true );
		}

		$event_type = (string) get_post_meta( $post_id, '_thisismyurl_event_type', true );
		if ( '' === $event_type ) {
			$legacy_kind = (string) get_post_meta( $post_id, '_event_kind', true );
			$event_type  = 'past_speaking' === $legacy_kind ? 'speaking' : 'training';
		}

		$registration_mode = (string) get_post_meta( $post_id, '_thisismyurl_event_registration_mode', true );
		if ( '' === $registration_mode ) {
			$registration_url = (string) get_post_meta( $post_id, '_thisismyurl_event_registration_url', true );
			$registration_mode = '' !== $registration_url ? 'external_url' : 'internal_form';
		}

		$price_currency = strtoupper( (string) get_post_meta( $post_id, '_thisismyurl_event_price_currency', true ) );
		if ( '' === $price_currency ) {
			$price_currency = 'CAD';
		}

		$pricing_model = (string) get_post_meta( $post_id, '_thisismyurl_event_pricing_model', true );
		if ( '' === $pricing_model ) {
			$pricing_model = 'tiered';
		}

		return array(
			'start_datetime_local' => self::convert_mysql_to_local_datetime( $start ),
			'end_datetime_local'   => self::convert_mysql_to_local_datetime( $end ),
			'event_type'           => $event_type,
			'timezone'             => (string) get_post_meta( $post_id, '_thisismyurl_event_timezone', true ),
			'venue'                => (string) get_post_meta( $post_id, '_thisismyurl_event_venue', true ),
			'location'             => $location,
			'delivery_mode'        => (string) get_post_meta( $post_id, '_thisismyurl_event_delivery_mode', true ),
			'event_status'         => (string) get_post_meta( $post_id, '_thisismyurl_event_status', true ),
			'registration_mode'    => $registration_mode,
			'training_program_id'  => $program_id,
			'capacity'             => (int) get_post_meta( $post_id, '_thisismyurl_event_capacity', true ),
			'price'                => (string) get_post_meta( $post_id, '_thisismyurl_event_price', true ),
			'price_currency'       => $price_currency,
			'pricing_model'        => $pricing_model,
			'price_min'            => (float) get_post_meta( $post_id, '_thisismyurl_event_price_min', true ),
			'price_max'            => (float) get_post_meta( $post_id, '_thisismyurl_event_price_max', true ),
			'registration_url'     => (string) get_post_meta( $post_id, '_thisismyurl_event_registration_url', true ),
			'contact_email'        => (string) get_post_meta( $post_id, '_thisismyurl_event_contact_email', true ),
			'host_name'            => (string) get_post_meta( $post_id, '_thisismyurl_event_host_name', true ),
			'host_url'             => (string) get_post_meta( $post_id, '_thisismyurl_event_host_url', true ),
			'talk_format'          => (string) get_post_meta( $post_id, '_thisismyurl_event_talk_format', true ),
			'duration_minutes'     => (int) get_post_meta( $post_id, '_thisismyurl_event_duration_minutes', true ),
			'recording_url'        => (string) get_post_meta( $post_id, '_thisismyurl_event_recording_url', true ),
			'slides_url'           => (string) get_post_meta( $post_id, '_thisismyurl_event_slides_url', true ),
		);
	}

	/**
	 * Convert mysql datetime into datetime-local format.
	 *
	 * @param string $value Mysql datetime.
	 * @return string
	 */
	private static function convert_mysql_to_local_datetime( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d\TH:i', $timestamp - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}

	/**
	 * Delivery mode labels.
	 *
	 * @return array<string, string>
	 */
	private static function delivery_modes(): array {
		return array(
			'onsite'  => __( 'Onsite', 'thisismyurl-shadow' ),
			'virtual' => __( 'Virtual', 'thisismyurl-shadow' ),
			'hybrid'  => __( 'Hybrid', 'thisismyurl-shadow' ),
			'either'  => __( 'Either', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Event status labels.
	 *
	 * @return array<string, string>
	 */
	private static function event_statuses(): array {
		return array(
			'scheduled' => __( 'Scheduled', 'thisismyurl-shadow' ),
			'waitlist'  => __( 'Waitlist', 'thisismyurl-shadow' ),
			'cancelled' => __( 'Cancelled', 'thisismyurl-shadow' ),
			'completed' => __( 'Completed', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Event type labels.
	 *
	 * @return array<string, string>
	 */
	private static function event_types(): array {
		return array(
			'training' => __( 'Training Day', 'thisismyurl-shadow' ),
			'speaking' => __( 'Speaking Engagement', 'thisismyurl-shadow' ),
			'webinar'  => __( 'Webinar', 'thisismyurl-shadow' ),
			'meetup'   => __( 'Meetup', 'thisismyurl-shadow' ),
			'workshop' => __( 'Workshop', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Registration mode labels.
	 *
	 * @return array<string, string>
	 */
	private static function registration_modes(): array {
		return array(
			'internal_form'   => __( 'On-site form', 'thisismyurl-shadow' ),
			'external_url'    => __( 'External website', 'thisismyurl-shadow' ),
			'no_registration' => __( 'No registration', 'thisismyurl-shadow' ),
		);
	}

	/**
	 * Pricing model labels.
	 *
	 * @return array<string, string>
	 */
	private static function pricing_models(): array {
		return array(
			'tiered' => __( 'Tiered training pricing', 'thisismyurl-shadow' ),
			'fixed'  => __( 'Fixed amount', 'thisismyurl-shadow' ),
			'range'  => __( 'Range', 'thisismyurl-shadow' ),
			'free'   => __( 'Free', 'thisismyurl-shadow' ),
			'quote'  => __( 'Quote required', 'thisismyurl-shadow' ),
		);
	}
}

