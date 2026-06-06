<?php
/**
 * Adjacent Posts Rel Links Diagnostic
 *
 * Checks whether WordPress is still injecting <link rel="prev"> and
 * <link rel="next"> tags into individual post pages. Google officially
 * deprecated support for these tags in 2019.
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
 * Diagnostic_Adjacent_Posts_Links Class
 *
 * @since 0.6095
 */
class Diagnostic_Adjacent_Posts_Links extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'adjacent-posts-links';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Adjacent Posts Rel Links in Head';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is injecting <link rel="prev"> and <link rel="next"> tags into post pages. Google deprecated these tags in 2019 and they no longer influence search rankings or crawling.';

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
	 * Checks whether adjacent_posts_rel_link_wp_head is still hooked to
	 * wp_head at its default priority of 10. These tags were included in
	 * WordPress core before Google's 2019 deprecation announcement.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when links are still output, null when healthy.
	 */
	public static function check() {
		// Perfmatters can remove these under head cleanup options.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['extras']['disable_adjacent_posts_rel_link'] ) ) {
			return null;
		}

		// WP Asset CleanUp handles this.
		if ( false !== get_option( 'wpacu_settings', false ) ) {
			return null;
		}

		// Definitive check: if the hook has been removed, tags are not output.
		if ( ! has_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress outputs <link rel="prev"> and <link rel="next"> tags on individual post pages to point to adjacent posts. Google officially deprecated these tags in March 2019 and stated they have no effect on crawling, indexing, or rankings. They now add bytes to every single post page with no benefit. They can be safely removed without any SEO impact.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 8,
			'details'      => array(
				'fix'       => __( 'Add to functions.php: remove_action(\'wp_head\', \'adjacent_posts_rel_link_wp_head\', 10); — or use Perfmatters / WP Asset CleanUp.', 'thisismyurl-shadow' ),
				'reference' => 'https://developers.google.com/search/blog/2019/03/two-new-changes-to-mobile-friendly-test',
			),
		);
	}
}
