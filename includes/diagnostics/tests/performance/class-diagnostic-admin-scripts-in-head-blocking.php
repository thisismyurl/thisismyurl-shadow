<?php
/**
 * Admin Scripts in Head Blocking Diagnostic
 *
 * Extracts the <head> section from the captured admin page HTML and counts
 * synchronous <script src> tags that lack both defer and async attributes.
 * Scripts in <head> without defer/async block HTML parsing until the full
 * script file has been downloaded, parsed, and executed; footer scripts are
 * expected and are therefore excluded from this check.
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
 * Diagnostic_Admin_Scripts_In_Head_Blocking Class
 *
 * @since 0.6095
 */
class Diagnostic_Admin_Scripts_In_Head_Blocking extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'admin-scripts-in-head-blocking';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Blocking Scripts in Admin <head>';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Counts synchronous external scripts in the HTML <head> that lack defer or async attributes. Each such script halts the browser\'s HTML parser until the file is fully fetched and executed, directly delaying time-to-interactive for admin pages. WordPress core itself enqueues very few scripts in <head>; a high count indicates plugins overriding the in_footer argument.';

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
	 * Blocking scripts in <head> above which a medium finding is raised.
	 *
	 * WordPress core places 1–2 scripts in <head> by default (e.g., hoverintent).
	 * Anything above 5 indicates plugins forcing scripts into <head> without defer.
	 *
	 * @var int
	 */
	private const THRESHOLD_MEDIUM = 5;

	/**
	 * Blocking scripts in <head> above which a high finding is raised.
	 *
	 * @var int
	 */
	private const THRESHOLD_HIGH = 10;

	/**
	 * Run the diagnostic check.
	 *
	 * Extracts only the <head>...</head> portion of the admin HTML and passes
	 * it to Admin_HTML::count_blocking_scripts() which looks for <script src>
	 * tags lacking both `defer` and `async`. Footer scripts are intentionally
	 * excluded because browsers do not block rendering for scripts loaded after
	 * the closing </body> tag.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when blocking head scripts are excessive, null when healthy.
	 */
	public static function check(): ?array {
		$html = Admin_HTML::get_html();

		if ( null === $html ) {
			return null; // HTML not yet captured; skip gracefully.
		}

		// Extract only the <head> section so footer scripts are not counted.
		$head_html = self::extract_head( $html );

		if ( null === $head_html ) {
			return null;
		}

		$blocking_count = Admin_HTML::count_blocking_scripts( $head_html );

		if ( $blocking_count <= self::THRESHOLD_MEDIUM ) {
			return null;
		}

		if ( $blocking_count > self::THRESHOLD_HIGH ) {
			$severity     = 'high';
			$threat_level = 55;
		} else {
			$severity     = 'medium';
			$threat_level = 38;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %d: count of blocking scripts found in <head> */
				__(
					'%d synchronous <script src> tags without defer or async were found in the admin page <head>. Each one halts the browser\'s HTML parser until the external script file is fully downloaded, parsed, and executed. This directly increases time-to-interactive for the admin interface. WordPress core intentionally places most scripts in the footer; plugins that force scripts into <head> with in_footer = false and no async/defer attribute override this best practice.',
					'thisismyurl-shadow'
				),
				$blocking_count
			),
			'severity'     => $severity,
			'threat_level' => $threat_level,
			'details'      => array(
				'blocking_head_scripts' => $blocking_count,
				'threshold_medium'      => self::THRESHOLD_MEDIUM,
				'threshold_high'        => self::THRESHOLD_HIGH,
				'note'                  => __(
					'Use a browser devtools Network panel (or the WP Rocket or Asset CleanUp plugin) to identify which plugins are registering "in_head" scripts. Ask plugin authors to add the defer or async strategy to their wp_enqueue_script() call. In WP 6.3+, scripts registered with a \'strategy\' of \'defer\' or \'async\' honour this across all enqueue calls automatically.',
					'thisismyurl-shadow'
				),
			),
		);
	}

	/**
	 * Extract the raw HTML content of the <head>…</head> section.
	 *
	 * @since  0.6095
	 * @param  string $html Full admin page HTML.
	 * @return string|null The <head> content, or null if the pattern is not found.
	 */
	private static function extract_head( string $html ): ?string {
		if ( preg_match( '/<head[\s>].*?<\/head>/is', $html, $matches ) ) {
			return $matches[0];
		}

		return null;
	}
}
