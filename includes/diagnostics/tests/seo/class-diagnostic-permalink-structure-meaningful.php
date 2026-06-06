<?php
/**
 * Permalink Structure Meaningful Diagnostic
 *
 * Checks whether the WordPress permalink structure is set to a human-readable
 * format rather than the default numeric query string that harms SEO.
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
 * Diagnostic_Permalink_Structure_Meaningful Class
 *
 * Reads the permalink_structure option and flags plain (query-string) or
 * numeric-ID slugs that offer no keyword context in the URL.
 *
 * @since 0.6095
 */
class Diagnostic_Permalink_Structure_Meaningful extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'permalink-structure-meaningful';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Permalink Structure Meaningful';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress permalink structure is set to a human-readable format rather than the default numeric query string that harms SEO.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'seo';

	/**
	 * Whether this diagnostic is part of the core trusted set.
	 *
	 * @var bool
	 */
	protected static $is_core = true;

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'high';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the permalink_structure WordPress option and returns a high-severity
	 * finding for plain (empty) structures or a medium-severity finding for
	 * numeric-ID structures. Returns null when a meaningful structure is in use.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when structure is suboptimal, null when healthy.
	 */
	public static function check() {
		$permalink_structure = get_option( 'permalink_structure', '' );

		// Plain permalinks (/?p=123).
		if ( '' === $permalink_structure ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'WordPress is using plain (query-string) permalinks such as /?p=123. These URLs contain no keywords and are difficult for users and search engines to interpret. Change the permalink structure under Settings → Permalinks to something more descriptive, such as "Post name" (/%postname%/).', 'thisismyurl-shadow' ),
				'severity'     => 'high',
				'threat_level' => 70,
				'details'      => array(
					'permalink_structure' => '',
					'format'              => 'plain',
				),
			);
		}

		// Numeric-only structure (e.g. /archives/123).
		if ( preg_match( '/^[^%]*%p%[^%]*$|\/archives\//i', $permalink_structure ) ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'The permalink structure uses a numeric post ID, which provides no keyword context to search engines or users. Switch to a structure that includes the post name, such as /%postname%/, for better SEO and readability.', 'thisismyurl-shadow' ),
				'severity'     => 'medium',
				'threat_level' => 50,
				'details'      => array(
					'permalink_structure' => $permalink_structure,
					'format'              => 'numeric',
				),
			);
		}

		return null;
	}
}
