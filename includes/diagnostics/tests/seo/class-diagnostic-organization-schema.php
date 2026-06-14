<?php
/**
 * Organization Schema Diagnostic
 *
 * Checks whether Organization or Person structured data schema is being output
 * with the minimum required fields to help search engines understand the site's identity.
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
 * Diagnostic_Organization_Schema Class
 *
 * Inspects Yoast SEO and Rank Math knowledge-graph settings for the required
 * organization name and logo fields, reporting any that are missing.
 *
 * @since 0.6095
 */
class Diagnostic_Organization_Schema extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'organization-schema';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Organization Schema';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether Organization or Person structured data schema is being output to help search engines understand and display the site\'s identity.';

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
	 * Reads Yoast SEO or Rank Math knowledge-graph options and collects any
	 * missing required fields (organization name, logo). Returns null when no
	 * recognised SEO plugin is found or when all required fields are present.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when fields are missing, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$has_yoast    = in_array( 'wordpress-seo/wp-seo.php', $active_plugins, true )
		             || in_array( 'wordpress-seo-premium/wp-seo-premium.php', $active_plugins, true );
		$has_rankmath = in_array( 'seo-by-rank-math/rank-math.php', $active_plugins, true )
		             || in_array( 'seo-by-rank-math-pro/rank-math-pro.php', $active_plugins, true );

		$missing_fields = array();

		if ( $has_yoast ) {
			$wpseo = get_option( 'wpseo', array() );
			$type  = isset( $wpseo['company_or_person'] ) ? $wpseo['company_or_person'] : '';

			if ( 'company' === $type || '' === $type ) {
				if ( empty( $wpseo['company_name'] ) ) {
					$missing_fields[] = 'organization name';
				}
				if ( empty( $wpseo['company_logo'] ) ) {
					$missing_fields[] = 'organization logo';
				}
			}
		} elseif ( $has_rankmath ) {
			$general = get_option( 'rank_math_settings_general', array() );
			if ( empty( $general['knowledgegraph_name'] ) ) {
				$missing_fields[] = 'organization name';
			}
			if ( empty( $general['knowledgegraph_logo'] ) ) {
				$missing_fields[] = 'organization logo';
			}
		} else {
			// No SEO plugin — can't determine schema output.
			return null;
		}

		if ( empty( $missing_fields ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: comma-separated list of missing fields */
				__( 'Organization schema is incomplete. The following fields are missing: %s. Complete these in your SEO plugin\'s knowledge graph / organization settings so search engines can accurately associate your brand with this site.', 'thisismyurl-shadow' ),
				implode( ', ', $missing_fields )
			),
			'severity'     => 'medium',
			'threat_level' => 30,
			'details'      => array(
				'missing_fields' => $missing_fields,
			),
		);
	}
}
