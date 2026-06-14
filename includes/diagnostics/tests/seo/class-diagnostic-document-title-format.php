<?php
/**
 * Document Title Format Diagnostic
 *
 * Checks whether the active theme declares title-tag support, which allows
 * WordPress and SEO plugins to fully manage the page title element.
 *
 * @package    Shadow by Christopher Ross
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
 * Diagnostic_Document_Title_Format Class
 *
 * Calls current_theme_supports('title-tag') to verify the theme yields
 * title-element control to WordPress, flagging themes that manage it themselves.
 *
 * @since 0.6095
 */
class Diagnostic_Document_Title_Format extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'document-title-format';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Document Title Format';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the active theme declares title-tag support, which allows WordPress and SEO plugins to fully manage the page <title> element.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'seo';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Verifies that the active theme declares add_theme_support('title-tag'),
	 * returning a medium-severity finding when the theme manages its own
	 * title element and may conflict with SEO plugin overrides.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when title-tag support is missing, null when healthy.
	 */
	public static function check() {
		// Themes that declare add_theme_support('title-tag') allow WordPress (and SEO plugins)
		// to control the <title> element fully. Without this, themes output their own title,
		// which may conflict with SEO plugin overrides.
		if ( ! current_theme_supports( 'title-tag' ) ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => __( 'The active theme does not declare support for the WordPress title-tag feature. This means the theme controls the <title> element directly, which can conflict with SEO plugin title templates and prevent proper meta title management. Use a theme that calls add_theme_support(\'title-tag\') in its functions.php.', 'thisismyurl-shadow' ),
				'severity'     => 'medium',
				'threat_level' => 40,
				'details'      => array(
					'title_tag_theme_support' => false,
					'active_theme'            => get_template(),
				),
			);
		}

		return null;
	}
}
