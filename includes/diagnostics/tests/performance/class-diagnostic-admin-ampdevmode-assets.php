<?php
/**
 * Admin AMP Dev-Mode Assets Diagnostic
 *
 * Scans the captured admin page HTML for the data-ampdevmode attribute.
 * This attribute is added by the AMP plugin (and Jetpack when AMP
 * integration is active) to mark assets injected specifically for AMP
 * validation. Its presence on a standard (non-AMP) admin page indicates
 * that the AMP validation runtime is being loaded unnecessarily, adding
 * HTTP requests, JS parse overhead, and potential compatibility issues.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_Admin_Page_HTML_Helper as Admin_HTML;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Admin_Ampdevmode_Assets Class
 *
 * @since 0.6095
 */
class Diagnostic_Admin_Ampdevmode_Assets extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'admin-ampdevmode-assets';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'AMP Dev-Mode Assets on Non-AMP Admin Page';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Detects data-ampdevmode attributes on scripts or styles present in the admin HTML. These markers are introduced by the AMP plugin (or Jetpack AMP integration) to support AMP validation tooling. Their presence on a standard admin page means the AMP runtime is active where it should not be, adding unnecessary asset requests and JavaScript processing overhead.';

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
	 * Searches the captured admin page HTML for any occurrence of the
	 * data-ampdevmode attribute. Even a single occurrence is considered
	 * a finding because this attribute has no legitimate purpose on
	 * non-AMP, non-AMP-Editor admin pages.
	 *
	 * Observed in the wild: Jetpack with the AMP module active injects
	 *   <script data-ampdevmode src="…/amp-helper-functions.js"></script>
	 *   <style data-ampdevmode>…</style>
	 * into every admin page, including non-AMP pages.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when data-ampdevmode assets are detected, null when clean.
	 */
	public static function check(): ?array {
		$html = Admin_HTML::get_html();

		if ( null === $html ) {
			return null; // HTML not yet captured; skip gracefully.
		}

		$found = preg_match_all( '/\bdata-ampdevmode\b/i', $html, $matches );

		if ( ! $found || $found < 1 ) {
			return null;
		}

		$occurrence_count = (int) $found;

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: number of data-ampdevmode occurrences in the HTML */
				__(
					'The data-ampdevmode attribute was found %d time(s) in the admin page HTML. This attribute is only meaningful on AMP-validated front-end pages; its presence in the WordPress admin indicates that the AMP validation runtime, or a plugin integrating with it (commonly Jetpack\'s AMP module), is injecting assets unnecessarily. These assets add extra HTTP requests, JavaScript parse overhead, and potential conflicts with standard admin styles.',
					'thisismyurl-shadow'
				),
				$occurrence_count
			),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'ampdevmode_occurrences' => $occurrence_count,
				'likely_cause'           => __(
					'Jetpack with the AMP module, or the standalone AMP plugin in Development Mode. Verify via Jetpack → Settings → Traffic → AMP or AMP → Settings → Developer Tools.',
					'thisismyurl-shadow'
				),
				'resolution'             => __(
					'If AMP is not actively used for this site, disable the AMP module in Jetpack (Settings → Traffic → AMP) or disable the AMP plugin entirely. If AMP is used only for the front end, ensure the plugin is not hooking into admin_print_scripts for validation assets outside of AMP page contexts.',
					'thisismyurl-shadow'
				),
			),
		);
	}
}
