<?php
/**
 * Homepage Meta Diagnostic
 *
 * Checks whether the homepage has a unique meta title and description
 * configured in an SEO plugin rather than relying on WordPress site defaults.
 *
 * @package    Shadow by Christopher Ross
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Request_Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Homepage_Meta Class
 *
 * Reads homepage meta title and description from Yoast SEO or Rank Math
 * post-meta and flags missing fields on the front page.
 *
 * @since 0.6095
 */
class Diagnostic_Homepage_Meta extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'homepage-meta';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Homepage Meta';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the homepage has a unique meta title and description configured in an SEO plugin rather than relying on the WordPress site title defaults.';

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
	 * Detects the active SEO plugin (Yoast or Rank Math), reads meta title
	 * and description from the front-page post meta, and returns a high-severity
	 * finding that lists any fields that are empty.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when homepage meta is incomplete, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$has_yoast    = in_array( 'wordpress-seo/wp-seo.php', $active_plugins, true )
		             || in_array( 'wordpress-seo-premium/wp-seo-premium.php', $active_plugins, true );
		$has_rankmath = in_array( 'seo-by-rank-math/rank-math.php', $active_plugins, true )
		             || in_array( 'seo-by-rank-math-pro/rank-math-pro.php', $active_plugins, true );

		if ( ! $has_yoast && ! $has_rankmath ) {
			return self::check_rendered_homepage_meta();
		}

		$show_on_front  = get_option( 'show_on_front', 'posts' ); // 'posts' or 'page'
		$front_page_id  = (int) get_option( 'page_on_front', 0 );
		$missing_fields = array();

		if ( $has_yoast ) {
			if ( 'page' === $show_on_front && $front_page_id > 0 ) {
				$title = get_post_meta( $front_page_id, '_yoast_wpseo_title', true );
				$desc  = get_post_meta( $front_page_id, '_yoast_wpseo_metadesc', true );
				if ( empty( trim( $title ) ) ) {
					$missing_fields[] = 'meta title';
				}
				if ( empty( trim( $desc ) ) ) {
					$missing_fields[] = 'meta description';
				}
			} else {
				$titles = get_option( 'wpseo_titles', array() );
				if ( empty( $titles['title-home-wpseo'] ) ) {
					$missing_fields[] = 'meta title (home)';
				}
				if ( empty( $titles['metadesc-home-wpseo'] ) ) {
					$missing_fields[] = 'meta description (home)';
				}
			}
		} elseif ( $has_rankmath ) {
			if ( 'page' === $show_on_front && $front_page_id > 0 ) {
				$title = get_post_meta( $front_page_id, 'rank_math_title', true );
				$desc  = get_post_meta( $front_page_id, 'rank_math_description', true );
				if ( empty( trim( $title ) ) ) {
					$missing_fields[] = 'meta title';
				}
				if ( empty( trim( $desc ) ) ) {
					$missing_fields[] = 'meta description';
				}
			}
		}

		if ( empty( $missing_fields ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: list of missing meta fields */
				__( 'The homepage is missing the following SEO metadata: %s. The homepage is typically the highest-traffic page on your site and should have a carefully crafted meta title and description. Fill these in via your SEO plugin.', 'thisismyurl-shadow' ),
				implode( ', ', $missing_fields )
			),
			'severity'     => 'high',
			'threat_level' => 60,
			'details'      => array(
				'missing_fields' => $missing_fields,
				'show_on_front'  => $show_on_front,
				'front_page_id'  => $front_page_id,
				'explanation_sections' => array(
					'summary' => __( 'Shadow by Christopher Ross checked the homepage metadata configured through your SEO plugin and found required fields missing. The homepage is usually the highest-authority page on the site, so incomplete metadata weakens search result presentation where it matters most.', 'thisismyurl-shadow' ),
					'how_wp_shadow_tested' => __( 'Shadow by Christopher Ross detected the active SEO plugin, then inspected either the configured front-page post meta or the plugin’s home-page settings for a custom meta title and meta description. Empty fields are treated as missing because search engines will fall back to generic defaults or page text extraction.', 'thisismyurl-shadow' ),
					'why_it_matters' => __( 'Homepage metadata influences how your site appears in search results and helps define the core topic and value proposition of the site. Missing or generic metadata can reduce click-through rate and make brand messaging inconsistent across search surfaces.', 'thisismyurl-shadow' ),
					'how_to_fix_it' => __( 'Open your SEO plugin’s homepage settings and write a deliberate meta title and meta description for the front page. Aim for a concise brand/topic title and a description that explains the benefit of the site rather than repeating generic site text.', 'thisismyurl-shadow' ),
				),
			),
		);
	}

	/**
	 * Fallback homepage-meta inspection when no supported SEO plugin is active.
	 *
	 * @return array|null
	 */
	private static function check_rendered_homepage_meta() {
		$home_url  = home_url( '/' );
		$result    = Diagnostic_Request_Helper::get_result(
			$home_url,
			array(
				'timeout'    => 7,
				'user-agent' => 'Shadow by Christopher Ross-Diagnostic/1.0',
			)
		);

		if ( empty( $result['success'] ) || empty( $result['response'] ) || ! is_array( $result['response'] ) ) {
			return null;
		}

		$response         = $result['response'];
		$body             = (string) wp_remote_retrieve_body( $response );
		$title            = self::extract_title_tag( $body );
		$meta_description = self::extract_meta_description( $body );
		$site_name        = trim( (string) get_bloginfo( 'name' ) );
		$site_tagline     = trim( (string) get_bloginfo( 'description' ) );
		$missing_fields   = array();

		if ( '' === $title ) {
			$missing_fields[] = 'meta title';
		}

		if ( '' === $meta_description ) {
			$missing_fields[] = 'meta description';
		}

		if ( empty( $missing_fields ) && '' !== $site_name ) {
			$title_lc       = strtolower( wp_strip_all_tags( $title ) );
			$site_name_lc   = strtolower( $site_name );
			$site_tagline_lc = strtolower( $site_tagline );

			$generic_title = $title_lc === $site_name_lc;
			if ( ! $generic_title && '' !== $site_tagline_lc ) {
				$generic_patterns = array(
					$site_name_lc . ' | ' . $site_tagline_lc,
					$site_name_lc . ' - ' . $site_tagline_lc,
					$site_name_lc . ' – ' . $site_tagline_lc,
				);
				$generic_title = in_array( $title_lc, $generic_patterns, true );
			}

			if ( $generic_title ) {
				$missing_fields[] = 'custom homepage title';
			}
		}

		if ( empty( $missing_fields ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: list of missing meta fields */
				__( 'The rendered homepage is missing or relying on generic SEO metadata for: %s. Even without a dedicated SEO plugin, the homepage should output a deliberate title and meta description so search results show intentional messaging rather than inferred defaults.', 'thisismyurl-shadow' ),
				implode( ', ', $missing_fields )
			),
			'severity'     => in_array( 'meta description', $missing_fields, true ) ? 'high' : 'medium',
			'threat_level' => in_array( 'meta description', $missing_fields, true ) ? 60 : 40,
			'details'      => array(
				'missing_fields'     => $missing_fields,
				'checked_url'        => $home_url,
				'rendered_title'     => $title,
				'meta_description'   => $meta_description,
				'explanation_sections' => array(
					'summary' => __( 'Shadow by Christopher Ross fetched the rendered homepage and inspected the final HTML output instead of relying on plugin-specific settings. The page is missing key metadata or is still using generic title output that does not clearly define the homepage’s search snippet.', 'thisismyurl-shadow' ),
					'how_wp_shadow_tested' => __( 'Shadow by Christopher Ross requested the homepage HTML, extracted the title tag and meta description, and compared the title output against the site name and default name-plus-tagline patterns. Missing description tags or obviously generic title output are flagged as incomplete homepage metadata.', 'thisismyurl-shadow' ),
					'why_it_matters' => __( 'When the homepage metadata is missing, search engines generate snippets from whatever on-page text they can find, which can weaken messaging and lower click-through rate. Generic titles also make it harder to communicate brand positioning and topical relevance from the search results page.', 'thisismyurl-shadow' ),
					'how_to_fix_it' => __( 'Add a deliberate homepage title and meta description either through your theme, an SEO plugin, or a custom head output integration. The title should communicate the brand and primary topic, and the description should clearly explain what the site offers and who it serves.', 'thisismyurl-shadow' ),
				),
			),
		);
	}

	/**
	 * Extract the page title from rendered HTML.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private static function extract_title_tag( string $html ): string {
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
			return trim( wp_strip_all_tags( html_entity_decode( (string) $matches[1], ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' ) ) );
		}

		return '';
	}

	/**
	 * Extract the meta description from rendered HTML.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private static function extract_meta_description( string $html ): string {
		if ( preg_match( '/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\']/is', $html, $matches ) ) {
			return trim( wp_strip_all_tags( html_entity_decode( (string) $matches[1], ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' ) ) );
		}

		if ( preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\']/is', $html, $matches ) ) {
			return trim( wp_strip_all_tags( html_entity_decode( (string) $matches[1], ENT_QUOTES, get_bloginfo( 'charset' ) ?: 'UTF-8' ) ) );
		}

		return '';
	}
}
