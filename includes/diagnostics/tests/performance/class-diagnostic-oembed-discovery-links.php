<?php
/**
 * oEmbed Discovery Links Diagnostic
 *
 * Checks whether WordPress is still injecting oEmbed discovery <link> tags
 * into every page's <head>. These tags advertise that your content can be
 * embedded by others — distinct from the wp-embed.js script that handles
 * embedding third-party content on your own site.
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
 * Diagnostic_Oembed_Discovery_Links Class
 *
 * @since 0.6095
 */
class Diagnostic_Oembed_Discovery_Links extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'oembed-discovery-links';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'oEmbed Discovery Links in Head';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether WordPress is injecting oEmbed discovery link tags into every page that advertise your content as embeddable — unnecessary overhead for sites that do not need their content embedded elsewhere.';

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
	 * Note: The "Embed Assets" diagnostic checks for wp-embed.js, which handles
	 * embedding third-party content on your site. This diagnostic targets the
	 * separate wp_oembed_add_discovery_links hook, which outputs <link> tags
	 * advertising that YOUR content can be embedded by other sites.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when discovery links are still output, null when healthy.
	 */
	public static function check() {
		// Perfmatters — disabling embeds also removes the oembed discovery links.
		$pm = get_option( 'perfmatters_options', array() );
		if ( is_array( $pm ) && ! empty( $pm['extras']['disable_embeds'] ) ) {
			return null;
		}

		// WP Rocket — disabling embeds covers the discovery links too.
		$rocket = get_option( 'wp_rocket_settings', array() );
		if ( is_array( $rocket ) && ! empty( $rocket['embeds'] ) ) {
			return null;
		}

		// WP Asset CleanUp handles this.
		if ( false !== get_option( 'wpacu_settings', false ) ) {
			return null;
		}

		// Definitive check: if the hook has been removed explicitly.
		if ( ! has_action( 'wp_head', 'wp_oembed_add_discovery_links' ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress outputs <link rel="alternate" type="application/json+oembed"> discovery tags in every page\'s <head>. These tags tell other sites that they can embed your content via oEmbed. Most small business sites do not publish content intended to be embedded by third parties, making these tags unnecessary overhead. Removing them does not break anything for your visitors.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 5,
			'details'      => array(
				'fix' => __( 'Add to functions.php: remove_action(\'wp_head\', \'wp_oembed_add_discovery_links\'); — or use Perfmatters / WP Rocket\'s "Disable Embeds" option.', 'thisismyurl-shadow' ),
			),
		);
	}
}
