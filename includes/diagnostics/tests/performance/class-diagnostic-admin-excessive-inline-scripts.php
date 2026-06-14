<?php
/**
 * Admin Excessive Inline Scripts Diagnostic
 *
 * Scans the captured admin page HTML and counts inline <script> blocks
 * (blocks with no external src attribute). A high number of inline scripts
 * indicates plugins are bypassing WordPress's asset management system,
 * preventing caching, and fragmenting the HTML parser's work.
 *
 * @package    Shadow by Christopher Ross
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
 * Diagnostic_Admin_Excessive_Inline_Scripts Class
 *
 * @since 0.6095
 */
class Diagnostic_Admin_Excessive_Inline_Scripts extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'admin-excessive-inline-scripts';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Excessive Inline Scripts in Admin';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Counts inline <script> blocks (no external src) on admin pages. Excessive inline scripts indicate plugins bypassing WordPress asset management, which prevents HTTP caching and increases raw HTML payload size.';

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
	 * Number of inline scripts above which a medium finding is raised.
	 *
	 * WordPress core itself outputs ~4–6 inline script blocks per admin page
	 * (userSettings, ajaxurl global, heartbeat, etc.). A count above this
	 * threshold indicates significant plugin-injected inline code.
	 *
	 * @var int
	 */
	private const THRESHOLD_MEDIUM = 20;

	/**
	 * Number of inline scripts above which a high finding is raised.
	 *
	 * @var int
	 */
	private const THRESHOLD_HIGH = 35;

	/**
	 * Run the diagnostic check.
	 *
	 * Counts standalone inline <script> blocks in the admin page HTML and flags
	 * pages with an unusually large number. Each inline script block:
	 *   - Cannot be cached by the browser or a CDN.
	 *   - Contributes to raw HTML page weight.
	 *   - Can trigger Content-Security-Policy violations on strict CSP setups.
	 *   - Requires the HTML parser to fully evaluate JS before continuing.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when inline scripts are excessive, null when healthy.
	 */
	public static function check(): ?array {
		$html = Admin_HTML::get_html();

		if ( null === $html ) {
			return null; // HTML not yet captured; skip gracefully.
		}

		$inline_count = Admin_HTML::count_inline_scripts( $html );

		if ( $inline_count < self::THRESHOLD_MEDIUM ) {
			return null;
		}

		if ( $inline_count >= self::THRESHOLD_HIGH ) {
			$severity     = 'high';
			$threat_level = 55;
		} else {
			$severity     = 'medium';
			$threat_level = 35;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: count of inline script blocks */
				__(
					'%d inline <script> blocks were detected on the admin page. Inline scripts cannot be cached by the browser or a CDN, increasing raw HTML payload on every page load. A high count typically indicates several plugins injecting per-page JavaScript data, tracking pixels, or initialisation code outside the standard WordPress enqueue system. Inline scripts also block the HTML parser until they are fully evaluated.',
					'thisismyurl-shadow'
				),
				$inline_count
			),
			'severity'     => $severity,
			'threat_level' => $threat_level,
			'details'      => array(
				'inline_script_count'  => $inline_count,
				'threshold_medium'     => self::THRESHOLD_MEDIUM,
				'threshold_high'       => self::THRESHOLD_HIGH,
				'note'                 => __(
					'Review active plugins for those injecting large inline JSON blobs or initialisation scripts directly into admin pages. Look for wp_localize_script() calls with oversized data payloads (Rank Math\'s JSON object and Jetpack\'s script data are common contributors). Where feasible, request that plugin authors move data to REST API endpoints fetched on demand.',
					'thisismyurl-shadow'
				),
			),
		);
	}
}
